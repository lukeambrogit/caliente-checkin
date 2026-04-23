<?php
/**
 * Admin Dashboard pentru Membership Manager - FAZA 3
 * 
 * CONFORMITATE .cursorrules:
 * - Citire NON-INTRUZIVĂ din ADD-ON #1 și tabele existente
 * - Analytics și rapoarte fără modificări în sisteme existente
 * - Best practices 2025: Performance, security, responsive design
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Admin
 * 
 * Gestionează dashboard-ul admin cu analytics și rapoarte detaliate
 * Best practices 2025: Modern admin interface, data visualization
 */
class OC_Membership_Admin {
    
    /**
     * @var OC_Membership_DB Database handler din ADD-ON #1
     */
    private OC_Membership_DB $validator_db;
    
    /**
     * Constructor cu dependency injection
     */
    public function __construct(OC_Membership_DB $validator_db) {
        $this->validator_db = $validator_db;
    }
    
    /**
     * Adaugă meniul admin pentru analytics
     */
    public function add_admin_menu(): void {
        // ELIMINATED: Submenus previously attached to non-existent parent 'oc-dashboard'.
        // Analytics and reports content is accessible via the Membership Manager tabs.
    }
    
    /**
     * Callback pentru pagina de analytics
     */
    public function analytics_page_callback(): void {
        // Verifică permisiuni
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Enqueue Chart.js from local bundle (no external CDN dependency)
        wp_enqueue_script('oc-chartjs', OC_PLUGIN_URL . 'assets/chart.umd.min.js', [], '4.4.0', true);
        
        // Obține statisticile pentru dashboard
        $analytics_data = $this->get_analytics_data();
        
        ?>
        <div class="wrap oc-membership-analytics">
            <h1><?php echo esc_html__('Membership Analytics', OC_TEXT_DOMAIN); ?></h1>
            
            <!-- Overview Cards -->
            <div class="oc-analytics-overview">
                <div class="oc-card">
                    <h3><?php esc_html_e('Total Memberships', OC_TEXT_DOMAIN); ?></h3>
                    <div class="oc-stat-number"><?php echo esc_html($analytics_data['total_memberships']); ?></div>
                </div>
                
                <div class="oc-card">
                    <h3><?php esc_html_e('Active Memberships', OC_TEXT_DOMAIN); ?></h3>
                    <div class="oc-stat-number active"><?php echo esc_html($analytics_data['active_memberships']); ?></div>
                </div>
                
                <div class="oc-card">
                    <h3><?php esc_html_e('QR Codes Generated', OC_TEXT_DOMAIN); ?></h3>
                    <div class="oc-stat-number"><?php echo esc_html($analytics_data['qr_codes_generated']); ?></div>
                </div>
                
                <div class="oc-card">
                    <h3><?php esc_html_e('Validations Today', OC_TEXT_DOMAIN); ?></h3>
                    <div class="oc-stat-number today"><?php echo esc_html($analytics_data['validations_today']); ?></div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="oc-analytics-charts">
                <div class="oc-chart-container">
                    <h3><?php esc_html_e('Usage Trends (Last 30 Days)', OC_TEXT_DOMAIN); ?></h3>
                    <canvas id="oc-usage-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="oc-chart-container">
                    <h3><?php esc_html_e('Popular Time Slots', OC_TEXT_DOMAIN); ?></h3>
                    <canvas id="oc-timeslots-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="oc-recent-activities">
                <h3><?php esc_html_e('Recent Validations', OC_TEXT_DOMAIN); ?></h3>
                <?php $this->render_recent_activities(); ?>
            </div>
        </div>
        
        <!-- Chart.js loaded via wp_enqueue_script at top of callback -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Usage Trends Chart
            const usageCtx = document.getElementById('oc-usage-chart').getContext('2d');
            new Chart(usageCtx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode($analytics_data['usage_labels']); ?>,
                    datasets: [{
                        label: 'Validations',
                        data: <?php echo wp_json_encode($analytics_data['usage_data']); ?>,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Time Slots Chart
            const timeslotsCtx = document.getElementById('oc-timeslots-chart').getContext('2d');
            new Chart(timeslotsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode($analytics_data['timeslot_labels']); ?>,
                    datasets: [{
                        label: 'Validations',
                        data: <?php echo wp_json_encode($analytics_data['timeslot_data']); ?>,
                        backgroundColor: '#0073aa'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        </script>
        
        <style>
        .oc-membership-analytics {
            max-width: 1200px;
        }
        
        .oc-analytics-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .oc-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .oc-card h3 {
            margin: 0 0 15px 0;
            color: #555;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .oc-stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }
        
        .oc-stat-number.active {
            color: #46b450;
        }
        
        .oc-stat-number.today {
            color: #ff6900;
        }
        
        .oc-analytics-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .oc-chart-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        .oc-chart-container h3 {
            margin-top: 0;
            color: #333;
        }
        
        .oc-recent-activities {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .oc-analytics-charts {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Callback pentru pagina de rapoarte
     */
    public function reports_page_callback(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $sync_notice = null;
        $manual_sync_request = isset($_POST['oc_manual_cached_sync'])
            ? sanitize_text_field(wp_unslash($_POST['oc_manual_cached_sync']))
            : '';

        if ($manual_sync_request === '1') {
            check_admin_referer('oc_manual_cached_sync_action', 'oc_manual_cached_sync_nonce');
            $sync_result = $this->run_safe_manual_cached_sync();

            $sync_notice = [
                'type' => empty($sync_result['errors']) ? 'success' : 'warning',
                'message' => sprintf(
                    'Sincronizare finalizata. Comenzi adaugate: %d, procesate: %d, actualizate: %d, sarite: %d, erori: %d.',
                    (int) ($sync_result['created'] ?? 0),
                    (int) ($sync_result['processed'] ?? 0),
                    (int) ($sync_result['updated'] ?? 0),
                    (int) ($sync_result['skipped'] ?? 0),
                    count($sync_result['errors'] ?? [])
                ),
            ];
        }

        $wp_date_format = (string) get_option('date_format', 'd/m/Y');
        $date_from_raw = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : date('Y-m-01');
        $date_to_raw   = isset($_GET['date_to'])   ? sanitize_text_field(wp_unslash($_GET['date_to']))   : date('Y-m-d');
        $date_from_ts  = strtotime($date_from_raw) ?: strtotime(date('Y-m-01'));
        $date_to_ts    = strtotime($date_to_raw)   ?: strtotime(date('Y-m-d'));
        $date_from_display = wp_date($wp_date_format, $date_from_ts);
        $date_to_display   = wp_date($wp_date_format, $date_to_ts);
        $locale            = str_replace('_', '-', get_locale());

        $selected_status  = sanitize_text_field(wp_unslash($_GET['status']          ?? ''));
        $selected_payment = sanitize_text_field(wp_unslash($_GET['payment_method']  ?? ''));
        $selected_course  = sanitize_text_field(wp_unslash($_GET['course']          ?? ''));
        $available_courses = $this->get_report_course_filter_options();

        // Filtre pentru query și tabel
        $filters = [
            'date_from'      => date('Y-m-d', $date_from_ts),
            'date_to'        => date('Y-m-d', $date_to_ts),
            'status'         => $selected_status,
            'payment_method' => $selected_payment,
            'course'         => $selected_course,
        ];

        // Metodele din dropdown sunt canonice, definite intern pentru consistență.
        ?>
        <div class="wrap oc-membership-reports">
            <h1><?php esc_html_e('Rapoarte Abonamente', OC_TEXT_DOMAIN); ?></h1>

            <?php if (is_array($sync_notice)): ?>
                <div class="notice notice-<?php echo esc_attr($sync_notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html((string) $sync_notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="oc-sync-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=membership-manager&tab=reports')); ?>" class="oc-manual-sync-form" id="oc-manual-sync-form">
                    <?php wp_nonce_field('oc_manual_cached_sync_action', 'oc_manual_cached_sync_nonce'); ?>
                    <input type="hidden" name="oc_manual_cached_sync" value="1">
                    <button type="submit" class="button button-secondary">Sincronizare manuala comenzi Woo</button>
                    <p class="description">Adauga memberships lipsa din comenzile Woo si apoi actualizeaza datele cached. Nu sterge si nu recreeaza tabele.</p>
                </form>
            </div>

            <!-- Filters -->
            <div class="oc-reports-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="membership-manager">
                    <input type="hidden" name="tab"  value="reports">

                    <div class="oc-filter-group">
                        <label for="date_from">De la</label>
                        <input type="text" id="date_from" name="date_from" class="oc-report-date-input" inputmode="numeric" autocomplete="off"
                               placeholder="<?php echo esc_attr($wp_date_format); ?>" value="<?php echo esc_attr($date_from_display); ?>">
                    </div>

                    <div class="oc-filter-group">
                        <label for="date_to">Până la</label>
                        <input type="text" id="date_to" name="date_to" class="oc-report-date-input" inputmode="numeric" autocomplete="off"
                               placeholder="<?php echo esc_attr($wp_date_format); ?>" value="<?php echo esc_attr($date_to_display); ?>">
                    </div>

                    <div class="oc-filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">Toate</option>
                            <option value="active"    <?php selected($selected_status, 'active'); ?>>Activ</option>
                            <option value="pending"   <?php selected($selected_status, 'pending'); ?>>În așteptare</option>
                            <option value="expired"   <?php selected($selected_status, 'expired'); ?>>Expirat</option>
                            <option value="cancelled" <?php selected($selected_status, 'cancelled'); ?>>Anulat</option>
                        </select>
                    </div>

                    <div class="oc-filter-group">
                        <label for="payment_method">Modalitate plată</label>
                        <select id="payment_method" name="payment_method">
                            <option value="">Toate</option>
                            <?php
                            $standard_methods = $this->get_canonical_payment_methods();
                            foreach ($standard_methods as $val => $label):
                            ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_payment, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="oc-filter-group oc-filter-group-wide">
                        <label for="course">Curs</label>
                        <select id="course" name="course">
                            <option value="">Toate</option>
                            <?php foreach ($available_courses as $course_option): ?>
                                <option value="<?php echo esc_attr((string) ($course_option['value'] ?? '')); ?>" <?php selected($selected_course, (string) ($course_option['value'] ?? '')); ?>>
                                    <?php echo esc_html((string) ($course_option['label'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="oc-filter-submit">
                        <button type="submit" class="button">Filtrează</button>
                    </div>

                    <div class="oc-filter-downloads">
                        <button type="button" id="export-csv"  class="button button-primary">⬇ Export CSV</button>
                        <button type="button" id="export-xlsx" class="button button-primary" style="background:#1d6f42;border-color:#1d6f42;">⬇ Export XLSX</button>
                    </div>
                </form>
            </div>

            <!-- Reports Table -->
            <div class="oc-reports-table">
                <?php $this->render_reports_table($filters); ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wpDateFormat = <?php echo wp_json_encode($wp_date_format); ?>;
            const locale = <?php echo wp_json_encode($locale); ?>;

            function parseDisplayDateToIso(rawValue) {
                const value = (rawValue || '').toString().trim();
                if (value === '') {
                    return '';
                }

                if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return value;
                }

                const tokens = [];
                let escaped = false;
                for (const ch of wpDateFormat) {
                    if (escaped) {
                        escaped = false;
                        continue;
                    }
                    if (ch === '\\') {
                        escaped = true;
                        continue;
                    }
                    if (['d', 'j', 'm', 'n', 'Y', 'y'].includes(ch)) {
                        tokens.push(ch);
                    }
                }

                const numbers = value.match(/\d+/g) || [];
                if (tokens.length === 0 || numbers.length !== tokens.length) {
                    return null;
                }

                let day = null;
                let month = null;
                let year = null;
                for (let i = 0; i < tokens.length; i++) {
                    const token = tokens[i];
                    const number = parseInt(numbers[i], 10);
                    if (Number.isNaN(number)) {
                        return null;
                    }
                    if (token === 'd' || token === 'j') {
                        day = number;
                    } else if (token === 'm' || token === 'n') {
                        month = number;
                    } else if (token === 'Y') {
                        year = number;
                    } else if (token === 'y') {
                        year = number >= 70 ? 1900 + number : 2000 + number;
                    }
                }

                if (!day || !month || !year) {
                    return null;
                }

                const date = new Date(year, month - 1, day);
                if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                    return null;
                }

                return `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            }

            function formatIsoForDisplay(isoValue) {
                if (!isoValue || !/^\d{4}-\d{2}-\d{2}$/.test(isoValue)) {
                    return '';
                }
                const [year, month, day] = isoValue.split('-').map(Number);
                const date = new Date(year, month - 1, day);
                const formatter = new Intl.DateTimeFormat((locale || 'en-US'), {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });
                return formatter.format(date);
            }

            document.querySelectorAll('.oc-report-date-input').forEach(function(field) {
                field.addEventListener('click', function(event) {
                    event.preventDefault();

                    const existing = document.querySelector('.oc-hidden-native-datepicker-report');
                    if (existing) {
                        existing.remove();
                    }

                    const rect = field.getBoundingClientRect();
                    const picker = document.createElement('input');
                    picker.type = 'date';
                    picker.className = 'oc-hidden-native-datepicker-report';
                    picker.lang = locale || 'en-US';
                    picker.style.position = 'fixed';
                    picker.style.top = `${Math.max(rect.top, 4)}px`;
                    picker.style.left = `${Math.max(rect.left, 4)}px`;
                    picker.style.width = `${Math.max(rect.width, 1)}px`;
                    picker.style.height = `${Math.max(rect.height, 1)}px`;
                    picker.style.opacity = '0.01';
                    picker.style.pointerEvents = 'none';
                    picker.style.zIndex = '999999';

                    const currentIso = parseDisplayDateToIso(field.value);
                    if (currentIso) {
                        picker.value = currentIso;
                    }

                    picker.addEventListener('change', function() {
                        if (picker.value) {
                            field.value = formatIsoForDisplay(picker.value);
                        }
                        picker.remove();
                    });

                    document.body.appendChild(picker);

                    try {
                        picker.focus({ preventScroll: true });
                        if (typeof picker.showPicker === 'function') {
                            picker.showPicker();
                        } else {
                            picker.click();
                        }
                    } catch (e) {
                        picker.click();
                    }
                });
            });

            const filtersForm = document.querySelector('.oc-reports-filters form');
            if (filtersForm) {
                filtersForm.addEventListener('submit', function(event) {
                    const fromField = document.getElementById('date_from');
                    const toField = document.getElementById('date_to');

                    const fromIso = parseDisplayDateToIso(fromField ? fromField.value : '');
                    const toIso = parseDisplayDateToIso(toField ? toField.value : '');

                    if (fromIso === null || toIso === null) {
                        event.preventDefault();
                        alert('Format invalid pentru filtrele de dată.');
                        return;
                    }

                    if (fromField && fromIso) {
                        fromField.value = fromIso;
                    }
                    if (toField && toIso) {
                        toField.value = toIso;
                    }
                });
            }

            const manualSyncForm = document.getElementById('oc-manual-sync-form');
            if (manualSyncForm) {
                manualSyncForm.addEventListener('submit', function(event) {
                    const ok = window.confirm('Confirma sincronizarea manuala. Actiunea este non-distructiva si actualizeaza doar datele cached. Continui?');
                    if (!ok) {
                        event.preventDefault();
                    }
                });
            }

            // Export buttons
            function buildExportUrl(format) {
                const formData = filtersForm ? new FormData(filtersForm) : new FormData();
                const date_from = String(formData.get('date_from') || '').trim();
                const date_to   = String(formData.get('date_to') || '').trim();
                const exportParams = new URLSearchParams();
                exportParams.set('action', 'oc_membership_export');
                exportParams.set('format', format);

                if (date_from) exportParams.set('date_from', parseDisplayDateToIso(date_from) || date_from);
                if (date_to)   exportParams.set('date_to',   parseDisplayDateToIso(date_to)   || date_to);

                ['status', 'payment_method', 'course'].forEach(function(name) {
                    const value = String(formData.get(name) || '').trim();
                    if (value !== '') {
                        exportParams.set(name, value);
                    }
                });

                exportParams.set('_wpnonce', '<?php echo esc_js(wp_create_nonce('oc_membership_export')); ?>');
                return ajaxurl + '?' + exportParams.toString();
            }

            document.getElementById('export-csv').addEventListener('click', function() {
                window.location.href = buildExportUrl('csv');
            });
            document.getElementById('export-xlsx').addEventListener('click', function() {
                window.location.href = buildExportUrl('xlsx');
            });
        });
        </script>
        
        <style>
        .oc-reports-filters {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .oc-sync-actions {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .oc-manual-sync-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .oc-manual-sync-form .description {
            margin: 0;
        }
        
        .oc-reports-filters form {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: end;
            justify-content: flex-start;
        }

        .oc-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
            flex: 1 1 170px;
            max-width: 220px;
        }

        .oc-filter-group-wide {
            flex: 1 1 320px;
            min-width: 260px;
            max-width: 420px;
        }

        .oc-reports-filters label {
            font-weight: 600;
            color: #2c3338;
            font-size: 12px;
            line-height: 1.2;
        }

        .oc-reports-filters input,
        .oc-reports-filters select {
            width: 100%;
            min-height: 38px;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            box-sizing: border-box;
        }

        .oc-filter-submit {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex: 0 0 auto;
        }

        .oc-filter-submit .button {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
        }

        .oc-filter-downloads {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            flex: 1 0 100%;
            justify-content: flex-start;
        }

        .oc-filter-downloads .button {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
        }

        @media (max-width: 1200px) {
            .oc-filter-group-wide {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .oc-filter-submit {
                justify-content: flex-start;
            }
        }

        @media (max-width: 820px) {
            .oc-reports-filters {
                padding: 14px;
            }

            .oc-filter-group,
            .oc-filter-group-wide,
            .oc-filter-submit,
            .oc-filter-downloads {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .oc-filter-submit .button,
            .oc-filter-downloads .button {
                width: 100%;
            }
        }
        
        /* Wrapper tabel — fără borduri proprii, tabelul le gestionează */
        .oc-reports-table {
            overflow-x: auto;
        }
        /* Preview table — identic vizual cu "Abonamente-style" din XLSX */
        .oc-reports-preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            font-family: Arial, sans-serif;
        }
        .oc-reports-preview-table th {
            background: #356854;
            color: #fff;
            padding: 10px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 10px;
            line-height: 1.2;
            white-space: nowrap;
            border: 1px solid #356854;
            vertical-align: middle;
        }
        .oc-reports-preview-table td {
            padding: 7px 10px;
            border: 1px solid #b7b7b7;
            vertical-align: middle;
        }
        .oc-reports-preview-table tbody td:nth-child(1)  { background: #f6b26b !important; }
        .oc-reports-preview-table tbody td:nth-child(2)  { background: #fce5cd !important; }
        .oc-reports-preview-table tbody td:nth-child(3)  { background: #fff2cc !important; }
        .oc-reports-preview-table tbody td:nth-child(4)  { background: #c9daf8 !important; }
        .oc-reports-preview-table tbody td:nth-child(5)  { background: #d9ead3 !important; }
        .oc-reports-preview-table tbody td:nth-child(6)  { background: #f4cccc !important; }
        .oc-reports-preview-table tbody td:nth-child(7)  { background: #d9d2e9 !important; }
        .oc-reports-preview-table tbody td:nth-child(8)  { background: #d9d2e9 !important; }
        .oc-reports-preview-table tbody td:nth-child(9)  { background: #ead1dc !important; }
        .oc-reports-preview-table tbody td:nth-child(10) { background: #ead1dc !important; }
        .oc-reports-preview-table tbody tr:hover td {
            filter: brightness(0.97);
        }
        .oc-reports-empty {
            padding: 30px;
            text-align: center;
            color: #888;
            font-style: italic;
        }
        .oc-reports-count {
            padding: 10px 15px;
            background: #eaf2ee;
            border: 1px solid #b7b7b7;
            border-bottom: none;
            font-size: 13px;
            color: #2d5c42;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    /**
     * Obține date pentru analytics din starea curentă și din audit trail-ul validărilor.
     */
    private function get_analytics_data(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        $log_table = $wpdb->prefix . 'membership_validation_log';
        $today = oc_membership_current_business_date();
        
        // Statistici de bază
        $total_memberships = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        // Numără membri unici activi (nu rânduri — un membru poate avea mai multe cursuri/rânduri).
        $active_memberships = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE validation_status = 'active'");
        $qr_codes_generated = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''",
            'simple_qr_filename'
        ));
        $validations_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE last_validation_date IS NOT NULL AND DATE(last_validation_date) = %s",
            $today
        ));
        
        // Usage trends (last 30 days)
        $usage_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(validation_date) as date, COUNT(*) as count
             FROM {$log_table}
             WHERE validation_status = 'success'
               AND validation_date >= %s
               AND (
                    validation_method = 'qr_code'
                    OR validation_metadata LIKE %s
                    OR validation_metadata LIKE %s
               )
             GROUP BY DATE(validation_date)
             ORDER BY date ASC",
            date('Y-m-d 00:00:00', strtotime('-30 days')),
            '%\"endpoint\":\"check-in\"%',
            '%\"consumed\":true%'
        ));

        if (empty($usage_data)) {
            $usage_data = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(last_validation_date) as date, COUNT(*) as count
                 FROM {$table_name}
                 WHERE last_validation_date IS NOT NULL
                   AND last_validation_date >= %s
                 GROUP BY DATE(last_validation_date)
                 ORDER BY date ASC",
                date('Y-m-d 00:00:00', strtotime('-30 days'))
            ));
        }
        
        $usage_labels = [];
        $usage_counts = [];
        foreach ($usage_data as $day) {
            $usage_labels[] = date('d/m', strtotime($day->date));
            $usage_counts[] = (int)$day->count;
        }
        
        // Popular time slots (din schedule manager READ-ONLY)
        $schedule_table = $wpdb->prefix . 'orar_cursuri';
        $timeslot_data = $wpdb->get_results("
            SELECT 
                CONCAT(TIME_FORMAT(sc.start_time, '%H:%i'), '-', TIME_FORMAT(sc.end_time, '%H:%i')) as timeslot,
                COUNT(*) as usage_count
            FROM {$schedule_table} sc
            INNER JOIN {$table_name} m ON sc.id = m.schedule_id
            WHERE m.last_validation_date IS NOT NULL
            GROUP BY sc.start_time, sc.end_time
            ORDER BY usage_count DESC
            LIMIT 10
        ");
        
        $timeslot_labels = [];
        $timeslot_counts = [];
        foreach ($timeslot_data as $slot) {
            $timeslot_labels[] = $slot->timeslot;
            $timeslot_counts[] = (int)$slot->usage_count;
        }
        
        $analytics_data = [
            'total_memberships' => (int)$total_memberships,
            'active_memberships' => (int)$active_memberships,
            'qr_codes_generated' => (int)$qr_codes_generated,
            'validations_today' => (int)$validations_today,
            'usage_labels' => $usage_labels,
            'usage_data' => $usage_counts,
            'timeslot_labels' => $timeslot_labels,
            'timeslot_data' => $timeslot_counts
        ];

        return $analytics_data;
    }
    
    /**
     * Render recent activities table
     */
    private function render_recent_activities(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $recent_activities = $wpdb->get_results(
            "SELECT m.*,
                    COALESCE(NULLIF(m.product_name, ''), p.post_title) as product_name,
                    v.post_title as variation_name
             FROM {$table_name} m
             LEFT JOIN {$wpdb->posts} p ON m.product_id = p.ID
             LEFT JOIN {$wpdb->posts} v ON m.variation_id = v.ID
             WHERE m.last_validation_date IS NOT NULL
             ORDER BY m.last_validation_date DESC
             LIMIT 20"
        );
        
        if (empty($recent_activities)) {
            echo '<p>' . esc_html__('No recent activities found.', OC_TEXT_DOMAIN) . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', OC_TEXT_DOMAIN); ?></th>
                    <th><?php esc_html_e('Product', OC_TEXT_DOMAIN); ?></th>
                    <th><?php esc_html_e('Sessions Used', OC_TEXT_DOMAIN); ?></th>
                    <th><?php esc_html_e('Last Activity', OC_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activities as $activity): ?>
                <?php $activity_display_name = oc_membership_resolve_user_display_name($activity->user_id > 0 ? (int) $activity->user_id : null, $activity, !empty($activity->order_id) ? (int) $activity->order_id : null); ?>
                <tr>
                    <td><?php echo esc_html($activity_display_name); ?></td>
                    <td><?php echo esc_html($this->get_recent_activity_product_label($activity)); ?></td>
                    <td><?php echo esc_html($activity->used_sessions . '/' . $activity->total_sessions); ?></td>
                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($activity->last_validation_date))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function get_recent_activity_product_label(object $activity): string {
        $variation_name = $this->clean_recent_activity_variation_name(
            (string) ($activity->variation_name ?? ''),
            (string) ($activity->product_name ?? '')
        );

        if ($variation_name !== '') {
            return $variation_name;
        }

        $product_name = trim((string) ($activity->product_name ?? ''));

        return $product_name !== '' ? $product_name : __('Unknown Product', OC_TEXT_DOMAIN);
    }

    private function clean_recent_activity_variation_name(string $variation_name, string $product_name = ''): string {
        $clean_name = trim($variation_name);
        $base_name = trim($product_name);

        if ($clean_name === '') {
            return '';
        }

        if ($base_name !== '' && stripos($clean_name, $base_name) === 0) {
            $clean_name = trim(substr($clean_name, strlen($base_name)), " -\t\n\r\0\x0B");
        }

        if (strpos($clean_name, ' - ') !== false) {
            $parts = array_filter(array_map('trim', explode(' - ', $clean_name)));
            $last_part = !empty($parts) ? (string) end($parts) : '';
            if ($last_part !== '') {
                return $last_part;
            }
        }

        return $clean_name;
    }
    
    /**
     * Render reports table preview
     */
    private function render_reports_table(array $filters = []): void {
        $rows = $this->get_reports_data($filters);
        $count = count($rows);

        echo '<div class="oc-reports-count">Total înregistrări: <strong>' . esc_html($count) . '</strong></div>';

        if (empty($rows)) {
            echo '<div class="oc-reports-empty">Nu există date pentru filtrele selectate.</div>';
            return;
        }

        echo '<table class="oc-reports-preview-table">';
        echo '<thead><tr>';
        $headers = ['Nr. Crt', 'Nume Prenume', 'Data Plată', 'Data Activare', 'Modalitate Plată', 'Sumă', 'Coplată', 'Tip Abonament', 'Cursuri', 'Nr. Scanări', 'Data Ultimei Scanări', 'Observații'];
        foreach ($headers as $h) {
            echo '<th>' . esc_html($h) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $nr = 1;
        foreach ($rows as $row) {
            $amount_cells = $this->get_report_amount_cells($row);
            $display_payment_date = $row['purchase_date'] ?? ($row['created_at'] ?? '');
            echo '<tr>';
            echo '<td>' . esc_html($nr++) . '</td>';
            echo '<td>' . esc_html($row['display_name'] ?? '') . '</td>';
            echo '<td>' . esc_html($this->format_report_date((string) $display_payment_date)) . '</td>';
            echo '<td>' . esc_html($this->format_report_date((string) ($row['activation_date'] ?? ''))) . '</td>';
            echo '<td>' . esc_html($this->format_payment_label($row['payment_method'] ?? '')) . '</td>';
            echo '<td>' . esc_html($amount_cells['suma']) . '</td>';
            echo '<td>' . esc_html($amount_cells['coplata']) . '</td>';
            echo '<td>' . esc_html($row['product_name'] ?? '') . '</td>';
            echo '<td>' . esc_html($row['courses_included'] ?? '') . '</td>';
            echo '<td>' . esc_html($row['report_scan_count'] ?? 0) . '</td>';
            echo '<td>' . esc_html($this->get_report_last_validation_display($row)) . '</td>';
            echo '<td>' . esc_html($row['observations'] ?? '') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Returnează datele pentru raport cu filtre dinamice
     */
    private function get_reports_data(array $filters): array {
        global $wpdb;
        $table = $this->validator_db->get_table_name('membership_validations');
        $log_table = $this->validator_db->get_table_name('membership_validation_log');

        $date_from = $filters['date_from'] ?? date('Y-m-01');
        $date_to   = $filters['date_to']   ?? date('Y-m-d');
        $status    = $filters['status']    ?? '';
        $payment   = $filters['payment_method'] ?? '';
        $course    = trim((string) ($filters['course'] ?? ''));
        $is_cancelled_filter = ($status === 'cancelled');

        // Normalizează data_to: include tot ziua (până la 23:59:59)
        $date_to_end = $date_to . ' 23:59:59';

        // Data raportului trebuie mapată la data reală a comenzii Woo, cu fallback pe DB.
        $date_filter_column = "COALESCE(NULLIF(p.post_date, ''), m.created_at)";

        $where  = ["{$date_filter_column} >= %s", "{$date_filter_column} <= %s"];
        $params = [$date_from . ' 00:00:00', $date_to_end];

        if (!empty($status)) {
            if ($is_cancelled_filter) {
                $where[]  = '(p.post_status = %s OR m.validation_status = %s)';
                $params[] = 'wc-cancelled';
                $params[] = 'cancelled';
            } else {
                $where[]  = 'm.validation_status = %s';
                $params[] = $status;
            }
        }

        if (!empty($payment)) {
            $payment_key = $this->normalize_payment_method_key($payment);
            if ($payment_key === 'oc_7card') {
                $where[]  = 'LOWER(m.payment_method) LIKE %s';
                $params[] = '%' . $wpdb->esc_like('7card') . '%';
            } elseif ($payment_key === 'oc_esx') {
                $where[]  = 'LOWER(m.payment_method) LIKE %s';
                $params[] = '%' . $wpdb->esc_like('esx') . '%';
            } elseif ($payment_key === 'transfer') {
                $where[]  = '(LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s)';
                $params[] = '%' . $wpdb->esc_like('transfer') . '%';
                $params[] = '%' . $wpdb->esc_like('bacs') . '%';
                $params[] = '%' . $wpdb->esc_like('iban') . '%';
            } elseif ($payment_key === 'cash') {
                $where[]  = '(LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s)';
                $params[] = '%' . $wpdb->esc_like('cash') . '%';
                $params[] = '%' . $wpdb->esc_like('numerar') . '%';
                $params[] = '%' . $wpdb->esc_like('studio') . '%';
                $params[] = '%' . $wpdb->esc_like('cod') . '%';
            } elseif ($payment_key === 'card') {
                $where[]  = '(LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s OR LOWER(m.payment_method) LIKE %s) AND LOWER(m.payment_method) NOT LIKE %s AND LOWER(m.payment_method) NOT LIKE %s';
                $params[] = '%' . $wpdb->esc_like('card') . '%';
                $params[] = '%' . $wpdb->esc_like('stripe') . '%';
                $params[] = '%' . $wpdb->esc_like('netopia') . '%';
                $params[] = '%' . $wpdb->esc_like('7card') . '%';
                $params[] = '%' . $wpdb->esc_like('esx') . '%';
            }
        }

        if ($course !== '') {
            $course_like = '%' . $wpdb->esc_like($course) . '%';
            $course_like_encoded = '%' . $wpdb->esc_like(htmlentities($course, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '%';
            $where[] = '(m.courses_included LIKE %s OR m.courses_included LIKE %s)';
            $params[] = $course_like;
            $params[] = $course_like_encoded;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);
        $has_observations_column = (bool) $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table} LIKE %s",
            'observations'
        ));
        $observations_sql = $has_observations_column
            ? "GROUP_CONCAT(DISTINCT NULLIF(TRIM(m.observations), '') ORDER BY m.observations SEPARATOR ' | ') AS observations,"
            : "'' AS observations,";

        $counts_daily_validations = $this->should_count_report_validations_by_day();

        // Un pachet cu mai multe cursuri are câte un rând per order_item în DB.
        // GROUP BY order_id elimină rândurile duplicate, agregând datele corect.
        // Logul de validare este pre-agregat pe order_id pentru a evita înmulțirea
        // rândurilor din m și pentru a putea număra zilele validate când regula este once_per_day.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    MIN(m.order_id)                                                             AS order_id,
                    MIN(m.user_id)                                                              AS user_id,
                    MIN(m.display_name)                                                          AS display_name,
                    MIN(COALESCE(NULLIF(p.post_date, ''), m.created_at))                        AS purchase_date,
                    MIN(m.start_date)                                                            AS activation_date,
                    MIN(m.created_at)                                                            AS created_at,
                    MIN(COALESCE(p.post_status, ''))                                             AS order_post_status,
                    MAX(CASE WHEN m.validation_status = 'cancelled' THEN 1 ELSE 0 END)          AS has_cancelled_status,
                    MAX(COALESCE(NULLIF(p.post_modified, ''), NULLIF(m.updated_at, ''), m.created_at)) AS status_changed_at,
                    MIN(m.payment_method)                                                        AS payment_method,
                    MIN(m.product_price)                                                         AS product_price,
                    MIN(m.product_name)                                                          AS product_name,
                    GROUP_CONCAT(DISTINCT NULLIF(TRIM(m.courses_included), '') ORDER BY m.courses_included SEPARATOR ', ') AS courses_included,
                    {$observations_sql}
                    SUM(m.used_sessions)                                                         AS used_sessions,
                    MAX(NULLIF(m.last_validation_date, '0000-00-00 00:00:00'))                   AS last_validation_date,
                    MAX(order_log_agg.last_success_date)                                         AS last_validation_fallback,
                    MAX(COALESCE(order_log_agg.actual_validation_days, 0))                       AS actual_validation_days
                 FROM {$table} m
                 LEFT JOIN {$wpdb->posts} p ON p.ID = m.order_id
                 LEFT JOIN (
                     SELECT mv.order_id,
                            COUNT(DISTINCT DATE(l.validation_date)) AS actual_validation_days,
                            MAX(l.validation_date) AS last_success_date
                     FROM {$table} mv
                     INNER JOIN {$log_table} l ON l.membership_id = mv.id
                     WHERE l.validation_status = 'success'
                       AND (l.validation_metadata IS NULL
                            OR (l.validation_metadata NOT LIKE '%\"event_type\":\"admin_adjustment\"%'
                                AND l.validation_metadata NOT LIKE '%\"event_type\":\"admin_cancel\"%'))
                       AND (
                            l.validation_metadata IS NULL
                            OR l.validation_metadata LIKE '%\"consumed\":true%'
                            OR l.validation_metadata LIKE '%\"code\":\"CHECK_IN_OK\"%'
                            OR l.validation_metadata LIKE '%\"endpoint\":\"check-in\"%'
                            OR l.validation_method IN ('qr_code', 'access_code', 'manual')
                       )
                       AND (l.validation_metadata IS NULL OR l.validation_metadata NOT LIKE '%\"code\":\"OK\"%')
                     GROUP BY mv.order_id
                 ) order_log_agg ON order_log_agg.order_id = m.order_id
                 {$where_sql}
                 GROUP BY m.order_id
                 ORDER BY MIN(COALESCE(NULLIF(p.post_date, ''), m.created_at)) ASC,
                          MIN(m.created_at) ASC,
                          MIN(m.order_id) ASC",
                ...$params
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            $order_id = (int) ($row['order_id'] ?? 0);
            $row['display_name'] = oc_membership_resolve_user_display_name(
                $user_id > 0 ? $user_id : null,
                $row,
                $order_id > 0 ? $order_id : null
            );
            $row['payment_method'] = $this->normalize_payment_method_key((string) ($row['payment_method'] ?? 'card'));
            $is_cancelled_row = ((string) ($row['order_post_status'] ?? '') === 'wc-cancelled')
                || ((int) ($row['has_cancelled_status'] ?? 0) === 1);
            $row['is_cancelled_report_row'] = $is_cancelled_row ? '1' : '0';
            $row['report_scan_count'] = (string) $this->resolve_report_scan_count($row, $counts_daily_validations);

            $raw_last_validation = trim((string) ($row['last_validation_date'] ?? ''));
            $is_zero_date = in_array($raw_last_validation, ['0000-00-00', '0000-00-00 00:00:00'], true);
            if ($raw_last_validation === '' || $is_zero_date) {
                $fallback_date = trim((string) ($row['last_validation_fallback'] ?? ''));
                if ($fallback_date !== '') {
                    $row['last_validation_date'] = $fallback_date;
                }
            }

            $resolved_last_validation = trim((string) ($row['last_validation_date'] ?? ''));
            $row['has_missing_validation_history'] = (
                (int) ($row['used_sessions'] ?? 0) > 0
                && ($resolved_last_validation === '' || in_array($resolved_last_validation, ['0000-00-00', '0000-00-00 00:00:00'], true))
            ) ? '1' : '0';

            if ($is_cancelled_row) {
                $existing_observations = trim((string) ($row['observations'] ?? ''));
                $row['observations'] = $existing_observations === ''
                    ? 'ANULAT'
                    : $existing_observations . ' | ANULAT';
            }

            if (($row['has_missing_validation_history'] ?? '0') === '1') {
                $existing_observations = trim((string) ($row['observations'] ?? ''));
                $sync_note = 'NESINCRONIZAT: sedinte folosite fara istoric validare';
                $row['observations'] = $existing_observations === ''
                    ? $sync_note
                    : $existing_observations . ' | ' . $sync_note;
            }
        }
        unset($row);

        return $results;
    }
    /**
     * Formatează data pentru raport: DD.MM.YYYY
     */
    private function format_report_date(string $date): string {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($date);
        return $ts ? date('d.m.Y', $ts) : '';
    }

    /**
     * Raportul trebuie sa numere validari pe zile cand regula activa este once_per_day.
     */
    private function should_count_report_validations_by_day(): bool {
        if (class_exists('OC_Membership_Smart_Validation_Service')) {
            $smart_validation_service = new OC_Membership_Smart_Validation_Service($this->validator_db);
            return $smart_validation_service->uses_daily_validation_lock();
        }

        // Fallback defensiv: pastreaza comportamentul actual daca serviciul nu este disponibil.
        $timing_rule = (string) get_option('oc_membership_validation_timing_rule', 'minutes_before_course');
        if ($timing_rule === 'once_per_day_after_hour') {
            return true;
        }
        $legacy_restriction = (string) get_option('oc_membership_validation_restriction', 'none');
        return $legacy_restriction === 'once_per_day';
    }

    /**
     * Stabileste numarul de scanari afisat in raport.
     * In modul once_per_day afisam numarul zilelor validate real, cu fallback pe used_sessions
     * pentru date istorice fara log complet.
     */
    private function resolve_report_scan_count(array $row, bool $counts_daily_validations): int {
        $used_sessions = max(0, (int) ($row['used_sessions'] ?? 0));
        if (!$counts_daily_validations) {
            return $used_sessions;
        }

        $actual_validation_days = max(0, (int) ($row['actual_validation_days'] ?? 0));
        if ($actual_validation_days > 0) {
            return $actual_validation_days;
        }

        return $used_sessions;
    }

    /**
     * Returneaza afisarea pentru coloana ultimei validari din raport.
     */
    private function get_report_last_validation_display(array $row): string {
        $formatted_date = $this->format_report_date((string) ($row['last_validation_date'] ?? ''));
        if ($formatted_date !== '') {
            return $formatted_date;
        }

        return (($row['has_missing_validation_history'] ?? '0') === '1')
            ? 'Nesincronizat'
            : '';
    }

    /**
     * Returneaza cursurile disponibile pentru filtrul din rapoarte.
     *
     * @return array<int,array{value:string,label:string}>
     */
    private function get_report_course_filter_options(): array {
        global $wpdb;

        $table = $this->validator_db->get_table_name('membership_validations');
        $raw_values = $wpdb->get_col(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            "SELECT DISTINCT courses_included
             FROM {$table}
             WHERE courses_included IS NOT NULL
               AND TRIM(courses_included) <> ''"
        );

        if (!is_array($raw_values) || empty($raw_values)) {
            return [];
        }

        $options_map = [];
        foreach ($raw_values as $raw_value) {
            $parts = explode(',', (string) $raw_value);
            foreach ($parts as $part) {
                $value = trim(wp_strip_all_tags((string) $part));
                if ($value === '') {
                    continue;
                }

                $label = trim(wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower($label, 'UTF-8')
                    : strtolower($label);

                if (!isset($options_map[$key])) {
                    $options_map[$key] = [
                        'value' => $value,
                        'label' => $label,
                    ];
                }
            }
        }

        if (empty($options_map)) {
            return [];
        }

        uasort($options_map, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($options_map);
    }

    /**
     * Returnează eticheta prietenoasă pentru metoda de plată
     */
    private function format_payment_label(string $method): string {
        $key = $this->normalize_payment_method_key($method);
        $labels = $this->get_canonical_payment_methods();
        return $labels[$key] ?? $labels['card'];
    }

    /**
     * Metodele 7Card/ESX trebuie tratate ca "Coplată" în rapoarte.
     */
    private function is_copayment_method(string $method): bool {
        $key = $this->normalize_payment_method_key($method);
        return in_array($key, ['oc_7card', 'oc_esx'], true);
    }

    /**
     * Metode canonice folosite în plugin.
     *
     * @return array<string,string>
     */
    private function get_canonical_payment_methods(): array {
        return [
            'cash' => 'Cash / Plată la studio',
            'card' => 'Card',
            'oc_7card' => '7Card',
            'oc_esx' => 'ESX',
            'transfer' => 'Transfer bancar',
        ];
    }

    /**
     * Normalizează orice variantă textuală la cheia canonică a plugin-ului.
     */
    private function normalize_payment_method_key(string $raw): string {
        $value = trim($raw);
        if ($value === '') {
            return 'card';
        }

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        if (strpos($normalized, '7card') !== false) {
            return 'oc_7card';
        }
        if (strpos($normalized, 'esx') !== false) {
            return 'oc_esx';
        }
        if (strpos($normalized, 'transfer') !== false || strpos($normalized, 'bacs') !== false || strpos($normalized, 'iban') !== false) {
            return 'transfer';
        }
        if (strpos($normalized, 'cash') !== false || strpos($normalized, 'numerar') !== false || strpos($normalized, 'studio') !== false || strpos($normalized, 'cod') !== false) {
            return 'cash';
        }
        if (strpos($normalized, 'card') !== false || strpos($normalized, 'stripe') !== false || strpos($normalized, 'netopia') !== false) {
            return 'card';
        }

        // Fallback pentru gateway-uri noi: tratăm generic ca plată cu cardul.
        return 'card';
    }

    /**
     * Împarte valoarea în coloanele Sumă/Coplată în funcție de metoda de plată.
     * Pentru 7Card/ESX valoarea merge în Coplată, iar Sumă rămâne goală.
     *
     * @param array<string,mixed> $row
     * @return array{suma:string,coplata:string}
     */
    private function get_report_amount_cells(array $row): array {
        if ((string) ($row['is_cancelled_report_row'] ?? '0') === '1') {
            return ['suma' => '0', 'coplata' => ''];
        }

        $price = (float) ($row['product_price'] ?? 0);
        $formatted = $price > 0 ? number_format($price, 0, ',', '') : '';

        if ($this->is_copayment_method((string) ($row['payment_method'] ?? ''))) {
            return ['suma' => '', 'coplata' => $formatted];
        }

        return ['suma' => $formatted, 'coplata' => ''];
    }

    /**
     * Sincronizare manuala, non-distructiva, pentru datele cached din membership_validations.
     * Nu sterge si nu recreeaza tabele.
     *
     * @return array{created:int,processed:int,updated:int,skipped:int,errors:array<int,string>}
     */
    private function run_safe_manual_cached_sync(): array {
        global $wpdb;

        $result = [
            'created' => 0,
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $creation_result = $this->create_missing_memberships_non_destructive();
        $result['created'] = (int) ($creation_result['created'] ?? 0);
        if (!empty($creation_result['errors']) && is_array($creation_result['errors'])) {
            $result['errors'] = array_merge($result['errors'], $creation_result['errors']);
        }

        $table_name = $this->validator_db->get_table_name('membership_validations');
        $rows = $wpdb->get_results(
            "SELECT id, order_id, user_id FROM {$table_name} WHERE order_id IS NOT NULL AND order_id > 0",
            ARRAY_A
        );

        if (empty($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            $membership_id = (int) ($row['id'] ?? 0);
            $order_id = (int) ($row['order_id'] ?? 0);
            $user_id = (int) ($row['user_id'] ?? 0);

            if ($membership_id <= 0 || $order_id <= 0) {
                $result['skipped']++;
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                $result['skipped']++;
                continue;
            }

            $update_data = [
                'product_name' => $this->get_manual_sync_product_name($order),
                'product_price' => number_format((float) $order->get_total(), 2, '.', ''),
                'payment_method' => $this->normalize_payment_method_key(
                    (string) $order->get_payment_method() . ' ' . (string) $order->get_payment_method_title()
                ),
                'payment_status' => $this->map_order_status_to_payment_for_sync((string) $order->get_status()),
                'member_discount' => $this->get_manual_sync_coupons($order),
                'courses_included' => $this->get_manual_sync_courses($order),
                'cached_data_synced_at' => current_time('mysql'),
            ];

            if ($user_id === 0) {
                $update_data['display_name'] = trim((string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name());
                $update_data['email'] = (string) $order->get_billing_email();
                $update_data['phone'] = (string) $order->get_billing_phone();
                if ($order->get_date_created()) {
                    $update_data['user_registered'] = $order->get_date_created()->date('Y-m-d H:i:s');
                }
            }

            $updated = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $membership_id],
                array_fill(0, count($update_data), '%s'),
                ['%d']
            );

            $result['processed']++;

            if ($updated === false) {
                $result['errors'][] = sprintf('Membership #%d (order #%d): update failed.', $membership_id, $order_id);
                continue;
            }

            $result['updated']++;
        }

        return $result;
    }

    /**
     * Populeaza non-distructiv membership_validations cu comenzi Woo lipsa.
     *
     * @return array{created:int,errors:array<int,string>}
     */
    private function create_missing_memberships_non_destructive(): array {
        global $wpdb;

        $result = [
            'created' => 0,
            'errors' => [],
        ];

        if (!class_exists('OC_Membership_Validator')) {
            $result['errors'][] = 'Validator core unavailable.';
            return $result;
        }

        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            $result['errors'][] = 'Validator instance unavailable.';
            return $result;
        }

        $table_name = $this->validator_db->get_table_name('membership_validations');
        $before = (int) $wpdb->get_var("SELECT COUNT(DISTINCT order_id) FROM {$table_name} WHERE order_id IS NOT NULL AND order_id > 0");

        if (method_exists($validator, 'sync_existing_orders')) {
            for ($i = 0; $i < 20; $i++) {
                $batch = $validator->sync_existing_orders();
                if (!is_array($batch)) {
                    break;
                }

                $found = (int) ($batch['total_found'] ?? 0);
                $synced = (int) ($batch['synced'] ?? 0);

                if (!empty($batch['errors']) && is_array($batch['errors'])) {
                    foreach ($batch['errors'] as $err) {
                        $result['errors'][] = (string) $err;
                    }
                }

                if ($found < 100 || $synced === 0) {
                    break;
                }
            }
        }

         $missing_processing = $wpdb->get_col(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-completed')
               AND p.ID NOT IN (
                    SELECT DISTINCT order_id
                    FROM {$table_name}
                    WHERE order_id IS NOT NULL AND order_id > 0
               )
             ORDER BY p.post_date DESC
             LIMIT 500"
        );

        if (!empty($missing_processing)) {
            foreach ($missing_processing as $order_id) {
                $validator->process_new_membership((int) $order_id);
            }
        }

        $after = (int) $wpdb->get_var("SELECT COUNT(DISTINCT order_id) FROM {$table_name} WHERE order_id IS NOT NULL AND order_id > 0");
        $result['created'] = max(0, $after - $before);

        return $result;
    }

    private function get_manual_sync_product_name(WC_Order $order): string {
        foreach ($order->get_items() as $item) {
            if ((float) $item->get_total() > 0 && (int) $item->get_variation_id() === 0) {
                $name = trim((string) $item->get_name());
                if ($name !== '') {
                    return $name;
                }
            }
        }

        foreach ($order->get_items() as $item) {
            $name = trim((string) $item->get_name());
            if ($name !== '') {
                return $name;
            }
        }

        return 'N/A';
    }

    private function get_manual_sync_courses(WC_Order $order): string {
        $courses = [];
        foreach ($order->get_items() as $item) {
            if ((int) $item->get_variation_id() > 0) {
                $name = trim((string) $item->get_name());
                if ($name !== '') {
                    $courses[] = $name;
                }
            }
        }

        $courses = array_values(array_unique($courses));
        return empty($courses) ? '' : implode(', ', $courses);
    }

    private function get_manual_sync_coupons(WC_Order $order): string {
        $coupons = [];
        foreach ($order->get_coupons() as $coupon_item) {
            $code = trim((string) $coupon_item->get_code());
            if ($code !== '') {
                $coupons[] = $code;
            }
        }

        $coupons = array_values(array_unique($coupons));
        return empty($coupons) ? '' : implode(', ', $coupons);
    }

    private function map_order_status_to_payment_for_sync(string $status): string {
        $normalized = trim(strtolower($status));

        $paid_statuses = ['wc-completed', 'wc-processing', 'completed', 'processing'];
        if (in_array($normalized, $paid_statuses, true)) {
            return 'paid';
        }

        $partial_statuses = ['wc-on-hold', 'on-hold'];
        if (in_array($normalized, $partial_statuses, true)) {
            return 'partial';
        }

        $unpaid_statuses = ['wc-cancelled', 'wc-refunded', 'cancelled', 'refunded', 'failed'];
        if (in_array($normalized, $unpaid_statuses, true)) {
            return 'unpaid';
        }

        return 'unpaid';
    }
    
    /**
     * AJAX handler pentru analytics data
     */
    public function ajax_analytics_data(): void {
        check_ajax_referer('oc_membership_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        $analytics_data = $this->get_analytics_data();
        wp_send_json_success($analytics_data);
    }
    
    /**
     * AJAX handler pentru export data (CSV sau XLSX)
     */
    public function ajax_export_data(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        // Nonce check
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'oc_membership_export')) {
            wp_die('Invalid nonce');
        }

        $format = isset($_GET['format']) && $_GET['format'] === 'xlsx' ? 'xlsx' : 'csv';

        // Filtre
        $date_from_raw = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : date('Y-m-01');
        $date_to_raw   = isset($_GET['date_to'])   ? sanitize_text_field(wp_unslash($_GET['date_to']))   : date('Y-m-d');
        $date_from     = date('Y-m-d', strtotime($date_from_raw) ?: strtotime(date('Y-m-01')));
        $date_to       = date('Y-m-d', strtotime($date_to_raw)   ?: strtotime(date('Y-m-d')));

        $filters = [
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'status'         => sanitize_text_field(wp_unslash($_GET['status']         ?? '')),
            'payment_method' => sanitize_text_field(wp_unslash($_GET['payment_method'] ?? '')),
            'course'         => sanitize_text_field(wp_unslash($_GET['course'] ?? '')),
        ];

        $rows = $this->get_reports_data($filters);

        // Coloane export (ordinea din exemplul CSV)
        $col_headers = [
            'Nr. Crt',
            'Nume Prenume',
            'Data Plata',
            'Data Activare',
            'Metoda de plata',
            'Suma',
            'Coplata',
            'Tip Abonament',
            'Cursuri',
            'Nr. Scanari',
            'Data Ultima Scanare',
            'Observatii',
        ];

        $month_label = date('m_Y', strtotime($date_from));
        $download_label = oc_membership_current_local_datetime()->format('d_m_Y');
        $base_name   = 'raport_abonamente_' . $month_label . '_download_' . $download_label;

        if ($format === 'xlsx') {
            // --- XLSX ---
            require_once plugin_dir_path(__FILE__) . '../../libs/xlsxwriter.class.php';

            $writer = new OC_XLSXWriter();
            $col_types = [
                'Nr. Crt'              => 'integer',
                'Nume Prenume'         => 'string',
                'Data Plata'           => 'string',
                'Data Activare'        => 'string',
                'Metoda de plata'      => 'string',
                'Suma'                 => 'integer',
                'Coplata'              => 'string',
                'Tip Abonament'        => 'string',
                'Cursuri'              => 'string',
                'Nr. Scanari'          => 'integer',
                'Data Ultima Scanare'  => 'string',
                'Observatii'           => 'string',
            ];
            $writer->writeSheetHeader('Raport', $col_types, [
                'font_scale' => 1.3,
                'row_height_multiplier' => 1.3,
                'widths' => [8, 28, 14, 14, 18, 10, 10, 20, 35, 12, 20, 35],
            ]);

            $nr = 1;
            foreach ($rows as $row) {
                $amount_cells = $this->get_report_amount_cells($row);
                $export_payment_date = $row['purchase_date'] ?? ($row['created_at'] ?? '');
                $writer->writeSheetRow('Raport', [
                    $nr++,
                    $row['display_name']        ?? '',
                    $this->format_report_date((string) $export_payment_date),
                    $this->format_report_date((string) ($row['activation_date'] ?? '')),
                    $this->format_payment_label($row['payment_method'] ?? ''),
                    $amount_cells['suma'] === '' ? '' : (int) str_replace(',', '', $amount_cells['suma']),
                    $amount_cells['coplata'],
                    $row['product_name']        ?? '',
                    $row['courses_included']    ?? '',
                    (int)($row['report_scan_count'] ?? 0),
                    $this->get_report_last_validation_display($row),
                    $row['observations']        ?? '',
                ]);
            }

            // Generăm în temp file ÎNAINTE de a curăța output bufferele
            $tmp_xlsx = tempnam(sys_get_temp_dir(), 'oc_xlsx_');
            $writer->writeToFile($tmp_xlsx);
            $xlsx_size = filesize($tmp_xlsx);

            // Curățăm TOATE output bufferele WordPress (ob_start) — altfel datele binare se corup
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $base_name . '.xlsx"');
            header('Content-Length: ' . $xlsx_size);
            header('Cache-Control: max-age=0');

            readfile($tmp_xlsx);
            unlink($tmp_xlsx);
            exit;
        }

        // --- CSV ---
        // Curățăm output bufferele WordPress înainte de a trimite fișierul
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $base_name . '.csv"');
        header('Cache-Control: max-age=0');

        $output = fopen('php://output', 'w');
        // BOM pentru Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Header row cu separator ;
        fputcsv($output, $col_headers, ';');

        $nr = 1;
        foreach ($rows as $row) {
            $amount_cells = $this->get_report_amount_cells($row);
            $export_payment_date = $row['purchase_date'] ?? ($row['created_at'] ?? '');
            fputcsv($output, [
                $nr++,
                $row['display_name']        ?? '',
                $this->format_report_date((string) $export_payment_date),
                $this->format_report_date((string) ($row['activation_date'] ?? '')),
                $this->format_payment_label($row['payment_method'] ?? ''),
                $amount_cells['suma'],
                $amount_cells['coplata'],
                $row['product_name']        ?? '',
                $row['courses_included']    ?? '',
                (int)($row['report_scan_count'] ?? 0),
                $this->get_report_last_validation_display($row),
                $row['observations']        ?? '',
            ], ';');
        }

        fclose($output);
        exit;
    }
}
