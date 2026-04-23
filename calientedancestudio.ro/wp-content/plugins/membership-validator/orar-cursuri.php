<?php
/**
 * Plugin Name: Membership Validator Core
 * Plugin URI: https://github.com/Remus0-me
 * Description: Plugin WordPress modular pentru validarea membrilor și gestionarea accesului. Include modul de orar pentru cursuri bazat pe produse variabile WooCommerce.
 * Version: 2.0.0
 * Author: Remus Lazar
 * Author URI: https://github.com/Remus0-me
 * Text Domain: membership-validator-core
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 8.2
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package MembershipValidatorCore
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug logging removed to prevent log spam

// Plugin constants
define('OC_PLUGIN_FILE', __FILE__);
define('OC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OC_PLUGIN_VERSION', '2.0.0');
define('OC_DB_VERSION', '2.0.0');
define('OC_TEXT_DOMAIN', 'membership-validator-core');

if (!function_exists('oc_is_debug_logging_enabled')) {
    function oc_is_debug_logging_enabled(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}

if (!function_exists('oc_log_debug')) {
    function oc_log_debug(string $message): void {
        if (oc_is_debug_logging_enabled()) {
            error_log($message);
        }
    }
}

if (!function_exists('oc_log_error')) {
    function oc_log_error(string $message): void {
        error_log($message);
    }
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', 
            __FILE__, 
            true
        );
    }
});

/**
 * Main plugin class - Membership Validator Core
 * 
 * @since 1.0.0
 */
final class OC_Orar_Cursuri {
    
    /**
     * Single instance
     * 
     * @var OC_Orar_Cursuri|null
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $db;
    public $admin;
    public $frontend;
    public $assets;
    public $dashboard;
    
    /**
     * Get single instance
     * 
     * @return OC_Orar_Cursuri
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // 🚀 Hook pentru sincronizare automată la activare
        add_action('oc_membership_sync_on_activation', [$this, 'run_activation_sync']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check WooCommerce dependency
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Informativ: avertizează în admin dacă lipsește pluginul de plăți,
        // dar NU blochează inițializarea pluginului.
        if (!$this->is_payment_providers_active()) {
            add_action('admin_notices', [$this, 'payment_providers_missing_notice']);
        }
        
        $this->load_dependencies();
        $this->init_components();
        
        // Load textdomain after everything is initialized
        add_action('init', [$this, 'load_textdomain']);
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            OC_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    private function is_woocommerce_active() {
        // Check if WooCommerce class exists
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Check WooCommerce version compatibility
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '6.0', '<')) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if Payments 7CARD and ESX plugin is active
     *
     * @return bool
     */
    private function is_payment_providers_active() {
        // Main plugin defines this constant on load.
        return defined('OC_PAYMENT_PROVIDERS_VERSION');
    }
    
    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        if (!class_exists('WooCommerce')) {
            $message = sprintf(
                /* translators: %s: WooCommerce plugin name */
                __('Orar Cursuri necesită %s pentru a funcționa. Te rugăm să instalezi și să activezi WooCommerce.', OC_TEXT_DOMAIN),
                '<strong>WooCommerce</strong>'
            );
        } else {
            $message = sprintf(
                /* translators: %s: WooCommerce minimum version */
                __('Orar Cursuri necesită WooCommerce versiunea %s sau mai nouă. Te rugăm să actualizezi WooCommerce.', OC_TEXT_DOMAIN),
                '<strong>6.0</strong>'
            );
        }
        
        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            wp_kses_post($message)
        );
    }

    /**
     * Display Payments 7CARD and ESX missing notice
     */
    public function payment_providers_missing_notice() {
        $message = sprintf(
            /* translators: %s: plugin name */
            __('Orar Cursuri necesită %s pentru a funcționa corect. Te rugăm să instalezi și să activezi pluginul.', OC_TEXT_DOMAIN),
            '<strong>Payments 7CARD and ESX</strong>'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post($message)
        );
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core framework classes
        require_once OC_PLUGIN_DIR . 'includes/class-oc-addon-manager.php';
        require_once OC_PLUGIN_DIR . 'includes/class-oc-dashboard.php';
        
        // Core classes
        require_once OC_PLUGIN_DIR . 'includes/class-oc-db.php';
        require_once OC_PLUGIN_DIR . 'includes/class-oc-admin.php';
        require_once OC_PLUGIN_DIR . 'includes/class-oc-frontend.php';
        require_once OC_PLUGIN_DIR . 'includes/class-oc-assets.php';
        
        // Utility classes
        require_once OC_PLUGIN_DIR . 'includes/class-oc-woocommerce.php';
        require_once OC_PLUGIN_DIR . 'includes/class-oc-settings.php';

        require_once OC_PLUGIN_DIR . 'includes/class-oc-validator.php';

        // React Check-in App shortcode mount point.
        require_once OC_PLUGIN_DIR . 'includes/class-oc-react-checkin-page.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize ADD-ON Manager first (required by other components)
        // This is done via class-oc-addon-manager.php which calls OC_Addon_Manager::init()
        
        // Initialize core framework
        $this->dashboard = new OC_Dashboard();
        
        // Initialize core classes
        $this->db = new OC_DB();
        $this->admin = new OC_Admin();
        $this->frontend = new OC_Frontend();
        $this->assets = new OC_Assets();

        // React Check-in App — registers [oc_checkin_app] shortcode.
        new OC_React_Checkin_Page();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check WooCommerce before activation
        if (!$this->is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Orar Cursuri necesită WooCommerce pentru a funcționa.', OC_TEXT_DOMAIN),
                __('Plugin Activation Error', OC_TEXT_DOMAIN),
                ['back_link' => true]
            );
        }

        // Load dependencies and create tables
        $this->load_dependencies();
        
        $db = new OC_DB();
        $result = $db->create_tables();
        
        // Log table creation for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oc_log_debug('OC Plugin Activation: Tables created = ' . ($result ? 'YES' : 'NO'));
            oc_log_debug('OC Plugin Activation: Table exists = ' . ($db->table_exists() ? 'YES' : 'NO'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Ensure built-in ADD-ONS are active
        $this->ensure_builtin_addons_active();
        
        // Migrate old option key if needed
        $this->migrate_old_options();
        
        // 🚀 SINCRONIZARE AUTOMATĂ comenzi WooCommerce la activare
        $this->sync_woocommerce_orders_on_activation();
        
        // 🎯 GENERARE QR CODES pentru toți membrii existenți
        $this->schedule_qr_generation_on_activation();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Programează generarea QR codes la activare
     */
    private function schedule_qr_generation_on_activation() {
        // Șterge transient vechi pentru a permite regenerare
        delete_transient('oc_qr_generation_scheduled');
        
        // Programează generarea QR cu delay minim + forțează execuție
        if (!wp_next_scheduled('oc_generate_missing_qr_codes')) {
            wp_schedule_single_event(time() + 2, 'oc_generate_missing_qr_codes');
            spawn_cron(); // Forțează execuție instant
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('[Plugin Activation] QR generation scheduled and cron spawned');
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Șterge transient QR pentru a permite regenerare la reactivare
        delete_transient('oc_qr_generation_scheduled');
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = [
            'selected_attribute' => '',
            'show_empty_days' => false,
            'primary_color' => '#e63946',
            'text_color' => '#111111',
            'secondary_color' => '#5f6368',
            'background_color' => '#ffffff',
            'muted_color' => '#fafafa',
            'border_color' => '#e5e7eb',
            'font_family' => 'Segoe UI, Roboto, Arial, sans-serif',
            'font_size' => '14px',
            'header_font_size' => '24px',
            'desktop_bg_image' => '',
            'mobile_bg_image' => '',
            'border_radius' => '12px'
        ];
        
        foreach ($default_settings as $key => $value) {
            if (false === get_option("oc_{$key}")) {
                add_option("oc_{$key}", $value);
            }
        }
        
        // Set DB version
        add_option('oc_db_version', OC_DB_VERSION);
    }
    
    /**
     * Migrate old option keys to new ones
     */
    private function migrate_old_options() {
        // Migrate old attribute_taxonomy to selected_attribute
        $old_value = get_option('oc_attribute_taxonomy', '');
        if (!empty($old_value)) {
            $new_value = get_option('oc_selected_attribute', '');
            if (empty($new_value)) {
                update_option('oc_selected_attribute', $old_value);
            }
            // Don't delete the old option yet for backward compatibility
        }
    }
    
    /**
     * 🚀 Sincronizează automat comenzile WooCommerce la activarea pluginului
     * 
     * Verifică inteligent dacă trebuie să sincronizeze:
     * - Dacă tabelul e gol → SINCRONIZEAZĂ
     * - Dacă ultima sincronizare e mai veche de 5 minute → SINCRONIZEAZĂ
     * 
     * @since 1.2.1
     */
    private function sync_woocommerce_orders_on_activation() {
        global $wpdb;
        
        // 🎯 VERIFICARE INTELIGENTĂ: Verifică dacă tabelul e gol
        $table_name = $wpdb->prefix . 'membership_validations';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        $has_data = false;
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
            $has_data = ($count > 0);
        }
        
        // Dacă tabelul e gol SAU nu există, forțează sincronizarea
        if (!$has_data) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC Activation: Tabel gol detectat, fortez sincronizarea...');
            }
            
            // ȘTERGE timestamp-ul vechi pentru a forța sincronizarea
            delete_option('oc_membership_last_activation_sync');
            
            // Programează sincronizarea INSTANT (1 secundă)
            wp_schedule_single_event(time() + 1, 'oc_membership_sync_on_activation');
            
            // 🔥 FORȚEAZĂ SPAWN CRON IMEDIAT (garantează execuția)
            spawn_cron();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC Activation: Sincronizare programata si cron fortat');
            }
            
            return;
        }
        
        // Dacă tabelul ARE date, verifică timestamp-ul ultimei sincronizări
        $last_sync = get_option('oc_membership_last_activation_sync', 0);
        $current_time = time();
        
        // Dacă sincronizarea a fost făcută în ultimele 5 minute, skip
        if (($current_time - $last_sync) < 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC Activation: Skip sincronizare (rulata recent)');
            }
            return;
        }
        
        // Marchează că sincronizarea va fi rulată
        update_option('oc_membership_last_activation_sync', $current_time);
        
        // Programează sincronizarea INSTANT (1 secundă) + forțează cron
        wp_schedule_single_event(time() + 1, 'oc_membership_sync_on_activation');
        spawn_cron();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oc_log_debug('OC Activation: Sincronizare programata pentru ' . date('H:i:s', time() + 1));
        }
    }
    
    /**
     * Rulează sincronizarea efectivă (apelată prin cron)
     * 
     * @since 1.2.1
     */
    public function run_activation_sync() {
        // 🚫 FLAG GLOBAL: Indică că suntem în SYNC MODE - ZERO emailuri trimise!
        if (!defined('OC_SYNC_MODE')) {
            define('OC_SYNC_MODE', true);
        }

        $debug = defined('WP_DEBUG') && WP_DEBUG;

        // 🔧 ÎNCARCĂ clasa Migration dacă nu e încărcată
        if (!class_exists('OC_Membership_Migration')) {
            $migration_file = OC_PLUGIN_DIR . 'includes/addons/membership-validator/membership-validator-migration.php';
            if (file_exists($migration_file)) {
                require_once($migration_file);
                if ($debug) oc_log_debug('OC Activation Sync: Clasa OC_Membership_Migration incarcata manual');
            } else {
                if ($debug) oc_log_debug('OC Activation Sync: Fisier migration nu exista');
                return;
            }
        }
        
        // Verifică din nou dacă clasa există după încărcare
        if (!class_exists('OC_Membership_Migration')) {
            if ($debug) oc_log_debug('OC Activation Sync: Clasa OC_Membership_Migration nu s-a putut incarca');
            return;
        }
        
        try {
            $migration = new OC_Membership_Migration();
            
            // Verifică schema DB
            $schema_check = $migration->verify_schema_integrity();
            
            if (!$schema_check['complete']) {
                // Schema incompletă - recreează tabelele
                if ($debug) oc_log_debug('OC Activation Sync: Schema incompleta, recreez tabelele...');
                $results = $migration->force_recreate_and_sync();
            } else {
                // Schema completă - sincronizează doar comenzile noi
                if ($debug) oc_log_debug('OC Activation Sync: Schema completa, sincronizez comenzile...');
                
                global $wpdb;
                $orders = $wpdb->get_results("
                    SELECT DISTINCT p.ID as order_id
                    FROM {$wpdb->posts} p
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-completed', 'wc-processing')
                    AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                    ORDER BY p.post_date DESC
                    LIMIT 100
                ");
                
                // 🚫 DEZACTIVEAZĂ TOATE EMAILURILE WOOCOMMERCE în timpul sincronizării
                $email_filters_disabled = [
                    'woocommerce_email_enabled_customer_completed_order',
                    'woocommerce_email_enabled_customer_processing_order',
                    'woocommerce_email_enabled_customer_invoice',
                    'woocommerce_email_enabled_new_order',
                    'woocommerce_email_enabled_customer_note',
                ];
                
                foreach ($email_filters_disabled as $filter) {
                    add_filter($filter, '__return_false', 999);
                }
                
                if ($debug) oc_log_debug('OC Activation Sync: Emailuri WooCommerce dezactivate pentru sincronizare');
                
                $count = 0;
                foreach ($orders as $order_data) {
                    do_action('woocommerce_order_status_completed', $order_data->order_id);
                    $count++;
                }
                
                // ✅ REACTIVEAZĂ emailurile după sincronizare
                foreach ($email_filters_disabled as $filter) {
                    remove_filter($filter, '__return_false', 999);
                }
                
                if ($debug) oc_log_debug('OC Activation Sync: Emailuri WooCommerce reactivate');
                
                $results = [
                    'success' => true,
                    'orders_found' => count($orders),
                    'memberships_created' => $count
                ];
            }
            
            // Log rezultatele
            if ($debug) {
                if ($results['success']) {
                    oc_log_debug(sprintf(
                        'OC Activation Sync completa: %d comenzi gasite, %d memberships create',
                        $results['orders_found'],
                        $results['memberships_created']
                    ));
                } else {
                    oc_log_debug('OC Activation Sync esuata: ' . implode(', ', $results['errors'] ?? []));
                }
            }
            
        } catch (Exception $e) {
            if ($debug) oc_log_debug('OC Activation Sync error: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure built-in ADD-ONS are active
     */
    private function ensure_builtin_addons_active() {
        $builtin_addons = ['schedule_manager', 'pool_product_manager'];
        $active_addons = get_option('oc_active_addons', []);
        
        // Merge built-in ADD-ONS with existing active ones
        $active_addons = array_unique(array_merge($active_addons, $builtin_addons));
        
        update_option('oc_active_addons', $active_addons);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oc_log_debug('OC Plugin: Built-in ADD-ONS activated: ' . implode(', ', $builtin_addons));
        }
    }
}

/**
 * Initialize plugin
 * 
 * @return OC_Orar_Cursuri
 */
function oc_orar_cursuri() {
    return OC_Orar_Cursuri::instance();
}

// Start the plugin
oc_orar_cursuri();
