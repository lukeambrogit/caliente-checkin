/**
 * Admin JavaScript for Membership Validator Core - Schedule Manager
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize color pickers
    if (typeof $.fn.wpColorPicker !== 'undefined') {
        $('.color-picker').wpColorPicker({
            change: function(event, ui) {
                // Update preview when color changes
                updatePreview();
            }
        });
    }
    
    // Initialize media uploader for background images
    $('.upload-button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var inputField = button.siblings('input[type="text"]');
        
        var mediaUploader = wp.media({
            title: 'Select Background Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            updatePreview();
        });
        
        mediaUploader.open();
    });
    
    // Remove background image
    $('.remove-image').on('click', function(e) {
        e.preventDefault();
        $(this).siblings('input[type="text"]').val('');
        updatePreview();
    });
    
    // Form submission with validation
    $('form').on('submit', function(e) {
        var hasErrors = false;
        
        // Basic validation
        $(this).find('input[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                hasErrors = true;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            if (typeof window.showNotification === 'function') {
                window.showNotification('Please fill in all required fields.', 'error');
            } else {
                alert('Please fill in all required fields.');
            }
        }
    });
    
    // Preview update function
    function updatePreview() {
        if ($('.oc-preview-section').length) {
            // Trigger preview update via AJAX
            $.ajax({
                url: ocAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'oc_get_schedule_html',
                    nonce: ocAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.oc-preview-content').html(response.data);
                    }
                },
                error: function() {
                    // Preview update failed
                }
            });
        }
    }
    
    // Auto-save functionality (optional)
    var autoSaveTimer;
    $('form input, form select, form textarea').on('change keyup', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Auto-save logic here if needed
            // Auto-save triggered
        }, 2000);
    });
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[title]').tooltip();
    }
    
    // Handle responsive toggles
    $('.responsive-toggle').on('click', function() {
        $(this).toggleClass('active');
        $('.responsive-content').toggle();
    });
});

/**
 * Global functions for admin
 */
window.ocAdmin = window.ocAdmin || {};

/**
 * Update schedule preview
 */
window.ocAdmin.updatePreview = function() {
    if (typeof ocAdmin !== 'undefined') {
        jQuery.ajax({
            url: ocAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'oc_get_schedule_html',
                nonce: ocAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    jQuery('.oc-preview-content').html(response.data);
                }
            },
            error: function() {
                // Preview update failed
            }
        });
    }
};
