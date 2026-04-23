<?php
/**
 * Pool Product Manager - Admin Settings Template
 *
 * @package    Membership_Validator_Core
 * @subpackage Pool_Product_Manager
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Obține instanța ADD-ON-ului
$pool_manager = OC_Pool_Product_Manager::get_instance();
$stats = $pool_manager->get_stats();


// Obține raportul de vizibilitate
$visibility = new OC_Pool_Visibility();
$visibility_report = $visibility->get_visibility_report();
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-products"></span>
        Pool Product Manager
    </h1>
    
    <div class="notice notice-info">
        <p><strong>Pool Product Manager</strong> este activ și funcțional în Membership Validator Core.</p>
    </div>
    
    <!-- Statistici principale -->
    <div class="oc-pool-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        
        <!-- Pachete -->
        <div class="oc-pool-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #0073aa;">
                <span class="dashicons dashicons-archive"></span>
                Pachete Active
            </h3>
            <div style="font-size: 36px; font-weight: bold; color: #0073aa;"><?php echo intval($stats['active_packages']); ?></div>
            <p style="margin: 10px 0 0 0; color: #666;">Pachete cu preț fix publicate</p>
            <a href="<?php echo admin_url('edit.php?post_type=product&meta_key=_oc_pool_enabled&meta_value=1'); ?>" class="button button-secondary">
                Vezi toate pachetele
            </a>
        </div>
        
        <!-- Produse POOL -->
        <div class="oc-pool-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #46b450;">
                <span class="dashicons dashicons-networking"></span>
                Produse POOL
            </h3>
            <div style="font-size: 36px; font-weight: bold; color: #46b450;"><?php echo intval($stats['pool_products']); ?></div>
            <p style="margin: 10px 0 0 0; color: #666;">Produse variabile folosite ca POOL</p>
            <button type="button" class="button button-secondary" id="show-pools-list">
                Vezi detalii POOL
            </button>
        </div>
        
        <!-- Versiune -->
        <div class="oc-pool-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #826eb4;">
                <span class="dashicons dashicons-admin-plugins"></span>
                Versiune ADD-ON
            </h3>
            <div style="font-size: 36px; font-weight: bold; color: #826eb4;"><?php echo esc_html($stats['version']); ?></div>
            <p style="margin: 10px 0 0 0; color: #666;">Pool Product Manager</p>
            <a href="https://github.com/remusdesign/pool-product-system" target="_blank" class="button button-secondary">
                Documentație
            </a>
        </div>
        
    </div>
    
    <!-- Tabs pentru secțiuni -->
    <nav class="nav-tab-wrapper" style="margin: 30px 0 20px 0;">
        <a href="#overview" class="nav-tab nav-tab-active" data-tab="overview">Prezentare generală</a>
        <a href="#pools" class="nav-tab" data-tab="pools">Produse POOL</a>
        <a href="#maintenance" class="nav-tab" data-tab="maintenance">Mentenanță</a>
        <a href="#help" class="nav-tab" data-tab="help">Ajutor</a>
    </nav>
    
    <!-- Conținutul tab-urilor -->
    <div class="tab-content">
        
        <!-- Tab: Overview -->
        <div id="tab-overview" class="tab-panel active">
            <h2>Prezentare generală</h2>
            
            <div class="oc-pool-overview-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                
                <!-- Coloana principală -->
                <div>
                    <h3>Ce este Pool Product Manager?</h3>
                    <p>Pool Product Manager permite crearea de <strong>pachete cu preț fix</strong> unde clienții pot selecta din multiple opțiuni dintr-un sau <em>două</em> produse "POOL" (variabile). Este ideal pentru vânzarea de cursuri, servicii sau orice alt tip de pachet personalizabil cu flexibilitate maximă.</p>
                    
                    <h4>Tipuri de pachete disponibile:</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;">
                        <p><strong>🔸 Mod Single POOL:</strong> Clienții selectează din opțiunile unui singur produs POOL</p>
                        <p><strong>🔸 Mod Dual POOL:</strong> Clienții selectează din opțiunile a două produse POOL diferite (ex: cursuri + echipament)</p>
                    </div>
                    
                    <h4>Caracteristici principale:</h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong>Pachete cu preț fix</strong> - Un preț pentru tot pachetul, indiferent de selecții</li>
                        <li><strong>Dual POOL support</strong> - Combină selecții din două produse POOL diferite</li>
                        <li><strong>Configurare flexibilă</strong> - Selecții minime/maxime per POOL, UI customizabil</li>
                        <li><strong>Interface inteligentă</strong> - Checkbox-uri pentru selecții multiple sau radio pentru poziții fixe</li>
                        <li><strong>Integrare completă</strong> - Funcționează seamless cu WooCommerce</li>
                        <li><strong>Compatibilitate Elementor</strong> - Detectare automată și stilizare adaptată</li>
                        <li><strong>SEO optimizat</strong> - Produsele POOL sunt ascunse din căutare</li>
                        <li><strong>Responsive</strong> - Interface adaptabil pe toate dispozitivele</li>
                    </ul>
                    
                    <h4>Cum funcționează:</h4>
                    <ol style="margin-left: 20px;">
                        <li><strong>Creezi produse POOL</strong> (variabile) cu toate opțiunile disponibile</li>
                        <li><strong>Creezi un pachet</strong> (produs simplu) și alegi modul: Single sau Dual POOL</li>
                        <li><strong>Configurezi POOL-urile</strong> și selectezi variațiile disponibile pentru fiecare</li>
                        <li><strong>Stabilești regulile</strong> - selecții minime/maxime per POOL, labels personalizate</li>
                        <li><strong>Configurezi prețul fix</strong> și publici pachetul</li>
                        <li><strong>Clienții selectează</strong> din opțiunile configurate, respectând limitele stabilite</li>
                    </ol>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <div style="background: #f1f1f1; padding: 20px; border-radius: 8px; border-left: 4px solid #0073aa;">
                        <h4 style="margin-top: 0;">Acțiuni rapide</h4>
                        <p>
                            <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                Creează primul pachet
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('edit.php?post_type=product&product_type=variable'); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-networking"></span>
                                Vezi produse variabile
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=membership-validator-dashboard'); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-dashboard"></span>
                                Dashboard principal
                            </a>
                        </p>
                    </div>
                    
                    <?php if ( $visibility_report['orphaned_pools'] > 0 ): ?>
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 20px;">
                        <h4 style="margin-top: 0; color: #856404;">
                            <span class="dashicons dashicons-warning"></span>
                            Atenție
                        </h4>
                        <p>Există <strong><?php echo intval($visibility_report['orphaned_pools']); ?> produse POOL</strong> care nu sunt folosite de nici un pachet. Acestea sunt ascunse din shop dar nu au pachete asociate.</p>
                        <p>
                            <button type="button" class="button button-secondary" onclick="switchTab('maintenance')">
                                Vezi în Mentenanță
                            </button>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
        
        <!-- Tab: Pools -->
        <div id="tab-pools" class="tab-panel" style="display: none;">
            <h2>Produse POOL</h2>
            
            <div id="pools-report">
                <p>Se încarcă raportul produselor POOL...</p>
            </div>
        </div>
        
        <!-- Tab: Maintenance -->
        <div id="tab-maintenance" class="tab-panel" style="display: none;">
            <h2>Mentenanță și Debugging</h2>
            
            <div class="oc-pool-maintenance-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                
                <!-- Migrare -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>
                        <span class="dashicons dashicons-database-import"></span>
                        Migrare din format vechi
                    </h3>
                    <p>Dacă ai folosit versiunea veche a pluginului (mv_pack_*), poți migra datele la noul format (oc_pool_*).</p>
                    
                    <button type="button" class="button button-secondary" id="check-migration">
                        Verifică pachete vechi
                    </button>
                    
                    <div id="migration-results" style="margin-top: 15px;"></div>
                </div>
                
                <!-- Cleanup -->
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>
                        <span class="dashicons dashicons-trash"></span>
                        Cleanup POOL-uri orfane
                    </h3>
                    <p>Produsele POOL care nu sunt folosite de nici un pachet pot fi setate ca draft pentru a fi ascunse complet.</p>
                    
                    <button type="button" class="button button-secondary" id="cleanup-orphaned-dry">
                        Simulare cleanup
                    </button>
                    
                    <button type="button" class="button button-primary" id="cleanup-orphaned-real" style="margin-left: 10px;">
                        Execută cleanup
                    </button>
                    
                    <div id="cleanup-results" style="margin-top: 15px;"></div>
                </div>
                
            </div>
            

        </div>
        
        <!-- Tab: Help -->
        <div id="tab-help" class="tab-panel" style="display: none;">
            <h2>Ajutor și Suport</h2>
            
            <div class="oc-pool-help-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                
                <!-- Documentație -->
                <div>
                    <h3>Documentație</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><a href="https://github.com/remusdesign/pool-product-system/wiki" target="_blank">Wiki complet</a></li>
                        <li><a href="https://github.com/remusdesign/pool-product-system#utilizare" target="_blank">Ghid de utilizare</a></li>
                        <li><a href="https://github.com/remusdesign/pool-product-system#dezvoltare" target="_blank">Documentație pentru dezvoltatori</a></li>
                        <li><a href="https://github.com/remusdesign/pool-product-system/blob/main/CHANGELOG.md" target="_blank">Changelog</a></li>
                    </ul>
                    
                    <h3>Hook-uri disponibile</h3>
                    <h4>Actions:</h4>
                    <code>
                        do_action('oc_pool_package_created', $package_id, $config);<br>
                        do_action('oc_pool_package_updated', $package_id, $config);<br>
                        do_action('oc_pool_selection_added', $package_id, $selection_id);
                    </code>
                    
                    <h4>Filters:</h4>
                    <code>
                        $price = apply_filters('oc_pool_package_price', $price, $package_id);<br>
                        $selections = apply_filters('oc_pool_available_selections', $selections, $pool_id);
                    </code>
                </div>
                
                <!-- Suport -->
                <div>
                    <h3>Suport</h3>
                    <p>Pentru probleme sau întrebări:</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><a href="https://github.com/remusdesign/pool-product-system/issues" target="_blank">Raportează probleme (GitHub Issues)</a></li>
                        <li><a href="https://github.com/remusdesign/pool-product-system/discussions" target="_blank">Discuții comunitate</a></li>
                        <li><a href="mailto:support@example.com">Email suport</a></li>
                    </ul>
                    
                    <h3>Informații sistem</h3>
                    <table class="widefat fixed striped">
                        <tbody>
                            <tr>
                                <td><strong>ADD-ON versiune:</strong></td>
                                <td><?php echo esc_html($stats['version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Core versiune:</strong></td>
                                <td><?php echo defined('OC_PLUGIN_VERSION') ? OC_PLUGIN_VERSION : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>WooCommerce versiune:</strong></td>
                                <td><?php echo class_exists('WooCommerce') ? WC()->version : 'Nu este instalat'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>WordPress versiune:</strong></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>PHP versiune:</strong></td>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
            </div>
        </div>
        
    </div>
</div>

<style>
.oc-pool-stat-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.nav-tab {
    outline: none;
}

.tab-panel {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 8px 8px;
}

.oc-pool-maintenance-grid > div:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

code {
    display: block;
    background: #f1f1f1;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.4;
    margin: 10px 0;
}
</style>

<script type="text/javascript">
jQuery(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        switchTab(tabId);
    });
    
    // Show pools list
    $('#show-pools-list').on('click', function() {
        switchTab('pools');
        loadPoolsReport();
    });
    
    // Migration check
    $('#check-migration').on('click', function() {
        var $btn = $(this);
        var $results = $('#migration-results');
        
        $btn.prop('disabled', true).text('Se verifică...');
        
        $.post(ajaxurl, {
            action: 'oc_pool_check_migration',
            security: '<?php echo wp_create_nonce("oc_pool_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                var data = response.data;
                var html = '<div class="notice notice-info inline"><p>';
                html += 'Găsite <strong>' + data.found + '</strong> pachete în format vechi.';
                if (data.found > 0) {
                    html += ' <button type="button" class="button button-primary" id="run-migration">Migrează acum</button>';
                }
                html += '</p></div>';
                $results.html(html);
            } else {
                $results.html('<div class="notice notice-error inline"><p>Eroare: ' + response.data + '</p></div>');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Verifică pachete vechi');
        });
    });
    
    // Cleanup buttons
    $('#cleanup-orphaned-dry').on('click', function() {
        runCleanup(true);
    });
    
    $('#cleanup-orphaned-real').on('click', function() {
        if (confirm('Ești sigur că vrei să setezi POOL-urile orfane ca draft?')) {
            runCleanup(false);
        }
    });
    

    
    // Functions
    function switchTab(tabId) {
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + tabId + '"]').addClass('nav-tab-active');
        
        $('.tab-panel').hide();
        $('#tab-' + tabId).show();
        
        // Încarcă raportul automat pentru tab-ul pools
        if (tabId === 'pools') {
            loadPoolsReport();
        }
    }
    
    function loadPoolsReport() {
        var $container = $('#pools-report');
        
        
        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'oc_pool_visibility_report',
                security: '<?php echo wp_create_nonce("oc_pool_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                var report = response.data;
                var html = '<div class="oc-pools-summary" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">';
                
                html += '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 6px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' + report.total_pools + '</div>';
                html += '<div style="color: #666;">Total POOL</div></div>';
                
                html += '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 6px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #46b450;">' + report.active_pools + '</div>';
                html += '<div style="color: #666;">POOL active</div></div>';
                
                html += '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 6px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #dc3232;">' + report.orphaned_pools + '</div>';
                html += '<div style="color: #666;">POOL orfane</div></div>';
                
                html += '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 6px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #826eb4;">' + report.pools_details.length + '</div>';
                html += '<div style="color: #666;">Cu detalii</div></div>';
                
                html += '</div>';
                
                // Tabel cu detalii
                if (report.pools_details.length > 0) {
                    html += '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr>';
                    html += '<th>ID</th><th>Nume POOL</th><th>Status</th><th>Variații</th><th>Pachete</th><th>Actions</th>';
                    html += '</tr></thead><tbody>';
                    
                    report.pools_details.forEach(function(pool) {
                        var statusBadge = pool.is_orphaned ? '<span style="background: #dc3232; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">ORPHAN</span>' : '<span style="background: #46b450; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">ACTIVE</span>';
                        
                        html += '<tr>';
                        html += '<td>' + pool.pool_id + '</td>';
                        html += '<td><strong>' + pool.pool_name + '</strong></td>';
                        html += '<td>' + pool.pool_status + ' ' + statusBadge + '</td>';
                        html += '<td>' + pool.variations_count + '</td>';
                        html += '<td>' + pool.packages_count + '</td>';
                        html += '<td><a href="<?php echo admin_url('post.php?action=edit&post='); ?>' + pool.pool_id + '" class="button button-small">Edit</a></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                }
                
                $container.html(html);
            } else {
                $container.html('<div class="notice notice-error"><p>Eroare la încărcarea raportului: ' + response.data + '</p></div>');
            }
        },
        error: function(xhr, status, error) {
            $container.html('<div class="notice notice-error"><p>Eroare la comunicarea cu serverul.</p></div>');
        }
    });
    }
    
    function runCleanup(dryRun) {
        var $btn = dryRun ? $('#cleanup-orphaned-dry') : $('#cleanup-orphaned-real');
        var $results = $('#cleanup-results');
        
        $btn.prop('disabled', true).text(dryRun ? 'Se simulează...' : 'Se execută...');
        
        $.post(ajaxurl, {
            action: 'oc_pool_cleanup_orphaned',
            dry_run: dryRun ? '1' : '',
            security: '<?php echo wp_create_nonce("oc_pool_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                var data = response.data;
                var className = dryRun ? 'notice-info' : 'notice-success';
                var html = '<div class="notice ' + className + ' inline"><p>';
                html += 'Găsite <strong>' + data.found + '</strong> POOL-uri orfane. ';
                html += 'Procesate: <strong>' + data.processed + '</strong>. ';
                if (data.errors.length > 0) {
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
        }).always(function() {
            $btn.prop('disabled', false).text(dryRun ? 'Simulare cleanup' : 'Execută cleanup');
        });
    }
    
    // Make switchTab global for inline onclick
    window.switchTab = switchTab;
});
</script>
