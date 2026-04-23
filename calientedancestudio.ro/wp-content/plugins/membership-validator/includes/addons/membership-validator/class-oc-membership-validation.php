<?php
/**
 * Validation Logic pentru Membership Validator
 * 
 * CONFORMITATE .cursorrules:
 * - Validare timp real cu date din {$wpdb->prefix}orar_cursuri (READ-ONLY)
 * - Integrare cu Pool Product Manager prin postmeta (NON-INTRUZIV)
 * - Gestionare ședințe și expirări cu persistență DB
 * - Securitate pentru validări
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OC_Membership_Smart_Validation_Service') && defined('OC_PLUGIN_DIR')) {
    require_once OC_PLUGIN_DIR . 'includes/addons/membership-manager/class-oc-membership-smart-validation-service.php';
}

/**
 * Class OC_Membership_Validation
 * 
 * Orchestrează validarea membership-urilor în timp real
 * Integrează cu toate sistemele existente prin API-uri publice
 */
class OC_Membership_Validation {
    
    /**
     * @var OC_Membership_DB Database handler
     */
    private OC_Membership_DB $db;
    
    /**
     * @var OC_Membership_QR QR system handler
     */
    private OC_Membership_QR $qr_system;

    /**
     * @var OC_Membership_Smart_Validation_Service|null Shared smart validator from plugin button flow
     */
    private ?OC_Membership_Smart_Validation_Service $smart_validation_service = null;
    
    /**
     * Constructor
     * 
     * @param OC_Membership_DB $db Database handler
     * @param OC_Membership_QR $qr_system QR system handler
     */
    public function __construct(OC_Membership_DB $db, OC_Membership_QR $qr_system) {
        $this->db = $db;
        $this->qr_system = $qr_system;
        if (class_exists('OC_Membership_Smart_Validation_Service')) {
            $this->smart_validation_service = new OC_Membership_Smart_Validation_Service($db);
        }
    }
    
    /**
     * Validare detaliată a membership-ului
     */
    private function validate_membership_details(array $qr_validation, array $context): array|\WP_Error {
        // Verifică ședințe disponibile
        if ($qr_validation['sessions_available'] <= 0) {
            return new \WP_Error('no_sessions', 'Nu mai aveți ședințe disponibile pe acest abonament.');
        }
        
        // Verifică expirarea
        if (oc_membership_is_expired($qr_validation['expires_at'] ?? null)) {
            return new \WP_Error('membership_expired', 'Abonamentul a expirat.');
        }
        
        return ['membership_valid' => true];
    }
    
    /**
     * Validare cu Schedule Manager (READ-ONLY conform .cursorrules)
     */
    private function validate_with_schedule(array $qr_validation, array $context): array|\WP_Error {
        global $wpdb;
        
        // Citește din tabelul orar_cursuri (READ-ONLY, NON-INTRUZIV)
        $schedule_table = $wpdb->prefix . 'orar_cursuri';
        $product_id = $qr_validation['product_id'];
        
        // Caută cursuri asociate produsului
        $schedules = $wpdb->get_results($wpdb->prepare("
            SELECT id, product_id, variation_id, weekday, start_time, end_time, room_number
            FROM {$schedule_table}
            WHERE product_id = %d OR variation_id = %d
            ORDER BY weekday, start_time
        ", $product_id, $product_id));
        
        if (empty($schedules)) {
            return new \WP_Error('no_schedule', 'Nu există program disponibil pentru acest abonament.');
        }
        
        return [
            'schedule_valid' => true,
            'schedule_info' => [
                'total_courses' => count($schedules),
                'courses' => $schedules
            ]
        ];
    }
    
    /**
     * Log încercare de validare pentru audit
     * Optimizat pentru production - folosește WordPress logging
     */
    private function log_validation_attempt(string $qr_token, bool $success, string $reason, array $context): void {
        // Log doar erorile în production pentru performanță
        if (!$success && WP_DEBUG) {
            error_log(sprintf(
                '[Membership Validator] Validation failed: %s | Token: %s***',
                $reason,
                substr($qr_token, 0, 8)
            ));
        }
    }
    
    /**
     * Generează QR code pentru un membership validation existent
     */
    public function generate_qr_for_validation(int $validation_id): array|\WP_Error {
        // Obține validation din DB
        $validation = $this->db->get_validation_by_id($validation_id);
        
        if (!$validation || $validation->validation_status !== 'active') {
            return new \WP_Error('validation_invalid', 'Validation nu este activ.');
        }
        
        // Verifică dacă există deja QR code
        if (!empty($validation->qr_token)) {
            return new \WP_Error('qr_exists', 'QR code există deja pentru această validare.');
        }
        
        // Citește date din sisteme existente
        $user_data = get_userdata($validation->user_id);
        $product_data = wc_get_product($validation->product_id);
        
        if (!$user_data || !$product_data) {
            return new \WP_Error('invalid_data', 'Date incomplete pentru generarea QR code.');
        }
        
        $membership_data = [
            'user_id' => $validation->user_id,
            'product_id' => $validation->product_id,
            'order_id' => $validation->order_id,
            'sessions_total' => $validation->total_sessions,
            'sessions_used' => $validation->used_sessions,
            'expires_at' => $validation->expiration_date
        ];
        
        // Generează QR code prin QR system
        return $this->qr_system->generate_qr_code($validation_id, $membership_data);
    }
    
    /**
     * Verifică status membership în timp real
     * Best practices 2025: Optimizat pentru performanță cu caching
     */
    public function get_user_active_memberships(int $user_id, int $product_id = 0): array {
        // 🎯 v1.3.0: Include și pending memberships pentru TRANSPARENT display
        return $this->get_user_memberships_with_status($user_id, $product_id, ['active', 'pending']);
    }
    
    /**
     * 🎯 v1.3.0: RENEWAL SYSTEM - Obține memberships cu status-uri specifice
     * 
     * Funcție nouă pentru transparență completă: returnează active + pending + expired
     * 
     * @param int $user_id ID utilizator
     * @param int $product_id ID produs (0 = toate)
     * @param array $statuses Status-uri de returnat ['active', 'pending', 'expired']
     * @return array Memberships găsite
     * @since 1.3.0
     */
    public function get_user_memberships_with_status(int $user_id, int $product_id = 0, array $statuses = ['active', 'pending', 'expired']): array {
        // 🔒 MANUAL ACTIVATION: Activare DOAR prin buton admin (eliminat activate_pending_memberships_instant)
        $this->db->sync_membership_statuses($user_id);
        
        // Performance optimization: Cache cu key pentru status-uri
        $status_key = implode('_', $statuses);
        $cache_key = "user_memberships_v13_{$user_id}_{$product_id}_{$status_key}";
        $cached_result = wp_cache_get($cache_key, 'membership_core_engine');
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // 🎯 Condiții pentru eligibilitate membru Pool + RENEWAL SYSTEM
        $where_conditions = [
            "m.user_id = %d",
            "m.variation_id > 0"  // Are variație din Pool = ESTE MEMBRU
        ];
        $where_values = [$user_id];
        
        // Status filter (active, pending, expired)
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $where_conditions[] = "m.validation_status IN ({$status_placeholders})";
        $where_values = array_merge($where_values, $statuses);
        
        if ($product_id > 0) {
            $where_conditions[] = "m.product_id = %d";
            $where_values[] = $product_id;
        }
        
        // 🎯 v1.3.0: Query cu date renewal system (start_date, duration_days, is_renewal)
        // Sortare: PENDING first (sus), apoi ACTIVE, apoi EXPIRED (jos)
        $query = "SELECT m.*, p.post_title as product_name, v.post_title as variation_name
                  FROM {$table_name} m
                  LEFT JOIN {$wpdb->posts} p ON m.product_id = p.ID
                  LEFT JOIN {$wpdb->posts} v ON m.variation_id = v.ID
                  WHERE " . implode(' AND ', $where_conditions) . " 
                  ORDER BY 
                    CASE m.validation_status
                      WHEN 'pending' THEN 1
                      WHEN 'active' THEN 2
                      WHEN 'expired' THEN 3
                      ELSE 4
                    END,
                    m.start_date ASC,
                    m.created_at DESC 
                  LIMIT 100";
        
        $memberships = $wpdb->get_results($wpdb->prepare($query, ...$where_values));
        
        if (empty($memberships)) {
            wp_cache_set($cache_key, [], 'membership_core_engine', OC_CACHE_INTERVAL);
            return [];
        }
        
        // Batch load products pentru performanță
        $product_ids = array_unique(array_column($memberships, 'product_id'));
        $products_cache = [];
        
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            $products_cache[$pid] = $product ? $product->get_name() : 'Unknown Product';
        }
        
        $result = [];
        foreach ($memberships as $membership) {
            // 🎯 Obține QR URL din pool_metadata JSON
            $qr_url = '';
            if (!empty($membership->pool_metadata)) {
                $pool_metadata = json_decode($membership->pool_metadata, true);
                if (isset($pool_metadata['qr_filename']) && !empty($pool_metadata['qr_filename'])) {
                    $upload_dir = wp_upload_dir();
                    $qr_url = $upload_dir['baseurl'] . '/membership-qr-codes/' . $pool_metadata['qr_filename'];
                }
            }
            
            $result[] = [
                'validation_id' => (int)$membership->id,
                'product_id' => (int)$membership->product_id,
                'product_name' => $products_cache[$membership->product_id] ?? 'Unknown Product',
                'variation_id' => (int)$membership->variation_id,
                'variation_name' => $membership->variation_name ?? '',
                'sessions_total' => (int)$membership->total_sessions,
                'sessions_used' => (int)$membership->used_sessions,
                'sessions_remaining' => (int)$membership->total_sessions - (int)$membership->used_sessions,
                
                // 🎯 v1.3.0: Date renewal system
                'start_date' => $membership->start_date,
                'expires_at' => $membership->expiration_date,
                'expiration_date' => $membership->expiration_date, // Alias pentru compatibility
                'duration_days' => (int)($membership->duration_days ?? 28),
                'validation_status' => $membership->validation_status,
                'is_renewal' => (bool)($membership->is_renewal ?? false),
                'previous_membership_id' => $membership->previous_membership_id ?? null,
                
                'has_qr_code' => !empty($membership->qr_token),
                'qr_url' => $qr_url, // 🎯 URL pentru afișare QR în frontend
                'created_at' => $membership->created_at
            ];
        }
        
        // Cache rezultatul pentru 5 minute
        wp_cache_set($cache_key, $result, 'membership_core_engine', OC_CACHE_INTERVAL);
        
        return $result;
    }
    
    /**
     * 🎯 Obține TOATE membership-urile din sistem (pentru admin view) - include PENDING + ACTIVE
     * 
     * @since 1.3.0 - Include și pending pentru transparență administrativă
     */
    public function get_all_active_memberships(): array {
        global $wpdb;
        
        $cache_key = 'all_memberships_v13';
        $cached = wp_cache_get($cache_key, 'membership_core_engine');
        if ($cached !== false) {
            return $cached;
        }
        
        $table_name = $this->db->get_table_name('membership_validations');
        
        // 🎯 v1.3.0: Include ACTIVE + PENDING + EXPIRED (fără limită de timp)
        
        $query = "SELECT m.*, 
                         COALESCE(u.display_name, 'Guest User') as display_name,
                         COALESCE(u.user_email, '') as user_email,
                         p.post_title as product_name,
                         v.post_title as variation_name
                  FROM {$table_name} m
                  LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID AND m.user_id > 0
                  LEFT JOIN {$wpdb->posts} p ON m.product_id = p.ID
                  LEFT JOIN {$wpdb->posts} v ON m.variation_id = v.ID
                  WHERE m.validation_status IN ('active', 'pending', 'expired')
                  AND m.variation_id > 0
                  ORDER BY 
                    CASE m.validation_status
                      WHEN 'pending' THEN 1
                      WHEN 'active' THEN 2
                      WHEN 'expired' THEN 3
                      ELSE 4
                    END,
                    m.user_id DESC, 
                    m.created_at DESC 
                  LIMIT 200";
        
        $memberships = $wpdb->get_results($query);
        
        if (empty($memberships)) {
            wp_cache_set($cache_key, [], 'membership_core_engine', OC_CACHE_INTERVAL);
            return [];
        }
        
        // Preload product data
        $product_ids = array_unique(array_column($memberships, 'product_id'));
        $products_cache = [];
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            $products_cache[$id] = $product ? $product->get_name() : 'Unknown Product';
        }
        
        $result = [];
        foreach ($memberships as $membership) {
            // 🎯 Obține QR URL din pool_metadata JSON
            $qr_url = '';
            if (!empty($membership->pool_metadata)) {
                $pool_metadata = json_decode($membership->pool_metadata, true);
                if (isset($pool_metadata['qr_filename']) && !empty($pool_metadata['qr_filename'])) {
                    $upload_dir = wp_upload_dir();
                    $qr_url = $upload_dir['baseurl'] . '/membership-qr-codes/' . $pool_metadata['qr_filename'];
                }
            }
            
            $result[] = [
                'validation_id' => (int)$membership->id,
                'user_id' => (int)$membership->user_id,
                'user_name' => $resolved_user_name,
                'user_email' => $membership->user_email ?: '',
                'product_id' => (int)$membership->product_id,
                'product_name' => $products_cache[$membership->product_id] ?? 'Unknown Product',
                'variation_id' => (int)$membership->variation_id,
                'variation_name' => $membership->variation_name ?? '',
                'sessions_total' => (int)$membership->total_sessions,
                'sessions_used' => (int)$membership->used_sessions,
                'sessions_remaining' => (int)$membership->total_sessions - (int)$membership->used_sessions,
                
                // 🎯 v1.3.0: Date renewal system
                'start_date' => $membership->start_date,
                'expires_at' => $membership->expiration_date,
                'expiration_date' => $membership->expiration_date, // Alias
                'duration_days' => (int)($membership->duration_days ?? 28),
                'validation_status' => $membership->validation_status,
                'is_renewal' => (bool)($membership->is_renewal ?? false),
                'previous_membership_id' => $membership->previous_membership_id ?? null,
                
                'has_qr_code' => !empty($membership->qr_token),
                'qr_url' => $qr_url, // 🎯 URL pentru afișare QR în frontend
                'created_at' => $membership->created_at
            ];
        }
        
        // Cache rezultatul pentru 2 minute (mai scurt pentru admin)
        wp_cache_set($cache_key, $result, 'membership_core_engine', 120);
        
        return $result;
    }
    
    /**
     * Cleanup automată membership-uri expirate
     */
    public function cleanup_expired_memberships(): void {
        $this->db->sync_membership_statuses();
        $this->qr_system->cleanup_expired_qr_codes();
    }
    
    /**
     * ⚡ INSTANT ACTIVATION: Activează membership-uri pending care au atins start_date
     * 
     * Această funcție rulează la FIECARE citire de memberships pentru a asigura
     * că status-urile sunt actualizate INSTANT, fără să aștepte cron job-ul zilnic.
     * 
     * IMPORTANT: Aceasta este implementarea PRINCIPALĂ de activare - cron job-ul 
     * este doar BACKUP pentru edge cases!
     * 
     * @since 1.3.2 - Real-time activation (no cron delay)
     */
    private function activate_pending_memberships_instant(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        $today = oc_membership_current_business_date();
        
        // ⚡ INSTANT CHECK: Verifică dacă există pending care trebuie activate ACUM
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE validation_status = 'pending' 
             AND start_date <= %s",
            $today
        ));
        
        // Performance: Dacă nu există pending, skip procesare
        if ($pending_count == 0) {
            return;
        }
        
        // Găsește toate membership-urile pending care trebuie activate ACUM
        $pending_memberships = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, variation_id, start_date, expiration_date
             FROM {$table_name}
             WHERE validation_status = 'pending'
             AND start_date <= %s
             ORDER BY start_date ASC",
            $today
        ));
        
        foreach ($pending_memberships as $membership) {
            // Resetează ore din course_hours_config
            if (method_exists($this->db, 'reset_membership_hours_on_activation')) {
                $this->db->reset_membership_hours_on_activation($membership->id);
            }
            
            // ⚡ INSTANT UPDATE: pending → active ACUM!
            $wpdb->update(
                $table_name,
                [
                    'validation_status' => 'active',
                    'activated_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $membership->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            // Invalidare cache pentru refresh UI instant
            if (method_exists($this->db, 'invalidate_membership_cache')) {
                $this->db->invalidate_membership_cache($membership->user_id);
            }
            
            // Generează QR code pentru noul membership activ
            if ($this->qr_system) {
                $qr_token = bin2hex(random_bytes(32));
                $this->qr_system->generate_qr_code($membership->id, $qr_token);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '⚡ [INSTANT Activation] Membership #%d activated NOW: user=%d, start_date=%s',
                    $membership->id,
                    $membership->user_id,
                    $membership->start_date
                ));
            }
        }
    }

    /**
     * Compat layer used by admin/manual flows.
     *
     * @param array $membership Membership row as associative array
     * @return array Validation result without consuming a session
     */
    public function validate_membership(array $membership): array {
        $membership_id = (int) ($membership['id'] ?? 0);
        if ($membership_id <= 0) {
            return [
                'success' => false,
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'error' => 'Membership invalid.',
                'message' => 'Membership invalid.'
            ];
        }

        $hydrated = $this->get_membership_by_id($membership_id);
        if (!$hydrated) {
            return [
                'success' => false,
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'error' => 'Membership not found.',
                'message' => 'Membership not found.'
            ];
        }

        return $this->build_validation_result($hydrated);
    }

    /**
     * Compat layer used by admin/manual flows.
     *
     * @param string $access_code Membership access code
     * @return array Validation + consume result
     */
    public function validate_access_code(string $access_code): array {
        $access_code = sanitize_text_field($access_code);
        if ($access_code === '') {
            return [
                'success' => false,
                'code' => 'MISSING_ACCESS_CODE',
                'error' => 'Access code is required.',
                'message' => 'Access code is required.'
            ];
        }

        $membership = $this->find_membership_by_access_code($access_code);
        if (!$membership) {
            return [
                'success' => false,
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'error' => 'No membership found for this access code.',
                'message' => 'No membership found for this access code.'
            ];
        }

        return $this->check_in_user((int) $membership->user_id, [
            'source' => 'manual_access_code',
            'validation_method' => 'access_code'
        ]);
    }

    /**
     * Shared validation engine for all app clients (web/mobile).
     */
    public function validate_user_membership(int $user_id, array $context = []): array {
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'valid' => false,
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found.',
                'status' => 'none',
                'has_membership' => false
            ];
        }

        $smart = $this->get_smart_validation_service();
        $resolved_user_name = oc_membership_resolve_user_display_name($user);
        if ($smart) {
            $preview = $smart->preview_validation($user_id);
            $membership = is_array($preview['membership'] ?? null) ? $preview['membership'] : [];

            // Smart preview only inspects active running courses.
            // If it cannot attach membership details, resolve latest real membership
            // so app UI can show correct pending/expired status instead of "none".
            if (empty($membership) && empty($preview['valid'])) {
                $fallback_membership = $this->find_membership_for_user($user_id);
                if ($fallback_membership) {
                    $fallback = $this->build_validation_result($fallback_membership);
                    $fallback['user_name'] = oc_membership_resolve_user_display_name($user, $fallback_membership);
                    $fallback['has_membership'] = true;

                    if ((string) ($fallback['status'] ?? '') === 'pending') {
                        $fallback['code'] = 'MEMBERSHIP_PENDING_ACTIVATION';
                        $fallback['message'] = 'Abonamentul necesita activare.';
                    } elseif ((string) ($fallback['status'] ?? '') === 'expired') {
                        $fallback['code'] = 'MEMBERSHIP_EXPIRED';
                        $fallback['message'] = 'Abonament expirat.';
                    } else {
                        $fallback['code'] = (string) ($preview['code'] ?? ($fallback['code'] ?? 'VALIDATION_FAILED'));
                        $fallback['message'] = (string) ($preview['message'] ?? ($fallback['message'] ?? 'Membership validation failed.'));
                    }

                    return $fallback;
                }
            }

            $product_name = $this->resolve_membership_subscription_name($membership);
            $variation_name = (string) ($membership['variation_name'] ?? '');

            if ($variation_name === '' && !empty($membership['variation_id'])) {
                $variation = wc_get_product((int) $membership['variation_id']);
                if ($variation) {
                    $variation_name = (string) $variation->get_name();
                }
            }

            return [
                'valid' => (bool) ($preview['valid'] ?? false),
                'code' => (string) ($preview['code'] ?? 'VALIDATION_FAILED'),
                'message' => (string) ($preview['message'] ?? 'Membership validation failed.'),
                'user_name' => oc_membership_resolve_user_display_name($user, $membership),
                'membership_id' => (int) ($membership['id'] ?? 0),
                'product_id' => (int) ($membership['product_id'] ?? 0),
                'product_name' => $product_name,
                'variation_name' => $variation_name,
                'product_display_name' => $this->compose_product_display_name($product_name, $variation_name),
                'payment_method' => $this->resolve_membership_payment_method($membership),
                'sessions_remaining' => (int) ($membership['remaining_sessions'] ?? 0),
                'sessions_total' => (int) ($membership['sessions_allocated'] ?? 0),
                'sessions_used' => (int) ($membership['used_sessions'] ?? 0),
                'is_unlimited' => (bool) ((int) ($membership['is_unlimited'] ?? 0) === 1),
                'expires_at' => $membership['expiration_date'] ?? null,
                'status' => !empty($membership)
                    ? $this->normalize_membership_status((string) ($membership['validation_status'] ?? 'none'), (int) ($membership['remaining_sessions'] ?? 0))
                    : 'none',
                'has_membership' => (bool) ($preview['has_membership'] ?? false)
            ];
        }

        $membership = $this->find_membership_for_user($user_id);
        if (!$membership) {
            return [
                'valid' => false,
                'code' => 'MEMBERSHIP_NOT_FOUND',
                'message' => 'No valid membership found for this user.',
                'status' => 'none',
                'user_name' => $resolved_user_name,
                'has_membership' => false
            ];
        }

        $result = $this->build_validation_result($membership);
        $result['user_name'] = oc_membership_resolve_user_display_name($user, $membership);

        return $result;
    }

    /**
     * Shared check-in engine for all app clients (web/mobile).
     */
    public function check_in_user(int $user_id, array $context = []): array {
        $smart = $this->get_smart_validation_service();
        if ($smart) {
            $result = $smart->validate_and_consume($user_id);
            if (empty($result['success'])) {
                return [
                    'success' => false,
                    'code' => (string) ($result['code'] ?? 'CHECKIN_FAILED'),
                    'message' => (string) ($result['message'] ?? 'Check-in failed.')
                ];
            }

            $membership = is_array($result['first_membership'] ?? null) ? $result['first_membership'] : [];
            return [
                'success' => true,
                'code' => 'CHECK_IN_OK',
                'message' => (string) ($result['message'] ?? 'Check-in successful.'),
                'membership_id' => (int) ($membership['id'] ?? 0),
                'sessions_remaining' => (int) ($membership['remaining_sessions'] ?? 0),
                'sessions_total' => (int) ($membership['total_sessions'] ?? 0),
                'sessions_used' => (int) ($membership['used_sessions'] ?? 0),
                'status' => $this->normalize_membership_status((string) ($membership['validation_status'] ?? 'active'), (int) ($membership['remaining_sessions'] ?? 0)),
                'expires_at' => $membership['expiration_date'] ?? null,
                'validated_count' => (int) ($result['validated_count'] ?? 0),
                'validated_courses' => is_array($result['validated_courses'] ?? null) ? $result['validated_courses'] : [],
                'skipped_courses' => is_array($result['skipped_courses'] ?? null) ? $result['skipped_courses'] : [],
            ];
        }

        $membership = $this->find_active_membership_for_checkin($user_id);
        if (!$membership) {
            return [
                'success' => false,
                'code' => 'CHECKIN_NOT_ALLOWED',
                'message' => 'No active membership available for check-in.'
            ];
        }

        $rule_error = $this->evaluate_membership_rules($membership);
        if ($rule_error !== null) {
            return [
                'success' => false,
                'code' => strtoupper($rule_error['code']),
                'message' => $rule_error['message'],
                'membership_id' => (int) $membership->id
            ];
        }

        $processed = $this->qr_system->process_validation((int) $membership->id, array_merge($context, [
            'endpoint' => $context['endpoint'] ?? 'check-in',
            'consumed' => true
        ]));

        if (!$processed) {
            return [
                'success' => false,
                'code' => 'CHECKIN_FAILED',
                'message' => 'Check-in failed. Membership may have no sessions left.',
                'membership_id' => (int) $membership->id
            ];
        }

        $updated = $this->get_membership_by_id((int) $membership->id);

        return [
            'success' => true,
            'code' => 'CHECK_IN_OK',
            'message' => 'Check-in successful.',
            'membership_id' => (int) $membership->id,
            'sessions_remaining' => (int) ($updated->remaining_sessions ?? 0),
            'sessions_total' => (int) ($updated->total_sessions ?? 0),
            'sessions_used' => (int) ($updated->used_sessions ?? 0),
            'status' => $this->normalize_membership_status((string) ($updated->validation_status ?? 'active'), (int) ($updated->remaining_sessions ?? 0)),
            'expires_at' => $updated->expiration_date ?? null
        ];
    }

    private function get_smart_validation_service(): ?OC_Membership_Smart_Validation_Service {
        return $this->smart_validation_service;
    }

    private function build_validation_result(object $membership): array {
        $status = $this->normalize_membership_status((string) ($membership->validation_status ?? 'none'), (int) ($membership->remaining_sessions ?? 0));
        $product_name = $this->resolve_membership_subscription_name($membership);
        $variation_name = '';

        if (!empty($membership->variation_name)) {
            $variation_name = (string) $membership->variation_name;
        } elseif (!empty($membership->variation_id)) {
            $variation = wc_get_product((int) $membership->variation_id);
            if ($variation) {
                $variation_name = (string) $variation->get_name();
            }
        }

        $base = [
            'valid' => $status === 'active',
            'code' => 'OK',
            'message' => $this->get_status_message($status),
            'membership_id' => (int) ($membership->id ?? 0),
            'product_id' => (int) ($membership->product_id ?? 0),
            'product_name' => $product_name,
            'variation_name' => $variation_name,
            'product_display_name' => $this->compose_product_display_name($product_name, $variation_name),
            'payment_method' => $this->resolve_membership_payment_method($membership),
            'sessions_remaining' => (int) ($membership->remaining_sessions ?? 0),
            'sessions_total' => (int) ($membership->total_sessions ?? 0),
            'sessions_used' => (int) ($membership->used_sessions ?? 0),
            'is_unlimited' => (bool) ($membership->is_unlimited ?? false),
            'expires_at' => $membership->expiration_date ?? null,
            'status' => $status,
            'has_membership' => true
        ];

        if ($status !== 'active') {
            $base['valid'] = false;
            return $base;
        }

        $rule_error = $this->evaluate_membership_rules($membership);
        if ($rule_error !== null) {
            $base['valid'] = false;
            $base['code'] = strtoupper($rule_error['code']);
            $base['message'] = $rule_error['message'];
            $base['status'] = 'restricted';
        }

        return $base;
    }

    /**
     * Resolve the purchased subscription/package name shown as "Abonament" in UI.
     * Priority: cached DB value -> main Woo order item (non-variation) -> product fallback.
     *
     * @param array<string,mixed>|object $membership
     */
    private function resolve_membership_subscription_name(array|object $membership): string {
        $cached_product_name = '';
        $order_id = 0;
        $product_id = 0;

        if (is_array($membership)) {
            $cached_product_name = trim((string) ($membership['product_name'] ?? ''));
            $order_id = (int) ($membership['order_id'] ?? 0);
            $product_id = (int) ($membership['product_id'] ?? 0);
        } else {
            $cached_product_name = trim((string) ($membership->product_name ?? ''));
            $order_id = (int) ($membership->order_id ?? 0);
            $product_id = (int) ($membership->product_id ?? 0);
        }

        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    if ((int) $item->get_variation_id() === 0 && (float) $item->get_total() > 0) {
                        $product = $item->get_product();
                        $name = $product ? trim((string) $product->get_name()) : trim((string) $item->get_name());
                        if ($name !== '') {
                            return $name;
                        }
                    }
                }

                foreach ($order->get_items() as $item) {
                    if ((int) $item->get_variation_id() === 0) {
                        $product = $item->get_product();
                        $name = $product ? trim((string) $product->get_name()) : trim((string) $item->get_name());
                        if ($name !== '') {
                            return $name;
                        }
                    }
                }
            }
        }

        if ($cached_product_name !== '' && strtoupper($cached_product_name) !== 'N/A') {
            return $cached_product_name;
        }

        if ($product_id > 0) {
            $product = wc_get_product($product_id);
            if ($product) {
                $name = trim((string) $product->get_name());
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'Abonament #' . max(0, $order_id > 0 ? $order_id : $product_id);
    }

    private function compose_product_display_name(string $product_name, string $variation_name): string {
        $base = trim($product_name);
        $variation = trim($variation_name);

        if ($base === '') {
            return $variation;
        }
        if ($variation === '') {
            return $base;
        }
        if (stripos($base, $variation) !== false) {
            return $base;
        }

        return sprintf('%s - %s', $base, $variation);
    }

    /**
     * Resolve payment method for API clients using stored membership value
     * with WooCommerce order fallback, and normalize 7CARD/ESX keys.
     *
     * @param array<string,mixed>|object $membership
     */
    private function resolve_membership_payment_method(array|object $membership): string {
        $raw_method = '';
        $order_id = 0;

        if (is_array($membership)) {
            $raw_method = (string) ($membership['payment_method'] ?? '');
            $order_id = (int) ($membership['order_id'] ?? 0);
        } else {
            $raw_method = (string) ($membership->payment_method ?? '');
            $order_id = (int) ($membership->order_id ?? 0);
        }

        $normalized = $this->normalize_payment_method_key($raw_method, '');
        if ($normalized !== '') {
            return $normalized;
        }

        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                $from_order = $this->normalize_payment_method_key(
                    (string) $order->get_payment_method(),
                    (string) $order->get_payment_method_title()
                );
                if ($from_order !== '') {
                    return $from_order;
                }

                return trim((string) $order->get_payment_method());
            }
        }

        return trim($raw_method);
    }

    private function normalize_payment_method_key(string $payment_method_id, string $payment_method_title): string {
        $normalize = static function (string $value): string {
            if ($value === '') {
                return '';
            }

            return function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
        };

        $method_id = $normalize(trim($payment_method_id));
        $method_title = $normalize(trim($payment_method_title));

        if (
            $method_id === 'oc_7card' ||
            strpos($method_id, '7card') !== false ||
            strpos($method_title, '7card') !== false
        ) {
            return 'oc_7card';
        }

        if (
            $method_id === 'oc_esx' ||
            strpos($method_id, 'esx') !== false ||
            strpos($method_title, 'esx') !== false
        ) {
            return 'oc_esx';
        }

        return '';
    }

    private function get_status_message(string $status): string {
        return match ($status) {
            'active' => 'Abonament activ si valabil.',
            'pending' => 'Abonament in asteptare.',
            'expired' => 'Abonament expirat.',
            default => 'Status abonament necunoscut.'
        };
    }

    private function normalize_membership_status(string $status, int $sessions_remaining): string {
        if ($status === 'active' && $sessions_remaining <= 0) {
            return 'expired';
        }

        return $status;
    }

    private function evaluate_membership_rules(object $membership): ?array {
        $is_unlimited = (int) ($membership->is_unlimited ?? 0) === 1;
        $sessions_remaining = (int) ($membership->remaining_sessions ?? 0);
        $expiration_date = (string) ($membership->expiration_date ?? '');
        $last_validation_date = (string) ($membership->last_validation_date ?? '');
        $validation_restriction = (string) ($membership->validation_restriction ?? 'none');
        if ($validation_restriction === '' || $validation_restriction === 'none' || $validation_restriction === 'unlimited') {
            $validation_restriction = (string) get_option('oc_membership_validation_restriction', 'none');
        }

        if (!$is_unlimited && $sessions_remaining <= 0) {
            return ['code' => 'membership_expired', 'message' => 'Abonament expirat: nu mai sunt sedinte disponibile.'];
        }

        if (oc_membership_is_expired($expiration_date)) {
            return ['code' => 'membership_expired', 'message' => 'Abonament expirat.'];
        }

        $today = oc_membership_current_business_date();

        // First source of truth: direct membership marker updated on successful consume.
        // This avoids false negatives when log metadata format differs.
        if (($validation_restriction === 'once_per_day' || $validation_restriction === 'once_per_session')
            && $this->is_same_local_day($last_validation_date, $today)) {
            return ['code' => 'already_validated_today', 'message' => 'Abonamentul a fost deja validat astazi.'];
        }

        $today_count = $this->get_today_consumed_validations_count((int) $membership->id, $today);

        if (($validation_restriction === 'once_per_day' || $validation_restriction === 'once_per_session') && $today_count > 0) {
            return ['code' => 'already_validated_today', 'message' => 'Abonamentul a fost deja validat astazi.'];
        }

        $max_daily_validations = (int) ($membership->max_daily_validations ?? 0);
        if ($max_daily_validations > 0 && $today_count >= $max_daily_validations) {
            return ['code' => 'daily_limit_reached', 'message' => 'Limita zilnica de validari a fost atinsa.'];
        }

        $allowed_days = (string) ($membership->allowed_days_of_week ?? '');
        if ($allowed_days !== '') {
            $decoded_days = json_decode($allowed_days, true);
            if (is_array($decoded_days) && !empty($decoded_days)) {
                $today_weekday = (int) current_time('N');
                $allowed_weekdays = array_map('intval', $decoded_days);
                if (!in_array($today_weekday, $allowed_weekdays, true)) {
                    return ['code' => 'day_not_allowed', 'message' => 'Abonamentul nu poate fi validat in aceasta zi.'];
                }
            }
        }

        return null;
    }

    private function is_same_local_day(string $datetime, string $day): bool {
        $datetime = trim($datetime);
        if ($datetime === '' || $day === '') {
            return false;
        }

        try {
            $timezone = wp_timezone();
            $dt = new DateTimeImmutable($datetime, $timezone);
            return $dt->format('Y-m-d') === $day;
        } catch (Exception $exception) {
            return false;
        }
    }

    private function find_membership_for_user(int $user_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validations';
                $today = oc_membership_current_business_date();

                $this->db->sync_membership_statuses($user_id);

        $active = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND validation_status = 'active'
                             AND (expiration_date IS NULL OR expiration_date >= %s)
               AND (remaining_sessions > 0 OR is_unlimited = 1)
             ORDER BY expiration_date DESC
             LIMIT 1",
                        $user_id,
                        $today
        ));
        if ($active) {
            return $active;
        }

        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND validation_status = 'pending'
             ORDER BY created_at DESC
             LIMIT 1",
            $user_id
        ));
        if ($pending) {
            return $pending;
        }

        $expired = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND validation_status = 'expired'
             ORDER BY expiration_date DESC, updated_at DESC
             LIMIT 1",
            $user_id
        ));

        return $expired ?: null;
    }

    private function find_active_membership_for_checkin(int $user_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validations';
                $today = oc_membership_current_business_date();

                $this->db->sync_membership_statuses($user_id);

        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND validation_status = 'active'
                             AND (expiration_date IS NULL OR expiration_date >= %s)
               AND (remaining_sessions > 0 OR is_unlimited = 1)
             ORDER BY expiration_date DESC
             LIMIT 1",
                        $user_id,
                        $today
        ));

        return $membership ?: null;
    }

    private function get_membership_by_id(int $membership_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validations';

        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            $membership_id
        ));

        return $membership ?: null;
    }

    private function find_membership_by_access_code(string $access_code): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validations';

        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE access_code = %s
             ORDER BY created_at DESC
             LIMIT 1",
            $access_code
        ));

        return $membership ?: null;
    }

    private function get_today_consumed_validations_count(int $membership_id, string $today): int {
        global $wpdb;
        $log_table = $wpdb->prefix . 'membership_validation_log';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$log_table}
             WHERE membership_id = %d
               AND validation_status = 'success'
               AND DATE(validation_date) = %s
               AND (
                    validation_method <> 'api'
                    OR validation_metadata LIKE %s
                    OR validation_metadata LIKE %s
               )",
            $membership_id,
            $today,
            '%\"endpoint\":\"check-in\"%',
            '%\"consumed\":true%'
        ));

        return max(0, $count);
    }
}