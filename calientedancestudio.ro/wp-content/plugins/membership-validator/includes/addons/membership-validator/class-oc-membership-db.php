<?php
/**
 * Membership Validator - Database Handler
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
 * Class OC_Membership_DB
 * 
 * Gestionare bază de date pentru membership validations
 * Implementare NON-INTRUZIVĂ conform .cursorrules
 */
class OC_Membership_DB {
    
    /**
     * Table version pentru migrări
     */
    private string $table_version = '2.0.0';
    
    /**
     * Prefix pentru tabele - folosește $wpdb->prefix conform .cursorrules
     */
    public function get_table_name(string $table): string {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Rulează migrarea DUPĂ încărcarea add-onurilor (care se încarcă pe init)
        add_action('wp_loaded', [$this, 'maybe_upgrade_schema'], 10);
        
        // 🚨 FORCE IMMEDIATE UPGRADE pentru course_mapping structure fix
        add_action('init', [$this, 'force_immediate_upgrade'], 5);
    }
    
    /**
     * Forțează upgrade imediat pentru a fixa structura course_mapping
     */
    public function force_immediate_upgrade(): void {
        // Rulează cel mult o dată pe zi pentru a evita query-uri inutile pe fiecare request.
        if (get_transient('oc_schema_check_done')) {
            return;
        }

        global $wpdb;

        $mapping_table = $this->get_table_name('membership_course_mapping');
        $mapping_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $mapping_table)) === $mapping_table;

        if ($mapping_exists) {
            $safe_mapping_table = esc_sql($mapping_table);
            $columns = $wpdb->get_results("DESCRIBE `{$safe_mapping_table}`");
            $column_names = array_column($columns, 'Field');

            // Dacă are structura veche, forțează upgrade
            if (in_array('schedule_id', $column_names) && !in_array('membership_product_id', $column_names)) {
                delete_option('oc_membership_db_version'); // Reset version to force upgrade
                delete_option('oc_active_addons'); // Reset addon cache to force reload
                $this->create_tables();
                set_transient('oc_schema_check_done', 1, DAY_IN_SECONDS);
                return;
            }
        }

        // Schema e corectă — marchează ca verificat pentru 24h.
        set_transient('oc_schema_check_done', 1, DAY_IN_SECONDS);
    }
    
    /**
     * Creează tabelele - DOAR tabele NOI conform .cursorrules
     */
    public function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabel principal pentru validări membership
        $table_name = $this->get_table_name('membership_validations');
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned DEFAULT 0,
            membership_uuid varchar(36) NOT NULL,
            qr_token varchar(64) DEFAULT NULL,
            qr_token_hash char(64) NOT NULL,
            qr_token_revoked_at datetime DEFAULT NULL,
            qr_code_url text,
            access_code varchar(20),
            schedule_id bigint(20) unsigned DEFAULT 0,
            course_product_id bigint(20) unsigned DEFAULT 0,
            course_variation_id bigint(20) unsigned DEFAULT 0,
            weekday tinyint(1) unsigned DEFAULT 0,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            room_number tinyint(1) unsigned DEFAULT 1,
            package_product_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID produs pachet',
            package_order_item_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID order item pachet',
            total_sessions int(11) NOT NULL DEFAULT 0,
            sessions_allocated int(11) DEFAULT 0 COMMENT 'Ședințe alocate din config',
            used_sessions int(11) NOT NULL DEFAULT 0,
            remaining_sessions int(11) NOT NULL DEFAULT 0,
            is_unlimited tinyint(1) DEFAULT 0 COMMENT 'VIP unlimited flag',
            start_date date DEFAULT NULL COMMENT 'Data când începe abonamentul (pentru queue)',
            expiration_date date DEFAULT NULL COMMENT 'Data când expiră abonamentul',
            duration_days int(11) DEFAULT 28 COMMENT 'Durata abonamentului în zile',
            is_renewal tinyint(1) DEFAULT 0 COMMENT 'Flag dacă este reînnoire',
            previous_membership_id bigint(20) unsigned DEFAULT NULL COMMENT 'ID membership precedent (pentru tracking renewal)',
            validation_status enum('active','pending','expired','cancelled','suspended','transferred') DEFAULT 'active',
            last_validation_date datetime DEFAULT NULL,
            validation_count int(11) NOT NULL DEFAULT 0,
            last_validator_user_id bigint(20) unsigned DEFAULT 0,
            max_daily_validations int(11) DEFAULT 0,
            validation_restriction enum('none','once_per_day','once_per_session','unlimited') DEFAULT 'none',
            allowed_days_of_week varchar(32) DEFAULT NULL,
            allowed_time_slots text DEFAULT NULL,
            membership_metadata longtext DEFAULT NULL,
            woocommerce_metadata longtext DEFAULT NULL,
            pool_metadata longtext DEFAULT NULL,
            display_name varchar(250) DEFAULT NULL COMMENT 'Nume afișat utilizator cached',
            email varchar(100) DEFAULT NULL COMMENT 'Email utilizator cached',
            phone varchar(20) DEFAULT NULL COMMENT 'Telefon utilizator cached',
            user_registered datetime DEFAULT NULL COMMENT 'Data înregistrare user cached',
            product_name varchar(255) DEFAULT NULL COMMENT 'Numele produsului cached',
            product_price decimal(10,2) DEFAULT 0.00 COMMENT 'Prețul produs cached',
            courses_included text DEFAULT NULL COMMENT 'Cursuri incluse cached',
            observations text DEFAULT NULL COMMENT 'Observații administrative',
            payment_method varchar(50) DEFAULT NULL COMMENT 'Metoda plată cached',
            payment_status varchar(20) DEFAULT NULL COMMENT 'Status plată cached',
            member_discount varchar(50) DEFAULT NULL COMMENT 'Reducere membru cached',
            last_attendance datetime DEFAULT NULL COMMENT 'Ultima prezență cached',
            cached_data_synced_at datetime DEFAULT NULL COMMENT 'Ultima sincronizare date cached',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            activated_at datetime DEFAULT NULL,
            suspended_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY membership_uuid (membership_uuid),
            UNIQUE KEY qr_token_hash (qr_token_hash),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY schedule_id (schedule_id),
            KEY validation_status (validation_status),
            KEY start_date (start_date),
            KEY expiration_date (expiration_date),
            KEY created_at (created_at),
            KEY last_validation_date (last_validation_date),
            KEY previous_membership_id (previous_membership_id),
            KEY user_variation_status (user_id, variation_id, validation_status),
            KEY idx_package_product (package_product_id),
            KEY idx_package_order_item (package_order_item_id),
            KEY idx_unlimited (is_unlimited),
            KEY idx_cached_email (email),
            KEY idx_cached_phone (phone),
            KEY idx_cached_product_name (product_name),
            KEY idx_cached_payment_status (payment_status),
            KEY idx_cached_sync (cached_data_synced_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 🎯 TABEL NOU: membership_validation_log pentru audit trail
        $this->create_validation_log_table();
        
        // 🎯 TABEL NOU: membership_course_mapping pentru sistem mapare abonamente
        $this->create_course_mapping_table();
        
        // 🎯 TABEL NOU v1.2.0: course_hours_config pentru template-uri ore/ședințe
        $this->create_course_hours_config_table();
        
        // Salvează versiunea tabelului
        update_option('oc_membership_db_version', $this->table_version);
    }
    
    /**
     * 🎯 Creează tabelul membership_validation_log pentru audit trail
     */
    private function create_validation_log_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $this->get_table_name('membership_validation_log');
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            membership_id bigint(20) unsigned NOT NULL COMMENT 'ID membership',
            user_id bigint(20) unsigned NOT NULL COMMENT 'ID utilizator',
            validator_user_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID validator',
            validation_method enum('qr_code','access_code','manual','api') DEFAULT 'qr_code',
            validation_status enum('success','failed','error') DEFAULT 'success',
            validation_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL COMMENT 'IP-ul de unde s-a validat',
            user_agent text DEFAULT NULL COMMENT 'User agent browser',
            validation_metadata longtext DEFAULT NULL COMMENT 'JSON: date suplimentare',
            error_message text DEFAULT NULL COMMENT 'Mesaj eroare dacă validarea a eșuat',
            PRIMARY KEY (id),
            KEY membership_id (membership_id),
            KEY user_id (user_id),
            KEY validation_date (validation_date),
            KEY validation_status (validation_status),
            KEY validation_method (validation_method)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 🎯 Creează tabelul membership_course_mapping pentru sistemul de mapare
     */
    private function create_course_mapping_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $this->get_table_name('membership_course_mapping');
        
        // Verifică dacă tabelul există și are structura veche
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
        
        if ($table_exists) {
            // Verifică structura existentă
            $safe_table_name = esc_sql($table_name);
            $columns = $wpdb->get_results("DESCRIBE `{$safe_table_name}`");
            $column_names = array_column($columns, 'Field');
            
            // Dacă are structura veche (schedule_id), drop și recreează
            if (in_array('schedule_id', $column_names) && !in_array('membership_product_id', $column_names)) {
                $wpdb->query("DROP TABLE `{$safe_table_name}`");
            }
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            membership_product_id bigint(20) unsigned NOT NULL COMMENT 'ID produs abonament',
            variation_id bigint(20) unsigned NOT NULL COMMENT 'ID variație validată',
            is_valid tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Dacă este valid pentru acest abonament',
            notes text DEFAULT NULL COMMENT 'Notițe admin pentru mapare',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (membership_product_id, variation_id),
            KEY idx_membership (membership_product_id),
            KEY idx_variation (variation_id),
            KEY idx_valid (is_valid)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verifică că s-a creat corect
    }

    /**
     * Verifică și creează tabelele DOAR dacă este necesar
     */
    public function maybe_create_tables(): void {
        global $wpdb;
        
        // Verifică dacă tabelul principal există
        $table_name = $this->get_table_name('membership_validations');
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
        
        // Verifică versiunea instalată
        $installed_version = get_option('oc_membership_db_version', '0.0.0');
        
        // Verifică separat tabelul course_mapping pentru upgrade
        $mapping_table = $this->get_table_name('membership_course_mapping');
        $mapping_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $mapping_table)) === $mapping_table;
        
        $needs_upgrade = false;
        
        if ($mapping_exists) {
            // Verifică dacă are structura veche
            $safe_mapping_table = esc_sql($mapping_table);
            $columns = $wpdb->get_results("DESCRIBE `{$safe_mapping_table}`");
            $column_names = array_column($columns, 'Field');
            
            if (in_array('schedule_id', $column_names) && !in_array('membership_product_id', $column_names)) {
                $needs_upgrade = true;
            }
        }
        
        // Creează tabelele dacă nu există, versiunea e mai veche, sau course_mapping needs upgrade
        if (!$table_exists || version_compare($installed_version, $this->table_version, '<') || $needs_upgrade) {
            $this->create_tables();
        }
    }
    
    /**
     * Upgrade schema dacă este necesar
     */
    public function maybe_upgrade_schema(): void {
        $this->maybe_create_tables();
        $this->ensure_observations_column_exists();
        
        // Rulează și migrarea pentru coloane cached
        $this->run_cached_data_migration();

        // 🎯 v1.3.0: Migrație pentru renewal system
        $this->run_renewal_system_migration();

        // v4: Cazuri distincte:
        //   - 7CARD/ESX (gateway) → is_unlimited=1 + expiration_date=NULL
        //   - VIP Pool flag       → is_unlimited=1 + expiration_date calculată normal
        //   - Marcat eronat (după nume vechi) → is_unlimited=0 + expiration_date calculată
        if (get_option('oc_fix_unlimited_by_payment_only_done') !== '4') {
            global $wpdb;
            $tbl       = $this->get_table_name('membership_validations');
            $duration_default = intval(
                (get_option('oc_membership_settings', [])['default_membership_duration'] ?? 28)
            ) ?: 28;
            $unlimited_rows = $wpdb->get_results(
                "SELECT id, order_id, product_id, start_date, duration_days FROM {$tbl} WHERE is_unlimited = 1"
            );
            foreach ($unlimited_rows as $row) {
                $order = wc_get_order((int) $row->order_id);
                $is_gateway_unl   = false;
                $is_pool_vip_unl  = false;
                if ($order) {
                    $pm       = strtolower((string) $order->get_payment_method());
                    $pm_title = strtolower((string) $order->get_payment_method_title());
                    $is_gateway_unl = in_array($pm, ['oc_7card', 'oc_esx'], true)
                        || strpos($pm, '7card') !== false
                        || strpos($pm, 'esx')   !== false
                        || strpos($pm_title, '7card') !== false
                        || strpos($pm_title, 'esx')   !== false;
                    if (!$is_gateway_unl) {
                        foreach ($order->get_items() as $_pi) {
                            if ($_pi->get_variation_id() == 0 && $_pi->get_total() > 0) {
                                if (get_post_meta($_pi->get_product_id(), '_oc_pool_is_unlimited', true) === 'yes') {
                                    $is_pool_vip_unl = true;
                                }
                                break;
                            }
                        }
                    }
                }
                $start    = $row->start_date ?: oc_membership_current_business_date();
                $duration = intval($row->duration_days) ?: $duration_default;
                $expiry   = date('Y-m-d', strtotime($start . ' +' . $duration . ' days'));

                if ($is_gateway_unl) {
                    // 7CARD/ESX: sesiuni nelimitate + FĂRĂ dată expirare
                    $wpdb->update($tbl, ['is_unlimited' => 1, 'expiration_date' => null],
                        ['id' => $row->id], ['%d', '%s'], ['%d']);
                } elseif ($is_pool_vip_unl) {
                    // VIP Pool flag: sesiuni nelimitate + dată expirare normală
                    $wpdb->update($tbl, ['is_unlimited' => 1, 'expiration_date' => $expiry],
                        ['id' => $row->id], ['%d', '%s'], ['%d']);
                } else {
                    // Marcat eronat ca unlimited (după nume) → resetează la normal
                    $wpdb->update($tbl, ['is_unlimited' => 0, 'expiration_date' => $expiry],
                        ['id' => $row->id], ['%d', '%s'], ['%d']);
                }
            }
            update_option('oc_fix_unlimited_by_payment_only_done', '4');
        }
    }

    /**
     * Ensure observations column exists even when dbDelta/version checks skip execution.
     */
    private function ensure_observations_column_exists(): void {
        global $wpdb;

        $table_name = $this->get_table_name('membership_validations');
        $column_exists = (bool) $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'observations'
        ));

        if ($column_exists) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $alter_result = $wpdb->query(
            "ALTER TABLE {$table_name} ADD COLUMN observations text DEFAULT NULL COMMENT 'Observatii administrative' AFTER courses_included"
        );

        if ($alter_result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Membership DB] Failed to add observations column via ALTER TABLE.');
        }
    }
    
    /**
     * 🎯 v1.3.0: Backwards compatibility migration pentru renewal system
     * 
     * Setează start_date pentru membership-uri vechi fără acest câmp
     * 
     * IMPORTANT: Această funcție rulează ÎNTOTDEAUNA pentru a asigura
     * că datele vechi sunt migrate, chiar dacă versiunea DB e deja 1.3.0
     * 
     * @since 1.3.0 - Renewal & Expiration System
     */
    private function run_renewal_system_migration(): void {
        // Dacă migrarea a fost deja finalizată, rulează doar auto-fix zilnic.
        if (get_option('oc_renewal_migration_v1_3_done')) {
            $this->maybe_run_daily_membership_status_sync();
            return;
        }

        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        // Verifică dacă coloanele noi există
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('start_date', $column_names)) {
            return; // Coloanele nu sunt încă create, așteptăm dbDelta
        }

        // STEP 1: Migrare start_date pentru memberships vechi
        $needs_migration = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_name}
            WHERE start_date IS NULL
        ");

        if ($needs_migration > 0) {
            oc_log_debug(sprintf('[Renewal System] Running backwards compatibility migration for %d memberships...', $needs_migration));

            // 🎯 IMPORTANT: Pentru membership-uri vechi, setează standard de 28 zile
            $updated = $wpdb->query("
                UPDATE {$table_name}
                SET start_date = DATE(created_at),
                    expiration_date = DATE_ADD(DATE(created_at), INTERVAL 28 DAY),
                    duration_days = 28
                WHERE start_date IS NULL
                AND created_at IS NOT NULL
            ");

            oc_log_debug(sprintf('[Renewal System] Migration completed: %d memberships updated', $updated));
        }

        // Migrare finalizată — marchează permanent.
        update_option('oc_renewal_migration_v1_3_done', '1.3.0');

        // STEP 2: Auto-fix statusuri inconsistente (CRITICAL FIX)
        $this->maybe_run_daily_membership_status_sync();
    }

    /**
     * Rulează sincronizarea statusurilor o singură dată pe zi calendaristică.
     */
    private function maybe_run_daily_membership_status_sync(): void {
        $today = oc_membership_current_business_date();
        $last_sync_date = (string) get_option('oc_membership_status_sync_date', '');

        if ($last_sync_date === $today) {
            return;
        }

        $this->auto_fix_membership_statuses();
        update_option('oc_membership_status_sync_date', $today);
    }
    
    /**
     * 🔧 AUTO-FIX: Expirare automată abonamente vechi
     * 
     * LOGICĂ SIMPLĂ:
     * 1. PENDING: created_at + 28 zile < AZI → EXPIRED (nu mai poate fi activat)
     * 2. ACTIVE: expiration_date < AZI → EXPIRED (perioada validitate expirată)
     * 
     * @since 1.3.0
     */
    public function auto_fix_membership_statuses(): void {
        $sync_result = $this->sync_membership_statuses();

        if ($sync_result['expired_pending'] > 0 || $sync_result['expired_active'] > 0 || $sync_result['restored_active'] > 0) {
            oc_log_debug(sprintf(
                '🔧 [Auto-Fix] Synced statuses: %d pending expired, %d active expired, %d active restored',
                $sync_result['expired_pending'],
                $sync_result['expired_active'],
                $sync_result['restored_active']
            ));
        }
    }

    /**
     * Sincronizează statusurile abonamentelor cu starea lor reală.
     *
     * @param int|null $user_id Dacă este setat, sincronizează doar utilizatorul respectiv.
     * @return array{expired_pending:int,expired_active:int,restored_active:int}
     */
    public function sync_membership_statuses(?int $user_id = null): array {
        global $wpdb;

        $table_name = $this->get_table_name('membership_validations');
        $posts_table = $wpdb->prefix . 'posts';
        $today = oc_membership_current_business_date();
        $updated_at = current_time('mysql');
        $user_condition = '';
        $user_params = [];

        if ($user_id !== null && $user_id > 0) {
            $user_condition = ' AND m.user_id = %d';
            $user_params[] = $user_id;
        }

        $expired_pending = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} m
             LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
             SET m.validation_status = 'expired',
                 m.updated_at = %s
             WHERE m.validation_status = 'pending'
             AND DATE_ADD(DATE(m.created_at), INTERVAL 28 DAY) < %s
             AND m.variation_id > 0
             AND (p.ID IS NULL OR p.post_status NOT IN ('wc-cancelled', 'wc-refunded')){$user_condition}",
            ...array_merge([$updated_at, $today], $user_params)
        ));

        $expired_active = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} m
             LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
             SET m.validation_status = 'expired',
                 m.updated_at = %s
             WHERE m.validation_status = 'active'
             AND m.expiration_date IS NOT NULL
             AND m.expiration_date < %s
             AND m.variation_id > 0
             AND (p.ID IS NULL OR p.post_status NOT IN ('wc-cancelled', 'wc-refunded')){$user_condition}",
            ...array_merge([$updated_at, $today], $user_params)
        ));

        $restored_active = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} m
             LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
             SET m.validation_status = 'active',
                 m.updated_at = %s
             WHERE m.validation_status = 'expired'
             AND m.expiration_date IS NOT NULL
             AND m.expiration_date >= %s
             AND m.variation_id > 0
             AND (m.is_unlimited = 1 OR m.remaining_sessions > 0)
             AND (m.start_date IS NULL OR m.start_date <= %s)
             AND (p.ID IS NULL OR p.post_status NOT IN ('wc-cancelled', 'wc-refunded')){$user_condition}",
            ...array_merge([$updated_at, $today, $today], $user_params)
        ));

        $restored_unlimited_active = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} m
             LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
             SET m.validation_status = 'active',
                 m.updated_at = %s
             WHERE m.validation_status = 'expired'
             AND m.expiration_date IS NULL
             AND (
                 m.is_unlimited = 1
                 OR (
                     (
                         LOWER(COALESCE(m.payment_method, '')) LIKE '%7card%'
                         OR LOWER(COALESCE(m.payment_method, '')) LIKE '%esx%'
                     )
                     AND COALESCE(m.product_price, 0) <= 0
                 )
             )
             AND m.variation_id > 0
             AND (m.start_date IS NULL OR m.start_date <= %s)
             AND (p.ID IS NULL OR p.post_status NOT IN ('wc-cancelled', 'wc-refunded')){$user_condition}",
            ...array_merge([$updated_at, $today], $user_params)
        ));

        $result = [
            'expired_pending' => max(0, (int) $expired_pending),
            'expired_active' => max(0, (int) $expired_active),
            'restored_active' => max(0, (int) $restored_active),
            'restored_unlimited_active' => max(0, (int) $restored_unlimited_active),
        ];

        if (array_sum($result) > 0) {
            $this->invalidate_membership_cache($user_id);
        }

        return $result;
    }
    
    /**
     * Rulează migrarea pentru coloane cached (1.1.0)
     */
    private function run_cached_data_migration(): void {
        // Verifică dacă migrarea s-a executat deja  
        $db_version = get_option('oc_membership_db_version', '1.0.0');
        
        if (version_compare($db_version, '1.1.0', '<')) {
            oc_log_debug('[Membership DB] Running cached data migration to 1.1.0...');
            
            // Include și rulează migrarea cu versiunea de la care se migrează
            require_once(plugin_dir_path(__FILE__) . 'membership-validator-migration.php');
            $migration = new OC_Membership_Migration();
            $migration->run_migration($db_version);

            oc_log_debug('[Membership DB] Cached data migration completed!');
        }
    }
    
    /**
     * Creează o validare membership nouă cu RENEWAL SYSTEM
     * 
     * @since 1.3.0 - Implementare renewal logic cu queue per variație
     */
    public function create_membership_validation(array $data): int {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        // Generează UUID și token-uri
        $membership_uuid = wp_generate_uuid4();
        $qr_token = $this->generate_qr_token();
        $qr_token_hash = hash('sha256', $qr_token);
        $access_code = $this->generate_access_code();
        
        // Colectează date din sisteme existente NON-INTRUZIV
        $schedule_data = $this->get_schedule_data_from_product($data['product_id'], $data['variation_id']);
        $woocommerce_metadata = $this->get_woocommerce_metadata($data['order_id'], $data['order_item_id']);
        $pool_metadata = $this->get_pool_metadata($data['product_id']);
        
        // 🎯 v1.3.0: RENEWAL SYSTEM - Calculează date cu logică queue per variație
        $duration_days = get_option('oc_membership_settings', [])['default_membership_duration'] ?? 28;
        $duration_days = intval($duration_days) ?: 28;
        
        // 🎯 v1.2.1: Folosește data de referință (data comenzii) dacă e disponibilă
        $reference_date = $data['reference_date'] ?? null;

        $activation_date_raw = isset($data['activation_date']) ? (string) $data['activation_date'] : '';
        $expiration_date_raw = isset($data['expiration_date']) ? (string) $data['expiration_date'] : '';

        $provided_activation_date = $this->normalize_iso_date_value($activation_date_raw);
        $provided_expiration_date = $this->normalize_iso_date_value($expiration_date_raw);

        if ($provided_activation_date !== '' && $provided_expiration_date !== '' && $provided_expiration_date < $provided_activation_date) {
            // Ignore invalid manual expiry and keep default auto-calculation path.
            $provided_expiration_date = '';
        }

        if ($provided_activation_date !== '') {
            $reference_date = $provided_activation_date;
        }
        
        $renewal_data = $this->calculate_membership_dates(
            $data['user_id'],
            $data['variation_id'] ?? 0,
            $duration_days,
            $reference_date
        );
        
        // 🎯 TOATE memberships-urile se creează cu status PENDING
        // Activarea se face DOAR manual prin butonul din interfață
        $renewal_data['status'] = 'pending';
        if ($provided_activation_date !== '') {
            $renewal_data['start_date'] = $provided_activation_date;
        }
        if ($provided_expiration_date !== '') {
            $renewal_data['expiration_date'] = $provided_expiration_date;
        }
        
        // 🎯 v1.2.0: Calculează sessions_allocated și used_sessions pentru tracking
        $is_unlimited = !empty($data['is_unlimited']) ? 1 : 0;
        $sessions_allocated = intval($data['sessions_allocated'] ?? $this->calculate_total_sessions($data));
        $sessions_used = 0; // Mereu 0 la creare
        $sessions_remaining = $sessions_allocated; // Toate ședințele disponibile

        if ($is_unlimited === 1) {
            $sessions_allocated = max($sessions_allocated, OC_UNLIMITED_SESSIONS);
            $sessions_remaining = $sessions_allocated;
            // 7CARD/ESX = sesiuni nelimitate + fără dată expirare
            // VIP Pool flag = sesiuni nelimitate + dată expirare normală (calculată mai sus)
            if (!empty($data['is_gateway_unlimited'])) {
                $renewal_data['expiration_date'] = null;
            }
        }
        
        $has_observations_column = (bool) $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'observations'
        ));

        $insert_data = [
            'user_id' => $data['user_id'],
            'order_id' => $data['order_id'],
            'order_item_id' => $data['order_item_id'],
            'product_id' => $data['product_id'],
            'variation_id' => $data['variation_id'] ?? 0,
            
            // 🎯 v1.2.0: Info pachet (backwards compatible)
            'package_product_id' => $data['package_product_id'] ?? 0,
            'package_order_item_id' => $data['package_order_item_id'] ?? 0,
            
            'membership_uuid' => $membership_uuid,
            'qr_token' => $qr_token, // Temporar pentru generare QR
            'qr_token_hash' => $qr_token_hash,
            'access_code' => $access_code,
            'schedule_id' => $schedule_data['id'] ?? 0,
            'course_product_id' => $schedule_data['product_id'] ?? 0,
            'course_variation_id' => $schedule_data['variation_id'] ?? 0,
            'weekday' => $schedule_data['weekday'] ?? 0,
            'start_time' => $schedule_data['start_time'] ?? null,
            'end_time' => $schedule_data['end_time'] ?? null,
            'room_number' => $schedule_data['room_number'] ?? 1,
            
            // Compatibility: păstrăm total_sessions, used_sessions, remaining_sessions
            'total_sessions' => $sessions_allocated, // CORECTARE: folosește sessions_allocated calculat, nu fallback-ul de 8
            'used_sessions' => $sessions_used,
            'remaining_sessions' => $sessions_allocated, // CORECTARE: inițial toate ședințele sunt disponibile
            
            // 🎯 v1.2.0: Tracking DIRECT ședințe
            'sessions_allocated' => $sessions_allocated,
            'is_unlimited' => $is_unlimited,
            
            // 🎯 v1.3.0: RENEWAL SYSTEM - Date calculate automat
            'start_date' => $renewal_data['start_date'],
            'expiration_date' => $renewal_data['expiration_date'],
            'duration_days' => $duration_days,
            'is_renewal' => $renewal_data['is_renewal'] ? 1 : 0,
            'previous_membership_id' => $renewal_data['previous_id'],
            'validation_status' => $renewal_data['status'],
            
            'woocommerce_metadata' => json_encode($woocommerce_metadata),
            'pool_metadata' => json_encode($pool_metadata),
            
            // 🎯 v1.2.1: Timestamp-uri folosesc data de referință (data comenzii) pentru sincronizări istorice
            // Suprascrie CURRENT_TIMESTAMP default pentru date corecte din istoric
            'created_at' => $this->build_reference_timestamp($reference_date),
            'updated_at' => $this->build_reference_timestamp($reference_date),
            'activated_at' => ($renewal_data['status'] === 'active') ? 
                ($reference_date ? $renewal_data['start_date'] . ' 00:00:00' : current_time('mysql')) : 
                null
        ];

        if ($has_observations_column) {
            $insert_data['observations'] = sanitize_textarea_field((string) ($data['observations'] ?? ''));
        }
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return 0;
        }
        
        $membership_id = $wpdb->insert_id;
        
        // 🎯 POPULEAZĂ DATELE CACHED pentru display rapid (inclusiv guest users)
        $this->populate_cached_data_for_membership($membership_id, $data);
        
        // Generează QR code (doar pentru active memberships)
        if ($renewal_data['status'] === 'active') {
            $this->generate_qr_code($membership_id, $qr_token);
        }
        
        // Șterge token-ul în clar după generarea QR (securitate)
        $wpdb->update(
            $table_name,
            ['qr_token' => null],
            ['id' => $membership_id]
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '✅ [Renewal System] Membership created: ID=%d, status=%s, start=%s, expires=%s, renewal=%s',
                $membership_id,
                $renewal_data['status'],
                $renewal_data['start_date'],
                $renewal_data['expiration_date'],
                $renewal_data['is_renewal'] ? 'YES' : 'NO'
            ));
        }
        
        // 🔄 CRITICAL: Invalidare cache după creare membership
        // Asigură că dashboard-ul afișează imediat datele noi
        $this->invalidate_membership_cache($data['user_id']);
        
        return $membership_id;
    }
    
    /**
     * 🔄 Invalidare cache memberships după operațiuni DB
     * 
     * Invalidează cache-ul pentru:
     * - User specific (pentru dashboard personal)
     * - Query-uri globale (pentru admin dashboard)
     * 
     * @param int|null $user_id User ID (null = invalidare globală)
     * @since 1.3.1
     */
    public function invalidate_membership_cache(?int $user_id = null): void {
        // Invalidare cache specific user
        if ($user_id) {
            wp_cache_delete('user_memberships_' . $user_id, 'memberships');
            wp_cache_delete('user_active_memberships_' . $user_id, 'memberships');
            wp_cache_delete('user_membership_summary_' . $user_id, 'memberships');
            wp_cache_delete("user_memberships_v13_{$user_id}_0_active_pending", 'membership_core_engine');
            wp_cache_delete("user_memberships_v13_{$user_id}_0_active_pending_expired", 'membership_core_engine');
            wp_cache_delete("user_memberships_v13_{$user_id}_0_active", 'membership_core_engine');
            wp_cache_delete("user_memberships_v13_{$user_id}_0_expired", 'membership_core_engine');
        }
        
        // Invalidare cache globală (pentru admin dashboard)
        wp_cache_delete('all_memberships', 'memberships');
        wp_cache_delete('all_members_list', 'memberships');
        wp_cache_delete('membership_stats', 'memberships');
        
        // Flush object cache complet pentru siguranță maximă
        // Necesar când se folosește cache persistent (Redis, Memcached)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('memberships');
            wp_cache_flush_group('membership_core_engine');
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '🔄 [Cache] Invalidated membership cache for user_id=%s',
                $user_id ? $user_id : 'ALL'
            ));
        }
    }
    
    /**
     * Citește date din Schedule Manager NON-INTRUZIV
     */
    private function get_schedule_data_from_product(int $product_id, int $variation_id = 0): array {
        global $wpdb;
        
        $schedule_table = $this->get_table_name('orar_cursuri');
        $search_product_id = $variation_id > 0 ? $variation_id : $product_id;
        
        $schedule = $wpdb->get_row($wpdb->prepare("
            SELECT id, product_id, variation_id, weekday, start_time, end_time, room_number
            FROM {$schedule_table}
            WHERE product_id = %d OR variation_id = %d
            ORDER BY id DESC
            LIMIT 1
        ", $search_product_id, $search_product_id), ARRAY_A);
        
        return $schedule ?: [];
    }
    
    /**
     * Citește metadata WooCommerce NON-INTRUZIV
     */
    private function get_woocommerce_metadata(int $order_id, int $order_item_id): array {
        $order = wc_get_order($order_id);
        if (!$order) {
            return [];
        }
        
        $order_item = $order->get_item($order_item_id);
        if (!$order_item) {
            return [];
        }
        
        return [
            'order_key' => $order->get_order_key(),
            'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'order_total' => $order->get_total(),
            'item_name' => $order_item->get_name(),
            'item_quantity' => $order_item->get_quantity(),
            'item_total' => $order_item->get_total()
        ];
    }
    
    /**
     * Citește metadata Pool Product Manager NON-INTRUZIV
     */
    private function get_pool_metadata(int $product_id): array {
        $pool_enabled = get_post_meta($product_id, '_oc_pool_enabled', true);
        $pool_price = get_post_meta($product_id, '_oc_pool_price', true);
        $pool_sessions = get_post_meta($product_id, '_oc_pool_sessions', true);
        
        return [
            'pool_enabled' => $pool_enabled === 'yes',
            'pool_price' => $pool_price,
            'pool_sessions' => intval($pool_sessions)
        ];
    }
    
    /**
     * Calculează total ședințe
     */
    private function calculate_total_sessions(array $data): int {
        // Citește din Pool Product Manager
        $pool_sessions = get_post_meta($data['product_id'], '_oc_pool_sessions', true);
        return intval($pool_sessions) ?: 8; // Default 8 ședințe (1 ședință = 1 oră)
    }
    
    /**
     * 🎯 POPULEAZĂ DATELE CACHED pentru un membership (inclusiv guest users)
     * 
     * Colectează date din WordPress, WooCommerce și le salvează în tabelul membership
     * pentru acces rapid fără JOIN-uri
     * 
     * @param int $membership_id ID membership creat
     * @param array $data Date originale folosite la creare
     * @since 1.2.1
     */
    private function populate_cached_data_for_membership(int $membership_id, array $data): void {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        
        $cached_data = [];
        $user_id = $data['user_id'] ?? 0;
        $order_id = $data['order_id'] ?? 0;
        
        // COLECTEAZĂ DATE UTILIZATOR (inclusiv guest users)
        if ($user_id > 0) {
            // Utilizator înregistrat - date din WordPress
            $user = get_user_by('id', $user_id);
            if ($user) {
                $cached_data['display_name'] = oc_membership_resolve_user_display_name($user, $data);
                $cached_data['email'] = $user->user_email;
                $cached_data['phone'] = get_user_meta($user_id, 'billing_phone', true) ?: get_user_meta($user_id, 'phone', true);
                $cached_data['user_registered'] = $user->user_registered;
                $cached_data['member_discount'] = get_user_meta($user_id, 'member_discount_coupon', true);
                $cached_data['last_attendance'] = get_user_meta($user_id, 'last_attendance_date', true);
            }
        }
        
        // COLECTEAZĂ DATE DIN COMANDĂ WOOCOMMERCE (pentru TOȚI utilizatorii)
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Pentru GUEST USERS (user_id = 0), ia datele din order billing
                if ($user_id == 0 || empty($cached_data['display_name'])) {
                    $full_name = oc_membership_resolve_user_display_name($user_id > 0 ? ($user ?? null) : null, $data, $order);
                    
                    if (!empty($full_name)) {
                        $cached_data['display_name'] = $full_name;
                    }
                    
                    $cached_data['email'] = $order->get_billing_email();
                    $cached_data['phone'] = $order->get_billing_phone();
                    $cached_data['user_registered'] = $order->get_date_created()->date('Y-m-d H:i:s');
                }
                
                // Date plată (pentru toți)
                $cached_data['payment_method'] = $this->normalize_payment_method_key(
                    (string) $order->get_payment_method(),
                    (string) $order->get_payment_method_title()
                );
                $cached_data['payment_status'] = $this->resolve_order_payment_status($order);
                
                // Prețul efectiv al pachetului poate fi suprascris manual înainte de activare.
                $cached_data['product_price'] = number_format(oc_membership_resolve_order_package_price($order), 2);
                
                // Numele pachetului real (nu pool product)
                $cached_data['product_name'] = $this->get_real_package_name_from_order($order);
                
                // Cursurile incluse
                $cached_data['courses_included'] = $this->get_courses_from_order($order);

                $has_observations_column = (bool) $wpdb->get_var($wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    'observations'
                ));
                if ($has_observations_column) {
                    // Observații administrative setate în fluxul Membership Manager
                    $cached_data['observations'] = sanitize_textarea_field((string) $order->get_meta('_oc_observations'));
                }
                
                // Cupoane folosite
                $coupons = $order->get_coupons();
                if (!empty($coupons)) {
                    $coupon_codes = [];
                    foreach ($coupons as $coupon_item) {
                        $coupon_codes[] = $coupon_item->get_code();
                    }
                    $cached_data['member_discount'] = implode(', ', $coupon_codes);
                }
            }
        }
        
        // Adaugă timestamp pentru sincronizare
        $cached_data['cached_data_synced_at'] = current_time('mysql');
        
        // UPDATE datele cached în tabelul membership
        $update_result = $wpdb->update(
            $table_name,
            $cached_data,
            ['id' => $membership_id],
            array_fill(0, count($cached_data), '%s'),
            ['%d']
        );
        
        // 🔄 CRITICAL FIX: Forțează commit DB pentru a preveni race conditions
        // Asigură că datele sunt scrise complet ÎNAINTE ca query-urile ulterioare să le citească
        if ($update_result !== false) {
            // Flush WordPress cache pentru acest membership
            wp_cache_delete("membership_cached_data_{$membership_id}", 'membership_core_engine');
            
            // Invalidează și cache-ul global pentru guest users
            wp_cache_delete('oc_guest_memberships_table', 'membership_manager');
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '✅ [Cached Data] Populat pentru membership #%d: %s (%s) - Update result: %s',
                $membership_id,
                $cached_data['display_name'] ?? 'N/A',
                $cached_data['email'] ?? 'N/A',
                $update_result !== false ? 'SUCCESS' : 'FAILED'
            ));
        }
    }
    
    /**
     * Helper: Mapează status comandă la payment status
     */
    private function map_order_status_to_payment(string $order_status): string {
        return oc_membership_map_order_status_to_payment($order_status);
    }

    private function resolve_order_payment_status(WC_Order $order): string {
        return oc_membership_resolve_order_payment_status($order);
    }

    /**
     * Normalizează metodele de plată la cheile canonice folosite în plugin.
     */
    private function normalize_payment_method_key(string $payment_method_id, string $payment_method_title = ''): string {
        return oc_membership_normalize_payment_method_key($payment_method_id, $payment_method_title);
    }
    
    /**
     * Helper: Găsește numele real al pachetului din order (nu pool product)
     */
    private function get_real_package_name_from_order($order): string {
        // Caută primul produs principal (nu variație) cu preț > 0
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $total = floatval($item->get_total());
            
            // Pachetul = produs fără variație + preț > 0
            if ($variation_id == 0 && $total > 0) {
                return $item->get_name();
            }
        }
        
        // Fallback: primul produs fără variație
        foreach ($order->get_items() as $item) {
            if ($item->get_variation_id() == 0) {
                return $item->get_name();
            }
        }
        
        return 'N/A';
    }
    
    /**
     * Helper: Găsește cursurile din order
     */
    private function get_courses_from_order($order): string {
        $courses = [];
        
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            
            // Caută variațiile (cursurile)
            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation_name = $variation->get_name();
                    
                    // Extrage numele cursului (după " - ")
                    if (strpos($variation_name, ' - ') !== false) {
                        $course_name = trim(substr($variation_name, strpos($variation_name, ' - ') + 3));
                        $courses[] = $course_name;
                    } else {
                        $courses[] = $variation_name;
                    }
                }
            }
        }
        
        return !empty($courses) ? implode(', ', $courses) : 'Toate cursurile';
    }
    
    /**
     * 🎯 RENEWAL SYSTEM: Calculează date membership cu logică "Queue per variație"
     * 
     * Logică:
     * - Același curs (variation_id) → PENDING (queue după ultimul)
     * - Curs diferit → ACTIVE imediat
     * 
     * @param int $user_id ID utilizator
     * @param int $variation_id ID variație curs
     * @param int $duration_days Durata abonament (zile)
     * @param string|null $reference_date Data de referință (default: azi) - pentru sincronizări istorice
     * @return array ['start_date' => ..., 'expiration_date' => ..., 'status' => ..., 'is_renewal' => bool, 'previous_id' => int|null]
     * @since 1.3.0
     */
    public function calculate_membership_dates(int $user_id, int $variation_id, int $duration_days = 28, ?string $reference_date = null): array {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        
        // 🎯 v1.2.1: Folosește data de referință (pentru sincronizări istorice) sau data curentă
        $today = $reference_date ?? oc_membership_current_business_date();
        
        // Verifică dacă există membership activ SAU pending pentru user + variation_id
        // Găsește ultimul membership (activ sau pending) pentru acest curs
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, expiration_date, validation_status 
             FROM {$table_name}
             WHERE user_id = %d 
             AND variation_id = %d 
             AND validation_status IN ('active', 'pending')
             ORDER BY expiration_date DESC
             LIMIT 1",
            $user_id,
            $variation_id
        ));
        
        // 🔒 MANUAL ACTIVATION: TOATE abonamentele sunt PENDING la achiziție
        // Admin activează manual la prima prezență fizică
        
        if ($existing && $existing->expiration_date) {
            // 🔄 RENEWAL: Același curs → Queue după ultimul
            $start_date = date('Y-m-d', strtotime($existing->expiration_date . ' +1 day'));
            $expiration_date = date('Y-m-d', strtotime($start_date . " +{$duration_days} days"));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '🔄 [Manual Activation] Renewal queued: user=%d, variation=%d, start=%s, status=pending (awaiting manual activation)',
                    $user_id, $variation_id, $start_date
                ));
            }
            
            return [
                'start_date' => $start_date,
                'expiration_date' => $expiration_date,
                'status' => 'pending', // 🔒 ÎNTOTDEAUNA pending
                'is_renewal' => true,
                'previous_id' => $existing->id
            ];
        } else {
            // ✨ NOU CURS: PENDING până la activare manuală
            $start_date = $today;
            $expiration_date = date('Y-m-d', strtotime($today . " +{$duration_days} days"));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '✨ [Manual Activation] New course: user=%d, variation=%d, start=%s, status=pending (awaiting manual activation)',
                    $user_id, $variation_id, $start_date
                ));
            }
            
            return [
                'start_date' => $start_date,
                'expiration_date' => $expiration_date,
                'status' => 'pending', // 🔒 ÎNTOTDEAUNA pending
                'is_renewal' => false,
                'previous_id' => null
            ];
        }
    }
    
    /**
     * 🎯 RENEWAL SYSTEM: Resetează ore la activarea membership-ului
     * 
     * Folosit când membership trece din PENDING → ACTIVE
     * Orele vechi dispar complet, se resetează din course_hours_config
     * 
     * @param int $membership_id ID membership de activat
     * @return bool Success
     * @since 1.3.0
     */
    public function reset_membership_hours_on_activation(int $membership_id): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        
        // Obține membership data
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT variation_id FROM {$table_name} WHERE id = %d",
            $membership_id
        ));
        
        if (!$membership || !$membership->variation_id) {
            return false;
        }
        
        // Citește ore din course_hours_config
        $config = $this->get_course_hours_config($membership->variation_id);
        
        if (!$config) {
            // Fallback: 8 ședințe default dacă nu există config
            $sessions = 8;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '⚠️ [Renewal System] No hours config for variation %d, using default 8 sessions',
                    $membership->variation_id
                ));
            }
        } else {
            $sessions = intval($config['sessions_per_month']);
        }
        
        // Resetează complet orele
        $result = $wpdb->update(
            $table_name,
            [
                'sessions_allocated' => $sessions,
                'total_sessions' => $sessions,
                'used_sessions' => 0,
                'remaining_sessions' => $sessions,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $membership_id],
            ['%d', '%d', '%d', '%d', '%s'],
            ['%d']
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '🔄 [Renewal System] Hours reset for membership %d: %d sessions allocated',
                $membership_id, $sessions
            ));
        }
        
        return $result !== false;
    }
    
    /**
     * Calculează data expirării (LEGACY - păstrat pentru compatibility)
     * Folosește calculate_membership_dates() pentru renewal logic
     */
    private function calculate_expiration_date(array $data): ?string {
        $expiry_days = get_post_meta($data['product_id'], '_oc_membership_expiry_days', true);
        $days = intval($expiry_days) ?: 365; // Default 1 an
        
        return date('Y-m-d', strtotime("+{$days} days"));
    }
    
    /**
     * Generează QR token
     */
    private function generate_qr_token(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generează access code
     */
    private function generate_access_code(): string {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
    }
    
    /**
     * Generează QR code (placeholder)
     */
    private function generate_qr_code(int $membership_id, string $qr_token): void {
        // Implementare QR code va fi în OC_Membership_QR
        do_action('oc_membership_generate_qr', $membership_id, $qr_token);
    }
    
    /**
     * Găsește membership prin QR token hash
     */
    public function get_membership_by_qr_hash(string $qr_token_hash): ?array {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();
        
        $membership = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE qr_token_hash = %s
            AND validation_status = 'active'
            AND (expiration_date IS NULL OR expiration_date >= %s)
        ", $qr_token_hash, $today), ARRAY_A);
        
        return $membership ?: null;
    }
    
    /**
     * Actualizează validare membership
     */
    public function update_validation(int $membership_id, array $data): bool {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        // Adaugă timestamp automat
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $membership_id]
        );
        
        return $result !== false;
    }
    
    /**
     * Găsește membership-uri pentru utilizator
     */
    public function get_user_memberships(int $user_id, string $status = 'active'): array {
        global $wpdb;

        $this->sync_membership_statuses($user_id);
        
        $table_name = $this->get_table_name('membership_validations');

        $query = "SELECT * FROM {$table_name}
            WHERE user_id = %d
            AND validation_status = %s";
        $query_args = [$user_id, $status];

        if ($status === 'active') {
            $query .= ' AND (expiration_date IS NULL OR expiration_date >= %s)';
            $query_args[] = oc_membership_current_business_date();
        }

        $query .= ' ORDER BY created_at DESC';

        $memberships = $wpdb->get_results($wpdb->prepare($query, ...$query_args), ARRAY_A);
        
        return $memberships ?: [];
    }
    
    /**
     * Get table version
     */
    public function get_table_version(): string {
        return $this->table_version;
    }
    
    /**
     * Cleanup method pentru maintenance
     */
    public function cleanup_expired_tokens(): void {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        // Șterge token-uri expirate mai vechi de 24h
        $wpdb->query("
            UPDATE {$table_name}
            SET qr_token = NULL
            WHERE qr_token IS NOT NULL
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }

    /**
     * Actualizează status membership-uri pe baza order_id
     */
    public function update_membership_status_by_order(int $order_id, string $status): bool {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        // Obține user_id pentru invalidare cache
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table_name} WHERE order_id = %d",
            $order_id
        ));
        
        $result = $wpdb->update(
            $table_name,
            ['validation_status' => $status, 'updated_at' => current_time('mysql')],
            ['order_id' => $order_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Invalidare cache după update
        if ($result !== false && !empty($user_ids)) {
            foreach ($user_ids as $user_id) {
                $this->invalidate_membership_cache($user_id);
            }
        }
        
        return $result !== false;
    }

    /**
     * Leagă membership de pachet Pool Product Manager
     */
    public function link_pool_package(int $product_id, int $package_id, array $config): bool {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        // Actualizează toate membership-urile pentru acest produs cu info pool
        $pool_metadata = json_encode([
            'pool_package_id' => $package_id,
            'pool_config' => $config,
            'linked_at' => current_time('mysql')
        ]);
        
        $result = $wpdb->update(
            $table_name,
            ['pool_metadata' => $pool_metadata, 'updated_at' => current_time('mysql')],
            ['product_id' => $product_id, 'validation_status' => 'active'],
            ['%s', '%s'],
            ['%d', '%s']
        );
        
        return $result !== false;
    }

    /**
     * Numără membership-uri active pentru un anumit schedule_id
     */
    public function count_active_memberships_for_schedule(int $schedule_id): int {
        global $wpdb;
        
        $table_name = $this->get_table_name('membership_validations');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE schedule_id = %d AND validation_status = 'active'",
            $schedule_id
        ));
        
        return (int) $count;
    }

    // ========================================
    // QR CODE METHODS - FAZA 2
    // ========================================

    /**
     * Update QR data pentru un validation
     * 
     * @param int $validation_id ID validation
     * @param array $qr_data Date QR (token, filename) - qr_secret salvat în pool_metadata
     * @return bool Success
     */
    public function update_qr_data(int $validation_id, array $qr_data): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $update_data = [
            'updated_at' => current_time('mysql')
        ];

        $update_format = ['%s'];

        // Adaugă QR token dacă există
        if (isset($qr_data['qr_token'])) {
            $update_data['qr_token'] = sanitize_text_field($qr_data['qr_token']);
            $update_format[] = '%s';
        }

        // Citește metadata existentă
        $existing_metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT pool_metadata FROM {$table_name} WHERE id = %d",
            $validation_id
        ));
        
        $metadata = $existing_metadata ? json_decode($existing_metadata, true) : [];
        
        // Adaugă filename în pool_metadata ca JSON
        if (isset($qr_data['qr_filename'])) {
            $metadata['qr_filename'] = sanitize_file_name($qr_data['qr_filename']);
            $metadata['qr_generated_at'] = $qr_data['qr_generated_at'] ?? current_time('mysql');
        }
        
        // Adaugă QR secret în pool_metadata (nu în coloană separată)
        if (isset($qr_data['qr_secret'])) {
            $metadata['qr_secret'] = sanitize_text_field($qr_data['qr_secret']);
        }
        
        // Update metadata
        $update_data['pool_metadata'] = json_encode($metadata);
        $update_format[] = '%s';

        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $validation_id],
            $update_format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Obține validation prin QR token
     * 
     * @param string $qr_token Token de căutat
     * @return object|null Validation object sau null
     */
    public function get_validation_by_qr_token(string $qr_token): ?object {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $validation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE qr_token = %s LIMIT 1",
            $qr_token
        ));

        return $validation ?: null;
    }

    /**
     * Obține validation prin ID
     * 
     * @param int $validation_id ID validation
     * @return object|null Validation object sau null
     */
    public function get_validation_by_id(int $validation_id): ?object {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $validation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
            $validation_id
        ));

        return $validation ?: null;
    }

    /**
     * Update numărul de ședințe folosite
     * 
     * @param int $validation_id ID validation
     * @param int $sessions_used Numărul nou de ședințe folosite
     * @return bool Success
     */
    public function update_sessions_count(int $validation_id, int $sessions_used): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $result = $wpdb->update(
            $table_name,
            [
                'sessions_used' => $sessions_used,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $validation_id],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update status membership
     * 
     * @param int $validation_id ID validation
     * @param string $status Noul status
     * @return bool Success
     */
    public function update_membership_status(int $validation_id, string $status): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $result = $wpdb->update(
            $table_name,
            [
                'validation_status' => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $validation_id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Obține validations expirate pentru cleanup
     * 
     * @return array Lista validation-urilor expirate
     */
    public function get_expired_validations(): array {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();

        $expired = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE validation_status IN ('active', 'expired') 
             AND expiration_date IS NOT NULL 
             AND expiration_date < %s",
            $today
        ));

        return $expired ?: [];
    }

    // ========================================
    // STATISTICI QR CODES - FAZA 2
    // ========================================

    /**
     * Numără QR codes generate
     */
    public function count_qr_codes_generated(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE qr_token IS NOT NULL AND qr_token != ''"
        );

        return (int) $count;
    }

    /**
     * Numără QR codes active
     */
    public function count_active_qr_codes(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE validation_status = 'active' 
             AND (expiration_date IS NULL OR expiration_date >= %s)
             AND qr_token IS NOT NULL 
             AND qr_token != ''",
            $today
        ));

        return (int) $count;
    }

    /**
     * Numără QR codes expirate
     */
    public function count_expired_qr_codes(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE (validation_status = 'expired' OR expiration_date < %s)
             AND qr_token IS NOT NULL 
             AND qr_token != ''",
            $today
        ));

        return (int) $count;
    }

    /**
     * Numără validări astăzi
     */
    public function count_validations_today(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $today = oc_membership_current_business_date();
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE sessions_used > 0 
             AND DATE(updated_at) = %s",
            $today
        ));

        return (int) $count;
    }

    /**
     * Numără validări săptămâna aceasta
     */
    public function count_validations_week(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $week_start = current_time('Y-m-d', strtotime('monday this week'));
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE sessions_used > 0 
             AND DATE(updated_at) >= %s",
            $week_start
        ));

        return (int) $count;
    }

    /**
     * Numără validări luna aceasta
     */
    public function count_validations_month(): int {
        global $wpdb;
        $table_name = $this->get_table_name('membership_validations');

        $month_start = current_time('Y-m-01');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE sessions_used > 0 
             AND DATE(updated_at) >= %s",
            $month_start
        ));

        return (int) $count;
    }

    // =========================================================================
    // 🎯 FUNCȚII PENTRU SISTEMUL DE MAPARE ABONAMENTE → CURSURI
    // =========================================================================

    /**
     * Salvează maparea dintre un abonament și variațiile validate
     */
    public function save_membership_course_mapping(int $membership_product_id, array $variation_ids): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_course_mapping');

        // Șterge mapările existente pentru acest abonament
        $wpdb->delete($table_name, ['membership_product_id' => $membership_product_id]);

        // Adaugă mapările noi
        foreach ($variation_ids as $variation_id) {
            $wpdb->insert($table_name, [
                'membership_product_id' => $membership_product_id,
                'variation_id' => absint($variation_id),
                'is_valid' => 1
            ]);
        }

        return true;
    }

    /**
     * Obține toate mapările pentru un abonament
     */
    public function get_membership_course_mappings(int $membership_product_id): array {
        global $wpdb;
        $table_name = $this->get_table_name('membership_course_mapping');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT mcm.*, 
                    v.post_title as variation_name,
                    oc.weekday, oc.start_time, oc.end_time, oc.room_number,
                    oc.product_id as pool_id
             FROM {$table_name} mcm
             LEFT JOIN {$wpdb->posts} v ON mcm.variation_id = v.ID
             LEFT JOIN {$wpdb->prefix}orar_cursuri oc ON mcm.variation_id = oc.variation_id
             WHERE mcm.membership_product_id = %d 
             AND mcm.is_valid = 1
             ORDER BY v.post_title",
            $membership_product_id
        ), ARRAY_A);
    }

    /**
     * Verifică dacă un abonament validează o variație specifică
     */
    public function can_membership_validate_variation(int $membership_product_id, int $variation_id): bool {
        global $wpdb;
        $table_name = $this->get_table_name('membership_course_mapping');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE membership_product_id = %d 
             AND variation_id = %d 
             AND is_valid = 1",
            $membership_product_id,
            $variation_id
        ));

        return $exists > 0;
    }

    /**
     * Obține toate abonamentele și cursurile lor mapate
     */
    public function get_all_membership_mappings(): array {
        global $wpdb;
        $table_name = $this->get_table_name('membership_course_mapping');

        return $wpdb->get_results("
            SELECT mcm.membership_product_id,
                   p.post_title as membership_name,
                   COUNT(mcm.variation_id) as mapped_variations
            FROM {$table_name} mcm
            LEFT JOIN {$wpdb->posts} p ON mcm.membership_product_id = p.ID
            WHERE mcm.is_valid = 1
            GROUP BY mcm.membership_product_id
            ORDER BY p.post_title
        ", ARRAY_A);
    }

    // =========================================================================
    // 🎯 SISTEM SIMPLIFICAT: COURSE HOURS CONFIG (v1.2.0)
    // =========================================================================

    /**
     * Creează tabelul course_hours_config pentru template-uri ore/ședințe
     * 
     * Tabel mic, doar configurare variation_id → ore/ședințe
     * Înlocuiește sistemul complex de mapare cu unul simplu
     * 
     * @since 1.2.0
     */
    public function create_course_hours_config_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table = $this->get_table_name('course_hours_config');
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_variation_id bigint(20) unsigned NOT NULL COMMENT 'ID variație curs',
            hours_per_month int(11) DEFAULT 0 COMMENT 'Ore template per lună',
            sessions_per_month int(11) DEFAULT 0 COMMENT 'Ședințe template per lună',
            is_unlimited tinyint(1) DEFAULT 0 COMMENT 'VIP unlimited flag',
            notes text DEFAULT NULL COMMENT 'Notițe admin',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_variation (course_variation_id),
            KEY idx_unlimited (is_unlimited)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Membership DB] Course hours config table created/verified");
        }
    }

    /**
     * Salvează configurarea ore/ședințe pentru un curs
     * 
     * @param int $variation_id ID variație curs
     * @param int $hours Ore per lună
     * @param int $sessions Ședințe per lună
     * @param bool $is_unlimited VIP unlimited flag
     * @return bool Success
     * 
     * @since 1.2.0
     */
    public function save_course_hours_config(int $variation_id, int $hours, int $sessions, bool $is_unlimited = false): bool {
        global $wpdb;
        $table = $this->get_table_name('course_hours_config');
        
        $result = $wpdb->replace($table, [
            'course_variation_id' => $variation_id,
            'hours_per_month' => $hours,
            'sessions_per_month' => $sessions,
            'is_unlimited' => $is_unlimited ? 1 : 0
        ], ['%d', '%d', '%d', '%d']);
        
        return $result !== false;
    }

    /**
     * Obține configurarea ore/ședințe pentru un curs
     * 
     * @param int $variation_id ID variație curs
     * @return array|null Date configurare sau null dacă nu există
     * 
     * @since 1.2.0
     */
    public function get_course_hours_config(int $variation_id): ?array {
        global $wpdb;
        $table = $this->get_table_name('course_hours_config');
        
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE course_variation_id = %d", 
            $variation_id
        ), ARRAY_A);
        
        return $config ?: null;
    }

    /**
     * Obține toate configurările ore/ședințe
     * 
     * @return array Lista completă de configurări
     * 
     * @since 1.2.0
     */
    public function get_all_course_hours_configs(): array {
        global $wpdb;
        $table = $this->get_table_name('course_hours_config');
        
        $configs = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY course_variation_id", 
            ARRAY_A
        );
        
        return $configs ?: [];
    }

    private function normalize_iso_date_value(string $raw_value): string {
        $raw_value = trim($raw_value);
        if ($raw_value === '') {
            return '';
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw_value, $matches)) {
            return '';
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private function build_reference_timestamp($reference_date): string {
        $normalized_reference_date = $this->normalize_iso_date_value((string) $reference_date);
        if ($normalized_reference_date === '') {
            return current_time('mysql');
        }

        return $normalized_reference_date . ' 00:00:00';
    }
}
