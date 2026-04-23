<?php
/**
 * Pool Product Manager - AJAX Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru handler-ele AJAX în Pool Product Manager
 */
class OC_Pool_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers pentru admin
        add_action( 'wp_ajax_oc_pool_get_pool_variations', [ $this, 'get_pool_variations' ] );
        add_action( 'wp_ajax_oc_pool_validate_package', [ $this, 'validate_package' ] );
        add_action( 'wp_ajax_oc_pool_search_products', [ $this, 'search_products' ] );
        
        // AJAX handlers pentru frontend
        add_action( 'wp_ajax_oc_pool_update_selections', [ $this, 'update_selections' ] );
        add_action( 'wp_ajax_nopriv_oc_pool_update_selections', [ $this, 'update_selections' ] );
        
        // AJAX handlers pentru maintenance
        add_action( 'wp_ajax_oc_pool_cleanup_orphaned', [ $this, 'cleanup_orphaned_pools' ] );
        add_action( 'wp_ajax_oc_pool_visibility_report', [ $this, 'get_visibility_report' ] );
        
        // Compatibility cu vechile handler-e
        add_action( 'wp_ajax_mv_pack_get_pool_variations', [ $this, 'legacy_get_pool_variations' ] );
    }
    
    /**
     * AJAX handler pentru încărcarea variațiilor din POOL
     */
    public function get_pool_variations() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $pool_id = intval( $_POST['pool_id'] ?? 0 );
        $package_id = intval( $_POST['package_id'] ?? 0 );
        $field_name = sanitize_text_field( $_POST['field_name'] ?? '_oc_pool_selected_variations' );
        $allowed_field_names = [
            '_oc_pool_selected_variations',
            '_oc_pool_pool1_variations',
            '_oc_pool_pool2_variations',
        ];
        if ( ! in_array( $field_name, $allowed_field_names, true ) ) {
            $field_name = '_oc_pool_selected_variations';
        }
        
        if ( ! $pool_id ) {
            wp_send_json_error( 'Invalid pool ID' );
        }
        
        $pool_product = wc_get_product( $pool_id );
        if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
            wp_send_json_error( 'Produsul POOL nu este valid sau nu este variabil' );
        }
        
        try {
            // Obține variațiile existente selectate pentru acest pachet, inclusiv dual mode.
            $config = oc_pool_get_package_config( $package_id );
            if ( $field_name === '_oc_pool_pool1_variations' ) {
                $selected_variations = $config ? ( $config['pool1_variations'] ?? [] ) : [];
            } elseif ( $field_name === '_oc_pool_pool2_variations' ) {
                $selected_variations = $config ? ( $config['pool2_variations'] ?? [] ) : [];
            } else {
                $selected_variations = $config ? ( $config['selected_variations'] ?? [] ) : [];
            }
            if ( ! is_array( $selected_variations ) ) {
                $selected_variations = [];
            }
            $selected_variations = array_map( 'intval', $selected_variations );
            
            // Generează HTML-ul cu checkboxurile
            $html = $this->generate_variations_html( $pool_product, $selected_variations, $field_name );
            
            wp_send_json_success( [ 'html' => $html ] );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in get_pool_variations - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la încărcarea variațiilor: ' . $e->getMessage() );
        }
    }
    
    /**
     * Generează HTML pentru variații
     *
     * @param WC_Product_Variable $pool_product
     * @param array $selected_variations
     * @param string $field_name
     * @return string
     */
    private function generate_variations_html( $pool_product, $selected_variations, $field_name = '_oc_pool_selected_variations' ) {
        $variations = $pool_product->get_available_variations();
        
        if ( empty( $variations ) ) {
            return '<em>Acest produs variabil nu are variații publicate.</em>';
        }
        
        $html = '<div>';
        
        foreach ( $variations as $variation ) {
            $variation_obj = wc_get_product( $variation['variation_id'] );
            if ( ! $variation_obj ) continue;
            
            $variation_name = wc_get_formatted_variation( $variation_obj, true, false );
            $is_checked = in_array( (int) $variation['variation_id'], $selected_variations, true );
            $stock_status = $variation_obj->is_in_stock() ? 'În stoc' : 'Stoc epuizat';
            $price = $variation_obj->get_price() ? wc_price( $variation_obj->get_price() ) : 'N/A';
            
            $html .= '<div>';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $variation['variation_id'] ) . '"';
            $html .= $is_checked ? ' checked' : '';
            $html .= '>';
            $html .= '<strong>' . esc_html( $variation_name ) . '</strong>';
            $html .= '<br><small>' . $stock_status . ' | Preț: ' . $price . ' | ID: ' . $variation['variation_id'] . '</small>';
            $html .= '</label>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<p><small><strong>Total:</strong> ' . count( $variations ) . ' variații | <strong>Selectate:</strong> ' . count( $selected_variations ) . '</small></p>';
        
        return $html;
    }
    
    /**
     * AJAX handler pentru validarea configurației unui pachet
     */
    public function validate_package() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $package_id = intval( $_POST['package_id'] ?? 0 );
        $pool_id = intval( $_POST['pool_id'] ?? 0 );
        $min_selections = intval( $_POST['min_selections'] ?? 0 );
        $max_selections = intval( $_POST['max_selections'] ?? 0 );
        
        $errors = [];
        $warnings = [];
        
        try {
            // Validare POOL
            if ( ! $pool_id ) {
                $errors[] = 'Trebuie să selectezi un produs POOL.';
            } else {
                $pool_product = wc_get_product( $pool_id );
                if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
                    $errors[] = 'Produsul POOL trebuie să fie de tip variabil.';
                } else {
                    $variations = $pool_product->get_available_variations();
                    $purchasable_count = count( array_filter( $variations, function($v) {
                        return $v['is_purchasable'] && $v['variation_is_active'];
                    }));
                    
                    if ( $purchasable_count < 1 ) {
                        $errors[] = 'Produsul POOL trebuie să aibă cel puțin o variație cumpărabilă.';
                    } elseif ( $purchasable_count < $min_selections ) {
                        $warnings[] = sprintf( 'POOL-ul are doar %d variații cumpărabile, dar cerințele minime sunt %d.', $purchasable_count, $min_selections );
                    }
                    
                    // Verifică variațiile selectate
                    $selected_variations = get_post_meta( $package_id, '_oc_pool_selected_variations', true );
                    if ( is_array( $selected_variations ) && ! empty( $selected_variations ) ) {
                        $valid_selected = array_filter( $selected_variations, function( $var_id ) use ( $variations ) {
                            foreach ( $variations as $variation ) {
                                if ( $variation['variation_id'] == $var_id && $variation['is_purchasable'] && $variation['variation_is_active'] ) {
                                    return true;
                                }
                            }
                            return false;
                        });
                        
                        if ( count( $valid_selected ) < $min_selections ) {
                            $errors[] = sprintf( 'Doar %d din variațiile selectate sunt valide, dar sunt necesare minim %d.', count( $valid_selected ), $min_selections );
                        }
                    }
                }
            }
            
            // Validare Min/Max
            if ( $min_selections < 1 ) {
                $errors[] = 'Selecțiile minime trebuie să fie cel puțin 1.';
            }
            
            if ( $max_selections > 0 && $max_selections < $min_selections ) {
                $errors[] = 'Selecțiile maxime nu pot fi mai puține decât minimele.';
            }
            
            // Validare produs pachet
            if ( $package_id ) {
                $package_product = wc_get_product( $package_id );
                if ( ! $package_product || $package_product->get_type() !== 'simple' ) {
                    $errors[] = 'Pachetul trebuie să fie un produs simplu.';
                }
            }
            
            $result = [
                'valid' => empty( $errors ),
                'errors' => $errors,
                'warnings' => $warnings
            ];
            
            wp_send_json_success( $result );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in validate_package - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la validare: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler pentru căutarea produselor
     */
    public function search_products() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $search_term = sanitize_text_field( $_POST['search'] ?? '' );
        $product_type = sanitize_text_field( $_POST['type'] ?? 'variable' );
        
        if ( strlen( $search_term ) < 2 ) {
            wp_send_json_error( 'Termenul de căutare trebuie să aibă cel puțin 2 caractere.' );
        }
        
        try {
            $args = [
                'post_type' => 'product',
                'post_status' => [ 'publish', 'private', 'draft' ],
                'posts_per_page' => 20,
                's' => $search_term,
                'meta_query' => [
                    [
                        'key' => '_product_type',
                        'value' => $product_type,
                        'compare' => '='
                    ]
                ]
            ];
            
            $products = get_posts( $args );
            $results = [];
            
            foreach ( $products as $product_post ) {
                $product = wc_get_product( $product_post->ID );
                if ( ! $product ) continue;
                
                $variations_count = 0;
                if ( $product->get_type() === 'variable' ) {
                    $variations_count = count( $product->get_children() );
                }
                
                $results[] = [
                    'id' => $product_post->ID,
                    'title' => $product_post->post_title,
                    'status' => $product_post->post_status,
                    'type' => $product->get_type(),
                    'variations_count' => $variations_count,
                    'edit_url' => get_edit_post_link( $product_post->ID )
                ];
            }
            
            wp_send_json_success( [ 'products' => $results ] );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in search_products - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la căutarea produselor: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler pentru actualizarea selecțiilor din frontend
     */
    public function update_selections() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_frontend_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        $package_id = intval( $_POST['package_id'] ?? 0 );
        $selections = array_filter( array_map( 'intval', $_POST['selections'] ?? [] ) );
        
        if ( ! $package_id ) {
            wp_send_json_error( 'Invalid package ID' );
        }
        
        try {
            // Încarcă configurația cu fallback
            $config = oc_pool_get_package_config( $package_id );
            if ( ! $config ) {
                wp_send_json_error( 'Configurația pachetului nu este validă.' );
            }
            
            // Validare de bază
            $min_selections = max( 1, intval( $config['min_selections'] ) );
            $max_selections = intval( $config['max_selections'] );
            
            if ( count( $selections ) < $min_selections ) {
                wp_send_json_error( sprintf( 'Selectează cel puțin %d opțiuni.', $min_selections ) );
            }
            
            if ( $max_selections && count( $selections ) > $max_selections ) {
                wp_send_json_error( sprintf( 'Poți selecta maximum %d opțiuni.', $max_selections ) );
            }
            
            // Returnează informații despre selecții
            $selection_info = [];
            foreach ( $selections as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation ) {
                    $selection_info[] = [
                        'id' => $variation_id,
                        'name' => wc_get_formatted_variation( $variation, true, false ),
                        'price' => $variation->get_price(),
                        'in_stock' => $variation->is_in_stock()
                    ];
                }
            }
            
            wp_send_json_success( [
                'selections' => $selection_info,
                'total_price' => $config['price'],
                'valid' => true
            ] );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in update_selections - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la actualizarea selecțiilor: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler pentru cleanup POOL-uri orfane
     */
    public function cleanup_orphaned_pools() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $dry_run = ! empty( $_POST['dry_run'] );
        
        try {
            // Necesită clasa de visibility
            if ( ! class_exists( 'OC_Pool_Visibility' ) ) {
                wp_send_json_error( 'Visibility component not available' );
            }
            
            $visibility = new OC_Pool_Visibility();
            $results = $visibility->cleanup_orphaned_pools( $dry_run );
            
            wp_send_json_success( $results );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in cleanup_orphaned_pools - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la cleanup: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler pentru raportul de vizibilitate
     */
    public function get_visibility_report() {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        try {
            // Necesită clasa de visibility
            if ( ! class_exists( 'OC_Pool_Visibility' ) ) {
                wp_send_json_error( 'Visibility component not available' );
            }
            
            $visibility = new OC_Pool_Visibility();
            $report = $visibility->get_visibility_report();
            
            wp_send_json_success( $report );
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Ajax: Error in get_visibility_report - ' . $e->getMessage() );
            }
            wp_send_json_error( 'Eroare la generarea raportului: ' . $e->getMessage() );
        }
    }
    
    /**
     * Legacy handler pentru compatibilitate cu pluginul vechi
     */
    public function legacy_get_pool_variations() {
        // Redirecționează către handler-ul nou
        $_POST['security'] = wp_create_nonce( 'oc_pool_admin_nonce' );
        $this->get_pool_variations();
    }
    
    /**
     * Verifică dacă request-ul AJAX este valid
     *
     * @param string $action
     * @param string $nonce_name
     * @param string $capability
     * @return bool
     */
    private function verify_ajax_request( $action, $nonce_name = 'oc_pool_admin_nonce', $capability = 'edit_products' ) {
        // Verifică nonce
        if ( ! wp_verify_nonce( $_POST['security'] ?? '', $nonce_name ) ) {
            wp_send_json_error( 'Invalid nonce' );
            return false;
        }
        
        // Verifică permisiuni
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Insufficient permissions' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Log pentru debugging
     *
     * @param string $message
     * @param string $level
     */
    private function log( $message, $level = 'INFO' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // Debug log removed for production
        }
    }
}
