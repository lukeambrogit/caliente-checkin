<?php
/**
 * Membership Validator - Helper Functions
 * 
 * @package MembershipValidator
 * @subpackage Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obține instanța principală a Membership Validator
 */
function oc_get_membership_validator(): ?OC_Membership_Validator {
    // Verifică dacă clasa există și e înregistrată
    if (!class_exists('OC_Membership_Validator')) {
        return null;
    }
    
    // Verifică dacă ADD-ON-ul e activ
    if (!class_exists('OC_Addon_Manager') || !OC_Addon_Manager::is_addon_active('membership_core_engine')) {
        return null;
    }
    
    return OC_Membership_Validator::get_instance();
}

/**
 * Verifică dacă un produs are abonament activat
 * Citește NON-INTRUZIV din sisteme existente
 */
function oc_is_membership_product(int $product_id, int $variation_id = 0): bool {
    $search_id = $variation_id > 0 ? $variation_id : $product_id;
    
    // Verifică Pool Product Manager
    $pool_enabled = get_post_meta($search_id, '_oc_pool_enabled', true);
    
    // Verifică setări membership specifice
    $membership_enabled = get_post_meta($search_id, '_oc_membership_enabled', true);
    
    return $pool_enabled === 'yes' || $membership_enabled === 'yes';
}

/**
 * Obține membership-urile active pentru un utilizator
 */
function oc_get_user_active_memberships(int $user_id): array {
    $validator = oc_get_membership_validator();
    if (!$validator) {
        return [];
    }
    
    return $validator->get_db()->get_user_memberships($user_id, 'active');
}

/**
 * Normalizează o valoare text folosită pentru afișare/căutare.
 */
function oc_membership_normalize_text_candidate($value): string {
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    return is_string($value) ? $value : '';
}

/**
 * Rezolvă numele afișat unitar pentru membership-uri și fluxurile publice.
 */
function oc_membership_resolve_user_display_name($user = null, $membership_data = null, $order = null): string {
    if (is_numeric($user)) {
        $user = get_userdata((int) $user);
    }

    if (is_numeric($order)) {
        $order = wc_get_order((int) $order);
    }

    $membership_array = [];
    if (is_array($membership_data)) {
        $membership_array = $membership_data;
    } elseif (is_object($membership_data)) {
        $membership_array = get_object_vars($membership_data);
    }

    if (!$order && !empty($membership_array['order_id'])) {
        $order = wc_get_order((int) $membership_array['order_id']);
    }

    $username = '';
    $profile_display_name = '';
    $profile_full_name = '';
    $billing_full_name = '';

    if ($user instanceof WP_User) {
        $username = oc_membership_normalize_text_candidate($user->user_login ?? '');
        $profile_display_name = oc_membership_normalize_text_candidate($user->display_name ?? '');
        $profile_full_name = oc_membership_normalize_text_candidate(trim(
            (string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? '')
        ));
        $billing_full_name = oc_membership_normalize_text_candidate(trim(
            (string) get_user_meta($user->ID, 'billing_first_name', true) . ' ' .
            (string) get_user_meta($user->ID, 'billing_last_name', true)
        ));
    }

    $order_full_name = '';
    if ($order instanceof WC_Order) {
        $order_full_name = oc_membership_normalize_text_candidate(trim(
            (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name()
        ));
    }

    $cached_display_name = oc_membership_normalize_text_candidate($membership_array['display_name'] ?? '');

    $preferred_candidates = [
        $profile_full_name,
        $billing_full_name,
        $order_full_name,
        $profile_display_name,
        $cached_display_name,
    ];

    foreach ($preferred_candidates as $candidate) {
        if ($candidate !== '' && ($username === '' || strtolower($candidate) !== strtolower($username))) {
            return $candidate;
        }
    }

    foreach ($preferred_candidates as $candidate) {
        if ($candidate !== '') {
            return $candidate;
        }
    }

    if ($username !== '') {
        return $username;
    }

    return !empty($membership_array['user_id']) ? 'Utilizator' : 'Guest User';
}

/**
 * Sincronizează statusul WooCommerce al unei comenzi create din plugin cu statusul membership-urilor aferente.
 */
function oc_membership_sync_plugin_order_state(int $order_id, ?string $requested_payment_status = null, array $args = []): ?string {
    if ($order_id <= 0 || !function_exists('wc_get_order')) {
        return null;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return null;
    }

    $args = wp_parse_args($args, [
        'process_membership' => false,
        'pending_note' => '',
        'completed_note' => '',
    ]);

    $validator = oc_get_membership_validator();
    if (!$validator || !$validator->get_db()) {
        return $order->get_status();
    }

    if (!empty($args['process_membership']) && method_exists($validator, 'process_new_membership')) {
        $validator->process_new_membership($order_id);
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
    }

    global $wpdb;

    $table_name = $validator->get_db()->get_table_name('membership_validations');
    $normalized_payment_status = $requested_payment_status !== null
        ? sanitize_key((string) $requested_payment_status)
        : '';

    if ($normalized_payment_status !== '') {
        $wpdb->update(
            $table_name,
            [
                'payment_status' => $normalized_payment_status,
                'updated_at' => current_time('mysql'),
                'cached_data_synced_at' => current_time('mysql'),
            ],
            ['order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        $order->update_meta_data('_oc_requested_payment_status', $normalized_payment_status);
        $order->save();
    }

    $memberships = $wpdb->get_results($wpdb->prepare(
        "SELECT validation_status, payment_status FROM {$table_name} WHERE order_id = %d",
        $order_id
    ), ARRAY_A);

    if (empty($memberships)) {
        return $order->get_status();
    }

    $has_active_membership = false;
    $has_pending_membership = false;
    $has_paid_membership = false;

    foreach ($memberships as $membership) {
        $validation_status = sanitize_key((string) ($membership['validation_status'] ?? ''));
        $payment_status = sanitize_key((string) ($membership['payment_status'] ?? ''));

        if ($validation_status === 'active') {
            $has_active_membership = true;
        }

        if ($validation_status === 'pending') {
            $has_pending_membership = true;
        }

        if ($payment_status === 'paid') {
            $has_paid_membership = true;
        }
    }

    $target_status = null;
    $target_note = '';

    if ($has_pending_membership || !$has_active_membership) {
        $target_status = 'on-hold';
        $target_note = (string) $args['pending_note'];
    } elseif ($has_active_membership && $has_paid_membership) {
        $target_status = 'completed';
        $target_note = (string) $args['completed_note'];
    }

    if ($target_status === null) {
        return $order->get_status();
    }

    $current_status = $order->get_status();
    if ($current_status === $target_status || in_array($current_status, ['cancelled', 'failed', 'refunded'], true)) {
        return $current_status;
    }

    $order->update_status($target_status, $target_note, true);

    return $target_status;
}

/**
 * Validează un QR token
 */
function oc_validate_membership_qr(string $qr_token): array {
    $validator = oc_get_membership_validator();
    if (!$validator) {
        return [
            'success' => false,
            'error' => 'Membership validator not available'
        ];
    }
    
    $qr_system = $validator->get_qr_system();
    $membership_validator = $validator->get_validator();
    if (!$qr_system || !$membership_validator) {
        return [
            'success' => false,
            'code' => 'SYSTEM_ERROR',
            'message' => 'QR validation system not available'
        ];
    }

    $qr_validation = $qr_system->validate_qr_token($qr_token, [
        'source' => 'helper_function',
        'validation_method' => 'qr_token'
    ]);

    if (!$qr_validation || empty($qr_validation['user_id'])) {
        return [
            'success' => false,
            'code' => 'INVALID_QR',
            'message' => 'QR code invalid sau expirat.'
        ];
    }

    $result = $membership_validator->check_in_user((int) $qr_validation['user_id'], [
        'source' => 'helper_function',
        'validation_method' => 'qr_token'
    ]);

    if (empty($result['success'])) {
        return $result;
    }

    return array_merge($result, [
        'user_id' => (int) $qr_validation['user_id'],
        'user_name' => (string) ($qr_validation['user_name'] ?? ''),
        'photo_url' => (string) ($qr_validation['photo_url'] ?? ''),
        'product_name' => (string) ($qr_validation['product_name'] ?? ''),
        'validated_at' => current_time('mysql')
    ]);
}

/**
 * Obține URL-ul QR code pentru un membership
 */
function oc_get_membership_qr_url(int $membership_id): ?string {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'membership_validations';
    
    $qr_url = $wpdb->get_var($wpdb->prepare("
        SELECT qr_code_url
        FROM {$table_name}
        WHERE id = %d
    ", $membership_id));
    
    return $qr_url ?: null;
}

/**
 * Generează link pentru descărcarea QR code
 */
function oc_get_qr_download_link(int $membership_id): string {
    return wp_nonce_url(
        add_query_arg([
            'action' => 'oc_download_qr',
            'membership_id' => $membership_id
        ], admin_url('admin-ajax.php')),
        'oc_download_qr',
        'nonce'
    );
}

/**
 * Returnează data și ora locală curentă în timezone-ul WordPress.
 */
function oc_membership_current_local_datetime(): DateTimeImmutable {
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        return new DateTimeImmutable(current_time('mysql'), $timezone);
    } catch (Exception $exception) {
        return new DateTimeImmutable('now', $timezone);
    }
}

/**
 * Returnează data curentă de business în timezone-ul WordPress,
 * în format intern ISO Y-m-d pentru comparații și stocare.
 */
function oc_membership_current_business_date(): string {
    return oc_membership_current_local_datetime()->format('Y-m-d');
}

/**
 * Normalizează o dată la formatul Y-m-d.
 */
function oc_membership_normalize_date_value(?string $date_value): string {
    $date_value = trim((string) $date_value);
    if ($date_value === '') {
        return '';
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        $date = new DateTimeImmutable($date_value, $timezone);
        return $date->format('Y-m-d');
    } catch (Exception $exception) {
        $timestamp = strtotime($date_value);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y-m-d', $timestamp, $timezone);
    }
}

/**
 * Verifică expirarea folosind doar ziua calendaristică.
 */
function oc_membership_is_expired(?string $expiration_date, ?string $current_date = null): bool {
    $normalized_expiration_date = oc_membership_normalize_date_value($expiration_date);
    if ($normalized_expiration_date === '') {
        return false;
    }

    $business_date = $current_date ?: oc_membership_current_business_date();

    return $normalized_expiration_date < $business_date;
}

/**
 * Returnează numărul de zile calendaristice până la expirare.
 */
function oc_membership_days_until_expiry(?string $expiration_date, ?string $current_date = null): ?int {
    $normalized_expiration_date = oc_membership_normalize_date_value($expiration_date);
    if ($normalized_expiration_date === '') {
        return null;
    }

    $business_date = $current_date ?: oc_membership_current_business_date();
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        $today = new DateTimeImmutable($business_date, $timezone);
        $expiry = new DateTimeImmutable($normalized_expiration_date, $timezone);
        return (int) $today->diff($expiry)->format('%r%a');
    } catch (Exception $exception) {
        return null;
    }
}

/**
 * Rezolvă statusul agregat al unui membru pornind de la pachetele lui vizibile.
 *
 * Fiecare pachet trebuie să conțină cel puțin:
 * - status: active|pending|expired
 * - price: totalul pachetului
 * - valid_until: data maximă de expirare a pachetului
 *
 * @return array{
 *   status:string,
 *   active_packages_count:int,
 *   active_packages_total:float,
 *   has_pending_packages:bool,
 *   nearest_active_expiry:?string,
 *   has_packages:bool
 * }
 */
function oc_membership_resolve_aggregate_member_status(array $packages): array {
    $summary = [
        'status' => 'inactive',
        'active_packages_count' => 0,
        'active_packages_total' => 0.0,
        'has_pending_packages' => false,
        'nearest_active_expiry' => null,
        'has_packages' => !empty($packages),
    ];

    foreach ($packages as $package) {
        $package_status = sanitize_key((string) ($package['status'] ?? 'pending'));

        if ($package_status === 'active') {
            $summary['active_packages_count']++;
            $summary['active_packages_total'] += (float) ($package['price'] ?? 0);

            $package_valid_until = oc_membership_normalize_date_value($package['valid_until'] ?? '');
            if (
                $package_valid_until !== ''
                && ($summary['nearest_active_expiry'] === null || $package_valid_until < $summary['nearest_active_expiry'])
            ) {
                $summary['nearest_active_expiry'] = $package_valid_until;
            }

            continue;
        }

        if ($package_status === 'pending') {
            $summary['has_pending_packages'] = true;
        }
    }

    if ($summary['active_packages_count'] > 0) {
        $summary['status'] = 'active';

        if ($summary['has_pending_packages'] && $summary['nearest_active_expiry'] !== null) {
            $days_until_expiry = oc_membership_days_until_expiry($summary['nearest_active_expiry']);
            if ($days_until_expiry !== null && $days_until_expiry <= 7) {
                $summary['status'] = 'pending';
            }
        }

        return $summary;
    }

    if ($summary['has_pending_packages']) {
        $summary['status'] = 'pending';
        return $summary;
    }

    if ($summary['has_packages']) {
        $summary['status'] = 'expired';
    }

    return $summary;
}

/**
 * Formatează timpul rămas până la expirare
 */
function oc_format_membership_expiry(string $expiration_date): string {
    if (empty($expiration_date)) {
        return __('Never expires', OC_TEXT_DOMAIN);
    }

    $business_date = oc_membership_current_business_date();
    $normalized_expiration_date = oc_membership_normalize_date_value($expiration_date);

    if ($normalized_expiration_date === '') {
        return __('Never expires', OC_TEXT_DOMAIN);
    }

    if (oc_membership_is_expired($normalized_expiration_date, $business_date)) {
        return __('Expired', OC_TEXT_DOMAIN);
    }

    if ($normalized_expiration_date === $business_date) {
        return __('Expires today', OC_TEXT_DOMAIN);
    }

    $days = oc_membership_days_until_expiry($normalized_expiration_date, $business_date);
    if ($days === null) {
        return __('Unknown', OC_TEXT_DOMAIN);
    }

    if ($days > 30) {
        $months = floor($days / 30);
        return sprintf(_n('%d month', '%d months', $months, OC_TEXT_DOMAIN), $months);
    }

    if ($days > 0) {
        return sprintf(_n('%d day', '%d days', $days, OC_TEXT_DOMAIN), $days);
    }

    return __('Expires today', OC_TEXT_DOMAIN);
}

/**
 * Obține numele produsului pentru un membership
 */
function oc_get_membership_product_name(array $membership): string {
    $product_id = $membership['variation_id'] > 0 ? $membership['variation_id'] : $membership['product_id'];
    $product = wc_get_product($product_id);
    
    return $product ? $product->get_name() : __('Unknown Product', OC_TEXT_DOMAIN);
}

/**
 * Verifică dacă utilizatorul curent poate gestiona un membership
 */
function oc_can_user_manage_membership(int $membership_id, int $user_id = 0): bool {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Admin poate gestiona toate
    if (current_user_can('manage_options')) {
        return true;
    }
    
    // Verifică dacă e proprietarul
    global $wpdb;
    $table_name = $wpdb->prefix . 'membership_validations';
    
    $owner_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id
        FROM {$table_name}
        WHERE id = %d
    ", $membership_id));
    
    return $owner_id == $user_id;
}

/**
 * Obține statistici membership pentru utilizator
 */
function oc_get_user_membership_stats(int $user_id): array {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'membership_validations';
    
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as total_memberships,
            SUM(CASE WHEN validation_status = 'active' THEN 1 ELSE 0 END) as active_memberships,
            SUM(CASE WHEN validation_status = 'expired' THEN 1 ELSE 0 END) as expired_memberships,
            SUM(total_sessions) as total_sessions,
            SUM(used_sessions) as used_sessions,
            SUM(remaining_sessions) as remaining_sessions
        FROM {$table_name}
        WHERE user_id = %d
    ", $user_id), ARRAY_A);
    
    return $stats ?: [
        'total_memberships' => 0,
        'active_memberships' => 0,
        'expired_memberships' => 0,
        'total_sessions' => 0,
        'used_sessions' => 0,
        'remaining_sessions' => 0
    ];
}

/**
 * Înregistrează o validare în log
 */
function oc_log_membership_validation(int $membership_id, string $method = 'manual', array $metadata = []): bool {
    global $wpdb;
    
    $log_table = $wpdb->prefix . 'membership_validation_log';
    
    // Obține datele membership
    $membership_table = $wpdb->prefix . 'membership_validations';
    $membership = $wpdb->get_row($wpdb->prepare("
        SELECT user_id FROM {$membership_table} WHERE id = %d
    ", $membership_id));
    
    if (!$membership) {
        return false;
    }
    
    $result = $wpdb->insert($log_table, [
        'membership_id' => $membership_id,
        'user_id' => $membership->user_id,
        'validator_user_id' => get_current_user_id(),
        'validation_method' => $method,
        'validation_status' => 'success',
        'validation_date' => current_time('mysql'),
        'ip_address' => oc_get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'validation_metadata' => json_encode($metadata)
    ]);
    
    return $result !== false;
}

/**
 * Obține IP-ul clientului
 */
function oc_get_client_ip(): string {
    // Îîncredem REMOTE_ADDR întti — nu poate fi falsificat de client.
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (filter_var($remote, FILTER_VALIDATE_IP)) {
        return $remote;
    }

    // Fallback: headere proxy/load-balancer (controllabile de user, folosite doar dacă REMOTE_ADDR lipsește).
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $remote ?: '0.0.0.0';
}

/**
 * Convertește status membership în text lizibil
 */
function oc_get_membership_status_label(string $status): string {
    $labels = [
        'active' => __('Active', OC_TEXT_DOMAIN),
        'expired' => __('Expired', OC_TEXT_DOMAIN),
        'cancelled' => __('Cancelled', OC_TEXT_DOMAIN),
        'suspended' => __('Suspended', OC_TEXT_DOMAIN),
        'transferred' => __('Transferred', OC_TEXT_DOMAIN)
    ];
    
    return $labels[$status] ?? ucfirst($status);
}

/**
 * Verifică dacă un membership poate fi validat acum
 */
function oc_can_validate_membership_now(array $membership): array {
    $validator = oc_get_membership_validator();
    if (!$validator) {
        return [
            'can_validate' => false,
            'reason' => 'Validator not available'
        ];
    }
    
    $validation_result = $validator->get_validator()->validate_membership($membership);
    
    return [
        'can_validate' => $validation_result['success'],
        'reason' => $validation_result['error'] ?? ''
    ];
}

/**
 * Obține lista cursurilor disponibile pentru un membership
 * Citește NON-INTRUZIV din Schedule Manager
 */
function oc_get_membership_available_courses(array $membership): array {
    global $wpdb;
    
    $schedule_table = $wpdb->prefix . 'orar_cursuri';
    
    // Dacă membership-ul e legat de un curs specific
    if ($membership['schedule_id']) {
        $course = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$schedule_table} WHERE id = %d
        ", $membership['schedule_id']), ARRAY_A);
        
        return $course ? [$course] : [];
    }
    
    // Altfel, toate cursurile pentru produsul dat
    $courses = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$schedule_table}
        WHERE product_id = %d OR variation_id = %d
        ORDER BY weekday, start_time
    ", $membership['product_id'], $membership['variation_id']), ARRAY_A);
    
    return $courses ?: [];
}

/**
 * Generează shortcode pentru afișarea QR code
 */
function oc_membership_qr_shortcode($atts): string {
    $atts = shortcode_atts([
        'membership_id' => 0,
        'size' => 200,
        'alt_text' => 'Membership QR Code'
    ], $atts);
    
    $membership_id = intval($atts['membership_id']);
    if (!$membership_id) {
        return '<p>Invalid membership ID</p>';
    }
    
    $qr_url = oc_get_membership_qr_url($membership_id);
    if (!$qr_url) {
        return '<p>QR code not available</p>';
    }
    
    $size = intval($atts['size']);
    $alt_text = esc_attr($atts['alt_text']);
    
    return sprintf(
        '<img src="%s" alt="%s" width="%d" height="%d" class="membership-qr-code" />',
        esc_url($qr_url),
        $alt_text,
        $size,
        $size
    );
}

// Înregistrează shortcode
add_shortcode('membership_qr', 'oc_membership_qr_shortcode');

/**
 * Hook pentru cleanup periodic și activare membership-uri PENDING
 */
function oc_membership_daily_cleanup(): void {
    $validator = oc_get_membership_validator();
    if ($validator && $validator->get_db() && $validator->get_qr_system()) {
        // Cleanup token-uri expirate
        $validator->get_db()->cleanup_expired_tokens();
        
        // Cleanup QR codes expirate
        $validator->get_qr_system()->cleanup_expired_qr_codes();
        
        // ❌ DEZACTIVAT: Activare automată OPRITĂ - abonamentele se activează DOAR manual prin buton
        // 🎯 v1.4.0: VÂNZARE ÎN AVANS - Activează membership-uri PENDING când cursurile apar în orar
        // oc_activate_pending_memberships_in_schedule();
    }
}

/**
 * 🎯 v1.4.0: Activează automat membership-uri PENDING când cursurile lor apar în orar
 * 
 * Acest hook ZILNIC verifică toate membership-urile cu status PENDING și:
 * - Verifică dacă variation_id-ul lor apare acum în tabela orar_cursuri
 * - Dacă DA → Activează membership-ul (status = active, generează QR)
 * - Dacă NU → Rămâne PENDING (se va verifica mâine)
 * 
 * @since 1.4.0
 */
function oc_activate_pending_memberships_in_schedule(): void {
    global $wpdb;
    
    $validations_table = $wpdb->prefix . 'membership_validations';
    $schedule_table = $wpdb->prefix . 'orar_cursuri';
    
    // Găsește toate membership-urile PENDING
    $pending_memberships = $wpdb->get_results("
        SELECT id, variation_id, user_id, product_id
        FROM {$validations_table}
        WHERE validation_status = 'pending'
    ", ARRAY_A);
    
    if (empty($pending_memberships)) {
        oc_log_debug('[Auto-Activate] Nu există membership-uri PENDING de procesat.');
        return;
    }
    
    $activated_count = 0;
    $validator = oc_get_membership_validator();
    
    foreach ($pending_memberships as $membership) {
        $membership_id = $membership['id'];
        $variation_id = $membership['variation_id'];
        $product_id = $membership['product_id'];
        $user_id = $membership['user_id'];
        
        // Verifică dacă cursul a apărut în orar
        $in_schedule = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$schedule_table} 
             WHERE variation_id = %d OR product_id = %d",
            $variation_id, $product_id
        ));
        
        if ($in_schedule && $in_schedule > 0) {
            // 🛡️ VERIFICARE CRITICĂ: Nu activa dacă user-ul MAI ARE un curs ACTIVE cu același variation_id
            // Acest PENDING e pentru renewal queue, nu pentru "vânzare în avans"
            $has_active_same_course = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$validations_table}
                WHERE user_id = %d
                AND variation_id = %d
                AND validation_status = 'active'
                AND id != %d
            ", $user_id, $variation_id, $membership_id));
            
            if ($has_active_same_course > 0) {
                oc_log_debug(sprintf(
                    '⏳ [Auto-Activate] Membership ID=%d (variation_id=%d) rămâne PENDING - user-ul mai are un curs ACTIVE cu același ID (renewal queue)',
                    $membership_id,
                    $variation_id
                ));
                continue; // Skip - e renewal, nu vânzare în avans
            }
            
            // Cursul e acum în orar → ACTIVEAZĂ membership-ul (doar dacă NU e renewal queue)
            
            // 1. Actualizează status și dată activare
            $wpdb->update(
                $validations_table,
                [
                    'validation_status' => 'active',
                    'activated_at' => current_time('mysql'),
                    'start_date' => oc_membership_current_business_date()
                ],
                ['id' => $membership_id]
            );
            
            // 2. Generează QR code (dacă nu există)
            if ($validator && $validator->get_qr_system()) {
                // Generează QR code folosind sistemul v2.0 (user_id based)
                $qr_result = $validator->get_qr_system()->generate_qr_code($membership_id, [
                    'user_id' => $membership['user_id']
                ]);
                
                if (!$qr_result) {
                    oc_log_error('[Auto-Activate] Failed to generate QR for membership ID=' . $membership_id);
                }
            }
            
            // 3. Invalidează cache pentru user
            if ($validator && $validator->get_db()) {
                $validator->get_db()->invalidate_membership_cache($membership['user_id']);
            }
            
            $activated_count++;
            
            oc_log_debug(sprintf(
                '✅ [Auto-Activate] Membership ID=%d (variation_id=%d) ACTIVAT - cursul a apărut în orar!',
                $membership_id,
                $variation_id
            ));
        }
    }

    oc_log_debug(sprintf(
        '✅ [Auto-Activate] Procesate %d membership-uri PENDING, %d activate automat.',
        count($pending_memberships),
        $activated_count
    ));
}

// Programează cleanup zilnic
if (!wp_next_scheduled('oc_membership_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'oc_membership_daily_cleanup');
}
add_action('oc_membership_daily_cleanup', 'oc_membership_daily_cleanup');

/**
 * 🧪 HELPER ADMIN: Buton manual pentru activare PENDING memberships (pentru teste)
 * Apelează direct funcția de activare fără să aștepte CRON-ul zilnic
 */
function oc_membership_manual_activate_pending(): void {
    // Verifică dacă user-ul e admin
    if (!current_user_can('manage_options')) {
        wp_die('Acces interzis.');
    }
    
    // Verifică parametrul de activare
    if (isset($_GET['oc_activate_pending']) && $_GET['oc_activate_pending'] === 'now') {
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if ($nonce === '' || !wp_verify_nonce($nonce, 'oc_activate_pending_memberships')) {
            wp_die('Security check failed.');
        }

        oc_activate_pending_memberships_in_schedule();
        
        wp_redirect(add_query_arg([
            'page' => 'membership-validator',
            'tab' => 'dashboard',
            'activated' => 'success'
        ], admin_url('admin.php')));
        exit;
    }
}
add_action('admin_init', 'oc_membership_manual_activate_pending');

/**
 * Formatează data în format local
 */
function oc_format_membership_date(string $date): string {
    if (empty($date)) {
        return '-';
    }
    
    return wp_date(get_option('date_format'), strtotime($date));
}

/**
 * Formatează ora în format local
 */
function oc_format_membership_time(string $time): string {
    if (empty($time)) {
        return '-';
    }
    
    return wp_date(get_option('time_format'), strtotime($time));
}

/**
 * Verifică compatibilitatea cu sistemele existente
 */
function oc_check_membership_compatibility(): array {
    $checks = [
        'wordpress' => version_compare(get_bloginfo('version'), '6.2', '>='),
        'woocommerce' => class_exists('WooCommerce') && version_compare(WC_VERSION, '7.0', '>='),
        'php' => version_compare(PHP_VERSION, '8.2', '>='),
        'mysql' => true, // Verificare básica
        'schedule_manager' => true, // Verificare tabel orar_cursuri
        'pool_manager' => class_exists('OC_Pool_Product_Manager')
    ];
    
    // Verifică tabelul Schedule Manager
    global $wpdb;
    $schedule_table = $wpdb->prefix . 'orar_cursuri';
    $checks['schedule_manager'] = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $schedule_table)) === $schedule_table;
    
    return $checks;
}
