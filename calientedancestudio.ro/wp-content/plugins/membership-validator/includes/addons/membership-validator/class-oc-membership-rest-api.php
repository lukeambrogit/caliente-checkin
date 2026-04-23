<?php
/**
 * REST API pentru Membership Validator
 *
 * @package MembershipValidator
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Membership_REST_API {

    private const API_NAMESPACE = 'caliente/v1';
    private const DEFAULT_RATE_LIMIT_PER_MINUTE = 100;
    private const RATE_LIMIT_WINDOW_SECONDS = MINUTE_IN_SECONDS;

    private ?OC_Membership_QR $qr_system = null;
    private ?OC_Membership_DB $db = null;

    /**
     * Context populated after successful API auth.
     *
     * @var array<string,mixed>
     */
    private array $auth_context = [];

    public function __construct(OC_Membership_QR $qr_system, OC_Membership_DB $db) {
        $this->qr_system = $qr_system;
        $this->db = $db;
    }

    public function register_routes(): void {
        // Legacy endpoint: keeps backward compatibility, but now requires auth.
        register_rest_route(self::API_NAMESPACE, '/validate-qr', [
            'methods' => 'GET',
            'callback' => [$this, 'validate_qr'],
            'permission_callback' => [$this, 'check_api_auth'],
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        return !empty($param);
                    }
                ],
                'device_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'request_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        register_rest_route(self::API_NAMESPACE, '/validate-membership', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_membership_by_user_id'],
            'permission_callback' => [$this, 'check_api_auth'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return $param > 0;
                    }
                ],
                'device_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'request_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        register_rest_route(self::API_NAMESPACE, '/check-in', [
            'methods' => 'POST',
            'callback' => [$this, 'check_in'],
            'permission_callback' => [$this, 'check_api_auth'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return $param > 0;
                    }
                ],
                'device_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'request_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        register_rest_route(self::API_NAMESPACE, '/checkins', [
            'methods' => 'GET',
            'callback' => [$this, 'get_checkins'],
            'permission_callback' => [$this, 'check_api_auth'],
            'args' => [
                'date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'default' => 100,
                ],
            ]
        ]);
    }

    public function get_checkins(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $date  = $request->get_param('date') ?: current_time('Y-m-d');
        $limit = min((int) ($request->get_param('limit') ?? 100), 500);

        $log_table   = $wpdb->prefix . 'membership_validation_log';
        $mv_table    = $wpdb->prefix . 'membership_validations';
        $users_table = $wpdb->prefix . 'users';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                l.id,
                l.user_id,
                COALESCE(u.display_name, CONCAT('User #', l.user_id)) AS user_name,
                l.validation_status AS status,
                l.validation_date,
                l.error_message,
                l.validation_metadata,
                mv.product_id
             FROM {$log_table} l
             LEFT JOIN {$users_table} u ON u.ID = l.user_id
             LEFT JOIN {$mv_table} mv   ON mv.id = l.membership_id
             WHERE DATE(l.validation_date) = %s
               AND l.validation_method = 'api'
               AND l.user_id > 0
             ORDER BY l.validation_date DESC
             LIMIT %d",
            $date,
            $limit
        ), ARRAY_A);

        if ($rows === null) {
            return $this->error_response($request, 'DB_ERROR', 'Could not fetch check-in log.', 500);
        }

        $entries = [];
        foreach ($rows as $row) {
            $meta = json_decode((string) ($row['validation_metadata'] ?? ''), true) ?: [];
            $product_name = '';
            if (!empty($row['product_id'])) {
                $product = wc_get_product((int) $row['product_id']);
                $product_name = $product ? $product->get_name() : '';
            }
            $entries[] = [
                'id'           => (int) $row['id'],
                'user_id'      => (int) $row['user_id'],
                'user_name'    => $row['user_name'],
                'product_name' => $product_name,
                'status'       => $row['status'],
                'result_code'  => $meta['code'] ?? '',
                'time'         => $row['validation_date'],
                'device_id'    => $meta['device_id'] ?? '',
                'error'        => $row['error_message'] ?? '',
            ];
        }

        return $this->success_response($request, 'OK', 'Check-in log fetched.', [
            'date'    => $date,
            'count'   => count($entries),
            'entries' => $entries,
        ]);
    }

    public function check_api_auth(WP_REST_Request $request): bool|WP_Error {
        $api_key = $this->extract_api_key($request);
        $device_id = $this->extract_device_id($request);

        if ($api_key === '' || $device_id === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Membership API Auth] Missing credentials. api_key_present=%s, device_id="%s", route=%s',
                    $api_key !== '' ? 'yes' : 'no',
                    $device_id,
                    $request->get_route()
                ));
            }
            return new WP_Error(
                'UNAUTHORIZED',
                'Missing API credentials (x-api-key and x-device-id).',
                ['status' => 401]
            );
        }

        $validation_result = $this->validate_device_api_key($device_id, $api_key);
        if (is_wp_error($validation_result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Membership API Auth] Validation failed. code=%s, message=%s, device_id="%s", route=%s',
                    $validation_result->get_error_code(),
                    $validation_result->get_error_message(),
                    $device_id,
                    $request->get_route()
                ));
            }
            return $validation_result;
        }

        $this->auth_context = [
            'device_id' => $device_id,
            'api_fingerprint' => substr(hash('sha256', $api_key), 0, 16),
            'scopes' => $validation_result['scopes'] ?? [],
            'rate_limit_per_minute' => (int) ($validation_result['rate_limit_per_minute'] ?? self::DEFAULT_RATE_LIMIT_PER_MINUTE),
        ];

        return true;
    }

    public function validate_qr(WP_REST_Request $request): WP_REST_Response {
        $rate_limit_response = $this->maybe_handle_rate_limit($request, 'validate-qr');
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        $token = (string) $request->get_param('token');
        $validation_data = $this->qr_system->validate_qr_token($token, [
            'source' => 'rest_api_validate_qr',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'request_id' => $this->get_request_id($request),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('user-agent')
        ]);

        if (!$validation_data) {
            $this->log_api_event(0, 0, 'failed', 'INVALID_QR', 'QR code invalid or expired', [
                'endpoint' => 'validate-qr',
                'device_id' => $this->auth_context['device_id'] ?? ''
            ]);
            return $this->error_response($request, 'INVALID_QR', 'QR code invalid, expired or no sessions remaining', 404);
        }

        // IMPORTANT: Use shared smart engine to keep behavior identical with app/admin:
        // - once-per-day restrictions
        // - multi-course same-day consumption
        $validator = $this->get_shared_validator();
        if (!$validator) {
            return $this->error_response($request, 'SYSTEM_ERROR', 'Validation engine unavailable.', 500);
        }

        $user_id = (int) ($validation_data['user_id'] ?? 0);
        $checkin_result = $validator->check_in_user($user_id, [
            'endpoint' => 'validate-qr',
            'source' => 'rest_api_validate_qr',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'request_id' => $this->get_request_id($request),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('user-agent')
        ]);

        if (empty($checkin_result['success'])) {
            $code = (string) ($checkin_result['code'] ?? 'CHECKIN_FAILED');
            $raw_message = (string) ($checkin_result['message'] ?? 'QR validation/check-in failed.');
            $message = $this->get_friendly_business_message($code, $raw_message);

            $this->log_api_event(
                (int) ($validation_data['validation_id'] ?? 0),
                $user_id,
                'failed',
                $code,
                $raw_message,
                [
                    'endpoint' => 'validate-qr',
                    'device_id' => $this->auth_context['device_id'] ?? '',
                    'friendly_message' => $message
                ]
            );

            // Include member data so the UI can still display who was scanned.
            $member_data = [
                'user_id'            => $user_id,
                'user_name'          => $validation_data['user_name'] ?? '',
                'product_name'       => $validation_data['product_name'] ?? '',
                'sessions_remaining' => (int) ($validation_data['sessions_available'] ?? 0),
                'sessions_total'     => (int) ($validation_data['sessions_total'] ?? 0),
                'expires_at'         => $validation_data['expires_at'] ?? '',
            ];

            $this->notify_ws_server([
                'user_id'      => $user_id,
                'user_name'    => $validation_data['user_name'] ?? '',
                'product_name' => $validation_data['product_name'] ?? '',
                'status'       => 'failed',
                'result_code'  => $code,
                'error'        => $message,
                'time'         => current_time('mysql'),
                'device_id'    => $this->auth_context['device_id'] ?? '',
                'date'         => current_time('Y-m-d'),
            ]);

            return $this->error_response($request, $code, $message, 409, $member_data);
        }

        $this->log_api_event((int) $validation_data['validation_id'], (int) $validation_data['user_id'], 'success', 'OK', 'QR validation succeeded', [
            'endpoint' => 'validate-qr',
            'device_id' => $this->auth_context['device_id'] ?? ''
        ]);

        $response_data = [
            'user_id'            => (int) $validation_data['user_id'],
            'user_name'          => $validation_data['user_name'],
            'validation_id'      => (int) ($checkin_result['membership_id'] ?? $validation_data['validation_id']),
            'product_name'       => $validation_data['product_name'],
            'sessions_remaining' => (int) ($checkin_result['sessions_remaining'] ?? $validation_data['sessions_available']),
            'sessions_total'     => (int) $validation_data['sessions_total'],
            'sessions_used'      => (int) ($checkin_result['sessions_used'] ?? $validation_data['sessions_used']),
            'expires_at'         => $validation_data['expires_at'],
            'status'             => (string) ($checkin_result['status'] ?? 'active'),
            'validated_at'       => current_time('c'),
        ];

        $this->notify_ws_server([
            'user_id'      => $response_data['user_id'],
            'user_name'    => $response_data['user_name'],
            'product_name' => $response_data['product_name'],
            'status'       => 'success',
            'result_code'  => 'OK',
            'error'        => '',
            'time'         => current_time('mysql'),
            'device_id'    => $this->auth_context['device_id'] ?? '',
            'date'         => current_time('Y-m-d'),
        ]);

        return $this->success_response($request, 'OK', 'Membership validation succeeded.', $response_data);
    }

    public function validate_membership_by_user_id(WP_REST_Request $request): WP_REST_Response {
        $rate_limit_response = $this->maybe_handle_rate_limit($request, 'validate-membership');
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        $user_id = (int) $request->get_param('user_id');
        $validator = $this->get_shared_validator();
        if (!$validator) {
            return $this->error_response($request, 'SYSTEM_ERROR', 'Validation engine unavailable.', 500);
        }

        $result = $validator->validate_user_membership($user_id, [
            'source' => 'rest_api_validate_membership',
            'endpoint' => 'validate-membership',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'request_id' => $this->get_request_id($request),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('user-agent')
        ]);

        $membership_id = (int) ($result['membership_id'] ?? 0);
        $status = !empty($result['valid']) ? 'success' : 'failed';
        $code = (string) ($result['code'] ?? ($result['valid'] ? 'OK' : 'VALIDATION_FAILED'));
        $raw_message = (string) ($result['message'] ?? 'Membership validation completed.');
        $message = $this->get_friendly_business_message($code, $raw_message);

        $this->log_api_event($membership_id, $user_id, $status, $code, $message, [
            'endpoint' => 'validate-membership',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'membership_status' => (string) ($result['status'] ?? 'none'),
            'raw_message' => $raw_message
        ]);

        return $this->success_response($request, $code, $message, [
            'valid' => (bool) ($result['valid'] ?? false),
            'user_id' => $user_id,
            'user_name' => (string) ($result['user_name'] ?? ''),
            'membership_id' => $membership_id,
            'product_id' => (int) ($result['product_id'] ?? 0),
            'product_name' => (string) ($result['product_name'] ?? ''),
            'variation_name' => (string) ($result['variation_name'] ?? ''),
            'product_display_name' => (string) ($result['product_display_name'] ?? ''),
            'payment_method' => (string) ($result['payment_method'] ?? ''),
            'sessions_remaining' => (int) ($result['sessions_remaining'] ?? 0),
            'sessions_total' => (int) ($result['sessions_total'] ?? 0),
            'sessions_used' => (int) ($result['sessions_used'] ?? 0),
            'is_unlimited' => (bool) ($result['is_unlimited'] ?? false),
            'expires_at' => $result['expires_at'] ?? null,
            'status' => (string) ($result['status'] ?? 'none'),
            'has_membership' => (bool) ($result['has_membership'] ?? false),
            'validated_at' => current_time('c')
        ]);
    }

    public function check_in(WP_REST_Request $request): WP_REST_Response {
        $rate_limit_response = $this->maybe_handle_rate_limit($request, 'check-in');
        if ($rate_limit_response instanceof WP_REST_Response) {
            return $rate_limit_response;
        }

        $user_id = (int) $request->get_param('user_id');
        $user = get_userdata($user_id);
        if (!$user) {
            $this->log_api_event(0, $user_id, 'failed', 'USER_NOT_FOUND', 'User not found on check-in', [
                'endpoint' => 'check-in',
                'device_id' => $this->auth_context['device_id'] ?? ''
            ]);
            return $this->error_response($request, 'USER_NOT_FOUND', 'User not found.', 404);
        }

        $validator = $this->get_shared_validator();
        if (!$validator) {
            return $this->error_response($request, 'SYSTEM_ERROR', 'Validation engine unavailable.', 500);
        }

        $request_id = $this->get_request_id($request);
        $result = $validator->check_in_user($user_id, [
            'endpoint' => 'check-in',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'request_id' => $request_id,
            'source' => 'rest_api_check_in',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('user-agent')
        ]);

        $membership_id = (int) ($result['membership_id'] ?? 0);
        $code = (string) ($result['code'] ?? (!empty($result['success']) ? 'CHECK_IN_OK' : 'CHECKIN_FAILED'));
        $raw_message = (string) ($result['message'] ?? (!empty($result['success']) ? 'Check-in successful.' : 'Check-in failed.'));
        $message = $this->get_friendly_business_message($code, $raw_message);
        $status = !empty($result['success']) ? 'success' : 'failed';

        $this->log_api_event($membership_id, $user_id, $status, $code, $message, [
            'endpoint' => 'check-in',
            'device_id' => $this->auth_context['device_id'] ?? '',
            'request_id' => $request_id,
            'raw_message' => $raw_message
        ]);

        if (empty($result['success'])) {
            return $this->error_response($request, $code, $message, 409);
        }

        return $this->success_response($request, 'CHECK_IN_OK', $message, [
            'user_id' => $user_id,
            'membership_id' => $membership_id,
            'sessions_remaining' => (int) ($result['sessions_remaining'] ?? 0),
            'sessions_total' => (int) ($result['sessions_total'] ?? 0),
            'sessions_used' => (int) ($result['sessions_used'] ?? 0),
            'status' => (string) ($result['status'] ?? 'active'),
            'expires_at' => $result['expires_at'] ?? null,
            'checked_in_at' => current_time('c'),
            'validated_count' => (int) ($result['validated_count'] ?? 0),
            'validated_courses' => is_array($result['validated_courses'] ?? null) ? $result['validated_courses'] : [],
            'skipped_courses' => is_array($result['skipped_courses'] ?? null) ? $result['skipped_courses'] : []
        ]);
    }

    private function success_response(
        WP_REST_Request $request,
        string $code,
        string $message,
        array $data = [],
        int $status = 200
    ): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'request_id' => $this->get_request_id($request),
            'data' => $data
        ], $status);
    }

    private function error_response(
        WP_REST_Request $request,
        string $code,
        string $message,
        int $status = 400,
        array $data = []
    ): WP_REST_Response {
        $compat_data = array_merge($data, [
            // Backward compatibility: some clients read nested data.message/code.
            'message' => $message,
            'code' => $code,
            'friendly_message' => $message,
        ]);

        return new WP_REST_Response([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'request_id' => $this->get_request_id($request),
            'data' => $compat_data,
            // Extra compatibility payload for legacy/mobile clients.
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function maybe_handle_rate_limit(WP_REST_Request $request, string $endpoint): ?WP_REST_Response {
        $device_id = (string) ($this->auth_context['device_id'] ?? '');
        if ($device_id === '') {
            return null;
        }

        $limit = (int) ($this->auth_context['rate_limit_per_minute'] ?? self::DEFAULT_RATE_LIMIT_PER_MINUTE);
        if ($limit <= 0) {
            return null;
        }

        $transient_key = 'oc_api_rl_' . md5($device_id);
        $state = get_transient($transient_key);

        if (!is_array($state)) {
            $state = [
                'count' => 0,
                'started_at' => time(),
            ];
        }

        $count = isset($state['count']) ? (int) $state['count'] : 0;
        if ($count >= $limit) {
            $retry_after = max(1, self::RATE_LIMIT_WINDOW_SECONDS - (time() - (int) ($state['started_at'] ?? time())));

            $this->log_api_event(0, 0, 'failed', 'RATE_LIMIT', 'Rate limit exceeded.', [
                'endpoint' => $endpoint,
                'device_id' => $device_id,
                'limit' => $limit,
                'retry_after' => $retry_after,
            ]);

            return $this->error_response(
                $request,
                'RATE_LIMIT',
                'Too many attempts. Please retry later.',
                429,
                [
                    'retry_after' => $retry_after,
                    'limit' => $limit,
                    'window_seconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]
            );
        }

        set_transient($transient_key, [
            'count' => $count + 1,
            'started_at' => (int) ($state['started_at'] ?? time()),
        ], self::RATE_LIMIT_WINDOW_SECONDS);

        return null;
    }

    private function get_request_id(WP_REST_Request $request): string {
        $request_id = (string) $request->get_param('request_id');
        if ($request_id === '') {
            $request_id = (string) $request->get_header('x-request-id');
        }

        $request_id = sanitize_text_field($request_id);
        if ($request_id === '') {
            $request_id = wp_generate_uuid4();
        }

        return substr($request_id, 0, 100);
    }

    private function extract_api_key(WP_REST_Request $request): string {
        $api_key = (string) $request->get_header('x-api-key');
        if ($api_key !== '') {
            return trim($api_key);
        }

        $authorization = (string) $request->get_header('authorization');
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return '';
    }

    private function extract_device_id(WP_REST_Request $request): string {
        $device_id = sanitize_text_field((string) $request->get_header('x-device-id'));
        if ($device_id === '') {
            $device_id = sanitize_text_field((string) $request->get_param('device_id'));
        }

        return substr($device_id, 0, 100);
    }

    /**
     * Supports two configuration formats:
     * - option `oc_membership_api_devices` keyed by device_id
     * - fallback global key from constant/option for MVP compatibility.
     */
    private function validate_device_api_key(string $device_id, string $api_key): array|WP_Error {
        $devices = get_option('oc_membership_api_devices', []);
        if (is_array($devices) && isset($devices[$device_id]) && is_array($devices[$device_id])) {
            $device_config = $devices[$device_id];
            $active = !isset($device_config['active']) || (bool) $device_config['active'];
            if (!$active) {
                return new WP_Error('FORBIDDEN', 'Device is disabled.', ['status' => 403]);
            }

            $hash = (string) ($device_config['api_key_hash'] ?? '');
            $plain = (string) ($device_config['api_key'] ?? '');
            $hash_match = $hash !== '' && hash_equals($hash, hash('sha256', $api_key));
            $plain_match = $plain !== '' && hash_equals($plain, $api_key);

            if (!$hash_match && !$plain_match) {
                return new WP_Error('UNAUTHORIZED', 'Invalid API key.', ['status' => 401]);
            }

            $device_config['last_used_at'] = current_time('mysql');
            $devices[$device_id] = $device_config;
            update_option('oc_membership_api_devices', $devices, false);

            $rate_limit_per_minute = array_key_exists('rate_limit_per_minute', $device_config)
                ? max(0, absint($device_config['rate_limit_per_minute']))
                : self::DEFAULT_RATE_LIMIT_PER_MINUTE;

            return [
                'scopes' => is_array($device_config['scopes'] ?? null) ? $device_config['scopes'] : [],
                'rate_limit_per_minute' => $rate_limit_per_minute,
            ];
        }

        $global_key = '';
        if (defined('OC_MEMBERSHIP_API_KEY')) {
            $global_key = (string) constant('OC_MEMBERSHIP_API_KEY');
        } elseif (get_option('oc_membership_api_key', '') !== '') {
            $global_key = (string) get_option('oc_membership_api_key', '');
        }

        if ($global_key === '') {
            return new WP_Error(
                'API_CONFIG_MISSING',
                'API auth is not configured. Set oc_membership_api_devices or oc_membership_api_key.',
                ['status' => 500]
            );
        }

        if (!hash_equals($global_key, $api_key)) {
            return new WP_Error('UNAUTHORIZED', 'Invalid API key.', ['status' => 401]);
        }

        return [
            'scopes' => [],
            'rate_limit_per_minute' => self::DEFAULT_RATE_LIMIT_PER_MINUTE,
        ];
    }

    private function get_client_ip(): string {
        if (function_exists('oc_get_client_ip')) {
            return oc_get_client_ip();
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function get_friendly_business_message(string $code, string $fallback): string {
        $normalized = strtoupper(trim($code));

        return match ($normalized) {
            'OK' => 'Abonament activ si valabil.',
            'CHECK_IN_OK' => 'Check-in efectuat cu succes.',
            'ALREADY_VALIDATED_TODAY', 'ALREADY_VALIDATED' => 'Abonamentul a fost deja validat astazi.',
            'NO_COURSE_TODAY' => 'Nu exista curs programat acum pentru acest abonament.',
            'NO_ELIGIBLE_COURSE' => 'Nu exista un curs eligibil pentru validare in acest moment.',
            'NO_ACTIVE_COURSES', 'CHECKIN_NOT_ALLOWED' => 'Nu exista abonament activ disponibil pentru validare.',
            'NO_SESSIONS', 'MEMBERSHIP_EXPIRED', 'MEMBERSHIP_NOT_FOUND' => 'Abonamentul nu mai are sedinte disponibile sau este expirat.',
            'MEMBERSHIP_PENDING_ACTIVATION' => 'Abonamentul necesita activare.',
            'DAY_NOT_ALLOWED' => 'Abonamentul nu poate fi validat in aceasta zi.',
            'DAILY_LIMIT_REACHED' => 'Limita zilnica de validari a fost atinsa.',
            'MISSING_ACCESS_CODE' => 'Codul de acces este obligatoriu.',
            'CHECKIN_FAILED', 'VALIDATION_FAILED' => 'Validarea nu a putut fi finalizata. Te rugam sa incerci din nou.',
            'USER_NOT_FOUND' => 'Utilizatorul nu a fost gasit.',
            default => $fallback !== '' ? $fallback : 'Validarea nu a putut fi finalizata. Te rugam sa incerci din nou.',
        };
    }


    /**
     * Non-blocking POST to the WebSocket broadcast server so connected dashboards
     * receive live check-in events without polling.
     */
    private function notify_ws_server(array $event): void {
        $ws_url    = (string) get_option('oc_ws_server_url', '');
        $ws_secret = (string) get_option('oc_ws_server_secret', '');
        if (empty($ws_url)) {
            return;
        }
        wp_remote_post(
            trailingslashit($ws_url) . 'broadcast',
            [
                'timeout'  => 2,
                'blocking' => false,  // fire-and-forget — don't slow down the API response
                'headers'  => [
                    'Content-Type' => 'application/json',
                    'X-WS-Secret'  => $ws_secret,
                ],
                'body'     => wp_json_encode($event),
            ]
        );
    }

    private function log_api_event(
        int $membership_id,
        int $user_id,
        string $validation_status,
        string $code,
        string $message,
        array $metadata = []
    ): void {
        global $wpdb;

        $status = in_array($validation_status, ['success', 'failed', 'error'], true)
            ? $validation_status
            : 'error';

        $log_table = $wpdb->prefix . 'membership_validation_log';
        $wpdb->insert($log_table, [
            'membership_id' => max(0, $membership_id),
            'user_id' => max(0, $user_id),
            'validator_user_id' => 0,
            'validation_method' => 'api',
            'validation_status' => $status,
            'validation_date' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'validation_metadata' => wp_json_encode(array_merge([
                'code' => $code,
                'message' => $message,
                'device_id' => $this->auth_context['device_id'] ?? null
            ], $metadata)),
            'error_message' => $status === 'success' ? null : $message
        ]);
    }

    private function get_shared_validator(): ?OC_Membership_Validation {
        $plugin = OC_Membership_Validator::get_instance();
        if (!$plugin) {
            return null;
        }

        return $plugin->get_validator();
    }
}

