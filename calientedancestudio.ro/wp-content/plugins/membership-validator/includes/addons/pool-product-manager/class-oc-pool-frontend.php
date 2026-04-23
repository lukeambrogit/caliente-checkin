<?php
/**
 * Pool Product Manager - Frontend Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru funcționalitatea frontend a Pool Product Manager
 */
class OC_Pool_Frontend {
    
    /**
     * Flag pentru a preveni inițializarea multiplă a hook-urilor
     * 
     * @var bool
     */
    private static $hooks_initialized = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Previne dublarea hook-urilor
        if ( self::$hooks_initialized ) {
            return;
        }
        
        // Hook-uri pentru interfața frontend doar dacă WooCommerce este activ
        if ( function_exists( 'wc_get_product' ) ) {
            add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'replace_frontend_ui' ] );
            add_action( 'wp_footer', [ $this, 'add_frontend_styles' ] );
        }
        
        self::$hooks_initialized = true;
    }
    
    /**
     * Înlocuiește UI-ul frontend pentru pachete
     */
    public function replace_frontend_ui() {
        // Protecție de bază
        if ( ! function_exists( 'is_product' ) || ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        
        global $product;
        
        if ( ! is_product() || ! $product || $product->get_type() !== 'simple' ) {
            return;
        }
        
        if ( ! function_exists( 'oc_pool_is_package' ) ) {
            return;
        }
        
        if ( ! oc_pool_is_package( $product->get_id() ) ) {
            return;
        }
        
        // Detectare Elementor îmbunătățită
        $is_elementor = $this->is_elementor_page();
        
        // Forțează detecția Elementor prin verificări suplimentare
        if ( !$is_elementor && class_exists( '\Elementor\Plugin' ) ) {
            $is_elementor = true; // Forțează pentru toate paginile cu Elementor activ
        }
        
        // ASCUNDE COMPLET interfața WooCommerce standard
        add_action( 'wp_footer', function() {
            echo '<style>
            /* Ascunde doar formularul standard de add to cart, NU prețul */
            .cart:not(.oc-pool-ui),
            .variations_form:not(.oc-pool-ui),
            .single_variation_wrap:not(.oc-pool-ui),
            
            /* Ascunde dropdown-urile */
            .oc-pool-slots select, 
            select[data-slot], 
            .select_container,
            
            /* Pentru Elementor */
            .elementor-widget-woocommerce-product-add-to-cart .cart:not(.oc-pool-ui),
            .elementor-add-to-cart .variations_form:not(.oc-pool-ui),
            .elementor-add-to-cart .single_variation_wrap:not(.oc-pool-ui)
            {
                display: none !important;
            }
            
            /* Asigură că butonul nostru este vizibil */
            .oc-pool-submit-btn {
                display: inline-block !important;
                visibility: visible !important;
            }
            
            /* Stiluri pentru opțiunile de slot (radio buttons) */
            .slot-option.variation-option.in-stock {
                padding: 10px;
                border: 1px solid #ccc;
                margin: 15px;
                border-radius: 5px;
            }
            
            /* Stiluri pentru checkbox-uri */
            .oc-variation-item {
                padding: 10px;
                border: 1px solid #ccc;
                margin: 15px;
                border-radius: 5px;
            }
            
            /* Stiluri pentru câmpul de cantitate */
            .quantity.woocommerce-quantity {
                margin-left: 15px !important;
                margin-bottom: 15px !important;
                margin-right: 15px !important;
            }
            </style>';
        });
        
        // Afișează UI-ul custom
        try {
            $this->render_frontend_ui( $product, $is_elementor );
        } catch ( Throwable $e ) {
            // Capturează orice eroare pentru a nu afecta pagina
            echo '<div class="woocommerce-error">Eroare la încărcarea interfeței pentru pachet.</div>';
        }
    }
    
    /**
     * Detectează dacă pagina folosește Elementor
     *
     * @return bool
     */
    private function is_elementor_page() {
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
     * Renderează interfața frontend
     *
     * @param WC_Product $product
     * @param bool $is_elementor
     */
    private function render_frontend_ui( $product, $is_elementor = false ) {
        $pack_id = $product->get_id();
        
        // Încarcă configurația cu fallback la formatul vechi
        if ( ! function_exists( 'oc_pool_get_package_config' ) ) {
            echo '<div class="woocommerce-error">Funcția de configurare nu este disponibilă.</div>';
            return;
        }
        
        $config = oc_pool_get_package_config( $pack_id );
        
        if ( ! $config ) {
            echo '<div class="woocommerce-error">Configurația pachetului nu este validă.</div>';
            return;
        }
        
        $pool_id = $config['pool_id'];
        $pool_product = $pool_id ? wc_get_product( $pool_id ) : null;
        
        if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
            echo '<div class="woocommerce-error">Produsul POOL nu este disponibil.</div>';
            return;
        }
        
        // Configurație pachet din helper function
        $pack_price = $config['price'];
        if ( ! $pack_price ) $pack_price = $product->get_price();
        
        $min_selections = max( 1, intval( $config['min_selections'] ) );
        $max_selections = intval( $config['max_selections'] );
        $ui_style = $config['ui_style'] ?: 'checkboxes';
        $helper_text = $config['helper_text'];
        
        // Obține variațiile selectate în admin pentru acest pachet
        $selected_variation_ids = $config['selected_variations'];
        if ( ! is_array( $selected_variation_ids ) ) {
            $selected_variation_ids = [];
        }
        
        // Variații disponibile - filtrează doar pe cele selectate în admin
        $all_variations = $pool_product->get_available_variations();
        $available_variations = array_filter( $all_variations, function($v) use ($selected_variation_ids) {
            // Include doar variațiile selectate în admin ȘI care sunt purchasable și active
            return in_array( $v['variation_id'], $selected_variation_ids ) && 
                   $v['is_purchasable'] && 
                   $v['variation_is_active'];
        });
        
        if ( empty( $available_variations ) ) {
            if ( empty( $selected_variation_ids ) ) {
                echo '<div class="woocommerce-error">Nu au fost selectate variații pentru acest pachet. Vă rugăm să configurați pachetul în admin.</div>';
            } else {
                echo '<div class="woocommerce-error">Nu există variații disponibile pentru acest pachet (toate sunt inactive sau indisponibile).</div>';
            }
            return;
        }
        
        ?>
        <div class="oc-pool-container woocommerce-product-form">
            <form class="cart oc-pool-ui" method="post" enctype="multipart/form-data">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $pack_id ); ?>">
                <input type="hidden" name="oc_pool_pool_id" value="<?php echo esc_attr( $pool_id ); ?>">
                
                <!-- Mesaj ajutător -->
                <?php if ( $helper_text ): ?>
                <div class="oc-pool-helper">
                    <p><?php echo wp_kses_post( $helper_text ); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Selecții -->
                <div class="oc-pool-selections">
                    
                    <?php if ( $ui_style === 'slots' ): ?>
                        <?php $this->render_slots_ui( $available_variations, $min_selections, $max_selections, $is_elementor ); ?>
                    <?php else: ?>
                        <?php $this->render_checkboxes_ui( $available_variations, $min_selections, $max_selections, $is_elementor ); ?>
                    <?php endif; ?>
                </div>
                
                <!-- Cantitate -->
                <div class="quantity woocommerce-quantity">
                    <input type="number" id="oc_pool_quantity" name="quantity" value="1" min="1" step="1" class="input-text qty text">
                </div>
                
                <!-- Buton Adaugă în coș -->
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $pack_id ); ?>" class="button alt add-to-cart single_add_to_cart_button oc-pool-submit-btn">
                    Adaugă în coș
                </button>
            </form>
        </div>
        
        <?php $this->render_scripts( $min_selections, $max_selections, $is_elementor ); ?>
        <?php
    }
    
    /**
     * Renderează UI cu checkbox-uri
     *
     * @param array $variations
     * @param int $min
     * @param int $max
     * @param bool $is_elementor
     */
    private function render_checkboxes_ui( $variations, $min, $max, $is_elementor = false ) {
        // Lista simplă pentru TOATE scenariile
        echo '<div class="oc-pool-variations-list" style="margin: 16px 0;">';
        
        foreach ( $variations as $variation ) {
            $variation_obj = wc_get_product( $variation['variation_id'] );
            if ( ! $variation_obj ) continue;
            
            $label = wc_get_formatted_variation( $variation_obj, true, false );
            $stock_status = $variation_obj->is_in_stock();
            
            echo '<div class="oc-variation-item">';
            
            echo '<input type="checkbox" name="oc_pool_selections[]" value="' . esc_attr( $variation['variation_id'] ) . '" 
                   id="variation_' . $variation['variation_id'] . '"' . 
                   ($stock_status ? '' : ' disabled') . '>';
            
            echo '<label for="variation_' . $variation['variation_id'] . '">';
            
            echo '<span>' . esc_html( $label ) . '</span>';
            
            if ( !$stock_status ) {
                echo '<span style="color: #dc3545; font-size: 12px;">Stoc epuizat</span>';
            }
            
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Renderează UI cu sloturi (radio buttons)
     *
     * @param array $variations
     * @param int $min
     * @param int $max
     * @param bool $is_elementor
     */
    private function render_slots_ui( $variations, $min, $max, $is_elementor = false ) {
        $slots_count = $max ?: max( $min, 3 );
        
        // Container cu clase standard WordPress/WooCommerce
        echo '<div class="woocommerce-slot-selection-wrapper slot-based-selection">';
        
        for ( $i = 1; $i <= $slots_count; $i++ ) {
            echo '<div class="woocommerce-slot-section slot-' . $i . '" data-slot="' . $i . '">';
            
            // Grid pentru opțiuni cu clase standard
            echo '<div class="slot-options-grid variations-grid">';
            
            // Opțiunea "nimic selectat"
            echo '<div class="slot-option empty-option" data-slot="' . $i . '">';
            
            echo '<input type="radio" name="oc_pool_selections[]" value="" 
                   id="slot_' . $i . '_empty" data-slot="' . $i . '" class="slot-radio" checked>';
            
            echo '<div class="radio-indicator"></div>';
            
            echo '</div>';
            
            // Opțiunile de variații
            foreach ( $variations as $variation ) {
                $variation_obj = wc_get_product( $variation['variation_id'] );
                if ( ! $variation_obj ) continue;
                
                $label = wc_get_formatted_variation( $variation_obj, true, false );
                $image = get_the_post_thumbnail( $variation_obj->get_parent_id(), 'thumbnail' );
                $stock_class = $variation_obj->is_in_stock() ? 'in-stock' : 'out-of-stock';
                
                echo '<div class="slot-option variation-option ' . $stock_class . '" 
                      data-slot="' . $i . '" data-variation="' . $variation['variation_id'] . '">';
                
                // Radio button vizibil
                echo '<input type="radio" name="oc_pool_selections[]" value="' . esc_attr( $variation['variation_id'] ) . '" 
                       id="slot_' . $i . '_var_' . $variation['variation_id'] . '" data-slot="' . $i . '" class="slot-radio"' . 
                       ($variation_obj->is_in_stock() ? '' : ' disabled') . '>';
                
                // Label pentru radio button
                echo '<label for="slot_' . $i . '_var_' . $variation['variation_id'] . '" class="variation-label">';
                
                // Imaginea produsului
                if ( $image ) {
                    echo '<div class="variation-image-wrapper">';
                    echo str_replace('class="', 'class="variation-thumbnail ', $image);
                    echo '</div>';
                }
                
                // Titlul variatiei
                echo '<span class="variation-title">' . esc_html( $label ) . '</span>';
                
                echo '</label>';
                
                echo '</div>';
            }
            
            echo '</div>'; // End slot options
            echo '</div>'; // End slot section
        }
        
        echo '</div>'; // End slots container
        
        // JavaScript minimal pentru funcționalitate
        ?>
        <script type="text/javascript">
        // JavaScript minimal pentru funcționalitate de bază
        jQuery(document).ready(function($) {
            // Ascunde TOATE dropdown-urile vechi de sloturi
            $('.oc-pool-slots select, select[data-slot], .select_container, select[name="oc_pool_selections[]"]').hide();
            $('.oc-pool-slots').hide(); // Ascunde containerul vechi complet
            
            // Click pe slot-uri folosind event delegation
            $(document).on('click', '.slot-option', function() {
                if ($(this).find('input[type="radio"]').is(':disabled')) return;
                
                var $this = $(this);
                var $slot = $this.closest('.woocommerce-slot-section');
                var $radio = $this.find('input[type="radio"]');
                
                // Elimină selecția din toate opțiunile din slot
                $slot.find('.slot-option').removeClass('selected active');
                
                // Adaugă selecția la opțiunea curentă
                $this.addClass('selected active');
                
                // Activează radio button
                $radio.prop('checked', true).trigger('change');
            });
            
            // Hover effects prin clase standard
            $(document).on('mouseenter', '.slot-option', function() {
                if (!$(this).find('input[type="radio"]').is(':checked') && !$(this).find('input[type="radio"]').is(':disabled')) {
                    $(this).addClass('hover');
                }
            }).on('mouseleave', '.slot-option', function() {
                if (!$(this).find('input[type="radio"]').is(':checked')) {
                    $(this).removeClass('hover');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderează JavaScript pentru validări
     *
     * @param int $min_selections
     * @param int $max_selections
     * @param bool $is_elementor
     */
    private function render_scripts( $min_selections, $max_selections, $is_elementor = false ) {
        $form_selector = $is_elementor ? '.oc-pool-elementor .oc-pool-ui' : '.oc-pool-ui';
        $button_selector = $is_elementor ? '.elementor-button' : '.single_add_to_cart_button';
        
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            var $form = $('<?php echo $form_selector; ?>');
            var unlimitedSelections = <?php echo (int) OC_POOL_MAX_SELECTIONS_UNLIMITED; ?>;
            var minSelections = <?php echo $min_selections; ?>;
            var maxSelections = <?php echo $max_selections ? (int) $max_selections : (int) OC_POOL_MAX_SELECTIONS_UNLIMITED; ?>;
            
            function validateSelections() {
                var selected = $form.find('input[name="oc_pool_selections[]"]:checked, select[name="oc_pool_selections[]"]').filter(function() {
                    return $(this).val() !== '';
                }).length;
                
                var $submit = $form.find('<?php echo $button_selector; ?>');
                
                if (selected < minSelections) {
                    $submit.prop('disabled', true).text('Selectează cel puțin ' + minSelections + ' opțiuni');
                    return false;
                } else if (maxSelections < unlimitedSelections && selected > maxSelections) {
                    $submit.prop('disabled', true).text('Poți selecta maximum ' + maxSelections + ' opțiuni');
                    return false;
                } else {
                    $submit.prop('disabled', false).text('Adaugă în coș');
                    return true;
                }
            }
            
            $form.on('change', 'input[name="oc_pool_selections[]"], select[name="oc_pool_selections[]"]', validateSelections);
            validateSelections(); // Validare inițială
            
            // Previne submit-ul invalid
            $form.on('submit', function(e) {
                if (!validateSelections()) {
                    e.preventDefault();
                    alert('Nu ai selectat minimul de ' + minSelections + ' cursuri.');
                }
            });
            
            <?php if ( $is_elementor ): ?>
            // Forțează re-styling pentru Elementor după AJAX
            $(document).ajaxComplete(function() {
                validateSelections();
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }
    
    /**
     * Adaugă stilurile frontend
     */
    public function add_frontend_styles() {
        // Verifică dacă suntem pe o pagină de produs cu pachet
        if ( ! is_product() ) {
            return;
        }
        
        global $product;
        if ( ! $product || ! oc_pool_is_package( $product->get_id() ) ) {
            return;
        }
        
        $is_elementor = $this->is_elementor_page();
        
        ?>
        <style type="text/css">
        /* Stiluri de bază OC Pool */
        .oc-pool-container { margin: 20px 0; }
        .oc-pool-ui { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .oc-pool-price { margin-bottom: 20px; text-align: center; }
        .oc-pool-price .price { font-size: 24px; font-weight: bold; color: #333; }
        .oc-pool-helper { margin-bottom: 20px; padding: 10px; background: #f9f9f9; border-radius: 3px; }
        .oc-pool-selections { margin-bottom: 20px; }
        .oc-pool-selections h4 { margin-bottom: 15px; }
        .oc-variation-item { margin-bottom: 10px; padding: 10px; border: 1px solid #eee; border-radius: 3px; }
        .oc-variation-item label { cursor: pointer; display: block; }
        .oc-pool-slots { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .oc-pool-slot { padding: 15px; border: 2px solid #ddd; border-radius: 5px; text-align: center; }
        .oc-pool-slot.filled { border-color: #007cba; background: #f0f8ff; }
        
        <?php if ( $is_elementor ): ?>
        /* Stiluri specifice Elementor */
        .oc-pool-elementor .oc-pool-ui {
            background: transparent;
            border: none;
            padding: 0;
            box-shadow: none;
        }
        
        .oc-pool-elementor .oc-pool-price .price {
            color: var(--e-global-color-primary, #007cba);
            font-family: var(--e-global-typography-primary-font-family, inherit);
        }
        
        .oc-pool-elementor .oc-variation-item {
            border-color: var(--e-global-color-accent, #ddd);
            background: var(--e-global-color-secondary, #f9f9f9);
        }
        
        .oc-pool-elementor .elementor-button {
            background-color: var(--e-global-color-primary, #007cba);
            border-color: var(--e-global-color-primary, #007cba);
            color: var(--e-global-color-white, #ffffff);
            font-family: var(--e-global-typography-accent-font-family, inherit);
            font-size: var(--e-global-typography-accent-font-size, 16px);
            font-weight: var(--e-global-typography-accent-font-weight, 600);
            text-transform: var(--e-global-typography-accent-text-transform, uppercase);
            letter-spacing: var(--e-global-typography-accent-letter-spacing, 0.1em);
            padding: 12px 24px;
            border-radius: var(--e-global-color-primary-border-radius, 3px);
            transition: all 0.3s ease;
        }
        
        .oc-pool-elementor .elementor-button:hover {
            background-color: var(--e-global-color-primary-hover, #005a87);
            border-color: var(--e-global-color-primary-hover, #005a87);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .oc-pool-elementor .elementor-field-textual {
            border-color: var(--e-global-color-accent, #ddd);
            border-radius: var(--e-global-color-accent-border-radius, 3px);
            padding: 10px 15px;
            font-family: var(--e-global-typography-text-font-family, inherit);
        }
        
        .oc-pool-elementor .elementor-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .oc-pool-elementor .elementor-column {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            border: 2px solid var(--e-global-color-accent, #ddd);
            border-radius: var(--e-global-color-accent-border-radius, 5px);
            text-align: center;
            background: var(--e-global-color-light, #f9f9f9);
        }
        
        .oc-pool-elementor .elementor-column.filled {
            border-color: var(--e-global-color-primary, #007cba);
            background: var(--e-global-color-primary-light, #f0f8ff);
        }
        
        /* Responsive pentru Elementor */
        @media (max-width: 768px) {
            .oc-pool-elementor .elementor-row {
                flex-direction: column;
            }
            
            .oc-pool-elementor .elementor-column {
                min-width: auto;
            }
        }
        <?php endif; ?>
        </style>
        <?php
    }
}
