<?php
/**
 * Shared Smart Validation Service
 *
 * Reuses the exact business flow from admin "Validate" button logic,
 * so browser/manual and mobile use identical validation behavior.
 *
 * @package MembershipValidator
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Membership_Smart_Validation_Service {

    private OC_Membership_DB $validator_db;

    public function __construct(OC_Membership_DB $validator_db) {
        $this->validator_db = $validator_db;
    }

    /**
     * Preview without consuming sessions.
     *
     * @return array{
     *   valid: bool,
     *   code: string,
     *   message: string,
     *   membership?: array<string,mixed>,
     *   has_membership: bool
     * }
     */
    public function preview_validation(int $user_id): array {
        $settings = $this->get_validation_timing_settings();
        if ($this->should_enforce_daily_lock($settings) && $this->has_user_daily_validation_lock($user_id)) {
            return [
                'valid' => false,
                'code' => 'ALREADY_VALIDATED_TODAY',
                'message' => 'Abonamentul a fost deja validat astazi.',
                'has_membership' => true
            ];
        }

        $all_courses = $this->get_all_user_active_courses($user_id);
        if (empty($all_courses)) {
            return [
                'valid' => false,
                'code' => 'NO_ACTIVE_COURSES',
                'message' => 'Utilizatorul nu are cursuri active.',
                'has_membership' => false
            ];
        }

        $running_courses = $this->find_all_running_courses_today($all_courses);
        if (empty($running_courses)) {
            return [
                'valid' => false,
                'code' => 'NO_COURSE_TODAY',
                'message' => 'Nu există cursuri programate azi.',
                'membership' => $all_courses[0],
                'has_membership' => true
            ];
        }

        foreach ($running_courses as $course) {
            $is_unlimited = (int) ($course['is_unlimited'] ?? 0) === 1;
            if (!$is_unlimited && (int) $course['remaining_sessions'] <= 0) {
                continue;
            }

            if ($this->is_validated_today((string) ($course['last_validation_date'] ?? ''))) {
                continue;
            }

            return [
                'valid' => true,
                'code' => 'OK',
                'message' => 'Abonament activ si valabil.',
                'membership' => $course,
                'has_membership' => true
            ];
        }

        return [
            'valid' => false,
            'code' => 'ALREADY_VALIDATED_TODAY',
            'message' => 'Abonamentul a fost deja validat astazi.',
            'membership' => $running_courses[0],
            'has_membership' => true
        ];
    }

    /**
     * Exact logic used by browser "Validate" button.
     *
     * @return array{
     *   success: bool,
     *   code: string,
     *   message: string,
     *   validated_count?: int,
     *   validated_courses?: array<int,string>,
     *   skipped_courses?: array<int,string>,
     *   validation_time?: string,
     *   current_time?: string,
     *   current_day?: string,
     *   first_membership?: array<string,mixed>|null
     * }
     */
    public function validate_and_consume(int $user_id): array {
        global $wpdb;
        $table_name = $this->validator_db->get_table_name('membership_validations');
        $settings = $this->get_validation_timing_settings();

        if ($this->should_enforce_daily_lock($settings) && $this->has_user_daily_validation_lock($user_id)) {
            return [
                'success' => false,
                'code' => 'ALREADY_VALIDATED_TODAY',
                'message' => 'Abonamentul a fost deja validat astazi.'
            ];
        }

        $all_courses = $this->get_all_user_active_courses($user_id);
        if (empty($all_courses)) {
            return [
                'success' => false,
                'code' => 'NO_ACTIVE_COURSES',
                'message' => 'Utilizatorul nu are cursuri active.'
            ];
        }

        $running_courses = $this->find_all_running_courses_today($all_courses);
        if (empty($running_courses)) {
            return [
                'success' => false,
                'code' => 'NO_COURSE_TODAY',
                'message' => 'Nu există cursuri programate azi.',
                'current_time' => $this->get_current_local_datetime()->format('H:i'),
                'current_day' => $this->get_current_local_datetime()->format('l')
            ];
        }

        $validated_courses = [];
        $skipped_courses = [];
        $first_membership_id = 0;
        $processed_membership_ids = [];

        foreach ($running_courses as $course) {
            $membership_id = (int) ($course['id'] ?? 0);
            if ($membership_id > 0 && isset($processed_membership_ids[$membership_id])) {
                // Safety net: never consume same membership twice in one validation pass.
                continue;
            }

            $is_unlimited = (int) ($course['is_unlimited'] ?? 0) === 1;

            if (!$is_unlimited && (int) $course['remaining_sessions'] <= 0) {
                $skipped_courses[] = $course['course_name'] . ' (fără ședințe)';
                continue;
            }

            if ($this->is_validated_today((string) ($course['last_validation_date'] ?? ''))) {
                $skipped_courses[] = $course['course_name'] . ' (deja validat azi)';
                continue;
            }

            // Intentionally same behavior as existing browser AJAX logic.
            $update_data = [
                'used_sessions' => ((int) $course['used_sessions']) + 1,
                'last_validation_date' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            $update_formats = ['%d', '%s', '%s'];

            if (!$is_unlimited) {
                $update_data['remaining_sessions'] = max(0, ((int) $course['remaining_sessions']) - 1);
                $update_formats = ['%d', '%d', '%s', '%s'];
            }

            $updated = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $course['id']],
                $update_formats,
                ['%d']
            );

            if ($updated !== false) {
                if ($membership_id > 0) {
                    $processed_membership_ids[$membership_id] = true;
                    $this->log_successful_validation($membership_id, $user_id, $course, $is_unlimited);
                }
                $validated_courses[] = $course['course_name'];
                if ($first_membership_id === 0) {
                    $first_membership_id = (int) $course['id'];
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[Multi Validation] User %d - Course: %s - Consumed 1 session - Remaining: %s',
                        $user_id,
                        $course['course_name'],
                        $is_unlimited ? '∞' : (string) max(0, ((int) $course['remaining_sessions']) - 1)
                    ));
                }
            }
        }

        if (empty($validated_courses)) {
            return [
                'success' => false,
                'code' => 'NO_ELIGIBLE_COURSE',
                'message' => "⚠️ Nici un curs nu a putut fi validat.\n" .
                    (!empty($skipped_courses) ? 'Motive: ' . implode(', ', $skipped_courses) : ''),
                'skipped_courses' => $skipped_courses
            ];
        }

        if ($this->should_enforce_daily_lock($settings)) {
            $this->set_user_daily_validation_lock($user_id);
        }

        $message = '✅ Validare reușită pentru ' . count($validated_courses) . " curs(uri):\n";
        $message .= '• ' . implode("\n• ", $validated_courses);
        if (!empty($skipped_courses)) {
            $message .= "\n\n⚠️ Omise: " . implode(', ', $skipped_courses);
        }

        return [
            'success' => true,
            'code' => 'CHECK_IN_OK',
            'message' => $message,
            'validated_count' => count($validated_courses),
            'validated_courses' => $validated_courses,
            'skipped_courses' => $skipped_courses,
            'validation_time' => current_time('d/m/Y H:i'),
            'first_membership' => $first_membership_id > 0 ? $this->get_membership_by_id($first_membership_id) : null
        ];
    }

    private function log_successful_validation(int $membership_id, int $user_id, array $course, bool $is_unlimited): void {
        global $wpdb;

        $log_table = $this->validator_db->get_table_name('membership_validation_log');
        $metadata = [
            'endpoint' => 'check-in',
            'source' => 'smart_validation_service',
            'consumed' => true,
            'course_name' => (string) ($course['course_name'] ?? ''),
            'schedule_id' => (int) ($course['schedule_id'] ?? 0),
            'remaining_sessions_after' => $is_unlimited ? OC_UNLIMITED_SESSIONS : max(0, ((int) ($course['remaining_sessions'] ?? 0)) - 1),
        ];

        $client_ip = function_exists('oc_get_client_ip')
            ? oc_get_client_ip()
            : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $wpdb->insert($log_table, [
            'membership_id' => $membership_id,
            'user_id' => max(0, $user_id),
            'validator_user_id' => max(0, get_current_user_id()),
            'validation_method' => 'api',
            'validation_status' => 'success',
            'validation_date' => current_time('mysql'),
            'ip_address' => $client_ip,
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'validation_metadata' => wp_json_encode($metadata),
            'error_message' => '',
        ], [
            '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
    }

    /**
     * True when the stored validation datetime is on the same local day as "now" (WP timezone).
     */
    private function is_validated_today(string $last_validation_date): bool {
        $last_validation_date = trim($last_validation_date);
        if ($last_validation_date === '') {
            return false;
        }

        try {
            $timezone = wp_timezone();
            $last = new DateTimeImmutable($last_validation_date, $timezone);
            return $last->format('Y-m-d') === $this->get_today_local_date();
        } catch (Exception $exception) {
            return false;
        }
    }

    private function get_current_local_datetime(): DateTimeImmutable {
        return oc_membership_current_local_datetime();
    }

    private function get_user_daily_lock_key(int $user_id): string {
        return 'oc_membership_daily_lock_' . max(0, $user_id);
    }

    private function get_user_daily_lock_meta_key(): string {
        return 'oc_membership_last_validation_day';
    }

    private function get_today_local_date(): string {
        return $this->get_current_local_datetime()->format('Y-m-d');
    }

    private function has_user_daily_validation_lock(int $user_id): bool {
        if ($user_id <= 0) {
            return false;
        }

        $today = $this->get_today_local_date();
        $meta_key = $this->get_user_daily_lock_meta_key();
        $last_day = (string) get_user_meta($user_id, $meta_key, true);
        if ($last_day === $today) {
            return true;
        }

        return (bool) get_transient($this->get_user_daily_lock_key($user_id));
    }

    private function set_user_daily_validation_lock(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, $this->get_user_daily_lock_meta_key(), $this->get_today_local_date());

        set_transient(
            $this->get_user_daily_lock_key($user_id),
            1,
            $this->get_seconds_until_tomorrow()
        );
    }

    private function get_seconds_until_tomorrow(): int {
        try {
            $now = $this->get_current_local_datetime();
            $tomorrow_start = $now->setTime(0, 0)->modify('+1 day');
            $seconds = $tomorrow_start->getTimestamp() - $now->getTimestamp();
            if ($seconds < 60) {
                return 60;
            }

            return $seconds;
        } catch (Exception $exception) {
            return DAY_IN_SECONDS;
        }
    }

    /**
     * Shared helper for any UI/report that needs to know if validations are counted per day.
     */
    public function uses_daily_validation_lock(): bool {
        return $this->should_enforce_daily_lock($this->get_validation_timing_settings());
    }

    /**
     * Daily lock is active for either:
     * - timing rule: once_per_day_after_hour
     * - legacy/global restriction: once_per_day
     */
    private function should_enforce_daily_lock(array $settings): bool {
        if (($settings['rule'] ?? '') === 'once_per_day_after_hour') {
            return true;
        }

        $legacy_restriction = (string) get_option('oc_membership_validation_restriction', 'none');
        return $legacy_restriction === 'once_per_day';
    }

    private function find_all_running_courses_today(array $user_courses): array {
        global $wpdb;

        $settings = $this->get_validation_timing_settings();
        $current_datetime = $this->get_current_local_datetime();
        $current_weekday = (int) $current_datetime->format('w'); // 0-6, 0=Sunday
        $schedule_table = $wpdb->prefix . 'orar_cursuri';

        $all_courses_today = [];

        foreach ($user_courses as $course) {
            if ($current_weekday === 0) {
                $schedules = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$schedule_table}
                     WHERE variation_id = %d
                     AND (weekday = 0 OR weekday = 7)
                     ORDER BY start_time ASC",
                    $course['variation_id']
                ), ARRAY_A);
            } else {
                $schedules = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$schedule_table}
                     WHERE variation_id = %d
                     AND weekday = %d
                     ORDER BY start_time ASC",
                    $course['variation_id'],
                    $current_weekday
                ), ARRAY_A);
            }

            // Add one entry per time slot so each window is checked independently.
            // Previously LIMIT 1 caused only the first slot to be evaluated,
            // which meant newly added slots on the same day were invisible to validation.
            if (!empty($schedules)) {
                foreach ($schedules as $schedule) {
                    $course_with_slot = $course;
                    $course_with_slot['schedule'] = $schedule;
                    $all_courses_today[] = $course_with_slot;
                }
            }
        }

        if ($settings['rule'] === 'minutes_before_course') {
            // Return only the memberships whose scheduled slot is within the validation window.
            // Each membership appears at most once (one slot per variation per day is guaranteed).
            return array_values(array_filter($all_courses_today, function (array $course) use ($settings, $current_datetime): bool {
                $schedule = $course['schedule'] ?? null;
                if (!is_array($schedule) || empty($schedule['start_time'])) {
                    return false;
                }

                return $this->has_course_started_with_window((string) $schedule['start_time'], $settings['minutes_before'], $current_datetime);
            }));
        }

        if ($settings['rule'] === 'once_per_day_after_hour' && $this->has_daily_start_hour_passed($settings['once_start_hour'], $current_datetime)) {
            // Return ALL memberships that have any slot scheduled today so that
            // validate_and_consume can decrement a session for each active course the
            // user holds — not just the first one found.
            return $all_courses_today;
        }

        return [];
    }

    private function get_validation_timing_settings(): array {
        $rule = (string) get_option('oc_membership_validation_timing_rule', 'minutes_before_course');
        if (!in_array($rule, ['minutes_before_course', 'once_per_day_after_hour'], true)) {
            $rule = 'minutes_before_course';
        }

        $minutes_before = (int) get_option('oc_membership_validation_window_minutes_before', 30);
        if ($minutes_before < 0) {
            $minutes_before = 0;
        }
        if ($minutes_before > 240) {
            $minutes_before = 240;
        }

        $once_start_hour = (string) get_option('oc_membership_once_per_day_start_hour', '00:00');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $once_start_hour)) {
            $once_start_hour = '00:00';
        }

        return [
            'rule' => $rule,
            'minutes_before' => $minutes_before,
            'once_start_hour' => $once_start_hour
        ];
    }

    private function has_course_started_with_window(string $start_time, int $minutes_before, DateTimeImmutable $current_datetime): bool {
        try {
            $timezone = wp_timezone();
            $date = $current_datetime->format('Y-m-d');
            $start_datetime = new DateTimeImmutable($date . ' ' . $start_time, $timezone);
        } catch (Exception $exception) {
            return false;
        }

        $window_start = $start_datetime->modify(sprintf('-%d minutes', $minutes_before));
        return $window_start instanceof DateTimeImmutable && $current_datetime >= $window_start;
    }

    private function has_daily_start_hour_passed(string $start_hour, DateTimeImmutable $current_datetime): bool {
        try {
            $timezone = wp_timezone();
            [$hour, $minute] = array_map('intval', explode(':', $start_hour));
            $date = $current_datetime->format('Y-m-d');
            $start_datetime = new DateTimeImmutable($date . sprintf(' %02d:%02d:00', $hour, $minute), $timezone);
        } catch (Exception $exception) {
            return false;
        }

        return $current_datetime >= $start_datetime;
    }

    private function get_all_user_active_courses(int $user_id): array {
        global $wpdb;
        $table_name = $this->validator_db->get_table_name('membership_validations');
        $posts_table = $wpdb->prefix . 'posts';

        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT
                m.id,
                m.order_id,
                m.product_id,
                m.variation_id,
                m.payment_method,
                m.total_sessions AS sessions_allocated,
                m.remaining_sessions,
                m.used_sessions,
                m.is_unlimited,
                m.last_validation_date,
                m.validation_status,
                m.expiration_date
             FROM {$table_name} m
             LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
             WHERE m.user_id = %d
             AND m.validation_status = 'active'
             AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
             ORDER BY m.id DESC",
            $user_id
        ), ARRAY_A);

        if (empty($courses)) {
            return [];
        }

        $enriched = [];
        foreach ($courses as $course) {
            $variation = wc_get_product($course['variation_id']);
            if ($variation) {
                $course['course_name'] = $variation->get_name();
                $enriched[] = $course;
            }
        }

        return $enriched;
    }

    private function get_membership_by_id(int $membership_id): ?array {
        global $wpdb;
        $table_name = $this->validator_db->get_table_name('membership_validations');
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
            $membership_id
        ), ARRAY_A);

        return $membership ?: null;
    }
}
