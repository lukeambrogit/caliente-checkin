<?php
/**
 * Schedule Repository
 * 
 * Repository pattern implementation for Schedule Manager.
 * Provides a clean interface for schedule data operations.
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
 * Schedule Repository Class
 * 
 * Implements repository pattern for schedule data.
 * Acts as a bridge between domain logic and database layer.
 */
class OC_Schedule_Repository {
    
    /**
     * Database layer instance
     * 
     * @var OC_Schedule_DB
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new OC_Schedule_DB();
    }
    
    /**
     * Find schedule by product ID
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Schedule entries
     */
    public function find_by_product($product_id) {
        return $this->db->get_schedule($product_id);
    }
    
    /**
     * Find all schedules
     * 
     * @return array All schedule entries
     */
    public function find_all() {
        $selected_product = get_option('oc_selected_product', 0);
        return $this->find_by_product($selected_product);
    }
    
    /**
     * Save schedule for a product
     * 
     * @param int $product_id WooCommerce product ID
     * @param array $schedule_data Schedule entries to save
     * @return bool|WP_Error Success status or error
     */
    public function save($product_id, $schedule_data) {
        return $this->db->save_schedule($product_id, $schedule_data);
    }
    
    /**
     * Delete schedule entry
     * 
     * @param int $entry_id Schedule entry ID
     * @return bool Success status
     */
    public function delete($entry_id) {
        return $this->db->delete_schedule_row($entry_id);
    }
    
    /**
     * Check for time conflicts
     * 
     * @param int $product_id WooCommerce product ID
     * @param int $variation_id WooCommerce variation ID
     * @param int $weekday Day of week (0-6)
     * @param string $start_time Start time (HH:MM)
     * @param string $end_time End time (HH:MM)
     * @param int $exclude_id Entry ID to exclude from check
     * @return bool True if conflict exists
     */
    public function has_time_conflict($product_id, $variation_id, $weekday, $start_time, $end_time, $exclude_id = 0) {
        return $this->db->has_schedule_overlap($product_id, $variation_id, $weekday, $start_time, $end_time, $exclude_id);
    }
    
    /**
     * Get schedule statistics
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Statistics array
     */
    public function get_statistics($product_id = 0) {
        return $this->db->get_schedule_stats($product_id);
    }
    
    /**
     * Find entries by day
     * 
     * @param int $product_id WooCommerce product ID
     * @param int $weekday Day of week (0-6)
     * @return array Schedule entries for specific day
     */
    public function find_by_day($product_id, $weekday) {
        $all_entries = $this->find_by_product($product_id);
        
        return array_filter($all_entries, function($entry) use ($weekday) {
            return intval($entry['weekday']) === intval($weekday);
        });
    }
    
    /**
     * Find entries by room
     * 
     * @param int $product_id WooCommerce product ID
     * @param int $room_number Room number
     * @return array Schedule entries for specific room
     */
    public function find_by_room($product_id, $room_number) {
        $all_entries = $this->find_by_product($product_id);
        
        return array_filter($all_entries, function($entry) use ($room_number) {
            return intval($entry['room_number']) === intval($room_number);
        });
    }
    
    /**
     * Find entries by time range
     * 
     * @param int $product_id WooCommerce product ID
     * @param string $start_time Start time (HH:MM)
     * @param string $end_time End time (HH:MM)
     * @return array Schedule entries within time range
     */
    public function find_by_time_range($product_id, $start_time, $end_time) {
        $all_entries = $this->find_by_product($product_id);
        
        return array_filter($all_entries, function($entry) use ($start_time, $end_time) {
            return $entry['start_time'] >= $start_time && $entry['end_time'] <= $end_time;
        });
    }
    
    /**
     * Count entries by criteria
     * 
     * @param int $product_id WooCommerce product ID
     * @param array $criteria Filter criteria
     * @return int Number of matching entries
     */
    public function count_by_criteria($product_id, $criteria = []) {
        $entries = $this->find_by_product($product_id);
        
        if (empty($criteria)) {
            return count($entries);
        }
        
        $filtered = array_filter($entries, function($entry) use ($criteria) {
            foreach ($criteria as $field => $value) {
                if (isset($entry[$field]) && $entry[$field] !== $value) {
                    return false;
                }
            }
            return true;
        });
        
        return count($filtered);
    }
    
    /**
     * Check if repository is ready
     * 
     * @return bool True if database layer is available
     */
    public function is_ready() {
        return $this->db->table_exists();
    }
}
