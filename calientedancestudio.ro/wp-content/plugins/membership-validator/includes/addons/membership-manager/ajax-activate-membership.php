<?php
/**
 * 🔒 AJAX Handler: Activare manuală abonament pending → active
 *
 * @package MembershipValidator
 * @since 1.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class OC_Membership_Activation_AJAX {

    public function __construct() {
        add_action('wp_ajax_oc_activate_membership_manual', [$this, 'activate_membership_manual']);
    }

    public function activate_membership_manual(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $activation_date = sanitize_text_field($_POST['activation_date'] ?? '');
        $preserved_expiration_date = sanitize_text_field($_POST['preserved_expiration_date'] ?? '');
        $preserve_no_expiry = intval($_POST['preserve_no_expiry'] ?? 0) === 1;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Order ID invalid.']);
        }

        if (!$activation_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activation_date)) {
            wp_send_json_error(['message' => 'Dată activare invalidă.']);
        }

        try {
            global $wpdb;

            $validator = OC_Membership_Validator::get_instance();
            if (!$validator || !$validator->get_db()) {
                wp_send_json_error(['message' => 'Validator DB nu este disponibil.']);
            }

            $db = $validator->get_db();
            $table_name = $db->get_table_name('membership_validations');

            $pending_memberships = $wpdb->get_results($wpdb->prepare(
                "SELECT id, user_id, order_item_id, variation_id, start_date, expiration_date, duration_days, is_unlimited, created_at, product_price
                 FROM {$table_name}
                 WHERE order_id = %d
                 AND validation_status = 'pending'
                 ORDER BY created_at ASC",
                $order_id
            ));

            if (empty($pending_memberships)) {
                wp_send_json_error(['message' => 'Nu există abonamente pending pentru această comandă.']);
            }

            $first_membership = reset($pending_memberships);
            $created_at = $first_membership->created_at ?? '';

            if (!empty($created_at)) {
                try {
                    $purchase_datetime = new DateTimeImmutable($created_at, wp_timezone());
                    $expiry_datetime = $purchase_datetime->modify('+28 days');
                } catch (Exception $exception) {
                    $purchase_timestamp = strtotime($created_at);
                    $expiry_datetime = $purchase_timestamp !== false
                        ? (new DateTimeImmutable('@' . strtotime('+28 days', $purchase_timestamp)))->setTimezone(wp_timezone())
                        : null;
                }

                if ($expiry_datetime instanceof DateTimeImmutable && oc_membership_current_local_datetime() > $expiry_datetime) {
                    $expiry_formatted = wp_date(get_option('date_format'), $expiry_datetime->getTimestamp(), wp_timezone());
                    wp_send_json_error([
                        'message' => "❌ Acest abonament a expirat la data de {$expiry_formatted}.\n\nAbonamentele pot fi activate doar în primele 28 de zile de la achiziție."
                    ]);
                }
            }

            $act_order = wc_get_order($order_id);
            $order_uses_gateway_payment = false;
            $order_has_gateway_copayment = false;
            $order_item_ids_by_variation = [];
            if ($act_order) {
                $order_uses_gateway_payment = oc_membership_is_gateway_payment_method(
                    (string) $act_order->get_payment_method(),
                    (string) $act_order->get_payment_method_title()
                );
                foreach ($pending_memberships as $pending_membership) {
                    if ((float) ($pending_membership->product_price ?? 0) > 0) {
                        $order_has_gateway_copayment = true;
                        break;
                    }
                }
                if (!$order_has_gateway_copayment && oc_membership_resolve_order_package_price($act_order) > 0) {
                    $order_has_gateway_copayment = true;
                }

                // Map order items by variation so activated rows keep a stable link to their order line.
                foreach ($act_order->get_items() as $order_item) {
                    $variation_id = (int) $order_item->get_variation_id();
                    if ($variation_id <= 0) {
                        continue;
                    }
                    if (!isset($order_item_ids_by_variation[$variation_id])) {
                        $order_item_ids_by_variation[$variation_id] = [];
                    }
                    $order_item_ids_by_variation[$variation_id][] = (int) $order_item->get_id();
                }
            }

            $activated_count = 0;
            $user_id = null;

            foreach ($pending_memberships as $membership) {
                if (!$user_id) {
                    $user_id = $membership->user_id;
                }

                $membership_order_item_id = (int) ($membership->order_item_id ?? 0);
                $membership_variation_id = (int) ($membership->variation_id ?? 0);
                if ($membership_order_item_id <= 0 && $membership_variation_id > 0 && !empty($order_item_ids_by_variation[$membership_variation_id])) {
                    $membership_order_item_id = (int) array_shift($order_item_ids_by_variation[$membership_variation_id]);
                }

                $duration_days = intval($membership->duration_days) ?: 28;
                $new_start_date = $activation_date;

                $manual_preserved_expiry = '';
                if ($preserved_expiration_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $preserved_expiration_date)) {
                    $manual_preserved_expiry = $preserved_expiration_date;
                }

                $current_created_at = (string) ($membership->created_at ?? '');
                $time_part = '00:00:00';
                if (preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', $current_created_at, $time_matches)) {
                    $time_part = $time_matches[1];
                }
                $new_created_at = $new_start_date . ' ' . $time_part;

                if ($order_uses_gateway_payment && !$order_has_gateway_copayment) {
                    if ($preserve_no_expiry) {
                        $new_expiration_date = null;
                    } elseif ($manual_preserved_expiry !== '') {
                        $new_expiration_date = $manual_preserved_expiry;
                    } else {
                        $new_expiration_date = null;
                    }
                } elseif ($preserve_no_expiry) {
                    $new_expiration_date = null;
                } elseif ($manual_preserved_expiry !== '') {
                    $new_expiration_date = $manual_preserved_expiry;
                } else {
                    $new_expiration_date = date('Y-m-d', strtotime($activation_date . " +{$duration_days} days"));
                }

                $update_data = [
                    'validation_status' => 'active',
                    'start_date' => $new_start_date,
                    'created_at' => $new_created_at,
                    'expiration_date' => $new_expiration_date,
                    'activated_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                $update_format = ['%s', '%s', '%s', '%s', '%s', '%s'];

                if ($membership_order_item_id > 0) {
                    $update_data['order_item_id'] = $membership_order_item_id;
                    $update_format[] = '%d';
                }

                $updated = $wpdb->update(
                    $table_name,
                    $update_data,
                    ['id' => $membership->id],
                    $update_format,
                    ['%d']
                );

                if ($updated !== false) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name}
                         SET total_sessions = sessions_allocated,
                             remaining_sessions = CASE
                                 WHEN is_unlimited = 1 OR sessions_allocated >= %d THEN %d
                                 ELSE GREATEST(0, sessions_allocated - IFNULL(used_sessions, 0))
                             END,
                             updated_at = %s
                         WHERE id = %d",
                        (int) OC_UNLIMITED_SESSIONS,
                        (int) OC_UNLIMITED_SESSIONS,
                        current_time('mysql'),
                        (int) $membership->id
                    ));

                    $activated_count++;

                    if ($membership->user_id > 0) {
                        do_action('oc_membership_activated', $membership->user_id, [
                            'membership_id' => $membership->id,
                            'order_id' => $order_id,
                            'variation_id' => $membership->variation_id,
                            'start_date' => $new_start_date,
                            'expiration_date' => $new_expiration_date
                        ]);
                    }

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            '✅ [Manual Activation] Membership #%d activated: order=%d, user=%d, date=%s, expires=%s, mode=%s',
                            $membership->id,
                            $order_id,
                            $membership->user_id,
                            $new_start_date,
                            $new_expiration_date ?? 'NULL',
                            $preserve_no_expiry ? 'no-expiry' : ($manual_preserved_expiry !== '' ? 'manual-expiry' : 'auto-expiry')
                        ));
                    }
                }
            }

            if ($user_id && method_exists($db, 'invalidate_membership_cache')) {
                $db->invalidate_membership_cache($user_id);
            }

            $activated_order = wc_get_order($order_id);
            if ($activated_count > 0 && $activated_order) {
                oc_membership_sync_plugin_order_state(
                    $order_id,
                    'paid',
                    [
                        'completed_note' => 'Comandă finalizată automat după activarea manuală a abonamentului.',
                    ]
                );
            }

            wp_send_json_success([
                'message' => sprintf(
                    '✅ %d curs(uri) din pachet activat(e) cu succes! (%s)',
                    $activated_count,
                    $preserve_no_expiry ? 'fără dată de expirare' : ($preserved_expiration_date !== '' ? 'expirare setată manual' : 'expirare recalculată automat')
                ),
                'activated_count' => $activated_count,
                'order_id' => $order_id
            ]);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Manual Activation] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare la activare: ' . $e->getMessage()]);
        }
    }
}

