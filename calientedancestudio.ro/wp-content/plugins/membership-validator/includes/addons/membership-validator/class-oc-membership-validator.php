<?php
/**
 * Membership Validator Core - Clasa Principală
 * 
 * @package MembershipValidator
 * @subpackage Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load constants
require_once plugin_dir_path(__FILE__) . 'constants.php';

/**
 * Class OC_Membership_Validator
 * 
 * Clasa principală pentru ADD-ON Membership Validator
 * Implementare conform pattern-ului sistemului existent
 */
class OC_Membership_Validator {
    
    /**
     * Instance of this class
     */
    private static ?OC_Membership_Validator $instance = null;
    
    /**
     * Database handler pentru membership validations
     */
    private ?OC_Membership_DB $db = null;
    
    /**
     * QR Code system handler
     */
    private ?OC_Membership_QR $qr_system = null;
    
    /**
     * Validation logic handler
     */
    private ?OC_Membership_Validation $validator = null;
    
    /**
     * Plugin version
     */
    private string $version = '2.0.0';
    
    /**
     * Singleton instance
     */
    public static function get_instance(): OC_Membership_Validator {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - urmează pattern-ul Pool Product Manager
     */
    private function __construct() {
        $this->init_addon();
    }
    
    /**
     * Inițializează ADD-ON-ul conform pattern-ului existent
     */
    private function init_addon(): void {
        // Verifică dependințele
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Încarcă componentele
        $this->load_components();
        
        // Hook-uri pentru lifecycle
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Setup hooks principal
        $this->setup_hooks();
        
        // Hook pentru activare - creează DB
        add_action('oc_addon_activated', [$this, 'on_addon_activated'], 10, 2);
        add_action('oc_addon_deactivated', [$this, 'on_addon_deactivated'], 10, 1);
    }
    
    /**
     * Verifică dependințele conform pattern-ului existent
     */
    private function check_dependencies(): bool {
        // Verifică WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return false;
        }
        
        // Verifică CORE
        if (!class_exists('OC_Orar_Cursuri')) {
            add_action('admin_notices', [$this, 'core_missing_notice']);
            return false;
        }
        
        // Verifică versiunea PHP
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Încarcă componentele ADD-ON-ului
     */
    private function load_components(): void {
        $addon_path = plugin_dir_path(__FILE__);
        
        require_once $addon_path . 'membership-validator-order-helpers.php';

        // Încarcă toate clasele
        require_once $addon_path . 'class-oc-membership-db.php';
        require_once $addon_path . 'class-oc-membership-qr.php';
        require_once $addon_path . 'class-oc-membership-validation.php';
        require_once $addon_path . 'class-oc-membership-sync.php';
        require_once $addon_path . 'class-oc-membership-rest-api.php'; // 🎯 REST API pentru app React
        require_once $addon_path . 'membership-validator-functions.php';
        require_once $addon_path . 'membership-validator-ajax.php';
        
        // Inițializează componentele cu dependințe corecte - FAZA 2
        $this->db = new OC_Membership_DB();
        
        // Verifică că DB s-a inițializat înainte de a crea dependințele
        if ($this->db) {
            $this->qr_system = new OC_Membership_QR($this->db);
            $this->validator = new OC_Membership_Validation($this->db, $this->qr_system);
            
            // 🎯 Inițializează REST API pentru app React
            $this->init_rest_api();
            
            // Inițializează sistemul de sincronizare automată
            new OC_Membership_Sync();
        }
    }
    
    /**
     * 🎯 Inițializează REST API pentru app React
     * 
     * Hook în rest_api_init pentru înregistrare endpoint-uri
     * 
     * @since 1.0.0
     */
    private function init_rest_api(): void {
        add_action('rest_api_init', function() {
            $rest_api = new OC_Membership_REST_API($this->qr_system, $this->db);
            $rest_api->register_routes();
        });
    }
    
    /**
     * Setup hooks principal pentru ADD-ON
     */
    private function setup_hooks(): void {
        // Creează membership-ul automat pe evenimentele standard WooCommerce.
        // Hook-uri pentru integrare cu WooCommerce - NON-INTRUZIV
        add_action('woocommerce_order_status_completed', [$this, 'process_new_membership'], 10, 1);
        // Creează și rândurile de așteptare imediat după checkout, ca să fie vizibile în My Account.
        add_action('woocommerce_checkout_order_processed', [$this, 'process_new_membership'], 10, 1);
        // Procesează și la finalizarea plății pentru gateway-urile care marchează plata separat.
        add_action('woocommerce_payment_complete', [$this, 'process_new_membership'], 10, 1);
        add_filter('woocommerce_payment_complete_order_status', [$this, 'keep_membership_orders_waiting_after_payment'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'normalize_membership_processing_status'], 10, 4);
        add_filter('wc_order_statuses', [$this, 'rename_membership_waiting_status']);
        
        // Hook-uri pentru anulare/refundare comenzi
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_membership'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'refund_membership'], 10, 1);
        
        // Hook-uri pentru integrare cu sisteme existente - NON-INTRUZIV
        add_action('oc_pool_package_created', [$this, 'link_membership_to_package'], 10, 2);
        add_filter('oc_schedule_data', [$this, 'enhance_schedule_with_membership_data'], 10, 1);
        
        // Hook-uri WordPress generale (deja adăugate în init_addon())
        
        // AJAX handlers - FAZA 2
        // Only authenticated users can validate memberships or generate QR codes
        add_action('wp_ajax_oc_validate_membership', [$this, 'ajax_validate_membership']);
        add_action('wp_ajax_oc_generate_qr_code', [$this, 'ajax_generate_qr_code']);
        
        // FAZA 2: WordPress Cron pentru cleanup expirări
        add_action('oc_membership_cleanup_expired', [$this, 'cron_cleanup_expired']);
        add_action('init', [$this, 'schedule_cleanup_cron']);
        
        // 🎯 v1.3.0: Cron pentru renewal system
        // ❌ DEZACTIVAT: Activare automată OPRITĂ - abonamentele se activează DOAR manual prin buton
        // add_action('oc_membership_activate_pending', [$this, 'activate_pending_memberships']);
        
        // 🎯 v2.0: Hook-uri pentru generare automată QR simplu
        add_action('user_register', [$this, 'generate_qr_for_new_user'], 10, 1);
        add_action('oc_membership_created', [$this, 'generate_qr_for_user'], 10, 2);
        add_action('oc_membership_activated', [$this, 'generate_qr_for_user'], 10, 2);
        add_action('oc_membership_expire_old', [$this, 'expire_old_memberships']);
        
        // 🎯 QR Code: Generare automată pentru membership-uri existente
        add_action('oc_generate_missing_qr_codes', [$this, 'generate_missing_qr_codes']);
        
    }

    /**
     * Păstrează comenzile de membership în așteptare după plată.
     * Finalizarea se face manual, nu automat prin statusul de plată.
     */
    public function keep_membership_orders_waiting_after_payment(string $status, int $_order_id, WC_Order $order): string {
        if (!$this->order_contains_membership_items($order)) {
            return $status;
        }

        if (in_array($status, ['processing', 'completed'], true)) {
            return 'on-hold';
        }

        return $status;
    }

    /**
     * Normalizează comenzile care ajung în processing înapoi la starea de așteptare.
     */
    public function normalize_membership_processing_status(int $order_id, string $_from, string $to, WC_Order $order): void {
        static $normalizing_order_ids = [];

        if ($to !== 'processing' || isset($normalizing_order_ids[$order_id])) {
            return;
        }

        if (!$this->order_contains_membership_items($order)) {
            return;
        }

        $normalizing_order_ids[$order_id] = true;

        try {
            $order->update_status(
                'on-hold',
                'Comandă menținută automat în așteptare până la finalizarea manuală a abonamentului.',
                true
            );
        } finally {
            unset($normalizing_order_ids[$order_id]);
        }
    }

    /**
     * Afișează statusul on-hold ca În așteptare în WooCommerce.
     */
    public function rename_membership_waiting_status(array $statuses): array {
        if (isset($statuses['wc-on-hold'])) {
            $statuses['wc-on-hold'] = 'În așteptare';
        }

        return $statuses;
    }

    /**
     * Detectează dacă o comandă conține produse gestionate de Membership Validator.
     */
    private function order_contains_membership_items(WC_Order $order): bool {
        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            if ($product_id <= 0) {
                continue;
            }

            if (
                get_post_meta($product_id, '_oc_pool_enabled', true)
                || get_post_meta($product_id, '_mv_pack_enabled', true)
                || wc_get_order_item_meta($item->get_id(), '_oc_pool_child', true)
                || wc_get_order_item_meta($item->get_id(), '_mv_pack_child', true)
            ) {
                return true;
            }
        }

        return false;
    }
    
    
    /**
     * Callback când ADD-ON-ul este activat
     */
    public function on_addon_activated(string $addon_id, array $addon): void {
        if ($addon_id !== 'membership_core_engine') {
            return;
        }
        
        // Verifică și creează tabelele DOAR dacă este necesar
        if ($this->db) {
            $this->db->maybe_create_tables();
        }
        
        // Creează directorul QR
        if ($this->qr_system) {
            $upload_dir = wp_upload_dir();
            $qr_dir = $upload_dir['basedir'] . '/membership-qr-codes/';
            if (!is_dir($qr_dir)) {
                wp_mkdir_p($qr_dir);
            }
        }
        
        // 🎯 Programează generarea QR codes pentru membership-uri existente IMEDIAT
        // Rulează ÎNTOTDEAUNA la activare (transient se șterge la dezactivare)
        if (!get_transient('oc_qr_generation_scheduled')) {
            // Verifică rapid dacă există membri fără QR
            global $wpdb;
            $validator_db = $this->get_db();
            if ($validator_db) {
                $table_name = $validator_db->get_table_name('membership_validations');
                $users_with_memberships = $wpdb->get_col("
                    SELECT DISTINCT user_id 
                    FROM {$table_name}
                    WHERE user_id > 0
                    AND variation_id > 0
                    LIMIT 10
                ");
                
                if (!empty($users_with_memberships)) {
                    // Programează generarea cu delay minim + forțează execuție
                    wp_schedule_single_event(time() + 1, 'oc_generate_missing_qr_codes');
                    spawn_cron(); // 🔥 FORȚEAZĂ execuție instant
                    
                    // Setează transient DOAR după programare (se șterge după finalizare în generate_missing_qr_codes)
                    set_transient('oc_qr_generation_scheduled', true, MINUTE_IN_SECONDS * 5);

                    oc_log_debug(sprintf(
                        '🎯 QR Generation: Programat pentru %d utilizatori, spawn_cron() forțat',
                        count($users_with_memberships)
                    ));
                } else {
                    oc_log_debug('⏭️ QR Generation: Skip - nu există membri cu abonamente');
                }
            }
        } else {
            oc_log_debug('⏭️ QR Generation: Skip - deja programat (transient activ)');
        }
    }
    
    /**
     * Callback când ADD-ON-ul este dezactivat
     */
    public function on_addon_deactivated(string $addon_id): void {
        if ($addon_id !== 'membership_core_engine') {
            return;
        }
        
        // Cleanup cron jobs la dezactivare - FAZA 2
        $this->cleanup_cron_jobs();
        
        // Șterge transient QR pentru a permite regenerare la reactivare
        delete_transient('oc_qr_generation_scheduled');

        oc_log_debug('[Plugin Deactivation] QR generation transient cleared');
    }

    /**
     * Cleanup cron jobs la dezactivare ADD-ON
     */
    private function cleanup_cron_jobs(): void {
        $timestamp = wp_next_scheduled('oc_membership_cleanup_expired');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'oc_membership_cleanup_expired');
        }
        
        // 🎯 v1.3.0: Cleanup renewal system cron jobs
        $timestamp_activate = wp_next_scheduled('oc_membership_activate_pending');
        if ($timestamp_activate) {
            wp_unschedule_event($timestamp_activate, 'oc_membership_activate_pending');
        }
        
        $timestamp_expire = wp_next_scheduled('oc_membership_expire_old');
        if ($timestamp_expire) {
            wp_unschedule_event($timestamp_expire, 'oc_membership_expire_old');
        }
    }
    
    /**
     * 🎯 Generează QR codes pentru toate membership-urile active fără QR
     * 
     * Rulează automat la activarea addon-ului prin wp_schedule_single_event
     * Procesează în batch-uri pentru a evita timeout-uri pe instalații mari
     * 
     * @since 1.0.0
     */
    /**
     * 🎯 v2.0 - Generează QR SIMPLU pentru toți utilizatorii cu membership
     * 
     * Rulează la activarea pluginului pentru a crea QR-uri simple (user_id only)
     * pentru TOȚI utilizatorii care au avut membership-uri (activ sau expirat)
     */
    public function generate_missing_qr_codes(): void {
        if (!$this->db || !$this->qr_system) {
            oc_log_debug('[QR Generation v2.0] DB or QR system not initialized');
            return;
        }
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // 🎯 Găsește TOȚI utilizatorii unici care au avut membership-uri
        $user_ids = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$table_name}
            WHERE user_id > 0
            AND variation_id > 0
        ");
        
        if (empty($user_ids)) {
            oc_log_debug('[QR Generation v2.0] No users with memberships found');
            delete_transient('oc_qr_generation_scheduled');
            return;
        }
        
        $generated_count = 0;
        $skipped_count = 0;
        $failed_count = 0;
        
        oc_log_debug(sprintf('[QR Generation v2.0] Starting generation for %d users', count($user_ids)));
        
        foreach ($user_ids as $user_id) {
            try {
                // Verifică dacă user-ul există
                $user = get_userdata($user_id);
                if (!$user) {
                    $failed_count++;
                    continue;
                }
                
                // Verifică dacă există deja QR simplu pentru acest user ȘI dacă fișierul există fizic
                $existing_qr = get_user_meta($user_id, 'simple_qr_filename', true);
                if ($existing_qr) {
                    // Verifică dacă fișierul există fizic pe disc
                    $upload_dir = wp_upload_dir();
                    $qr_path = $upload_dir['basedir'] . '/membership-qr-codes/' . $existing_qr;
                    
                    if (file_exists($qr_path)) {
                    $skipped_count++;
                    continue;
                    } else {
                        // Fișierul lipsește fizic → șterge meta și regenerează
                        delete_user_meta($user_id, 'simple_qr_filename');
                        oc_log_debug(sprintf(
                            '[QR Generation v2.0] 🔄 File missing for user #%d, regenerating...',
                            $user_id
                        ));
                    }
                }
                
                // 🎯 Generează QR SIMPLU pentru user
                $qr_result = $this->qr_system->generate_simple_user_qr($user_id);
                
                if ($qr_result) {
                    $generated_count++;

                    oc_log_debug(sprintf(
                        '[QR Generation v2.0] ✅ Generated simple QR for user #%d (%s) - File: %s',
                        $user_id,
                        oc_membership_resolve_user_display_name($user),
                        $qr_result['filename']
                    ));
                } else {
                    $failed_count++;
                    oc_log_debug(sprintf(
                        '[QR Generation v2.0] ❌ Failed to generate QR for user #%d',
                        $user_id
                    ));
                }
                
                // Sleep scurt pentru a nu supraîncărca serverul
                usleep(50000); // 0.05 secunde
                
            } catch (Exception $e) {
                $failed_count++;
                oc_log_debug(sprintf(
                    '[QR Generation v2.0] ❌ Exception for user #%d: %s',
                    $user_id,
                    $e->getMessage()
                ));
            }
        }

        // Log final și finalizare
        oc_log_debug(sprintf(
            '[QR Generation v2.0] ✅ COMPLETE: %d generated, %d skipped (existing), %d failed out of %d total users',
            $generated_count,
            $skipped_count,
            $failed_count,
            count($user_ids)
        ));
        
        // Finalizare - șterge transient
        delete_transient('oc_qr_generation_scheduled');
    }
    
    /**
     * 🎯 v2.0 - Generează QR automat când se creează user nou
     * 
     * Hook: user_register
     * 
     * @param int $user_id ID utilizator nou creat
     */
    public function generate_qr_for_new_user(int $user_id): void {
        if (!$this->qr_system) {
            return;
        }
        
        // Generează QR simplu pentru user nou
        $qr_result = $this->qr_system->generate_simple_user_qr($user_id);
        
        if ($qr_result) {
            $user = get_userdata($user_id);
            oc_log_debug(sprintf(
                '[QR v2.0] ✅ Auto-generated QR for new user #%d (%s) on registration',
                $user_id,
                $user ? oc_membership_resolve_user_display_name($user) : 'Unknown'
            ));
        }
    }
    
    /**
     * 🎯 v2.0 - Generează QR automat când se creează/activează membership
     * 
     * Hook: oc_membership_created, oc_membership_activated
     * 
     * @param int $user_id ID utilizator
     * @param array $membership_data Date membership (opțional)
     */
    public function generate_qr_for_user(int $user_id, array $membership_data = []): void {
        if (!$this->qr_system) {
            return;
        }
        
        // Verifică dacă există deja QR pentru acest user
        $existing_qr = get_user_meta($user_id, 'simple_qr_filename', true);
        if ($existing_qr) {
            return; // Skip dacă există deja
        }
        
        // Generează QR simplu pentru user
        $qr_result = $this->qr_system->generate_simple_user_qr($user_id);
        
        if ($qr_result) {
            $user = get_userdata($user_id);
            oc_log_debug(sprintf(
                '[QR v2.0] ✅ Auto-generated QR for user #%d (%s) on membership action',
                $user_id,
                $user ? oc_membership_resolve_user_display_name($user) : 'Unknown'
            ));
        }
    }
    
    /**
     * Adaugă meniul admin pentru ADD-ON - TAB SYSTEM
     */
    public function add_admin_menu(): void {
        // Pagina principală Membership Validator cu taburi interne
        add_submenu_page(
            'membership-validator-dashboard',
            __('Membership Validator', OC_TEXT_DOMAIN),
            __('🎫 Membership Validator', OC_TEXT_DOMAIN),
            'manage_options',
            'membership-validator',
            [$this, 'admin_page_callback']
        );
        
        // ELIMINATED: Separate submenu for Validation Settings
        // Settings are now a tab within the main Membership Validator page
        // Callback method kept for compatibility
        
        // ELIMINATED: Separator for Membership Manager (no longer needed with tab system)
    }
    
    
    /**
     * Enqueue admin assets - TAB SYSTEM SUPPORT
     */
    public function enqueue_admin_assets($hook): void {
        // Doar pe pagina principală cu taburi
        if ($hook !== 'membership-validator_page_membership-validator') {
            return;
        }
        
        // Get current tab for conditional loading if needed
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        
        // CSS admin (folosește stilul dashboard existent)
        wp_enqueue_style(
            'oc-membership-validator-admin',
            OC_PLUGIN_URL . 'assets/dashboard.css',
            [],
            $this->version
        );
        
        // JavaScript admin
        wp_enqueue_script(
            'oc-membership-validator-admin',
            OC_PLUGIN_URL . 'assets/dashboard.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Stiluri specifice pentru pagina de debug
        if ($hook === 'membership-validator_page_membership-validator-debug') {
            add_action('admin_head', [$this, 'add_debug_admin_styles']);
        }
        
        // Localization pentru AJAX
        wp_localize_script('oc-membership-validator-admin', 'ocMembershipData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_membership_validator_nonce'),
            'strings' => [
                'loading' => __('Se încarcă...', OC_TEXT_DOMAIN),
                'error' => __('A apărut o eroare.', OC_TEXT_DOMAIN),
                'success' => __('Operația a fost efectuată cu succes.', OC_TEXT_DOMAIN),
                'confirm' => __('Sunteți sigur?', OC_TEXT_DOMAIN)
            ]
        ]);
    }
    
    /**
     * Notice pentru WooCommerce lipsă
     */
    public function woocommerce_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Membership Validator:</strong> ';
        echo 'Requires WooCommerce to function properly.';
        echo '</p></div>';
    }
    
    /**
     * Notice pentru CORE lipsă
     */
    public function core_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Membership Validator:</strong> ';
        echo 'Requires Membership Validator Core plugin.';
        echo '</p></div>';
    }
    
    /**
     * Notice pentru versiunea PHP
     */
    public function php_version_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Membership Validator:</strong> ';
        echo 'Requires PHP 8.2 or higher. Current version: ' . PHP_VERSION;
        echo '</p></div>';
    }
    
    
    /**
     * 🎯 SISTEMUL POOL: Verifică dacă item-ul este copil Pool (variație din abonament)
     * Suportă ambele sisteme: nou (_oc_pool_child) și vechi (_mv_pack_child)
     */
    private function is_pool_child_item($item): bool {
        // 🎯 MODIFICAT: Acceptă cursuri chiar dacă NU sunt în orar (pentru vânzare în avans)
        // Verifică DOAR dacă are meta field _oc_pool_child sau _mv_pack_child
        
        // Verifică meta pentru sistem NOU
        $is_oc_pool_child = $item->get_meta('_oc_pool_child') === 'yes';
        
        // Verifică meta pentru sistem VECHI 
        $is_mv_pack_child = $item->get_meta('_mv_pack_child') === 'yes';
        
        if ($is_oc_pool_child || $is_mv_pack_child) {
            return true; // Acceptă chiar dacă nu e în orar
        }
        
        // 🔥 FALLBACK: Detectează cursuri din pachet bazat pe: total=0 + variation_id > 0
        $variation_id = $item->get_variation_id();
        $total = $item->get_total();
        
        if ($variation_id > 0 && $total == 0) {
            // Este o variație cu preț 0 (probabil curs din pachet)
            return true; // Acceptă chiar dacă nu e în orar
        }
        
        return false;
    }
    
    /**
     * 🗄️ Verifică dacă variation_id există în tabelul orar_cursuri
     */
    private function variation_exists_in_schedule(int $product_id, int $variation_id): bool {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}orar_cursuri 
             WHERE product_id = %d AND variation_id = %d",
            $product_id,
            $variation_id
        ));
        
        return $exists > 0;
    }

    /**
     * LEGACY: Verifică dacă un produs este tip membership - versiune îmbunătățită
     */
    private function is_membership_product(int $product_id): bool {
        $product = wc_get_product($product_id);
        if (!$product) return false;

        // 🎯 DETECTARE FLEXIBILĂ MEMBERSHIP:
        
        // 1. Verifică meta explicit
        $is_membership = get_post_meta($product_id, '_oc_is_membership', true);
        if ($is_membership === 'yes') return true;
        
        // 2. Verifică categoria 'membership'
        if (has_term('membership', 'product_cat', $product_id)) return true;
        
        // 3. Verifică numele produsului (contains membership/abonament)
        $product_name = strtolower($product->get_name());
        if (strpos($product_name, 'membership') !== false || 
            strpos($product_name, 'abonament') !== false ||
            strpos($product_name, 'sedinte') !== false ||
            strpos($product_name, 'cursuri') !== false) {
            return true;
        }
        
        return false;
    }

    
    /**
     * Get plugin version
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Get database handler
     */
    public function get_db(): ?OC_Membership_DB {
        return $this->db;
    }
    
    /**
     * Get QR system handler
     */
    public function get_qr_system(): ?OC_Membership_QR {
        return $this->qr_system;
    }
    
    /**
     * Get validator handler
     */
    public function get_validator(): ?OC_Membership_Validation {
        return $this->validator;
    }
    
    /**
     * Callback pentru pagina admin principală - TAB SYSTEM ENABLED
     */
    public function admin_page_callback(): void {
        // Verifică permisiunile
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Get current tab from URL parameter
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        
        // Validate tab
        $valid_tabs = ['dashboard', 'settings'];
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'dashboard';
        }
        
        // Include template-ul cu taburi
        $template_path = OC_PLUGIN_DIR . 'templates/membership-validator/admin-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_default_admin_page();
        }
    }
    
    /**
     * Callback pentru pagina de settings
     */
    public function settings_page_callback(): void {
        // Verifică permisiunile
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Procesează salvarea setărilor
        if (isset($_POST['oc_save_membership_settings']) && wp_verify_nonce($_POST['oc_membership_nonce'], 'oc_save_membership_settings')) {
            $this->save_settings();
        }
        
        // Include template-ul
        $template_path = plugin_dir_path(__FILE__) . '../../templates/membership-validator/settings-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_default_settings_page();
        }
    }
    
    /**
     * Callback pentru pagina de debug
     */
    public function debug_page_callback(): void {
        // Verifică permisiunile
        if (!current_user_can('manage_options')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Calea corectă către fișierul de debug din ADD-ON
        $debug_path = plugin_dir_path(__FILE__) . 'membership-validator-debug.php';
        
        if (file_exists($debug_path)) {
            // Setăm flag că suntem în admin
            if (!defined('OC_MEMBERSHIP_ADMIN_DEBUG')) {
                define('OC_MEMBERSHIP_ADMIN_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
            }
            
            echo '<div class="wrap">';
            echo '<h1>' . __('Membership Validator - Debug Tools', OC_TEXT_DOMAIN) . '</h1>';
            echo '<p class="description">' . __('Instrumentele de debug pentru testarea funcționalităților Membership Validator.', OC_TEXT_DOMAIN) . '</p>';
            echo '<div style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 3px;">';
            
            // Captură output-ul din test-browser.php
            ob_start();
            include $debug_path;
            $debug_content = ob_get_clean();
            
            // Afișează conținutul
            echo $debug_content;
            
            echo '</div>';
            echo '</div>';
        } else {
            $this->render_default_debug_page();
        }
    }
    
    /**
     * Salvează setările ADD-ON-ului
     */
    private function save_settings(): void {
        $settings = [
            'oc_membership_qr_enabled' => isset($_POST['oc_membership_qr_enabled']) ? '1' : '0',
            'oc_membership_auto_expire' => isset($_POST['oc_membership_auto_expire']) ? '1' : '0',
            'oc_membership_session_limit' => absint($_POST['oc_membership_session_limit'] ?? 10),
            'oc_membership_validation_restriction' => sanitize_text_field($_POST['oc_membership_validation_restriction'] ?? 'none'),
            'oc_membership_cleanup_days' => absint($_POST['oc_membership_cleanup_days'] ?? 30),
            'oc_membership_debug_enabled' => isset($_POST['oc_membership_debug_enabled']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Setările Membership Validator au fost salvate cu succes!', OC_TEXT_DOMAIN) . 
                 '</p></div>';
        });
    }
    
    /**
     * Render pagină admin default dacă template-ul nu există
     */
    private function render_default_admin_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('Membership Validator', OC_TEXT_DOMAIN) . '</h1>';
        echo '<div class="notice notice-info"><p>';
        echo __('ADD-ON-ul Membership Validator este activ și funcțional!', OC_TEXT_DOMAIN);
        echo '</p></div>';
        
        // Statistici rapide
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        $total_memberships = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $active_memberships = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE validation_status = 'active'");
        
        echo '<div class="card">';
        echo '<h2>' . __('Statistici', OC_TEXT_DOMAIN) . '</h2>';
        echo '<p><strong>' . __('Total Abonamente:', OC_TEXT_DOMAIN) . '</strong> ' . $total_memberships . '</p>';
        echo '<p><strong>' . __('Abonamente Active:', OC_TEXT_DOMAIN) . '</strong> ' . $active_memberships . '</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render pagină settings default dacă template-ul nu există
     */
    private function render_default_settings_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('Membership Validator - Setări', OC_TEXT_DOMAIN) . '</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('oc_save_membership_settings', 'oc_membership_nonce');
        
        $qr_enabled = get_option('oc_membership_qr_enabled', '1');
        $auto_expire = get_option('oc_membership_auto_expire', '1');
        $session_limit = get_option('oc_membership_session_limit', '10');
        $validation_restriction = get_option('oc_membership_validation_restriction', 'none');
        $cleanup_days = get_option('oc_membership_cleanup_days', '30');
        $debug_enabled = get_option('oc_membership_debug_enabled', '0');
        
        echo '<table class="form-table">';
        
        echo '<tr><th scope="row">' . __('QR Codes Activate', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><input type="checkbox" name="oc_membership_qr_enabled" value="1" ' . checked($qr_enabled, '1', false) . ' />';
        echo '<p class="description">' . __('Activează generarea codurilor QR pentru abonamente.', OC_TEXT_DOMAIN) . '</p></td></tr>';
        
        echo '<tr><th scope="row">' . __('Auto Expirare', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><input type="checkbox" name="oc_membership_auto_expire" value="1" ' . checked($auto_expire, '1', false) . ' />';
        echo '<p class="description">' . __('Expirează automat abonamentele după data limită.', OC_TEXT_DOMAIN) . '</p></td></tr>';
        
        echo '<tr><th scope="row">' . __('Limită Ședințe Default', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><input type="number" name="oc_membership_session_limit" value="' . esc_attr($session_limit) . '" min="1" max="100" />';
        echo '<p class="description">' . __('Numărul default de ședințe pentru abonamente noi.', OC_TEXT_DOMAIN) . '</p></td></tr>';
        
        echo '<tr><th scope="row">' . __('Restricție Validare', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><select name="oc_membership_validation_restriction">';
        echo '<option value="none"' . selected($validation_restriction, 'none', false) . '>' . __('Fără restricții', OC_TEXT_DOMAIN) . '</option>';
        echo '<option value="once_per_day"' . selected($validation_restriction, 'once_per_day', false) . '>' . __('O dată pe zi', OC_TEXT_DOMAIN) . '</option>';
        echo '<option value="once_per_session"' . selected($validation_restriction, 'once_per_session', false) . '>' . __('O dată pe ședință', OC_TEXT_DOMAIN) . '</option>';
        echo '</select></td></tr>';
        
        echo '<tr><th scope="row">' . __('Cleanup după (zile)', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><input type="number" name="oc_membership_cleanup_days" value="' . esc_attr($cleanup_days) . '" min="1" max="365" />';
        echo '<p class="description">' . __('Șterge datele vechi după numărul specificat de zile.', OC_TEXT_DOMAIN) . '</p></td></tr>';
        
        echo '<tr><th scope="row">' . __('Debug Mode', OC_TEXT_DOMAIN) . '</th>';
        echo '<td><input type="checkbox" name="oc_membership_debug_enabled" value="1" ' . checked($debug_enabled, '1', false) . ' />';
        echo '<p class="description">' . __('Activează logging extins pentru debugging.', OC_TEXT_DOMAIN) . '</p></td></tr>';
        
        echo '</table>';
        
        submit_button(__('Salvează Setările', OC_TEXT_DOMAIN), 'primary', 'oc_save_membership_settings');
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render pagină debug default dacă template-ul nu există
     */
    private function render_default_debug_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . __('Membership Validator - Debug Tools', OC_TEXT_DOMAIN) . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('Eroare:', OC_TEXT_DOMAIN) . '</strong> ';
        echo __('Fișierul de debug nu a fost găsit la calea:', OC_TEXT_DOMAIN) . ' ';
        echo '<code>' . plugin_dir_path(__FILE__) . 'membership-validator-debug.php</code>';
        echo '</p></div>';
        
        echo '<div class="card">';
        echo '<h2>' . __('Link Direct pentru Debug', OC_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . __('Poți accesa direct instrumentele de debug la:', OC_TEXT_DOMAIN) . '</p>';
        echo '<p><a href="' . plugin_dir_url(__FILE__) . 'membership-validator-debug.php?test_key=membership_test_2024" target="_blank" class="button button-primary">';
        echo __('Deschide Debug Tools', OC_TEXT_DOMAIN);
        echo '</a></p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * HOOK: Procesează membership la finalizare comandă
     * 
     * v1.2.0: Detectare pachet + tracking sessions direct + warnings nemapate
     * SISTEMUL POOL: Procesează produse Pool și variațiile lor
     * 
     * @param int $order_id ID comandă WooCommerce
     * @return void
     */
    public function process_new_membership(int $order_id): void {
        if (!$this->db) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $is_unlimited_gateway = $this->is_unlimited_payment_method(
            (string) $order->get_payment_method(),
            (string) $order->get_payment_method_title()
        );
        
        // Idempotent: dacă am procesat deja acest order, ieșim
        if ($order->get_meta('_oc_membership_processed') === 'yes') {
            return;
        }

        // 🎯 v1.2.0: Găsește PACHET (backwards compatibility _mv_pack_ + _oc_pool_)
        $package_item = null;
        $package_product_id = 0;
        $package_order_item_id = 0;
        foreach ($order->get_items() as $item_id => $item) {
            // Pachet = produs fără variație + preț > 0
            if ($item->get_variation_id() == 0 && $item->get_total() > 0) {
                $product_id = $item->get_product_id();

                $is_oc_pool = get_post_meta($product_id, '_oc_pool_enabled', true);
                $is_mv_pack = get_post_meta($product_id, '_mv_pack_enabled', true);

                if ($is_oc_pool || $is_mv_pack) {
                    $package_item = $item;
                    $package_product_id = $product_id;
                    $package_order_item_id = $item_id;
                    break;
                }
            }
        }
        
        $gateway_copayment_amount = $this->resolve_gateway_copayment_amount($order, $package_item);
        $is_gateway_copayment = $is_unlimited_gateway && $gateway_copayment_amount > 0;
        $is_gateway_unlimited = $is_unlimited_gateway && !$is_gateway_copayment;

        // Creează memberships cu tracking DIRECT + WARNING nemapate
        foreach ($order->get_items() as $item_id => $item) {
            if ($this->is_pool_child_item($item)) {
                $variation_id = $item->get_variation_id();
                $product_id = $item->get_product_id();
                
                // 🎯 v1.2.0: Determinare sessions și unlimited
                // LOGICA: Dacă pachetul e VIP → TOATE cursurile sunt nelimitate
                //         Altfel → folosește config ore/ședințe
                
                // Verifică bifa VIP direct din meta Pool product
                $is_vip_pool = $package_product_id && get_post_meta( $package_product_id, '_oc_pool_is_unlimited', true ) === 'yes';

                if ($is_gateway_unlimited || $is_vip_pool) {
                    // Gateway nelimitat (7CARD/ESX) SAU bifă VIP explicit în Pool config
                    $sessions_allocated = OC_UNLIMITED_SESSIONS;
                    $is_unlimited = 1;
                } else {
                    // 📊 PACHET NORMAL → citește config ore/ședințe
                    $config = $this->db->get_course_hours_config($variation_id);
                    
                    if (!$config) {
                        // ⚠️ WARNING: Curs nemapt - funcțional cu fallback
                        $sessions_allocated = 8;
                        $is_unlimited = 0;
                        
                        // 🚫 Email admin DOAR pentru comenzi recente (ultimele 7 zile) și NU în sync mode
                        $order_created = $order->get_date_created();
                        $days_old = $order_created
                            ? ((oc_membership_current_local_datetime()->getTimestamp() - $order_created->getTimestamp()) / DAY_IN_SECONDS)
                            : OC_ORDER_AGE_FALLBACK_DAYS;
                        
                        // Email admin (doar prima dată per curs - cache 24h) și DOAR pentru comenzi recente
                        $unmapped_flag = 'unmapped_course_' . $variation_id;
                        $is_sync_mode = defined('OC_SYNC_MODE') && OC_SYNC_MODE;
                        
                        if (!get_transient($unmapped_flag) && $days_old <= 7 && !$is_sync_mode) {
                            $course_product = wc_get_product($variation_id);
                            $course_name = $course_product ? $course_product->get_name() : "Curs #$variation_id";
                            
                            wp_mail(
                                get_option('admin_email'),
                                '⚠️ Curs nemapt detectat în comandă',
                                sprintf(
                                    "Cursul '%s' (ID: %d) nu este configurat în Ore Cursuri.\n\nComanda: #%d\nFolosit default: 8 ședințe\n\nConfigurează-l aici:\n%s",
                                    $course_name, $variation_id, $order_id,
                                    admin_url('admin.php?page=membership-manager&tab=hours-config')
                                )
                            );
                            
                            // Cache 24h pentru a nu spama
                            set_transient($unmapped_flag, 1, DAY_IN_SECONDS);
                        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                            // Log pentru comenzi care NU trimit email (vechi sau sync mode)
                            $reason = $is_sync_mode ? 'SYNC MODE' : sprintf('comandă veche (%d zile)', round($days_old));
                            error_log(sprintf(
                                '[Membership Validator] %s - Curs nemapt: variation_id=%d, order_id=%d - Email NU trimis',
                                $reason, $variation_id, $order_id
                            ));
                        }
                    } else {
                        // ✅ Curs mapt - folosește config (FĂRĂ unlimited din config!)
                        $sessions_allocated = $config['sessions_per_month'];
                        $is_unlimited = 0; // Cursurile normale nu sunt niciodată unlimited din config
                    }
                }
                
                // Prioritize explicit admin-provided dates from order meta when available.
                $activation_date_meta = trim((string) $order->get_meta('_oc_activation_date'));
                $expiration_date_meta = trim((string) $order->get_meta('_oc_expiration_date'));

                $activation_date = $this->normalize_iso_date_value($activation_date_meta);
                $expiration_date = $this->normalize_iso_date_value($expiration_date_meta);
                $observations = sanitize_textarea_field((string) $order->get_meta('_oc_observations'));

                // 🎯 v1.2.1: Folosește DATA COMENZII pentru calcule (nu data curentă!)
                $order_date = $order->get_date_created();
                $order_date_string = $order_date ? $order_date->date('Y-m-d') : oc_membership_current_business_date();
                $reference_date = $activation_date !== '' ? $activation_date : $order_date_string;
                
                // Construiește datele membership cu tracking DIRECT
                $membership_data = [
                    'user_id' => $order->get_user_id(),
                    'order_id' => $order_id,
                    'order_item_id' => $item_id,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    
                    // 🎯 v1.2.0: Info PACHET
                    'package_product_id' => $package_product_id,
                    'package_order_item_id' => $package_order_item_id,
                    
                    // 🎯 v1.2.0: Tracking DIRECT ședințe
                    'sessions_allocated' => $sessions_allocated,
                    'is_unlimited' => $is_unlimited,
                    'is_gateway_unlimited' => $is_gateway_unlimited ? 1 : 0, // 7CARD/ESX fără coplată = fără dată expirare
                    
                    // Explicit dates from admin flow are respected downstream when present.
                    'activation_date' => $activation_date,
                    'expiration_date' => $expiration_date,
                    'observations' => $observations,

                    // 🎯 v1.2.1: DATA REFERINȚĂ pentru calcule (data comenzii, NU data curentă!)
                    'reference_date' => $reference_date
                ];
                
                $membership_id = $this->db->create_membership_validation($membership_data);
                
                // 🎯 HOOK: Generare automată QR la creare membership
                if ($membership_id && $order->get_user_id() > 0) {
                    do_action('oc_membership_created', $order->get_user_id(), $membership_data);
                }
            }
        }
        
        // Marchează order-ul ca procesat pentru a preveni duplicate
        $order->update_meta_data('_oc_membership_processed', 'yes');
        $order->save();
        
        // 🔒 MANUAL ACTIVATION: TOATE abonamentele rămân PENDING până la activare manuală de admin
        // Eliminat activate_pending_memberships_instant() - activare doar prin buton admin
    }

    private function resolve_gateway_copayment_amount($order, $package_item): float {
        if (!$order) {
            return 0.0;
        }

        // Preferă prețul explicit setat în fluxul admin (new client / renew).
        $custom_price = max(0, (float) $order->get_meta('_oc_custom_package_price'));
        if ($custom_price > 0) {
            return $custom_price;
        }

        if ($package_item && method_exists($package_item, 'get_total')) {
            $package_total = max(0, (float) $package_item->get_total());
            if ($package_total > 0) {
                return $package_total;
            }
        }

        return max(0, (float) $order->get_total());
    }

    private function is_unlimited_payment_method(string $payment_method_id, string $payment_method_title): bool {
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

        if (in_array($method_id, ['oc_7card', 'oc_esx'], true)) {
            return true;
        }

        return (
            strpos($method_id, '7card') !== false ||
            strpos($method_id, 'esx') !== false ||
            strpos($method_title, '7card') !== false ||
            strpos($method_title, 'esx') !== false
        );
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

    /**
     * HOOK: Anulează membership la anulare comandă
     */
    public function cancel_membership(int $order_id): void {
        if (!$this->db) return;
        $this->db->update_membership_status_by_order($order_id, 'cancelled');
    }

    /**
     * HOOK: Rambursează membership la rambursare comandă
     */
    public function refund_membership(int $order_id): void {
        if (!$this->db) return;
        $this->db->update_membership_status_by_order($order_id, 'cancelled');
    }

    /**
     * HOOK: Leagă membership de pachet Pool Product Manager
     */
    public function link_membership_to_package(int $package_id, array $config): void {
        if (!$this->db) return;
        
        // Citește din metadata Pool Product Manager (NON-INTRUZIV)
        $pool_data = get_post_meta($package_id, '_oc_pool_config', true);
        if ($pool_data && isset($pool_data['product_id'])) {
            $this->db->link_pool_package($pool_data['product_id'], $package_id, $config);
        }
    }

    /**
     * 🔄 FUNCȚIE: Sincronizează toate comenzile WooCommerce existente
     */
    public function sync_existing_orders(): array {
        if (!$this->db) {
            return ['error' => 'Database not available'];
        }

        global $wpdb;
        
        // Obține toate comenzile completed care nu sunt în tabelul nostru
        $existing_orders = $wpdb->get_results("
            SELECT p.ID as order_id, p.post_date
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order' 
            AND p.post_status = 'wc-completed'
            AND p.ID NOT IN (
                SELECT DISTINCT order_id 
                FROM {$wpdb->prefix}membership_validations 
                WHERE order_id IS NOT NULL
            )
            ORDER BY p.post_date DESC
            LIMIT 100
        ");

        $synced_count = 0;
        $skipped_count = 0;
        $errors = [];

        foreach ($existing_orders as $order_data) {
            try {
                $order = wc_get_order($order_data->order_id);
                if (!$order) {
                    $skipped_count++;
                    continue;
                }

                $has_membership = false;
                foreach ($order->get_items() as $item_id => $item) {
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();
                    
                    // SISTEMUL POOL: Sincronizează doar copii Pool
                    if ($this->is_pool_child_item($item)) {
                        $membership_data = [
                            'user_id' => $order->get_user_id(),
                            'order_id' => $order_data->order_id,
                            'order_item_id' => $item_id,
                            'product_id' => $product_id,
                            'variation_id' => $item->get_variation_id()
                        ];
                        
                        $this->db->create_membership_validation($membership_data);
                        $has_membership = true;
                    }
                }

                if ($has_membership) {
                    $synced_count++;
                } else {
                    $skipped_count++;
                }

            } catch (Exception $e) {
                $errors[] = "Order {$order_data->order_id}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'synced' => $synced_count,
            'skipped' => $skipped_count,
            'total_found' => count($existing_orders),
            'errors' => $errors
        ];
    }

    /**
     * HOOK: Îmbunătățește datele de schedule cu informații membership
     */
    public function enhance_schedule_with_membership_data(array $schedule_data): array {
        if (!$this->db || empty($schedule_data)) return $schedule_data;

        // Adaugă informații despre membership-uri active pentru fiecare curs
        foreach ($schedule_data as &$course) {
            if (isset($course['id'])) {
                $course['active_memberships'] = $this->db->count_active_memberships_for_schedule($course['id']);
            }
        }

        return $schedule_data;
    }


    /**
     * Adaugă stiluri specifice pentru pagina de debug în admin
     */
    public function add_debug_admin_styles(): void {
        ?>
        <style type="text/css">
            /* Stiluri moderne pentru debug tools în WordPress admin */
            .wrap .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
                gap: 20px !important;
                margin: 20px 0 !important;
            }
            
            .wrap .stat-card {
                background: white !important;
                padding: 20px !important;
                border-radius: 8px !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                text-align: center !important;
                border-top: 4px solid #0073aa !important;
                border: 1px solid #ddd !important;
            }
            
            .wrap .stat-number {
                font-size: 1.4em !important;
                font-weight: bold !important;
                color: #0073aa !important;
                margin: 10px 0 !important;
            }
            
            .wrap .stat-label {
                color: #666 !important;
                font-size: 0.9em !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                font-weight: 600 !important;
            }
            
            .wrap .test-section {
                margin: 25px 0 !important;
                padding: 20px !important;
                border: 1px solid #ddd !important;
                border-radius: 8px !important;
                background: #fafafa !important;
                border-left: 4px solid #0073aa !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
            }
            
            .wrap .test-section:hover {
                box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
            }
            
            .wrap .test-section h2 {
                color: #23282d !important;
                border-bottom: 2px solid #0073aa !important;
                padding-bottom: 8px !important;
                margin-top: 0 !important;
                margin-bottom: 15px !important;
                font-weight: 600 !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            
            .wrap .status-indicator {
                display: inline-flex !important;
                align-items: center !important;
                gap: 4px !important;
                padding: 3px 8px !important;
                border-radius: 12px !important;
                font-size: 0.85em !important;
                font-weight: 500 !important;
            }
            
            .wrap .status-indicator.success {
                background: #d1ecf1 !important;
                color: #0c5460 !important;
                border: 1px solid #bee5eb !important;
            }
            
            .wrap .status-indicator.error {
                background: #f8d7da !important;
                color: #721c24 !important;
                border: 1px solid #f5c6cb !important;
            }
            
            .wrap .status-indicator.warning {
                background: #fff3cd !important;
                color: #856404 !important;
                border: 1px solid #ffeaa7 !important;
            }
            
            .wrap .test-section table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 15px 0 !important;
                background: white !important;
                border-radius: 6px !important;
                overflow: hidden !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            }
            
            .wrap .test-section table th {
                background: #23282d !important;
                color: white !important;
                padding: 12px 10px !important;
                text-align: left !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                font-size: 0.8em !important;
                letter-spacing: 0.5px !important;
            }
            
            .wrap .test-section table td {
                padding: 10px !important;
                border-bottom: 1px solid #eee !important;
                vertical-align: middle !important;
            }
            
            .wrap .test-section table tr:nth-child(even) {
                background: #f9f9f9 !important;
            }
            
            .wrap .test-section table tr:hover {
                background: #f0f8ff !important;
            }
            
            .wrap .code {
                background: #23282d !important;
                color: #f1f1f1 !important;
                padding: 15px !important;
                border-radius: 6px !important;
                margin: 12px 0 !important;
                font-family: Consolas, Monaco, 'Courier New', monospace !important;
                font-size: 0.9em !important;
                line-height: 1.4 !important;
                border-left: 3px solid #0073aa !important;
                overflow-x: auto !important;
            }
            
            .wrap .version-badge {
                background: #0073aa !important;
                color: white !important;
                padding: 3px 6px !important;
                border-radius: 10px !important;
                font-size: 0.75em !important;
                font-weight: 500 !important;
            }
            
            .wrap .icon {
                font-size: 1.1em !important;
                margin-right: 4px !important;
            }
            
            .wrap code {
                background: #f1f1f1 !important;
                padding: 2px 4px !important;
                border-radius: 3px !important;
                font-family: Consolas, Monaco, 'Courier New', monospace !important;
                font-size: 0.85em !important;
                color: #d63384 !important;
            }

            /* Responsive pentru admin */
            @media (max-width: 782px) {
                .wrap .stats-grid {
                    grid-template-columns: 1fr !important;
                }
                
                .wrap .test-section {
                    padding: 15px !important;
                }
                
                .wrap .test-section table {
                    font-size: 0.9em !important;
                }
            }
        </style>
        <?php
    }

    // ========================================
    // WORDPRESS CRON - FAZA 2
    // ========================================

    /**
     * Programează cleanup-ul automat pentru expirări
     */
    public function schedule_cleanup_cron(): void {
        if (!wp_next_scheduled('oc_membership_cleanup_expired')) {
            wp_schedule_event(time(), 'daily', 'oc_membership_cleanup_expired');
        }
        
        // ❌ DEZACTIVAT: Activare automată zilnică - doar activare manuală din buton
        // if (!wp_next_scheduled('oc_membership_activate_pending')) {
        //     wp_schedule_event(time(), 'daily', 'oc_membership_activate_pending');
        // }
        
        // 🎯 v1.3.0: Schedule pentru expirare memberships
        if (!wp_next_scheduled('oc_membership_expire_old')) {
            wp_schedule_event(time(), 'daily', 'oc_membership_expire_old');
        }
    }

    /**
     * Cleanup automat membership-uri expirate - rulează zilnic (LEGACY)
     */
    public function cron_cleanup_expired(): void {
        if (!$this->validator) {
            return;
        }
        
        $this->validator->cleanup_expired_memberships();
    }
    
    /**
     * 🎯 RENEWAL SYSTEM: Activează automat membership-uri pending când start_date <= TODAY
     * Rulează zilnic la miezul nopții
     * 
     * @since 1.3.0
     */
    public function activate_pending_memberships(): void {
        if (!$this->db) {
            return;
        }
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
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
        
        $activated_count = 0;
        
        foreach ($pending_memberships as $membership) {
            // Resetează ore din course_hours_config
            $hours_reset = $this->db->reset_membership_hours_on_activation($membership->id);
            
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
            
            // Generează QR code pentru noul membership activ
            if ($this->qr_system) {
                $qr_token = bin2hex(random_bytes(32));
                $this->qr_system->generate_qr_code($membership->id, $qr_token);
            }
            
            $activated_count++;
        }
        
        // 🔄 CRITICAL: Invalidare cache după activări (pentru refresh dashboard)
        if ($activated_count > 0) {
            // Object cache invalidation handled per-entry by DB layer
        }
    }
    
    /**
     * 🎯 RENEWAL SYSTEM: Marchează membership-uri expirate când expiration_date < TODAY
     * Rulează zilnic la miezul nopții
     * 
     * @since 1.3.0
     */
    public function expire_old_memberships(): void {
        if (!$this->db) {
            return;
        }

        $this->db->sync_membership_statuses();
    }

    // ========================================
    // AJAX HANDLERS - FAZA 2
    // ========================================

    /**
     * AJAX handler pentru validare membership cu QR code - FAZA 2
     * Best practices 2025: Enhanced security, input validation
     */
    public function ajax_validate_membership(): void {
        // Enhanced security validation
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_membership_validation')) {
            wp_die('Security check failed', 'Error', ['response' => 403]);
        }
        
        $qr_token = sanitize_text_field(wp_unslash($_POST['qr_token'] ?? ''));
        
        if (empty($qr_token) || strlen($qr_token) < 8) {
            wp_send_json_error(['message' => 'QR token invalid.']);
            return;
        }
        
        // Enhanced context validation
        $context = [
            'location' => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
            'instructor' => sanitize_text_field(wp_unslash($_POST['instructor'] ?? '')),
            'source' => 'ajax_request',
            'timestamp' => current_time('mysql'),
            'user_ip' => function_exists('oc_get_client_ip') ? oc_get_client_ip() : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
        ];
        
        // Validare prin sistemul complet FAZA 2
        if (!$this->validator) {
            wp_send_json_error(['message' => 'Sistemul de validare nu este disponibil.']);
            return;
        }
        
        $qr_validation = $this->qr_system ? $this->qr_system->validate_qr_token($qr_token, $context) : false;
        if (!$qr_validation || empty($qr_validation['user_id'])) {
            wp_send_json_error([
                'message' => 'QR code invalid sau expirat.',
                'error_code' => 'INVALID_QR'
            ]);
            return;
        }

        $result = $this->validator->check_in_user((int) $qr_validation['user_id'], $context);
        if (empty($result['success'])) {
            wp_send_json_error([
                'message' => (string) ($result['message'] ?? 'Validarea nu a putut fi finalizata.'),
                'error_code' => (string) ($result['code'] ?? 'VALIDATION_FAILED')
            ]);
            return;
        }

        $result['user_id'] = (int) $qr_validation['user_id'];
        $result['user_name'] = (string) ($qr_validation['user_name'] ?? '');
        $result['photo_url'] = (string) ($qr_validation['photo_url'] ?? '');
        $result['product_name'] = (string) ($qr_validation['product_name'] ?? '');
        $result['validated_at'] = current_time('mysql');
        
        wp_send_json_success($result);
    }

    /**
     * AJAX handler pentru generare QR code - FAZA 2
     * Best practices 2025: Enhanced security, capabilities check
     */
    public function ajax_generate_qr_code(): void {
        // Enhanced security validation
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_membership_qr_generation')) {
            wp_die('Security check failed', 'Error', ['response' => 403]);
        }
        
        // Capability check pentru generare QR
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }
        
        $validation_id = absint($_POST['validation_id'] ?? 0);
        
        if ($validation_id <= 0) {
            wp_send_json_error(['message' => 'Validation ID invalid.']);
            return;
        }
        
        // Generare QR prin sistemul complet FAZA 2
        if (!$this->validator) {
            wp_send_json_error(['message' => 'Sistemul de validare nu este disponibil.']);
            return;
        }
        
        $result = $this->validator->generate_qr_for_validation($validation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'error_code' => $result->get_error_code()
            ]);
            return;
        }
        
        wp_send_json_success($result);
    }

    // ========================================
    // SECURITY HELPERS - BEST PRACTICES 2025
    // ========================================

}

// Clasa este încărcată prin OC_Membership_Validator_Loader
// NU se auto-inițializează aici pentru a evita conflictele
