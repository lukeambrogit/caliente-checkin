<?php
/**
 * Schedule AJAX Handler
 * 
 * Handles all AJAX requests for Schedule Manager ADD-ON.
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
 * Schedule AJAX Handler Class
 * 
 * Manages all AJAX operations for schedule management.
 */
class OC_Schedule_Ajax {
    
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
     * Initialize WordPress AJAX hooks
     */
    private function init_hooks() {
        // Schedule operations
        add_action('wp_ajax_oc_save_schedule', [$this, 'save_schedule']);
        add_action('wp_ajax_oc_save_flexible_schedule', [$this, 'save_flexible_schedule']);
        add_action('wp_ajax_oc_get_schedule', [$this, 'get_schedule']);
        add_action('wp_ajax_oc_delete_day_schedule', [$this, 'delete_day_schedule']);
        add_action('wp_ajax_oc_load_day_schedule', [$this, 'load_day_schedule']);
        add_action('wp_ajax_oc_get_schedule_html', [$this, 'ajax_get_schedule_html']);
        
        // Product and variation operations
        add_action('wp_ajax_oc_update_product', [$this, 'update_product']);
        add_action('wp_ajax_oc_get_terms', [$this, 'get_terms']);
        add_action('wp_ajax_oc_get_course_terms', [$this, 'get_course_terms']);
        
        // Settings operations
        add_action('wp_ajax_oc_update_schedule_title', [$this, 'update_schedule_title']);
        add_action('wp_ajax_oc_upload_background', [$this, 'upload_background']);
        add_action('wp_ajax_oc_get_background_settings', [$this, 'get_background_settings']);
    }
    
    /**
     * Save schedule via AJAX
     */
    public function save_schedule() {
        // Disable error reporting for clean JSON response
        ini_set('display_errors', 0);
        error_reporting(0);
        
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Get and validate input data
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $schedule_data = isset($_POST['schedule_data']) ? $_POST['schedule_data'] : '';
        
        if (empty($product_id)) {
            wp_send_json_error(__('ID produs invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        if (empty($schedule_data)) {
            wp_send_json_error(__('Nu există date de salvat.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Decode and process schedule data
        $decoded_data = json_decode(stripslashes($schedule_data), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Date JSON invalide.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Convert to format expected by service
        $schedule_rows = $this->process_schedule_data($decoded_data);
        
        // Save through service
        $result = $this->service->save_schedule($product_id, $schedule_rows);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        // Update selected product
        update_option('oc_selected_product', $product_id);
        
        wp_send_json_success([
            'message' => __('Orar salvat cu succes.', OC_TEXT_DOMAIN),
            'rows_saved' => count($schedule_rows),
            'product_id' => $product_id
        ]);
    }
    
    /**
     * Save flexible schedule via AJAX
     */
    public function save_flexible_schedule() {
        // Disable error reporting for clean JSON response
        ini_set('display_errors', 0);
        error_reporting(0);
        
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Get and validate schedule data
        $schedule_data = isset($_POST['schedule_data']) ? $_POST['schedule_data'] : '';
        
        if (empty($schedule_data)) {
            wp_send_json_error(__('Nu există date de salvat.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Decode JSON data
        $decoded_data = json_decode(stripslashes($schedule_data), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Date JSON invalide.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Get selected product
        $selected_product = get_option('oc_selected_product', 0);
        
        if (empty($selected_product)) {
            wp_send_json_error(__('Nici un produs selectat. Selectează un produs Pool din dropdown înainte de a salva.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Process and save
        $schedule_rows = $this->process_flexible_schedule_data($decoded_data);
        
        if (empty($schedule_rows)) {
            wp_send_json_error(__('Nu s-au putut procesa datele orarului.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Add product_id to each row (required by OC_Schedule entity validation)
        foreach ($schedule_rows as &$row) {
            $row['product_id'] = $selected_product;
        }
        
        $result = $this->service->save_schedule($selected_product, $schedule_rows);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success([
            'message' => __('Orar flexibil salvat cu succes.', OC_TEXT_DOMAIN),
            'rows_saved' => count($schedule_rows)
        ]);
    }
    
    /**
     * Get schedule data via AJAX (FIXED: like OLD structure with time_key)
     */
    public function get_schedule() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        $schedules = $this->service->get_schedules($product_id);
        
        // Convert to expected format for flexible admin (LIKE OLD STRUCTURE)
        $schedule_data = [];
        foreach ($schedules as $schedule) {
            // Format times and create time key
            $start_time = date('H:i', strtotime($schedule->get_start_time()));
            $end_time = date('H:i', strtotime($schedule->get_end_time()));
            $time_key = $start_time . '-' . $end_time;
            $weekday = $schedule->get_weekday();
            $room = $schedule->get_room_number();
            
            if (!isset($schedule_data[$time_key])) {
                $schedule_data[$time_key] = [];
            }
            
            if (!isset($schedule_data[$time_key][$weekday])) {
                $schedule_data[$time_key][$weekday] = [];
            }
            
            $schedule_data[$time_key][$weekday][$room] = [
                'variation_id' => $schedule->get_variation_id(),
                'variation_name' => $schedule->get_variation_name() ?? '',
                // Backward compatibility
                'term_id' => $schedule->get_variation_id(),
                'term_name' => $schedule->get_variation_name() ?? ''
            ];
        }
        
        wp_send_json_success($schedule_data);
    }
    
    /**
     * Update selected product via AJAX
     */
    public function update_product() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (empty($product_id)) {
            wp_send_json_error(__('ID produs invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Validate product exists and is variable
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(__('Produsul nu există sau nu este variabil.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Update selected product
        update_option('oc_selected_product', $product_id);
        
        // Get variations for the product
        $woocommerce = new OC_WooCommerce();
        $variations = $woocommerce->get_product_variations($product_id);
        
        wp_send_json_success([
            'message' => __('Produs actualizat cu succes.', OC_TEXT_DOMAIN),
            'product_id' => $product_id,
            'variations' => $variations
        ]);
    }
    
    /**
     * Get terms/variations for a product via AJAX
     */
    public function get_terms() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (empty($product_id)) {
            wp_send_json_error(__('ID produs invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        $woocommerce = new OC_WooCommerce();
        $variations = $woocommerce->get_product_variations($product_id);
        
        wp_send_json_success([
            'variations' => $variations
        ]);
    }
    
    /**
     * Get course terms via AJAX (alias for get_terms)
     */
    public function get_course_terms() {
        $this->get_terms();
    }
    
    /**
     * Update schedule title via AJAX
     */
    public function update_schedule_title() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        
        if (empty($title)) {
            wp_send_json_error(__('Titlul nu poate fi gol.', OC_TEXT_DOMAIN));
            return;
        }
        
        update_option('oc_schedule_title', $title);
        
        wp_send_json_success([
            'message' => __('Titlu actualizat cu succes.', OC_TEXT_DOMAIN),
            'title' => $title
        ]);
    }
    
    /**
     * Handle background image upload via AJAX
     */
    public function upload_background() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload = wp_handle_upload($_FILES['background_image'], ['test_form' => false]);
        
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
            return;
        }
        
        $image_url = $upload['url'];
        update_option('oc_background_image', $image_url);
        
        wp_send_json_success([
            'message' => __('Imagine încărcată cu succes.', OC_TEXT_DOMAIN),
            'url' => $image_url
        ]);
    }
    
    /**
     * Get background settings via AJAX
     */
    public function get_background_settings() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $settings = [
            'background_image' => get_option('oc_background_image', ''),
            'background_color' => get_option('oc_background_color', '#f7eee8'),
            'primary_color' => get_option('oc_primary_color', '#d48945'),
            'secondary_color' => get_option('oc_secondary_color', '#8d786b'),
            'text_color' => get_option('oc_text_color', '#333333')
        ];
        
        wp_send_json_success($settings);
    }
    
    /**
     * Delete day schedule via AJAX
     */
    public function delete_day_schedule() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;

        // Backward-compatible path: delete a single entry by ID.
        if ($entry_id > 0) {
            $result = $this->service->delete_schedule_entry($entry_id);

            if (!$result) {
                wp_send_json_error(__('Eroare la ștergerea intrării.', OC_TEXT_DOMAIN));
                return;
            }

            wp_send_json_success([
                'message' => __('Intrare ștearsă cu succes.', OC_TEXT_DOMAIN),
                'deleted_count' => 1,
            ]);
            return;
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            $product_id = absint(get_option('oc_selected_product', 0));
        }

        if ($product_id <= 0) {
            wp_send_json_error(__('ID produs invalid.', OC_TEXT_DOMAIN));
            return;
        }

        $weekday = $this->normalize_weekday_input($_POST['weekday'] ?? null);
        if ($weekday === null) {
            wp_send_json_error(__('Zi invalidă.', OC_TEXT_DOMAIN));
            return;
        }

        $deleted_count = 0;
        $all_schedules = $this->service->get_schedules($product_id);
        foreach ($all_schedules as $schedule) {
            if (!($schedule instanceof OC_Schedule)) {
                continue;
            }

            if ((int) $schedule->get_weekday() !== $weekday) {
                continue;
            }

            $schedule_id = (int) $schedule->get_id();
            if ($schedule_id <= 0) {
                continue;
            }

            if ($this->service->delete_schedule_entry($schedule_id)) {
                $deleted_count++;
            }
        }

        if ($deleted_count <= 0) {
            wp_send_json_error(__('Nu există intrări pentru această zi.', OC_TEXT_DOMAIN));
            return;
        }

        wp_send_json_success([
            'message' => __('Zi ștearsă cu succes.', OC_TEXT_DOMAIN),
            'deleted_count' => $deleted_count,
        ]);
    }

    /**
     * Normalize weekday input from JS (numeric or text) to DB format 0..6.
     *
     * @param mixed $raw_weekday
     * @return int|null
     */
    private function normalize_weekday_input($raw_weekday) {
        if (is_numeric($raw_weekday)) {
            $weekday = (int) $raw_weekday;
            if ($weekday === 7) {
                $weekday = 0;
            }
            return ($weekday >= 0 && $weekday <= 6) ? $weekday : null;
        }

        $weekday_key = sanitize_key((string) wp_unslash($raw_weekday));
        $weekday_map = [
            'duminica' => 0,
            'luni' => 1,
            'marti' => 2,
            'miercuri' => 3,
            'joi' => 4,
            'vineri' => 5,
            'sambata' => 6,
        ];

        return array_key_exists($weekday_key, $weekday_map) ? $weekday_map[$weekday_key] : null;
    }
    
    /**
     * Load day schedule via AJAX
     */
    public function load_day_schedule() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Token de securitate invalid.', OC_TEXT_DOMAIN));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $weekday = isset($_POST['weekday']) ? absint($_POST['weekday']) : 0;
        
        $day_schedules = [];
        $all_schedules = $this->service->get_schedules($product_id);
        
        foreach ($all_schedules as $schedule) {
            if ($schedule->get_weekday() === $weekday) {
                $day_schedules[] = $schedule->to_array();
            }
        }
        
        wp_send_json_success([
            'schedules' => $day_schedules,
            'day_name' => $weekday < 7 ? OC_Schedule::get_day_names()[$weekday] : ''
        ]);
    }
    
    /**
     * Get schedule HTML via AJAX (FIXED: identical to functional commit)
     */
    public function ajax_get_schedule_html() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_admin_nonce')) {
            wp_send_json_error(__('Verificarea de securitate a eșuat.', OC_TEXT_DOMAIN));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Nu aveți permisiuni suficiente.', OC_TEXT_DOMAIN));
            return;
        }
        
        try {
            // Get the shortcode output (like in original version)
            $html = do_shortcode('[orar_cursuri]');
            
            if (empty(trim($html))) {
                wp_send_json_success('<div style="text-align: center; padding: 40px; color: #999;"><em>Nu există date de afișat în orar.</em></div>');
            } else {
                // FIXED: Don't add toggle buttons - they already exist in template
                // Just wrap the schedule content in admin-preview class
                wp_send_json_success($html);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(__('Eroare la generarea orarului: ', OC_TEXT_DOMAIN) . $e->getMessage());
        }
    }
    
    /**
     * Verify AJAX nonce (FIXED: accepts both nonce formats like OLD structure)
     * 
     * @return bool True if nonce is valid
     */
    private function verify_nonce() {
        // Check both nonce formats like in OLD structure
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'oc_admin_nonce')) {
            return true;
        }
        
        if (isset($_POST['_ajax_nonce']) && wp_verify_nonce($_POST['_ajax_nonce'], 'oc_admin_nonce')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Process raw schedule data from form
     * 
     * @param array $raw_data Raw schedule data
     * @return array Processed schedule rows
     */
    private function process_schedule_data($raw_data) {
        $schedule_rows = [];
        
        foreach ($raw_data as $time_key => $time_data) {
            // Extract start and end time from time_key (format: "09:00-10:00")
            $time_parts = explode('-', $time_key);
            if (count($time_parts) !== 2) {
                continue; // Skip invalid time keys
            }
            
            $start_time = trim($time_parts[0]);
            $end_time = trim($time_parts[1]);
            
            // Process each weekday and room
            foreach ($time_data as $weekday => $rooms) {
                foreach ($rooms as $room => $course_data) {
                    if (!empty($course_data['variation_id'])) {
                        $schedule_rows[] = [
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'weekday' => intval($weekday),
                            'room_number' => intval($room),
                            'variation_id' => intval($course_data['variation_id'])
                        ];
                    }
                }
            }
        }
        
        return $schedule_rows;
    }
    
    /**
     * Process flexible schedule data
     * 
     * @param array $raw_data Raw flexible schedule data
     * @return array Processed schedule rows
     */
    private function process_flexible_schedule_data($raw_data) {
        $schedule_rows = [];
        
        foreach ($raw_data as $index => $day_data) {
            // FIX: JavaScript sends 'day' not 'weekday'!
            if (!isset($day_data['day']) || !isset($day_data['hours'])) {
                continue;
            }
            
            $weekday = intval($day_data['day']); // Changed from 'weekday' to 'day'
            
            foreach ($day_data['hours'] as $hour_index => $hour_data) {
                // Process each room
                $time_parts = explode('-', $hour_data['time']);
                $start_time = trim($time_parts[0] ?? '');
                $end_time = trim($time_parts[1] ?? '');
                
                // Check all 4 rooms
                for ($room = 1; $room <= 4; $room++) {
                    $room_key = 'room' . $room;
                    if (!empty($hour_data[$room_key])) {
                        $variation_id = intval($hour_data[$room_key]);
                        $schedule_rows[] = [
                            'weekday' => $weekday,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'room_number' => $room,
                            'variation_id' => $variation_id
                        ];
                    }
                }
            }
        }
        
        return $schedule_rows;
    }
    
    /**
     * Generate HTML for schedule display
     * 
     * @param array $schedule_by_days Schedule organized by days
     * @return string Generated HTML
     */
    private function generate_schedule_html($schedule_by_days) {
        $html = '<div class="oc-schedule-preview">';
        
        foreach ($schedule_by_days as $weekday => $day_data) {
            $html .= '<div class="oc-day-section">';
            $html .= '<h3>' . esc_html($day_data['day_name']) . '</h3>';
            
            if (!empty($day_data['entries'])) {
                $html .= '<ul class="oc-schedule-entries">';
                foreach ($day_data['entries'] as $schedule) {
                    $html .= '<li>';
                    $html .= '<strong>' . esc_html($schedule->get_time_range()) . '</strong> - ';
                    $html .= esc_html($schedule->get_variation_name());
                    $html .= ' <small>(Sala ' . $schedule->get_room_number() . ')</small>';
                    $html .= '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p><em>' . __('Nu există cursuri programate.', OC_TEXT_DOMAIN) . '</em></p>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
}
