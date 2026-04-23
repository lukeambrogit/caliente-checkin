<?php
/**
 * Template pentru pagina de administrare Membership Validator - WITH TABS
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current tab from URL
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

// Validate tab
$valid_tabs = ['dashboard', 'settings'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'dashboard';
}
?>

<div class="wrap oc-validator-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- TAB NAVIGATION -->
    <nav class="nav-tab-wrapper oc-validator-tab-wrapper">
        <a href="?page=membership-validator&tab=dashboard" 
           class="nav-tab <?php echo $current_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
            🎫 <?php esc_html_e('Validator Dashboard', OC_TEXT_DOMAIN); ?>
        </a>
        <a href="?page=membership-validator&tab=settings" 
           class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            ⚙️ <?php esc_html_e('Validation Settings', OC_TEXT_DOMAIN); ?>
        </a>
    </nav>
    
    <!-- TAB CONTENT -->
    <div class="oc-validator-tab-content">
        <?php
        // Include tab content based on current tab
        switch ($current_tab) {
            case 'settings':
                $tab_file = dirname(__FILE__) . '/tab-settings.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-error"><p>Template file not found: tab-settings.php</p></div>';
                }
                break;
                
            case 'dashboard':
            default:
                $tab_file = dirname(__FILE__) . '/tab-dashboard.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-error"><p>Template file not found: tab-dashboard.php</p></div>';
                }
                break;
        }
        ?>
    </div>
</div>

<style>
/* TAB NAVIGATION STYLES */
.oc-validator-admin-wrap {
    margin: 20px 20px 0 2px;
}

.oc-validator-tab-wrapper {
    margin: 20px 0;
    border-bottom: 1px solid #ccd0d4;
}

.oc-validator-tab-wrapper .nav-tab {
    font-size: 14px;
    padding: 8px 16px;
    margin: 0 4px -1px 0;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
}

.oc-validator-tab-wrapper .nav-tab:hover {
    background: #fff;
}

.oc-validator-tab-wrapper .nav-tab-active {
    background: #fff;
    border-bottom-color: #fff;
    color: #2271b1;
    font-weight: 600;
}

.oc-validator-tab-content {
    background: #fff;
    min-height: 400px;
    margin: 0;
    padding: 20px;
    display: block;
    border: 1px solid #ccd0d4;
    border-top: none;
    border-radius: 0 0 4px 4px;
}
</style>

