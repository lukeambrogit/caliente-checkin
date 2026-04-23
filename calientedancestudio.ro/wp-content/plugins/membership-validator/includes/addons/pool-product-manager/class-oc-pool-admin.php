<?php
/**
 * Pool Product Manager - Admin Component
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clasa pentru funcționalitatea admin a Pool Product Manager
 */
class OC_Pool_Admin {
    
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
        
        // Înregistrez hook-urile IMEDIAT (cu safety checks în execuție)
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_admin_fields' ] );
        add_action( 'woocommerce_process_product_meta_simple', [ $this, 'save_admin_fields' ] );
        add_action( 'admin_notices', [ $this, 'show_admin_notices' ] );
        
        // AJAX handler for pool variations is handled by OC_Pool_Ajax::get_pool_variations()
        // Removed duplicate registration to avoid triple-firing
        
        // Migration helpers pentru compatibilitate
        add_action( 'admin_init', [ $this, 'maybe_migrate_old_meta' ] );
        
        self::$hooks_initialized = true;
    }
    
    /**
     * Adaugă câmpurile admin pentru configurarea pachetelor
     */
    public function add_admin_fields() {
        // Safety checks pentru funcțiile necesare
        if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'oc_pool_get_package_config' ) ) {
            return;
        }
        
        global $post;
        if ( 'product' !== get_post_type( $post ) ) return;
        
        // Verifică tipul de produs din POST dacă există, altfel din baza de date
        $product_type = isset( $_POST['product-type'] ) ? $_POST['product-type'] : '';
        if ( ! $product_type && $post->ID ) {
            $product = wc_get_product( $post->ID );
            $product_type = $product ? $product->get_type() : '';
        }
        
        // Afișează DOAR pentru produse simple
        if ( $product_type !== 'simple' ) return;
        
        // Încarcă configurația existentă (cu fallback pentru formatul vechi)
        $config = oc_pool_get_package_config( $post->ID );
        if ( ! $config ) {
            $config = [
                'enabled' => false,
                'price' => '',
                'pool_id' => '',
                'min_selections' => 1,
                'max_selections' => '',
                'ui_style' => 'checkboxes',
                'allow_duplicates' => false,
                'helper_text' => '',
                'selected_variations' => [],
                'allowed_payment_gateways' => []
            ];
        }
        
        echo '<div class="options_group oc-pool-settings">';
        echo '<h3>Pool Product Manager - Mod Pachet</h3>';
        
        // Activare Mod Pachet
        woocommerce_wp_checkbox([
            'id' => '_oc_pool_enabled',
            'label' => 'Activează Mod Pachet',
            'description' => 'Permite vânzarea ca pachet cu selecție din produs POOL',
            'value' => $config['enabled'] ? 'yes' : 'no'
        ]);
        
        // Preț pachet
        woocommerce_wp_text_input([
            'id' => '_oc_pool_price',
            'label' => 'Preț pachet (' . get_woocommerce_currency_symbol() . ')',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'description' => 'Lasă gol pentru a folosi Regular Price',
            'value' => $config['price']
        ]);
        
        // Dual Mode Toggle Section  
        $is_dual_mode = ($config['dual_mode'] === 'yes' || $config['dual_mode'] === true);
        
        // Debug pentru a vedea valorile
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
            echo '<!-- DEBUG: dual_mode = ' . var_export( $config['dual_mode'], true ) . ', is_dual_mode = ' . var_export( $is_dual_mode, true ) . ' -->';
        }
        
        echo '<div class="oc-pool-mode-toggle">';
        echo '<h4 class="oc-pool-mode-title">Mod Pool</h4>';
        echo '<p class="oc-pool-mode-description">Selectează modul de funcționare:</p>';
        
        // Container pentru radio buttons pe același rând
        echo '<div class="oc-pool-mode-radio-container">';
         
         echo '<label class="oc-pool-mode-option">';
         echo '<input type="radio" name="_oc_pool_mode_selection" value="single"' . checked( !$is_dual_mode, true, false ) . '>';
         echo '<span><strong>Single Pool</strong> - O selecție din unui produs POOL</span>';
         echo '</label>';
         
         echo '<label class="oc-pool-mode-option">';
         echo '<input type="radio" name="_oc_pool_mode_selection" value="dual"' . checked( $is_dual_mode, true, false ) . '>';
         echo '<span><strong>Dual Pool</strong> - Două selecții (din același sau din pool-uri diferite)</span>';
         echo '</label>';
         
         echo '</div>'; // Închide oc-pool-mode-radio-container
         
         // Hidden field pentru compatibilitate
         echo '<input type="hidden" id="_oc_pool_dual_mode" name="_oc_pool_dual_mode" value="' . ($is_dual_mode ? 'yes' : 'no') . '">';
         
         echo '</div>'; // Închide oc-pool-mode-toggle
        
        // Secțiune separată pentru Single Mode
        echo '<div id="oc-pool-single-mode" class="oc-pool-mode-section">';
        echo '<h4>Configurare Pool Standard</h4>';
        
        // Selector POOL - dropdown cu toate produsele variabile
        $pool_id = $config['pool_id'];
        $variable_products = oc_pool_get_all_variable_products();
        
        echo '<p class="form-field _oc_pool_pool_id_field">';
        echo '<label for="_oc_pool_pool_id">Produs POOL (variabil)</label>';
        echo '<select id="_oc_pool_pool_id" name="_oc_pool_pool_id">';
        echo '<option value="">-- Selectează produs variabil --</option>';
        
        // Debug info
        if ( empty( $variable_products ) ) {
            echo '<option value="" disabled>Nu s-au găsit produse variabile</option>';
        } else {
            echo '<option value="" disabled>Găsite ' . count( $variable_products ) . ' produse variabile</option>';
        }
        
        foreach ( $variable_products as $product_data ) {
            $selected = selected( $pool_id, $product_data['id'], false );
            $variations_count = $product_data['variations_count'];
            $status_badge = '';
            
            if ( $product_data['status'] !== 'publish' ) {
                $status_badge = ' [' . strtoupper( $product_data['status'] ) . ']';
            }
            
            echo '<option value="' . esc_attr( $product_data['id'] ) . '"' . $selected . '>';
            echo esc_html( $product_data['title'] ) . ' (#' . $product_data['id'] . ')';
            echo ' - ' . $variations_count . ' variații' . $status_badge;
            echo '</option>';
        }
        
        echo '</select>';
        echo '<span class="description">Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)</span>';
        
        // Debug temporar - afișează toate produsele găsite
        if ( current_user_can( 'manage_options' ) && isset( $_GET['oc_debug'] ) ) {
            echo '<div class="oc-pool-debug-box">';
            echo '<strong>Debug - Toate produsele din site:</strong><br>';
            
            $all_products = get_posts([
                'post_type' => 'product',
                'post_status' => ['publish', 'private', 'draft'],
                'posts_per_page' => -1
            ]);
            
            foreach ( $all_products as $prod ) {
                $p = wc_get_product( $prod->ID );
                if ( $p ) {
                    echo sprintf( 'ID: %d, Titlu: %s, Tip: %s, Status: %s<br>', 
                        $prod->ID, $prod->post_title, $p->get_type(), $prod->post_status );
                }
            }
            echo '</div>';
        } else {
            echo '<br><small><a href="' . add_query_arg( 'oc_debug', '1' ) . '">Debug: Vezi toate produsele</a></small>';
        }
        echo '</p>';
        
        // Min selecții
        woocommerce_wp_text_input([
            'id' => '_oc_pool_min_selections',
            'label' => 'Selecții minime (obligatorii)',
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
            'description' => 'Numărul minim de variații ce trebuie selectate',
            'value' => $config['min_selections']
        ]);
        
        // Max selecții
        woocommerce_wp_text_input([
            'id' => '_oc_pool_max_selections',
            'label' => 'Selecții maxime (opțional)',
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
            'description' => 'Numărul maxim de variații ce pot fi selectate (opțional)',
            'value' => $config['max_selections']
        ]);
        
        // Stil UI
        woocommerce_wp_select([
            'id' => '_oc_pool_ui_style',
            'label' => 'Stil UI',
            'options' => [
                'slots' => 'Radio pe sloturi (Slot 1, Slot 2...)',
                'checkboxes' => 'Checkbox-uri (listă unică)'
            ],
            'description' => 'Modul de afișare a selecțiilor în front-end',
            'value' => $config['ui_style'] ?: 'checkboxes'
        ]);
        
        // Politică duplicate
        woocommerce_wp_checkbox([
            'id' => '_oc_pool_allow_duplicates',
            'label' => 'Permite duplicate prin cantitate',
            'description' => 'Implicit: duplicate interzise în același pachet, permise doar când qty > 1',
            'value' => $config['allow_duplicates'] ? 'yes' : 'no'
        ]);
        
        // Mesaj ajutător
        woocommerce_wp_textarea_input([
            'id' => '_oc_pool_helper_text',
            'label' => 'Mesaj ajutător',
            'description' => 'Text afișat deasupra selecțiilor (opțional)',
            'value' => $config['helper_text']
        ]);

        // Metode de plată permise pentru acest pachet (opțional)
        $selected_gateways = $config['allowed_payment_gateways'] ?? [];
        if ( ! is_array( $selected_gateways ) ) {
            $selected_gateways = [];
        }

        echo '<p class="form-field _oc_pool_allowed_payment_gateways_field">';
        echo '<label>Metode de plată permise</label>';
        echo '<span class="wrap">';
        echo '<label>';
        echo '<input type="checkbox" name="_oc_pool_allowed_payment_gateways[]" value="oc_7card"' . checked( in_array( 'oc_7card', $selected_gateways, true ), true, false ) . '>7CARD';
        echo '</label>';
        echo '<label>';
        echo '<input type="checkbox" name="_oc_pool_allowed_payment_gateways[]" value="oc_esx"' . checked( in_array( 'oc_esx', $selected_gateways, true ), true, false ) . '>ESX';
        echo '</label>';
        echo '</span>';
        echo '<span class="description">Selectează explicit metodele permise pentru acest produs POOL. Pentru gateway-urile 7CARD/ESX configurate ca POOL-only, dacă nu bifezi metoda aici, ea nu va fi afișată la checkout.</span>';
        echo '</p>';

        // Bifă abonament VIP/Nelimitat
        $is_unlimited_pool = get_post_meta( $post->ID, '_oc_pool_is_unlimited', true );
        echo '<p class="form-field _oc_pool_is_unlimited_field">';
        echo '<label for="_oc_pool_is_unlimited">Abonament VIP / Nelimitat</label>';
        echo '<input type="checkbox" id="_oc_pool_is_unlimited" name="_oc_pool_is_unlimited" value="yes"' . checked( $is_unlimited_pool, 'yes', false ) . '>';
        echo '<span class="description">Bifă dacă acest pachet oferă acces nelimitat (fără număr fix de şedințe şi fără dată de expirare). Implicit nebifat.</span>';
        echo '</p>';
        
                 // Selecția variațiilor din POOL pentru Single Mode
         echo '<div id="oc-pool-variations-selector" style="' . ( $pool_id ? 'display: block;' : 'display: none;' ) . '">';
         echo '<p class="form-field">';
         echo '<label><strong>Selectează variațiile disponibile în acest pachet:</strong></label>';
         echo '<div id="oc-pool-variations-list">';
         if ( $pool_id ) {
             // Pre-populează variațiile pentru Single Mode dacă există
             $selected_variations = $config['selected_variations'] ?? [];
            if ( ! is_array( $selected_variations ) ) {
                $selected_variations = [];
            }
            $this->render_pool_variations_html( (int) $pool_id, $selected_variations, '_oc_pool_selected_variations' );
         } else {
             echo '<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>';
         }
         echo '</div>';
         echo '<span class="description">Bifează doar variațiile pe care vrei să le incluzi în acest pachet.</span>';
         echo '</p>';
         echo '</div>';
        
        echo '</div>'; // Închide oc-pool-single-mode
        
        // Secțiune separată pentru Dual Mode
        echo '<div id="oc-pool-dual-mode" class="oc-pool-mode-section" style="display: none;">';
        echo '<h4>Configurare Dual Pool Mode</h4>';
        echo '<p class="description">Configurează 2 selecții din același produs POOL sau din pool-uri diferite.</p>';
        
        // Pool 1
        echo '<div class="oc-dual-pool-section">';
        echo '<h5>Prima Selecție (Pool 1)</h5>';
        
        // Label pentru Pool 1
        woocommerce_wp_text_input([
            'id' => '_oc_pool_pool1_label',
            'label' => 'Label Pool 1',
            'description' => 'Textul afișat pentru prima selecție (ex: "Prima opțiune:")',
            'value' => $config['pool1_label'] ?: 'Prima selecție:',
            'wrapper_class' => 'form-field-wide'
        ]);
        
        // Selector Pool 1
        echo '<p class="form-field _oc_pool_pool1_id_field">';
        echo '<label for="_oc_pool_pool1_id">Produs POOL 1 (variabil)</label>';
        echo '<select id="_oc_pool_pool1_id" name="_oc_pool_pool1_id">';
        echo '<option value="">-- Selectează produs variabil --</option>';
        
        foreach ( $variable_products as $product_data ) {
            $selected = selected( $config['pool1_id'], $product_data['id'], false );
            echo '<option value="' . esc_attr( $product_data['id'] ) . '"' . $selected . '>';
            echo esc_html( $product_data['title'] ) . ' (#' . $product_data['id'] . ') - ' . $product_data['variations_count'] . ' variații';
            echo '</option>';
        }
        
        echo '</select>';
        echo '</p>';
        
        // Selector variații Pool 1
        echo '<div id="oc-pool1-variations-selector" style="' . ( $config['pool1_id'] ? 'display: block;' : 'display: none;' ) . '">';
        echo '<p class="form-field">';
        echo '<label><strong>Selectează variațiile pentru Pool 1:</strong></label>';
        echo '<div id="oc-pool1-variations-list">';
        if ( $config['pool1_id'] ) {
            // Pre-populează variațiile pentru Pool 1 dacă există
            $pool1_variations = $config['pool1_variations'] ?? [];
            if ( ! is_array( $pool1_variations ) ) {
                $pool1_variations = [];
            }
            $this->render_pool_variations_html( (int) $config['pool1_id'], $pool1_variations, '_oc_pool_pool1_variations' );
        } else {
            echo '<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>';
        }
        echo '</div>';
        echo '<span class="description">Bifează doar variațiile pe care vrei să le incluzi în Pool 1.</span>';
        echo '</p>';
        echo '</div>';
        
                 // Min selecții Pool 1
         woocommerce_wp_text_input([
             'id' => '_oc_pool_pool1_min',
             'label' => 'Selecții minime Pool 1',
             'type' => 'number',
             'custom_attributes' => ['min' => '1'],
             'value' => $config['pool1_min'] ?: '1'
         ]);
         
         // Stil UI Pool 1
         woocommerce_wp_select([
             'id' => '_oc_pool_pool1_ui_style',
             'label' => 'Stil UI Pool 1',
             'options' => [
                 'slots' => 'Radio pe sloturi (Slot 1, Slot 2...)',
                 'checkboxes' => 'Checkbox-uri (listă unică)'
             ],
             'description' => 'Modul de afișare a selecțiilor pentru Pool 1 în front-end',
             'value' => $config['pool1_ui_style'] ?: 'checkboxes'
         ]);
        
        echo '</div>'; // Închide Pool 1
        
        // Pool 2
        echo '<div class="oc-dual-pool-section">';
        echo '<h5>A Doua Selecție (Pool 2)</h5>';
        
        // Label pentru Pool 2
        woocommerce_wp_text_input([
            'id' => '_oc_pool_pool2_label',
            'label' => 'Label Pool 2',
            'description' => 'Textul afișat pentru a doua selecție (ex: "A doua opțiune:")',
            'value' => $config['pool2_label'] ?: 'A doua selecție:',
            'wrapper_class' => 'form-field-wide'
        ]);
        
        // Selector Pool 2
        echo '<p class="form-field _oc_pool_pool2_id_field">';
        echo '<label for="_oc_pool_pool2_id">Produs POOL 2 (variabil)</label>';
        echo '<select id="_oc_pool_pool2_id" name="_oc_pool_pool2_id">';
        echo '<option value="">-- Selectează produs variabil --</option>';
        
        foreach ( $variable_products as $product_data ) {
            $selected = selected( $config['pool2_id'], $product_data['id'], false );
            echo '<option value="' . esc_attr( $product_data['id'] ) . '"' . $selected . '>';
            echo esc_html( $product_data['title'] ) . ' (#' . $product_data['id'] . ') - ' . $product_data['variations_count'] . ' variații';
            echo '</option>';
        }
        
        echo '</select>';
        echo '</p>';
        
        // Selector variații Pool 2
        echo '<div id="oc-pool2-variations-selector" style="' . ( $config['pool2_id'] ? 'display: block;' : 'display: none;' ) . '">';
        echo '<p class="form-field">';
        echo '<label><strong>Selectează variațiile pentru Pool 2:</strong></label>';
        echo '<div id="oc-pool2-variations-list">';
        if ( $config['pool2_id'] ) {
            // Pre-populează variațiile pentru Pool 2 dacă există
            $pool2_variations = $config['pool2_variations'] ?? [];
            if ( ! is_array( $pool2_variations ) ) {
                $pool2_variations = [];
            }
            $this->render_pool_variations_html( (int) $config['pool2_id'], $pool2_variations, '_oc_pool_pool2_variations' );
        } else {
            echo '<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>';
        }
        echo '</div>';
        echo '<span class="description">Bifează doar variațiile pe care vrei să le incluzi în Pool 2.</span>';
        echo '</p>';
        echo '</div>';
        
                 // Min selecții Pool 2
         woocommerce_wp_text_input([
             'id' => '_oc_pool_pool2_min',
             'label' => 'Selecții minime Pool 2',
             'type' => 'number',
             'custom_attributes' => ['min' => '1'],
             'value' => $config['pool2_min'] ?: '1'
         ]);
         
         // Stil UI Pool 2
         woocommerce_wp_select([
             'id' => '_oc_pool_pool2_ui_style',
             'label' => 'Stil UI Pool 2',
             'options' => [
                 'slots' => 'Radio pe sloturi (Slot 1, Slot 2...)',
                 'checkboxes' => 'Checkbox-uri (listă unică)'
             ],
             'description' => 'Modul de afișare a selecțiilor pentru Pool 2 în front-end',
             'value' => $config['pool2_ui_style'] ?: 'checkboxes'
         ]);
        
        echo '</div>'; // Închide Pool 2
        
        // Opțiuni pentru duplicate
        woocommerce_wp_checkbox([
            'id' => '_oc_pool_allow_same_variation',
            'label' => 'Permite aceeași variație în ambele pool-uri',
            'description' => 'Dacă este debifat, clientul nu poate selecta aceeași variație în ambele pool-uri',
            'value' => $config['allow_same_variation'] ? 'yes' : 'no'
        ]);
        
        echo '</div>'; // Închide oc-pool-dual-mode
        
        // JavaScript pentru îmbunătățirea selectorului
        $this->render_admin_javascript( $post->ID );
        
        // CSS pentru admin
        $this->render_admin_styles();
    }
    
    /**
     * Renderează JavaScript pentru admin
     *
     * @param int $post_id
     */
    private function render_admin_javascript( $post_id ) {
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            // Stilizare pentru selectorul POOL
            $('#_oc_pool_pool_id').css('min-height', '30px');
            
            // Validare în timp real
            $('#_oc_pool_min_selections, #_oc_pool_max_selections').on('change', function() {
                var min = parseInt($('#_oc_pool_min_selections').val()) || 0;
                var max = parseInt($('#_oc_pool_max_selections').val()) || 0;
                
                if (max > 0 && max < min) {
                    alert('Selecțiile maxime nu pot fi mai puține decât minimele!');
                    $(this).focus();
                }
            });
            
            // Funcție pentru toggle între single și dual mode
            function togglePoolMode() {
                var selectedMode = $('input[name="_oc_pool_mode_selection"]:checked').val();
                var isDualMode = (selectedMode === 'dual');
                
                // Actualizează hidden field pentru salvare
                $('#_oc_pool_dual_mode').val(isDualMode ? 'yes' : 'no');
                
                if (isDualMode) {
                    $('#oc-pool-single-mode').hide();
                    $('#oc-pool-dual-mode').show();
                } else {
                    $('#oc-pool-single-mode').show();
                    $('#oc-pool-dual-mode').hide();
                }
            }
            
            // Ascultă schimbarea radio buttons pentru mod
            $('input[name="_oc_pool_mode_selection"]').on('change', togglePoolMode);
            
            // Inițializează la încărcare cu delay pentru a fi sigur că DOM este gata
            setTimeout(function() {
                togglePoolMode();
            }, 100);
            
            // Ascunde/afișează panoul când se schimbă tipul de produs
            function togglePackagePanel() {
                var productType = $('#product-type').val();
                var $panel = $('.oc-pool-settings');
                
                if (productType === 'simple') {
                    $panel.show();
                } else {
                    $panel.hide();
                }
            }
            
            // Ascultă schimbarea tipului de produs
            $('#product-type').on('change', togglePackagePanel);
            
            // Verifică inițial
            togglePackagePanel();
            
            // Încărcare dinamică variații din POOL
            $('#_oc_pool_pool_id').on('change', function() {
                var poolId = $(this).val();
                var $container = $('#oc-pool-variations-selector');
                var $list = $('#oc-pool-variations-list');
                
                if (!poolId) {
                    $container.hide();
                    $list.html('<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>');
                    return;
                }
                
                $list.html('<em>Se încarcă variațiile...</em>');
                $container.show();
                
                // AJAX pentru încărcarea variațiilor
                $.post(ajaxurl, {
                    action: 'oc_pool_get_pool_variations',
                    pool_id: poolId,
                    package_id: <?php echo $post_id; ?>,
                    security: '<?php echo wp_create_nonce("oc_pool_admin_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        $list.html(response.data.html);
                        $container.show().css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                    } else {
                        $list.html('<em class="oc-pool-ajax-error">Eroare: ' + response.data + '</em>');
                    }
                }).fail(function() {
                    $list.html('<em class="oc-pool-ajax-error">Eroare la încărcarea variațiilor.</em>');
                });
            });
            
                         // Încarcă variațiile la load dacă există POOL selectat
             if ($('#_oc_pool_pool_id').val()) {
                 $('#_oc_pool_pool_id').trigger('change');
             }
             
             // Force trigger pentru single mode dacă este pre-populat
             setTimeout(function() {
                 if ($('#_oc_pool_pool_id').val() && $('#oc-pool-single-mode').is(':visible')) {
                     $('#_oc_pool_pool_id').trigger('change');
                 }
             }, 200);
            
            // Preview rapid pentru selectorul POOL
            $('#_oc_pool_pool_id').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var poolId = $(this).val();
                
                if (poolId) {
                    var variationsText = selectedOption.text().match(/(\d+) variații/);
                    var count = variationsText ? variationsText[1] : '0';
                    
                    $(this).next('.description').html(
                        'Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)<br>' +
                        '<small class="oc-pool-variations-count"><strong>' + count + ' variații</strong> disponibile în acest POOL</small>'
                    );
                } else {
                    $(this).next('.description').text('Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)');
                }
            });
            
            // Trigger inițial pentru preview
            $('#_oc_pool_pool_id').trigger('change');
            
            // DUAL MODE - Încărcare dinamică variații pentru Pool 1
            $('#_oc_pool_pool1_id').on('change', function() {
                var poolId = $(this).val();
                var $container = $('#oc-pool1-variations-selector');
                var $list = $('#oc-pool1-variations-list');
                
                if (!poolId) {
                    $container.hide();
                    $list.html('<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>');
                    return;
                }
                
                $list.html('<em>Se încarcă variațiile...</em>');
                $container.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oc_pool_get_pool_variations',
                        pool_id: poolId,
                        package_id: <?php echo intval( $post_id ); ?>,
                        field_name: '_oc_pool_pool1_variations',
                        security: '<?php echo wp_create_nonce( "oc_pool_admin_nonce" ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $list.html(response.data.html);
                            // Compat fallback: normalizează name-ul checkbox-urilor pentru Pool 1,
                            // chiar dacă un handler vechi întoarce _oc_pool_selected_variations[].
                            $list.find('input[type="checkbox"][name="_oc_pool_selected_variations[]"]').attr('name', '_oc_pool_pool1_variations[]');
                        } else {
                            $list.html('<em class="oc-pool-ajax-error">Eroare: ' + response.data + '</em>');
                        }
                    },
                    error: function() {
                        $list.html('<em class="oc-pool-ajax-error">Eroare la încărcarea variațiilor.</em>');
                    }
                });
            });
            
            // DUAL MODE - Încărcare dinamică variații pentru Pool 2
            $('#_oc_pool_pool2_id').on('change', function() {
                var poolId = $(this).val();
                var $container = $('#oc-pool2-variations-selector');
                var $list = $('#oc-pool2-variations-list');
                
                if (!poolId) {
                    $container.hide();
                    $list.html('<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>');
                    return;
                }
                
                $list.html('<em>Se încarcă variațiile...</em>');
                $container.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oc_pool_get_pool_variations',
                        pool_id: poolId,
                        package_id: <?php echo intval( $post_id ); ?>,
                        field_name: '_oc_pool_pool2_variations',
                        security: '<?php echo wp_create_nonce( "oc_pool_admin_nonce" ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $list.html(response.data.html);
                            // Compat fallback: normalizează name-ul checkbox-urilor pentru Pool 2,
                            // chiar dacă un handler vechi întoarce _oc_pool_selected_variations[].
                            $list.find('input[type="checkbox"][name="_oc_pool_selected_variations[]"]').attr('name', '_oc_pool_pool2_variations[]');
                        } else {
                            $list.html('<em class="oc-pool-ajax-error">Eroare: ' + response.data + '</em>');
                        }
                    },
                    error: function() {
                        $list.html('<em class="oc-pool-ajax-error">Eroare la încărcarea variațiilor.</em>');
                    }
                });
            });
            
            // Încarcă variațiile la load pentru Pool 1 și Pool 2 dacă există
            if ($('#_oc_pool_pool1_id').val()) {
                $('#_oc_pool_pool1_id').trigger('change');
            }
            if ($('#_oc_pool_pool2_id').val()) {
                $('#_oc_pool_pool2_id').trigger('change');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Renderează stilurile admin
     */
    private function render_admin_styles() {
        ?>
        <style type="text/css">
        :root {
            --oc-pool-surface: #ffffff;
            --oc-pool-surface-muted: #f6f8fb;
            --oc-pool-border: #d6dbe1;
            --oc-pool-border-strong: #c3cad4;
            --oc-pool-text: #1f2933;
            --oc-pool-text-soft: #5f6b7a;
            --oc-pool-accent: #0a6aa1;
            --oc-pool-accent-soft: #eaf4fb;
            --oc-pool-danger: #c2352a;
            --oc-pool-shadow: 0 1px 2px rgba(16, 24, 40, 0.06), 0 6px 16px rgba(16, 24, 40, 0.05);
            --oc-pool-radius: 8px;
        }

        .oc-pool-mode-toggle,
        #oc-pool-single-mode,
        #oc-pool-dual-mode {
            margin-top: 20px !important;
            padding: 18px !important;
            background: var(--oc-pool-surface) !important;
            border: 1px solid var(--oc-pool-border) !important;
            border-radius: var(--oc-pool-radius) !important;
            box-shadow: var(--oc-pool-shadow) !important;
        }

        .oc-pool-mode-title,
        #oc-pool-single-mode > h4,
        #oc-pool-dual-mode > h4 {
            margin: 0 0 8px 0 !important;
            color: var(--oc-pool-text) !important;
            font-size: 15px !important;
            line-height: 1.35 !important;
            font-weight: 700 !important;
            letter-spacing: 0.01em !important;
        }

        .oc-pool-mode-description,
        #oc-pool-dual-mode > .description {
            margin: 0 0 14px 0 !important;
            color: var(--oc-pool-text-soft) !important;
            font-size: 13px !important;
            line-height: 1.5 !important;
            font-style: normal !important;
        }

        .oc-pool-mode-radio-container {
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
            width: 100% !important;
        }

        .oc-pool-mode-option {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin: 0 !important;
            padding: 12px 14px !important;
            border: 1px solid var(--oc-pool-border) !important;
            border-radius: 7px !important;
            cursor: pointer !important;
            background: var(--oc-pool-surface-muted) !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
            width: 100% !important;
        }

        @media (max-width: 900px) {
            .oc-pool-mode-radio-container {
                grid-template-columns: 1fr !important;
            }
        }

        .oc-pool-mode-option:hover {
            border-color: var(--oc-pool-border-strong) !important;
            background: #f9fbfd !important;
            box-shadow: 0 2px 10px rgba(16, 24, 40, 0.08) !important;
            transform: none !important;
        }

        .oc-pool-mode-option:has(input:checked) {
            border-color: var(--oc-pool-accent) !important;
            background: var(--oc-pool-accent-soft) !important;
        }

        .oc-pool-mode-option input[type="radio"] {
            width: 16px !important;
            height: 16px !important;
            margin: 0 !important;
            flex-shrink: 0 !important;
        }

        .oc-pool-mode-option span {
            font-size: 13px !important;
            line-height: 1.45 !important;
            color: var(--oc-pool-text) !important;
            font-weight: 500 !important;
        }

        #_oc_pool_pool_id,
        #_oc_pool_pool1_id,
        #_oc_pool_pool2_id {
            width: min(560px, 100%) !important;
        }

        #oc-pool-single-mode .form-field,
        #oc-pool-dual-mode .form-field {
            margin-bottom: 14px !important;
        }

        #oc-pool-single-mode .description,
        #oc-pool-dual-mode .description {
            color: var(--oc-pool-text-soft) !important;
        }

        #oc-pool-dual-mode .oc-dual-pool-section {
            margin: 16px 0 0 0 !important;
            padding: 14px !important;
            border: 1px solid var(--oc-pool-border) !important;
            border-radius: 7px !important;
            background: var(--oc-pool-surface-muted) !important;
        }

        #oc-pool-dual-mode .oc-dual-pool-section > h5 {
            margin: 0 0 12px 0 !important;
            color: var(--oc-pool-text) !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        .oc-pool-debug-box {
            margin-top: 10px !important;
            padding: 10px 12px !important;
            background: #f5f7fa !important;
            border: 1px dashed var(--oc-pool-border-strong) !important;
            border-radius: 6px !important;
        }

        ._oc_pool_allowed_payment_gateways_field .wrap {
            display: inline-grid !important;
            grid-auto-flow: row !important;
            gap: 6px !important;
        }

        ._oc_pool_allowed_payment_gateways_field .wrap label {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 0 !important;
            line-height: 1.4 !important;
        }

        ._oc_pool_allowed_payment_gateways_field .wrap input[type="checkbox"] {
            margin: 0 !important;
        }

        ._oc_pool_is_unlimited_field label[for="_oc_pool_is_unlimited"] {
            font-weight: 700 !important;
            color: var(--oc-pool-text) !important;
        }

        ._oc_pool_is_unlimited_field #_oc_pool_is_unlimited {
            margin-left: 8px !important;
            width: auto !important;
        }

        ._oc_pool_is_unlimited_field .description {
            display: block !important;
            margin-top: 5px !important;
        }

        .oc-pool-ajax-error {
            color: var(--oc-pool-danger) !important;
            font-style: normal !important;
            font-weight: 600 !important;
        }

        .oc-pool-variations-count {
            color: var(--oc-pool-accent) !important;
        }

        #oc-pool-variations-selector,
        #oc-pool1-variations-selector,
        #oc-pool2-variations-selector {
            margin-top: 12px !important;
            clear: both;
        }

        #oc-pool-variations-list,
        #oc-pool1-variations-list,
        #oc-pool2-variations-list {
            max-height: 400px !important;
            overflow-y: auto !important;
            border: 1px solid var(--oc-pool-border) !important;
            padding: 0 !important;
            background: var(--oc-pool-surface-muted) !important;
            border-radius: 7px !important;
            width: 100% !important;
            box-sizing: border-box;
        }

        #oc-pool-variations-list > div,
        #oc-pool1-variations-list > div,
        #oc-pool2-variations-list > div {
            padding: 12px !important;
        }

        #oc-pool-variations-list > div > div,
        #oc-pool1-variations-list > div > div,
        #oc-pool2-variations-list > div > div,
        #oc-pool1-variations-list .oc-variation-item,
        #oc-pool2-variations-list .oc-variation-item {
            margin: 0 0 10px 0 !important;
            padding: 12px !important;
            background: var(--oc-pool-surface) !important;
            border: 1px solid var(--oc-pool-border) !important;
            border-radius: 6px !important;
            min-height: 70px !important;
            box-sizing: border-box !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease !important;
            width: auto !important;
            max-width: none !important;
            flex: none !important;
        }

        #oc-pool-variations-list > div > div:hover,
        #oc-pool1-variations-list > div > div:hover,
        #oc-pool2-variations-list > div > div:hover,
        #oc-pool1-variations-list .oc-variation-item:hover,
        #oc-pool2-variations-list .oc-variation-item:hover {
            border-color: var(--oc-pool-accent) !important;
            background: #fbfdff !important;
            box-shadow: 0 3px 10px rgba(10, 106, 161, 0.12) !important;
        }

        #oc-pool-variations-list label,
        #oc-pool1-variations-list label,
        #oc-pool2-variations-list label {
            display: block !important;
            cursor: pointer !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            width: 100% !important;
            line-height: 1.4 !important;
        }

        #oc-pool-variations-list input[type="checkbox"],
        #oc-pool1-variations-list input[type="checkbox"],
        #oc-pool2-variations-list input[type="checkbox"] {
            width: 18px !important;
            height: 18px !important;
            margin: 0 10px 0 0 !important;
            vertical-align: top !important;
            position: relative !important;
            top: 2px;
        }

        #oc-pool-variations-list strong,
        #oc-pool1-variations-list strong,
        #oc-pool2-variations-list strong {
            font-size: 14px !important;
            line-height: 1.4 !important;
            color: var(--oc-pool-text) !important;
            font-weight: 600 !important;
        }

        #oc-pool-variations-list input[type="checkbox"]:checked + strong,
        #oc-pool1-variations-list input[type="checkbox"]:checked + strong,
        #oc-pool2-variations-list input[type="checkbox"]:checked + strong {
            color: var(--oc-pool-accent) !important;
        }

        #oc-pool-variations-list small,
        #oc-pool1-variations-list small,
        #oc-pool2-variations-list small {
            display: block !important;
            color: var(--oc-pool-text-soft) !important;
            margin: 6px 0 0 28px !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
            padding: 0 !important;
        }

        #oc-pool-variations-list > p,
        #oc-pool1-variations-list > p,
        #oc-pool2-variations-list > p {
            margin: 10px 12px !important;
            padding: 10px 12px !important;
            background: var(--oc-pool-surface) !important;
            border: 1px solid var(--oc-pool-border) !important;
            border-radius: 6px !important;
            font-size: 12px !important;
        }

        #oc-pool-variations-selector[style*="display: none"],
        #oc-pool1-variations-selector[style*="display: none"],
        #oc-pool2-variations-selector[style*="display: none"] {
            display: block !important;
        }

        .oc-pool-settings #oc-pool-variations-selector,
        .oc-pool-settings #oc-pool1-variations-selector,
        .oc-pool-settings #oc-pool2-variations-selector {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Salvează câmpurile admin
     *
     * @param int $post_id
     */
    public function save_admin_fields( $post_id ) {
        $mode_selection_posted = sanitize_text_field( $_POST['_oc_pool_mode_selection'] ?? '' );
        $is_dual_mode_posted = ($mode_selection_posted === 'dual') || (sanitize_text_field( $_POST['_oc_pool_dual_mode'] ?? '' ) === 'yes');
        $legacy_selected_variations = [];
        if ( isset( $_POST['_oc_pool_selected_variations'] ) && is_array( $_POST['_oc_pool_selected_variations'] ) ) {
            $legacy_selected_variations = array_map( 'intval', $_POST['_oc_pool_selected_variations'] );
        }
        
        $fields = [
            '_oc_pool_enabled',
            '_oc_pool_price',
            '_oc_pool_pool_id',
            '_oc_pool_min_selections',
            '_oc_pool_max_selections',
            '_oc_pool_ui_style',
            '_oc_pool_allow_duplicates',
            '_oc_pool_helper_text',
            '_oc_pool_selected_variations',
            '_oc_pool_allowed_payment_gateways',
            '_oc_pool_is_unlimited',

            // Dual mode fields
            '_oc_pool_dual_mode',
            '_oc_pool_pool1_id',
            '_oc_pool_pool2_id',
            '_oc_pool_pool1_label',
            '_oc_pool_pool2_label',
                         '_oc_pool_pool1_variations',
             '_oc_pool_pool2_variations',
             '_oc_pool_pool1_min',
             '_oc_pool_pool2_min',
             '_oc_pool_pool1_ui_style',
             '_oc_pool_pool2_ui_style',
             '_oc_pool_allow_same_variation'
        ];
        
        foreach ( $fields as $field ) {
            if ( in_array( $field, ['_oc_pool_selected_variations', '_oc_pool_pool1_variations', '_oc_pool_pool2_variations', '_oc_pool_allowed_payment_gateways'] ) ) {
                // Tratează array-urile de variații selectate separat
                if ( isset( $_POST[$field] ) && is_array( $_POST[$field] ) ) {
                    if ( $field === '_oc_pool_allowed_payment_gateways' ) {
                        $allowed_gateways = array_values( array_intersect(
                            array_map( 'sanitize_text_field', $_POST[$field] ),
                            [ 'oc_7card', 'oc_esx' ]
                        ) );
                        update_post_meta( $post_id, $field, $allowed_gateways );
                    } else {
                        $selected_variations = array_map( 'intval', $_POST[$field] );
                        update_post_meta( $post_id, $field, $selected_variations );
                    }
                    
                } else {
                    // Compat fallback: pentru dual mode, dacă request-ul vine cu câmpul legacy
                    // (_oc_pool_selected_variations[]), populăm pool1/pool2 din acesta
                    // pentru a evita pierderea selecțiilor la salvare.
                    if (
                        $is_dual_mode_posted
                        && in_array( $field, ['_oc_pool_pool1_variations', '_oc_pool_pool2_variations'], true )
                        && ! empty( $legacy_selected_variations )
                    ) {
                        update_post_meta( $post_id, $field, $legacy_selected_variations );
                    } else {
                        delete_post_meta( $post_id, $field );
                    }
                    
                }
            } elseif ( $field === '_oc_pool_is_unlimited' ) {
                // Checkbox: 'yes' dacă bifat, ştergem meta dacă nebifat
                if ( isset( $_POST[$field] ) && $_POST[$field] === 'yes' ) {
                    update_post_meta( $post_id, $field, 'yes' );
                } else {
                    update_post_meta( $post_id, $field, 'no' );
                }
            } else {
                // Tratează câmpurile normale
                if ( isset( $_POST[$field] ) ) {
                    update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
                } else {
                    delete_post_meta( $post_id, $field );
                }
            }
        }

        $this->sync_legacy_meta( $post_id );
        
        // Procesează separat mode selection radio button
        if ( isset( $_POST['_oc_pool_mode_selection'] ) ) {
            $mode_selection = sanitize_text_field( $_POST['_oc_pool_mode_selection'] );
            $dual_mode_value = ( $mode_selection === 'dual' ) ? 'yes' : 'no';
            update_post_meta( $post_id, '_oc_pool_dual_mode', $dual_mode_value );
            
            // Dacă se comută la single mode, șterge câmpurile dual
            if ( $mode_selection === 'single' ) {
                                 $dual_fields = [
                     '_oc_pool_pool1_id', '_oc_pool_pool2_id',
                     '_oc_pool_pool1_label', '_oc_pool_pool2_label',
                     '_oc_pool_pool1_variations', '_oc_pool_pool2_variations',
                     '_oc_pool_pool1_min', '_oc_pool_pool2_min',
                     '_oc_pool_pool1_ui_style', '_oc_pool_pool2_ui_style',
                     '_oc_pool_allow_same_variation'
                 ];
                
                foreach ( $dual_fields as $dual_field ) {
                    delete_post_meta( $post_id, $dual_field );
                }
            }
        }
        
                 // Validări la salvare
         if ( isset( $_POST['_oc_pool_enabled'] ) && $_POST['_oc_pool_enabled'] ) {
             $mode_selection = sanitize_text_field( $_POST['_oc_pool_mode_selection'] ?? 'single' );
             $errors = [];
             
             if ( $mode_selection === 'single' ) {
                 // Validare pentru Single Mode
                 $pool_id = intval( $_POST['_oc_pool_pool_id'] ?? 0 );
                 $min = intval( $_POST['_oc_pool_min_selections'] ?? 0 );
                 $max = intval( $_POST['_oc_pool_max_selections'] ?? 0 );
                 
                 if ( ! $pool_id ) {
                     $errors[] = 'Trebuie să selectezi un produs POOL pentru Single Mode.';
                 }
             } else {
                 // Validare pentru Dual Mode
                 $pool1_id = intval( $_POST['_oc_pool_pool1_id'] ?? 0 );
                 $pool2_id = intval( $_POST['_oc_pool_pool2_id'] ?? 0 );
                 $min = intval( $_POST['_oc_pool_pool1_min'] ?? 0 );
                 $max = 0; // Dual mode nu folosește max pentru validarea generală
                 
                 if ( ! $pool1_id ) {
                     $errors[] = 'Trebuie să selectezi produsul POOL 1 pentru Dual Mode.';
                 }
                 if ( ! $pool2_id ) {
                     $errors[] = 'Trebuie să selectezi produsul POOL 2 pentru Dual Mode.';
                 }
                 
                 $pool_id = $pool1_id; // Pentru validarea de mai jos
             }
             
             // Validare comună pentru POOL-ul principal
             if ( $pool_id ) {
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
                    }
                }
            }
            
            // Validare Min/Max
            if ( $min < 1 ) {
                $errors[] = 'Selecțiile minime trebuie să fie cel puțin 1.';
            }
            
            if ( $max > 0 && $max < $min ) {
                $errors[] = 'Selecțiile maxime nu pot fi mai puține decât minimele.';
            }
            
            // Afișare erori
            if ( ! empty( $errors ) ) {
                set_transient( 'oc_pool_admin_errors_' . $post_id, $errors, 60 );
            }
            
            // Trigger action pentru salvare
            do_action( 'oc_pool_package_updated', $post_id, [
                'pool_id' => $pool_id,
                'min_selections' => $min,
                'max_selections' => $max
            ]);
        }
    }

    /**
     * Menține meta-urile legacy sincronizate pentru compatibilitate cu path-urile vechi.
     *
     * @param int $post_id
     */
    private function sync_legacy_meta( $post_id ) {
        $meta_map = [
            '_oc_pool_enabled' => '_mv_pack_enabled',
            '_oc_pool_price' => '_mv_pack_price',
            '_oc_pool_pool_id' => '_mv_pack_pool_id',
            '_oc_pool_min_selections' => '_mv_pack_min_selections',
            '_oc_pool_max_selections' => '_mv_pack_max_selections',
            '_oc_pool_ui_style' => '_mv_pack_ui_style',
            '_oc_pool_allow_duplicates' => '_mv_pack_allow_duplicates',
            '_oc_pool_helper_text' => '_mv_pack_helper_text',
            '_oc_pool_selected_variations' => '_mv_pack_selected_variations'
        ];

        foreach ( $meta_map as $new_key => $legacy_key ) {
            $value = get_post_meta( $post_id, $new_key, true );

            if ( $value === '' || $value === [] ) {
                delete_post_meta( $post_id, $legacy_key );
                continue;
            }

            update_post_meta( $post_id, $legacy_key, $value );
        }
    }
    
    /**
     * Afișează notificările admin
     */
    public function show_admin_notices() {
        global $post;
        if ( ! $post || get_post_type( $post ) !== 'product' ) return;
        
        $errors = get_transient( 'oc_pool_admin_errors_' . $post->ID );
        if ( $errors ) {
            echo '<div class="notice notice-error"><p><strong>Erori Pool Product Manager:</strong></p><ul>';
            foreach ( $errors as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul></div>';
            delete_transient( 'oc_pool_admin_errors_' . $post->ID );
        }
        
        // Verifică dacă POOL-ul unui pachet este încă valid
        if ( get_post_meta( $post->ID, '_oc_pool_enabled', true ) ) {
            $this->check_package_validity( $post->ID );
        }
    }
    
    /**
     * Verifică validitatea unui pachet
     *
     * @param int $package_id
     */
    private function check_package_validity( $package_id ) {
        $is_dual_mode = get_post_meta( $package_id, '_oc_pool_dual_mode', true ) === 'yes';
        $errors = [];
        
        if ( $is_dual_mode ) {
            // Verificare pentru Dual Mode
            $pool1_id = get_post_meta( $package_id, '_oc_pool_pool1_id', true );
            $pool2_id = get_post_meta( $package_id, '_oc_pool_pool2_id', true );
            
            // Verifică Pool 1
            if ( $pool1_id ) {
                $pool1_product = wc_get_product( $pool1_id );
                if ( ! $pool1_product ) {
                    $errors[] = 'Produsul POOL 1 nu mai există.';
                } elseif ( $pool1_product->get_type() !== 'variable' ) {
                    $errors[] = 'Produsul POOL 1 nu mai este de tip variabil.';
                } else {
                    $variations1 = $pool1_product->get_available_variations();
                    $purchasable_count1 = count( array_filter( $variations1, function($v) {
                        return $v['is_purchasable'] && $v['variation_is_active'];
                    }));
                    
                    if ( $purchasable_count1 < 1 ) {
                        $errors[] = 'Produsul POOL 1 nu mai are variații cumpărabile.';
                    }
                }
            }
            
            // Verifică Pool 2
            if ( $pool2_id ) {
                $pool2_product = wc_get_product( $pool2_id );
                if ( ! $pool2_product ) {
                    $errors[] = 'Produsul POOL 2 nu mai există.';
                } elseif ( $pool2_product->get_type() !== 'variable' ) {
                    $errors[] = 'Produsul POOL 2 nu mai este de tip variabil.';
                } else {
                    $variations2 = $pool2_product->get_available_variations();
                    $purchasable_count2 = count( array_filter( $variations2, function($v) {
                        return $v['is_purchasable'] && $v['variation_is_active'];
                    }));
                    
                    if ( $purchasable_count2 < 1 ) {
                        $errors[] = 'Produsul POOL 2 nu mai are variații cumpărabile.';
                    }
                }
            }
        } else {
            // Verificare pentru Single Mode
            $pool_id = get_post_meta( $package_id, '_oc_pool_pool_id', true );
            $pool_product = $pool_id ? wc_get_product( $pool_id ) : null;
            
            if ( ! $pool_product ) {
                $errors[] = 'Produsul POOL nu mai există.';
            } elseif ( $pool_product->get_type() !== 'variable' ) {
                $errors[] = 'Produsul POOL nu mai este de tip variabil.';
            } else {
                $variations = $pool_product->get_available_variations();
                $purchasable_count = count( array_filter( $variations, function($v) {
                    return $v['is_purchasable'] && $v['variation_is_active'];
                }));
                
                if ( $purchasable_count < 1 ) {
                    $errors[] = 'Produsul POOL nu mai are variații cumpărabile.';
                }
            }
        }
        
        if ( ! empty( $errors ) ) {
            echo '<div class="notice notice-error"><p><strong>Problemă cu pachetul:</strong></p><ul>';
            foreach ( $errors as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul><p>Te rog actualizează configurația pachetului.</p></div>';
        }
    }
    
    /**
     * AJAX handler pentru încărcarea variațiilor din POOL
     */
    public function ajax_get_pool_variations() {
        if ( ! wp_verify_nonce( $_POST['security'], 'oc_pool_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $pool_id = intval( $_POST['pool_id'] );
        $package_id = intval( $_POST['package_id'] );
        $field_name = sanitize_text_field( $_POST['field_name'] ?? '_oc_pool_selected_variations' );
        
        if ( ! $pool_id ) {
            wp_send_json_error( 'Invalid pool ID' );
        }
        
        $pool_product = wc_get_product( $pool_id );
        if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
            wp_send_json_error( 'Produsul POOL nu este valid sau nu este variabil' );
        }
        
        // Obține variațiile existente selectate pentru acest pachet (cu fallback pentru dual mode)
        $package_config = oc_pool_get_package_config( $package_id );
        
        if ( $field_name === '_oc_pool_pool1_variations' ) {
            $selected_variations = $package_config['pool1_variations'] ?? [];
        } elseif ( $field_name === '_oc_pool_pool2_variations' ) {
            $selected_variations = $package_config['pool2_variations'] ?? [];
        } else {
            $selected_variations = $package_config['selected_variations'] ?? [];
        }
        
        if ( ! is_array( $selected_variations ) ) {
            $selected_variations = [];
        }
        
        // Generează HTML-ul cu checkboxurile
        $html = '';
        $variations = $pool_product->get_available_variations();
        
        if ( empty( $variations ) ) {
            $html = '<em>Acest produs variabil nu are variații publicate.</em>';
        } else {
            $html .= '<div>';
            
            foreach ( $variations as $variation ) {
                $variation_obj = wc_get_product( $variation['variation_id'] );
                if ( ! $variation_obj ) continue;
                
                $variation_name = wc_get_formatted_variation( $variation_obj, true, false );
                $is_checked = in_array( $variation['variation_id'], $selected_variations );
                $stock_status = $variation_obj->is_in_stock() ? 'În stoc' : 'Stoc epuizat';
                
                $html .= '<div class="oc-variation-item">';
                $html .= '<label>';
                $html .= '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $variation['variation_id'] ) . '"';
                $html .= $is_checked ? ' checked' : '';
                $html .= '>';
                $html .= '<strong>' . esc_html( $variation_name ) . '</strong>';
                $html .= '<br><small>' . $stock_status . ' | ID: ' . $variation['variation_id'] . '</small>';
                $html .= '</label>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '<p><small><strong>Total:</strong> ' . count( $variations ) . ' variații | <strong>Selectate:</strong> ' . count( $selected_variations ) . '</small></p>';
        }
        
        wp_send_json_success( ['html' => $html] );
    }
    
    /**
     * Randează HTML-ul pentru variațiile unui pool
     *
     * @param int $pool_id
     * @param array $selected_variations
     * @param string $field_name
     */
    private function render_pool_variations_html( int $pool_id, array $selected_variations, string $field_name ): void {
        if ( ! $pool_id ) {
            echo '<em>Selectează un POOL mai sus pentru a vedea variațiile...</em>';
            return;
        }
        
        $pool_product = wc_get_product( $pool_id );
        if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
            echo '<em>Produsul POOL nu este valid sau nu este variabil.</em>';
            return;
        }
        
        $variations = $pool_product->get_available_variations();
        
        if ( empty( $variations ) ) {
            echo '<em>Acest produs variabil nu are variații publicate.</em>';
            return;
        }
        
        echo '<div>';
        
        foreach ( $variations as $variation ) {
            $variation_obj = wc_get_product( $variation['variation_id'] );
            if ( ! $variation_obj ) continue;
            
            $variation_name = wc_get_formatted_variation( $variation_obj, true, false );
            $is_checked = in_array( $variation['variation_id'], $selected_variations );
            $stock_status = $variation_obj->is_in_stock() ? 'În stoc' : 'Stoc epuizat';
            
            echo '<div class="oc-variation-item">';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $variation['variation_id'] ) . '"';
            echo $is_checked ? ' checked' : '';
            echo '>';
            echo '<strong>' . esc_html( $variation_name ) . '</strong>';
            echo '<br><small>' . $stock_status . ' | ID: ' . $variation['variation_id'] . '</small>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<p><small><strong>Total:</strong> ' . count( $variations ) . ' variații | <strong>Selectate:</strong> ' . count( $selected_variations ) . '</small></p>';
    }
    
    /**
     * Migrează meta-urile vechi la noul format
     */
    public function maybe_migrate_old_meta() {
        // Verifică dacă avem meta-uri vechi de migrat
        if ( get_option( 'oc_pool_migration_done' ) ) {
            return;
        }
        
        global $wpdb;
        
        // Găsește produsele cu meta-uri vechi
        $old_packages = $wpdb->get_col( $wpdb->prepare( "
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value = '1'
        ", '_mv_pack_enabled' ) );
        
        if ( empty( $old_packages ) ) {
            update_option( 'oc_pool_migration_done', true );
            return;
        }
        
        // Maparea meta-urilor
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
        
        $migrated_count = 0;
        
        foreach ( $old_packages as $package_id ) {
            foreach ( $meta_map as $old_key => $new_key ) {
                $value = get_post_meta( $package_id, $old_key, true );
                if ( $value !== '' ) {
                    update_post_meta( $package_id, $new_key, $value );
                }
            }
            $migrated_count++;
        }
        
        // Marchează migrarea ca finalizată
        update_option( 'oc_pool_migration_done', true );
        
        if ( $migrated_count > 0 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // Migration completed successfully
            }
        }
    }
}
