<?php
/**
 * Dashboard class
 * 
 * Handles the main admin dashboard with ADD-ON management
 * and core system overview.
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard management class
 */
class OC_Dashboard {

    /**
     * Runtime notice for core settings page.
     *
     * @var array{type:string,message:string,context:string}|null
     */
    private ?array $core_settings_notice = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_dashboard_menu'], 5); // High priority to appear first
        add_action('wp_ajax_oc_toggle_addon', [$this, 'ajax_toggle_addon']);
        add_action('wp_ajax_oc_check_database', [$this, 'ajax_check_database']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
    }
    
    /**
     * Add dashboard menu
     */
    public function add_dashboard_menu() {
        // Main dashboard page
        add_menu_page(
            __('Membership Validator', OC_TEXT_DOMAIN),
            __('Membership Validator', OC_TEXT_DOMAIN),
            'manage_options',
            'membership-validator-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-groups',
            30
        );
        
        // Dashboard submenu (to rename the first item)
        add_submenu_page(
            'membership-validator-dashboard',
            __('Dashboard', OC_TEXT_DOMAIN),
            __('🏠 Dashboard', OC_TEXT_DOMAIN),
            'manage_options',
            'membership-validator-dashboard',
            [$this, 'dashboard_page']
        );
        
        // Core Settings submenu
        add_submenu_page(
            'membership-validator-dashboard',
            __('Core Settings', OC_TEXT_DOMAIN),
            __('⚙️ Core Settings', OC_TEXT_DOMAIN),
            'manage_options',
            'membership-validator-core-settings',
            [$this, 'core_settings_page']
        );

        // Check-in App submenu
        add_submenu_page(
            'membership-validator-dashboard',
            __('Check-in App', OC_TEXT_DOMAIN),
            __('📱 Check-in App', OC_TEXT_DOMAIN),
            'manage_options',
            'membership-validator-checkin',
            [$this, 'checkin_settings_page']
        );

        // ELIMINATED: ADD-ONS section separator (cleaner menu with tab system)
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        // Only load on dashboard pages
        if (strpos($hook, 'membership-validator') === false && strpos($hook, 'orar-cursuri') === false) {
            return;
        }
        
        // Dashboard specific styles
        wp_enqueue_style(
            'oc-dashboard-style',
            OC_PLUGIN_URL . 'assets/dashboard.css',
            [],
            OC_PLUGIN_VERSION
        );
        
        // Dashboard specific JavaScript
        wp_enqueue_script(
            'oc-dashboard-script',
            OC_PLUGIN_URL . 'assets/dashboard.js',
            ['jquery'],
            OC_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('oc-dashboard-script', 'ocDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_dashboard_nonce'),
            'strings' => [
                'activating' => __('Activating...', OC_TEXT_DOMAIN),
                'deactivating' => __('Deactivating...', OC_TEXT_DOMAIN),
                'error' => __('Error occurred. Please try again.', OC_TEXT_DOMAIN),
                'success' => __('ADD-ON status updated successfully.', OC_TEXT_DOMAIN),
                'active' => __('Active', OC_TEXT_DOMAIN),
                'inactive' => __('Inactive', OC_TEXT_DOMAIN),
                'activate' => __('Activate', OC_TEXT_DOMAIN),
                'deactivate' => __('Deactivate', OC_TEXT_DOMAIN),
                'checking' => __('Checking...', OC_TEXT_DOMAIN),
                'fixed' => __('Fixed!', OC_TEXT_DOMAIN)
            ]
        ]);
    }
    
    /**
     * Main dashboard page
     */
    public function dashboard_page() {
        $addons = class_exists('OC_Addon_Manager') ? OC_Addon_Manager::get_addons() : [];
        $active_addons = class_exists('OC_Addon_Manager') ? OC_Addon_Manager::get_active_addons() : [];
        
        include OC_PLUGIN_DIR . 'templates/dashboard-page.php';
    }
    
    /**
     * Core settings page
     */
    public function core_settings_page() {
        // Handle form submission for core settings
        if ((isset($_POST['oc_save_core_settings']) || isset($_POST['oc_change_active_pin']))
            && wp_verify_nonce($_POST['oc_core_nonce'], 'oc_save_core_settings')) {
            $this->save_core_settings();
        }

        $oc_core_notice = $this->core_settings_notice;
        
        include OC_PLUGIN_DIR . 'templates/core-settings-page.php';
    }
    
    /**
     * Save core settings
     */
    private function save_core_settings() {
        $core_settings = [
            'oc_enable_debug' => isset($_POST['oc_enable_debug']) ? '1' : '0',
            'oc_cache_enabled' => isset($_POST['oc_cache_enabled']) ? '1' : '0',
            'oc_api_enabled' => isset($_POST['oc_api_enabled']) ? '1' : '0',
            'oc_logging_enabled' => isset($_POST['oc_logging_enabled']) ? '1' : '0'
        ];

        $notice_type = 'success';
        $notice_message = __('Core settings saved successfully!', OC_TEXT_DOMAIN);
        
        foreach ($core_settings as $key => $value) {
            update_option($key, $value);
        }

        $pin_hash = $this->get_active_membership_edit_pin_hash();
        $is_change_pin_request = isset($_POST['oc_change_active_pin']);

        if ($is_change_pin_request) {
            $old_pin = trim((string) wp_unslash($_POST['oc_active_membership_edit_pin_old'] ?? ''));
            $new_pin = trim((string) wp_unslash($_POST['oc_active_membership_edit_pin_new'] ?? ''));
            $confirm_pin = trim((string) wp_unslash($_POST['oc_active_membership_edit_pin_confirm'] ?? ''));

            if ($pin_hash === '') {
                $notice_type = 'error';
                $notice_message = __('PIN-ul nu este configurat inca.', OC_TEXT_DOMAIN);
            } elseif ($old_pin === '' || $new_pin === '' || $confirm_pin === '') {
                $notice_type = 'error';
                $notice_message = __('Completeaza toate campurile pentru schimbarea PIN-ului.', OC_TEXT_DOMAIN);
            } elseif (!wp_check_password($old_pin, $pin_hash)) {
                $notice_type = 'error';
                $notice_message = __('PIN-ul vechi este incorect.', OC_TEXT_DOMAIN);
            } elseif (strlen($new_pin) < 4) {
                $notice_type = 'error';
                $notice_message = __('PIN-ul nou trebuie sa aiba cel putin 4 caractere.', OC_TEXT_DOMAIN);
            } elseif (!hash_equals($new_pin, $confirm_pin)) {
                $notice_type = 'error';
                $notice_message = __('PIN-ul nou si confirmarea nu coincid.', OC_TEXT_DOMAIN);
            } else {
                update_option('oc_membership_active_edit_pin_hash', wp_hash_password($new_pin), false);
                delete_option('oc_membership_active_edit_pin');
                $notice_message = __('PIN schimbat cu succes.', OC_TEXT_DOMAIN);
            }
        } else {
            // Initial PIN setup (only when PIN is not already configured).
            $raw_pin = isset($_POST['oc_active_membership_edit_pin'])
                ? trim((string) wp_unslash($_POST['oc_active_membership_edit_pin']))
                : '';

            if ($raw_pin !== '' && $pin_hash === '') {
                if (strlen($raw_pin) < 4) {
                    $notice_type = 'error';
                    $notice_message = __('PIN-ul trebuie sa aiba cel putin 4 caractere.', OC_TEXT_DOMAIN);
                } else {
                    update_option('oc_membership_active_edit_pin_hash', wp_hash_password($raw_pin), false);
                    delete_option('oc_membership_active_edit_pin');
                    $notice_message = __('Core settings saved successfully! PIN setat pentru protectie.', OC_TEXT_DOMAIN);
                }
            }
        }

        // Auto-generate API key the first time the API is enabled
        // and insert it into wp-config.php. Falls back to DB if file is not writable.
        if ($core_settings['oc_api_enabled'] === '1') {
            $has_constant = defined('OC_MEMBERSHIP_API_KEY') && constant('OC_MEMBERSHIP_API_KEY') !== '';
            if (!$has_constant) {
                $this->maybe_write_api_key_to_config();
            }
        }
        
        $notice_context = $is_change_pin_request
            ? ($notice_type === 'success' ? 'pin_change_success' : 'pin_change_error')
            : 'general';

        $this->core_settings_notice = [
            'type' => $notice_type,
            'message' => $notice_message,
            'context' => $notice_context,
        ];
    }

    /**
     * Returneaza hash-ul PIN-ului pentru editarea abonamentelor active.
     * Migreaza automat valoarea legacy in clar la hash.
     */
    private function get_active_membership_edit_pin_hash(): string {
        $hash = (string) get_option('oc_membership_active_edit_pin_hash', '');
        if ($hash !== '') {
            return $hash;
        }

        $legacy_plain = trim((string) get_option('oc_membership_active_edit_pin', ''));
        if ($legacy_plain === '') {
            return '';
        }

        $migrated_hash = wp_hash_password($legacy_plain);
        update_option('oc_membership_active_edit_pin_hash', $migrated_hash, false);
        delete_option('oc_membership_active_edit_pin');

        return $migrated_hash;
    }
    
    /**
     * Ensure the API key is stored in the DB option so the settings UI can display it.
     * We no longer write to wp-config.php at runtime — the key lives in the DB only.
     * If OC_MEMBERSHIP_API_KEY is already defined as a constant (set manually in
     * wp-config.php), we sync it to the DB option so it shows up in the UI.
     */
    private function maybe_write_api_key_to_config(): void {
        // If a constant is defined (manually or from a previous write), sync to DB.
        if (defined('OC_MEMBERSHIP_API_KEY') && get_option('oc_membership_api_key', '') === '') {
            update_option('oc_membership_api_key', constant('OC_MEMBERSHIP_API_KEY'));
            return;
        }

        // If already stored in DB, nothing to do.
        if (get_option('oc_membership_api_key', '') !== '') {
            return;
        }

        // Generate a new key and store it in the DB only.
        update_option('oc_membership_api_key', wp_generate_password(48, false));
    }

    /**
     * Locate the wp-config.php file (same logic as WordPress core installer).
     */
    private function find_wp_config_path(): ?string {
        $base = ABSPATH . 'wp-config.php';
        if (file_exists($base)) {
            return $base;
        }
        // One directory up (standard layout where wp-config.php is outside ABSPATH)
        $up = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($up) && !file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
            return $up;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Check-in App settings
    // -------------------------------------------------------------------------

    /**
     * Check-in App settings page — manages per-device API keys and React app config.
     */
    public function checkin_settings_page(): void {
        $checkin_notice = null;
        $new_plain_key  = null;

        if ( isset( $_POST['oc_checkin_nonce'] )
            && wp_verify_nonce( $_POST['oc_checkin_nonce'], 'oc_checkin_settings' ) ) {

            $action = sanitize_key( $_POST['oc_checkin_action'] ?? '' );

            switch ( $action ) {
                case 'save_config':
                    $device_id = sanitize_text_field( trim( (string) ( $_POST['oc_checkin_device_id'] ?? '' ) ) );
                    if ( $device_id !== '' ) {
                        update_option( 'oc_checkin_device_id', $device_id, false );
                        $checkin_notice = [ 'type' => 'success', 'message' => __( 'Device ID saved.', OC_TEXT_DOMAIN ) ];
                    } else {
                        $checkin_notice = [ 'type' => 'error', 'message' => __( 'Device ID cannot be empty.', OC_TEXT_DOMAIN ) ];
                    }
                    break;

                case 'regenerate_token':
                    [ $new_plain_key, $checkin_notice ] = $this->generate_checkin_device_key(
                        get_option( 'oc_checkin_device_id', 'studio-checkin-1' )
                    );
                    break;

                case 'add_device':
                    $device_id = sanitize_text_field( trim( (string) ( $_POST['oc_new_device_id'] ?? '' ) ) );
                    if ( $device_id === '' ) {
                        $checkin_notice = [ 'type' => 'error', 'message' => __( 'Device ID cannot be empty.', OC_TEXT_DOMAIN ) ];
                    } else {
                        [ $new_plain_key, $checkin_notice ] = $this->generate_checkin_device_key( $device_id );
                    }
                    break;

                case 'delete_device':
                    $device_id = sanitize_text_field( trim( (string) ( $_POST['oc_delete_device_id'] ?? '' ) ) );
                    if ( $device_id !== '' ) {
                        $devices = get_option( 'oc_membership_api_devices', [] );
                        if ( is_array( $devices ) && array_key_exists( $device_id, $devices ) ) {
                            unset( $devices[ $device_id ] );
                            update_option( 'oc_membership_api_devices', $devices, false );
                        }
                        $checkin_notice = [
                            'type'    => 'success',
                            // translators: %s = device ID
                            'message' => sprintf( __( "Device '%s' deleted.", OC_TEXT_DOMAIN ), esc_html( $device_id ) ),
                        ];
                    }
                    break;

                case 'save_ws_config':
                    $ws_url    = esc_url_raw( trim( (string) ( $_POST['oc_ws_server_url'] ?? '' ) ) );
                    $ws_secret = sanitize_text_field( trim( (string) ( $_POST['oc_ws_server_secret'] ?? '' ) ) );
                    update_option( 'oc_ws_server_url', $ws_url, false );
                    if ( $ws_secret !== '' ) {
                        update_option( 'oc_ws_server_secret', $ws_secret, false );
                    }
                    $checkin_notice = [ 'type' => 'success', 'message' => __( 'WebSocket server settings saved.', OC_TEXT_DOMAIN ) ];
                    break;
            }
        }

        $oc_checkin_device_id  = (string) get_option( 'oc_checkin_device_id', 'studio-checkin-1' );
        $oc_checkin_api_token  = (string) get_option( 'oc_checkin_api_token', '' );
        $oc_api_devices        = get_option( 'oc_membership_api_devices', [] );
        if ( ! is_array( $oc_api_devices ) ) {
            $oc_api_devices = [];
        }
        $oc_ws_server_url    = (string) get_option( 'oc_ws_server_url', '' );
        $oc_ws_server_secret = (string) get_option( 'oc_ws_server_secret', '' );

        include OC_PLUGIN_DIR . 'templates/checkin-settings-page.php';
    }

    /**
     * Generate a fresh API key for a device, persist the hash, and — if the device
     * matches the current check-in device — also persist the plain key as the token.
     *
     * @param string $device_id
     * @return array{ 0: array{device_id:string,key:string}, 1: array{type:string,message:string} }
     */
    private function generate_checkin_device_key( string $device_id ): array {
        $plain_key = wp_generate_password( 48, false );

        $devices = get_option( 'oc_membership_api_devices', [] );
        if ( ! is_array( $devices ) ) {
            $devices = [];
        }

        $existing                       = $devices[ $device_id ] ?? [];
        $existing['api_key_hash']       = hash( 'sha256', $plain_key );
        $existing['active']             = $existing['active'] ?? true;
        $existing['scopes']             = $existing['scopes'] ?? [];
        $existing['rate_limit_per_minute'] = $existing['rate_limit_per_minute'] ?? 60;
        $existing['created_at']         = $existing['created_at'] ?? current_time( 'mysql' );
        $existing['last_used_at']       = null;
        $devices[ $device_id ]          = $existing;

        update_option( 'oc_membership_api_devices', $devices, false );

        // Keep the plain token in sync when this is the active check-in device.
        if ( $device_id === (string) get_option( 'oc_checkin_device_id', 'studio-checkin-1' ) ) {
            update_option( 'oc_checkin_api_token', $plain_key, false );
        }

        return [
            [ 'device_id' => $device_id, 'key' => $plain_key ],
            [
                'type'    => 'success',
                // translators: %s = device ID
                'message' => sprintf(
                    __( "Key generated for device '%s'. Copy it below — it won't be shown again.", OC_TEXT_DOMAIN ),
                    esc_html( $device_id )
                ),
            ],
        ];
    }

    /**
     * AJAX: Toggle ADD-ON status
     */
    public function ajax_toggle_addon() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed.', OC_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', OC_TEXT_DOMAIN));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        if (empty($addon_id) || !in_array($action, ['activate', 'deactivate'])) {
            wp_send_json_error(__('Invalid parameters.', OC_TEXT_DOMAIN));
        }
        
        if ($action === 'activate') {
            $result = OC_Addon_Manager::activate_addon($addon_id);
        } else {
            $result = OC_Addon_Manager::deactivate_addon($addon_id);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('ADD-ON %s successfully.', OC_TEXT_DOMAIN),
                $action === 'activate' ? __('activated', OC_TEXT_DOMAIN) : __('deactivated', OC_TEXT_DOMAIN)
            ),
            'new_status' => $action === 'activate' ? 'active' : 'inactive'
        ]);
    }
    
    /**
     * Get system status info
     * 
     * @return array System status data
     */
    public function get_system_status() {
        global $wpdb;
        
        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'plugin_version' => OC_PLUGIN_VERSION,
            'database_version' => get_option('oc_db_version', '1.0.0'),
            'active_addons_count' => class_exists('OC_Addon_Manager') ? count(OC_Addon_Manager::get_active_addons()) : 0,
            'total_addons_count' => class_exists('OC_Addon_Manager') ? count(OC_Addon_Manager::get_addons()) : 0,
            'cache_enabled' => get_option('oc_cache_enabled', '1') === '1',
            'debug_enabled' => get_option('oc_enable_debug', '0') === '1',
            'schedule_entries' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}orar_cursuri")
        ];
    }
    
    /**
     * AJAX: Check and create database tables
     */
    public function ajax_check_database() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_dashboard_nonce')) {
            wp_send_json_error(__('Security check failed.', OC_TEXT_DOMAIN));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', OC_TEXT_DOMAIN));
        }
        
        // Check and create tables
        $db = new OC_DB();
        $table_existed = $db->table_exists();
        $result = $db->force_check_tables();
        
        if (!$result) {
            wp_send_json_error(__('Failed to create database tables.', OC_TEXT_DOMAIN));
        }
        
        $message = $table_existed 
            ? __('Database tables already exist and are working correctly.', OC_TEXT_DOMAIN)
            : __('Database tables were missing and have been created successfully.', OC_TEXT_DOMAIN);
        
        // Get updated system status
        $system_status = $this->get_system_status();
        
        wp_send_json_success([
            'message' => $message,
            'table_existed' => $table_existed,
            'system_status' => $system_status
        ]);
    }
}
