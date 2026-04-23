<?php
/**
 * Pool Product Manager - Order Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru gestionarea comenzilor în Pool Product Manager
 */
class OC_Pool_Order {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook-uri pentru salvarea în comenzi
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_meta' ], 20, 4 );
        
        // Hook-uri pentru afișarea în comenzi
        add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_order_item_meta' ] );
        add_filter( 'woocommerce_order_item_name', [ $this, 'mark_order_items' ], 20, 3 );
        
        // Hook pentru re-order
        add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'handle_reorder' ], 20, 3 );
        
        // CSS pentru stilizarea elementelor din pachete
        add_action( 'wp_head', [ $this, 'add_child_styles' ] );
        add_action( 'admin_head', [ $this, 'add_child_styles' ] );
    }
    
    /**
     * Salvează meta-urile pachetului în order items
     *
     * @param WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param WC_Order $order
     */
    public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
        // Pentru pachete - toate meta-urile ascunse (doar în DB)
        if ( isset( $values['oc_pool'] ) && $values['oc_pool']['is_package'] ) {
            $pack_data = $values['oc_pool'];
            
            // Meta-uri ascunse pentru identificare și funcționalitate
            $item->add_meta_data( '_oc_pool_type', 'package' );
            $item->add_meta_data( '_oc_pool_pool_id', $pack_data['pool_id'] );
            $item->add_meta_data( '_oc_pool_selections_count', count( $pack_data['slots'] ) );
            
            // Salvează selecțiile (ascunse)
            foreach ( $pack_data['slots'] as $slot ) {
                $item->add_meta_data( sprintf( '_oc_pool_slot_%d_label', $slot['slot'] ), $slot['label'] );
                $item->add_meta_data( sprintf( '_oc_pool_slot_%d_variation_id', $slot['slot'] ), $slot['variation_id'] );
                
                // Salvează atributele (ascunse)
                foreach ( $slot['attributes'] as $attr_name => $attr_value ) {
                    $item->add_meta_data( 
                        sprintf( '_oc_pool_slot_%d_%s', $slot['slot'], sanitize_title( $attr_name ) ), 
                        $attr_value 
                    );
                }
            }
            
            // Trigger action pentru save
            do_action( 'oc_pool_order_item_saved', $item, $pack_data, $order );
        }
        
        // Pentru linii copil - toate ascunse
        if ( isset( $values['oc_pool_child'] ) && $values['oc_pool_child'] ) {
            $item->add_meta_data( '_oc_pool_child', 'yes' );
            $item->add_meta_data( '_oc_pool_parent_id', $values['oc_pool_parent_id'] ?? '' );
            $item->add_meta_data( '_oc_pool_variation_id', $values['oc_pool_variation_id'] ?? '' );
        }
    }
    
    /**
     * Ascunde meta-urile OC Pool din interfața admin
     *
     * @param array $hidden_meta
     * @return array
     */
    public function hide_order_item_meta( $hidden_meta ) {
        $oc_pool_meta = [
            '_oc_pool_type',
            '_oc_pool_pool_id', 
            '_oc_pool_selections_count',
            '_oc_pool_child',
            '_oc_pool_parent_id',
            '_oc_pool_variation_id'
        ];
        
        // Adaugă meta-urile dinamice pentru sloturi (până la 20 sloturi)
        for ( $i = 1; $i <= 20; $i++ ) {
            $oc_pool_meta[] = "_oc_pool_slot_{$i}_label";
            $oc_pool_meta[] = "_oc_pool_slot_{$i}_variation_id";
            
            // Atribute comune pentru cursuri/abonamente
            $common_attributes = [
                'pa_alege-tipul-de-abonament',
                'pa_niveau',
                'pa_duree', 
                'pa_intensitate',
                'pa_tip-curs',
                'pa_nivel',
                'pa_durata',
                'pa_grupa',
                'pa_zi',
                'pa_ora'
            ];
            
            foreach ( $common_attributes as $attr ) {
                $oc_pool_meta[] = "_oc_pool_slot_{$i}_{$attr}";
            }
        }
        
        return array_merge( $hidden_meta, $oc_pool_meta );
    }
    
    /**
     * Marchează și în order items (admin, emailuri, etc.)
     *
     * @param string $name
     * @param WC_Order_Item $item
     * @param bool $is_visible
     * @return string
     */
    public function mark_order_items( $name, $item, $is_visible ) {
        // Verifică dacă este un item copil din pachet
        if ( $item->get_meta( '_oc_pool_child' ) === 'yes' ) {
            $name = '<span class="oc-pool-child-item">' . $name . '</span>';
        }
        
        return $name;
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
                        
                        // Obține label-ul din meta sau recalculează
                        $label_key = "_oc_pool_slot_{$slot_num}_label";
                        $label = $item->get_meta( $label_key );
                        if ( ! $label ) {
                            $label = wc_get_formatted_variation( $variation, true, false );
                        }
                        
                        $slots[] = [
                            'slot' => $slot_num,
                            'variation_id' => $variation_id,
                            'label' => $label,
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
                
                // Log reorder
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // Debug log removed
                }
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Adaugă CSS pentru stilizarea elementelor din pachet
     */
    public function add_child_styles() {
        ?>
        <style type="text/css">
        /* Stilizare pentru elementele din pachet - UNIVERSAL */
        .oc-pool-child-item {
            display: inline-block;
            width: 100%;
            margin-left: 20px !important;
            padding-left: 15px !important;
            border-left: 3px solid #ddd !important;
            opacity: 0.8 !important;
            font-style: italic !important;
            position: relative !important;
            box-sizing: border-box !important;
        }
        
        .oc-pool-child-item:before {
            content: "└ " !important;
            color: #999 !important;
            font-weight: bold !important;
            margin-right: 5px !important;
            font-style: normal !important;
        }
        
        /* Pentru admin - stilizare specifică */
        .wp-admin .oc-pool-child-item {
            background: #f9f9f9 !important;
            padding: 5px 10px 5px 25px !important;
            margin: 2px 0 2px 20px !important;
            border-radius: 3px !important;
            border-left: 3px solid #0073aa !important;
            display: block !important;
        }
        
        .wp-admin .oc-pool-child-item:before {
            content: "• " !important;
            color: #0073aa !important;
        }
        
        /* Pentru tabele admin - mai specific */
        .wp-admin table .oc-pool-child-item {
            background: #f0f8ff !important;
            border-left: 4px solid #0073aa !important;
            padding-left: 30px !important;
            margin-left: 10px !important;
        }
        
        /* Pentru checkout - mai discret */
        .woocommerce-checkout .oc-pool-child-item {
            font-size: 0.9em !important;
            color: #666 !important;
        }
        
        /* Pentru coș */
        .woocommerce-cart .oc-pool-child-item {
            color: #777 !important;
            background: #fafafa !important;
            padding: 5px 10px 5px 25px !important;
            margin: 3px 0 !important;
            border-radius: 3px !important;
        }
        
        /* Pentru order preview și order details */
        .order_details .oc-pool-child-item,
        .woocommerce-order .oc-pool-child-item {
            background: #f9f9f9 !important;
            border-left: 3px solid #0073aa !important;
            padding: 8px 15px 8px 30px !important;
            margin: 5px 0 !important;
            border-radius: 4px !important;
            display: block !important;
        }
        
        /* Pentru emailuri - stil simplu dar vizibil */
        .mv-pack-child-item {
            text-indent: 20px !important;
        }
        
        /* Force styling pentru toate contextele */
        td .oc-pool-child-item,
        th .oc-pool-child-item,
        li .oc-pool-child-item,
        div .oc-pool-child-item {
            margin-left: 15px !important;
            padding-left: 20px !important;
            border-left: 3px solid #ccc !important;
            background: rgba(0,115,170,0.05) !important;
        }
        
        /* Compatibility cu old style names */
        .mv-pack-child-item {
            display: inline-block;
            width: 100%;
            margin-left: 20px !important;
            padding-left: 15px !important;
            border-left: 3px solid #ddd !important;
            opacity: 0.8 !important;
            font-style: italic !important;
            position: relative !important;
            box-sizing: border-box !important;
        }
        
        .mv-pack-child-item:before {
            content: "└ " !important;
            color: #999 !important;
            font-weight: bold !important;
            margin-right: 5px !important;
            font-style: normal !important;
        }
        </style>
        <?php
    }
    
    /**
     * Obține informațiile despre un pachet din order item
     *
     * @param WC_Order_Item_Product $item
     * @return array|false
     */
    public function get_package_info( $item ) {
        if ( $item->get_meta( '_oc_pool_type' ) !== 'package' ) {
            return false;
        }
        
        $pool_id = $item->get_meta( '_oc_pool_pool_id' );
        $selections_count = $item->get_meta( '_oc_pool_selections_count' );
        
        $slots = [];
        for ( $i = 1; $i <= $selections_count; $i++ ) {
            $label = $item->get_meta( "_oc_pool_slot_{$i}_label" );
            $variation_id = $item->get_meta( "_oc_pool_slot_{$i}_variation_id" );
            
            if ( $label && $variation_id ) {
                $slots[] = [
                    'slot' => $i,
                    'label' => $label,
                    'variation_id' => $variation_id
                ];
            }
        }
        
        return [
            'pool_id' => $pool_id,
            'selections_count' => $selections_count,
            'slots' => $slots
        ];
    }
    
    /**
     * Generează un raport pentru pachete din ordine
     *
     * @param WC_Order $order
     * @return array
     */
    public function get_order_packages_report( $order ) {
        $packages = [];
        
        foreach ( $order->get_items() as $item_id => $item ) {
            $package_info = $this->get_package_info( $item );
            if ( $package_info ) {
                $packages[] = [
                    'item_id' => $item_id,
                    'product_name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'package_info' => $package_info
                ];
            }
        }
        
        return $packages;
    }
    
    /**
     * Exportă datele pachetelor pentru analiză
     *
     * @param array $order_ids
     * @return array
     */
    public function export_packages_data( $order_ids ) {
        $data = [];
        
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) continue;
            
            $packages = $this->get_order_packages_report( $order );
            if ( ! empty( $packages ) ) {
                $data[] = [
                    'order_id' => $order_id,
                    'order_date' => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
                    'customer_id' => $order->get_customer_id(),
                    'packages' => $packages
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Statistici pachete pentru Site Health
     *
     * @return array
     */
    public function get_packages_stats() {
        global $wpdb;
        
        // Total pachete vândute
        $total_packages = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}woocommerce_order_itemmeta 
            WHERE meta_key = '_oc_pool_type' 
            AND meta_value = 'package'
        " );
        
        // Pachete vândute în ultima lună
        $recent_packages = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oim.meta_key = '_oc_pool_type' 
            AND oim.meta_value = 'package'
            AND p.post_date >= %s
        ", date( 'Y-m-d', strtotime( '-30 days' ) ) ) );
        
        return [
            'total_packages_sold' => intval( $total_packages ),
            'recent_packages_sold' => intval( $recent_packages )
        ];
    }
}
