<?php
/**
 * Membership Manager - ADD-ON #2 pentru Dashboard-uri și Analytics
 * 
 * CONFORMITATE .cursorrules:
 * - ADD-ON #2 independent în /includes/addons/membership-manager/
 * - Creează tabele NOI: membership_course_mapping, membership_validation_log
 * - Integrare NON-INTRUZIVĂ cu ADD-ON #1 (Membership Validator)
 * - Zero modificări în fișiere existente
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Manager
 * 
 * ADD-ON #2: Gestionează dashboard-uri client și admin, analytics, shortcode-uri
 * Best practices 2025: Modularul, scalabil, cu dependency injection
 */
class OC_Membership_Manager {
    
    /**
     * @var string Plugin version
     */
    private string $version = '2.0.0';
    
    /**
     * @var OC_Membership_Manager|null Singleton instance
     */
    private static ?OC_Membership_Manager $instance = null;
    
    /**
     * @var OC_Membership_Admin|null Admin dashboard handler
     */
    private ?OC_Membership_Admin $admin = null;
    
    /**
     * @var OC_Membership_Dashboard|null Client dashboard handler
     */
    private ?OC_Membership_Dashboard $dashboard = null;
    
    /**
     * @var OC_Membership_Shortcodes_Refactored|null Shortcodes handler
     */
    private ?OC_Membership_Shortcodes_Refactored $shortcodes = null;
    
    /**
     * @var OC_Membership_DB|null Database handler din ADD-ON #1
     */
    private ?OC_Membership_DB $validator_db = null;
    
    /**
     * Singleton pattern pentru ADD-ON
     */
    public static function get_instance(): OC_Membership_Manager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privat pentru singleton
     */
    private function __construct() {
        $this->init_addon();
    }
    
    /**
     * Inițializează ADD-ON-ul conform pattern-ului existent
     */
    private function init_addon(): void {
        // OPTIMIZARE: STOP SPAM LOGGING COMPLET
        static $already_initialized = false;
        if ($already_initialized) return;
        $already_initialized = true;
        
        // Verifică dependințele (ADD-ON #1 trebuie să fie activ)
        if (!$this->check_dependencies()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC_Membership_Manager: Dependencies failed in init_addon()');
            }
            return;
        }
        
        // 🔧 AUTO-FIX: Expirare automată abonamente vechi (pending >28 zile, active expirate)
        if (is_admin() && class_exists('OC_Membership_Validator')) {
            $validator = OC_Membership_Validator::get_instance();
            if ($validator && $validator->get_db() && !get_transient('oc_manager_auto_fix_statuses_done')) {
                $validator->get_db()->auto_fix_membership_statuses();
                set_transient('oc_manager_auto_fix_statuses_done', 1, DAY_IN_SECONDS);
            }
        }
        
        // Încarcă componentele
        $this->load_components();
        
        // Setup hooks
        $this->setup_hooks();
        
        // Log removed to prevent spam
    }
    
    /**
     * Verifică dependințele - ADD-ON #1 trebuie să fie activ
     */
    private function check_dependencies(): bool {
        // Verifică că ADD-ON #1 (Membership Validator) este activ
        if (!class_exists('OC_Membership_Validator')) {
            add_action('admin_notices', [$this, 'validator_missing_notice']);
            return false;
        }
        
        // Verifică WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Încarcă componentele ADD-ON-ului
     */
    private function load_components(): void {
        // Verifică dependency - ADD-ON #1 trebuie să fie activ
        if (!class_exists('OC_Membership_Validator')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC_Membership_Manager: Dependency missing - OC_Membership_Validator nu este disponibil');
            }
            return;
        }
        
        $addon_path = plugin_dir_path(__FILE__);
        
        // Încarcă toate clasele FAZA 3
        require_once $addon_path . 'class-oc-membership-admin.php';
        require_once $addon_path . 'class-oc-membership-dashboard.php';
        require_once $addon_path . 'class-oc-membership-shortcodes-refactored.php';
        
        // Obține referința la DB-ul din ADD-ON #1 (NON-INTRUZIV)
        try {
            $validator_instance = OC_Membership_Validator::get_instance();
            $this->validator_db = $validator_instance->get_db();
            
            // Inițializează componentele cu dependency injection
            if ($this->validator_db) {
                $this->admin = new OC_Membership_Admin($this->validator_db);
                $this->dashboard = new OC_Membership_Dashboard($this->validator_db);
                $this->shortcodes = new OC_Membership_Shortcodes_Refactored($this->validator_db);
                
                // 🔒 Load AJAX handler pentru activare manuală
                require_once OC_PLUGIN_DIR . 'includes/addons/membership-manager/ajax-activate-membership.php';
                new OC_Membership_Activation_AJAX();
                
                // Log removed to prevent spam
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    oc_log_debug('OC_Membership_Manager: validator_db este null');
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oc_log_debug('OC_Membership_Manager: Eroare la incarcarea componentelor: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Setup hooks pentru ADD-ON
     */
    private function setup_hooks(): void {
        // Log removed to prevent spam
        
        // Hook pentru crearea tabelelor FAZA 3
        add_action('oc_addon_activated', [$this, 'on_addon_activated'], 10, 2);
        add_action('oc_addon_deactivated', [$this, 'on_addon_deactivated'], 10, 1);
        
        // Hook pentru integrare cu WooCommerce My Account
        // Prioritate mare ca să re-adăugăm itemul și dacă alte plugin-uri rescriu meniul.
        add_filter('woocommerce_account_menu_items', [$this, 'add_membership_menu_item'], 999, 1);
        add_action('woocommerce_account_membership_endpoint', [$this, 'membership_dashboard_content']);
        
        // ✅ NOU: Ascunde sidebar-ul My Account pe pagina membership
        add_action('template_redirect', [$this, 'hide_my_account_sidebar_on_membership'], 5);
        
        // ✅ CRITICAL FIX: Înregistrare endpoint în hook init pentru ca pagina să existe mereu
        // Prioritate 20 pentru a rula după ce WooCommerce își înregistrează endpoint-urile
        add_action('init', [$this, 'add_woocommerce_endpoint'], 20);
        
        // ✅ FIX SUPLIMENTAR: Forțează flush rewrite rules când se accesează endpoint-ul pentru prima dată
        // Acest hook rulează înainte de procesarea template-ului
        add_action('template_redirect', [$this, 'ensure_endpoint_registered'], 5);
        
        // ✅ NOU: Redirect după login la pagina specificată în parametru redirect_to
        // Prioritate mare pentru a suprascrie comportamentul default WooCommerce
        // Folosim aceeași metodă pentru ambele hook-uri - metoda detectează automat semnătura
        add_filter('woocommerce_login_redirect', [$this, 'custom_login_redirect'], 99, 2);
        add_filter('login_redirect', [$this, 'custom_login_redirect'], 99, 3);
        
        // Hook pentru admin menu (similar cu Pool Product Manager)
        add_action('admin_menu', [$this, 'add_admin_menu'], 20); // După ADD-ON #1
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Hook pentru shortcode-uri - înregistrează IMEDIAT dacă init a trecut deja
        if (did_action('init')) {
            // Hook-ul init a trecut deja - înregistrează acum
            $this->register_shortcodes();
            static $logged_direct = false;
            if (defined('WP_DEBUG') && WP_DEBUG && !$logged_direct) {
                // Log removed to prevent spam
                $logged_direct = true;
            }
        } else {
            // Hook-ul init nu a trecut încă - înregistrează normal
            add_action('init', [$this, 'register_shortcodes']);
        }
        
        // Fallback shortcode dacă ADD-ON-ul nu este complet încărcat (prioritate mai mică)
        add_action('init', [$this, 'register_fallback_shortcode'], 25);
        
        // Hook pentru AJAX endpoints FAZA 3
        add_action('wp_ajax_oc_membership_analytics', [$this, 'ajax_analytics_data']);
        add_action('wp_ajax_oc_membership_export', [$this, 'ajax_export_data']);
        
        // Hook pentru AJAX endpoints - Admin Table Editing
        // Note: Admin table este creat în shortcodes refactored, nu în admin class
        // Înregistrăm hooks după ce shortcodes sunt înregistrate
        add_action('init', function() {
            if ($this->shortcodes) {
                $admin_table = $this->shortcodes->get_admin_table();
                if ($admin_table) {
                    // Admin AJAX handlers
                    add_action('wp_ajax_oc_validate_membership_smart', [$admin_table, 'ajax_validate_membership_smart']);
                    add_action('wp_ajax_oc_get_user_qr_codes', [$admin_table, 'ajax_get_user_qr_codes']);
                    add_action('wp_ajax_oc_save_member_data', [$admin_table, 'ajax_save_member_data']);
                    add_action('wp_ajax_oc_get_package_courses', [$admin_table, 'ajax_get_package_courses']);
                    add_action('wp_ajax_oc_get_all_pool_courses', [$admin_table, 'ajax_get_all_pool_courses']);
                    add_action('wp_ajax_oc_add_supplementary_course', [$admin_table, 'ajax_add_supplementary_course']);
                    add_action('wp_ajax_oc_renew_subscription', [$admin_table, 'ajax_renew_subscription']);
                    add_action('wp_ajax_oc_get_membership_validation_history', [$admin_table, 'ajax_get_membership_validation_history']);
                    add_action('wp_ajax_oc_verify_active_membership_edit_pin', [$admin_table, 'ajax_verify_active_membership_edit_pin']);
                    
                    // NOTE: nopriv handlers removed — all smart-validation handlers require nonce + authenticated user
                    add_action('wp_ajax_oc_create_new_client', [$admin_table, 'ajax_create_new_client']);
                    add_action('wp_ajax_oc_create_woo_order_guest', [$admin_table, 'ajax_create_woo_order_for_guest']);
                    add_action('wp_ajax_oc_cancel_membership', [$admin_table, 'ajax_cancel_membership']); // ← NOU: Anulare abonament
                    // Handler oc_activate_membership_manual is registered via OC_Membership_Activation_AJAX
                }
            }
        }, 30); // Prioritate mai mare decât înregistrarea shortcodes
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Log removed to prevent spam
        }
    }
    
    /**
     * Callback când ADD-ON-ul este activat
     */
    public function on_addon_activated(string $addon_id, array $addon): void {
        if ($addon_id !== 'membership_manager') {
            return;
        }
        
        // Aliniază schema prin DB-ul canonical din Membership Validator.
        if ($this->validator_db) {
            $this->validator_db->maybe_create_tables();
        }
        
        // Adaugă WooCommerce endpoint pentru My Account
        $this->add_woocommerce_endpoint();
        
        // Flush rewrite rules pentru endpoint nou (la activare)
        flush_rewrite_rules();
        
        // Marchează că endpoint-ul a fost înregistrat
        update_option('oc_membership_endpoint_flushed', true);
    }
    
    /**
     * Callback când ADD-ON-ul este dezactivat
     */
    public function on_addon_deactivated(string $addon_id): void {
        if ($addon_id !== 'membership_manager') {
            return;
        }
        
        // Remove WooCommerce endpoint
        $this->remove_woocommerce_endpoint();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Șterge opțiunea pentru ca endpoint-ul să fie reînregistrat la următoarea activare
        delete_option('oc_membership_endpoint_flushed');
    }
    
    /**
     * Adaugă endpoint pentru WooCommerce My Account
     * Public pentru a putea fi apelată din hook init
     */
    public function add_woocommerce_endpoint(): void {
        // Verifică că WooCommerce este activ
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Adaugă endpoint-ul
        add_rewrite_endpoint('membership', EP_ROOT | EP_PAGES);
        
        // ✅ FIX: Flush rewrite rules doar dacă endpoint-ul nu există deja înregistrat
        // Evită flush la fiecare request (performanță)
        $option_key = 'oc_membership_endpoint_flushed';
        if (!get_option($option_key, false)) {
            flush_rewrite_rules(false); // false = nu reface toate endpoint-urile, doar le actualizează
            update_option($option_key, true);
        }
    }
    
    /**
     * Remove endpoint pentru WooCommerce My Account
     */
    private function remove_woocommerce_endpoint(): void {
        // WordPress va handle automat la flush_rewrite_rules
    }
    
    /**
     * Adaugă item în meniul My Account WooCommerce
     */
    public function add_membership_menu_item(array $items): array {
        $membership_label = __('Abonamente', OC_TEXT_DOMAIN);

        if (isset($items['membership'])) {
            $items['membership'] = $membership_label;
            return $items;
        }

        // Inserează "Abonamente" după "Dashboard"
        $new_items = [];
        $inserted = false;
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            if ($key === 'dashboard') {
                $new_items['membership'] = $membership_label;
                $inserted = true;
            }
        }

        // Fallback: dacă dashboard lipsește (meniu custom), adaugă la final.
        if (!$inserted) {
            $new_items['membership'] = $membership_label;
        }

        return $new_items;
    }
    
    /**
     * Asigură că endpoint-ul este înregistrat când se accesează
     * Hook în template_redirect pentru a fixa problema 404
     */
    public function ensure_endpoint_registered(): void {
        global $wp;

        $has_membership_query_var = isset($wp->query_vars['membership']);
        $has_membership_get_param = isset($_GET['membership']);

        // Detectează și cazul permalink-urilor "pretty" (/my-account/membership/)
        $request_path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $my_account_path = wp_parse_url((string) wc_get_page_permalink('myaccount'), PHP_URL_PATH);
        $is_membership_pretty_url = false;

        if (is_string($request_path) && is_string($my_account_path) && $request_path !== '' && $my_account_path !== '') {
            $request_path = '/' . trim($request_path, '/');
            $my_account_path = '/' . trim($my_account_path, '/');
            $membership_path = rtrim($my_account_path, '/') . '/membership';
            $is_membership_pretty_url = $request_path === $membership_path || $request_path === ($membership_path . '/');
        }

        $is_membership_request = $has_membership_query_var || $has_membership_get_param || $is_membership_pretty_url;
        if (!$is_membership_request) {
            return;
        }

        // Verifică dacă endpoint-ul este deja în query vars
        if (!$has_membership_query_var) {
            // Endpoint-ul nu este detectat - forțează înregistrarea și flush
            $this->add_woocommerce_endpoint();
            flush_rewrite_rules(false);

            // Redirect la endpoint-ul corect pentru reîncărcare cu rewrite rules actualizate
            if (function_exists('wc_get_account_endpoint_url')) {
                wp_safe_redirect(wc_get_account_endpoint_url('membership'));
            } else {
                wp_safe_redirect(add_query_arg('membership', '', wc_get_page_permalink('myaccount')));
            }
            exit;
        }
    }
    
    /**
     * Ascunde sidebar-ul My Account pe pagina membership
     */
    public function hide_my_account_sidebar_on_membership(): void {
        global $wp;
        
        // Verifică dacă suntem pe endpoint-ul membership
        if (is_account_page() && isset($wp->query_vars['membership'])) {
            // Ascunde navigația laterală (sidebar-ul cu link-uri)
            remove_action('woocommerce_account_navigation', 'woocommerce_account_navigation');
            add_filter('woocommerce_account_menu_items', '__return_empty_array', 999);
            
            // Adaugă CSS pentru a face pagina full-width fără sidebar
            add_action('wp_head', [$this, 'add_membership_page_styles'], 999);
            
            // Modifică wrapper-ul pentru a elimina spațiul pentru sidebar
            add_filter('body_class', [$this, 'add_membership_full_width_class']);
        }
    }
    
    /**
     * Adaugă CSS pentru pagina membership (full-width, fără sidebar)
     */
    public function add_membership_page_styles(): void {
        ?>
        <style id="oc-membership-full-width">
            /* Ascunde navigația laterală My Account */
            body.woocommerce-account.oc-membership-full-width .woocommerce-MyAccount-navigation,
            body.woocommerce-account.oc-membership-full-width .woocommerce-account-navigation {
                display: none !important;
            }
            
            /* Face conținutul full-width */
            body.woocommerce-account.oc-membership-full-width .woocommerce-MyAccount-content,
            body.woocommerce-account.oc-membership-full-width .woocommerce-account-content {
                width: 100% !important;
                float: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Ascunde wrapper-ul WooCommerce standard */
            body.woocommerce-account.oc-membership-full-width .woocommerce-account .woocommerce-MyAccount-navigation-wrapper,
            body.woocommerce-account.oc-membership-full-width .woocommerce-account .woocommerce-account-navigation-wrapper {
                display: none !important;
            }
            
            /* Full-width pentru pagina completă */
            body.woocommerce-account.oc-membership-full-width .woocommerce-account .woocommerce-MyAccount-wrapper,
            body.woocommerce-account.oc-membership-full-width .woocommerce-account .woocommerce-account-wrapper {
                max-width: 100% !important;
                width: 100% !important;
            }
        </style>
        <?php
    }
    
    /**
     * Adaugă clasă CSS pentru pagina membership full-width
     */
    public function add_membership_full_width_class(array $classes): array {
        global $wp;
        
        if (is_account_page() && isset($wp->query_vars['membership'])) {
            $classes[] = 'oc-membership-full-width';
        }
        
        return $classes;
    }
    
    /**
     * Conținut pentru pagina membership din My Account
     */
    public function membership_dashboard_content(): void {
        // ✅ AFIȘARE SHORTCODE [membership_page] în My Account
        // Aceasta va afișa același conținut ca pagina cu shortcode-ul
        if ($this->shortcodes) {
            echo do_shortcode('[membership_page]');
        } else {
            echo '<p>' . esc_html__('Membership system is loading...', OC_TEXT_DOMAIN) . '</p>';
        }
    }
    
    /**
     * Custom redirect după login - redirecționează la pagina specificată în parametru redirect_to
     * 
     * Gestionăm două semnături diferite:
     * - woocommerce_login_redirect: (string $redirect_to, WP_User $user)
     * - login_redirect: (string $redirect_to, string $requested_redirect_to, WP_User|WP_Error|null $user)
     * 
     * @param string $redirect_to URL-ul default de redirect
     * @param string|WP_User|WP_Error $param2 Al doilea parametru (WP_User pentru WooCommerce sau string pentru login_redirect)
     * @param WP_User|WP_Error|null $param3 Al treilea parametru (doar pentru login_redirect)
     * @return string URL-ul final de redirect
     */
    public function custom_login_redirect(string $redirect_to, $param2 = '', $param3 = null): string {
        // ✅ DETECTARE SEMNĂTURĂ: Verifică tipul celui de-al doilea parametru
        // Dacă al doilea parametru este WP_User sau WP_Error → woocommerce_login_redirect
        if ($param2 instanceof WP_User || $param2 instanceof WP_Error) {
            $user = $param2;
            $requested_redirect_to = '';
        } else {
            // Dacă al doilea parametru este string → login_redirect
            $requested_redirect_to = (string)$param2;
            $user = $param3;
        }
        
        // ✅ FIX: Verifică dacă este WP_Error (login eșuat) - returnează redirect default
        if ($user instanceof WP_Error) {
            return $redirect_to;
        }
        
        // Verifică dacă utilizatorul este valid WP_User
        if (!$user instanceof WP_User) {
            return $redirect_to;
        }
        
        // Verifică dacă există parametru redirect_to în URL sau în $_REQUEST
        $redirect_param = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : (isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '');
        
        if (!empty($redirect_param)) {
            $redirect_url = urldecode($redirect_param);
            
            // Validare securitate: verifică că URL-ul este de pe același site
            $parsed_url = wp_parse_url($redirect_url);
            $home_parsed = wp_parse_url(home_url());
            
            // Verifică că host-ul este același
            if ($parsed_url && isset($parsed_url['host'])) {
                $valid_host = ($parsed_url['host'] === $home_parsed['host']) || 
                             ($parsed_url['host'] === $_SERVER['HTTP_HOST']);
                
                if ($valid_host) {
                    // Curăță parametrul redirect_to din URL pentru a evita loop-uri
                    $clean_url = remove_query_arg('redirect_to', $redirect_url);
                    return esc_url_raw($clean_url);
                }
            }
        }
        
        // Dacă există requested_redirect_to și este valid, folosește-l
        if (!empty($requested_redirect_to)) {
            $parsed_url = wp_parse_url($requested_redirect_to);
            $home_parsed = wp_parse_url(home_url());
            
            if ($parsed_url && isset($parsed_url['host'])) {
                $valid_host = ($parsed_url['host'] === $home_parsed['host']) || 
                             ($parsed_url['host'] === $_SERVER['HTTP_HOST']);
                
                if ($valid_host) {
                    return esc_url_raw($requested_redirect_to);
                }
            }
        }
        
        // Fallback: redirect la my-account dacă nu există parametru
        $my_account_url = wc_get_page_permalink('myaccount');
        if ($my_account_url) {
            return $my_account_url;
        }
        
        return $redirect_to;
    }
    
    /**
     * Adaugă meniu admin - TAB SYSTEM
     */
    public function add_admin_menu(): void {
        // Pagina principală Membership Manager cu taburi interne
        add_submenu_page(
            'membership-validator-dashboard',
            __('Membership Manager', OC_TEXT_DOMAIN),
            '📊 Membership Manager',
            'manage_options',
            'membership-manager',
            [$this, 'admin_page_callback']
        );
        
        // ELIMINATED: Separate submenus for Analytics, Reports, Mapping, Settings, Shortcodes, Debug
        // These are now tabs within the main Membership Manager page
        // All callback methods kept for compatibility and internal tab routing
    }
    
    /**
     * Înregistrează shortcode-urile
     */
    public function register_shortcodes(): void {
        if ($this->shortcodes) {
            $this->shortcodes->register_all();
            

        } else {
            // Log eroarea FĂRĂ backup pentru a evita conflictele
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('❌ OC_Membership_Manager: Shortcodes component nu este încărcat - NU se înregistrează backup');
            }
            // ELIMINAT: add_shortcode backup pentru a evita conflictele duplicate
        }
    }
    
    /**
     * Înregistrează fallback shortcode dacă componentele nu sunt încărcate
     */
    public function register_fallback_shortcode(): void {
        global $shortcode_tags;
        
        // Verifică de 2 ori înainte să înregistreze fallback
        if (!isset($shortcode_tags['membership_page'])) {
            // Dă o șansă să se înregistreze shortcode-ul principal
            if ($this->shortcodes) {
                $this->shortcodes->register_all();
            }
            
            // Verifică din nou după încercarea finală
            if (!isset($shortcode_tags['membership_page'])) {
                // ELIMINAT: Fallback shortcode pentru a evita conflictele duplicate
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('❌ OC_Membership_Manager: Shortcode principal nu s-a înregistrat - NU se adaugă fallback pentru a evita conflictele');
                }
            }
        } else {
            // Shortcode-ul principal este înregistrat
            if (defined('WP_DEBUG') && WP_DEBUG) {
                                        // Log removed to prevent spam
            }
        }
    }
    
    /**
     * Direct shortcode backup când $this->shortcodes este null
     */
    public function direct_shortcode_backup($atts): string {
        // Mesaj pentru admin cu debugging info
        if (current_user_can('administrator')) {
            return '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h4>🔧 Membership Page - Mode Debug</h4>
                <p><strong>Pentru administratori:</strong></p>
                <ul>
                    <li>✅ ADD-ON-ul Membership Manager este activat</li>
                    <li>⚠️ Shortcodes component nu s-a încărcat corect</li>
                    <li>🔄 Se folosește înregistrare directă de backup</li>
                </ul>
                <p><a href="' . admin_url('admin.php?page=oc-system-debug') . '" class="button">Verifică Debug Tools</a></p>
            </div>';
        }
        
        // Pentru utilizatorii obișnuiți
        return '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
            <p>📱 Pagina membership se încarcă...</p>
        </div>';
    }
    
    /**
     * Output pentru fallback shortcode
     */
    public function fallback_shortcode_output($atts): string {
        // Verifică dacă utilizatorul este admin pentru a afișa mesaje de debug
        if (current_user_can('administrator')) {
            return '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h4>⚠️ Membership Page Temporar Indisponibilă</h4>
                <p><strong>Pentru administratori:</strong></p>
                <ul>
                    <li>Verificați că ADD-ON-ul <strong>Membership Validator</strong> este activat</li>
                    <li>Verificați că ADD-ON-ul <strong>Membership Manager</strong> este activat</li>
                    <li>Verificați log-urile WordPress pentru erori</li>
                </ul>
                <p><a href="' . admin_url('admin.php?page=membership-validator-dashboard') . '" class="button">Mergi la Dashboard</a></p>
            </div>';
        }
        
        // Pentru utilizatorii obișnuiți, mesaj simplu
        return '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
            <p>📱 Pagina membership este temporar indisponibilă. Vă rugăm să încercați mai târziu.</p>
        </div>';
    }
    
    /**
     * AJAX handler pentru analytics data
     */
    public function ajax_analytics_data(): void {
        if ($this->admin) {
            $this->admin->ajax_analytics_data();
        }
    }
    
    /**
     * AJAX handler pentru export data
     */
    public function ajax_export_data(): void {
        if ($this->admin) {
            $this->admin->ajax_export_data();
        }
    }
    
    /**
     * Notice pentru ADD-ON #1 lipsă
     */
    public function validator_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Membership Manager:</strong> ';
        echo 'Requires Membership Validator add-on to be active.';
        echo '</p></div>';
    }
    
    /**
     * Notice pentru WooCommerce lipsă
     */
    public function woocommerce_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Membership Manager:</strong> ';
        echo 'Requires WooCommerce to be active.';
        echo '</p></div>';
    }
    
    /**
     * Get plugin version
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Get admin component
     */
    public function get_admin(): ?OC_Membership_Admin {
        return $this->admin;
    }
    
    /**
     * Get dashboard component
     */
    public function get_dashboard(): ?OC_Membership_Dashboard {
        return $this->dashboard;
    }
    
    /**
     * Get shortcodes component
     */
    public function get_shortcodes(): ?OC_Membership_Shortcodes_Refactored {
        return $this->shortcodes;
    }
    
    /**
     * Callback pentru pagina principală admin
     */
    public function admin_page_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get current tab from URL parameter
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'manager';
        $debug_tab_enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');
        
        // Validate tab - 🎯 v1.2.0: adăugat hours-config
        $valid_tabs = ['manager', 'analytics', 'reports', 'mapping', 'hours-config', 'settings', 'shortcodes', 'debug'];
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'manager';
        }
        
        if ($current_tab === 'debug' && !$debug_tab_enabled) {
            $current_tab = 'manager';
        }
        
        // Include template cu taburi
        $template_path = OC_PLUGIN_DIR . 'templates/membership-manager/admin-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
            return;
        }
        
        // Fallback - render original
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Membership Manager', OC_TEXT_DOMAIN); ?></h1>
            
            <div class="oc-admin-grid">
                <div class="oc-admin-card">
                    <h3>📈 Analytics</h3>
                    <p>View detailed analytics and usage statistics for all memberships.</p>
                    <a href="<?php echo admin_url('admin.php?page=oc-membership-analytics'); ?>" class="button button-primary">
                        View Analytics
                    </a>
                </div>
                
                <div class="oc-admin-card">
                    <h3>📋 Reports</h3>
                    <p>Generate and export detailed reports for accounting and management.</p>
                    <a href="<?php echo admin_url('admin.php?page=oc-membership-reports'); ?>" class="button button-primary">
                        View Reports
                    </a>
                </div>
                
                <div class="oc-admin-card">
                    <h3>⚙️ Settings</h3>
                    <p>Configure membership management settings and preferences.</p>
                    <a href="<?php echo admin_url('admin.php?page=oc-membership-settings'); ?>" class="button button-primary">
                        Manage Settings
                    </a>
                </div>
                
                <div class="oc-admin-card">
                    <h3>🎨 Shortcodes</h3>
                    <p>Learn about available shortcodes for frontend integration.</p>
                    <a href="<?php echo admin_url('admin.php?page=oc-membership-shortcodes'); ?>" class="button button-primary">
                        View Shortcodes
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .oc-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .oc-admin-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        
        .oc-admin-card h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .oc-admin-card p {
            color: #646970;
            margin-bottom: 15px;
        }
        </style>
        <?php
    }
    
    /**
     * Callback pentru pagina Analytics
     */
    public function analytics_page_callback(): void {
        if ($this->admin) {
            $this->admin->analytics_page_callback();
        }
    }
    
    /**
     * Callback pentru pagina Reports
     */
    public function reports_page_callback(): void {
        if ($this->admin) {
            $this->admin->reports_page_callback();
        }
    }
    
    /**
     * Callback pentru pagina Settings
     */
    public function settings_page_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Process form submission
        if (isset($_POST['submit']) && check_admin_referer('oc_membership_settings')) {
            $settings = [
                'default_membership_duration' => sanitize_text_field($_POST['default_membership_duration'] ?? '30'),
                'max_daily_sessions' => absint($_POST['max_daily_sessions'] ?? 2),
                'enable_email_notifications' => isset($_POST['enable_email_notifications']),
                'require_validation_confirmation' => isset($_POST['require_validation_confirmation'])
            ];
            
            update_option('oc_membership_settings', $settings);
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $settings = get_option('oc_membership_settings', [
            'default_membership_duration' => '30',
            'max_daily_sessions' => '2',
            'enable_email_notifications' => false,
            'require_validation_confirmation' => false
        ]);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Membership Settings', OC_TEXT_DOMAIN); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('oc_membership_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Membership Duration (days)</th>
                        <td>
                            <input type="number" name="default_membership_duration" 
                                   value="<?php echo esc_attr($settings['default_membership_duration']); ?>" 
                                   min="1" max="365" />
                            <p class="description">Default duration for new memberships in days.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Max Daily Sessions</th>
                        <td>
                            <input type="number" name="max_daily_sessions" 
                                   value="<?php echo esc_attr($settings['max_daily_sessions']); ?>" 
                                   min="1" max="10" />
                            <p class="description">Maximum sessions a member can validate per day.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Email Notifications</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_email_notifications" 
                                       <?php checked($settings['enable_email_notifications']); ?> />
                                Send email notifications for membership activities
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Validation Confirmation</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_validation_confirmation" 
                                       <?php checked($settings['require_validation_confirmation']); ?> />
                                Require confirmation for each validation
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Callback pentru pagina Shortcodes
     */
    public function shortcodes_page_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Membership Shortcodes', OC_TEXT_DOMAIN); ?></h1>
            
            <div class="oc-shortcode-docs">
                <div class="oc-shortcode-section">
                    <h2>Available Shortcode</h2>
                    <p>Use this shortcode to display a complete membership page on your website.</p>
                </div>
                
                <div class="oc-shortcode-item">
                    <h3><code>[membership_page]</code></h3>
                    <p>🎯 <strong>SHORTCODE INTELIGENT</strong> - Detectează automat rolul utilizatorului și afișează conținutul potrivit:</p>
                    
                    <div class="oc-role-section">
                        <h4>👤 Pentru UTILIZATORI NORMALI:</h4>
                        <ul>
                            <li>🎫 <strong>Propriile membership-uri</strong> - Progres și ședințe rămase</li>
                            <li>📱 <strong>QR codes personale</strong> - Pentru validare la cursuri</li>
                            <li>⏰ <strong>Expiry tracking</strong> - Notificări și status</li>
                            <li>📞 <strong>Layout compact</strong> - Optimizat pentru mobile</li>
                        </ul>
                    </div>
                    
                    <div class="oc-role-section">
                        <h4>👨‍💼 Pentru ADMINISTRATORI:</h4>
                        <ul>
                            <li>📊 <strong>TOATE membership-urile</strong> - Overview complet sistem</li>
                            <li>👥 <strong>Management utilizatori</strong> - Inclusiv guest users</li>
                            <li>🛠️ <strong>Tools administrative</strong> - Statistici și controale</li>
                            <li>📱 <strong>Layout full</strong> - Detalii complete pentru management</li>
                        </ul>
                    </div>
                    
                    <h4>Utilizare ULTRA-SIMPLĂ:</h4>
                    <div class="oc-shortcode-example" style="background: #28a745; color: white; padding: 15px; border-radius: 5px;">
                        <strong>🎯 UN SINGUR SHORTCODE PENTRU TOT:</strong><br>
                        <code style="background: white; color: #333; padding: 5px 10px; border-radius: 3px; font-size: 16px;">[membership_page]</code>
                    </div>
                    
                    <p><strong>🚀 THAT'S IT!</strong> ZERO configurare necesară. Sistemul afișează automat:</p>
                    <ul style="color: #28a745; font-weight: bold;">
                        <li>✅ <strong>TOATE membership-urile</strong> (active + expirate)</li>
                        <li>✅ <strong>Rolul utilizatorului</strong> (admin vs user)</li>
                        <li>✅ <strong>Layout potrivit</strong> (full vs compact)</li>
                        <li>✅ <strong>Funcționalități complete</strong> (management vs usage)</li>
                        <li>✅ <strong>QR codes și statistici</strong> (automat pentru toți)</li>
                    </ul>
                    
                    <div style="background: #e7f3ff; padding: 10px; border-left: 4px solid #007cba; margin: 10px 0;">
                        <strong>💡 SIMPLU LA MAXIM:</strong> Nu mai trebuie să configurezi nimic! Shortcode-ul detectează automat ce să afișeze pentru fiecare utilizator.
                    </div>
                </div>
                
                <div class="oc-shortcode-item">
                    <h3>🎨 Styling</h3>
                    <p>The shortcode includes responsive CSS that adapts to your theme. You can customize colors and layout using CSS:</p>
                    <div class="oc-shortcode-example">
                        <strong>CSS classes available:</strong><br>
                        <code>.oc-membership-page</code> - Main container<br>
                        <code>.oc-membership-card</code> - Individual membership cards<br>
                        <code>.oc-qr-btn</code> - QR code buttons<br>
                        <code>.oc-stats-overview</code> - Statistics section
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .oc-shortcode-docs {
            max-width: 800px;
        }
        
        .oc-shortcode-section {
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }
        
        .oc-shortcode-item {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .oc-shortcode-item h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .oc-shortcode-item code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        
        .oc-shortcode-example {
            background: #f8f8f9;
            border: 1px solid #e1e1e1;
            padding: 10px;
            border-radius: 3px;
            margin-top: 10px;
        }
        
        .oc-shortcode-example code {
            background: transparent;
            color: #d63384;
        }
        </style>
        <?php
    }
    
    
    /**
     * Callback pentru pagina Debug unificată
     */
    public function unified_debug_page_callback(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Include debug tools cu titluri production-ready
        if (!defined('OC_MEMBERSHIP_ADMIN_DEBUG')) {
            define('OC_MEMBERSHIP_ADMIN_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
        }
        
        echo '<div class="wrap">';
        echo '<h1>🔧 System Debug Tools</h1>';
        echo '<div class="oc-debug-container">';
        echo '<div class="oc-debug-info-box">';
        echo '<strong>🔌 System Status:</strong><br>';
        echo '📊 Comprehensive testing and diagnostics for the membership validation system.<br>';
        echo '🌐 Access: <span class="oc-debug-highlight">WordPress Admin</span>';
        echo '</div>';
        
        $debug_file = plugin_dir_path(__FILE__) . '../membership-validator/membership-validator-debug.php';
        if (file_exists($debug_file)) {
            include $debug_file;
        } else {
            echo '<div class="oc-debug-warning-box"><strong>⚠️ Warning:</strong> Debug tools not found.</div>';
        }
        
        echo '</div>'; // oc-debug-container
        echo '</div>'; // wrap
        
        // CSS pentru debug identic cu developer-debug-page.php
        echo '<style>
        .oc-debug-container { 
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .oc-debug-stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin: 20px 0;
        }
        .oc-debug-stat-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center;
        }
        .oc-debug-stat-number { 
            font-size: 2em; 
            font-weight: bold; 
            display: block;
        }
        .oc-debug-info-box { 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0;
        }
        .oc-debug-warning-box { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0;
        }
        .oc-debug-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            background: white;
        }
        .oc-debug-table th, .oc-debug-table td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left;
            font-size: 13px;
        }
        .oc-debug-table th { 
            background: #f8f9fa; 
            font-weight: bold;
        }
        .oc-debug-table tr:nth-child(even) { 
            background: #f8f9fa; 
        }
        .oc-debug-table tr:hover { 
            background: #e8f4fd; 
        }
        .oc-debug-code { 
            background: #2c3e50; 
            color: #ecf0f1; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: "Courier New", monospace;
            overflow-x: auto;
            margin: 10px 0;
            white-space: pre-wrap;
        }
        .oc-debug-highlight {
            background: #f39c12;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .oc-debug-nav {
            margin: 20px 0;
        }
        .oc-debug-nav a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 15px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 13px;
        }
        .oc-debug-nav a:hover {
            background: #005a87;
        }
        .oc-debug-empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        h2.oc-debug-section {
            color: #23282d;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        h3.oc-debug-subsection {
            color: #555;
            margin-top: 30px;
        }
        
        /* Mapare clase pentru compatibility */
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #46b450; font-weight: bold; }
        .error { color: #dc3232; font-weight: bold; }
        .warning { color: #ffb900; font-weight: bold; }
        .code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: "Courier New", monospace; overflow-x: auto; margin: 10px 0; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
        table th, table td { border: 1px solid #ddd; padding: 12px 8px; text-align: left; font-size: 13px; }
        table th { background: #f8f9fa; font-weight: bold; }
        table tr:nth-child(even) { background: #f8f9fa; }
        table tr:hover { background: #e8f4fd; }
        </style>';
    }
    
    /**
     * Enqueue admin assets
     * 
     * Best Practice: CSS în fișier separat, nu inline în PHP
     */
    public function enqueue_admin_assets($hook): void {
        // Hook-ul REAL din debug.log: membership-validator_page_membership-manager
        if ($hook !== 'membership-validator_page_membership-manager') {
            return;
        }
        
        // Încarcă CSS extracted pentru membership manager (v2.1.0 - extracted from inline)
        wp_enqueue_style(
            'oc-membership-manager-extracted',
            OC_PLUGIN_URL . 'assets/membership-manager-extracted.css',
            [],
            filemtime(OC_PLUGIN_DIR . 'assets/membership-manager-extracted.css')
        );
        
        // Încarcă CSS-ul pentru debug tab DOAR când tab-ul debug este activ
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'manager';
        if ($current_tab === 'debug' && defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            wp_enqueue_style(
                'oc-membership-manager-debug',
                OC_PLUGIN_URL . 'assets/membership-manager-debug.css',
                ['oc-membership-manager-extracted'],
                filemtime(OC_PLUGIN_DIR . 'assets/membership-manager-debug.css')
            );
        }
        
        // Enqueue admin table editing assets
        wp_enqueue_script(
            'oc-admin-table-editing',
            OC_PLUGIN_URL . 'assets/admin-table-editing.js',
            ['jquery'],
            filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.js'),
            true
        );
        
        wp_localize_script('oc-admin-table-editing', 'ocAdminData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_membership_admin'),
            'dateFormat' => get_option('date_format'),
            'timeFormat' => get_option('time_format'),
            'locale' => get_locale()
        ]);
        
        wp_enqueue_style(
            'oc-admin-table-editing',
            OC_PLUGIN_URL . 'assets/admin-table-editing.css',
            [],
            filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.css')
        );
    }

    // =========================================================================
    // 🎯 PAGINĂ NOUĂ: CONFIGURARE MAPĂRI ABONAMENTE → CURSURI
    // =========================================================================

    /**
     * Callback pentru pagina de configurare mapări cursuri
     */
    public function course_mapping_page_callback(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Procesează salvarea mapărilor
        if (isset($_POST['save_mapping']) && wp_verify_nonce($_POST['mapping_nonce'], 'save_course_mapping')) {
            $this->save_course_mapping();
        }

        $this->render_course_mapping_page();
    }

    /**
     * Salvează mapările cursuri din formular
     */
    private function save_course_mapping(): void {
        $membership_product_id = absint($_POST['membership_product_id'] ?? 0);
        $selected_courses = array_map('absint', $_POST['selected_courses'] ?? []);

        if ($membership_product_id > 0) {
            // Obține instanța DB din Membership Validator
            $validator = OC_Membership_Validator::get_instance();
            if ($validator && $validator->get_db()) {
                $db = $validator->get_db();
                $success = $db->save_membership_course_mapping($membership_product_id, $selected_courses);

                if ($success) {
                    echo '<div class="notice notice-success"><p>';
                    echo sprintf(__('Mapările pentru abonamentul ID %d au fost salvate cu succes!', OC_TEXT_DOMAIN), $membership_product_id);
                    echo '</p></div>';
                    
                    // Redirect pentru a păstra selecția în dropdown și pentru a reîncărca checkboxurile
                    echo '<script type="text/javascript">';
                    echo 'window.location.href = "' . add_query_arg('product_id', $membership_product_id, remove_query_arg(['product_id'])) . '";';
                    echo '</script>';
                } else {
                    echo '<div class="notice notice-error"><p>';
                    echo __('Eroare la salvarea mapărilor!', OC_TEXT_DOMAIN);
                    echo '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-warning"><p>';
            echo __('Te rugăm să selectezi un abonament înainte de a salva mapările.', OC_TEXT_DOMAIN);
            echo '</p></div>';
        }
    }

    /**
     * Randează pagina de configurare mapări
     */
    private function render_course_mapping_page(): void {
        // 🎯 Obține PACHETELE (produse simple) care folosesc Pool cu variații în orar
        global $wpdb;
        
        // QUERY 1: Pachete cu noul format (_oc_pool_pool_id)
        $pool_products_new = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title,
                   pm_pool.meta_value as pool_id,
                   pool_p.post_title as pool_name,
                   'new_format' as source_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_pool ON p.ID = pm_pool.post_id AND pm_pool.meta_key = '_oc_pool_pool_id'
            INNER JOIN {$wpdb->posts} pool_p ON pm_pool.meta_value = pool_p.ID
            INNER JOIN {$wpdb->prefix}orar_cursuri oc ON pool_p.ID = oc.product_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pool_p.post_type = 'product'
            AND pool_p.post_status = 'publish'
        ");
        
        // QUERY 2: Pachete cu vechiul format (_mv_pack_pool_id) - BACKWARDS COMPATIBILITY
        $pool_products_old = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title,
                   pm_pool.meta_value as pool_id,
                   pool_p.post_title as pool_name,
                   'old_format' as source_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_pool ON p.ID = pm_pool.post_id AND pm_pool.meta_key = '_mv_pack_pool_id'
            INNER JOIN {$wpdb->posts} pool_p ON pm_pool.meta_value = pool_p.ID
            INNER JOIN {$wpdb->prefix}orar_cursuri oc ON pool_p.ID = oc.product_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pool_p.post_type = 'product'
            AND pool_p.post_status = 'publish'
        ");
        
        // QUERY 3: Pachete cu DUAL MODE (_oc_pool_dual_mode = 'yes') care au pool1_id configurat
        $pool_products_dual = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title,
                   pm_pool1.meta_value as pool_id,
                   pool_p.post_title as pool_name,
                   'dual_mode' as source_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_dual ON p.ID = pm_dual.post_id AND pm_dual.meta_key = '_oc_pool_dual_mode' AND pm_dual.meta_value = 'yes'
            INNER JOIN {$wpdb->postmeta} pm_pool1 ON p.ID = pm_pool1.post_id AND pm_pool1.meta_key = '_oc_pool_pool1_id' AND pm_pool1.meta_value != ''
            INNER JOIN {$wpdb->posts} pool_p ON pm_pool1.meta_value = pool_p.ID
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pool_p.post_type = 'product'
            AND pool_p.post_status = 'publish'
        ");

        // Combină rezultatele și elimină duplicatele
        $all_products = array_merge($pool_products_new, $pool_products_old, $pool_products_dual);
        $unique_products = [];
        $seen_ids = [];
        
        foreach ($all_products as $product) {
            if (!in_array($product->ID, $seen_ids)) {
                $unique_products[] = $product;
                $seen_ids[] = $product->ID;
            }
        }
        
        $pool_products = $unique_products;
        
        // Fallback 1: Pachete cu meta enabled (ambele formate)
        if (empty($pool_products)) {
            $pool_products = $wpdb->get_results("
                SELECT DISTINCT p.ID, p.post_title, 'Pool Package' as pool_name, 'enabled_fallback' as source_type
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key IN ('_oc_pool_enabled', '_mv_pack_enabled')
                AND pm.meta_value IN ('yes', '1', 'on')
                ORDER BY p.post_title
            ");
        }
        
        // Fallback 2: Toate produsele simple
        if (empty($pool_products)) {
            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_virtual',
                        'value' => 'yes',
                        'compare' => '!='
                    ]
                ]
            ];
            $fallback_products = get_posts($args);
            $pool_products = array_map(function($p) {
                return (object)['ID' => $p->ID, 'post_title' => $p->post_title, 'pool_name' => 'Simple Product', 'source_type' => 'ultimate_fallback'];
            }, $fallback_products);
        }
        
        // Sortează după titel
        usort($pool_products, function($a, $b) {
            return strcmp($a->post_title, $b->post_title);
        });

        // 🎯 Obține variațiile din produsele Pool folosind funcțiile Pool addon-ului
        $schedule_courses = [];
        
        // Verifică dacă Pool addon-ul este activ și funcțiile există
        if (function_exists('oc_pool_get_all_pool_ids')) {
            $pool_ids = oc_pool_get_all_pool_ids();
            
            if (!empty($pool_ids)) {
                global $wpdb;
                $pool_ids_str = implode(',', array_map('intval', $pool_ids));
                
                $schedule_courses = $wpdb->get_results("
                    SELECT DISTINCT 
                           v.ID as variation_id,
                           v.post_title as variation_name,
                           p.ID as product_id,
                           p.post_title as pool_name,
                           CASE WHEN oc.variation_id IS NOT NULL THEN 'in_schedule' ELSE 'pool_only' END as has_schedule
                    FROM {$wpdb->posts} v
                    INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
                    LEFT JOIN {$wpdb->prefix}orar_cursuri oc ON v.ID = oc.variation_id
                    WHERE v.post_type = 'product_variation' 
                    AND v.post_status = 'publish'
                    AND p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND p.ID IN ({$pool_ids_str})
                    ORDER BY p.post_title, v.post_title
                ");
            }
        }

        // 🔍 DEBUG: Afișează informații pentru debugging
        if (current_user_can('manage_options') && isset($_GET['debug'])) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">';
            echo '<h4>🔍 DEBUG INFO:</h4>';
            // Verifică Pool addon status
            if (!function_exists('oc_pool_get_all_pool_ids')) {
                echo '<p style="color: #d63638;"><strong>❌ Pool Product Manager addon nu este activ!</strong></p>';
            } else {
                $pool_ids = function_exists('oc_pool_get_all_pool_ids') ? oc_pool_get_all_pool_ids() : [];
                echo '<p><strong>Pool addon activ - Găsite ' . count($pool_ids) . ' Pool-uri configurate</strong></p>';
                
                if (!empty($pool_ids)) {
                    echo '<p><strong>Pool IDs:</strong> ' . implode(', ', $pool_ids) . '</p>';
                }
                
                echo '<p><strong>Găsite ' . count($schedule_courses) . ' variații din Pool-uri</strong></p>';
                
                if (!empty($schedule_courses)) {
                    echo '<pre style="font-size: 11px; max-height: 200px; overflow-y: auto;">';
                    foreach (array_slice($schedule_courses, 0, 5) as $course) {
                        echo "Pool ID#{$course->product_id}: {$course->pool_name} | Variație: {$course->variation_name} | In orar: {$course->has_schedule}\n";
                    }
                    if (count($schedule_courses) > 5) echo "... și încă " . (count($schedule_courses) - 5) . " variații\n";
                    echo '</pre>';
                } else if (!empty($pool_ids)) {
                    echo '<p style="color: #d63638;"><strong>❌ Pool-urile nu au variații publicate</strong></p>';
                    
                    foreach ($pool_ids as $pool_id) {
                        $pool_product = wc_get_product($pool_id);
                        if ($pool_product) {
                            $variations = $pool_product->get_children();
                            echo "<p>Pool #{$pool_id}: {$pool_product->get_name()} - " . count($variations) . " variații</p>";
                        }
                    }
                } else {
                    echo '<p style="color: #d63638;"><strong>❌ Nu există Pool-uri configurate în sistem</strong></p>';
                    
                    // Verifică pachete
                    if (function_exists('oc_pool_is_package')) {
                        $all_products = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY post_title");
                        echo '<p><strong>Pachete disponibile:</strong></p>';
                        echo '<ul style="max-height: 150px; overflow-y: auto;">';
                        foreach ($all_products as $prod) {
                            $is_package = oc_pool_is_package($prod->ID);
                            $pool_id = get_post_meta($prod->ID, '_oc_pool_pool_id', true);
                            if ($is_package) {
                                echo "<li>✅ Pachet ID#{$prod->ID}: {$prod->post_title} → Pool #{$pool_id}</li>";
                            }
                        }
                        echo '</ul>';
                    }
                }
            }
            echo '</div>';
        }

        ?>
        <div class="wrap">
            <h1>🎫 <?php echo esc_html(__('Configurare Mapări Abonamente → Cursuri', OC_TEXT_DOMAIN)); ?></h1>
            
            <!-- SECȚIUNEA NOUĂ: Afișarea mapărilor existente -->
            <?php $this->render_existing_mappings_section($pool_products); ?>
            
            <div class="card" style="max-width: none;">
                <h2><?php _e('Configurează ce cursuri validează fiecare abonament', OC_TEXT_DOMAIN); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('save_course_mapping', 'mapping_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="membership_product_id"><?php _e('Selectează Abonamentul:', OC_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <select name="membership_product_id" id="membership_product_id" class="regular-text">
                                    <option value=""><?php _e('-- Selectează un pachet/abonament --', OC_TEXT_DOMAIN); ?></option>
                                    <?php 
                                    // Păstrează valoarea selectată din URL sau POST
                                    $selected_product_id = '';
                                    if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
                                        $selected_product_id = absint($_GET['product_id']);
                                    } elseif (isset($_POST['membership_product_id']) && !empty($_POST['membership_product_id'])) {
                                        $selected_product_id = absint($_POST['membership_product_id']);
                                    }
                                    
                                    foreach ($pool_products as $product): 
                                    ?>
                                        <option value="<?php echo esc_attr($product->ID); ?>" 
                                                <?php selected($selected_product_id, $product->ID); ?>>
                                            <?php 
                                            echo esc_html($product->post_title);
                                            if (!empty($product->pool_name) && $product->pool_name !== 'Simple Product') {
                                                echo ' → Pool: ' . esc_html($product->pool_name);
                                            }
                                            echo ' (ID: ' . $product->ID . ')';
                                            
                                            // Debug info pentru source type
                                            if (!empty($product->source_type)) {
                                                $debug_labels = [
                                                    'new_format' => ' [NEW]',
                                                    'old_format' => ' [OLD]', 
                                                    'dual_mode' => ' [MIXT]',
                                                    'enabled_fallback' => ' [ENABLED]',
                                                    'ultimate_fallback' => ' [SIMPLE]'
                                                ];
                                                echo $debug_labels[$product->source_type] ?? '';
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Selectează abonamentul pentru care vrei să configurezi cursurile validate.', OC_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3><?php _e('Cursuri Disponibile:', OC_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Bifează cursurile care sunt validate de abonamentul selectat:', OC_TEXT_DOMAIN); ?></p>

                    <div style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fafafa;">
                        <?php
                        $current_pool = '';
                        foreach ($schedule_courses as $course):
                            if ($current_pool !== $course->pool_name):
                                if ($current_pool !== '') echo '</div>';
                                $current_pool = $course->pool_name;
                                echo '<h4 style="margin: 15px 0 10px 0; color: #0073aa; font-size: 1.1em; padding-bottom: 5px; border-bottom: 1px solid #ccc;">';
                                echo '🎯 Pool: ' . esc_html($current_pool ?: 'Pool necunoscut');
                                echo '</h4>';
                                echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px; margin-bottom: 15px;">';
                            endif;
                            
                            // Informații despre variație
                            $variation_name = $course->variation_name ?: "Variație #{$course->variation_id}";
                            $status_icon = $course->has_schedule === 'in_schedule' ? '✅' : '⚪';
                            $status_text = $course->has_schedule === 'in_schedule' ? 'Are programe în orar' : 'Fără programe în orar';
                            ?>
                            
                            <label style="display: flex; align-items: center; padding: 10px 12px; background: white; border-radius: 4px; border: 1px solid #e0e0e0; cursor: pointer; font-size: 13px; transition: all 0.2s;" 
                                   onmouseover="this.style.backgroundColor='#f0f8ff'; this.style.borderColor='#0073aa';" 
                                   onmouseout="this.style.backgroundColor='white'; this.style.borderColor='#e0e0e0';">
                                <input type="checkbox" name="selected_courses[]" value="<?php echo esc_attr($course->variation_id); ?>" 
                                       <?php 
                                       // Verifică dacă această variație este deja mapată pentru produsul selectat
                                       if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
                                           $validator = OC_Membership_Validator::get_instance();
                                           if ($validator && $validator->get_db()) {
                                               $db = $validator->get_db();
                                               $existing_mappings = $db->get_membership_course_mappings(absint($_GET['product_id']));
                                               $mapped_variations = array_column($existing_mappings, 'variation_id');
                                               echo in_array($course->variation_id, $mapped_variations) ? 'checked' : '';
                                           }
                                       }
                                       ?>
                                       style="margin-right: 12px; margin-top: 0;">
                                <span style="flex: 1;">
                                    <strong style="color: #2c5aa0; display: block; margin-bottom: 2px; font-size: 14px;">
                                        <?php echo esc_html($variation_name); ?>
                                    </strong>
                                    <small style="color: #999; display: block; margin-bottom: 3px; font-size: 11px;">
                                        ID: <?php echo esc_html($course->variation_id); ?>
                                    </small>
                                    <small style="color: #666; display: flex; align-items: center;">
                                        <span style="margin-right: 6px;"><?php echo $status_icon; ?></span>
                                        <?php echo esc_html($status_text); ?>
                                    </small>
                                </span>
                            </label>
                            
                        <?php endforeach; ?>
                        <?php if ($current_pool !== '') echo '</div>'; ?>
                        
                        <?php if (empty($schedule_courses)): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <p><strong>Nu s-au găsit variații Pool disponibile.</strong></p>
                                <p>Verifică că există produse Pool cu variații publicate.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <p class="submit">
                        <input type="submit" name="save_mapping" class="button-primary" value="<?php _e('Salvează Mapările', OC_TEXT_DOMAIN); ?>">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Randează secțiunea cu mapările existente
     */
    private function render_existing_mappings_section($pool_products): void {
        // Procesează editarea unei mapări
        if (isset($_POST['edit_mapping']) && wp_verify_nonce($_POST['edit_mapping_nonce'], 'edit_course_mapping')) {
            $this->handle_edit_mapping();
        }

        // Procesează ștergerea unei mapări
        if (isset($_POST['delete_mapping']) && wp_verify_nonce($_POST['delete_mapping_nonce'], 'delete_course_mapping')) {
            $this->handle_delete_mapping();
        }

        // Obține toate mapările existente
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return;
        }

        $db = $validator->get_db();
        $all_mappings = $this->get_all_existing_mappings($db);

        if (empty($all_mappings)) {
            echo '<div class="notice notice-info"><p><strong>Nu există mapări configurate încă.</strong> Folosește formularul de mai jos pentru a crea prima mapare.</p></div>';
            return;
        }

        ?>
        <div class="card" style="max-width: none; margin-bottom: 20px;">
            <h2>📋 <?php _e('Mapări Existente', OC_TEXT_DOMAIN); ?></h2>
            <p><?php _e('Aici sunt afișate toate mapările configure pentru abonamente.', OC_TEXT_DOMAIN); ?></p>
            
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 200px;"><strong>Abonament</strong></th>
                            <th><strong>Cursuri Mapate</strong></th>
                            <th style="width: 100px; text-align: center;"><strong>Total</strong></th>
                            <th style="width: 180px; text-align: center;"><strong>Acțiuni</strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_mappings as $membership_id => $mappings): ?>
                            <?php 
                            $membership_product = wc_get_product($membership_id);
                            $membership_name = $membership_product ? $membership_product->get_name() : "Produs #$membership_id";
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($membership_name); ?></strong><br>
                                    <small style="color: #666;">ID: <?php echo $membership_id; ?></small>
                                </td>
                                <td>
                                    <div style="max-height: 120px; overflow-y: auto;">
                                        <?php 
                                        $course_count = 0;
                                        $current_pool = '';
                                        foreach ($mappings as $mapping): 
                                            $course_count++;
                                            
                                            // Obține informații despre pool
                                            if ($mapping['pool_id'] && $mapping['pool_id'] != $current_pool) {
                                                $current_pool = $mapping['pool_id'];
                                                $pool_product = wc_get_product($current_pool);
                                                $pool_name = $pool_product ? $pool_product->get_name() : "Pool #$current_pool";
                                                
                                                if ($course_count > 1) echo '<br>';
                                                echo '<strong style="color: #0073aa; font-size: 12px;">🎯 ' . esc_html($pool_name) . ':</strong><br>';
                                            }
                                            
                                            echo '<span style="display: inline-block; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 3px; padding: 2px 6px; margin: 1px 2px; font-size: 11px;">';
                                            echo esc_html($mapping['variation_name'] ?: "Variație #{$mapping['variation_id']}");
                                            
                                            // Afișează informații despre orar dacă există
                                            if ($mapping['weekday'] && $mapping['start_time']) {
                                                $weekdays = ['', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă', 'Duminică'];
                                                $weekday_name = $weekdays[$mapping['weekday']] ?? '';
                                                echo ' - ' . $weekday_name . ' ' . substr($mapping['start_time'], 0, 5);
                                                if ($mapping['room_number']) {
                                                    echo ' (Sala ' . $mapping['room_number'] . ')';
                                                }
                                            }
                                            echo '</span>';
                                        endforeach; 
                                        ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge" style="background: #0073aa; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px;">
                                        <?php echo count($mappings); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                        <!-- Buton Edit -->
                                        <button type="button" 
                                                class="button button-small" 
                                                onclick="editMapping(<?php echo $membership_id; ?>)"
                                                title="Editează maparea"
                                                style="display: flex; align-items: center; gap: 4px; min-width: 80px;">
                                            <span class="dashicons dashicons-edit" style="font-size: 14px; line-height: 1;"></span>
                                            <span>Edit</span>
                                        </button>
                                        
                                        <!-- Buton Delete -->
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Sigur vrei să ștergi această mapare? Această acțiune nu poate fi anulată.');">
                                            <?php wp_nonce_field('delete_course_mapping', 'delete_mapping_nonce'); ?>
                                            <input type="hidden" name="delete_membership_id" value="<?php echo $membership_id; ?>">
                                            <button type="submit" name="delete_mapping" 
                                                    class="button button-small" 
                                                    style="color: #d63638; display: flex; align-items: center; gap: 4px; min-width: 80px;"
                                                    title="Șterge maparea">
                                                <span class="dashicons dashicons-trash" style="font-size: 14px; line-height: 1;"></span>
                                                <span>Șterge</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- JavaScript pentru funcționalitatea Edit -->
        <script type="text/javascript">
        function editMapping(membershipId) {
            // Găsește dropdown-ul pentru membership și selectează produsul
            var dropdown = document.getElementById('membership_product_id');
            if (dropdown) {
                dropdown.value = membershipId;
                
                // Trigger change event pentru a încărca mapările existente
                var event = new Event('change');
                dropdown.dispatchEvent(event);
                
                // Scroll către secțiunea de editare
                document.querySelector('.card h2').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Highlight secțiunea pentru 2 secunde
                var editSection = dropdown.closest('.card');
                editSection.style.background = '#fff3cd';
                editSection.style.border = '2px solid #ffc107';
                
                setTimeout(function() {
                    editSection.style.background = '';
                    editSection.style.border = '';
                }, 2000);
            }
        }

        // Adaugă event listener pentru încărcarea mapărilor când se schimbă dropdown-ul
        document.addEventListener('DOMContentLoaded', function() {
            var dropdown = document.getElementById('membership_product_id');
            if (dropdown) {
                dropdown.addEventListener('change', function() {
                    var membershipId = this.value;
                    if (membershipId) {
                        // Reîncarcă pagina cu parametrul product_id pentru a pre-bifa checkboxurile
                        var currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('product_id', membershipId);
                        window.location.href = currentUrl.toString();
                    } else {
                        // Dacă nu e selectat nimic, elimină parametrul din URL
                        var currentUrl = new URL(window.location);
                        currentUrl.searchParams.delete('product_id');
                        window.location.href = currentUrl.toString();
                    }
                });
            }
            
            // Afișează un mesaj de încărcare pentru user experience mai bun
            var form = document.querySelector('form[method="post"]');
            if (form) {
                form.addEventListener('submit', function() {
                    var submitButton = form.querySelector('input[type="submit"]');
                    if (submitButton) {
                        submitButton.value = 'Se salvează...';
                        submitButton.disabled = true;
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Obține toate mapările existente grupate pe abonament
     */
    private function get_all_existing_mappings($db): array {
        global $wpdb;
        $table_name = $db->get_table_name('membership_course_mapping');

        $all_mappings_data = $wpdb->get_results(
            "SELECT mcm.membership_product_id, mcm.variation_id,
                    v.post_title as variation_name,
                    oc.weekday, oc.start_time, oc.end_time, oc.room_number,
                    oc.product_id as pool_id
             FROM {$table_name} mcm
             LEFT JOIN {$wpdb->posts} v ON mcm.variation_id = v.ID
             LEFT JOIN {$wpdb->prefix}orar_cursuri oc ON mcm.variation_id = oc.variation_id
             WHERE mcm.is_valid = 1
             ORDER BY mcm.membership_product_id, oc.product_id, v.post_title",
            ARRAY_A
        );

        // Grupează pe membership_product_id
        $grouped_mappings = [];
        foreach ($all_mappings_data as $mapping) {
            $grouped_mappings[$mapping['membership_product_id']][] = $mapping;
        }

        return $grouped_mappings;
    }

    /**
     * Procesează editarea unei mapări
     */
    private function handle_edit_mapping(): void {
        // Funcționalitatea de editare este gestionată prin reload cu product_id
        // în URL și pre-bifarea checkboxurilor în formularul principal
    }

    /**
     * Procesează ștergerea unei mapări
     */
    private function handle_delete_mapping(): void {
        $membership_id = absint($_POST['delete_membership_id'] ?? 0);
        
        if ($membership_id > 0) {
            $validator = OC_Membership_Validator::get_instance();
            if ($validator && $validator->get_db()) {
                $db = $validator->get_db();
                
                global $wpdb;
                $table_name = $db->get_table_name('membership_course_mapping');
                
                $deleted = $wpdb->delete(
                    $table_name,
                    ['membership_product_id' => $membership_id],
                    ['%d']
                );
                
                if ($deleted !== false) {
                    echo '<div class="notice notice-success"><p>';
                    echo sprintf(__('Maparea pentru abonamentul ID %d a fost ștearsă cu succes!', OC_TEXT_DOMAIN), $membership_id);
                    echo '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>';
                    echo __('Eroare la ștergerea mapării!', OC_TEXT_DOMAIN);
                    echo '</p></div>';
                }
            }
        }
    }
}

// Clasa este încărcată prin OC_Addon_Manager
// NU se auto-inițializează aici pentru a evita conflictele
