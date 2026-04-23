<?php
/**
 * Schedule Service
 * 
 * Business logic service for Schedule Manager.
 * Orchestrates schedule operations and business rules.
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
 * Schedule Service Class
 * 
 * Contains business logic for schedule management.
 * Coordinates between repositories and entities.
 */
class OC_Schedule_Service {
    
    /**
     * Schedule repository
     * 
     * @var OC_Schedule_Repository
     */
    private $repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new OC_Schedule_Repository();
    }
    
    /**
     * Get all schedules for a product
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Array of OC_Schedule entities
     */
    public function get_schedules($product_id = 0) {
        $data = $this->repository->find_by_product($product_id);
        $schedules = [];
        
        foreach ($data as $row) {
            $schedules[] = OC_Schedule::from_array($row);
        }
        
        return $schedules;
    }
    
    /**
     * Save schedule for a product
     * 
     * @param int $product_id WooCommerce product ID
     * @param array $schedule_data Raw schedule data from form
     * @return bool|WP_Error Success status or error
     */
    public function save_schedule($product_id, $schedule_data) {
        // Validate product ID
        if (empty($product_id)) {
            return new WP_Error('invalid_product', __('ID produs invalid.', OC_TEXT_DOMAIN));
        }
        
        // Convert raw data to schedule entities for validation
        $schedules = [];
        foreach ($schedule_data as $row) {
            $schedule = OC_Schedule::from_array($row);
            $validation = $schedule->validate();
            
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            $schedules[] = $schedule;
        }
        
        // Check for conflicts within the new schedule
        $conflict_check = $this->check_internal_conflicts($schedules);
        if (is_wp_error($conflict_check)) {
            return $conflict_check;
        }
        
        // Save to repository
        return $this->repository->save($product_id, $schedule_data);
    }
    
    /**
     * Delete schedule entry
     * 
     * @param int $entry_id Schedule entry ID
     * @return bool Success status
     */
    public function delete_schedule_entry($entry_id) {
        if (empty($entry_id)) {
            return false;
        }
        
        return $this->repository->delete($entry_id);
    }
    
    /**
     * Get schedule organized by days
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Schedule organized by weekdays
     */
    public function get_schedule_by_days($product_id = 0) {
        $schedules = $this->get_schedules($product_id);
        $organized = [];
        
        foreach ($schedules as $schedule) {
            $day = $schedule->get_weekday();
            if (!isset($organized[$day])) {
                $organized[$day] = [
                    'day_name' => $schedule->get_weekday_name(),
                    'entries' => []
                ];
            }
            $organized[$day]['entries'][] = $schedule;
        }
        
        // Sort by weekday
        ksort($organized);
        
        // Sort entries within each day by start time
        foreach ($organized as &$day_data) {
            usort($day_data['entries'], function($a, $b) {
                return strcmp($a->get_start_time(), $b->get_start_time());
            });
        }
        
        return $organized;
    }
    
    /**
     * Get schedule organized by rooms
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Schedule organized by rooms
     */
    public function get_schedule_by_rooms($product_id = 0) {
        $schedules = $this->get_schedules($product_id);
        $organized = [];
        
        foreach ($schedules as $schedule) {
            $room = $schedule->get_room_number();
            if (!isset($organized[$room])) {
                $organized[$room] = [];
            }
            $organized[$room][] = $schedule;
        }
        
        // Sort by room number
        ksort($organized);
        
        return $organized;
    }
    
    /**
     * Get schedule statistics
     * 
     * @param int $product_id WooCommerce product ID
     * @return array Detailed statistics
     */
    public function get_statistics($product_id = 0) {
        $basic_stats = $this->repository->get_statistics($product_id);
        $schedules = $this->get_schedules($product_id);
        
        // Calculate additional statistics
        $variations_used = [];
        $peak_hours = [];
        
        foreach ($schedules as $schedule) {
            $variations_used[$schedule->get_variation_id()] = $schedule->get_variation_name();
            
            $hour = intval(substr($schedule->get_start_time(), 0, 2));
            if (!isset($peak_hours[$hour])) {
                $peak_hours[$hour] = 0;
            }
            $peak_hours[$hour]++;
        }
        
        // Find peak hour
        $peak_hour = !empty($peak_hours) ? array_keys($peak_hours, max($peak_hours))[0] : 0;
        
        return array_merge($basic_stats, [
            'unique_variations' => count($variations_used),
            'variations_list' => array_values($variations_used),
            'peak_hour' => $peak_hour . ':00',
            'peak_hour_count' => !empty($peak_hours) ? max($peak_hours) : 0
        ]);
    }
    
    /**
     * Check for conflicts within a set of schedules
     * 
     * @param array $schedules Array of OC_Schedule entities
     * @return bool|WP_Error True if no conflicts, WP_Error if conflicts found
     */
    private function check_internal_conflicts($schedules) {
        for ($i = 0; $i < count($schedules); $i++) {
            for ($j = $i + 1; $j < count($schedules); $j++) {
                if ($schedules[$i]->conflicts_with($schedules[$j])) {
                    return new WP_Error(
                        'schedule_conflict',
                        sprintf(
                            __('Conflict detectat între %s (%s) și %s (%s) în ziua %s.', OC_TEXT_DOMAIN),
                            $schedules[$i]->get_variation_name(),
                            $schedules[$i]->get_time_range(),
                            $schedules[$j]->get_variation_name(),
                            $schedules[$j]->get_time_range(),
                            $schedules[$i]->get_weekday_name()
                        )
                    );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Find available time slots for a day and room
     * 
     * @param int $product_id WooCommerce product ID
     * @param int $weekday Day of week (0-6)
     * @param int $room_number Room number
     * @param string $start_hour Start hour for search (default: "08:00")
     * @param string $end_hour End hour for search (default: "22:00")
     * @param int $slot_duration Duration in minutes (default: 60)
     * @return array Available time slots
     */
    public function find_available_slots($product_id, $weekday, $room_number, $start_hour = "08:00", $end_hour = "22:00", $slot_duration = 60) {
        $existing = $this->repository->find_by_day($product_id, $weekday);
        $room_schedules = array_filter($existing, function($entry) use ($room_number) {
            return intval($entry['room_number']) === intval($room_number);
        });
        
        $available_slots = [];
        $current_time = strtotime($start_hour);
        $end_time = strtotime($end_hour);
        
        while ($current_time + ($slot_duration * 60) <= $end_time) {
            $slot_start = date('H:i', $current_time);
            $slot_end = date('H:i', $current_time + ($slot_duration * 60));
            
            $is_available = true;
            foreach ($room_schedules as $schedule) {
                $schedule_start = strtotime($schedule['start_time']);
                $schedule_end = strtotime($schedule['end_time']);
                
                if ($current_time < $schedule_end && ($current_time + ($slot_duration * 60)) > $schedule_start) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available) {
                $available_slots[] = [
                    'start_time' => $slot_start,
                    'end_time' => $slot_end,
                    'duration' => $slot_duration
                ];
            }
            
            $current_time += 30 * 60; // Check every 30 minutes
        }
        
        return $available_slots;
    }
    
    /**
     * Validate schedule data before saving
     * 
     * @param array $schedule_data Raw schedule data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_schedule_data($schedule_data) {
        if (!is_array($schedule_data) || empty($schedule_data)) {
            return new WP_Error('empty_schedule', __('Datele orarului sunt goale.', OC_TEXT_DOMAIN));
        }
        
        foreach ($schedule_data as $index => $row) {
            $schedule = OC_Schedule::from_array($row);
            $validation = $schedule->validate();
            
            if (is_wp_error($validation)) {
                return new WP_Error(
                    'validation_failed',
                    sprintf(__('Eroare la rândul %d: %s', OC_TEXT_DOMAIN), $index + 1, $validation->get_error_message())
                );
            }
        }
        
        return true;
    }
    
    /**
     * Check if service is ready to use
     * 
     * @return bool True if ready
     */
    public function is_ready() {
        return $this->repository->is_ready();
    }
}
