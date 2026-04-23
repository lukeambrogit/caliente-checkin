<?php
/**
 * Database operations class - Core functionality only
 * 
 * Handles core database operations including table creation.
 * Schedule Manager database operations moved to Schedule Manager ADD-ON.
 * 
 * @package MembershipValidatorCore
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Database management class
 * Schedule Manager database operations moved to modular ADD-ON structure
 */
class OC_DB {
    
    /**
     * Table name for schedule (still maintained for compatibility)
     * 
     * @var string
     */
    private $table_name;
    
    /**
     * WordPress database object
     * 
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'orar_cursuri';
        
        // Auto-check and create tables if needed
        $this->ensure_tables_exist();
    }
    
    /**
     * Ensure tables exist - auto-check and create if needed
     * Schedule table is still created here for backward compatibility
     * but operations are handled by Schedule Manager ADD-ON
     * 
     * @return bool Success status
     */
    public function ensure_tables_exist() {
        // Check if schedule table exists
        $table_name = $this->get_table_name();
        $sql = $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
        $table_exists = $this->wpdb->get_var($sql);
        
        if ($table_exists != $table_name) {
            // Create schedule table
            $result = $this->create_schedule_table();
            if (!$result) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    oc_log_debug('[OC_DB] Failed to create schedule table');
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create schedule table
     * Table structure maintained but operations handled by Schedule Manager ADD-ON
     * 
     * @return bool Success status
     */
    public function create_schedule_table() {
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL,
            weekday tinyint(1) unsigned NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            room_number tinyint(2) unsigned NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY weekday (weekday),
            KEY time_range (start_time, end_time),
            KEY room_number (room_number)
        ) {$this->wpdb->get_charset_collate()};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $table_exists = $this->table_exists();
        if ($table_exists && defined('WP_DEBUG') && WP_DEBUG) {
            oc_log_debug('[OC_DB] Schedule table created successfully');
        }

        return $table_exists;
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
     * Force check and create tables if needed (public method)
     * 
     * @return bool Success status
     */
    public function force_check_tables() {
        return $this->ensure_tables_exist();
    }
    
    /**
     * Create tables - Alias for backward compatibility
     * Called by orar-cursuri.php on plugin activation
     * 
     * @return bool Success status
     */
    public function create_tables() {
        return $this->ensure_tables_exist();
    }
    
    /**
     * Get database prefix
     * 
     * @return string Database prefix
     */
    public function get_prefix() {
        return $this->wpdb->prefix;
    }
    
    /**
     * Check if table exists
     * 
     * @param string $table_name Table name to check
     * @return bool True if table exists
     */
    public function table_exists($table_name = null) {
        if ($table_name === null) {
            $table_name = $this->table_name;
        }
        
        $sql = $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
        $result = $this->wpdb->get_var($sql);
        
        return $result === $table_name;
    }
    
    /**
     * Get schedule data for a product (RESTORED: for compatibility with frontend)
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
        foreach ($results as &$row) {
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
        
        return $results;
    }
}
