<?php
defined('ABSPATH') || exit;
/** Manager Settings Tab - ORIGINAL CONTENT from settings_page_callback */

// Process form submission
if (isset($_POST['submit']) && check_admin_referer('oc_membership_settings')) {
    $settings = [
        'default_membership_duration' => sanitize_text_field($_POST['default_membership_duration'] ?? '30'),
        'max_daily_sessions' => absint($_POST['max_daily_sessions'] ?? 2),
        'enable_email_notifications' => isset($_POST['enable_email_notifications']),
        'require_validation_confirmation' => isset($_POST['require_validation_confirmation'])
    ];
    
    update_option('oc_membership_settings', $settings);
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

$settings = get_option('oc_membership_settings', [
    'default_membership_duration' => '28',
    'max_daily_sessions' => '2',
    'enable_email_notifications' => false,
    'require_validation_confirmation' => false
]);
?>

<h1><?php echo esc_html__('Membership Settings', OC_TEXT_DOMAIN); ?></h1>

<form method="post" action="">
    <?php wp_nonce_field('oc_membership_settings'); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">Default Membership Duration (days)</th>
            <td>
                <input type="number" name="default_membership_duration" 
                       value="<?php echo esc_attr($settings['default_membership_duration']); ?>" 
                       min="1" max="365" />
                <p class="description">Durata standard pentru abonamente noi (recomandare: 28 zile = 1 lună). Acest câmp determină perioada de valabilitate automată pentru toate abonamentele.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Max Daily Sessions</th>
            <td>
                <input type="number" name="max_daily_sessions" 
                       value="<?php echo esc_attr($settings['max_daily_sessions']); ?>" 
                       min="1" max="10" />
                <p class="description">Maximum sessions a member can validate per day.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Email Notifications</th>
            <td>
                <label>
                    <input type="checkbox" name="enable_email_notifications" 
                           <?php checked($settings['enable_email_notifications']); ?> />
                    Send email notifications for membership activities
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Validation Confirmation</th>
            <td>
                <label>
                    <input type="checkbox" name="require_validation_confirmation" 
                           <?php checked($settings['require_validation_confirmation']); ?> />
                    Require confirmation for each validation
                </label>
            </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>
</form>
