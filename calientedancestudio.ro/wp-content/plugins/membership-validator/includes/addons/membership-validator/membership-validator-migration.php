<?php
/**
 * Membership Validator - Database Migration
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
 * Class OC_Membership_Migration
 * 
 * Gestionare migrări și upgrade-uri DB
 * Implementare NON-INTRUZIVĂ conform .cursorrules
 */
class OC_Membership_Migration {
    
    /**
     * Versiunea curentă a schema DB
     */
    private string $current_version = '2.0.0';
    
    /**
     * Opțiunea pentru versiunea DB
     */
    private string $version_option = 'oc_membership_db_version';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', [$this, 'check_migration'], 15);
        add_action('admin_init', [$this, 'admin_migration_check']);
    }
    
    /**
     * Verifică dacă e necesară migrarea
     */
    public function check_migration(): void {
        $installed_version = get_option($this->version_option, '0.0.0');
        
        if (version_compare($installed_version, $this->current_version, '<')) {
            $this->run_migration($installed_version);
        }
    }
    
    /**
     * Verificare migrare în admin
     */
    public function admin_migration_check(): void {
        if (current_user_can('manage_options')) {
            $this->check_migration();
        }
    }
    
    /**
     * Rulează migrarea
     */
    public function run_migration(string $from_version): void {
        global $wpdb;
        
        // Backup tabelelor existente înainte de migrare
        $this->backup_existing_tables();
        
        try {
            // Migrări step-by-step
            if (version_compare($from_version, '1.0.0', '<')) {
                $this->migrate_to_1_0_0();
            }
            
            if (version_compare($from_version, '1.1.0', '<')) {
                $this->migrate_to_1_1_0();
            }
            
            if (version_compare($from_version, '1.2.0', '<')) {
                $this->migrate_to_1_2_0();
            }
            
            // Actualizează versiunea
            update_option($this->version_option, $this->current_version);
            
            // Migration successful
            
        } catch (Exception $e) {
            // Restore backup dacă e posibil
            $this->restore_backup_if_needed();
            
            // Show admin notice
            add_action('admin_notices', [$this, 'migration_error_notice']);
        }
    }
    
    /**
     * Migrare la versiunea 1.0.0 - Tabele inițiale
     */
    private function migrate_to_1_0_0(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Tabel principal membership_validations
        $this->create_membership_validations_table();
        
        // 2. Tabel log validări
        $this->create_validation_log_table();
        
        // 3. Migrează date existente dacă sunt (din Pool Product Manager)
        $this->migrate_existing_pool_data();
        
        // 4. Creează indexuri pentru performanță
        $this->create_performance_indexes();
    }
    
    /**
     * Creează tabelul principal membership_validations
     */
    private function create_membership_validations_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            
            -- Identificare utilizator și comandă
            user_id bigint(20) unsigned NOT NULL COMMENT 'ID utilizator WordPress',
            order_id bigint(20) unsigned NOT NULL COMMENT 'ID comandă WooCommerce',
            order_item_id bigint(20) unsigned NOT NULL COMMENT 'ID item comandă WooCommerce',
            
            -- Identificare produs
            product_id bigint(20) unsigned NOT NULL COMMENT 'ID produs WooCommerce',
            variation_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID variație produs',
            
            -- Coduri unice și QR
            membership_uuid varchar(36) NOT NULL COMMENT 'UUID v4 unic per abonament',
            qr_token varchar(64) DEFAULT NULL COMMENT 'Token QR temporar pentru generare',
            qr_token_hash char(64) NOT NULL COMMENT 'Hash SHA-256 pentru validare',
            qr_token_revoked_at datetime DEFAULT NULL COMMENT 'Timestamp revocare token',
            qr_code_url text COMMENT 'URL către imagine QR generată',
            access_code varchar(20) COMMENT 'Cod alfanumeric pentru acces manual',
            
            -- Date din Schedule Manager (citite NON-INTRUZIV)
            schedule_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID din orar_cursuri',
            course_product_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID produs curs',
            course_variation_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID variație curs',
            weekday tinyint(1) unsigned DEFAULT 0 COMMENT 'Ziua săptămânii',
            start_time time DEFAULT NULL COMMENT 'Ora început',
            end_time time DEFAULT NULL COMMENT 'Ora sfârșit',
            room_number tinyint(1) unsigned DEFAULT 1 COMMENT 'Numărul sălii',
            
            -- Gestionarea ședințelor
            total_sessions int(11) NOT NULL DEFAULT 0 COMMENT 'Total ședințe',
            used_sessions int(11) NOT NULL DEFAULT 0 COMMENT 'Ședințe folosite',
            remaining_sessions int(11) NOT NULL DEFAULT 0 COMMENT 'Ședințe rămase',
            expiration_date date DEFAULT NULL COMMENT 'Data expirării',
            
            -- Status și validări
            validation_status enum('active','expired','cancelled','suspended','transferred') DEFAULT 'active',
            last_validation_date datetime DEFAULT NULL COMMENT 'Data ultimei validări',
            validation_count int(11) NOT NULL DEFAULT 0 COMMENT 'Numărul validărilor',
            last_validator_user_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID validator',
            
            -- Restricții și reguli
            max_daily_validations int(11) DEFAULT 0 COMMENT 'Maxim validări pe zi',
            validation_restriction enum('none','once_per_day','once_per_session','unlimited') DEFAULT 'none',
            allowed_days_of_week varchar(32) DEFAULT NULL COMMENT 'JSON: zile permise',
            allowed_time_slots text DEFAULT NULL COMMENT 'JSON: intervale orare',
            
            -- Metadata
            membership_metadata longtext DEFAULT NULL COMMENT 'JSON: date suplimentare',
            woocommerce_metadata longtext DEFAULT NULL COMMENT 'JSON: date WooCommerce',
            pool_metadata longtext DEFAULT NULL COMMENT 'JSON: date Pool Manager',
            
            -- Timestamps
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            activated_at datetime DEFAULT NULL COMMENT 'Data activării',
            suspended_at datetime DEFAULT NULL COMMENT 'Data suspendării',
            
            PRIMARY KEY (id),
            UNIQUE KEY membership_uuid (membership_uuid),
            UNIQUE KEY qr_token_hash (qr_token_hash),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY schedule_id (schedule_id),
            KEY validation_status (validation_status),
            KEY expiration_date (expiration_date),
            KEY created_at (created_at)
        ) {$charset_collate} COMMENT='Validări membership - NON-INTRUZIV';";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Creează tabelul de log validări
     */
    private function create_validation_log_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'membership_validation_log';
        
        $sql = "CREATE TABLE {$table_name} (
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
        ) {$charset_collate} COMMENT='Log validări membership';";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Migrează date existente din Pool Product Manager - NON-INTRUZIV
     */
    private function migrate_existing_pool_data(): void {
        global $wpdb;
        
        // Citește comenzi WooCommerce cu produse Pool - NON-INTRUZIV
        $orders = $wpdb->get_results("
            SELECT DISTINCT p.ID as order_id, p.post_date, pm.meta_value as user_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_customer_user'
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ORDER BY p.post_date DESC
        ");
        
        foreach ($orders as $order_data) {
            $this->migrate_order_pool_items($order_data);
        }
    }
    
    /**
     * Migrează item-urile unei comenzi care au Pool enabled
     */
    private function migrate_order_pool_items($order_data): void {
        $order = wc_get_order($order_data->order_id);
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Verifică dacă produsul are Pool enabled - NON-INTRUZIV
            $search_id = $variation_id > 0 ? $variation_id : $product_id;
            $pool_enabled = get_post_meta($search_id, '_oc_pool_enabled', true);
            
            if ($pool_enabled === 'yes') {
                $this->create_migrated_membership($order_data, $item);
            }
        }
    }
    
    /**
     * Creează membership migrat din date Pool
     */
    private function create_migrated_membership($order_data, $order_item): void {
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            return;
        }
        
        // Verifică dacă nu există deja
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$table_name}
            WHERE order_id = %d AND order_item_id = %d
        ", $order_data->order_id, $order_item->get_id()));
        
        if ($exists) {
            return; // Deja migrat
        }
        
        // Creează membership nou
        $validator->get_db()->create_membership_validation([
            'user_id' => $order_data->user_id,
            'order_id' => $order_data->order_id,
            'order_item_id' => $order_item->get_id(),
            'product_id' => $order_item->get_product_id(),
            'variation_id' => $order_item->get_variation_id()
        ]);
    }
    
    /**
     * Migrare la versiunea 1.1.0 - Adaugă coloane pentru date cached
     */
    private function migrate_to_1_1_0(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Adaugă coloanele pentru date cached (eliminate JOIN-urile)
        $columns_to_add = [
            // Date utilizator din WordPress
            "ADD COLUMN display_name varchar(250) DEFAULT NULL COMMENT 'Nume afișat utilizator cached'",
            "ADD COLUMN email varchar(100) DEFAULT NULL COMMENT 'Email utilizator cached'", 
            "ADD COLUMN phone varchar(20) DEFAULT NULL COMMENT 'Telefon utilizator cached'",
            "ADD COLUMN user_registered datetime DEFAULT NULL COMMENT 'Data înregistrare user cached'",
            
            // Date produs din WooCommerce
            "ADD COLUMN product_name varchar(255) DEFAULT NULL COMMENT 'Numele produsului cached'",
            "ADD COLUMN product_price decimal(10,2) DEFAULT 0.00 COMMENT 'Prețul produs cached'",
            "ADD COLUMN courses_included text DEFAULT NULL COMMENT 'Cursuri incluse cached'",
            
            // Date plată din WooCommerce
            "ADD COLUMN payment_method varchar(50) DEFAULT NULL COMMENT 'Metoda plată cached'",
            "ADD COLUMN payment_status varchar(20) DEFAULT NULL COMMENT 'Status plată cached'",
            
            // Date suplimentare
            "ADD COLUMN member_discount varchar(50) DEFAULT NULL COMMENT 'Reducere membru cached'",
            "ADD COLUMN last_attendance datetime DEFAULT NULL COMMENT 'Ultima prezență cached'",
            
            // Flag pentru sincronizare
            "ADD COLUMN cached_data_synced_at datetime DEFAULT NULL COMMENT 'Ultima sincronizare date cached'"
        ];
        
        foreach ($columns_to_add as $column_sql) {
            $full_sql = "ALTER TABLE {$table_name} {$column_sql}";
            
            // Verifică dacă coloana nu există deja
            $column_check = explode(' ', $column_sql)[2]; // Extrage numele coloanei
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, str_replace($wpdb->prefix, '', $table_name), $column_check
            ));
            
            if (!$exists) {
                $wpdb->query($full_sql);
            }
        }
        
        // Populează datele existente cu valorile cached
        $this->populate_cached_data();
        
        // Adaugă indexuri pentru coloanele noi
        $this->create_cached_data_indexes();
    }
    
    /**
     * Populează datele cached pentru înregistrările existente
     */
    private function populate_cached_data(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Obține toate membership-urile care nu au date cached
        $memberships = $wpdb->get_results($wpdb->prepare("
            SELECT id, user_id, order_id, product_id, variation_id 
            FROM {$table_name} 
            WHERE cached_data_synced_at IS NULL
            LIMIT 100
        "));
        
        foreach ($memberships as $membership) {
            $cached_data = $this->collect_cached_data_for_membership($membership);
            
            // UPDATE cu datele cached
            $wpdb->update(
                $table_name,
                array_merge($cached_data, ['cached_data_synced_at' => current_time('mysql')]),
                ['id' => $membership->id],
                array_merge(array_fill(0, count($cached_data), '%s'), ['%s']),
                ['%d']
            );
        }
    }
    
    /**
     * Colectează datele cached pentru un membership
     */
    private function collect_cached_data_for_membership($membership): array {
        $cached_data = [];
        
        // Date utilizator din WordPress
        if ($membership->user_id > 0) {
            $user = get_user_by('id', $membership->user_id);
            if ($user) {
                $cached_data['display_name'] = oc_membership_resolve_user_display_name($user, $membership);
                $cached_data['email'] = $user->user_email;
                $cached_data['phone'] = get_user_meta($membership->user_id, 'phone', true) ?: get_user_meta($membership->user_id, 'billing_phone', true);
                $cached_data['user_registered'] = $user->user_registered;
                $cached_data['member_discount'] = get_user_meta($membership->user_id, 'member_discount_coupon', true);
                $cached_data['last_attendance'] = get_user_meta($membership->user_id, 'last_attendance_date', true);
            }
        }
        
        // Date din WooCommerce order
        if ($membership->order_id > 0) {
            $order = wc_get_order($membership->order_id);
            if ($order) {
                // Pentru guest users, obține datele din order
                if ($membership->user_id == 0) {
                    $cached_data['display_name'] = oc_membership_resolve_user_display_name(null, $membership, $order);
                    $cached_data['email'] = $order->get_billing_email();
                    $cached_data['phone'] = $order->get_billing_phone();
                    $cached_data['user_registered'] = $order->get_date_created()->date('Y-m-d H:i:s');
                }
                
                // Date plată
                $payment_method_title = $order->get_payment_method_title();
                $payment_method = $order->get_payment_method();
                $cached_data['payment_method'] = $payment_method_title ?: $payment_method ?: 'unknown';
                $cached_data['payment_status'] = $this->get_payment_status_from_order($order);
                
                // Date produs - găsește pachetul real
                $cached_data['product_name'] = $this->get_real_package_name_from_order_migration($membership->order_id);
                $cached_data['product_price'] = $this->get_product_price_from_order($order, $membership);
                $cached_data['courses_included'] = $this->get_courses_from_order($order, $membership);
            }
        }
        
        return array_filter($cached_data); // Elimină valorile null
    }
    
    /**
     * Helper: obține status plată din order
     */
    private function get_payment_status_from_order($order): string {
        $status = $order->get_status();
        
        switch ($status) {
            case 'completed':
            case 'processing':
                return 'paid';
            case 'pending':
                return 'unpaid';
            case 'on-hold':
                return 'partial';
            case 'cancelled':
            case 'refunded':
            case 'failed':
                return 'unpaid';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Helper: obține numele real al pachetului din order
     */
    private function get_real_package_name_from_order_migration(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }
        
        // Caută primul produs principal (nu variație)
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            if ($product_id && !$variation_id) {
                return $item->get_name();
            }
        }
        
        // Fallback: primul item
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            return $first_item->get_name();
        }
        
        return '';
    }
    
    /**
     * Helper: obține prețul produsului din order
     */
    private function get_product_price_from_order($order, $membership): float {
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();
            
            $product_match = ($item_product_id == $membership->product_id);
            $variation_match = ($membership->variation_id && $item_variation_id == $membership->variation_id);
            
            if ($product_match || $variation_match) {
                return (float)$item->get_total();
            }
        }
        
        return (float)$order->get_total();
    }
    
    /**
     * Helper: obține cursurile din order
     */
    private function get_courses_from_order($order, $membership): string {
        // Caută variațiile (cursurile) pentru product_id
        $courses = [];
        
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();
            
            if ($item_product_id == $membership->product_id && $item_variation_id > 0) {
                $variation = wc_get_product($item_variation_id);
                if ($variation) {
                    $variation_name = $variation->get_name();
                    
                    // Curăță numele
                    $parent_product = wc_get_product($item_product_id);
                    if ($parent_product) {
                        $parent_name = $parent_product->get_name();
                        $clean_name = str_replace($parent_name . ' - ', '', $variation_name);
                        $clean_name = str_replace($parent_name, '', $clean_name);
                        $clean_name = trim($clean_name, ' -');
                        
                        $courses[] = !empty($clean_name) ? $clean_name : $variation_name;
                    } else {
                        $courses[] = $variation_name;
                    }
                }
            }
        }
        
        return !empty($courses) ? implode(', ', $courses) : 'Toate cursurile';
    }
    
    /**
     * Creează indexuri pentru datele cached
     */
    private function create_cached_data_indexes(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_cached_email ON {$table_name} (email)",
            "CREATE INDEX IF NOT EXISTS idx_cached_phone ON {$table_name} (phone)", 
            "CREATE INDEX IF NOT EXISTS idx_cached_product_name ON {$table_name} (product_name)",
            "CREATE INDEX IF NOT EXISTS idx_cached_payment_status ON {$table_name} (payment_status)",
            "CREATE INDEX IF NOT EXISTS idx_cached_sync ON {$table_name} (cached_data_synced_at)"
        ];
        
        foreach ($indexes as $sql) {
            $wpdb->query($sql);
        }
    }
    
    /**
     * Creează indexuri pentru performanță
     */
    private function create_performance_indexes(): void {
        global $wpdb;
        
        $validations_table = $wpdb->prefix . 'membership_validations';
        $log_table = $wpdb->prefix . 'membership_validation_log';
        
        // Indexuri compuse pentru căutări frecvente
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_user_status ON {$validations_table} (user_id, validation_status)",
            "CREATE INDEX IF NOT EXISTS idx_product_status ON {$validations_table} (product_id, validation_status)",
            "CREATE INDEX IF NOT EXISTS idx_schedule_time ON {$validations_table} (schedule_id, weekday, start_time)",
            "CREATE INDEX IF NOT EXISTS idx_expiry_status ON {$validations_table} (expiration_date, validation_status)",
            "CREATE INDEX IF NOT EXISTS idx_log_membership_date ON {$log_table} (membership_id, validation_date)",
            "CREATE INDEX IF NOT EXISTS idx_log_user_date ON {$log_table} (user_id, validation_date)"
        ];
        
        foreach ($indexes as $sql) {
            $wpdb->query($sql);
        }
    }
    
    /**
     * Backup tabelelor existente
     */
    private function backup_existing_tables(): void {
        global $wpdb;
        
        $tables_to_backup = [
            $wpdb->prefix . 'membership_validations',
            $wpdb->prefix . 'membership_validation_log'
        ];
        
        foreach ($tables_to_backup as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                $backup_table = $table . '_backup_' . wp_date('Y_m_d_H_i_s');
                $safe_backup_table = esc_sql($backup_table);
                $safe_table = esc_sql($table);
                $wpdb->query("CREATE TABLE `{$safe_backup_table}` AS SELECT * FROM `{$safe_table}`");
            }
        }
    }
    
    /**
     * Restore backup dacă e necesar
     */
    private function restore_backup_if_needed(): void {
        // Implementare restore în caz de eroare critică
        // Pentru moment, doar log error
        // Backup restore might be needed
    }
    
    /**
     * Notice pentru eroare migrare
     */
    public function migration_error_notice(): void {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Membership Validator:</strong> ';
        echo 'Database migration failed. Please check error logs or contact support.';
        echo '</p></div>';
    }
    
    /**
     * Obține versiunea curentă DB
     */
    public function get_current_version(): string {
        return $this->current_version;
    }
    
    /**
     * Obține versiunea instalată
     */
    public function get_installed_version(): string {
        return get_option($this->version_option, '0.0.0');
    }
    
    /**
     * Forțează o re-migrare (pentru debugging)
     */
    public function force_migration(): void {
        delete_option($this->version_option);
        $this->check_migration();
    }
    
    /**
     * Forțează re-popularea datelor cached (pentru debugging payment issues)
     */
    public function force_cached_data_repopulation(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Reset cached_data_synced_at pentru a forța re-popularea
        $safe_table_name = esc_sql($table_name);
        $wpdb->query("UPDATE `{$safe_table_name}` SET cached_data_synced_at = NULL WHERE cached_data_synced_at IS NOT NULL");
        
        // Rulează popularea din nou
        $this->populate_cached_data();
    }
    
    /**
     * Verifică integritatea tabelelor
     */
    public function verify_table_integrity(): array {
        global $wpdb;
        
        $results = [];
        
        $tables = [
            'membership_validations' => $wpdb->prefix . 'membership_validations',
            'membership_validation_log' => $wpdb->prefix . 'membership_validation_log'
        ];
        
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            $results[$name] = [
                'exists' => $exists,
                'table_name' => $table
            ];
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                $results[$name]['record_count'] = intval($count);
            }
        }
        
        return $results;
    }
    
    /**
     * Migrare la versiunea 1.2.0 - Tracking direct + pachet info
     * 
     * Adaugă coloane pentru tracking DIRECT al ședințelor și info pachet
     * Sistemul simplificat: 2 TABELE TOTAL
     * 
     * @since 1.2.0
     */
    private function migrate_to_1_2_0(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Adaugă coloanele pentru tracking pachet
        $columns_to_add = [
            "ADD COLUMN package_product_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID produs pachet' AFTER variation_id",
            "ADD COLUMN package_order_item_id bigint(20) unsigned DEFAULT 0 COMMENT 'ID order item pachet' AFTER package_product_id",
            
            // Tracking ședințe DIRECT (eliminăm dependența de alte tabele)
            "ADD COLUMN sessions_allocated int(11) DEFAULT 0 COMMENT 'Ședințe alocate din config' AFTER total_sessions",
            "ADD COLUMN is_unlimited tinyint(1) DEFAULT 0 COMMENT 'VIP unlimited flag' AFTER remaining_sessions"
        ];
        
        foreach ($columns_to_add as $column_sql) {
            $full_sql = "ALTER TABLE {$table_name} {$column_sql}";
            
            // Verifică dacă coloana nu există deja
            $column_check = explode(' ', $column_sql)[2]; // Extrage numele coloanei
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME, str_replace($wpdb->prefix, '', $table_name), $column_check
            ));
            
            if (!$exists) {
                $wpdb->query($full_sql);
            }
        }
        
        // Adaugă indexuri pentru coloanele noi
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_package_product ON {$table_name} (package_product_id)",
            "CREATE INDEX IF NOT EXISTS idx_package_order_item ON {$table_name} (package_order_item_id)",
            "CREATE INDEX IF NOT EXISTS idx_unlimited ON {$table_name} (is_unlimited)"
        ];
        
        foreach ($indexes as $sql) {
            $wpdb->query($sql);
        }
        
        // Populează datele existente cu valorile noi
        $this->populate_package_data_v1_2();
    }
    
    /**
     * Populează datele package_* și sessions_* pentru membership-uri existente
     * 
     * Detectează automat pachetul din order și populează sessions din config
     * 
     * @since 1.2.0
     */
    private function populate_package_data_v1_2(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Obține membership-uri care nu au package_product_id setat
        $memberships = $wpdb->get_results($wpdb->prepare("
            SELECT id, user_id, order_id, order_item_id, product_id, variation_id, 
                   total_sessions, used_sessions, remaining_sessions
            FROM {$table_name} 
            WHERE (package_product_id = 0 OR package_product_id IS NULL)
            OR (sessions_allocated = 0 OR sessions_allocated IS NULL)
            LIMIT 100
        "));
        
        foreach ($memberships as $membership) {
            $updates = [];
            
            // Detectează și populează package_* din comandă
            if (!$membership->package_product_id || $membership->package_product_id == 0) {
                $package_data = $this->detect_package_from_order($membership->order_id);
                if ($package_data) {
                    $updates['package_product_id'] = $package_data['product_id'];
                    $updates['package_order_item_id'] = $package_data['item_id'];
                }
            }
            
            // Populează sessions_* din config sau fallback
            if (!$membership->sessions_allocated || $membership->sessions_allocated == 0) {
                $session_data = $this->calculate_sessions_for_migration($membership->variation_id, $membership);
                $updates = array_merge($updates, $session_data);
            }
            
            // Actualizează dacă avem date noi
            if (!empty($updates)) {
                $wpdb->update($table_name, $updates, ['id' => $membership->id]);
            }
        }
    }
    
    /**
     * Detectează pachetul din order (backwards compatibility _mv_pack_ + _oc_pool_)
     * 
     * @param int $order_id ID comandă
     * @return array|null Array cu product_id și item_id sau null
     * 
     * @since 1.2.0
     */
    private function detect_package_from_order(int $order_id): ?array {
        $order = wc_get_order($order_id);
        if (!$order) return null;
        
        foreach ($order->get_items() as $item_id => $item) {
            // Pachetul = produs fără variație + preț > 0
            if ($item->get_variation_id() == 0 && $item->get_total() > 0) {
                $product_id = $item->get_product_id();
                
                // Verifică prefixe (backwards compatibility!)
                $is_oc_pool = get_post_meta($product_id, '_oc_pool_enabled', true);
                $is_mv_pack = get_post_meta($product_id, '_mv_pack_enabled', true);
                
                if ($is_oc_pool || $is_mv_pack) {
                    return [
                        'product_id' => $product_id,
                        'item_id' => $item_id
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Calculează sessions din config pentru migrare
     * 
     * @param int $variation_id ID variație curs
     * @param object $membership Obiect membership
     * @return array Date sessions_allocated, is_unlimited
     * 
     * @since 1.2.0
     */
    private function calculate_sessions_for_migration(int $variation_id, object $membership): array {
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            return [
                'sessions_allocated' => 8,
                'is_unlimited' => 0
            ];
        }
        
        $db = $validator->get_db();
        $config = $db->get_course_hours_config($variation_id);
        
        if ($config) {
            $sessions_allocated = $config['sessions_per_month'];
            $is_unlimited = $config['is_unlimited'];
        } else {
            // Fallback: 8 ședințe sau folosește total_sessions existent
            $sessions_allocated = $membership->total_sessions > 0 ? $membership->total_sessions : 8;
            $is_unlimited = 0;
        }
        
        return [
            'sessions_allocated' => $sessions_allocated,
            'is_unlimited' => $is_unlimited
        ];
    }
    
    /**
     * Cleanup migrări vechi
     */
    public function cleanup_old_migrations(): void {
        global $wpdb;
        
        // Șterge tabelele de backup mai vechi de 30 zile
        $tables = $wpdb->get_results($wpdb->prepare('SHOW TABLES LIKE %s', '%\\_backup\\_%'), ARRAY_N);
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Extrage timestamp din numele tabelului
            if (preg_match('/_backup_(\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2})$/', $table_name, $matches)) {
                $backup_date = DateTime::createFromFormat('Y_m_d_H_i_s', $matches[1]);
                if ($backup_date && $backup_date->diff(new DateTime())->days > 30) {
                    $safe_table_name = esc_sql($table_name);
                    $wpdb->query("DROP TABLE IF EXISTS `{$safe_table_name}`");
                }
            }
        }
    }
    
    /**
     * 🔧 FORȚEAZĂ RECREAREA TABELELOR cu schema nouă și SINCRONIZEAZĂ comenzile WooCommerce
     * 
     * Folosește această funcție când:
     * - Ai instalat pluginul pe un domeniu nou
     * - Schema DB e incompletă sau corruptă
     * - Comenzile WooCommerce nu se sincronizează automat
     * 
     * @return array Status cu numărul de comenzi procesate
     * @since 1.2.1
     */
    public function force_recreate_and_sync(): array {
        // 🚫 FLAG GLOBAL: Indică că suntem în SYNC MODE - ZERO emailuri trimise!
        if (!defined('OC_SYNC_MODE')) {
            define('OC_SYNC_MODE', true);
        }
        
        global $wpdb;
        
        $results = [
            'success' => false,
            'tables_recreated' => 0,
            'orders_found' => 0,
            'memberships_created' => 0,
            'errors' => []
        ];
        
        try {
            // PASUL 1: Backup tabelele existente (dacă există)
            $this->backup_existing_tables();
            
            // PASUL 2: Șterge tabelele existente
            $tables_to_drop = [
                $wpdb->prefix . 'membership_validations',
                $wpdb->prefix . 'membership_validation_log',
                $wpdb->prefix . 'membership_course_mapping',
                $wpdb->prefix . 'course_hours_config'
            ];
            
            foreach ($tables_to_drop as $table) {
                $safe_table = esc_sql($table);
                $wpdb->query("DROP TABLE IF EXISTS `{$safe_table}`");
                $results['tables_recreated']++;
            }
            
            // PASUL 3: Recreează tabelele cu schema nouă
            $validator = OC_Membership_Validator::get_instance();
            if ($validator && $validator->get_db()) {
                $db = $validator->get_db();
                $db->create_tables();
            } else {
                $results['errors'][] = 'Nu s-a putut obține instanța DB';
                return $results;
            }
            
            // PASUL 4: Reset versiunea DB pentru a forța migrările
            delete_option('oc_membership_db_version');
            
            // PASUL 5: Sincronizează toate comenzile WooCommerce completate din ultimul an
            $orders = $wpdb->get_results("
                SELECT DISTINCT p.ID as order_id, p.post_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND pm.meta_key = '_customer_user'
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                ORDER BY p.post_date DESC
            ");
            
            $results['orders_found'] = count($orders);
            
            // 🚫 DEZACTIVEAZĂ TOATE EMAILURILE WOOCOMMERCE în timpul migrării
            $email_filters_disabled = [
                'woocommerce_email_enabled_customer_completed_order',
                'woocommerce_email_enabled_customer_processing_order',
                'woocommerce_email_enabled_customer_invoice',
                'woocommerce_email_enabled_new_order',
                'woocommerce_email_enabled_customer_note',
            ];
            
            foreach ($email_filters_disabled as $filter) {
                add_filter($filter, '__return_false', OC_FILTER_PRIORITY_MAX);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('🚫 Migration: Emailuri WooCommerce DEZACTIVATE pentru migrare');
            }
            
            foreach ($orders as $order_data) {
                $order = wc_get_order($order_data->order_id);
                if (!$order) {
                    continue;
                }
                
                // Procesează fiecare item din comandă
                foreach ($order->get_items() as $item_id => $item) {
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();
                    
                    // Verifică dacă produsul/variația are Pool enabled sau MV Pack enabled
                    $search_id = $variation_id > 0 ? $variation_id : $product_id;
                    $pool_enabled = get_post_meta($search_id, '_oc_pool_enabled', true);
                    $mv_pack_enabled = get_post_meta($search_id, '_mv_pack_enabled', true);
                    
                    if ($pool_enabled === 'yes' || $mv_pack_enabled === 'yes') {
                        // Creează membership prin hook WooCommerce
                        try {
                            // Folosește hook-ul normal pentru a crea membership
                            do_action('woocommerce_order_status_completed', $order_data->order_id);
                            $results['memberships_created']++;
                            
                            // Break după primul produs găsit pentru a evita duplicate
                            break;
                        } catch (Exception $e) {
                            $results['errors'][] = sprintf(
                                'Order #%d: %s',
                                $order_data->order_id,
                                $e->getMessage()
                            );
                        }
                    }
                }
            }
            
            // ✅ REACTIVEAZĂ emailurile după migrare
            foreach ($email_filters_disabled as $filter) {
                remove_filter($filter, '__return_false', OC_FILTER_PRIORITY_MAX);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('✅ Migration: Emailuri WooCommerce REACTIVATE');
            }
            
            $results['success'] = true;
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            
            // În caz de eroare, încearcă restore backup
            $this->restore_backup_if_needed();
        }
        
        return $results;
    }
    
    /**
     * Verifică dacă tabelele au schema completă (toate coloanele necesare)
     * 
     * @return array Status cu coloanele lipsă
     */
    public function verify_schema_integrity(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $required_columns = [
            'sessions_allocated',
            'is_unlimited',
            'package_product_id',
            'package_order_item_id',
            'display_name',
            'email',
            'phone',
            'product_name',
            'payment_method',
            'payment_status',
            'cached_data_synced_at'
        ];
        
        $safe_table_name = esc_sql($table_name);
        $existing_columns = $wpdb->get_results("DESCRIBE `{$safe_table_name}`");
        $existing_column_names = array_column($existing_columns, 'Field');
        
        $missing_columns = array_diff($required_columns, $existing_column_names);
        
        return [
            'complete' => empty($missing_columns),
            'missing_columns' => array_values($missing_columns),
            'total_columns' => count($existing_column_names),
            'required_columns' => count($required_columns)
        ];
    }
}
