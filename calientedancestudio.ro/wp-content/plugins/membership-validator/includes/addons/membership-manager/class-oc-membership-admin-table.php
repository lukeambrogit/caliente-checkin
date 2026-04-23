<?php
/**
 * Admin Table Handler - REFACTORED din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR tabelul admin centralizat editable
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - Păstrează toate funcționalitățile existente
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('OC_Membership_Smart_Validation_Service')) {
    require_once plugin_dir_path(__FILE__) . 'class-oc-membership-smart-validation-service.php';
}

/**
 * Class OC_Membership_Admin_Table
 * 
 * Gestionează tabelul admin centralizat cu toate funcționalitățile:
 * - Search nativ WordPress Users
 * - 16 coloane editabile inline 
 * - Integrare cu wp_users și wp_usermeta
 * - AJAX save operations
 * - Bulk operations și export
 */
class OC_Membership_Admin_Table {
    
    use OC_Membership_Pricing;
    use OC_Membership_Courses;
    use OC_Membership_WooCommerce;
    
    /**
     * @var OC_Membership_DB Database handler din ADD-ON #1
     */
    private OC_Membership_DB $validator_db;
    
    /**
     * @var OC_Membership_Data_Handler Data handler pentru procesare date
     */
    private OC_Membership_Data_Handler $data_handler;
    
    /**
     * @var OC_Membership_Dropdown_Builder Dropdown builder pentru opțiuni
     */
    private OC_Membership_Dropdown_Builder $dropdown_builder;
    
    /**
     * @var OC_Membership_Cards_Renderer Cards renderer pentru admin cards
     */
    private OC_Membership_Cards_Renderer $cards_renderer;

    /**
     * @var OC_Membership_Smart_Validation_Service Shared smart validation logic
     */
    private OC_Membership_Smart_Validation_Service $smart_validation_service;

    /**
     * @var bool Tracks whether the current table request already synced all membership statuses.
     */
    private bool $table_request_synced_all_statuses = false;

    /**
     * @var array<int,bool> Tracks user-scoped syncs already executed in the current table request.
     */
    private array $table_request_synced_user_ids = [];
    
    /**
     * Constructor cu dependency injection
     */
    // ============================================
    // SECTION 1: CONSTRUCTOR & PROPERTIES
    // ============================================

    /**
     * Constructor cu dependency injection
     */
    public function __construct(OC_Membership_DB $validator_db, OC_Membership_Data_Handler $data_handler) {
        $this->validator_db = $validator_db;
        $this->data_handler = $data_handler;
        $this->dropdown_builder = new OC_Membership_Dropdown_Builder($validator_db);
        $this->cards_renderer = new OC_Membership_Cards_Renderer($validator_db, $data_handler);
        $this->smart_validation_service = new OC_Membership_Smart_Validation_Service($validator_db);
    }

    
    // ============================================
    // SECTION 2: PUBLIC AJAX HANDLERS
    // ============================================

    /**
     * AJAX Handler pentru VALIDARE SMART bazată pe orar
     * 
     * Găsește cursul care rulează ACUM și consumă o ședință
     */
    public function ajax_validate_membership_smart(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'User ID invalid.']);
        }
        
        try {
            $result = $this->smart_validation_service->validate_and_consume($user_id);
            if (empty($result['success'])) {
                $error_payload = ['message' => (string) ($result['message'] ?? 'Validare eșuată.')];
                if (isset($result['current_time'])) {
                    $error_payload['current_time'] = $result['current_time'];
                }
                if (isset($result['current_day'])) {
                    $error_payload['current_day'] = $result['current_day'];
                }
                wp_send_json_error($error_payload);
            }

            wp_send_json_success([
                'message' => $result['message'],
                'validated_count' => (int) ($result['validated_count'] ?? 0),
                'validated_courses' => $result['validated_courses'] ?? [],
                'skipped_courses' => $result['skipped_courses'] ?? [],
                'validation_time' => $result['validation_time'] ?? current_time('d/m/Y H:i')
            ]);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Smart Validation] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX Handler pentru obținere QR codes utilizator
     * Returnează toate QR codes active pentru un utilizator
     */
    public function ajax_get_user_qr_codes(): void {
        // 🔒 Verifică nonce pentru prevenirea CSRF
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        // 🔒 SECURITATE: Verifică că utilizatorul este autentificat
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error(['message' => 'Nu sunteți autentificat.']);
            return;
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(['message' => 'User ID invalid.']);
            return;
        }
        
        // 🔒 VERIFICARE PERMISIUNI: Admins pot vedea QR-ul oricui, utilizatorii normali doar al lor
        $is_admin = current_user_can('manage_woocommerce') || current_user_can('administrator');
        
        if (!$is_admin && $current_user_id !== $user_id) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni să vizualizați QR-ul altui utilizator.']);
            return;
        }
        
        // ✅ SECURITATE: nonce + autentificare WordPress + verificare că user-ul accesează doar propriile date

        // Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'Utilizator negăsit.']);
        }
        
        // 🎯 v2.0: Get validator instance pentru generare QR SIMPLU
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            wp_send_json_error(['message' => 'Validator system not found.']);
        }
        
        $qr_system = $validator->get_qr_system();
        if (!$qr_system) {
            wp_send_json_error(['message' => 'QR system not initialized.']);
        }
        
        //  v2.0: Obține QR SIMPLU din usermeta
        $qr_filename = get_user_meta($user_id, 'simple_qr_filename', true);
        
        // Dacă nu există QR, generează automat ACUM
        if (empty($qr_filename)) {
            $qr_result = $qr_system->generate_simple_user_qr($user_id);
            
            if (!$qr_result) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[QR AJAX ERROR] User #%d - Failed to generate QR', $user_id));
                }
                wp_send_json_error(['message' => 'Eroare la generarea codului QR.']);
            }
            
            $qr_filename = $qr_result['filename'];
        }
        
        // Construiește URL QR
        $upload_dir = wp_upload_dir();
        $qr_url = $upload_dir['baseurl'] . '/membership-qr-codes/' . $qr_filename;
        
        // Verifică dacă fișierul există fizic
        $qr_path = $upload_dir['basedir'] . '/membership-qr-codes/' . $qr_filename;
        if (!file_exists($qr_path)) {
            // Regenerează dacă fișierul a fost șters
            $qr_result = $qr_system->generate_simple_user_qr($user_id);
            
            if ($qr_result) {
                $qr_url = $qr_result['url'];
            } else {
                wp_send_json_error(['message' => 'Eroare la regenerarea codului QR.']);
            }
        }
        
        //  Returnează QR SIMPLU (unul singur per user, permanent)
        wp_send_json_success([
            'qr_codes' => [
                [
                    'user_id' => $user_id,
                    'product_name' => 'QR Membru Universal (ID: ' . $user_id . ')',
                    'qr_url' => $qr_url,
                    'expires_at' => 'Permanent (nu expiră)',
                    'type' => 'simple',
                    'filename' => $qr_filename
                ]
            ],
            'user_name' => oc_membership_resolve_user_display_name($user),
            'user_id' => $user_id
        ]);
    }
    
    /**
     * AJAX Handler pentru salvare date membru editate
     * 
     * Actualizează:
     * - WP User (display_name, email)
     * - User Meta (phone, billing info)
     * - Membership Validation (product, sessions, dates, status)
     * - WooCommerce Order (payment method, status)
     */
    public function ajax_save_member_data(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }
        
        $user_id_raw = $_POST['user_id'] ?? 0;
        $membership_id = intval($_POST['membership_id'] ?? 0);
        $order_id = intval($_POST['order_id'] ?? 0);
        $data = $_POST['data'] ?? [];
        $package_date_updates_raw = $_POST['package_date_updates'] ?? '[]';
        $package_meta_updates_raw = $_POST['package_meta_updates'] ?? '[]';
        
        // Handle guest users (user_id format: "guest_4")
        $is_guest = strpos($user_id_raw, 'guest_') === 0;
        if ($is_guest) {
            $user_id = 0; // Guest users have user_id = 0
        } else {
            $user_id = intval($user_id_raw);
        }
        
        if (!$is_guest && $user_id <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[Admin Edit] Error: Invalid user_id: {$user_id_raw}");
            }
            wp_send_json_error(['message' => 'ID-ul utilizatorului nu este valid.']);
        }
        
        try {
            global $wpdb;
            $updates_made = [];
            $active_edit_unlock_available = (!$is_guest && $user_id > 0)
                ? $this->has_active_membership_edit_unlock($user_id)
                : false;
            $blocked_active_sensitive_changes = false;
            
            // 1. Actualizează WP User SAU creează cont nou pentru guest users
            if (isset($data['display_name']) || isset($data['email'])) {
                if ($is_guest) {
                    // GUEST USER: Creează cont WordPress nou
                    if (!empty($data['email'])) {
                        $email = sanitize_email($data['email']);
                        $display_name = !empty($data['display_name']) ? sanitize_text_field($data['display_name']) : '';
                        
                        // Verifică dacă email-ul există deja
                        if (email_exists($email)) {
                            throw new Exception("❌ Email '{$email}' este deja folosit de alt utilizator.");
                        }
                        
                        // Creează cont WordPress nou
                        $new_user_id = $this->create_wp_account_for_guest($email, $display_name, $data['phone'] ?? '');
                        if ($new_user_id) {
                            // Actualizează membership cu noul user_id
                            $table_name = $this->validator_db->get_table_name('membership_validations');
                            $wpdb->update(
                                $table_name,
                                ['user_id' => $new_user_id],
                                ['id' => $membership_id],
                                ['%d'],
                                ['%d']
                            );
                            
                            // Trimite email cu link resetare parolă
                            $this->send_account_creation_email($new_user_id);
                            
                            $updates_made[] = 'Cont WordPress creat și email trimis';
                            $user_id = $new_user_id; // Update pentru restul procesului
                            $is_guest = false; // Nu mai este guest
                        }
                    }
                } else {
                    // UTILIZATOR EXISTENT: Actualizează datele
                    $user_data = ['ID' => $user_id];
                    
                    if (!empty($data['display_name'])) {
                        $user_data['display_name'] = sanitize_text_field($data['display_name']);
                        $updates_made[] = 'Nume actualizat';
                    }
                    
                    if (!empty($data['email'])) {
                        $email = sanitize_email($data['email']);
                        
                        // Verificare directă în DB pentru email duplicat
                        $existing_user_id = $wpdb->get_var($wpdb->prepare("
                            SELECT ID FROM {$wpdb->users} 
                            WHERE user_email = %s AND ID != %d
                            LIMIT 1
                        ", $email, $user_id));
                        
                        if ($existing_user_id) {
                            $existing_user = get_userdata($existing_user_id);
                            $existing_name = $existing_user ? oc_membership_resolve_user_display_name($existing_user) : 'Utilizator necunoscut';
                            throw new Exception("❌ Email '{$email}' este deja folosit de: {$existing_name} (ID: {$existing_user_id})");
                        }
                        
                        $user_data['user_email'] = $email;
                        $updates_made[] = 'Email actualizat';
                    }
                    
                    if (count($user_data) > 1) {
                        $result = wp_update_user($user_data);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                    }
                }
            }
            
            // 2. Actualizează User Meta cu validări (doar pentru utilizatori reali, nu guest)
            if (!$is_guest && isset($data['phone'])) {
                $phone = sanitize_text_field($data['phone']);
                
                // Validare telefon duplicat DOAR dacă telefonul nu este gol
                if (!empty($phone)) {
                    $existing_phone = $wpdb->get_var($wpdb->prepare("
                        SELECT user_id FROM {$wpdb->usermeta} 
                        WHERE meta_key = 'billing_phone' AND meta_value = %s AND user_id != %d
                        LIMIT 1
                    ", $phone, $user_id));
                    
                    if ($existing_phone) {
                        $existing_user = get_userdata($existing_phone);
                        $existing_name = $existing_user ? oc_membership_resolve_user_display_name($existing_user) : 'Utilizator necunoscut';
                        throw new Exception("❌ Telefon '{$phone}' este deja folosit de: {$existing_name} (ID: {$existing_phone})");
                    }
                }
                
                update_user_meta($user_id, 'billing_phone', $phone);
                $updates_made[] = 'Telefon actualizat';
            }
            
            if (isset($data['member_discount'])) {
                update_user_meta($user_id, 'member_discount', sanitize_text_field($data['member_discount']));
                $updates_made[] = 'Reducere actualizată';
            }
            
            $package_date_updates = [];
            if (is_string($package_date_updates_raw) && $package_date_updates_raw !== '') {
                $decoded_package_updates = json_decode(wp_unslash($package_date_updates_raw), true);
                if (is_array($decoded_package_updates)) {
                    $package_date_updates = $decoded_package_updates;
                }
            } elseif (is_array($package_date_updates_raw)) {
                $package_date_updates = $package_date_updates_raw;
            }

            $explicit_package_expirations_by_order = [];
            $explicit_package_expirations_by_membership = [];
            foreach ($package_date_updates as $package_update) {
                if (!is_array($package_update) || !array_key_exists('expiration_date', $package_update)) {
                    continue;
                }

                $package_no_expiry = isset($package_update['no_expiry']) && intval($package_update['no_expiry']) === 1;
                $raw_package_expiration = trim((string) ($package_update['expiration_date'] ?? ''));
                $normalized_package_expiration = null;
                if (!$package_no_expiry) {
                    $normalized_package_expiration = $this->normalize_date_input_for_storage(
                        $raw_package_expiration,
                        false
                    );
                }

                if (!$package_no_expiry && $normalized_package_expiration === null) {
                    continue;
                }

                $package_order_id = intval($package_update['order_id'] ?? 0);
                if ($package_order_id > 0) {
                    $explicit_package_expirations_by_order[$package_order_id] = $normalized_package_expiration;
                }

                $package_membership_id = intval($package_update['membership_id'] ?? 0);
                if ($package_membership_id > 0) {
                    $explicit_package_expirations_by_membership[$package_membership_id] = $normalized_package_expiration;
                }
            }

            $package_meta_updates = [];
            if (is_string($package_meta_updates_raw) && $package_meta_updates_raw !== '') {
                $decoded_package_meta_updates = json_decode(wp_unslash($package_meta_updates_raw), true);
                if (is_array($decoded_package_meta_updates)) {
                    $package_meta_updates = $decoded_package_meta_updates;
                }
            } elseif (is_array($package_meta_updates_raw)) {
                $package_meta_updates = $package_meta_updates_raw;
            }

            // 3. Actualizează datele per pachet (created_at / expiration_date / no_expiry)
            if (!empty($package_date_updates)) {
                $table_name = $this->validator_db->get_table_name('membership_validations');

                foreach ($package_date_updates as $package_update) {
                    if (!is_array($package_update)) {
                        continue;
                    }

                    $package_order_id = intval($package_update['order_id'] ?? 0);
                    $package_membership_id = intval($package_update['membership_id'] ?? 0);

                    $scope_current_membership = null;
                    if ($package_membership_id > 0) {
                        $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
                            $package_membership_id
                        ), ARRAY_A);
                    }

                    if (!$scope_current_membership && $package_order_id > 0) {
                        if ($is_guest) {
                            $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$table_name} WHERE order_id = %d AND user_id = 0 ORDER BY id ASC LIMIT 1",
                                $package_order_id
                            ), ARRAY_A);
                        } else {
                            $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$table_name} WHERE order_id = %d AND user_id = %d ORDER BY id ASC LIMIT 1",
                                $package_order_id,
                                $user_id
                            ), ARRAY_A);
                        }
                    }

                    if (!$scope_current_membership) {
                        continue;
                    }

                    $scope_status = (string) ($scope_current_membership['validation_status'] ?? '');
                    if ($scope_status === 'active' && !$active_edit_unlock_available) {
                        $blocked_active_sensitive_changes = true;
                        continue;
                    }

                    $package_updates = [];

                    if (isset($package_update['created_at'])) {
                        $raw_purchase_date = trim((string) $package_update['created_at']);
                        if ($raw_purchase_date !== '') {
                            $normalized_purchase_date = $this->normalize_date_input_for_storage($raw_purchase_date, false);
                            if ($normalized_purchase_date === null) {
                                throw new Exception('Format dată achiziție invalid. Folosește dd/mm/yyyy.');
                            }

                            $current_created_at = (string) ($scope_current_membership['created_at'] ?? '');
                            $time_part = '00:00:00';
                            if (preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', $current_created_at, $time_matches)) {
                                $time_part = $time_matches[1];
                            }
                            $package_updates['created_at'] = $normalized_purchase_date . ' ' . $time_part;
                        }
                    }

                    $package_no_expiry_checked = isset($package_update['no_expiry']) && intval($package_update['no_expiry']) === 1;
                    if ($package_no_expiry_checked) {
                        $package_updates['expiration_date'] = null;
                    } elseif (array_key_exists('expiration_date', $package_update)) {
                        $raw_expiration = trim((string) ($package_update['expiration_date'] ?? ''));
                        if ($raw_expiration === '') {
                            $auto_expiration = $this->calculate_expiration_from_purchase_date($scope_current_membership, $package_updates);
                            if ($auto_expiration === null) {
                                throw new Exception('Data expirare nu poate fi goală. Setează o dată validă sau bifează "fără dată de expirare".');
                            }
                            $package_updates['expiration_date'] = $auto_expiration;
                        } else {
                            $normalized_expiration = $this->normalize_date_input_for_storage($raw_expiration, false);
                            if ($normalized_expiration === null) {
                                $auto_expiration = $this->calculate_expiration_from_purchase_date($scope_current_membership, $package_updates);
                                if ($auto_expiration === null) {
                                    throw new Exception('Format dată expirare invalid. Folosește dd/mm/yyyy.');
                                }
                                $package_updates['expiration_date'] = $auto_expiration;
                            } else {
                                $package_updates['expiration_date'] = $normalized_expiration;
                            }
                        }
                    }

                    if (!$package_no_expiry_checked
                        && !array_key_exists('expiration_date', $package_updates)
                        && array_key_exists('created_at', $package_updates)) {
                        $auto_expiration = $this->calculate_expiration_from_purchase_date($scope_current_membership, $package_updates);
                        if ($auto_expiration !== null) {
                            $package_updates['expiration_date'] = $auto_expiration;
                        }
                    }

                    if (empty($package_updates)) {
                        continue;
                    }

                    $package_updates['updated_at'] = current_time('mysql');

                    if ($package_order_id > 0) {
                        if ($is_guest) {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                [
                                    'order_id' => $package_order_id,
                                    'user_id' => 0,
                                ],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d', '%d']
                            );
                        } else {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                [
                                    'order_id' => $package_order_id,
                                    'user_id' => $user_id,
                                ],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d', '%d']
                            );
                        }

                        if ($updated === false) {
                            throw new Exception('Eroare la actualizarea datelor de pachet.');
                        }
                    } else {
                        $target_id = intval($scope_current_membership['id'] ?? 0);
                        if ($target_id > 0) {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                ['id' => $target_id],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d']
                            );
                            if ($updated === false) {
                                throw new Exception('Eroare la actualizarea datelor de abonament.');
                            }
                        }
                    }

                    $updates_made[] = 'Date pachet actualizate (Comandă #' . ($package_order_id > 0 ? $package_order_id : intval($scope_current_membership['id'] ?? 0)) . ')';
                }
            }

            // 4. Actualizează date financiare per pachet (product_price / payment_method)
            if (!empty($package_meta_updates)) {
                $table_name = $this->validator_db->get_table_name('membership_validations');
                $available_payment_methods = $this->get_available_payment_methods();
                $has_observations_column = (bool) $wpdb->get_var($wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    'observations'
                ));

                foreach ($package_meta_updates as $package_meta_update) {
                    if (!is_array($package_meta_update)) {
                        continue;
                    }

                    $package_order_id = intval($package_meta_update['order_id'] ?? 0);
                    $package_membership_id = intval($package_meta_update['membership_id'] ?? 0);

                    $scope_current_membership = null;
                    if ($package_membership_id > 0) {
                        $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
                            $package_membership_id
                        ), ARRAY_A);
                    }

                    if (!$scope_current_membership && $package_order_id > 0) {
                        if ($is_guest) {
                            $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$table_name} WHERE order_id = %d AND user_id = 0 ORDER BY id ASC LIMIT 1",
                                $package_order_id
                            ), ARRAY_A);
                        } else {
                            $scope_current_membership = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$table_name} WHERE order_id = %d AND user_id = %d ORDER BY id ASC LIMIT 1",
                                $package_order_id,
                                $user_id
                            ), ARRAY_A);
                        }
                    }

                    if (!$scope_current_membership) {
                        continue;
                    }

                    $scope_status = (string) ($scope_current_membership['validation_status'] ?? '');
                    if ($scope_status === 'active' && !$active_edit_unlock_available) {
                        $blocked_active_sensitive_changes = true;
                        continue;
                    }

                    $package_updates = [];

                    if (array_key_exists('product_price', $package_meta_update) && $package_meta_update['product_price'] !== null && $package_meta_update['product_price'] !== '') {
                        $package_updates['product_price'] = max(0, round((float) $package_meta_update['product_price'], 2));
                    }

                    if ($has_observations_column && array_key_exists('observations', $package_meta_update)) {
                        $package_updates['observations'] = sanitize_textarea_field((string) $package_meta_update['observations']);
                    }

                    if (isset($package_meta_update['payment_method'])) {
                        $payment_method_key = $this->normalize_payment_method_key((string) $package_meta_update['payment_method']);
                        if (!isset($available_payment_methods[$payment_method_key])) {
                            $payment_method_key = 'unknown';
                        }
                        $package_updates['payment_method'] = $payment_method_key;
                    }

                    if (isset($package_meta_update['payment_status'])) {
                        $payment_status_value = sanitize_text_field((string) $package_meta_update['payment_status']);
                        if ($payment_status_value !== '') {
                            $allowed_payment_statuses = ['paid', 'unpaid', 'partial'];
                            if (!in_array($payment_status_value, $allowed_payment_statuses, true)) {
                                $payment_status_value = 'unpaid';
                            }
                            $package_updates['payment_status'] = $payment_status_value;
                        }
                    }

                    if (empty($package_updates)) {
                        continue;
                    }

                    $package_updates['updated_at'] = current_time('mysql');

                    if ($package_order_id > 0) {
                        if ($is_guest) {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                [
                                    'order_id' => $package_order_id,
                                    'user_id' => 0,
                                ],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d', '%d']
                            );
                        } else {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                [
                                    'order_id' => $package_order_id,
                                    'user_id' => $user_id,
                                ],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d', '%d']
                            );
                        }

                        if ($updated === false) {
                            throw new Exception('Eroare la actualizarea datelor financiare ale pachetului.');
                        }

                        $order = wc_get_order($package_order_id);
                        if ($order) {
                            if (array_key_exists('product_price', $package_updates)) {
                                $this->sync_order_package_price($order, (float) $package_updates['product_price']);
                            }

                            if (array_key_exists('payment_method', $package_updates)) {
                                $order->set_payment_method($package_updates['payment_method']);
                                if (isset($available_payment_methods[$package_updates['payment_method']])) {
                                    $order->set_payment_method_title($available_payment_methods[$package_updates['payment_method']]);
                                }
                            }

                            if (array_key_exists('payment_status', $package_updates)) {
                                $payment_status = sanitize_key((string) $package_updates['payment_status']);
                                $new_status = $this->convert_payment_status_to_order_status($payment_status);
                                if ($new_status !== null) {
                                    $order->update_status($new_status);
                                }
                                if (in_array($payment_status, ['paid', 'unpaid', 'partial'], true)) {
                                    $order->update_meta_data('_oc_requested_payment_status', $payment_status);
                                }
                            }

                            if (array_key_exists('observations', $package_updates)) {
                                $order->update_meta_data('_oc_observations', (string) $package_updates['observations']);
                            }

                            $order->save();
                        }
                    } else {
                        $target_id = intval($scope_current_membership['id'] ?? 0);
                        if ($target_id > 0) {
                            $updated = $wpdb->update(
                                $table_name,
                                $package_updates,
                                ['id' => $target_id],
                                array_fill(0, count($package_updates), '%s'),
                                ['%d']
                            );
                            if ($updated === false) {
                                throw new Exception('Eroare la actualizarea datelor financiare ale abonamentului.');
                            }
                        }
                    }

                    $updates_made[] = 'Date financiare pachet actualizate (Comandă #' . ($package_order_id > 0 ? $package_order_id : intval($scope_current_membership['id'] ?? 0)) . ')';
                }
            }

            // 5. Actualizează Membership Data
            if ($membership_id > 0) {
                $membership_updates = [];
                $table_name = $this->validator_db->get_table_name('membership_validations');
                $current_membership = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
                    $membership_id
                ), ARRAY_A);
                $membership_is_active = ((string) ($current_membership['validation_status'] ?? '')) === 'active';
                $allow_sensitive_active_edit = !$membership_is_active || $active_edit_unlock_available;
                $explicit_package_expiration = null;
                $has_explicit_package_expiration = false;

                if ($membership_id > 0 && array_key_exists($membership_id, $explicit_package_expirations_by_membership)) {
                    $explicit_package_expiration = $explicit_package_expirations_by_membership[$membership_id];
                    $has_explicit_package_expiration = true;
                } elseif ($order_id > 0 && array_key_exists($order_id, $explicit_package_expirations_by_order)) {
                    $explicit_package_expiration = $explicit_package_expirations_by_order[$order_id];
                    $has_explicit_package_expiration = true;
                }

                // Backward compatibility: older UI field name for editable price.
                if (isset($data['product_price_display']) && !isset($data['product_price'])) {
                    $data['product_price'] = $data['product_price_display'];
                }
                
                if (isset($data['product_id']) && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Admin Edit] product_id change ignored; subscription type is locked in edit mode.');
                }
                
                if ($allow_sensitive_active_edit && isset($data['variation_id'])) {
                    $membership_updates['variation_id'] = intval($data['variation_id']);
                    $updates_made[] = 'Curs actualizat';
                }
                
                if ($allow_sensitive_active_edit && isset($data['product_price'])) {
                    $membership_updates['product_price'] = floatval($data['product_price']);
                    $updates_made[] = 'Preț actualizat';
                }

                if ($allow_sensitive_active_edit && isset($data['created_at'])) {
                    $raw_purchase_date = trim((string) $data['created_at']);
                    if ($raw_purchase_date !== '') {
                        $normalized_purchase_date = $this->normalize_date_input_for_storage($raw_purchase_date, false);
                        if ($normalized_purchase_date === null) {
                            throw new Exception('Format dată achiziție invalid. Folosește dd/mm/yyyy.');
                        }

                        $current_created_at = (string) ($current_membership['created_at'] ?? '');
                        $time_part = '00:00:00';
                        if (preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', $current_created_at, $time_matches)) {
                            $time_part = $time_matches[1];
                        }
                        $membership_updates['created_at'] = $normalized_purchase_date . ' ' . $time_part;
                        $updates_made[] = 'Data achiziționării actualizată';
                    }
                }

                if ($allow_sensitive_active_edit && isset($data['payment_method'])) {
                    $payment_method_key = $this->normalize_payment_method_key((string) $data['payment_method']);
                    if ($payment_method_key === 'unknown') {
                        $payment_method_key = 'cash';
                    }
                    $membership_updates['payment_method'] = $payment_method_key;
                    $updates_made[] = 'Modalitate de plată actualizată';
                }

                if ($allow_sensitive_active_edit && isset($data['payment_status'])) {
                    $payment_status_value = sanitize_text_field((string) $data['payment_status']);
                    $allowed_payment_statuses = ['paid', 'unpaid', 'partial'];
                    if (!in_array($payment_status_value, $allowed_payment_statuses, true)) {
                        $payment_status_value = 'unpaid';
                    }
                    $membership_updates['payment_status'] = $payment_status_value;
                    $updates_made[] = 'Status plată actualizat';
                }
                
                if ($allow_sensitive_active_edit && isset($data['sessions_allocated'])) {
                    $membership_updates['sessions_allocated'] = intval($data['sessions_allocated']);
                    $membership_updates['total_sessions'] = intval($data['sessions_allocated']);
                    $updates_made[] = 'Ședințe alocate actualizate';
                }
                
                if ($allow_sensitive_active_edit && isset($data['remaining_sessions'])) {
                    $membership_updates['remaining_sessions'] = intval($data['remaining_sessions']);
                    $updates_made[] = 'Ședințe rămase actualizate';
                }
                
                if ($allow_sensitive_active_edit && isset($data['used_sessions'])) {
                    $membership_updates['used_sessions'] = intval($data['used_sessions']);
                    $updates_made[] = 'Ședințe folosite actualizate';
                }

                $has_sessions_allocated_input = $allow_sensitive_active_edit && array_key_exists('sessions_allocated', $data);
                $has_remaining_sessions_input = $allow_sensitive_active_edit && array_key_exists('remaining_sessions', $data);
                $has_used_sessions_input = $allow_sensitive_active_edit && array_key_exists('used_sessions', $data);
                $has_any_sessions_input = $has_sessions_allocated_input || $has_remaining_sessions_input || $has_used_sessions_input;
                
                $no_expiry_checked = $allow_sensitive_active_edit && isset($data['no_expiry']) && intval($data['no_expiry']) === 1;
                if ($no_expiry_checked) {
                    $membership_updates['expiration_date'] = null;
                    $updates_made[] = 'Data expirare eliminată (fără expirare)';
                } elseif ($allow_sensitive_active_edit && isset($data['expiration_date'])) {
                    $raw_expiration = trim((string) $data['expiration_date']);

                    if ($raw_expiration === '') {
                        $auto_expiration = $this->calculate_expiration_from_purchase_date($current_membership, $membership_updates);
                        if ($auto_expiration === null) {
                            throw new Exception('Data expirare nu poate fi goală. Setează o dată validă sau bifează "fără dată de expirare".');
                        }
                        $membership_updates['expiration_date'] = $auto_expiration;
                        $updates_made[] = 'Data expirare recalculată automat din data achiziționării';
                    } else {
                        $normalized_expiration = $this->normalize_date_input_for_storage($raw_expiration, false);

                        if ($normalized_expiration === null) {
                            $auto_expiration = $this->calculate_expiration_from_purchase_date($current_membership, $membership_updates);
                            if ($auto_expiration === null) {
                                throw new Exception('Format dată expirare invalid. Folosește dd/mm/yyyy.');
                            }
                            $membership_updates['expiration_date'] = $auto_expiration;
                            $updates_made[] = 'Data expirare invalidă a fost corectată automat din data achiziționării';
                        } else {
                            $membership_updates['expiration_date'] = $normalized_expiration;
                            $updates_made[] = 'Data expirare actualizată';
                        }
                    }
                }

                if (!$no_expiry_checked && !array_key_exists('expiration_date', $membership_updates) && array_key_exists('created_at', $membership_updates)) {
                    $auto_expiration = $this->calculate_expiration_from_purchase_date($current_membership, $membership_updates);
                    if ($auto_expiration !== null) {
                        $membership_updates['expiration_date'] = $auto_expiration;
                        $updates_made[] = 'Data expirare recalculată automat după modificarea datei achiziționării';
                    }
                }

                if ($membership_is_active && !$allow_sensitive_active_edit) {
                    $blocked_active_sensitive_changes = true;
                }
                
                // ❌ BLOCAT: validation_status poate fi schimbat DOAR prin butonul dedicat de activare
                // Eliminat: if (isset($data['validation_status'])) { ... }
                // Status-ul se modifică DOAR prin: ajax-activate-membership.php
                
                // 7CARD/ESX cu coplată => intrări finite (nu nelimitat).
                $effective_payment_method = (string) ($membership_updates['payment_method']
                    ?? $data['payment_method']
                    ?? ($current_membership['payment_method'] ?? ''));
                $effective_payment_key = $this->normalize_payment_method_key($effective_payment_method);
                $effective_price = array_key_exists('product_price', $membership_updates)
                    ? (float) $membership_updates['product_price']
                    : (float) ($current_membership['product_price'] ?? 0);
                $is_gateway_copayment = $this->is_gateway_payment_method($effective_payment_key) && $effective_price > 0;
                $is_gateway_no_copayment = $this->is_gateway_payment_method($effective_payment_key) && !$is_gateway_copayment;

                if ($is_gateway_copayment) {
                    $effective_variation_id = array_key_exists('variation_id', $membership_updates)
                        ? (int) $membership_updates['variation_id']
                        : (int) ($current_membership['variation_id'] ?? 0);
                    $used_sessions_effective = array_key_exists('used_sessions', $membership_updates)
                        ? max(0, (int) $membership_updates['used_sessions'])
                        : max(0, (int) ($current_membership['used_sessions'] ?? 0));

                    $target_sessions = 0;
                    if ($effective_variation_id > 0) {
                        $config = $this->validator_db->get_course_hours_config($effective_variation_id);
                        if (!empty($config['sessions_per_month'])) {
                            $target_sessions = max(1, (int) $config['sessions_per_month']);
                        }
                    }

                    if ($target_sessions <= 0) {
                        $existing_allocated = array_key_exists('sessions_allocated', $membership_updates)
                            ? (int) $membership_updates['sessions_allocated']
                            : (int) ($current_membership['sessions_allocated'] ?? 0);
                        if ($existing_allocated > 0 && $existing_allocated < OC_UNLIMITED_SESSIONS) {
                            $target_sessions = $existing_allocated;
                        } else {
                            $target_sessions = 8;
                        }
                    }

                    $membership_updates['is_unlimited'] = 0;
                    $membership_updates['sessions_allocated'] = $target_sessions;
                    $membership_updates['total_sessions'] = $target_sessions;
                    $membership_updates['remaining_sessions'] = max(0, $target_sessions - $used_sessions_effective);

                    if (!$no_expiry_checked && !array_key_exists('expiration_date', $membership_updates)) {
                        if ($has_explicit_package_expiration) {
                            $membership_updates['expiration_date'] = $explicit_package_expiration;
                        } else {
                            $start_date = (string) ($current_membership['start_date'] ?? oc_membership_current_business_date());
                            if ($start_date === '' || $start_date === '0000-00-00') {
                                $start_date = oc_membership_current_business_date();
                            }
                            $duration_days = (int) ($current_membership['duration_days'] ?? 0);
                            if ($duration_days <= 0) {
                                $duration_days = $this->get_default_membership_duration_days();
                            }
                            $membership_updates['expiration_date'] = $this->add_days_to_iso_date_wp($start_date, $duration_days);
                        }
                    }

                    $updates_made[] = 'Coplata 7CARD/ESX detectată: intrări finite aplicate';
                } elseif ($is_gateway_no_copayment) {
                    $used_sessions_effective = array_key_exists('used_sessions', $membership_updates)
                        ? max(0, (int) $membership_updates['used_sessions'])
                        : max(0, (int) ($current_membership['used_sessions'] ?? 0));
                    $unlimited_sessions = OC_UNLIMITED_SESSIONS;

                    $membership_updates['is_unlimited'] = 1;
                    $membership_updates['sessions_allocated'] = $unlimited_sessions;
                    $membership_updates['total_sessions'] = $unlimited_sessions;
                    $membership_updates['remaining_sessions'] = max(0, $unlimited_sessions - $used_sessions_effective);
                    $membership_updates['expiration_date'] = null;

                    $updates_made[] = '7CARD/ESX fără coplată: nelimitat și fără expirare aplicat';
                }

                // VIP Pool: ședințele trebuie să rămână nelimitate (fără fallback la valori finite).
                $is_vip_pool_package = false;
                if ($order_id > 0) {
                    $membership_order = wc_get_order($order_id);
                    if ($membership_order) {
                        foreach ($membership_order->get_items() as $membership_order_item) {
                            if ((int) $membership_order_item->get_variation_id() === 0) {
                                $membership_pool_product_id = (int) $membership_order_item->get_product_id();
                                if ($membership_pool_product_id > 0 && get_post_meta($membership_pool_product_id, '_oc_pool_is_unlimited', true) === 'yes') {
                                    $is_vip_pool_package = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($is_vip_pool_package) {
                    $used_sessions_effective = array_key_exists('used_sessions', $membership_updates)
                        ? max(0, (int) $membership_updates['used_sessions'])
                        : max(0, (int) ($current_membership['used_sessions'] ?? 0));
                    $unlimited_sessions = OC_UNLIMITED_SESSIONS;

                    $membership_updates['is_unlimited'] = 1;
                    $membership_updates['sessions_allocated'] = $unlimited_sessions;
                    $membership_updates['total_sessions'] = $unlimited_sessions;
                    $membership_updates['remaining_sessions'] = max(0, $unlimited_sessions - $used_sessions_effective);

                    $updates_made[] = 'Pachet VIP detectat: ședințe nelimitate aplicate';
                }

                if (!empty($membership_updates)) {
                    $membership_updates['updated_at'] = current_time('mysql');
                    
                    $updated = $wpdb->update(
                        $table_name,
                        $membership_updates,
                        ['id' => $membership_id],
                        array_fill(0, count($membership_updates), '%s'),
                        ['%d']
                    );
                    
                    if ($updated === false) {
                        throw new Exception('Eroare la actualizarea membership-ului.');
                    }

                    // Sincronizează expiration_date pe tot pachetul (order_id), nu doar pe un singur membership
                    if ($order_id > 0 && array_key_exists('expiration_date', $membership_updates)) {
                        $package_expiration = $membership_updates['expiration_date'];
                        $package_updated_at = current_time('mysql');

                        if ($is_guest) {
                            if ($package_expiration === null) {
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$table_name} SET expiration_date = NULL, updated_at = %s WHERE order_id = %d AND user_id = 0",
                                    $package_updated_at,
                                    $order_id
                                ));
                            } else {
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$table_name} SET expiration_date = %s, updated_at = %s WHERE order_id = %d AND user_id = 0",
                                    $package_expiration,
                                    $package_updated_at,
                                    $order_id
                                ));
                            }
                        } else {
                            if ($package_expiration === null) {
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$table_name} SET expiration_date = NULL, updated_at = %s WHERE order_id = %d AND user_id = %d",
                                    $package_updated_at,
                                    $order_id,
                                    $user_id
                                ));
                            } else {
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$table_name} SET expiration_date = %s, updated_at = %s WHERE order_id = %d AND user_id = %d",
                                    $package_expiration,
                                    $package_updated_at,
                                    $order_id,
                                    $user_id
                                ));
                            }
                        }

                        $updates_made[] = 'Data expirare sincronizată pe toate cursurile din pachet';
                    }

                    // Sincronizează data achiziționării pe tot pachetul (order_id)
                    if ($order_id > 0 && array_key_exists('created_at', $membership_updates)) {
                        $package_created_at = $membership_updates['created_at'];
                        $package_updated_at = current_time('mysql');

                        if ($is_guest) {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$table_name} SET created_at = %s, updated_at = %s WHERE order_id = %d AND user_id = 0",
                                $package_created_at,
                                $package_updated_at,
                                $order_id
                            ));
                        } else {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$table_name} SET created_at = %s, updated_at = %s WHERE order_id = %d AND user_id = %d",
                                $package_created_at,
                                $package_updated_at,
                                $order_id,
                                $user_id
                            ));
                        }

                        $updates_made[] = 'Data achiziționării sincronizată pe toate cursurile din pachet';
                    }

                    // Sincronizează politica de ședințe pe tot pachetul când metoda devine gateway.
                    if ($order_id > 0 && ($is_gateway_copayment || $is_gateway_no_copayment)) {
                        $package_updated_at = current_time('mysql');

                        if ($is_gateway_no_copayment) {
                            $where_sql = $is_guest
                                ? $wpdb->prepare('order_id = %d AND user_id = 0', $order_id)
                                : $wpdb->prepare('order_id = %d AND user_id = %d', $order_id, $user_id);

                            $wpdb->query(
                                "UPDATE {$table_name}
                                 SET is_unlimited = 1,
                                     sessions_allocated = " . (int) OC_UNLIMITED_SESSIONS . ",
                                     total_sessions = " . (int) OC_UNLIMITED_SESSIONS . ",
                                     remaining_sessions = GREATEST(0, " . (int) OC_UNLIMITED_SESSIONS . " - IFNULL(used_sessions,0)),
                                     expiration_date = NULL,
                                     updated_at = '" . esc_sql($package_updated_at) . "'
                                 WHERE {$where_sql}"
                            );

                            $updates_made[] = 'Regula gateway fără coplată sincronizată pe toate cursurile din pachet';
                        } elseif ($is_gateway_copayment) {
                            $hours_table = $this->validator_db->get_table_name('course_hours_config');
                            $copay_expiration = array_key_exists('expiration_date', $membership_updates)
                                ? $membership_updates['expiration_date']
                                : null;

                            $scope_where = $is_guest
                                ? $wpdb->prepare('m.order_id = %d AND m.user_id = 0', $order_id)
                                : $wpdb->prepare('m.order_id = %d AND m.user_id = %d', $order_id, $user_id);

                            $target_expr = "CASE
                                    WHEN h.sessions_per_month IS NOT NULL AND h.sessions_per_month > 0 THEN h.sessions_per_month
                                    WHEN m.sessions_allocated > 0 AND m.sessions_allocated < " . (int) OC_UNLIMITED_SESSIONS . " THEN m.sessions_allocated
                                    ELSE 8
                                END";

                            if ($copay_expiration === null) {
                                $wpdb->query(
                                    "UPDATE {$table_name} m
                                     LEFT JOIN {$hours_table} h ON h.course_variation_id = m.variation_id
                                     SET m.is_unlimited = 0,
                                         m.sessions_allocated = {$target_expr},
                                         m.total_sessions = {$target_expr},
                                         m.remaining_sessions = GREATEST(0, ({$target_expr}) - IFNULL(m.used_sessions,0)),
                                         m.updated_at = '" . esc_sql($package_updated_at) . "'
                                     WHERE {$scope_where}"
                                );
                            } else {
                                $wpdb->query($wpdb->prepare(
                                    "UPDATE {$table_name} m
                                     LEFT JOIN {$hours_table} h ON h.course_variation_id = m.variation_id
                                     SET m.is_unlimited = 0,
                                         m.sessions_allocated = {$target_expr},
                                         m.total_sessions = {$target_expr},
                                         m.remaining_sessions = GREATEST(0, ({$target_expr}) - IFNULL(m.used_sessions,0)),
                                         m.expiration_date = %s,
                                         m.updated_at = %s
                                     WHERE {$scope_where}",
                                    $copay_expiration,
                                    $package_updated_at
                                ));
                            }

                            $updates_made[] = 'Regula gateway cu coplată sincronizată pe toate cursurile din pachet';
                        }
                    }

                    // Pentru editarea de ședințe din UI, sincronizează TOATE cursurile active/pending din pachetul selectat.
                    if ($order_id > 0 && $has_any_sessions_input && !$is_gateway_copayment && !$is_gateway_no_copayment) {
                        $package_updated_at = current_time('mysql');
                        $scope_where = $is_guest
                            ? $wpdb->prepare("order_id = %d AND user_id = 0 AND validation_status IN ('active','pending')", $order_id)
                            : $wpdb->prepare("order_id = %d AND user_id = %d AND validation_status IN ('active','pending')", $order_id, $user_id);

                        if ($is_vip_pool_package) {
                            $wpdb->query(
                                "UPDATE {$table_name}
                                 SET is_unlimited = 1,
                                     sessions_allocated = " . (int) OC_UNLIMITED_SESSIONS . ",
                                     total_sessions = " . (int) OC_UNLIMITED_SESSIONS . ",
                                     remaining_sessions = GREATEST(0, " . (int) OC_UNLIMITED_SESSIONS . " - IFNULL(used_sessions,0)),
                                     updated_at = '" . esc_sql($package_updated_at) . "'
                                 WHERE {$scope_where}"
                            );

                            $updates_made[] = 'Ședințe VIP nelimitate sincronizate pe întregul pachet activ/pending';
                        } else {
                            $set_clauses = [];
                            if (array_key_exists('sessions_allocated', $membership_updates)) {
                                $target_sessions = max(0, (int) $membership_updates['sessions_allocated']);
                                $set_clauses[] = 'is_unlimited = 0';
                                $set_clauses[] = 'sessions_allocated = ' . $target_sessions;
                                $set_clauses[] = 'total_sessions = ' . $target_sessions;

                                if (!array_key_exists('remaining_sessions', $membership_updates)) {
                                    $set_clauses[] = 'remaining_sessions = GREATEST(0, ' . $target_sessions . ' - IFNULL(used_sessions,0))';
                                }
                            }

                            if (array_key_exists('used_sessions', $membership_updates)) {
                                $set_clauses[] = 'used_sessions = ' . max(0, (int) $membership_updates['used_sessions']);
                            }

                            if (array_key_exists('remaining_sessions', $membership_updates)) {
                                $set_clauses[] = 'remaining_sessions = ' . max(0, (int) $membership_updates['remaining_sessions']);
                            }

                            if (!empty($set_clauses)) {
                                $set_clauses[] = "updated_at = '" . esc_sql($package_updated_at) . "'";
                                $wpdb->query(
                                    "UPDATE {$table_name}
                                     SET " . implode(', ', $set_clauses) . "
                                     WHERE {$scope_where}"
                                );

                                $updates_made[] = 'Ședințe sincronizate pe toate cursurile active/pending din pachet';
                            }
                        }
                    }

                    // Sincronizează prețul pe tot pachetul (order_id) pentru rapoarte coerente.
                    if ($order_id > 0 && array_key_exists('product_price', $membership_updates)) {
                        $package_updated_at = current_time('mysql');
                        $package_price = (float) $membership_updates['product_price'];

                        if ($is_guest) {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$table_name} SET product_price = %f, updated_at = %s WHERE order_id = %d AND user_id = 0",
                                $package_price,
                                $package_updated_at,
                                $order_id
                            ));
                        } else {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$table_name} SET product_price = %f, updated_at = %s WHERE order_id = %d AND user_id = %d",
                                $package_price,
                                $package_updated_at,
                                $order_id,
                                $user_id
                            ));
                        }

                        $updates_made[] = 'Preț abonament sincronizat pe toate cursurile din pachet';
                    }

                    // Sincronizează payment_method/payment_status pe tot pachetul (order_id)
                    if ($order_id > 0 && (array_key_exists('payment_method', $membership_updates) || array_key_exists('payment_status', $membership_updates))) {
                        $package_updated_at = current_time('mysql');
                        $package_payment_updates = ['updated_at' => $package_updated_at];

                        if (array_key_exists('payment_method', $membership_updates)) {
                            $package_payment_updates['payment_method'] = $membership_updates['payment_method'];
                        }
                        if (array_key_exists('payment_status', $membership_updates)) {
                            $package_payment_updates['payment_status'] = $membership_updates['payment_status'];
                        }

                        if ($is_guest) {
                            $wpdb->update(
                                $table_name,
                                $package_payment_updates,
                                ['order_id' => $order_id, 'user_id' => 0]
                            );
                        } else {
                            $wpdb->update(
                                $table_name,
                                $package_payment_updates,
                                ['order_id' => $order_id, 'user_id' => $user_id]
                            );
                        }

                        $updates_made[] = 'Datele de plată sincronizate pe toate cursurile din pachet';
                    }
                }
            }
            
            // 3b. Editare individuală ședințe per variație (course_sessions)
            $course_sessions_raw = isset($_POST['course_sessions']) ? wp_unslash($_POST['course_sessions']) : '';
            if (!empty($course_sessions_raw) && is_string($course_sessions_raw)) {
                $course_sessions_arr = json_decode($course_sessions_raw, true);
                if (is_array($course_sessions_arr)) {
                    foreach ($course_sessions_arr as $cs) {
                        $cs_id = intval($cs['id'] ?? 0);
                        if ($cs_id <= 0) {
                            continue;
                        }
                        $cs_row = $wpdb->get_row($wpdb->prepare(
                            "SELECT id, user_id, validation_status FROM {$table_name} WHERE id = %d LIMIT 1",
                            $cs_id
                        ), ARRAY_A);
                        if (!$cs_row) {
                            continue;
                        }
                        // Verificare ownership
                        if (!$is_guest && (int) $cs_row['user_id'] !== (int) $user_id) {
                            continue;
                        }
                        if (!in_array($cs_row['validation_status'], ['active', 'pending'], true)) {
                            continue;
                        }
                        if ((string) $cs_row['validation_status'] === 'active' && !$active_edit_unlock_available) {
                            $blocked_active_sensitive_changes = true;
                            continue;
                        }
                        $cs_updates = ['updated_at' => current_time('mysql')];
                        if (array_key_exists('sessions_allocated', $cs)) {
                            $cs_alloc = max(0, intval($cs['sessions_allocated']));
                            $cs_updates['sessions_allocated'] = $cs_alloc;
                            $cs_updates['total_sessions']     = $cs_alloc;
                            $cs_updates['is_unlimited']       = ($cs_alloc >= (int) OC_UNLIMITED_SESSIONS) ? 1 : 0;
                        }
                        if (array_key_exists('used_sessions', $cs)) {
                            $cs_updates['used_sessions'] = max(0, intval($cs['used_sessions']));
                        }
                        if (array_key_exists('remaining_sessions', $cs)) {
                            $cs_updates['remaining_sessions'] = max(0, intval($cs['remaining_sessions']));
                        }
                        if (count($cs_updates) > 1) {
                            $wpdb->update(
                                $table_name,
                                $cs_updates,
                                ['id' => $cs_id],
                                array_fill(0, count($cs_updates), '%s'),
                                ['%d']
                            );
                            $updates_made[] = 'Ședințe actualizate (curs ID ' . $cs_id . ')';
                        }
                    }
                }
            }

            // 4. Actualizează WooCommerce Order
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order) {
                    if (isset($data['payment_method'])) {
                        $order->set_payment_method(sanitize_text_field($data['payment_method']));
                    }
                    
                    if (isset($data['payment_status'])) {
                        $payment_status = sanitize_key((string) $data['payment_status']);
                        $new_status = $this->convert_payment_status_to_order_status($payment_status);
                        if ($new_status !== null) {
                            $order->update_status($new_status);
                        }
                        if (in_array($payment_status, ['paid', 'unpaid', 'partial'], true)) {
                            $order->update_meta_data('_oc_requested_payment_status', $payment_status);
                        }
                    }
                    $order->save();
                }
            }
            
            // 5. Log ajustare manuală pentru audit trail
            if ($membership_id > 0 && !empty($updates_made)) {
                $this->log_validation_event($membership_id, $user_id, 'admin_adjustment', [
                    'changes' => $updates_made,
                    'modified_fields' => array_keys($data),
                    'order_id' => $order_id,
                ]);
            }

            if ($blocked_active_sensitive_changes) {
                $updates_made[] = 'Unele modificări pe abonament activ au fost blocate: este necesar PIN-ul pentru "Editează abonament activ".';
            }

            // ✅ RĂSPUNS SUCCESS INSTANT
            wp_send_json_success([
                'message' => 'Modificările au fost salvate cu succes!',
                'updates' => $updates_made
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Admin Edit] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }
    /**
     * AJAX: Creează comandă WooCommerce pentru guest user (SEPARAT de salvare date)
     * SIMPLU: Folosește create_woocommerce_order_helper() - ACEEAȘI funcție ca la creare client nou
     */
    public function ajax_create_woo_order_for_guest(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }
        
        $membership_id = intval($_POST['membership_id'] ?? 0);
        
        if ($membership_id <= 0) {
            wp_send_json_error(['message' => 'Membership ID invalid.']);
        }
        
        try {
            global $wpdb;
            
            // Obține datele membership-ului
            $table_name = $this->validator_db->get_table_name('membership_validations');
            $membership = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$table_name} WHERE id = %d
            ", $membership_id));
            
            if (!$membership) {
                throw new Exception('Membership-ul nu există.');
            }
            
            // FOLOSEȘTE ACEEAȘI FUNCȚIE ca la creare client nou!
            $new_order_id = $this->create_woocommerce_order_helper([
                'user_id' => 0, // Guest user
                'package_id' => $membership->product_id,
                'course_selections' => [$membership->variation_id],
                'payment_status' => 'paid',
                'payment_method' => 'cash', // ← IMPLICIT CASH pentru comenzi din interfață
                'activation_date' => oc_membership_current_business_date(),
                'expiration_date' => $membership->expiration_date
            ]);
            
            // Actualizează membership cu noul order_id
            $wpdb->update(
                $table_name,
                ['order_id' => $new_order_id],
                ['id' => $membership_id],
                ['%d'],
                ['%d']
            );

            oc_membership_sync_plugin_order_state(
                $new_order_id,
                'paid',
                [
                    'pending_note' => 'Comandă creată din Membership Manager și păstrată pending până la activarea abonamentului.',
                    'completed_note' => 'Comandă sincronizată automat după activarea abonamentului.',
                ]
            );
            
            wp_send_json_success([
                'message' => 'Comandă WooCommerce creată cu succes!',
                'order_id' => $new_order_id,
                'order_url' => admin_url('post.php?post=' . $new_order_id . '&action=edit')
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Guest Order] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }
    /**
     * AJAX Handler pentru REÎNNOIRE abonament utilizator existent
     * IDENTIC cu ajax_create_new_client() DAR fără crearea user-ului (user-ul există deja!)
     */
    public function ajax_renew_subscription(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Permisiuni insuficiente']);
        }
        
        $data = $_POST['data'] ?? [];
        // EXACT CA LA ajax_create_new_client() - aceleași validări
        $required = ['user_id', 'package_id', 'course_selections', 'payment_status', 'activation_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[AJAX Renew] Missing required field: {$field}");
                }
                wp_send_json_error(['message' => "Câmp obligatoriu: {$field}"]);
            }
        }
        
        try {
            global $wpdb;
            $table_name = $this->validator_db->get_table_name('membership_validations');

                $raw_user_id = isset($data['user_id']) ? sanitize_text_field((string) $data['user_id']) : '';
                $resolved_user_id = 0;

            if ($raw_user_id !== '' && is_numeric($raw_user_id)) {
                $resolved_user_id = intval($raw_user_id);
            } elseif (preg_match('/^guest_(\d+)$/', $raw_user_id, $matches)) {
                $membership_id = intval($matches[1]);
                if ($membership_id > 0) {
                    $resolved_user_id = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT user_id FROM {$table_name} WHERE id = %d LIMIT 1",
                        $membership_id
                    )));
                }
            }

            if ($resolved_user_id <= 0 && !empty($data['real_user_id'])) {
                $resolved_user_id = intval($data['real_user_id']);
            }

            if ($resolved_user_id <= 0) {
                wp_send_json_error(['message' => 'Utilizator invalid pentru reînnoire. Activează mai întâi abonamentul pending sau selectează un utilizator valid.']);
            }

                $requested_payment_key = $this->normalize_payment_method_key((string) ($data['payment_method'] ?? ''));
                $is_gateway_payment = $this->is_gateway_payment_method($requested_payment_key);
                $replace_rows = [];
                $replace_label = 'abonamente active/pending existente';

                $candidate_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, order_id, validation_status, payment_method FROM {$table_name} WHERE user_id = %d AND validation_status IN ('pending','active')",
                    $resolved_user_id
                ), ARRAY_A);

                [$replace_rows, $replace_label] = $this->resolve_renew_replace_targets($candidate_rows, $is_gateway_payment);

                $has_existing = !empty($replace_rows);
                $confirmed_replace = !empty($data['confirm_gateway_replace_existing']) && intval($data['confirm_gateway_replace_existing']) === 1;

                if ($has_existing && !$confirmed_replace) {
                    $pending_count = 0;
                    $active_count = 0;
                    foreach ($replace_rows as $replace_row) {
                        if (($replace_row['validation_status'] ?? '') === 'pending') {
                            $pending_count++;
                        } elseif (($replace_row['validation_status'] ?? '') === 'active') {
                            $active_count++;
                        }
                    }

                    wp_send_json_error([
                        'message' => 'Există deja abonamente care trebuie înlocuite pentru acest client. Confirmă înainte de creare.',
                        'requires_confirmation' => true,
                        'replace_label' => $replace_label,
                        'pending_count' => $pending_count,
                        'active_count' => $active_count,
                        'existing_count' => count($replace_rows),
                    ]);
                }
            
            $order_id = $this->create_woocommerce_order_helper([
                'user_id' => $resolved_user_id,
                'package_id' => intval($data['package_id']),
                'course_selections' => array_map('intval', $data['course_selections']),
                'payment_status' => sanitize_text_field($data['payment_status']),
                'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cash'),
                'activation_date' => sanitize_text_field($data['activation_date']),
                'expiration_date' => sanitize_text_field($data['expiration_date'] ?? ''),
                'product_price' => isset($data['product_price']) ? floatval($data['product_price']) : null
            ]);
            
            if (!empty($replace_rows)) {
                try {
                    $replace_note = $is_gateway_payment
                        ? 'Abonament activ/pending înlocuit de abonament nou 7CARD/ESX din Membership Manager.'
                        : 'Abonament 7CARD/ESX activ/pending înlocuit de abonament nou din Membership Manager.';
                    $this->replace_existing_gateway_memberships($resolved_user_id, $replace_rows, $replace_note);
                } catch (Exception $replace_error) {
                    $created_order = $order_id > 0 ? wc_get_order($order_id) : null;
                    if ($created_order && !in_array($created_order->get_status(), ['cancelled', 'failed'], true)) {
                        $created_order->update_status('cancelled', 'Comandă anulată: înlocuirea abonamentelor anterioare a eșuat.');
                    }
                    throw $replace_error;
                }
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Comanda nouă nu a putut fi încărcată după creare.');
            }

            oc_membership_sync_plugin_order_state(
                $order_id,
                (string) ($data['payment_status'] ?? ''),
                [
                    'process_membership' => true,
                    'pending_note' => 'Comandă reînnoire creată din Membership Manager și păstrată pending până la activarea abonamentului.',
                    'completed_note' => 'Comandă reînnoire sincronizată automat după activarea abonamentului.',
                ]
            );
            
            // ✅ RĂSPUNS SUCCESS INSTANT
            wp_send_json_success([
                'message' => 'Abonament adăugat cu succes! Procesare în curs...',
                'order_id' => $order_id,
                'user_id' => $resolved_user_id
            ]);
            
            // Hook-ul woocommerce_order_status_completed va crea membership-urile automat
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }
    /**
     * AJAX Handler pentru ADĂUGAREA unui curs suplimentar la un membru existent
     * SIMPLU: Folosește create_woocommerce_order_helper() EXISTENT - ACEEAȘI funcție ca la creare client nou
     */
    public function ajax_add_supplementary_course(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $variation_id = intval($_POST['variation_id'] ?? 0);
        $sessions = intval($_POST['sessions'] ?? 0);
        
        if ($user_id <= 0) {
            wp_send_json_error(['message' => 'ID utilizator invalid.']);
        }
        
        if ($variation_id <= 0) {
            wp_send_json_error(['message' => 'Curs invalid.']);
        }
        
        if ($sessions <= 0) {
            wp_send_json_error(['message' => 'Număr ședințe invalid.']);
        }
        
        try {
            global $wpdb;
            
            // Verifică dacă utilizatorul există
            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception('Utilizatorul nu există.');
            }
            
            // Obține Pool ID pentru variation
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                throw new Exception('Cursul selectat nu există.');
            }
            
            $pool_id = $variation->get_parent_id();
            if (!$pool_id) {
                throw new Exception('Nu s-a găsit Pool-ul pentru acest curs.');
            }
            
            // Găsește un pachet Pool care conține acest Pool
            $package_id = $this->find_package_for_variation($pool_id);
            if (!$package_id) {
                throw new Exception('Nu există pachete configurate pentru acest curs.');
            }
            
            // FOLOSEȘTE ACEEAȘI FUNCȚIE ca la creare client nou!
            $order_id = $this->create_woocommerce_order_helper([
                'user_id' => $user_id,
                'package_id' => $package_id,
                'course_selections' => [$variation_id],
                'payment_status' => 'paid',
                'payment_method' => 'cash', // ← IMPLICIT CASH pentru comenzi din interfață
                'activation_date' => oc_membership_current_business_date(),
                'expiration_date' => $this->add_days_to_iso_date_wp(
                    oc_membership_current_business_date(),
                    $this->get_default_membership_duration_days()
                )
            ]);
            
            oc_membership_sync_plugin_order_state(
                $order_id,
                'paid',
                [
                    'process_membership' => true,
                    'pending_note' => 'Comandă pentru curs suplimentar creată din Membership Manager și păstrată pending până la activarea abonamentului.',
                    'completed_note' => 'Comandă pentru curs suplimentar sincronizată automat după activarea abonamentului.',
                ]
            );
            
            // ✅ RĂSPUNS SUCCESS INSTANT
            wp_send_json_success([
                'message' => 'Curs adăugat cu succes! Procesare în curs...',
                'order_id' => $order_id,
                'course_name' => $variation->get_name(),
                'sessions' => $sessions
            ]);
            
            // Hook-ul woocommerce_order_status_completed va crea membership-ul automat
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Add Supplementary Course] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX Handler pentru obținerea TUTUROR cursurilor Pool cu ședințe mapate
     * Folosit pentru "Adaugă Curs" - afișează dropdown cu toate cursurile + sessions
     */
    public function ajax_get_all_pool_courses(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        try {
            if (!function_exists('oc_pool_get_all_pool_ids')) {
                wp_send_json_error(['message' => 'Pool addon nu este activ']);
            }
            
            $pool_ids = oc_pool_get_all_pool_ids();
            if (empty($pool_ids)) {
                wp_send_json_error(['message' => 'Nu există Pool-uri configurate']);
            }
            
            // Obține TOATE cursurile din TOATE Pool-urile cu ședințe din Pool config
            $courses = [];
            
            foreach ($pool_ids as $pool_id) {
                $pool_product = wc_get_product($pool_id);
                if ($pool_product && $pool_product->is_type('variable')) {
                    $variations = $pool_product->get_available_variations();
                    foreach ($variations as $var) {
                        $var_obj = wc_get_product($var['variation_id']);
                        if ($var_obj && $var['is_purchasable']) {
                            $variation_id = $var['variation_id'];
                            
                            // Obține ședințele DIRECT din Pool config (sursa primară)
                            $sessions = 0;
                            $pool_config = get_post_meta($pool_id, '_oc_pool_config', true);
                            
                            if (is_array($pool_config) && isset($pool_config[$variation_id]['sessions'])) {
                                $sessions = intval($pool_config[$variation_id]['sessions']);
                            }
                            
                            // Default la 8 ședințe dacă nu există în config
                            if ($sessions <= 0) {
                                $sessions = 8;
                            }
                            
                            $courses[] = [
                                'variation_id' => $variation_id,
                                'course_name' => $var_obj->get_name(),
                                'pool_name' => $pool_product->get_name(),
                                'sessions' => intval($sessions)
                            ];
                        }
                    }
                }
            }
            
            if (empty($courses)) {
                wp_send_json_error(['message' => 'Nu există cursuri disponibile în Pool-uri']);
            }
            
            // Sortează după nume curs
            usort($courses, function($a, $b) {
                return strcmp($a['course_name'], $b['course_name']);
            });
            
            wp_send_json_success(['courses' => $courses, 'count' => count($courses)]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Get All Pool Courses] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_get_package_courses(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        $package_id = intval($_POST['package_id'] ?? 0);
        if (!$package_id) {
            wp_send_json_error(['message' => 'Pachet invalid']);
        }
        
        try {
            // 1. Verifică dacă este DUAL MODE
            $is_dual_mode = get_post_meta($package_id, '_oc_pool_dual_mode', true) === 'yes';
            
            if ($is_dual_mode) {
                // DUAL MODE: 2 carduri separate, unul per pool
                $pool1_id      = get_post_meta($package_id, '_oc_pool_pool1_id', true);
                $pool2_id      = get_post_meta($package_id, '_oc_pool_pool2_id', true);
                $pool1_allowed = get_post_meta($package_id, '_oc_pool_pool1_variations', true);
                $pool2_allowed = get_post_meta($package_id, '_oc_pool_pool2_variations', true);
                $pool1_label   = get_post_meta($package_id, '_oc_pool_pool1_label', true) ?: 'Grup 1';
                $pool2_label   = get_post_meta($package_id, '_oc_pool_pool2_label', true) ?: 'Grup 2';
                $pool1_min     = max(1, (int)(get_post_meta($package_id, '_oc_pool_pool1_min', true) ?: 1));
                $pool2_min     = max(1, (int)(get_post_meta($package_id, '_oc_pool_pool2_min', true) ?: 1));
                $pool1_style   = get_post_meta($package_id, '_oc_pool_pool1_ui_style', true) ?: 'checkboxes';
                $pool2_style   = get_post_meta($package_id, '_oc_pool_pool2_ui_style', true) ?: 'checkboxes';
                // Slots = exact pool_min selections (one per slot); checkboxes = unlimited (0)
                $pool1_max     = ($pool1_style === 'slots') ? $pool1_min : 0;
                $pool2_max     = ($pool2_style === 'slots') ? $pool2_min : 0;

                if (!$pool1_id) {
                    wp_send_json_error(['message' => 'Pachet Dual Mode: Pool 1 nu este configurat']);
                }

                $pool1_allowed = is_array($pool1_allowed) ? $pool1_allowed : [];
                $pool2_allowed = is_array($pool2_allowed) ? $pool2_allowed : [];

                // Colectează cursuri Pool 1
                $pool1_courses = [];
                $pool1_product = wc_get_product($pool1_id);
                if ($pool1_product && $pool1_product->is_type('variable') && !empty($pool1_allowed)) {
                    foreach ($pool1_product->get_available_variations() as $var) {
                        if (!in_array($var['variation_id'], $pool1_allowed, true)) continue;
                        $var_obj = wc_get_product($var['variation_id']);
                        if ($var_obj && $var['is_purchasable'] && $var['variation_is_active']) {
                            $pool1_courses[] = ['variation_id' => $var['variation_id'], 'name' => $var_obj->get_name()];
                        }
                    }
                    usort($pool1_courses, fn($a, $b) => strcmp($a['name'], $b['name']));
                }

                // Colectează cursuri Pool 2 (poate fi same produs sau altul)
                $pool2_courses = [];
                if ($pool2_id && !empty($pool2_allowed)) {
                    $pool2_product = ($pool2_id === $pool1_id) ? $pool1_product : wc_get_product($pool2_id);
                    if ($pool2_product && $pool2_product->is_type('variable')) {
                        foreach ($pool2_product->get_available_variations() as $var) {
                            if (!in_array($var['variation_id'], $pool2_allowed, true)) continue;
                            $var_obj = wc_get_product($var['variation_id']);
                            if ($var_obj && $var['is_purchasable'] && $var['variation_is_active']) {
                                $pool2_courses[] = ['variation_id' => $var['variation_id'], 'name' => $var_obj->get_name()];
                            }
                        }
                        usort($pool2_courses, fn($a, $b) => strcmp($a['name'], $b['name']));
                    }
                }

                if (empty($pool1_courses) && empty($pool2_courses)) {
                    wp_send_json_error(['message' => 'Nu există cursuri disponibile pentru acest pachet']);
                }

                // Generează HTML cu 2 carduri separate
                $dual_groups = [
                    ['courses' => $pool1_courses, 'label' => $pool1_label, 'min' => $pool1_min, 'max' => $pool1_max, 'group' => 'pool1', 'style' => $pool1_style],
                    ['courses' => $pool2_courses, 'label' => $pool2_label, 'min' => $pool2_min, 'max' => $pool2_max, 'group' => 'pool2', 'style' => $pool2_style],
                ];

                ob_start();
                foreach ($dual_groups as $grp) {
                    if (empty($grp['courses'])) continue;
                    $is_radio  = ($grp['style'] === 'slots');
                    $inp_type  = $is_radio ? 'radio' : 'checkbox';
                    $inp_name  = $is_radio ? ('course_group_' . $grp['group']) : 'course_selections[]';
                    $inp_class = $is_radio ? 'oc-pool-radio-input' : '';
                    if ($is_radio) {
                        $min_hint = 'alege exact ' . $grp['min'];
                    } elseif ($grp['max'] > 0) {
                        $min_hint = 'min ' . $grp['min'] . ', max ' . $grp['max'];
                    } else {
                        $min_hint = 'min ' . $grp['min'];
                    }
                    ?>
                    <div class="oc-course-pool-group"
                         data-group="<?php echo esc_attr($grp['group']); ?>"
                         data-label="<?php echo esc_attr($grp['label']); ?>"
                         data-ui-style="<?php echo esc_attr($grp['style']); ?>"
                         data-min-selections="<?php echo (int)$grp['min']; ?>"
                         data-max-selections="<?php echo (int)$grp['max']; ?>">
                        <div class="oc-pool-group-header">
                            <strong class="oc-pool-group-title"><?php echo esc_html($grp['label']); ?></strong>
                            <span class="oc-pool-group-hint">(<?php echo esc_html($min_hint); ?>)</span>
                        </div>
                        <div class="oc-courses-grid oc-pool-group-courses">
                            <?php foreach ($grp['courses'] as $course): ?>
                            <label class="oc-course-checkbox<?php echo $is_radio ? ' oc-course-radio-label' : ''; ?>">
                                <input type="<?php echo $inp_type; ?>"
                                       name="<?php echo esc_attr($inp_name); ?>"
                                       value="<?php echo esc_attr($course['variation_id']); ?>"
                                       <?php echo $inp_class ? 'class="' . esc_attr($inp_class) . '"' : ''; ?>>
                                <span class="oc-course-text">
                                    <span class="oc-course-name"><?php echo esc_html($course['name']); ?></span>
                                    <span class="oc-course-id">ID: <?php echo (int) $course['variation_id']; ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                }
                $html = ob_get_clean();

                wp_send_json_success([
                    'html'          => $html,
                    'is_dual_mode'  => true,
                    'courses_count' => count($pool1_courses) + count($pool2_courses),
                ]);
                return;

            } else {
                // SINGLE MODE (codul existent)
                // 1. Verifică dacă pachetul are Pool configurat (cu backward compatibility)
                $pool_id = get_post_meta($package_id, '_oc_pool_pool_id', true);
                
                // Backward compatibility - verifică și formatul vechi
                if (!$pool_id) {
                    $pool_id = get_post_meta($package_id, '_mv_pack_pool_id', true);
                }
                
                if (!$pool_id) {
                    wp_send_json_error(['message' => 'Acest pachet nu are Pool configurat']);
                }
                
                // 2. Obține variațiile PERMISE pentru acest pachet (configurate în admin)
                $selected_variation_ids = get_post_meta($package_id, '_oc_pool_selected_variations', true);
                
                // Backward compatibility - verifică și formatul vechi
                if (!is_array($selected_variation_ids) || empty($selected_variation_ids)) {
                    $selected_variation_ids = get_post_meta($package_id, '_mv_pack_selected_variations', true);
                }
                
                if (!is_array($selected_variation_ids) || empty($selected_variation_ids)) {
                    wp_send_json_error(['message' => 'Acest pachet nu are cursuri configurate. Te rugăm să configurezi cursurile permise în admin WooCommerce.']);
                }
            }
            
            // 3. Obține produsul Pool
            $pool_product = wc_get_product($pool_id);
            if (!$pool_product || !$pool_product->is_type('variable')) {
                wp_send_json_error(['message' => 'Pool-ul asociat nu este valid']);
            }
            
            // 4. Colectează variațiile – SINGLE MODE (dual mode a ieșit deja cu return)
            $courses = [];

            // SINGLE MODE: Colectează din Pool configurat
            $all_variations = $pool_product->get_available_variations();
            if (empty($all_variations)) {
                wp_send_json_error(['message' => 'Nu există cursuri disponibile în acest Pool']);
            }

            foreach ($all_variations as $var) {
                if (!in_array($var['variation_id'], $selected_variation_ids, true)) {
                    continue;
                }
                $var_obj = wc_get_product($var['variation_id']);
                if ($var_obj && $var['is_purchasable'] && $var['variation_is_active']) {
                    $courses[] = [
                        'variation_id' => $var['variation_id'],
                        'name'         => $var_obj->get_name(),
                        'pool_name'    => $pool_product->get_name(),
                    ];
                }
            }
            
            if (empty($courses)) {
                wp_send_json_error(['message' => 'Nu există cursuri disponibile pentru achiziție în acest pachet']);
            }
            
            // 6. Sortează după nume
            usort($courses, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            // 7. Citește setările de afiare din Pool Product Admin
            $ui_style = get_post_meta($package_id, '_oc_pool_ui_style', true);
            if (!$ui_style) {
                $ui_style = get_post_meta($package_id, '_mv_pack_ui_style', true) ?: 'checkboxes';
            }
            $min_sel = max(1, (int)(
                get_post_meta($package_id, '_oc_pool_min_selections', true)
                ?: get_post_meta($package_id, '_mv_pack_min_selections', true)
                ?: 1
            ));
            $max_sel = (int)(
                get_post_meta($package_id, '_oc_pool_max_selections', true)
                ?: get_post_meta($package_id, '_mv_pack_max_selections', true)
                ?: 0
            );
            if ($ui_style === 'slots' && $max_sel === 0) {
                // Slots with no explicit max = exact $min_sel selections (matches pool product behaviour)
                $max_sel = $min_sel;
            }

            $is_radio  = ($ui_style === 'slots');
            $inp_type  = $is_radio ? 'radio' : 'checkbox';
            $inp_name  = $is_radio ? 'course_group_single' : 'course_selections[]';
            $inp_class = $is_radio ? 'oc-pool-radio-input' : '';

            if ($is_radio && $min_sel === $max_sel) {
                $hint = 'alege exact ' . $min_sel;
            } elseif ($max_sel > 0) {
                $hint = 'min ' . $min_sel . ', max ' . $max_sel;
            } else {
                $hint = 'min ' . $min_sel;
            }

            // 8. Generează HTML în container de grup (consistent cu dual mode)
            ob_start();
            ?>
            <div class="oc-course-pool-group"
                 data-group="single"
                 data-label="Cursuri disponibile"
                 data-ui-style="<?php echo esc_attr($ui_style); ?>"
                 data-min-selections="<?php echo (int)$min_sel; ?>"
                 data-max-selections="<?php echo (int)$max_sel; ?>">
                <div class="oc-pool-group-header">
                    <strong class="oc-pool-group-title">Cursuri disponibile</strong>
                    <span class="oc-pool-group-hint">(<?php echo esc_html($hint); ?>)</span>
                </div>
                <div class="oc-courses-grid oc-pool-group-courses">
                    <?php foreach ($courses as $course): ?>
                    <label class="oc-course-checkbox<?php echo $is_radio ? ' oc-course-radio-label' : ''; ?>">
                        <input type="<?php echo $inp_type; ?>"
                               name="<?php echo esc_attr($inp_name); ?>"
                               value="<?php echo esc_attr($course['variation_id']); ?>"
                               <?php echo $inp_class ? 'class="' . esc_attr($inp_class) . '"' : ''; ?>>
                        <span class="oc-course-text">
                            <span class="oc-course-name"><?php echo esc_html($course['name']); ?></span>
                            <span class="oc-course-id">ID: <?php echo (int) $course['variation_id']; ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html, 
                'courses_count' => count($courses),
                'pool_id' => $pool_id,
                'pool_name' => $pool_product->get_name()
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Get Courses] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    /**
     * AJAX Handler pentru crearea unui client nou
     * Orchestrator principal: creează user + comandă WooCommerce
     */
    public function ajax_create_new_client(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Permisiuni insuficiente']);
        }
        
        $data = $_POST['data'] ?? [];
        $required = ['first_name', 'last_name', 'email', 'phone', 'package_id', 'course_selections', 'payment_status', 'activation_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                wp_send_json_error(['message' => "Câmp obligatoriu: {$field}"]);
            }
        }

        $data['first_name'] = sanitize_text_field((string) ($data['first_name'] ?? ''));
        $data['last_name'] = sanitize_text_field((string) ($data['last_name'] ?? ''));
        
        // Creează display_name din first_name + last_name
        $data['display_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        
        try {
            // 0. Validare prealabilă pentru duplicat (cu mesaje clare)
            $this->validate_client_data_before_creation($data);
            
            // 1. Creează WP User cu date de facturare
            $user_id = $this->create_wp_user_helper([
                'display_name' => sanitize_text_field($data['display_name']),
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'email' => sanitize_email($data['email']),
                'phone' => sanitize_text_field($data['phone']),
                'password' => !empty($data['password']) ? $data['password'] : wp_generate_password(12, true),
                'billing_first_name' => sanitize_text_field($data['billing_first_name'] ?? $data['first_name']),
                'billing_last_name' => sanitize_text_field($data['billing_last_name'] ?? $data['last_name']),
                'billing_address_1' => sanitize_text_field($data['billing_address_1'] ?? ''),
                'billing_city' => sanitize_text_field($data['billing_city'] ?? ''),
                'billing_state' => sanitize_text_field($data['billing_state'] ?? ''),
                'billing_postcode' => sanitize_text_field($data['billing_postcode'] ?? ''),
                'billing_country' => sanitize_text_field($data['billing_country'] ?? 'RO')
            ]);
            
            // 2. Creează WooCommerce Order
            $order_id = $this->create_woocommerce_order_helper([
                'user_id' => $user_id,
                'package_id' => intval($data['package_id']),
                'course_selections' => array_map('intval', $data['course_selections']),
                'payment_status' => sanitize_text_field($data['payment_status']),
                'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cash'),
                'activation_date' => sanitize_text_field($data['activation_date']),
                'expiration_date' => sanitize_text_field($data['expiration_date'] ?? ''),
                'product_price' => isset($data['product_price']) ? floatval($data['product_price']) : null,
                'observations' => sanitize_textarea_field((string) ($data['observations'] ?? ''))
            ]);
            
            oc_membership_sync_plugin_order_state(
                $order_id,
                (string) ($data['payment_status'] ?? ''),
                [
                    'process_membership' => true,
                    'pending_note' => 'Comandă creată din Membership Manager și păstrată pending până la activarea abonamentului.',
                    'completed_note' => 'Comandă sincronizată automat după activarea abonamentului.',
                ]
            );
            
            // TRIMITE RĂSPUNS SUCCESS INSTANT (înainte de procese grele)
            wp_send_json_success([
                'message' => 'Client și comandă create cu succes! Procesare în curs...',
                'user_id' => $user_id,
                'order_id' => $order_id
            ]);
            
            // Codul de mai jos NU se va executa deoarece wp_send_json_success() face wp_die()
            // DAR lăsăm comentariile pentru documentație
            
            // PROCESE POST-CREATE (vor fi executate de hook-urile WooCommerce):
            // - Trimite email client (via WooCommerce hooks)
            // - Trimite email admin (via WooCommerce hooks)
            // - Log admin action (via WooCommerce hooks)
            // - Creează membership-uri (via hook woocommerce_order_status_completed)
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Admin Create Client] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX Handler pentru ANULAREA unui abonament
     * 
     * Acțiuni:
     * 1. Setează comanda WooCommerce la status "cancelled"
     * 2. Marchează membership-ul ca "expired" în DB
     * 3. Actualizează cache-ul
     * 
     * @return void
     */
    public function ajax_cancel_membership(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(['message' => 'ID comandă invalid.']);
        }
        
        try {
            global $wpdb;
            $table_name = $this->validator_db->get_table_name('membership_validations');
            
            // 1. Găsește TOATE membership-urile din acest pachet (order_id)
            $memberships = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$table_name} WHERE order_id = %d
            ", $order_id), ARRAY_A);
            
            if (empty($memberships)) {
                throw new Exception('Nu există membership-uri pentru această comandă.');
            }
            
            // 2. Anulează comanda WooCommerce
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() !== 'cancelled') {
                $order->update_status('cancelled', 'Pachet anulat din Membership Manager - toate cursurile marcate ca expirate');
            }
            
            // 3. Marchează TOATE membership-urile din pachet ca EXPIRATE
            $updated_count = 0;
            $user_id = 0;
            
            foreach ($memberships as $membership) {
                $updated = $wpdb->update(
                    $table_name,
                    [
                        'validation_status' => 'expired',
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $membership['id']],
                    ['%s', '%s'],
                    ['%d']
                );
                
                if ($updated !== false) {
                    $updated_count++;

                    $this->log_validation_event(
                        (int) $membership['id'],
                        (int) ($membership['user_id'] ?? 0),
                        'admin_cancel',
                        [
                            'order_id' => $order_id,
                            'reason' => 'Pachet anulat din Membership Manager',
                        ]
                    );
                }
                
                // Memorează user_id pentru invalidare cache
                if (!$user_id && !empty($membership['user_id'])) {
                    $user_id = $membership['user_id'];
                }
            }
            
            if ($updated_count === 0) {
                throw new Exception('Eroare la actualizarea statusurilor membership-urilor.');
            }
            
            // 4. Șterge cache pentru utilizator
            if ($user_id > 0) {
                wp_cache_delete('oc_user_memberships_' . $user_id, 'oc_membership');
                
                // Invalidează și cache-ul din validator_db
                if ($this->validator_db) {
                    $this->validator_db->invalidate_membership_cache($user_id);
                }
            }
            
            wp_send_json_success([
                'message' => sprintf('Pachet anulat cu succes! %d cursuri marcate ca expirate.', $updated_count),
                'order_id' => $order_id,
                'updated_count' => $updated_count
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Cancel Membership] Error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Eroare: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Istoric validări pentru un abonament/pachet (paginat)
     */
    public function ajax_get_membership_validation_history(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');

        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }

        $membership_id = intval($_POST['membership_id'] ?? 0);
        $order_id = intval($_POST['order_id'] ?? 0);
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(1, min(50, intval($_POST['per_page'] ?? 20)));

        if ($membership_id <= 0 && $order_id <= 0) {
            wp_send_json_error(['message' => 'Lipsesc membership_id/order_id pentru istoric.']);
        }

        $membership_ids = $this->resolve_history_membership_ids($membership_id, $order_id);
        if (empty($membership_ids)) {
            wp_send_json_success([
                'events' => [],
                'page' => $page,
                'has_more' => false,
            ]);
        }

        wp_send_json_success($this->build_history_response($membership_ids, $page, $per_page));
    }

    private function resolve_history_membership_ids(int $membership_id, int $order_id): array {
        global $wpdb;

        $membership_table = $this->validator_db->get_table_name('membership_validations');
        $membership_ids = [];

        if ($order_id > 0) {
            $order_memberships = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$membership_table} WHERE order_id = %d",
                $order_id
            ));
            if (is_array($order_memberships)) {
                foreach ($order_memberships as $mid) {
                    $mid = intval($mid);
                    if ($mid > 0) {
                        $membership_ids[] = $mid;
                    }
                }
            }
        }

        if ($membership_id > 0) {
            $membership_ids[] = $membership_id;
        }

        return array_values(array_unique(array_filter(array_map('intval', $membership_ids))));
    }

    private function build_history_response(array $membership_ids, int $page, int $per_page, bool $paginate = true): array {
        global $wpdb;

        $log_table = $wpdb->prefix . 'membership_validation_log';
        $membership_table = $this->validator_db->get_table_name('membership_validations');
        $offset = max(0, ($page - 1) * max(1, $per_page));

        $in_placeholders = implode(',', array_fill(0, count($membership_ids), '%d'));
        $where_sql = "membership_id IN ({$in_placeholders})";
        $query_args = $membership_ids;

        $where_sql .= " AND (validation_metadata IS NULL OR (validation_metadata NOT LIKE %s AND validation_metadata NOT LIKE %s))";
        $query_args[] = '%"event_type":"admin_adjustment"%';
        $query_args[] = '%"event_type":"admin_cancel"%';
        $where_sql .= " AND validation_method IN ('qr_code', 'access_code', 'api', 'manual')";

        $logs_sql = "SELECT id, membership_id, user_id, validator_user_id, validation_method, validation_status, validation_date, validation_metadata, error_message
                     FROM {$log_table}
                     WHERE {$where_sql}
                     ORDER BY validation_date DESC, id DESC";

        if ($paginate) {
            $query_args[] = $per_page + 1;
            $query_args[] = $offset;
            $logs_sql .= " LIMIT %d OFFSET %d";
        }

        $raw_logs = $wpdb->get_results($wpdb->prepare($logs_sql, ...$query_args), ARRAY_A);

        $has_more = $paginate && count($raw_logs) > $per_page;
        $rows = $has_more ? array_slice($raw_logs, 0, $per_page) : $raw_logs;

        $membership_rows_sql = "SELECT id, variation_id, courses_included, product_name FROM {$membership_table} WHERE id IN ({$in_placeholders})";
        $membership_rows = $wpdb->get_results($wpdb->prepare($membership_rows_sql, ...$membership_ids), ARRAY_A);
        $membership_map = [];
        foreach ((array) $membership_rows as $mrow) {
            $membership_map[intval($mrow['id'])] = $mrow;
        }

        $history_rows = [];
        foreach ($rows as $log_row) {
            $metadata = json_decode((string) ($log_row['validation_metadata'] ?? ''), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $history_rows[] = [
                'row' => $log_row,
                'metadata' => $metadata,
                'endpoint' => (string) ($metadata['endpoint'] ?? ''),
                'device_id' => (string) ($metadata['device_id'] ?? ''),
                'timestamp' => strtotime((string) ($log_row['validation_date'] ?? '')) ?: 0,
            ];
        }

        $events = [];
        foreach ($history_rows as $history_row) {
            $log_row = $history_row['row'];
            $mid = intval($log_row['membership_id'] ?? 0);
            $metadata = $history_row['metadata'];

            if ($this->should_skip_duplicate_mobile_validation($history_row, $history_rows)) {
                continue;
            }

            $course_label = 'N/A';
            if (isset($metadata['course_name']) && trim((string) $metadata['course_name']) !== '') {
                $course_label = trim((string) $metadata['course_name']);
            } elseif (isset($membership_map[$mid])) {
                $membership_product_name = trim((string) ($membership_map[$mid]['product_name'] ?? ''));
                if ($membership_product_name !== '') {
                    $course_label = $membership_product_name;
                }

                $variation_id = intval($membership_map[$mid]['variation_id'] ?? 0);
                if ($course_label === 'N/A' && $variation_id > 0) {
                    $variation_product = wc_get_product($variation_id);
                    if ($variation_product) {
                        $course_label = (string) $variation_product->get_name();
                    }
                }

                if ($course_label === 'N/A') {
                    $courses_included = trim((string) ($membership_map[$mid]['courses_included'] ?? ''));
                    if ($courses_included !== '') {
                        $course_label = $courses_included;
                    }
                }
            }

            $event_type = 'scan_valid';
            $event_label = 'Scan valid';
            $method = (string) ($log_row['validation_method'] ?? '');
            $status = (string) ($log_row['validation_status'] ?? '');

            if ($method === 'manual' && $status === 'success') {
                $event_type = 'scan_valid';
                $event_label = 'Validare reușită';
            } elseif ($method === 'manual') {
                $event_type = 'scan_failed';
                $event_label = 'Validare eșuată';
            } elseif (in_array($method, ['api', 'qr_code', 'access_code'], true) && $status === 'success') {
                $event_type = 'scan_valid';
                $event_label = 'Validare reușită';
            } elseif (in_array($method, ['api', 'qr_code', 'access_code'], true)) {
                $event_type = 'scan_failed';
                $event_label = 'Validare eșuată';
            } elseif ($status !== 'success') {
                $event_type = 'scan_failed';
                $event_label = 'Validare eșuată';
            }

            $raw_date = (string) ($log_row['validation_date'] ?? '');
            $formatted_date = $this->format_date_european($raw_date);
            if (!empty($raw_date) && strlen($raw_date) >= 16) {
                $formatted_date .= ' ' . substr($raw_date, 11, 5);
            }

            $events[] = [
                'id' => intval($log_row['id'] ?? 0),
                'membership_id' => $mid,
                'event_type' => $event_type,
                'event_label' => $event_label,
                'course' => $course_label,
                'validated_at' => $raw_date,
                'validated_at_display' => trim($formatted_date),
                'status' => $status,
                'error' => (string) ($log_row['error_message'] ?? ''),
            ];
        }

        return [
            'events' => $events,
            'page' => $page,
            'has_more' => $has_more,
        ];
    }

    private function should_skip_duplicate_mobile_validation(array $candidate_row, array $history_rows): bool {
        $endpoint = (string) ($candidate_row['endpoint'] ?? '');
        if ($endpoint !== 'validate-membership') {
            return false;
        }

        $row = (array) ($candidate_row['row'] ?? []);
        if ((string) ($row['validation_method'] ?? '') !== 'api') {
            return false;
        }

        $membership_id = intval($row['membership_id'] ?? 0);
        $device_id = (string) ($candidate_row['device_id'] ?? '');
        $timestamp = intval($candidate_row['timestamp'] ?? 0);

        if ($membership_id <= 0 || $timestamp <= 0) {
            return false;
        }

        foreach ($history_rows as $comparison_row) {
            $comparison = (array) ($comparison_row['row'] ?? []);
            if ((string) ($comparison['validation_method'] ?? '') !== 'api') {
                continue;
            }

            if ((string) ($comparison_row['endpoint'] ?? '') !== 'check-in') {
                continue;
            }

            if (intval($comparison['membership_id'] ?? 0) !== $membership_id) {
                continue;
            }

            $comparison_device_id = (string) ($comparison_row['device_id'] ?? '');
            if ($device_id !== '' && $comparison_device_id !== '' && $device_id !== $comparison_device_id) {
                continue;
            }

            $comparison_timestamp = intval($comparison_row['timestamp'] ?? 0);
            if ($comparison_timestamp <= 0) {
                continue;
            }

            if (abs($comparison_timestamp - $timestamp) <= 10) {
                return true;
            }
        }

        return false;
    }

    private function get_latest_success_validation_dates(array $membership_ids): array {
        global $wpdb;

        $membership_ids = array_values(array_filter(array_map('intval', $membership_ids)));
        if (empty($membership_ids)) {
            return [];
        }

        $log_table = $wpdb->prefix . 'membership_validation_log';
        $in_placeholders = implode(',', array_fill(0, count($membership_ids), '%d'));
        $sql = "SELECT membership_id, MAX(validation_date) AS latest_validation_date
                FROM {$log_table}
                WHERE membership_id IN ({$in_placeholders})
                  AND validation_status = %s
                  AND validation_method IN ('qr_code', 'access_code', 'api', 'manual')
                  AND (validation_metadata IS NULL OR (validation_metadata NOT LIKE %s AND validation_metadata NOT LIKE %s))
                GROUP BY membership_id";

        $args = $membership_ids;
        $args[] = 'success';
        $args[] = '%"event_type":"admin_adjustment"%';
        $args[] = '%"event_type":"admin_cancel"%';

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $membership_id = intval($row['membership_id'] ?? 0);
            $latest_date = trim((string) ($row['latest_validation_date'] ?? ''));
            if ($membership_id > 0 && $latest_date !== '') {
                $map[$membership_id] = $latest_date;
            }
        }

        return $map;
    }

    private function render_validation_history_items_html(array $events): string {
        if (empty($events)) {
            return '<div class="oc-validation-history-empty">Nu există evenimente înregistrate.</div>';
        }

        $html = '';
        foreach ($events as $event) {
            $event_type = sanitize_html_class((string) ($event['event_type'] ?? 'scan_valid'));
            $event_label = esc_html((string) ($event['event_label'] ?? 'Validare'));
            $course = esc_html((string) ($event['course'] ?? 'N/A'));
            $validated_at = esc_html((string) ($event['validated_at_display'] ?? 'N/A'));
            $error = trim((string) ($event['error'] ?? ''));

            $html .= '<div class="oc-validation-history-item">';
            $html .= '<div class="oc-validation-history-main">';
            $html .= '<span class="oc-validation-history-badge oc-history-' . $event_type . '">' . $event_label . '</span>';
            $html .= '<span class="oc-validation-history-course">' . $course . '</span>';
            $html .= '</div>';
            $html .= '<div class="oc-validation-history-meta">' . $validated_at . '</div>';
            if ($error !== '') {
                $html .= '<div class="oc-validation-history-error">' . esc_html($error) . '</div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function render_validation_history_panel_html(int $membership_id, int $order_id, bool $is_admin): string {
        $panel_attributes = ' data-order-id="' . esc_attr($order_id) . '" data-membership-id="' . esc_attr($membership_id) . '"';
        $preloaded = !$is_admin;
        $panel_attributes .= $preloaded ? ' data-history-preloaded="1"' : '';

        $html = '<div class="oc-validation-history-panel"' . $panel_attributes . ' style="display:none;">';

        if ($is_admin) {
            $html .= '<div class="oc-validation-history-list"></div>';
            $html .= '<div class="oc-validation-history-footer">';
            $html .= '<button type="button" class="oc-btn oc-btn-secondary oc-btn-history-load-more" data-page="1" style="display:none;">Încarcă mai mult</button>';
            $html .= '</div>';
            $html .= '</div>';

            return $html;
        }

        $history = $this->build_history_response($this->resolve_history_membership_ids($membership_id, $order_id), 1, 100, false);
        $html .= '<div class="oc-validation-history-list">' . $this->render_validation_history_items_html($history['events'] ?? []) . '</div>';
        $html .= '</div>';

        return $html;
    }

    public function get_frontend_script_localization_data(): array {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce('oc_membership_admin'),
            'dateFormat' => get_option('date_format'),
            'timeFormat' => get_option('time_format'),
            'locale' => get_locale(),
            'defaultMembershipDuration' => $this->get_default_membership_duration_days(),
        ];
    }

    /**
     * AJAX: Verifică PIN-ul pentru editarea câmpurilor sensibile pe abonamente active.
     */
    public function ajax_verify_active_membership_edit_pin(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');

        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            wp_send_json_error(['message' => 'Nu aveți permisiuni suficiente.']);
        }

        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        $pin = trim((string) ($_POST['pin'] ?? ''));

        if ($pin === '') {
            wp_send_json_error(['message' => 'PIN-ul este obligatoriu.']);
        }

        $pin_hash = $this->get_active_membership_edit_pin_hash();
        if ($pin_hash === '') {
            wp_send_json_error(['message' => 'PIN-ul nu este configurat. Setați opțiunea oc_membership_active_edit_pin_hash.']);
        }

        if (!wp_check_password($pin, $pin_hash)) {
            wp_send_json_error(['message' => 'PIN invalid.']);
        }

        $ttl_seconds = 10 * MINUTE_IN_SECONDS;
        $this->grant_active_membership_edit_unlock($target_user_id, $ttl_seconds);

        wp_send_json_success([
            'message' => 'PIN valid. Editarea abonamentului activ este deblocată temporar.',
            'unlocked_for_seconds' => $ttl_seconds,
            'unlocked_until' => time() + $ttl_seconds,
        ]);
    }

    
    // ============================================
    // SECTION 3: MAIN RENDERING METHODS
    // ============================================
    
    /**
     * 📋 RENDERARE TABEL ADMIN CENTRALIZAT
     * 
     * Tabel editable cu toate funcționalitățile necesare pentru managementul zilnic:
     * - Search nativ WordPress Users
     * - 16 coloane editabile inline 
     * - Integrare cu wp_users și wp_usermeta
     * - AJAX save operations
     * - Bulk operations și export
     */
    public function render_admin_table(int $current_user_id = 0, bool $is_admin = true): string {
        // Protecție contra randare multiplă pe aceeași pagină
        static $render_count = 0;
        $render_count++;
        
        // Dacă s-a randat deja de mai mult de 1 dată, returnează gol (fără warning vizibil)
        if ($render_count > 1) {
            return ''; // Return gol în loc de warning vizibil
        }
        
        // 🔒 SECURITATE: Dacă nu este admin și încearcă să vadă date altui user, blochează
        if (!$is_admin && $current_user_id === 0) {
            $current_user_id = get_current_user_id();
        }
        
        // 🎨 ENQUEUE ASSETS pentru shortcode (dacă nu este pagină admin)
        if (!is_admin()) {
            $this->enqueue_frontend_assets();
        }
        
        // 🔄 AUTO-RESYNC: DEZACTIVAT - se rulează DOAR manual când e nevoie
        // Membership-urile se creează automat prin hook-urile WooCommerce (woocommerce_order_status_completed)
        // Pentru resync manual, folosește: cleanup-order-XXXXX.php
        // $this->auto_resync_missing_memberships();
        
        // Procesare AJAX și search
        $search_term = sanitize_text_field($_GET['search'] ?? '');
        $status_filter = sanitize_key($_GET['status_filter'] ?? 'all');
        if (!in_array($status_filter, ['all', 'pending', 'active', 'expired', 'no_membership'], true)) {
            $status_filter = 'all';
        }
        $page_from_query_var = (int) get_query_var('paged');
        $page_from_query_var_page = (int) get_query_var('page');
        $page_from_get_paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 0;
        $page_from_get_page = isset($_GET['page']) ? (int) $_GET['page'] : 0;
        $page = max(
            1,
            $page_from_get_paged > 0
                ? $page_from_get_paged
                : (
                    $page_from_get_page > 0
                        ? $page_from_get_page
                        : (
                            $page_from_query_var > 0
                                ? $page_from_query_var
                                : $page_from_query_var_page
                        )
                )
        );

        // Fallback final pentru permalink-uri de tip /page/2/
        if ($page <= 1 && !empty($_SERVER['REQUEST_URI']) && preg_match('#/page/([0-9]+)/?$#', (string) $_SERVER['REQUEST_URI'], $m)) {
            $page = max(1, (int) ($m[1] ?? 1));
        }
        $per_page = 20;
        
        // 🔒 FILTRARE PE BAZĂ DE ROL
        // Dacă utilizator normal, filtrează doar propriile date
        $filter_user_id = (!$is_admin && $current_user_id > 0) ? $current_user_id : null;

        $this->sync_membership_statuses_for_table_request($filter_user_id);
        
        // Obține date utilizatori cu WordPress Users Query
        $members_data = $this->data_handler->get_all_members_with_wp_users(
            $search_term,
            $page,
            $per_page,
            $filter_user_id,
            $is_admin,
            $status_filter
        );
        
        // Procesare salvare AJAX
        if (isset($_POST['action']) && $_POST['action'] === 'save_member_data') {
            $this->handle_save_member_data();
        }
        
        return $this->render_table_html($members_data, $search_term, $page, $per_page, $is_admin);
    }
    /**
     * Renderează HTML-ul complet al tabelului
     */
    private function render_table_html(array $members_data, string $search_term, int $page, int $per_page, bool $is_admin): string {
        ob_start();
        
        // Injectează CSS-ul cu specificitate mare pentru a suprascrie Elementor/tema
        echo $this->render_table_styles();
        ?>
        <!-- Informații discrete despre tabel (doar pentru debug) -->
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <div class="notice notice-info oc-debug-notice">
            <p>📊 Găsit: <strong><?php echo esc_html($members_data['total_found']); ?></strong> membri cu abonamente active. 
            <?php if ($search_term): ?>🔍 Search: "<?php echo esc_html($search_term); ?>"<?php endif; ?>
            📄 Pagina: <?php echo esc_html($page); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="oc-admin-table-container">
            <?php echo $this->render_table_header($search_term, $members_data, $is_admin); ?>
            <?php echo $this->render_table_content($members_data, $is_admin); ?>
            <?php echo $this->render_pagination($members_data, $page, $per_page); ?>
        </div>
        
        <?php 
        require_once plugin_dir_path(__FILE__) . 'admin-table-styles.php';
        echo get_admin_table_styles(); 
        ?>
        <?php echo $this->render_table_scripts(); ?>
        <?php
        
        return ob_get_clean();
    }
    /**
     * Renderează header-ul tabelului cu search și statistici
     */
    private function render_table_header(string $search_term, array $members_data = [], bool $is_admin = true): string {
        $total_found = $members_data['total_found'] ?? 0;
        $counts = $members_data['status_counts'] ?? [
            'all' => $total_found,
            'pending' => 0,
            'active' => 0,
            'expired' => 0,
            'no_membership' => 0
        ];
        $current_filter = $members_data['status_filter'] ?? 'all';

        $build_filter_url = function(string $filter_key): string {
            $params = $_GET;
            $params['status_filter'] = $filter_key;
            unset($params['paged'], $params['page']);
            return esc_url(add_query_arg($params));
        };
        
        ob_start();
        ?>
        <div class="oc-table-header">
            <h2><?php echo $is_admin ? '📋 Abonamente membri' : 'Abonamentul meu'; ?> 
                <?php if ($total_found > 0): ?>
                    <span class="oc-total-count">(<?php echo $total_found; ?>)</span>
                <?php endif; ?>
            </h2>
            
            <!-- 🎯 Status Badges cu Counters - DOAR ADMIN -->
            <?php if ($is_admin): ?>
            <div class="oc-status-badges">
                <a href="<?php echo $build_filter_url('all'); ?>" class="oc-status-badge <?php echo $current_filter === 'all' ? 'oc-badge-selected' : ''; ?>">
                    ⚪ Toate: <?php echo (int) ($counts['all'] ?? 0); ?>
                </a>
                <a href="<?php echo $build_filter_url('pending'); ?>" class="oc-status-badge oc-badge-pending <?php echo $current_filter === 'pending' ? 'oc-badge-selected' : ''; ?>">
                    🔵 În așteptare: <?php echo (int) ($counts['pending'] ?? 0); ?>
                </a>
                <a href="<?php echo $build_filter_url('active'); ?>" class="oc-status-badge oc-badge-active <?php echo $current_filter === 'active' ? 'oc-badge-selected' : ''; ?>">
                    🟢 Active: <?php echo (int) ($counts['active'] ?? 0); ?>
                </a>
                <a href="<?php echo $build_filter_url('expired'); ?>" class="oc-status-badge oc-badge-expired <?php echo $current_filter === 'expired' ? 'oc-badge-selected' : ''; ?>">
                    🔴 Expirate: <?php echo (int) ($counts['expired'] ?? 0); ?>
                </a>
                <a href="<?php echo $build_filter_url('no_membership'); ?>" class="oc-status-badge <?php echo $current_filter === 'no_membership' ? 'oc-badge-selected' : ''; ?>">
                    ⚫ Fără abonament: <?php echo (int) ($counts['no_membership'] ?? 0); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Search și Add Client - DOAR ADMIN -->
            <?php if ($is_admin): ?>
            <div class="oc-header-actions">
            <!-- Search Box cu WordPress nativ -->
            <div class="oc-search-container">
                <?php $reset_url = esc_url(remove_query_arg(['search', 'paged', 'page'])); ?>
                <form method="get" class="oc-search-form" data-reset-url="<?php echo $reset_url; ?>">
                    <?php
                    // Păstrează parametrii existenți din URL
                    foreach ($_GET as $key => $value) {
                        if ($key !== 'search' && $key !== 'paged' && $key !== 'page') {
                            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                        }
                    }
                    ?>
                    <input type="text" 
                           name="search" 
                           value="<?php echo esc_attr($search_term); ?>" 
                           placeholder="Căutare după nume, email sau telefon..."
                           class="oc-search-input">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        Caută
                    </button>
                    <?php if ($search_term): ?>
                        <a href="<?php echo esc_url(remove_query_arg(['search', 'paged', 'page'])); ?>" class="button">
                            <span class="dashicons dashicons-image-rotate"></span>
                            Resetează
                        </a>
                    <?php endif; ?>
                </form>
            </div>
                
                <!-- Buton Adaugă Client Nou -->
                <div class="oc-add-client-button-wrapper">
                    <form class="oc-inline-form">
                        <button type="button" class="button button-primary" id="oc-toggle-add-client-form" aria-controls="oc-add-client-form-container" aria-expanded="false">
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                            Adaugă client nou
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Container form creare client (ascuns initial) -->
        <div class="oc-add-client-form-container" id="oc-add-client-form-container" style="display: none;">
            <?php echo $this->render_add_client_form(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Renderează conținutul principal al tabelului
     */
    private function render_table_content(array $members_data, bool $is_admin = true): string {
        ob_start();
        ?>
        <!-- Layout Cards Unificat: Pentru desktop și mobil -->
        <div class="oc-admin-cards-container">
            <form id="oc-admin-cards-form" method="post">
                <?php wp_nonce_field('oc_save_member_data', 'oc_member_nonce'); ?>
                
                <!-- Cards View pentru toți membrii -->
                <?php if (empty($members_data['members'])): ?>
                    <div class="oc-empty-state">
                        <p><strong>Nu există abonamente active încă.</strong></p>
                        <p>Abonamentele vor apărea automat după achiziționarea unui abonament pe website-ul nostru sau la recepția studioului de dans.</p>
                        <p>Dacă ați achiziționat un abonament și nu apare în contul dvs., vă rugăm să solicitați informații la recepția studioului de dans.</p>
                    </div>
                <?php else: ?>
                    <div class="oc-admin-cards-grid">
                        <?php foreach ($members_data['members'] as $member): ?>
                            <?php echo $this->render_admin_member_card($member, $is_admin); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Placeholder pentru member card - va fi implementat în Cards Renderer
     */
    private function render_admin_member_card(array $member, bool $is_admin = true): string {
        $user_id = $member['user_id'];
        $is_guest = strpos($user_id, 'guest_') === 0;
        
        // Determină ID-ul numeric real al utilizatorului
        $real_user_id = 0;
        if (!$is_guest) {
            if (is_numeric($user_id)) {
                $real_user_id = intval($user_id);
            } else {
                // Dacă e username, găsește ID-ul
                $user = get_user_by('login', $user_id);
                if ($user) {
                    $real_user_id = $user->ID;
                }
            }
        }
        $member['real_user_id'] = $real_user_id;
        
        // 🎯 v1.3.0: Calculează color coding bazat pe status și expirare
        $validation_status = $member['validation_status'] ?? $member['membership_status'] ?? 'active';
        $expires_date = $member['expiration_date'] ?? $member['expires_at'] ?? null;
        $member_order_id = intval($member['order_id'] ?? 0);
        $member_payment_key = $this->normalize_payment_method_key((string) ($member['payment_method'] ?? ''));
        $member_gateway_copayment = $this->is_gateway_copayment_context(
            $member_order_id,
            $member_payment_key,
            (float) ($member['product_price'] ?? 0)
        );
        $member_unlimited_by_payment = $this->is_gateway_payment_method($member_payment_key) && !$member_gateway_copayment;
        $member_is_unlimited = (!empty($member['is_unlimited']) && !$member_gateway_copayment) || $member_unlimited_by_payment;
        $display_expires_date = $member_is_unlimited ? null : $expires_date;
        $validation_status = $this->get_effective_membership_status(
            $validation_status,
            $display_expires_date,
            $member_is_unlimited,
            isset($member['remaining_sessions']) ? (int) $member['remaining_sessions'] : null
        );

        $header_packages = $this->get_user_header_packages(
            (int) $real_user_id,
            $is_guest ? $member_order_id : 0
        );
        $aggregate_member_status = oc_membership_resolve_aggregate_member_status($header_packages);
        $header_active_packages_total = (float) ($aggregate_member_status['active_packages_total'] ?? 0.0);
        $header_active_packages_count = (int) ($aggregate_member_status['active_packages_count'] ?? 0);
        $validation_status = (string) ($aggregate_member_status['status'] ?? $validation_status);
        $display_expires_date = $validation_status === 'active'
            ? ($aggregate_member_status['nearest_active_expiry'] ?? null)
            : null;
        
        $card_status_class = 'status-unknown';
        if ($validation_status === 'pending') {
            $card_status_class = 'status-pending'; // Albastru
        } elseif ($validation_status === 'expired') {
            $card_status_class = 'status-expired'; // Roșu
        } elseif ($validation_status === 'active' && $display_expires_date) {
            $days_until_expiry = $this->get_days_until_membership_expiry($display_expires_date);
            if ($days_until_expiry !== null && $days_until_expiry <= 7) {
                $card_status_class = 'status-expires-soon'; // Galben (< 7 zile)
            } else {
                $card_status_class = 'status-active'; // Verde (> 7 zile)
            }
        } elseif ($validation_status === 'active') {
            $card_status_class = 'status-active'; // Verde (fără dată expirare)
        }
        
        ob_start();
        ?>
        <div class="oc-admin-card <?php echo esc_attr($card_status_class); ?>" 
             data-user-id="<?php echo esc_attr($user_id); ?>" 
             data-real-user-id="<?php echo esc_attr($is_guest ? 0 : $member['real_user_id'] ?? $user_id); ?>"
               data-payment-method="<?php echo esc_attr($member_payment_key); ?>"
             data-status="<?php echo esc_attr($validation_status); ?>">
            <!-- Header cu info minimale complete -->
            <div class="oc-card-header">
                <div class="oc-card-main-info">
                    <div class="oc-member-identity">
                        <div class="oc-member-name">
                            <?php echo esc_html($member['display_name'] ?? $member['user_name'] ?? 'N/A'); ?>
                            <?php if (!$is_guest && isset($member['user_id']) && $member['user_id'] > 0): ?>
                                <span class="oc-user-id-badge" title="User ID">ID: <?php echo esc_html($member['user_id']); ?></span>
                            <?php endif; ?>
                            <?php if ($is_guest): ?>
                                <span class="oc-guest-badge">Guest</span>
                            <?php endif; ?>
                        </div>
                        <div class="oc-member-email">
                            <?php echo esc_html($member['email'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    
                    <div class="oc-membership-info">
                        <div class="oc-subscription-type">
                            <strong>Abonamente:</strong>
                        </div>
                        <?php if (!empty($header_packages)): ?>
                            <?php $header_payment_methods = $this->get_available_payment_methods(); ?>
                            <div class="oc-header-packages-list">
                                <?php foreach ($header_packages as $header_package): ?>
                                    <?php
                                    $pkg_name = (string) ($header_package['name'] ?? 'Abonament');
                                    $pkg_price = number_format((float) ($header_package['price'] ?? 0), 2, '.', '');
                                    $pkg_valid_until = (string) ($header_package['valid_until'] ?? '');
                                    $pkg_status = (string) ($header_package['status'] ?? 'pending');
                                    $pkg_payment_key = $this->normalize_payment_method_key((string) ($header_package['payment_method'] ?? ''));
                                    $pkg_payment_label = $header_payment_methods[$pkg_payment_key] ?? 'Necunoscut';
                                    $pkg_status_text = $pkg_status === 'active' ? 'Activ' : ($pkg_status === 'expired' ? 'Expirat' : 'În așteptare');
                                    $pkg_courses = is_array($header_package['courses'] ?? null) ? $header_package['courses'] : [];
                                    $pkg_valid_text = $pkg_valid_until !== '' ? $this->format_date_european($pkg_valid_until) : 'Fără expirare';
                                    ?>
                                    <div class="oc-header-package-item" data-status="<?php echo esc_attr($pkg_status); ?>">
                                        <span class="oc-header-package-name"><?php echo esc_html($pkg_name); ?></span>
                                        <span class="oc-header-package-status oc-status-<?php echo esc_attr($pkg_status); ?>"><?php echo esc_html($pkg_status_text); ?></span>
                                        <span class="oc-header-package-meta">Valabil până la: <strong><?php echo esc_html($pkg_valid_text); ?></strong></span>
                                        <span class="oc-header-package-meta"><strong>Preț abonament:</strong> <?php echo esc_html($pkg_price); ?> lei</span>
                                        <span class="oc-header-package-meta"><strong>Modalitate de plată:</strong> <?php echo esc_html($pkg_payment_label); ?></span>
                                        <div class="oc-header-package-courses">
                                            <strong>Cursuri incluse:</strong>
                                            <?php if (!empty($pkg_courses)): ?>
                                                <ul class="oc-header-package-courses-list">
                                                    <?php foreach ($pkg_courses as $pkg_course_name): ?>
                                                        <li><?php echo esc_html((string) $pkg_course_name); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="oc-header-package-courses-empty">N/A</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="oc-header-package-item" data-status="pending">
                                <span class="oc-header-package-name">Niciun abonament activ/în așteptare</span>
                            </div>
                        <?php endif; ?>
                        <!-- 🎯 v1.3.0: Status și Date de Expirare -->
                        <?php 
                        $start_date = $member['start_date'] ?? null;
                        $expires_date = $display_expires_date;
                        if ($member_is_unlimited) {
                            $expires_date = null;
                        }
                        
                        // Calculează dacă e pending
                        $is_pending = ($validation_status === 'pending');
                        ?>
                        
                        <?php if ($is_pending && $start_date): ?>
                        <!-- ⏳ PENDING: Afișează când începe -->
                        <div class="oc-start-date-info">
                            <strong>🚀 Începe:</strong> 
                            <span class="oc-start-date-value">
                                <?php echo $this->format_date_european($start_date); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="oc-validity-info">
                            <strong>Total abonamente active:</strong> <?php echo esc_html((string) $header_active_packages_count); ?>
                            ·
                            <strong>Preț: <?php echo esc_html(number_format($header_active_packages_total, 2, '.', '')); ?> lei</strong>
                        </div>
                    </div>
                </div>
                
                <div class="oc-card-actions">
                    <span class="oc-status-badge status-<?php echo esc_attr($validation_status); ?>">
                        <?php
                        $wc_order_post_status = $wc_order_post_status ?? ($member_order_id > 0 ? get_post_status($member_order_id) : '');
                        // Map status la text user-friendly
                        if ($validation_status === 'expired' && $wc_order_post_status === 'wc-cancelled') {
                            $status_text = '🚫 Anulat';
                        } elseif ($validation_status === 'expired' && $wc_order_post_status === 'wc-refunded') {
                            $status_text = '💸 Rambursat';
                        } else {
                            $status_text = match($validation_status) {
                                'pending' => '⏳ În așteptare',
                                'active'  => '✓ Activ',
                                'expired' => '✕ Expirat',
                                default   => ucfirst($validation_status)
                            };
                        }
                        echo esc_html($status_text);
                        ?>
                    </span>
                    <div class="oc-action-buttons">
                        <button type="button" class="oc-btn oc-btn-info" onclick="toggleAdminCard('<?php echo esc_attr($user_id); ?>')" title="Informații detaliate" data-user-id="<?php echo esc_attr($user_id); ?>" aria-controls="card-details-<?php echo esc_attr($user_id); ?>" aria-expanded="false">
                            <span class="dashicons dashicons-info" aria-hidden="true"></span>
                            Detalii
                        </button>
                        <?php if ($is_admin): ?>
                        <button type="button" class="oc-btn oc-btn-validate" onclick="validateMembership('<?php echo esc_attr($user_id); ?>')" title="Validare manuală">
                            <span class="dashicons dashicons-yes-alt"></span>
                            Validează
                        </button>
                        <?php endif; ?>
                        <?php
                        // 🎯 FIX: Pentru utilizatori normali, afișează QR direct din meta (fără AJAX)
                        $qr_filename = get_user_meta($user_id, 'simple_qr_filename', true);
                        $qr_url = '';
                        if ($qr_filename) {
                            $upload_dir = wp_upload_dir();
                            $qr_url = $upload_dir['baseurl'] . '/membership-qr-codes/' . $qr_filename;
                        }
                        ?>
                        <button type="button" 
                                class="oc-btn oc-btn-qr" 
                                onclick="<?php echo $qr_url ? 'showQRCodeDirect(\'' . esc_js($qr_url) . '\', \'' . esc_js($member['display_name']) . '\')' : 'alert(\'QR code nu este disponibil.\')'; ?>" 
                                title="Afișare cod QR">
                            <span class="dashicons dashicons-smartphone"></span>
                            QR
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if ($card_status_class === 'status-pending'): ?>
            <!-- 🔒 MANUAL ACTIVATION: Mesaj pentru utilizatori cu abonamente pending -->
            <div class="oc-pending-activation-notice">
                <div class="oc-pending-notice-inner">
                    <span class="oc-pending-icon dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <div class="oc-pending-content">
                        <strong class="oc-pending-title">Abonament în așteptare</strong>
                        <span class="oc-pending-text">
                            <?php if ($is_admin): ?>
                                Apasă pe „Detalii” pentru activare.
                            <?php else: ?>
                                Se activează la prima prezență.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Content expandabil cu toate detaliile editable -->
            <div class="oc-card-details" id="card-details-<?php echo esc_attr($user_id); ?>" style="display: none;">
                <div class="oc-admin-form">
                    <!-- Row 1: Date personale -->
                    <div class="oc-form-row">
                        <div class="oc-form-group">
                            <label for="name-<?php echo esc_attr($user_id); ?>">Nume prenume</label>
                            <input type="text" 
                                   id="name-<?php echo esc_attr($user_id); ?>" 
                                   name="display_name"
                                   value="<?php echo esc_attr($member['display_name'] ?? ''); ?>" 
                                   data-original-value="<?php echo esc_attr($member['display_name'] ?? ''); ?>"
                                   data-field-type="text"
                                   disabled class="oc-field-readonly">
                        </div>
                        <div class="oc-form-group">
                            <label for="email-<?php echo esc_attr($user_id); ?>">Email</label>
                            <input type="email" 
                                   id="email-<?php echo esc_attr($user_id); ?>" 
                                   name="email"
                                   value="<?php echo esc_attr($member['email']); ?>" 
                                   data-original-value="<?php echo esc_attr($member['email']); ?>"
                                   data-field-type="email"
                                   disabled class="oc-field-readonly">
                        </div>
                    </div>
                    
                    <!-- Row 2: Contact + Reduceri Membri -->
                    <div class="oc-form-row">
                        <div class="oc-form-group">
                            <label for="phone-<?php echo esc_attr($user_id); ?>">Telefon</label>
                            <input type="tel" 
                                   id="phone-<?php echo esc_attr($user_id); ?>" 
                                   name="phone"
                                   value="<?php echo esc_attr($member['phone'] ?? ''); ?>" 
                                   data-original-value="<?php echo esc_attr($member['phone'] ?? ''); ?>"
                                   data-field-type="tel"
                                   disabled class="oc-field-readonly">
                        </div>
                        <div class="oc-form-group">
                            <label for="discount-<?php echo esc_attr($user_id); ?>">Reduceri Membri</label>
                            <select id="discount-<?php echo esc_attr($user_id); ?>" 
                                    name="member_discount" 
                                    data-original-value="<?php echo esc_attr($member['member_discount'] ?? ''); ?>"
                                    data-field-type="select"
                                    disabled class="oc-field-readonly">
                                <option value="">Fără reducere</option>
                                <?php 
                                // Get WooCommerce coupons for discount dropdown
                                $coupons = get_posts([
                                    'post_type' => 'shop_coupon',
                                    'post_status' => 'publish',
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                foreach ($coupons as $coupon): 
                                    // Mapează member_discount din DB la cuponul WooCommerce
                                    $member_discount = $member['member_discount'] ?? '';
                                    $coupon_code = $coupon->post_title;
                                    
                                    // Verifică match prin mai multe criterii (DOAR dacă member_discount nu este gol)
                                    $is_selected = false;
                                    if (!empty($member_discount)) {
                                        if ($member_discount === $coupon_code) {
                                            $is_selected = true; // Match exact
                                        } elseif (stripos($member_discount, $coupon_code) !== false) {
                                            $is_selected = true; // Match parțial
                                        } elseif (stripos($coupon_code, $member_discount) !== false) {
                                            $is_selected = true; // Match invers
                                        }
                                    }
                                ?>
                                    <option value="<?php echo esc_attr($coupon->post_title); ?>" 
                                            <?php if ($is_selected) echo 'selected="selected"'; ?>>
                                        <?php echo esc_html($coupon->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Row 3: Rezumat nou - pachetele sunt gestionate individual (doar admin) -->
                    <?php if ($is_admin): ?>
                    <div class="oc-form-row">
                        <div class="oc-form-group oc-full-width">
                            <label>Abonamente și prețuri</label>
                            <small>Poți actualiza fiecare pachet de mai jos: perioada, cursurile, prețul și plata.</small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Row 4: Cursuri Incluse (LISTĂ EXPANDATĂ per curs) -->
                    <div class="oc-form-row">
                        <div class="oc-form-group oc-full-width">
                            <label>Cursuri Incluse</label>
                            
                            <?php
                            $member_is_gateway = $this->is_gateway_payment_method($member_payment_key);
                            $member_has_unlimited_sessions = $member_is_unlimited;
                            // Afișează mesajul "Acces nelimitat" când membrul are ședințe nelimitate
                            // doar pentru VIP Pool, nu pentru gateway-uri 7CARD/ESX.
                            $show_vip_notice_only = $member_has_unlimited_sessions && !$member_is_gateway;
                            ?>

                            <?php if ($show_vip_notice_only && $validation_status !== 'pending'): ?>
                                <?php
                                // VIP: card vizual identic cu package-section, dar cu mesaj nelimitat în loc de cursuri
                                $vip_order_id   = $member['order_id'] ?? 0;
                                $vip_expiry     = $member['expiration_date'] ?? $member['expires_at'] ?? null;
                                $vip_pkg_status = $this->get_effective_membership_status(
                                    (string) $validation_status,
                                    $vip_expiry,
                                    $member_has_unlimited_sessions && empty($vip_expiry),
                                    isset($member['remaining_sessions']) ? (int) $member['remaining_sessions'] : null
                                );
                                $vip_border     = ($vip_pkg_status === 'expired') ? '#dc3545' : '#28a745';
                                $vip_badge      = ($vip_pkg_status === 'expired')
                                    ? '<span class="oc-status-badge status-expired">✕ Expirat</span>'
                                    : '<span class="oc-status-badge status-active">✓ Activ</span>';
                                $vip_date_text  = $vip_expiry
                                    ? 'Expiră: <strong>' . $this->format_date_european($vip_expiry) . '</strong>'
                                    : 'Expiră: <strong>Fără expirare</strong>';
                                ?>
                                <div class="oc-courses-expandedlist">
                                    <div class="oc-package-section oc-package-<?php echo esc_attr($vip_pkg_status); ?>"
                                         data-order-id="<?php echo esc_attr($vip_order_id); ?>"
                                         style="border-color: <?php echo esc_attr($vip_border); ?>;">
                                        <div class="oc-package-header">
                                            <div class="oc-package-title-wrap">
                                                <h4 class="oc-package-title">🎫 <?php echo esc_html($member['product_name'] ?? 'Abonament VIP'); ?></h4>
                                                <?php if ($vip_order_id): ?>
                                                    <small class="oc-package-order">Comandă #<?php echo esc_html($vip_order_id); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="oc-package-status-wrap">
                                                <?php echo $vip_badge; ?>
                                                <div class="oc-package-date"><?php echo $vip_date_text; ?></div>
                                            </div>
                                        </div>
                                        <div class="oc-package-courses">
                                            <div class="oc-vip-unlimited-notice">
                                                <strong class="oc-vip-text">
                                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                                    Acces nelimitat la toate cursurile din abonamentul activ.
                                                </strong>
                                            </div>
                                        </div>
                                        <div class="oc-package-actions-container">
                                            <div class="oc-package-buttons-row">
                                                <button type="button" class="oc-btn oc-btn-secondary oc-btn-validation-history"
                                                    data-membership-id="<?php echo esc_attr($member['validation_id'] ?? $member['membership_id'] ?? 0); ?>"
                                                    data-order-id="<?php echo esc_attr($vip_order_id); ?>">
                                                    <span class="dashicons dashicons-list-view"></span> Vezi Istoric Validări
                                                </button>
                                                <?php if ($is_admin): ?>
                                                <button type="button" class="oc-btn oc-btn-danger oc-btn-cancel-membership"
                                                    data-user-id="<?php echo esc_attr($member['real_user_id'] ?? $user_id); ?>"
                                                    data-membership-id="<?php echo esc_attr($member['validation_id'] ?? $member['membership_id'] ?? 0); ?>"
                                                    data-order-id="<?php echo esc_attr($vip_order_id); ?>"
                                                    title="Anulează comanda WooCommerce #<?php echo esc_attr($vip_order_id); ?> și marchează abonamentul ca expirat">
                                                    <span class="dashicons dashicons-trash"></span> Anulează Acest Pachet
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php echo $this->render_validation_history_panel_html((int) ($member['validation_id'] ?? $member['membership_id'] ?? 0), (int) $vip_order_id, $is_admin); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$show_vip_notice_only || $validation_status === 'pending'): ?>
                                <!-- LISTĂ cursuri + acțiuni pachet; pentru VIP pending este necesară secțiunea de activare -->
                                <?php echo $this->render_editable_courses_list_with_sessions($user_id, $member['validation_id'] ?? $member['membership_id'] ?? 0, $is_admin); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Row 6: Status -->
                    <div class="oc-form-row">
                        <div class="oc-form-group">
                            <label for="status-<?php echo esc_attr($user_id); ?>">Status membru</label>
                            <select id="status-<?php echo esc_attr($user_id); ?>" 
                                    name="validation_status" 
                                    data-original-value="<?php echo esc_attr($member['membership_status'] ?? ''); ?>"
                                    data-field-type="select"
                                    disabled 
                                    class="oc-field-readonly oc-field-always-readonly">
                                <option value="active" <?php selected($member['membership_status'] ?? '', 'active'); ?>>Activ</option>
                                <option value="expired" <?php selected($member['membership_status'] ?? '', 'expired'); ?>>Expirat</option>
                                <option value="completed" <?php selected($member['membership_status'] ?? '', 'completed'); ?>>Completat</option>
                                <option value="inactive" <?php selected($member['membership_status'] ?? '', 'inactive'); ?>>Inactiv</option>
                            </select>
                            <?php if ($is_admin): ?>
                            <small class="oc-field-info-text">ℹ️ Statusul se schimbă când activezi abonamentul.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons - DOAR ADMIN -->
                    <?php if ($is_admin): ?>
                    <div class="oc-form-actions">
                        <button type="button" class="oc-btn oc-btn-secondary oc-edit-btn" 
                            data-user-id="<?php echo esc_attr($user_id); ?>"><span class="dashicons dashicons-edit"></span> Edit</button>
                        <button type="button" class="oc-btn oc-btn-success oc-btn-add-subscription" 
                                data-user-id="<?php echo esc_attr($user_id); ?>" aria-controls="oc-renew-container-<?php echo esc_attr($user_id); ?>" aria-expanded="false">
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Abonament Nou
                        </button>
                        <button type="button" class="oc-btn oc-btn-primary oc-btn-save-member" 
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                data-membership-id="<?php echo esc_attr($member['validation_id'] ?? $member['membership_id'] ?? 0); ?>"
                                data-order-id="<?php echo esc_attr($member['order_id'] ?? 0); ?>"
                            style="display: none;"><span class="dashicons dashicons-saved"></span> Salvează Date</button>
                        <button type="button" class="oc-btn oc-btn-success oc-btn-create-order" 
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                data-membership-id="<?php echo esc_attr($member['validation_id'] ?? $member['membership_id'] ?? 0); ?>"
                            style="display: none;"><span class="dashicons dashicons-cart"></span> Creează Comandă WooCommerce</button>
                        <button type="button" class="oc-btn oc-btn-danger oc-btn-cancel-edit" 
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                            style="display: none;"><span class="dashicons dashicons-no-alt"></span> Anulează</button>
                    </div>
                    <?php if (($validation_status ?? '') === 'active'): ?>
                    <div class="oc-form-actions-protected">
                        <button type="button" class="oc-btn oc-btn-warning oc-edit-active-membership-btn"
                            data-user-id="<?php echo esc_attr($user_id); ?>"
                            data-membership-id="<?php echo esc_attr($member['validation_id'] ?? $member['membership_id'] ?? 0); ?>">
                            <span class="dashicons dashicons-lock"></span> Editează abonament activ
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- FORMULAR REÎNNOIRE/ADĂUGARE ABONAMENT NOU (ACELAȘI ca la creare client) -->
                    <div class="oc-renew-container" id="oc-renew-container-<?php echo esc_attr($user_id); ?>" style="display: none;">
                        <hr class="oc-renew-separator">
                        <?php echo $this->render_renew_subscription_form($member); ?>
                    </div>
                    
                    <?php 
                    // 📜 Istoric abonamente expirate (pentru TOȚI utilizatorii)
                    try {
                        if (method_exists($this, 'render_expired_memberships_history')) {
                            echo $this->render_expired_memberships_history($user_id, $is_admin);
                        }
                    } catch (Throwable $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[EXPIRED HISTORY FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                        }
                    }
                    ?>
                    
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    
    // ============================================
    // SECTION 4: FORM RENDERING METHODS
    // ============================================
    
    /**
     * Renderează paginarea
     */
    private function render_pagination(array $members_data, int $page, int $per_page): string {
        $total_pages = $members_data['total_pages'] ?? 1;
        $total_found = $members_data['total_found'] ?? 0;
        
        // Calculează range-ul afișat
        $start = (($page - 1) * $per_page) + 1;
        $end = min($page * $per_page, $total_found);
        
        ob_start();
        ?>
        <!-- Info despre numărul de membri și pagina curentă -->
        <div class="oc-pagination-info">
            <div class="oc-pagination-stats">
                <?php if ($total_found > 0): ?>
                    Afișare <strong><?php echo $start; ?>-<?php echo $end; ?></strong> din <strong><?php echo $total_found; ?></strong> membri
                <?php else: ?>
                    <strong>0</strong> membri găsiți
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="oc-pagination-controls">
                <?php
                // Use query-arg pagination to work reliably on My Account endpoints and shortcode pages.
                // IMPORTANT: Do not use add_query_arg for %#% placeholder (it gets URL-encoded).
                $base_url = remove_query_arg(['paged', 'page']);
                $base_url .= (strpos($base_url, '?') !== false ? '&' : '?') . 'paged=%#%';

                echo paginate_links([
                    'base' => $base_url,
                    'format' => '',
                    'current' => max(1, $page),
                    'total' => $total_pages,
                    'prev_text' => '‹ Anterior',
                    'next_text' => 'Următorul ›',
                    'type' => 'list',
                    'end_size' => 3,
                    'mid_size' => 3,
                ]);
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- CSS moved to assets/membership-manager-extracted.css -->
        <?php
        return ob_get_clean();
    }
    /**
     * Returnează CSS-ul pentru tabel
     * 
     * NOTE: CSS moved to assets/membership-manager-extracted.css (v2.1.0)
     */
    private function render_table_styles(): string {
        // CSS extracted to external file for better performance and caching
        return '<!-- CSS loaded from assets/membership-manager-extracted.css -->';
    }
    /**
     * Returnează JavaScript-ul pentru tabel
     */
    private function render_table_scripts(): string {
        $nonce = wp_create_nonce('oc_membership_admin');
        $ajax_url = admin_url('admin-ajax.php');
        
        return "
        <script>
        // Define ajaxurl for frontend (WordPress only defines it in admin)
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '{$ajax_url}';
        }
        
        // Toggle admin card expansion
        function toggleAdminCard(userId) {
            const details = document.getElementById('card-details-' + userId);
            if (!details) {
                return;
            }

            const currentCard = details.closest('.oc-admin-card');
            const infoButton = currentCard ? currentCard.querySelector('.oc-btn-info') : null;
            const isOpening = window.getComputedStyle(details).display === 'none';

            if (isOpening) {
                if (typeof window.ocAdminCloseAddClientForm === 'function') {
                    window.ocAdminCloseAddClientForm();
                }
                if (typeof window.ocAdminCloseVisibleRenewForms === 'function') {
                    window.ocAdminCloseVisibleRenewForms(userId);
                }
            }
            
            // 🎯 ACCORDION: Închide alte carduri deschise
            if (isOpening) {
                document.querySelectorAll('.oc-card-details').forEach(function(otherCard) {
                    if (otherCard.id !== 'card-details-' + userId && window.getComputedStyle(otherCard).display !== 'none') {
                        otherCard.style.display = 'none';
                        const otherParentCard = otherCard.closest('.oc-admin-card');
                        const otherInfoButton = otherParentCard ? otherParentCard.querySelector('.oc-btn-info') : null;
                        const otherRenewContainer = otherCard.querySelector('.oc-renew-container');
                        const otherAddButton = otherCard.querySelector('.oc-btn-add-subscription');

                        if (otherInfoButton) {
                            otherInfoButton.classList.remove('is-active');
                            otherInfoButton.setAttribute('aria-expanded', 'false');
                        }

                        if (otherRenewContainer) {
                            otherRenewContainer.style.display = 'none';
                        }

                        if (otherAddButton) {
                            otherAddButton.classList.remove('oc-btn-warning');
                            otherAddButton.classList.add('oc-btn-success');
                            otherAddButton.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }
            
            // Toggle cardul curent
            if (isOpening) {
                details.style.display = 'block';
                if (infoButton) {
                    infoButton.classList.add('is-active');
                    infoButton.setAttribute('aria-expanded', 'true');
                }
                
                // 🎯 SCROLL automat la întreg cardul admin (cu offset 15px sub bara navigare)
                // Așteaptă ca DOM-ul să se reorganizeze complet după accordion + expandare (100ms)
                setTimeout(function() {
                    // Selectează cardul specific după data-user-id
                    const card = document.querySelector('.oc-admin-card[data-user-id=\"' + userId + '\"]');
                    
                    if (card) {
                        const offsetTop = card.getBoundingClientRect().top + window.pageYOffset - 15;
                        window.scrollTo({ top: offsetTop, behavior: 'smooth' });
                    } else {
                        console.error('[Scroll] Card not found for user:', userId);
                    }
                }, 100);
            } else {
                details.style.display = 'none';
                if (infoButton) {
                    infoButton.classList.remove('is-active');
                    infoButton.setAttribute('aria-expanded', 'false');
                }

                const renewContainer = details.querySelector('.oc-renew-container');
                const addButton = details.querySelector('.oc-btn-add-subscription');
                if (renewContainer) {
                    renewContainer.style.display = 'none';
                }
                if (addButton) {
                    addButton.classList.remove('oc-btn-warning');
                    addButton.classList.add('oc-btn-success');
                    addButton.setAttribute('aria-expanded', 'false');
                }
            }
        }
        
        // Validate membership - SMART VALIDATION bazată pe orar
        function showValidationModal(message, isSuccess = true) {
            // Creează modal
            const modal = document.createElement('div');
            modal.className = 'oc-validation-modal';
            modal.innerHTML = '<div class=\"oc-validation-modal-content ' + (isSuccess ? 'success' : 'error') + '\">' +
                '<div class=\"oc-validation-modal-badge\"><span class=\"dashicons ' + (isSuccess ? 'dashicons-yes-alt' : 'dashicons-warning') + '\" aria-hidden=\"true\"></span></div>' +
                '<h3 class=\"oc-validation-title\">' + (isSuccess ? 'Validare reușită' : 'Validarea nu a fost finalizată') + '</h3>' +
                '<div class=\"oc-validation-message\">' + message.replace(/\\n/g, '<br>') + '</div>' +
                '<div class=\"oc-validation-actions\"><button class=\"oc-validation-close button button-primary\">OK</button></div>' +
                '</div>';
            
            document.body.appendChild(modal);
            
            // Close pe click buton sau background
            const closeModal = () => {
                modal.remove();
                if (isSuccess) location.reload();
            };
            
            modal.querySelector('.oc-validation-close').onclick = closeModal;
            modal.onclick = (e) => { if (e.target === modal) closeModal(); };
        }
        
        function showLoadingModal() {
            const modal = document.createElement('div');
            modal.className = 'oc-validation-modal';
            modal.id = 'oc-loading-modal';
            modal.innerHTML = '<div class=\"oc-validation-modal-content loading\">' +
                '<div class=\"oc-validation-modal-badge\"><div class=\"oc-spinner\"></div></div>' +
                '<h3 class=\"oc-validation-title\">Se verifică accesul</h3>' +
                '<div class=\"oc-validation-message\">Validare în curs...</div>' +
                '</div>';
            document.body.appendChild(modal);
            return modal;
        }
        
        // Show QR Code DIRECT (fără AJAX) - pentru utilizatori normali
        function showQRCodeDirect(qrUrl, userName) {
            const qrCodes = [{
                user_id: 0,
                product_name: 'QR Membru',
                qr_url: qrUrl,
                expires_at: 'Permanent',
                type: 'simple'
            }];

            displayQRModal(qrCodes, userName);
        }

        // Show QR Code for user (cu AJAX) - pentru admin (backward compatibility)
        function showQRCode(userId) {
            const loadingModal = showLoadingModal();

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_get_user_qr_codes',
                    user_id: userId,
                    nonce: '{$nonce}'
                },
                success: function(response) {
                    loadingModal.remove();
                    if (response.success && response.data.qr_codes && response.data.qr_codes.length > 0) {
                        displayQRModal(response.data.qr_codes, response.data.user_name);
                    } else {
                        alert('Nu există coduri QR active pentru acest utilizator.');
                    }
                },
                error: function(xhr, status, error) {
                    loadingModal.remove();
                    alert('Eroare la încărcarea codurilor QR.');
                }
            });
        }

        function displayQRModal(qrCodes, userName) {
            const modal = document.createElement('div');
            modal.className = 'oc-qr-modal-overlay';

            const safeUserName = String(userName || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');

            let content = '<div class=\'oc-qr-modal-card\' role=\'dialog\' aria-modal=\'true\' aria-label=\'Coduri QR\'>';
            content += '<div class=\'oc-qr-modal-head\'>';
            content += '<div class=\'oc-qr-modal-heading\'>';
            content += '<div class=\'oc-qr-modal-kicker\'>Coduri QR active</div>';
            content += '<h2 class=\'oc-qr-modal-title\'>' + safeUserName + '</h2>';
            content += '</div>';
            content += '<button type=\'button\' class=\'oc-qr-modal-icon-close oc-qr-modal-close\' aria-label=\'Închide fereastra\'><span class=\'dashicons dashicons-no-alt\' aria-hidden=\'true\'></span></button>';
            content += '</div>';
            content += '<div class=\'oc-qr-grid\'>';

            qrCodes.forEach(function(qr, index) {
                content += '<div class=\'oc-qr-item\'>';
                content += '<div class=\'oc-qr-image-wrap\'>';
                content += '<img src=\'' + qr.qr_url + '\' alt=\'QR Code\' class=\'oc-qr-image\'>';
                content += '</div>';
                content += '<div class=\'oc-qr-item-label\'>QR ' + (index + 1) + '</div>';
                content += '</div>';
            });

            content += '</div>';

            const primaryQr = qrCodes[0] && qrCodes[0].qr_url ? qrCodes[0].qr_url : '';
            content += '<div class=\'oc-qr-modal-actions\'>';
            if (primaryQr) {
                content += '<a href=\'' + primaryQr + '\' download=\'qr-abonament-1.webp\' class=\'oc-qr-btn oc-qr-btn-download\'><span class=\'dashicons dashicons-download\' aria-hidden=\'true\'></span>Descarcă QR Code</a>';
            }
            content += '<button type=\'button\' class=\'oc-qr-btn oc-qr-btn-close oc-qr-modal-close\'><span class=\'dashicons dashicons-no-alt\' aria-hidden=\'true\'></span>Închide fereastra</button>';
            content += '</div>';
            content += '</div>';

            modal.innerHTML = content;
            document.body.appendChild(modal);

            const closeButtons = modal.querySelectorAll('.oc-qr-modal-close');
            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    modal.remove();
                });
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.remove();
            });

            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    modal.remove();
                    document.removeEventListener('keydown', escHandler);
                }
            });
        }
        
        function validateMembership(userId) {
            const card = document.querySelector('.oc-admin-card[data-user-id=\"' + userId + '\"]');
            const paymentMethod = card ? (card.getAttribute('data-payment-method') || '').toLowerCase() : '';

            const is7card = paymentMethod === 'oc_7card';
            const isEsx = paymentMethod === 'oc_esx';

            const runValidation = function() {
                // Show loading modal
                const loadingModal = showLoadingModal();

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oc_validate_membership_smart',
                        user_id: userId,
                        nonce: '{$nonce}'
                    },
                    success: function(response) {
                        loadingModal.remove();

                        if (response.success) {
                            showValidationModal(response.data.message, true);
                    } else {
                            showValidationModal(response.data.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        loadingModal.remove();
                        showValidationModal('Eroare la validare: ' + error, false);
                    }
                });
            };

            if (is7card || isEsx) {
                const provider = is7card ? '7CARD' : 'ESX';
                showGatewayConfirmationModal(provider, function() {
                    runValidation();
                }, function() {
                    showValidationModal('Nicio problemă 🤝 Nu am validat această scanare ' + provider + '. Când ești gata, poți încerca din nou.', false);
                });
                return;
            }

            runValidation();
        }

        function showGatewayConfirmationModal(provider, onConfirm, onCancel) {
            const modal = document.createElement('div');
            modal.className = 'oc-validation-modal';
            modal.innerHTML = '<div class=\"oc-validation-modal-content oc-validation-modal-content-gateway\">' +
                '<div class=\"oc-validation-modal-badge\"><span class=\"dashicons dashicons-smartphone\" aria-hidden=\"true\"></span></div>' +
                '<h3 class=\"oc-validation-title\">Confirmă validarea</h3>' +
                '<div class=\"oc-validation-message\">' +
                    'Super! Ai scanat <strong>' + provider + '</strong>.<br>' +
                    'Confirmi validarea accesului pentru această intrare?' +
                '</div>' +
                '<div class=\"oc-validation-actions\">' +
                    '<button class=\"oc-validation-yes button button-primary\">Da, validează</button>' +
                    '<button class=\"oc-validation-no button\">Nu acum</button>' +
                '</div>' +
                '</div>';

            document.body.appendChild(modal);

            const closeModal = () => modal.remove();
            modal.querySelector('.oc-validation-yes').onclick = function() {
                closeModal();
                if (typeof onConfirm === 'function') onConfirm();
            };
            modal.querySelector('.oc-validation-no').onclick = function() {
                closeModal();
                if (typeof onCancel === 'function') onCancel();
            };
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeModal();
                    if (typeof onCancel === 'function') onCancel();
                }
            };
        }
        </script>";
    }
    /**
     * Renderează formular pentru REÎNNOIRE/Adăugare abonament la utilizator EXISTENT
     * IDENTIC cu formularul de creare client nou, dar pentru user existent
     */
    private function render_renew_subscription_form(array $member): string {
        $pool_packages = $this->get_all_pool_packages();
        $user_id = $member['user_id'];
        $default_duration_days = $this->get_default_membership_duration_days();
        $default_activation_date = oc_membership_current_business_date();
        $default_activation_display = $this->format_date_european($default_activation_date);
        $default_expiration_iso = $this->add_days_to_iso_date_wp($default_activation_date, $default_duration_days) ?? $default_activation_date;
        $default_expiration = $this->format_date_european($default_expiration_iso);
        
        ob_start();
        ?>
        <div class="oc-renew-subscription-form oc-form-shell oc-form-shell-renew">
            <div class="oc-form-header oc-form-header-inline">
                <div class="oc-form-hero-copy">
                    <span class="oc-form-kicker"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Abonament nou</span>
                    <h3 class="oc-renew-title"><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span> Adaugă Abonament Nou</h3>
                    <p class="oc-form-subtitle">Completează câmpurile de mai jos pentru a adăuga un abonament nou pentru <strong><?php echo esc_html($member['display_name']); ?></strong>.</p>
                </div>
                <button type="button" class="oc-btn-cancel-renew oc-form-hero-close" aria-label="Închide formularul de abonament">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
            
            <div class="oc-renew-form" data-user-id="<?php echo esc_attr($user_id); ?>" data-duration-days="<?php echo esc_attr($default_duration_days); ?>">
                <!-- Section: Selectare Abonament -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span><span>Selectare Abonament</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field oc-field-full">
                            <label>Pachet <span class="required">*</span></label>
                            <select name="package_id" required class="oc-renew-package-select" data-user-id="<?php echo esc_attr($user_id); ?>">
                                <option value="">-- Selectează pachet --</option>
                                <?php foreach ($pool_packages as $package): ?>
                                <option value="<?php echo esc_attr($package->ID); ?>" 
                                        data-price="<?php echo esc_attr($package->price); ?>"
                                        data-duration-days="<?php echo esc_attr(max(1, intval($package->duration_days ?? $default_duration_days))); ?>">
                                    <?php echo esc_html($package->post_title); ?> - <?php echo wc_price($package->price); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="oc-form-field oc-field-full oc-renew-courses-container" style="display:none;">
                            <label>Cursuri <span class="required">*</span></label>
                            <div class="oc-courses-grid oc-renew-course-selections"></div>
                        </div>
                        <div class="oc-form-field">
                            <label>Activ de la <span class="required">*</span></label>
                            <input type="text" name="activation_date" required
                                class="oc-wp-date-input"
                                inputmode="numeric"
                                autocomplete="off"
                                value="<?php echo esc_attr($default_activation_display); ?>"
                                placeholder="<?php echo esc_attr(get_option('date_format')); ?>">
                            <small>Alege data de început.</small>
                        </div>
                        <div class="oc-form-field">
                            <label>Expiră la</label>
                            <input type="text" name="expiration_date"
                                class="oc-wp-date-input"
                                inputmode="numeric"
                                autocomplete="off"
                                data-duration-days="<?php echo esc_attr($default_duration_days); ?>"
                                value="<?php echo esc_attr($default_expiration); ?>"
                                placeholder="<?php echo esc_attr(get_option('date_format')); ?>">
                            <small>Se completează automat, dar o poți schimba.</small>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Section: Date Comandă -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-cart" aria-hidden="true"></span><span>Date Comandă</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field">
                            <label>Status Plată <span class="required">*</span></label>
                            <select name="payment_status" required>
                                <option value="paid">✅ &nbsp; Plătit</option>
                                <option value="unpaid">⏳ &nbsp; Neplătit</option>
                            </select>
                        </div>
                        <div class="oc-form-field">
                            <label>Metodă Plată</label>
                            <select name="payment_method">
                                <?php foreach ($this->get_available_payment_methods() as $method_key => $method_label): ?>
                                    <option value="<?php echo esc_attr($method_key); ?>" <?php selected($method_key, 'cash'); ?>>
                                        <?php echo esc_html($method_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="oc-form-field">
                            <label>Preț abonament</label>
                            <input type="number" name="product_price"
                                   id="renew-price-<?php echo esc_attr($user_id); ?>"
                                   class="oc-renew-price-input"
                                   step="0.01" min="0"
                                   value="0">
                            <small>Se completează automat după pachet. Poți modifica dacă este nevoie.</small>
                        </div>
                        <div class="oc-form-field oc-field-full">
                            <label class="oc-send-email-label">
                                <input type="checkbox" name="send_email" value="1" checked class="oc-send-email-checkbox"> 
                                Trimite email confirmare către client
                            </label>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Action Buttons -->
                <div class="oc-form-actions-bottom">
                    <button type="button" class="button button-primary button-large oc-btn-submit-renew">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        Adaugă Abonament
                    </button>
                    <button type="button" class="button button-large oc-btn-cancel-renew">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                        Anulează
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_add_client_form(): string {
        $pool_packages = $this->get_all_pool_packages();
        $default_duration_days = $this->get_default_membership_duration_days();
        $default_activation_date = oc_membership_current_business_date();
        $default_activation_display = $this->format_date_european($default_activation_date);
        $default_expiration_iso = $this->add_days_to_iso_date_wp($default_activation_date, $default_duration_days) ?? $default_activation_date;
        $default_expiration = $this->format_date_european($default_expiration_iso);
        
        ob_start();
        ?>
        <div class="oc-add-client-form oc-form-shell oc-form-shell-client">
            <div class="oc-form-header">
                <div class="oc-form-hero-copy">
                    <span class="oc-form-kicker"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Client nou</span>
                    <h3><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> Creare Client Nou</h3>
                    <p class="oc-form-subtitle">Completează datele de bază și alege abonamentul potrivit.</p>
                </div>
                <button type="button" class="oc-btn-close-form oc-form-hero-close" aria-label="Închide formularul client nou"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
            </div>
            
            <form id="oc-new-client-form" data-duration-days="<?php echo esc_attr($default_duration_days); ?>">
                <!-- Section 1: Date Client -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span>Date Client</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field">
                            <label>Prenume <span class="required">*</span></label>
                            <input type="text" name="first_name" required placeholder="Ex: Ion">
                        </div>
                        <div class="oc-form-field">
                            <label>Nume <span class="required">*</span></label>
                            <input type="text" name="last_name" required placeholder="Ex: Popescu">
                        </div>
                        <div class="oc-form-field">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="Ex: ion@example.com">
                        </div>
                        <div class="oc-form-field">
                            <label>Telefon <span class="required">*</span></label>
                            <input type="tel" name="phone" required placeholder="Ex: 0712345678">
                        </div>
                        <div class="oc-form-field">
                            <label>Parolă (opțional)</label>
                            <input type="password" name="password" placeholder="Lasă gol dacă vrei să o generăm automat">
                            <small>Dacă nu completezi parola, o generăm noi.</small>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Section 2: Selectare Abonament -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span><span>Selectare Abonament</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field oc-field-full">
                            <label>Pachet <span class="required">*</span></label>
                            <select id="new-client-package" name="package_id" required class="oc-new-package-select">
                                <option value="">-- Selectează pachet --</option>
                                <?php foreach ($pool_packages as $package): ?>
                                <option value="<?php echo esc_attr($package->ID); ?>" 
                                        data-price="<?php echo esc_attr($package->price); ?>"
                                        data-duration-days="<?php echo esc_attr(max(1, intval($package->duration_days ?? $default_duration_days))); ?>">
                                    <?php echo esc_html($package->post_title); ?> - <?php echo wc_price($package->price); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="oc-form-field oc-field-full" id="oc-new-courses-container" style="display:none;">
                            <label>Cursuri <span class="required">*</span></label>
                            <div class="oc-courses-grid" id="oc-new-course-selections"></div>
                        </div>
                        <div class="oc-form-field">
                            <label>Activ de la <span class="required">*</span></label>
                            <input type="text" name="activation_date" required
                                class="oc-wp-date-input"
                                inputmode="numeric"
                                autocomplete="off"
                                value="<?php echo esc_attr($default_activation_display); ?>"
                                placeholder="<?php echo esc_attr(get_option('date_format')); ?>">
                            <small>Alege data de început.</small>
                        </div>
                        <div class="oc-form-field">
                            <label>Expiră la</label>
                           <input type="text" name="expiration_date"
                               class="oc-wp-date-input"
                               inputmode="numeric"
                               autocomplete="off"
                               data-duration-days="<?php echo esc_attr($default_duration_days); ?>"
                               value="<?php echo esc_attr($default_expiration); ?>"
                               placeholder="<?php echo esc_attr(get_option('date_format')); ?>">
                            <small>Se completează automat, dar o poți schimba.</small>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Section 2.5: Date Facturare (Opționale) -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-media-document" aria-hidden="true"></span><span>Date Facturare</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field">
                            <label>Prenume Facturare</label>
                            <input type="text" name="billing_first_name" placeholder="Prenume">
                        </div>
                        <div class="oc-form-field">
                            <label>Nume Facturare</label>
                            <input type="text" name="billing_last_name" placeholder="Nume">
                        </div>
                        <div class="oc-form-field oc-field-full">
                            <label>Adresă Facturare</label>
                            <input type="text" name="billing_address_1" placeholder="Strada, numărul">
                        </div>
                        <div class="oc-form-field">
                            <label>Oraș</label>
                            <input type="text" name="billing_city" placeholder="Oraș">
                        </div>
                        <div class="oc-form-field">
                            <label>Județ</label>
                            <input type="text" name="billing_state" placeholder="Județ">
                        </div>
                        <div class="oc-form-field">
                            <label>Cod Poștal</label>
                            <input type="text" name="billing_postcode" placeholder="Cod poștal">
                        </div>
                        <div class="oc-form-field">
                            <label>Țară</label>
                            <select name="billing_country">
                                <option value="RO">România</option>
                                <option value="MD">Moldova</option>
                                <option value="BG">Bulgaria</option>
                                <option value="HU">Ungaria</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Section 3: Date Comandă -->
                <fieldset class="oc-form-section">
                    <legend><span class="dashicons dashicons-cart" aria-hidden="true"></span><span>Date Comandă</span></legend>
                    <div class="oc-form-grid">
                        <div class="oc-form-field">
                            <label>Status Plată <span class="required">*</span></label>
                            <select name="payment_status" required>
                                <option value="paid">✅ Plătit</option>
                                <option value="unpaid">⏳ Neplătit</option>
                            </select>
                        </div>
                        <div class="oc-form-field">
                            <label>Metodă Plată</label>
                            <select name="payment_method">
                                <?php foreach ($this->get_available_payment_methods() as $method_key => $method_label): ?>
                                    <option value="<?php echo esc_attr($method_key); ?>" <?php selected($method_key, 'cash'); ?>>
                                        <?php echo esc_html($method_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="oc-form-field">
                            <label>Preț abonament</label>
                            <input type="number" name="product_price"
                                   id="new-client-price"
                                   step="0.01" min="0"
                                   value="0">
                            <small>Se completează automat după pachet. Poți modifica dacă este nevoie.</small>
                        </div>
                        <div class="oc-form-field oc-field-full">
                            <label>Observații</label>
                            <textarea name="observations" rows="3" placeholder="Ex: coplată, preferințe sau un mesaj scurt"></textarea>
                            <small>Opțional. Poți nota aici orice detaliu util.</small>
                        </div>
                        <div class="oc-form-field oc-field-full">
                            <label class="oc-send-email-label">
                                <input type="checkbox" name="send_email" value="1" checked class="oc-send-email-checkbox"> 
                                Trimite email confirmare către client
                            </label>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Action Buttons -->
                <div class="oc-form-actions-bottom">
                    <button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Creează client și comandă</button>
                    <button type="button" class="button button-large oc-btn-close-form"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span> Anulează</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Renderează lista de cursuri pentru TOȚI utilizatorii (activi, expirați, guest)
     * UNIFICATE - ACEEAȘI logică pentru TOȚI
     * 
     * @param mixed $user_id ID utilizator (poate fi numeric sau "guest_X")
     * @param int $membership_id ID membership specific (pentru guest users)
     * @param bool $is_admin Dacă utilizatorul curent este admin
     * @return string HTML cu listă cursuri
     */
    private function render_editable_courses_list_with_sessions($user_id, $membership_id = 0, bool $is_admin = true): string {
        global $wpdb;
        
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return '<div class="oc-error-message"><em>Eroare încărcare date</em></div>';
        }
        
        $db = $validator->get_db();
        $table = $db->get_table_name('membership_validations');
        $has_observations_column = (bool) $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table} LIKE %s",
            'observations'
        ));
        $observations_select = $has_observations_column ? ', m.observations' : ", '' AS observations";
        
        // Determină dacă e guest user
        $is_guest = (!is_numeric($user_id) || $user_id == 0);
        $real_user_id = $is_guest ? 0 : absint($user_id);
        
        // Obține TOATE membership-urile (diferit pentru guest vs utilizatori reali)
        // EXCLUDE cursurile din comenzi anulate (wc-cancelled)
        $posts_table = $wpdb->prefix . 'posts';
        
        if ($is_guest && $membership_id > 0) {
            // Guest: ia doar membership-ul specific (exclude comenzi anulate)
            $all_memberships = $wpdb->get_results($wpdb->prepare("
                SELECT m.id, m.order_id, m.product_id, m.product_price, m.product_name, m.variation_id, m.sessions_allocated, m.used_sessions, m.remaining_sessions, m.is_unlimited, m.payment_method, m.validation_status, m.expiration_date, m.start_date, m.last_validation_date, m.created_at, m.duration_days {$observations_select}
                FROM {$table} m
                LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                WHERE m.user_id = 0 AND m.id = %d
                AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
                ORDER BY m.id DESC
            ", $membership_id), ARRAY_A);
        } else {
            // Utilizatori reali: ia toate membership-urile user-ului (exclude comenzi anulate)
            $all_memberships = $wpdb->get_results($wpdb->prepare("
                SELECT m.id, m.order_id, m.product_id, m.product_price, m.product_name, m.variation_id, m.sessions_allocated, m.used_sessions, m.remaining_sessions, m.is_unlimited, m.payment_method, m.validation_status, m.expiration_date, m.start_date, m.last_validation_date, m.created_at, m.duration_days {$observations_select}
                FROM {$table} m
                LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                WHERE m.user_id = %d
                AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
                ORDER BY m.id DESC
            ", $real_user_id), ARRAY_A);
        }
        
        if (empty($all_memberships)) {
            return '<div class="oc-empty-message"><em>Nici un curs găsit pentru acest utilizator</em></div>';
        }

        // Display-only fallback: dacă last_validation_date lipsește din membership_validations,
        // folosim ultima validare reușită din audit log pentru a evita afișarea N/A.
        $fallback_last_validation_dates = $this->get_latest_success_validation_dates(array_column($all_memberships, 'id'));
        
        // 🎯 GRUPARE PE PACHETE (order_id)
        $packages = [];
        foreach ($all_memberships as $membership) {
            $order_id = $membership['order_id'] ?? 0;
            if (!isset($packages[$order_id])) {
                $packages[$order_id] = [
                    'order_id' => $order_id,
                    'courses' => [],
                    'created_at' => $membership['created_at'] ?? null
                ];
            }
            $packages[$order_id]['courses'][] = $membership;
        }
        
        // 🎯 SORTARE INTELIGENTĂ:
        // 1. ACTIVE primele (cele vechi sus, cele noi jos)
        // 2. PENDING ultimele (sub toate ACTIVE)
        usort($packages, function($a, $b) {
            // Determină status pachet: ACTIVE dacă are măcar un curs ACTIVE, altfel PENDING
            $a_has_active = false;
            foreach ($a['courses'] as $course) {
                if (($course['validation_status'] ?? '') === 'active') {
                    $a_has_active = true;
                    break;
                }
            }
            
            $b_has_active = false;
            foreach ($b['courses'] as $course) {
                if (($course['validation_status'] ?? '') === 'active') {
                    $b_has_active = true;
                    break;
                }
            }
            
            // Prioritate 1: ACTIVE înainte de PENDING
            if ($a_has_active && !$b_has_active) return -1; // A active, B pending → A înainte
            if (!$a_has_active && $b_has_active) return 1;  // A pending, B active → B înainte
            
            // Prioritate 2: În cadrul aceleiași categorii, cele vechi ÎNAINTE de cele noi (ASC)
            return strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0');
        });
        
        // 🎯 Construiește lista HTML EDITABILĂ - GRUPAT PE PACHETE (FĂRĂ inline styles)
        $html = '<div class="oc-courses-expandedlist">';
        
        $today = oc_membership_current_business_date();
        $package_counter = 1;
        $active_packages_total_price = 0.0;
        $active_packages_count = 0;
        $has_active_or_pending_packages = false;

        foreach ($packages as $pkg_for_visibility) {
            $pkg_courses = $pkg_for_visibility['courses'] ?? [];
            foreach ($pkg_courses as $pkg_course_for_visibility) {
                $pkg_status_for_visibility = (string) ($pkg_course_for_visibility['validation_status'] ?? '');
                if (in_array($pkg_status_for_visibility, ['active', 'pending'], true)) {
                    $has_active_or_pending_packages = true;
                    break 2;
                }
            }
        }
        
        // Iterare prin fiecare PACHET (order)
        foreach ($packages as $package) {
            $order_id = $package['order_id'];
            $courses = $package['courses'];

            $resolve_course_status = function(array $course): string {
                $payment_key = $this->normalize_payment_method_key((string) ($course['payment_method'] ?? ''));
                $gateway_copayment = $this->is_gateway_copayment_context(
                    (int) ($course['order_id'] ?? 0),
                    $payment_key,
                    (float) ($course['product_price'] ?? 0)
                );
                $is_unlimited = (((int) ($course['is_unlimited'] ?? 0) === 1) && !$gateway_copayment)
                    || ($this->is_gateway_payment_method($payment_key) && !$gateway_copayment)
                    || (int) ($course['sessions_allocated'] ?? 0) >= OC_UNLIMITED_SESSIONS;

                return $this->get_effective_membership_status(
                    (string) ($course['validation_status'] ?? 'active'),
                    (string) ($course['expiration_date'] ?? ''),
                    $is_unlimited && empty($course['expiration_date']),
                    isset($course['remaining_sessions']) ? (int) $course['remaining_sessions'] : null
                );
            };
            
            // Obține informații despre comandă
            $order = wc_get_order($order_id);
            $package_name = 'Abonament #' . $order_id;
            $package_product_id = 0;

            // Fallback profesional: folosește numele cached din membership_validations
            foreach ($courses as $course) {
                if (!empty($course['product_name']) && $course['product_name'] !== 'N/A') {
                    $package_name = $course['product_name'];
                    break;
                }
            }
            
            if ($order) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_variation_id() == 0 && $item->get_total() > 0) {
                        $product = $item->get_product();
                        if ($product) {
                            $package_name = $product->get_name();
                            $package_product_id = $product->get_id();
                            break;
                        }
                    }
                }

                // Pentru comenzi cu total 0: caută și produs simplu principal fără condiția de total
                if (strpos($package_name, 'Abonament #') === 0) {
                    foreach ($order->get_items() as $item) {
                        if ($item->get_variation_id() == 0) {
                            $product = $item->get_product();
                            if ($product) {
                                $package_name = $product->get_name();
                                $package_product_id = $product->get_id();
                                break;
                            }
                        }
                    }
                }
            }

            if ($package_product_id <= 0 && !empty($courses[0]['product_id'])) {
                $package_product_id = (int) $courses[0]['product_id'];
            }

            $package_is_vip_pool = ($package_product_id > 0)
                && get_post_meta($package_product_id, '_oc_pool_is_unlimited', true) === 'yes';
            
            // 🎯 Determină STATUS-ul PACHETULUI
            $all_active = true;
            $all_pending = true;
            $all_expired = true;
            $earliest_start = null;
            $latest_expiry = null;
            $package_duration_days = 28;
            $package_has_unlimited_sessions = false;
            $package_has_gateway_unlimited = false;
            
            foreach ($courses as $course) {
                $status = $resolve_course_status($course);
                $start_date = $course['start_date'] ?? null;
                $expiry_date = $course['expiration_date'] ?? null;
                $course_payment = function_exists('mb_strtolower')
                    ? mb_strtolower(trim((string) ($course['payment_method'] ?? '')), 'UTF-8')
                    : strtolower(trim((string) ($course['payment_method'] ?? '')));
                $course_payment_key = $this->normalize_payment_method_key((string) ($course['payment_method'] ?? ''));
                $course_gateway_copayment = $this->is_gateway_copayment_context(
                    (int) ($course['order_id'] ?? $order_id),
                    $course_payment_key,
                    (float) ($course['product_price'] ?? 0)
                );
                $course_has_unlimited_sessions = ((int) ($course['is_unlimited'] ?? 0) === 1 && !$course_gateway_copayment)
                    || ($this->is_gateway_payment_method($course_payment_key) && !$course_gateway_copayment)
                    || (int) ($course['sessions_allocated'] ?? 0) >= OC_UNLIMITED_SESSIONS
                    || ($package_is_vip_pool && !$course_gateway_copayment);

                $course_is_gateway_unlimited = $this->is_gateway_payment_method($course_payment_key) && !$course_gateway_copayment;

                if ($course_is_gateway_unlimited) {
                    $package_has_gateway_unlimited = true;
                }

                if ($course_has_unlimited_sessions) {
                    $package_has_unlimited_sessions = true;
                }
                
                if ($status !== 'active') $all_active = false;
                if ($status !== 'pending') $all_pending = false;
                if ($status !== 'expired') $all_expired = false;
                
                if ($start_date && (!$earliest_start || $start_date < $earliest_start)) {
                    $earliest_start = $start_date;
                }
                if ($expiry_date && (!$latest_expiry || $expiry_date > $latest_expiry)) {
                    $latest_expiry = $expiry_date;
                }

                if (!empty($course['duration_days']) && intval($course['duration_days']) > 0) {
                    $package_duration_days = intval($course['duration_days']);
                }
            }

            $package_price = 0.0;
            foreach ($courses as $course) {
                $course_price = (float) ($course['product_price'] ?? 0);
                if ($course_price > $package_price) {
                    $package_price = $course_price;
                }
            }

            if ($package_is_vip_pool) {
                $package_has_unlimited_sessions = true;
            }
            
            // Stabilește status pachet
            if ($all_expired) {
                $package_status = 'expired';
                $package_status_text = '✕ Expirat';
                $package_status_color = '#dc3545';
                $border_color = '#dc3545';
            } else            if ($all_pending) {
                $package_status = 'pending';
                $package_status_text = '⏳ În așteptare';
                $package_status_color = '#0073aa';
                $border_color = '#0073aa';
            } elseif ($all_active) {
                $package_status = 'active';
                $package_status_text = '✓ Activ';
                $package_status_color = '#28a745';
                $border_color = '#28a745';
            } else {
                // Mixt (active + pending/expired)
                $package_status = 'mixed';
                $package_status_text = '🔀 Mixt';
                $package_status_color = '#17a2b8';
                $border_color = '#17a2b8';
            }

            // Dacă există pachete active/pending, cele expirate rămân doar în istoric.
            // Dacă NU există active/pending, afișăm pachetele expirate aici pentru context imediat.
            if ($has_active_or_pending_packages) {
                if (!in_array($package_status, ['active', 'pending'], true)) {
                    continue;
                }
            } elseif (!in_array($package_status, ['active', 'pending', 'expired'], true)) {
                continue;
            }

            if ($package_status === 'active') {
                $active_packages_total_price += $package_price;
                $active_packages_count++;
            }

            $package_badge_status_class = in_array($package_status, ['active', 'pending', 'expired'], true)
                ? $package_status
                : 'active';
            
            // Important: nu filtrăm/ascundem pachetele după status,
            // pentru a evita dispariția cardurilor în cazuri mixte/istorice.
            
            // Renderează PACHET (FĂRĂ inline styles - folosim clase CSS)
            $html .= '<div class="oc-package-section oc-package-' . $package_status . '" data-order-id="' . esc_attr($order_id) . '" data-border-color="' . esc_attr($border_color) . '" style="border-color: ' . $border_color . ';">';
            
            // Header pachet (FĂRĂ inline styles - toate în CSS)
            $html .= '<div class="oc-package-header">';
            $html .= '<div class="oc-package-title-wrap">';
            $html .= '<h4 class="oc-package-title">🎫 ' . esc_html($package_name) . '</h4>';
            $html .= '<small class="oc-package-order">Comandă #' . $order_id . '</small>';
            $html .= '</div>';
            $html .= '<div class="oc-package-status-wrap">';
            $html .= '<span class="oc-status-badge status-' . esc_attr($package_badge_status_class) . '">' . esc_html($package_status_text) . '</span>';
            $package_payment_key = '';
            foreach ($courses as $course_payment_row) {
                $candidate_payment_key = $this->normalize_payment_method_key((string) ($course_payment_row['payment_method'] ?? ''));
                if ($candidate_payment_key !== '') {
                    $package_payment_key = $candidate_payment_key;
                    break;
                }
            }
            $payment_methods = $this->get_available_payment_methods();
            $package_payment_label = $payment_methods[$package_payment_key] ?? 'Necunoscut';
            $package_payment_status = 'unpaid';
            $package_observations = '';
            foreach ($courses as $course_payment_status_row) {
                $candidate_payment_status = sanitize_text_field((string) ($course_payment_status_row['payment_status'] ?? ''));
                if ($candidate_payment_status !== '') {
                    $package_payment_status = $candidate_payment_status;
                    break;
                }
            }
            foreach ($courses as $course_observation_row) {
                $candidate_observation = trim((string) ($course_observation_row['observations'] ?? ''));
                if ($candidate_observation !== '') {
                    $package_observations = $candidate_observation;
                    break;
                }
            }

            // Sursa de adevăr pentru status plată este comanda WooCommerce când există order_id.
            if ($order_id > 0) {
                $package_order = wc_get_order($order_id);
                if ($package_order) {
                    $woo_status = (string) $package_order->get_status();
                    $requested_payment_status = sanitize_key((string) $package_order->get_meta('_oc_requested_payment_status'));
                    if (in_array($requested_payment_status, ['paid', 'unpaid', 'partial'], true)) {
                        $package_payment_status = $requested_payment_status;
                    } elseif ($package_order->is_paid()) {
                        $package_payment_status = 'paid';
                    } else {
                        $woo_to_payment_status = [
                            'completed' => 'paid',
                            'processing' => 'paid',
                            'pending' => 'unpaid',
                            'on-hold' => 'partial',
                            'failed' => 'unpaid',
                            'cancelled' => 'unpaid',
                            'refunded' => 'partial',
                        ];
                        if (isset($woo_to_payment_status[$woo_status])) {
                            $package_payment_status = $woo_to_payment_status[$woo_status];
                        }
                    }
                }
            }

            $payment_status_labels = [
                'paid' => 'Achitat',
                'unpaid' => 'Neachitat',
                'partial' => 'Parțial',
            ];
            if (!isset($payment_status_labels[$package_payment_status])) {
                $package_payment_status = 'unpaid';
            }
            $package_payment_status_label = $payment_status_labels[$package_payment_status];

            $package_start_text = $earliest_start ? $this->format_date_european($earliest_start) : '';
            $valid_until_text = $latest_expiry ? $this->format_date_european($latest_expiry) : 'Fără expirare';
            if ($package_status === 'pending' && $package_start_text !== '' && $package_start_text !== 'N/A') {
                $html .= '<div class="oc-package-date">Activ de la: <strong>' . esc_html($package_start_text) . '</strong></div>';
            }
            $html .= '<div class="oc-package-date">Valabil până la: <strong>' . esc_html($valid_until_text) . '</strong></div>';
            if (!empty($package['created_at'])) {
                $html .= '<div class="oc-package-date">Achiziționat la: <strong>' . esc_html($this->format_date_european($package['created_at'])) . '</strong></div>';
            }

            // Închidem zona din dreapta sus aici: badge + date.
            $html .= '</div>';
            $html .= '</div>';

            if ($is_admin) {
                $payment_methods = $this->get_available_payment_methods();
                $pending_meta_editable = ($package_status === 'pending');
                $meta_field_class = $pending_meta_editable ? 'oc-field-editable' : 'oc-field-readonly';
                $meta_field_disabled_attr = $pending_meta_editable ? '' : ' disabled';
                $html .= '<div class="oc-package-financial-fields">';
                $html .= '<div class="oc-package-financial-item">';
                $html .= '<label for="package-price-' . esc_attr($order_id) . '">Preț abonament</label>';
                $html .= '<input type="number" id="package-price-' . esc_attr($order_id) . '" name="package_product_price" class="oc-package-meta-field ' . esc_attr($meta_field_class) . '" data-field-type="number" data-original-value="' . esc_attr(number_format($package_price, 2, '.', '')) . '" value="' . esc_attr(number_format($package_price, 2, '.', '')) . '" step="0.01" min="0"' . $meta_field_disabled_attr . '>';
                $html .= '</div>';
                $html .= '<div class="oc-package-financial-item">';
                $html .= '<label for="package-payment-' . esc_attr($order_id) . '">Modalitate de plată</label>';
                $html .= '<select id="package-payment-' . esc_attr($order_id) . '" name="package_payment_method" class="oc-package-meta-field ' . esc_attr($meta_field_class) . '" data-field-type="select" data-original-value="' . esc_attr($package_payment_key) . '"' . $meta_field_disabled_attr . '>';
                foreach ($payment_methods as $method_key => $method_label) {
                    $selected = selected($package_payment_key, $method_key, false);
                    $html .= '<option value="' . esc_attr($method_key) . '" ' . $selected . '>' . esc_html($method_label) . '</option>';
                }
                $html .= '</select>';
                $html .= '</div>';
                $html .= '<div class="oc-package-financial-item">';
                $html .= '<label for="package-payment-status-' . esc_attr($order_id) . '">Status plată</label>';
                $html .= '<select id="package-payment-status-' . esc_attr($order_id) . '" name="package_payment_status" class="oc-package-meta-field ' . esc_attr($meta_field_class) . '" data-field-type="select" data-original-value="' . esc_attr($package_payment_status) . '"' . $meta_field_disabled_attr . '>';
                foreach ($payment_status_labels as $status_key => $status_label) {
                    $selected = selected($package_payment_status, $status_key, false);
                    $html .= '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                }
                $html .= '</select>';
                $html .= '</div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="oc-package-price">Preț pachet: <strong>' . esc_html(number_format($package_price, 2, '.', '')) . ' lei</strong></div>';
                $html .= '<div class="oc-package-payment">Modalitate de plată: <strong>' . esc_html($package_payment_label) . '</strong></div>';
                $html .= '<div class="oc-package-payment">Status plată: <strong>' . esc_html($package_payment_status_label) . '</strong></div>';
                if ($package_observations !== '') {
                    $html .= '<div class="oc-package-payment">Observații: <strong>' . esc_html($package_observations) . '</strong></div>';
                }
            }
            
            // Cursuri din pachet
            $html .= '<div class="oc-package-courses">';
            $html .= '<div class="oc-package-courses-label">Cursuri incluse</div>';

            if ($package_has_unlimited_sessions && !$package_has_gateway_unlimited && $package_status !== 'pending') {
                $html .= '<div class="oc-vip-unlimited-notice">';
                $html .= '<strong class="oc-vip-text">';
                $html .= '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ';
                $html .= 'Acces nelimitat la toate cursurile din abonamentul activ.';
                $html .= '</strong>';
                $html .= '</div>';
            } else {
                $course_counter = 1;

                foreach ($courses as $course) {
                $membership_id = $course['id'];
                $variation_id = $course['variation_id'];
                $course_status = $resolve_course_status($course);
                $course_created_date = $course['created_at'] ?? null;
                $course_expiry_date = $course['expiration_date'] ?? null;
                
                // Nume curs
                $course_product = wc_get_product($variation_id);
                if (!$course_product) continue;
            
                $course_name = $course_product->get_name();
                $sessions_allocated = (int)$course['sessions_allocated'];
                $sessions_remaining = (int)$course['remaining_sessions'];
                $sessions_used = (int)$course['used_sessions'];
                $course_parent_id = method_exists($course_product, 'get_parent_id') ? (int) $course_product->get_parent_id() : 0;
                $course_config = $this->validator_db->get_course_hours_config($variation_id);
                $course_default_sessions = $course_config ? (int) ($course_config['sessions_per_month'] ?? 0) : 0;
                if ($course_default_sessions <= 0) {
                    $course_default_sessions = (int) get_post_meta($variation_id, '_oc_pool_sessions', true);
                }
                if ($course_default_sessions <= 0 && $course_parent_id > 0) {
                    $course_default_sessions = (int) get_post_meta($course_parent_id, '_oc_pool_sessions', true);
                }
                if ($course_default_sessions <= 0) {
                    $course_default_sessions = 8;
                }
                $course_payment = function_exists('mb_strtolower')
                    ? mb_strtolower(trim((string) ($course['payment_method'] ?? '')), 'UTF-8')
                    : strtolower(trim((string) ($course['payment_method'] ?? '')));
                $course_payment_key = $this->normalize_payment_method_key((string) ($course['payment_method'] ?? ''));
                $course_gateway_copayment = $this->is_gateway_copayment_context(
                    (int) ($course['order_id'] ?? $order_id),
                    $course_payment_key,
                    (float) ($course['product_price'] ?? 0)
                );
                $is_unlimited_course = ((int)($course['is_unlimited'] ?? 0) === 1 && !$course_gateway_copayment)
                    || ($this->is_gateway_payment_method($course_payment_key) && !$course_gateway_copayment)
                    || $sessions_allocated >= OC_UNLIMITED_SESSIONS
                    || ($package_is_vip_pool && !$course_gateway_copayment);
                $allocated_display = $is_unlimited_course ? 'Nelimitat' : (string) $sessions_allocated;
                $remaining_display = $is_unlimited_course ? 'Nelimitat' : (string) $sessions_remaining;
                $raw_last_validation = trim((string) ($course['last_validation_date'] ?? ''));
                if ($raw_last_validation === '' || $raw_last_validation === '0000-00-00' || $raw_last_validation === '0000-00-00 00:00:00') {
                    $raw_last_validation = (string) ($fallback_last_validation_dates[(int) $membership_id] ?? '');
                }
                $last_validation = $raw_last_validation !== ''
                    ? $this->format_date_european($raw_last_validation)
                    : 'Niciodată';
                if ($last_validation === 'N/A') {
                    $last_validation = 'Niciodată';
                }
                
                // Determină culoarea border-ului pe bază de status curs
                if ($course_status === 'pending') {
                    $course_border_color = '#0073aa'; // Albastru
                    $course_status_badge = '<span class="oc-status-badge status-pending">⏳ În așteptare</span>';
                } elseif ($course_status === 'expired') {
                    $course_border_color = '#dc3545'; // Roșu
                    $course_status_badge = '<span class="oc-status-badge status-expired">✕ Expirat</span>';
                } else {
                    $course_border_color = '#28a745'; // Verde
                    $course_status_badge = '<span class="oc-status-badge status-active">✓ Activ</span>';
                }
            
                $html .= '<div class="oc-course-entry" style="border-left-color: ' . $course_border_color . ';" data-default-allocated="' . esc_attr($course_default_sessions) . '" data-unlimited-value="' . esc_attr((int) OC_UNLIMITED_SESSIONS) . '">';
                
                // Header curs cu status și date (FĂRĂ inline styles)
                $html .= '<div class="oc-course-header">';
                $html .= '<div class="oc-course-title-wrap">';
                $html .= '<label class="oc-course-label">' . $course_counter . '. ' . esc_html($course_name) . '</label>';
                $html .= $course_status_badge;
                $html .= '</div>';
                $html .= '<div class="oc-course-date-wrap">';
                if ($course_status === 'pending') {
                    $pending_date_bits = [];
                    if (!empty($start_date)) {
                        $pending_date_bits[] = '🚀 Activ de la: <strong>' . $this->format_date_european($start_date) . '</strong>';
                    }
                    if ($course_created_date) {
                        $pending_date_bits[] = '🛒 Achiziționat: <strong>' . $this->format_date_european($course_created_date) . '</strong>';
                    }
                    $html .= implode('<br>', $pending_date_bits);
                } elseif ($course_expiry_date) {
                    $html .= '📅 Expiră: <strong>' . $this->format_date_european($course_expiry_date) . '</strong>';
                }
                $html .= '</div>';
                $html .= '</div>';
                
                // Grid cu informații curs (RESPONSIVE cu clase CSS - FĂRĂ inline styles)
                $html .= '<div class="oc-course-stats-grid">';
                $course_can_edit_sessions = $is_admin && in_array($course_status, ['active', 'pending'], true);
                $show_course_inputs_by_default = $course_can_edit_sessions && $course_status === 'pending';
                $course_input_class = $show_course_inputs_by_default ? 'oc-field-editable' : 'oc-field-readonly';
                $course_input_disabled_attr = $show_course_inputs_by_default ? '' : ' disabled';
                $course_input_style = $show_course_inputs_by_default ? '' : ' style="display:none;"';
                $course_stat_style = $show_course_inputs_by_default ? ' style="display:none;"' : '';

                // Incluse
                $html .= '<div class="oc-stat-item">';
                $html .= '<label class="oc-stat-label">📥 Incluse</label>';
                $html .= '<div class="oc-stat-value oc-stat-allocated oc-stat-display"' . $course_stat_style . '>' . esc_html($allocated_display) . '</div>';
                if ($course_can_edit_sessions) {
                    $html .= '<input type="number" class="oc-course-session-input ' . esc_attr($course_input_class) . '" data-field-type="number" data-membership-id="' . esc_attr($membership_id) . '" data-field-name="sessions_allocated" value="' . esc_attr((int) $sessions_allocated) . '" min="0" step="1"' . $course_input_disabled_attr . $course_input_style . '>';
                }
                $html .= '</div>';

                // Rămase
                $html .= '<div class="oc-stat-item">';
                $html .= '<label class="oc-stat-label">📊 Rămase</label>';
                $html .= '<div class="oc-stat-value oc-stat-remaining oc-stat-display"' . $course_stat_style . '>' . esc_html($remaining_display) . '</div>';
                if ($course_can_edit_sessions) {
                    $html .= '<input type="number" class="oc-course-session-input ' . esc_attr($course_input_class) . '" data-field-type="number" data-membership-id="' . esc_attr($membership_id) . '" data-field-name="remaining_sessions" value="' . esc_attr((int) $sessions_remaining) . '" min="0" step="1"' . $course_input_disabled_attr . $course_input_style . '>';
                }
                $html .= '</div>';

                // Folosite
                $html .= '<div class="oc-stat-item">';
                $html .= '<label class="oc-stat-label">✅ Folosite</label>';
                $html .= '<div class="oc-stat-value oc-stat-used oc-stat-display"' . $course_stat_style . '>' . $sessions_used . '</div>';
                if ($course_can_edit_sessions) {
                    $html .= '<input type="number" class="oc-course-session-input ' . esc_attr($course_input_class) . '" data-field-type="number" data-membership-id="' . esc_attr($membership_id) . '" data-field-name="used_sessions" value="' . esc_attr((int) $sessions_used) . '" min="0" step="1"' . $course_input_disabled_attr . $course_input_style . '>';
                }
                $html .= '</div>';
                
                // Ultima validare
                $html .= '<div class="oc-stat-item">';
                $html .= '<label class="oc-stat-label">📅 Validare</label>';
                $html .= '<div class="oc-stat-value oc-stat-validation">' . esc_html($last_validation) . '</div>';
                $html .= '</div>';
                
                $html .= '</div>'; // Close grid

                $html .= '</div>'; // Close course-entry
                
                $course_counter++;
            } // Close foreach courses
            } // Close unlimited/normal display switch
            
            $html .= '</div>'; // Close package-courses
            
            // Câmpurile de date per pachet sunt vizibile doar pentru admin.
            if ($is_admin) {
                $package_membership_id = !empty($courses) ? intval($courses[0]['id'] ?? 0) : 0;
                $package_created_at = !empty($package['created_at']) ? (string) $package['created_at'] : '';
                $package_expiration_raw = (string) ($latest_expiry ?? '');
                $package_no_expiry = empty($package_expiration_raw);
                $package_dates_suffix = $order_id > 0 ? (string) $order_id : ('membership-' . $package_membership_id);
                $pending_dates_editable = $package_status === 'pending';
                $date_field_class = $pending_dates_editable ? 'oc-field-editable' : 'oc-field-readonly';
                $date_field_disabled_attr = $pending_dates_editable ? '' : ' disabled';

                $html .= '<div class="oc-package-date-fields" data-order-id="' . esc_attr($order_id) . '" data-membership-id="' . esc_attr($package_membership_id) . '">';
                $html .= '<div class="oc-package-date-grid">';

                $html .= '<div class="oc-form-group">';
                $html .= '<label for="purchase-date-package-' . esc_attr($package_dates_suffix) . '">Data achiziționării</label>';
                $html .= '<input type="text" id="purchase-date-package-' . esc_attr($package_dates_suffix) . '" name="created_at" class="oc-wp-date-input oc-package-date-field ' . esc_attr($date_field_class) . '" inputmode="numeric" autocomplete="off" data-duration-days="' . esc_attr($package_duration_days) . '" value="' . esc_attr($this->format_date_european($package_created_at)) . '" data-original-value="' . esc_attr($this->format_date_european($package_created_at)) . '" data-field-type="text" placeholder="' . esc_attr(get_option('date_format')) . '"' . $date_field_disabled_attr . '>';
                $html .= '</div>';

                $html .= '<div class="oc-form-group">';
                $html .= '<label for="valid-until-package-' . esc_attr($package_dates_suffix) . '">Valabil până la</label>';
                $html .= '<input type="text" id="valid-until-package-' . esc_attr($package_dates_suffix) . '" name="expiration_date" class="oc-wp-date-input oc-package-date-field ' . esc_attr($date_field_class) . '" inputmode="numeric" autocomplete="off" data-duration-days="' . esc_attr($package_duration_days) . '" value="' . esc_attr($this->format_date_european($package_expiration_raw)) . '" data-original-value="' . esc_attr($this->format_date_european($package_expiration_raw)) . '" data-field-type="text" placeholder="' . esc_attr(get_option('date_format')) . '"' . $date_field_disabled_attr . '>';

                $html .= '<div class="oc-expiry-controls">';
                $html .= '<label class="oc-no-expiry-label">';
                $html .= '<input type="checkbox" name="no_expiry" class="oc-no-expiry-checkbox oc-package-date-field ' . esc_attr($date_field_class) . '" data-original-value="' . ($package_no_expiry ? '1' : '0') . '" ' . checked($package_no_expiry, true, false) . $date_field_disabled_attr . '>';
                $html .= '<span class="oc-no-expiry-text">fără dată de expirare</span>';
                $html .= '</label>';

                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }

            // 🔒 CONTAINER COMUN pentru butoanele de activare și anulare - DOAR ADMIN
            $first_membership_id_in_package = !empty($courses) ? ($courses[0]['id'] ?? 0) : 0;
            
            if ($is_admin) {
                $html .= '<div class="oc-package-observations-wrap">';
                $html .= '<label for="package-observations-' . esc_attr($order_id) . '">Observații</label>';
                $html .= '<textarea id="package-observations-' . esc_attr($order_id) . '" name="package_observations" rows="2" class="oc-package-meta-field ' . esc_attr($meta_field_class) . '" data-field-type="textarea" data-original-value="' . esc_attr($package_observations) . '"' . $meta_field_disabled_attr . '>' . esc_textarea($package_observations) . '</textarea>';
                $html .= '</div>';
            }

            if ($is_admin || $package_status !== 'pending') {
                $html .= '<div class="oc-package-actions-container">';
                $html .= '<div class="oc-package-buttons-row">';
                if ($package_status !== 'pending') {
                    $html .= '<button type="button" class="oc-btn oc-btn-secondary oc-btn-validation-history" data-membership-id="' . esc_attr($first_membership_id_in_package) . '" data-order-id="' . esc_attr($order_id) . '"><span class="dashicons dashicons-list-view"></span> Vezi Istoric Validări</button>';
                }

                if ($is_admin && $package_status === 'pending') {
                    $html .= '<button type="button" class="oc-btn oc-btn-primary oc-btn-activate-membership" data-order-id="' . esc_attr($order_id) . '"><span class="dashicons dashicons-controls-play"></span> Activează Abonamentul</button>';
                }

                if ($is_admin) {
                    $html .= '<button type="button" class="oc-btn oc-btn-danger oc-btn-cancel-membership" ';
                    $html .= 'data-user-id="' . esc_attr($real_user_id) . '" ';
                    $html .= 'data-membership-id="' . esc_attr($first_membership_id_in_package) . '" ';
                    $html .= 'data-order-id="' . esc_attr($order_id) . '" ';
                    $html .= 'title="Anulează comanda WooCommerce #' . $order_id . ' și marchează TOATE cursurile din acest pachet ca expirate">';
                    $html .= '<span class="dashicons dashicons-trash"></span> Anulează Acest Pachet';
                    $html .= '</button>';
                }

                $html .= '</div>';

                if ($package_status !== 'pending') {
                    $html .= $this->render_validation_history_panel_html((int) $first_membership_id_in_package, (int) $order_id, $is_admin);
                }

                $html .= '</div>';
            }
            
            $html .= '</div>'; // Close package-section
            
            $package_counter++;
        } // Close foreach packages

        $html .= '<div class="oc-active-packages-total">';
        $html .= '<strong>Total pachete active: ' . intval($active_packages_count) . '</strong> · ';
        $html .= '<strong>' . esc_html(number_format($active_packages_total_price, 2, '.', '')) . ' lei</strong>';
        $html .= '</div>';
        
        // Buton pentru adăugare curs nou
        // Pentru guest: folosim primul membership_id găsit
        $first_membership_id = 0;
        if (!empty($all_memberships)) {
            $first_membership_id = $all_memberships[0]['id'] ?? 0;
        }
        $data_attr = $is_guest ? 'data-membership-id="' . esc_attr($first_membership_id) . '"' : 'data-user-id="' . esc_attr($real_user_id) . '"';
        
        $html .= '<div class="oc-add-course-btn-container" style="display: none;" ' . $data_attr . '>';
        $html .= '<button type="button" class="button button-secondary oc-btn-add-course" ' . $data_attr . '>';
        $html .= '<span class="dashicons dashicons-plus-alt"></span> Adaugă Curs Suplimentar';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</div>'; // Close expandedlist
        
        return $html;
    }

    
    // ============================================
    // SECTION 5: DATA HELPER METHODS
    // ============================================

    /**
     * Găsește TOATE cursurile care rulează AZI (în intervalul de toleranță 30 min)
     * 
     * @param array $user_courses Lista cursurilor utilizatorului
     * @return array Lista cursurilor care rulează azi
     */
    private function find_all_running_courses_today(array $user_courses): array {
        global $wpdb;
        
        // Ora și ziua curentă
        $current_datetime = oc_membership_current_local_datetime();
        $current_time = $current_datetime->format('H:i');
        $current_weekday = (int) $current_datetime->format('w'); // 0-6, 0=Duminică
        
        // Tabelul orar
        $schedule_table = $wpdb->prefix . 'orar_cursuri';
        
        $all_courses_today = [];
        $at_least_one_started = false;
        
        // Pas 1: Găsește TOATE cursurile programate AZI
        foreach ($user_courses as $course) {
            // Caută în orar dacă acest curs este programat AZI (indiferent de oră)
            // 🔧 FIX: Pentru duminică (0) caută și weekday=7 pentru backwards compatibility
            if ($current_weekday === 0) {
                $schedule = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$schedule_table}
                    WHERE variation_id = %d
                    AND (weekday = 0 OR weekday = 7)
                    LIMIT 1
                ", $course['variation_id']), ARRAY_A);
            } else {
                $schedule = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$schedule_table}
                    WHERE variation_id = %d
                    AND weekday = %d
                    LIMIT 1
                ", $course['variation_id'], $current_weekday), ARRAY_A);
            }
            
            if ($schedule) {
                $course['schedule'] = $schedule;
                $all_courses_today[] = $course;
                
                // Verifică dacă acest curs a început deja (cu toleranță 30 min înainte)
                $start_with_tolerance = date('H:i', strtotime($schedule['start_time']) - 1800); // -30 min
                if ($current_time >= $start_with_tolerance) {
                    $at_least_one_started = true;
                }
            }
        }
        
        // Pas 2: Dacă măcar UN curs a început → returnează TOATE cursurile de azi
        if ($at_least_one_started) {
            return $all_courses_today;
        }
        
        return [];
    }
    
    /**
     * Găsește cursul care rulează ACUM bazat pe orar (DEPRECATED - folosește find_all_running_courses_today)
     * 
     * @param array $user_courses Lista cursurilor utilizatorului
     * @return array|null Cursul care rulează sau null
     */
    private function find_running_course(array $user_courses): ?array {
        global $wpdb;
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        
        // Ora și ziua curentă
        $current_datetime = oc_membership_current_local_datetime();
        $current_time = $current_datetime->format('H:i');
        $current_weekday = (int) $current_datetime->format('w'); // 0-6, 0=Duminică
        
        if ($debug) {
            // Debug trace removed to keep logs clean.
        }
        
        // Tabelul orar
        $schedule_table = $wpdb->prefix . 'orar_cursuri';
        
        foreach ($user_courses as $course) {
            if ($debug) {
                // Debug trace removed to keep logs clean.
            }
            
            // Caută în orar dacă acest curs rulează acum SAU începe în max 30 min
            // Folosim ADDTIME(start_time, '-00:30:00') pentru a permite validare 30 min înainte
            $schedule = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$schedule_table}
                WHERE variation_id = %d
                AND weekday = %d
                AND ADDTIME(start_time, '-00:30:00') <= %s
                AND end_time >= %s
                LIMIT 1
            ", $course['variation_id'], $current_weekday, $current_time, $current_time), ARRAY_A);
            
            if ($debug) {
                // Debug trace removed to keep logs clean.
            }
            
            if ($schedule) {
                // GĂSIT! Acest curs rulează acum sau începe în max 30 min
                if ($debug) {
                    // Debug trace removed to keep logs clean.
                }
                $course['schedule'] = $schedule;
                return $course;
            }
        }
        
        if ($debug) {
            // Debug trace removed to keep logs clean.
        }
        return null; // Nici un curs nu rulează acum
    }
    
    /**
     * Format dată după setările WordPress.
     *
     * @param string|null $date Data în orice format MySQL
     * @return string Data formatată sau 'N/A'
     */
    private function format_date_european(?string $date): string {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return 'N/A';
        }
        
        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return 'N/A';
            }
            return wp_date(get_option('date_format'), $timestamp);
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function sync_user_membership_statuses_if_possible(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        $this->sync_membership_statuses_for_table_request($user_id);
    }

    private function sync_membership_statuses_for_table_request(?int $user_id = null): void {
        if (!$this->validator_db || !method_exists($this->validator_db, 'sync_membership_statuses')) {
            return;
        }

        if ($user_id !== null && $user_id > 0) {
            if ($this->table_request_synced_all_statuses || isset($this->table_request_synced_user_ids[$user_id])) {
                return;
            }

            $this->validator_db->sync_membership_statuses($user_id);
            $this->table_request_synced_user_ids[$user_id] = true;
            return;
        }

        if ($this->table_request_synced_all_statuses) {
            return;
        }

        $this->validator_db->sync_membership_statuses();
        $this->table_request_synced_all_statuses = true;
        $this->table_request_synced_user_ids = [];
    }

    private function get_effective_membership_status(string $stored_status, ?string $expiration_date, bool $is_unlimited = false, ?int $remaining_sessions = null): string {
        if (in_array($stored_status, ['pending', 'cancelled', 'suspended', 'transferred'], true)) {
            return $stored_status;
        }

        if (!$is_unlimited && $remaining_sessions !== null && $remaining_sessions <= 0) {
            return 'expired';
        }

        if ($is_unlimited && empty($expiration_date)) {
            return $stored_status === 'expired' ? 'expired' : 'active';
        }

        if (oc_membership_is_expired($expiration_date)) {
            return 'expired';
        }

        if ($stored_status === 'expired') {
            return 'active';
        }

        return $stored_status;
    }

    private function get_days_until_membership_expiry(?string $expiration_date): ?int {
        $normalized_expiration_date = oc_membership_normalize_date_value($expiration_date);
        if ($normalized_expiration_date === '') {
            return null;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

        try {
            $today = new DateTimeImmutable(oc_membership_current_business_date(), $timezone);
            $expiry = new DateTimeImmutable($normalized_expiration_date, $timezone);
            return (int) $today->diff($expiry)->format('%r%a');
        } catch (Exception $exception) {
            return null;
        }
    }
    
    /**
     * Obține TOATE cursurile active pentru un utilizator cu ședințe
     * 
     * @param int $user_id User ID
     * @return array Lista cursurilor cu ședințe
     */
    private function get_all_user_active_courses(int $user_id): array {
        global $wpdb;
        $this->sync_user_membership_statuses_if_possible($user_id);
        $table_name = $this->validator_db->get_table_name('membership_validations');
        $posts_table = $wpdb->prefix . 'posts';
        $today = oc_membership_current_business_date();
        
        // FIX: Exclude cursurile PENDING și comenzile anulate
        // DOAR cursurile ACTIVE pot fi validate
        $courses = $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.id,
                m.variation_id,
                m.sessions_allocated,
                m.remaining_sessions,
                m.used_sessions,
                m.is_unlimited,
                m.last_validation_date,
                m.validation_status,
                m.payment_method
            FROM {$table_name} m
            LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
            WHERE m.user_id = %d
            AND m.validation_status = 'active'
            AND (m.expiration_date IS NULL OR m.expiration_date >= %s)
            AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
            ORDER BY m.id DESC
        ", $user_id, $today), ARRAY_A);
        
        if (empty($courses)) {
            return [];
        }
        
        // Enricheze cu numele cursurilor
        $enriched = [];
        foreach ($courses as $course) {
            $variation = wc_get_product($course['variation_id']);
            if ($variation) {
                $course['course_name'] = $variation->get_name();
                $enriched[] = $course;
            }
        }
        
        return $enriched;
    }

    private function get_user_header_packages(int $user_id, int $guest_order_id = 0): array {
        global $wpdb;

        $table_name = $this->validator_db->get_table_name('membership_validations');
        $posts_table = $wpdb->prefix . 'posts';

        if ($user_id > 0) {
            $this->sync_user_membership_statuses_if_possible($user_id);
                        $rows = $wpdb->get_results($wpdb->prepare(
                                "SELECT m.order_id, m.product_name, m.product_price, m.expiration_date, m.created_at, m.validation_status, m.variation_id, m.payment_method, m.remaining_sessions, m.is_unlimited
                 FROM {$table_name} m
                 LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                 WHERE m.user_id = %d
                                     AND m.validation_status IN ('active','pending','expired')
                   AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
                 ORDER BY m.created_at ASC",
                $user_id
            ), ARRAY_A);
        } elseif ($guest_order_id > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                                "SELECT m.order_id, m.product_name, m.product_price, m.expiration_date, m.created_at, m.validation_status, m.variation_id, m.payment_method, m.remaining_sessions, m.is_unlimited
                 FROM {$table_name} m
                 LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                 WHERE m.user_id = 0
                   AND m.order_id = %d
                                     AND m.validation_status IN ('active','pending','expired')
                   AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')
                 ORDER BY m.created_at ASC",
                $guest_order_id
            ), ARRAY_A);
        } else {
            $rows = [];
        }

        if (empty($rows)) {
            return [];
        }

        $packages = [];
        foreach ($rows as $row) {
            $order_id = (int) ($row['order_id'] ?? 0);
            if ($order_id <= 0) {
                continue;
            }

            if (!isset($packages[$order_id])) {
                $packages[$order_id] = [
                    'name' => (string) ($row['product_name'] ?? ('Abonament #' . $order_id)),
                    'price' => (float) ($row['product_price'] ?? 0),
                    'valid_until' => !empty($row['expiration_date']) ? (string) $row['expiration_date'] : '',
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'status' => (string) ($row['validation_status'] ?? 'pending'),
                    'payment_method' => (string) ($row['payment_method'] ?? ''),
                    'courses' => [],
                ];
            }

            if (!empty($row['product_name']) && strpos($packages[$order_id]['name'], 'Abonament #') === 0) {
                $packages[$order_id]['name'] = (string) $row['product_name'];
            }

            $row_price = (float) ($row['product_price'] ?? 0);
            if ($row_price > (float) $packages[$order_id]['price']) {
                $packages[$order_id]['price'] = $row_price;
            }

            $row_expiration = !empty($row['expiration_date']) ? (string) $row['expiration_date'] : '';
            if ($row_expiration !== '' && ($packages[$order_id]['valid_until'] === '' || $row_expiration > $packages[$order_id]['valid_until'])) {
                $packages[$order_id]['valid_until'] = $row_expiration;
            }

            $row_status = $this->get_effective_membership_status(
                (string) ($row['validation_status'] ?? 'pending'),
                $row_expiration,
                !empty($row['is_unlimited']) && empty($row_expiration),
                isset($row['remaining_sessions']) ? (int) $row['remaining_sessions'] : null
            );
            $existing_status = (string) ($packages[$order_id]['status'] ?? 'pending');
            if ($existing_status !== 'active' && $row_status === 'active') {
                $packages[$order_id]['status'] = 'active';
            } elseif ($existing_status === 'expired' && $row_status === 'pending') {
                $packages[$order_id]['status'] = 'pending';
            }

            if (empty($packages[$order_id]['payment_method']) && !empty($row['payment_method'])) {
                $packages[$order_id]['payment_method'] = (string) $row['payment_method'];
            }

            $variation_id = (int) ($row['variation_id'] ?? 0);
            if ($variation_id > 0) {
                $variation_product = wc_get_product($variation_id);
                if ($variation_product) {
                    $course_name = trim((string) $variation_product->get_name());
                    if ($course_name !== '' && !in_array($course_name, $packages[$order_id]['courses'], true)) {
                        $packages[$order_id]['courses'][] = $course_name;
                    }
                }
            }
        }

        $has_non_expired = false;
        foreach ($packages as $pkg_visibility) {
            $pkg_status_visibility = (string) ($pkg_visibility['status'] ?? 'pending');
            if (in_array($pkg_status_visibility, ['active', 'pending'], true)) {
                $has_non_expired = true;
                break;
            }
        }

        if ($has_non_expired) {
            $packages = array_values(array_filter($packages, static function(array $pkg_row): bool {
                return in_array((string) ($pkg_row['status'] ?? 'pending'), ['active', 'pending'], true);
            }));
        }

        usort($packages, static function(array $a, array $b): int {
            return strtotime((string) ($a['created_at'] ?? '0')) <=> strtotime((string) ($b['created_at'] ?? '0'));
        });

        return $packages;
    }

    private function has_active_or_pending_memberships(int $user_id, int $guest_order_id = 0): bool {
        global $wpdb;

        $table_name = $this->validator_db->get_table_name('membership_validations');
        $posts_table = $wpdb->prefix . 'posts';

        if ($user_id > 0) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table_name} m
                 LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                 WHERE m.user_id = %d
                   AND m.validation_status IN ('active','pending')
                   AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')",
                $user_id
            ));
        } elseif ($guest_order_id > 0) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table_name} m
                 LEFT JOIN {$posts_table} p ON m.order_id = p.ID AND p.post_type = 'shop_order'
                 WHERE m.user_id = 0
                   AND m.order_id = %d
                   AND m.validation_status IN ('active','pending')
                   AND (p.ID IS NULL OR p.post_status != 'wc-cancelled')",
                $guest_order_id
            ));
        } else {
            $count = 0;
        }

        return ((int) $count) > 0;
    }
    
    /**
     * Obține toate pachetele Pool disponibile pentru dropdown
     * 
     * @return array Lista de pachete cu ID, titlu și preț
     */
    private function get_all_pool_packages(): array {
        global $wpdb;
        $default_duration_days = $this->get_default_membership_duration_days();
        
        $packages = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title,
                   pm_price.meta_value as price,
                   pm_pool.meta_value as pool_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_oc_pool_price'
            LEFT JOIN {$wpdb->postmeta} pm_pool ON p.ID = pm_pool.post_id AND pm_pool.meta_key = '_oc_pool_id'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_enabled.meta_key IN ('_oc_pool_enabled', '_mv_pack_enabled')
            AND pm_enabled.meta_value IN ('yes', '1', 'on')
            ORDER BY p.post_title
        ");
        
        // Fallback dacă nu găsește pachete Pool
        if (empty($packages)) {
            return [];
        }
        
        // 🔧 FIX: Pentru produse variable cu preț 0, extrage prețul din prima variantă
        foreach ($packages as $package) {
            $package->duration_days = $default_duration_days;

            if (empty($package->price) || $package->price == 0) {
                $wc_product = wc_get_product($package->ID);
                
                if ($wc_product && $wc_product->is_type('variable')) {
                    $variations = $wc_product->get_available_variations();
                    if (!empty($variations)) {
                        $first_variation = reset($variations);
                        $package->price = isset($first_variation['display_price']) ? $first_variation['display_price'] : 0;
                    }
                } else {
                    // Pentru produse simple, încearcă să obții prețul standard WooCommerce
                    $package->price = $wc_product ? $wc_product->get_price() : 0;
                }
            }
        }
        
        return $packages ?: [];
    }

    private function get_default_membership_duration_days(): int {
        $settings = get_option('oc_membership_settings', []);
        $duration_days = intval($settings['default_membership_duration'] ?? 28);

        return $duration_days > 0 ? $duration_days : 28;
    }

    private function calculate_expiration_from_purchase_date(array $current_membership, array $membership_updates): ?string {
        $duration_days = (int) ($current_membership['duration_days'] ?? 0);
        if ($duration_days <= 0) {
            $duration_days = $this->get_default_membership_duration_days();
        }

        $created_source = (string) ($membership_updates['created_at'] ?? $current_membership['created_at'] ?? '');
        if ($created_source === '') {
            return null;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $created_source, $matches)) {
            return null;
        }

        $purchase_date = $matches[1];
        return $this->add_days_to_iso_date_wp($purchase_date, $duration_days);
    }

    private function add_days_to_iso_date_wp(string $date_iso, int $days): ?string {
        $date_iso = trim($date_iso);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_iso)) {
            return null;
        }

        $timezone = wp_timezone();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_iso, $timezone);
        if (!$date) {
            return null;
        }

        $shifted = $date->modify(sprintf('+%d days', $days));
        if (!$shifted) {
            return null;
        }

        return $shifted->format('Y-m-d');
    }

    private function normalize_date_input_for_storage(string $raw_value, bool $strict = true): ?string {
        $raw_value = trim($raw_value);
        if ($raw_value === '') {
            if ($strict) {
                throw new Exception('Data de activare este obligatorie.');
            }
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw_value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if (preg_match('/^(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})$/', $raw_value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if ($strict) {
            throw new Exception('Format dată invalid. Folosește dd/mm/yyyy.');
        }

        return null;
    }

    private function resolve_order_dates_for_creation(array $data): array {
        $activation_date = $this->normalize_date_input_for_storage((string) ($data['activation_date'] ?? ''), true) ?? '';

        $duration_days = intval($data['duration_days'] ?? 0);
        if ($duration_days <= 0) {
            $duration_days = $this->get_default_membership_duration_days();
        }

        $no_expiry = !empty($data['no_expiry']) && intval($data['no_expiry']) === 1;
        $expiration_date = '';

        if (!$no_expiry) {
            $normalized_expiration = $this->normalize_date_input_for_storage((string) ($data['expiration_date'] ?? ''), false);
            if ($normalized_expiration !== null) {
                $expiration_date = $normalized_expiration;
            } elseif ($activation_date !== '') {
                $expiration_date = $this->add_days_to_iso_date_wp($activation_date, $duration_days) ?? '';
            }
        }

        return [
            'activation_date' => $activation_date,
            'expiration_date' => $expiration_date,
        ];
    }

    
    // ============================================
    // SECTION 6: WOOCOMMERCE HELPER METHODS
    // ============================================

    /**
     * Creează cont WordPress pentru guest user
     * 
     * @param string $email Email utilizator
     * @param string $display_name Nume complet
     * @param string $phone Telefon
     * @return int|false User ID sau false
     */
    /**
     * Creează cont WordPress pentru guest user
     * WRAPPER peste create_wp_user_helper() pentru a evita duplicarea codului
     */
    private function create_wp_account_for_guest(string $email, string $display_name, string $phone = '') {
        try {
            // Generează parolă random
            $password = wp_generate_password(12, true, true);
            
            // Split display_name în first_name și last_name
            $name_parts = explode(' ', $display_name, 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
            
            // Folosește helper-ul unificat
            $user_id = $this->create_wp_user_helper([
                'display_name' => $display_name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'billing_first_name' => '',
                'billing_last_name' => '',
                'billing_address_1' => '',
                'billing_city' => '',
                'billing_state' => '',
                'billing_postcode' => '',
                'billing_country' => 'RO'
            ]);
            
            return $user_id;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Account Creation] Error: ' . $e->getMessage());
            }
            return false;
        }
    }
    /**
     * Validare prealabilă pentru crearea clientului (cu mesaje clare)
     * 
     * @param array $data Date client
     * @throws Exception Dacă există duplicat
     */
    private function validate_client_data_before_creation(array $data): void {
        global $wpdb;
        
        // Validare email duplicat cu detalii - verificare directă în DB
        $existing_user_id = $wpdb->get_var($wpdb->prepare("
            SELECT ID FROM {$wpdb->users} 
            WHERE user_email = %s
            LIMIT 1
        ", $data['email']));
        
        if ($existing_user_id) {
            $existing_user = get_userdata($existing_user_id);
            $existing_name = $existing_user ? oc_membership_resolve_user_display_name($existing_user) : 'Utilizator necunoscut';
            throw new Exception("❌ Email '{$data['email']}' este deja folosit de: {$existing_name} (ID: {$existing_user_id})");
        }
        
        // Validare telefon duplicat cu detalii
        $existing_phone = $wpdb->get_var($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'billing_phone' AND meta_value = %s
            LIMIT 1
        ", $data['phone']));
        
        if ($existing_phone) {
            $existing_user = get_userdata($existing_phone);
            $existing_name = $existing_user ? oc_membership_resolve_user_display_name($existing_user) : 'Utilizator necunoscut';
            throw new Exception("❌ Telefon '{$data['phone']}' este deja folosit de: {$existing_name} (ID: {$existing_phone})");
        }
        
        // Validare nume duplicat (doar warning, nu oprește crearea)
        $existing_name = $wpdb->get_var($wpdb->prepare("
            SELECT ID FROM {$wpdb->users} 
            WHERE display_name = %s
            LIMIT 1
        ", $data['display_name']));
        
        if ($existing_name) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("⚠️ [Client Creation] Warning: Display name '{$data['display_name']}' already exists (User ID: {$existing_name})");
            }
        }
    }
    /**
     * Helper pentru crearea unui WP User cu validări
     * 
     * @param array $data Date user (display_name, email, phone, password)
     * @return int User ID
     * @throws Exception Dacă există duplicat sau eroare
     */
    private function create_wp_user_helper(array $data): int {
        global $wpdb;
        
        // Validările de duplicat se fac în validate_client_data_before_creation()
        // Aici doar creăm utilizatorul - dar să facem o verificare finală pentru siguranță
        
        // Generare username unic
        $username = sanitize_user(strtolower(str_replace(' ', '_', $data['display_name'])));
        $base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i++;
        }
        
        // Creează user
        $user_id = wp_create_user($username, $data['password'], $data['email']);
        if (is_wp_error($user_id)) {
            throw new Exception($user_id->get_error_message());
        }
        
        // Actualizează display name, first_name, last_name și rol
        wp_update_user([
            'ID' => $user_id,
            'display_name' => (string) ($data['display_name'] ?? ''),
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'role' => 'customer'
        ]);
        
        // Setează billing phone
        update_user_meta($user_id, 'billing_phone', $data['phone']);
        
        // Setează date de facturare (dacă sunt furnizate)
        if (!empty($data['billing_first_name'])) {
            update_user_meta($user_id, 'billing_first_name', $data['billing_first_name']);
        }
        if (!empty($data['billing_last_name'])) {
            update_user_meta($user_id, 'billing_last_name', $data['billing_last_name']);
        }
        if (!empty($data['billing_address_1'])) {
            update_user_meta($user_id, 'billing_address_1', $data['billing_address_1']);
        }
        if (!empty($data['billing_city'])) {
            update_user_meta($user_id, 'billing_city', $data['billing_city']);
        }
        if (!empty($data['billing_state'])) {
            update_user_meta($user_id, 'billing_state', $data['billing_state']);
        }
        if (!empty($data['billing_postcode'])) {
            update_user_meta($user_id, 'billing_postcode', $data['billing_postcode']);
        }
        if (!empty($data['billing_country'])) {
            update_user_meta($user_id, 'billing_country', $data['billing_country']);
        }
        
        return $user_id;
    }
    /**
     * Helper pentru crearea unei comenzi WooCommerce
     * 
     * @param array $data Date comandă (user_id, package_id, course_selections, payment_status, payment_method)
     * @return int Order ID
     * @throws Exception Dacă există eroare
     */
    private function create_woocommerce_order_helper(array $data): int {
        $order = wc_create_order([
            'customer_id' => intval($data['user_id']),
            'status' => 'pending'
        ]);
        
        if (is_wp_error($order)) {
            throw new Exception($order->get_error_message());
        }
        
        // Add package product
        $package = wc_get_product($data['package_id']);
        if (!$package) {
            throw new Exception('Pachet invalid');
        }
        
        $item_id = $order->add_product($package, 1);
        wc_update_order_item_meta($item_id, '_oc_pool', 'yes');

        $custom_package_price = null;
        if (array_key_exists('product_price', $data) && $data['product_price'] !== null && $data['product_price'] !== '') {
            $custom_package_price = max(0, round((float) $data['product_price'], 2));
        }

        if ($custom_package_price !== null) {
            $package_item = $order->get_item($item_id);
            if ($package_item) {
                $package_item->set_subtotal($custom_package_price);
                $package_item->set_total($custom_package_price);
                $package_item->save();
            }
            $order->update_meta_data('_oc_custom_package_price', (string) $custom_package_price);
        }

        if (array_key_exists('observations', $data)) {
            $order->update_meta_data('_oc_observations', sanitize_textarea_field((string) $data['observations']));
        }
        
        // Add course variations as child items
        foreach ($data['course_selections'] as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $child_id = $order->add_product($variation, 1);
                wc_update_order_item_meta($child_id, '_oc_pool_child', 'yes');
                wc_update_order_item_meta($child_id, '_mv_pack_child', 'yes');
                wc_update_order_item_meta($child_id, '_line_total', '0');

                $child_item = $order->get_item($child_id);
                if ($child_item) {
                    $child_item->set_subtotal(0);
                    $child_item->set_total(0);
                    $child_item->save();
                }
            }
        }
        
        // Setează payment method și titlul său (pentru afișare corectă)
        $payment_method = $this->normalize_payment_method_key((string) ($data['payment_method'] ?? 'cash'));
        if ($payment_method === 'unknown') {
            $payment_method = 'cash';
        }
        $order->set_payment_method($payment_method);

        $payment_title = $this->get_payment_method_title_by_key($payment_method);
        if (empty($payment_title) || $payment_title === 'Necunoscut') {
            $payment_title = 'Cash / Numerar';
        }
        $order->set_payment_method_title($payment_title);
        
        // Setează billing address din user meta
        $user = get_userdata($data['user_id']);
        if ($user) {
            // Folosește display_name ca fallback pentru first_name dacă nu există billing_first_name
            $billing_first_name = get_user_meta($data['user_id'], 'billing_first_name', true) ?: $user->first_name ?: $user->display_name;
            $billing_last_name = get_user_meta($data['user_id'], 'billing_last_name', true) ?: $user->last_name ?: '';
            $billing_phone = get_user_meta($data['user_id'], 'billing_phone', true) ?: '';
            
            // Dacă billing_first_name este gol, folosește display_name
            if (empty($billing_first_name)) {
                $billing_first_name = $user->display_name;
            }
            
            // Setează numele clientului (nu admin-ului)
            $order->set_billing_first_name($billing_first_name);
            $order->set_billing_last_name($billing_last_name);
            $order->set_billing_email($user->user_email);
            $order->set_billing_phone($billing_phone);
            
            // Setează și customer name pentru WooCommerce
            $order->set_customer_note('Client creat prin Membership Manager - ' . current_time('d.m.Y H:i'));
            
            // Setează billing address dacă există
            $billing_address_1 = get_user_meta($data['user_id'], 'billing_address_1', true);
            if ($billing_address_1) {
                $order->set_billing_address_1($billing_address_1);
            }
            $billing_city = get_user_meta($data['user_id'], 'billing_city', true);
            if ($billing_city) {
                $order->set_billing_city($billing_city);
            }
            $billing_state = get_user_meta($data['user_id'], 'billing_state', true);
            if ($billing_state) {
                $order->set_billing_state($billing_state);
            }
            $billing_postcode = get_user_meta($data['user_id'], 'billing_postcode', true);
            if ($billing_postcode) {
                $order->set_billing_postcode($billing_postcode);
            }
            $billing_country = get_user_meta($data['user_id'], 'billing_country', true);
            if ($billing_country) {
                $order->set_billing_country($billing_country);
            }
        }
        
        // Normalizează datele server-side și calculează fallback automat pentru expirare.
        $resolved_dates = $this->resolve_order_dates_for_creation($data);
        if ($resolved_dates['activation_date'] !== '') {
            $order->update_meta_data('_oc_activation_date', $resolved_dates['activation_date']);
        }
        if ($resolved_dates['expiration_date'] !== '') {
            $order->update_meta_data('_oc_expiration_date', $resolved_dates['expiration_date']);
        }
        $order->update_meta_data('_oc_created_via_membership_manager', 'yes');
        $order->update_meta_data('_oc_requested_payment_status', sanitize_key((string) ($data['payment_status'] ?? '')));

        $order->calculate_totals();
        $order->save();

        if (($data['payment_status'] ?? '') !== 'paid') {
            $validator = OC_Membership_Validator::get_instance();
            if ($validator) {
                $validator->process_new_membership($order->get_id());
            }
        }
        
        return $order->get_id();
    }

    private function sync_order_package_price(WC_Order $order, float $package_price): void {
        $package_price = max(0, round($package_price, 2));

        foreach ($order->get_items() as $order_item) {
            if ((int) $order_item->get_variation_id() === 0) {
                $order_item->set_subtotal($package_price);
                $order_item->set_total($package_price);
            } else {
                $order_item->set_subtotal(0);
                $order_item->set_total(0);
            }
            $order_item->save();
        }

        $order->update_meta_data('_oc_custom_package_price', (string) $package_price);
        $order->calculate_totals();
    }

    private function normalize_payment_method_key(string $payment_method): string {
        $payment_method = trim(wp_strip_all_tags($payment_method));
        if ($payment_method === '') {
            return 'unknown';
        }

        $available_methods = $this->get_available_payment_methods();
        if (isset($available_methods[$payment_method])) {
            return $payment_method;
        }

        $payment_method_lower = function_exists('mb_strtolower')
            ? mb_strtolower($payment_method, 'UTF-8')
            : strtolower($payment_method);

        foreach ($available_methods as $method_key => $method_label) {
            $method_label_lower = function_exists('mb_strtolower')
                ? mb_strtolower((string) $method_label, 'UTF-8')
                : strtolower((string) $method_label);
            if ($payment_method_lower === $method_label_lower) {
                return (string) $method_key;
            }
        }

        if (strpos($payment_method_lower, '7card') !== false) {
            return 'oc_7card';
        }
        if (strpos($payment_method_lower, 'esx') !== false) {
            return 'oc_esx';
        }
        if (
            strpos($payment_method_lower, 'cash') !== false ||
            strpos($payment_method_lower, 'numerar') !== false ||
            strpos($payment_method_lower, 'plata la studio') !== false ||
            strpos($payment_method_lower, 'studioul de dans') !== false
        ) {
            return 'cash';
        }
        if (
            strpos($payment_method_lower, 'card') !== false ||
            strpos($payment_method_lower, 'netopia') !== false ||
            strpos($payment_method_lower, 'stripe') !== false
        ) {
            return 'card';
        }
        if (
            strpos($payment_method_lower, 'transfer') !== false ||
            strpos($payment_method_lower, 'bancar') !== false ||
            strpos($payment_method_lower, 'bacs') !== false
        ) {
            return 'transfer';
        }

        return 'unknown';
    }

    private function is_gateway_payment_method(string $payment_method_key): bool {
        return in_array($payment_method_key, ['oc_7card', 'oc_esx'], true);
    }

    private function replace_existing_gateway_memberships(int $user_id, array $existing_rows, string $order_note = ''): void {
        global $wpdb;

        if ($user_id <= 0 || empty($existing_rows)) {
            return;
        }

        $table_name = $this->validator_db->get_table_name('membership_validations');
        $existing_order_ids = [];
        $existing_membership_ids = [];
        foreach ($existing_rows as $existing_row) {
            $existing_membership_id = (int) ($existing_row['id'] ?? 0);
            if ($existing_membership_id > 0) {
                $existing_membership_ids[] = $existing_membership_id;
            }

            $existing_order_id = (int) ($existing_row['order_id'] ?? 0);
            if ($existing_order_id > 0) {
                $existing_order_ids[$existing_order_id] = true;
            }
        }

        if (empty($existing_membership_ids)) {
            return;
        }

        $changed_orders = [];
        $transaction_active = false;
        $effective_order_note = trim($order_note) !== ''
            ? trim($order_note)
            : 'Abonament activ/pending înlocuit de abonament nou din Membership Manager.';
        try {
            foreach (array_keys($existing_order_ids) as $existing_order_id) {
                $existing_order = wc_get_order($existing_order_id);
                if ($existing_order && $existing_order->get_status() !== 'cancelled') {
                    $changed_orders[$existing_order_id] = $existing_order->get_status();
                    $existing_order->update_status('cancelled', $effective_order_note);
                }
            }

            if ($this->table_supports_transactions($table_name)) {
                $tx_started = $wpdb->query('START TRANSACTION');
                if ($tx_started === false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Renew Replace] START TRANSACTION failed, continuing without transaction fallback.');
                    }
                } else {
                    $transaction_active = true;
                }
            }

            $membership_placeholders = implode(',', array_fill(0, count($existing_membership_ids), '%d'));
            $delete_sql = "DELETE FROM {$table_name} WHERE user_id = %d AND id IN ({$membership_placeholders})";
            $delete_args = array_merge([$user_id], $existing_membership_ids);
            $deleted = $wpdb->query($wpdb->prepare($delete_sql, ...$delete_args));
            if ($deleted === false) {
                if ($transaction_active) {
                    $wpdb->query('ROLLBACK');
                    $transaction_active = false;
                }
                throw new Exception('Nu s-au putut șterge abonamentele active/pending existente.');
            }

            if ($transaction_active) {
                $committed = $wpdb->query('COMMIT');
                if ($committed === false) {
                    $wpdb->query('ROLLBACK');
                    $transaction_active = false;
                    throw new Exception('Tranzacția de înlocuire abonamente nu a putut fi finalizată.');
                }
            }
        } catch (Exception $e) {
            if ($transaction_active) {
                $wpdb->query('ROLLBACK');
            }
            foreach ($changed_orders as $existing_order_id => $previous_status) {
                $existing_order = wc_get_order((int) $existing_order_id);
                if ($existing_order && $existing_order->get_status() === 'cancelled') {
                    $existing_order->update_status((string) $previous_status, 'Restaurare status după eșec la înlocuirea abonamentelor existente.');
                }
            }
            throw $e;
        }
    }

    private function resolve_renew_replace_targets(array $candidate_rows, bool $is_gateway_payment): array {
        if ($is_gateway_payment) {
            return [$candidate_rows, 'abonamente active/pending existente'];
        }

        $replace_rows = [];
        foreach ($candidate_rows as $candidate_row) {
            $existing_payment_key = $this->normalize_payment_method_key((string) ($candidate_row['payment_method'] ?? ''));
            if ($this->is_gateway_payment_method($existing_payment_key)) {
                $replace_rows[] = $candidate_row;
            }
        }

        return [$replace_rows, 'abonamente 7CARD/ESX active/pending'];
    }

    private function table_supports_transactions(string $table_name): bool {
        global $wpdb;

        $db_name = defined('DB_NAME') ? (string) DB_NAME : '';
        if ($db_name === '' || $table_name === '') {
            return false;
        }

        $engine = $wpdb->get_var($wpdb->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s LIMIT 1',
            $db_name,
            $table_name
        ));

        if (!is_string($engine) || $engine === '') {
            return false;
        }

        return strtoupper($engine) === 'INNODB';
    }

    private function is_gateway_copayment_context(int $order_id, string $payment_method_key, float $row_price = 0.0): bool {
        if (!$this->is_gateway_payment_method($payment_method_key)) {
            return false;
        }

        static $order_copayment_cache = [];

        if ($order_id > 0) {
            if (array_key_exists($order_id, $order_copayment_cache)) {
                return $order_copayment_cache[$order_id];
            }

            $max_price = 0.0;
            global $wpdb;
            if ($this->validator_db) {
                $table_name = $this->validator_db->get_table_name('membership_validations');
                $max_price = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(product_price) FROM {$table_name} WHERE order_id = %d",
                    $order_id
                ));
            }

            $order = wc_get_order($order_id);
            if ($order) {
                // Coplata trebuie dedusă din prețul membership-ului, nu din totalul istoric al comenzii.
                $has_copayment = ($row_price > 0) || ($max_price > 0);
                $order_copayment_cache[$order_id] = $has_copayment;
                return $has_copayment;
            }

            $has_copayment = ($row_price > 0) || ($max_price > 0);
            $order_copayment_cache[$order_id] = $has_copayment;
            return $has_copayment;
        }

        return $row_price > 0;
    }

    private function get_payment_method_title_by_key(string $payment_method): string {
        $available_methods = $this->get_available_payment_methods();
        if (isset($available_methods[$payment_method])) {
            return (string) $available_methods[$payment_method];
        }

        return ucfirst(str_replace('_', ' ', $payment_method));
    }

    
    // ============================================
    // SECTION 7: UTILITY METHODS
    // ============================================

    /**
     * Enqueue assets pentru frontend (când se folosește shortcode)
     */
    private function enqueue_frontend_assets(): void {
        static $assets_enqueued = false;
        if ($assets_enqueued) return;
        $assets_enqueued = true;
        
        wp_enqueue_script(
            'oc-admin-table-editing',
            OC_PLUGIN_URL . 'assets/admin-table-editing.js',
            ['jquery'],
            filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.js'),
            true
        );
        
        wp_localize_script('oc-admin-table-editing', 'ocAdminData', $this->get_frontend_script_localization_data());
        
        wp_enqueue_style(
            'oc-admin-table-editing',
            OC_PLUGIN_URL . 'assets/admin-table-editing.css',
            [],
            filemtime(OC_PLUGIN_DIR . 'assets/admin-table-editing.css')
        );
        
        // 🆕 Enqueue responsive fixes pentru admin
        wp_enqueue_style(
            'oc-membership-responsive-fixes',
            OC_PLUGIN_URL . 'assets/membership-responsive-fixes.css',
            ['oc-admin-table-editing'],
            filemtime(OC_PLUGIN_DIR . 'assets/membership-responsive-fixes.css')
        );
    }
    /**
     * Trimite email cu link creare/resetare parolă
     * 
     * @param int $user_id ID utilizator
     * @return bool Success
     */
    private function send_account_creation_email(int $user_id): bool {
        // 🚫 NU trimite emailuri în SYNC MODE
        if (defined('OC_SYNC_MODE') && OC_SYNC_MODE) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Generează link resetare parolă
        $reset_key = get_password_reset_key($user);
        if (is_wp_error($reset_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Email] Error generating reset key: ' . $reset_key->get_error_message());
            }
            return false;
        }
        
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
        // Compune email
        $to = $user->user_email;
        $subject = sprintf('[%s] Contul tău a fost creat', get_bloginfo('name'));
        $resolved_user_name = oc_membership_resolve_user_display_name($user);
        
        $message = sprintf(
            "Bună %s,\n\n" .
            "Un cont a fost creat pentru tine pe %s.\n\n" .
            "Detalii cont:\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "Nume: %s\n" .
            "Email: %s\n\n" .
            "Pentru a-ți seta parola și activa contul, accesează link-ul de mai jos:\n\n" .
            "%s\n\n" .
            "Link-ul este valabil 24 de ore.\n\n" .
            "După ce îți setezi parola, vei putea accesa:\n" .
            "• Dashboard-ul tău: %s\n" .
            "• Abonamentele tale\n" .
            "• Istoricul participării\n\n" .
            "Dacă nu ai solicitat crearea acestui cont, te rugăm să ignori acest email.\n\n" .
            "Cu respect,\n" .
            "Echipa %s",
            $resolved_user_name ?: 'Prieten',
            get_bloginfo('name'),
            $resolved_user_name ?: 'Prieten',
            $user->user_email,
            $reset_url,
            wc_get_page_permalink('myaccount'),
            get_bloginfo('name')
        );
        
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        return $sent;
    }

    /**
     * Returnează hash-ul PIN-ului pentru editarea abonamentelor active.
     * Acceptă și opțiunea legacy în clar, pe care o migrează automat la hash.
     */
    private function get_active_membership_edit_pin_hash(): string {
        $hash = (string) get_option('oc_membership_active_edit_pin_hash', '');
        if ($hash !== '') {
            return $hash;
        }

        $legacy_plain = (string) get_option('oc_membership_active_edit_pin', '');
        if ($legacy_plain === '') {
            return '';
        }

        $legacy_plain = trim($legacy_plain);
        if ($legacy_plain === '') {
            return '';
        }

        $migrated_hash = wp_hash_password($legacy_plain);
        update_option('oc_membership_active_edit_pin_hash', $migrated_hash, false);

        return $migrated_hash;
    }

    private function get_active_membership_edit_unlock_key(int $editor_user_id, int $target_user_id): string {
        return 'oc_active_edit_unlock_' . max(0, $editor_user_id) . '_' . max(0, $target_user_id);
    }

    private function grant_active_membership_edit_unlock(int $target_user_id, int $ttl_seconds = 600): void {
        $editor_user_id = get_current_user_id();
        if ($editor_user_id <= 0) {
            return;
        }

        if ($ttl_seconds <= 0) {
            $ttl_seconds = 600;
        }

        set_transient(
            $this->get_active_membership_edit_unlock_key($editor_user_id, $target_user_id),
            time(),
            $ttl_seconds
        );
    }

    private function has_active_membership_edit_unlock(int $target_user_id): bool {
        $editor_user_id = get_current_user_id();
        if ($editor_user_id <= 0) {
            return false;
        }

        return get_transient($this->get_active_membership_edit_unlock_key($editor_user_id, $target_user_id)) !== false;
    }

    /**
     * Log generic de eveniment în membership_validation_log.
     */
    private function log_validation_event(int $membership_id, int $user_id, string $event_type, array $metadata = [], string $validation_status = 'success'): void {
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validation_log';

        $payload = array_merge([
            'event_type' => $event_type,
            'admin_id' => get_current_user_id(),
            'admin_name' => oc_membership_resolve_user_display_name(wp_get_current_user()),
        ], $metadata);

        $wpdb->insert($table, [
            'membership_id' => $membership_id,
            'user_id' => max(0, $user_id),
            'validator_user_id' => max(0, get_current_user_id()),
            'validation_method' => 'manual',
            'validation_status' => in_array($validation_status, ['success', 'failed', 'error'], true) ? $validation_status : 'success',
            'validation_date' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'validation_metadata' => wp_json_encode($payload),
            'error_message' => '',
        ], [
            '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);
    }

    /**
     * Helper: Găsește un pachet Pool care conține Pool-ul specificat
     */
    private function find_package_for_variation($pool_id): int {
        global $wpdb;
        
        $package_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_oc_pool_id'
            AND meta_value = %d
            LIMIT 1
        ", $pool_id));

        return $package_id ? intval($package_id) : 0;
    }

    /**
     * Trimite email de notificare către admin când se creează un client nou
     * 
     * @param array $data Date client și comandă
     */
    private function send_admin_notification_email(array $data): void {
        // 🚫 NU trimite emailuri în SYNC MODE
        if (defined('OC_SYNC_MODE') && OC_SYNC_MODE) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $package = wc_get_product($data['package_id']);
        $package_name = $package ? $package->get_name() : 'Pachet #' . $data['package_id'];
        
        $subject = sprintf('[%s] Client nou creat: %s', get_bloginfo('name'), $data['client_name']);
        
        $message = sprintf(
            "Un client nou a fost creat de %s:\n\n" .
            "CLIENT:\n" .
            "• Nume: %s\n" .
            "• Email: %s\n" .
            "• Telefon: %s\n\n" .
            "ABONAMENT:\n" .
            "• Pachet: %s\n" .
            "• Comandă WooCommerce: #%d\n" .
            "• User ID: #%d\n\n" .
            "DATA CREARE: %s\n\n" .
            "---\n" .
            "Această notificare poate fi dezactivată din WordPress Admin → Membership Manager → Settings",
            oc_membership_resolve_user_display_name(wp_get_current_user()),
            $data['client_name'],
            $data['client_email'],
            $data['client_phone'],
            $package_name,
            $data['order_id'],
            $data['user_id'],
            current_time('d.m.Y H:i')
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        
        if (defined('WP_DEBUG') && WP_DEBUG && !$sent) {
            error_log('[Email Notification] Failed to send admin notification');
        }
    }
    
    /**
     * 🔄 AUTO-RESYNC: Creează automat membership-uri lipsă pentru comenzi completed
     * 
     * Această funcție se apelează automat la fiecare încărcare de pagină și:
     * - Scanează ultimele 10 comenzi completed
     * - Identifică cursurile care ACUM există în orar DAR nu au membership
     * - Creează membership-urile lipsă
     * 
     * Cazuri de utilizare:
     * - Admin adaugă un curs nou în Schedule Manager
     * - Comenzile vechi care conțineau acel curs vor primi automat membership-ul
     */
    private function auto_resync_missing_memberships(): void {
        global $wpdb;
        
        // Doar pentru admin/manager pentru performanță
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager')) {
            return;
        }
        
        // Tabele
        $posts_table = $wpdb->prefix . 'posts';
        $schedule_table = $wpdb->prefix . 'orar_cursuri';
        $memberships_table = $this->validator_db->get_table_name('membership_validations');
        
        // Găsește ultimele 10 comenzi completed (limită pentru performanță)
        $recent_orders = $wpdb->get_results("
            SELECT ID 
            FROM {$posts_table}
            WHERE post_type = 'shop_order'
            AND post_status = 'wc-completed'
            ORDER BY ID DESC
            LIMIT 10
        ", ARRAY_A);
        
        if (empty($recent_orders)) {
            return;
        }
        
        $created_count = 0;
        
        foreach ($recent_orders as $order_data) {
            $order_id = $order_data['ID'];
            $order = wc_get_order($order_id);
            
            if (!$order) {
                continue;
            }
            
            $user_id = $order->get_customer_id();
            $items = $order->get_items();
            
            foreach ($items as $item) {
                // Verifică AMBELE prefixe (backward compatibility)
                $is_oc_pool_child = wc_get_order_item_meta($item->get_id(), '_oc_pool_child', true);
                $is_mv_pack_child = wc_get_order_item_meta($item->get_id(), '_mv_pack_child', true);
                $variation_id = $item->get_variation_id();
                $order_item_id = $item->get_id();
                
                if (!($is_oc_pool_child || $is_mv_pack_child) || !$variation_id) {
                    continue;
                }
                
                // Verifică dacă cursul EXISTĂ în orar
                $in_schedule = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$schedule_table} WHERE variation_id = %d
                ", $variation_id));
                
                if (!$in_schedule) {
                    continue; // Cursul nu există în orar - skip
                }
                
                // Verifică dacă membership-ul LIPSEȘTE (cu order_item_id pentru precizie)
                $existing = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$memberships_table}
                    WHERE order_id = %d AND order_item_id = %d AND variation_id = %d
                ", $order_id, $order_item_id, $variation_id));
                
                if ($existing > 0) {
                    continue; // Membership deja există - skip
                }
                
                // CREEAZĂ MEMBERSHIP LIPSĂ!
                $activation_date_meta = trim((string) $order->get_meta('_oc_activation_date'));
                $activation_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $activation_date_meta)
                    ? $activation_date_meta
                    : $order->get_date_created()->format('Y-m-d');
                $row_timestamp = $activation_date . ' 00:00:00';

                // Unlimited: (1) gateway 7CARD/ESX sau (2) bifă VIP în Pool config.
                $pm = strtolower((string) $order->get_payment_method());
                $pm_title = strtolower((string) $order->get_payment_method_title());
                $pm_key = $this->normalize_payment_method_key($pm);
                $pm_title_key = $this->normalize_payment_method_key($pm_title);
                $is_gateway_unlimited = $this->is_gateway_payment_method($pm_key) || $this->is_gateway_payment_method($pm_title_key);
                $has_gateway_copayment = $is_gateway_unlimited && ((float) $order->get_total() > 0);
                $is_gateway_no_expiry = $is_gateway_unlimited && !$has_gateway_copayment;
                // Caută pachetul (produs principal fără variație) din comandă
                $pool_pkg_id = 0;
                foreach ($order->get_items() as $_pi) {
                    if ($_pi->get_variation_id() == 0 && $_pi->get_total() > 0) {
                        $pool_pkg_id = $_pi->get_product_id();
                        break;
                    }
                }
                $is_vip_pool_flag = $pool_pkg_id && get_post_meta($pool_pkg_id, '_oc_pool_is_unlimited', true) === 'yes';
                $is_unlimited_row = ($is_gateway_no_expiry || $is_vip_pool_flag) ? 1 : 0;

                // 7CARD/ESX = fără dată expirare; VIP Pool flag = dată expirare normală
                if ($is_gateway_no_expiry) {
                    $expiration_date = null;
                } else {
                    $default_duration_days = $this->get_default_membership_duration_days();
                    $expiration_date = $order->get_meta('_oc_expiration_date') ?: $this->add_days_to_iso_date_wp($activation_date, $default_duration_days);
                }
                
                // Obține numărul de ședințe din course_hours_config (EXACT ca în migration!)
                $variation = wc_get_product($variation_id);
                $parent_id = $variation ? $variation->get_parent_id() : 0;
                
                // Metoda 1: Din course_hours_config (configurare cursuri)
                $config = $this->validator_db->get_course_hours_config($variation_id);
                $sessions_allocated = $config ? $config['sessions_per_month'] : 0;
                
                // Metoda 2 (fallback): Din variation meta
                if (!$sessions_allocated) {
                    $sessions_allocated = get_post_meta($variation_id, '_oc_pool_sessions', true);
                }
                
                // Metoda 3 (fallback): Din parent Pool product
                if (!$sessions_allocated) {
                    $sessions_allocated = get_post_meta($parent_id, '_oc_pool_sessions', true);
                }
                
                // Default final (8 ședințe, ca în migration)
                if (!$sessions_allocated) {
                    $sessions_allocated = 8;
                }

                if ($is_unlimited_row) {
                    $sessions_allocated = (int) OC_UNLIMITED_SESSIONS;
                }
                
                // Generează UUID și token-uri OBLIGATORII
                $membership_uuid = wp_generate_uuid4();
                $qr_token = bin2hex(random_bytes(32));
                $qr_token_hash = hash('sha256', $qr_token);
                
                $inserted = $wpdb->insert(
                    $memberships_table,
                    [
                        'user_id' => $user_id,
                        'order_id' => $order_id,
                        'order_item_id' => $order_item_id,
                        'product_id' => $parent_id,
                        'variation_id' => $variation_id,
                        'membership_uuid' => $membership_uuid,
                        'qr_token_hash' => $qr_token_hash,
                        'sessions_allocated' => intval($sessions_allocated),
                        'remaining_sessions' => intval($sessions_allocated),
                        'used_sessions' => 0,
                        'is_unlimited' => $is_unlimited_row,
                        'validation_status' => 'active',
                        'start_date' => $activation_date,
                        'expiration_date' => $expiration_date,
                        'payment_status' => 'paid',
                        'created_at' => $row_timestamp,
                        'updated_at' => $row_timestamp
                    ],
                    ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
                );
                
                if ($inserted) {
                    $created_count++;
                }
            }
        }

        // Clear cache dacă s-au creat membership-uri noi
        if ($created_count > 0) {
            // Intentionally no verbose logging here.
        }
    }
    
    /**
     * 📜 Randează DIV expandabil cu ISTORIC abonamente EXPIRATE
     * 
     * Afișează TOATE abonamentele expirate ale utilizatorului în format colapsabil
     * Disponibil pentru TOȚI utilizatorii - se afișează în CARDUL EXPANDAT
     * 
     * @param int|string $user_id ID utilizator (int pentru WP users, string 'guest_XXX' pentru guest users)
     * @return string HTML
     * @since 1.5.0
     */
    private function render_expired_memberships_history($user_id, bool $is_admin = true): string {
        // Verifică dacă e guest user
        $is_guest = is_string($user_id) && strpos($user_id, 'guest_') === 0;
        
        if (!isset($this->validator_db)) {
            return '<!-- ERROR: validator_db lipsește -->';
        }
        
        // Pentru guest users, nu avem query în DB (user_id = 0)
        if ($is_guest) {
            $expired_memberships = [];
        } else {
            global $wpdb;
            $table_name = $this->validator_db->get_table_name('membership_validations');
            
            // Obține TOATE abonamentele inactive/expirate ale user-ului + numele cursurilor + statusul comenzii WC
            $expired_memberships = $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, 
                        m.display_name as cached_name,
                        m.email as cached_email,
                        m.product_name as cached_product_name,
                        m.product_price as cached_product_price,
                        m.activated_at,
                        v.post_title as variation_name,
                        o.post_status as order_post_status,
                        o.post_modified as order_modified_date
                 FROM {$table_name} m
                 LEFT JOIN {$wpdb->posts} v ON m.variation_id = v.ID
                 LEFT JOIN {$wpdb->posts} o ON m.order_id = o.ID AND m.order_id > 0
                 WHERE m.user_id = %d
                 AND m.validation_status NOT IN ('active', 'pending')
                 ORDER BY m.created_at DESC",
                $user_id
            ));
            
            if ($wpdb->last_error) {
                return '<!-- SQL ERROR: ' . esc_html($wpdb->last_error) . ' -->';
            }
        }
        
        $html = '<div class="oc-expired-history-section">';
        
        // Dacă nu există abonamente expirate, afișează mesaj
        if (empty($expired_memberships)) {
            // Header cu badge "0" (FĂRĂ inline styles)
            $html .= '<div class="oc-expired-history-header" data-user-id="' . esc_attr($user_id) . '">';
            $html .= '<div class="oc-expired-header-inner">';
            $html .= '<span class="oc-expired-icon">📜</span>';
            $html .= '<strong class="oc-expired-title">Istoric Abonamente Expirate</strong>';
            $html .= '<span class="oc-expired-badge">0</span>';
            $html .= '</div>';
            $html .= '<span class="oc-toggle-icon">▼</span>';
            $html .= '</div>';
            
            // Conținut expandabil (ascuns by default)
            $html .= '<div class="oc-expired-history-content" style="display: none;">';
            $html .= '<div class="oc-expired-empty">';
            $html .= '<span class="oc-expired-empty-icon">📭</span>';
            $html .= '<p class="oc-expired-empty-text">Nu există abonamente expirate în istoric.</p>';
            $html .= '</div>';
            $html .= '</div>'; // End expired-history-content
        } else {
            // Grupare pe pachete (order_id + start_date + expiration_date) pentru a distinge pachete diferite
            $packages = [];
            foreach ($expired_memberships as $membership) {
                $order_id = $membership->order_id ?? 0;
                $start_date = $membership->start_date ?? '';
                $expiration_date = $membership->expiration_date ?? '';
                
                // Cheie unică pentru fiecare pachet distinct
                $package_key = $order_id . '_' . $start_date . '_' . $expiration_date;
                
                if (!isset($packages[$package_key])) {
                    $packages[$package_key] = [
                        'order_id' => $order_id,
                        'product_name' => $membership->cached_product_name ?: 'Abonament Necunoscut',
                        'price' => $membership->cached_product_price,
                        'start_date' => $start_date,
                        'expiration_date' => $expiration_date,
                        'order_post_status' => $membership->order_post_status ?? '',
                        'order_modified_date' => $membership->order_modified_date ?? '',
                        'validation_status' => $membership->validation_status ?? 'expired',
                        'courses' => []
                    ];
                }
                $packages[$package_key]['courses'][] = $membership;
            }
            
            // Număr de pachete (comenzi unice)
            $total_packages = count($packages);
            
            // Header cu buton expandabil (FĂRĂ inline styles)
            $html .= '<div class="oc-expired-history-header" data-user-id="' . esc_attr($user_id) . '">';
            $html .= '<div class="oc-expired-header-inner">';
            $html .= '<span class="oc-expired-icon">📜</span>';
            $html .= '<strong class="oc-expired-title">Istoric Abonamente Expirate</strong>';
            $html .= '<span class="oc-expired-badge">' . $total_packages . '</span>';
            $html .= '</div>';
            $html .= '<span class="oc-toggle-icon">▼</span>';
            $html .= '</div>';
            
            // Conținut expandabil (ascuns by default)
            $html .= '<div class="oc-expired-history-content" style="display: none;">';
            
            // Afișează fiecare pachet cu cursurile sale
            foreach ($packages as $package) {
                $product_name = $package['product_name'];
                $price = $package['price'] ? number_format(floatval($package['price']), 2) : '0.00';
                $order_id = $package['order_id'];
                $total_courses = count($package['courses']);
                
                // 🔧 LOGICĂ: Abonamente PENDING expirate (neactivate) vs ACTIVE expirate
                $first_course = reset($package['courses']); // Primul curs pentru a accesa datele
                $created_at = $first_course->created_at ?? '';
                $activated_at = $first_course->activated_at ?? null;
                
                // Detectează dacă e PENDING expirat: activated_at NULL = nu a fost niciodată activat
                $is_pending_expired = empty($activated_at) || $activated_at === '0000-00-00 00:00:00';
                
                if ($is_pending_expired && !empty($created_at)) {
                    // PENDING expirat: Afișează data achiziției + data expirării (created_at + durata configurată)
                    $purchased_date = $this->format_date_european($created_at);
                    $default_duration_days = $this->get_default_membership_duration_days();
                    $created_date_iso = preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $created_at, $m) ? $m[1] : '';
                    $expiry_date_iso = $created_date_iso !== '' ? ($this->add_days_to_iso_date_wp($created_date_iso, $default_duration_days) ?? '') : '';
                    $expiry_date = $this->format_date_european($expiry_date_iso);
                    $date_label_1 = '📅 Achizitionat la:';
                    $date_value_1 = $purchased_date;
                    $date_label_2 = '⏰ Expirat:';
                    $date_value_2 = $expiry_date;
                } else {
                    // ACTIVE expirat: Afișează data activării + data expirării
                    $activation_date = $this->format_date_european($activated_at ?: $package['start_date']);
                    $expiration_date = $this->format_date_european($package['expiration_date']);
                    $date_label_1 = '📅 Activat la:';
                    $date_value_1 = $activation_date;
                    $date_label_2 = '⏰ Expirat:';
                    $date_value_2 = $expiration_date;
                }
                
                // Ajustează data a 2-a în funcție de statusul comenzii
                $order_post_status = $package['order_post_status'] ?? '';
                $val_status        = $package['validation_status'] ?? 'expired';
                $order_mod_fmt     = $this->format_date_european($package['order_modified_date'] ?? '');
                if ($order_post_status === 'wc-cancelled') {
                    $date_label_2 = '🚫 Anulat:';
                    $date_value_2 = $order_mod_fmt ?: $date_value_2;
                } elseif ($order_post_status === 'wc-refunded') {
                    $date_label_2 = '💸 Rambursat:';
                    $date_value_2 = $order_mod_fmt ?: $date_value_2;
                } elseif ($order_post_status === 'wc-failed') {
                    $date_label_2 = '⚠️ Eșuat:';
                    $date_value_2 = $order_mod_fmt ?: $date_value_2;
                }

                // Determină badge-ul de status
                if ($order_post_status === 'wc-cancelled') {
                    $status_icon  = '🚫';
                    $status_label = 'Anulat';
                    $status_class = 'oc-status-cancelled';
                } elseif ($order_post_status === 'wc-refunded') {
                    $status_icon  = '💸';
                    $status_label = 'Ramburs';
                    $status_class = 'oc-status-refunded';
                } elseif ($order_post_status === 'wc-failed') {
                    $status_icon  = '⚠️';
                    $status_label = 'Eșuat';
                    $status_class = 'oc-status-failed';
                } elseif ($val_status === 'inactive') {
                    $status_icon  = '⏸️';
                    $status_label = 'Inactiv';
                    $status_class = 'oc-status-inactive';
                } else {
                    $status_icon  = '❌';
                    $status_label = 'Expirat';
                    $status_class = 'oc-status-expired';
                }

                $html .= '<div class="oc-expired-membership-item">';
                
                // Header cu produs și preț (FĂRĂ inline styles)
                $html .= '<div class="oc-expired-item-header">';
                $html .= '<strong class="oc-expired-product-name">' . $status_icon . ' ' . esc_html($product_name) . '</strong>';
                $html .= '<span class="oc-expired-status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
                $html .= '<span class="oc-expired-price">' . esc_html($price) . ' RON</span>';
                $html .= '</div>';
                
                // Detalii dată (FĂRĂ inline styles)
                $html .= '<div class="oc-expired-dates-grid">';
                $html .= '<div class="oc-expired-date-item"><strong>' . $date_label_1 . '</strong> ' . esc_html($date_value_1) . '</div>';
                $html .= '<div class="oc-expired-date-item"><strong>' . $date_label_2 . '</strong> ' . esc_html($date_value_2) . '</div>';
                $html .= '</div>';
                
                // Cursuri incluse (FĂRĂ inline styles)
                $html .= '<div class="oc-expired-courses-section">';
                $html .= '<div class="oc-expired-courses-title">📚 Cursuri Incluse (' . $total_courses . '):</div>';
                
                foreach ($package['courses'] as $course) {
                    $course_name = $course->variation_name ?: 'Curs Necunoscut';
                    $sessions_used = (int)$course->used_sessions;
                    $sessions_total = (int)$course->sessions_allocated;
                    $is_unlimited_course = (int)($course->is_unlimited ?? 0) === 1;
                    $sessions_total_display = $is_unlimited_course ? 'Nelimitat' : (string) $sessions_total;
                    
                    $html .= '<div class="oc-expired-course-item">';
                    $html .= '<span class="oc-expired-course-name">🎓 ' . esc_html($course_name) . '</span>';
                    $html .= '<span class="oc-expired-course-sessions">Folosite: ' . $sessions_used . '/' . esc_html($sessions_total_display) . '</span>';
                    $html .= '</div>';
                }
                
                $html .= '</div>'; // End cursuri
                
                // Link comandă WooCommerce
                if ($order_id) {
                    $order_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                    $html .= '<div class="oc-expired-order-link">';
                    $html .= '<a href="' . esc_url($order_url) . '" target="_blank" class="oc-order-link">🛒 Comandă #' . esc_html($order_id) . '</a>';
                    $html .= '</div>';
                }
                
                $html .= '</div>'; // End oc-expired-membership-item
            } // End foreach packages
            
            $html .= '</div>'; // End expired-history-content
        } // End if empty
        
        $html .= '</div>'; // End expired-history-section
        
        return $html;
    }

}
