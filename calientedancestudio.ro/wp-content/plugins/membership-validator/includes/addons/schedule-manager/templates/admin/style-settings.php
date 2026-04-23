<?php
/**
 * Style settings page template
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = OC_Settings::get_all();
$background_mode = isset($settings['background_mode']) ? $settings['background_mode'] : 'gradient';
if (!in_array($background_mode, ['gradient', 'image'], true)) {
    $background_mode = 'gradient';
}
$background_image = isset($settings['background_image']) ? $settings['background_image'] : '';
$background_image_mobile = isset($settings['background_image_mobile']) ? $settings['background_image_mobile'] : '';
$background_image_opacity = isset($settings['background_image_opacity']) ? (float) $settings['background_image_opacity'] : 1.0;
if ($background_image_opacity < 0) {
    $background_image_opacity = 0;
}
if ($background_image_opacity > 1) {
    $background_image_opacity = 1;
}
$background_image_opacity_percent = (int) round($background_image_opacity * 100);
$background_image_size_desktop = isset($settings['background_image_size_desktop']) ? (float) $settings['background_image_size_desktop'] : 100.0;
if ($background_image_size_desktop < 50) {
    $background_image_size_desktop = 50;
}
if ($background_image_size_desktop > 200) {
    $background_image_size_desktop = 200;
}
$background_image_size_desktop_percent = (int) round($background_image_size_desktop);
$background_image_size_mobile = isset($settings['background_image_size_mobile']) ? (float) $settings['background_image_size_mobile'] : 100.0;
if ($background_image_size_mobile < 50) {
    $background_image_size_mobile = 50;
}
if ($background_image_size_mobile > 200) {
    $background_image_size_mobile = 200;
}
$background_image_size_mobile_percent = (int) round($background_image_size_mobile);
$background_image_mobile_behavior = isset($settings['background_image_mobile_behavior']) ? sanitize_key($settings['background_image_mobile_behavior']) : 'cover';
if (!in_array($background_image_mobile_behavior, ['cover', 'repeat'], true)) {
    $background_image_mobile_behavior = 'cover';
}
$selected_product = absint(get_option('oc_selected_product', 0));
$course_variations = [];
$saved_course_color_map = get_option('oc_course_color_map', []);

if ($selected_product > 0) {
    $woocommerce = new OC_WooCommerce();
    $course_variations = $woocommerce->get_product_variations($selected_product);
}

if (!is_array($saved_course_color_map)) {
    $saved_course_color_map = [];
}
?>

<div class="wrap oc-style-wrap">
    <?php if (isset($_GET['oc_style_saved']) && '1' === $_GET['oc_style_saved']) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Setările de aspect au fost salvate.', OC_TEXT_DOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <h1 class="wp-heading-inline">
        <?php esc_html_e('Setări Aspect - Orar Cursuri', OC_TEXT_DOMAIN); ?>
    </h1>
    
    <p class="description">
        <?php esc_html_e('Configurezi aici toate culorile, fonturile, logo-ul și fundalul (gradient sau imagine) pentru orar. Schimbările se văd în previzualizare, iar pe site după Salvare Setări.', OC_TEXT_DOMAIN); ?>
    </p>
    
    <form method="post" action="" class="oc-style-form">
        <?php wp_nonce_field('oc_save_style_settings', 'oc_style_nonce'); ?>
        
        <div class="oc-style-content">
            <!-- Color Settings -->
            <div class="oc-section oc-colors-section">
                <h2><?php esc_html_e('Setări Culori', OC_TEXT_DOMAIN); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oc_primary_color"><?php esc_html_e('Culoare Primară', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_primary_color" name="oc_primary_color" 
                                   value="<?php echo esc_attr($settings['primary_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#d48945">
                            <p class="description"><?php esc_html_e('Controlează culoarea principală de accent (ex: badge-uri, accente vizuale, elemente evidențiate). Format: HEX sau RGBA (ex: #d48945 sau rgba(212,137,69,0.85)).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_title_color"><?php esc_html_e('Culoare Titlu', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_title_color" name="oc_title_color" 
                                   value="<?php echo esc_attr($settings['title_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#5d473d">
                            <p class="description"><?php esc_html_e('Culoarea titlului mare al orarului (H2 din header). Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_text_color"><?php esc_html_e('Culoare Text', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_text_color" name="oc_text_color" 
                                   value="<?php echo esc_attr($settings['text_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#5f4a40">
                            <p class="description"><?php esc_html_e('Culoarea textului principal din tabel/carduri (ore, denumiri, conținut). Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_secondary_color"><?php esc_html_e('Culoare Secundară', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_secondary_color" name="oc_secondary_color" 
                                   value="<?php echo esc_attr($settings['secondary_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#8d786b">
                            <p class="description"><?php esc_html_e('Culoarea textului secundar (label-uri, detalii, elemente auxiliare). Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_background_color"><?php esc_html_e('Culoare Fundal', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_background_color" name="oc_background_color" 
                                   value="<?php echo esc_attr($settings['background_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#f7eee8">
                            <p class="description"><?php esc_html_e('Culoarea de bază a fundalului pentru wrapper-ul orarului. Dacă gradientul este activ, aceasta rămâne culoarea de fallback.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_muted_color"><?php esc_html_e('Culoare Muted', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_muted_color" name="oc_muted_color" 
                                   value="<?php echo esc_attr($settings['muted_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#f5ece5">
                            <p class="description"><?php esc_html_e('Culoarea pentru zonele neutre: header tabel, rânduri alternate, fundaluri discrete. Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_border_color"><?php esc_html_e('Culoare Borduri', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_border_color" name="oc_border_color" 
                                   value="<?php echo esc_attr($settings['border_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#e3d5c9">
                            <p class="description"><?php esc_html_e('Culoarea bordurilor, separatorilor și contururilor din orar. Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Typography Settings -->
            <div class="oc-section oc-typography-section">
                <h2><?php esc_html_e('Setări Tipografie', OC_TEXT_DOMAIN); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oc_font_family"><?php esc_html_e('Familie Font', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_font_family" name="oc_font_family" class="regular-text">
                                <?php foreach (OC_Admin::get_font_family_options() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" 
                                            <?php selected($settings['font_family'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Setează familia de font pentru tot conținutul orarului (desktop + mobile).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_font_size"><?php esc_html_e('Mărime Font', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_font_size" name="oc_font_size" 
                                   value="<?php echo esc_attr($settings['font_size']); ?>" 
                                   class="small-text" placeholder="14px">
                            <p class="description"><?php esc_html_e('Dimensiunea textului de bază din orar. Acceptă unități CSS: px, rem, em, % (ex: 15px).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_header_font_size"><?php esc_html_e('Mărime Font Titlu', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_header_font_size" name="oc_header_font_size" 
                                   value="<?php echo esc_attr($settings['header_font_size']); ?>" 
                                   class="small-text" placeholder="30px">
                            <p class="description"><?php esc_html_e('Dimensiunea titlului principal al orarului. Acceptă unități CSS (ex: 30px).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Layout Settings -->
            <div class="oc-section oc-layout-section">
                <h2><?php esc_html_e('Setări Layout', OC_TEXT_DOMAIN); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oc_border_radius"><?php esc_html_e('Raza Colțurilor', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_border_radius" name="oc_border_radius" 
                                   value="<?php echo esc_attr($settings['border_radius']); ?>" 
                                   class="small-text" placeholder="16px">
                            <p class="description"><?php esc_html_e('Controlează rotunjirea colțurilor pentru carduri și containere. Acceptă unități CSS (ex: 16px).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Per-course Color Mapping -->
            <div class="oc-section oc-course-colors-section">
                <h2><?php esc_html_e('Culori per Curs', OC_TEXT_DOMAIN); ?></h2>

                <?php if (empty($selected_product)) : ?>
                    <p class="description"><?php esc_html_e('Selectează mai întâi un produs în tab-ul Schedule pentru a configura culorile pe curs.', OC_TEXT_DOMAIN); ?></p>
                <?php elseif (empty($course_variations)) : ?>
                    <p class="description"><?php esc_html_e('Nu există variații disponibile pentru produsul selectat.', OC_TEXT_DOMAIN); ?></p>
                <?php else : ?>
                    <p class="description"><?php esc_html_e('Mapare explicită per curs: alegi Fundal Pill și Text Pill pentru fiecare variație. Dacă Text Pill rămâne gol, pluginul calculează automat o culoare de contrast (închis/deschis).', OC_TEXT_DOMAIN); ?></p>

                    <table class="widefat striped" style="max-width: 900px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Curs', OC_TEXT_DOMAIN); ?></th>
                                <th style="width: 220px;"><?php esc_html_e('Fundal Pill', OC_TEXT_DOMAIN); ?></th>
                                <th style="width: 220px;"><?php esc_html_e('Text Pill', OC_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_variations as $variation) : ?>
                                <?php
                                $variation_id = absint($variation['id'] ?? 0);
                                if ($variation_id <= 0) {
                                    continue;
                                }
                                $saved_bg = $saved_course_color_map[$variation_id]['bg'] ?? '';
                                $saved_text = $saved_course_color_map[$variation_id]['text'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($variation['name'] ?? ('#' . $variation_id)); ?></strong>
                                        <div class="description">ID: <?php echo esc_html($variation_id); ?></div>
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            class="oc-color-picker"
                                            name="oc_course_colors[<?php echo esc_attr($variation_id); ?>][bg]"
                                            value="<?php echo esc_attr($saved_bg); ?>"
                                            data-default-color="<?php echo esc_attr($settings['muted_color']); ?>"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            class="oc-color-picker"
                                            name="oc_course_colors[<?php echo esc_attr($variation_id); ?>][text]"
                                            value="<?php echo esc_attr($saved_text); ?>"
                                            data-default-color="<?php echo esc_attr($settings['text_color']); ?>"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Logo Settings -->
            <div class="oc-section oc-logo-section">
                <h2><?php esc_html_e('Logo și Titlu', OC_TEXT_DOMAIN); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oc_logo_image"><?php esc_html_e('Logo', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-logo-upload">
                                <input type="url" id="oc_logo_image" name="oc_logo_image" 
                                       value="<?php echo esc_attr($settings['logo_image']); ?>" 
                                       class="regular-text" placeholder="https://">
                                <button type="button" class="button oc-upload-logo">
                                    <?php esc_html_e('Încarcă Logo', OC_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="button oc-remove-logo">
                                    <?php esc_html_e('Elimină', OC_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                            <div class="oc-logo-preview" id="oc_logo_preview">
                                <?php if (!empty($settings['logo_image'])): ?>
                                    <img src="<?php echo esc_url($settings['logo_image']); ?>" alt="Logo Preview" style="max-width: 100px; max-height: 50px;">
                                <?php endif; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Imaginea logo afișată în header-ul orarului, lângă titlu.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_logo_width"><?php esc_html_e('Lățime Logo', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_logo_width" name="oc_logo_width" 
                                   value="<?php echo esc_attr($settings['logo_width']); ?>" 
                                   class="small-text" placeholder="50px">
                            <p class="description"><?php esc_html_e('Lățimea logo-ului. Acceptă unități CSS (ex: 50px, 3rem, auto).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_logo_height"><?php esc_html_e('Înălțime Logo', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_logo_height" name="oc_logo_height" 
                                   value="<?php echo esc_attr($settings['logo_height']); ?>" 
                                   class="small-text" placeholder="auto">
                            <p class="description"><?php esc_html_e('Înălțimea logo-ului. Acceptă unități CSS (ex: 40px, 2rem, auto).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_logo_position"><?php esc_html_e('Poziția Logo', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_logo_position" name="oc_logo_position">
                                <option value="left" <?php selected($settings['logo_position'], 'left'); ?>>
                                    <?php esc_html_e('Stânga titlului', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="right" <?php selected($settings['logo_position'], 'right'); ?>>
                                    <?php esc_html_e('Dreapta titlului', OC_TEXT_DOMAIN); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Poziționează logo-ul în stânga sau dreapta titlului din header.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Background Gradient -->
            <div class="oc-section oc-gradient-section">
                <h2><?php esc_html_e('Gradient de Fundal', OC_TEXT_DOMAIN); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oc_background_mode"><?php esc_html_e('Tip Fundal', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_background_mode" name="oc_background_mode">
                                <option value="gradient" <?php selected($background_mode, 'gradient'); ?>>
                                    <?php esc_html_e('Gradient de Fundal', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="image" <?php selected($background_mode, 'image'); ?>>
                                    <?php esc_html_e('Imagine de Fundal', OC_TEXT_DOMAIN); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Alegi un singur fundal pentru wrapper: Gradient sau Imagine. Varianta nealeasă nu se aplică.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image"><?php esc_html_e('Imagine Fundal', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-logo-upload">
                                <input type="url" id="oc_background_image" name="oc_background_image"
                                       value="<?php echo esc_attr($background_image); ?>"
                                       class="regular-text" placeholder="https://">
                                <button type="button" class="button oc-upload-image" data-target="oc_background_image">
                                    <?php esc_html_e('Alege Imagine', OC_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="button oc-remove-image" data-target="oc_background_image">
                                    <?php esc_html_e('Elimină', OC_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                            <div class="oc-logo-preview" id="oc_background_image_preview_wrap">
                                <img
                                    id="oc_background_image_preview"
                                    src="<?php echo esc_url($background_image); ?>"
                                    alt="Background Preview"
                                    style="max-width: 220px; max-height: 120px; border-radius: 8px; <?php echo empty($background_image) ? 'display:none;' : ''; ?>"
                                >
                            </div>
                            <p class="description"><?php esc_html_e('Imaginea de fundal pentru wrapper. Colțurile rămân rotunjite conform setării Raza Colțurilor.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image_mobile"><?php esc_html_e('Imagine Fundal Mobil', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-logo-upload">
                                <input type="url" id="oc_background_image_mobile" name="oc_background_image_mobile"
                                       value="<?php echo esc_attr($background_image_mobile); ?>"
                                       class="regular-text" placeholder="https://">
                                <button type="button" class="button oc-upload-image" data-target="oc_background_image_mobile">
                                    <?php esc_html_e('Alege Imagine', OC_TEXT_DOMAIN); ?>
                                </button>
                                <button type="button" class="button oc-remove-image" data-target="oc_background_image_mobile">
                                    <?php esc_html_e('Elimină', OC_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                            <div class="oc-logo-preview" id="oc_background_image_mobile_preview_wrap">
                                <img
                                    id="oc_background_image_mobile_preview"
                                    src="<?php echo esc_url($background_image_mobile); ?>"
                                    alt="Mobile Background Preview"
                                    style="max-width: 220px; max-height: 120px; border-radius: 8px; <?php echo empty($background_image_mobile) ? 'display:none;' : ''; ?>"
                                >
                            </div>
                            <p class="description"><?php esc_html_e('Opțional: imagine separată doar pentru mobil. Recomandat format portret, pentru Cover fără tăieri agresive.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image_opacity"><?php esc_html_e('Opacitate Imagine', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-alpha-control" style="max-width:420px;">
                                <input
                                    type="range"
                                    id="oc_background_image_opacity"
                                    name="oc_background_image_opacity"
                                    class="oc-alpha-slider"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    value="<?php echo esc_attr($background_image_opacity); ?>"
                                >
                                <span class="oc-alpha-value" id="oc_background_image_opacity_value"><?php echo esc_html($background_image_opacity_percent); ?>%</span>
                            </div>
                            <p class="description"><?php esc_html_e('Reglează transparența imaginii de fundal (0% = invizibilă, 100% = complet vizibilă).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image_size_desktop"><?php esc_html_e('Dimensiune Imagine Desktop', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-alpha-control" style="max-width:420px;">
                                <input
                                    type="range"
                                    id="oc_background_image_size_desktop"
                                    name="oc_background_image_size_desktop"
                                    class="oc-alpha-slider"
                                    min="50"
                                    max="200"
                                    step="1"
                                    value="<?php echo esc_attr($background_image_size_desktop); ?>"
                                >
                                <span class="oc-alpha-value" id="oc_background_image_size_desktop_value"><?php echo esc_html($background_image_size_desktop_percent); ?>%</span>
                            </div>
                            <p class="description"><?php esc_html_e('Scalare separată pentru desktop (50% - 200%).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image_size_mobile"><?php esc_html_e('Dimensiune Imagine Mobil', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-alpha-control" style="max-width:420px;">
                                <input
                                    type="range"
                                    id="oc_background_image_size_mobile"
                                    name="oc_background_image_size_mobile"
                                    class="oc-alpha-slider"
                                    min="50"
                                    max="200"
                                    step="1"
                                    value="<?php echo esc_attr($background_image_size_mobile); ?>"
                                >
                                <span class="oc-alpha-value" id="oc_background_image_size_mobile_value"><?php echo esc_html($background_image_size_mobile_percent); ?>%</span>
                            </div>
                            <p class="description"><?php esc_html_e('Scalare separată pentru mobil (50% - 200%).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>

                    <tr class="oc-background-image-row">
                        <th scope="row">
                            <label for="oc_background_image_mobile_behavior"><?php esc_html_e('Comportament Imagine pe Mobil', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_background_image_mobile_behavior" name="oc_background_image_mobile_behavior">
                                <option value="cover" <?php selected($background_image_mobile_behavior, 'cover'); ?>>
                                    <?php esc_html_e('Acoperă (cover, fără repetare)', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="repeat" <?php selected($background_image_mobile_behavior, 'repeat'); ?>>
                                    <?php esc_html_e('Repetă modelul (repeat)', OC_TEXT_DOMAIN); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Pe ecrane mici: Cover pentru imagini foto. Repeat pentru pattern-uri fără cusături pe secțiuni lungi.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="oc-gradient-row">
                        <th scope="row">
                            <label for="oc_gradient_start"><?php esc_html_e('Culoare Start', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_gradient_start" name="oc_gradient_start" 
                                   value="<?php echo esc_attr($settings['gradient_start']); ?>" 
                                   class="oc-color-picker" data-default-color="#ff7a3d">
                            <p class="description"><?php esc_html_e('Prima culoare a gradientului (punctul de start). Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="oc-gradient-row">
                        <th scope="row">
                            <label for="oc_gradient_end"><?php esc_html_e('Culoare Final', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_gradient_end" name="oc_gradient_end" 
                                   value="<?php echo esc_attr($settings['gradient_end']); ?>" 
                                   class="oc-color-picker" data-default-color="#ffd08a">
                            <p class="description"><?php esc_html_e('A doua culoare a gradientului (punctul de final). Format: HEX sau RGBA.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="oc-gradient-row">
                        <th scope="row">
                            <label for="oc_gradient_direction"><?php esc_html_e('Direcție Gradient', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_gradient_direction" name="oc_gradient_direction">
                                <option value="132deg" <?php selected($settings['gradient_direction'], '132deg'); ?>>
                                    <?php esc_html_e('Diagonal Caliente (132deg)', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="135deg" <?php selected($settings['gradient_direction'], '135deg'); ?>>
                                    <?php esc_html_e('Diagonal (stânga-sus → dreapta-jos)', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="90deg" <?php selected($settings['gradient_direction'], '90deg'); ?>>
                                    <?php esc_html_e('Vertical (sus → jos)', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="0deg" <?php selected($settings['gradient_direction'], '0deg'); ?>>
                                    <?php esc_html_e('Orizontal (stânga → dreapta)', OC_TEXT_DOMAIN); ?>
                                </option>
                                <option value="45deg" <?php selected($settings['gradient_direction'], '45deg'); ?>>
                                    <?php esc_html_e('Diagonal (stânga-jos → dreapta-sus)', OC_TEXT_DOMAIN); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Unghiul/direcția gradientului pe fundalul orarului.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="oc-gradient-row">
                        <th scope="row">
                            <label><?php esc_html_e('Preview Gradient', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-gradient-preview" id="oc_gradient_preview"></div>
                            <p class="description"><?php esc_html_e('Previzualizare live pentru combinația Start/Final + Direcție.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            

            <!-- Live Preview -->
            <div class="oc-section oc-preview-section">
                <h2><?php esc_html_e('Previzualizare Live', OC_TEXT_DOMAIN); ?></h2>
                
                <div class="oc-preview-container">
                    <div class="admin-preview-controls">
                        <div class="admin-view-toggle">
                            <button class="admin-view-btn active" data-view="desktop">🖥️ Desktop</button>
                            <button class="admin-view-btn" data-view="mobile">📱 Mobile</button>
                        </div>
                    </div>
                    
                    <div class="oc-preview-content admin-preview" id="admin-schedule-preview">
                        <div class="oc-loading-preview" style="text-align: center; padding: 40px; color: #666;">
                            <em>Se încarcă previzualizarea...</em>
                        </div>
                    </div>
                </div>
                
                <p class="description">
                    <?php esc_html_e('Previzualizarea se actualizează în admin pe măsură ce modifici opțiunile. Pentru frontend (site live), apasă Salvează Setările.', OC_TEXT_DOMAIN); ?>
                </p>
            </div>
        </div>
        
        <!-- Submit Button -->
        <p class="submit">
            <input type="submit" name="oc_save_style_settings" 
                   class="button-primary" value="<?php esc_attr_e('Salvează Setările', OC_TEXT_DOMAIN); ?>">
            
            <button type="button" class="button oc-reset-settings" 
                    data-confirm="<?php esc_attr_e('Ești sigur că vrei să resetezi toate setările la valorile implicite?', OC_TEXT_DOMAIN); ?>">
                <?php esc_html_e('Resetează la Implicit', OC_TEXT_DOMAIN); ?>
            </button>
            
            <button type="button" class="button oc-export-settings">
                <?php esc_html_e('Exportă Setările', OC_TEXT_DOMAIN); ?>
            </button>
            
            <button type="button" class="button oc-import-settings">
                <?php esc_html_e('Importă Setările', OC_TEXT_DOMAIN); ?>
            </button>
        </p>
    </form>
</div>

<!-- Import Modal -->
<div id="oc-import-modal" class="oc-modal" style="display: none;">
    <div class="oc-modal-content">
        <div class="oc-modal-header">
            <h3><?php esc_html_e('Importă Setări', OC_TEXT_DOMAIN); ?></h3>
            <button type="button" class="oc-modal-close">&times;</button>
        </div>
        <div class="oc-modal-body">
            <p><?php esc_html_e('Încarcă un fișier JSON cu setările de aspect:', OC_TEXT_DOMAIN); ?></p>
            <input type="file" id="oc-import-file" accept=".json">
            <div class="oc-import-result"></div>
        </div>
        <div class="oc-modal-footer">
            <button type="button" class="button button-primary oc-import-confirm">
                <?php esc_html_e('Importă', OC_TEXT_DOMAIN); ?>
            </button>
            <button type="button" class="button oc-modal-cancel">
                <?php esc_html_e('Anulează', OC_TEXT_DOMAIN); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    window.ocToggleBackgroundFields = function() {
        const mode = $('#oc_background_mode').val() || 'gradient';
        $('.oc-gradient-row').toggle(mode === 'gradient');
        $('.oc-background-image-row').toggle(mode === 'image');
    };

    function clamp(num, min, max) {
        return Math.max(min, Math.min(max, num));
    }

    function rgbToHex(r, g, b) {
        const toHex = function(n) {
            const h = clamp(parseInt(n, 10), 0, 255).toString(16);
            return h.length === 1 ? ('0' + h) : h;
        };
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    function parseCssColor(value) {
        if (!value) {
            return null;
        }

        const v = String(value).trim();
        if (v.toLowerCase() === 'transparent') {
            return { r: 0, g: 0, b: 0, a: 0 };
        }

        let m = v.match(/^#([0-9a-f]{3})$/i);
        if (m) {
            const h = m[1];
            return {
                r: parseInt(h.charAt(0) + h.charAt(0), 16),
                g: parseInt(h.charAt(1) + h.charAt(1), 16),
                b: parseInt(h.charAt(2) + h.charAt(2), 16),
                a: 1
            };
        }

        m = v.match(/^#([0-9a-f]{6})$/i);
        if (m) {
            const h = m[1];
            return {
                r: parseInt(h.substring(0, 2), 16),
                g: parseInt(h.substring(2, 4), 16),
                b: parseInt(h.substring(4, 6), 16),
                a: 1
            };
        }

        m = v.match(/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(0|0?\.[0-9]+|1(?:\.0+)?)\s*)?\)$/i);
        if (m) {
            return {
                r: clamp(parseInt(m[1], 10), 0, 255),
                g: clamp(parseInt(m[2], 10), 0, 255),
                b: clamp(parseInt(m[3], 10), 0, 255),
                a: m[4] !== undefined ? clamp(parseFloat(m[4]), 0, 1) : 1
            };
        }

        return null;
    }

    function getOpacityControlForInput($input) {
        const $pickerContainer = $input.closest('.wp-picker-container');
        if ($pickerContainer.length) {
            return $pickerContainer.next('.oc-alpha-control');
        }
        return $input.nextAll('.oc-alpha-control').first();
    }

    function syncOpacityFromInput($input) {
        const parsed = parseCssColor($input.val()) || parseCssColor($input.data('default-color'));
        if (!parsed) {
            return;
        }

        const alphaFromState = $input.data('ocAlpha');
        const alpha = Math.round((alphaFromState !== undefined ? alphaFromState : (parsed.a ?? 1)) * 100);
        const $control = getOpacityControlForInput($input);
        if ($control.length) {
            $control.find('.oc-alpha-slider').val(alpha);
            $control.find('.oc-alpha-value').text(alpha + '%');
        }
    }

    function applyAlphaToInput($input, alphaPercent) {
        const alpha = clamp(alphaPercent, 0, 100) / 100;
        $input.data('ocAlpha', alpha);
        $input.trigger('change');
        const $control = getOpacityControlForInput($input);
        if ($control.length) {
            $control.find('.oc-alpha-value').text(Math.round(alpha * 100) + '%');
        }
    }

    function normalizePickerValuesFromSavedColors() {
        $('.oc-color-picker').each(function() {
            const $input = $(this);
            const parsed = parseCssColor($input.val()) || parseCssColor($input.data('default-color'));
            if (!parsed) {
                $input.data('ocAlpha', 1);
                return;
            }

            $input.data('ocAlpha', parsed.a ?? 1);
            $input.val(rgbToHex(parsed.r, parsed.g, parsed.b));
        });
    }

    function composeColorForSave($input) {
        const parsed = parseCssColor($input.val()) || parseCssColor($input.data('default-color'));
        if (!parsed) {
            return;
        }

        const alphaFromState = $input.data('ocAlpha');
        const alpha = alphaFromState !== undefined ? clamp(parseFloat(alphaFromState), 0, 1) : 1;
        if (alpha >= 0.999) {
            $input.val(rgbToHex(parsed.r, parsed.g, parsed.b));
            return;
        }

        const alphaText = alpha.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        $input.val('rgba(' + parsed.r + ', ' + parsed.g + ', ' + parsed.b + ', ' + alphaText + ')');
    }

    function getBackgroundImageOpacity() {
        const raw = parseFloat($('#oc_background_image_opacity').val());
        if (isNaN(raw)) {
            return 1;
        }
        return clamp(raw, 0, 1);
    }

    function updateBackgroundImageOpacityLabel() {
        const opacity = getBackgroundImageOpacity();
        $('#oc_background_image_opacity_value').text(Math.round(opacity * 100) + '%');
    }

    function getBackgroundImageSizePercent(selector, fallback) {
        const raw = parseFloat($(selector).val());
        if (isNaN(raw)) {
            return fallback;
        }
        return clamp(raw, 50, 200);
    }

    function updateBackgroundImageSizeLabels() {
        const desktopSize = getBackgroundImageSizePercent('#oc_background_image_size_desktop', 100);
        const mobileSize = getBackgroundImageSizePercent('#oc_background_image_size_mobile', 100);
        $('#oc_background_image_size_desktop_value').text(Math.round(desktopSize) + '%');
        $('#oc_background_image_size_mobile_value').text(Math.round(mobileSize) + '%');
    }

    normalizePickerValuesFromSavedColors();

    // Initialize color pickers after normalizing rgba values.
    $('.oc-color-picker').wpColorPicker();
    updateBackgroundImageOpacityLabel();
    updateBackgroundImageSizeLabels();

    function initOpacityControls() {
        $('.oc-color-picker').each(function() {
            const $input = $(this);
            if ($input.data('ocOpacityInit')) {
                return;
            }

            const inputId = $input.attr('id') || ('oc-color-' + Math.random().toString(36).slice(2));
            const sliderId = inputId + '-alpha';
            const parsed = parseCssColor($input.val()) || parseCssColor($input.data('default-color')) || { r: 212, g: 137, b: 69, a: 1 };
            const alphaFromState = $input.data('ocAlpha');
            const alpha = Math.round((alphaFromState !== undefined ? alphaFromState : (parsed.a ?? 1)) * 100);

            const $control = $('<div class="oc-alpha-control"><label for="' + sliderId + '">Opacitate</label><input type="range" id="' + sliderId + '" class="oc-alpha-slider" min="0" max="100" step="1" value="' + alpha + '"><span class="oc-alpha-value">' + alpha + '%</span></div>');

            const $pickerContainer = $input.closest('.wp-picker-container');
            if ($pickerContainer.length) {
                $pickerContainer.after($control);
            } else {
                $input.after($control);
            }

            $input.data('ocOpacityInit', true);
        });
    }

    initOpacityControls();

    $(document).on('input change', '.oc-alpha-slider', function() {
        const $control = $(this).closest('.oc-alpha-control');
        const $input = $control.prevAll('.wp-picker-container').first().find('.oc-color-picker').first();
        const $target = $input.length ? $input : $control.prevAll('.oc-color-picker').first();
        if (!$target.length) {
            return;
        }

        applyAlphaToInput($target, parseInt($(this).val(), 10) || 0);
    });

    $(document).on('change input', '.oc-color-picker', function() {
        syncOpacityFromInput($(this));
    });

    $('#oc_background_image_opacity').on('input change', function() {
        updateBackgroundImageOpacityLabel();
        if (typeof window.ocUpdatePreviewStyles === 'function') {
            window.ocUpdatePreviewStyles();
        }
    });

    $('#oc_background_image_size_desktop, #oc_background_image_size_mobile').on('input change', function() {
        updateBackgroundImageSizeLabels();
        if (typeof window.ocUpdatePreviewStyles === 'function') {
            window.ocUpdatePreviewStyles();
        }
    });

    $('.oc-style-form').on('submit', function() {
        $('.oc-color-picker').each(function() {
            composeColorForSave($(this));
        });
    });
    
    // Media uploader for background images
    $('.oc-upload-image').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const targetInput = $('#' + button.data('target'));
        const previewImg = $('#' + button.data('target') + '_preview');
        
        const mediaUploader = wp.media({
            title: 'Selectează Imaginea de Fundal',
            button: {
                text: 'Utilizează această imagine'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            targetInput.val(attachment.url);
            if (previewImg.length) {
                previewImg.attr('src', attachment.url).show();
            }
            targetInput.trigger('change');
        });
        
        mediaUploader.open();
    });
    
    // Remove image functionality
    $('.oc-remove-image').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const targetInput = $('#' + button.data('target'));
        const previewImg = $('#' + button.data('target') + '_preview');
        
        targetInput.val('');
        if (previewImg.length) {
            previewImg.hide();
        }
        targetInput.trigger('change');
    });
    
    // Reset settings functionality
    $('.oc-reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (confirm($(this).data('confirm'))) {
            // Reset all form fields to defaults
            $('#oc_primary_color').val('#d48945').wpColorPicker('color', '#d48945');
            $('#oc_title_color').val('#5d473d').wpColorPicker('color', '#5d473d');
            $('#oc_text_color').val('#5f4a40').wpColorPicker('color', '#5f4a40');
            $('#oc_secondary_color').val('#8d786b').wpColorPicker('color', '#8d786b');
            $('#oc_background_color').val('#f7eee8').wpColorPicker('color', '#f7eee8');
            $('#oc_muted_color').val('#f5ece5').wpColorPicker('color', '#f5ece5');
            $('#oc_border_color').val('#e3d5c9').wpColorPicker('color', '#e3d5c9');
            $('#oc_font_family').val('Segoe UI, Roboto, Arial, sans-serif');
            $('#oc_font_size').val('15px');
            $('#oc_header_font_size').val('30px');
            $('#oc_border_radius').val('16px');
            $('#oc_gradient_start').val('#ff7a3d').wpColorPicker('color', '#ff7a3d');
            $('#oc_gradient_end').val('#ffd08a').wpColorPicker('color', '#ffd08a');
            $('#oc_gradient_direction').val('132deg');
            $('#oc_background_mode').val('gradient');
            $('#oc_background_image').val('');
            $('#oc_background_image_mobile').val('');
            $('#oc_background_image_opacity').val('1');
            $('#oc_background_image_size_desktop').val('100');
            $('#oc_background_image_size_mobile').val('100');
            $('#oc_background_image_mobile_behavior').val('cover');
            $('#oc_background_image_preview').hide();
            $('#oc_background_image_mobile_preview').hide();
            
            if (typeof window.ocToggleBackgroundFields === 'function') {
                window.ocToggleBackgroundFields();
            }
            if (typeof window.ocUpdatePreviewStyles === 'function') {
                window.ocUpdatePreviewStyles();
            }
            updateBackgroundImageOpacityLabel();
            updateBackgroundImageSizeLabels();
            $('.oc-color-picker').each(function() {
                $(this).data('ocAlpha', 1);
            });
            $('.oc-color-picker').each(function() {
                syncOpacityFromInput($(this));
            });
        }
    });
    
    // Export settings
    $('.oc-export-settings').on('click', function(e) {
        e.preventDefault();

        const getComposed = function(selector) {
            const $input = $(selector);
            const parsed = parseCssColor($input.val()) || parseCssColor($input.data('default-color'));
            if (!parsed) {
                return $input.val();
            }
            const alpha = $input.data('ocAlpha');
            if (alpha === undefined || alpha >= 0.999) {
                return rgbToHex(parsed.r, parsed.g, parsed.b);
            }
            const alphaText = clamp(parseFloat(alpha), 0, 1).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            return 'rgba(' + parsed.r + ', ' + parsed.g + ', ' + parsed.b + ', ' + alphaText + ')';
        };
        
        const settings = {
            primary_color: getComposed('#oc_primary_color'),
            text_color: getComposed('#oc_text_color'),
            secondary_color: getComposed('#oc_secondary_color'),
            background_color: getComposed('#oc_background_color'),
            muted_color: getComposed('#oc_muted_color'),
            border_color: getComposed('#oc_border_color'),
            font_family: $('#oc_font_family').val(),
            font_size: $('#oc_font_size').val(),
            header_font_size: $('#oc_header_font_size').val(),
            border_radius: $('#oc_border_radius').val(),
            background_mode: $('#oc_background_mode').val(),
            background_image: $('#oc_background_image').val(),
            background_image_mobile: $('#oc_background_image_mobile').val(),
            background_image_opacity: $('#oc_background_image_opacity').val(),
            background_image_size_desktop: $('#oc_background_image_size_desktop').val(),
            background_image_size_mobile: $('#oc_background_image_size_mobile').val(),
            background_image_mobile_behavior: $('#oc_background_image_mobile_behavior').val(),
            gradient_start: getComposed('#oc_gradient_start'),
            gradient_end: getComposed('#oc_gradient_end'),
            gradient_direction: $('#oc_gradient_direction').val()
        };
        
        const dataStr = JSON.stringify(settings, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'orar-cursuri-settings.json';
        link.click();
    });
    
    // Import settings modal
    $('.oc-import-settings').on('click', function(e) {
        e.preventDefault();
        $('#oc-import-modal').show();
    });
    
    $('.oc-modal-close, .oc-modal-cancel').on('click', function() {
        $('#oc-import-modal').hide();
    });
    
    $('.oc-import-confirm').on('click', function() {
        const fileInput = $('#oc-import-file')[0];
        if (fileInput.files.length === 0) {
            alert('Te rugăm să selectezi un fișier JSON.');
            return;
        }
        
        const file = fileInput.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                
                // Apply imported settings
                Object.keys(settings).forEach(function(key) {
                    const input = $('#oc_' + key);
                    if (input.length) {
                        if (input.hasClass('oc-color-picker')) {
                            const parsed = parseCssColor(settings[key]);
                            if (parsed) {
                                input.data('ocAlpha', parsed.a ?? 1);
                                input.val(rgbToHex(parsed.r, parsed.g, parsed.b)).wpColorPicker('color', rgbToHex(parsed.r, parsed.g, parsed.b));
                            }
                        } else {
                            input.val(settings[key]);
                        }
                    }
                });

                setTimeout(function() {
                    $('.oc-color-picker').each(function() {
                        syncOpacityFromInput($(this));
                    });
                    updateBackgroundImageOpacityLabel();
                    if (typeof window.ocUpdatePreviewStyles === 'function') {
                        window.ocUpdatePreviewStyles();
                    }
                }, 100);
                
                $('#oc-import-modal').hide();
                alert('Setările au fost importate cu succes!');
            } catch (error) {
                alert('Fișierul selectat nu este valid.');
            }
        };
        
        reader.readAsText(file);
    });
});
</script>

<style>
.oc-style-wrap {
    max-width: 900px;
    margin: 20px 0;
}

.oc-style-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.oc-section {
    margin-bottom: 30px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.oc-section h3 {
    margin-top: 0;
    color: #1d2327;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 10px;
}

.oc-alpha-control {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
    max-width: 320px;
}

.oc-alpha-control label {
    min-width: 64px;
    color: #4b5563;
    font-size: 12px;
    font-weight: 600;
}

.oc-alpha-slider {
    flex: 1;
}

.oc-alpha-value {
    min-width: 42px;
    text-align: right;
    font-size: 12px;
    color: #374151;
    font-weight: 600;
}



.oc-modal {
    position: fixed !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(15, 23, 42, 0.62) !important;
    backdrop-filter: blur(3px) !important;
    z-index: 9999 !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px !important;
    box-sizing: border-box !important;
}

.oc-modal[style*="display: block"],
.oc-modal[style*="display:block"],
.oc-modal[style*="display: flex"],
.oc-modal[style*="display:flex"] {
    display: flex !important;
}

.oc-modal-content {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 14px !important;
    width: min(560px, 100%) !important;
    max-width: 100% !important;
    max-height: min(88vh, 760px) !important;
    overflow-y: auto !important;
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.28) !important;
    box-sizing: border-box !important;
}

.oc-modal-header {
    padding: 22px 24px 16px !important;
    border-bottom: 1px solid #e5e7eb !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: flex-start !important;
    gap: 12px !important;
}

.oc-modal-header h3 {
    margin: 0;
}

.oc-modal-close {
    background: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
    border-radius: 999px !important;
    width: 36px !important;
    height: 36px !important;
    font-size: 24px !important;
    cursor: pointer !important;
    padding: 0 !important;
    line-height: 1 !important;
    color: #374151 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.oc-modal-body {
    padding: 18px 24px 22px !important;
    box-sizing: border-box !important;
}

.oc-modal-footer {
    padding: 16px 24px 22px !important;
    border-top: 1px solid #e5e7eb !important;
    text-align: right !important;
    box-sizing: border-box !important;
}

.oc-modal-footer .button {
    margin-left: 10px;
}

@media (max-width: 768px) {
    .oc-modal {
        padding: 10px !important;
    }

    .oc-modal-content {
        border-radius: 12px !important;
        max-height: 92vh !important;
    }

    .oc-modal-header,
    .oc-modal-body,
    .oc-modal-footer {
        padding-left: 14px !important;
        padding-right: 14px !important;
    }
}

/* Toggle Switch */
.oc-toggle {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.oc-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.oc-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.oc-toggle-slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.oc-toggle input:checked + .oc-toggle-slider {
    background-color: #2196F3;
}

.oc-toggle input:focus + .oc-toggle-slider {
    box-shadow: 0 0 1px #2196F3;
}

.oc-toggle input:checked + .oc-toggle-slider:before {
    transform: translateX(26px);
}

/* Gradient Preview */
.oc-gradient-preview {
    width: 200px;
    height: 100px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: linear-gradient(132deg, #ff7a3d, #ffd08a);
    margin-top: 10px;
}

/* Gradient Section */
.oc-gradient-section {
    border-left: 4px solid #ff7a3d;
}

.oc-preview-content .oc-schedule-wrapper.oc-has-bg-image-preview {
    position: relative;
    overflow: hidden;
}

.oc-preview-content .oc-schedule-wrapper.oc-has-bg-image-preview::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: var(--oc-bg-image-url);
    background-position: center center;
    background-size: var(--oc-bg-image-size-desktop, 100% auto);
    background-repeat: no-repeat;
    opacity: var(--oc-bg-image-opacity, 1);
    pointer-events: none;
    z-index: 0;
}

.oc-preview-content .oc-schedule-wrapper.oc-has-bg-image-preview > * {
    position: relative;
    z-index: 1;
}

.oc-preview-content.mobile-view .oc-schedule-wrapper {
    width: min(100%, 420px) !important;
    max-width: 420px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

.oc-preview-content.mobile-view .oc-schedule-wrapper.oc-has-bg-image-preview.oc-mobile-bg-repeat-preview::before {
    background-image: var(--oc-bg-image-mobile-url, var(--oc-bg-image-url));
    background-size: auto;
    background-repeat: repeat;
    background-position: top center;
}

.oc-preview-content.mobile-view .oc-schedule-wrapper.oc-has-bg-image-preview.oc-mobile-bg-cover-preview::before {
    background-image: var(--oc-bg-image-mobile-url, var(--oc-bg-image-url));
    background-size: var(--oc-bg-image-size-mobile, 100% auto);
    background-repeat: repeat-y;
    background-position: top center;
}

.oc-preview-content.mobile-view .oc-schedule-logo {
    width: clamp(96px, 28vw, 150px) !important;
    height: auto !important;
}
</style>

<script>
// Define AJAX object for style settings
window.ocAjax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('oc_admin_nonce'); ?>'
};

jQuery(document).ready(function($) {
    if (typeof window.ocToggleBackgroundFields !== 'function') {
        window.ocToggleBackgroundFields = function() {
            const mode = $('#oc_background_mode').val() || 'gradient';
            $('.oc-gradient-row').toggle(mode === 'gradient');
            $('.oc-background-image-row').toggle(mode === 'image');
        };
    }

    // Initialize WordPress Media Uploader support
    
    // Initialize color pickers
    $('.oc-color-picker').wpColorPicker();
    
    // Update gradient preview in real time
    function updateGradientPreview() {
        const startColor = $('#oc_gradient_start').val() || '#ff7a3d';
        const endColor = $('#oc_gradient_end').val() || '#ffd08a';
        const direction = $('#oc_gradient_direction').val() || '132deg';
        
        const gradientCSS = `linear-gradient(${direction}, ${startColor}, ${endColor})`;
        $('#oc_gradient_preview').css('background', gradientCSS);
    }
    
    // Update preview when colors or direction change
    $('#oc_gradient_start, #oc_gradient_end').wpColorPicker({
        change: function() {
            setTimeout(updateGradientPreview, 100);
        },
        clear: function() {
            setTimeout(updateGradientPreview, 100);
        }
    });
    
    $('#oc_gradient_direction').on('change', updateGradientPreview);
    
    // Initial preview update
    updateGradientPreview();
    
    // Refresh preview with latest schedule data (same as admin-page.php)
    window.refreshPreview = function() {
        const previewContainer = $('#admin-schedule-preview');
        previewContainer.html('<div class="oc-loading-preview" style="text-align: center; padding: 40px; color: #666;"><em>Se actualizează previzualizarea...</em></div>');
        
        // Show preview section if hidden
        $('.oc-preview-section').show();
        
        // Use AJAX to get fresh schedule HTML
        $.post(window.ocAjax.ajax_url, {
            action: 'oc_get_schedule_html',
            nonce: window.ocAjax.nonce
        }, function(response) {
            if (response.success && response.data) {
                previewContainer.html(response.data);
                
                // Apply current gradient settings to preview
                window.ocUpdatePreviewStyles();
            } else {
                previewContainer.html('<div style="text-align: center; padding: 40px; color: #999;"><em>Nu există date de afișat în orar.</em></div>');
            }
        }).fail(function(xhr, status, error) {
            console.error('Preview refresh failed:', error);
            previewContainer.html('<div style="text-align: center; padding: 40px; color: #d63638;"><em>Eroare la încărcarea previzualizării.</em></div>');
        });
    };
    
    // Update preview styles based on current settings
    window.ocUpdatePreviewStyles = function() {
        const backgroundMode = $('#oc_background_mode').val() || 'gradient';
        const backgroundImage = $('#oc_background_image').val() || '';
        const backgroundImageMobile = $('#oc_background_image_mobile').val() || '';
        const mobileBgBehavior = $('#oc_background_image_mobile_behavior').val() || 'cover';
        const backgroundImageOpacityRaw = parseFloat($('#oc_background_image_opacity').val());
        const backgroundImageOpacity = (isNaN(backgroundImageOpacityRaw) ? 1 : Math.max(0, Math.min(1, backgroundImageOpacityRaw)));
        const backgroundImageSizeDesktop = getBackgroundImageSizePercent('#oc_background_image_size_desktop', 100);
        const backgroundImageSizeMobile = getBackgroundImageSizePercent('#oc_background_image_size_mobile', 100);
        const startColor = $('#oc_gradient_start').val() || '#ff7a3d';
        const endColor = $('#oc_gradient_end').val() || '#ffd08a';
        const direction = $('#oc_gradient_direction').val() || '132deg';
        const borderRadius = $('#oc_border_radius').val() || '16px';

        if (backgroundMode === 'gradient') {
            const gradientCSS = `linear-gradient(${direction}, ${startColor}, ${endColor})`;
            $('.oc-preview-content .oc-schedule-wrapper').css({
                'background': gradientCSS,
                'padding': '20px',
                'border-radius': borderRadius,
                'background-size': '',
                'background-position': '',
                'background-repeat': ''
            });
        } else if (backgroundMode === 'image' && backgroundImage) {
            $('.oc-preview-content .oc-schedule-wrapper').css({
                'background-image': '',
                'padding': '20px',
                'border-radius': borderRadius,
                '--oc-bg-image-opacity': backgroundImageOpacity,
                '--oc-bg-image-url': `url('${backgroundImage}')`,
                '--oc-bg-image-mobile-url': backgroundImageMobile ? `url('${backgroundImageMobile}')` : '',
                '--oc-bg-image-size-desktop': `${backgroundImageSizeDesktop}% auto`,
                '--oc-bg-image-size-mobile': `${backgroundImageSizeMobile}% auto`
            });
            $('.oc-preview-content .oc-schedule-wrapper').addClass('oc-has-bg-image-preview');
            $('.oc-preview-content .oc-schedule-wrapper').toggleClass('oc-mobile-bg-repeat-preview', mobileBgBehavior === 'repeat');
            $('.oc-preview-content .oc-schedule-wrapper').toggleClass('oc-mobile-bg-cover-preview', mobileBgBehavior !== 'repeat');
        } else {
            $('.oc-preview-content .oc-schedule-wrapper').css({
                'background': '',
                'padding': '',
                'border-radius': '',
                'background-image': '',
                'background-size': '',
                'background-position': '',
                'background-repeat': '',
                '--oc-bg-image-opacity': '',
                '--oc-bg-image-url': '',
                '--oc-bg-image-mobile-url': '',
                '--oc-bg-image-size-desktop': '',
                '--oc-bg-image-size-mobile': ''
            });
            $('.oc-preview-content .oc-schedule-wrapper').removeClass('oc-has-bg-image-preview');
            $('.oc-preview-content .oc-schedule-wrapper').removeClass('oc-mobile-bg-repeat-preview');
            $('.oc-preview-content .oc-schedule-wrapper').removeClass('oc-mobile-bg-cover-preview');
        }
        
        // Update logo in preview
        const logoImage = $('#oc_logo_image').val();
        const logoWidth = $('#oc_logo_width').val() || '120px';
        const logoHeight = $('#oc_logo_height').val() || 'auto';
        const titleColor = $('#oc_title_color').val() || '#5d473d';
        
        // Apply title color
        $('.oc-preview-content .oc-schedule-title').css('color', titleColor);
        
        // Apply logo styling
        if (logoImage) {
            $('.oc-preview-content .oc-schedule-logo').css({
                'width': logoWidth,
                'height': logoHeight,
                'object-fit': 'contain'
            });

            if ($('.oc-preview-content').hasClass('mobile-view')) {
                const numericWidth = parseFloat(logoWidth);
                if (!isNaN(numericWidth) && numericWidth < 96) {
                    $('.oc-preview-content .oc-schedule-logo').css('width', '96px');
                }
            }
        }
    };
    
    // Update preview when gradient settings change
    $('#oc_background_mode, #oc_gradient_direction, #oc_background_image, #oc_background_image_mobile, #oc_background_image_mobile_behavior, #oc_border_radius').on('change', function() {
        window.ocToggleBackgroundFields();
        setTimeout(window.ocUpdatePreviewStyles, 100);
    });
    
    // Update preview when gradient colors change
    $('#oc_gradient_start, #oc_gradient_end').on('change', function() {
        setTimeout(window.ocUpdatePreviewStyles, 100);
    });

    window.ocToggleBackgroundFields();
    
    // Update preview when title color changes
    $('#oc_title_color').on('change', function() {
        setTimeout(window.ocUpdatePreviewStyles, 100);
    });
    
    // Logo upload functionality - with timeout to ensure wp.media is loaded
    setTimeout(function() {
        $('.oc-upload-logo').off('click').on('click', function(e) {
            e.preventDefault();
            // Check if wp.media is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media Uploader nu este disponibil. Te rugăm să reîmprospătezi pagina și să încerci din nou.');
                return;
            }
            
            var mediaUploader = wp.media({
                title: 'Selectează Logo',
                button: {
                    text: 'Folosește acest logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#oc_logo_image').val(attachment.url);
                $('#oc_logo_preview').html('<img src="' + attachment.url + '" alt="Logo Preview" style="max-width: 100px; max-height: 50px;">');
                
                // Trigger change event to update preview
                $('#oc_logo_image').trigger('change');
            });
            
            mediaUploader.open();
        });
    }, 1000); // End of setTimeout for upload functionality
    
    // Remove logo functionality - also with timeout
    setTimeout(function() {
        $('.oc-remove-logo').off('click').on('click', function(e) {
            e.preventDefault();
            $('#oc_logo_image').val('');
            $('#oc_logo_preview').empty();
            
            // Trigger change event to update preview
            $('#oc_logo_image').trigger('change');
        });
    }, 1000); // End of setTimeout for remove functionality
    
    // Update preview when logo settings change
    $('#oc_logo_image, #oc_logo_width, #oc_logo_height, #oc_logo_position').on('change', function() {
        setTimeout(window.ocUpdatePreviewStyles, 100);
    });
    
    // Logo upload functionality initialized with timeout
    
    // Handle admin view toggle buttons (SAME as in admin)
    $(document).on('click', '.admin-view-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var view = $btn.data('view');
        var $preview = $('#admin-schedule-preview');
        var $buttons = $('.admin-view-btn');
        
        // Update button states
        $buttons.removeClass('active');
        $btn.addClass('active');
        
        // Update preview view
        if (view === 'mobile') {
            $preview.addClass('mobile-view');
        } else {
            $preview.removeClass('mobile-view');
        }
    });
    
    // Load initial preview
    setTimeout(window.refreshPreview, 1000);
    
}); // End of jQuery(document).ready()
</script>