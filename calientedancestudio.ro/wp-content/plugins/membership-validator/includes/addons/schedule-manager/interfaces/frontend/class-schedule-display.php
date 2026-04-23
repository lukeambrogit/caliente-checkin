<?php
/**
 * Schedule Frontend Display
 * 
 * Handles frontend display for Schedule Manager ADD-ON.
 * Extracted from OC_Frontend core class to follow modular architecture.
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
 * Schedule Frontend Display Class
 * 
 * Manages frontend display, shortcodes, and asset loading for Schedule Manager.
 */
class OC_Schedule_Display {
    
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
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Initialize frontend functionality
     * NOTE: Shortcodes are registered by OC_Frontend to avoid conflicts
     * This class provides the modular logic that OC_Frontend can use
     */
    public function init() {
        // Shortcode registration moved to OC_Frontend to avoid conflicts
        // This class serves as the modular backend for schedule functionality
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->should_load_assets()) {
            return;
        }

        // TEMPORARY: Use ORIGINAL assets from /assets/ until modular assets are working
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
        
        wp_enqueue_script(
            'oc-frontend-simple-script',
            OC_PLUGIN_URL . 'assets/frontend-simple.js',
            [], // No dependencies - pure vanilla JS for cache compatibility
            OC_PLUGIN_VERSION,
            true // Load in footer for better performance
        );
        
        // Add dynamic styles
        $this->add_dynamic_styles();
    }
    
    /**
     * Check if assets should be loaded on current page
     * 
     * @return bool True if assets should be loaded
     */
    private function should_load_assets() {
        global $post;
        
        // Always load on pages with schedule shortcode
        if ($post && has_shortcode($post->post_content, 'orar_cursuri')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'schedule_manager')) {
            return true;
        }
        
        // Load on specific pages (can be configured)
        $load_on_pages = apply_filters('oc_schedule_load_assets_on_pages', []);
        if (!empty($load_on_pages) && is_page($load_on_pages)) {
            return true;
        }
        
        // Don't load by default
        return false;
    }
    
    /**
     * Render schedule shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered HTML
     */
    public function render_schedule_shortcode($atts = [], $content = null) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'product_id' => 0,
            'show_empty_days' => 'false',
            'layout' => 'cards', // cards, table, grid
            'class' => '',
            'show_room' => 'true',
            'show_duration' => 'false',
            'mobile_layout' => 'cards'
        ], $atts, 'orar_cursuri');
        
        // Get product ID
        $product_id = intval($atts['product_id']);
        if (empty($product_id)) {
            $product_id = get_option('oc_selected_product', 0);
        }
        
        if (empty($product_id)) {
            return '<div class="oc-schedule-error">' . 
                   __('Nu este selectat nici un produs pentru afișarea orarului.', OC_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        // Get schedule data
        $schedule_by_days = $this->service->get_schedule_by_days($product_id);
        
        if (empty($schedule_by_days)) {
            return '<div class="oc-schedule-empty">' . 
                   __('Nu există cursuri programate momentan.', OC_TEXT_DOMAIN) . 
                   '</div>';
        }
        
        // Generate HTML based on layout
        return $this->render_schedule_html($schedule_by_days, $atts);
    }
    
    /**
     * Render schedule HTML
     * 
     * @param array $schedule_by_days Schedule data organized by days
     * @param array $atts Display attributes
     * @return string Rendered HTML
     */
    private function render_schedule_html($schedule_by_days, $atts) {
        $show_empty_days = ($atts['show_empty_days'] === 'true');
        $layout = $atts['layout'];
        $custom_class = sanitize_html_class($atts['class']);
        $show_room = ($atts['show_room'] === 'true');
        $show_duration = ($atts['show_duration'] === 'true');
        
        // Start building HTML
        $html = '<div class="oc-schedule-container ' . $custom_class . '" data-layout="' . esc_attr($layout) . '">';
        
        // Add schedule title
        $schedule_title = get_option('oc_schedule_title', __('Orarul Cursurilor', OC_TEXT_DOMAIN));
        if (!empty($schedule_title)) {
            $html .= '<div class="oc-schedule-header">';
            $html .= '<h2 class="oc-schedule-title">' . esc_html($schedule_title) . '</h2>';
            $html .= '</div>';
        }
        
        // Add layout-specific HTML
        switch ($layout) {
            case 'table':
                $html .= $this->render_table_layout($schedule_by_days, $atts);
                break;
            case 'grid':
                $html .= $this->render_grid_layout($schedule_by_days, $atts);
                break;
            case 'cards':
            default:
                $html .= $this->render_cards_layout($schedule_by_days, $atts);
                break;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render cards layout
     * 
     * @param array $schedule_by_days Schedule data
     * @param array $atts Display attributes
     * @return string Rendered HTML
     */
    private function render_cards_layout($schedule_by_days, $atts) {
        $show_room = ($atts['show_room'] === 'true');
        $show_duration = ($atts['show_duration'] === 'true');
        
        $html = '<div class="oc-schedule-cards">';
        
        // Desktop version
        $html .= '<div class="oc-desktop-only cards">';
        foreach ($schedule_by_days as $weekday => $day_data) {
            if (empty($day_data['entries'])) {
                continue;
            }
            
            foreach ($day_data['entries'] as $schedule) {
                $html .= '<div class="card" data-day="' . esc_attr($day_data['day_name']) . '">';
                $html .= '<div class="top">';
                $html .= '<span class="badge">' . esc_html($day_data['day_name']) . '</span>';
                $html .= '<span class="badge">' . esc_html($schedule->get_time_range()) . '</span>';
                $html .= '</div>';
                
                $html .= '<div class="rooms">';
                $html .= '<div class="room-entry">';
                $html .= '<span class="course-name">' . esc_html($schedule->get_variation_name()) . '</span>';
                
                if ($show_room) {
                    $html .= '<span class="room-info">Sala ' . $schedule->get_room_number() . '</span>';
                }
                
                if ($show_duration) {
                    $html .= '<span class="duration">' . $schedule->get_duration_minutes() . ' min</span>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        
        // Mobile version
        $html .= '<div class="oc-mobile-only cards">';
        foreach ($schedule_by_days as $weekday => $day_data) {
            if (empty($day_data['entries'])) {
                continue;
            }
            
            foreach ($day_data['entries'] as $schedule) {
                $html .= '<div class="card" data-day="' . esc_attr($day_data['day_name']) . '">';
                $html .= '<div class="top">';
                $html .= '<span class="badge">' . esc_html($day_data['day_name']) . '</span>';
                $html .= '<span class="badge">' . esc_html($schedule->get_time_range()) . '</span>';
                $html .= '</div>';
                
                $html .= '<div class="rooms">';
                $html .= '<div class="room-entry">';
                $html .= '<span class="course-name">' . esc_html($schedule->get_variation_name()) . '</span>';
                
                if ($show_room) {
                    $html .= '<span class="room-info">Sala ' . $schedule->get_room_number() . '</span>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render table layout
     * 
     * @param array $schedule_by_days Schedule data
     * @param array $atts Display attributes
     * @return string Rendered HTML
     */
    private function render_table_layout($schedule_by_days, $atts) {
        $show_room = ($atts['show_room'] === 'true');
        $show_duration = ($atts['show_duration'] === 'true');
        
        $html = '<div class="oc-schedule-table-container">';
        $html .= '<table class="oc-schedule-table">';
        
        // Table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>' . __('Ziua', OC_TEXT_DOMAIN) . '</th>';
        $html .= '<th>' . __('Ora', OC_TEXT_DOMAIN) . '</th>';
        $html .= '<th>' . __('Curs', OC_TEXT_DOMAIN) . '</th>';
        if ($show_room) {
            $html .= '<th>' . __('Sala', OC_TEXT_DOMAIN) . '</th>';
        }
        if ($show_duration) {
            $html .= '<th>' . __('Durată', OC_TEXT_DOMAIN) . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body
        $html .= '<tbody>';
        foreach ($schedule_by_days as $weekday => $day_data) {
            if (empty($day_data['entries'])) {
                continue;
            }
            
            foreach ($day_data['entries'] as $schedule) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($day_data['day_name']) . '</td>';
                $html .= '<td>' . esc_html($schedule->get_time_range()) . '</td>';
                $html .= '<td>' . esc_html($schedule->get_variation_name()) . '</td>';
                
                if ($show_room) {
                    $html .= '<td>Sala ' . $schedule->get_room_number() . '</td>';
                }
                
                if ($show_duration) {
                    $html .= '<td>' . $schedule->get_duration_minutes() . ' min</td>';
                }
                
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render grid layout
     * 
     * @param array $schedule_by_days Schedule data
     * @param array $atts Display attributes
     * @return string Rendered HTML
     */
    private function render_grid_layout($schedule_by_days, $atts) {
        $show_room = ($atts['show_room'] === 'true');
        
        $html = '<div class="oc-schedule-grid">';
        
        foreach ($schedule_by_days as $weekday => $day_data) {
            if (empty($day_data['entries'])) {
                continue;
            }
            
            $html .= '<div class="oc-day-card">';
            $html .= '<div class="oc-day-header">';
            $html .= '<h3 class="oc-day-name">' . esc_html($day_data['day_name']) . '</h3>';
            $html .= '<span class="oc-day-badge">' . count($day_data['entries']) . ' cursuri</span>';
            $html .= '</div>';
            
            $html .= '<div class="oc-courses-list">';
            foreach ($day_data['entries'] as $schedule) {
                $html .= '<div class="oc-course-item">';
                $html .= '<div class="oc-course-time">' . esc_html($schedule->get_time_range()) . '</div>';
                $html .= '<div class="oc-course-name">' . esc_html($schedule->get_variation_name()) . '</div>';
                
                if ($show_room) {
                    $html .= '<div class="oc-course-details">';
                    $html .= '<span class="oc-course-room">📍 Sala ' . $schedule->get_room_number() . '</span>';
                    $html .= '<span class="oc-course-duration">⏱️ ' . $schedule->get_duration_minutes() . ' min</span>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add dynamic styles to page
     */
    private function add_dynamic_styles() {
        $primary_color = get_option('oc_primary_color', '#d48945');
        $secondary_color = get_option('oc_secondary_color', '#8d786b');
        $background_color = get_option('oc_background_color', '#f7eee8');
        $text_color = get_option('oc_text_color', '#333333');
        $background_image = get_option('oc_background_image', '');
        $custom_css = get_option('oc_custom_css', '');
        
        $dynamic_css = "
        :root {
            --oc-primary-color: {$primary_color};
            --oc-secondary-color: {$secondary_color};
            --oc-background-color: {$background_color};
            --oc-text-color: {$text_color};
        }
        
        .oc-schedule-container {
            background-color: var(--oc-background-color);
            color: var(--oc-text-color);
        }
        
        .oc-schedule-title {
            color: var(--oc-primary-color);
        }
        
        .badge {
            background-color: var(--oc-primary-color);
        }
        
        .oc-day-badge {
            background-color: var(--oc-secondary-color);
        }
        ";
        
        if (!empty($background_image)) {
            $dynamic_css .= "
            .oc-schedule-container {
                background-image: url('{$background_image}');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
            }
            ";
        }
        
        if (!empty($custom_css)) {
            $dynamic_css .= "\n" . $custom_css;
        }
        
        wp_add_inline_style('oc-schedule-frontend-style', $dynamic_css);
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
