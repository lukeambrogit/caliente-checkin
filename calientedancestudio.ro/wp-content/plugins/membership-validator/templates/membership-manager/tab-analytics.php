<?php
defined('ABSPATH') || exit;
/** Analytics Tab - calls admin->analytics_page_callback */
$manager_instance = OC_Membership_Manager::get_instance();
$admin = $manager_instance ? $manager_instance->get_admin() : null;

if ($admin) {
    $admin->analytics_page_callback();
} else {
    echo '<div class="notice notice-warning"><p>Analytics component not available.</p></div>';
}

