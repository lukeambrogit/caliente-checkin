/**
 * Dashboard JavaScript for Membership Validator Core
 * 
 * @package MembershipValidatorCore
 * @since 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // ADD-ON toggle functionality
    $('.oc-toggle-addon').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $addonItem = $button.closest('.oc-addon-item');
        var addonId = $button.data('addon');
        var action = $button.data('action');
        
        // Prevent double clicks
        if ($button.prop('disabled')) {
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        $addonItem.addClass('loading');
        
        var originalText = $button.text();
        $button.text(action === 'activate' ? ocDashboard.strings.activating : ocDashboard.strings.deactivating);
        
        // AJAX request
        $.ajax({
            url: ocDashboard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'oc_toggle_addon',
                nonce: ocDashboard.nonce,
                addon_id: addonId,
                action_type: action
            },
            success: function(response) {
                if (response.success) {
                    // Update UI based on new status
                    updateAddonUI($addonItem, $button, response.data.new_status);
                    
                    // Show success message
                    showNotice(response.data.message, 'success');
                    
                    // Reload page after 1 second to refresh menu structure
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                    
                } else {
                    showNotice(response.data || ocDashboard.strings.error, 'error');
                    resetButtonState($button, originalText);
                }
            },
            error: function(xhr, status, error) {
                showNotice(ocDashboard.strings.error, 'error');
                resetButtonState($button, originalText);
            },
            complete: function() {
                $button.prop('disabled', false);
                $addonItem.removeClass('loading');
            }
        });
    });
    
    /**
     * Update ADD-ON UI after status change
     */
    function updateAddonUI($addonItem, $button, newStatus) {
        var $statusBadge = $addonItem.find('.oc-status-badge');
        
        if (newStatus === 'active') {
            $addonItem.removeClass('inactive').addClass('active');
            $statusBadge.removeClass('oc-status-inactive').addClass('oc-status-active')
                       .text(ocDashboard.strings.active || 'Active');
            $button.removeClass('button-primary').addClass('button-secondary')
                   .text(ocDashboard.strings.deactivate || 'Deactivate')
                   .data('action', 'deactivate');
        } else {
            $addonItem.removeClass('active').addClass('inactive');
            $statusBadge.removeClass('oc-status-active').addClass('oc-status-inactive')
                       .text(ocDashboard.strings.inactive || 'Inactive');
            $button.removeClass('button-secondary').addClass('button-primary')
                   .text(ocDashboard.strings.activate || 'Activate')
                   .data('action', 'activate');
        }
    }
    
    /**
     * Reset button to original state
     */
    function resetButtonState($button, originalText) {
        $button.text(originalText).prop('disabled', false);
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Insert after the page title
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Handle manual dismiss
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Confirm deactivation for critical ADD-ONS
     */
    $('.oc-toggle-addon[data-action="deactivate"]').on('click', function(e) {
        var addonName = $(this).closest('.oc-addon-item').find('h3').text();
        
        if (!confirm('Are you sure you want to deactivate "' + addonName + '"? This may affect functionality.')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
    
    /**
     * Tab switching for Core Settings
     */
    if ($('.oc-core-settings').length) {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            
            // Update tab navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Update tab content
            $('.oc-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });
    }
    
    /**
     * Handle quick action buttons
     */
    $('.oc-action-button').on('click', function(e) {
        // Add a subtle animation
        $(this).css('transform', 'scale(0.98)');
        setTimeout(() => {
            $(this).css('transform', '');
        }, 150);
    });
    
    /**
     * Database check functionality
     */
    $('#oc-check-database-btn').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.find('span:not(.dashicons)').text();
        
        // Prevent double clicks
        if ($button.prop('disabled')) {
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        $button.find('span:not(.dashicons)').text(ocDashboard.strings.checking || 'Checking...');
        $button.find('.dashicons').removeClass('dashicons-database').addClass('dashicons-update');
        
        // AJAX request
        $.ajax({
            url: ocDashboard.ajaxUrl,
            method: 'POST',
            data: {
                action: 'oc_check_database',
                nonce: ocDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showNotice(response.data.message, 'success');
                    
                    // Update button if tables were created
                    if (!response.data.table_existed) {
                        $button.find('span:not(.dashicons)').text(ocDashboard.strings.fixed || 'Fixed!');
                        setTimeout(function() {
                            $button.find('span:not(.dashicons)').text(originalText);
                        }, 3000);
                    }
                    
                } else {
                    showNotice(response.data || ocDashboard.strings.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotice(ocDashboard.strings.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-database');
                if ($button.find('span:not(.dashicons)').text() === (ocDashboard.strings.checking || 'Checking...')) {
                    $button.find('span:not(.dashicons)').text(originalText);
                }
            }
        });
    });
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[title]').tooltip({
            position: { my: "center bottom-20", at: "center top", using: function( position, feedback ) {
                $(this).css( position );
                $("<div>")
                    .addClass("arrow")
                    .addClass(feedback.vertical)
                    .addClass(feedback.horizontal)
                    .appendTo(this);
            }}
        });
    }
});
