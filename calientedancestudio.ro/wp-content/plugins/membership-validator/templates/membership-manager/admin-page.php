<?php
/**
 * Template Membership Manager - WITH TABS
 */

if (!defined('ABSPATH')) exit;

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'manager';
$debug_tab_enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');

// Validate - 🎯 v1.2.0: adăugat hours-config
$valid_tabs = ['manager', 'analytics', 'reports', 'mapping', 'hours-config', 'settings', 'shortcodes', 'debug'];
if (!in_array($current_tab, $valid_tabs)) $current_tab = 'manager';

if ($current_tab === 'debug' && !$debug_tab_enabled) {
    $current_tab = 'manager';
}
?>

<div class="wrap oc-manager-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=membership-manager&tab=manager" class="nav-tab <?php echo $current_tab === 'manager' ? 'nav-tab-active' : ''; ?>">📊 Manager</a>
        <a href="?page=membership-manager&tab=analytics" class="nav-tab <?php echo $current_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">📈 Analytics</a>
        <a href="?page=membership-manager&tab=reports" class="nav-tab <?php echo $current_tab === 'reports' ? 'nav-tab-active' : ''; ?>">📋 Reports</a>
        <a href="?page=membership-manager&tab=mapping" class="nav-tab <?php echo $current_tab === 'mapping' ? 'nav-tab-active' : ''; ?>">🎫 Mapping</a>
        <a href="?page=membership-manager&tab=hours-config" class="nav-tab <?php echo $current_tab === 'hours-config' ? 'nav-tab-active' : ''; ?>">⏰ Ore Cursuri</a>
        <a href="?page=membership-manager&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">⚙️ Settings</a>
        <a href="?page=membership-manager&tab=shortcodes" class="nav-tab <?php echo $current_tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>">🎨 Shortcodes</a>
        <?php if ($debug_tab_enabled): ?>
        <a href="?page=membership-manager&tab=debug" class="nav-tab <?php echo $current_tab === 'debug' ? 'nav-tab-active' : ''; ?>">🔧 Debug</a>
        <?php endif; ?>
    </nav>
    
    <div class="oc-manager-tab-content">
        <?php
        $tab_file = dirname(__FILE__) . '/tab-' . $current_tab . '.php';
        if (file_exists($tab_file)) {
            include $tab_file;
        } else {
            echo '<div class="notice notice-error"><p>Tab file not found: tab-' . esc_html($current_tab) . '.php</p></div>';
        }
        ?>
    </div>
</div>

<style>
.oc-manager-tab-content {
    background: #fff;
    min-height: 400px;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
    border-radius: 0 0 4px 4px;
}
</style>

