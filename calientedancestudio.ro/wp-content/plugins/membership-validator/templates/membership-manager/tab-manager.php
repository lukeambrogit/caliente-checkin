<?php
defined('ABSPATH') || exit;
/** Manager Dashboard Tab - ORIGINAL CONTENT from admin_page_callback */
?>
<div class="oc-admin-grid">
    <div class="oc-admin-card">
        <h3>📈 Analytics</h3>
        <p>View detailed analytics and usage statistics for all memberships.</p>
        <a href="?page=membership-manager&tab=analytics" class="button button-primary">View Analytics</a>
    </div>
    <div class="oc-admin-card">
        <h3>📋 Reports</h3>
        <p>Generate and export detailed reports for accounting and management.</p>
        <a href="?page=membership-manager&tab=reports" class="button button-primary">View Reports</a>
    </div>
    <div class="oc-admin-card">
        <h3>⚙️ Settings</h3>
        <p>Configure membership management settings and preferences.</p>
        <a href="?page=membership-manager&tab=settings" class="button button-primary">Manage Settings</a>
    </div>
    <div class="oc-admin-card">
        <h3>🎨 Shortcodes</h3>
        <p>Learn about available shortcodes for frontend integration.</p>
        <a href="?page=membership-manager&tab=shortcodes" class="button button-primary">View Shortcodes</a>
    </div>
</div>

<style>
.oc-admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
.oc-admin-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
.oc-admin-card h3 { margin-top: 0; color: #23282d; }
.oc-admin-card p { color: #646970; margin-bottom: 15px; }
</style>

