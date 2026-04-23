<?php
/**
 * Assets management class
 * 
 * Handles CSS and JavaScript assets loading
 * and optimization for both admin and frontend.
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets management class
 */
class OC_Assets {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with shortcode or always if needed
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // Main frontend stylesheet
        wp_enqueue_style(
            'oc-frontend',
            $this->get_asset_url('frontend.css'),
            [],
            $this->get_asset_version('frontend.css')
        );
        
        // RTL support
        wp_style_add_data('oc-frontend', 'rtl', 'replace');
        
        // Dynamic styles
        $this->add_dynamic_styles();
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }
        
        // Admin stylesheet
        wp_enqueue_style(
            'oc-admin',
            $this->get_asset_url('admin.css'),
            ['wp-color-picker'],
            $this->get_asset_version('admin.css')
        );
        
        // Frontend stylesheet for preview
        wp_enqueue_style(
            'oc-frontend-preview',
            $this->get_asset_url('frontend.css'),
            [],
            $this->get_asset_version('frontend.css')
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'oc-admin',
            $this->get_asset_url('admin.js'),
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable'],
            $this->get_asset_version('admin.js'),
            true
        );
        
        // Media uploader for style settings
        if ($hook === 'orar-cursuri_page_orar-cursuri-style') {
            wp_enqueue_media();
        }
        
        // Localize admin script
        $this->localize_admin_script();
        
        // RTL support
        wp_style_add_data('oc-admin', 'rtl', 'replace');
    }
    
    /**
     * Check if frontend assets should be loaded
     * 
     * @return bool
     */
    private function should_load_frontend_assets() {
        global $post;
        
        // Always load if we're on a page/post with the shortcode
        if ($post && has_shortcode($post->post_content, 'orar_cursuri')) {
            return true;
        }
        
        // Load on archive pages that might use the shortcode
        if (is_archive() || is_home() || is_front_page()) {
            return apply_filters('oc_load_assets_on_archives', false);
        }
        
        // Allow other plugins to force loading
        return apply_filters('oc_force_load_frontend_assets', false);
    }
    
    /**
     * Check if current admin page is a plugin page
     * 
     * @param string $hook
     * @return bool
     */
    private function is_plugin_admin_page($hook) {
        $plugin_pages = [
            'toplevel_page_orar-cursuri',
            'orar-cursuri_page_orar-cursuri-style',
            'toplevel_page_membership-validator-dashboard',
            'membership-validator_page_membership-validator-core-settings',
            'membership-validator_page_orar-cursuri',
            'membership-validator_page_orar-cursuri-style',
            'membership-validator_page_orar-cursuri-debug'
        ];
        
        return in_array($hook, $plugin_pages);
    }
    
    /**
     * Get asset URL
     * 
     * @param string $file
     * @return string
     */
    private function get_asset_url($file) {
        return OC_PLUGIN_URL . 'assets/' . $file;
    }
    
    /**
     * Get asset version for cache busting
     * 
     * @param string $file
     * @return string
     */
    private function get_asset_version($file) {
        $file_path = OC_PLUGIN_DIR . 'assets/' . $file;
        
        if (file_exists($file_path)) {
            return filemtime($file_path);
        }
        
        return OC_PLUGIN_VERSION;
    }
    
    /**
     * Add dynamic CSS styles based on settings
     */
    private function add_dynamic_styles() {
        $settings = $this->get_style_settings();
        
        $css = $this->generate_css_variables($settings);
        $css .= $this->generate_background_styles($settings);
        $css .= $this->generate_responsive_styles($settings);
        
        wp_add_inline_style('oc-frontend', $css);
    }
    
    /**
     * Get style settings from database
     * 
     * @return array
     */
    private function get_style_settings() {
        return [
            'primary_color' => get_option('oc_primary_color', '#d48945'),
            'text_color' => get_option('oc_text_color', '#5f4a40'),
            'secondary_color' => get_option('oc_secondary_color', '#8d786b'),
            'background_color' => get_option('oc_background_color', '#f7eee8'),
            'muted_color' => get_option('oc_muted_color', '#f5ece5'),
            'border_color' => get_option('oc_border_color', '#e3d5c9'),
            'font_family' => get_option('oc_font_family', 'Segoe UI, Roboto, Arial, sans-serif'),
            'font_size' => get_option('oc_font_size', '15px'),
            'header_font_size' => get_option('oc_header_font_size', '30px'),
            'border_radius' => get_option('oc_border_radius', '16px'),
            'desktop_bg_image' => get_option('oc_desktop_bg_image', ''),
            'mobile_bg_image' => get_option('oc_mobile_bg_image', '')
        ];
    }
    
    /**
     * Generate CSS variables
     * 
     * @param array $settings
     * @return string
     */
    private function generate_css_variables($settings) {
        return ":root {
            --oc-primary: {$settings['primary_color']};
            --oc-text: {$settings['text_color']};
            --oc-secondary: {$settings['secondary_color']};
            --oc-background: {$settings['background_color']};
            --oc-muted: {$settings['muted_color']};
            --oc-border: {$settings['border_color']};
            --oc-font-family: {$settings['font_family']};
            --oc-font-size: {$settings['font_size']};
            --oc-header-font-size: {$settings['header_font_size']};
            --oc-border-radius: {$settings['border_radius']};
        }";
    }
    
    /**
     * Generate background image styles
     * 
     * @param array $settings
     * @return string
     */
    private function generate_background_styles($settings) {
        $css = '';
        
        // Desktop background
        if (!empty($settings['desktop_bg_image'])) {
            $css .= "
            .oc-schedule-table {
                background-image: url('" . esc_url($settings['desktop_bg_image']) . "');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
            }";
        }
        
        // Mobile background
        if (!empty($settings['mobile_bg_image'])) {
            $css .= "
            @media (max-width: 899px) {
                .oc-schedule-card {
                    background-image: url('" . esc_url($settings['mobile_bg_image']) . "');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                }
            }";
        }
        
        return $css;
    }
    
    /**
     * Generate responsive styles
     * 
     * @param array $settings
     * @return string
     */
    private function generate_responsive_styles($settings) {
        $mobile_font_size = $this->calculate_mobile_font_size($settings['font_size']);
        $mobile_header_size = $this->calculate_mobile_font_size($settings['header_font_size']);
        
        return "
        @media (max-width: 600px) {
            .oc-schedule-wrapper {
                font-size: {$mobile_font_size};
            }
            .oc-schedule-title {
                font-size: {$mobile_header_size};
            }
        }";
    }
    
    /**
     * Calculate mobile font size (slightly smaller)
     * 
     * @param string $desktop_size
     * @return string
     */
    private function calculate_mobile_font_size($desktop_size) {
        $size_value = floatval($desktop_size);
        $size_unit = preg_replace('/[0-9.]/', '', $desktop_size);
        
        $mobile_size = $size_value * 0.9; // 10% smaller on mobile
        
        return $mobile_size . $size_unit;
    }
    
    /**
     * Localize admin script with data and translations
     */
    private function localize_admin_script() {
        $localization_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Ești sigur că vrei să ștergi acest rând?', OC_TEXT_DOMAIN),
                'saveSuccess' => __('Orarul a fost salvat cu succes!', OC_TEXT_DOMAIN),
                'saveError' => __('Eroare la salvarea orarului. Te rugăm să încerci din nou.', OC_TEXT_DOMAIN),
                'overlapError' => __('Există o suprapunere în orar pentru acest curs și interval orar.', OC_TEXT_DOMAIN),
                'requiredFields' => __('Te rugăm să completezi toate câmpurile obligatorii.', OC_TEXT_DOMAIN),
                'timeError' => __('Ora de start trebuie să fie înainte de ora de sfârșit.', OC_TEXT_DOMAIN),
                'selectAttribute' => __('Te rugăm să selectezi un atribut părinte.', OC_TEXT_DOMAIN),
                'loading' => __('Se încarcă...', OC_TEXT_DOMAIN),
                'uploadImage' => __('Încarcă imagine', OC_TEXT_DOMAIN),
                'selectImage' => __('Selectează imagine', OC_TEXT_DOMAIN),
                'removeImage' => __('Elimină imagine', OC_TEXT_DOMAIN)
            ],
            'weekdays' => OC_Admin::get_weekday_options(),
            'settings' => [
                'dateFormat' => get_option('date_format'),
                'timeFormat' => get_option('time_format'),
                'startOfWeek' => get_option('start_of_week')
            ]
        ];
        
        wp_localize_script('oc-admin', 'ocAdmin', $localization_data);
    }
    
    /**
     * Preload critical assets
     */
    public static function preload_critical_assets() {
        if (!is_admin()) {
            // Preload critical frontend CSS
            echo '<link rel="preload" href="' . esc_url(OC_PLUGIN_URL . 'assets/frontend.css') . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        }
    }
    
    /**
     * Add resource hints for better performance
     */
    public static function add_resource_hints() {
        // DNS prefetch for external resources if any
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">';
    }
    
    /**
     * Optimize CSS delivery
     * 
     * @param string $css
     * @return string
     */
    public static function optimize_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        
        return trim($css);
    }
    
    /**
     * Check if assets are optimized
     * 
     * @return bool
     */
    public static function are_assets_optimized() {
        return apply_filters('oc_assets_optimized', false);
    }
}
