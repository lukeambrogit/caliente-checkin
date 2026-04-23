<?php
defined('ABSPATH') || exit;
/**
 * Validation Settings Tab Content - ORIGINAL CONTENT
 */

// Procesează salvarea (trebuie să fie înaintea output-ului HTML)
if (isset($_POST['oc_save_membership_settings']) && wp_verify_nonce($_POST['oc_membership_nonce'], 'oc_save_membership_settings')) {
    $validation_timing_rule = sanitize_text_field($_POST['oc_membership_validation_timing_rule'] ?? 'minutes_before_course');
    if (!in_array($validation_timing_rule, ['minutes_before_course', 'once_per_day_after_hour'], true)) {
        $validation_timing_rule = 'minutes_before_course';
    }

    $validation_window_minutes_before = absint($_POST['oc_membership_validation_window_minutes_before'] ?? 30);
    if ($validation_window_minutes_before > 240) {
        $validation_window_minutes_before = 240;
    }

    $once_per_day_start_hour = sanitize_text_field($_POST['oc_membership_once_per_day_start_hour'] ?? '00:00');
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $once_per_day_start_hour)) {
        $once_per_day_start_hour = '00:00';
    }

    $settings = [
        'oc_membership_qr_enabled' => isset($_POST['oc_membership_qr_enabled']) ? '1' : '0',
        'oc_membership_auto_expire' => isset($_POST['oc_membership_auto_expire']) ? '1' : '0',
        'oc_membership_session_limit' => absint($_POST['oc_membership_session_limit'] ?? 10),
        'oc_membership_validation_restriction' => sanitize_text_field($_POST['oc_membership_validation_restriction'] ?? 'none'),
        'oc_membership_cleanup_days' => absint($_POST['oc_membership_cleanup_days'] ?? 30),
        'oc_membership_debug_enabled' => isset($_POST['oc_membership_debug_enabled']) ? '1' : '0',
        'oc_membership_validation_timing_rule' => $validation_timing_rule,
        'oc_membership_validation_window_minutes_before' => (string) $validation_window_minutes_before,
        'oc_membership_once_per_day_start_hour' => $once_per_day_start_hour
    ];
    
    foreach ($settings as $key => $value) {
        update_option($key, $value);
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html__('Setările Membership Validator au fost salvate cu succes!', OC_TEXT_DOMAIN);
    echo '</p></div>';
}

// Get current settings
$qr_enabled = get_option('oc_membership_qr_enabled', '1');
$auto_expire = get_option('oc_membership_auto_expire', '1');
$session_limit = get_option('oc_membership_session_limit', '10');
$validation_restriction = get_option('oc_membership_validation_restriction', 'none');
$cleanup_days = get_option('oc_membership_cleanup_days', '30');
$debug_enabled = get_option('oc_membership_debug_enabled', '0');
$validation_timing_rule = get_option('oc_membership_validation_timing_rule', 'minutes_before_course');
$validation_window_minutes_before = (string) get_option('oc_membership_validation_window_minutes_before', '30');
$once_per_day_start_hour = get_option('oc_membership_once_per_day_start_hour', '00:00');

if (!in_array($validation_timing_rule, ['minutes_before_course', 'once_per_day_after_hour'], true)) {
    $validation_timing_rule = 'minutes_before_course';
}

$allowed_minutes = ['0', '10', '15', '20', '30', '45', '60', '90', '120', '180', '240'];
if (!in_array($validation_window_minutes_before, $allowed_minutes, true)) {
    $validation_window_minutes_before = '30';
}

if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $once_per_day_start_hour)) {
    $once_per_day_start_hour = '00:00';
}
?>

<form method="post" action="">
    <?php wp_nonce_field('oc_save_membership_settings', 'oc_membership_nonce'); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php echo __('QR Codes Activate', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input type="checkbox" name="oc_membership_qr_enabled" value="1" <?php checked($qr_enabled, '1'); ?> />
                <p class="description"><?php echo __('Activează generarea codurilor QR pentru abonamente.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php echo __('Auto Expirare', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input type="checkbox" name="oc_membership_auto_expire" value="1" <?php checked($auto_expire, '1'); ?> />
                <p class="description"><?php echo __('Expirează automat abonamentele după data limită.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php echo __('Limită Ședințe Default', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input type="number" name="oc_membership_session_limit" value="<?php echo esc_attr($session_limit); ?>" min="1" max="100" />
                <p class="description"><?php echo __('Numărul default de ședințe pentru abonamente noi.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php echo __('Restricție Validare', OC_TEXT_DOMAIN); ?></th>
            <td>
                <select name="oc_membership_validation_restriction">
                    <option value="none" <?php selected($validation_restriction, 'none'); ?>><?php echo __('Fără restricții', OC_TEXT_DOMAIN); ?></option>
                    <option value="once_per_day" <?php selected($validation_restriction, 'once_per_day'); ?>><?php echo __('O dată pe zi', OC_TEXT_DOMAIN); ?></option>
                    <option value="once_per_session" <?php selected($validation_restriction, 'once_per_session'); ?>><?php echo __('O dată pe ședință', OC_TEXT_DOMAIN); ?></option>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php echo __('Regulă validare (alege una)', OC_TEXT_DOMAIN); ?></th>
            <td>
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="oc_membership_validation_timing_rule" value="minutes_before_course" <?php checked($validation_timing_rule, 'minutes_before_course'); ?> />
                    <?php echo __('Minute înainte de curs (înainte de validare)', OC_TEXT_DOMAIN); ?>
                </label>
                <label style="display:block;">
                    <input type="radio" name="oc_membership_validation_timing_rule" value="once_per_day_after_hour" <?php checked($validation_timing_rule, 'once_per_day_after_hour'); ?> />
                    <?php echo __('O dată pe zi, după ora setată', OC_TEXT_DOMAIN); ?>
                </label>
                <p class="description"><?php echo __('Regulile nu funcționează simultan: se aplică doar regula selectată.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php echo __('Minute înainte de curs', OC_TEXT_DOMAIN); ?></th>
            <td>
                <select id="oc-membership-minutes-before" name="oc_membership_validation_window_minutes_before">
                    <?php foreach ($allowed_minutes as $minutes): ?>
                        <option value="<?php echo esc_attr($minutes); ?>" <?php selected($validation_window_minutes_before, $minutes); ?>>
                            <?php echo esc_html($minutes); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php echo __('Ex: 30 înseamnă că validarea este permisă cu 30 de minute înainte de ora de start.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php echo __('Ora start validare (o dată pe zi)', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input id="oc-membership-once-hour" type="time" name="oc_membership_once_per_day_start_hour" value="<?php echo esc_attr($once_per_day_start_hour); ?>" step="900" />
                <p class="description"><?php echo __('Validarea devine permisă după această oră, iar resetarea rămâne la 00:00 (zi calendaristică).', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php echo __('Cleanup după (zile)', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input type="number" name="oc_membership_cleanup_days" value="<?php echo esc_attr($cleanup_days); ?>" min="1" max="365" />
                <p class="description"><?php echo __('Șterge datele vechi după numărul specificat de zile.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php echo __('Debug Mode', OC_TEXT_DOMAIN); ?></th>
            <td>
                <input type="checkbox" name="oc_membership_debug_enabled" value="1" <?php checked($debug_enabled, '1'); ?> />
                <p class="description"><?php echo __('Activează logging extins pentru debugging.', OC_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
    </table>
    
    <?php submit_button(__('Salvează Setările', OC_TEXT_DOMAIN), 'primary', 'oc_save_membership_settings'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var radios = document.querySelectorAll('input[name="oc_membership_validation_timing_rule"]');
    var minutesField = document.getElementById('oc-membership-minutes-before');
    var hourField = document.getElementById('oc-membership-once-hour');

    function refreshTimingFields() {
        var selected = document.querySelector('input[name="oc_membership_validation_timing_rule"]:checked');
        var rule = selected ? selected.value : 'minutes_before_course';

        if (minutesField) {
            minutesField.disabled = rule !== 'minutes_before_course';
        }
        if (hourField) {
            hourField.disabled = rule !== 'once_per_day_after_hour';
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', refreshTimingFields);
    });

    refreshTimingFields();
});
</script>
