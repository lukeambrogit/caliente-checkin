<?php
/**
 * Schedule Manager ADD-ON - Entry Point
 * 
 * Entry point pentru ADD-ON-ul Schedule Manager conform noii structuri modulare.
 * Acest fișier va deveni punctul principal de intrare pentru Schedule Manager
 * după finalizarea restructurării.
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
 * Schedule Manager ADD-ON Class
 * 
 * Clasa principală pentru ADD-ON-ul Schedule Manager cu arhitectura nouă.
 * În momentul de față este doar un placeholder pentru structura viitoare.
 */
class OC_Schedule_Addon {
    
    /**
     * Instance pentru Singleton pattern
     * 
     * @var OC_Schedule_Addon|null
     */
    private static $instance = null;
    
    /**
     * Admin component instance
     * 
     * @var OC_Schedule_Admin
     */
    private $admin;
    
    /**
     * AJAX component instance
     * 
     * @var OC_Schedule_Ajax
     */
    private $ajax;
    
    /**
     * Frontend component instance
     * 
     * @var OC_Schedule_Display
     */
    private $frontend;
    
    /**
     * Constructor privat pentru Singleton
     */
    private function __construct() {
        // Încarcă toate componentele ADD-ON-ului
        $this->load_components();
        
        // Înregistrează hook-urile WordPress
        $this->register_hooks();
        
        // Schedule Manager loaded successfully
    }
    
    /**
     * Obține instanța Singleton
     * 
     * @return OC_Schedule_Addon
     */
    public static function get_instance(): OC_Schedule_Addon {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inițializează ADD-ON-ul (momentan placeholder)
     */
    public function init(): void {
        // Placeholder pentru inițializare viitoare
        // Sistemul actual prin OC_Admin rămâne activ
    }
    
    /**
     * Încarcă componentele ADD-ON-ului
     */
    private function load_components(): void {
        
        // Infrastructure layer
        require_once dirname(__FILE__) . '/infrastructure/class-schedule-db.php';
        
        // Domain layer
        require_once dirname(__FILE__) . '/domain/entities/class-schedule.php';
        require_once dirname(__FILE__) . '/domain/repositories/class-schedule-repository.php';
        require_once dirname(__FILE__) . '/domain/services/class-schedule-service.php';
        
        // Interface layer - Admin
        require_once dirname(__FILE__) . '/interfaces/admin/class-schedule-admin.php';
        require_once dirname(__FILE__) . '/interfaces/admin/class-schedule-ajax.php';
        
        // Interface layer - Frontend
        require_once dirname(__FILE__) . '/interfaces/frontend/class-schedule-display.php';
        
        // Initialize components
        $this->admin = new OC_Schedule_Admin();
        $this->ajax = new OC_Schedule_Ajax();
        $this->frontend = new OC_Schedule_Display();
        
        // All components loaded
    }
    
    /**
     * Înregistrează hook-urile WordPress
     */
    private function register_hooks(): void {
        // Hook-urile sunt înregistrate automat în constructorii componentelor
        // Nu este nevoie de hook-uri suplimentare aici pentru moment
        
        // Hook pentru cleanup la dezactivare
        register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
    }
    
    /**
     * Cleanup la dezactivarea ADD-ON-ului
     */
    public function on_deactivate(): void {
        // Cleanup tasks when addon is deactivated
        // Cleanup completed
    }
    
    /**
     * Get admin component
     * 
     * @return OC_Schedule_Admin
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Get AJAX component
     * 
     * @return OC_Schedule_Ajax
     */
    public function get_ajax() {
        return $this->ajax;
    }
    
    /**
     * Get frontend component
     * 
     * @return OC_Schedule_Display
     */
    public function get_frontend() {
        return $this->frontend;
    }
}

// Instanțiez ADD-ON-ul Schedule Manager cu noua structură
OC_Schedule_Addon::get_instance();
