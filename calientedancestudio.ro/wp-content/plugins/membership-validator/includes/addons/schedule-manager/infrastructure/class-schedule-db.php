<?php
/**
 * Schedule Database Layer
 * 
 * Handles all database operations for Schedule Manager ADD-ON.
 * Extracted from OC_DB core class to follow modular architecture.
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
 * Schedule Database Management Class
 * 
 * Manages database operations for schedule data including
 * table creation, CRUD operations, and data validation.
 */
class OC_Schedule_DB {
    
    /**
     * WordPress database object
     * 
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Schedule table name
     * 
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'orar_cursuri';
    }
    
    /**
     * Save complete schedule for a product
     * 
     * Deletes existing schedule and inserts new rows in a transaction.
     * This ensures data consistency and prevents partial updates.
     * 
     * @param int $product_id WooCommerce product ID
     * @param array $schedule_rows Array of schedule rows to save
     * @return bool|WP_Error Success status or error object
     */
    public function save_schedule($product_id, $schedule_rows) {
        if (empty($product_id) || !is_array($schedule_rows)) {
            return new WP_Error('invalid_data', __('Date invalide pentru salvarea orarului.', OC_TEXT_DOMAIN));
        }
        
        // IMPORTANT: Prevent accidental deletion of all schedule data
        if (empty($schedule_rows)) {
            return new WP_Error('empty_schedule', __('Nu se poate salva un orar gol. Datele existente au fost păstrate.', OC_TEXT_DOMAIN));
        }
        
        // Start database transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Delete existing schedule for this product
            $deleted = $this->wpdb->delete(
                $this->table_name,
                ['product_id' => $product_id],
                ['%d']
            );
            
            if (false === $deleted) {
                throw new Exception(__('Eroare la ștergerea orarului existent.', OC_TEXT_DOMAIN));
            }
            
            // Insert new schedule rows
            $inserted_count = 0;
            foreach ($schedule_rows as $index => $row) {
                $result = $this->insert_schedule_row($product_id, $row);
                if ($result) {
                    $inserted_count++;
                } else {
                    throw new Exception(__('Eroare la inserarea rândului de orar.', OC_TEXT_DOMAIN));
                }
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            
            // Fire action hook for other systems to respond
            // 🎯 v1.4.0: Transmite și schedule_rows pentru activare PENDING memberships
            do_action('oc_schedule_saved', $product_id, $inserted_count, $schedule_rows);
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('save_failed', $e->getMessage());
        }
    }
    
    /**
     * Insert a single schedule row
     * 
     * @param int $product_id WooCommerce product ID
     * @param array $row Schedule row data
     * @return bool Success status
     */
    private function insert_schedule_row($product_id, $row) {
        // Ensure room_number has a default value
        $room_number = isset($row['room_number']) ? absint($row['room_number']) : 1;
        
        // Validate required fields
        if (empty($product_id)) {
            throw new Exception(__('ID produs este obligatoriu.', OC_TEXT_DOMAIN));
        }
        
        if (empty($row['variation_id'])) {
            throw new Exception(__('ID variație este obligatoriu.', OC_TEXT_DOMAIN));
        }
        
        if (empty($row['start_time']) || empty($row['end_time'])) {
            throw new Exception(__('Ora de început și sfârșit sunt obligatorii.', OC_TEXT_DOMAIN));
        }
        
        $data = [
            'product_id' => absint($product_id),
            'variation_id' => absint($row['variation_id']),
            'weekday' => absint($row['weekday']),
            'start_time' => sanitize_text_field($row['start_time']),
            'end_time' => sanitize_text_field($row['end_time']),
            'room_number' => $room_number,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $formats = ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s'];
        
        $result = $this->wpdb->insert($this->table_name, $data, $formats);
        
        return false !== $result;
    }
    
    /**
     * Get schedule data for a product
     * 
     * @param int $product_id WooCommerce product ID (0 for default product)
     * @param bool $use_cache Whether to use caching (future feature)
     * @return array Schedule rows with enhanced data
     */
    public function get_schedule($product_id = 0, $use_cache = true) {
        if (empty($product_id)) {
            $product_id = get_option('oc_selected_product', 0);
        }
        
        if (empty($product_id)) {
            return [];
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE product_id = %d 
             ORDER BY weekday ASC, start_time ASC, room_number ASC",
            $product_id
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if (null === $results) {
            $results = [];
        }
        
        // Enhance with variation data
        return $this->enhance_with_variation_data($results);
    }
    
    /**
     * Enhance schedule data with WooCommerce variation information
     * 
     * @param array $schedule_rows Raw schedule rows from database
     * @return array Enhanced schedule rows with variation names
     */
    private function enhance_with_variation_data($schedule_rows) {
        if (empty($schedule_rows)) {
            return [];
        }
        
        foreach ($schedule_rows as &$row) {
            if (!empty($row['variation_id'])) {
                $variation = wc_get_product($row['variation_id']);
                if ($variation && $variation->exists()) {
                    // Get clean variation name (without parent product name)
                    $variation_name = $variation->get_name();
                    $parent_name = $variation->get_parent_id() ? get_the_title($variation->get_parent_id()) : '';
                    
                    // Remove parent name from variation name if present
                    if ($parent_name && strpos($variation_name, $parent_name) === 0) {
                        $variation_name = trim(str_replace($parent_name, '', $variation_name), ' -');
                    }
                    
                    $row['variation_name'] = $variation_name;
                    $row['parent_product_id'] = $variation->get_parent_id();
                } else {
                    $row['variation_name'] = __('Variație indisponibilă', OC_TEXT_DOMAIN);
                    $row['parent_product_id'] = 0;
                }
            }
        }
        
        return $schedule_rows;
    }
    
    /**
     * Delete a specific schedule row
     * 
     * @param int $row_id Schedule row ID
     * @return bool Success status
     */
    public function delete_schedule_row($row_id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => absint($row_id)],
            ['%d']
        );
        
        if (false !== $result) {
            do_action('oc_schedule_row_deleted', $row_id);
        }
        
        return false !== $result;
    }
    
    /**
     * Check for schedule time overlaps
     * 
     * Prevents double-booking by checking if a new schedule entry
     * conflicts with existing entries for the same variation.
     * 
     * @param int $product_id WooCommerce product ID
     * @param int $variation_id WooCommerce variation ID
     * @param int $weekday Day of week (0-6, 0=Sunday)
     * @param string $start_time Start time (HH:MM format)
     * @param string $end_time End time (HH:MM format)
     * @param int $exclude_id Row ID to exclude from check (for updates)
     * @return bool True if overlap exists
     */
    public function has_schedule_overlap($product_id, $variation_id, $weekday, $start_time, $end_time, $exclude_id = 0) {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE product_id = %d 
             AND variation_id = %d 
             AND weekday = %d 
             AND id != %d
             AND (
                 (start_time <= %s AND end_time > %s) OR
                 (start_time < %s AND end_time >= %s) OR
                 (start_time >= %s AND end_time <= %s)
             )
             LIMIT 1",
            $product_id,
            $variation_id,
            $weekday,
            $exclude_id,
            $start_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        );
        
        $result = $this->wpdb->get_var($sql);
        
        return !empty($result);
    }
    
    /**
     * Get statistics for schedule
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Schedule statistics
     */
    public function get_schedule_stats($product_id = 0) {
        if (empty($product_id)) {
            $product_id = get_option('oc_selected_product', 0);
        }
        
        if (empty($product_id)) {
            return [
                'total_entries' => 0,
                'total_days' => 0,
                'total_hours' => 0,
                'rooms_used' => 0
            ];
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_entries,
                COUNT(DISTINCT weekday) as total_days,
                COUNT(DISTINCT room_number) as rooms_used,
                SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))) / 3600 as total_hours
             FROM {$this->table_name} 
             WHERE product_id = %d",
            $product_id
        );
        
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        
        return [
            'total_entries' => intval($result['total_entries']),
            'total_days' => intval($result['total_days']),
            'total_hours' => round(floatval($result['total_hours']), 2),
            'rooms_used' => intval($result['rooms_used'])
        ];
    }
    
    /**
     * Get table name
     * 
     * @return string Full table name with prefix
     */
    public function get_table_name() {
        return $this->table_name;
    }
    
    /**
     * Check if schedule table exists
     * 
     * @return bool True if table exists
     */
    public function table_exists() {
        $table_name = $this->get_table_name();
        $result = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $result === $table_name;
    }
    
    /**
     * Create schedule table if it doesn't exist
     * 
     * @return bool Success status
     */
    public function ensure_table_exists() {
        if ($this->table_exists()) {
            return true;
        }
        
        // Table creation is handled by OC_DB core class
        // This method is for future use when we fully decouple
        return false;
    }
}
