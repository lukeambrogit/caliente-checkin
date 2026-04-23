<?php
/**
 * Trait pentru logica prețurilor - EXTRAS din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR logica calculării prețurilor din comenzi WooCommerce
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
 * Trait OC_Membership_Pricing
 * 
 * Conține toată logica pentru calcularea prețurilor din comenzi WooCommerce
 * EXTRAS IDENTIC din class-oc-membership-shortcodes.php
 */
trait OC_Membership_Pricing {
    
    /**
     * Obține prețul real al produsului din mai multe surse
     * EXACT ca în versiunea originală - linia 2876
     */
    public function get_real_product_price(array $membership_data): string {
        $price = 0;
        $currency = get_woocommerce_currency_symbol();
        
        // 1. PREȚUL REAL PLĂTIT din ultima achiziție pentru acest product_id
        $order_debug = '';
        if (isset($membership_data['user_id']) && isset($membership_data['product_id'])) {
            $user_id = $membership_data['user_id'];
            $product_id = $membership_data['product_id'];
            
            // Pentru guest users, folosește order_id direct din membership_validations
            if ($user_id == 0 && isset($membership_data['order_id']) && $membership_data['order_id']) {
                $order = wc_get_order($membership_data['order_id']);
                $order_debug = sprintf('guest_user_order=%d', $membership_data['order_id']);
            } else {
                // Pentru users normali, găsește ultima comandă pentru acest produs
                $order = $this->get_last_order_for_product($user_id, $product_id);
                $order_debug = sprintf('last_order_for_user=%d_product=%d: %s', 
                    $user_id, $product_id, $order ? 'order_id=' . $order->get_id() : 'NOT_FOUND');
            }
            
            if ($order) {
                // Găsește item-ul specific pentru acest produs în comandă
                foreach ($order->get_items() as $item) {
                    $item_product_id = $item->get_product_id();
                    $item_variation_id = $item->get_variation_id();
                    $line_total = $item->get_total();
                    $line_subtotal = $item->get_subtotal();
                    
                    // Pentru Pool packages, verifică AMBELE: product_id și variation_id
                    $product_match = ($item_product_id == $product_id);
                    $variation_match = (isset($membership_data['variation_id']) && 
                                      $membership_data['variation_id'] && 
                                      $item_variation_id == $membership_data['variation_id']);
                    
                    if ($product_match || $variation_match) {
                        $order_debug .= sprintf(', found_item=YES(prod:%s,var:%s), item_total=%.2f, item_subtotal=%.2f',
                            $product_match ? 'YES' : 'NO',
                            $variation_match ? 'YES' : 'NO',
                            $line_total, $line_subtotal
                        );
                        
                        // Pentru Pool packages: variation are preț 0, dar ORDER TOTAL = prețul pachetului
                        if ($line_total > 0) {
                            $price = $line_total; // PREȚ CU REDUCERI
                            $order_debug .= sprintf(', using_item_total=%.2f', $price);
                        } elseif ($line_subtotal > 0) {
                            $price = $line_subtotal; // PREȚ FĂRĂ REDUCERI  
                            $order_debug .= sprintf(', using_item_subtotal=%.2f', $price);
                        } else {
                            // POOL PACKAGE: item_total = 0, verifică order total sau meta fields
                            $order_total = $order->get_total();
                            $order_subtotal = $order->get_subtotal();
                            
                            if ($order_total > 0) {
                                $price = $order_total; // PREȚUL ÎNTREGULUI PACHET
                                $order_debug .= sprintf(', using_order_total=%.2f(pool_package)', $price);
                            } elseif ($order_subtotal > 0) {
                                $price = $order_subtotal; // PREȚUL PACHETULUI FĂRĂ REDUCERI
                                $order_debug .= sprintf(', using_order_subtotal=%.2f(pool_package)', $price);
                            } else {
                                // Pentru pachete gratuite, caută prețul din meta fields ale pachetului
                                $package_price = $this->get_package_meta_price($product_id);
                                if ($package_price > 0) {
                                    $price = $package_price; // PREȚUL DIN META FIELDS
                                    $order_debug .= sprintf(', using_package_meta=%.2f(free_package)', $price);
                                } else {
                                    // Pachet GRATUIT - afișează 0.00 lei explicit
                                    $price = 0.00;
                                    $order_debug .= ', confirmed_free_package=0.00';
                                }
                            }
                        }
                        break;
                    }
                }
                
                if ($price <= 0) {
                    $order_debug .= sprintf(', no_matching_item_for_product=%d_or_variation=%d', 
                        $product_id, $membership_data['variation_id'] ?? 0);
                }
            }
        }
        
        // 2. Fallback la prețul pachetului din meta fields (pentru pachete fără order)
        if ($price <= 0 && isset($membership_data['product_id']) && $membership_data['product_id']) {
            $product_id = $membership_data['product_id'];
            
            // Folosește aceeași metodă ca pentru pachete gratuite
            $package_price = $this->get_package_meta_price($product_id);
            
            if ($package_price > 0) {
                $price = $package_price;
            } else {
                // Pachet cu adevărat gratuit sau fără preț
                $price = 0.00;
            }
        }
        
        // 3. Formatează prețul (inclusiv pentru pachete gratuite)
        if ($price >= 0) { // Include și 0.00 pentru pachete gratuite
            return number_format($price, 2, '.', '') . ' ' . $currency;
        }
        
        return 'N/A';
    }
    
    /**
     * Obține prețul pachetului din meta fields
     * EXACT ca în versiunea originală - linia 1980
     * 
     * @param int $product_id
     * @return float
     */
    public function get_package_meta_price($product_id): float {
        // Prioritizează sursele de preț pentru pachete
        $price_sources = [
            '_oc_pool_price',       // Pool Product Manager
            '_regular_price',       // WooCommerce regular
            '_sale_price',          // WooCommerce sale  
            '_price',               // WooCommerce current
            'pool_price',           // Pool meta alternativ
        ];
        
        foreach ($price_sources as $meta_key) {
            $meta_price = get_post_meta($product_id, $meta_key, true);
            if ($meta_price && (float)$meta_price > 0) {
                return (float)$meta_price;
            }
        }
        
        // Verifică și prețul din WooCommerce product object
        $product = wc_get_product($product_id);
        if ($product && $product->get_price() > 0) {
            return (float)$product->get_price();
        }
        
        return 0.00; // Pachet cu adevărat gratuit
    }
    
    /**
     * Găsește ultima comandă pentru un user și produs specific
     * EXACT ca în versiunea originală - linia 2013
     * 
     * @param int $user_id
     * @param int $product_id
     * @return WC_Order|null
     */
    public function get_last_order_for_product($user_id, $product_id): ?WC_Order {
        global $wpdb;
        
        // Pentru guest users, verifică order_id direct din membership_validations
        if ($user_id == 0) {
            return null; // Guest users sunt gestionați separat
        }
        
        // Găsește comenzile pentru user care conțin produsul specific
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT oi.order_id 
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oim.meta_key = '_product_id' 
            AND oim.meta_value = %d
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            ORDER BY p.post_date DESC
            LIMIT 10
        ", $product_id));
        
        // Verifică fiecare comandă pentru user_id
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_customer_id() == $user_id) {
                return $order; // Prima comandă găsită = cea mai recentă
            }
        }
        
        return null;
    }
    
    /**
     * Obține numele real al pachetului din comanda WooCommerce
     * EXACT ca în versiunea originală - linia 3036
     * Returnează numele pachetului principal (NU al variației Pool Product)
     */
    public function get_real_package_name_from_order(int $order_id): string {
        if (!$order_id) {
            return '';
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }

        // Iterează prin items din comandă pentru a găsi pachetul principal
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Dacă este un produs principal (nu variație), returnează numele acestuia
            if ($product_id && !$variation_id) {
                return $item->get_name();
            }
            
            // Dacă este o variație, verifică dacă nu este Pool Product
            if ($product_id && $variation_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $pool_enabled = get_post_meta($product_id, '_oc_pool_enabled', true);
                    
                    // Dacă NU este Pool Product, este probabil pachetul principal
                    if (!$pool_enabled) {
                        return $item->get_name();
                    }
                }
            }
        }

        // Fallback: returnează primul item din comandă
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            return $first_item->get_name();
        }

        return '';
    }
}
