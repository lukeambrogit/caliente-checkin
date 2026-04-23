<?php
defined('ABSPATH') || exit;
/** Course Mapping Tab - calls course_mapping_page_callback */
$manager_instance = OC_Membership_Manager::get_instance();

if ($manager_instance) {
    $manager_instance->course_mapping_page_callback();
} else {
    echo '<div class="notice notice-warning"><p>Membership Manager component not available.</p></div>';
}
