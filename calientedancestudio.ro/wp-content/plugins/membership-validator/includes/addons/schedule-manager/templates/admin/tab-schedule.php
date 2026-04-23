<?php
/**
 * Schedule Manager Tab Content
 */

// Variables $product_attributes, $selected_attribute, $schedule_rows, $course_terms 
// sunt disponibile din admin_page()
?>

<div class="oc-admin-container">
        
        <!-- Section pentru selectarea atributului -->
        <div class="oc-section oc-attribute-section">
            <h2><?php esc_html_e('Configurare Orar', OC_TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="oc_variable_product"><?php esc_html_e('Produs Variabil pentru Cursuri', OC_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <?php if (empty($product_attributes)): ?>
                            <div class="notice notice-warning inline">
                                <p>
                                    <strong><?php esc_html_e('Atenție!', OC_TEXT_DOMAIN); ?></strong>
                                    <?php esc_html_e('Nu s-au găsit produse variabile WooCommerce.', OC_TEXT_DOMAIN); ?>
                                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" target="_blank">
                                        <?php esc_html_e('Creează produs variabil WooCommerce →', OC_TEXT_DOMAIN); ?>
                                    </a>
                                </p>
                            </div>
                        <?php elseif (!empty($selected_attribute) && empty($course_terms)): ?>
                            <div class="notice notice-info inline">
                                <p>
                                    <strong><?php esc_html_e('Info:', OC_TEXT_DOMAIN); ?></strong>
                                    <?php esc_html_e('Produsul selectat nu are variații configurate.', OC_TEXT_DOMAIN); ?>
                                    <a href="<?php echo admin_url('post.php?post=' . $selected_attribute . '&action=edit'); ?>" target="_blank">
                                        <?php esc_html_e('Adaugă variații la produs →', OC_TEXT_DOMAIN); ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <select id="oc_variable_product" name="oc_variable_product" class="regular-text" <?php echo empty($product_attributes) ? 'disabled' : ''; ?>>
                            <option value=""><?php esc_html_e('Selectează un produs variabil...', OC_TEXT_DOMAIN); ?></option>
                            <?php 
                            if (!empty($product_attributes) && is_array($product_attributes)) {
                                foreach ($product_attributes as $product_id => $product_data) : ?>
                                    <option value="<?php echo esc_attr($product_id); ?>" 
                                        <?php selected($selected_attribute, $product_id); ?>>
                                        <?php echo esc_html($product_data['name']); ?> (ID: <?php echo $product_id; ?>)
                                    </option>
                                <?php endforeach; 
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Selectează produsul variabil care conține cursurile disponibile ca variații.', OC_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oc_schedule_title"><?php esc_html_e('Titlu Orar', OC_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input type="text" id="oc_schedule_title" name="oc_schedule_title" 
                               class="regular-text" value="<?php echo esc_attr(get_option('oc_schedule_title', 'CALIENTE DANCE STUDIO — ORAR')); ?>" />
                        <p class="description">
                            <?php esc_html_e('Titlul care va fi afișat în partea de sus a orarului.', OC_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Schedule Configuration Section -->
        <div class="oc-section oc-schedule-section" style="<?php echo empty($selected_attribute) ? 'display:none;' : ''; ?>">
            <div class="oc-schedule-header">
                <h2><?php esc_html_e('Configurare Program Orar', OC_TEXT_DOMAIN); ?></h2>
            </div>
            
            <div class="oc-flexible-schedule">
                <div class="oc-intro-text">
                    <p>Zilele existente sunt încărcate automat și pot fi editate direct. Adaugă zile noi după necesitate.</p>
                    <p><strong>Structură:</strong> Zi → Ore → 4 Săli (Sala 1-4) per oră</p>
                </div>
                
                <div class="oc-loading-message" id="oc-loading-message" style="display: none;">
                    <p><em>Se încarcă orarul existent...</em></p>
                </div>
                
                <div class="oc-flexible-container" id="oc-flexible-container">
                    <!-- Existing schedule will be loaded here automatically -->
                </div>
                
                <div class="oc-add-day-section">
                    <button type="button" class="button button-secondary oc-add-day-btn" id="oc-add-day">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Adaugă Zi Nouă
                    </button>
                    <p class="description">Adaugă o zi nouă la programul existent</p>
                </div>
                
                <div class="oc-save-section">
                    <button type="button" class="button button-primary" id="oc-save-flexible">
                        <span class="dashicons dashicons-yes"></span>
                        Salvare Orar
                    </button>
                    <p class="description">Salvează toate modificările din orar</p>
                </div>
            </div>
            
        </div>
        
        <!-- Schedule Preview -->
        <div class="oc-section oc-preview-section" <?php echo empty($schedule_rows) ? 'style="display:none;"' : ''; ?>>
            <h2><?php esc_html_e('Previzualizare Orar', OC_TEXT_DOMAIN); ?></h2>
            <div class="oc-preview-container">
                <div class="oc-preview-note">
                    <p><?php esc_html_e('Previzualizarea se actualizează automat după modificări.', OC_TEXT_DOMAIN); ?></p>
                    <p>
                        <strong><?php esc_html_e('Shortcode:', OC_TEXT_DOMAIN); ?></strong>
                        <code>[orar_cursuri]</code>
                        <button type="button" class="button button-small oc-copy-shortcode" data-shortcode="[orar_cursuri]">
                            <?php esc_html_e('Copiază', OC_TEXT_DOMAIN); ?>
                        </button>
                    </p>
                </div>
                
                <div class="oc-preview-content" id="oc-preview-content">
                    <div class="oc-loading-preview" style="text-align: center; padding: 40px; color: #666;">
                        <em>Se încarcă previzualizarea...</em>
                    </div>
                </div>
            </div>
        </div>

<style>
/* CLEAN CSS - NO UNNECESSARY STYLES */
.oc-admin-container {
    margin: 20px 0;
}

.oc-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.oc-schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
/* Management Section Styles */
.oc-management-section {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.oc-management-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.oc-management-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.oc-existing-schedule {
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    background: #f8f9fa;
}

.oc-loading {
    padding: 40px;
    text-align: center;
    color: #666;
}

.oc-day-item {
    border-bottom: 1px solid #e1e5e9;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}

.oc-day-item:last-child {
    border-bottom: none;
}

.oc-day-item:hover {
    background: #f8f9fa;
}

.oc-day-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.oc-day-name {
    font-weight: 600;
    font-size: 16px;
    color: #2271b1;
    text-transform: uppercase;
}

.oc-day-details {
    font-size: 14px;
    color: #646970;
}

.oc-day-actions {
    display: flex;
    gap: 8px;
}

.oc-day-actions .button {
    padding: 4px 12px;
    font-size: 12px;
}

.button.oc-edit-day {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.button.oc-edit-day:hover {
    background: #135e96;
    border-color: #135e96;
}

.button.oc-delete-day {
    background: #d63638;
    color: #fff;
    border-color: #d63638;
}

.button.oc-delete-day:hover {
    background: #b32d2e;
    border-color: #b32d2e;
}

/* Add Day Section Styles */
.oc-add-day-section {
    text-align: center;
    padding: 30px 20px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    margin-top: 20px;
    background: #fafafa;
}

.oc-add-day-section .button {
    padding: 10px 20px;
    font-size: 14px;
}

.oc-add-day-section .description {
    margin: 10px 0 0 0;
    color: #666;
    font-style: italic;
}

/* Save Section Styles */
.oc-save-section {
    text-align: center;
    padding: 15px 20px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-top: 20px;
    background: #f9f9f9;
}

.oc-save-section .description {
    margin: 8px 0 0 0;
    color: #666;
    font-weight: normal;
    font-size: 13px;
}

/* UNIFIED BUTTON STYLES */
.oc-admin-container .button {
    padding: 8px 16px !important;
    font-size: 14px !important;
    font-weight: normal !important;
    line-height: 1.4 !important;
    min-height: 36px !important;
    box-sizing: border-box !important;
}

.oc-admin-container .button .dashicons {
    font-size: 16px !important;
    line-height: 1 !important;
    vertical-align: middle !important;
    margin-right: 6px !important;
}

.oc-admin-container .button-small {
    padding: 4px 8px !important;
    font-size: 12px !important;
    min-height: 28px !important;
}

/* RESPONSIVE FORM STYLES */
.oc-flexible-container {
    margin-top: 20px;
}

.oc-day-section {
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    background: #fff;
}

.oc-day-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
}

.oc-day-controls {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.oc-day-select {
    min-width: 150px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.oc-hour-row {
    border-bottom: 1px solid #eee;
    padding: 15px 20px;
}

.oc-hour-row:last-child {
    border-bottom: none;
}

.oc-hour-controls {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 20px;
    align-items: start;
}

.oc-time-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
}

.oc-rooms-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.oc-room-select {
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    width: 100%;
    min-width: 0;
}

/* MOBILE RESPONSIVE STYLES */
@media (max-width: 1024px) {
    .oc-hour-controls {
        grid-template-columns: 100px 1fr auto;
        gap: 15px;
    }
    
    .oc-rooms-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .oc-room-select {
    font-size: 12px;
        padding: 6px 8px;
    }
}

@media (max-width: 768px) {
    .oc-admin-container {
        margin: 10px 0;
        padding: 0 10px;
    }
    
    .oc-section {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .oc-schedule-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .oc-day-header {
        padding: 12px 15px;
    }
    
    .oc-day-controls {
        gap: 10px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .oc-day-select {
        min-width: auto;
        width: 100%;
    }
    
    .oc-hour-row {
        padding: 12px 15px;
    }
    
    .oc-hour-controls {
        grid-template-columns: 1fr;
        gap: 12px;
        align-items: stretch;
    }
    
    .oc-time-select {
        width: 100%;
    }
    
    .oc-rooms-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .oc-room-select {
        font-size: 14px;
        padding: 10px 12px;
        width: 100%;
    }
    
    .oc-add-day-section,
    .oc-save-section {
        padding: 20px 15px;
    }
    
    .oc-admin-container .button {
        width: 100%;
        justify-content: center;
        margin-bottom: 8px;
    }
    
    .oc-day-controls .button {
        width: auto;
        margin-bottom: 0;
    }
}

@media (max-width: 480px) {
    .oc-section {
        padding: 12px;
    }
    
    .oc-day-header {
        padding: 10px 12px;
    }
    
    .oc-hour-row {
        padding: 10px 12px;
    }
    
    .oc-room-select {
        font-size: 14px;
        padding: 12px;
        min-height: 44px; /* Touch-friendly */
    }
    
    .oc-time-select,
    .oc-day-select {
        font-size: 14px;
        padding: 12px;
        min-height: 44px; /* Touch-friendly */
    }
    
    .oc-admin-container .button {
        padding: 12px 16px !important;
        min-height: 44px !important; /* Touch-friendly */
        font-size: 14px !important;
    }
}

/* Delete Existing Day Button */
.oc-delete-existing-day {
    background: #d63638 !important;
    color: white !important;
    border-color: #d63638 !important;
}

/* PREVIEW BACKGROUND STYLES */
/* Desktop: Background pe întregul oc-schedule-wrapper */
@media (min-width: 900px) {
    .oc-preview-section .oc-schedule-wrapper {
        background-image: var(--desktop-bg-image, none);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: local;
        padding: 20px;
        border-radius: 12px;
    }
    .oc-preview-section .table-wrap {
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        overflow: hidden;
    }
}

/* Mobile: Background separat pentru fiecare card */
@media (max-width: 899px) {
    .oc-preview-section .oc-schedule-wrapper {
        background: transparent;
        padding: 20px;
    }
    .oc-preview-section .card {
        background-image: var(--mobile-bg-image, none);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: local;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 12px;
    }
    .oc-preview-section .card .top,
    .oc-preview-section .card .rooms,
    .oc-preview-section .card .room {
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(5px);
    }
    .oc-preview-section .card .top {
        border-radius: 12px 12px 0 0;
        margin: -12px -12px 8px -12px;
        padding: 12px;
    }
    .oc-preview-section .card .rooms {
        border-radius: 0 0 12px 12px;
        margin: 8px -12px -12px -12px;
        padding: 12px;
    }
}

.oc-delete-existing-day:hover {
    background: #b32d2e !important;
    border-color: #b32d2e !important;
    color: white !important;
}
</style>

<script type="text/javascript">
// SINGLE CONSOLIDATED JAVASCRIPT BLOCK

// Basic configuration
window.ocAjax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('oc_admin_nonce'); ?>'
};

// Initialize course terms
<?php 
// Safely encode course terms
$course_terms_json = json_encode($course_terms ?: [], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS);
if (json_last_error() !== JSON_ERROR_NONE) {
    $course_terms_json = '[]';
}
?>
window.courseTerms = <?php echo $course_terms_json; ?>;

// Course terms initialized

// Main jQuery ready block
jQuery(document).ready(function($) {
    // Load initial state if product is selected
    const selectedProduct = '<?php echo esc_js($selected_attribute); ?>';
    if (selectedProduct) {
        $('#oc_variable_product').val(selectedProduct);
        $('.oc-schedule-section').show();
    }
    
    // Handle product selection change
    $('#oc_variable_product').on('change', function() {
        const selectedProduct = $(this).val();
        
        if (selectedProduct) {
            $('.oc-schedule-section').show();
            
            // Update the option via AJAX
            $.post(window.ocAjax.ajax_url, {
                action: 'oc_update_product',
                nonce: window.ocAjax.nonce,
                product_id: selectedProduct
    }, function(response) {
        if (response.success) {
                    location.reload();
                }
            });
        } else {
            $('.oc-schedule-section').hide();
        }
    });
    
    // Handle schedule title change
    $('#oc_schedule_title').on('change blur', function() {
        const title = $(this).val();
        
        $.post(window.ocAjax.ajax_url, {
            action: 'oc_update_schedule_title',
            nonce: window.ocAjax.nonce,
            schedule_title: title
        }, function(response) {
            if (response.success) {
                // Refresh preview if it exists
                if (typeof window.refreshPreview === 'function') {
                    window.refreshPreview();
                }
            }
        });
    });
    
    // Debug button click handler
    // Rezervat pentru evenimente viitoare
    
    // Copy shortcode handler
    $('.oc-copy-shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        const textArea = document.createElement('textarea');
        textArea.value = shortcode;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            alert('Shortcode copiat în clipboard!');
        } catch (err) {
            alert('Nu s-a putut copia. Selectați și copiați manual: ' + shortcode);
        }
        document.body.removeChild(textArea);
    });
    
         // Add Day button handler
     $('#oc-add-day').on('click', function() {
         window.addDay();
     });
     
     // Save button handler
     $('#oc-save-flexible').on('click', function() {
         window.saveFlexibleSchedule();
     });
     
     // Event delegation for dynamic buttons
     $(document).on('click', '.oc-add-hour', function() {
         const dayIndex = $(this).closest('.oc-day-section').data('day-index');
         window.addHour(dayIndex);
     });
     
     $(document).on('click', '.oc-remove-day', function() {
         const dayIndex = $(this).closest('.oc-day-section').data('day-index');
         window.removeDay(dayIndex);
     });
     
     $(document).on('click', '.oc-remove-hour', function() {
         const dayIndex = $(this).closest('.oc-day-section').data('day-index');
         const hourIndex = $(this).closest('.oc-hour-row').data('hour-index');
         window.removeHour(dayIndex, hourIndex);
     });
     
     // Management section handlers
         $('#oc-refresh-schedule').on('click', function() {
        window.loadExistingSchedule();
     });
     
     // Event delegation for delete existing day buttons (in form)
     $(document).on('click', '.oc-delete-existing-day', function() {
         const weekday = $(this).data('weekday');
         // Clean('Delete existing day clicked for:', weekday);
         if (confirm('Sigur doriți să ștergeți această zi din orar?\n\nAceastă acțiune va șterge definitiv ziua din baza de date.')) {
             window.deleteExistingDay(weekday, $(this).closest('.oc-day-section'));
         }
     });
     
     // Load existing schedule automatically into editable form
     if ('<?php echo esc_js($selected_attribute); ?>') {
                 setTimeout(function() {
            window.loadExistingScheduleIntoForm();
            window.refreshPreview(); // Initial preview load
            
            // Debug: Check if we have background settings
            jQuery.post(window.ocAjax.ajax_url, {
                action: 'oc_get_background_settings',
                nonce: window.ocAjax.nonce
            }, function(response) {
                // Background settings loaded
                if (response.success && response.data) {
                    // Background images loaded successfully
                }
                window.updatePreviewBackgrounds(); // Apply backgrounds to preview
            });
        }, 1000);
     }
     
     // Ensure course dropdowns are refreshed after everything loads
     setTimeout(function() {
         window.refreshAllCourseDropdowns();
     }, 1500);
     
     // Initialization complete
 });

// Load existing schedule automatically into editable form
window.loadExistingScheduleIntoForm = function() {
    
    const loadingMsg = jQuery('#oc-loading-message');
    const container = jQuery('#oc-flexible-container');
    
    loadingMsg.show();
    container.empty(); // Clear any existing content
    
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_get_schedule',
        nonce: window.ocAjax.nonce,
        product_id: '<?php echo esc_js($selected_attribute); ?>'
    }, function(response) {
        // Schedule data loaded from server
        loadingMsg.hide();
        
        if (response.success && response.data) {
            // Processing schedule data
            // Convert structured data back to flat array
            const flatData = [];
            Object.keys(response.data).forEach(function(timeKey) {
                const timeData = response.data[timeKey];
                Object.keys(timeData).forEach(function(weekday) {
                    const weekdayData = timeData[weekday];
                    Object.keys(weekdayData).forEach(function(room) {
                        const roomData = weekdayData[room];
                        const timeParts = timeKey.split('-');
                        flatData.push({
                            weekday: parseInt(weekday),
                            start_time: timeParts[0],
                            end_time: timeParts[1],
                            term_id: roomData.term_id,
                            term_name: roomData.term_name,
                            room_number: parseInt(room)
                        });
                    });
                });
            });
            
            // Data converted to flat format
            if (flatData.length > 0) {
                // Populating form with schedule data
                window.populateFormWithAllScheduleData(flatData);
        } else {
                // No data to populate
            }
        } else {
            // No valid response data received
        }
    }).fail(function(xhr, status, error) {
        console.error('Failed to load existing schedule:', xhr, status, error);
        loadingMsg.hide();
    });
};

// Legacy function - kept for compatibility
window.loadExistingSchedule = function() {
    // Clean('=== LOADING EXISTING SCHEDULE ===');
    
    const container = jQuery('#oc-existing-schedule');
    container.html('<div class="oc-loading">Încărcare orar existent...</div>');
    
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_get_schedule',
        nonce: window.ocAjax.nonce,
        product_id: '<?php echo esc_js($selected_attribute); ?>'
    }, function(response) {
        // Clean('Schedule data response:', response);
        
        if (response.success && response.data) {
            // Convert structured data back to flat array for management
            const flatData = [];
            Object.keys(response.data).forEach(function(timeKey) {
                const timeData = response.data[timeKey];
                Object.keys(timeData).forEach(function(weekday) {
                    const weekdayData = timeData[weekday];
                    Object.keys(weekdayData).forEach(function(room) {
                        const roomData = weekdayData[room];
                        const timeParts = timeKey.split('-');
                        flatData.push({
                            weekday: parseInt(weekday),
                            start_time: timeParts[0],
                            end_time: timeParts[1],
                            term_id: roomData.term_id,
                            term_name: roomData.term_name,
                            room_number: parseInt(room)
                        });
                    });
                });
            });
            
            // Clean('Converted flat data:', flatData);
            window.renderExistingSchedule(flatData);
    } else {
            container.html('<div class="oc-loading">Nu există date în orar.</div>');
        }
    }).fail(function(xhr, status, error) {
        console.error('Failed to load schedule:', error);
        container.html('<div class="oc-loading">Eroare la încărcarea orarului.</div>');
    });
};

// Render existing schedule
window.renderExistingSchedule = function(scheduleData) {
    // Clean('=== RENDERING EXISTING SCHEDULE ===', scheduleData);
    
    const container = jQuery('#oc-existing-schedule');
    
    if (!scheduleData || scheduleData.length === 0) {
        container.html('<div class="oc-loading">Nu există date în orar.</div>');
        return;
    }
    
    // Group by weekday
    const groupedByDay = {};
    scheduleData.forEach(function(row) {
        if (!groupedByDay[row.weekday]) {
            groupedByDay[row.weekday] = [];
        }
        groupedByDay[row.weekday].push(row);
    });
    
    const weekdayNames = {
        0: 'Duminică', // ISO 8601: Sunday = 0
        1: 'Luni', 2: 'Marți', 3: 'Miercuri', 
        4: 'Joi', 5: 'Vineri', 6: 'Sâmbătă', 
        7: 'Duminică' // Backwards compatibility
    };
    
    const weekdayRoNames = {
        0: 'duminica', // ISO 8601: Sunday = 0
        1: 'luni', 2: 'marti', 3: 'miercuri', 
        4: 'joi', 5: 'vineri', 6: 'sambata',
        7: 'duminica' // Backwards compatibility
    };
    
    let html = '';
    
    // Sort weekdays numerically
    const sortedWeekdays = Object.keys(groupedByDay).sort((a, b) => parseInt(a) - parseInt(b));
    
    sortedWeekdays.forEach(function(weekday) {
        const dayData = groupedByDay[weekday];
        const dayName = weekdayNames[weekday] || `Ziua ${weekday}`;
        const dayNameRo = weekdayRoNames[weekday] || `ziua${weekday}`;
        
        // Count unique time slots
        const timeSlots = [...new Set(dayData.map(row => `${row.start_time}-${row.end_time}`))];
        const coursesCount = dayData.length;
        
        html += `
            <div class="oc-day-item">
                <div class="oc-day-info">
                    <div class="oc-day-name">${dayName}</div>
                    <div class="oc-day-details">
                        ${timeSlots.length} interval${timeSlots.length !== 1 ? 'e' : ''} • 
                        ${coursesCount} curs${coursesCount !== 1 ? 'uri' : ''}
                    </div>
                </div>
                <div class="oc-day-actions">
                    <button type="button" class="button oc-edit-day" data-weekday="${weekday}">
                        <span class="dashicons dashicons-edit"></span> Editează
                    </button>
                    <button type="button" class="button oc-delete-day" data-weekday="${weekday}">
                        <span class="dashicons dashicons-trash"></span> Șterge
                    </button>
                </div>
            </div>
        `;
    });
    
    if (html) {
        container.html(html);
    } else {
        container.html('<div class="oc-loading">Nu există date în orar.</div>');
    }
};

// Delete day from schedule
window.deleteDay = function(weekday) {
    // Clean('=== DELETING DAY ===', weekday);
    
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_delete_day_schedule',
        nonce: window.ocAjax.nonce,
        weekday: parseInt(weekday, 10),
        product_id: parseInt(jQuery('#oc-selected-product').val(), 10) || parseInt('<?php echo esc_js($selected_attribute); ?>', 10) || 0
    }, function(response) {
        // Clean('Delete response:', response);
        
        if (response.success) {
            alert('Ziua a fost ștearsă cu succes!');
            window.loadExistingSchedule(); // Refresh the list
            jQuery('.oc-preview-section .oc-preview-content').load(location.href + ' .oc-preview-content > *'); // Refresh preview
        } else {
            alert('Eroare la ștergere: ' + (response.data || 'Eroare necunoscută'));
        }
    }).fail(function(xhr, status, error) {
        console.error('Delete failed:', error);
        alert('Eroare la ștergerea zilei: ' + error);
    });
};

// Edit day - load data and populate form
window.editDay = function(weekday) {
    // Clean('=== EDITING DAY ===', weekday);
    
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_load_day_schedule',
        nonce: window.ocAjax.nonce,
        weekday: weekday
    }, function(response) {
        // Clean('Load day response:', response);
        
        if (response.success && response.data) {
            window.populateFormWithDayData(weekday, response.data);
        } else {
            alert('Eroare la încărcarea datelor pentru editare: ' + (response.data || 'Date invalide'));
        }
    }).fail(function(xhr, status, error) {
        console.error('Load day failed:', error);
        alert('Eroare la încărcarea zilei pentru editare: ' + error);
    });
};

// Populate form with day data
window.populateFormWithDayData = function(weekday, dayData) {
    // Clean('=== POPULATING FORM WITH DAY DATA ===', weekday, dayData);
    
    // Clear existing form
    jQuery('#oc-flexible-container').empty();
    
    if (!dayData || dayData.length === 0) {
        alert('Nu există date pentru această zi.');
        return;
    }
    
    // Group by time slots
    const timeSlots = {};
    dayData.forEach(function(row) {
        const timeKey = `${row.start_time}-${row.end_time}`;
        if (!timeSlots[timeKey]) {
            timeSlots[timeKey] = {
                time: row.start_time,
                rooms: {}
            };
        }
        timeSlots[timeKey].rooms[`room${row.room_number}`] = row.term_id;
    });
    
    // Add a day with populated data
    window.addDay();
    
    const daySection = jQuery('#oc-flexible-container .oc-day-section').last();
    
    // Set the weekday
    daySection.find('.oc-day-select').val(weekday);
    
    // Remove the default empty hour and add populated hours
    daySection.find('.oc-hour-row').remove();
    
    Object.keys(timeSlots).forEach(function(timeKey) {
        const timeData = timeSlots[timeKey];
        const dayIndex = daySection.data('day-index');
        
        window.addHour(dayIndex);
        
        const hourRow = daySection.find('.oc-hour-row').last();
        
        // Set time (remove seconds if present)
        const timeValue = timeData.time.substring(0, 5);
        hourRow.find('.oc-time-select').val(timeValue);
        
        // Set rooms
        Object.keys(timeData.rooms).forEach(function(roomKey) {
            const termId = timeData.rooms[roomKey];
            hourRow.find(`select[name*="[${roomKey}]"]`).val(termId);
        });
    });
    
    // Scroll to the form
    jQuery('html, body').animate({
        scrollTop: jQuery('.oc-schedule-section').offset().top - 50
    }, 500);
    
    alert(`Datele pentru ${weekday.toUpperCase()} au fost încărcate în formular. Modificați și salvați.`);
};

// Populate form with all schedule data (organized by weekdays)
window.populateFormWithAllScheduleData = function(scheduleData) {
    // Populating form with all schedule data
    
    // Clear existing form
    jQuery('#oc-flexible-container').empty();
    
    if (!scheduleData || scheduleData.length === 0) {
        // No schedule data to populate
        return;
    }
    
    // Group by weekday
    const groupedByWeekday = {};
    scheduleData.forEach(function(row) {
        if (!groupedByWeekday[row.weekday]) {
            groupedByWeekday[row.weekday] = [];
        }
        groupedByWeekday[row.weekday].push(row);
    });
    
    const weekdayNumbers = {
        0: 'duminica', // ISO 8601: Sunday = 0
        1: 'luni', 2: 'marti', 3: 'miercuri', 
        4: 'joi', 5: 'vineri', 6: 'sambata', 
        7: 'duminica' // Backwards compatibility
    };
    
    // Sort weekdays numerically
    const sortedWeekdays = Object.keys(groupedByWeekday).sort((a, b) => parseInt(a) - parseInt(b));

    
    sortedWeekdays.forEach(function(weekdayNum) {
        const weekdayData = groupedByWeekday[weekdayNum];
        const weekdayName = weekdayNumbers[weekdayNum] || `day${weekdayNum}`;
        
        // 🔧 FIX: Convertim weekday=0 (duminică din DB) în 7 pentru dropdown
        // Dropdown-ul are opțiuni 1-7, nu 0-6
        const dropdownValue = (parseInt(weekdayNum) === 0) ? 7 : weekdayNum;

        // Clean for raw weekday data mapping
        
        // Group by time slots for this weekday
        const timeSlots = {};
        weekdayData.forEach(function(row) {
            const timeKey = `${row.start_time}-${row.end_time}`;
            if (!timeSlots[timeKey]) {
                timeSlots[timeKey] = {
                    time: row.start_time,
                    rooms: {}
                };
            }
            timeSlots[timeKey].rooms[`room${row.room_number}`] = row.term_id;
        });
        
        // Clean(`Time slots for ${weekdayName}:`, timeSlots);
        
        // Add a day to the form
        window.addDay();
        
        const daySection = jQuery('#oc-flexible-container .oc-day-section').last();
        const dayIndex = daySection.data('day-index');
        
        // Set the weekday (uses converted value 1-7 for dropdown compatibility)
        daySection.find('.oc-day-select').val(dropdownValue);
        
        // Remove the default empty hour and add populated hours
        daySection.find('.oc-hour-row').remove();
        
        // Remove normal delete button and add red delete button for existing days
        const dayHeader = daySection.find('.oc-day-header .oc-day-controls');
        dayHeader.find('.oc-remove-day').remove(); // Remove normal delete button
        
        if (dayHeader.find('.oc-delete-existing-day').length === 0) {
            dayHeader.append(`
                <button type="button" class="button oc-delete-existing-day" data-weekday="${weekdayNum}">
                    <span class="dashicons dashicons-trash"></span> 
                    Șterge Zi Existenta
                </button>
            `);
        }
        
        // Add time slots
        Object.keys(timeSlots).forEach(function(timeKey, timeIndex) {
            const timeData = timeSlots[timeKey];
            
            window.addHour(dayIndex);
            
            // Get the specific hour row that was just added (by index)
            const hourRows = daySection.find('.oc-hour-row');
            const hourRow = jQuery(hourRows[timeIndex]); // Get the specific row for this time slot
            
            // Clean(`Setting time slot ${timeKey} for weekday ${weekdayName} (row ${timeIndex}):`, timeData);
            
            // Set time (use full timeKey which is already in HH:MM-HH:MM format)
            const timeSelect = hourRow.find('.oc-time-select');
            // timeKey is already 'HH:MM-HH:MM' format (e.g., '11:00-12:00')
            timeSelect.val(timeKey);
            // Clean(`Set time to ${timeKey}, current value:`, timeSelect.val());
            
            // Set rooms
            Object.keys(timeData.rooms).forEach(function(roomKey) {
                const termId = timeData.rooms[roomKey];
                const roomSelect = hourRow.find(`select[name*="[${roomKey}]"]`);

                
                roomSelect.val(termId);
                const actualValue = roomSelect.val();

                
                // If value didn't set, try alternative method
                if (actualValue != termId && termId) {

                    roomSelect.find(`option[value="${termId}"]`).prop('selected', true);

                }
            });
        });
    });
    
    // Force refresh all dropdowns after population to ensure they have the correct values
    setTimeout(() => {

        window.refreshAllCourseDropdowns();
    }, 200);
    

};

// Refresh preview with latest schedule data
window.refreshPreview = function() {
    // Clean('=== REFRESHING PREVIEW ===');
    
    const previewContainer = jQuery('#oc-preview-content');
    previewContainer.html('<div class="oc-loading-preview" style="text-align: center; padding: 40px; color: #666;"><em>Se actualizează previzualizarea...</em></div>');
    
    // Show preview section if hidden
    jQuery('.oc-preview-section').show();
    
    // Use AJAX to get fresh schedule HTML
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_get_schedule_html',
        nonce: window.ocAjax.nonce
    }, function(response) {
        // Clean('Preview refresh response:', response);
        
        if (response.success && response.data) {
            previewContainer.html(response.data);
            
            // Apply current background settings to preview
            window.updatePreviewBackgrounds();
        } else {
            previewContainer.html('<div style="text-align: center; padding: 40px; color: #999;"><em>Nu există date de afișat în orar.</em></div>');
        }
    }).fail(function(xhr, status, error) {
        console.error('Preview refresh failed:', error);
        previewContainer.html('<div style="text-align: center; padding: 40px; color: #d63638;"><em>Eroare la încărcarea previzualizării.</em></div>');
    });
};

// Update background images in preview section
window.updatePreviewBackgrounds = function() {
    // Get current background settings via AJAX
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_get_background_settings',
        nonce: window.ocAjax.nonce
    }, function(response) {
        if (response.success && response.data) {
            const settings = response.data;
            const previewSection = jQuery('.oc-preview-section');
            
            // Set CSS custom properties for background images
            if (settings.desktop_bg_image) {
                previewSection.css('--desktop-bg-image', `url('${settings.desktop_bg_image}')`);
            } else {
                previewSection.css('--desktop-bg-image', 'none');
            }
            
            if (settings.mobile_bg_image) {
                previewSection.css('--mobile-bg-image', `url('${settings.mobile_bg_image}')`);
            } else {
                previewSection.css('--mobile-bg-image', 'none');
            }
            
            // Apply styles directly to preview elements as fallback
            if (settings.desktop_bg_image) {
                previewSection.find('.oc-schedule-wrapper').css({
                    'background-image': `url('${settings.desktop_bg_image}')`,
                    'background-size': 'cover',
                    'background-position': 'center',
                    'background-repeat': 'no-repeat'
                });
            }
            
            if (settings.mobile_bg_image) {
                previewSection.find('.card').css({
                    'background-image': `url('${settings.mobile_bg_image}')`,
                    'background-size': 'cover',
                    'background-position': 'center',
                    'background-repeat': 'no-repeat'
                });
            }
        }
    });
};

// Delete existing day from database and remove from form
window.deleteExistingDay = function(weekday, daySection) {
    // Clean('=== DELETING EXISTING DAY ===', weekday);
    
    // Show loading state
    const deleteBtn = daySection.find('.oc-delete-existing-day');
    const originalText = deleteBtn.html();
    deleteBtn.html('<span class="dashicons dashicons-update-alt"></span> Ștergere...').prop('disabled', true);
    
    jQuery.post(window.ocAjax.ajax_url, {
        action: 'oc_delete_day_schedule',
        nonce: window.ocAjax.nonce,
        weekday: parseInt(weekday, 10),
        product_id: parseInt(jQuery('#oc-selected-product').val(), 10) || parseInt('<?php echo esc_js($selected_attribute); ?>', 10) || 0
    }, function(response) {
        // Clean('Delete response:', response);
        
        if (response.success) {
            // Remove the day section from form
            daySection.fadeOut(300, function() {
                daySection.remove();
            });
            
            // Update preview
            setTimeout(function() {
                window.refreshPreview();
            }, 500);
            
            alert('Ziua a fost ștearsă cu succes din orar!');
        } else {
            alert('Eroare la ștergere: ' + (response.data || 'Eroare necunoscută'));
            // Restore button
            deleteBtn.html(originalText).prop('disabled', false);
        }
    }).fail(function(xhr, status, error) {
        console.error('Delete failed:', error);
        alert('Eroare la ștergerea zilei: ' + error);
        // Restore button
        deleteBtn.html(originalText).prop('disabled', false);
    });
    };

// Add Day function
window.addDay = function() {
    // Clean('=== ADD DAY FUNCTION CALLED ===');
    
    const container = document.getElementById('oc-flexible-container');
    if (!container) {
        console.error('Schedule container not found');
        return;
    }

    const dayCount = container.querySelectorAll('.oc-day-section').length;
    const newDayIndex = dayCount;

    // Romanian weekdays (value = numeric weekday for database)
    const weekdays = [
        { value: '1', label: 'Luni' },
        { value: '2', label: 'Marți' },
        { value: '3', label: 'Miercuri' },
        { value: '4', label: 'Joi' },
        { value: '5', label: 'Vineri' },
        { value: '6', label: 'Sâmbătă' },
        { value: '7', label: 'Duminică' }
    ];

    let dayOptionsHtml = '<option value="">Selectează ziua</option>';
    weekdays.forEach(day => {
        dayOptionsHtml += `<option value="${day.value}">${day.label}</option>`;
    });

    const dayHtml = `
        <div class="oc-day-section" data-day-index="${newDayIndex}">
            <div class="oc-day-header">
                <div class="oc-day-controls">
                    <select class="oc-day-select" name="flexible_schedule[${newDayIndex}][day]">
                        ${dayOptionsHtml}
                    </select>
                    <button type="button" class="button oc-add-hour">+ Adaugă Oră</button>
                    <button type="button" class="button oc-remove-day">Șterge Zi</button>
                </div>
            </div>
            <div class="oc-hours-container" id="hours-container-${newDayIndex}">
                <!-- Hours will be added here -->
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', dayHtml);
    // Clean('Day added successfully');
};

// Add Hour function
window.addHour = function(dayIndex) {
    // Clean('=== ADD HOUR FUNCTION CALLED ===', dayIndex);
    
    const hoursContainer = document.getElementById(`hours-container-${dayIndex}`);
    if (!hoursContainer) {
        console.error('Hours container not found for day:', dayIndex);
        return;
    }

    const hourCount = hoursContainer.querySelectorAll('.oc-hour-row').length;
    const newHourIndex = hourCount;

    // Time options (8:00 to 22:00 with 1-hour ranges)
    const timeOptions = [];
    for (let hour = 8; hour <= 21; hour++) {
        const startTime = `${hour.toString().padStart(2, '0')}:00`;
        const endTime = `${(hour + 1).toString().padStart(2, '0')}:00`;
        timeOptions.push({
            value: `${startTime}-${endTime}`,
            label: `${startTime} - ${endTime}`
        });
        
        // Add half-hour slot
        if (hour < 21) {
            const startTime30 = `${hour.toString().padStart(2, '0')}:30`;
            const endTime30 = `${(hour + 1).toString().padStart(2, '0')}:30`;
            timeOptions.push({
                value: `${startTime30}-${endTime30}`,
                label: `${startTime30} - ${endTime30}`
            });
        }
    }

    let timeOptionsHtml = '<option value="">Selectează ora</option>';
    timeOptions.forEach(time => {
        timeOptionsHtml += `<option value="${time.value}">${time.label}</option>`;
    });

    // Course options
    let courseOptionsHtml = '<option value="">Selectează cursul</option>';
    if (window.courseTerms && window.courseTerms.length > 0) {
        window.courseTerms.forEach(term => {
            courseOptionsHtml += `<option value="${term.term_id || term.id}">${term.name}</option>`;
        });
    }

    const hourHtml = `
        <div class="oc-hour-row" data-hour-index="${newHourIndex}">
            <div class="oc-hour-controls">
                <select class="oc-time-select" name="flexible_schedule[${dayIndex}][hours][${newHourIndex}][time]">
                    ${timeOptionsHtml}
                </select>
                <div class="oc-rooms-grid">
                    <select name="flexible_schedule[${dayIndex}][hours][${newHourIndex}][room1]" class="oc-room-select">
                        ${courseOptionsHtml}
                    </select>
                    <select name="flexible_schedule[${dayIndex}][hours][${newHourIndex}][room2]" class="oc-room-select">
                        ${courseOptionsHtml}
                    </select>
                    <select name="flexible_schedule[${dayIndex}][hours][${newHourIndex}][room3]" class="oc-room-select">
                        ${courseOptionsHtml}
                    </select>
                    <select name="flexible_schedule[${dayIndex}][hours][${newHourIndex}][room4]" class="oc-room-select">
                        ${courseOptionsHtml}
                    </select>
                </div>
                <button type="button" class="button oc-remove-hour">Șterge Oră</button>
            </div>
        </div>
    `;

    hoursContainer.insertAdjacentHTML('beforeend', hourHtml);
    
    // Force refresh course options in case they weren't available initially
    setTimeout(() => {
        const newHourRow = hoursContainer.querySelector(`[data-hour-index="${newHourIndex}"]`);
        if (newHourRow && window.courseTerms && window.courseTerms.length > 0) {
            const roomSelects = newHourRow.querySelectorAll('.oc-room-select');
            let freshCourseOptionsHtml = '<option value="">Selectează cursul</option>';
            window.courseTerms.forEach(term => {
                freshCourseOptionsHtml += `<option value="${term.term_id || term.id}">${term.name}</option>`;
            });
            
                roomSelects.forEach(select => {
                const currentValue = select.value;
                select.innerHTML = freshCourseOptionsHtml;
                select.value = currentValue; // Restore selection if any
                });

        }
    }, 100);
    

};

// Global function to refresh all course dropdowns
window.refreshAllCourseDropdowns = function() {
    if (!window.courseTerms || window.courseTerms.length === 0) {
        // courseTerms not available for refreshing dropdowns
        return;
    }
    
    let courseOptionsHtml = '<option value="">Selectează cursul</option>';
    window.courseTerms.forEach(term => {
        courseOptionsHtml += `<option value="${term.term_id || term.id}">${term.name}</option>`;
    });
    
    const allSalaSelects = document.querySelectorAll('.oc-room-select');
    let refreshedCount = 0;
    
    allSalaSelects.forEach(select => {
        const currentValue = select.value;
        const $select = jQuery(select);
        
        // Update options
        select.innerHTML = courseOptionsHtml;
        
        // Try multiple methods to restore the value
        if (currentValue) {
            select.value = currentValue;
            $select.val(currentValue);
            $select.find(`option[value="${currentValue}"]`).prop('selected', true);
            
            // Verify it worked
            if (select.value === currentValue) {
                refreshedCount++;
            } else {
                // Failed to restore value to dropdown
            }
        }
    });
    

};

// Remove functions
window.removeDay = function(dayIndex) {
    const dayElement = document.querySelector(`[data-day-index="${dayIndex}"]`);
    if (dayElement) {
        dayElement.remove();
        // Clean('Day removed:', dayIndex);
    }
};

window.removeHour = function(dayIndex, hourIndex) {
    const hourElement = document.querySelector(`[data-day-index="${dayIndex}"] [data-hour-index="${hourIndex}"]`);
    if (hourElement) {
        hourElement.remove();
        // Clean('Hour removed:', dayIndex, hourIndex);
    }
};

// Save function
window.saveFlexibleSchedule = function() {
    const formData = new FormData();
    formData.append('action', 'oc_save_flexible_schedule');
    formData.append('_ajax_nonce', window.ocAjax.nonce);

    // Collect all form data
    const scheduleData = {};
    const dayElements = document.querySelectorAll('.oc-day-section');
    
    dayElements.forEach((dayElement, dayIndex) => {
        const daySelect = dayElement.querySelector('.oc-day-select');
        const dayValue = daySelect ? daySelect.value : '';

        
        if (dayValue) {
            scheduleData[dayIndex] = {
                day: dayValue,
                hours: {}
            };

            const hourElements = dayElement.querySelectorAll('.oc-hour-row');

            
            hourElements.forEach((hourElement, hourIndex) => {
                const timeSelect = hourElement.querySelector('.oc-time-select');
                const timeValue = timeSelect ? timeSelect.value : '';

                
                if (timeValue) {
                    const roomSelects = hourElement.querySelectorAll('.oc-room-select');

                    
                    const roomData = {
                        time: timeValue,
                        room1: roomSelects[0] ? roomSelects[0].value : '',
                        room2: roomSelects[1] ? roomSelects[1].value : '',
                        room3: roomSelects[2] ? roomSelects[2].value : '',
                        room4: roomSelects[3] ? roomSelects[3].value : ''
                    };
                    

                    
                    // Check for duplicate courses in the same time slot
                    const coursesInHour = [roomData.room1, roomData.room2, roomData.room3, roomData.room4].filter(val => val && val !== '');
                    const uniqueCourses = [...new Set(coursesInHour)];
                    if (coursesInHour.length !== uniqueCourses.length) {
                        // Duplicate courses detected - handled by backend validation
                    }
                    
                    scheduleData[dayIndex].hours[hourIndex] = roomData;
                    
                    // Count non-empty rooms
                    const nonEmptySalas = Object.values(roomData).filter(val => val && val !== 'time').filter(val => val !== '').length;

                } else {

                }
            });
            

        }
    });


    formData.append('schedule_data', JSON.stringify(scheduleData));

    // Show loading
    const saveButton = document.getElementById('oc-save-flexible');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Se salvează...';
    } else {
        console.error('❌ [Schedule Save] Save button NOT FOUND when trying to disable!');
    }

    // Send AJAX request
    fetch(window.ocAjax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Clean('Response status:', response.status);
        return response.text().then(text => {
            // Clean('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {

        
        if (data.success) {

            alert('Programul a fost salvat cu succes!');
            // Refresh preview after successful save
            setTimeout(function() {
                window.refreshPreview();
            }, 500);
            // Reload existing schedule
            loadExistingScheduleIntoForm();
        } else {
            console.error('Save failed:', data);
            alert('Eroare la salvare: ' + (data.data || data.message || 'Eroare necunoscută'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Eroare la salvare: ' + error.message);
    })
    .finally(() => {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Salvare Orar';
        }
    });
};

</script>

</div><!-- /.oc-admin-container -->
