<?php
defined('ABSPATH') || exit;
/** Reports Tab - calls admin->reports_page_callback */
$manager_instance = OC_Membership_Manager::get_instance();
$admin = $manager_instance ? $manager_instance->get_admin() : null;

if ($admin) {
    $admin->reports_page_callback();
} else {
    echo '<div class="notice notice-warning"><p>Reports component not available.</p></div>';
}

