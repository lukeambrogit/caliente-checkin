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
?>

<div class="wrap oc-style-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Setări Aspect - Orar Cursuri', OC_TEXT_DOMAIN); ?>
    </h1>
    
    <p class="description">
        <?php esc_html_e('Personalizează aspectul orarului cu culori, fonturi și imagini de fundal. Modificările vor fi reflectate automat pe frontend.', OC_TEXT_DOMAIN); ?>
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
                                   class="oc-color-picker" data-default-color="#e63946">
                            <p class="description"><?php esc_html_e('Culoarea principală folosită pentru accent și header.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_title_color"><?php esc_html_e('Culoare Titlu', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_title_color" name="oc_title_color" 
                                   value="<?php echo esc_attr($settings['title_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#e63946">
                            <p class="description"><?php esc_html_e('Culoarea titlului orarului (H2).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_text_color"><?php esc_html_e('Culoare Text', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_text_color" name="oc_text_color" 
                                   value="<?php echo esc_attr($settings['text_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#111111">
                            <p class="description"><?php esc_html_e('Culoarea textului principal.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_secondary_color"><?php esc_html_e('Culoare Secundară', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_secondary_color" name="oc_secondary_color" 
                                   value="<?php echo esc_attr($settings['secondary_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#5f6368">
                            <p class="description"><?php esc_html_e('Culoarea textului secundar și detaliilor.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_background_color"><?php esc_html_e('Culoare Fundal', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_background_color" name="oc_background_color" 
                                   value="<?php echo esc_attr($settings['background_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#ffffff">
                            <p class="description"><?php esc_html_e('Culoarea de fundal a orarului.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_muted_color"><?php esc_html_e('Culoare Muted', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_muted_color" name="oc_muted_color" 
                                   value="<?php echo esc_attr($settings['muted_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#fafafa">
                            <p class="description"><?php esc_html_e('Culoarea pentru header-ul tabelului și zonele neutre.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_border_color"><?php esc_html_e('Culoare Borduri', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_border_color" name="oc_border_color" 
                                   value="<?php echo esc_attr($settings['border_color']); ?>" 
                                   class="oc-color-picker" data-default-color="#e5e7eb">
                            <p class="description"><?php esc_html_e('Culoarea bordurilor și separatoarelor.', OC_TEXT_DOMAIN); ?></p>
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
                            <p class="description"><?php esc_html_e('Familia de fonturi folosită în orar.', OC_TEXT_DOMAIN); ?></p>
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
                            <p class="description"><?php esc_html_e('Mărimea fontului pentru textul din orar (ex: 14px, 1rem).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_header_font_size"><?php esc_html_e('Mărime Font Titlu', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_header_font_size" name="oc_header_font_size" 
                                   value="<?php echo esc_attr($settings['header_font_size']); ?>" 
                                   class="small-text" placeholder="24px">
                            <p class="description"><?php esc_html_e('Mărimea fontului pentru titlul orarului.', OC_TEXT_DOMAIN); ?></p>
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
                                   class="small-text" placeholder="12px">
                            <p class="description"><?php esc_html_e('Raza pentru colțurile rotunjite (ex: 12px, 0.5rem).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
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
                            <p class="description"><?php esc_html_e('Logo afișat lângă titlul orarului.', OC_TEXT_DOMAIN); ?></p>
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
                            <p class="description"><?php esc_html_e('Lățimea logo-ului (ex: 50px, 3rem, auto).', OC_TEXT_DOMAIN); ?></p>
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
                            <p class="description"><?php esc_html_e('Înălțimea logo-ului (ex: 40px, 2rem, auto).', OC_TEXT_DOMAIN); ?></p>
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
                            <p class="description"><?php esc_html_e('Poziția logo-ului față de titlu.', OC_TEXT_DOMAIN); ?></p>
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
                            <label><?php esc_html_e('Activare Gradient', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <label class="oc-toggle">
                                <input type="checkbox" id="oc_gradient_enabled" name="oc_gradient_enabled" 
                                       value="1" <?php checked($settings['gradient_enabled'], '1'); ?>>
                                <span class="oc-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e('Activează gradient de fundal CSS (suprascrie imaginile de fundal).', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_gradient_start"><?php esc_html_e('Culoare Start', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_gradient_start" name="oc_gradient_start" 
                                   value="<?php echo esc_attr($settings['gradient_start']); ?>" 
                                   class="oc-color-picker" data-default-color="#ff7b00">
                            <p class="description"><?php esc_html_e('Culoarea de start a gradient-ului.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_gradient_end"><?php esc_html_e('Culoare Final', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="text" id="oc_gradient_end" name="oc_gradient_end" 
                                   value="<?php echo esc_attr($settings['gradient_end']); ?>" 
                                   class="oc-color-picker" data-default-color="#ffd700">
                            <p class="description"><?php esc_html_e('Culoarea de final a gradient-ului.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oc_gradient_direction"><?php esc_html_e('Direcție Gradient', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="oc_gradient_direction" name="oc_gradient_direction">
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
                            <p class="description"><?php esc_html_e('Direcția în care se aplică gradient-ul.', OC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Preview Gradient', OC_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <div class="oc-gradient-preview" id="oc_gradient_preview"></div>
                            <p class="description"><?php esc_html_e('Previzualizare gradient în timp real.', OC_TEXT_DOMAIN); ?></p>
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
                    <?php esc_html_e('Previzualizarea se actualizează automat când modifici setările. Pentru a vedea modificările pe site, salvează setările.', OC_TEXT_DOMAIN); ?>
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
    // Initialize color pickers
    $('.oc-color-picker').wpColorPicker();
    
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
    });
    
    // Reset settings functionality
    $('.oc-reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (confirm($(this).data('confirm'))) {
            // Reset all form fields to defaults
            $('#oc_primary_color').val('#e63946').wpColorPicker('color', '#e63946');
            $('#oc_text_color').val('#111111').wpColorPicker('color', '#111111');
            $('#oc_secondary_color').val('#5f6368').wpColorPicker('color', '#5f6368');
            $('#oc_background_color').val('#ffffff').wpColorPicker('color', '#ffffff');
            $('#oc_muted_color').val('#fafafa').wpColorPicker('color', '#fafafa');
            $('#oc_border_color').val('#e5e7eb').wpColorPicker('color', '#e5e7eb');
            $('#oc_font_family').val('Segoe UI, Roboto, Arial, sans-serif');
            $('#oc_font_size').val('14px');
            $('#oc_header_font_size').val('24px');
            $('#oc_border_radius').val('12px');
            $('#oc_desktop_bg_image').val('');
            $('#oc_mobile_bg_image').val('');
            
            // Images removed - gradient only
        }
    });
    
    // Export settings
    $('.oc-export-settings').on('click', function(e) {
        e.preventDefault();
        
        const settings = {
            primary_color: $('#oc_primary_color').val(),
            text_color: $('#oc_text_color').val(),
            secondary_color: $('#oc_secondary_color').val(),
            background_color: $('#oc_background_color').val(),
            muted_color: $('#oc_muted_color').val(),
            border_color: $('#oc_border_color').val(),
            font_family: $('#oc_font_family').val(),
            font_size: $('#oc_font_size').val(),
            header_font_size: $('#oc_header_font_size').val(),
            border_radius: $('#oc_border_radius').val(),
            desktop_bg_image: $('#oc_desktop_bg_image').val(),
            mobile_bg_image: $('#oc_mobile_bg_image').val()
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
                            input.val(settings[key]).wpColorPicker('color', settings[key]);
                        } else {
                            input.val(settings[key]);
                        }
                    }
                });
                
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



.oc-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.oc-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
}

.oc-modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.oc-modal-header h3 {
    margin: 0;
}

.oc-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
}

.oc-modal-body {
    padding: 20px;
}

.oc-modal-footer {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    text-align: right;
}

.oc-modal-footer .button {
    margin-left: 10px;
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
    background: linear-gradient(135deg, #ff7b00, #ffd700);
    margin-top: 10px;
}

/* Gradient Section */
.oc-gradient-section {
    border-left: 4px solid #ff7b00;
}
</style>

<script>
// Define AJAX object for style settings
window.ocAjax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('oc_admin_nonce'); ?>'
};

jQuery(document).ready(function($) {
    // Initialize WordPress Media Uploader support
    
    // Initialize color pickers
    $('.oc-color-picker').wpColorPicker();
    
    // Update gradient preview in real time
    function updateGradientPreview() {
        const startColor = $('#oc_gradient_start').val() || '#ff7b00';
        const endColor = $('#oc_gradient_end').val() || '#ffd700';
        const direction = $('#oc_gradient_direction').val() || '135deg';
        
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
                updatePreviewStyles();
            } else {
                previewContainer.html('<div style="text-align: center; padding: 40px; color: #999;"><em>Nu există date de afișat în orar.</em></div>');
            }
        }).fail(function(xhr, status, error) {
            console.error('Preview refresh failed:', error);
            previewContainer.html('<div style="text-align: center; padding: 40px; color: #d63638;"><em>Eroare la încărcarea previzualizării.</em></div>');
        });
    };
    
    // Update preview styles based on current settings
    function updatePreviewStyles() {
        const gradientEnabled = $('#oc_gradient_enabled').is(':checked');
        const startColor = $('#oc_gradient_start').val() || '#ff7b00';
        const endColor = $('#oc_gradient_end').val() || '#ffd700';
        const direction = $('#oc_gradient_direction').val() || '135deg';
        
        if (gradientEnabled) {
            const gradientCSS = `linear-gradient(${direction}, ${startColor}, ${endColor})`;
            $('.oc-preview-content .oc-schedule-wrapper').css({
                'background': gradientCSS,
                'padding': '20px',
                'border-radius': '12px'
            });
            
            // Make elements transparent
            $('.oc-preview-content .oc-schedule-wrapper .table-wrap, .oc-preview-content .oc-schedule-wrapper .table-wrap table, .oc-preview-content .oc-schedule-wrapper .table-wrap table thead th, .oc-preview-content .oc-schedule-wrapper .table-wrap table tbody td, .oc-preview-content .oc-schedule-wrapper .cards .card').css({
                'background': 'transparent'
            });
            
            // Add text shadow for readability
            $('.oc-preview-content .oc-schedule-wrapper .table-wrap table thead th').css({
                'text-shadow': '0 1px 2px rgba(255,255,255,0.8)'
            });
            
            $('.oc-preview-content .oc-schedule-wrapper .table-wrap table tbody td').css({
                'text-shadow': '0 1px 2px rgba(255,255,255,0.6)'
            });
        }
        
        // Update logo in preview
        const logoImage = $('#oc_logo_image').val();
        const logoWidth = $('#oc_logo_width').val() || '50px';
        const logoHeight = $('#oc_logo_height').val() || 'auto';
        const titleColor = $('#oc_title_color').val() || '#e63946';
        
        // Apply title color
        $('.oc-preview-content .oc-schedule-title').css('color', titleColor);
        
        // Apply logo styling
        if (logoImage) {
            $('.oc-preview-content .oc-schedule-logo').css({
                'width': logoWidth,
                'height': logoHeight,
                'object-fit': 'contain'
            });
        }
    }
    
    // Update preview when gradient settings change
    $('#oc_gradient_enabled, #oc_gradient_direction').on('change', function() {
        setTimeout(updatePreviewStyles, 100);
    });
    
    // Update preview when gradient colors change
    $('#oc_gradient_start, #oc_gradient_end').on('change', function() {
        setTimeout(updatePreviewStyles, 100);
    });
    
    // Update preview when title color changes
    $('#oc_title_color').on('change', function() {
        setTimeout(updatePreviewStyles, 100);
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
        setTimeout(updatePreviewStyles, 100);
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