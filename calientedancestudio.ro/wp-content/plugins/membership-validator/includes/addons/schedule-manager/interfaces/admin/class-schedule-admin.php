<?php
/**
 * Schedule Admin Interface
 * 
 * Handles admin interface for Schedule Manager ADD-ON.
 * Extracted from OC_Admin core class to follow modular architecture.
 * 
 * @package MembershipValidatorCore
 * @subpackage ScheduleManager
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schedule Admin Interface Class
 * 
 * Manages admin menus, pages, and asset loading for Schedule Manager.
 */
class OC_Schedule_Admin {
    
    /**
     * Schedule service instance
     * 
     * @var OC_Schedule_Service
     */
    private $service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->service = new OC_Schedule_Service();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Add admin menu items
     * 
     * Înregistrează doar pagina principală - taburile sunt gestionate intern
     */
    public function add_admin_menu() {
        // Add Schedule Manager as single submenu with internal tabs
        add_submenu_page(
            'membership-validator-dashboard',
            __('Schedule Manager', OC_TEXT_DOMAIN),
            __('📅 Schedule Manager', OC_TEXT_DOMAIN),
            'manage_options',
            'orar-cursuri',
            [$this, 'admin_page']
        );
        
        // ELIMINATED: Separate submenus for Appearance and Debug
        // These are now tabs within the main Schedule Manager page
        // Callback methods kept for compatibility and internal tab routing
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Handle appearance form submit before rendering the page.
        $this->handle_style_settings_save();

        // Register settings
        $this->register_settings();
        
        // Ensure a product is selected for frontend display
        $this->ensure_product_selected();
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on Schedule Manager page (all tabs)
        $allowed_hooks = [
            'toplevel_page_orar-cursuri',  // Legacy hook
            'membership-validator_page_orar-cursuri', // New hook for schedule manager with tabs
        ];
        
        if (!in_array($hook, $allowed_hooks)) {
            return;
        }
        
        // Get current tab for conditional asset loading
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'schedule';
        
        // TEMPORARY: Use ORIGINAL assets from /assets/ until modular assets are working
        wp_enqueue_style(
            'oc-admin-style',
            OC_PLUGIN_URL . 'assets/admin.css',
            [],
            OC_PLUGIN_VERSION
        );
        
        // Also enqueue ORIGINAL frontend styles for preview
        wp_enqueue_style(
            'oc-frontend-style',
            OC_PLUGIN_URL . 'assets/frontend.css',
            [],
            OC_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'oc-frontend-simple-style',
            OC_PLUGIN_URL . 'assets/frontend-simple.css',
            ['oc-frontend-style'],
            OC_PLUGIN_VERSION
        );
        
        // Use ORIGINAL admin script
        wp_enqueue_script(
            'oc-admin-script',
            OC_PLUGIN_URL . 'assets/admin.js',
            ['jquery', 'wp-color-picker'],
            OC_PLUGIN_VERSION,
            true
        );
        
        // CRITICAL: Also enqueue frontend JS for mobile functionality in preview
        wp_enqueue_script(
            'oc-frontend-simple-script',
            OC_PLUGIN_URL . 'assets/frontend-simple.js',
            [],
            OC_PLUGIN_VERSION,
            true
        );
        
        // Add color picker
        wp_enqueue_style('wp-color-picker');
        
        // Always enqueue media uploader for admin pages
        wp_enqueue_media();
        
        // CRITICAL: Add dynamic styles like in OC_Frontend for consistent preview
        $this->add_frontend_dynamic_styles();
        
        // Localize script with data
        $this->localize_admin_script();
        
        // Add inline JavaScript for admin preview toggle
        $this->add_admin_preview_script();
    }
    
    /**
     * Add frontend dynamic styles for consistent preview (EXACT copy from OC_Frontend)
     */
    private function add_frontend_dynamic_styles() {
        $primary_color = get_option('oc_primary_color', '#d48945');
        $title_color = get_option('oc_title_color', '#5d473d');
        $text_color = get_option('oc_text_color', '#5f4a40');
        $secondary_color = get_option('oc_secondary_color', '#8d786b');
        $background_color = get_option('oc_background_color', '#f7eee8');
        $muted_color = get_option('oc_muted_color', '#f5ece5');
        $border_color = get_option('oc_border_color', '#e3d5c9');
        $font_family = get_option('oc_font_family', 'Segoe UI, Roboto, Arial, sans-serif');
        $font_size = get_option('oc_font_size', '15px');
        $header_font_size = get_option('oc_header_font_size', '30px');
        $border_radius = get_option('oc_border_radius', '16px');
        $logo_width = get_option('oc_logo_width', '120px');

        $gradient_enabled = get_option('oc_gradient_enabled', '0');
        $background_mode = sanitize_key(get_option('oc_background_mode', 'gradient'));
        if (!in_array($background_mode, ['gradient', 'image'], true)) {
            $background_mode = 'gradient';
        }
        $background_image = esc_url(get_option('oc_background_image', ''));
        $background_image_mobile = esc_url(get_option('oc_background_image_mobile', ''));
        $background_image_opacity = (float) get_option('oc_background_image_opacity', '1');
        if ($background_image_opacity < 0) {
            $background_image_opacity = 0;
        }
        if ($background_image_opacity > 1) {
            $background_image_opacity = 1;
        }
        $background_image_size_desktop = (float) get_option('oc_background_image_size_desktop', '100');
        if ($background_image_size_desktop < 50) {
            $background_image_size_desktop = 50;
        }
        if ($background_image_size_desktop > 200) {
            $background_image_size_desktop = 200;
        }
        $background_image_size_mobile = (float) get_option('oc_background_image_size_mobile', '100');
        if ($background_image_size_mobile < 50) {
            $background_image_size_mobile = 50;
        }
        if ($background_image_size_mobile > 200) {
            $background_image_size_mobile = 200;
        }
        $background_image_mobile_behavior = sanitize_key(get_option('oc_background_image_mobile_behavior', 'cover'));
        if (!in_array($background_image_mobile_behavior, ['cover', 'repeat'], true)) {
            $background_image_mobile_behavior = 'cover';
        }
        $gradient_start = get_option('oc_gradient_start', '#ff7a3d');
        $gradient_end = get_option('oc_gradient_end', '#ffd08a');
        $gradient_direction = get_option('oc_gradient_direction', '132deg');
        
        $custom_css = ":root {
            --oc-primary: {$primary_color} !important;
            --oc-title: {$title_color} !important;
            --oc-text: {$text_color} !important;
            --oc-secondary: {$secondary_color} !important;
            --oc-background: {$background_color} !important;
            --oc-muted: {$muted_color} !important;
            --oc-border: {$border_color} !important;
            --oc-font-family: {$font_family} !important;
            --oc-font-size: {$font_size} !important;
            --oc-header-font-size: {$header_font_size} !important;
            --oc-border-radius: {$border_radius} !important;
        }
        
        /* Logo styling follows saved settings; mobile override keeps it readable. */
        .oc-schedule-logo {
            width: {$logo_width} !important;
            height: auto !important;
            object-fit: contain !important;
            vertical-align: middle !important;
        }
        
        /* Header styling */
        .oc-schedule-header {
            margin: 0 auto 18px !important;
            max-width: 1100px !important;
        }
        
        .oc-title-logo-wrapper {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 15px !important;
        }
        
        .oc-schedule-title {
            margin: 0 !important;
            font-size: var(--oc-header-font-size, 24px) !important;
            color: var(--oc-title, {$title_color}) !important;
            letter-spacing: 0.2px !important;
            font-family: var(--oc-font-family, {$font_family}) !important;
            font-weight: 700 !important;
        }
        
        /* Style Wrap - KEEP FULL WIDTH */
        .oc-style-wrap {
            width: 100% !important;
            max-width: none !important;
        }
        
        /* Schedule Wrapper */
        .oc-schedule-wrapper {
            background: transparent !important;
            max-width: 1100px !important;
            margin: 16px auto 20px auto !important;
            border: none !important;
            border-radius: var(--oc-border-radius, {$border_radius}) !important;
            overflow: visible !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03) !important;
        }
        
        /* Mobile Cards Styling */
        .oc-schedule-wrapper .cards {
            display: grid !important;
            gap: 6px !important;
            max-width: 800px !important;
            margin: 0 auto !important;
            background: transparent !important;
        }
        
        .oc-schedule-wrapper .card {
            border: 1px solid var(--oc-border, {$border_color}) !important;
            border-radius: var(--oc-border-radius, {$border_radius}) !important;
            background: transparent !important;
            padding: 12px !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04) !important;
        }
        
        .oc-schedule-wrapper .card .top {
            display: flex !important;
            justify-content: space-between !important;
            gap: 8px !important;
            margin-bottom: 8px !important;
            background: transparent !important;
        }
        
        .oc-schedule-wrapper .badge {
            font-weight: 700 !important;
            color: var(--oc-primary, {$primary_color}) !important;
            padding: 6px 8px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: all 0.2s ease !important;
        }
        
        .oc-schedule-wrapper .rooms {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 6px !important;
            background: transparent !important;
        }
        
        .oc-schedule-wrapper .room {
            background: #f7f7f7 !important;
            border: 1px solid var(--oc-border, {$border_color}) !important;
            border-radius: 8px !important;
            padding: 8px 10px !important;
            font-size: 15px !important;
            color: var(--oc-text, {$text_color}) !important;
            font-family: var(--oc-font-family, {$font_family}) !important;
        }
        
        .oc-schedule-wrapper .label {
            font-weight: 600 !important;
            margin-right: 6px !important;
        }
        
        /* Desktop Table Styling */
        .oc-schedule-wrapper .table-wrap {
            display: none !important;
            background: transparent !important;
            border: 1px solid var(--oc-border, {$border_color}) !important;
            border-radius: var(--oc-border-radius, {$border_radius}) !important;
            overflow: hidden !important;
        }
        
        .oc-schedule-wrapper .table-wrap table {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: transparent !important;
            border: none !important;
        }
        
        .oc-schedule-wrapper .table-wrap table thead th {
            background: var(--oc-muted, {$muted_color}) !important;
            color: var(--oc-primary, {$primary_color}) !important;
            font-weight: 700 !important;
            text-align: left !important;
            border: none !important;
            border-bottom: 1px solid var(--oc-border, {$border_color}) !important;
            border-right: 1px solid var(--oc-border, {$border_color}) !important;
            padding: 12px 14px !important;
            font-size: var(--oc-font-size, {$font_size}) !important;
            font-family: var(--oc-font-family, {$font_family}) !important;
        }
        
        .oc-schedule-wrapper .table-wrap table tbody td {
            padding: 12px 14px !important;
            border: none !important;
            border-bottom: 1px solid var(--oc-border, {$border_color}) !important;
            border-right: 1px solid var(--oc-border, {$border_color}) !important;
            font-size: var(--oc-font-size, {$font_size}) !important;
            vertical-align: top !important;
            background: transparent !important;
            color: var(--oc-text, {$text_color}) !important;
            font-family: var(--oc-font-family, {$font_family}) !important;
        }
        
        .oc-schedule-wrapper .muted {
            color: var(--oc-secondary, {$secondary_color}) !important;
        }
        
        /* ADMIN PREVIEW: View toggle system */
        .admin-preview-controls {
            text-align: center !important;
            margin-bottom: 20px !important;
        }
        
        .admin-view-toggle {
            display: inline-flex !important;
            background: var(--oc-muted, {$muted_color}) !important;
            border-radius: 6px !important;
            padding: 4px !important;
            gap: 4px !important;
        }
        
        .admin-view-btn {
            padding: 8px 16px !important;
            border: none !important;
            background: transparent !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-weight: 600 !important;
            color: var(--oc-secondary, {$secondary_color}) !important;
            transition: all 0.2s ease !important;
        }
        
        .admin-view-btn.active {
            background: var(--oc-primary, {$primary_color}) !important;
            color: white !important;
        }
        
        .admin-view-btn:hover:not(.active) {
            background: rgba(0,0,0,0.05) !important;
        }
        
        /* ADMIN PREVIEW: Default show desktop only */
        .admin-preview .oc-schedule-wrapper .table-wrap {
            display: block !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        .admin-preview .oc-schedule-wrapper .table-wrap table {
            min-width: 900px !important;
        }
        
        .admin-preview .oc-schedule-wrapper .cards {
            display: none !important;
        }
        
        /* When mobile view is active */
        .admin-preview.mobile-view .oc-schedule-wrapper .table-wrap {
            display: none !important;
        }
        
        .admin-preview.mobile-view .oc-schedule-wrapper .cards {
            display: block !important;
        }

        .admin-preview.mobile-view .oc-schedule-wrapper {
            width: min(100%, 420px) !important;
            max-width: 420px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        
        /* Desktop breakpoint (normal frontend behavior) */
        @media (min-width: 900px) {
            .oc-schedule-wrapper:not(.admin-preview) .table-wrap {
                display: block !important;
            }
            .oc-schedule-wrapper:not(.admin-preview) .cards {
                display: none !important;
            }
        }";

        // Add gradient background if enabled, but keep text contrast strong.
        if ($background_mode === 'gradient' && $gradient_enabled === '1') {
            $custom_css .= "
            .oc-schedule-wrapper {
                background: linear-gradient({$gradient_direction}, {$gradient_start}, {$gradient_end}) !important;
                background-attachment: scroll !important;
                padding: 20px !important;
                border-radius: var(--oc-border-radius, 12px) !important;
                min-height: auto !important;
            }
            
            .oc-schedule-wrapper .table-wrap,
            .oc-schedule-wrapper .table-wrap table {
                background: rgba(255, 255, 255, 0.94) !important;
                backdrop-filter: blur(1px) saturate(108%) !important;
            }

            .oc-schedule-wrapper .table-wrap table thead th {
                color: var(--oc-primary) !important;
                font-weight: 700 !important;
                background: rgba(255, 247, 238, 0.95) !important;
            }

            .oc-schedule-wrapper .table-wrap table tbody td {
                color: var(--oc-text) !important;
                background: rgba(255, 255, 255, 0.9) !important;
            }

            .oc-schedule-wrapper .cards .card {
                background: rgba(255, 255, 255, 0.92) !important;
                backdrop-filter: blur(2px) saturate(108%) !important;
                border: 1px solid rgba(145, 91, 60, 0.18) !important;
            }";
        } elseif ($background_mode === 'image' && !empty($background_image)) {
            $desktop_image_size = $background_image_size_desktop . '% auto';
            $mobile_image_size = $background_image_mobile_behavior === 'repeat' ? 'auto' : ($background_image_size_mobile . '% auto');
            $mobile_image_repeat = $background_image_mobile_behavior === 'repeat' ? 'repeat' : 'repeat-y';
            $mobile_image_position = 'top center';
            $mobile_image_source = !empty($background_image_mobile) ? $background_image_mobile : $background_image;
            $custom_css .= "
            .oc-schedule-wrapper {
                padding: 20px !important;
                border-radius: var(--oc-border-radius, 12px) !important;
                overflow: hidden !important;
                position: relative !important;
            }

            .oc-schedule-wrapper::before {
                content: '' !important;
                position: absolute !important;
                inset: 0 !important;
                background-image: url('{$background_image}') !important;
                background-position: top center !important;
                background-size: {$desktop_image_size} !important;
                background-repeat: repeat-y !important;
                opacity: {$background_image_opacity} !important;
                pointer-events: none !important;
                z-index: 0 !important;
            }

            @media (max-width: 899px) {
                .oc-schedule-wrapper::before {
                    background-image: url('{$mobile_image_source}') !important;
                    background-size: {$mobile_image_size} !important;
                    background-repeat: {$mobile_image_repeat} !important;
                    background-position: {$mobile_image_position} !important;
                }
            }

            .admin-preview.mobile-view .oc-schedule-wrapper::before {
                background-image: url('{$mobile_image_source}') !important;
                background-size: {$mobile_image_size} !important;
                background-repeat: {$mobile_image_repeat} !important;
                background-position: {$mobile_image_position} !important;
            }

            .oc-schedule-wrapper > * {
                position: relative !important;
                z-index: 1 !important;
            }";
        }
        
        // Visual refactor inspired by the provided schedule mockup.
        $modern_css = "
        .oc-schedule-wrapper {
            position: relative !important;
            max-width: 1140px !important;
            margin: 10px auto 22px auto !important;
            padding: 22px 20px 18px !important;
            border-radius: calc(var(--oc-border-radius, {$border_radius}) + 8px) !important;
            border: 1px solid color-mix(in srgb, var(--oc-border, {$border_color}) 75%, #ffffff) !important;
            background:
                radial-gradient(1200px 520px at 50% 130%, rgba(255,255,255,0.36), rgba(255,255,255,0) 70%),
                radial-gradient(820px 320px at 8% 14%, rgba(255,255,255,0.28), rgba(255,255,255,0) 72%),
                var(--oc-background, {$background_color}) !important;
            box-shadow: 0 22px 48px rgba(93, 61, 43, 0.12), 0 2px 8px rgba(93, 61, 43, 0.08) !important;
        }

        .oc-schedule-header {
            margin: 2px auto 16px !important;
            text-align: center !important;
        }

        .oc-title-logo-wrapper {
            gap: 12px !important;
        }

        .oc-schedule-title {
            letter-spacing: 0.02em !important;
            line-height: 1.1 !important;
            color: #5f4a40 !important;
            text-transform: uppercase !important;
            font-weight: 800 !important;
        }

        .oc-schedule-wrapper .table-wrap {
            border-radius: calc(var(--oc-border-radius, {$border_radius}) + 4px) !important;
            border: 1px solid color-mix(in srgb, var(--oc-border, {$border_color}) 80%, #ffffff) !important;
            background: rgba(255, 250, 246, 0.92) !important;
            overflow: hidden !important;
        }

        .oc-schedule-wrapper .table-wrap table {
            background: transparent !important;
        }

        .oc-schedule-wrapper .table-wrap table thead th {
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
            font-size: clamp(11px, 1.02vw, 15px) !important;
            color: #6e584c !important;
            background: color-mix(in srgb, var(--oc-muted, {$muted_color}) 86%, #ffffff) !important;
            border-color: color-mix(in srgb, var(--oc-border, {$border_color}) 78%, #ffffff) !important;
            padding: 12px 14px !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody td {
            background: rgba(255, 252, 249, 0.98) !important;
            border-color: color-mix(in srgb, var(--oc-border, {$border_color}) 70%, #ffffff) !important;
            color: #4a352b !important;
            font-weight: 700 !important;
            padding: 10px 10px !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody tr:nth-child(even) td {
            background: rgba(248, 236, 225, 0.98) !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody tr:hover td {
            background: rgba(241, 226, 212, 0.98) !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ziua {
            width: 172px !important;
            text-align: left !important;
            vertical-align: top !important;
            font-size: clamp(23px, 1.18vw, 16px) !important;
            color: #4f392f !important;
            letter-spacing: 0.01em !important;
            padding-top: 14px !important;
            background: rgba(255, 249, 244, 0.96) !important;
        }

        .oc-schedule-wrapper .table-wrap .oc-day-cell {
            border-right: 1px solid rgba(116, 63, 34, 0.18) !important;
        }

        .oc-schedule-wrapper .table-wrap .oc-day-pill {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            min-height: 36px !important;
            max-width: 100% !important;
            padding: 7px 16px !important;
            border-radius: 12px !important;
            background: linear-gradient(100deg, color-mix(in srgb, var(--oc-primary, {$primary_color}) 82%, #cc6b35), color-mix(in srgb, var(--oc-primary, {$primary_color}) 62%, #b85a2a)) !important;
            color: #ffffff !important;
            text-shadow: 0 1px 1px rgba(80, 40, 20, 0.35) !important;
            font-size: clamp(12px, 1.05vw, 16px) !important;
            font-weight: 800 !important;
            letter-spacing: 0.03em !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.34), 0 4px 10px rgba(133, 72, 39, 0.25) !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ziua.is-featured-day {
            background: linear-gradient(100deg, color-mix(in srgb, var(--oc-primary, {$primary_color}) 72%, #f8bc83), color-mix(in srgb, var(--oc-primary, {$primary_color}) 48%, #ffd2a2)) !important;
            color: #ffffff !important;
            text-shadow: 0 1px 1px rgba(80, 40, 20, 0.26) !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ora {
            width: 96px !important;
            color: #4f392e !important;
            font-weight: 800 !important;
            font-variant-numeric: tabular-nums !important;
        }

        .oc-schedule-wrapper .oc-empty {
            color: #8f7d72 !important;
            font-weight: 600 !important;
        }

        .oc-schedule-wrapper .cards {
            gap: 10px !important;
            max-width: 860px !important;
        }

        .oc-schedule-wrapper .card {
            background: rgba(255, 250, 246, 0.99) !important;
            border: 1px solid color-mix(in srgb, var(--oc-border, {$border_color}) 76%, #ffffff) !important;
            border-radius: calc(var(--oc-border-radius, {$border_radius}) + 2px) !important;
            box-shadow: 0 8px 18px rgba(91, 63, 44, 0.08) !important;
            padding: 12px !important;
        }

        .oc-schedule-wrapper .card .top {
            margin-bottom: 10px !important;
            gap: 8px !important;
            flex-wrap: wrap !important;
        }

        .oc-schedule-wrapper .badge {
            display: inline-flex !important;
            align-items: center !important;
            min-height: 30px !important;
            padding: 4px 10px !important;
            border-radius: 999px !important;
            border: 1px solid rgba(145, 94, 60, 0.4) !important;
            background: rgba(244, 214, 188, 0.98) !important;
            color: #4d3325 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
        }

        .oc-schedule-wrapper .rooms {
            gap: 7px !important;
        }

        .oc-schedule-wrapper .room {
            background: rgba(248, 233, 221, 0.98) !important;
            border: 1px solid color-mix(in srgb, var(--oc-border, {$border_color}) 76%, #ffffff) !important;
            border-radius: 10px !important;
            padding: 8px 10px !important;
            color: #4b372d !important;
        }

        .oc-schedule-wrapper .label {
            color: #6d5142 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
            font-size: 11px !important;
            font-weight: 700 !important;
        }

        @media (max-width: 899px) {
            .oc-schedule-wrapper {
                padding: 14px 12px !important;
                border-radius: 18px !important;
            }

            .oc-schedule-title {
                font-size: clamp(22px, 6.1vw, 30px) !important;
            }
        }
        ";

        $custom_css .= "\n" . $modern_css;

        // Final reference tuning layer: closer to provided screenshot proportions and tones.
        $reference_css = "
        .oc-schedule-wrapper {
            padding: 20px 20px 16px !important;
            border-radius: 24px !important;
            background: linear-gradient(180deg, #f7eee8 0%, #f3e7de 100%) !important;
            border: 1px solid #e7d8cc !important;
            box-shadow: 0 14px 32px rgba(90, 62, 43, 0.10) !important;
        }

        .oc-schedule-header {
            margin: 0 auto 14px !important;
        }

        .oc-schedule-title {
            color: #5d473d !important;
            font-size: clamp(28px, 2.1vw, 44px) !important;
            font-weight: 800 !important;
            letter-spacing: 0.01em !important;
            text-transform: uppercase !important;
        }

        .oc-schedule-wrapper .table-wrap {
            border-radius: 20px !important;
            border: 1px solid #dfd0c4 !important;
            background: rgba(255, 251, 247, 0.82) !important;
            overflow: hidden !important;
        }

        .oc-schedule-wrapper .table-wrap table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
        }

        .oc-schedule-wrapper .table-wrap table thead th {
            background: rgba(245, 236, 229, 0.98) !important;
            color: #654f44 !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            text-align: left !important;
            border-right: 1px solid #decec2 !important;
            border-bottom: 1px solid #decec2 !important;
            padding: 10px 8px !important;
        }

        .oc-schedule-wrapper .table-wrap table thead th.col-ziua-header {
            text-align: center !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody td {
            background: rgba(255, 251, 248, 0.96) !important;
            color: #5f4a40 !important;
            border-right: 1px solid #e3d5c9 !important;
            border-bottom: 1px solid #e3d5c9 !important;
            padding: 10px 8px !important;
            font-size: 14px !important;
            line-height: 1.3 !important;
            font-weight: 600 !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody tr:nth-child(even) td {
            background: rgba(252, 246, 240, 0.96) !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ora {
            color: #5d473d !important;
            font-size: 14px !important;
            font-weight: 800 !important;
            width: 10% !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ziua {
            background: rgba(252, 246, 240, 0.98) !important;
            width: 16% !important;
            padding: 0 !important;
            vertical-align: top !important;
            text-align: center !important;
        }

        .oc-schedule-wrapper .table-wrap .oc-day-pill {
            width: 100% !important;
            min-height: 54px !important;
            border-radius: 0 0 16px 0 !important;
            padding: 14px 18px !important;
            background: linear-gradient(180deg, #e8ab66 0%, #dd9852 55%, #d48945 100%) !important;
            color: #fffdfa !important;
            font-size: 16px !important;
            font-weight: 800 !important;
            letter-spacing: 0.02em !important;
            text-align: left !important;
            justify-content: flex-start !important;
            text-shadow: 0 1px 0 rgba(109, 58, 26, 0.4) !important;
            border-right: 1px solid rgba(172, 108, 63, 0.45) !important;
            border-bottom: 1px solid rgba(172, 108, 63, 0.35) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.28), 0 1px 3px rgba(120, 72, 40, 0.20) !important;
            box-sizing: border-box !important;
        }

        .oc-schedule-wrapper .oc-course-pill {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 12px !important;
            padding: 9px 13px !important;
            height: auto !important;
            min-height: calc(2.4em + 18px) !important;
            width: 100% !important;
            max-width: 100% !important;
            background: linear-gradient(180deg, #f1dcc8 0%, #ead1bb 100%) !important;
            border: 1px solid #d8bda8 !important;
            color: #5a463c !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere !important;
            text-align: center !important;
            box-sizing: border-box !important;
            word-break: break-word !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.66) !important;
        }

        .oc-schedule-wrapper .oc-empty {
            color: #b8a79a !important;
            font-weight: 600 !important;
        }

        .oc-schedule-wrapper .card {
            background: rgba(255, 251, 247, 0.98) !important;
            border: 1px solid #e0cfc1 !important;
            border-radius: 14px !important;
            box-shadow: 0 6px 14px rgba(90, 62, 43, 0.08) !important;
        }

        .oc-schedule-wrapper .badge {
            background: linear-gradient(180deg, #f1dcc8 0%, #ead1bb 100%) !important;
            border: 1px solid #d8bda8 !important;
            color: #5a463c !important;
            font-weight: 700 !important;
        }

        .oc-schedule-wrapper .room {
            background: rgba(250, 240, 231, 0.98) !important;
            border: 1px solid #ddc8b7 !important;
            color: #5d473d !important;
        }
        ";

        $custom_css .= "\n" . $reference_css;

        // Bind final design colors to admin-configurable CSS vars.
        $wrapper_background = "linear-gradient(180deg, var(--oc-background, #f7eee8) 0%, var(--oc-muted, #f5ece5) 100%)";
        if ($background_mode === 'image' && !empty($background_image)) {
            $wrapper_background = "url('{$background_image}') center center / cover no-repeat";
        } elseif ($background_mode === 'gradient' && $gradient_enabled === '1') {
            $wrapper_background = "linear-gradient({$gradient_direction}, {$gradient_start}, {$gradient_end})";
        }

        $color_bind_css = "
        .oc-schedule-wrapper {
            background: {$wrapper_background} !important;
            border-color: var(--oc-border, #e3d5c9) !important;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            overflow: hidden !important;
        }

        .oc-schedule-wrapper .table-wrap {
            border-color: var(--oc-border, #e3d5c9) !important;
            background: transparent !important;
        }

        .oc-schedule-wrapper .table-wrap table {
            background: transparent !important;
        }

        .oc-schedule-wrapper .table-wrap table thead th {
            background: var(--oc-muted, #f5ece5) !important;
            color: var(--oc-text, #5f4a40) !important;
            border-right-color: var(--oc-border, #e3d5c9) !important;
            border-bottom-color: var(--oc-border, #e3d5c9) !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody td {
            background: transparent !important;
            color: var(--oc-text, #5f4a40) !important;
            border-right-color: var(--oc-border, #e3d5c9) !important;
            border-bottom-color: var(--oc-border, #e3d5c9) !important;
        }

        .oc-schedule-wrapper .table-wrap table tbody tr:nth-child(even) td {
            background: transparent !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ora {
            color: var(--oc-text, #5f4a40) !important;
        }

        .oc-schedule-wrapper .table-wrap .col-ziua {
            background: transparent !important;
        }

        .oc-schedule-wrapper .table-wrap .oc-day-pill {
            background: linear-gradient(180deg, var(--oc-primary, #d48945), var(--oc-primary, #d48945)) !important;
            background: linear-gradient(180deg, color-mix(in srgb, var(--oc-primary, #d48945) 78%, white), var(--oc-primary, #d48945) 100%) !important;
            border-right-color: var(--oc-primary, #d48945) !important;
            border-right-color: color-mix(in srgb, var(--oc-primary, #d48945) 72%, black) !important;
            border-bottom-color: var(--oc-primary, #d48945) !important;
            border-bottom-color: color-mix(in srgb, var(--oc-primary, #d48945) 72%, black) !important;
            box-sizing: border-box !important;
        }

        .oc-schedule-wrapper .oc-course-pill,
        .oc-schedule-wrapper .badge {
            background: var(--oc-muted, #f5ece5) !important;
            background: linear-gradient(180deg, color-mix(in srgb, var(--oc-muted, #f5ece5) 88%, white), color-mix(in srgb, var(--oc-primary, #d48945) 24%, var(--oc-muted, #f5ece5))) !important;
            border-color: var(--oc-border, #e3d5c9) !important;
            border-color: color-mix(in srgb, var(--oc-primary, #d48945) 34%, var(--oc-border, #e3d5c9)) !important;
            color: var(--oc-text, #5f4a40) !important;
        }

        .oc-schedule-wrapper .room {
            background: transparent !important;
            border-color: var(--oc-border, #e3d5c9) !important;
            color: var(--oc-text, #5f4a40) !important;
        }

        .oc-schedule-wrapper .oc-empty,
        .oc-schedule-wrapper .label {
            color: var(--oc-secondary, #8d786b) !important;
        }

        .admin-preview.mobile-view .oc-schedule-wrapper .oc-schedule-logo {
            width: clamp(96px, 28vw, 150px) !important;
            height: auto !important;
        }

        .admin-preview.mobile-view .oc-schedule-wrapper .cards .card {
            background: rgba(255, 255, 255, 0.52) !important;
            background: color-mix(in srgb, var(--oc-background, #f7eee8) 42%, transparent) !important;
            border-color: color-mix(in srgb, var(--oc-border, #e3d5c9) 70%, white) !important;
            backdrop-filter: blur(2px) !important;
        }

        .admin-preview.mobile-view .oc-schedule-wrapper .cards .room {
            background: rgba(255, 255, 255, 0.28) !important;
            background: color-mix(in srgb, var(--oc-muted, #f5ece5) 34%, transparent) !important;
            border-color: color-mix(in srgb, var(--oc-border, #e3d5c9) 60%, white) !important;
        }

        @media (max-width: 899px) {
            .oc-schedule-wrapper .oc-schedule-logo {
                width: clamp(96px, 28vw, 150px) !important;
                height: auto !important;
            }

            .oc-schedule-wrapper .cards .card {
                background: rgba(255, 255, 255, 0.52) !important;
                background: color-mix(in srgb, var(--oc-background, #f7eee8) 42%, transparent) !important;
                border-color: color-mix(in srgb, var(--oc-border, #e3d5c9) 70%, white) !important;
                backdrop-filter: blur(2px) !important;
            }

            .oc-schedule-wrapper .cards .room {
                background: rgba(255, 255, 255, 0.28) !important;
                background: color-mix(in srgb, var(--oc-muted, #f5ece5) 34%, transparent) !important;
                border-color: color-mix(in srgb, var(--oc-border, #e3d5c9) 60%, white) !important;
            }
        }
        ";

        $custom_css .= "\n" . $color_bind_css;

        wp_add_inline_style('oc-frontend-style', $custom_css);
    }
    
    /**
     * Add JavaScript for admin preview toggle functionality
     */
    private function add_admin_preview_script() {
        $script = "
        jQuery(document).ready(function($) {
            // Handle admin view toggle buttons
            $(document).on('click', '.admin-view-btn', function(e) {
                e.preventDefault();
                
                var \$btn = $(this);
                var view = \$btn.data('view');
                var \$preview = $('#admin-schedule-preview');
                var \$buttons = $('.admin-view-btn');
                
                // Update button states
                \$buttons.removeClass('active');
                \$btn.addClass('active');
                
                // Update preview view
                if (view === 'mobile') {
                    \$preview.addClass('mobile-view');
                } else {
                    \$preview.removeClass('mobile-view');
                }
            });
        });
        ";
        
        wp_add_inline_script('oc-admin-script', $script);
    }
    
    /**
     * Localize admin script with necessary data
     */
    private function localize_admin_script() {
        $woocommerce = new OC_WooCommerce();
        $variable_products = $woocommerce->get_variable_products();
        
        // Get selected product
        $selected_product = get_option('oc_selected_product', 0);
        
        // If no product is selected but we have available products, select the first one
        if (empty($selected_product) && !empty($variable_products)) {
            $first_product = reset($variable_products);
            update_option('oc_selected_product', $first_product['id']);
            $selected_product = $first_product['id'];
        }
        
        // Get schedule data for JavaScript (FIXED: like OLD structure)
        $schedule_data = [];
        if (!empty($selected_product)) {
            $schedules = $this->service->get_schedules($selected_product);
            
            // Convert to format JavaScript can use (SIMPLE structure like OLD admin_page)
            foreach ($schedules as $schedule) {
                $weekday = $schedule->get_weekday();
                $room = $schedule->get_room_number();
                if (!isset($schedule_data[$weekday])) {
                    $schedule_data[$weekday] = [];
                }
                $schedule_data[$weekday][$room] = [
                    'variation_id' => $schedule->get_variation_id(),
                    'start_time' => $schedule->get_start_time(),
                    'end_time' => $schedule->get_end_time()
                ];
            }
        }
        
        wp_localize_script('oc-admin-script', 'ocAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_admin_nonce'),
            'selectedProduct' => $selected_product,
            'selectedAttribute' => $selected_product, // Backward compatibility
            'scheduleData' => $schedule_data, // CRITICAL: Schedule data for JavaScript!
            'strings' => [
                'saving' => __('Salvare...', OC_TEXT_DOMAIN),
                'saved' => __('Salvat', OC_TEXT_DOMAIN),
                'error' => __('Eroare la salvare', OC_TEXT_DOMAIN),
                'deleteConfirm' => __('Ești sigur că vrei să ștergi această intrare?', OC_TEXT_DOMAIN),
                'selectProduct' => __('Selectează un produs', OC_TEXT_DOMAIN),
                'noVariations' => __('Nu există variații pentru acest produs', OC_TEXT_DOMAIN),
                'confirmDelete' => __('Ești sigur că vrei să ștergi acest rând?', OC_TEXT_DOMAIN),
                'saveSuccess' => __('Orarul a fost salvat cu succes!', OC_TEXT_DOMAIN),
                'saveError' => __('Eroare la salvarea orarului. Te rugăm să încerci din nou.', OC_TEXT_DOMAIN),
                'overlapError' => __('Există o suprapunere în orar pentru acest curs și interval orar.', OC_TEXT_DOMAIN)
            ]
        ]);
    }
    
    /**
     * Main admin page callback - TAB SYSTEM ENABLED
     */
    public function admin_page() {
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Get current tab from URL parameter
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'schedule';
        $debug_tab_enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');
        
        // Validate tab
        $valid_tabs = ['schedule', 'appearance', 'debug'];
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = 'schedule';
        }
        
        if ($current_tab === 'debug' && !$debug_tab_enabled) {
            $current_tab = 'schedule';
        }
        
        // Set variables needed by template
        $woocommerce = new OC_WooCommerce();
        $variable_products = $woocommerce->get_variable_products();
        $selected_product = get_option('oc_selected_product', 0);
        
        // Set template variables for compatibility
        $course_terms = [];
        $selected_attribute = $selected_product;
        $product_attributes = $variable_products; // CRITICAL: Template needs this variable!
        
        if (!empty($selected_product)) {
            $variations = $woocommerce->get_product_variations($selected_product);
            $course_terms = $variations;
        }
        
        // CRITICAL: Load existing schedule data from database
        $schedule_data = [];
        if (!empty($selected_product)) {
            $schedules = $this->service->get_schedules($selected_product);
            
            // Convert to format JavaScript/template can use (SIMPLE structure like OLD admin_page)
            foreach ($schedules as $schedule) {
                $weekday = $schedule->get_weekday();
                $room = $schedule->get_room_number();
                if (!isset($schedule_data[$weekday])) {
                    $schedule_data[$weekday] = [];
                }
                $schedule_data[$weekday][$room] = [
                    'variation_id' => $schedule->get_variation_id(),
                    'start_time' => $schedule->get_start_time(),
                    'end_time' => $schedule->get_end_time()
                ];
            }
        }
        
        // Clear WooCommerce cache if needed
        if (empty($variable_products)) {
            $woocommerce->clear_products_cache();
        }
        
        // Load admin page template with tabs
        $template_path = dirname(__FILE__) . '/../../templates/admin/schedule-list.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_admin_page();
        }
    }
    
    /**
     * Style settings page callback
     */
    public function style_settings_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Set variables needed by template (if any)
        
        // Load style settings template from Schedule Manager's own templates
        $template_path = dirname(__FILE__) . '/../../templates/admin/style-settings.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_style_page();
        }
    }
    
    /**
     * Developer debug page callback
     */
    public function developer_debug_page() {
        // Check permissions
        if (!current_user_can('administrator')) {
            wp_die(__('Nu aveți permisiuni pentru această pagină.', OC_TEXT_DOMAIN));
        }
        
        // Load debug template
        $template_path = dirname(__FILE__) . '/../../templates/admin/debug-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_fallback_debug_page();
        }
    }
    
    /**
     * Register admin settings
     */
    private function register_settings() {
        // Schedule settings
        register_setting('oc_schedule_settings', 'oc_selected_product');
        register_setting('oc_schedule_settings', 'oc_schedule_title');
        
        // Style settings
        register_setting('oc_style_settings', 'oc_primary_color');
        register_setting('oc_style_settings', 'oc_title_color');
        register_setting('oc_style_settings', 'oc_secondary_color');
        register_setting('oc_style_settings', 'oc_background_color');
        register_setting('oc_style_settings', 'oc_muted_color');
        register_setting('oc_style_settings', 'oc_border_color');
        register_setting('oc_style_settings', 'oc_text_color');
        register_setting('oc_style_settings', 'oc_background_image');
        register_setting('oc_style_settings', 'oc_font_family');
        register_setting('oc_style_settings', 'oc_font_size');
        register_setting('oc_style_settings', 'oc_header_font_size');
        register_setting('oc_style_settings', 'oc_border_radius');
        register_setting('oc_style_settings', 'oc_logo_image');
        register_setting('oc_style_settings', 'oc_logo_width');
        register_setting('oc_style_settings', 'oc_logo_height');
        register_setting('oc_style_settings', 'oc_logo_position');
        register_setting('oc_style_settings', 'oc_gradient_enabled');
        register_setting('oc_style_settings', 'oc_background_mode');
        register_setting('oc_style_settings', 'oc_background_image');
        register_setting('oc_style_settings', 'oc_background_image_mobile');
        register_setting('oc_style_settings', 'oc_background_image_opacity');
        register_setting('oc_style_settings', 'oc_background_image_size_desktop');
        register_setting('oc_style_settings', 'oc_background_image_size_mobile');
        register_setting('oc_style_settings', 'oc_background_image_mobile_behavior');
        register_setting('oc_style_settings', 'oc_gradient_start');
        register_setting('oc_style_settings', 'oc_gradient_end');
        register_setting('oc_style_settings', 'oc_gradient_direction');
        register_setting('oc_style_settings', 'oc_course_color_map');
        register_setting('oc_style_settings', 'oc_custom_css');
    }

    /**
     * Persist style settings posted from Appearance tab.
     */
    private function handle_style_settings_save() {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? '')) {
            return;
        }

        if (!isset($_POST['oc_style_nonce'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['oc_style_nonce']) || !wp_verify_nonce($_POST['oc_style_nonce'], 'oc_save_style_settings')) {
            return;
        }

        $color_fields = [
            'oc_primary_color', 'oc_title_color', 'oc_text_color', 'oc_secondary_color',
            'oc_background_color', 'oc_muted_color', 'oc_border_color',
            'oc_gradient_start', 'oc_gradient_end'
        ];

        foreach ($color_fields as $field) {
            if (isset($_POST[$field])) {
                $value = $this->sanitize_css_color_value(wp_unslash($_POST[$field]));
                if (!empty($value)) {
                    update_option($field, $value);
                }
            }
        }

        $text_fields = [
            'oc_font_family', 'oc_font_size', 'oc_header_font_size', 'oc_border_radius',
            'oc_logo_width', 'oc_logo_height', 'oc_gradient_direction'
        ];

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }

        if (isset($_POST['oc_logo_image'])) {
            update_option('oc_logo_image', esc_url_raw(wp_unslash($_POST['oc_logo_image'])));
        }

        if (isset($_POST['oc_background_image'])) {
            update_option('oc_background_image', esc_url_raw(wp_unslash($_POST['oc_background_image'])));
        }

        if (isset($_POST['oc_background_image_mobile'])) {
            update_option('oc_background_image_mobile', esc_url_raw(wp_unslash($_POST['oc_background_image_mobile'])));
        }

        if (isset($_POST['oc_background_image_opacity'])) {
            $image_opacity = (float) wp_unslash($_POST['oc_background_image_opacity']);
            if ($image_opacity < 0) {
                $image_opacity = 0;
            }
            if ($image_opacity > 1) {
                $image_opacity = 1;
            }
            update_option('oc_background_image_opacity', (string) $image_opacity);
        }

        if (isset($_POST['oc_background_image_size_desktop'])) {
            $image_size_desktop = (float) wp_unslash($_POST['oc_background_image_size_desktop']);
            if ($image_size_desktop < 50) {
                $image_size_desktop = 50;
            }
            if ($image_size_desktop > 200) {
                $image_size_desktop = 200;
            }
            update_option('oc_background_image_size_desktop', (string) $image_size_desktop);
        }

        if (isset($_POST['oc_background_image_size_mobile'])) {
            $image_size_mobile = (float) wp_unslash($_POST['oc_background_image_size_mobile']);
            if ($image_size_mobile < 50) {
                $image_size_mobile = 50;
            }
            if ($image_size_mobile > 200) {
                $image_size_mobile = 200;
            }
            update_option('oc_background_image_size_mobile', (string) $image_size_mobile);
        }

        $mobile_bg_behavior = 'cover';
        if (isset($_POST['oc_background_image_mobile_behavior'])) {
            $behavior_candidate = sanitize_key(wp_unslash($_POST['oc_background_image_mobile_behavior']));
            if (in_array($behavior_candidate, ['cover', 'repeat'], true)) {
                $mobile_bg_behavior = $behavior_candidate;
            }
        }
        update_option('oc_background_image_mobile_behavior', $mobile_bg_behavior);

        $background_mode = 'gradient';
        if (isset($_POST['oc_background_mode'])) {
            $mode_candidate = sanitize_key(wp_unslash($_POST['oc_background_mode']));
            if (in_array($mode_candidate, ['gradient', 'image'], true)) {
                $background_mode = $mode_candidate;
            }
        }
        update_option('oc_background_mode', $background_mode);

        if (isset($_POST['oc_logo_position'])) {
            $logo_position = sanitize_key(wp_unslash($_POST['oc_logo_position']));
            if (!in_array($logo_position, ['left', 'right'], true)) {
                $logo_position = 'left';
            }
            update_option('oc_logo_position', $logo_position);
        }

        // Keep legacy toggle in sync for existing rendering branches.
        update_option('oc_gradient_enabled', $background_mode === 'gradient' ? '1' : '0');

        // Save per-course color mapping only when fields are present in POST.
        // This prevents accidental map wipes (e.g. missing section/POST truncation).
        if (isset($_POST['oc_course_colors']) && is_array($_POST['oc_course_colors'])) {
            $course_color_map = [];
            foreach (wp_unslash($_POST['oc_course_colors']) as $variation_id => $course_colors) {
                $variation_id = absint($variation_id);
                if ($variation_id <= 0 || !is_array($course_colors)) {
                    continue;
                }

                $bg = isset($course_colors['bg']) ? $this->sanitize_css_color_value($course_colors['bg']) : '';
                $text = isset($course_colors['text']) ? $this->sanitize_css_color_value($course_colors['text']) : '';

                if (empty($bg) && empty($text)) {
                    continue;
                }

                $course_color_map[$variation_id] = [
                    'bg' => $bg ?: '',
                    'text' => $text ?: '',
                ];
            }

            update_option('oc_course_color_map', $course_color_map);
        }

        // Keep user on the same admin page tab after save.
        $redirect_url = add_query_arg(
            [
                'page' => 'orar-cursuri',
                'tab' => 'appearance',
                'oc_style_saved' => '1'
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Sanitize CSS color values (hex/rgb/rgba/transparent).
     */
    private function sanitize_css_color_value($value) {
        $value = trim((string) sanitize_text_field((string) $value));
        if ($value === '') {
            return '';
        }

        if (strtolower($value) === 'transparent') {
            return 'transparent';
        }

        $hex = sanitize_hex_color($value);
        if (!empty($hex)) {
            return $hex;
        }

        if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(0|0?\.[0-9]+|1(?:\.0+)?))?\s*\)$/i', $value, $m)) {
            $r = max(0, min(255, (int) $m[1]));
            $g = max(0, min(255, (int) $m[2]));
            $b = max(0, min(255, (int) $m[3]));
            if (isset($m[4]) && $m[4] !== '') {
                $a = max(0, min(1, (float) $m[4]));
                return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim((string) $a, '0'), '.'));
            }
            return sprintf('rgb(%d, %d, %d)', $r, $g, $b);
        }

        return '';
    }
    
    /**
     * Ensure a product is selected for frontend display
     */
    private function ensure_product_selected() {
        $selected_product = get_option('oc_selected_product', 0);
        
        if (empty($selected_product)) {
            $woocommerce = new OC_WooCommerce();
            $variable_products = $woocommerce->get_variable_products();
            
            if (!empty($variable_products)) {
                $first_product = reset($variable_products);
                update_option('oc_selected_product', $first_product['id']);
            }
        }
    }
    
    /**
     * Render fallback admin page when template is missing
     */
    private function render_fallback_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Schedule Manager', OC_TEXT_DOMAIN) . '</h1>';
        echo '<div class="notice notice-error"><p>' . __('Template-ul admin nu a fost găsit.', OC_TEXT_DOMAIN) . '</p></div>';
        echo '</div>';
    }
    
    /**
     * Render fallback style page when template is missing
     */
    private function render_fallback_style_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Appearance Settings', OC_TEXT_DOMAIN) . '</h1>';
        echo '<div class="notice notice-error"><p>' . __('Template-ul de stil nu a fost găsit.', OC_TEXT_DOMAIN) . '</p></div>';
        echo '</div>';
    }
    
    /**
     * Render fallback debug page when template is missing
     */
    private function render_fallback_debug_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Debug Tools', OC_TEXT_DOMAIN) . '</h1>';
        echo '<div class="notice notice-error"><p>' . __('Template-ul debug nu a fost găsit.', OC_TEXT_DOMAIN) . '</p></div>';
        
        // Show basic debug info
        echo '<h2>' . __('Service Status', OC_TEXT_DOMAIN) . '</h2>';
        echo '<p><strong>' . __('Schedule Service Ready:', OC_TEXT_DOMAIN) . '</strong> ';
        echo $this->service->is_ready() ? '✅ Yes' : '❌ No';
        echo '</p>';
        
        // Show statistics
        $stats = $this->service->get_statistics();
        echo '<h2>' . __('Statistics', OC_TEXT_DOMAIN) . '</h2>';
        echo '<ul>';
        foreach ($stats as $key => $value) {
            echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
        }
        echo '</ul>';
        
        echo '</div>';
    }
    
    /**
     * Get schedule service instance
     * 
     * @return OC_Schedule_Service
     */
    public function get_service() {
        return $this->service;
    }
}
