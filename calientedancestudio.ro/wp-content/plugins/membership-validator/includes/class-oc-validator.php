<?php
/**
 * Validation class
 * 
 * Handles data validation for schedule entries,
 * settings, and user inputs.
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validation class
 */
class OC_Validator {
    
    /**
     * Validate schedule row data
     * 
     * @param array $row Schedule row data
     * @return array|WP_Error Validated row data or error
     */
    public function validate_schedule_row($row) {
        $errors = [];
        
        // Validate variation_id
        if (empty($row['variation_id']) || !is_numeric($row['variation_id'])) {
            $errors[] = __('ID-ul variației este obligatoriu și trebuie să fie numeric.', OC_TEXT_DOMAIN);
        } else {
            $variation_id = absint($row['variation_id']);
            $variation = wc_get_product($variation_id);
            if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
                $errors[] = __('Variația selectată nu există.', OC_TEXT_DOMAIN);
            }
        }
        
        // Validate weekday
        if (empty($row['weekday']) || !is_numeric($row['weekday'])) {
            $errors[] = __('Ziua săptămânii este obligatorie.', OC_TEXT_DOMAIN);
        } else {
            $weekday = absint($row['weekday']);
            if ($weekday < 1 || $weekday > 7) {
                $errors[] = __('Ziua săptămânii trebuie să fie între 1 (Luni) și 7 (Duminică).', OC_TEXT_DOMAIN);
            }
        }
        
        // Validate start_time
        if (empty($row['start_time'])) {
            $errors[] = __('Ora de început este obligatorie.', OC_TEXT_DOMAIN);
        } else {
            $start_time = $this->validate_time_format($row['start_time']);
            if (false === $start_time) {
                $errors[] = __('Ora de început are un format invalid. Folosește formatul HH:MM (24h).', OC_TEXT_DOMAIN);
            }
        }
        
        // Validate end_time
        if (empty($row['end_time'])) {
            $errors[] = __('Ora de sfârșit este obligatorie.', OC_TEXT_DOMAIN);
        } else {
            $end_time = $this->validate_time_format($row['end_time']);
            if (false === $end_time) {
                $errors[] = __('Ora de sfârșit are un format invalid. Folosește formatul HH:MM (24h).', OC_TEXT_DOMAIN);
            }
        }
        
        // Validate time range
        if (isset($start_time) && isset($end_time) && $start_time && $end_time) {
            if ($start_time >= $end_time) {
                $errors[] = __('Ora de început trebuie să fie înainte de ora de sfârșit.', OC_TEXT_DOMAIN);
            }
            
            // Check minimum duration (e.g., 30 minutes)
            $start_timestamp = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            $duration = $end_timestamp - $start_timestamp;
            
            if ($duration < 1800) { // 30 minutes
                $errors[] = __('Durata cursului trebuie să fie de cel puțin 30 de minute.', OC_TEXT_DOMAIN);
            }
            
            if ($duration > 14400) { // 4 hours
                $errors[] = __('Durata cursului nu poate depăși 4 ore.', OC_TEXT_DOMAIN);
            }
        }
        
        // Return errors if any
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        // Return sanitized data
        return [
            'variation_id' => absint($row['variation_id']),
            'weekday' => absint($row['weekday']),
            'start_time' => $start_time,
            'end_time' => $end_time,
            'room_number' => isset($row['room_number']) ? absint($row['room_number']) : 1
        ];
    }
    
    /**
     * Validate time format
     * 
     * @param string $time Time string
     * @return string|false Validated time or false
     */
    public function validate_time_format($time) {
        $time = sanitize_text_field($time);
        
        // Check format HH:MM
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return false;
        }
        
        // Ensure it's a valid time
        $time_parts = explode(':', $time);
        $hour = intval($time_parts[0]);
        $minute = intval($time_parts[1]);
        
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return false;
        }
        
        // Return in HH:MM format
        return sprintf('%02d:%02d', $hour, $minute);
    }
    
    /**
     * Validate weekday
     * 
     * @param mixed $weekday
     * @return int|false
     */
    public function validate_weekday($weekday) {
        $weekday = absint($weekday);
        
        if ($weekday >= 1 && $weekday <= 7) {
            return $weekday;
        }
        
        return false;
    }
    
    /**
     * Validate color value
     * 
     * @param string $color
     * @return string|false
     */
    public function validate_color($color) {
        $color = sanitize_hex_color($color);
        
        if ($color) {
            return $color;
        }
        
        // Also accept named colors
        $named_colors = [
            'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink',
            'black', 'white', 'gray', 'brown', 'cyan', 'magenta'
        ];
        
        $color_lower = strtolower(trim($color));
        if (in_array($color_lower, $named_colors)) {
            return $color_lower;
        }
        
        return false;
    }
    
    /**
     * Validate font size
     * 
     * @param string $size
     * @return string|false
     */
    public function validate_font_size($size) {
        $size = sanitize_text_field($size);
        
        // Accept px, em, rem, %
        if (preg_match('/^\d+(\.\d+)?(px|em|rem|%)$/', $size)) {
            return $size;
        }
        
        return false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url
     * @return string|false
     */
    public function validate_url($url) {
        $url = esc_url_raw($url);
        
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        return false;
    }
    
    /**
     * Validate taxonomy
     * 
     * @param string $taxonomy
     * @return string|false
     */
    public function validate_taxonomy($taxonomy) {
        $taxonomy = sanitize_key($taxonomy);
        
        // Check if it's a WooCommerce attribute taxonomy
        if (strpos($taxonomy, 'pa_') !== 0) {
            return false;
        }
        
        // Check if taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            return false;
        }
        
        return $taxonomy;
    }
    
    /**
     * Validate variation ID for specific product
     * 
     * @param int $variation_id
     * @param int $product_id
     * @return int|false
     */
    public function validate_variation_id($variation_id, $product_id = 0) {
        $variation_id = absint($variation_id);
        
        if ($variation_id <= 0) {
            return false;
        }
        
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
            return false;
        }
        
        // Optionally validate that variation belongs to the specific product
        if ($product_id > 0 && $variation->get_parent_id() !== $product_id) {
            return false;
        }
        
        return $variation_id;
    }
    
    /**
     * Validate shortcode attributes
     * 
     * @param array $atts
     * @return array Validated attributes
     */
    public function validate_shortcode_atts($atts) {
        $validated = [];
        
        // show_empty_days
        if (isset($atts['show_empty_days'])) {
            $validated['show_empty_days'] = filter_var(
                $atts['show_empty_days'], 
                FILTER_VALIDATE_BOOLEAN
            );
        }
        
        // class
        if (isset($atts['class'])) {
            $validated['class'] = sanitize_html_class($atts['class']);
        }
        
        return $validated;
    }
    
    /**
     * Validate schedule data for overlaps
     * 
     * @param array $schedule_rows
     * @return array Array of overlap errors
     */
    public function validate_schedule_overlaps($schedule_rows) {
        $overlaps = [];
        
        for ($i = 0; $i < count($schedule_rows); $i++) {
            for ($j = $i + 1; $j < count($schedule_rows); $j++) {
                $row1 = $schedule_rows[$i];
                $row2 = $schedule_rows[$j];
                
                // Check if same variation and weekday
                if ($row1['variation_id'] === $row2['variation_id'] && 
                    $row1['weekday'] === $row2['weekday']) {
                    
                    // Check time overlap
                    if ($this->times_overlap(
                        $row1['start_time'], $row1['end_time'],
                        $row2['start_time'], $row2['end_time']
                    )) {
                        $variation = wc_get_product($row1['variation_id']);
                        $weekday_name = $this->get_weekday_name($row1['weekday']);
                        
                        $overlaps[] = sprintf(
                            __('Suprapunere detectată pentru %s în ziua de %s între %s-%s și %s-%s.', OC_TEXT_DOMAIN),
                            $variation ? $variation->get_name() : 'N/A',
                            $weekday_name,
                            $row1['start_time'],
                            $row1['end_time'],
                            $row2['start_time'],
                            $row2['end_time']
                        );
                    }
                }
            }
        }
        
        return $overlaps;
    }
    
    /**
     * Check if two time ranges overlap
     * 
     * @param string $start1
     * @param string $end1
     * @param string $start2
     * @param string $end2
     * @return bool
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        $start1_ts = strtotime($start1);
        $end1_ts = strtotime($end1);
        $start2_ts = strtotime($start2);
        $end2_ts = strtotime($end2);
        
        return ($start1_ts < $end2_ts) && ($end1_ts > $start2_ts);
    }
    
    /**
     * Get weekday name by number
     * 
     * @param int $weekday
     * @return string
     */
    private function get_weekday_name($weekday) {
        $weekdays = [
            1 => __('Luni', OC_TEXT_DOMAIN),
            2 => __('Marți', OC_TEXT_DOMAIN),
            3 => __('Miercuri', OC_TEXT_DOMAIN),
            4 => __('Joi', OC_TEXT_DOMAIN),
            5 => __('Vineri', OC_TEXT_DOMAIN),
            6 => __('Sâmbătă', OC_TEXT_DOMAIN),
            7 => __('Duminică', OC_TEXT_DOMAIN)
        ];
        
        return $weekdays[$weekday] ?? '';
    }
    
    /**
     * Validate business hours
     * 
     * @param string $start_time
     * @param string $end_time
     * @return bool
     */
    public function validate_business_hours($start_time, $end_time) {
        $start_hour = intval(explode(':', $start_time)[0]);
        $end_hour = intval(explode(':', $end_time)[0]);
        
        // Typical business hours: 6 AM to 11 PM
        if ($start_hour < 6 || $end_hour > 23) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize CSS
     * 
     * @param string $css
     * @return string
     */
    public function sanitize_css($css) {
        // Remove potentially dangerous CSS
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/behaviour\s*:/i', '', $css);
        $css = preg_replace('/@import/i', '', $css);
        
        return wp_strip_all_tags($css);
    }
    
    /**
     * Validate and sanitize HTML class
     * 
     * @param string $class
     * @return string
     */
    public function validate_html_class($class) {
        $class = sanitize_html_class($class);
        
        // Remove any remaining invalid characters
        $class = preg_replace('/[^a-zA-Z0-9_-]/', '', $class);
        
        return $class;
    }
}
