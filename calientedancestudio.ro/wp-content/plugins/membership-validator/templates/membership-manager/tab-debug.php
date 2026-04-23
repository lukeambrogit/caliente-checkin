<?php
defined('ABSPATH') || exit;

$debug_enabled = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');
if (!$debug_enabled) {
    wp_die(esc_html__('You do not have permission to view this page.', OC_TEXT_DOMAIN));
}

$canonical_template = dirname(__FILE__, 2) . '/developer-debug-page.php';
if (file_exists($canonical_template)) {
    include $canonical_template;
    return;
}

wp_die(esc_html__('Debug template not found.', OC_TEXT_DOMAIN));
