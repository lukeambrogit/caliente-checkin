<?php
/**
 * Core Settings page template
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-core-settings">
    <?php
    $oc_core_notice = $oc_core_notice ?? null;
    if (is_array($oc_core_notice) && !empty($oc_core_notice['message'])) :
        $notice_type = in_array(($oc_core_notice['type'] ?? ''), ['success', 'error', 'warning', 'info'], true)
            ? $oc_core_notice['type']
            : 'info';
        ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html((string) $oc_core_notice['message']); ?></p></div>
    <?php endif; ?>

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('Core Settings', OC_TEXT_DOMAIN); ?>
    </h1>
    
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
            <?php esc_html_e('General', OC_TEXT_DOMAIN); ?>
        </a>
        <a href="#appearance" class="nav-tab" data-tab="appearance">
            <?php esc_html_e('Appearance', OC_TEXT_DOMAIN); ?>
        </a>
        <a href="#developer" class="nav-tab" data-tab="developer">
            <?php esc_html_e('Developer', OC_TEXT_DOMAIN); ?>
        </a>
    </nav>
    
    <div class="oc-tabs-content">
        <!-- General Tab -->
        <div id="tab-general" class="oc-tab-content active">
            <form method="post" action="">
                <?php wp_nonce_field('oc_save_core_settings', 'oc_core_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    <tbody>


                        
                        <tr>
                            <th scope="row">
                                <label for="oc_api_enabled"><?php esc_html_e('Enable API', OC_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="oc_api_enabled" name="oc_api_enabled" value="1" 
                                           <?php checked(get_option('oc_api_enabled', '0'), '1'); ?> />
                                    <?php esc_html_e('Enable REST API endpoints for external integrations', OC_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Allow external applications to access membership and schedule data via REST API.', OC_TEXT_DOMAIN); ?>
                                </p>
                            </td>
                        </tr>

                        <?php
                        // Resolve current API key (constant takes priority over DB option)
                        $oc_api_key    = '';
                        $oc_key_source = '';
                        if (defined('OC_MEMBERSHIP_API_KEY') && constant('OC_MEMBERSHIP_API_KEY') !== '') {
                            $oc_api_key    = constant('OC_MEMBERSHIP_API_KEY');
                            $oc_key_source = 'wp-config.php';
                        } else {
                            $oc_db_key = get_option('oc_membership_api_key', '');
                            if ($oc_db_key !== '') {
                                $oc_api_key    = $oc_db_key;
                                $oc_key_source = 'database';
                            }
                        }
                        $oc_api_enabled = get_option('oc_api_enabled', '0') === '1';
                        $oc_api_key_masked = '';
                        if ($oc_api_key !== '') {
                            $oc_api_key_length = strlen($oc_api_key);
                            if ($oc_api_key_length <= 16) {
                                $oc_api_key_masked = str_repeat('*', $oc_api_key_length);
                            } else {
                                $oc_api_key_masked = substr($oc_api_key, 0, 8)
                                    . str_repeat('*', max(8, $oc_api_key_length - 16))
                                    . substr($oc_api_key, -8);
                            }
                        }
                        ?>
                        <tr id="oc-api-key-row" style="<?php echo esc_attr($oc_api_enabled ? '' : 'display:none'); ?>">
                            <th scope="row"><?php esc_html_e('API Key', OC_TEXT_DOMAIN); ?></th>
                            <td>
                                <?php if ($oc_api_key !== '') : ?>
                                    <div style="display:flex;align-items:center;gap:8px;max-width:520px;">
                                        <input type="text" id="oc-api-key-display"
                                               value="<?php echo esc_attr($oc_api_key_masked); ?>"
                                               readonly
                                               class="regular-text"
                                               style="font-family:monospace;flex:1;" />
                                        <button type="button" id="oc-copy-api-key" class="button" data-copy-value="<?php echo esc_attr($oc_api_key); ?>">
                                            <?php esc_html_e('Copiaza', OC_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php
                                        printf(
                                            /* translators: %s: source location */
                                            esc_html__('Source: %s', OC_TEXT_DOMAIN),
                                            '<code>' . esc_html($oc_key_source) . '</code>'
                                        );
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <p class="description">
                                        <?php esc_html_e('Save settings to auto-generate an API key.', OC_TEXT_DOMAIN); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php
                        $active_edit_pin_is_set = (string) get_option('oc_membership_active_edit_pin_hash', '') !== ''
                            || (string) get_option('oc_membership_active_edit_pin', '') !== '';
                        $pin_notice_context = is_array($oc_core_notice) ? (string) ($oc_core_notice['context'] ?? '') : '';
                        $show_pin_change_form = ($pin_notice_context === 'pin_change_error');
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="oc_active_membership_edit_pin"><?php esc_html_e('PIN editare abonament activ', OC_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <?php if ($active_edit_pin_is_set) : ?>
                                    <strong><?php esc_html_e('PIN setat pentru protectie', OC_TEXT_DOMAIN); ?></strong>
                                    <div style="margin-top:10px;">
                                        <button type="button" class="button" id="oc-toggle-change-pin">
                                            <?php esc_html_e('Schimba PIN', OC_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                    <div id="oc-change-pin-fields" style="margin-top:12px;<?php echo $show_pin_change_form ? '' : 'display:none;'; ?>">
                                        <p>
                                            <label for="oc_active_membership_edit_pin_old"><strong><?php esc_html_e('PIN vechi', OC_TEXT_DOMAIN); ?></strong></label><br>
                                            <input type="password" id="oc_active_membership_edit_pin_old" name="oc_active_membership_edit_pin_old" class="regular-text" autocomplete="off" />
                                        </p>
                                        <p>
                                            <label for="oc_active_membership_edit_pin_new"><strong><?php esc_html_e('PIN nou', OC_TEXT_DOMAIN); ?></strong></label><br>
                                            <input type="password" id="oc_active_membership_edit_pin_new" name="oc_active_membership_edit_pin_new" class="regular-text" autocomplete="new-password" />
                                        </p>
                                        <p>
                                            <label for="oc_active_membership_edit_pin_confirm"><strong><?php esc_html_e('Confirma PIN nou', OC_TEXT_DOMAIN); ?></strong></label><br>
                                            <input type="password" id="oc_active_membership_edit_pin_confirm" name="oc_active_membership_edit_pin_confirm" class="regular-text" autocomplete="new-password" />
                                        </p>
                                        <p>
                                            <button type="submit" name="oc_change_active_pin" value="1" class="button button-secondary">
                                                <?php esc_html_e('Salveaza PIN nou', OC_TEXT_DOMAIN); ?>
                                            </button>
                                        </p>
                                    </div>
                                <?php else : ?>
                                    <input type="password"
                                           id="oc_active_membership_edit_pin"
                                           name="oc_active_membership_edit_pin"
                                           class="regular-text"
                                           autocomplete="new-password"
                                           placeholder="Introdu PIN" />
                                    <p class="description">
                                        <?php esc_html_e('Seteaza PIN-ul folosit de butonul "Editeaza abonament activ".', OC_TEXT_DOMAIN); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="oc_logging_enabled"><?php esc_html_e('Enable Logging', OC_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="oc_logging_enabled" name="oc_logging_enabled" value="1" 
                                           <?php checked(get_option('oc_logging_enabled', '0'), '1'); ?> />
                                    <?php esc_html_e('Log membership validation events and errors', OC_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Keep logs of membership validations, ADD-ON activations, and system errors.', OC_TEXT_DOMAIN); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('Save General Settings', OC_TEXT_DOMAIN), 'primary', 'oc_save_core_settings'); ?>
            </form>
        </div>
        
        <!-- Appearance Tab -->
        <div id="tab-appearance" class="oc-tab-content">
            <div class="oc-redirect-notice">
                <h3><?php esc_html_e('Appearance Settings', OC_TEXT_DOMAIN); ?></h3>
                <p><?php esc_html_e('Appearance settings have been moved to their dedicated page for better organization.', OC_TEXT_DOMAIN); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=orar-cursuri&tab=appearance')); ?>" class="button button-primary">
                    <?php esc_html_e('Go to Appearance Settings', OC_TEXT_DOMAIN); ?>
                </a>
            </div>
        </div>
        
        <!-- Developer Tab -->
        <div id="tab-developer" class="oc-tab-content">
            <form method="post" action="">
                <?php wp_nonce_field('oc_save_core_settings', 'oc_core_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="oc_enable_debug"><?php esc_html_e('Debug Mode', OC_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="oc_enable_debug" name="oc_enable_debug" value="1" 
                                           <?php checked(get_option('oc_enable_debug', '0'), '1'); ?> />
                                    <?php esc_html_e('Enable debug mode for detailed error reporting', OC_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Shows detailed error messages and debug information. Use only in development.', OC_TEXT_DOMAIN); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="oc-developer-tools">
                    <h3><?php esc_html_e('Developer Tools', OC_TEXT_DOMAIN); ?></h3>
                    
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <div class="oc-debug-tools">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=orar-cursuri&tab=debug')); ?>" class="button button-secondary">
                                <?php esc_html_e('Open Debug Console', OC_TEXT_DOMAIN); ?>
                            </a>
                            

                        </div>
                    <?php else: ?>
                        <p class="description">
                            <?php esc_html_e('To access advanced developer tools, enable WP_DEBUG in your wp-config.php file.', OC_TEXT_DOMAIN); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php submit_button(__('Save Developer Settings', OC_TEXT_DOMAIN), 'primary', 'oc_save_core_settings'); ?>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tab = $(this).data('tab');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.oc-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Show/hide API key row when checkbox toggles
    $('#oc_api_enabled').on('change', function() {
        $('#oc-api-key-row').toggle(this.checked);
    });

    // Copy API key to clipboard (works on HTTP too, not just HTTPS)
    $('#oc-copy-api-key').on('click', function() {
        var $btn   = $(this);
        var val    = String($btn.data('copyValue') || '');

        if (!val) {
            $btn.text('Eroare!');
            setTimeout(function() { $btn.text('Copiaza'); }, 1500);
            return;
        }

        // Modern Clipboard API (HTTPS / localhost only)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(val).then(function() {
                $btn.text('Copiat!');
                setTimeout(function() { $btn.text('Copiaza'); }, 1500);
            });
            return;
        }

        // Fallback: temporary field + execCommand (works on plain HTTP)
        var $temp = $('<textarea readonly></textarea>')
            .css({
                position: 'absolute',
                left: '-9999px',
                top: '0'
            })
            .val(val)
            .appendTo('body');

        $temp[0].select();
        $temp[0].setSelectionRange(0, val.length);
        try {
            document.execCommand('copy');
            $btn.text('Copiat!');
            setTimeout(function() { $btn.text('Copiaza'); }, 1500);
        } catch(e) {
            $btn.text('Eroare!');
            setTimeout(function() { $btn.text('Copiaza'); }, 1500);
        }
        $temp.remove();
        window.getSelection && window.getSelection().removeAllRanges();
    });

    $('#oc-toggle-change-pin').on('click', function() {
        $('#oc-change-pin-fields').slideToggle(150);
    });
});


</script>
