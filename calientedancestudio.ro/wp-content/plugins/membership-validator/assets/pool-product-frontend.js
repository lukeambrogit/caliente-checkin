/**
 * Pool Product Manager - Frontend JavaScript
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

(function($) {
    'use strict';
    
    // Obiectul principal
    var PoolProductFrontend = {
        
        settings: {
            minSelections: 1,
            maxSelections: 999,
            isElementor: false,
            formSelector: '.oc-pool-ui',
            buttonSelector: '.single_add_to_cart_button'
        },
        
        init: function() {
            this.detectEnvironment();
            this.bindEvents();
            this.initValidation();
            this.setupSlots();
            this.hideOldDropdowns();
        },
        
        detectEnvironment: function() {
            // Detectează dacă rulează în Elementor
            this.settings.isElementor = this.isElementorPage();
            
            if (this.settings.isElementor) {
                this.settings.formSelector = '.oc-pool-elementor .oc-pool-ui';
                this.settings.buttonSelector = '.elementor-button';
            }
            
            // Extrage setările din localized data
            if (typeof ocPoolData !== 'undefined') {
                // Setările vor fi disponibile din localization
                this.debug('Pool data loaded', ocPoolData);
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Event listeners pentru validare
            $(document).on('change', this.settings.formSelector + ' input[name="oc_pool_selections[]"]', function() {
                self.validateSelections();
            });
            
            $(document).on('change', this.settings.formSelector + ' select[name="oc_pool_selections[]"]', function() {
                self.validateSelections();
            });
            
            // Event listeners pentru slot-uri
            $(document).on('click', '.slot-option', function() {
                self.handleSlotClick($(this));
            });
            
            // Event listeners pentru checkbox-uri - click pe întregul container
            $(document).on('click', '.oc-variation-item', function(e) {
                var $checkbox = $(this).find('input[type="checkbox"]');
                
                // Dacă click-ul este pe checkbox, îl lăsăm să-și facă treaba natural
                if ($(e.target).is('input[type="checkbox"]')) {
                    return;
                }
                
                // Pentru orice alt click în item (inclusiv pe label), togglează checkbox-ul manual
                if ($checkbox.length && !$checkbox.is(':disabled')) {
                    // Prevenim comportamentul natural al label-ului
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Togglează manual
                    var newState = !$checkbox.is(':checked');
                    $checkbox.prop('checked', newState);
                    
                    // Trigger change manual
                    $checkbox.trigger('change');
                    
                } else {
                    // No action - disabled or no checkbox found
                }
            });
            
            // Event listener pentru schimbarea checkbox-urilor
            $(document).on('change', '.oc-variation-item input[type="checkbox"]', function() {
                self.handleCheckboxChange($(this));
            });
            
            // Hover effects pentru slot-uri
            $(document).on('mouseenter', '.slot-option', function() {
                self.handleSlotHover($(this), true);
            });
            
            $(document).on('mouseleave', '.slot-option', function() {
                self.handleSlotHover($(this), false);
            });
            
            // Submit form validation
            $(document).on('submit', this.settings.formSelector, function(e) {
                if (!self.validateSelections()) {
                    e.preventDefault();
                    self.showError('Nu ai selectat minimul de ' + self.settings.minSelections + ' opțiuni.');
                }
            });
            
            // Quantity change
            $(document).on('change', '#oc_pool_quantity', function() {
                self.handleQuantityChange($(this));
            });
        },
        
        initValidation: function() {
            // Extrage setările de validare din formular
            var $form = $(this.settings.formSelector);
            if ($form.length) {
                // Încarcă din data attributes sau config global
                this.settings.minSelections = parseInt($form.data('min-selections')) || 1;
                this.settings.maxSelections = parseInt($form.data('max-selections')) || 999;
            }
            
            // Validare inițială
            this.validateSelections();
        },
        
        setupSlots: function() {
            // Configurează slot-urile pentru radio buttons
            $('.woocommerce-slot-section').each(function() {
                var $slot = $(this);
                var slotNumber = $slot.data('slot');
                
                // Setează primul slot ca empty by default
                $slot.find('.empty-option input[type="radio"]').prop('checked', true);
                $slot.find('.empty-option').addClass('selected active');
            });
        },
        
        hideOldDropdowns: function() {
            // Ascunde TOATE dropdown-urile vechi de sloturi
            $('.oc-pool-slots select, select[data-slot], .select_container, select[name="oc_pool_selections[]"]').hide();
            $('.oc-pool-slots').hide(); // Ascunde containerul vechi complet
        },
        
        // =============================================================================
        // Event Handlers
        // =============================================================================
        
        handleSlotClick: function($option) {
            if ($option.find('input[type="radio"]').is(':disabled')) return;
            
            var $slot = $option.closest('.woocommerce-slot-section');
            var $radio = $option.find('input[type="radio"]');
            
            // Elimină selecția din toate opțiunile din slot
            $slot.find('.slot-option').removeClass('selected active');
            
            // Adaugă selecția la opțiunea curentă
            $option.addClass('selected active');
            
            // Activează radio button
            $radio.prop('checked', true).trigger('change');
            
            this.debug('Slot clicked', {
                slot: $slot.data('slot'),
                value: $radio.val()
            });
        },
        
        handleSlotHover: function($option, isEnter) {
            if (!$option.find('input[type="radio"]').is(':checked') && 
                !$option.find('input[type="radio"]').is(':disabled')) {
                if (isEnter) {
                    $option.addClass('hover');
                } else {
                    $option.removeClass('hover');
                }
            }
        },
        
        handleCheckboxChange: function($checkbox) {
            
            this.validateSelections();
            
            this.debug('Checkbox changed', {
                variation_id: $checkbox.val(),
                checked: $checkbox.is(':checked')
            });
        },
        
        handleQuantityChange: function($quantity) {
            var newQty = parseInt($quantity.val()) || 1;
            
            // Validare cantitate
            if (newQty < 1) {
                $quantity.val(1);
                newQty = 1;
            }
            
            this.debug('Quantity changed', { quantity: newQty });
            
            // Re-validează selecțiile pentru cantitate nouă
            this.validateSelections();
        },
        
        // =============================================================================
        // Validation
        // =============================================================================
        
        validateSelections: function() {
            var $form = $(this.settings.formSelector);
            var selected = this.getSelectedCount();
            var $submit = $form.find(this.settings.buttonSelector);
            
            if (selected < this.settings.minSelections) {
                this.setSubmitState($submit, false, 'Selectează cel puțin ' + this.settings.minSelections + ' opțiuni');
                return false;
            } else if (this.settings.maxSelections < 999 && selected > this.settings.maxSelections) {
                this.setSubmitState($submit, false, 'Poți selecta maximum ' + this.settings.maxSelections + ' opțiuni');
                return false;
            } else {
                this.setSubmitState($submit, true, 'Adaugă în coș');
                return true;
            }
        },
        
        getSelectedCount: function() {
            var $form = $(this.settings.formSelector);
            
            return $form.find('input[name="oc_pool_selections[]"]:checked, select[name="oc_pool_selections[]"]')
                .filter(function() {
                    return $(this).val() !== '';
                }).length;
        },
        
        setSubmitState: function($submit, enabled, text) {
            $submit.prop('disabled', !enabled);
            if (text) {
                $submit.text(text);
            }
            
            // Visual feedback
            if (enabled) {
                $submit.removeClass('oc-pool-disabled');
            } else {
                $submit.addClass('oc-pool-disabled');
            }
        },
        
        // =============================================================================
        // AJAX și Server Communication
        // =============================================================================
        
        updateSelections: function(selections) {
            var self = this;
            var $form = $(this.settings.formSelector);
            var packageId = $form.find('input[name="add-to-cart"]').val();
            
            if (!packageId || !ocPoolData) return;
            
            $.post(ocPoolData.ajaxUrl, {
                action: 'oc_pool_update_selections',
                package_id: packageId,
                selections: selections,
                security: ocPoolData.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.debug('Selections updated', response.data);
                    self.displaySelectionInfo(response.data);
                } else {
                    self.showError('Eroare la actualizarea selecțiilor: ' + response.data);
                }
            })
            .fail(function() {
                self.showError('Eroare de comunicare cu serverul.');
            });
        },
        
        displaySelectionInfo: function(data) {
            // Afișează informații despre selecții (opțional)
            var $container = $('.oc-pool-selection-info');
            if (!$container.length) return;
            
            var html = '<div class="oc-pool-selections-summary">';
            html += '<h4>Selecțiile tale:</h4><ul>';
            
            data.selections.forEach(function(selection) {
                var stockIndicator = selection.in_stock ? '✅' : '❌';
                html += '<li>' + stockIndicator + ' ' + selection.name;
                if (selection.price) {
                    html += ' - ' + selection.price;
                }
                html += '</li>';
            });
            
            html += '</ul></div>';
            $container.html(html);
        },
        
        // =============================================================================
        // UI Feedback
        // =============================================================================
        
        showError: function(message) {
            // Afișează eroare în UI
            var $container = $('.oc-pool-ui');
            var $existing = $container.find('.oc-pool-error');
            
            if ($existing.length) {
                $existing.remove();
            }
            
            var html = '<div class="oc-pool-error notice notice-error" style="margin: 10px 0; padding: 10px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">';
            html += '<p>' + message + '</p>';
            html += '</div>';
            
            $container.prepend(html);
            
            // Auto-hide după 5 secunde
            setTimeout(function() {
                $('.oc-pool-error').fadeOut();
            }, 5000);
        },
        
        showSuccess: function(message) {
            // Afișează mesaj de succes
            var $container = $('.oc-pool-ui');
            var html = '<div class="oc-pool-success notice notice-success" style="margin: 10px 0; padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">';
            html += '<p>' + message + '</p>';
            html += '</div>';
            
            $container.prepend(html);
            
            // Auto-hide după 3 secunde
            setTimeout(function() {
                $('.oc-pool-success').fadeOut();
            }, 3000);
        },
        
        setLoading: function(isLoading) {
            var $form = $(this.settings.formSelector);
            
            if (isLoading) {
                $form.addClass('oc-pool-updating');
            } else {
                $form.removeClass('oc-pool-updating');
            }
        },
        
        // =============================================================================
        // Utility Functions
        // =============================================================================
        
        isElementorPage: function() {
            // Verifică dacă rulează în Elementor
            return $('body').hasClass('elementor-page') ||
                   $('[data-elementor-type]').length > 0 ||
                   $('.elementor-widget-woocommerce-product-add-to-cart').length > 0;
        },
        
        getSelectedVariations: function() {
            var selections = [];
            var $form = $(this.settings.formSelector);
            
            $form.find('input[name="oc_pool_selections[]"]:checked').each(function() {
                var value = $(this).val();
                if (value && value !== '') {
                    selections.push(parseInt(value));
                }
            });
            
            return selections;
        },
        
        debug: function(message, data) {
            // Debug removed for production
        }
    };
    
    // Auto-initialize pentru backward compatibility
    var LegacyScript = {
        init: function() {
            // JavaScript minimal pentru funcționalitate de bază (din codul original)
            
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
            
            // Click pe checkbox-uri folosind event delegation - întregul rând
            $(document).on('click', '.oc-variation-item', function(e) {
                var $checkbox = $(this).find('input[type="checkbox"]');
                
                // Dacă click-ul este pe checkbox, îl lăsăm să-și facă treaba natural
                if ($(e.target).is('input[type="checkbox"]')) {
                    return;
                }
                
                // Pentru orice alt click în item, togglează checkbox-ul manual
                if ($checkbox.length && !$checkbox.is(':disabled')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var newState = !$checkbox.is(':checked');
                    $checkbox.prop('checked', newState);
                    $checkbox.trigger('change');
                }
            });
            
            // Handler pentru schimbarea checkbox-urilor
            $(document).on('change', '.oc-variation-item input[type="checkbox"]', function() {
                // Legacy checkbox change handler
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
        }
    };
    
    // Initialize când documentul este gata
    $(document).ready(function() {
        // Verifică dacă avem date localized moderne
        if (typeof ocPoolData !== 'undefined') {
            PoolProductFrontend.init();
        } else {
            // Fallback la scripul legacy pentru compatibility
            LegacyScript.init();
        }
    });
    
    // Re-initialize după AJAX-ul Elementor
    $(document).ajaxComplete(function() {
        if (PoolProductFrontend.settings.isElementor) {
            PoolProductFrontend.validateSelections();
        }
    });
    
    // Make functions globally available
    window.PoolProductFrontend = PoolProductFrontend;
    
})(jQuery);
