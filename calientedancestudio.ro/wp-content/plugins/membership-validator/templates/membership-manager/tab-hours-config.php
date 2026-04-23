<?php
/**
 * Template Tab: Configurare Ore Cursuri
 * 
 * v1.2.0: Sistem simplificat mapare variation_id → ore/ședințe
 * 
 * @package MembershipValidator
 * @since 1.2.0
 */

if (!defined('ABSPATH')) exit;

$validator = OC_Membership_Validator::get_instance();
$db = $validator->get_db();

// Salvare configurări
if (isset($_POST['save_hours_config'])) {
    check_admin_referer('save_hours_config', 'hours_nonce');
    
    $hours = array_map('absint', (array) ($_POST['hours'] ?? []));
    $sessions = array_map('absint', (array) ($_POST['sessions'] ?? []));
    
    $saved_count = 0;
    
    foreach ($hours as $variation_id => $hour_value) {
        $result = $db->save_course_hours_config(
            absint($variation_id),
            absint($hour_value),
            absint($sessions[$variation_id] ?? 0),
            false // is_unlimited = false (se determină de tip pachet, nu de curs!)
        );
        if ($result) $saved_count++;
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo wp_kses_post(sprintf(__('✅ <strong>%d configurări salvate cu succes!</strong>', OC_TEXT_DOMAIN), $saved_count));
    echo '<br><em>Configurările se aplică DOAR pentru comenzi noi. Pentru abonamente existente, folosește butonul "🔄 Actualizează Abonamente Existente".</em>';
    echo '</p></div>';
}

// SYNC MANUAL pentru abonamente existente (BUTON SEPARAT)
if (isset($_POST['sync_existing_memberships'])) {
    check_admin_referer('sync_existing', 'sync_nonce');
    
    global $wpdb;
    $validations_table = $db->get_table_name('membership_validations');
    $synced_count = 0;
    
    // Obține toate configurările
    $configs = $db->get_all_course_hours_configs();
    
    foreach ($configs as $config) {
        // Actualizează membership-uri pentru acest curs (DOAR NON-VIP)
        $result = $wpdb->query($wpdb->prepare("
            UPDATE {$validations_table}
            SET sessions_allocated = %d,
                remaining_sessions = GREATEST(0, %d - used_sessions),
                updated_at = NOW()
            WHERE variation_id = %d
            AND validation_status = 'active'
            AND is_unlimited = 0
        ", 
            $config['sessions_per_month'],
            $config['sessions_per_month'],
            $config['course_variation_id']
        ));
        
        if ($result !== false) {
            $synced_count += $result;
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo sprintf('🔄 <strong>%d abonamente existente actualizate cu succes!</strong>', $synced_count);
    echo '<br><em>Abonamentele VIP (nelimitate) nu au fost modificate.</em>';
    echo '</p></div>';
}

// Obține toate variațiile din Pool Products
$pool_ids = oc_pool_get_all_pool_ids();
$all_variations = [];

foreach ($pool_ids as $pool_id) {
    $pool_product = wc_get_product($pool_id);
    if ($pool_product && $pool_product->is_type('variable')) {
        $variations = $pool_product->get_available_variations();
        
        foreach ($variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            if ($variation_obj) {
                $all_variations[] = [
                    'id' => $variation['variation_id'],
                    'name' => $variation_obj->get_name(),
                    'pool_id' => $pool_id,
                    'pool_name' => $pool_product->get_name()
                ];
            }
        }
    }
}

// Sortare alfabetică după nume curs
usort($all_variations, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$configs = $db->get_all_course_hours_configs();
$config_map = array_column($configs, null, 'course_variation_id');

// Verifică cursuri nemapate
$unmapped_variations = [];
foreach ($all_variations as $variation) {
    if (!isset($config_map[$variation['id']])) {
        $unmapped_variations[] = $variation;
    }
}
?>

<div class="wrap">
    <h1>⏰ Configurare Ore Cursuri</h1>
    
    <div class="notice notice-info">
        <p><strong>ℹ️ Informații importante:</strong></p>
        <ul style="margin-left: 20px; list-style-type: disc;">
            <li>Configurați ore și ședințe pentru fiecare curs (variație) - se aplică pentru <strong>pachete NORMALE</strong>.</li>
            <li><strong>Pachete VIP:</strong> Produse cu "VIP" sau "NELIMITAT" în nume → toate cursurile devin automat nelimitate (configurările nu se aplică).</li>
            <li>Cursurile nemapate vor folosi <strong>8 ședințe default</strong> la comenzi noi (doar pentru pachete normale).</li>
            <li>Modificările afectează DOAR comenzile noi, nu cele existente.</li>
        </ul>
    </div>
    
    <?php if (!empty($unmapped_variations)): ?>
        <div class="notice notice-warning">
            <p><strong>⚠️ Avertizare: <?php echo count($unmapped_variations); ?> cursuri NU sunt mapate!</strong></p>
            <p>Cursurile nemapate vor folosi <strong>8 ședințe default</strong> la comenzi noi și veți primi email de notificare.</p>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; font-weight: bold;">
                    📋 Vezi lista cursurilor nemapate (<?php echo count($unmapped_variations); ?>)
                </summary>
                <ul style="margin: 10px 0 0 20px; list-style-type: disc;">
                    <?php foreach (array_slice($unmapped_variations, 0, 10) as $unmapped): ?>
                        <li>
                            <strong><?php echo esc_html($unmapped['name']); ?></strong> 
                            <small>(ID: <?php echo intval($unmapped['id']); ?>, Pool: <?php echo esc_html($unmapped['pool_name']); ?>)</small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($unmapped_variations) > 10): ?>
                        <li><em>... și încă <?php echo count($unmapped_variations) - 10; ?> cursuri</em></li>
                    <?php endif; ?>
                </ul>
            </details>
        </div>
    <?php else: ?>
        <div class="notice notice-success">
            <p>✅ <strong>Toate cursurile sunt mapate corect!</strong></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($all_variations)): ?>
        <div class="notice notice-error">
            <p>❌ <strong>Nu s-au găsit cursuri (variații)!</strong></p>
            <p>Verificați că aveți produse Pool cu variații create în WooCommerce.</p>
        </div>
    <?php else: ?>
    
    <form method="post" style="margin-top: 20px;">
        <?php wp_nonce_field('save_hours_config', 'hours_nonce'); ?>
        
        <div style="margin-bottom: 15px;">
            <p style="margin: 0;">
                <strong>Total cursuri:</strong> <?php echo count($all_variations); ?> | 
                <strong>Mapate:</strong> <span style="color: #46b450;"><?php echo count($all_variations) - count($unmapped_variations); ?></span> | 
                <strong>Nemapate:</strong> <span style="color: #dc3232;"><?php echo count($unmapped_variations); ?></span>
            </p>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%">Status</th>
                    <th style="width: 55%">Curs (Variație)</th>
                    <th style="width: 20%">Ședințe/Lună</th>
                    <th style="width: 20%">Pachet Pool</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_variations as $variation): 
                    $config = $config_map[$variation['id']] ?? null;
                    $is_mapped = $config !== null;
                    $bg_color = !$is_mapped ? '#fff3cd' : 'transparent';
                ?>
                <tr style="background-color: <?php echo $bg_color; ?>;">
                    <td style="text-align: center; padding: 8px;">
                        <?php if ($is_mapped): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: #f0b849; font-size: 20px;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($variation['name']); ?></strong><br>
                        <small style="color: #666;">ID variație: <?php echo intval($variation['id']); ?></small>
                    </td>
                    <td>
                        <input type="number" 
                               name="sessions[<?php echo intval($variation['id']); ?>]"
                               value="<?php echo $config ? absint($config['sessions_per_month']) : 8; ?>"
                               min="1" 
                               max="100" 
                               style="width: 100px; font-size: 16px; font-weight: bold;"
                               title="Şedinȟe per lună pentru acest curs">
                        <input type="hidden" name="hours[<?php echo intval($variation['id']); ?>]" value="<?php echo $config ? absint($config['hours_per_month']) : 8; ?>">
                    </td>
                    <td>
                        <small><?php echo esc_html($variation['pool_name']); ?></small><br>
                        <small style="color: #999;">Pool ID: <?php echo intval($variation['pool_id']); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="submit" style="margin-top: 20px;">
            <input type="submit" 
                   name="save_hours_config" 
                   class="button button-primary button-large" 
                   value="💾 Salvează Configurările">
            <span style="margin-left: 15px; color: #666;">
                Se vor salva <?php echo count($all_variations); ?> configurări (se aplică pentru comenzi NOI)
            </span>
        </p>
    </form>
    
    <!-- BUTON SEPARAT: Actualizează abonamente EXISTENTE -->
    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #f0b849; border-radius: 4px;">
        <h3 style="margin-top: 0;">🔄 Actualizare Abonamente Existente</h3>
        <p><strong>⚠️ ATENȚIE:</strong> Această operațiune va actualiza TOATE abonamentele active cu valorile din configurările de mai sus.</p>
        <p style="margin-bottom: 20px;">
            <strong>Ce face:</strong> Setează <code>sessions_allocated</code> din config pentru fiecare abonament existent.<br>
            <strong>NU afectează:</strong> Abonamentele VIP (rămân nelimitate), ședințele deja folosite (used_sessions).
        </p>
        
        <form method="post" onsubmit="return confirm('Sigur vrei să actualizezi TOATE abonamentele existente cu configurările din tabel?');">
            <?php wp_nonce_field('sync_existing', 'sync_nonce'); ?>
            <input type="submit" 
                   name="sync_existing_memberships" 
                   class="button button-secondary button-large" 
                   value="🔄 Actualizează Abonamente Existente"
                   style="background: #f0b849; color: white; border-color: #f0b849;">
        </form>
    </div>
    
    <?php endif; ?>
    
    <div class="notice notice-info" style="margin-top: 30px;">
        <p><strong>📚 Ghid utilizare:</strong></p>
        <ul style="margin-left: 20px; list-style-type: disc;">
            <li><strong>Ședințe/Lună:</strong> Numărul de ședințe consumabile la validare QR (1 ședință = 1 oră)</li>
            <li><strong>Default:</strong> Cursuri nemapate folosesc <strong>8 ședințe default</strong> (pentru pachete normale)</li>
            <li><strong>Pachet VIP:</strong> Produse cu "VIP" sau "NELIMITAT" în nume → toate cursurile devin automat nelimitate</li>
            <li><strong>Status:</strong> ✅ = mapt corect | ⚠️ = nemapt (va folosi 8 ședințe default)</li>
            <li><strong>Actualizare existente:</strong> Folosește butonul separat 🔄 pentru a actualiza abonamentele active</li>
        </ul>
    </div>
</div>

<style>
.wp-list-table td {
    vertical-align: middle;
}

.wp-list-table input[type="number"] {
    padding: 5px;
    font-size: 14px;
}

.wp-list-table input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

details summary {
    padding: 5px;
    background: #f0f0f1;
    border-radius: 4px;
}

details[open] summary {
    margin-bottom: 10px;
}
</style>

