<?php
/**
 * Dashboard page template
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$dashboard = new OC_Dashboard();
$system_status = $dashboard->get_system_status();

// Ensure variables are defined even if ADD-ON Manager isn't loaded
if (!isset($addons)) $addons = [];
if (!isset($active_addons)) $active_addons = [];
?>

<div class="wrap oc-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups"></span>
        <?php esc_html_e('Membership Validator Core', OC_TEXT_DOMAIN); ?>
    </h1>
    
    <div class="oc-dashboard-grid">
        <!-- System Overview -->
        <div class="oc-card oc-system-overview">
            <h2><?php esc_html_e('System Overview', OC_TEXT_DOMAIN); ?></h2>
            
            <div class="oc-stats-grid">
                <div class="oc-stat">
                    <div class="oc-stat-number"><?php echo esc_html($system_status['plugin_version']); ?></div>
                    <div class="oc-stat-label"><?php esc_html_e('Core Version', OC_TEXT_DOMAIN); ?></div>
                </div>
                
                <div class="oc-stat">
                    <div class="oc-stat-number"><?php echo esc_html($system_status['active_addons_count']); ?>/<?php echo esc_html($system_status['total_addons_count']); ?></div>
                    <div class="oc-stat-label"><?php esc_html_e('Active ADD-ONS', OC_TEXT_DOMAIN); ?></div>
                </div>
                
                <div class="oc-stat">
                    <div class="oc-stat-number"><?php echo esc_html($system_status['schedule_entries'] ?? '0'); ?></div>
                    <div class="oc-stat-label"><?php esc_html_e('Schedule Entries', OC_TEXT_DOMAIN); ?></div>
                </div>
                

            </div>
            
            <div class="oc-system-details">
                <h3><?php esc_html_e('System Information', OC_TEXT_DOMAIN); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('WordPress:', OC_TEXT_DOMAIN); ?></strong> <?php echo esc_html($system_status['wordpress_version']); ?></li>
                    <li><strong><?php esc_html_e('PHP:', OC_TEXT_DOMAIN); ?></strong> <?php echo esc_html($system_status['php_version']); ?></li>
                    <li><strong><?php esc_html_e('WooCommerce:', OC_TEXT_DOMAIN); ?></strong> <?php echo esc_html($system_status['woocommerce_version']); ?></li>
                    <li><strong><?php esc_html_e('Database Version:', OC_TEXT_DOMAIN); ?></strong> <?php echo esc_html($system_status['database_version']); ?></li>
                </ul>
            </div>
        </div>
        
        <!-- ADD-ONS Management -->
        <div class="oc-card oc-addons-management">
            <h2><?php esc_html_e('ADD-ONS Management', OC_TEXT_DOMAIN); ?></h2>
            
            <?php if (empty($addons)): ?>
                <div class="oc-no-addons">
                    <p><?php esc_html_e('No ADD-ONS are registered yet.', OC_TEXT_DOMAIN); ?></p>
                </div>
            <?php else: ?>
                <div class="oc-addons-list">
                    <?php foreach ($addons as $addon_id => $addon): ?>
                        <?php $is_active = in_array($addon_id, $active_addons); ?>
                        <div class="oc-addon-item <?php echo $is_active ? 'active' : 'inactive'; ?>" data-addon="<?php echo esc_attr($addon_id); ?>">
                            <div class="oc-addon-icon">
                                <span class="dashicons <?php echo esc_attr($addon['icon']); ?>"></span>
                            </div>
                            
                            <div class="oc-addon-info">
                                <h3><?php echo esc_html($addon['name']); ?></h3>
                                <p><?php echo esc_html($addon['description']); ?></p>
                                <div class="oc-addon-meta">
                                    <span class="oc-version"><?php esc_html_e('Version:', OC_TEXT_DOMAIN); ?> <?php echo esc_html($addon['version']); ?></span>
                                    <?php if (!empty($addon['author'])): ?>
                                        <span class="oc-author"><?php esc_html_e('By:', OC_TEXT_DOMAIN); ?> <?php echo esc_html($addon['author']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($addon['builtin']): ?>
                                        <span class="oc-builtin"><?php esc_html_e('Built-in', OC_TEXT_DOMAIN); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="oc-addon-actions">
                                <div class="oc-status-badge oc-status-<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $is_active ? esc_html__('Active', OC_TEXT_DOMAIN) : esc_html__('Inactive', OC_TEXT_DOMAIN); ?>
                                </div>
                                
                                <button type="button" 
                                        class="button oc-toggle-addon <?php echo $is_active ? 'button-secondary' : 'button-primary'; ?>"
                                        data-addon="<?php echo esc_attr($addon_id); ?>"
                                        data-action="<?php echo $is_active ? 'deactivate' : 'activate'; ?>">
                                    <?php echo $is_active ? esc_html__('Deactivate', OC_TEXT_DOMAIN) : esc_html__('Activate', OC_TEXT_DOMAIN); ?>
                                </button>
                                
                                <?php if ($is_active && !empty($addon['settings_page'])): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $addon['settings_page'])); ?>" 
                                       class="button button-secondary">
                                        <?php esc_html_e('Settings', OC_TEXT_DOMAIN); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="oc-card oc-quick-actions">
            <h2><?php esc_html_e('Quick Actions', OC_TEXT_DOMAIN); ?></h2>
            
            <div class="oc-actions-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=membership-validator-core-settings')); ?>" class="oc-action-button">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span><?php esc_html_e('Core Settings', OC_TEXT_DOMAIN); ?></span>
                </a>
                
                <?php if (class_exists('OC_Addon_Manager') && OC_Addon_Manager::is_addon_active('schedule_manager')): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orar-cursuri')); ?>" class="oc-action-button">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php esc_html_e('Manage Schedule', OC_TEXT_DOMAIN); ?></span>
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=orar-cursuri&tab=appearance')); ?>" class="oc-action-button">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <span><?php esc_html_e('Appearance', OC_TEXT_DOMAIN); ?></span>
                </a>
                
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=orar-cursuri&tab=debug')); ?>" class="oc-action-button">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <span><?php esc_html_e('Debug Tools', OC_TEXT_DOMAIN); ?></span>
                    </a>
                <?php endif; ?>
                
                <button type="button" class="oc-action-button oc-check-database" id="oc-check-database-btn">
                    <span class="dashicons dashicons-database"></span>
                    <span><?php esc_html_e('Check Database', OC_TEXT_DOMAIN); ?></span>
                </button>
            </div>
        </div>
        
        <!-- Recent Activity (placeholder for future) -->
        <div class="oc-card oc-recent-activity">
            <h2><?php esc_html_e('Recent Activity', OC_TEXT_DOMAIN); ?></h2>
            <div class="oc-activity-placeholder">
                <p><?php esc_html_e('Activity logging will be available in future updates.', OC_TEXT_DOMAIN); ?></p>
            </div>
        </div>
    </div>
</div>
