<?php
/**
 * Membership Validator - Sync Handler
 * 
 * @package MembershipValidator
 * @subpackage Core
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Sync
 * 
 * Gestionează sincronizarea automată a datelor cached
 * Implementare NON-INTRUZIVĂ conform .cursorrules
 */
class OC_Membership_Sync {
    
    /**
     * Database handler
     */
    private ?OC_Membership_DB $db = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inițializează hook-urile pentru sincronizare
     */
    private function init_hooks(): void {
        // Hook-uri pentru modificări utilizatori
        add_action('profile_update', [$this, 'sync_user_data'], 10, 2);
        add_action('user_register', [$this, 'sync_new_user_data'], 10, 1);
        add_action('updated_user_meta', [$this, 'sync_user_meta'], 10, 4);
        
        // Hook-uri pentru modificări WooCommerce
        add_action('woocommerce_order_status_changed', [$this, 'sync_order_status'], 10, 4);
        add_action('woocommerce_payment_complete', [$this, 'sync_payment_complete'], 10, 1);
        add_action('woocommerce_order_refunded', [$this, 'sync_order_refunded'], 10, 2);
        
        // Hook-uri pentru ștergere comenzi (multiple pentru compatibilitate)
        add_action('woocommerce_delete_order', [$this, 'sync_order_deleted'], 10, 1);
        add_action('before_delete_post', [$this, 'sync_order_deleted_before'], 10, 1);
        add_action('delete_post', [$this, 'sync_order_deleted_after'], 10, 1);
        add_action('wp_trash_post', [$this, 'sync_order_trashed'], 10, 1);
        
        // Hook-uri pentru modificări produse
        add_action('woocommerce_product_object_updated', [$this, 'sync_product_updated'], 10, 1);
        add_action('save_post_product', [$this, 'sync_post_updated'], 10, 2);
        
        // Obține referința la DB
        add_action('wp_loaded', [$this, 'init_db_reference'], 5);
    }
    
    /**
     * Inițializează referința către DB
     */
    public function init_db_reference(): void {
        $validator = OC_Membership_Validator::get_instance();
        if ($validator && $validator->get_db()) {
            $this->db = $validator->get_db();
        }
    }
    
    /**
     * Sincronizează datele utilizatorului când se actualizează profilul
     */
    public function sync_user_data(int $user_id, WP_User $old_user_data): void {
        if (!$this->db) return;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Actualizează datele cached pentru acest user
        $wpdb->update(
            $table_name,
            [
                'display_name' => oc_membership_resolve_user_display_name($user),
                'email' => $user->user_email,
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['user_id' => $user_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Sincronizează când se creează un utilizator nou
     */
    public function sync_new_user_data(int $user_id): void {
        if (!$this->db) return;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        // Nu face nimic special pentru utilizatori noi
        // Sincronizarea se va face când se creează membership-ul
    }
    
    /**
     * Sincronizează meta data utilizatorului
     */
    public function sync_user_meta(int $meta_id, int $user_id, string $meta_key, $meta_value): void {
        if (!$this->db) return;
        
        // Doar pentru anumite meta keys relevante
        $relevant_keys = ['phone', 'billing_phone', 'member_discount_coupon', 'last_attendance_date'];
        if (!in_array($meta_key, $relevant_keys)) return;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        $update_data = ['cached_data_synced_at' => current_time('mysql')];
        
        // Mapează meta key la coloana cached
        switch ($meta_key) {
            case 'phone':
            case 'billing_phone':
                $update_data['phone'] = (string) $meta_value;
                break;
            case 'member_discount_coupon':
                $update_data['member_discount'] = (string) $meta_value;
                break;
            case 'last_attendance_date':
                $update_data['last_attendance'] = (string) $meta_value;
                break;
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            ['user_id' => $user_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
    }
    
    /**
     * Sincronizează când se schimbă statusul comenzii
     */
    public function sync_order_status(int $order_id, string $from_status, string $to_status, WC_Order $order): void {
        if (!$this->db) {
            return;
        }
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Actualizează payment status pe baza noului status order
        $payment_status = $this->resolve_order_payment_status($order);
        
        // Pentru fiecare record din acest order, fă sync complet
        $order_records = $wpdb->get_results($wpdb->prepare("
            SELECT id FROM {$table_name} WHERE order_id = %d
        ", $order_id));
        
        foreach ($order_records as $record) {
            // Obține membership complet
            $membership = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$table_name} WHERE id = %d
            ", $record->id));
            
            if (!$membership) continue;
            
            // Colectează datele cached pentru acest membership
            $cached_data = [];
            
            // Date produs din order
            if ($membership->order_id > 0) {
                $order = wc_get_order($membership->order_id);
                if ($order) {
                    // Găsește pachetul real (nu pool product)
                    $real_package_name = $this->get_real_package_name_from_order($membership->order_id);
                    if ($real_package_name && $real_package_name !== 'N/A') {
                        $cached_data['product_name'] = $real_package_name;
                    }
                    
                    // Găsește cursurile
                    $courses = $this->get_courses_from_order($order, $membership);
                    if ($courses && $courses !== 'N/A') {
                        $cached_data['courses_included'] = $courses;
                    }
                    
                    // Payment method - metoda corectă de plată
                    $cached_data['payment_method'] = $this->normalize_payment_method_key(
                        (string) $order->get_payment_method(),
                        (string) $order->get_payment_method_title()
                    );
                    
                    // Member discount - verifică cupoanele folosite
                    $coupons = $order->get_coupons();
                    if (!empty($coupons)) {
                        $coupon_codes = [];
                        foreach ($coupons as $coupon_item) {
                            $coupon_codes[] = $coupon_item->get_code();
                        }
                        $cached_data['member_discount'] = implode(', ', $coupon_codes);
                    }
                    
                    $cached_data['product_price'] = number_format(oc_membership_resolve_order_package_price($order), 2);
                }
            }
            
            // Actualizează cu datele complete
            $wpdb->update(
                $table_name,
                array_merge($cached_data, [
                    'payment_status' => $payment_status,
                    'cached_data_synced_at' => current_time('mysql')
                ]),
                ['id' => $record->id],
                array_fill(0, count($cached_data) + 2, '%s'),
                ['%d']
            );
        }
    }
    
    /**
     * Sincronizează când se șterge o comandă WooCommerce
     */
    public function sync_order_deleted(int $order_id): void {
        static $processed_order_ids = [];

        if (!$this->db) return;
        if ($order_id <= 0 || isset($processed_order_ids[$order_id])) return;

        $processed_order_ids[$order_id] = true;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Șterge toate membership-urile asociate cu această comandă
        $wpdb->delete(
            $table_name,
            ['order_id' => $order_id],
            ['%d']
        );
    }
    
    /**
     * Sincronizează când se șterge o comandă (before_delete_post hook)
     */
    public function sync_order_deleted_before(int $post_id): void {
        // Verifică dacă este o comandă WooCommerce
        if (get_post_type($post_id) === 'shop_order') {
            $this->sync_order_deleted($post_id);
        }
    }
    
    /**
     * Sincronizează când se șterge o comandă (delete_post hook)
     */
    public function sync_order_deleted_after(int $post_id): void {
        // Verifică dacă este o comandă WooCommerce
        if (get_post_type($post_id) === 'shop_order') {
            $this->sync_order_deleted($post_id);
        }
    }
    
    /**
     * Sincronizează când se pune o comandă în trash (wp_trash_post hook)
     */
    public function sync_order_trashed(int $post_id): void {
        // Verifică dacă este o comandă WooCommerce
        if (get_post_type($post_id) === 'shop_order') {
            $this->sync_order_deleted($post_id);
        }
    }
    
    /**
     * Sincronizează când se completează plata
     */
    public function sync_payment_complete(int $order_id): void {
        if (!$this->db) return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Actualizează payment method și status
        $payment_method = $this->normalize_payment_method_key(
            (string) $order->get_payment_method(),
            (string) $order->get_payment_method_title()
        );
        $is_gateway_unlimited = $this->is_unlimited_payment_method((string) $order->get_payment_method(), (string) $order->get_payment_method_title());
        $is_pool_unlimited = $this->is_pool_unlimited_order($order);
        
        $wpdb->update(
            $table_name,
            [
                'payment_method' => $payment_method,
                'payment_status' => 'paid',
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($is_gateway_unlimited) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET expiration_date = NULL,
                     is_unlimited = 1,
                     cached_data_synced_at = %s
                 WHERE order_id = %d",
                current_time('mysql'),
                $order_id
            ));
        } elseif ($is_pool_unlimited) {
            // VIP din Pool: sesiuni nelimitate, dar expirarea rămâne cea existentă (manual/normală).
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET is_unlimited = 1,
                     cached_data_synced_at = %s
                 WHERE order_id = %d",
                current_time('mysql'),
                $order_id
            ));
        }
    }
    
    /**
     * Sincronizează când se face refund
     */
    public function sync_order_refunded(int $order_id, int $refund_id): void {
        if (!$this->db) return;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        $wpdb->update(
            $table_name,
            [
                'payment_status' => 'unpaid',
                'validation_status' => 'cancelled',
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Sincronizează când se actualizează un produs
     */
    public function sync_product_updated(WC_Product $product): void {
        if (!$this->db) return;
        
        $product_id = $product->get_id();
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Actualizează product_name pentru toate membership-urile cu acest produs
        $wpdb->update(
            $table_name,
            [
                'product_name' => $product->get_name(),
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['product_id' => $product_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Sincronizează când se actualizează un post (poate fi și un produs)
     */
    public function sync_post_updated(int $post_id, WP_Post $post): void {
        if (!$this->db) return;
        
        // Doar pentru produse WooCommerce
        if ($post->post_type !== 'product') return;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Actualizează product_name
        $wpdb->update(
            $table_name,
            [
                'product_name' => $post->post_title,
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['product_id' => $post_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Helper: găsește numele real al pachetului din order (nu pool product)
     */
    private function get_real_package_name_from_order(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) return 'N/A';
        
        // Primul pas: caută produsul principal cu preț > 0
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $total = floatval($item->get_total());
            
            // Caută produsul principal cu preț > 0 (pachetul)
            if ($total > 0 && $variation_id == 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    return $product->get_name();
                }
            }
        }
        
        // Al doilea pas: pentru order-uri gratuite, caută primul produs fără variație
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Pentru produse gratuite, ia primul produs principal (nu variație)
            if ($variation_id == 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    // Exclude pool products (ID 48401)
                    if ($product_id != 48401) {
                        return $product->get_name();
                    }
                }
            }
        }
        
        return 'N/A';
    }
    
    /**
     * Helper: găsește cursurile din order
     */
    private function get_courses_from_order($order, $membership): string {
        $courses = [];
        
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            
            // Caută variațiile (cursurile)
            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation_name = $variation->get_name();
                    
                    // Extrage numele cursului (după " - ")
                    if (strpos($variation_name, ' - ') !== false) {
                        $course_name = trim(substr($variation_name, strpos($variation_name, ' - ') + 3));
                        $courses[] = $course_name;
                    }
                }
            }
        }
        
        return !empty($courses) ? implode(', ', $courses) : 'N/A';
    }
    
    // Metoda get_package_price_from_order() ștearsă - folosim direct $order->get_total()
    
    /**
     * Helper: mapează statusul comenzii la payment status
     */
    private function map_order_status_to_payment(string $order_status): string {
        return oc_membership_map_order_status_to_payment($order_status);
    }

    private function resolve_order_payment_status(WC_Order $order): string {
        return oc_membership_resolve_order_payment_status($order);
    }
    
    /**
     * Forțează sincronizarea pentru un user specific
     */
    public function force_sync_user(int $user_id): bool {
        if (!$this->db) return false;
        
        $user = get_userdata($user_id);
        if (!$user) return false;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        // Colectează toate datele fresh
        $phone = get_user_meta($user_id, 'billing_phone', true) ?: get_user_meta($user_id, 'phone', true);
        $member_discount = get_user_meta($user_id, 'member_discount_coupon', true);
        $last_attendance = get_user_meta($user_id, 'last_attendance_date', true);
        
        $updated = $wpdb->update(
            $table_name,
            [
                'display_name' => oc_membership_resolve_user_display_name($user),
                'email' => $user->user_email,
                'phone' => $phone ?: '',
                'member_discount' => $member_discount ?: '',
                'last_attendance' => $last_attendance ?: '',
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['user_id' => $user_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        return $updated !== false;
    }
    
    /**
     * Forțează sincronizarea pentru o comandă specifică
     */
    public function force_sync_order(int $order_id): bool {
        if (!$this->db) return false;
        
        $order = wc_get_order($order_id);
        if (!$order) return false;
        
        global $wpdb;
        $table_name = $this->db->get_table_name('membership_validations');
        
        $payment_method = $this->normalize_payment_method_key(
            (string) $order->get_payment_method(),
            (string) $order->get_payment_method_title()
        );
        $payment_status = $this->map_order_status_to_payment($order->get_status());
        $uses_gateway_payment = $this->is_unlimited_payment_method((string) $order->get_payment_method(), (string) $order->get_payment_method_title());
        $has_gateway_copayment = $uses_gateway_payment && $this->has_gateway_copayment_for_order($order_id, $order);
        $is_gateway_unlimited = $uses_gateway_payment && !$has_gateway_copayment;
        $is_pool_unlimited = $this->is_pool_unlimited_order($order);
        
        $updated = $wpdb->update(
            $table_name,
            [
                'payment_method' => $payment_method,
                'payment_status' => $payment_status,
                'cached_data_synced_at' => current_time('mysql')
            ],
            ['order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($is_gateway_unlimited) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET expiration_date = NULL,
                     is_unlimited = 1,
                     cached_data_synced_at = %s
                 WHERE order_id = %d",
                current_time('mysql'),
                $order_id
            ));
        } elseif ($is_pool_unlimited) {
            // VIP din Pool: sesiuni nelimitate, dar expirarea rămâne cea existentă (manual/normală).
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name}
                 SET is_unlimited = 1,
                     cached_data_synced_at = %s
                 WHERE order_id = %d",
                current_time('mysql'),
                $order_id
            ));
        }
        
        return $updated !== false;
    }

    private function is_unlimited_payment_method(string $payment_method_id, string $payment_method_title): bool {
        return oc_membership_is_gateway_payment_method($payment_method_id, $payment_method_title);
    }

    private function has_gateway_copayment_for_order(int $order_id, \WC_Order $order): bool {
        global $wpdb;

        $max_price = 0.0;
        if ($this->db) {
            $table_name = $this->db->get_table_name('membership_validations');
            $max_price = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(product_price) FROM {$table_name} WHERE order_id = %d",
                $order_id
            ));
        }

        if ($max_price > 0) {
            return true;
        }

        return oc_membership_resolve_order_package_price($order) > 0;
    }

    /**
     * Normalizează metodele de plată la cheile canonice folosite în plugin.
     */
    private function normalize_payment_method_key(string $payment_method_id, string $payment_method_title = ''): string {
        return oc_membership_normalize_payment_method_key($payment_method_id, $payment_method_title);
    }

    private function is_pool_unlimited_order(\WC_Order $order): bool {
        foreach ($order->get_items() as $item) {
            // Produs principal de pachet (nu variație)
            if ((int) $item->get_variation_id() === 0 && (float) $item->get_total() > 0) {
                $product_id = (int) $item->get_product_id();
                if ($product_id > 0 && get_post_meta($product_id, '_oc_pool_is_unlimited', true) === 'yes') {
                    return true;
                }
                break;
            }
        }

        return false;
    }
}
