<?php
/**
 * Shortcodes pentru Frontend - REFACTORED VERSION
 * 
 * CONFORMITATE .cursorrules:
 * - Shortcode-uri responsive și mobile-friendly
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - Best practices 2025: Accessibility, performance, SEO
 * - REFACTORED: Folosește module separate pentru fiecare funcționalitate
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include toate trait-urile necesare pentru modulele refactorizate
require_once plugin_dir_path(__FILE__) . 'traits/trait-oc-membership-pricing.php';
require_once plugin_dir_path(__FILE__) . 'traits/trait-oc-membership-courses.php';
require_once plugin_dir_path(__FILE__) . 'traits/trait-oc-membership-woocommerce.php';

// Include toate modulele refactorizate
require_once plugin_dir_path(__FILE__) . 'class-oc-membership-data-handler.php';
require_once plugin_dir_path(__FILE__) . 'class-oc-membership-admin-table.php';
require_once plugin_dir_path(__FILE__) . 'class-oc-membership-cards-renderer.php';
require_once plugin_dir_path(__FILE__) . 'class-oc-membership-dropdown-builder.php';

/**
 * Class OC_Membership_Shortcodes_Refactored
 * 
 * Gestionează toate shortcode-urile pentru frontend
 * REFACTORED: Coordonează între modulele specializate
 */
class OC_Membership_Shortcodes_Refactored {
    
    /**
     * @var OC_Membership_DB Database handler din ADD-ON #1
     */
    private OC_Membership_DB $validator_db;
    
    /**
     * @var OC_Membership_Data_Handler Handler pentru procesare date
     */
    private OC_Membership_Data_Handler $data_handler;
    
    /**
     * @var OC_Membership_Admin_Table Handler pentru tabelul admin
     */
    private OC_Membership_Admin_Table $admin_table;
    
    /**
     * @var OC_Membership_Cards_Renderer Handler pentru rendering cards
     */
    private OC_Membership_Cards_Renderer $cards_renderer;
    
    /**
     * @var OC_Membership_Dropdown_Builder Handler pentru dropdown-uri
     */
    private OC_Membership_Dropdown_Builder $dropdown_builder;
    
    /**
     * Constructor cu dependency injection
     */
    public function __construct(OC_Membership_DB $validator_db) {
        $this->validator_db = $validator_db;
        
        // Inițializează modulele în ordinea dependențelor
        $this->data_handler = new OC_Membership_Data_Handler($validator_db);
        $this->dropdown_builder = new OC_Membership_Dropdown_Builder($validator_db);
        $this->cards_renderer = new OC_Membership_Cards_Renderer($validator_db, $this->data_handler);
        $this->admin_table = new OC_Membership_Admin_Table($validator_db, $this->data_handler);
    }
    
    /**
     * Get admin table instance (pentru AJAX hooks)
     * 
     * @return OC_Membership_Admin_Table|null
     */
    public function get_admin_table(): ?OC_Membership_Admin_Table {
        return $this->admin_table ?? null;
    }
    
    /**
     * Înregistrează shortcode-urile
     */
    public function register_all(): void {
        add_shortcode('membership_page', [$this, 'membership_page_shortcode']);
        add_shortcode('membership_table', [$this, 'membership_table_shortcode']);
        
        // Hook pentru enqueue assets când shortcode-ul este folosit
        add_action('wp_footer', [$this, 'maybe_enqueue_assets']);
    }
    
    /**
     * 🎯 SHORTCODE INTELIGENT: [membership_page]
     * 
     * Detectează AUTOMAT rolul utilizatorului și afișează conținutul potrivit:
     * 
     * 👤 UTILIZATORI NORMALI:
     * - Propriile membership-uri active cu QR codes
     * - Progress tracking și expiry dates  
     * - Layout compact și user-friendly
     * 
     * 👨‍💼 ADMINISTRATORI:
     * - AFIȘARE AUTOMATĂ: Tabel admin centralizat editable
     * - TOATE membership-urile din sistem (inclusiv guest users)
     * - Tools complete de management și editare inline
     */
    public function membership_page_shortcode(array $atts): string {
        // Enqueue jQuery explicit pentru funcțiile JavaScript inline (showQRCode, etc.)
        wp_enqueue_script('jquery');
        
        // Enqueue CSS extracted IMEDIAT când shortcode-ul este apelat (nu în wp_footer)
        if (defined('OC_PLUGIN_URL') && defined('OC_PLUGIN_DIR')) {
            // CSS-uri necesare
            wp_enqueue_style(
                'oc-membership-manager-extracted-frontend',
                OC_PLUGIN_URL . 'assets/membership-manager-extracted.css',
                [],
                filemtime(OC_PLUGIN_DIR . 'assets/membership-manager-extracted.css')
            );
            
            wp_enqueue_style(
                'oc-admin-table-editing-css-frontend',
                OC_PLUGIN_URL . 'assets/admin-table-editing.css',
                [],
                filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.css')
            );
            
            // CSS responsive pentru mobile/tablet
            wp_enqueue_style(
                'oc-membership-responsive-fixes-frontend',
                OC_PLUGIN_URL . 'assets/membership-responsive-fixes.css',
                ['oc-membership-manager-extracted-frontend'],
                filemtime(OC_PLUGIN_DIR . 'assets/membership-responsive-fixes.css')
            );
            
            // 🚨 EMERGENCY FIX: CSS agresiv pentru mobile cu !important peste inline styles
            wp_enqueue_style(
                'oc-membership-mobile-emergency-fix',
                OC_PLUGIN_URL . 'assets/membership-mobile-emergency-fix.css',
                ['oc-membership-responsive-fixes-frontend'], // Încarcă DUPĂ celelalte
                filemtime(OC_PLUGIN_DIR . 'assets/membership-mobile-emergency-fix.css')
            );
            
            // 🔥 FIX CRITIC: Enqueue JavaScript pentru funcționalitate butoane
            // Folosim ACELAȘI handle ca în admin - WordPress gestionează automat duplicatele
            wp_enqueue_script(
                'oc-admin-table-editing',
                OC_PLUGIN_URL . 'assets/admin-table-editing.js',
                ['jquery'],
                filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.js'),
                true // Load în footer
            );
            
            wp_localize_script('oc-admin-table-editing', 'ocAdminData', $this->admin_table->get_frontend_script_localization_data());
        }
        
        // Protecție contra multiple execuții pe aceeași pagină
        static $execution_count = 0;
        $execution_count++;
        
        // Parse parametri FĂRĂ defaults pentru a detecta ce este specificat explicit
        $original_atts = $atts; // Păstrează atributele originale
        $atts = shortcode_atts([
            'mode' => null  // null = nu este specificat
        ], $atts, 'membership_page');
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            // ✅ REDIRECT AUTOMAT la /my-account/ DOAR când se accesează pagina cu [membership_page]
            // Verifică că nu suntem deja pe /my-account/ (evită loop infinit)
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/my-account/') === false) {
                // Obține URL-ul curent pentru redirect după login
                $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $request_uri;
                
                // Construiește URL pentru /my-account/ cu parametru redirect_to
                $my_account_url = wc_get_page_permalink('myaccount');
                if (!$my_account_url) {
                    $my_account_url = home_url('/my-account/');
                }
                
                // Adaugă parametru pentru redirect după login
                $redirect_url = add_query_arg('redirect_to', urlencode($current_url), $my_account_url);
                
                // Redirect automat
                wp_safe_redirect($redirect_url);
                exit;
            }
            
            // Dacă suntem deja pe /my-account/, afișează mesajul
            return $this->render_login_message();
        }
        
        // 🔒 VERIFICARE PERMISIUNI REALĂ
        $is_admin = current_user_can('administrator') || 
                    current_user_can('manage_woocommerce') || 
                    current_user_can('manage_options');
        
        // 📋 AFIȘARE PE BAZĂ DE ROL
        if ($is_admin) {
            // ADMIN: Tabel complet cu TOATE datele și funcționalități
            $output = $this->admin_table->render_admin_table($current_user_id, true);
        } else {
            // USER NORMAL: Tabel filtrat doar cu PROPRIILE date, fără butoane admin
            $output = $this->admin_table->render_admin_table($current_user_id, false);
        }
        
        // ✅ ADĂUGARE: Link către My Account pentru utilizatorii logați
        $my_account_url = wc_get_page_permalink('myaccount');
        if ($my_account_url) {
            $output .= '<div class="oc-membership-account-link" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px; text-align: center;">
                            <p style="margin: 0 0 10px 0;">' . esc_html__('Vrei să accesezi contul tău?', OC_TEXT_DOMAIN) . '</p>
                            <a href="' . esc_url($my_account_url) . '" class="button" style="display: inline-block;">
                                ' . esc_html__('Accesează My Account', OC_TEXT_DOMAIN) . '
                            </a>
                        </div>';
        }
        
        return $output;
    }
    
    /**
     * 📋 SHORTCODE DEDICAT: [membership_table]
     * 
     * Shortcode dedicat pentru tabelul admin centralizat cu control de acces.
     */
    public function membership_table_shortcode(array $atts): string {
        // Protecție contra multiple execuții
        static $table_execution_count = 0;
        $table_execution_count++;
        
        // Parse parametri (pentru flexibilitate viitoare)
        $atts = shortcode_atts([
            'search' => '', // pre-populate search
            'per_page' => 20 // items per page
        ], $atts, 'membership_table');
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return $this->render_login_message();
        }
        
        // 🔒 VERIFICARE ACCES: Doar administratori
        $is_admin = current_user_can('administrator') || 
                   current_user_can('manage_woocommerce') || 
                   current_user_can('manage_options');
        
        if (!$is_admin) {
            return '<div class="notice notice-error"><p>' . 
                   __('Accesul la tabelul admin este permis doar administratorilor.', OC_TEXT_DOMAIN) . 
                   '</p></div>';
        }
        
        // Renderează tabelul admin centralizat - DELEGAT LA MODULUL SPECIALIZAT
        return $this->admin_table->render_admin_table();
    }
    
    /**
     * Render cards view - DELEGAT LA CARDS RENDERER
     */
    private function render_cards_view(int $current_user_id, bool $is_admin): string {
        return $this->cards_renderer->render_cards_view($current_user_id, $is_admin);
    }
    
    /**
     * Helper: Render mesaj login (folosit doar când suntem deja pe /my-account/)
     */
    private function render_login_message(): string {
        // Obține URL-ul paginii curente pentru redirect după login (dacă nu suntem pe /my-account/)
        $current_url = '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($request_uri, '/my-account/') === false) {
            $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $request_uri;
        }
        
        // Construiește URL pentru /my-account/
        $my_account_url = wc_get_page_permalink('myaccount');
        if (!$my_account_url) {
            $my_account_url = home_url('/my-account/');
        }
        
        // Adaugă parametru pentru redirect după login dacă există URL curent
        if (!empty($current_url)) {
            $my_account_url = add_query_arg('redirect_to', urlencode($current_url), $my_account_url);
        }
        
        return '<div class="oc-login-message">
                    <p>' . esc_html__('Please log in to view your memberships.', OC_TEXT_DOMAIN) . '</p>
                    ' . (strpos($request_uri, '/my-account/') !== false ? 
                        '<p>' . esc_html__('You are already on the login page. Please log in above.', OC_TEXT_DOMAIN) . '</p>' :
                        '<a href="' . esc_url($my_account_url) . '" class="oc-login-link button">' . 
                        esc_html__('Login', OC_TEXT_DOMAIN) . '</a>'
                    ) . '
                </div>';
    }
    
    /**
     * Marchează că un shortcode a fost folosit (pentru assets)
     */
    private function mark_shortcode_used(string $shortcode): void {
        if (!isset($GLOBALS['oc_shortcodes_used'])) {
            $GLOBALS['oc_shortcodes_used'] = [];
        }
        
        $GLOBALS['oc_shortcodes_used'][] = $shortcode;
    }
    
    /**
     * Enqueue assets doar dacă shortcode-urile sunt folosite
     */
    public function maybe_enqueue_assets(): void {
        if (empty($GLOBALS['oc_shortcodes_used'])) {
            return;
        }
        
        // CSS pentru shortcode-uri (CSS-ul extracted este deja enqueue-uit în shortcode direct)
        wp_add_inline_style('wp-block-library', $this->get_shortcode_styles());
        
        // JavaScript pentru shortcode-uri
        wp_add_inline_script('jquery', $this->get_shortcode_scripts());
    }
    
    /**
     * CSS pentru shortcode optimizat - un singur shortcode
     */
    private function get_shortcode_styles(): string {
        return '
        .oc-membership-page { margin: 20px 0; }
        .oc-login-message { text-align: center; padding: 40px 20px; }
        .oc-login-link { 
            display: inline-block; 
            margin-top: 15px; 
            padding: 10px 20px; 
            background: #0073aa; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
        }
        .oc-login-link:hover { background: #005a87; }';
    }
    
    /**
     * JavaScript pentru shortcode-uri - funcționalitate minimă
     */
    private function get_shortcode_scripts(): string {
        return '
        document.addEventListener("DOMContentLoaded", function() {
            // Shortcodes loaded
        });';
    }
}
