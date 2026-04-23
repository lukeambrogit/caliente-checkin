<?php
/**
 * Shared order/payment helpers for Membership Validator.
 *
 * Extracted from existing DB/Sync logic without changing business rules.
 * These functions are loaded before the addon classes so they can be reused
 * safely across bootstrap boundaries.
 *
 * @package MembershipValidator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('oc_membership_is_known_payment_status')) {
    function oc_membership_is_known_payment_status(string $payment_status): bool {
        return in_array(sanitize_key($payment_status), ['paid', 'unpaid', 'partial'], true);
    }
}

if (!function_exists('oc_membership_map_order_status_to_payment')) {
    function oc_membership_map_order_status_to_payment(string $order_status): string {
        switch (sanitize_key($order_status)) {
            case 'completed':
            case 'processing':
                return 'paid';
            case 'on-hold':
                return 'partial';
            case 'pending':
            case 'cancelled':
            case 'refunded':
            case 'failed':
            default:
                return 'unpaid';
        }
    }
}

if (!function_exists('oc_membership_resolve_order_payment_status')) {
    function oc_membership_resolve_order_payment_status(WC_Order $order): string {
        $requested_payment_status = sanitize_key((string) $order->get_meta('_oc_requested_payment_status'));
        if (oc_membership_is_known_payment_status($requested_payment_status)) {
            return $requested_payment_status;
        }

        if ($order->is_paid()) {
            return 'paid';
        }

        return oc_membership_map_order_status_to_payment($order->get_status());
    }
}

if (!function_exists('oc_membership_normalize_payment_method_key')) {
    function oc_membership_normalize_payment_method_key(string $payment_method_id, string $payment_method_title = ''): string {
        $raw = trim($payment_method_id . ' ' . $payment_method_title);
        if ($raw === '') {
            return 'card';
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($raw, 'UTF-8')
            : strtolower($raw);

        if (strpos($normalized, '7card') !== false) {
            return 'oc_7card';
        }
        if (strpos($normalized, 'esx') !== false) {
            return 'oc_esx';
        }
        if (strpos($normalized, 'transfer') !== false || strpos($normalized, 'bacs') !== false || strpos($normalized, 'iban') !== false) {
            return 'transfer';
        }
        if (strpos($normalized, 'cash') !== false || strpos($normalized, 'numerar') !== false || strpos($normalized, 'studio') !== false || strpos($normalized, 'cod') !== false) {
            return 'cash';
        }
        if (strpos($normalized, 'card') !== false || strpos($normalized, 'stripe') !== false || strpos($normalized, 'netopia') !== false) {
            return 'card';
        }

        return 'card';
    }
}

if (!function_exists('oc_membership_is_gateway_payment_method')) {
    function oc_membership_is_gateway_payment_method(string $payment_method_id, string $payment_method_title = ''): bool {
        return in_array(
            oc_membership_normalize_payment_method_key($payment_method_id, $payment_method_title),
            ['oc_7card', 'oc_esx'],
            true
        );
    }
}

if (!function_exists('oc_membership_resolve_order_package_price')) {
    function oc_membership_resolve_order_package_price(WC_Order $order): float {
        $custom_package_price = $order->get_meta('_oc_custom_package_price');
        if ($custom_package_price !== '' && is_numeric($custom_package_price)) {
            return max(0, round((float) $custom_package_price, 2));
        }

        foreach ($order->get_items() as $order_item) {
            if ((int) $order_item->get_variation_id() !== 0) {
                continue;
            }

            return max(0, round((float) $order_item->get_total(), 2));
        }

        return max(0, round((float) $order->get_total(), 2));
    }
}