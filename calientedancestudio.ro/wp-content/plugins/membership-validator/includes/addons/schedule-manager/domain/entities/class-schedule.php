<?php
/**
 * Schedule Entity
 * 
 * Domain entity representing a schedule entry.
 * Encapsulates schedule data and behavior following domain-driven design.
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
 * Schedule Entity Class
 * 
 * Represents a single schedule entry with validation and business logic.
 */
class OC_Schedule {
    
    /**
     * Schedule entry ID
     * 
     * @var int
     */
    private $id;
    
    /**
     * WooCommerce product ID
     * 
     * @var int
     */
    private $product_id;
    
    /**
     * WooCommerce variation ID
     * 
     * @var int
     */
    private $variation_id;
    
    /**
     * Day of week (0-6, 0=Sunday)
     * 
     * @var int
     */
    private $weekday;
    
    /**
     * Start time (HH:MM format)
     * 
     * @var string
     */
    private $start_time;
    
    /**
     * End time (HH:MM format)
     * 
     * @var string
     */
    private $end_time;
    
    /**
     * Room number
     * 
     * @var int
     */
    private $room_number;
    
    /**
     * Variation name (cached)
     * 
     * @var string
     */
    private $variation_name;
    
    /**
     * Created timestamp
     * 
     * @var string
     */
    private $created_at;
    
    /**
     * Updated timestamp
     * 
     * @var string
     */
    private $updated_at;
    
    /**
     * Day names mapping
     * 
     * @var array
     */
    private static $day_names = [
        0 => 'Duminică',
        1 => 'Luni',
        2 => 'Marți',
        3 => 'Miercuri',
        4 => 'Joi',
        5 => 'Vineri',
        6 => 'Sâmbătă'
    ];
    
    /**
     * Constructor
     * 
     * @param array $data Schedule data array
     */
    public function __construct($data = []) {
        $this->id = isset($data['id']) ? intval($data['id']) : 0;
        $this->product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
        $this->variation_id = isset($data['variation_id']) ? intval($data['variation_id']) : 0;
        
        // Normalizare weekday: convertim 7 (duminică) în 0 pentru compatibilitate ISO 8601
        $weekday = isset($data['weekday']) ? intval($data['weekday']) : 0;
        $this->weekday = ($weekday === 7) ? 0 : $weekday;
        
        $this->start_time = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '';
        $this->end_time = isset($data['end_time']) ? sanitize_text_field($data['end_time']) : '';
        $this->room_number = isset($data['room_number']) ? intval($data['room_number']) : 1;
        $this->variation_name = isset($data['variation_name']) ? sanitize_text_field($data['variation_name']) : '';
        $this->created_at = isset($data['created_at']) ? $data['created_at'] : '';
        $this->updated_at = isset($data['updated_at']) ? $data['updated_at'] : '';
    }
    
    /**
     * Get schedule ID
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get product ID
     * 
     * @return int
     */
    public function get_product_id() {
        return $this->product_id;
    }
    
    /**
     * Get variation ID
     * 
     * @return int
     */
    public function get_variation_id() {
        return $this->variation_id;
    }
    
    /**
     * Get weekday
     * 
     * @return int
     */
    public function get_weekday() {
        return $this->weekday;
    }
    
    /**
     * Get weekday name
     * 
     * @return string
     */
    public function get_weekday_name() {
        return isset(self::$day_names[$this->weekday]) ? self::$day_names[$this->weekday] : '';
    }
    
    /**
     * Get start time
     * 
     * @return string
     */
    public function get_start_time() {
        return $this->start_time;
    }
    
    /**
     * Get end time
     * 
     * @return string
     */
    public function get_end_time() {
        return $this->end_time;
    }
    
    /**
     * Get room number
     * 
     * @return int
     */
    public function get_room_number() {
        return $this->room_number;
    }
    
    /**
     * Get variation name
     * 
     * @return string
     */
    public function get_variation_name() {
        return $this->variation_name;
    }
    
    /**
     * Get duration in minutes
     * 
     * @return int Duration in minutes
     */
    public function get_duration_minutes() {
        if (empty($this->start_time) || empty($this->end_time)) {
            return 0;
        }
        
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);
        
        return round(($end - $start) / 60);
    }
    
    /**
     * Get formatted time range
     * 
     * @return string Formatted time range (e.g., "09:00 - 10:00")
     */
    public function get_time_range() {
        return $this->start_time . ' - ' . $this->end_time;
    }
    
    /**
     * Check if schedule entry is valid
     * 
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate() {
        $errors = [];
        
        // Validate product ID
        if (empty($this->product_id)) {
            $errors[] = __('ID produs este obligatoriu.', OC_TEXT_DOMAIN);
        }
        
        // Validate variation ID
        if (empty($this->variation_id)) {
            $errors[] = __('ID variație este obligatoriu.', OC_TEXT_DOMAIN);
        }
        
        // Validate weekday
        if ($this->weekday < 0 || $this->weekday > 6) {
            $errors[] = __('Ziua săptămânii trebuie să fie între 0 și 6.', OC_TEXT_DOMAIN);
        }
        
        // Validate times
        if (empty($this->start_time) || empty($this->end_time)) {
            $errors[] = __('Ora de început și sfârșit sunt obligatorii.', OC_TEXT_DOMAIN);
        } else {
            // Check time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $this->start_time)) {
                $errors[] = __('Ora de început are format invalid.', OC_TEXT_DOMAIN);
            }
            
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $this->end_time)) {
                $errors[] = __('Ora de sfârșit are format invalid.', OC_TEXT_DOMAIN);
            }
            
            // Check that end time is after start time
            if (strtotime($this->end_time) <= strtotime($this->start_time)) {
                $errors[] = __('Ora de sfârșit trebuie să fie după ora de început.', OC_TEXT_DOMAIN);
            }
        }
        
        // Validate room number
        if ($this->room_number < 1 || $this->room_number > 10) {
            $errors[] = __('Numărul sălii trebuie să fie între 1 și 10.', OC_TEXT_DOMAIN);
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        return true;
    }
    
    /**
     * Check if this schedule conflicts with another
     * 
     * @param OC_Schedule $other Other schedule to check against
     * @return bool True if conflicts exist
     */
    public function conflicts_with(OC_Schedule $other) {
        // Different days don't conflict
        if ($this->weekday !== $other->get_weekday()) {
            return false;
        }
        
        // Different rooms don't conflict
        if ($this->room_number !== $other->get_room_number()) {
            return false;
        }
        
        // Check time overlap
        $this_start = strtotime($this->start_time);
        $this_end = strtotime($this->end_time);
        $other_start = strtotime($other->get_start_time());
        $other_end = strtotime($other->get_end_time());
        
        return ($this_start < $other_end && $this_end > $other_start);
    }
    
    /**
     * Convert to array
     * 
     * @return array Schedule data as array
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variation_id' => $this->variation_id,
            'weekday' => $this->weekday,
            'weekday_name' => $this->get_weekday_name(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'time_range' => $this->get_time_range(),
            'room_number' => $this->room_number,
            'variation_name' => $this->variation_name,
            'duration_minutes' => $this->get_duration_minutes(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
    
    /**
     * Create schedule from array data
     * 
     * @param array $data Schedule data
     * @return OC_Schedule New schedule instance
     */
    public static function from_array($data) {
        return new self($data);
    }
    
    /**
     * Get day names array
     * 
     * @return array Day names mapping
     */
    public static function get_day_names() {
        return self::$day_names;
    }
}
