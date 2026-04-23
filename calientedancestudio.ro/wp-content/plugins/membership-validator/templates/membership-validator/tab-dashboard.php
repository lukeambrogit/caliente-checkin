<?php
defined('ABSPATH') || exit;
/**
 * Validator Dashboard Tab Content - ORIGINAL CONTENT
 */

// Conținutul EXACT din render_default_admin_page()
?>

<div class="notice notice-info"><p>
    <?php echo __('ADD-ON-ul Membership Validator este activ și funcțional!', OC_TEXT_DOMAIN); ?>
</p></div>

<?php
// Statistici rapide
global $wpdb;
$table_name = $wpdb->prefix . 'membership_validations';
$total_memberships = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$active_memberships = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE validation_status = 'active'");
?>

<div class="card">
    <h2><?php echo __('Statistici', OC_TEXT_DOMAIN); ?></h2>
    <p><strong><?php echo __('Total Abonamente:', OC_TEXT_DOMAIN); ?></strong> <?php echo intval($total_memberships); ?></p>
    <p><strong><?php echo __('Abonamente Active:', OC_TEXT_DOMAIN); ?></strong> <?php echo intval($active_memberships); ?></p>
</div>
