<?php
/**
 * Frontend display class
 * 
 * Handles all frontend functionality including
 * shortcode registration and schedule rendering for
 * Membership Validator Core and Schedule Manager module.
 * 
 * @package MembershipValidatorCore
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend management class
 */
class OC_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Initialize frontend
     */
    public function init() {
        add_shortcode('orar_cursuri', [$this, 'render_schedule_shortcode']);
        add_shortcode('schedule_manager', [$this, 'render_schedule_shortcode']); // Compatibility
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Force enqueue on frontend for now to ensure backgrounds work
        wp_enqueue_style(
            'oc-frontend-style',
            OC_PLUGIN_URL . 'assets/frontend.css',
            [],
            OC_PLUGIN_VERSION
        );
        
        // Enqueue simple CSS for mobile controls only
        wp_enqueue_style(
            'oc-frontend-simple-style',
            OC_PLUGIN_URL . 'assets/frontend-simple.css',
            ['oc-frontend-style'],
OC_PLUGIN_VERSION
        );
        
        // Enqueue SIMPLE frontend JavaScript - păstrează aspectul original
        wp_enqueue_script(
            'oc-frontend-simple-script',
            OC_PLUGIN_URL . 'assets/frontend-simple.js',
            [], // No dependencies - pure vanilla JS for cache compatibility
OC_PLUGIN_VERSION, // New version for simple approach
            true // Load in footer for better performance
        );
        
        // Always add dynamic styles
        $this->add_dynamic_styles();
    }
    
    /**
     * Check if current page has schedule shortcode
     * 
     * @return bool
     */
    private function has_schedule_shortcode() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'orar_cursuri');
    }
    
    /**
     * Add dynamic CSS styles based on settings
     */
    private function add_dynamic_styles() {
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
        $logo_width = get_option('oc_logo_width', '50px');
        $logo_height = get_option('oc_logo_height', 'auto');
        
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
        
        /* Header and Logo Styling */
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
        
        .oc-schedule-logo {
            width: {$logo_width} !important;
            height: {$logo_height} !important;
            object-fit: contain !important;
            vertical-align: middle !important;
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
        
        /* Ensure schedule container has no bottom spacing */
        .oc-schedule-wrapper .oc-schedule-container {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }
        
        /* Desktop Table Styling with Fixed Borders */
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
        
        /* Header styling */
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
        
        /* Remove right border from last header */
        .oc-schedule-wrapper .table-wrap table thead th:last-child {
            border-right: none !important;
        }
        
        /* Body cells styling */
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
        
        /* Remove right border from last cell in each row */
        .oc-schedule-wrapper .table-wrap table tbody td:last-child {
            border-right: none !important;
        }
        
        /* Remove bottom border from last row and eliminate spacing */
        .oc-schedule-wrapper .table-wrap table tbody tr:last-child td {
            border-bottom: none !important;
            padding-bottom: 12px !important;
        }
        
        /* Ensure no extra spacing in table wrapper */
        .oc-schedule-wrapper .table-wrap {
            margin-bottom: 0 !important;
        }
        
        /* Ensure table has no bottom margin/padding */
        .oc-schedule-wrapper .table-wrap table {
            margin-bottom: 0 !important;
            border-bottom: none !important;
        }
        
        /* Ensure tbody has no bottom spacing */
        .oc-schedule-wrapper .table-wrap table tbody {
            border-bottom: none !important;
        }
        
        /* Column specific styling */
        .oc-schedule-wrapper .table-wrap .col-ziua {
            width: 140px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
        }
        
        .oc-schedule-wrapper .table-wrap .col-ora {
            width: 90px !important;
            font-weight: 700 !important;
            color: var(--oc-primary, {$primary_color}) !important;
            font-variant-numeric: tabular-nums !important;
        }
        
        .oc-schedule-wrapper .muted {
            color: var(--oc-secondary, {$secondary_color}) !important;
        }
        
        .oc-schedule-wrapper .nowrap {
            white-space: nowrap !important;
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
        
        /* Desktop breakpoint */
        @media (min-width: 900px) {
            .oc-schedule-wrapper .table-wrap {
                display: block !important;
            }
            .oc-schedule-wrapper .cards {
                display: none !important;
            }
        }";
        
        // Gradient background - keep contrast high for schedule readability.
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

        .oc-toggle-all-btn {
            background: linear-gradient(130deg, #d97b45, #c96934) !important;
            color: #ffffff !important;
            border-radius: 10px !important;
            border: 0 !important;
            box-shadow: 0 8px 16px rgba(130, 74, 42, 0.22) !important;
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
     * Render schedule shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_schedule_shortcode($atts) {
        $atts = shortcode_atts([
            'show_empty_days' => get_option('oc_show_empty_days', false),
            'hide_empty_rooms' => get_option('oc_hide_empty_rooms', true),
            'show_only_populated' => get_option('oc_show_only_populated', false),
            'class' => ''
        ], $atts, 'orar_cursuri');
        
        // Get schedule data
        $db = new OC_DB();
        $selected_product = get_option('oc_selected_product', 0);
        
        if (empty($selected_product)) {
            // Check if there are any variable products at all
            $woocommerce = new OC_WooCommerce();
            $variable_products = $woocommerce->get_variable_products();
            
            if (empty($variable_products)) {
                return $this->render_no_schedule_message('no_variable_products');
            } else {
                return $this->render_no_schedule_message('no_product_selected');
            }
        }
        
        $schedule_rows = $db->get_schedule($selected_product);
        
        if (empty($schedule_rows)) {
            return $this->render_no_schedule_message('no_schedule_data');
        }
        
        // Organize schedule data
        $schedule_data = [];
        foreach ($schedule_rows as $row) {
            // Format times without seconds (HH:MM only)
            $start_time = date('H:i', strtotime($row['start_time']));
            $end_time = date('H:i', strtotime($row['end_time']));
            $time_key = $start_time . '-' . $end_time; // Internal key includes range for grouping
            $display_time = $start_time; // But display only start time
            $weekday = $row['weekday'];
            $room = $row['room_number'];
            
            if (!isset($schedule_data[$time_key])) {
                $schedule_data[$time_key] = [
                    'display_time' => $display_time,
                    'weekdays' => []
                ];
            }
            
            if (!isset($schedule_data[$time_key]['weekdays'][$weekday])) {
                $schedule_data[$time_key]['weekdays'][$weekday] = [];
            }
            
            $schedule_data[$time_key]['weekdays'][$weekday][$room] = [
                'variation_id' => $row['variation_id'],
                'variation_name' => $row['variation_name'],
                // Backward compatibility
                'term_id' => $row['variation_id'],
                'term_name' => $row['variation_name']
            ];
        }
        

        
        // Apply filters based on attributes
        if (!$atts['show_empty_days']) {
            $schedule_data = $this->remove_empty_days($schedule_data);
        }
        
        if ($atts['hide_empty_rooms']) {
            $schedule_data = $this->remove_empty_rooms($schedule_data);
        }
        
        if ($atts['show_only_populated']) {
            $schedule_data = $this->filter_only_populated($schedule_data);
        }
        
        // Force color vars on the wrapper to avoid external theme/Elementor overrides.
        $wrapper_style = $this->get_wrapper_color_vars_inline();
        $instance_class = 'oc-schedule-instance-' . wp_generate_password(6, false, false);
        $instance_style = $this->get_instance_color_override_css($instance_class);

        // Render HTML
        ob_start();
        ?>
        <style><?php echo wp_strip_all_tags($instance_style); ?></style>
        <div class="oc-schedule-wrapper <?php echo esc_attr(trim($atts['class'] . ' ' . $instance_class)); ?>" style="<?php echo esc_attr($wrapper_style); ?>">
            <?php echo $this->render_schedule_header(); ?>
            
            <div class="oc-schedule-container">
                <?php echo $this->render_desktop_table($schedule_data); ?>
                <?php echo $this->render_mobile_cards($schedule_data); ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Build inline CSS vars for schedule wrapper colors.
     *
     * @return string
     */
    private function get_wrapper_color_vars_inline() {
        $primary = $this->sanitize_css_color_value(get_option('oc_primary_color', '#d48945'), '#d48945');
        $title = $this->sanitize_css_color_value(get_option('oc_title_color', '#5d473d'), '#5d473d');
        $text = $this->sanitize_css_color_value(get_option('oc_text_color', '#5f4a40'), '#5f4a40');
        $secondary = $this->sanitize_css_color_value(get_option('oc_secondary_color', '#8d786b'), '#8d786b');
        $background = $this->sanitize_css_color_value(get_option('oc_background_color', '#f7eee8'), '#f7eee8');
        $muted = $this->sanitize_css_color_value(get_option('oc_muted_color', '#f5ece5'), '#f5ece5');
        $border = $this->sanitize_css_color_value(get_option('oc_border_color', '#e3d5c9'), '#e3d5c9');

        return sprintf(
            '--oc-primary:%1$s;--oc-title:%2$s;--oc-text:%3$s;--oc-secondary:%4$s;--oc-background:%5$s;--oc-muted:%6$s;--oc-border:%7$s;',
            $primary,
            $title,
            $text,
            $secondary,
            $background,
            $muted,
            $border
        );
    }

    /**
     * Build per-instance CSS overrides with high specificity.
     *
     * @param string $instance_class
     * @return string
     */
    private function get_instance_color_override_css($instance_class) {
        $primary = $this->sanitize_css_color_value(get_option('oc_primary_color', '#d48945'), '#d48945');
        $title = $this->sanitize_css_color_value(get_option('oc_title_color', '#5d473d'), '#5d473d');
        $text = $this->sanitize_css_color_value(get_option('oc_text_color', '#5f4a40'), '#5f4a40');
        $secondary = $this->sanitize_css_color_value(get_option('oc_secondary_color', '#8d786b'), '#8d786b');
        $background = $this->sanitize_css_color_value(get_option('oc_background_color', '#f7eee8'), '#f7eee8');
        $muted = $this->sanitize_css_color_value(get_option('oc_muted_color', '#f5ece5'), '#f5ece5');
        $border = $this->sanitize_css_color_value(get_option('oc_border_color', '#e3d5c9'), '#e3d5c9');
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
        $gradient_start = $this->sanitize_css_color_value(get_option('oc_gradient_start', '#ff7a3d'), '#ff7a3d');
        $gradient_end = $this->sanitize_css_color_value(get_option('oc_gradient_end', '#ffd08a'), '#ffd08a');
        $gradient_direction = sanitize_text_field(get_option('oc_gradient_direction', '132deg'));

        $s = '.oc-schedule-wrapper.' . sanitize_html_class($instance_class);
        $wrapper_background = "linear-gradient(180deg, {$background} 0%, {$muted} 100%)";
        if ($background_mode === 'gradient' && $gradient_enabled === '1') {
            $wrapper_background = "linear-gradient({$gradient_direction}, {$gradient_start}, {$gradient_end})";
        }

        $image_layer_css = '';
        if ($background_mode === 'image' && !empty($background_image)) {
            $desktop_image_size = $background_image_size_desktop . '% auto';
            $mobile_image_size = $background_image_mobile_behavior === 'repeat' ? 'auto' : ($background_image_size_mobile . '% auto');
            $mobile_image_repeat = $background_image_mobile_behavior === 'repeat' ? 'repeat' : 'repeat-y';
            $mobile_image_position = 'top center';
            $mobile_image_source = !empty($background_image_mobile) ? $background_image_mobile : $background_image;
            $image_layer_css = "
        {$s} { position: relative !important; overflow: hidden !important; }
        {$s}::before { content: '' !important; position: absolute !important; inset: 0 !important; background-image: url('{$background_image}') !important; background-position: top center !important; background-size: {$desktop_image_size} !important; background-repeat: repeat-y !important; opacity: {$background_image_opacity} !important; pointer-events: none !important; z-index: 0 !important; }
        @media (max-width: 899px) { {$s}::before { background-image: url('{$mobile_image_source}') !important; background-size: {$mobile_image_size} !important; background-repeat: {$mobile_image_repeat} !important; background-position: {$mobile_image_position} !important; } }
        {$s} > * { position: relative !important; z-index: 1 !important; }
            ";
        }

        return "
        {$s} { background: {$wrapper_background} !important; border-color: {$border} !important; background-size: cover !important; background-position: center center !important; background-repeat: no-repeat !important; overflow: hidden !important; }
        {$s} .oc-schedule-title { color: {$title} !important; }
        {$s} .table-wrap { background: transparent !important; border-color: {$border} !important; }
        {$s} .table-wrap table { background: transparent !important; }
        {$s} .table-wrap table thead th { background: {$muted} !important; color: {$text} !important; border-right-color: {$border} !important; border-bottom-color: {$border} !important; }
        {$s} .table-wrap table tbody td { background: transparent !important; color: {$text} !important; border-right-color: {$border} !important; border-bottom-color: {$border} !important; }
        {$s} .table-wrap table tbody tr:nth-child(even) td { background: transparent !important; }
        {$s} .table-wrap .col-ziua { background: transparent !important; }
        {$s} .table-wrap .oc-day-pill { background: {$primary} !important; border-right-color: {$primary} !important; border-bottom-color: {$primary} !important; box-sizing: border-box !important; }
        {$s} .oc-course-pill, {$s} .badge { background: {$muted} !important; border-color: {$border} !important; color: {$text} !important; }
        {$s} .room { background: transparent !important; border-color: {$border} !important; color: {$text} !important; }
        {$s} .oc-empty, {$s} .label { color: {$secondary} !important; }
        {$image_layer_css}
        ";
    }
    
    /**
     * Remove empty days from schedule
     * 
     * @param array $schedule_data
     * @return array
     */
    private function remove_empty_days($schedule_data) {
        foreach ($schedule_data as $time_key => $time_data) {
            foreach ($time_data['weekdays'] as $weekday => $rooms) {
                $has_content = false;
                foreach ($rooms as $room_data) {
                    if (!empty($room_data['variation_id']) || !empty($room_data['term_id'])) {
                        $has_content = true;
                        break;
                    }
                }
                
                if (!$has_content) {
                    unset($schedule_data[$time_key]['weekdays'][$weekday]);
                }
            }
            
            // Remove time slot if no weekdays left
            if (empty($schedule_data[$time_key]['weekdays'])) {
                unset($schedule_data[$time_key]);
            }
        }
        
        return $schedule_data;
    }
    
    /**
     * Remove empty rooms from schedule
     * 
     * @param array $schedule_data
     * @return array
     */
    private function remove_empty_rooms($schedule_data) {
        foreach ($schedule_data as $time_key => $time_data) {
            foreach ($time_data['weekdays'] as $weekday => $rooms) {
                foreach ($rooms as $room => $room_data) {
                    if (empty($room_data['variation_id']) && empty($room_data['term_id'])) {
                        unset($schedule_data[$time_key]['weekdays'][$weekday][$room]);
                    }
                }
            }
        }
        
        return $schedule_data;
    }
    
    /**
     * Filter to show only populated time slots
     * 
     * @param array $schedule_data
     * @return array
     */
    private function filter_only_populated($schedule_data) {
        $filtered = [];
        
        foreach ($schedule_data as $time_key => $time_data) {
            $has_populated_content = false;
            
            foreach ($time_data['weekdays'] as $weekday => $rooms) {
                foreach ($rooms as $room_data) {
                    if (!empty($room_data['variation_id']) || !empty($room_data['term_id'])) {
                        $has_populated_content = true;
                        break 2;
                    }
                }
            }
            
            if ($has_populated_content) {
                $filtered[$time_key] = $time_data;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Render schedule header
     * 
     * @return string
     */
    private function render_schedule_header() {
        // Get title from option, with fallback to default
        $header_title = get_option('oc_schedule_title', 'CALIENTE DANCE STUDIO — ORAR');
        $header_title = apply_filters('oc_schedule_header_title', $header_title);
        
        // Get logo settings
        $logo_image = get_option('oc_logo_image', '');
        $logo_position = get_option('oc_logo_position', 'left');
        
        $logo_html = '';
        if (!empty($logo_image)) {
            $logo_html = '<img src="' . esc_url($logo_image) . '" alt="Logo" class="oc-schedule-logo">';
        }
        
        $header_content = '';
        if ($logo_position === 'left' && $logo_html) {
            $header_content = $logo_html . '<h2 class="oc-schedule-title">' . esc_html($header_title) . '</h2>';
        } elseif ($logo_position === 'right' && $logo_html) {
            $header_content = '<h2 class="oc-schedule-title">' . esc_html($header_title) . '</h2>' . $logo_html;
        } else {
            $header_content = '<h2 class="oc-schedule-title">' . esc_html($header_title) . '</h2>';
        }
        
        return '<header class="oc-schedule-header">
                    <div class="oc-title-logo-wrapper">
                        ' . $header_content . '
                    </div>
                </header>';
    }
    
    /**
     * Render desktop table (like EXEMPLU-ORAR.html)
     * 
     * @param array $schedule_data
     * @return string
     */
    private function render_desktop_table($schedule_data) {
        if (empty($schedule_data)) {
            return '<div class="oc-no-schedule">Nu există cursuri programate.</div>';
        }
        
        $weekdays = $this->get_weekday_names();
        
        // Group by weekday and time, then organize by rooms
        $organized_data = $this->organize_data_by_weekday_time($schedule_data);
        $course_color_map = $this->get_course_color_map();
        
        ob_start();
        ?>
        <div class="table-wrap oc-desktop-only">
            <table>
                <thead>
                    <tr>
                        <th class="col-ziua-header">ZIUA</th>
                        <th class="col-ora">ORA</th>
                        <th>SALA 1</th>
                        <th>SALA 2</th>
                        <th>SALA 3</th>
                        <th>SALA 4</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($organized_data as $weekday_num => $weekday_data):
                        
                        $time_count = count($weekday_data);
                        $first_time = true;
                        foreach ($weekday_data as $time_key => $rooms): ?>
                            <tr>
                                <?php if ($first_time): ?>
                                    <td class="col-ziua oc-day-cell" rowspan="<?php echo $time_count; ?>"><span class="oc-day-pill"><?php echo esc_html(strtoupper($weekdays[$weekday_num])); ?></span></td>
                                <?php endif; ?>
                                <td class="col-ora"><?php echo esc_html($time_key); ?></td>
                                <?php for ($room = 1; $room <= 4; $room++): ?>
                                    <td>
                                        <?php if (!empty($rooms[$room])): ?>
                                            <?php $pill_style = $this->get_course_pill_inline_style($rooms[$room], $course_color_map); ?>
                                            <span class="oc-course-pill"<?php echo $pill_style ? ' style="' . esc_attr($pill_style) . '"' : ''; ?>><?php echo esc_html($rooms[$room]['variation_name'] ?? $rooms[$room]['term_name'] ?? ''); ?></span>
                                        <?php else: ?>
                                            <span class="muted oc-empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php 
                        $first_time = false;
                        endforeach; 
                    endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Organize data by weekday and time for rowspan rendering
     */
    private function organize_data_by_weekday_time($schedule_data) {
        $organized = [];
        
        foreach ($schedule_data as $time_key => $time_data) {
            $display_time = $time_data['display_time'];
            foreach ($time_data['weekdays'] as $weekday_num => $weekday_data) {
                if (!isset($organized[$weekday_num])) {
                    $organized[$weekday_num] = [];
                }
                
                // Use display_time as key for rendering
                $organized[$weekday_num][$display_time] = [];
                
                // Initialize all rooms as empty
                for ($room = 1; $room <= 4; $room++) {
                    $organized[$weekday_num][$display_time][$room] = null;
                }
                
                // Fill with actual data
                foreach ($weekday_data as $room => $room_data) {
                    if (!empty($room_data['variation_id']) || !empty($room_data['term_id'])) {
                        $organized[$weekday_num][$display_time][$room] = $room_data;
                    }
                }
            }
        }
        
        // Sort by weekday and time
        ksort($organized);
        foreach ($organized as &$weekday_data) {
            ksort($weekday_data);
        }
        
        return $organized;
    }
    
    /**
     * Render mobile cards
     * 
     * @param array $schedule_data
     * @return string
     */
    private function render_mobile_cards($schedule_data) {
        if (empty($schedule_data)) {
            return '';
        }
        
        $weekdays = $this->get_weekday_names();
        $organized_data = $this->organize_data_by_weekday_time($schedule_data);
        $course_color_map = $this->get_course_color_map();
        
        ob_start();
        ?>
        <section class="cards oc-mobile-only" aria-label="Orar pe mobil">
            <?php foreach ($organized_data as $weekday_num => $weekday_data): ?>
                <?php foreach ($weekday_data as $time_key => $rooms): ?>
                    <div class="card">
                        <div class="top">
                            <div class="badge"><?php echo esc_html(strtoupper($weekdays[$weekday_num])); ?></div>
                            <div class="badge">Ora <?php echo esc_html($time_key); ?></div>
                        </div>
                        <div class="rooms">
                            <?php for ($room = 1; $room <= 4; $room++): ?>
                                <div class="room">
                                    <span class="label">Sala <?php echo $room; ?>:</span>
                                    <?php if (!empty($rooms[$room])): ?>
                                        <?php $pill_style = $this->get_course_pill_inline_style($rooms[$room], $course_color_map); ?>
                                        <span class="oc-course-pill"<?php echo $pill_style ? ' style="' . esc_attr($pill_style) . '"' : ''; ?>><?php echo esc_html($rooms[$room]['variation_name'] ?? $rooms[$room]['term_name'] ?? ''); ?></span>
                                    <?php else: ?>
                                        <span class="muted oc-empty">—</span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </section>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get all rooms used in schedule
     * 
     * @param array $schedule_data
     * @return array
     */
    private function get_all_rooms($schedule_data) {
        $rooms = [];
        
        foreach ($schedule_data as $time_data) {
            foreach ($time_data as $weekday_data) {
                foreach ($weekday_data as $room => $room_data) {
                    if (!in_array($room, $rooms)) {
                        $rooms[] = $room;
                    }
                }
            }
        }
        
        sort($rooms);
        return $rooms;
    }
    
    /**
     * Get weekday names
     * 
     * @return array
     */
    private function get_weekday_names() {
        return [
            1 => 'LUNI',
            2 => 'MARȚI',
            3 => 'MIERCURI',
            4 => 'JOI',
            5 => 'VINERI',
            6 => 'SÂMBĂTĂ',
            7 => 'DUMINICĂ'
        ];
    }

    /**
     * Return saved per-course color map.
     *
     * @return array
     */
    private function get_course_color_map() {
        $map = get_option('oc_course_color_map', []);
        return is_array($map) ? $map : [];
    }

    /**
     * Build inline style for course pill based on variation id.
     *
     * @param array $room_data
     * @param array $course_color_map
     * @return string
     */
    private function get_course_pill_inline_style($room_data, $course_color_map) {
        $variation_id = absint($room_data['variation_id'] ?? $room_data['term_id'] ?? 0);
        if ($variation_id <= 0 || empty($course_color_map[$variation_id]) || !is_array($course_color_map[$variation_id])) {
            return '';
        }

        $bg = $this->sanitize_css_color_value($course_color_map[$variation_id]['bg'] ?? '', '');
        $text = $this->sanitize_css_color_value($course_color_map[$variation_id]['text'] ?? '', '');

        if (empty($bg) && empty($text)) {
            return '';
        }

        if (empty($text) && !empty($bg)) {
            $text = $this->get_contrast_text_color($bg);
        }

        $style = '';
        if (!empty($bg)) {
            $style .= 'background:' . $bg . ' !important;';
            $style .= 'border-color:' . $bg . ' !important;';
            $style .= 'box-shadow:none !important;';
        }
        if (!empty($text)) {
            $style .= 'color:' . $text . ' !important;';
        }

        return $style;
    }

    /**
     * Compute readable text color for a background hex.
     *
     * @param string $hex
     * @return string
     */
    private function get_contrast_text_color($hex) {
        $rgb = $this->parse_color_to_rgb($hex);
        if (!is_array($rgb)) {
            return '#1f2937';
        }

        $r = $rgb[0];
        $g = $rgb[1];
        $b = $rgb[2];
        $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

        return $luminance >= 150 ? '#1f2937' : '#ffffff';
    }

    /**
     * Allow hex/rgb/rgba/transparent values for style colors.
     */
    private function sanitize_css_color_value($value, $fallback = '') {
        $value = trim((string) sanitize_text_field((string) $value));
        if ($value === '') {
            return $fallback;
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

        return $fallback;
    }

    /**
     * Parse CSS color to RGB array.
     */
    private function parse_color_to_rgb($value) {
        $value = trim((string) $value);
        $hex = sanitize_hex_color($value);
        if (!empty($hex)) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 6) {
                return [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ];
            }
        }

        if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(0|0?\.[0-9]+|1(?:\.0+)?))?\s*\)$/i', $value, $m)) {
            return [
                max(0, min(255, (int) $m[1])),
                max(0, min(255, (int) $m[2])),
                max(0, min(255, (int) $m[3])),
            ];
        }

        return null;
    }
    
    /**
     * Render no schedule message
     * 
     * @param string $type Type of message: no_product_selected, no_schedule_data, default
     * @return string
     */
    private function render_no_schedule_message($type = 'default') {
        $messages = [
            'no_variable_products' => 'Nu există produse variabile în WooCommerce. Administratorul trebuie să creeze produse variabile cu variații pentru cursuri.',
            'no_product_selected' => 'Nu este selectat nici un produs pentru orar. Administratorul trebuie să selecteze un produs variabil în setările pluginului.',
            'no_schedule_data' => 'Nu există încă cursuri programate pentru produsul selectat. Administratorul poate adăuga cursuri în setările pluginului.',
            'default' => 'Nu este configurat nici un orar. Vă rugăm să contactați administratorul.'
        ];
        
        $message = isset($messages[$type]) ? $messages[$type] : $messages['default'];
        
        return '<div class="oc-no-schedule">
                    <p>' . esc_html(__($message, OC_TEXT_DOMAIN)) . '</p>
                </div>';
    }
}
