<?php
/**
 * Data Handler - REFACTORED din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR procesarea datelor din WordPress/WooCommerce
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - Păstrează toate funcționalitățile existente pentru date
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Data_Handler
 * 
 * Gestionează toate operațiunile cu datele:
 * - Procesare date din WordPress/WooCommerce  
 * - Guest users management
 * - Membership status logic
 * - Formatare utilizatori pentru tabel
 */
class OC_Membership_Data_Handler {
    
    use OC_Membership_Pricing;
    use OC_Membership_Courses;
    use OC_Membership_WooCommerce;
    
    /**
     * @var OC_Membership_DB Database handler din ADD-ON #1
     */
    private OC_Membership_DB $validator_db;

    /**
     * @var array<int, bool>
     */
    private array $request_synced_user_ids = [];
    
    /**
     * Constructor cu dependency injection
     */
    public function __construct(OC_Membership_DB $validator_db) {
        $this->validator_db = $validator_db;
    }

    private function sync_user_membership_statuses_if_needed(int $user_id): void {
        if ($user_id <= 0 || isset($this->request_synced_user_ids[$user_id])) {
            return;
        }

        if (method_exists($this->validator_db, 'sync_membership_statuses')) {
            $this->validator_db->sync_membership_statuses($user_id);
        }

        $this->request_synced_user_ids[$user_id] = true;
    }
    
    /**
     * Obține toți membrii cu WordPress Users Query
     */
    public function get_all_members_with_wp_users(
        string $search_term = '',
        int $page = 1,
        int $per_page = 20,
        ?int $filter_user_id = null,
        bool $include_all_wp_clients_for_admin = false,
        string $status_filter = 'all'
    ): array {
        // 🔒 MANUAL ACTIVATION: Activare DOAR prin buton admin (eliminat activate_pending_memberships_realtime)
        
        // Obține doar utilizatorii care au membership-uri în tabelul membership_validations
        global $wpdb;
        
        // Obține referința la DB-ul din ADD-ON #1
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return ['members' => [], 'total_found' => 0, 'total_pages' => 0, 'current_page' => $page];
        }
        
        $db = $validator->get_db();
        $table_name = $db->get_table_name('membership_validations');
        
        // 🎯 ADMIN MODE: afișează TOȚI clienții WordPress (cu sau fără abonament)
        // Doar pentru admin view; include rolurile uzuale de client.
        if ($include_all_wp_clients_for_admin) {
            $members = [];

            $client_roles = ['customer', 'subscriber', 'client'];
            $args = [
                'number' => -1,
                'orderby' => 'registered',
                'order' => 'DESC',
                'role__in' => $client_roles
            ];

            $args = $this->apply_search_candidate_ids_to_user_query_args($args, $search_term);

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            $users = $this->filter_users_by_search_term($users, $search_term);

            foreach ($users as $user) {
                $members[] = $this->format_user_for_table($user);
            }

            $status_counts = $this->calculate_members_status_counts($members);
            $members = $this->apply_status_filter($members, $status_filter);
            $total_found = count($members);

            usort($members, function($a, $b) {
                $status_a = $this->resolve_member_display_status($a);
                $status_b = $this->resolve_member_display_status($b);
                $member_id_a = (int) ($a['membership_id'] ?? 0);
                $member_id_b = (int) ($b['membership_id'] ?? 0);

                $priority_a = $this->get_status_priority($status_a, $member_id_a);
                $priority_b = $this->get_status_priority($status_b, $member_id_b);

                return $priority_a - $priority_b;
            });

            $offset = ($page - 1) * $per_page;
            $members = array_slice($members, $offset, $per_page);

            return [
                'members' => $members,
                'total_found' => $total_found,
                'total_pages' => (int)ceil($total_found / $per_page),
                'current_page' => $page,
                'status_counts' => $status_counts,
                'status_filter' => $status_filter
            ];
        }

        // 🎯 v1.3.0: Query pentru utilizatori cu membership-uri (active + pending + expired)
        // Afișăm TOȚI membrii cu abonamente, inclusiv expired fără limită de timp
        // 🔒 FILTRARE: Dacă $filter_user_id este setat, afișează doar datele acelui utilizator
        $user_filter_sql = '';
        if ($filter_user_id !== null && $filter_user_id > 0) {
            $user_filter_sql = $wpdb->prepare(" AND m.user_id = %d", $filter_user_id);
        }
        
        // variation_id > 0 = "este abonament Pool real" — se aplică DOAR în admin view
        // (multiple users). Când clientul vede PROPRIILE DATE, afișăm TOT (inclusiv
        // abonamente din produse simple cu variation_id = 0).
        $variation_filter_sql = ($filter_user_id === null) ? "AND m.variation_id > 0" : "";
        
        $user_ids_query = "
            SELECT DISTINCT m.user_id 
            FROM {$table_name} m
            WHERE m.validation_status IN ('active', 'pending', 'expired')
            {$variation_filter_sql}
            {$user_filter_sql}
        ";
        
        $user_ids_results = $wpdb->get_col($user_ids_query);
        
        if (empty($user_ids_results)) {
            return ['members' => [], 'total_found' => 0, 'total_pages' => 0, 'current_page' => $page];
        }
        
        // Separă guest users (user_id = 0) și WordPress users
        $guest_users = array_filter($user_ids_results, function($id) { return $id == 0; });
        $wp_users = array_filter($user_ids_results, function($id) { return $id > 0; });
        
        $members = [];
        
        // Procesează WordPress Users cu search (FĂRĂ paginare pentru moment)
        if (!empty($wp_users)) {
            $args = [
                'include' => $wp_users,
                'number' => -1, // Obține TOȚI users pentru sortare corectă
                'orderby' => 'registered',
                'order' => 'DESC'
            ];

            $args = $this->apply_search_candidate_ids_to_user_query_args($args, $search_term, $wp_users);

            $user_query = new WP_User_Query($args);
            $users = $user_query->get_results();

            $users = $this->filter_users_by_search_term($users, $search_term);
            
            foreach ($users as $user) {
                $members[] = $this->format_user_for_table($user);
            }
        }
        
        // Procesează Guest Users (user_id = 0) cu search
        if (!empty($guest_users)) {
            $guest_memberships = $this->get_guest_memberships_for_table('');
            $guest_memberships = $this->filter_guest_memberships_by_search_term($guest_memberships, $search_term);
            
            foreach ($guest_memberships as $guest_membership) {
                $members[] = $guest_membership;
            }
        }
        
        // Counters din TOATE rezultatele (nu doar pagina curentă)
        $status_counts = $this->calculate_members_status_counts($members);

        // Aplică filtrul de status pe setul complet
        $members = $this->apply_status_filter($members, $status_filter);

        // Total corect = TOȚI membrii după filtrul selectat (înainte de paginare)
        $total_found = count($members);
        
        // 🎯 SORTARE PRIORITIZATĂ: pending → active → expired → fără abonament
        usort($members, function($a, $b) {
            // Determină statusul efectiv pentru fiecare membru pe baza regulilor de business date.
            $status_a = $this->resolve_member_display_status($a);
            $status_b = $this->resolve_member_display_status($b);
            $member_id_a = (int) ($a['membership_id'] ?? 0);
            $member_id_b = (int) ($b['membership_id'] ?? 0);
            
            // Calculează prioritate pentru fiecare membru (mai mic = mai prioritar)
            $priority_a = $this->get_status_priority($status_a, $member_id_a);
            $priority_b = $this->get_status_priority($status_b, $member_id_b);
            
            // Sortează ascending (prioritate mai mică = apare mai sus)
            return $priority_a - $priority_b;
        });
        
        // 🎯 PAGINARE FINALĂ: Aplică paginarea DUPĂ sortare
        $offset = ($page - 1) * $per_page;
        $members = array_slice($members, $offset, $per_page);

        return [
            'members' => $members,
            'total_found' => $total_found,
            'total_pages' => ceil($total_found / $per_page),
            'current_page' => $page,
            'status_counts' => $status_counts,
            'status_filter' => $status_filter
        ];
    }
    
    /**
     * 🎯 Calculează prioritatea unui membru pentru sortare
     * Prioritate mai mică = apare mai sus în listă
     * 
     * @param string $status Status membership (active, pending, expired, etc.)
     * @param string|null $expiration_date Data expirării (Y-m-d)
     * @return int Prioritate (1 = cel mai urgent, 4 = cel mai puțin urgent)
     */
    private function get_status_priority(string $status, int $membership_id): int {
        // Prioritate 1: PENDING (necesită activare manuală - URGENT!)
        if ($status === 'pending') {
            return 1;
        }
        
        // Prioritate 2: ACTIVE
        if ($status === 'active') {
            return 2;
        }
        
        // Prioritate 3: EXPIRED / COMPLETED / CANCELLED
        if (in_array($status, ['expired', 'completed', 'cancelled'], true)) {
            return 3;
        }

        // Prioritate 4: Fără abonament / rest statusuri
        if ($membership_id <= 0 || in_array($status, ['inactive', 'none', ''], true)) {
            return 4;
        }

        return 4;
    }

    private function apply_status_filter(array $members, string $status_filter): array {
        if ($status_filter === 'all') {
            return $members;
        }

        return array_values(array_filter($members, function($member) use ($status_filter) {
            return $this->get_member_status_bucket($member) === $status_filter;
        }));
    }

    private function calculate_members_status_counts(array $members): array {
        $counts = [
            'all' => count($members),
            'pending' => 0,
            'active' => 0,
            'expired' => 0,
            'no_membership' => 0
        ];

        foreach ($members as $member) {
            $bucket = $this->get_member_status_bucket($member);
            if (isset($counts[$bucket])) {
                $counts[$bucket]++;
            }
        }

        return $counts;
    }

    private function get_member_status_bucket(array $member): string {
        $membership_id = (int) ($member['membership_id'] ?? 0);
        if ($membership_id <= 0) {
            return 'no_membership';
        }

        $status = $this->resolve_member_display_status($member);
        if ($status === 'pending') {
            return 'pending';
        }

        if ($status === 'active') {
            return 'active';
        }

        if (in_array($status, ['expired', 'completed', 'cancelled'], true)) {
            return 'expired';
        }

        return 'no_membership';
    }

    private function is_effectively_unlimited_membership(array $membership_data): bool {
        $payment_method = (string) ($membership_data['payment_method'] ?? '');

        if ($this->is_vip_pool_order((int) ($membership_data['order_id'] ?? 0))) {
            return true;
        }

        if ($this->is_gateway_copayment_context(
            (int) ($membership_data['order_id'] ?? 0),
            $payment_method,
            (float) ($membership_data['product_price'] ?? 0)
        )) {
            return false;
        }

        return (int) ($membership_data['is_unlimited'] ?? 0) === 1
            || $this->is_unlimited_payment_method($payment_method);
    }

    private function get_days_until_membership_expiry(?string $expiration_date): ?int {
        $normalized_expiration_date = oc_membership_normalize_date_value($expiration_date);
        if ($normalized_expiration_date === '') {
            return null;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        try {
            $today = new DateTimeImmutable(oc_membership_current_business_date(), $timezone);
            $expiry = new DateTimeImmutable($normalized_expiration_date, $timezone);
            return (int) $today->diff($expiry)->format('%r%a');
        } catch (Exception $exception) {
            return null;
        }
    }

    private function resolve_member_display_status(array $member): string {
        $membership_id = (int) ($member['membership_id'] ?? $member['id'] ?? 0);
        if ($membership_id <= 0) {
            return 'inactive';
        }

        $stored_status = (string) ($member['validation_status'] ?? $member['membership_status'] ?? '');
        if (in_array($stored_status, ['pending', 'cancelled', 'suspended', 'transferred'], true)) {
            return $stored_status;
        }

        $remaining_sessions = null;
        if (array_key_exists('remaining_sessions', $member)) {
            $remaining_sessions = (int) $member['remaining_sessions'];
        } elseif (array_key_exists('sessions_remaining', $member)) {
            $remaining_sessions = (int) $member['sessions_remaining'];
        }

        $is_unlimited = $this->is_effectively_unlimited_membership($member);
        $expiration_date = $member['expiration_date'] ?? $member['expiry_date'] ?? $member['expires_at'] ?? null;

        if (!$is_unlimited && $remaining_sessions !== null && $remaining_sessions <= 0) {
            return 'expired';
        }

        if ($is_unlimited && oc_membership_normalize_date_value($expiration_date) === '') {
            return $stored_status === 'expired' ? 'expired' : 'active';
        }

        if (oc_membership_is_expired($expiration_date)) {
            return 'expired';
        }

        if ($stored_status === 'expired') {
            return 'active';
        }

        if ($stored_status === '' || in_array($stored_status, ['inactive', 'none'], true)) {
            return 'active';
        }

        return $stored_status;
    }

    private function build_membership_status_context(array $membership): array {
        return [
            'membership_id' => $membership['id'] ?? 0,
            'id' => $membership['id'] ?? 0,
            'validation_status' => $membership['validation_status'] ?? '',
            'membership_status' => $membership['validation_status'] ?? '',
            'expiration_date' => $membership['expiration_date'] ?? $membership['expires_at'] ?? null,
            'payment_method' => $membership['payment_method'] ?? '',
            'product_price' => (float) ($membership['product_price'] ?? 0),
            'order_id' => (int) ($membership['order_id'] ?? 0),
            'is_unlimited' => !empty($membership['is_unlimited']),
            'remaining_sessions' => array_key_exists('remaining_sessions', $membership)
                ? (int) $membership['remaining_sessions']
                : null,
        ];
    }

    private function build_member_package_summaries(array $memberships): array {
        $packages = [];

        foreach ($memberships as $membership) {
            $package_key = (int) ($membership['order_id'] ?? 0);
            if ($package_key <= 0) {
                $package_key = -1 * (int) ($membership['id'] ?? 0);
            }

            $effective_status = $this->resolve_member_display_status($this->build_membership_status_context($membership));
            $expiration_date = oc_membership_normalize_date_value($membership['expiration_date'] ?? $membership['expires_at'] ?? '');

            if (!isset($packages[$package_key])) {
                $packages[$package_key] = [
                    'status' => $effective_status,
                    'price' => (float) ($membership['product_price'] ?? 0),
                    'valid_until' => $expiration_date,
                ];
            }

            if ((float) ($membership['product_price'] ?? 0) > (float) $packages[$package_key]['price']) {
                $packages[$package_key]['price'] = (float) ($membership['product_price'] ?? 0);
            }

            if (
                $expiration_date !== ''
                && (
                    (string) ($packages[$package_key]['valid_until'] ?? '') === ''
                    || $expiration_date > (string) $packages[$package_key]['valid_until']
                )
            ) {
                $packages[$package_key]['valid_until'] = $expiration_date;
            }

            $existing_status = (string) ($packages[$package_key]['status'] ?? 'pending');
            if ($existing_status !== 'active' && $effective_status === 'active') {
                $packages[$package_key]['status'] = 'active';
            } elseif ($existing_status === 'expired' && $effective_status === 'pending') {
                $packages[$package_key]['status'] = 'pending';
            }
        }

        return array_values($packages);
    }
    
    /**
     * Formatează user pentru tabel
     */
    public function format_user_for_table(WP_User $user): array {
        // Obține membership data din membership_validations
        $validator = OC_Membership_Validator::get_instance();
        $membership_data = null;

        $this->sync_user_membership_statuses_if_needed((int) $user->ID);
        
        if ($validator && $validator->get_db()) {
            $db = $validator->get_db();
            global $wpdb;
            $table_name = $db->get_table_name('membership_validations');
            
            // Obține TOATE memberships pentru user - CĂUTARE DUBLĂ cu DEBUG
            // 1. Încearcă după user_id
            $all_memberships = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 WHERE user_id = %d 
                 ORDER BY created_at DESC",
                $user->ID
            ), ARRAY_A);
            
            // 2. Dacă nu găsește, încearcă după email (pentru cazuri de user_id = 0)
            if (empty($all_memberships)) {
                $all_memberships = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name} 
                     WHERE email = %s 
                     ORDER BY created_at DESC",
                    $user->user_email
                ), ARRAY_A);
            }
            
            // Prioritizează statusul afișat pentru cazurile cu abonament cumpărat neactivat.
            // Dacă există pending și abonamentul activ este pe final (<=7 zile), cardul trece în pending.
            $membership_data = null;
            if (!empty($all_memberships)) {
                $active_membership = null;
                $pending_membership = null;
                $package_summaries = $this->build_member_package_summaries($all_memberships);
                $aggregate_status = oc_membership_resolve_aggregate_member_status($package_summaries);
                $target_status = (string) ($aggregate_status['status'] ?? 'inactive');

                foreach ($all_memberships as $membership) {
                    $status = $this->resolve_member_display_status($this->build_membership_status_context($membership));

                    if ($status === 'active' && $active_membership === null) {
                        $active_membership = $membership;
                    }
                    if ($status === 'pending' && $pending_membership === null) {
                        $pending_membership = $membership;
                    }
                }

                if ($target_status === 'pending' && $pending_membership !== null) {
                    $membership_data = $pending_membership;
                }

                if (!$membership_data && $target_status === 'active' && $active_membership !== null) {
                    $membership_data = $active_membership;
                }

                if (!$membership_data && $active_membership !== null) {
                    $membership_data = $active_membership;
                }

                if (!$membership_data) {
                    $membership_data = $all_memberships[0];
                }
            }
        }

        $membership_data = is_array($membership_data) ? $membership_data : [];

        // Pentru utilizatori WordPress, preferă numele real din profil și folosește
        // username-ul doar ca ultim fallback.
        $display_name = oc_membership_resolve_user_display_name($user, $membership_data);
        $email = $membership_data['email'] ?? $user->user_email;
        $phone = $membership_data['phone'] ?? get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true);
        $user_registered = $membership_data['user_registered'] ?? $user->user_registered;
        
        // Date din coloanele cached
        $payment_method = $membership_data['payment_method'] ?? 'card';
        $payment_status = $membership_data['payment_status'] ?? 'unpaid';
        $member_discount = $membership_data['member_discount'] ?? '';
        $last_attendance = $membership_data['last_attendance'] ?? '';
        
        // 🔍 LAST VALIDATION DATE: Caută cea mai recentă validare din TOATE memberships-urile active
        $last_validation_date = '';
        if (!empty($all_memberships)) {
            foreach ($all_memberships as $membership) {
                if (!empty($membership['last_validation_date'])) {
                    // Găsește data cea mai recentă
                    if (empty($last_validation_date) || strtotime($membership['last_validation_date']) > strtotime($last_validation_date)) {
                        $last_validation_date = $membership['last_validation_date'];
                    }
                }
            }
        }
        
        // Status membership (încă din user meta până implementăm sync)
        $membership_status = get_user_meta($user->ID, 'membership_status', true) ?: 'inactive';

        // Default values  
        $full_name = $display_name;
        
        // 🎯 v1.2.0: Folosește coloanele NOI pentru tracking sessions
        $sessions_total = $membership_data['sessions_allocated'] ?? $membership_data['total_sessions'] ?? 0;
        $sessions_remaining = $membership_data['remaining_sessions'] ?? 0;
        $is_unlimited = (int) ($membership_data['is_unlimited'] ?? 0);
        $is_gateway_copayment = $this->is_gateway_copayment_context(
            (int) ($membership_data['order_id'] ?? 0),
            (string) $payment_method,
            (float) ($membership_data['product_price'] ?? 0)
        );
        $is_unlimited = $this->is_effectively_unlimited_membership([
            'order_id' => (int) ($membership_data['order_id'] ?? 0),
            'payment_method' => (string) $payment_method,
            'product_price' => (float) ($membership_data['product_price'] ?? 0),
            'is_unlimited' => (int) ($membership_data['is_unlimited'] ?? 0),
        ]) ? 1 : 0;

        if ($this->is_vip_pool_order((int) ($membership_data['order_id'] ?? 0))) {
            $sessions_total = (int) OC_UNLIMITED_SESSIONS;
            $sessions_remaining = max(0, (int) OC_UNLIMITED_SESSIONS - (int) ($membership_data['used_sessions'] ?? 0));
        }

        $sessions_used = $membership_data['used_sessions'] ?? max(0, $sessions_total - $sessions_remaining);
        
        // Folosește datele CACHED direct din tabel
        $product_name = $membership_data['product_name'] ?? 'N/A';
        $product_price = $membership_data['product_price'] ?? '0.00';
        $courses_included = $membership_data['courses_included'] ?? 'N/A';
        
        // AUTO-SYNC: Simplu și eficient - DOAR pentru records fără date cached
        $needs_sync = (empty($membership_data['product_name']) || $membership_data['product_name'] === 'N/A') && 
                     isset($membership_data['order_id']) && $membership_data['order_id'] > 0;
        
        if ($needs_sync) {
            $this->auto_populate_cached_data($membership_data);
            
            // Re-citește datele după populare
            if ($validator && $validator->get_db()) {
                $db = $validator->get_db();
                global $wpdb;
                $table_name = $db->get_table_name('membership_validations');
                
                $updated_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    $membership_data['id']
                ), ARRAY_A);
                
                if ($updated_data) {
                    $product_name = $updated_data['product_name'] ?? 'N/A';
                    $product_price = $updated_data['product_price'] ?? '0.00';
                    $courses_included = $updated_data['courses_included'] ?? 'N/A';
                    $payment_method = $updated_data['payment_method'] ?? 'card';
                    $payment_status = $updated_data['payment_status'] ?? 'unpaid';
                }
            }
        }
        
        // DEBUG eliminat
        
        // 🎯 v1.3.0: Prioritizează expiration_date din renewal system
        $expiry_date = $membership_data['expiration_date'] ?? $membership_data['expires_at'] ?? '';
        $purchase_date = $membership_data['created_at'] ?? $user->user_registered;
        
        // Folosește datele CACHED - nu mai calculez din WooCommerce
        // $payment_method și $payment_status sunt deja setate din cached data

        // 🎯 v1.3.0: Prioritizează validation_status din DB (renewal system)
        if ($membership_data) {
            $membership_status = $this->resolve_member_display_status([
                'id' => $membership_data['id'] ?? 0,
                'validation_status' => $membership_data['validation_status'] ?? $membership_status,
                'membership_status' => $membership_status,
                'expiration_date' => $expiry_date,
                'payment_method' => $payment_method,
                'product_price' => (float) $product_price,
                'order_id' => (int) ($membership_data['order_id'] ?? 0),
                'is_unlimited' => $is_unlimited,
                'remaining_sessions' => $sessions_remaining,
            ]);
        }

        return [
            'user_id' => $user->ID,
            'membership_id' => $membership_data['id'] ?? 0,
            'order_id' => $membership_data['order_id'] ?? 0,
            'product_id' => $membership_data['product_id'] ?? 0,
            'variation_id' => $membership_data['variation_id'] ?? 0,
            'display_name' => $full_name,
            'user_name' => $display_name,
            'full_name' => $full_name,
            'first_name' => '', // Deprecated
            'last_name' => '', // Deprecated
            'email' => $email,
            'phone' => $phone,
            'member_discount' => $member_discount,
            'product_name' => $product_name,
            'product_price' => $product_price,
            'courses_included' => $courses_included,
            
            // 🎯 v1.2.0: Folosește coloanele NOI (cu fallback la vechi pentru compatibilitate)
            'sessions_total' => $sessions_total, // sessions_allocated sau total_sessions
            'sessions_allocated' => $sessions_total, // Alias pentru noul sistem
            'sessions_remaining' => $sessions_remaining,
            'used_sessions' => $sessions_used,
            'is_unlimited' => $is_unlimited, // Flag VIP
            
            'purchase_date' => $purchase_date,
            'created_at' => $purchase_date,
            'last_attendance' => $last_attendance,
            'last_validation_date' => $last_validation_date,
            'expiry_date' => $expiry_date,
            'expiration_date' => $expiry_date, // Alias pentru sortare
            'expires_at' => $expiry_date,
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'membership_status' => $membership_status,
            'validation_status' => $membership_status, // Alias pentru sortare
            'user_registered' => $user_registered
        ];
    }

    /**
     * Filtrează utilizatorii WP după toate câmpurile relevante.
     */
    private function filter_users_by_search_term(array $users, string $search_term): array {
        $tokens = $this->tokenize_search_term($search_term);
        if (empty($tokens)) {
            return $users;
        }

        $user_ids = array_values(array_filter(array_map(
            static fn($user) => $user instanceof WP_User ? (int) $user->ID : 0,
            $users
        )));
        if (!empty($user_ids)) {
            update_meta_cache('user', $user_ids);
        }

        $filtered = [];
        foreach ($users as $user) {
            if ($user instanceof WP_User && $this->user_matches_search_term($user, $tokens)) {
                $filtered[] = $user;
            }
        }

        return $filtered;
    }

    /**
     * Filtrează membership-urile guest după câmpurile relevante.
     */
    private function filter_guest_memberships_by_search_term(array $memberships, string $search_term): array {
        $tokens = $this->tokenize_search_term($search_term);
        if (empty($tokens)) {
            return $memberships;
        }

        $filtered = [];
        foreach ($memberships as $membership) {
            $haystack = strtolower(implode(' ', array_filter([
                oc_membership_normalize_text_candidate($membership['user_id'] ?? ''),
                oc_membership_normalize_text_candidate($membership['membership_id'] ?? $membership['id'] ?? ''),
                oc_membership_normalize_text_candidate($membership['order_id'] ?? ''),
                oc_membership_normalize_text_candidate($membership['display_name'] ?? ''),
                oc_membership_normalize_text_candidate($membership['email'] ?? ''),
                oc_membership_normalize_text_candidate($membership['phone'] ?? ''),
                oc_membership_normalize_text_candidate($membership['product_name'] ?? ''),
            ])));

            $matches = true;
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $filtered[] = $membership;
            }
        }

        return $filtered;
    }

    /**
     * Verifică dacă un utilizator match-uiește termenul de căutare.
     */
    private function user_matches_search_term(WP_User $user, array $tokens): bool {
        $resolved_name = oc_membership_resolve_user_display_name($user);
        $haystack = strtolower(implode(' ', array_filter([
            oc_membership_normalize_text_candidate($user->ID ?? ''),
            oc_membership_normalize_text_candidate($user->user_login ?? ''),
            oc_membership_normalize_text_candidate($user->user_email ?? ''),
            oc_membership_normalize_text_candidate($user->display_name ?? ''),
            oc_membership_normalize_text_candidate($user->first_name ?? ''),
            oc_membership_normalize_text_candidate($user->last_name ?? ''),
            oc_membership_normalize_text_candidate($user->nickname ?? ''),
            oc_membership_normalize_text_candidate(get_user_meta($user->ID, 'billing_first_name', true)),
            oc_membership_normalize_text_candidate(get_user_meta($user->ID, 'billing_last_name', true)),
            oc_membership_normalize_text_candidate(get_user_meta($user->ID, 'billing_phone', true)),
            oc_membership_normalize_text_candidate(get_user_meta($user->ID, 'phone', true)),
            oc_membership_normalize_text_candidate($resolved_name),
        ])));

        foreach ($tokens as $token) {
            if (!str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Restrânge candidatele WP_User_Query când există termen de căutare.
     */
    private function apply_search_candidate_ids_to_user_query_args(array $args, string $search_term, ?array $candidate_user_ids = null): array {
        $tokens = $this->tokenize_search_term($search_term);
        if (empty($tokens)) {
            return $args;
        }

        $matched_user_ids = $this->get_user_ids_matching_search_tokens($tokens, $candidate_user_ids);
        $args['include'] = empty($matched_user_ids) ? [0] : $matched_user_ids;

        return $args;
    }

    /**
     * Găsește utilizatorii care match-uiesc toți tokenii pe câmpurile de bază și meta.
     */
    private function get_user_ids_matching_search_tokens(array $tokens, ?array $candidate_user_ids = null): array {
        $matched_user_ids = null;

        foreach ($tokens as $token) {
            $token_matches = $this->get_user_ids_matching_search_token($token, $candidate_user_ids);
            if ($matched_user_ids === null) {
                $matched_user_ids = $token_matches;
            } else {
                $matched_user_ids = array_values(array_intersect($matched_user_ids, $token_matches));
            }

            if (empty($matched_user_ids)) {
                return [];
            }
        }

        return $matched_user_ids ?? [];
    }

    /**
     * Găsește utilizatorii care match-uiesc un token pe profile/meta relevante.
     */
    private function get_user_ids_matching_search_token(string $token, ?array $candidate_user_ids = null): array {
        global $wpdb;

        $candidate_user_ids = is_array($candidate_user_ids)
            ? array_values(array_unique(array_filter(array_map('intval', $candidate_user_ids))))
            : null;

        $token_id = ctype_digit($token) ? (int) $token : 0;

        $user_where = '';
        $meta_where = '';
        $user_where_params = [];
        $meta_where_params = [];
        if ($candidate_user_ids !== null) {
            if (empty($candidate_user_ids)) {
                return [];
            }

            $candidate_placeholders = implode(',', array_fill(0, count($candidate_user_ids), '%d'));
            $user_where = " AND ID IN ({$candidate_placeholders})";
            $meta_where = " AND user_id IN ({$candidate_placeholders})";
            $user_where_params = $candidate_user_ids;
            $meta_where_params = $candidate_user_ids;
        }

        $like = '%' . $wpdb->esc_like($token) . '%';

        $user_sql = "SELECT ID
             FROM {$wpdb->users}
             WHERE (
                 user_login LIKE %s OR
                 user_email LIKE %s OR
                 display_name LIKE %s OR
                 user_nicename LIKE %s";
        $user_params = [$like, $like, $like, $like];
        if ($token_id > 0) {
            $user_sql .= " OR ID = %d";
            $user_params[] = $token_id;
        }
        $user_sql .= "){$user_where}";
        $user_params = array_merge($user_params, $user_where_params);
        $user_ids = $wpdb->get_col($wpdb->prepare($user_sql, ...$user_params));

        $meta_keys = [
            'first_name',
            'last_name',
            'nickname',
            'billing_first_name',
            'billing_last_name',
            'billing_phone',
            'phone',
        ];
        $meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $meta_query = "SELECT DISTINCT user_id
                       FROM {$wpdb->usermeta}
                       WHERE meta_key IN ({$meta_placeholders})
                       AND meta_value LIKE %s{$meta_where}";
        $meta_params = array_merge($meta_keys, [$like], $meta_where_params);
        $meta_ids = $wpdb->get_col($wpdb->prepare($meta_query, ...$meta_params));

        $membership_ids = [];
        if ($token_id > 0) {
            $validator = OC_Membership_Validator::get_instance();
            if ($validator && $validator->get_db()) {
                $membership_table = $validator->get_db()->get_table_name('membership_validations');
                $membership_where = '';
                $membership_where_params = [];

                if ($candidate_user_ids !== null) {
                    $candidate_placeholders = implode(',', array_fill(0, count($candidate_user_ids), '%d'));
                    $membership_where = " AND user_id IN ({$candidate_placeholders})";
                    $membership_where_params = $candidate_user_ids;
                }

                $membership_sql = "SELECT DISTINCT user_id
                                   FROM {$membership_table}
                                   WHERE user_id > 0
                                   AND (id = %d OR order_id = %d){$membership_where}";
                $membership_params = array_merge([$token_id, $token_id], $membership_where_params);
                $membership_ids = $wpdb->get_col($wpdb->prepare($membership_sql, ...$membership_params));
            }
        }

        return array_values(array_unique(array_map('intval', array_merge($user_ids, $meta_ids, $membership_ids))));
    }

    /**
     * Normalizează termenul de căutare în tokeni comparabili.
     */
    private function tokenize_search_term(string $search_term): array {
        $normalized = strtolower(oc_membership_normalize_text_candidate($search_term));
        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter(explode(' ', $normalized))));
    }
    
    /**
     * AUTO-SYNC: Populează TOATE datele cached afișate
     */
    private function auto_populate_cached_data(array $membership_data): void {
        $order = wc_get_order($membership_data['order_id']);
        if (!$order) return;
        
        // SYNC COMPLET - TOATE datele afișate
        $cached_data = [];
        
        // 1. Product name (pachetul real)
        $cached_data['product_name'] = $this->get_real_package_name_from_order($membership_data['order_id']);
        
        // 2. Product price (prețul total)
        $cached_data['product_price'] = number_format($order->get_total(), 2);
        
        // 3. Payment method (metoda de plată)
        $cached_data['payment_method'] = $this->normalize_payment_method_key(
            (string) $order->get_payment_method(),
            (string) $order->get_payment_method_title()
        );
        
        // 4. Payment status (status plată separat de status comandă)
        $cached_data['payment_status'] = $this->resolve_order_payment_status($order);
        
        // 5. Member discount (cupoanele folosite)
        $coupons = $order->get_coupons();
        if (!empty($coupons)) {
            $coupon_codes = [];
            foreach ($coupons as $coupon_item) {
                $coupon_codes[] = $coupon_item->get_code();
            }
            $cached_data['member_discount'] = implode(', ', $coupon_codes);
        } else {
            $cached_data['member_discount'] = '';
        }
        
        // 6. Courses included (cursurile din variațiile comenzii)
        $cached_data['courses_included'] = $this->get_courses_from_order($order, (object)$membership_data);
        
        // 7. User data pentru guest users
        if ($membership_data['user_id'] == 0) {
            $cached_data['display_name'] = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $cached_data['email'] = $order->get_billing_email();
            $cached_data['phone'] = $order->get_billing_phone();
            $cached_data['user_registered'] = $order->get_date_created()->date('Y-m-d H:i:s');
        }
        
        // 8. Timestamp sync
        $cached_data['cached_data_synced_at'] = current_time('mysql');
        
        // Update complet în DB
        $validator = OC_Membership_Validator::get_instance();
        if ($validator && $validator->get_db()) {
            global $wpdb;
            $table_name = $validator->get_db()->get_table_name('membership_validations');
            
            $wpdb->update(
                $table_name,
                $cached_data,
                ['id' => $membership_data['id']],
                array_fill(0, count($cached_data), '%s'),
                ['%d']
            );
        }
    }
    
    /**
     * Helper: găsește numele real al pachetului din order
     */
    private function get_real_package_name_from_order(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) return 'N/A';
        
        // Primul pas: caută produsul principal cu preț > 0
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $total = floatval($item->get_total());
            
            if ($total > 0 && $variation_id == 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    return $product->get_name();
                }
            }
        }
        
        // Al doilea pas: pentru order-uri gratuite
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            if ($variation_id == 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    return $product->get_name();
                }
            }
        }
        
        return 'N/A';
    }
    
    /**
     * Helper: găsește cursurile din order
     */
    private function get_courses_from_order($order, $membership): string {
        $courses = [];
        
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            
            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation_name = $variation->get_name();
                    
                    if (strpos($variation_name, ' - ') !== false) {
                        $course_name = trim(substr($variation_name, strpos($variation_name, ' - ') + 3));
                        $courses[] = $course_name;
                    }
                }
            }
        }
        
        return !empty($courses) ? implode(', ', $courses) : 'N/A';
    }
    
    // Metoda get_package_price_from_order() ștearsă - folosim direct $order->get_total()
    
    /**
     * Obține guest memberships pentru tabel (user_id = 0) cu date complete din WooCommerce
     */
    public function get_guest_memberships_for_table(string $search_term = ''): array {
        global $wpdb;
        
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return [];
        }
        
        $db = $validator->get_db();
        $table_name = $db->get_table_name('membership_validations');
        
        // 🎯 v1.3.0: Query pentru guest memberships (active + pending + expired)
        $query = "SELECT * FROM {$table_name} 
                  WHERE user_id = 0
                  AND validation_status IN ('active', 'pending', 'expired')
                  AND variation_id > 0";
        
        // Adaugă search pentru guests pe coloanele cached
        $query_params = [];
        if (!empty($search_term)) {
            $search_like = '%' . $wpdb->esc_like($search_term) . '%';
            $query .= " AND (email LIKE %s OR phone LIKE %s OR display_name LIKE %s)";
            $query_params[] = $search_like;
            $query_params[] = $search_like;
            $query_params[] = $search_like;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        // Execute query cu sau fără prepare (depinde de search term)
        if (!empty($query_params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$query_params), ARRAY_A);
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }
        
        $guest_members = [];
        foreach ($results as $result) {
            // DEBUG eliminat
            
            // Folosește datele CACHED direct din tabel
            $product_name = $result['product_name'] ?? 'N/A';
            $product_price = $result['product_price'] ?? '0.00';
            $courses_included = $result['courses_included'] ?? 'N/A';
            $payment_method = $result['payment_method'] ?? 'card';
            $payment_status = $result['payment_status'] ?? 'unpaid';
            
            // AUTO-SYNC pentru guest users - simplu și eficient
            if ((empty($result['product_name']) || $result['product_name'] === 'N/A') && $result['order_id'] > 0) {
                // Auto-sync guest data
                $this->auto_populate_cached_data($result);
                
                // Re-citește datele după sync
                global $wpdb;
                $validator = OC_Membership_Validator::get_instance();
                if ($validator && $validator->get_db()) {
                    $db = $validator->get_db();
                    $table_name = $db->get_table_name('membership_validations');
                    
                    $updated_result = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE id = %d",
                        $result['id']
                    ), ARRAY_A);
                    
                    if ($updated_result) {
                        $product_price = $updated_result['product_price'] ?? '0.00';
                        $payment_method = $updated_result['payment_method'] ?? 'card';
                        $payment_status = $updated_result['payment_status'] ?? 'unpaid';
                        $result['member_discount'] = $updated_result['member_discount'] ?? '';
                        // Actualizează și alte câmpuri dacă au fost modificate
                        $result['product_name'] = $updated_result['product_name'] ?? $result['product_name'];
                        $result['courses_included'] = $updated_result['courses_included'] ?? $result['courses_included'];
                    }
                }
            }
            
            // Construiește datele pentru guest member
            $guest_members[] = [
                'user_id' => 'guest_' . $result['id'], // Unique ID pentru guest
                'membership_id' => $result['id'], // ID-ul din membership_validations
                'order_id' => $result['order_id'],
                'product_id' => $result['product_id'] ?? 0, // LIPSEA ASTA PENTRU GUESTS!
                'variation_id' => $result['variation_id'] ?? 0, // ȘI ASTA PENTRU GUESTS!
                'display_name' => $result['display_name'] ?? 'Guest User',
                'user_name' => $result['display_name'] ?? 'Guest User',
                'full_name' => $result['display_name'] ?? 'Guest User',
                'first_name' => '', // Deprecated
                'last_name' => '', // Deprecated
                'email' => $result['email'] ?? 'guest@no-email.local',
                'phone' => $result['phone'] ?? '',
                'member_discount' => $result['member_discount'] ?? '',
                'product_name' => $product_name,
                'product_price' => $product_price,
                'courses_included' => $courses_included,
                
                // 🎯 v1.2.0: Folosește coloanele NOI (cu fallback)
                'sessions_total' => $result['sessions_allocated'] ?? $result['total_sessions'] ?? 0,
                'sessions_allocated' => $result['sessions_allocated'] ?? $result['total_sessions'] ?? 0,
                'sessions_remaining' => $result['remaining_sessions'] ?? 0,
                'used_sessions' => (int) ($result['used_sessions'] ?? 0),
                'is_unlimited' => $this->is_effectively_unlimited_membership([
                    'order_id' => (int) ($result['order_id'] ?? 0),
                    'payment_method' => (string) $payment_method,
                    'product_price' => (float) ($result['product_price'] ?? 0),
                    'is_unlimited' => (int) ($result['is_unlimited'] ?? 0),
                ]) ? 1 : 0,
                
                'purchase_date' => $result['created_at'],
                'created_at' => $result['created_at'], // Alias pentru compatibilitate
                'last_attendance' => $result['last_attendance'] ?? '',
                'last_validation_date' => $result['last_validation_date'] ?? '',
                'expiry_date' => $result['expiration_date'] ?? $result['expires_at'] ?? '', // v1.3.0: Renewal system
                'expires_at' => $result['expiration_date'] ?? $result['expires_at'] ?? '', // Alias pentru compatibilitate
                'payment_method' => $payment_method,
                'payment_status' => $payment_status,
                'membership_status' => $this->get_membership_status($result),
                'user_registered' => $result['user_registered'] ?? $result['created_at']
            ];
        }
        
        return $guest_members;
    }
    
    /**
     * Obține datele de billing din WooCommerce order (va fi mutat în trait WooCommerce)
     */
    private function get_order_billing_data(int $order_id): array {
        if (!$order_id) {
            return $this->get_empty_billing_data();
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->get_empty_billing_data();
        }
        
        // Extrage datele de billing
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        
        return [
            'first_name' => $first_name ?: 'Guest',
            'last_name' => $last_name ?: 'User',
            'full_name' => trim($first_name . ' ' . $last_name) ?: 'Guest User',
            'email' => $email ?: 'guest@no-email.local',
            'phone' => $phone ?: '',
            'payment_method' => $this->normalize_payment_method_key(
                (string) $order->get_payment_method(),
                (string) $order->get_payment_method_title()
            ),
            'payment_status' => $this->map_order_status_to_payment((string) $order->get_status())
        ];
    }
    
    /**
     * Date de fallback pentru cazurile în care nu se găsește order-ul
     */
    private function get_empty_billing_data(): array {
        return [
            'first_name' => 'Guest',
            'last_name' => 'User',
            'full_name' => 'Guest User',
            'email' => 'guest@no-order.local',
            'phone' => '',
            'payment_method' => 'card',
            'payment_status' => 'unpaid'
        ];
    }
    
    /**
     * Determină statusul membership-ului
     */
    private function get_membership_status(array $membership_data): string {
        return $this->resolve_member_display_status($membership_data);
    }
    
    /**
     * Normalizează metodele de plată la cheile canonice folosite în plugin.
     */
    private function normalize_payment_method_key(string $payment_method_id, string $payment_method_title = ''): string {
        $raw = trim($payment_method_id . ' ' . $payment_method_title);
        if ($raw === '') {
            return 'card';
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($raw, 'UTF-8')
            : strtolower($raw);

        if (strpos($normalized, '7card') !== false) {
            return 'oc_7card';
        }
        if (strpos($normalized, 'esx') !== false) {
            return 'oc_esx';
        }
        if (strpos($normalized, 'transfer') !== false || strpos($normalized, 'bacs') !== false || strpos($normalized, 'iban') !== false) {
            return 'transfer';
        }
        if (strpos($normalized, 'cash') !== false || strpos($normalized, 'numerar') !== false || strpos($normalized, 'studio') !== false || strpos($normalized, 'cod') !== false) {
            return 'cash';
        }
        if (strpos($normalized, 'card') !== false || strpos($normalized, 'stripe') !== false || strpos($normalized, 'netopia') !== false) {
            return 'card';
        }

        // Fallback pentru gateway-uri noi: tratăm generic ca plată cu cardul.
        return 'card';
    }

    private function is_unlimited_payment_method(string $payment_method): bool {
        $payment_method = trim($payment_method);
        if ($payment_method === '') {
            return false;
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($payment_method, 'UTF-8')
            : strtolower($payment_method);

        return (
            $normalized === 'oc_7card' ||
            $normalized === 'oc_esx' ||
            strpos($normalized, '7card') !== false ||
            strpos($normalized, 'esx') !== false
        );
    }

    private function is_gateway_copayment_context(int $order_id, string $payment_method, float $row_price = 0.0): bool {
        if (!$this->is_unlimited_payment_method($payment_method)) {
            return false;
        }

        static $order_copayment_cache = [];

        if ($order_id > 0) {
            if (array_key_exists($order_id, $order_copayment_cache)) {
                return $order_copayment_cache[$order_id];
            }

            global $wpdb;
            $table_name = $this->validator_db->get_table_name('membership_validations');
            $max_price = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(product_price) FROM {$table_name} WHERE order_id = %d",
                $order_id
            ));

            $order = wc_get_order($order_id);
            if ($order) {
                // Coplata trebuie dedusă din prețul membership-ului, nu din totalul istoric al comenzii.
                $has_copayment = ($row_price > 0) || ($max_price > 0);
                $order_copayment_cache[$order_id] = $has_copayment;
                return $has_copayment;
            }

            $has_copayment = ($row_price > 0) || ($max_price > 0);
            $order_copayment_cache[$order_id] = $has_copayment;
            return $has_copayment;
        }

        return $row_price > 0;
    }

    private function is_vip_pool_order(int $order_id): bool {
        if ($order_id <= 0) {
            return false;
        }

        static $vip_order_cache = [];
        if (array_key_exists($order_id, $vip_order_cache)) {
            return $vip_order_cache[$order_id];
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $vip_order_cache[$order_id] = false;
            return false;
        }

        foreach ($order->get_items() as $order_item) {
            if ((int) $order_item->get_variation_id() !== 0) {
                continue;
            }

            $pool_product_id = (int) $order_item->get_product_id();
            if ($pool_product_id > 0 && get_post_meta($pool_product_id, '_oc_pool_is_unlimited', true) === 'yes') {
                $vip_order_cache[$order_id] = true;
                return true;
            }
        }

        $vip_order_cache[$order_id] = false;
        return false;
    }
    
    /**
     * 🎯 REAL-TIME ACTIVATION: Activează membership-uri pending care au atins start_date
     * 
     * Această funcție rulează la fiecare afișare a tabelului pentru a asigura
     * că interface-ul afișează status-urile corecte în timp real, fără să aștepte
     * cron job-ul zilnic.
     * 
     * @since 1.3.1
     */
    private function activate_pending_memberships_realtime(): void {
        global $wpdb;
        
        // Obține referința la DB-ul din ADD-ON #1
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return;
        }
        
        $db = $validator->get_db();
        $table_name = $db->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();
        
        // Găsește toate membership-urile pending care trebuie activate astăzi
        $pending_memberships = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, variation_id, start_date, expiration_date
             FROM {$table_name}
             WHERE validation_status = 'pending'
             AND start_date <= %s
             ORDER BY start_date ASC",
            $today
        ));
        
        if (empty($pending_memberships)) {
            return;
        }
        
        foreach ($pending_memberships as $membership) {
            // Resetează ore din course_hours_config
            $db->reset_membership_hours_on_activation($membership->id);
            
            // Actualizează status → active
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
            
            // Invalidare cache pentru refresh UI
            if (method_exists($db, 'invalidate_membership_cache')) {
                $db->invalidate_membership_cache($membership->user_id);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '⚡ [Real-Time Activation] Membership #%d activated: user=%d, start_date=%s',
                    $membership->id,
                    $membership->user_id,
                    $membership->start_date
                ));
            }
        }
    }
}
