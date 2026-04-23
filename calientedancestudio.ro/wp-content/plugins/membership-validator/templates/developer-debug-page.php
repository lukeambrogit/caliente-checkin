<?php
/**
 * Developer Debug Page Template
 * 
 * @package OrarCursuri
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Capability check — template must only run in an authorised admin context
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to view this page.', OC_TEXT_DOMAIN));
}

// Initialize classes
$db = new OC_DB();
$woocommerce = new OC_WooCommerce();

global $wpdb;

// Get data
$table_name = $wpdb->prefix . 'orar_cursuri';
$total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$total_products = count($woocommerce->get_variable_products());
$selected_product = get_option('oc_selected_product', 0);
$plugin_version = defined('OC_PLUGIN_VERSION') ? OC_PLUGIN_VERSION : 'N/A';
$db_version = get_option('oc_db_version', 'N/A');
?>

<div class="wrap">
    <h1>🔧 Developer Debug Tool</h1>
    
    <style>
        .oc-debug-container { 
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .oc-debug-stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin: 20px 0;
        }
        .oc-debug-stat-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center;
        }
        .oc-debug-stat-number { 
            font-size: 2em; 
            font-weight: bold; 
            display: block;
        }
        .oc-debug-info-box { 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0;
        }
        .oc-debug-warning-box { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 20px 0;
        }
        .oc-debug-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            background: white;
        }
        .oc-debug-table th, .oc-debug-table td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left;
            font-size: 13px;
        }
        .oc-debug-table th { 
            background: #f8f9fa; 
            font-weight: bold;
        }
        .oc-debug-table tr:nth-child(even) { 
            background: #f8f9fa; 
        }
        .oc-debug-table tr:hover { 
            background: #e8f4fd; 
        }
        .oc-debug-code { 
            background: #2c3e50; 
            color: #ecf0f1; 
            padding: 15px; 
            border-radius: 5px; 
            font-family: "Courier New", monospace;
            overflow-x: auto;
            margin: 10px 0;
            white-space: pre-wrap;
        }
        .oc-debug-highlight {
            background: #f39c12;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .oc-debug-nav {
            margin: 20px 0;
        }
        .oc-debug-nav a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 15px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 13px;
        }
        .oc-debug-nav a:hover {
            background: #005a87;
        }
        .oc-debug-empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        h2.oc-debug-section {
            color: #23282d;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        h3.oc-debug-subsection {
            color: #555;
            margin-top: 30px;
        }
    </style>

    <!-- Navigation -->
    <div class="oc-debug-nav">
        <a href="#overview">📊 Overview</a>
        <a href="#schedule-data">📅 Date Orar</a>
        <a href="#products">🛍️ Produse</a>
        <a href="#variations">🎯 Variații</a>
        <a href="#options">⚙️ Opțiuni</a>
        <a href="#database">💾 Database</a>

    </div>

    <div class="oc-debug-container">
        <!-- 1. OVERVIEW SECTION -->
        <h2 id="overview" class="oc-debug-section">📊 Overview Plugin</h2>

        <div class="oc-debug-stats">
            <div class="oc-debug-stat-card">
                <span class="oc-debug-stat-number"><?php echo intval($total_entries); ?></span>
                Intrări în Orar
            </div>
            <div class="oc-debug-stat-card">
                <span class="oc-debug-stat-number"><?php echo intval($total_products); ?></span>
                Produse Variabile
            </div>
            <div class="oc-debug-stat-card">
                <span class="oc-debug-stat-number"><?php echo intval($selected_product); ?></span>
                Produs Selectat
            </div>
            <div class="oc-debug-stat-card">
                <span class="oc-debug-stat-number"><?php echo esc_html($plugin_version); ?></span>
                Versiune Plugin
            </div>
        </div>

        <div class="oc-debug-info-box">
            <strong>🔌 Status Plugin:</strong><br>
            📁 Versiune Plugin: <span class="oc-debug-highlight"><?php echo esc_html($plugin_version); ?></span><br>
            💾 Versiune DB: <span class="oc-debug-highlight"><?php echo esc_html($db_version); ?></span><br>
            🛗 Produs Activ: <span class="oc-debug-highlight"><?php echo $selected_product ? 'ID ' . intval($selected_product) : 'Niciunul'; ?></span><br>
            📊 Total Intrări: <span class="oc-debug-highlight"><?php echo intval($total_entries); ?></span><br>
            🌐 Acces: <span class="oc-debug-highlight">WordPress Admin</span>
        </div>

        <!-- 2. DATE ORAR -->
        <h2 id="schedule-data" class="oc-debug-section">📅 Date Orar Cursuri</h2>

        <?php if ($total_entries > 0): ?>
            <?php
            $schedule_data = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY weekday, start_time, room_number", ARRAY_A);
            $weekdays = [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică'];
            ?>
            
            <h3 class="oc-debug-subsection">📋 Toate Intrările din Orar (<?php echo count($schedule_data); ?> total)</h3>
            <table class="oc-debug-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product ID</th>
                        <th>Variation ID</th>
                        <th>Zi Săptămână</th>
                        <th>Ora Start</th>
                        <th>Ora End</th>
                        <th>Sala</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule_data as $row): ?>
                        <?php $weekday_name = $weekdays[$row['weekday']] ?? 'N/A'; ?>
                        <tr>
                            <td><?php echo intval($row['id']); ?></td>
                            <td><?php echo intval($row['product_id']); ?></td>
                            <td><?php echo intval($row['variation_id']); ?></td>
                            <td><?php echo esc_html($weekday_name) . ' (' . intval($row['weekday']) . ')'; ?></td>
                            <td><?php echo esc_html($row['start_time']); ?></td>
                            <td><?php echo esc_html($row['end_time']); ?></td>
                            <td>Sala <?php echo intval($row['room_number']); ?></td>
                            <td><?php echo esc_html($row['created_at']); ?></td>
                            <td><?php echo esc_html($row['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Statistici pe zile -->
            <h3 class="oc-debug-subsection">📊 Distribuție pe Zile</h3>
            <?php
            $weekday_stats = $wpdb->get_results("
                SELECT weekday, COUNT(*) as count 
                FROM {$table_name} 
                GROUP BY weekday 
                ORDER BY weekday
            ", ARRAY_A);
            ?>
            <table class="oc-debug-table" style="max-width: 500px;">
                <thead>
                    <tr><th>Zi</th><th>Număr Cursuri</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($weekday_stats as $stat): ?>
                        <?php $weekday_name = $weekdays[$stat['weekday']] ?? 'N/A'; ?>
                        <tr>
                            <td><?php echo esc_html($weekday_name); ?></td>
                            <td><?php echo intval($stat['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>
            <div class="oc-debug-empty-state">
                <h3>📭 Nici un curs în orar</h3>
                <p>Nu există încă cursuri programate în baza de date.</p>
            </div>
        <?php endif; ?>

        <!-- 3. PRODUSE VARIABILE -->
        <h2 id="products" class="oc-debug-section">🛍️ Produse Variabile WooCommerce</h2>

        <?php
        $variable_products = $woocommerce->get_variable_products();
        ?>

        <?php if (!empty($variable_products)): ?>
            <h3 class="oc-debug-subsection">📦 Lista Produse Variabile (<?php echo count($variable_products); ?> total)</h3>
            <table class="oc-debug-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nume Produs</th>
                        <th>Status</th>
                        <th>Nr. Variații</th>
                        <th>În Orar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variable_products as $product_id => $product_data): ?>
                        <?php
                        $variations_count = count($woocommerce->get_product_variations($product_id));
                        $in_schedule = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table_name} WHERE product_id = %d", 
                            $product_id
                        ));
                        $is_selected = ($product_id == $selected_product) ? ' 🎯' : '';
                        ?>
                        <tr>
                            <td><?php echo intval($product_id) . esc_html($is_selected); ?></td>
                            <td><?php echo esc_html($product_data['name']); ?></td>
                            <td><?php echo esc_html($product_data['status']); ?></td>
                            <td><?php echo intval($variations_count); ?></td>
                            <td><?php echo $in_schedule > 0 ? '✅ ' . intval($in_schedule) . ' cursuri' : '❌ Nu'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="oc-debug-warning-box">
                <strong>⚠️ Atenție:</strong> Nu există produse variabile în WooCommerce!
            </div>
        <?php endif; ?>

        <!-- 4. VARIAȚII -->
        <h2 id="variations" class="oc-debug-section">🎯 Variații Produse</h2>

        <?php if ($selected_product): ?>
            <?php 
            $selected_product_data = $variable_products[$selected_product] ?? null;
            ?>
            <?php if ($selected_product_data): ?>
                <h3 class="oc-debug-subsection">🎯 Variații pentru Produsul Selectat: <?php echo esc_html($selected_product_data['name']); ?> (ID: <?php echo intval($selected_product); ?>)</h3>
                
                <?php
                $variations = $woocommerce->get_product_variations($selected_product);
                ?>
                <?php if (!empty($variations)): ?>
                    <table class="oc-debug-table">
                        <thead>
                            <tr>
                                <th>Variation ID</th>
                                <th>Nume Curățat</th>
                                <th>Nume Original</th>
                                <th>SKU</th>
                                <th>Preț</th>
                                <th>Status</th>
                                <th>În Orar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variations as $variation): ?>
                                <?php
                                $original_name = wc_get_product($variation['id'])->get_name();
                                $in_schedule = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$table_name} WHERE variation_id = %d", 
                                    $variation['id']
                                ));
                                ?>
                                <tr>
                                    <td><?php echo intval($variation['id']); ?></td>
                                    <td><strong><?php echo esc_html($variation['name']); ?></strong></td>
                                    <td style="color: #666;"><?php echo esc_html($original_name); ?></td>
                                    <td><?php echo esc_html($variation['sku']); ?></td>
                                    <td><?php echo wp_kses_post($variation['price_html']); ?></td>
                                    <td><?php echo esc_html($variation['stock_status']); ?></td>
                                    <td><?php echo $in_schedule > 0 ? '✅ ' . intval($in_schedule) . ' intrări' : '❌ Nu'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="oc-debug-warning-box">
                        <strong>⚠️ Atenție:</strong> Produsul selectat nu are variații!
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="oc-debug-info-box">
                <strong>ℹ️ Info:</strong> Nici un produs selectat. Selectează un produs din admin pentru a vedea variațiile.
            </div>
        <?php endif; ?>

        <!-- 5. OPȚIUNI PLUGIN -->
        <h2 id="options" class="oc-debug-section">⚙️ Opțiuni Plugin din wp_options</h2>

        <?php
        $plugin_options = [
            'oc_selected_product' => 'Produs Selectat',
            'oc_selected_attribute' => 'Atribut Selectat (depreciat)',
            'oc_db_version' => 'Versiune DB',
            'oc_show_empty_days' => 'Afișează Zile Goale',
            'oc_primary_color' => 'Culoare Primară',
            'oc_text_color' => 'Culoare Text',
            'oc_secondary_color' => 'Culoare Secundară',
            'oc_background_color' => 'Culoare Fundal',
            'oc_muted_color' => 'Culoare Muted',
            'oc_border_color' => 'Culoare Border',
            'oc_font_family' => 'Font Family',
            'oc_font_size' => 'Font Size',
            'oc_header_font_size' => 'Header Font Size',
            'oc_desktop_bg_image' => 'Imagine Fundal Desktop',
            'oc_mobile_bg_image' => 'Imagine Fundal Mobile',
            'oc_border_radius' => 'Border Radius'
        ];
        ?>

        <table class="oc-debug-table">
            <thead>
                <tr><th>Opțiune</th><th>Descriere</th><th>Valoare</th></tr>
            </thead>
            <tbody>
                <?php foreach ($plugin_options as $option_name => $description): ?>
                    <?php
                    $value = get_option($option_name, '(nu există)');
                    $display_value = is_array($value) ? print_r($value, true) : $value;
                    $display_value = strlen($display_value) > 100 ? substr($display_value, 0, 100) . '...' : $display_value;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($option_name); ?></code></td>
                        <td><?php echo esc_html($description); ?></td>
                        <td><?php echo esc_html($display_value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 6. DATABASE INFO -->
        <h2 id="database" class="oc-debug-section">💾 Informații Database</h2>

        <?php
        // Schema tabelului
        $safe_table_name = esc_sql($table_name);
        $table_schema = $wpdb->get_results("SHOW CREATE TABLE `{$safe_table_name}`", ARRAY_A);
        ?>
        <?php if (!empty($table_schema)): ?>
            <h3 class="oc-debug-subsection">🗄️ Schema Tabel <?php echo esc_html($table_name); ?></h3>
            <div class="oc-debug-code"><?php echo esc_html($table_schema[0]['Create Table']); ?></div>
        <?php endif; ?>

        <?php
        // Indexuri
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$safe_table_name}`", ARRAY_A);
        ?>
        <?php if (!empty($indexes)): ?>
            <h3 class="oc-debug-subsection">🔍 Indexuri Tabel</h3>
            <table class="oc-debug-table">
                <thead>
                    <tr><th>Nume Index</th><th>Coloane</th><th>Unic</th><th>Tip</th></tr>
                </thead>
                <tbody>
                    <?php
                    $grouped_indexes = [];
                    foreach ($indexes as $index) {
                        $grouped_indexes[$index['Key_name']][] = $index;
                    }
                    
                    foreach ($grouped_indexes as $index_name => $index_data):
                        $columns = array_column($index_data, 'Column_name');
                        $is_unique = $index_data[0]['Non_unique'] == 0 ? 'Da' : 'Nu';
                        $index_type = $index_data[0]['Index_type'];
                    ?>
                        <tr>
                            <td><?php echo esc_html($index_name); ?></td>
                            <td><?php echo esc_html(implode(', ', $columns)); ?></td>
                            <td><?php echo esc_html($is_unique); ?></td>
                            <td><?php echo esc_html($index_type); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>



        <!-- Footer -->
        <div class="oc-debug-info-box" style="margin-top: 40px; text-align: center; background: #d4edda; border-color: #c3e6cb;">
            <strong>✅ Debug completat cu succes!</strong><br>
            Generat la: <?php echo date('Y-m-d H:i:s'); ?><br>
            Pentru suport tehnic, salvează aceste informații.
        </div>

    </div> <!-- oc-debug-container -->
</div> <!-- wrap -->
