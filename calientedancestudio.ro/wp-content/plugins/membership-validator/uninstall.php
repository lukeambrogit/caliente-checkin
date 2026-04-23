<?php
/**
 * Uninstall script for Orar Cursuri Plugin
 * 
 * This file is executed when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data including database tables and options.
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function oc_uninstall_cleanup() {
    global $wpdb;

    $drop_tables = [
        $wpdb->prefix . 'orar_cursuri',
        $wpdb->prefix . 'membership_validations',
        $wpdb->prefix . 'membership_course_mapping',
        $wpdb->prefix . 'membership_validation_log',
        $wpdb->prefix . 'membership_analytics',
        $wpdb->prefix . 'pool_products',
        $wpdb->prefix . 'pool_selections',
    ];
    
    foreach ($drop_tables as $table_name) {
        $safe_table_name = esc_sql($table_name);
        $wpdb->query("DROP TABLE IF EXISTS `{$safe_table_name}`");
    }
    
    // Delete plugin options
    $options_to_delete = [
        'oc_attribute_taxonomy',
        'oc_show_empty_days',
        'oc_primary_color',
        'oc_text_color',
        'oc_secondary_color',
        'oc_background_color',
        'oc_muted_color',
        'oc_border_color',
        'oc_font_family',
        'oc_font_size',
        'oc_header_font_size',
        'oc_border_radius',
        'oc_desktop_bg_image',
        'oc_mobile_bg_image',
        'oc_cache_duration',
        'oc_enable_cache',
        'oc_load_assets_everywhere',
        'oc_custom_css',
        'oc_show_course_descriptions',
        'oc_enable_tooltips',
        'oc_date_format',
        'oc_time_format',
        'oc_db_version',
        'oc_plugin_version',
        // ADD-ON Manager options
        'oc_active_addons',
        'oc_selected_product',
        'oc_selected_attribute',
        // Membership options
        'oc_membership_settings',
        'oc_membership_db_version',
        // Pool Product options
        'oc_pool_settings'
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
        delete_site_option($option); // For multisite
    }
    
    // Clear caches
    wp_cache_flush();
    
    // Delete transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_orar_cursuri_%' 
         OR option_name LIKE '_transient_timeout_orar_cursuri_%'"
    );
    
    // For multisite installations
    if (is_multisite()) {
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} 
             WHERE meta_key LIKE '_transient_orar_cursuri_%' 
             OR meta_key LIKE '_transient_timeout_orar_cursuri_%'"
        );
    }
    
    // Clear any remaining cache
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('orar_cursuri');
    }
}

// Execute cleanup
oc_uninstall_cleanup();
