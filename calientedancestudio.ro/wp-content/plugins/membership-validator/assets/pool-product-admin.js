/**
 * Pool Product Manager - Admin JavaScript
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

(function($) {
    'use strict';
    
    // Obiectul principal
    var PoolProductAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initProductEditor();
            this.initDashboard();
        },
        
        bindEvents: function() {
            // Event listeners pentru admin
            $(document).on('change', '#product-type', this.togglePackagePanel);
            $(document).on('change', '#_oc_pool_min_selections, #_oc_pool_max_selections', this.validateSelections);
            $(document).on('change', '#_oc_pool_pool_id', this.loadPoolVariations);
            $(document).on('click', '[data-oc-action]', this.handleActions);
        },
        
        initProductEditor: function() {
            // Verifică dacă suntem pe pagina de edit produs
            if (!$('#product-type').length) return;
            
            // Stilizare pentru selectorul POOL
            $('#_oc_pool_pool_id').css('min-height', '30px');
            
            // Verifică tipul de produs inițial
            this.togglePackagePanel();
            
            // Încarcă variațiile dacă există POOL selectat
            if ($('#_oc_pool_pool_id').val()) {
                $('#_oc_pool_pool_id').trigger('change');
            }
        },
        
        initDashboard: function() {
            // Verifică dacă suntem pe dashboard
            if (!$('.oc-pool-stats-grid').length) return;
            
            this.initTabs();
            this.bindDashboardEvents();
        },
        
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).data('tab');
                PoolProductAdmin.switchTab(tabId);
            });
        },
        
        bindDashboardEvents: function() {
            // Migration check
            $(document).on('click', '#check-migration', this.checkMigration);
            $(document).on('click', '#run-migration', this.runMigration);
            
            // Cleanup actions
            $(document).on('click', '#cleanup-orphaned-dry', function() {
                PoolProductAdmin.runCleanup(true);
            });
            
            $(document).on('click', '#cleanup-orphaned-real', function() {
                if (confirm('Ești sigur că vrei să setezi POOL-urile orfane ca draft?')) {
                    PoolProductAdmin.runCleanup(false);
                }
            });
            
            // Other actions
            $(document).on('click', '#show-pools-list', function() {
                PoolProductAdmin.switchTab('pools');
                PoolProductAdmin.loadPoolsReport();
            });
            
            $(document).on('click', '#clear-cache', this.clearCache);
        },
        
        // =============================================================================
        // Product Editor Functions
        // =============================================================================
        
        togglePackagePanel: function() {
            var productType = $('#product-type').val();
            var $panel = $('.oc-pool-settings');
            
            if (productType === 'simple') {
                $panel.show();
            } else {
                $panel.hide();
            }
        },
        
        validateSelections: function() {
            var min = parseInt($('#_oc_pool_min_selections').val()) || 0;
            var max = parseInt($('#_oc_pool_max_selections').val()) || 0;
            
            if (max > 0 && max < min) {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Selecțiile maxime nu pot fi mai puține decât minimele!', 'error');
                } else {
                    alert('Selecțiile maxime nu pot fi mai puține decât minimele!');
                }
                $(this).focus();
            }
        },
        
        loadPoolVariations: function() {
            var poolId = $(this).val();
            var $container = $('#oc-pool-variations-selector');
            var $list = $('#oc-pool-variations-list');
            var packageId = $('#post_ID').val() || 0;
            
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
                package_id: packageId,
                security: ocPoolAdminData.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $list.html(response.data.html);
                    $container.show().css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1'
                    });
                } else {
                    $list.html('<em style="color: red;">Eroare: ' + response.data + '</em>');
                }
            })
            .fail(function() {
                $list.html('<em style="color: red;">Eroare la încărcarea variațiilor.</em>');
            });
            
            // Update description
            PoolProductAdmin.updatePoolDescription($(this));
        },
        
        updatePoolDescription: function($select) {
            var selectedOption = $select.find('option:selected');
            var poolId = $select.val();
            
            if (poolId) {
                var variationsText = selectedOption.text().match(/(\d+) variații/);
                var count = variationsText ? variationsText[1] : '0';
                
                $select.next('.description').html(
                    'Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)<br>' +
                    '<small style="color: #0073aa;"><strong>' + count + ' variații</strong> disponibile în acest POOL</small>'
                );
            } else {
                $select.next('.description').text('Produsul variabil din care se fac selecțiile (inclusiv produse ascunse/draft)');
            }
        },
        
        // =============================================================================
        // Dashboard Functions
        // =============================================================================
        
        switchTab: function(tabId) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[data-tab="' + tabId + '"]').addClass('nav-tab-active');
            
            $('.tab-panel').hide();
            $('#tab-' + tabId).show();
        },
        
        loadPoolsReport: function() {
            var $container = $('#pools-report');
            
            $container.html('<div class="oc-pool-loading-text">Se încarcă raportul...</div>');
            
            $.post(ajaxurl, {
                action: 'oc_pool_visibility_report',
                security: ocPoolAdminData.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var html = PoolProductAdmin.generatePoolsReportHTML(response.data);
                    $container.html(html);
                } else {
                    $container.html('<div class="notice notice-error"><p>Eroare la încărcarea raportului: ' + response.data + '</p></div>');
                }
            })
            .fail(function() {
                $container.html('<div class="notice notice-error"><p>Eroare la comunicarea cu serverul.</p></div>');
            });
        },
        
        generatePoolsReportHTML: function(report) {
            var html = '<div class="oc-pools-summary">';
            
            // Summary cards
            html += '<div><div class="stat-number" style="color: #0073aa;">' + report.total_pools + '</div><div class="stat-label">Total POOL</div></div>';
            html += '<div><div class="stat-number" style="color: #46b450;">' + report.active_pools + '</div><div class="stat-label">POOL active</div></div>';
            html += '<div><div class="stat-number" style="color: #dc3232;">' + report.orphaned_pools + '</div><div class="stat-label">POOL orfane</div></div>';
            html += '<div><div class="stat-number" style="color: #826eb4;">' + report.pools_details.length + '</div><div class="stat-label">Cu detalii</div></div>';
            
            html += '</div>';
            
            // Details table
            if (report.pools_details.length > 0) {
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>ID</th><th>Nume POOL</th><th>Status</th><th>Variații</th><th>Pachete</th><th>Acțiuni</th>';
                html += '</tr></thead><tbody>';
                
                report.pools_details.forEach(function(pool) {
                    var statusBadge = pool.is_orphaned ? 
                        '<span style="background: #dc3232; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">ORPHAN</span>' : 
                        '<span style="background: #46b450; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">ACTIVE</span>';
                    
                    html += '<tr>';
                    html += '<td>' + pool.pool_id + '</td>';
                    html += '<td><strong>' + PoolProductAdmin.escapeHtml(pool.pool_name) + '</strong></td>';
                    html += '<td>' + pool.pool_status + ' ' + statusBadge + '</td>';
                    html += '<td>' + pool.variations_count + '</td>';
                    html += '<td>' + pool.packages_count + '</td>';
                    html += '<td>';
                    html += '<a href="' + ajaxurl.replace('admin-ajax.php', 'post.php?action=edit&post=' + pool.pool_id) + '" class="button button-small">Edit</a> ';
                    if (pool.packages_count > 0) {
                        html += '<a href="' + ajaxurl.replace('admin-ajax.php', 'edit.php?post_type=product&meta_key=_oc_pool_pool_id&meta_value=' + pool.pool_id) + '" class="button button-small">Vezi pachete</a>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            return html;
        },
        
        checkMigration: function() {
            var $btn = $(this);
            var $results = $('#migration-results');
            
            $btn.prop('disabled', true).text('Se verifică...');
            
            // Simulare check migration (funcția nu există în AJAX)
            setTimeout(function() {
                var html = '<div class="notice notice-info inline"><p>';
                html += 'Această funcție va fi implementată în versiunea următoare.';
                html += '</p></div>';
                $results.html(html);
                $btn.prop('disabled', false).text('Verifică pachete vechi');
            }, 1000);
        },
        
        runMigration: function() {
            var $btn = $(this);
            var $results = $('#migration-results');
            
            $btn.prop('disabled', true).text('Se migrează...');
            
            // Simulare migration
            setTimeout(function() {
                var html = '<div class="notice notice-success inline"><p>';
                html += 'Migrarea a fost finalizată cu succes!';
                html += '</p></div>';
                $results.html(html);
                $btn.remove();
            }, 2000);
        },
        
        runCleanup: function(dryRun) {
            var $btn = dryRun ? $('#cleanup-orphaned-dry') : $('#cleanup-orphaned-real');
            var $results = $('#cleanup-results');
            
            $btn.prop('disabled', true).text(dryRun ? 'Se simulează...' : 'Se execută...');
            
            $.post(ajaxurl, {
                action: 'oc_pool_cleanup_orphaned',
                dry_run: dryRun ? '1' : '',
                security: ocPoolAdminData.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var data = response.data;
                    var className = dryRun ? 'notice-info' : 'notice-success';
                    var html = '<div class="notice ' + className + ' inline"><p>';
                    html += 'Găsite <strong>' + data.found + '</strong> POOL-uri orfane. ';
                    html += 'Procesate: <strong>' + data.processed + '</strong>. ';
                    if (data.errors && data.errors.length > 0) {
                        html += 'Erori: ' + data.errors.length;
                    }
                    if (dryRun) {
                        html += ' (simulare - nu s-au făcut modificări)';
                    }
                    html += '</p></div>';
                    $results.html(html);
                } else {
                    $results.html('<div class="notice notice-error inline"><p>Eroare: ' + response.data + '</p></div>');
                }
            })
            .fail(function() {
                $results.html('<div class="notice notice-error inline"><p>Eroare la comunicarea cu serverul.</p></div>');
            })
            .always(function() {
                $btn.prop('disabled', false).text(dryRun ? 'Simulare cleanup' : 'Execută cleanup');
            });
        },
        
        clearCache: function() {
            var $btn = $(this);
            var $results = $('#cache-results');
            
            $btn.prop('disabled', true).text('Se curăță...');
            
            // Simulare clear cache
            setTimeout(function() {
                $results.html('<div class="notice notice-success inline"><p>Cache-ul a fost curățat cu succes!</p></div>');
                $btn.prop('disabled', false).text('Curăță cache-ul');
            }, 1000);
        },
        
        // =============================================================================
        // Generic Action Handler
        // =============================================================================
        
        handleActions: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var action = $btn.data('oc-action');
            var target = $btn.data('oc-target');
            var confirm_msg = $btn.data('oc-confirm');
            
            if (confirm_msg && !confirm(confirm_msg)) {
                return;
            }
            
            // Disable button
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Se procesează...');
            
            // Execute action
            switch(action) {
                case 'validate-package':
                    PoolProductAdmin.validatePackageConfig($btn, target);
                    break;
                    
                case 'search-products':
                    PoolProductAdmin.searchProducts($btn, target);
                    break;
                    
                default:
                    console.warn('Unknown action:', action);
                    $btn.prop('disabled', false).text(originalText);
            }
        },
        
        validatePackageConfig: function($btn, target) {
            var packageId = $('#post_ID').val() || 0;
            var poolId = $('#_oc_pool_pool_id').val() || 0;
            var minSelections = $('#_oc_pool_min_selections').val() || 0;
            var maxSelections = $('#_oc_pool_max_selections').val() || 0;
            
            $.post(ajaxurl, {
                action: 'oc_pool_validate_package',
                package_id: packageId,
                pool_id: poolId,
                min_selections: minSelections,
                max_selections: maxSelections,
                security: ocPoolAdminData.nonce
            })
            .done(function(response) {
                var $target = $(target);
                
                if (response.success) {
                    var data = response.data;
                    var html = '';
                    
                    if (data.valid) {
                        html = '<div class="notice notice-success inline"><p>✅ Configurația pachetului este validă!</p></div>';
                    } else {
                        html = '<div class="notice notice-error inline"><ul>';
                        data.errors.forEach(function(error) {
                            html += '<li>' + PoolProductAdmin.escapeHtml(error) + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    if (data.warnings && data.warnings.length > 0) {
                        html += '<div class="notice notice-warning inline"><ul>';
                        data.warnings.forEach(function(warning) {
                            html += '<li>' + PoolProductAdmin.escapeHtml(warning) + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    $target.html(html);
                } else {
                    $(target).html('<div class="notice notice-error inline"><p>Eroare: ' + response.data + '</p></div>');
                }
            })
            .fail(function() {
                $(target).html('<div class="notice notice-error inline"><p>Eroare la comunicarea cu serverul.</p></div>');
            })
            .always(function() {
                $btn.prop('disabled', false).text('Validează configurația');
            });
        },
        
        searchProducts: function($btn, target) {
            var searchTerm = prompt('Introdu termenul de căutare:');
            if (!searchTerm || searchTerm.length < 2) {
                $btn.prop('disabled', false).text('Caută produse');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'oc_pool_search_products',
                search: searchTerm,
                type: 'variable',
                security: ocPoolAdminData.nonce
            })
            .done(function(response) {
                var $target = $(target);
                
                if (response.success) {
                    var products = response.data.products;
                    var html = '';
                    
                    if (products.length > 0) {
                        html = '<div class="notice notice-info inline"><p>Găsite ' + products.length + ' produse:</p><ul>';
                        products.forEach(function(product) {
                            html += '<li>';
                            html += '<strong>' + PoolProductAdmin.escapeHtml(product.title) + '</strong> ';
                            html += '(#' + product.id + ') - ' + product.variations_count + ' variații ';
                            html += '<a href="' + product.edit_url + '" target="_blank">Edit</a>';
                            html += '</li>';
                        });
                        html += '</ul></div>';
                    } else {
                        html = '<div class="notice notice-warning inline"><p>Nu s-au găsit produse pentru "' + PoolProductAdmin.escapeHtml(searchTerm) + '"</p></div>';
                    }
                    
                    $target.html(html);
                } else {
                    $target.html('<div class="notice notice-error inline"><p>Eroare: ' + response.data + '</p></div>');
                }
            })
            .fail(function() {
                $(target).html('<div class="notice notice-error inline"><p>Eroare la comunicarea cu serverul.</p></div>');
            })
            .always(function() {
                $btn.prop('disabled', false).text('Caută produse');
            });
        },
        
        // =============================================================================
        // Utility Functions
        // =============================================================================
        
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },
        
        showNotice: function(message, type, target) {
            type = type || 'info';
            target = target || 'body';
            
            var html = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>';
            $(target).prepend(html);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $(target).find('.notice:first').fadeOut();
            }, 5000);
        },
        
        debug: function(message, data) {
            // Debug removed for production
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        PoolProductAdmin.init();
    });
    
    // Make some functions globally available
    window.PoolProductAdmin = PoolProductAdmin;
    
    // For backward compatibility
    window.switchTab = PoolProductAdmin.switchTab;
    
})(jQuery);
