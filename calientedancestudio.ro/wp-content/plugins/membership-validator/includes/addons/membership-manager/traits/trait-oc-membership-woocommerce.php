<?php
/**
 * Trait pentru integrare WooCommerce - EXTRAS din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR integrarea cu WooCommerce (orders, payment, billing)
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - PĂSTREAZĂ EXACT funcționalitățile existente
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OC_Membership_WooCommerce
 * 
 * Conține toată logica pentru integrarea cu WooCommerce
 * EXTRAS IDENTIC din class-oc-membership-shortcodes.php
 */
trait OC_Membership_WooCommerce {
    
    /**
     * Obține datele de billing din WooCommerce order
     * EXACT ca în versiunea originală - linia 2690
     */
    public function get_order_billing_data(int $order_id): array {
        if (!$order_id) {
            return $this->get_empty_billing_data();
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->get_empty_billing_data();
        }
        
        // Extrage datele de billing
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        
        return [
            'first_name' => $first_name ?: 'Guest',
            'last_name' => $last_name ?: 'User',
            'full_name' => trim($first_name . ' ' . $last_name) ?: 'Guest User',
            'email' => $email ?: 'guest@no-email.local',
            'phone' => $phone ?: '',
            'payment_method' => $this->get_order_payment_method($order),
            'payment_status' => $this->get_order_payment_status($order)
        ];
    }
    
    /**
     * Date de fallback pentru cazurile în care nu se găsește order-ul
     * EXACT ca în versiunea originală - linia 2720
     */
    public function get_empty_billing_data(): array {
        return [
            'first_name' => 'Guest',
            'last_name' => 'User',
            'full_name' => 'Guest User',
            'email' => 'guest@no-order.local',
            'phone' => '',
            'payment_method' => 'card',
            'payment_status' => 'unpaid'
        ];
    }
    
    /**
     * Obține metoda de plată din order
     * EXACT ca în versiunea originală - linia 2735
     */
    public function get_order_payment_method(WC_Order $order): string {
        return $this->normalize_payment_method_key(
            (string) $order->get_payment_method(),
            (string) $order->get_payment_method_title()
        );
    }
    
    /**
     * Obține statusul plății din order
     * EXACT ca în versiunea originală - linia 2757
     */
    public function get_order_payment_status(WC_Order $order): string {
        return $this->resolve_order_payment_status($order);
    }

    /**
     * Rezolvă statusul de plată folosind întâi statusul cerut explicit,
     * apoi starea reală WooCommerce, apoi fallback-ul din statusul comenzii.
     */
    private function resolve_order_payment_status(WC_Order $order): string {
        $requested_payment_status = sanitize_key((string) $order->get_meta('_oc_requested_payment_status'));
        if ($this->is_known_payment_status($requested_payment_status)) {
            return $requested_payment_status;
        }

        if ($order->is_paid()) {
            return 'paid';
        }

        return $this->map_order_status_to_payment($order->get_status());
    }

    /**
     * Mapează statusul WooCommerce la payment status-ul intern al pluginului.
     */
    private function map_order_status_to_payment(string $order_status): string {
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

    /**
     * Verifică dacă statusul de plată este unul suportat de plugin.
     */
    private function is_known_payment_status(string $payment_status): bool {
        return in_array($payment_status, ['paid', 'unpaid', 'partial'], true);
    }
    
    /**
     * Actualizează datele unei comenzi cu informații de plată
     * Metodă helper pentru integrarea cu tabelul admin
     */
    public function update_order_payment_info(int $order_id, array $payment_data): bool {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        try {
            // Actualizează metoda de plată dacă este furnizată
            if (isset($payment_data['payment_method'])) {
                $order->set_payment_method($payment_data['payment_method']);
            }
            
            // Actualizează statusul comenzii pe baza payment_status
            if (isset($payment_data['payment_status'])) {
                $normalized_payment_status = sanitize_key((string) $payment_data['payment_status']);
                $new_status = $this->convert_payment_status_to_order_status($normalized_payment_status);
                if ($new_status) {
                    $order->set_status($new_status);
                }

                if ($this->is_known_payment_status($normalized_payment_status)) {
                    $order->update_meta_data('_oc_requested_payment_status', $normalized_payment_status);
                }
            }
            
            $order->save();
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Convertește payment_status intern la WooCommerce order status
     */
    private function convert_payment_status_to_order_status(string $payment_status): ?string {
        switch (sanitize_key($payment_status)) {
            case 'paid':
                return 'completed';
            case 'unpaid':
            case 'partial':
                return 'on-hold';
            default:
                return null;
        }
    }
    
    /**
     * Obține informații complete despre o comandă pentru afișare în tabel
     */
    public function get_order_display_info(int $order_id): array {
        $order = wc_get_order($order_id);
        if (!$order) {
            return [
                'order_number' => 'N/A',
                'order_date' => 'N/A',
                'order_total' => 'N/A',
                'order_status' => 'N/A',
                'payment_method' => 'card',
                'payment_status' => 'unpaid'
            ];
        }
        
        return [
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'order_total' => $order->get_formatted_order_total(),
            'order_status' => $order->get_status(),
            'payment_method' => $this->get_order_payment_method($order),
            'payment_status' => $this->get_order_payment_status($order)
        ];
    }
    
    /**
     * Verifică dacă o comandă poate fi editată
     */
    public function can_edit_order(int $order_id): bool {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Comenzile completed, cancelled, refunded nu pot fi editate
        $non_editable_statuses = ['completed', 'cancelled', 'refunded', 'failed'];
        return !in_array($order->get_status(), $non_editable_statuses, true);
    }
    
    /**
     * Obține toate metodele de plată disponibile pentru dropdown
     */
    public function get_available_payment_methods(): array {
        $methods = [
            'cash' => 'Cash / Plată la studio',
            'card' => 'Card',
            'oc_7card' => '7Card',
            'oc_esx' => 'ESX',
            'transfer' => 'Transfer bancar'
        ];

        if (!function_exists('WC') || !WC() || !WC()->payment_gateways()) {
            // Fallback minim pentru admin dacă Woo nu este încă inițializat complet
            $methods['oc_7card'] = '7Card';
            $methods['oc_esx'] = 'ESX';
            return $methods;
        }

        // În admin, folosim lista completă de gateway-uri înregistrate (nu doar "available" din checkout context)
        $gateways = WC()->payment_gateways()->payment_gateways();
        
        foreach ($gateways as $gateway) {
            if (isset($gateway->enabled) && $gateway->enabled === 'yes') {
                $gateway_id = isset($gateway->id) ? (string) $gateway->id : '';
                $gateway_title = isset($gateway->title) ? (string) $gateway->title : '';
                $gateway_id_l = function_exists('mb_strtolower') ? mb_strtolower($gateway_id, 'UTF-8') : strtolower($gateway_id);
                $gateway_title_l = function_exists('mb_strtolower') ? mb_strtolower($gateway_title, 'UTF-8') : strtolower($gateway_title);

                $is_cash_alias = (
                    strpos($gateway_id_l, 'cash') !== false ||
                    strpos($gateway_id_l, 'studio') !== false ||
                    strpos($gateway_title_l, 'cash') !== false ||
                    strpos($gateway_title_l, 'numerar') !== false ||
                    strpos($gateway_title_l, 'plata la studio') !== false ||
                    strpos($gateway_title_l, 'studioul de dans') !== false
                );

                if ($is_cash_alias) {
                    $methods['cash'] = 'Cash / Plată la studio';
                    continue;
                }

                $methods[$gateway->id] = $gateway->title;
            }
        }

        // Asigură prezența metodelor custom critice pentru abonamente
        if (!isset($methods['oc_7card'])) {
            $methods['oc_7card'] = '7Card';
        }
        if (!isset($methods['oc_esx'])) {
            $methods['oc_esx'] = 'ESX';
        }
        
        return $methods;
    }

    /**
     * Normalizează metodele de plată la cheile canonice folosite de plugin.
     */
    private function normalize_payment_method_key(string $payment_method_id, string $payment_method_title = ''): string {
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

        // Fallback pentru gateway-uri noi: tratăm generic ca plată cu cardul.
        return 'card';
    }
    
}
