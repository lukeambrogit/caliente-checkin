<?php
/**
 * Admin interface class - Core functionality only
 * 
 * Handles core WordPress admin functionality.
 * Schedule Manager functionality moved to Schedule Manager ADD-ON.
 * 
 * @package MembershipValidatorCore
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Admin management class
 * Schedule Manager functionality moved to modular ADD-ON structure
 */
class OC_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only core admin functionality remains here
        // Schedule Manager AJAX hooks and menus moved to Schedule Manager ADD-ON
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
    }
    
    /**
     * Add admin menu - Core admin menus only
     * Schedule Manager menus are now handled by Schedule Manager ADD-ON
     */
    public function add_admin_menu() {
        // Main menu page is registered by OC_Dashboard (priority 5). This duplicate registration
        // was removed to avoid a secondary callback overriding the dashboard page on certain hooks.
        
        // ADD-ONs will register their own submenus automatically
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Core admin initialization only
        // Schedule Manager settings moved to Schedule Manager ADD-ON
        
        // Register core settings here if needed
        $this->register_core_settings();
        
        // Enqueue admin assets (method exists already at line 144)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Register core settings (not Schedule Manager specific)
     */
    private function register_core_settings() {
        // Core plugin settings only
        // Schedule Manager settings moved to Schedule Manager ADD-ON
        
        $settings = [
            'oc_attribute_taxonomy',
            'oc_show_empty_days',
            'oc_primary_color',
            'oc_text_color',
            'oc_secondary_color',
            'oc_background_color',
            'oc_muted_color',
            'oc_border_color',
            'oc_border_radius',
            'oc_font_family',
            'oc_font_size',
            'oc_header_font_size',
            'oc_selected_product',
            'oc_desktop_bg_image',
            'oc_mobile_bg_image',
            'oc_gradient_enabled',
            'oc_gradient_start',
            'oc_gradient_end',
            'oc_gradient_direction',
            'oc_custom_css'
        ];
        
        foreach ($settings as $setting) {
            register_setting('oc_general_settings', $setting);
        }
        
        // Ensure a product is selected for frontend display
        $this->ensure_product_selected();
    }
    
    /**
     * Ensure a product is selected for frontend display
     */
    private function ensure_product_selected() {
        static $called = false;
        if ($called) {
            return;
        }
        $called = true;

        $selected_product = get_option('oc_selected_product', 0);
        
        if (empty($selected_product)) {
            // Get available variable products
            $woocommerce = new OC_WooCommerce();
            $variable_products = $woocommerce->get_variable_products();
            
            // Auto-select first available product if none selected but products exist
            if (!empty($variable_products)) {
                $first_product_id = array_key_first($variable_products);
                update_option('oc_selected_product', $first_product_id);
            }
        }
    }
    
    /**
     * Enqueue core admin assets (RESTORED: from functional commit)
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        $plugin_pages = [
            'toplevel_page_orar-cursuri',
            'orar-cursuri_page_orar-cursuri-style',
            'toplevel_page_membership-validator-dashboard',
            'membership-validator_page_membership-validator-core-settings',
            'membership-validator_page_orar-cursuri',
            'membership-validator_page_orar-cursuri-style',
            'membership-validator_page_orar-cursuri-debug',
        ];
        if (!in_array($hook, $plugin_pages, true)) {
            return;
        }

        // Admin styles
        wp_enqueue_style(
            'oc-admin-style',
            OC_PLUGIN_URL . 'assets/admin.css',
            [],
            OC_PLUGIN_VERSION
        );
        
        // Admin scripts  
        wp_enqueue_script(
            'oc-admin-script',
            OC_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            OC_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Get weekday options for localization
     * This function was kept for compatibility with OC_Assets
     * 
     * @return array Weekday options
     */
    public static function get_weekday_options() {
        return [
            1 => __('Luni', OC_TEXT_DOMAIN),
            2 => __('Marți', OC_TEXT_DOMAIN),
            3 => __('Miercuri', OC_TEXT_DOMAIN),
            4 => __('Joi', OC_TEXT_DOMAIN),
            5 => __('Vineri', OC_TEXT_DOMAIN),
            6 => __('Sâmbătă', OC_TEXT_DOMAIN),
            0 => __('Duminică', OC_TEXT_DOMAIN)
        ];
    }
    
    /**
     * Get font family options for settings (QUICK FIX)
     * 
     * @return array
     */
    public static function get_font_family_options() {
        return [
            'Segoe UI, Roboto, Arial, sans-serif' => 'Segoe UI (Default)',
            'Arial, sans-serif' => 'Arial',
            'Helvetica, Arial, sans-serif' => 'Helvetica',
            'Georgia, serif' => 'Georgia',
            'Times New Roman, serif' => 'Times New Roman',
            'Courier New, monospace' => 'Courier New'
        ];
    }
}
