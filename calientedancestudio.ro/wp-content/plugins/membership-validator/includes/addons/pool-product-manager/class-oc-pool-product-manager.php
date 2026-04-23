<?php
/**
 * Pool Product Manager ADD-ON pentru Membership Validator Core
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    2.0.0
 * @author     Remus Lazar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa principală pentru Pool Product Manager ADD-ON
 */
class OC_Pool_Product_Manager {
    
    /**
     * Instanța singleton
     *
     * @var OC_Pool_Product_Manager
     */
    private static $instance = null;
    
    /**
     * Flag pentru a preveni inițializarea multiplă
     *
     * @var bool
     */
    private static $initialized = false;
    
    /**
     * Versiunea ADD-ON-ului
     *
     * @var string
     */
    public $version = '2.0.0';
    
    /**
     * ID-ul ADD-ON-ului
     *
     * @var string  
     */
    public $addon_id = 'pool_product_manager';
    
    /**
     * Componente ADD-ON
     *
     * @var array
     */
    public $components = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Previne inițializarea multiplă
        if ( self::$initialized ) {
            return;
        }
        
        // Inițializare ADD-ON cu protecție împotriva erorilor
        try {
            if ( $this->check_dependencies() ) {
                $this->init_addon();
                self::$initialized = true;
            }
        } catch ( Throwable $e ) {
            // Log doar erorile critice în producție
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'OC_Pool_Product_Manager: Constructor error - ' . $e->getMessage() );
            }
        }
    }
    
    /**
     * Obține instanța singleton
     *
     * @return OC_Pool_Product_Manager
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    

    
    /**
     * Inițializează ADD-ON-ul
     */
    private function init_addon() {
        // Verifică dependințele
        if ( ! $this->check_dependencies() ) {
            return;
        }
        
        // Încarcă componentele
        $this->load_components();
        
        // Hook-uri pentru lifecycle (admin SAFE)
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Hook-uri pentru compatibilitate cu pluginul vechi
        $this->setup_legacy_compatibility();
        
        // Actions și filters custom
        $this->setup_hooks();
        
        // ADD-ON inițializat cu succes
    }
    
    /**
     * Verifică dependințele
     *
     * @return bool
     */
    private function check_dependencies(): bool {
        // Verifică WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
            return false;
        }
        
        // Verifică CORE
        if ( ! class_exists( 'OC_Orar_Cursuri' ) ) {
            add_action( 'admin_notices', [ $this, 'core_missing_notice' ] );
            return false;
        }
        
        return true;
    }
    
    /**
     * Încarcă componentele ADD-ON-ului
     */
    private function load_components(): void {
        $addon_path = dirname( __FILE__ );
        
        // Încarcă funcțiile helper cu backwards compatibility
        $functions_file = $addon_path . '/pool-product-functions.php';
        if ( file_exists( $functions_file ) ) {
            require_once $functions_file;
        }
        
        // Încarcă migrația DB
        $migration_file = $addon_path . '/pool-product-migration.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
        
        // Încarcă toate componentele ADD-ON-ului
        $components = [
            'class-oc-pool-admin.php',
            'class-oc-pool-visibility.php',
            'class-oc-pool-ajax.php'
        ];
        
        foreach ( $components as $component ) {
            $component_file = $addon_path . '/' . $component;
            if ( file_exists( $component_file ) ) {
                require_once $component_file;
            }
        }
        
        // Instanțiază componentele
        if ( is_admin() ) {
            if ( class_exists( 'OC_Pool_Admin' ) ) {
                new OC_Pool_Admin();
            }
        }
        
        // Instanțiază AJAX handler-ul (necesar pentru toate contextele)
        if ( class_exists( 'OC_Pool_Ajax' ) ) {
            new OC_Pool_Ajax();
        }
        
        // Încarcă versiunea DIRECTĂ (exact ca pluginul original)
        $direct_file = $addon_path . '/pool-product-direct.php';
        if ( file_exists( $direct_file ) ) {
            require_once $direct_file;
        }
    }
    
    /**
     * Configurează hook-urile custom
     */
    private function setup_hooks(): void {
        // Actions pentru lifecycle pachet
        add_action( 'oc_pool_package_created', [ $this, 'log_package_created' ], 10, 2 );
        add_action( 'oc_pool_package_updated', [ $this, 'clear_package_cache' ], 10, 1 );
        add_action( 'oc_pool_package_deleted', [ $this, 'cleanup_package_data' ], 10, 1 );
        
        // Filters pentru customizare
        add_filter( 'oc_pool_package_price', [ $this, 'filter_package_price' ], 10, 2 );
        add_filter( 'oc_pool_available_selections', [ $this, 'filter_available_selections' ], 10, 2 );
    }
    
    /**
     * Configurează compatibilitatea cu pluginul vechi
     */
    private function setup_legacy_compatibility() {
        // Wrapper functions pentru funcții mv_pack_*
        if ( ! function_exists( 'mv_pack_get_all_variable_products' ) ) {
            function mv_pack_get_all_variable_products() {
                return oc_pool_get_all_variable_products();
            }
        }
        
        if ( ! function_exists( 'mv_pack_get_all_pool_ids' ) ) {
            function mv_pack_get_all_pool_ids() {
                return oc_pool_get_all_pool_ids();
            }
        }
        
        if ( ! function_exists( 'mv_pack_is_elementor_page' ) ) {
            function mv_pack_is_elementor_page() {
                return oc_pool_is_elementor_page();
            }
        }
    }
    
    /**
     * Adaugă meniu admin
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'membership-validator-dashboard',
            'Pool Product Manager',
            '🛒 Pool Products',
            'manage_woocommerce',
            'pool-product-manager',
            [ $this, 'admin_page_callback' ]
        );
    }
    
    /**
     * Callback pentru pagina admin
     */
    public function admin_page_callback() {
        // Verifică permisiunile
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Nu aveți permisiuni pentru această pagină.' ) );
        }
        
        // Include template-ul
        $template_path = dirname( __FILE__ ) . '/../../../templates/pool-product-manager/admin-settings.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>Pool Product Manager</h1>';
            echo '<p>Template-ul admin nu a fost găsit.</p></div>';
        }
    }
    
    /**
     * Încarcă assets-urile frontend
     */
    public function enqueue_frontend_assets() {
        // Doar pe paginile de produs cu pachete
        if ( ! is_product() ) {
            return;
        }
        
        global $product;
        if ( ! $product ) {
            return;
        }
        
        // Asigură că $product este un obiect WC_Product valid
        if ( is_string( $product ) || is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }
        
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
            return;
        }
        
        // Verifică dacă funcția helper există înainte s-o apeleze
        if ( function_exists( 'oc_pool_is_package' ) && ! oc_pool_is_package( $product->get_id() ) ) {
            return;
        }
        
        // CSS frontend
        wp_enqueue_style(
            'oc-pool-frontend',
            OC_PLUGIN_URL . 'assets/pool-product-frontend.css',
            [],
            $this->version
        );
        
        // JavaScript frontend
        wp_enqueue_script(
            'oc-pool-frontend',
            OC_PLUGIN_URL . 'assets/pool-product-frontend.js',
            [ 'jquery' ],
            $this->version,
            true
        );
        
        // Localization
        wp_localize_script( 'oc-pool-frontend', 'ocPoolData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'oc_pool_frontend_nonce' ),
            'strings' => [
                'loading' => __( 'Se încarcă...' ),
                'error' => __( 'A apărut o eroare.' ),
                'selectMin' => __( 'Selectează cel puțin %d opțiuni.' ),
                'selectMax' => __( 'Poți selecta maximum %d opțiuni.' )
            ]
        ]);
    }
    
    /**
     * Încarcă assets-urile admin
     */
    public function enqueue_admin_assets( $hook ) {
        // Doar pe paginile de edit produs și pagina ADD-ON-ului
        if ( ! in_array( $hook, [
            'post.php',
            'post-new.php',
            'membership-validator_page_pool-product-manager'
        ] ) ) {
            return;
        }
        
        // Verifică tipul de post pentru pagini de produs
        global $post;
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            if ( ! $post || $post->post_type !== 'product' ) {
                return;
            }
        }
        
        // CSS admin
        wp_enqueue_style(
            'oc-pool-admin',
            OC_PLUGIN_URL . 'assets/pool-product-admin.css',
            [],
            $this->version
        );
        
        // JavaScript admin
        wp_enqueue_script(
            'oc-pool-admin',
            OC_PLUGIN_URL . 'assets/pool-product-admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );
        
        // Localization
        wp_localize_script( 'oc-pool-admin', 'ocPoolAdminData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'oc_pool_admin_nonce' ),
            'strings' => [
                'loading' => __( 'Se încarcă variațiile...' ),
                'error' => __( 'Eroare la încărcarea variațiilor.' ),
                'noVariations' => __( 'Acest produs nu are variații.' ),
                'selectPool' => __( 'Selectează un POOL mai sus pentru a vedea variațiile...' )
            ]
        ]);
    }
    
    /**
     * Notificare că CORE lipsește
     */
    public function core_missing_notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Pool Product Manager</strong> necesită Membership Validator Core pentru a funcționa.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Notificare că WooCommerce lipsește
     */
    public function woocommerce_missing_notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Pool Product Manager</strong> necesită WooCommerce pentru a funcționa.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Log crearea unui pachet
     *
     * @param int $package_id
     * @param array $config
     */
    public function log_package_created( $package_id, $config ) {
        // Pachet creat cu succes
    }
    
    /**
     * Curăță cache-ul pentru un pachet
     *
     * @param int $package_id
     */
    public function clear_package_cache( $package_id ) {
        // Clear cache WordPress
        wp_cache_delete( 'oc_pool_package_' . $package_id );
    }
    
    /**
     * Cleanup date la ștergerea unui pachet
     *
     * @param int $package_id
     */
    public function cleanup_package_data( $package_id ) {
        // Curăță meta-urile
        $meta_keys = [
            '_oc_pool_enabled',
            '_oc_pool_price', 
            '_oc_pool_pool_id',
            '_oc_pool_min_selections',
            '_oc_pool_max_selections',
            '_oc_pool_ui_style',
            '_oc_pool_allow_duplicates',
            '_oc_pool_helper_text',
            '_oc_pool_selected_variations',
            '_oc_pool_allowed_payment_gateways'
        ];
        
        foreach ( $meta_keys as $key ) {
            delete_post_meta( $package_id, $key );
        }
        
        // Curăță cache-ul
        $this->clear_package_cache( $package_id );
    }
    
    /**
     * Filter pentru prețul pachetului
     *
     * @param float $price
     * @param int $package_id
     * @return float
     */
    public function filter_package_price( $price, $package_id ) {
        // Hook pentru alte plugin-uri să modifice prețul
        return $price;
    }
    
    /**
     * Filter pentru selecțiile disponibile
     *
     * @param array $selections
     * @param int $pool_id
     * @return array
     */
    public function filter_available_selections( $selections, $pool_id ) {
        // Hook pentru alte plugin-uri să modifice selecțiile
        return $selections;
    }
    
    /**
     * Obține configurația unui pachet
     *
     * @param int $package_id
     * @return array|false
     */
    public function get_package_config( $package_id ) {
        if ( ! oc_pool_is_package( $package_id ) ) {
            return false;
        }
        
        return [
            'enabled' => get_post_meta( $package_id, '_oc_pool_enabled', true ),
            'price' => get_post_meta( $package_id, '_oc_pool_price', true ),
            'pool_id' => get_post_meta( $package_id, '_oc_pool_pool_id', true ),
            'min_selections' => get_post_meta( $package_id, '_oc_pool_min_selections', true ),
            'max_selections' => get_post_meta( $package_id, '_oc_pool_max_selections', true ),
            'ui_style' => get_post_meta( $package_id, '_oc_pool_ui_style', true ),
            'allow_duplicates' => get_post_meta( $package_id, '_oc_pool_allow_duplicates', true ),
            'helper_text' => get_post_meta( $package_id, '_oc_pool_helper_text', true ),
            'selected_variations' => get_post_meta( $package_id, '_oc_pool_selected_variations', true ),
            'allowed_payment_gateways' => get_post_meta( $package_id, '_oc_pool_allowed_payment_gateways', true )
        ];
    }
    
    /**
     * Actualizează configurația unui pachet
     *
     * @param int $package_id
     * @param array $config
     * @return bool
     */
    public function update_package_config( $package_id, $config ) {
        $meta_map = [
            'enabled' => '_oc_pool_enabled',
            'price' => '_oc_pool_price',
            'pool_id' => '_oc_pool_pool_id',
            'min_selections' => '_oc_pool_min_selections',
            'max_selections' => '_oc_pool_max_selections',
            'ui_style' => '_oc_pool_ui_style',
            'allow_duplicates' => '_oc_pool_allow_duplicates',
            'helper_text' => '_oc_pool_helper_text',
            'selected_variations' => '_oc_pool_selected_variations',
            'allowed_payment_gateways' => '_oc_pool_allowed_payment_gateways'
        ];
        
        foreach ( $config as $key => $value ) {
            if ( isset( $meta_map[ $key ] ) ) {
                update_post_meta( $package_id, $meta_map[ $key ], $value );
            }
        }
        
        // Trigger action
        do_action( 'oc_pool_package_updated', $package_id, $config );
        
        return true;
    }
    
    /**
     * Obține statistici ADD-ON
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;
        
        // Numărul de pachete active (cu backwards compatibility)
        $active_packages = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(DISTINCT pm.post_id) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key IN (%s, %s)
            AND pm.meta_value IN ('1', 'yes', 'on')
            AND p.post_status = 'publish'
            AND p.post_type = 'product'
        ", '_oc_pool_enabled', '_mv_pack_enabled' ) );
        
        // Numărul de produse POOL
        $pool_products = count( oc_pool_get_all_pool_ids() );
        
        return [
            'active_packages' => intval( $active_packages ),
            'pool_products' => intval( $pool_products ),
            'version' => $this->version
        ];
    }
}

// Inițializare ADD-ON
OC_Pool_Product_Manager::get_instance();
