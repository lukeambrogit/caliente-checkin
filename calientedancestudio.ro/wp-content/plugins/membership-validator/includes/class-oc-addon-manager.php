<?php
/**
 * ADD-ON Manager class
 * 
 * Handles ADD-ON registration, activation, deactivation
 * and provides the framework for modular extensions.
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ADD-ON management class
 */
class OC_Addon_Manager {
    
    /**
     * Registered ADD-ONS
     * 
     * @var array
     */
    private static $addons = [];
    
    /**
     * Active ADD-ONS
     * 
     * @var array
     */
    private static $active_addons = [];
    
    /**
     * Initialization flag
     * 
     * @var bool
     */
    private static $initialized = false;

    /**
     * Runtime load warnings for admin visibility.
     *
     * @var array<int,string>
     */
    private static $load_errors = [];
    
    /**
     * Initialize the ADD-ON system
     */
    public static function init() {
        // Prevent multiple initializations
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        
        // Register built-in ADD-ONS after init to avoid translation issues
        add_action('init', [__CLASS__, 'register_builtin_addons']);
        add_action('init', [__CLASS__, 'load_active_addons']);
        add_action('admin_notices', [__CLASS__, 'render_load_errors']);
    }
    
    /**
     * Register a new ADD-ON
     * 
     * @param string $addon_id Unique ADD-ON identifier
     * @param array $addon_data ADD-ON configuration
     */
    public static function register_addon($addon_id, $addon_data) {
        $defaults = [
            'name' => '',
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'requires_core' => '2.0.0',
            'class' => '',
            'file' => '',
            'builtin' => false,
            'settings_page' => '',
            'menu_title' => '',
            'icon' => 'dashicons-admin-generic'
        ];
        
        self::$addons[$addon_id] = wp_parse_args($addon_data, $defaults);
    }
    
    /**
     * Register built-in ADD-ONS
     */
    public static function register_builtin_addons() {
        // Prevent multiple registrations
        if (!empty(self::$addons)) {
            return;
        }
        // Schedule Manager - RE-ENABLED: fixing asset conflicts properly
        self::register_addon('schedule_manager', [
            'name' => __('📅 Schedule Manager', OC_TEXT_DOMAIN),
            'description' => __('Gestionează orarul de cursuri și activități cu integrare WooCommerce - Arhitectură modulară', OC_TEXT_DOMAIN),
            'version' => '2.1.0',
            'author' => 'Remus Lazar',
            'requires_core' => '2.0.0',
            'class' => 'OC_Schedule_Addon',
            'file' => OC_PLUGIN_DIR . 'includes/addons/schedule-manager/class-schedule-addon.php',
            'builtin' => true,
            'settings_page' => 'orar-cursuri',
            'menu_title' => __('📅 Schedule Manager', OC_TEXT_DOMAIN),
            'icon' => 'dashicons-calendar-alt',
            'modular' => true // Flag pentru identificarea ADD-ON-urilor modulare
        ]);
        
        // Pool Product Manager - ADD-ON pentru pachete cu preț fix
        self::register_addon('pool_product_manager', [
            'name' => __('Pool Product Manager', OC_TEXT_DOMAIN),
            'description' => __('Sistem complet pentru vânzarea de pachete cu preț fix cu selecție din produse POOL', OC_TEXT_DOMAIN),
            'version' => '2.0.0',
            'author' => 'Remus Lazar',
            'requires_core' => '2.0.0',
            'class' => 'OC_Pool_Product_Manager',
            'file' => OC_PLUGIN_DIR . 'includes/addons/pool-product-manager/class-oc-pool-product-manager.php',
            'builtin' => true,
            'settings_page' => 'pool-product-manager',
            'menu_title' => __('Pool Product Manager', OC_TEXT_DOMAIN),
            'icon' => 'dashicons-products'
        ]);
        
        // Membership Validator - ADD-ON pentru validarea abonamentelor cu QR
        self::register_addon('membership_core_engine', [
            'name' => __('Membership Validator', OC_TEXT_DOMAIN),
            'description' => __('Sistem de validare abonamente cu coduri QR și gestionare ședințe', OC_TEXT_DOMAIN),
            'version' => '2.0.0',
            'author' => 'Remus Lazar',
            'requires_core' => '2.0.0',
            'class' => 'OC_Membership_Validator',
            'file' => OC_PLUGIN_DIR . 'includes/addons/membership-validator/class-oc-membership-validator.php',
            'builtin' => false,
            'settings_page' => 'membership-validator',
            'menu_title' => __('Membership Validator', OC_TEXT_DOMAIN),
            'icon' => 'dashicons-id'
        ]);
        
        // Membership Manager - ADD-ON pentru dashboard-uri și analytics (FAZA 3)
        self::register_addon('membership_manager', [
            'name' => __('Membership Manager', OC_TEXT_DOMAIN),
            'description' => __('Dashboard-uri client și admin, analytics și rapoarte pentru abonamente', OC_TEXT_DOMAIN),
            'version' => '2.0.0',
            'author' => 'Remus Lazar',
            'requires_core' => '2.0.0',
            'class' => 'OC_Membership_Manager',
            'file' => OC_PLUGIN_DIR . 'includes/addons/membership-manager/class-oc-membership-manager.php',
            'builtin' => false,
            'depends_on' => ['membership_core_engine'], // Depinde de ADD-ON #1
            'settings_page' => 'oc-membership-analytics',
            'menu_title' => __('Membership Manager', OC_TEXT_DOMAIN),
            'icon' => 'dashicons-chart-bar'
        ]);
    }
    
    /**
     * Activate an ADD-ON
     * 
     * @param string $addon_id ADD-ON identifier
     * @return bool|WP_Error Success or error
     */
    public static function activate_addon($addon_id) {
        if (!isset(self::$addons[$addon_id])) {
            return new WP_Error('addon_not_found', __('ADD-ON not found.', OC_TEXT_DOMAIN));
        }
        
        $addon = self::$addons[$addon_id];

        foreach ((array) ($addon['depends_on'] ?? []) as $dependency_id) {
            if (!self::is_addon_active($dependency_id)) {
                $dependency_result = self::activate_addon($dependency_id);
                if (is_wp_error($dependency_result)) {
                    return $dependency_result;
                }
            }
        }
        
        // Check core version requirement
        if (version_compare(OC_PLUGIN_VERSION, $addon['requires_core'], '<')) {
            return new WP_Error('core_version_required', 
                sprintf(__('This ADD-ON requires core version %s or higher.', OC_TEXT_DOMAIN), $addon['requires_core'])
            );
        }
        
        // Load ADD-ON class if specified
        if (!empty($addon['class']) && !empty($addon['file']) && file_exists($addon['file'])) {
            require_once $addon['file'];
            
            if (class_exists($addon['class'])) {
                $class_name = $addon['class'];
                
                // Check if class uses singleton pattern (has get_instance method)
                if (method_exists($class_name, 'get_instance')) {
                    $class_name::get_instance();
                } else {
                    new $class_name();
                }
            }
        }
        
        // Add to active ADD-ONS
        $active_addons = self::get_active_addons();
        if (!in_array($addon_id, $active_addons)) {
            $active_addons[] = $addon_id;
            update_option('oc_active_addons', $active_addons);
        }
        
        do_action('oc_addon_activated', $addon_id, $addon);
        
        return true;
    }
    
    /**
     * Deactivate an ADD-ON
     * 
     * @param string $addon_id ADD-ON identifier
     * @return bool Success status
     */
    public static function deactivate_addon($addon_id) {
        $dependent_addons = self::get_active_dependent_addons($addon_id);
        if (!empty($dependent_addons)) {
            $dependent_names = array_map(static function ($dependent_id) {
                return self::$addons[$dependent_id]['name'] ?? $dependent_id;
            }, $dependent_addons);

            return new WP_Error(
                'addon_has_dependents',
                sprintf(
                    __('Nu poți dezactiva acest ADD-ON cât timp sunt active: %s', OC_TEXT_DOMAIN),
                    implode(', ', $dependent_names)
                )
            );
        }

        $active_addons = self::get_active_addons();
        $key = array_search($addon_id, $active_addons);
        
        if ($key !== false) {
            unset($active_addons[$key]);
            $active_addons = array_values($active_addons);
            update_option('oc_active_addons', $active_addons);
            self::$active_addons = $active_addons;
            
            do_action('oc_addon_deactivated', $addon_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if an ADD-ON is active
     * 
     * @param string $addon_id ADD-ON identifier
     * @return bool Active status
     */
    public static function is_addon_active($addon_id) {
        return in_array($addon_id, self::get_active_addons());
    }
    
    /**
     * Get all registered ADD-ONS
     * 
     * @return array Registered ADD-ONS
     */
    public static function get_addons() {
        return self::$addons;
    }
    
    /**
     * Get active ADD-ONS
     * 
     * @return array Active ADD-ON IDs
     */
    public static function get_active_addons() {
        if (empty(self::$active_addons)) {
            self::$active_addons = get_option('oc_active_addons', [
                'schedule_manager',
                'pool_product_manager', 
                'membership_core_engine',
                'membership_manager'
            ]);
        }
        return self::$active_addons;
    }
    
    /**
     * Force refresh addon cache pentru a aplica noile default values
     */
    public static function refresh_addon_cache() {
        self::$active_addons = [];
    }
    
    /**
     * Migrează ID-urile addon-urilor pentru redenumiri
     */
    private static function migrate_addon_ids() {
        $current_active = get_option('oc_active_addons', []);
        $migrations = [
            'membership_validator' => 'membership_core_engine'
        ];
        
        $updated = false;
        foreach ($migrations as $old_id => $new_id) {
            $key = array_search($old_id, $current_active);
            if ($key !== false) {
                $current_active[$key] = $new_id;
                $updated = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (defined('WP_DEBUG') && WP_DEBUG) error_log("🔄 [Addon Manager] Migrated addon ID: {$old_id} → {$new_id}");
                }
            }
        }
        
        if ($updated) {
            update_option('oc_active_addons', array_values($current_active));
            self::$active_addons = array_values($current_active);
        }
    }
    
    /**
     * Load active ADD-ONS
     */
    public static function load_active_addons() {
        // 🚨 MIGRAȚIA ID-ULUI: Actualizează membership_validator → membership_core_engine
        self::migrate_addon_ids();
        
        $active_addons = self::get_active_addons();
        $loaded_addons = [];

        foreach ($active_addons as $addon_id) {
            self::load_addon_with_dependencies($addon_id, $active_addons, $loaded_addons, []);
        }
        
        do_action('oc_addons_loaded');
    }

    private static function load_addon_with_dependencies($addon_id, array $active_addons, array &$loaded_addons, array $loading_stack): void {
        if (isset($loaded_addons[$addon_id]) || !isset(self::$addons[$addon_id])) {
            return;
        }

        if (in_array($addon_id, $loading_stack, true)) {
            self::$load_errors[] = sprintf(
                __('S-a detectat o dependență circulară la încărcarea ADD-ON-ului %s.', OC_TEXT_DOMAIN),
                $addon_id
            );
            return;
        }

        $addon = self::$addons[$addon_id];
        $dependencies = (array) ($addon['depends_on'] ?? []);

        foreach ($dependencies as $dependency_id) {
            if (!in_array($dependency_id, $active_addons, true) || !isset(self::$addons[$dependency_id])) {
                self::$load_errors[] = sprintf(
                    __('ADD-ON-ul %1$s nu a fost încărcat deoarece lipsește dependența %2$s.', OC_TEXT_DOMAIN),
                    $addon['name'] ?: $addon_id,
                    $dependency_id
                );
                return;
            }

            self::load_addon_with_dependencies($dependency_id, $active_addons, $loaded_addons, array_merge($loading_stack, [$addon_id]));
            if (!isset($loaded_addons[$dependency_id])) {
                self::$load_errors[] = sprintf(
                    __('ADD-ON-ul %1$s a fost oprit deoarece dependența %2$s nu s-a putut încărca.', OC_TEXT_DOMAIN),
                    $addon['name'] ?: $addon_id,
                    $dependency_id
                );
                return;
            }
        }

        self::boot_addon($addon_id, $addon);
        $loaded_addons[$addon_id] = true;
    }

    private static function boot_addon($addon_id, array $addon): void {
        if (empty($addon['class']) || empty($addon['file']) || !file_exists($addon['file'])) {
            self::$load_errors[] = sprintf(
                __('ADD-ON-ul %s nu a putut fi încărcat deoarece fișierul sursă lipsește.', OC_TEXT_DOMAIN),
                $addon['name'] ?: $addon_id
            );
            return;
        }

        require_once $addon['file'];

        if (!class_exists($addon['class'])) {
            self::$load_errors[] = sprintf(
                __('ADD-ON-ul %s nu a putut fi încărcat deoarece clasa principală nu este disponibilă.', OC_TEXT_DOMAIN),
                $addon['name'] ?: $addon_id
            );
            return;
        }

        $class_name = $addon['class'];

        if (method_exists($class_name, 'get_instance')) {
            $class_name::get_instance();
            return;
        }

        new $class_name();
    }

    public static function render_load_errors(): void {
        if (!current_user_can('manage_options') || empty(self::$load_errors)) {
            return;
        }

        $messages = array_unique(array_filter(array_map('strval', self::$load_errors)));
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $message) {
            echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Returnează lista addon-urilor active care depind de addon-ul dat.
     *
     * @param string $addon_id
     * @return array
     */
    private static function get_active_dependent_addons($addon_id) {
        $dependent_addons = [];
        $active_addons = self::get_active_addons();

        foreach ($active_addons as $active_addon_id) {
            if ($active_addon_id === $addon_id || !isset(self::$addons[$active_addon_id])) {
                continue;
            }

            $dependencies = (array) (self::$addons[$active_addon_id]['depends_on'] ?? []);
            if (in_array($addon_id, $dependencies, true)) {
                $dependent_addons[] = $active_addon_id;
            }
        }

        return $dependent_addons;
    }
    
    /**
     * Get ADD-ON info
     * 
     * @param string $addon_id ADD-ON identifier
     * @return array|false ADD-ON data or false if not found
     */
    public static function get_addon($addon_id) {
        return isset(self::$addons[$addon_id]) ? self::$addons[$addon_id] : false;
    }
}

// Initialize ADD-ON system
OC_Addon_Manager::init();
