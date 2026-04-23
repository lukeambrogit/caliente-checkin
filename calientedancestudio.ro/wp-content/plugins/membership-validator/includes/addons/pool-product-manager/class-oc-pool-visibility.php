<?php
/**
 * Pool Product Manager - Visibility Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru gestionarea vizibilității și SEO în Pool Product Manager
 */
class OC_Pool_Visibility {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook-uri pentru ascunderea produselor POOL
        add_action( 'pre_get_posts', [ $this, 'hide_pool_products' ] );
        
        // Hook-uri pentru SEO
        add_action( 'wp_head', [ $this, 'noindex_pool_products' ] );
        add_action( 'template_redirect', [ $this, 'redirect_pool_products' ] );
        
        // Debugging și monitoring
        add_action( 'admin_notices', [ $this, 'show_pool_visibility_notices' ] );
        
        // Cache management
        add_action( 'woocommerce_process_product_meta_simple', [ $this, 'clear_pool_cache' ] );
        add_action( 'init', [ $this, 'setup_caching' ] );
    }
    
    /**
     * Ascunde produsele POOL din shop și căutare
     *
     * @param WP_Query $query
     */
    public function hide_pool_products( $query ) {
        if ( is_admin() || ! $query->is_main_query() ) return;
        
        // În shop, categorii, arhive de produse
        if ( is_shop() || is_product_category() || is_product_tag() || $query->is_search() ) {
            $pool_ids = $this->get_all_pool_ids();
            
            if ( ! empty( $pool_ids ) ) {
                $post__not_in = $query->get( 'post__not_in' ) ?: [];
                $post__not_in = array_merge( $post__not_in, $pool_ids );
                $query->set( 'post__not_in', array_unique( $post__not_in ) );
                
                // Log pentru debugging
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $pool_ids ) ) {
                    // Debug log removed
                }
            }
        }
    }
    
    /**
     * Adaugă noindex la produsele POOL
     */
    public function noindex_pool_products() {
        if ( ! is_product() ) return;
        
        global $post;
        $pool_ids = $this->get_all_pool_ids();
        
        if ( in_array( $post->ID, $pool_ids ) ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            
            // Log pentru debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // Debug log removed
            }
        }
    }
    
    /**
     * Redirect produse POOL la shop sau la primul pachet care le folosește
     */
    public function redirect_pool_products() {
        if ( ! is_product() ) return;
        
        global $post;
        $pool_ids = $this->get_all_pool_ids();
        
        if ( in_array( $post->ID, $pool_ids ) ) {
            // Încearcă să găsească primul pachet care folosește acest POOL
            $package_id = $this->find_first_package_using_pool( $post->ID );
            
            if ( $package_id ) {
                $redirect_url = get_permalink( $package_id );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // Debug log removed
                }
            } else {
                $redirect_url = wc_get_page_permalink( 'shop' );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // Debug log removed
                }
            }
            
            wp_redirect( $redirect_url, 301 );
            exit;
        }
    }
    
    /**
     * Returnează toate ID-urile de produse folosite ca POOL
     *
     * @return array
     */
    private function get_all_pool_ids() {
        // Încearcă din cache mai întâi
        $pool_ids = wp_cache_get( 'oc_pool_ids', 'oc_pool' );
        
        if ( $pool_ids === false ) {
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
            
            // Cache pentru 30 minute
            wp_cache_set( 'oc_pool_ids', $pool_ids, 'oc_pool', 1800 );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // POOL IDs updated successfully
            }
        }
        
        return $pool_ids;
    }
    
    /**
     * Găsește primul pachet care folosește un POOL specific
     *
     * @param int $pool_id
     * @return int|null
     */
    private function find_first_package_using_pool( $pool_id ) {
        global $wpdb;
        
        // Caută în noul format
        $package_id = $wpdb->get_var( $wpdb->prepare( "
            SELECT p.post_id 
            FROM {$wpdb->postmeta} p
            INNER JOIN {$wpdb->posts} post ON p.post_id = post.ID
            WHERE p.meta_key = %s 
            AND p.meta_value = %s 
            AND post.post_status = 'publish'
            AND post.post_type = 'product'
            LIMIT 1
        ", '_oc_pool_pool_id', $pool_id ) );
        
        // Dacă nu găsește, încearcă în formatul vechi
        if ( ! $package_id ) {
            $package_id = $wpdb->get_var( $wpdb->prepare( "
                SELECT p.post_id 
                FROM {$wpdb->postmeta} p
                INNER JOIN {$wpdb->posts} post ON p.post_id = post.ID
                WHERE p.meta_key = %s 
                AND p.meta_value = %s 
                AND post.post_status = 'publish'
                AND post.post_type = 'product'
                LIMIT 1
            ", '_mv_pack_pool_id', $pool_id ) );
        }
        
        return $package_id ? intval( $package_id ) : null;
    }
    
    /**
     * Afișează notificări despre vizibilitatea POOL-urilor
     */
    public function show_pool_visibility_notices() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [ 'edit-product', 'product' ] ) ) return;
        
        // Verifică dacă există POOL-uri fără pachete asociate
        $orphaned_pools = $this->get_orphaned_pools();
        
        if ( ! empty( $orphaned_pools ) ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Pool Product Manager:</strong> Există ' . count( $orphaned_pools ) . ' produse POOL care nu sunt folosite de nici un pachet.</p>';
            echo '<p>Aceste produse sunt ascunse din shop dar nu au pachete asociate: ';
            
            $pool_links = [];
            foreach ( array_slice( $orphaned_pools, 0, 3 ) as $pool_id ) {
                $pool_title = get_the_title( $pool_id );
                $edit_link = get_edit_post_link( $pool_id );
                $pool_links[] = '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $pool_title ) . '</a>';
            }
            
            echo implode( ', ', $pool_links );
            if ( count( $orphaned_pools ) > 3 ) {
                echo ' și altele ' . ( count( $orphaned_pools ) - 3 ) . '...';
            }
            echo '</p></div>';
        }
    }
    
    /**
     * Găsește POOL-urile care nu sunt folosite de nici un pachet
     *
     * @return array
     */
    private function get_orphaned_pools() {
        $all_pools = $this->get_all_pool_ids();
        $orphaned = [];
        
        foreach ( $all_pools as $pool_id ) {
            $package_id = $this->find_first_package_using_pool( $pool_id );
            if ( ! $package_id ) {
                // Verifică dacă produsul mai există
                $pool_product = wc_get_product( $pool_id );
                if ( $pool_product ) {
                    $orphaned[] = $pool_id;
                }
            }
        }
        
        return $orphaned;
    }
    
    /**
     * Curăță cache-ul când se salvează un pachet
     *
     * @param int $post_id
     */
    public function clear_pool_cache( $post_id ) {
        if ( get_post_meta( $post_id, '_oc_pool_enabled', true ) || 
             get_post_meta( $post_id, '_mv_pack_enabled', true ) ) {
            wp_cache_delete( 'oc_pool_ids', 'oc_pool' );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // Cache cleared successfully
            }
        }
    }
    
    /**
     * Configurează sistemul de cache
     */
    public function setup_caching() {
        // Cache pentru POOL IDs (30 minute)
        if ( ! wp_cache_get( 'oc_pool_ids', 'oc_pool' ) ) {
            $pool_ids = $this->get_all_pool_ids();
            wp_cache_set( 'oc_pool_ids', $pool_ids, 'oc_pool', 1800 );
        }
    }
    
    /**
     * Verifică dacă un produs este POOL
     *
     * @param int $product_id
     * @return bool
     */
    public function is_pool_product( $product_id ) {
        $pool_ids = $this->get_all_pool_ids();
        return in_array( intval( $product_id ), $pool_ids );
    }
    
    /**
     * Obține toate pachetele care folosesc un POOL specific
     *
     * @param int $pool_id
     * @return array
     */
    public function get_packages_using_pool( $pool_id ) {
        global $wpdb;
        
        // Caută în noul format
        $new_packages = $wpdb->get_col( $wpdb->prepare( "
            SELECT p.post_id 
            FROM {$wpdb->postmeta} p
            INNER JOIN {$wpdb->posts} post ON p.post_id = post.ID
            WHERE p.meta_key = %s 
            AND p.meta_value = %s 
            AND post.post_status = 'publish'
            AND post.post_type = 'product'
        ", '_oc_pool_pool_id', $pool_id ) );
        
        // Caută și în formatul vechi
        $old_packages = $wpdb->get_col( $wpdb->prepare( "
            SELECT p.post_id 
            FROM {$wpdb->postmeta} p
            INNER JOIN {$wpdb->posts} post ON p.post_id = post.ID
            WHERE p.meta_key = %s 
            AND p.meta_value = %s 
            AND post.post_status = 'publish'
            AND post.post_type = 'product'
        ", '_mv_pack_pool_id', $pool_id ) );
        
        $packages = array_unique( array_merge( $new_packages, $old_packages ) );
        return array_map( 'intval', $packages );
    }
    
    /**
     * Generează un raport de vizibilitate pentru admin
     *
     * @return array
     */
    public function get_visibility_report() {
        $pool_ids = $this->get_all_pool_ids();
        $orphaned_pools = $this->get_orphaned_pools();
        $active_pools = array_diff( $pool_ids, $orphaned_pools );
        
        $report = [
            'total_pools' => count( $pool_ids ),
            'active_pools' => count( $active_pools ),
            'orphaned_pools' => count( $orphaned_pools ),
            'pools_details' => []
        ];
        
        foreach ( $pool_ids as $pool_id ) {
            $pool_product = wc_get_product( $pool_id );
            if ( ! $pool_product ) continue;
            
            $packages = $this->get_packages_using_pool( $pool_id );
            
            $report['pools_details'][] = [
                'pool_id' => $pool_id,
                'pool_name' => $pool_product->get_name(),
                'pool_status' => $pool_product->get_status(),
                'variations_count' => count( $pool_product->get_children() ),
                'packages_count' => count( $packages ),
                'packages' => $packages,
                'is_orphaned' => in_array( $pool_id, $orphaned_pools )
            ];
        }
        
        return $report;
    }
    
    /**
     * Exportă raportul de vizibilitate ca CSV
     *
     * @return string
     */
    public function export_visibility_report_csv() {
        $report = $this->get_visibility_report();
        
        $csv = "Pool ID,Pool Name,Pool Status,Variations Count,Packages Count,Is Orphaned\n";
        
        foreach ( $report['pools_details'] as $pool ) {
            $csv .= sprintf( "%d,%s,%s,%d,%d,%s\n",
                $pool['pool_id'],
                '"' . str_replace( '"', '""', $pool['pool_name'] ) . '"',
                $pool['pool_status'],
                $pool['variations_count'],
                $pool['packages_count'],
                $pool['is_orphaned'] ? 'Yes' : 'No'
            );
        }
        
        return $csv;
    }
    
    /**
     * Cleanup POOL-uri orfane (pentru maintenance)
     *
     * @param bool $dry_run
     * @return array
     */
    public function cleanup_orphaned_pools( $dry_run = true ) {
        $orphaned_pools = $this->get_orphaned_pools();
        $results = [
            'found' => count( $orphaned_pools ),
            'processed' => 0,
            'errors' => []
        ];
        
        if ( $dry_run ) {
            $results['message'] = 'Dry run - no products were modified';
            return $results;
        }
        
        foreach ( $orphaned_pools as $pool_id ) {
            try {
                // Doar le marchează ca draft, nu le șterge
                wp_update_post([
                    'ID' => $pool_id,
                    'post_status' => 'draft'
                ]);
                
                $results['processed']++;
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // Debug log removed
                }
                
            } catch ( Exception $e ) {
                $results['errors'][] = sprintf( 'Error processing POOL %d: %s', $pool_id, $e->getMessage() );
            }
        }
        
        // Curăță cache-ul
        wp_cache_delete( 'oc_pool_ids', 'oc_pool' );
        
        return $results;
    }
}
