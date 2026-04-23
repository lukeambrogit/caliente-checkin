<?php
/**
 * Template pentru pagina de administrare Schedule Manager - WITH TABS
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current tab from URL
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'schedule';
$debug_tab_enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');

// Validate tab
$valid_tabs = ['schedule', 'appearance', 'debug'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'schedule';
}

if ($current_tab === 'debug' && !$debug_tab_enabled) {
    $current_tab = 'schedule';
}
?>

<div class="wrap oc-schedule-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- TAB NAVIGATION -->
    <nav class="nav-tab-wrapper oc-tab-wrapper">
        <a href="?page=orar-cursuri&tab=schedule" 
           class="nav-tab <?php echo $current_tab === 'schedule' ? 'nav-tab-active' : ''; ?>">
            📅 <?php esc_html_e('Schedule Manager', OC_TEXT_DOMAIN); ?>
        </a>
        <a href="?page=orar-cursuri&tab=appearance" 
           class="nav-tab <?php echo $current_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
            🎨 <?php esc_html_e('Appearance Settings', OC_TEXT_DOMAIN); ?>
        </a>
        <?php if ($debug_tab_enabled): ?>
        <a href="?page=orar-cursuri&tab=debug" 
           class="nav-tab <?php echo $current_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
            🔧 <?php esc_html_e('Debug Tools', OC_TEXT_DOMAIN); ?>
        </a>
        <?php endif; ?>
    </nav>
    
    <!-- TAB CONTENT -->
    <div class="oc-schedule-tab-content">
        <?php
        // Include tab content based on current tab
        switch ($current_tab) {
            case 'appearance':
                $tab_file = dirname(__FILE__) . '/tab-appearance.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-error"><p>Template file not found: tab-appearance.php</p></div>';
                }
                break;
                
            case 'debug':
                $tab_file = dirname(__FILE__) . '/tab-debug.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-error"><p>Template file not found: tab-debug.php</p></div>';
                }
                break;
                
            case 'schedule':
            default:
                $tab_file = dirname(__FILE__) . '/tab-schedule.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-error"><p>Template file not found: tab-schedule.php</p></div>';
                }
                break;
        }
        ?>
    </div>
</div>

<style>
/* TAB NAVIGATION STYLES */
.oc-schedule-admin-wrap {
    margin: 20px 20px 0 2px;
}

.oc-tab-wrapper {
    margin: 20px 0;
    border-bottom: 1px solid #ccd0d4;
}

.oc-tab-wrapper .nav-tab {
    font-size: 14px;
    padding: 8px 16px;
    margin: 0 4px -1px 0;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
}

.oc-tab-wrapper .nav-tab:hover {
    background: #fff;
}

.oc-tab-wrapper .nav-tab-active {
    background: #fff;
    border-bottom-color: #fff;
    color: #2271b1;
    font-weight: 600;
}

.oc-schedule-tab-content {
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
