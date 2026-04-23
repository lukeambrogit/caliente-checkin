<?php
/**
 * Settings management class
 * 
 * Handles plugin settings storage, retrieval,
 * and validation.
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings management class
 */
class OC_Settings {
    
    /**
     * Settings prefix
     */
    const SETTINGS_PREFIX = 'oc_';
    
    /**
     * Default settings
     * 
     * @var array
     */
    private static $default_settings = [
        'selected_attribute' => '',
        'show_empty_days' => false,
        'primary_color' => '#d48945',
        'title_color' => '#5d473d',
        'text_color' => '#5f4a40',
        'secondary_color' => '#8d786b',
        'background_color' => '#f7eee8',
        'muted_color' => '#f5ece5',
        'border_color' => '#e3d5c9',
        'font_family' => 'Segoe UI, Roboto, Arial, sans-serif',
        'font_size' => '15px',
        'header_font_size' => '30px',
        'border_radius' => '16px',

        'gradient_enabled' => '0',
        'background_mode' => 'gradient',
        'background_image' => '',
        'background_image_mobile' => '',
        'background_image_opacity' => '1',
        'background_image_size_desktop' => '100',
        'background_image_size_mobile' => '100',
        'background_image_mobile_behavior' => 'cover',
        'gradient_start' => '#ff7a3d',
        'gradient_end' => '#ffd08a',
        'gradient_direction' => '132deg',
        'logo_image' => '',
        'logo_width' => '50px',
        'logo_height' => 'auto',
        'logo_position' => 'left',

        'load_assets_everywhere' => false,
        'custom_css' => '',
        'show_course_descriptions' => false,
        'enable_tooltips' => true,
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i'
    ];
    
    /**
     * Get setting value
     * 
     * @param string $key Setting key (without prefix)
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get($key, $default = null) {
        $full_key = self::SETTINGS_PREFIX . $key;
        
        // Use provided default or fallback to defaults array
        if (null === $default && isset(self::$default_settings[$key])) {
            $default = self::$default_settings[$key];
        }
        
        return get_option($full_key, $default);
    }
    
    /**
     * Set setting value
     * 
     * @param string $key Setting key (without prefix)
     * @param mixed $value Setting value
     * @return bool
     */
    public static function set($key, $value) {
        $full_key = self::SETTINGS_PREFIX . $key;
        
        // Sanitize value
        $sanitized_value = self::sanitize_setting($key, $value);
        
        return update_option($full_key, $sanitized_value);
    }
    
    /**
     * Delete setting
     * 
     * @param string $key Setting key (without prefix)
     * @return bool
     */
    public static function delete($key) {
        $full_key = self::SETTINGS_PREFIX . $key;
        return delete_option($full_key);
    }
    
    /**
     * Get all settings
     * 
     * @return array
     */
    public static function get_all() {
        $settings = [];
        
        foreach (self::$default_settings as $key => $default_value) {
            $settings[$key] = self::get($key, $default_value);
        }
        
        return $settings;
    }
    
    /**
     * Set multiple settings
     * 
     * @param array $settings Associative array of settings
     * @return bool
     */
    public static function set_multiple($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!self::set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Reset settings to defaults
     * 
     * @param array $keys Specific keys to reset, or empty for all
     * @return bool
     */
    public static function reset($keys = []) {
        if (empty($keys)) {
            $keys = array_keys(self::$default_settings);
        }
        
        $success = true;
        
        foreach ($keys as $key) {
            if (isset(self::$default_settings[$key])) {
                if (!self::set($key, self::$default_settings[$key])) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Initialize default settings
     */
    public static function init_defaults() {
        foreach (self::$default_settings as $key => $value) {
            $full_key = self::SETTINGS_PREFIX . $key;
            
            // Only add if doesn't exist
            if (false === get_option($full_key)) {
                add_option($full_key, $value);
            }
        }
    }
    
    /**
     * Sanitize setting value based on key
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return mixed Sanitized value
     */
    private static function sanitize_setting($key, $value) {
        switch ($key) {
            case 'primary_color':
            case 'title_color':
            case 'text_color':
            case 'secondary_color':
            case 'background_color':
            case 'muted_color':
            case 'border_color':
            case 'gradient_start':
            case 'gradient_end':
                return self::sanitize_css_color_value($value);
                
            case 'font_family':
            case 'font_size':
            case 'header_font_size':
            case 'border_radius':
            case 'date_format':
            case 'time_format':
                return sanitize_text_field($value);

            case 'background_mode':
                $mode = sanitize_key($value);
                return in_array($mode, ['gradient', 'image'], true) ? $mode : 'gradient';

            case 'background_image':
                return esc_url_raw($value);

            case 'background_image_mobile':
                return esc_url_raw($value);

            case 'background_image_opacity':
                $opacity = (float) $value;
                if ($opacity < 0) {
                    $opacity = 0;
                }
                if ($opacity > 1) {
                    $opacity = 1;
                }
                return (string) $opacity;

            case 'background_image_size_desktop':
            case 'background_image_size_mobile':
                $size = (float) $value;
                if ($size < 50) {
                    $size = 50;
                }
                if ($size > 200) {
                    $size = 200;
                }
                return (string) $size;

            case 'background_image_mobile_behavior':
                $behavior = sanitize_key($value);
                return in_array($behavior, ['cover', 'repeat'], true) ? $behavior : 'cover';
                
            case 'desktop_bg_image':
            case 'mobile_bg_image':
                return esc_url_raw($value);
                
            case 'custom_css':
                return wp_strip_all_tags($value);
                
            case 'selected_attribute':
                return sanitize_key($value);
                

                
            case 'show_empty_days':
            case 'load_assets_everywhere':
            case 'show_course_descriptions':
            case 'enable_tooltips':
                return (bool) $value;
                
            default:
                if (is_string($value)) {
                    return sanitize_text_field($value);
                }
                return $value;
        }
    }
    
    /**
     * Validate setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool|WP_Error
     */
    public static function validate_setting($key, $value) {
        switch ($key) {
            case 'primary_color':
            case 'title_color':
            case 'text_color':
            case 'secondary_color':
            case 'background_color':
            case 'muted_color':
            case 'border_color':
            case 'gradient_start':
            case 'gradient_end':
                if (!empty($value) && !self::is_valid_css_color($value)) {
                    return new WP_Error('invalid_color', sprintf(
                        __('Culoarea pentru %s este invalidă.', OC_TEXT_DOMAIN),
                        $key
                    ));
                }
                break;
                
            case 'font_size':
            case 'header_font_size':
            case 'border_radius':
                if (!empty($value) && !preg_match('/^\d+(\.\d+)?(px|em|rem|%)$/', $value)) {
                    return new WP_Error('invalid_size', sprintf(
                        __('Dimensiunea pentru %s este invalidă. Folosește unități valide (px, em, rem, %).', OC_TEXT_DOMAIN),
                        $key
                    ));
                }
                break;
                
            case 'desktop_bg_image':
            case 'mobile_bg_image':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return new WP_Error('invalid_url', sprintf(
                        __('URL-ul pentru %s este invalid.', OC_TEXT_DOMAIN),
                        $key
                    ));
                }
                break;
                

                break;
                
            case 'selected_attribute':
                if (!empty($value)) {
                    $woocommerce = new OC_WooCommerce();
                    if (!$woocommerce->is_valid_selected_attribute($value)) {
                        return new WP_Error('invalid_taxonomy',
                            __('Taxonomia selectată nu este validă.', OC_TEXT_DOMAIN)
                        );
                    }
                }
                break;
        }
        
        return true;
    }

    /**
     * Sanitize CSS color value (hex/rgb/rgba/transparent).
     */
    private static function sanitize_css_color_value($value) {
        $value = trim((string) sanitize_text_field((string) $value));
        if ($value === '') {
            return '';
        }

        if (strtolower($value) === 'transparent') {
            return 'transparent';
        }

        $hex = sanitize_hex_color($value);
        if (!empty($hex)) {
            return $hex;
        }

        if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(0|0?\.[0-9]+|1(?:\.0+)?))?\s*\)$/i', $value, $m)) {
            $r = max(0, min(255, (int) $m[1]));
            $g = max(0, min(255, (int) $m[2]));
            $b = max(0, min(255, (int) $m[3]));
            if (isset($m[4]) && $m[4] !== '') {
                $a = max(0, min(1, (float) $m[4]));
                return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim((string) $a, '0'), '.'));
            }
            return sprintf('rgb(%d, %d, %d)', $r, $g, $b);
        }

        return '';
    }

    /**
     * Validate CSS color value.
     */
    private static function is_valid_css_color($value) {
        return self::sanitize_css_color_value($value) !== '';
    }
    
    /**
     * Get settings schema for API or forms
     * 
     * @return array
     */
    public static function get_schema() {
        return [
            'selected_attribute' => [
                'type' => 'string',
                'description' => __('Taxonomia atributului WooCommerce folosit', OC_TEXT_DOMAIN),
                'required' => false
            ],
            'show_empty_days' => [
                'type' => 'boolean',
                'description' => __('Afișează zilele fără cursuri', OC_TEXT_DOMAIN),
                'default' => false
            ],
            'primary_color' => [
                'type' => 'string',
                'format' => 'hex-color',
                'description' => __('Culoarea primară', OC_TEXT_DOMAIN),
                'default' => '#d48945'
            ],
            'text_color' => [
                'type' => 'string',
                'format' => 'hex-color',
                'description' => __('Culoarea textului', OC_TEXT_DOMAIN),
                'default' => '#5f4a40'
            ],
            'secondary_color' => [
                'type' => 'string',
                'format' => 'hex-color',
                'description' => __('Culoarea secundară', OC_TEXT_DOMAIN),
                'default' => '#8d786b'
            ],
            'background_color' => [
                'type' => 'string',
                'format' => 'hex-color',
                'description' => __('Culoarea de fundal', OC_TEXT_DOMAIN),
                'default' => '#f7eee8'
            ],
            'font_family' => [
                'type' => 'string',
                'description' => __('Familia de fonturi', OC_TEXT_DOMAIN),
                'default' => 'Segoe UI, Roboto, Arial, sans-serif'
            ],
            'font_size' => [
                'type' => 'string',
                'description' => __('Dimensiunea fontului', OC_TEXT_DOMAIN),
                'default' => '15px'
            ]
        ];
    }
    
    /**
     * Export settings
     * 
     * @return array
     */
    public static function export() {
        $settings = self::get_all();
        
        return [
            'version' => OC_PLUGIN_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => $settings
        ];
    }
    
    /**
     * Import settings
     * 
     * @param array $data Import data
     * @return bool|WP_Error
     */
    public static function import($data) {
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return new WP_Error('invalid_import', __('Datele de import sunt invalide.', OC_TEXT_DOMAIN));
        }
        
        $settings = $data['settings'];
        $errors = [];
        
        // Validate each setting
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, self::$default_settings)) {
                continue; // Skip unknown settings
            }
            
            $validation = self::validate_setting($key, $value);
            if (is_wp_error($validation)) {
                $errors[] = $validation->get_error_message();
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        // Import valid settings
        return self::set_multiple($settings);
    }
    
    /**
     * Clean up plugin settings (used in uninstall)
     */
    public static function cleanup() {
        foreach (array_keys(self::$default_settings) as $key) {
            self::delete($key);
        }
        
        // Also delete any other plugin options
        delete_option('oc_db_version');
        delete_option('oc_plugin_version');
    }
}
