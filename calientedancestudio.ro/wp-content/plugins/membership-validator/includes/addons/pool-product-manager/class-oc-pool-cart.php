<?php
/**
 * Pool Product Manager - Cart Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru gestionarea coșului în Pool Product Manager
 */
class OC_Pool_Cart {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook-uri pentru validare add-to-cart
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 20, 5 );
        
        // Hook-uri pentru manipularea cart item data
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 20, 3 );
        add_action( 'woocommerce_add_to_cart', [ $this, 'add_child_items' ], 20, 6 );
        
        // Hook-uri pentru setarea prețurilor
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_package_price' ], 15 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_child_prices' ], 20 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_child_prices_zero' ], 25 );
        
        // Hook-uri pentru sincronizare și integritate
        add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'sync_child_quantities' ], 20, 4 );
        add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'prevent_child_removal' ], 20, 2 );
        add_action( 'woocommerce_remove_cart_item', [ $this, 'remove_child_items' ], 20, 2 );
        add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'cleanup_orphaned_children' ] );
        
        // Hook-uri pentru afișare în coș
        add_filter( 'woocommerce_widget_cart_item_quantity', [ $this, 'hide_child_widget_quantity' ], 10, 3 );
        add_filter( 'woocommerce_cart_item_quantity', [ $this, 'hide_child_quantity_input' ], 10, 3 );
        add_filter( 'woocommerce_cart_item_price', [ $this, 'hide_child_price' ], 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'hide_child_subtotal' ], 10, 3 );
        add_filter( 'woocommerce_cart_contents_count', [ $this, 'exclude_children_from_count' ] );
        add_filter( 'woocommerce_widget_cart_item_visible', [ $this, 'hide_children_from_widget' ], 10, 3 );
        add_filter( 'woocommerce_cart_item_name', [ $this, 'mark_child_items' ], 20, 3 );
        
        // Hook-uri pentru afișarea meta-urilor
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 20, 2 );
    }
    
    /**
     * Validează adăugarea în coș pentru pachete
     *
     * @param bool $passed
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param array $variations
     * @return bool
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
        if ( ! oc_pool_is_package( $product_id ) ) return $passed;
        
        $config = oc_pool_get_package_config( $product_id );
        if ( ! $config ) return $passed;
        
        $pool_id = $config['pool_id'];
        $min_selections = max( 1, intval( $config['min_selections'] ) );
        $max_selections = intval( $config['max_selections'] );
        
        $selections = isset( $_POST['oc_pool_selections'] ) ? array_filter( (array) $_POST['oc_pool_selections'] ) : [];
        $quantity = max( 1, intval( $quantity ) );
        
        // Validare număr selecții
        if ( count( $selections ) < $min_selections ) {
            wc_add_notice( sprintf( 'Selectează cel puțin %d opțiuni.', $min_selections ), 'error' );
            return false;
        }
        
        if ( $max_selections && count( $selections ) > $max_selections ) {
            wc_add_notice( sprintf( 'Poți selecta maximum %d opțiuni.', $max_selections ), 'error' );
            return false;
        }
        
        // Validare duplicate în același pachet (doar dacă qty = 1)
        if ( $quantity == 1 && count( $selections ) !== count( array_unique( $selections ) ) ) {
            wc_add_notice( 'Opțiunile selectate trebuie să fie diferite în cadrul aceluiași pachet.', 'error' );
            return false;
        }
        
        // Validare că toate selecțiile sunt variații valide din POOL ȘI selectate în admin
        $pool_product = wc_get_product( $pool_id );
        if ( ! $pool_product ) {
            wc_add_notice( 'Produsul POOL nu este disponibil.', 'error' );
            return false;
        }
        
        // Obține variațiile selectate în admin pentru acest pachet
        $selected_variation_ids = $config['selected_variations'];
        if ( ! is_array( $selected_variation_ids ) ) {
            $selected_variation_ids = [];
        }
        
        // Variații valide = sunt în POOL ȘI sunt selectate în admin ȘI sunt active/purchasable
        $all_pool_variations = $pool_product->get_available_variations();
        $valid_variation_ids = array_map( function($v) { 
            return $v['variation_id']; 
        }, array_filter( $all_pool_variations, function($v) use ($selected_variation_ids) {
            return in_array( $v['variation_id'], $selected_variation_ids ) &&
                   $v['is_purchasable'] && 
                   $v['variation_is_active'];
        }));
        
        foreach ( $selections as $variation_id ) {
            if ( ! in_array( intval( $variation_id ), $valid_variation_ids ) ) {
                wc_add_notice( 'Una din selecțiile tale nu mai este disponibilă sau nu este inclusă în acest pachet.', 'error' );
                return false;
            }
        }
        
        return $passed;
    }
    
    /**
     * Adaugă meta data la cart item pentru pachet
     *
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( ! oc_pool_is_package( $product_id ) ) return $cart_item_data;
        
        $config = oc_pool_get_package_config( $product_id );
        if ( ! $config ) return $cart_item_data;
        
        $selections = isset( $_POST['oc_pool_selections'] ) ? array_filter( (array) $_POST['oc_pool_selections'] ) : [];
        $pool_id = $config['pool_id'];
        
        if ( ! empty( $selections ) && $pool_id ) {
            $cart_item_data['oc_pool'] = [
                'pool_id' => $pool_id,
                'selections' => array_map( 'intval', $selections ),
                'is_package' => true
            ];
            
            // Adaugă și informații despre selecții pentru afișare
            $slots = [];
            foreach ( $selections as $i => $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation ) {
                    $slots[] = [
                        'slot' => $i + 1,
                        'variation_id' => $variation_id,
                        'label' => wc_get_formatted_variation( $variation, true, false ),
                        'attributes' => $variation->get_attributes()
                    ];
                }
            }
            $cart_item_data['oc_pool']['slots'] = $slots;
        }
        
        return $cart_item_data;
    }
    
    /**
     * Adaugă linii copil în coș după adăugarea pachetului
     *
     * @param string $cart_item_key
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param array $variation
     * @param array $cart_item_data
     */
    public function add_child_items( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( ! isset( $cart_item_data['oc_pool'] ) ) return;
        
        $pack_data = $cart_item_data['oc_pool'];
        $pool_id = $pack_data['pool_id'];
        $selections = $pack_data['selections'];
        
        // Adaugă o linie copil pentru fiecare selecție
        foreach ( $selections as $selected_variation_id ) {
            WC()->cart->add_to_cart( 
                $pool_id,                    // Product ID (POOL)
                $quantity,                   // Quantity (sincronizată cu pachetul)
                $selected_variation_id,      // Variation ID
                [],                          // Variation attributes
                [                            // Cart item data
                    'oc_pool_child' => true,
                    'oc_pool_parent_key' => $cart_item_key,
                    'oc_pool_parent_id' => $product_id,
                    'oc_pool_variation_id' => $selected_variation_id
                ]
            );
        }
    }
    
    /**
     * Setează prețul fix pentru pachet
     *
     * @param WC_Cart $cart
     */
    public function set_package_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['oc_pool'] ) && $cart_item['oc_pool']['is_package'] ) {
                $product_id = $cart_item['product_id'];
                $config = oc_pool_get_package_config( $product_id );
                $pack_price = $config ? $config['price'] : '';
                
                if ( ! $pack_price ) {
                    $product = wc_get_product( $product_id );
                    $pack_price = $product ? $product->get_regular_price() : 0;
                }
                
                $cart_item['data']->set_price( floatval( $pack_price ) );
            }
        }
    }
    
    /**
     * Setează prețul la 0 pentru liniile copil
     *
     * @param WC_Cart $cart
     */
    public function set_child_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
                $cart_item['data']->set_price( 0 );
            }
        }
    }
    
    /**
     * Setează prețul la 0 pentru liniile copil în calculele finale
     *
     * @param WC_Cart $cart
     */
    public function set_child_prices_zero( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
                $cart_item['data']->set_price( 0 );
            }
        }
    }
    
    /**
     * Sincronizează cantitatea liniilor copil când se modifică cantitatea pachetului
     *
     * @param string $cart_item_key
     * @param int $quantity
     * @param int $old_quantity
     * @param WC_Cart $cart
     */
    public function sync_child_quantities( $cart_item_key, $quantity, $old_quantity, $cart ) {
        $cart_item = $cart->get_cart_item( $cart_item_key );
        
        // Dacă este un pachet, sincronizează copiii
        if ( isset( $cart_item['oc_pool'] ) && $cart_item['oc_pool']['is_package'] ) {
            foreach ( $cart->get_cart() as $child_key => $child_item ) {
                if ( isset( $child_item['oc_pool_parent_key'] ) && $child_item['oc_pool_parent_key'] === $cart_item_key ) {
                    $cart->set_quantity( $child_key, $quantity, false );
                }
            }
        }
    }
    
    /**
     * Previne ștergerea individuală a liniilor copil
     *
     * @param string $link
     * @param string $cart_item_key
     * @return string
     */
    public function prevent_child_removal( $link, $cart_item_key ) {
        $cart_item = WC()->cart->get_cart_item( $cart_item_key );
        
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return '<span class="oc-pool-child-notice" title="Această linie face parte dintr-un pachet">🔒</span>';
        }
        
        return $link;
    }
    
    /**
     * Șterge liniile copil când se șterge pachetul
     *
     * @param string $cart_item_key
     * @param WC_Cart $cart
     */
    public function remove_child_items( $cart_item_key, $cart ) {
        $cart_item = $cart->get_cart_item( $cart_item_key );
        
        // Dacă se șterge un pachet, șterge și copiii
        if ( isset( $cart_item['oc_pool'] ) && $cart_item['oc_pool']['is_package'] ) {
            foreach ( $cart->get_cart() as $child_key => $child_item ) {
                if ( isset( $child_item['oc_pool_parent_key'] ) && $child_item['oc_pool_parent_key'] === $cart_item_key ) {
                    $cart->remove_cart_item( $child_key );
                }
            }
        }
    }
    
    /**
     * Repară liniile copil orfane (cleanup)
     *
     * @param WC_Cart $cart
     */
    public function cleanup_orphaned_children( $cart ) {
        $package_keys = [];
        $orphaned_keys = [];
        
        // Identifică pachetele existente
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( isset( $item['oc_pool'] ) && $item['oc_pool']['is_package'] ) {
                $package_keys[] = $key;
            }
        }
        
        // Identifică copiii orfani
        foreach ( $cart->get_cart() as $key => $item ) {
            if ( isset( $item['oc_pool_child'] ) && $item['oc_pool_child'] ) {
                $parent_key = $item['oc_pool_parent_key'] ?? '';
                if ( ! in_array( $parent_key, $package_keys ) ) {
                    $orphaned_keys[] = $key;
                }
            }
        }
        
        // Șterge copiii orfani
        foreach ( $orphaned_keys as $key ) {
            $cart->remove_cart_item( $key );
        }
    }
    
    /**
     * Ascunde cantitatea pentru liniile copil în toate widget-urile
     *
     * @param string $quantity_html
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function hide_child_widget_quantity( $quantity_html, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return '<span class="quantity">-</span>';
        }
        return $quantity_html;
    }
    
    /**
     * Ascunde input-ul de cantitate pentru liniile copil
     *
     * @param string $product_quantity
     * @param string $cart_item_key
     * @param array $cart_item
     * @return string
     */
    public function hide_child_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return '<span class="product-quantity">-</span>';
        }
        return $product_quantity;
    }
    
    /**
     * Ascunde prețul pentru liniile copil
     *
     * @param string $price
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function hide_child_price( $price, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return '<span class="amount">-</span>';
        }
        return $price;
    }
    
    /**
     * Ascunde subtotalul pentru liniile copil
     *
     * @param string $subtotal
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function hide_child_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return '<span class="amount">-</span>';
        }
        return $subtotal;
    }
    
    /**
     * Exclude variațiile din contorul de produse în coș
     *
     * @param int $count
     * @return int
     */
    public function exclude_children_from_count( $count ) {
        $cart = WC()->cart;
        if ( ! $cart ) return $count;
        
        $adjusted_count = 0;
        foreach ( $cart->get_cart() as $cart_item ) {
            // Numără doar produsele care NU sunt variații copil
            if ( ! isset( $cart_item['oc_pool_child'] ) || ! $cart_item['oc_pool_child'] ) {
                $adjusted_count += $cart_item['quantity'];
            }
        }
        
        return $adjusted_count;
    }
    
    /**
     * Exclude variațiile din widget-urile coșului modal
     *
     * @param bool $visible
     * @param array $cart_item
     * @param string $cart_item_key
     * @return bool
     */
    public function hide_children_from_widget( $visible, $cart_item, $cart_item_key ) {
        // Ascunde variațiile copil din widget-urile coșului
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            return false;
        }
        return $visible;
    }
    
    /**
     * Marchează vizual liniile copil în coș
     *
     * @param string $name
     * @param array $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function mark_child_items( $name, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
            // Stilizare discretă - indent și culoare mai estompată
            $name = '<span class="oc-pool-child-item">' . $name . '</span>';
        }
        
        return $name;
    }
    
    /**
     * Afișează meta-urile în coș (opțional - dezactivat pentru a fi complet ascuns)
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        // Meta-urile sunt ascunse - nu afișăm nimic
        // Datele rămân în backend pentru funcționalitate
        return $item_data;
    }
    
    /**
     * Suport pentru re-order cu reconstruirea pachetelor
     *
     * @param array $cart_item_data
     * @param WC_Order_Item $item
     * @param WC_Order $order
     * @return array
     */
    public function handle_reorder( $cart_item_data, $item, $order ) {
        // Doar pentru pachete în reorder
        if ( $item->get_meta( '_oc_pool_type' ) === 'package' ) {
            $pool_id = $item->get_meta( '_oc_pool_pool_id' );
            $pool_product = $pool_id ? wc_get_product( $pool_id ) : null;
            
            if ( ! $pool_product ) {
                wc_add_notice( 'Un pachet din comanda anterioară nu mai poate fi reconstruit (POOL indisponibil).', 'notice' );
                return $cart_item_data;
            }
            
            // Reconstruiește selecțiile din meta
            $selections = [];
            $slots = [];
            
            foreach ( $item->get_meta_data() as $meta ) {
                $key = $meta->get_data()['key'];
                $value = $meta->get_data()['value'];
                
                if ( preg_match( '/^_oc_pool_slot_(\d+)_variation_id$/', $key, $matches ) ) {
                    $slot_num = intval( $matches[1] );
                    $variation_id = intval( $value );
                    
                    // Verifică dacă variația mai există și e cumpărabilă
                    $variation = wc_get_product( $variation_id );
                    if ( $variation && $variation->is_purchasable() ) {
                        $selections[] = $variation_id;
                        $slots[] = [
                            'slot' => $slot_num,
                            'variation_id' => $variation_id,
                            'label' => wc_get_formatted_variation( $variation, true, false ),
                            'attributes' => $variation->get_attributes()
                        ];
                    } else {
                        wc_add_notice( sprintf( 'O selecție din pachetul anterior (Slot %d) nu mai este disponibilă.', $slot_num ), 'notice' );
                    }
                }
            }
            
            if ( ! empty( $selections ) ) {
                $cart_item_data['oc_pool'] = [
                    'pool_id' => $pool_id,
                    'selections' => $selections,
                    'slots' => $slots,
                    'is_package' => true
                ];
            }
        }
        
        return $cart_item_data;
    }
}
