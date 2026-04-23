<?php
/**
 * Constants pentru Membership Validator
 * Centralizare magic numbers pentru mentenabilitate
 * 
 * @package MembershipValidator
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========================================
// BUSINESS LOGIC CONSTANTS
// ========================================

/**
 * Valoare simbolică pentru ședințe nelimitate (VIP memberships)
 * Folosit în DB și logica de validare
 */
define('OC_UNLIMITED_SESSIONS', 999);

/**
 * Interval cache pentru date membership (în secunde)
 * 300 secunde = 5 minute
 */
define('OC_CACHE_INTERVAL', 300);

/**
 * Număr default de rezultate per pagină în AJAX
 */
define('OC_AJAX_DEFAULT_LIMIT', 20);

/**
 * Prioritate maximă pentru WordPress filters
 * Folosit pentru a asigura execuția la final
 */
define('OC_FILTER_PRIORITY_MAX', 999);

/**
 * Dimensiune default pentru QR code (pixeli)
 */
define('OC_QR_DEFAULT_SIZE', 300);

/**
 * Dimensiune avatar default (pixeli)
 */
define('OC_AVATAR_DEFAULT_SIZE', 300);

/**
 * Valoare default pentru maxSelections în pool products
 * Folosit când nu este setată o limită explicită
 */
define('OC_POOL_MAX_SELECTIONS_UNLIMITED', 999);

/**
 * Fallback pentru zile vechi când data comenzii nu este disponibilă
 */
define('OC_ORDER_AGE_FALLBACK_DAYS', 999);

