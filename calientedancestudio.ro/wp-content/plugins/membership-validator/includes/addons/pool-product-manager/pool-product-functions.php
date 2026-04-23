<?php
/**
 * Pool Product Manager - Helper Functions
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// Funcții Helper Principale
// =============================================================================

/**
 * Verifică dacă un produs este pachet
 *
 * @param int $product_id
 * @return bool
 */
function oc_pool_is_package( $product_id ) {
    // Verifică noul format
    $enabled = get_post_meta( $product_id, '_oc_pool_enabled', true );
    if ( $enabled ) {
        return true;
    }
    
    // Compatibility cu formatul vechi
    $old_enabled = get_post_meta( $product_id, '_mv_pack_enabled', true );
    return ! empty( $old_enabled );
}

/**
 * Obține configurația unui pachet
 *
 * @param int $package_id
 * @return array|false
 */
function oc_pool_get_package_config( $package_id ) {
    if ( ! oc_pool_is_package( $package_id ) ) {
        return false;
    }
    
    // Încearcă din noul format (cu suport dual mode)
    $config = [
        'enabled' => get_post_meta( $package_id, '_oc_pool_enabled', true ),
        'price' => get_post_meta( $package_id, '_oc_pool_price', true ),
        'pool_id' => get_post_meta( $package_id, '_oc_pool_pool_id', true ),
        'min_selections' => get_post_meta( $package_id, '_oc_pool_min_selections', true ),
        'max_selections' => get_post_meta( $package_id, '_oc_pool_max_selections', true ),
        'ui_style' => get_post_meta( $package_id, '_oc_pool_ui_style', true ),
        'allow_duplicates' => get_post_meta( $package_id, '_oc_pool_allow_duplicates', true ),
        'helper_text' => get_post_meta( $package_id, '_oc_pool_helper_text', true ),
        'selected_variations' => get_post_meta( $package_id, '_oc_pool_selected_variations', true ),
        'allowed_payment_gateways' => get_post_meta( $package_id, '_oc_pool_allowed_payment_gateways', true ),
        
        // DUAL MODE - opțional, pentru backward compatibility
        'dual_mode' => get_post_meta( $package_id, '_oc_pool_dual_mode', true ),
        'pool1_id' => get_post_meta( $package_id, '_oc_pool_pool1_id', true ),
        'pool2_id' => get_post_meta( $package_id, '_oc_pool_pool2_id', true ),
        'pool1_label' => get_post_meta( $package_id, '_oc_pool_pool1_label', true ),
        'pool2_label' => get_post_meta( $package_id, '_oc_pool_pool2_label', true ),
        'pool1_variations' => get_post_meta( $package_id, '_oc_pool_pool1_variations', true ),
        'pool2_variations' => get_post_meta( $package_id, '_oc_pool_pool2_variations', true ),
        'pool1_min' => get_post_meta( $package_id, '_oc_pool_pool1_min', true ),
        'pool2_min' => get_post_meta( $package_id, '_oc_pool_pool2_min', true ),
        'pool1_ui_style' => get_post_meta( $package_id, '_oc_pool_pool1_ui_style', true ),
        'pool2_ui_style' => get_post_meta( $package_id, '_oc_pool_pool2_ui_style', true ),
        'allow_same_variation' => get_post_meta( $package_id, '_oc_pool_allow_same_variation', true )
    ];
    
    // Fallback la formatul vechi dacă nu găsește date noi
    if ( empty( $config['enabled'] ) && empty( $config['pool_id'] ) ) {
        $config = [
            'enabled' => get_post_meta( $package_id, '_mv_pack_enabled', true ),
            'price' => get_post_meta( $package_id, '_mv_pack_price', true ),
            'pool_id' => get_post_meta( $package_id, '_mv_pack_pool_id', true ),
            'min_selections' => get_post_meta( $package_id, '_mv_pack_min_selections', true ),
            'max_selections' => get_post_meta( $package_id, '_mv_pack_max_selections', true ),
            'ui_style' => get_post_meta( $package_id, '_mv_pack_ui_style', true ),
            'allow_duplicates' => get_post_meta( $package_id, '_mv_pack_allow_duplicates', true ),
            'helper_text' => get_post_meta( $package_id, '_mv_pack_helper_text', true ),
            'selected_variations' => get_post_meta( $package_id, '_mv_pack_selected_variations', true ),
            'allowed_payment_gateways' => []
        ];
    }
    
    // Curăță datele dual mode dacă nu este activat dual mode
    if ( empty( $config['dual_mode'] ) || $config['dual_mode'] !== 'yes' ) {
        $config['dual_mode'] = false;
        $config['pool1_id'] = '';
        $config['pool2_id'] = '';
        $config['pool1_label'] = '';
        $config['pool2_label'] = '';
        $config['pool1_variations'] = [];
        $config['pool2_variations'] = [];
        $config['pool1_min'] = '';
        $config['pool2_min'] = '';
        $config['allow_same_variation'] = false;
    }
    
    // Type casting pentru array-uri să prevină erori TypeError
    $config['selected_variations'] = is_array( $config['selected_variations'] ) ? $config['selected_variations'] : [];
    $config['pool1_variations'] = is_array( $config['pool1_variations'] ) ? $config['pool1_variations'] : [];
    $config['pool2_variations'] = is_array( $config['pool2_variations'] ) ? $config['pool2_variations'] : [];
    $config['allowed_payment_gateways'] = is_array( $config['allowed_payment_gateways'] ) ? $config['allowed_payment_gateways'] : [];
    
    return $config;
}

/**
 * Filtrează variațiile disponibile bazat pe ID-urile selectate
 *
 * @param array $all_variations
 * @param array $selected_variation_ids
 * @return array
 */
if ( ! function_exists( 'oc_pool_get_active_variation_ids' ) ) {
function oc_pool_get_active_variation_ids( $all_variations ) {
    return array_map( function( $variation ) {
        return (int) $variation['variation_id'];
    }, array_filter( $all_variations, function( $variation ) {
        return ! empty( $variation['is_purchasable'] )
            && ! empty( $variation['variation_is_active'] );
    } ) );
}
}

/**
 * Rezolvă ID-urile de variații configurate, cu fallback la toate variațiile active.
 *
 * @param array $all_variations
 * @param array $selected_variation_ids
 * @return array
 */
if ( ! function_exists( 'oc_pool_resolve_variation_ids' ) ) {
function oc_pool_resolve_variation_ids( $all_variations, $selected_variation_ids ) {
    $active_variation_ids = oc_pool_get_active_variation_ids( $all_variations );

    if ( empty( $active_variation_ids ) ) {
        return [];
    }

    if ( empty( $selected_variation_ids ) || ! is_array( $selected_variation_ids ) ) {
        return $active_variation_ids;
    }

    $selected_variation_ids = array_map( 'intval', $selected_variation_ids );
    $resolved_variation_ids = array_values( array_intersect( $active_variation_ids, $selected_variation_ids ) );

    if ( ! empty( $resolved_variation_ids ) ) {
        return $resolved_variation_ids;
    }

    return $active_variation_ids;
}
}

if ( ! function_exists( 'oc_pool_filter_variations' ) ) {
function oc_pool_filter_variations( $all_variations, $selected_variation_ids ) {
    $resolved_variation_ids = oc_pool_resolve_variation_ids( $all_variations, $selected_variation_ids );

    if ( empty( $resolved_variation_ids ) ) {
        return [];
    }
    
    return array_filter( $all_variations, function( $variation ) use ( $resolved_variation_ids ) {
        return in_array( (int) $variation['variation_id'], $resolved_variation_ids, true ) && 
               isset( $variation['is_purchasable'] ) && $variation['is_purchasable'] &&
               isset( $variation['variation_is_active'] ) && $variation['variation_is_active'];
    });
}
}

/**
 * Verifică dacă un pachet folosește dual mode
 *
 * @param int $package_id
 * @return bool
 */
function oc_pool_is_dual_mode( $package_id ): bool {
    $config = oc_pool_get_package_config( $package_id );
    return ! empty( $config['dual_mode'] ) && ! empty( $config['pool1_id'] ) && ! empty( $config['pool2_id'] );
}

/**
 * Obține produsul POOL pentru un pachet
 *
 * @param int $package_id
 * @return WC_Product_Variable|false
 */
function oc_pool_get_pool_product( $package_id ) {
    $config = oc_pool_get_package_config( $package_id );
    if ( ! $config || ! $config['pool_id'] ) {
        return false;
    }
    
    $pool_product = wc_get_product( $config['pool_id'] );
    if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
        return false;
    }
    
    return $pool_product;
}

/**
 * Obține variațiile disponibile pentru un pachet
 *
 * @param int $package_id
 * @return array
 */
function oc_pool_get_package_variations( $package_id ) {
    $config = oc_pool_get_package_config( $package_id );
    $pool_product = oc_pool_get_pool_product( $package_id );
    
    if ( ! $pool_product || ! is_array( $config['selected_variations'] ) ) {
        return [];
    }
    
    $all_variations = $pool_product->get_available_variations();

    return oc_pool_filter_variations( $all_variations, $config['selected_variations'] );
}

/**
 * Validează selecțiile unui pachet
 *
 * @param int $package_id
 * @param array $selections
 * @param int $quantity
 * @return array Errors array
 */
function oc_pool_validate_selections( $package_id, $selections, $quantity = 1 ) {
    $errors = [];
    $config = oc_pool_get_package_config( $package_id );
    
    if ( ! $config ) {
        $errors[] = 'Configurația pachetului nu este validă.';
        return $errors;
    }
    
    $min_selections = max( 1, intval( $config['min_selections'] ) );
    $max_selections = intval( $config['max_selections'] );
    
    // Validare număr selecții
    if ( count( $selections ) < $min_selections ) {
        $errors[] = sprintf( 'Selectează cel puțin %d opțiuni.', $min_selections );
    }
    
    if ( $max_selections && count( $selections ) > $max_selections ) {
        $errors[] = sprintf( 'Poți selecta maximum %d opțiuni.', $max_selections );
    }
    
    // Validare duplicate în același pachet (doar dacă qty = 1)
    if ( $quantity == 1 && count( $selections ) !== count( array_unique( $selections ) ) ) {
        $errors[] = 'Opțiunile selectate trebuie să fie diferite în cadrul aceluiași pachet.';
    }
    
    // Validare că toate selecțiile sunt variații valide
    $available_variations = oc_pool_get_package_variations( $package_id );
    $valid_variation_ids = array_map( function($v) { 
        return $v['variation_id']; 
    }, $available_variations );
    
    foreach ( $selections as $variation_id ) {
        if ( ! in_array( intval( $variation_id ), $valid_variation_ids ) ) {
            $errors[] = 'Una din selecțiile tale nu mai este disponibilă sau nu este inclusă în acest pachet.';
            break;
        }
    }
    
    return $errors;
}

/**
 * Returnează toate produsele variabile pentru dropdown
 *
 * @return array
 */
function oc_pool_get_all_variable_products() {
    // Cache key
    $cache_key = 'oc_pool_variable_products';
    $variable_products = wp_cache_get( $cache_key );
    
    if ( $variable_products === false ) {
        // Debugging - să vedem ce produse avem
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // OC_Pool: Caută produse variabile...' );
        }
        
        // Metodă 1: Prin get_posts și verificare tip (mai sigur)
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'private', 'draft'], // Include și ascunse
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        
        $products = get_posts( $args );
        $variable_products = [];
        
        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) continue;
            
            // Verifică dacă este variabil
            if ( $product->get_type() !== 'variable' ) continue;
            
            // Numără variațiile
            $variations = $product->get_children();
            $variations_count = count( $variations );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // Debug: Produs variabil găsit
            }
            
            $variable_products[] = [
                'id' => $product_post->ID,
                'title' => $product_post->post_title,
                'status' => $product_post->post_status,
                'variations_count' => $variations_count
            ];
        }
        
        // Dacă nu găsește nimic, încearcă metoda 2: prin taxonomie
        if ( empty( $variable_products ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // OC_Pool: Încearcă metoda alternativă prin taxonomie...' );
            }
            
            $args_alt = [
                'post_type' => 'product',
                'post_status' => ['publish', 'private', 'draft'],
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_type',
                        'field' => 'slug',
                        'terms' => 'variable'
                    ]
                ],
                'orderby' => 'title',
                'order' => 'ASC'
            ];
            
            $products_alt = get_posts( $args_alt );
            
            foreach ( $products_alt as $product_post ) {
                $product = wc_get_product( $product_post->ID );
                if ( ! $product || $product->get_type() !== 'variable' ) continue;
                
                $variations = $product->get_children();
                $variations_count = count( $variations );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // Debug: Produs variabil găsit prin metoda alternativă
                }
                
                $variable_products[] = [
                    'id' => $product_post->ID,
                    'title' => $product_post->post_title,
                    'status' => $product_post->post_status,
                    'variations_count' => $variations_count
                ];
            }
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // OC_Pool: Total produse variabile găsite: ' . count( $variable_products ) );
        }
        
        // Cache pentru 1 oră
        wp_cache_set( $cache_key, $variable_products, '', 3600 );
    }
    
    return $variable_products;
}

/**
 * Returnează toate ID-urile de produse folosite ca POOL
 *
 * @return array
 */
function oc_pool_get_all_pool_ids() {
    static $pool_ids = null;
    
    if ( $pool_ids === null ) {
        global $wpdb;
        
        // Caută în noul format
        $new_pool_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value != '' 
            AND meta_value != '0'
        ", '_oc_pool_pool_id' ) );
        
        // Caută și în formatul vechi pentru backwards compatibility
        $old_pool_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value != '' 
            AND meta_value != '0'
        ", '_mv_pack_pool_id' ) );
        
        // Combină și deduplică
        $pool_ids = array_unique( array_merge( $new_pool_ids, $old_pool_ids ) );
        $pool_ids = array_map( 'intval', $pool_ids );
    }
    
    return $pool_ids;
}

/**
 * Detectează dacă pagina folosește Elementor
 *
 * @return bool
 */
function oc_pool_is_elementor_page() {
    // Verifică dacă Elementor este activ
    if ( ! class_exists( '\Elementor\Plugin' ) ) return false;
    
    // Verifică dacă pagina este construită cu Elementor
    if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || 
         \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
        return true;
    }
    
    // Verifică meta pentru pagini publice
    global $post;
    if ( $post && get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
        return true;
    }
    
    // Verifică dacă există widget-uri Elementor WooCommerce în content
    if ( $post && strpos( $post->post_content, 'elementor-widget-woocommerce' ) !== false ) {
        return true;
    }
    
    // Verifică dacă există date Elementor în post_content
    if ( $post && strpos( $post->post_content, '"widgetType":"woocommerce-product-add-to-cart"' ) !== false ) {
        return true;
    }
    
    // Verifică în DOM dacă există clase Elementor (pentru cazuri dinamice)
    if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || 
         ( isset( $_GET['elementor-preview'] ) ) ||
         ( isset( $_GET['ver'] ) && strpos( $_GET['ver'], 'elementor' ) !== false ) ) {
        return true;
    }
    
    // Forțează detecția dacă rulează într-un context Elementor
    if ( did_action( 'elementor/loaded' ) && 
         ( strpos( $_SERVER['REQUEST_URI'] ?? '', 'elementor' ) !== false ||
           isset( $_POST['action'] ) && strpos( $_POST['action'], 'elementor' ) !== false ) ) {
        return true;
    }
    
    return false;
}

/**
 * Curăță cache-ul pentru un pachet
 *
 * @param int $package_id
 */
function oc_pool_clear_package_cache( $package_id ) {
    // Clear cache WordPress
    wp_cache_delete( 'oc_pool_package_' . $package_id );
    wp_cache_delete( 'oc_pool_variable_products' );
}

// =============================================================================
// Funcții de Compatibility cu Pluginul Vechi
// =============================================================================

if ( ! function_exists( 'mv_pack_is_package' ) ) {
    /**
     * Compatibility wrapper
     */
    function mv_pack_is_package( $product_id ) {
        return oc_pool_is_package( $product_id );
    }
}

if ( ! function_exists( 'mv_pack_get_package_config' ) ) {
    /**
     * Compatibility wrapper
     */
    function mv_pack_get_package_config( $package_id ) {
        return oc_pool_get_package_config( $package_id );
    }
}

if ( ! function_exists( 'mv_pack_get_all_variable_products' ) ) {
    /**
     * Compatibility wrapper
     */
    function mv_pack_get_all_variable_products() {
        return oc_pool_get_all_variable_products();
    }
}

if ( ! function_exists( 'mv_pack_get_all_pool_ids' ) ) {
    /**
     * Compatibility wrapper
     */
    function mv_pack_get_all_pool_ids() {
        return oc_pool_get_all_pool_ids();
    }
}

if ( ! function_exists( 'mv_pack_is_elementor_page' ) ) {
    /**
     * Compatibility wrapper
     */
    function mv_pack_is_elementor_page() {
        return oc_pool_is_elementor_page();
    }
}

// =============================================================================
// Funcții pentru Debugging și Maintenance
// =============================================================================

/**
 * Obține informații de debug pentru un pachet
 *
 * @param int $package_id
 * @return array
 */
function oc_pool_get_debug_info( $package_id ) {
    $config = oc_pool_get_package_config( $package_id );
    $pool_product = oc_pool_get_pool_product( $package_id );
    $variations = oc_pool_get_package_variations( $package_id );
    
    return [
        'package_id' => $package_id,
        'is_package' => oc_pool_is_package( $package_id ),
        'config' => $config,
        'pool_product' => $pool_product ? [
            'id' => $pool_product->get_id(),
            'name' => $pool_product->get_name(),
            'status' => $pool_product->get_status(),
            'type' => $pool_product->get_type(),
            'total_variations' => count( $pool_product->get_children() )
        ] : null,
        'available_variations' => count( $variations ),
        'selected_variations' => is_array( $config['selected_variations'] ) ? count( $config['selected_variations'] ) : 0
    ];
}

/**
 * Migrate meta-uri de la formatul vechi la cel nou
 *
 * @param int $package_id
 * @return bool
 */
function oc_pool_migrate_package_meta( $package_id ) {
    $meta_map = [
        '_mv_pack_enabled' => '_oc_pool_enabled',
        '_mv_pack_price' => '_oc_pool_price',
        '_mv_pack_pool_id' => '_oc_pool_pool_id',
        '_mv_pack_min_selections' => '_oc_pool_min_selections',
        '_mv_pack_max_selections' => '_oc_pool_max_selections',
        '_mv_pack_ui_style' => '_oc_pool_ui_style',
        '_mv_pack_allow_duplicates' => '_oc_pool_allow_duplicates',
        '_mv_pack_helper_text' => '_oc_pool_helper_text',
        '_mv_pack_selected_variations' => '_oc_pool_selected_variations'
    ];
    
    $migrated = false;
    
    foreach ( $meta_map as $old_key => $new_key ) {
        $value = get_post_meta( $package_id, $old_key, true );
        if ( $value !== '' ) {
            update_post_meta( $package_id, $new_key, $value );
            $migrated = true;
        }
    }
    
    return $migrated;
}

/**
 * Găsește toate pachetele cu meta-uri vechi
 *
 * @return array
 */
function oc_pool_find_legacy_packages() {
    global $wpdb;
    
    return $wpdb->get_col( $wpdb->prepare( "
        SELECT DISTINCT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = %s 
        AND meta_value = '1'
    ", '_mv_pack_enabled' ) );
}

/**
 * Migrează toate pachetele vechi
 *
 * @return array Rezultatele migrării
 */
function oc_pool_migrate_all_legacy_packages() {
    $legacy_packages = oc_pool_find_legacy_packages();
    $results = [
        'found' => count( $legacy_packages ),
        'migrated' => 0,
        'errors' => []
    ];
    
    foreach ( $legacy_packages as $package_id ) {
        try {
            if ( oc_pool_migrate_package_meta( $package_id ) ) {
                $results['migrated']++;
            }
        } catch ( Exception $e ) {
            $results['errors'][] = sprintf( 'Error migrating package %d: %s', $package_id, $e->getMessage() );
        }
    }
    
    return $results;
}

// =============================================================================
// Funcții pentru Site Health
// =============================================================================

/**
 * Adaugă informații Pool Product Manager la Site Health
 *
 * @param array $debug_info
 * @return array
 */
function oc_pool_add_debug_info( $debug_info ) {
    // Statistici generale
    $all_pools = oc_pool_get_all_pool_ids();
    $legacy_packages = oc_pool_find_legacy_packages();
    
    $active_packages = 0;
    global $wpdb;
    $active_packages = $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) 
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key IN (%s, %s)
        AND pm.meta_value = '1'
        AND p.post_status = 'publish'
        AND p.post_type = 'product'
    ", '_oc_pool_enabled', '_mv_pack_enabled' ) );
    
    $debug_info['oc-pool-product-manager'] = [
        'label' => 'Pool Product Manager',
        'fields' => [
            'active_packages' => [
                'label' => 'Pachete active',
                'value' => intval( $active_packages ),
            ],
            'pool_products' => [
                'label' => 'Produse POOL',
                'value' => count( $all_pools ),
            ],
            'legacy_packages' => [
                'label' => 'Pachete format vechi',
                'value' => count( $legacy_packages ),
            ],
            'addon_version' => [
                'label' => 'Versiune ADD-ON',
                'value' => '1.0.0',
            ]
        ]
    ];
    
    return $debug_info;
}

// Hook pentru Site Health
add_filter( 'debug_information', 'oc_pool_add_debug_info' );
