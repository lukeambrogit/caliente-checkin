<?php
/**
 * Client Dashboard pentru WooCommerce My Account - FAZA 3
 * 
 * CONFORMITATE .cursorrules:
 * - Integrare NON-INTRUZIVĂ cu WooCommerce My Account
 * - Citire doar din ADD-ON #1 și tabele existente
 * - Mobile-friendly și responsive design
 * - Best practices 2025: UX, accessibility, performance
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Dashboard
 * 
 * Client dashboard pentru gestionarea membership-urilor în My Account
 * Best practices 2025: Progressive enhancement, responsive design
 */
class OC_Membership_Dashboard {
    
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
     * Render dashboard-ul principal pentru client
     * 
     * v1.2.0: BEST PRACTICES 2025 - ZERO CSS/JS INLINE!
     * - Folosește wp_enqueue_style/script pentru fișiere separate
     * - wp_localize_script pentru date JavaScript
     * - Afișare 1 card pachet + carduri cursuri grupate
     * - Modern, accessible, responsive
     */
    public function render_client_dashboard(): void {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<p>Autentifică-te pentru a vedea abonamentele.</p>';
            return;
        }
        
        // ✅ ENQUEUE FIȘIERE SEPARATE (BEST PRACTICE 2025!)
        wp_enqueue_style(
            'oc-membership-dashboard', 
            OC_PLUGIN_URL . 'assets/membership-dashboard.css', 
            [], 
            filemtime(OC_PLUGIN_DIR . 'assets/membership-dashboard.css')
        );
        
        // 🎯 RESPONSIVE FIXES - Mobile-friendly & overflow fixes
        wp_enqueue_style(
            'oc-membership-responsive-fixes',
            OC_PLUGIN_URL . 'assets/membership-responsive-fixes.css',
            ['oc-membership-dashboard'], // Depinde de CSS-ul principal
            filemtime(OC_PLUGIN_DIR . 'assets/membership-responsive-fixes.css')
        );
        
        wp_enqueue_script(
            'oc-membership-dashboard', 
            OC_PLUGIN_URL . 'assets/membership-dashboard.js', 
            ['jquery'], 
            filemtime(OC_PLUGIN_DIR . 'assets/membership-dashboard.js'), 
            true
        );
        
        // ✅ LOCALIZE SCRIPT pentru date (BEST PRACTICE 2025!)
        wp_localize_script('oc-membership-dashboard', 'ocMembershipData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oc_membership_dashboard'),
            'userId' => $user_id,
            'autoRefresh' => false, // Session refresh disabled (oc_get_membership_sessions handler not implemented)
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'dateFormat' => get_option('date_format'),
            'timeFormat' => get_option('time_format'),
            'locale' => get_locale(),
            'translations' => [
                'loading' => __('Loading...', OC_TEXT_DOMAIN),
                'error' => __('Error loading data', OC_TEXT_DOMAIN),
                'noSessions' => __('No sessions remaining', OC_TEXT_DOMAIN),
                'updated' => __('Data updated', OC_TEXT_DOMAIN)
            ]
        ]);

        // 🎯 v1.2.0: Obține membership-uri GRUPATE după pachet
        $memberships = $this->get_user_memberships_grouped_by_package($user_id);
        
        if (empty($memberships)) {
            ?>
            <div class="oc-no-memberships">
                <p>Nu ai abonamente active.</p>
                <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button">Cumpără Abonament</a>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="oc-membership-dashboard" data-user-id="<?php echo esc_attr($user_id); ?>">
            <?php foreach ($memberships as $package_data): ?>
                
                <!-- CARD PACHET -->
                <div class="oc-package-card">
                    <div class="package-header">
                        <h2><?php echo esc_html($package_data['package_name']); ?></h2>
                        <span class="package-price"><?php echo number_format($package_data['package_price'], 2); ?> lei</span>
                    </div>
                    <div class="package-meta">
                        <span class="package-date">
                            📅 Cumpărat: <?php echo esc_html(wp_date(get_option('date_format'), strtotime($package_data['purchase_date']))); ?>
                        </span>
                        <span class="package-courses-count">
                            🎵 <?php echo count($package_data['courses']); ?> cursuri incluse
                        </span>
                    </div>
            </div>
            
                <!-- CARDURI CURSURI -->
                <div class="oc-courses-grid">
                    <?php foreach ($package_data['courses'] as $course): ?>
                        <div class="oc-course-card <?php echo $course['is_unlimited'] ? 'vip-unlimited' : ''; ?>" 
                             data-validation-id="<?php echo esc_attr($course['validation_id']); ?>">
                            <div class="course-name">
                                <span class="course-icon">🎵</span>
                                <strong><?php echo esc_html($course['course_name']); ?></strong>
                    </div>
                            
                            <?php if ($course['is_unlimited']): ?>
                                <div class="unlimited-badge">
                                    <span class="unlimited-icon">∞</span>
                                    <span class="unlimited-text">Acces Nelimitat</span>
                </div>
            <?php else: ?>
                                <div class="progress-section">
                                    <div class="sessions-info">
                                        <span class="sessions-remaining" data-remaining="<?php echo $course['sessions_remaining']; ?>">
                                            <?php echo $course['sessions_remaining']; ?>
                                        </span>
                                        <span class="sessions-separator">/</span>
                                        <span class="sessions-total"><?php echo $course['sessions_allocated']; ?></span>
                                        <span class="sessions-label">ședințe</span>
                                    </div>
                                    <div class="progress-bar-wrapper">
                                        <progress value="<?php echo $course['sessions_used']; ?>" 
                                                  max="<?php echo $course['sessions_allocated']; ?>"
                                                  class="oc-progress-bar">
                                        </progress>
                                    </div>
                                    <div class="sessions-used-label">
                                        <small><?php echo $course['sessions_used']; ?> ședințe folosite</small>
                                    </div>
                </div>
            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
        </div>
        
            <?php endforeach; ?>
        </div>
        
        <!-- ✅ ZERO CSS/JS INLINE! Totul în fișiere separate! -->
        <?php
    }
    
    /**
     * Obține membership-uri grupate după pachet
     * 
     * v1.2.0: Query SIMPLU - TOT din membership_validations!
     * 
     * @param int $user_id ID utilizator
     * @return array Date grupate [package_key => [...]]
     */
    private function get_user_memberships_grouped_by_package(int $user_id): array {
        global $wpdb;
        
        $validator = OC_Membership_Validator::get_instance();
        $db = $validator->get_db();
        $validations_table = $db->get_table_name('membership_validations');
        
        // Query SIMPLU - TOT din membership_validations!
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                id as validation_id,
                package_product_id,
                package_order_item_id,
                order_id,
                variation_id as course_variation_id,
                created_at,
                sessions_allocated,
                used_sessions as sessions_used,
                remaining_sessions as sessions_remaining,
                is_unlimited
            FROM {$validations_table}
            WHERE user_id = %d 
            AND validation_status = 'active'
            AND (expiration_date IS NULL OR expiration_date > NOW())
            ORDER BY package_order_item_id, created_at DESC
        ", $user_id), ARRAY_A);
        
        // Grupare după pachet
        $grouped = [];
        foreach ($results as $row) {
            $package_key = $row['package_order_item_id'] ?: $row['validation_id'];
            
            if (!isset($grouped[$package_key])) {
                $package_product = wc_get_product($row['package_product_id']);
                
                // Preț pachet
                $order = wc_get_order($row['order_id']);
                $package_price = 0;
                if ($order) {
                    $package_item = $order->get_item($row['package_order_item_id']);
                    if ($package_item) {
                        $package_price = $package_item->get_total();
                    }
                }
                
                $grouped[$package_key] = [
                    'package_name' => $package_product ? $package_product->get_name() : 'Abonament',
                    'package_price' => $package_price,
                    'purchase_date' => $row['created_at'],
                    'courses' => []
                ];
            }
            
            // Nume curs (nu ID!)
            $course_product = wc_get_product($row['course_variation_id']);
            $course_name = $course_product ? $course_product->get_name() : 'Curs #' . $row['course_variation_id'];
            
            $grouped[$package_key]['courses'][] = [
                'validation_id' => $row['validation_id'],
                'course_name' => $course_name,
                'sessions_allocated' => (int)$row['sessions_allocated'],
                'sessions_used' => (int)$row['sessions_used'],
                'sessions_remaining' => (int)$row['sessions_remaining'],
                'is_unlimited' => (bool)$row['is_unlimited']
            ];
        }
        
        return $grouped;
    }
    
    /**
     * Render o singură cartelă de membership
     */
    private function render_membership_card(array $membership): void {
        $sessions_used = (int)$membership['sessions_used'];
        $sessions_total = (int)$membership['total_sessions'];
        $sessions_remaining = $membership['sessions_remaining'];
        $progress_percentage = $sessions_total > 0 ? ($sessions_used / $sessions_total) * 100 : 0;
        
        // Determină status-ul pentru display
        $status_class = 'active';
        $status_text = __('Active', OC_TEXT_DOMAIN);
        
        if (oc_membership_is_expired((string) ($membership['expires_at'] ?? ''))) {
            $status_class = 'expired';
            $status_text = __('Expired', OC_TEXT_DOMAIN);
        } elseif ($sessions_remaining <= 0) {
            $status_class = 'completed';
            $status_text = __('Completed', OC_TEXT_DOMAIN);
        }
        
        ?>
        <div class="oc-membership-card">
            <div class="oc-card-header">
                <h3 class="oc-card-title"><?php echo esc_html($membership['product_name']); ?></h3>
                <span class="oc-card-status <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($status_text); ?>
                </span>
            </div>
            
            <div class="oc-sessions-progress">
                <div class="oc-sessions-info">
                    <span class="oc-sessions-text">
                        <?php printf(
                            esc_html__('Sessions: %d used of %d', OC_TEXT_DOMAIN),
                            $sessions_used,
                            $membership['total_sessions']
                        ); ?>
                    </span>
                    <span class="oc-sessions-remaining">
                        <?php printf(
                            esc_html__('%d remaining', OC_TEXT_DOMAIN),
                            $sessions_remaining
                        ); ?>
                    </span>
                </div>
                <div class="oc-progress-bar">
                    <div class="oc-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
                </div>
            </div>
            
            <?php if ($membership['expires_at']): ?>
            <div class="oc-expiry-info">
                <small class="oc-expiry-text">
                    <?php printf(
                        esc_html__('Expires: %s', OC_TEXT_DOMAIN),
                        wp_date(get_option('date_format'), strtotime($membership['expires_at']))
                    ); ?>
                </small>
            </div>
            <?php endif; ?>
            
            <div class="oc-card-actions">
                <?php if ($membership['has_qr_code'] && $status_class === 'active' && $sessions_remaining > 0): ?>
                    <button class="oc-btn oc-btn-primary oc-show-qr" 
                            data-validation-id="<?php echo esc_attr($membership['validation_id']); ?>"
                            data-product-name="<?php echo esc_attr($membership['product_name']); ?>">
                        <?php esc_html_e('Show QR Code', OC_TEXT_DOMAIN); ?>
                    </button>
                <?php endif; ?>
                
                <a href="<?php echo esc_url($this->get_product_url($membership['product_id'])); ?>" 
                   class="oc-btn oc-btn-secondary">
                    <?php esc_html_e('View Details', OC_TEXT_DOMAIN); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obține URL-ul produsului WooCommerce
     */
    private function get_product_url(int $product_id): string {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return wc_get_page_permalink('shop');
        }
        
        return $product->get_permalink();
    }
    
    /**
     * Render mobile-optimized membership summary
     */
    public function render_mobile_summary(): void {
        // Pentru shortcode usage pe mobile
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $validator_instance = OC_Membership_Validator::get_instance();
        $validation_handler = $validator_instance->get_validator();
        
        if (!$validation_handler) {
            return;
        }
        
        $memberships = $validation_handler->get_user_active_memberships($user_id);
        $active_count = count(array_filter($memberships, function($m) {
            return $m['sessions_remaining'] > 0;
        }));
        
        ?>
        <div class="oc-mobile-summary">
            <div class="oc-summary-stat">
                <span class="oc-stat-number"><?php echo esc_html($active_count); ?></span>
                <span class="oc-stat-label"><?php esc_html_e('Active Memberships', OC_TEXT_DOMAIN); ?></span>
            </div>
            
            <?php if ($active_count > 0): ?>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('membership')); ?>" 
                   class="oc-summary-link">
                    <?php esc_html_e('Manage Memberships', OC_TEXT_DOMAIN); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <style>
        .oc-mobile-summary {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
        }
        
        .oc-summary-stat {
            display: block;
            margin-bottom: 15px;
        }
        
        .oc-stat-number {
            display: block;
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            line-height: 1;
        }
        
        .oc-stat-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .oc-summary-link {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: #fff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .oc-summary-link:hover {
            background: #005a87;
            color: #fff !important;
        }
        </style>
        <?php
    }
}
