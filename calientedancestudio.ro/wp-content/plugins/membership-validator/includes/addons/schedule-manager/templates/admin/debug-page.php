<?php
/**
 * Debug Page Wrapper (Schedule Manager)
 *
 * Keep a single source of truth for debug UI in `templates/developer-debug-page.php`
 * and include it from Schedule Manager.
 */

if (!defined('ABSPATH')) {
    exit;
}

$canonical_template = dirname(__FILE__, 6) . '/templates/developer-debug-page.php';
if (file_exists($canonical_template)) {
    include $canonical_template;
    return;
}

wp_die(esc_html__('Debug template not found.', OC_TEXT_DOMAIN));
