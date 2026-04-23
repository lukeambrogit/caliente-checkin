<?php
/**
 * Pool Product Manager - Direct Copy din originalul MV Pack System
 * EXACT ca originalul dar cu prefixul schimbat de la mv_ la oc_
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// HPOS compatibility is declared in the main plugin file (orar-cursuri.php).

// =============================================================================
// OC-01 • Setări Admin (Produs PACHET)
// =============================================================================

// Adaugă panoul "Mod Pachet (preț fix)" DOAR pe produse simple - COMENTAT pentru a evita dublarea cu OC_Pool_Admin
// add_action( 'woocommerce_product_options_general_product_data', 'oc_pool_admin_fields' );
function oc_pool_admin_fields() {
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
	
	echo '<div class="options_group oc-pool-settings">';
	echo '<h3>Pool Product Manager - Mod Pachet</h3>';
	
	// Activare Mod Pachet
	woocommerce_wp_checkbox([
		'id' => '_oc_pool_enabled',
		'label' => 'Activează Mod Pachet',
		'description' => 'Permite vânzarea ca pachet cu selecție din produs POOL'
	]);
	
	// Preț pachet
	woocommerce_wp_text_input([
		'id' => '_oc_pool_price',
		'label' => 'Preț pachet (' . get_woocommerce_currency_symbol() . ')',
		'type' => 'number',
		'custom_attributes' => ['step' => '0.01', 'min' => '0'],
		'description' => 'Lasă gol pentru a folosi Regular Price'
	]);
	
	// Selector POOL - dropdown cu toate produsele variabile
	$pool_id = get_post_meta( $post->ID, '_oc_pool_pool_id', true );
	$variable_products = oc_pool_get_all_variable_products();
	
	echo '<p class="form-field _oc_pool_pool_id_field">';
	echo '<label for="_oc_pool_pool_id">Produs POOL (variabil)</label>';
	echo '<select id="_oc_pool_pool_id" name="_oc_pool_pool_id" style="width: 50%;">';
	echo '<option value="">' . esc_html__( '-- Selectează produs variabil --', OC_TEXT_DOMAIN ) . '</option>';
	
	// Debug info
	if ( empty( $variable_products ) ) {
		echo '<option value="" disabled>' . esc_html__( 'Nu s-au găsit produse variabile', OC_TEXT_DOMAIN ) . '</option>';
	} else {
		echo '<option value="" disabled>' . sprintf( esc_html__( 'Găsite %d produse variabile', OC_TEXT_DOMAIN ), count( $variable_products ) ) . '</option>';
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
		echo ' - ' . absint( $variations_count ) . ' ' . esc_html__( 'variații', OC_TEXT_DOMAIN ) . wp_kses_post( $status_badge );
		echo '</option>';
	}
	
	echo '</select>';
	echo '<span class="description">' . esc_html__( 'Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)', OC_TEXT_DOMAIN ) . '</span>';
	
	// Debug temporar - afișează toate produsele găsite
	if ( current_user_can( 'manage_options' ) && isset( $_GET['oc_debug'] ) ) {
		echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
		echo '<strong>' . esc_html__( 'Debug - Toate produsele din site:', OC_TEXT_DOMAIN ) . '</strong><br>';
		
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
		'description' => 'Numărul minim de variații ce trebuie selectate'
	]);
	
	// Max selecții
	woocommerce_wp_text_input([
		'id' => '_oc_pool_max_selections',
		'label' => 'Selecții maxime (opțional)',
		'type' => 'number',
		'custom_attributes' => ['min' => '1'],
		'description' => 'Numărul maxim de variații ce pot fi selectate (opțional)'
	]);
	
	// Stil UI
	woocommerce_wp_select([
		'id' => '_oc_pool_ui_style',
		'label' => 'Stil UI',
		'options' => [
			'slots' => 'Radio pe sloturi (Slot 1, Slot 2...)',
			'checkboxes' => 'Checkbox-uri (listă unică)'
		],
		'description' => 'Modul de afișare a selecțiilor în front-end'
	]);
	
	// Politică duplicate
	woocommerce_wp_checkbox([
		'id' => '_oc_pool_allow_duplicates',
		'label' => 'Permite duplicate prin cantitate',
		'description' => 'Implicit: duplicate interzise în același pachet, permise doar când qty > 1'
	]);
	
	// Mesaj ajutător
	woocommerce_wp_textarea_input([
		'id' => '_oc_pool_helper_text',
		'label' => 'Mesaj ajutător',
		'description' => 'Text afișat deasupra selecțiilor (opțional)'
	]);
	
	// Selecția variațiilor din POOL
	echo '<div id="oc-pool-variations-selector" style="display: none;">';
	echo '<p class="form-field">';
	echo '<label><strong>' . esc_html__( 'Selectează variațiile disponibile în acest pachet:', OC_TEXT_DOMAIN ) . '</strong></label>';
	echo '<div id="oc-pool-variations-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
	echo '<em>' . esc_html__( 'Selectează un POOL mai sus pentru a vedea variațiile...', OC_TEXT_DOMAIN ) . '</em>';
	echo '</div>';
	echo '<span class="description">' . esc_html__( 'Bifează doar variațiile pe care vrei să le incluzi în acest pachet.', OC_TEXT_DOMAIN ) . '</span>';
	echo '</p>';
	echo '</div>';
	
	echo '</div>';
	
	// JavaScript pentru îmbunătățirea selectorului
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
				package_id: <?php echo $post->ID; ?>,
				security: '<?php echo wp_create_nonce("oc_pool_admin_nonce"); ?>'
			}, function(response) {
				if (response.success) {
					$list.html(response.data.html);
					// Forțează afișarea containerului și vizibilitatea
					$container.show().css({
						'display': 'block',
						'visibility': 'visible',
						'opacity': '1'
					});
				} else {
					$list.html('<em style="color: red;">Eroare: ' + response.data + '</em>');
				}
			}).fail(function() {
				$list.html('<em style="color: red;">Eroare la încărcarea variațiilor.</em>');
			});
		});
		
		// Încarcă variațiile la load dacă există POOL selectat
		if ($('#_oc_pool_pool_id').val()) {
			$('#_oc_pool_pool_id').trigger('change');
		}
		
		// Debug: Forțează afișarea containerului la load
		setTimeout(function() {
			var $container = $('#oc-pool-variations-selector');
			if ($container.length) {
				$container.show().css({
					'display': 'block !important',
					'visibility': 'visible !important'
				});
			}
		}, 1000);
		
		// Preview rapid pentru selectorul POOL
		$('#_oc_pool_pool_id').on('change', function() {
			var selectedOption = $(this).find('option:selected');
			var poolId = $(this).val();
			
			if (poolId) {
				var variationsText = selectedOption.text().match(/(\d+) variații/);
				var count = variationsText ? variationsText[1] : '0';
				
				$(this).next('.description').html(
					'Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)<br>' +
					'<small style="color: #0073aa;"><strong>' + count + ' variații</strong> disponibile în acest POOL</small>'
				);
			} else {
				$(this).next('.description').text('Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)');
			}
		});
		
		// Trigger inițial pentru preview
		$('#_oc_pool_pool_id').trigger('change');
	});
	</script>
	
	<style type="text/css">
	/* CSS pentru admin WooCommerce - selector variații */
	#oc-pool-variations-selector {
		margin-top: 15px;
		clear: both;
	}
	
	#oc-pool-variations-list {
		max-height: 400px !important;
		overflow-y: auto !important;
		border: 1px solid #ddd !important;
		padding: 0 !important;
		background: #f9f9f9 !important;
		border-radius: 4px;
		width: 100% !important;
		box-sizing: border-box;
	}
	
	/* Container intern pentru scroll */
	#oc-pool-variations-list > div {
		max-height: none !important;
		overflow: visible !important;
		padding: 15px !important;
	}
	
	/* Fiecare item de variație */
	#oc-pool-variations-list > div > div {
		margin-bottom: 10px !important;
		padding: 12px !important;
		background: #fff !important;
		border: 1px solid #e1e1e1 !important;
		border-radius: 4px !important;
		border-bottom: 1px solid #e1e1e1 !important;
		height: auto !important;
		min-height: 70px !important;
		box-sizing: border-box !important;
		transition: all 0.2s ease;
	}
	
	#oc-pool-variations-list > div > div:hover {
		background: #f0f8ff !important;
		border-color: #0073aa !important;
		box-shadow: 0 2px 4px rgba(0,115,170,0.1);
	}
	
	#oc-pool-variations-list label {
		display: block !important;
		cursor: pointer !important;
		margin: 0 !important;
		padding: 0 !important;
		background: transparent !important;
		border: none !important;
		border-radius: 0 !important;
		width: 100% !important;
		height: auto !important;
		line-height: 1.4;
	}
	
	#oc-pool-variations-list input[type="checkbox"] {
		display: inline-block !important;
		width: 18px !important;
		height: 18px !important;
		margin: 0 10px 0 0 !important;
		vertical-align: top !important;
		position: relative !important;
		top: 2px;
		float: none !important;
	}
	
	#oc-pool-variations-list strong {
		display: inline !important;
		font-weight: 600 !important;
		color: #333 !important;
		font-size: 14px !important;
		line-height: 1.4;
	}
	
	#oc-pool-variations-list input[type="checkbox"]:checked + strong {
		color: #0073aa !important;
		font-weight: bold !important;
	}
	
	#oc-pool-variations-list small {
		display: block !important;
		color: #666 !important;
		margin: 5px 0 0 28px !important;
		font-size: 12px !important;
		line-height: 1.3;
		padding: 0 !important;
	}
	
	#oc-pool-variations-list br {
		display: block !important;
		content: "" !important;
		margin: 4px 0 !important;
	}
	
	/* Summary info */
	#oc-pool-variations-list > p {
		margin: 10px 15px !important;
		padding: 10px !important;
		background: #fff !important;
		border: 1px solid #ccd0d4 !important;
		border-radius: 3px !important;
		font-size: 12px !important;
	}
	
	/* Asigură vizibilitatea containerului */
	#oc-pool-variations-selector[style*="display: none"] {
		display: block !important;
	}
	
	/* Pentru cazurile când se afișează */
	.oc-pool-settings #oc-pool-variations-selector {
		display: block !important;
		visibility: visible !important;
		opacity: 1 !important;
	}
	</style>
	
	<?php
}

// Salvează meta-urile la produs - COMENTAT pentru a evita dublarea cu OC_Pool_Admin
// add_action( 'woocommerce_process_product_meta_simple', 'oc_pool_save_admin_fields' );
function oc_pool_save_admin_fields( $post_id ) {
	$fields = [
		'_oc_pool_enabled',
		'_oc_pool_price',
		'_oc_pool_pool_id',
		'_oc_pool_min_selections',
		'_oc_pool_max_selections',
		'_oc_pool_ui_style',
		'_oc_pool_allow_duplicates',
		'_oc_pool_helper_text',
		'_oc_pool_selected_variations'
	];
	
	foreach ( $fields as $field ) {
		if ( $field === '_oc_pool_selected_variations' ) {
			// Tratează array-ul de variații selectate separat
			if ( isset( $_POST[$field] ) && is_array( $_POST[$field] ) ) {
				$selected_variations = array_map( 'intval', $_POST[$field] );
				update_post_meta( $post_id, $field, $selected_variations );
			} else {
				delete_post_meta( $post_id, $field );
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
	
	// Validări la salvare
	if ( isset( $_POST['_oc_pool_enabled'] ) && $_POST['_oc_pool_enabled'] ) {
		$pool_id = (int) ( $_POST['_oc_pool_pool_id'] ?? 0 );
		$min = (int) ( $_POST['_oc_pool_min_selections'] ?? 0 );
		$max = (int) ( $_POST['_oc_pool_max_selections'] ?? 0 );
		
		$errors = [];
		
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
	}
}

// Afișează erorile de validare
// add_action( 'admin_notices', 'oc_pool_admin_notices' ); // COMENTAT - gestionat de OC_Pool_Admin
function oc_pool_admin_notices() {
	global $post;
	if ( ! $post || get_post_type( $post ) !== 'product' ) return;
	
	$errors = get_transient( 'oc_pool_admin_errors_' . $post->ID );
	if ( $errors ) {
		echo '<div class="notice notice-error"><p><strong>Erori Mod Pachet:</strong></p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
		delete_transient( 'oc_pool_admin_errors_' . $post->ID );
	}
}

// =============================================================================
// OC-03 • UI Front-end (Pagina PACHET) + Compatibilitate Elementor - EXACT ca originalul
// =============================================================================

// Verifică dacă produsul este pachet și înlocuiește UI-ul
add_action( 'woocommerce_before_add_to_cart_form', 'oc_pool_replace_frontend_ui' );
add_action( 'woocommerce_single_product_summary', 'oc_pool_replace_frontend_ui', 31 );

function oc_pool_frontend_ui_already_rendered( $product_id ) {
	if ( ! isset( $GLOBALS['oc_pool_rendered_frontend_ui'] ) || ! is_array( $GLOBALS['oc_pool_rendered_frontend_ui'] ) ) {
		$GLOBALS['oc_pool_rendered_frontend_ui'] = [];
	}

	return ! empty( $GLOBALS['oc_pool_rendered_frontend_ui'][ (int) $product_id ] );
}

function oc_pool_mark_frontend_ui_rendered( $product_id ) {
	if ( ! isset( $GLOBALS['oc_pool_rendered_frontend_ui'] ) || ! is_array( $GLOBALS['oc_pool_rendered_frontend_ui'] ) ) {
		$GLOBALS['oc_pool_rendered_frontend_ui'] = [];
	}

	$GLOBALS['oc_pool_rendered_frontend_ui'][ (int) $product_id ] = true;
}

function oc_pool_replace_frontend_ui() {
	global $product;
	
	if ( ! is_product() || ! $product || $product->get_type() !== 'simple' ) return;
	// Verifică atât formatul nou cât și cel vechi (backwards compatibility)
	$is_package = get_post_meta( $product->get_id(), '_oc_pool_enabled', true ) || 
	              get_post_meta( $product->get_id(), '_mv_pack_enabled', true );
	if ( ! $is_package ) return;

	if ( oc_pool_frontend_ui_already_rendered( $product->get_id() ) ) return;
	oc_pool_mark_frontend_ui_rendered( $product->get_id() );
	
	// Detectare Elementor îmbunătățită
	$is_elementor = oc_pool_is_elementor_page();
	
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
		
		/* Stiluri pentru câmpul de cantitate (doar formularul custom POOL) */
		.oc-pool-ui .quantity.woocommerce-quantity {
			margin-left: 15px !important;
			margin-bottom: 15px !important;
			margin-right: 15px !important;
		}
		</style>';
	});
	
	// Afișează UI-ul custom
	oc_pool_render_frontend_ui( $product, $is_elementor );
}

// oc_pool_is_elementor_page() is defined in pool-product-functions.php (canonical implementation)

function oc_pool_render_frontend_ui( $product, $is_elementor = false ) {
	$pack_id = $product->get_id();
	
	// Verifică dacă este dual mode
	$is_dual_mode = get_post_meta( $pack_id, '_oc_pool_dual_mode', true ) === 'yes';
	
	if ( $is_dual_mode ) {
		oc_pool_render_dual_frontend_ui( $product, $is_elementor );
		return;
	}
	
	// SINGLE MODE - codul existent
	// Backwards compatibility pentru pool_id
	$pool_id = get_post_meta( $pack_id, '_oc_pool_pool_id', true );
	if ( ! $pool_id ) {
		$pool_id = get_post_meta( $pack_id, '_mv_pack_pool_id', true );
	}
	$pool_product = $pool_id ? wc_get_product( $pool_id ) : null;
	
	if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
		echo '<div class="woocommerce-error">Produsul POOL nu este disponibil.</div>';
		return;
	}
	
	// Configurație pachet cu backwards compatibility
	$pack_price = get_post_meta( $pack_id, '_oc_pool_price', true );
	if ( ! $pack_price ) {
		$pack_price = get_post_meta( $pack_id, '_mv_pack_price', true );
	}
	if ( ! $pack_price ) $pack_price = $product->get_price();
	
	$min_selections = max( 1, (int) ( get_post_meta( $pack_id, '_oc_pool_min_selections', true ) ?: 
	                                  get_post_meta( $pack_id, '_mv_pack_min_selections', true ) ?: 1 ) );
	
	$max_selections = (int) ( get_post_meta( $pack_id, '_oc_pool_max_selections', true ) ?: 
	                          get_post_meta( $pack_id, '_mv_pack_max_selections', true ) ?: 0 );
	
	$ui_style = get_post_meta( $pack_id, '_oc_pool_ui_style', true ) ?: 'checkboxes';
	if ( ! metadata_exists( 'post', $pack_id, '_oc_pool_ui_style' ) || $ui_style === '' ) {
		$legacy_ui_style = get_post_meta( $pack_id, '_mv_pack_ui_style', true );
		if ( $legacy_ui_style ) $ui_style = $legacy_ui_style;
	}
	
	$helper_text = get_post_meta( $pack_id, '_oc_pool_helper_text', true );
	if ( ! $helper_text ) {
		$helper_text = get_post_meta( $pack_id, '_mv_pack_helper_text', true );
	}
	
	// Obține variațiile selectate în admin pentru acest pachet cu backwards compatibility
	$selected_variation_ids = get_post_meta( $pack_id, '_oc_pool_selected_variations', true );
	if ( ! is_array( $selected_variation_ids ) || empty( $selected_variation_ids ) ) {
		$selected_variation_ids = get_post_meta( $pack_id, '_mv_pack_selected_variations', true );
	}
	if ( ! is_array( $selected_variation_ids ) ) {
		$selected_variation_ids = [];
	}
	
	// Variații disponibile - filtrează doar pe cele selectate în admin
	$all_variations = $pool_product->get_available_variations();
	$available_variations = oc_pool_filter_variations( $all_variations, $selected_variation_ids );
	
	if ( empty( $available_variations ) ) {
		echo '<div class="woocommerce-error">' . esc_html__( 'Nu există variații disponibile pentru acest pachet.', OC_TEXT_DOMAIN ) . '</div>';
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

			<p class="oc-pool-single-purchase-note">
				<?php echo esc_html__( 'Acest abonament se cumpără individual: maximum 1 per comandă.', 'membership-validator-core' ); ?>
			</p>
			
			<!-- Selecții -->
			<div class="oc-pool-selections">
				
				<?php if ( $ui_style === 'slots' ): ?>
					<?php oc_pool_render_slots_ui( $available_variations, $min_selections, $max_selections, $is_elementor ); ?>
				<?php else: ?>
					<?php oc_pool_render_checkboxes_ui( $available_variations, $min_selections, $max_selections, $is_elementor ); ?>
				<?php endif; ?>
			</div>
			
			<!-- Cantitate fixă: 1 per comandă -->
			<div class="quantity woocommerce-quantity oc-pool-quantity-locked">
				<input type="hidden" id="oc_pool_quantity" name="quantity" value="1">
				<span class="oc-pool-quantity-label" aria-live="polite">Cantitate: 1</span>
			</div>
			
			<!-- Buton Adaugă în coș -->
			<div class="oc-pool-submit-section">
				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $pack_id ); ?>" class="button alt add-to-cart single_add_to_cart_button oc-pool-submit-btn">
					Adaugă în coș
				</button>
			</div>
		</form>
	</div>
	
	<?php oc_pool_render_scripts( $min_selections, $max_selections, $is_elementor ); ?>
	<?php oc_pool_render_styles( $is_elementor ); ?>
	<?php
}

function oc_pool_render_checkboxes_ui( $variations, $min, $max, $is_elementor = false, $pool_prefix = '' ) {
	// Lista simplă pentru TOATE scenariile - EXACT ca originalul
	echo '<div class="oc-pool-variations-list" style="margin: 16px 0;">';
	
	foreach ( $variations as $variation ) {
		$variation_obj = wc_get_product( $variation['variation_id'] );
		if ( ! $variation_obj ) continue;
		
		$label = wc_get_formatted_variation( $variation_obj, true, false );
		$stock_status = $variation_obj->is_in_stock();
		
		echo '<div class="oc-variation-item">';
		
		$field_name = $pool_prefix ? "oc_pool_{$pool_prefix}_selections[]" : "oc_pool_selections[]";
		$input_id = $pool_prefix ? "variation_{$pool_prefix}_{$variation['variation_id']}" : "variation_{$variation['variation_id']}";
		
		echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $variation['variation_id'] ) . '" 
			   id="' . esc_attr( $input_id ) . '"' . 
			   ($stock_status ? '' : ' disabled') . '>';
		
		echo '<label for="' . esc_attr( $input_id ) . '">';
		
		echo '<span>' . esc_html( $label ) . '</span>';
		
		if ( !$stock_status ) {
			echo '<span style="color: #dc3545; font-size: 12px;">Stoc epuizat</span>';
		}
		
		echo '</label>';
		echo '</div>';
	}
	
	echo '</div>';
}

function oc_pool_render_slots_ui( $variations, $min, $max, $is_elementor = false, $pool_prefix = '' ) {
	// Pentru dual mode, folosește exact numărul minim necesar
	// Pentru single mode, folosește max(min, 3) pentru a permite opțiuni multiple
	$slots_count = $max ?: $min;
	
	// Container cu clase standard WordPress/WooCommerce
	echo '<div class="woocommerce-slot-selection-wrapper slot-based-selection">';
	
	for ( $i = 1; $i <= $slots_count; $i++ ) {
		echo '<div class="woocommerce-slot-section slot-' . $i . '" data-slot="' . $i . '">';
		
		// Grid pentru opțiuni cu clase standard
		echo '<div class="slot-options-grid variations-grid">';
		
		// Opțiunea "nimic selectat"
		echo '<div class="slot-option empty-option" data-slot="' . $i . '">';
		
		$field_name = $pool_prefix ? "oc_pool_{$pool_prefix}_selections[]" : "oc_pool_selections[]";
		$input_id = $pool_prefix ? "slot_{$pool_prefix}_{$i}_empty" : "slot_{$i}_empty";
		
		echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="" 
			   id="' . esc_attr( $input_id ) . '" data-slot="' . $i . '" class="slot-radio" checked>';
		
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
			$input_id = $pool_prefix ? "slot_{$pool_prefix}_{$i}_var_{$variation['variation_id']}" : "slot_{$i}_var_{$variation['variation_id']}";
			
			echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $variation['variation_id'] ) . '" 
				   id="' . esc_attr( $input_id ) . '" data-slot="' . $i . '" class="slot-radio"' . 
				   ($variation_obj->is_in_stock() ? '' : ' disabled') . '>';
			
			// Label pentru radio button
			echo '<label for="' . esc_attr( $input_id ) . '" class="variation-label">';
			
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
		$('.oc-pool-slots select, select[data-slot], .select_container').hide();
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

function oc_pool_render_scripts( $min_selections, $max_selections, $is_elementor = false ) {
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
			var selected = $form.find('input[name*="_selections[]"]:checked, select[name*="_selections[]"]').filter(function() {
				return $(this).val() !== '';
			}).length;
			
			var $submit = $form.find('<?php echo $button_selector; ?>');
			
			if (selected < minSelections) {
				$submit.prop('disabled', true).addClass('oc-pool-disabled').text('Selectează cel puțin ' + minSelections + ' opțiuni');
				return false;
			} else if (maxSelections < unlimitedSelections && selected > maxSelections) {
				$submit.prop('disabled', true).addClass('oc-pool-disabled').text('Poți selecta maximum ' + maxSelections + ' opțiuni');
				return false;
			} else {
				$submit.prop('disabled', false).removeClass('oc-pool-disabled').text('Adaugă în coș');
				return true;
			}
		}
		
		$form.on('change', 'input[name*="_selections[]"], select[name*="_selections[]"]', validateSelections);
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

function oc_pool_render_styles( $is_elementor = false ) {
	?>
	<style type="text/css">
	/* Stiluri de bază OC Pool */
	.oc-pool-container { margin: 20px 0; }
	.oc-pool-ui { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
	.oc-pool-container form.cart.oc-pool-ui {
		display: flex !important;
		flex-direction: column !important;
		align-items: stretch !important;
		gap: 8px;
	}
	.oc-pool-container form.cart.oc-pool-ui .oc-pool-selections,
	.oc-pool-container form.cart.oc-pool-ui .oc-pool-section {
		width: 100%;
	}
	.oc-pool-price { margin-bottom: 20px; text-align: center; }
	.oc-pool-price .price { font-size: 24px; font-weight: bold; color: #333; }
	.oc-pool-helper { margin-bottom: 20px; padding: 10px; background: #f9f9f9; border-radius: 3px; }
	.oc-pool-selections { margin-bottom: 20px; }
	.oc-pool-container form.cart.oc-pool-ui > .oc-pool-selections,
	.oc-pool-container form.cart.oc-pool-ui > .oc-pool-section {
		margin-bottom: 6px;
	}
	.oc-pool-selections h4 { margin-bottom: 15px; }
	.oc-variation-item { margin: 20px; padding:10px; border: 1px solid #ccc; border-radius: 3px; }
	.oc-variation-item label { cursor: pointer; display: block; }
	.oc-pool-slots { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
	.oc-pool-slot { padding: 15px; border: 2px solid #ddd; border-radius: 5px; text-align: center; }
	.oc-pool-slot.filled { border-color: #007cba; background: #f0f8ff; }
	.oc-pool-single-purchase-note {
		margin: 0 0 12px 0;
		padding: 10px 12px;
		border-left: 4px solid #2271b1;
		background: #f0f6fc;
		color: #1e293b;
		font-weight: 600;
		line-height: 1.4;
	}
	.oc-pool-quantity-locked {
		margin: 0 0 6px 0 !important;
	}
	.oc-pool-quantity-label {
		display: inline-block;
		padding: 7px 12px;
		border: 1px solid #cbd5e1;
		border-radius: 6px;
		background: #f8fafc;
		font-weight: 600;
		color: #0f172a;
	}
	.oc-pool-ui .single_add_to_cart_button,
	.oc-pool-ui .button.alt.add-to-cart {
		display: inline-flex !important;
		align-items: center;
		justify-content: center;
		float: none !important;
		clear: both;
		margin: 0 !important;
		width: auto;
		position: static !important;
	}
	.oc-pool-ui .oc-pool-submit-section {
		margin-top: 0;
		width: 100%;
		display: flex;
		justify-content: flex-start;
	}
	.oc-pool-container form.cart.oc-pool-ui > .oc-pool-single-purchase-note,
	.oc-pool-container form.cart.oc-pool-ui > .oc-pool-quantity-locked,
	.oc-pool-container form.cart.oc-pool-ui > .oc-pool-submit-section {
		margin-left: 20px;
		margin-right: 20px;
	}
	
	/* Buton disabled */
	.oc-pool-disabled {
		opacity: 0.7;
		cursor: not-allowed !important;
		background-color: #ccc !important;
		border-color: #ccc !important;
		color: #666 !important;
	}
	
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

// =============================================================================
// OC-04 • Add-to-Cart & Prețare - EXACT ca originalul
// =============================================================================

// Pentru abonamentele POOL, cantitatea este fixă la 1 (fără incrementare/decrementare).
add_filter( 'woocommerce_is_sold_individually', 'oc_pool_force_sold_individually', 20, 2 );
function oc_pool_force_sold_individually( $sold_individually, $product ) {
	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return $sold_individually;
	}

	$product_id = (int) $product->get_id();
	$is_package = get_post_meta( $product_id, '_oc_pool_enabled', true ) || get_post_meta( $product_id, '_mv_pack_enabled', true );

	if ( $is_package ) {
		return true;
	}

	return $sold_individually;
}

// Mesaj informativ pe pagina produsului înainte de Add to cart.
add_action( 'woocommerce_before_add_to_cart_button', 'oc_pool_single_purchase_notice', 5 );
function oc_pool_single_purchase_notice() {
	global $product;
	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return;
	}

	$product_id = (int) $product->get_id();
	$is_package = get_post_meta( $product_id, '_oc_pool_enabled', true ) || get_post_meta( $product_id, '_mv_pack_enabled', true );
	if ( ! $is_package ) {
		return;
	}

	echo '<p class="oc-pool-single-purchase-note">';
	echo esc_html__( 'Acest abonament se cumpără individual: maximum 1 per comandă.', 'membership-validator-core' );
	echo '</p>';
}

// Validare înainte de adăugare în coș
add_filter( 'woocommerce_add_to_cart_validation', 'oc_pool_validate_add_to_cart', 20, 5 );
function oc_pool_validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
	// Backwards compatibility check
	$is_package = get_post_meta( $product_id, '_oc_pool_enabled', true ) || 
	              get_post_meta( $product_id, '_mv_pack_enabled', true );
	if ( ! $is_package ) return $passed;

	// Regula business: un singur produs POOL per checkout, qty fix 1.
	$quantity = max( 1, (int) $quantity );
	if ( $quantity > 1 ) {
		wc_add_notice( 'Este permisă achiziționarea unui singur abonament per comandă.', 'error' );
		return false;
	}

	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['oc_pool'] ) || empty( $cart_item['oc_pool']['is_package'] ) ) {
				continue;
			}

			// Există deja un produs POOL în coș (indiferent care): blocăm al doilea.
			wc_add_notice( 'Este permisă achiziționarea unui singur abonament per comandă. Finalizează această comandă sau golește coșul pentru a adăuga alt abonament.', 'error' );
			return false;
		}
	}
	
	// Verifică dacă este dual mode
	$is_dual_mode = get_post_meta( $product_id, '_oc_pool_dual_mode', true ) === 'yes';
	
	if ( $is_dual_mode ) {
		return oc_pool_validate_dual_add_to_cart( $passed, $product_id, $quantity );
	}
	
	// SINGLE MODE - codul existent
	// Backwards compatibility pentru meta values - optimizat PHP 8.2+
	$pool_id = get_post_meta( $product_id, '_oc_pool_pool_id', true ) ?: 
	           get_post_meta( $product_id, '_mv_pack_pool_id', true );
	
	$min_selections = max( 1, (int) ( get_post_meta( $product_id, '_oc_pool_min_selections', true ) ?: 
	                                  get_post_meta( $product_id, '_mv_pack_min_selections', true ) ?: 1 ) );
	
	$max_selections = (int) ( get_post_meta( $product_id, '_oc_pool_max_selections', true ) ?: 
	                          get_post_meta( $product_id, '_mv_pack_max_selections', true ) ?: 0 );
	
	$selections = array_filter( (array) ( $_POST['oc_pool_selections'] ?? [] ) );
	$quantity = max( 1, (int) $quantity );
	
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
	
	// Obține variațiile selectate în admin pentru acest pachet cu backwards compatibility
	$selected_variation_ids = get_post_meta( $product_id, '_oc_pool_selected_variations', true );
	if ( ! is_array( $selected_variation_ids ) || empty( $selected_variation_ids ) ) {
		$selected_variation_ids = get_post_meta( $product_id, '_mv_pack_selected_variations', true );
	}
	if ( ! is_array( $selected_variation_ids ) ) {
		$selected_variation_ids = [];
	}
	
	// Variații valide = sunt în POOL ȘI sunt selectate în admin ȘI sunt active/purchasable
	$all_pool_variations = $pool_product->get_available_variations();
	$valid_variation_ids = oc_pool_resolve_variation_ids( $all_pool_variations, $selected_variation_ids );
	
	foreach ( $selections as $variation_id ) {
		if ( ! in_array( (int) $variation_id, $valid_variation_ids, true ) ) {
			wc_add_notice( 'Una din selecțiile tale nu mai este disponibilă sau nu este inclusă în acest pachet.', 'error' );
			return false;
		}
	}
	
	return $passed;
}

// Safety net: blochează setarea cantității > 1 din pagina de coș pentru pachete POOL.
add_filter( 'woocommerce_update_cart_validation', 'oc_pool_validate_cart_quantity_update', 20, 4 );
function oc_pool_validate_cart_quantity_update( $passed, $cart_item_key, $values, $quantity ) {
	$is_package = ! empty( $values['oc_pool'] ) && ! empty( $values['oc_pool']['is_package'] );
	if ( ! $is_package ) {
		return $passed;
	}

	if ( (int) $quantity > 1 ) {
		wc_add_notice( 'Este permisă achiziționarea unui singur abonament per comandă.', 'error' );
		return false;
	}

	return $passed;
}

// Adaugă meta data la cart item pentru pachet
add_filter( 'woocommerce_add_cart_item_data', 'oc_pool_add_cart_item_data', 20, 3 );
function oc_pool_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
	// Backwards compatibility check
	$is_package = get_post_meta( $product_id, '_oc_pool_enabled', true ) || 
	              get_post_meta( $product_id, '_mv_pack_enabled', true );
	if ( ! $is_package ) return $cart_item_data;
	
	// Verifică dacă este dual mode
	$is_dual_mode = get_post_meta( $product_id, '_oc_pool_dual_mode', true ) === 'yes';
	
	if ( $is_dual_mode ) {
		return oc_pool_add_dual_cart_item_data( $cart_item_data, $product_id, $variation_id );
	}
	
	// SINGLE MODE - codul existent
	$selections = isset( $_POST['oc_pool_selections'] ) ? array_filter( (array) $_POST['oc_pool_selections'] ) : [];
	
	// Backwards compatibility pentru pool_id
	$pool_id = get_post_meta( $product_id, '_oc_pool_pool_id', true );
	if ( ! $pool_id ) {
		$pool_id = get_post_meta( $product_id, '_mv_pack_pool_id', true );
	}
	
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

// Adaugă linii copil în coș după adăugarea pachetului
add_action( 'woocommerce_add_to_cart', 'oc_pool_add_child_items', 20, 6 );
function oc_pool_add_child_items( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	if ( ! isset( $cart_item_data['oc_pool'] ) ) return;
	
	$pack_data = $cart_item_data['oc_pool'];
	
	// Verifică dacă este dual mode
	if ( isset( $pack_data['dual_mode'] ) && $pack_data['dual_mode'] ) {
		// DUAL MODE
		$pool1_id = $pack_data['pool1_id'];
		$pool2_id = $pack_data['pool2_id'];
		$pool1_selections = $pack_data['pool1_selections'];
		$pool2_selections = $pack_data['pool2_selections'];
		
		// Adaugă items pentru Pool 1
		foreach ( $pool1_selections as $selected_variation_id ) {
			WC()->cart->add_to_cart( 
				$pool1_id,                   // Product ID (POOL 1)
				$quantity,                   // Quantity (sincronizată cu pachetul)
				$selected_variation_id,      // Variation ID
				[],                          // Variation attributes
				[                            // Cart item data
					'oc_pool_child' => true,
					'oc_pool_parent_key' => $cart_item_key,
					'oc_pool_parent_id' => $product_id,
					'oc_pool_variation_id' => $selected_variation_id,
					'oc_pool_pool_number' => 1
				]
			);
		}
		
		// Adaugă items pentru Pool 2
		foreach ( $pool2_selections as $selected_variation_id ) {
			WC()->cart->add_to_cart( 
				$pool2_id,                   // Product ID (POOL 2)
				$quantity,                   // Quantity (sincronizată cu pachetul)
				$selected_variation_id,      // Variation ID
				[],                          // Variation attributes
				[                            // Cart item data
					'oc_pool_child' => true,
					'oc_pool_parent_key' => $cart_item_key,
					'oc_pool_parent_id' => $product_id,
					'oc_pool_variation_id' => $selected_variation_id,
					'oc_pool_pool_number' => 2
				]
			);
		}
	} else {
		// SINGLE MODE - codul existent
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
}

// Setează prețul la 0 pentru liniile copil
add_action( 'woocommerce_before_calculate_totals', 'oc_pool_set_child_prices', 20 );
function oc_pool_set_child_prices( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
			$cart_item['data']->set_price( 0 );
		}
	}
}

// Setează prețul fix pentru pachet
add_action( 'woocommerce_before_calculate_totals', 'oc_pool_set_package_price', 15 );
function oc_pool_set_package_price( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['oc_pool'] ) && $cart_item['oc_pool']['is_package'] ) {
			$product_id = $cart_item['product_id'];
			
			// Backwards compatibility pentru pack_price
			$pack_price = get_post_meta( $product_id, '_oc_pool_price', true );
			if ( ! $pack_price ) {
				$pack_price = get_post_meta( $product_id, '_mv_pack_price', true );
			}
			
			if ( ! $pack_price ) {
				$product = wc_get_product( $product_id );
				$pack_price = $product ? $product->get_regular_price() : 0;
			}
			
			$cart_item['data']->set_price( floatval( $pack_price ) );
		}
	}
}

// =============================================================================
// OC-05 • Sincronizare & Integritate în Coș - EXACT ca originalul
// =============================================================================

// Sincronizează cantitatea liniilor copil când se modifică cantitatea pachetului
add_action( 'woocommerce_after_cart_item_quantity_update', 'oc_pool_sync_child_quantities', 20, 4 );
function oc_pool_sync_child_quantities( $cart_item_key, $quantity, $old_quantity, $cart ) {
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

// Previne ștergerea individuală a liniilor copil
add_filter( 'woocommerce_cart_item_remove_link', 'oc_pool_prevent_child_removal', 20, 2 );
function oc_pool_prevent_child_removal( $link, $cart_item_key ) {
	$cart_item = WC()->cart->get_cart_item( $cart_item_key );
	
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return '<span class="oc-pool-child-notice" title="Această linie face parte dintr-un pachet">🔒</span>';
	}
	
	return $link;
}

// Șterge liniile copil când se șterge pachetul
add_action( 'woocommerce_remove_cart_item', 'oc_pool_remove_child_items', 20, 2 );
function oc_pool_remove_child_items( $cart_item_key, $cart ) {
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

// Repară liniile copil orfane (cleanup)
add_action( 'woocommerce_cart_loaded_from_session', 'oc_pool_cleanup_orphaned_children' );
function oc_pool_cleanup_orphaned_children( $cart ) {
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

// =============================================================================
// OC-05B • Ascundere Cantitate & Preț pentru Variații în Coș - EXACT ca originalul
// =============================================================================

// Ascunde cantitatea pentru liniile copil în toate widget-urile
add_filter( 'woocommerce_widget_cart_item_quantity', 'oc_pool_hide_child_widget_quantity', 10, 3 );
function oc_pool_hide_child_widget_quantity( $quantity_html, $cart_item, $cart_item_key ) {
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return '<span class="quantity">-</span>';
	}
	return $quantity_html;
}

// Ascunde input-ul de cantitate pentru liniile copil
add_filter( 'woocommerce_cart_item_quantity', 'oc_pool_hide_child_quantity_input', 10, 3 );
function oc_pool_hide_child_quantity_input( $product_quantity, $cart_item_key, $cart_item ) {
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return '<span class="product-quantity">-</span>';
	}
	return $product_quantity;
}

// Ascunde prețul pentru liniile copil
add_filter( 'woocommerce_cart_item_price', 'oc_pool_hide_child_price', 10, 3 );
function oc_pool_hide_child_price( $price, $cart_item, $cart_item_key ) {
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return '<span class="amount">-</span>';
	}
	return $price;
}

// Ascunde subtotalul pentru liniile copil
add_filter( 'woocommerce_cart_item_subtotal', 'oc_pool_hide_child_subtotal', 10, 3 );
function oc_pool_hide_child_subtotal( $subtotal, $cart_item, $cart_item_key ) {
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return '<span class="amount">-</span>';
	}
	return $subtotal;
}

// Setează prețul la 0 pentru liniile copil în calculele finale
add_action( 'woocommerce_before_calculate_totals', 'oc_pool_set_child_prices_zero', 25 );
function oc_pool_set_child_prices_zero( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

	foreach ( $cart->get_cart() as $cart_item ) {
		if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
			$cart_item['data']->set_price( 0 );
		}
	}
}

// Exclude variațiile din contorul de produse în coș
add_filter( 'woocommerce_cart_contents_count', 'oc_pool_exclude_children_from_count' );
function oc_pool_exclude_children_from_count( $count ) {
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

// Exclude variațiile din widget-urile coșului modal
add_filter( 'woocommerce_widget_cart_item_visible', 'oc_pool_hide_children_from_widget', 10, 3 );
function oc_pool_hide_children_from_widget( $visible, $cart_item, $cart_item_key ) {
	// Ascunde variațiile copil din widget-urile coșului
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		return false;
	}
	return $visible;
}

// =============================================================================
// OC-06 • Salvare în Comandă & Order Meta - EXACT ca originalul
// =============================================================================

// Salvează meta-urile pachetului în order items
add_action( 'woocommerce_checkout_create_order_line_item', 'oc_pool_save_order_item_meta', 20, 4 );
function oc_pool_save_order_item_meta( $item, $cart_item_key, $values, $order ) {
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
	}
	
	// Pentru linii copil - toate ascunse
	if ( isset( $values['oc_pool_child'] ) && $values['oc_pool_child'] ) {
		$item->add_meta_data( '_oc_pool_child', 'yes' );
		$item->add_meta_data( '_oc_pool_parent_id', $values['oc_pool_parent_id'] ?? '' );
		$item->add_meta_data( '_oc_pool_variation_id', $values['oc_pool_variation_id'] ?? '' );
	}
}

// Ascunde meta-urile OC Pool din interfața admin
add_filter( 'woocommerce_hidden_order_itemmeta', 'oc_pool_hide_order_item_meta' );
function oc_pool_hide_order_item_meta( $hidden_meta ) {
	$oc_pool_meta = [
		'_oc_pool_type',
		'_oc_pool_pool_id', 
		'_oc_pool_selections_count',
		'_oc_pool_child',
		'_oc_pool_parent_id',
		'_oc_pool_variation_id'
	];
	
	// Adaugă meta-urile dinamice pentru sloturi (până la 10 sloturi)
	for ( $i = 1; $i <= 10; $i++ ) {
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_label";
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_variation_id";
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_pa_alege-tipul-de-abonament";
		// Adaugă și alte atribute posibile
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_pa_niveau";
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_pa_duree";
		$oc_pool_meta[] = "_oc_pool_slot_{$i}_pa_intensitate";
	}
	
	return array_merge( $hidden_meta, $oc_pool_meta );
}

	// Restricționează metodele de plată la checkout în funcție de pachetul POOL
	add_filter( 'woocommerce_available_payment_gateways', 'oc_pool_filter_available_payment_gateways', 20 );
	function oc_pool_filter_available_payment_gateways( $available_gateways ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $available_gateways;
		}

		if ( empty( $available_gateways ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $available_gateways;
		}

		$allowed_sets = [];
		$supported_gateways = [ 'oc_7card', 'oc_esx' ];

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['oc_pool'] ) || empty( $cart_item['oc_pool']['is_package'] ) ) {
				continue;
			}

			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			if ( ! $product_id ) {
				continue;
			}

			$allowed = get_post_meta( $product_id, '_oc_pool_allowed_payment_gateways', true );
			if ( ! is_array( $allowed ) || empty( $allowed ) ) {
				continue;
			}

			$allowed = array_values( array_intersect( array_map( 'sanitize_text_field', $allowed ), $supported_gateways ) );
			if ( ! empty( $allowed ) ) {
				$allowed_sets[] = $allowed;
			}
		}

		if ( empty( $allowed_sets ) ) {
			return $available_gateways;
		}

		$original_gateways = $available_gateways;

		$final_allowed = array_shift( $allowed_sets );
		foreach ( $allowed_sets as $allowed_set ) {
			$final_allowed = array_values( array_intersect( $final_allowed, $allowed_set ) );
		}

		if ( empty( $final_allowed ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice(
					'Produsele din coș au restricții diferite de plată (7CARD/ESX). Selectează aceeași metodă permisă pe toate produsele sau finalizează comenzile separat.',
					'notice'
				);
			}

			$final_allowed = [];
			foreach ( $allowed_sets as $allowed_set ) {
				$final_allowed = array_values( array_unique( array_merge( $final_allowed, $allowed_set ) ) );
			}
		}

		foreach ( array_keys( $available_gateways ) as $gateway_id ) {
			if ( ! in_array( $gateway_id, $final_allowed, true ) ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}

		if ( empty( $available_gateways ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice(
					'Metodele de plată configurate pentru pachet nu sunt disponibile momentan. Verifică setările gateway-urilor 7CARD/ESX.',
					'notice'
				);
			}

			return $original_gateways;
		}

		return $available_gateways;
	}

	// Forțează afișarea secțiunii de plată pentru pachete POOL, inclusiv la total 0
	add_filter( 'woocommerce_cart_needs_payment', 'oc_pool_force_cart_needs_payment', 20, 2 );
	function oc_pool_force_cart_needs_payment( $needs_payment, $cart ) {
		if ( $needs_payment ) {
			return $needs_payment;
		}

		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return $needs_payment;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['oc_pool'] ) || empty( $cart_item['oc_pool']['is_package'] ) ) {
				continue;
			}

			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			if ( ! $product_id ) {
				continue;
			}

			$allowed = get_post_meta( $product_id, '_oc_pool_allowed_payment_gateways', true );
			if ( is_array( $allowed ) && ! empty( $allowed ) ) {
				return true;
			}
		}

		return $needs_payment;
	}

// =============================================================================
// OC-07 • Afișare Coș/Checkout/Email/My Account - EXACT ca originalul
// =============================================================================

// Afișează meta-urile în coș (opțional - dezactivat pentru a fi complet ascuns)
add_filter( 'woocommerce_get_item_data', 'oc_pool_display_cart_item_data', 20, 2 );
function oc_pool_display_cart_item_data( $item_data, $cart_item ) {
	// Meta-urile sunt ascunse - nu afișăm nimic
	// Datele rămân în backend pentru funcționalitate
	return $item_data;
}

// Marchează vizual liniile copil în coș (stilizare discretă)
add_filter( 'woocommerce_cart_item_name', 'oc_pool_mark_child_items', 20, 3 );
function oc_pool_mark_child_items( $name, $cart_item, $cart_item_key ) {
	if ( isset( $cart_item['oc_pool_child'] ) && $cart_item['oc_pool_child'] ) {
		// Stilizare discretă - indent și culoare mai estompată
		$name = '<span class="oc-pool-child-item">' . $name . '</span>';
	}
	
	return $name;
}

// Marchează și în order items (admin, emailuri, etc.)
add_filter( 'woocommerce_order_item_name', 'oc_pool_mark_order_items', 20, 3 );
function oc_pool_mark_order_items( $name, $item, $is_visible ) {
	// Verifică dacă este un item copil din pachet
	if ( $item->get_meta( '_oc_pool_child' ) === 'yes' ) {
		$name = '<span class="oc-pool-child-item">' . $name . '</span>';
	}
	
	return $name;
}

// Adaugă CSS pentru stilizarea elementelor din pachet
add_action( 'wp_head', 'oc_pool_add_child_styles' );
add_action( 'admin_head', 'oc_pool_add_child_styles' );
function oc_pool_add_child_styles() {
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
	.oc-pool-child-item {
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
	</style>
	<?php
}

// =============================================================================
// OC-08 • Vizibilitate & SEO (POOL) - EXACT ca originalul
// =============================================================================

// Ascunde produsele POOL din shop și căutare
add_action( 'pre_get_posts', 'oc_pool_hide_pool_products' );
function oc_pool_hide_pool_products( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) return;
	
	// În shop, categorii, arhive de produse
	if ( is_shop() || is_product_category() || is_product_tag() || $query->is_search() ) {
		$pool_ids = oc_pool_get_all_pool_ids();
		
		if ( ! empty( $pool_ids ) ) {
			$post__not_in = $query->get( 'post__not_in' ) ?: [];
			$post__not_in = array_merge( $post__not_in, $pool_ids );
			$query->set( 'post__not_in', array_unique( $post__not_in ) );
		}
	}
}

// Returnează toate produsele variabile pentru dropdown
if ( ! function_exists( 'oc_pool_get_all_variable_products' ) ) {
function oc_pool_get_all_variable_products() {
	// Căutare produse variabile
	
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
		
					// Produs variabil găsit și adăugat
		
		$variable_products[] = [
			'id' => $product_post->ID,
			'title' => $product_post->post_title,
			'status' => $product_post->post_status,
			'variations_count' => $variations_count
		];
	}
	
	// Dacă nu găsește nimic, încearcă metoda 2: prin taxonomie
	if ( empty( $variable_products ) ) {
		// Metoda alternativă prin taxonomie
		
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
			
			// Produs variabil găsit prin metoda alternativă
			
			$variable_products[] = [
				'id' => $product_post->ID,
				'title' => $product_post->post_title,
				'status' => $product_post->post_status,
				'variations_count' => $variations_count
			];
		}
	}
	
	// Total produse variabile găsite
	
	return $variable_products;
}
}

// Returnează toate ID-urile de produse folosite ca POOL
if ( ! function_exists( 'oc_pool_get_all_pool_ids' ) ) {
function oc_pool_get_all_pool_ids() {
	static $pool_ids = null;
	
	if ( $pool_ids === null ) {
		global $wpdb;
		
		$pool_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT DISTINCT meta_value 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = %s 
			AND meta_value != '' 
			AND meta_value != '0'
		", '_oc_pool_pool_id' ) );
		
		$pool_ids = array_map( 'intval', $pool_ids );
	}
	
	return $pool_ids;
}
}

// Adaugă noindex la produsele POOL
add_action( 'wp_head', 'oc_pool_noindex_pool_products' );
function oc_pool_noindex_pool_products() {
	if ( ! is_product() ) return;
	
	global $post;
	$pool_ids = oc_pool_get_all_pool_ids();
	
	if ( in_array( $post->ID, $pool_ids ) ) {
		echo '<meta name="robots" content="noindex, nofollow">' . "\n";
	}
}

// Redirect produse POOL la shop sau la primul pachet care le folosește
add_action( 'template_redirect', 'oc_pool_redirect_pool_products' );
function oc_pool_redirect_pool_products() {
	if ( ! is_product() ) return;
	
	global $post;
	$pool_ids = oc_pool_get_all_pool_ids();
	
	if ( in_array( $post->ID, $pool_ids ) ) {
		// Încearcă să găsească primul pachet care folosește acest POOL
		global $wpdb;
		$package_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = %s 
			AND meta_value = %s 
			LIMIT 1
		", '_oc_pool_pool_id', $post->ID ) );
		
		if ( $package_id ) {
			wp_redirect( get_permalink( $package_id ), 301 );
		} else {
			wp_redirect( wc_get_page_permalink( 'shop' ), 301 );
		}
		exit;
	}
}

// AJAX handler pentru încărcarea variațiilor din POOL
// Removed duplicate add_action: this action is handled by OC_Pool_Ajax::get_pool_variations()
function oc_pool_ajax_get_pool_variations() {
	if ( ! wp_verify_nonce( $_POST['security'], 'oc_pool_admin_nonce' ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}
	
	if ( ! current_user_can( 'edit_products' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}
	
	$pool_id = intval( $_POST['pool_id'] );
	$package_id = intval( $_POST['package_id'] );
	
	if ( ! $pool_id ) {
		wp_send_json_error( 'Invalid pool ID' );
	}
	
	$pool_product = wc_get_product( $pool_id );
	if ( ! $pool_product || $pool_product->get_type() !== 'variable' ) {
		wp_send_json_error( 'Produsul POOL nu este valid sau nu este variabil' );
	}
	
	// Obține variațiile existente selectate pentru acest pachet
	$selected_variations = get_post_meta( $package_id, '_oc_pool_selected_variations', true );
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
			
			$html .= '<div>';
			$html .= '<label>';
			$html .= '<input type="checkbox" name="_oc_pool_selected_variations[]" value="' . esc_attr( $variation['variation_id'] ) . '"';
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

// Fallback de performanță pentru cataloage mari (cacheing basic)
add_action( 'init', 'oc_pool_setup_caching' );
function oc_pool_setup_caching() {
	// Cache pentru POOL IDs (30 minute)
	if ( ! wp_cache_get( 'oc_pool_pool_ids' ) ) {
		$pool_ids = oc_pool_get_all_pool_ids();
		wp_cache_set( 'oc_pool_pool_ids', $pool_ids, '', 1800 );
	}
}

// Curăță cache-ul când se salvează un pachet
add_action( 'woocommerce_process_product_meta_simple', 'oc_pool_clear_cache' );
function oc_pool_clear_cache( $post_id ) {
	if ( get_post_meta( $post_id, '_oc_pool_enabled', true ) ) {
		wp_cache_delete( 'oc_pool_pool_ids' );
	}
}

/**
 * Renderează UI-ul frontend pentru DUAL MODE
 *
 * @param WC_Product $product
 * @param bool $is_elementor
 */
function oc_pool_render_dual_frontend_ui( $product, $is_elementor = false ) {
	$pack_id = $product->get_id();
	
	// Obține configurația dual mode
	$config = oc_pool_get_package_config( $pack_id );
	if ( ! $config || ! $config['dual_mode'] ) {
		echo '<div class="woocommerce-error">Configurația dual mode nu este validă.</div>';
		return;
	}
	
	// Pool 1 și Pool 2
	$pool1_id = $config['pool1_id'];
	$pool2_id = $config['pool2_id'];
	$pool1_product = $pool1_id ? wc_get_product( $pool1_id ) : null;
	$pool2_product = $pool2_id ? wc_get_product( $pool2_id ) : null;
	
	if ( ! $pool1_product || $pool1_product->get_type() !== 'variable' ) {
		echo '<div class="woocommerce-error">Pool 1 nu este disponibil.</div>';
		return;
	}
	
	if ( ! $pool2_product || $pool2_product->get_type() !== 'variable' ) {
		echo '<div class="woocommerce-error">Pool 2 nu este disponibil.</div>';
		return;
	}
	
	// Configurație
	$pack_price = $config['price'] ?: $product->get_price();
	$pool1_label = $config['pool1_label'] ?: 'Prima selecție:';
	$pool2_label = $config['pool2_label'] ?: 'A doua selecție:';
	$pool1_min = max( 1, (int) $config['pool1_min'] );
	$pool2_min = max( 1, (int) $config['pool2_min'] );
	$pool1_ui_style = $config['pool1_ui_style'] ?: 'checkboxes';
	$pool2_ui_style = $config['pool2_ui_style'] ?: 'checkboxes';
	$allow_same_variation = $config['allow_same_variation'];
	
	// Obține variațiile pentru fiecare pool
	$pool1_selected_ids = $config['pool1_variations'] ?: [];
	$pool2_selected_ids = $config['pool2_variations'] ?: [];
	
	
	// Verifică dacă funcția există
	if ( ! function_exists( 'oc_pool_filter_variations' ) ) {
		echo '<div class="woocommerce-error">Funcția oc_pool_filter_variations nu este disponibilă.</div>';
		return;
	}
	
	$pool1_variations = oc_pool_filter_variations( $pool1_product->get_available_variations(), $pool1_selected_ids );
	$pool2_variations = oc_pool_filter_variations( $pool2_product->get_available_variations(), $pool2_selected_ids );
	
	if ( empty( $pool1_variations ) ) {
		echo '<div class="woocommerce-error">Nu există variații disponibile pentru Pool 1.</div>';
		return;
	}
	
	if ( empty( $pool2_variations ) ) {
		echo '<div class="woocommerce-error">Nu există variații disponibile pentru Pool 2.</div>';
		return;
	}
	
	?>
	<div class="oc-pool-container oc-pool-dual-mode woocommerce-product-form">
		<form class="cart oc-pool-ui" method="post" enctype="multipart/form-data">
			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $pack_id ); ?>">
			<input type="hidden" name="oc_pool_dual_mode" value="yes">
			<input type="hidden" name="oc_pool_pool1_id" value="<?php echo esc_attr( $pool1_id ); ?>">
			<input type="hidden" name="oc_pool_pool2_id" value="<?php echo esc_attr( $pool2_id ); ?>">
			<input type="hidden" name="oc_pool_allow_same_variation" value="<?php echo $allow_same_variation ? 'yes' : 'no'; ?>">

			<p class="oc-pool-single-purchase-note">
				<?php echo esc_html__( 'Acest abonament se cumpără individual: maximum 1 per comandă.', 'membership-validator-core' ); ?>
			</p>
			
			<!-- Pool 1 -->
			<div class="oc-pool-section oc-pool-section-1">
				<h4 class="oc-pool-section-title" style="margin-left: 10px !important; padding-left: 15px !important;"><?php echo esc_html( $pool1_label ); ?></h4>
				
				<div class="oc-pool-selections" data-pool="1" data-min="<?php echo esc_attr( $pool1_min ); ?>">
					<?php if ( $pool1_ui_style === 'slots' ): ?>
						<?php oc_pool_render_slots_ui( $pool1_variations, $pool1_min, 0, $is_elementor, 'pool1' ); ?>
					<?php else: ?>
						<?php oc_pool_render_checkboxes_ui( $pool1_variations, $pool1_min, 0, $is_elementor, 'pool1' ); ?>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Pool 2 -->
			<div class="oc-pool-section oc-pool-section-2">
				<h4 class="oc-pool-section-title" style="margin-left: 10px !important; padding-left: 15px !important;"><?php echo esc_html( $pool2_label ); ?></h4>
				
				<div class="oc-pool-selections" data-pool="2" data-min="<?php echo esc_attr( $pool2_min ); ?>">
					<?php if ( $pool2_ui_style === 'slots' ): ?>
						<?php oc_pool_render_slots_ui( $pool2_variations, $pool2_min, 0, $is_elementor, 'pool2' ); ?>
					<?php else: ?>
						<?php oc_pool_render_checkboxes_ui( $pool2_variations, $pool2_min, 0, $is_elementor, 'pool2' ); ?>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Cantitate fixă: 1 per comandă -->
			<div class="quantity woocommerce-quantity oc-pool-quantity-locked">
				<input type="hidden" id="oc_pool_quantity" name="quantity" value="1">
				<span class="oc-pool-quantity-label" aria-live="polite">Cantitate: 1</span>
			</div>
			
			<!-- Buton -->
			<div class="oc-pool-submit-section">
				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $pack_id ); ?>" class="button alt add-to-cart single_add_to_cart_button oc-pool-submit-btn" disabled>
					Adaugă în coș
				</button>
			</div>
		</form>
	</div>
	
	<?php oc_pool_render_dual_scripts( $pool1_min, $pool2_min, $allow_same_variation, $is_elementor ); ?>
	<?php oc_pool_render_styles( $is_elementor ); ?>
	<?php
}

/**
 * Renderează script-urile pentru DUAL MODE
 *
 * @param int $pool1_min
 * @param int $pool2_min
 * @param bool $allow_same_variation
 * @param bool $is_elementor
 */
function oc_pool_render_dual_scripts( $pool1_min, $pool2_min, $allow_same_variation, $is_elementor = false ) {
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		const pool1Min = <?php echo intval( $pool1_min ); ?>;
		const pool2Min = <?php echo intval( $pool2_min ); ?>;
		const allowSameVariation = <?php echo $allow_same_variation ? 'true' : 'false'; ?>;
		
		// Funcție pentru validarea selecțiilor
		function validateDualSelections() {
			const pool1Selected = $('input[name="oc_pool_pool1_selections[]"]:checked').filter(function() {
				return $(this).val() && $(this).val() !== '';
			}).length;
			const pool2Selected = $('input[name="oc_pool_pool2_selections[]"]:checked').filter(function() {
				return $(this).val() && $(this).val() !== '';
			}).length;
			
			const pool1Valid = pool1Selected >= pool1Min;
			const pool2Valid = pool2Selected >= pool2Min;
			
			// Actualizează butonul
			const $submitBtn = $('button[name="add-to-cart"], .single_add_to_cart_button');
			if (pool1Valid && pool2Valid) {
				$submitBtn.prop('disabled', false).removeClass('disabled');
			} else {
				$submitBtn.prop('disabled', true).addClass('disabled');
			}
			
			// Actualizează contoarele
			updatePoolCounters();
			
			// Verifică duplicate dacă nu sunt permise
			if (!allowSameVariation) {
				checkDuplicateSelections();
			}
		}
		
		// Funcție pentru actualizarea contorilor
		function updatePoolCounters() {
			const pool1Selected = $('input[name="oc_pool_pool1_selections[]"]:checked').filter(function() {
				return $(this).val() && $(this).val() !== '';
			}).length;
			const pool2Selected = $('input[name="oc_pool_pool2_selections[]"]:checked').filter(function() {
				return $(this).val() && $(this).val() !== '';
			}).length;
			
			$('.oc-pool-section-1 .selection-counter').text(`${pool1Selected}/${pool1Min} selecționate`);
			$('.oc-pool-section-2 .selection-counter').text(`${pool2Selected}/${pool2Min} selecționate`);
		}
		
		// Funcție pentru verificarea duplicatelor
		function checkDuplicateSelections() {
			if (allowSameVariation) return;
			
			const pool1Values = $('input[name="oc_pool_pool1_selections[]"]:checked').map(function() {
				return $(this).val();
			}).get().filter(val => val && val !== ''); // Exclude valorile goale
			
			const pool2Values = $('input[name="oc_pool_pool2_selections[]"]:checked').map(function() {
				return $(this).val();
			}).get().filter(val => val && val !== ''); // Exclude valorile goale
			
			// Găsește duplicate (doar dacă ambele array-uri au valori)
			const duplicates = pool1Values.length > 0 && pool2Values.length > 0 ? 
				pool1Values.filter(value => pool2Values.includes(value)) : [];
			
			if (duplicates.length > 0) {
				// Afișează warning
				$('.oc-pool-duplicate-warning').remove();
				$('.oc-pool-container').prepend(
					'<div class="oc-pool-duplicate-warning woocommerce-error">' +
					'Nu poți selecta aceeași variație în ambele pool-uri.' +
					'</div>'
				);
				$('button[name="add-to-cart"], .single_add_to_cart_button').prop('disabled', true).addClass('disabled');
			} else {
				$('.oc-pool-duplicate-warning').remove();
			}
		}
		
		// Event listeners
		$('input[name="oc_pool_pool1_selections[]"], input[name="oc_pool_pool2_selections[]"]').on('change', function() {
			validateDualSelections();
		});
		
		// Validare inițială
		validateDualSelections();
		
		// Adaugă contoare dacă nu există
		if ($('.oc-pool-section-1 .selection-counter').length === 0) {
			$('.oc-pool-section-1 .oc-pool-section-title').after('<div class="selection-counter" style="font-size: 12px; color: #666; margin: 5px 0; margin-left: 15px;">');
		}
		if ($('.oc-pool-section-2 .selection-counter').length === 0) {
			$('.oc-pool-section-2 .oc-pool-section-title').after('<div class="selection-counter" style="font-size: 12px; color: #666; margin: 5px 0; margin-left: 15px;">');
		}
	});
	</script>
	<?php
}

/**
 * Validare pentru DUAL MODE add to cart
 *
 * @param bool $passed
 * @param int $product_id
 * @param int $quantity
 * @return bool
 */
function oc_pool_validate_dual_add_to_cart( $passed, $product_id, $quantity ) {
	$config = oc_pool_get_package_config( $product_id );
	if ( ! $config || ! $config['dual_mode'] ) {
		wc_add_notice( 'Configurația dual mode nu este validă.', 'error' );
		return false;
	}
	
	$pool1_selections = array_filter( (array) ( $_POST['oc_pool_pool1_selections'] ?? [] ) );
	$pool2_selections = array_filter( (array) ( $_POST['oc_pool_pool2_selections'] ?? [] ) );
	
	$pool1_min = max( 1, (int) $config['pool1_min'] );
	$pool2_min = max( 1, (int) $config['pool2_min'] );
	$allow_same_variation = $config['allow_same_variation'];
	
	// Validare Pool 1
	if ( count( $pool1_selections ) < $pool1_min ) {
		wc_add_notice( sprintf( 'Selectează cel puțin %d opțiuni pentru prima selecție.', $pool1_min ), 'error' );
		return false;
	}
	
	// Validare Pool 2  
	if ( count( $pool2_selections ) < $pool2_min ) {
		wc_add_notice( sprintf( 'Selectează cel puțin %d opțiuni pentru a doua selecție.', $pool2_min ), 'error' );
		return false;
	}
	
	// Verifică duplicate dacă nu sunt permise
	if ( ! $allow_same_variation ) {
		// Convertește la int pentru comparație corectă
		$pool1_int = array_map( 'intval', $pool1_selections );
		$pool2_int = array_map( 'intval', $pool2_selections );
		$duplicates = array_intersect( $pool1_int, $pool2_int );
		
		if ( ! empty( $duplicates ) ) {
			wc_add_notice( 'Nu poți selecta aceeași variație în ambele pool-uri.', 'error' );
			return false;
		}
	}
	
	// Validare că toate selecțiile sunt din pool-urile configurate
	$pool1_valid_ids = oc_pool_resolve_variation_ids(
		wc_get_product( $config['pool1_id'] )->get_available_variations(),
		$config['pool1_variations'] ?: []
	);
	$pool2_valid_ids = oc_pool_resolve_variation_ids(
		wc_get_product( $config['pool2_id'] )->get_available_variations(),
		$config['pool2_variations'] ?: []
	);
	
	
	foreach ( $pool1_selections as $selection ) {
		if ( ! in_array( (int) $selection, array_map( 'intval', $pool1_valid_ids ), true ) ) {
			wc_add_notice( 'Selecție invalidă pentru primul pool.', 'error' );
			return false;
		}
	}
	
	foreach ( $pool2_selections as $selection ) {
		if ( ! in_array( (int) $selection, array_map( 'intval', $pool2_valid_ids ), true ) ) {
			wc_add_notice( 'Selecție invalidă pentru al doilea pool.', 'error' );
			return false;
		}
	}
	
	return $passed;
}

/**
 * Adaugă cart item data pentru DUAL MODE
 *
 * @param array $cart_item_data
 * @param int $product_id
 * @param int $variation_id
 * @return array
 */
function oc_pool_add_dual_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
	$pool1_selections = isset( $_POST['oc_pool_pool1_selections'] ) ? array_filter( (array) $_POST['oc_pool_pool1_selections'] ) : [];
	$pool2_selections = isset( $_POST['oc_pool_pool2_selections'] ) ? array_filter( (array) $_POST['oc_pool_pool2_selections'] ) : [];
	
	$config = oc_pool_get_package_config( $product_id );
	$pool1_id = $config['pool1_id'];
	$pool2_id = $config['pool2_id'];
	$pool1_label = $config['pool1_label'] ?: 'Prima selecție:';
	$pool2_label = $config['pool2_label'] ?: 'A doua selecție:';
	
	if ( ! empty( $pool1_selections ) && ! empty( $pool2_selections ) && $pool1_id && $pool2_id ) {
		// Creează slots pentru ambele pool-uri
		$slots = [];
		
		// Pool 1 slots
		foreach ( $pool1_selections as $i => $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$slots[] = [
					'pool' => 1,
					'pool_label' => $pool1_label,
					'slot' => $i + 1,
					'variation_id' => $variation_id,
					'label' => wc_get_formatted_variation( $variation, true, false ),
					'attributes' => $variation->get_attributes()
				];
			}
		}
		
		// Pool 2 slots
		foreach ( $pool2_selections as $i => $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$slots[] = [
					'pool' => 2,
					'pool_label' => $pool2_label,
					'slot' => $i + 1,
					'variation_id' => $variation_id,
					'label' => wc_get_formatted_variation( $variation, true, false ),
					'attributes' => $variation->get_attributes()
				];
			}
		}
		
		$cart_item_data['oc_pool'] = [
			'dual_mode' => true,
			'pool1_id' => $pool1_id,
			'pool2_id' => $pool2_id,
			'pool1_selections' => array_map( 'intval', $pool1_selections ),
			'pool2_selections' => array_map( 'intval', $pool2_selections ),
			'pool1_label' => $pool1_label,
			'pool2_label' => $pool2_label,
			'is_package' => true,
			'slots' => $slots
		];
	}
	
	return $cart_item_data;
}

?>
