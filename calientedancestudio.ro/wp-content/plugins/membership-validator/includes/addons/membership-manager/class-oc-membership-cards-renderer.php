<?php
/**
 * Cards Renderer - REFACTORED din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR rendering-ul card-urilor pentru users
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - PĂSTREAZĂ EXACT funcționalitățile existente pentru cards
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Cards_Renderer
 * 
 * Gestionează rendering-ul card-urilor pentru users:
 * - Cards view pentru utilizatori normali
 * - Admin cards view expandabile  
 * - Modal-uri QR codes
 * - Layout-uri responsive
 */
class OC_Membership_Cards_Renderer {
    
    use OC_Membership_Pricing;
    use OC_Membership_Courses;
    use OC_Membership_WooCommerce;
    
    /**
     * @var OC_Membership_DB Database handler din ADD-ON #1
     */
    private OC_Membership_DB $validator_db;
    
    /**
     * @var OC_Membership_Data_Handler Data handler pentru procesare date
     */
    private OC_Membership_Data_Handler $data_handler;
    
    /**
     * Constructor cu dependency injection
     */
    public function __construct(OC_Membership_DB $validator_db, OC_Membership_Data_Handler $data_handler) {
        $this->validator_db = $validator_db;
        $this->data_handler = $data_handler;
    }
    
    /**
     * Renderează cards view pentru utilizatori
     * EXTRAS din versiunea originală - linia 144
     */
    public function render_cards_view(int $current_user_id, bool $is_admin): string {
        if ($is_admin) {
            // 👨‍💼 ADMIN VIEW: Toate membership-urile cu tools complete
            $target_user_id = null; // toate membership-urile
            $show_stats = true;     // statistici complete
            $show_qr = true;        // QR codes pentru management
            $layout = 'full';       // layout complet cu detalii
            $view_all_members = true;
            $show_expired = true;   // admins văd TOTUL (activ + expirat)
        } else {
            // 👤 USER VIEW: Doar propriile membership-uri
            $target_user_id = $current_user_id;
            $show_stats = true;     // statistici personale
            $show_qr = true;        // QR codes pentru utilizare
            $layout = 'compact';    // layout simplu și curat
            $view_all_members = false;
            $show_expired = true;   // users văd TOTUL (activ + expirat)
        }
        
        $memberships = $this->get_user_memberships($target_user_id, $show_expired);
        $summary = $view_all_members ? $this->get_all_summary() : $this->get_user_summary($target_user_id ?: $current_user_id);
        
        ob_start();
        ?>
        <div class="oc-membership-page oc-layout-<?php echo esc_attr($layout); ?>">
            <!-- Header cu statistici generale -->
            <?php if ($show_stats): ?>
                <?php echo $this->render_page_header($view_all_members, $summary, $show_qr); ?>
            <?php endif; ?>
            
            <!-- Lista de membership-uri -->
            <?php if (empty($memberships)): ?>
                <?php echo $this->render_empty_state(); ?>
            <?php else: ?>
                <div class="oc-memberships-grid">
                    <?php foreach ($memberships as $membership): ?>
                        <?php echo $this->render_membership_card($membership, $show_qr, $view_all_members); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Modal pentru QR codes -->
            <?php if ($show_qr): ?>
                <?php echo $this->render_qr_modal(); ?>
            <?php endif; ?>
        </div>
        
        <!-- CSS și JavaScript pentru cards -->
        <?php echo $this->render_cards_styles(); ?>
        <?php echo $this->render_cards_scripts(); ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Renderează header-ul paginii cu statistici
     * EXTRAS din versiunea originală - linia 152
     */
    private function render_page_header(bool $view_all_members, array $summary, bool $show_qr): string {
        ob_start();
        ?>
        <div class="oc-page-header">
            <h2><?php 
                if ($view_all_members) {
                    esc_html_e('All Memberships (Admin View)', OC_TEXT_DOMAIN);
                } else {
                    esc_html_e('My Memberships', OC_TEXT_DOMAIN);
                }
            ?></h2>
            <div class="oc-stats-overview">
                <div class="oc-stat">
                    <span class="oc-stat-number"><?php 
                        echo esc_html($view_all_members ? $summary['total_active'] : $summary['active_memberships']); 
                    ?></span>
                    <span class="oc-stat-label"><?php esc_html_e('Active', OC_TEXT_DOMAIN); ?></span>
                </div>
                
                <?php if ($view_all_members): ?>
                <div class="oc-stat">
                    <span class="oc-stat-number"><?php echo esc_html($summary['total_users']); ?></span>
                    <span class="oc-stat-label"><?php esc_html_e('Users', OC_TEXT_DOMAIN); ?></span>
                </div>
                <div class="oc-stat">
                    <span class="oc-stat-number"><?php echo esc_html($summary['total_sessions_used']); ?></span>
                    <span class="oc-stat-label"><?php esc_html_e('Sessions Used', OC_TEXT_DOMAIN); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($summary['next_expiry']): ?>
                <div class="oc-stat">
                    <span class="oc-stat-date"><?php echo esc_html(wp_date(get_option('date_format'), strtotime($summary['next_expiry']))); ?></span>
                    <span class="oc-stat-label"><?php esc_html_e('Next Expiry', OC_TEXT_DOMAIN); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($show_qr && $summary['has_active_qr']): ?>
                <div class="oc-stat">
                    <button class="oc-quick-qr-btn" data-validation-id="<?php echo esc_attr($summary['latest_validation_id']); ?>">
                        📱 Quick QR
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render membership card pentru shortcode cu RENEWAL SYSTEM support
     * 
     * @since 1.3.0 - Support pentru pending/active/expired status
     */
    public function render_membership_card(array $membership, bool $show_qr = true, bool $admin_view = false): string {
        $sessions_used = (int)($membership['used_sessions'] ?? 0);
        $sessions_total = (int)($membership['total_sessions'] ?? 0);
        $sessions_remaining = $membership['sessions_remaining'] ?? 0;
        $progress_percentage = $sessions_total > 0 ? ($sessions_used / $sessions_total) * 100 : 0;
        
        // 🎯 v1.3.0: Determină status din validation_status (nu din calcule)
        $validation_status = $membership['validation_status'] ?? 'active';
        $start_date = $membership['start_date'] ?? null;
        $expiration_date = $membership['expiration_date'] ?? $membership['expires_at'] ?? null;
        $is_renewal = (bool)($membership['is_renewal'] ?? false);
        
        // Map status la clase CSS
        switch ($validation_status) {
            case 'pending':
                $status_class = 'pending';
                $status_text = __('⏳ În așteptare', OC_TEXT_DOMAIN);
                break;
            case 'expired':
                $status_class = 'expired';
                $status_text = __('✕ Expirat', OC_TEXT_DOMAIN);
                break;
            case 'active':
            default:
                // Check dacă e aproape de expirare pentru warning
                $days_until_expiry = oc_membership_days_until_expiry((string) $expiration_date);
                if ($expiration_date && $days_until_expiry !== null && $days_until_expiry <= 7 && !oc_membership_is_expired((string) $expiration_date)) {
                    $status_class = 'active expires-soon';
                    $status_text = __('✓ Activ (expiră curând)', OC_TEXT_DOMAIN);
                } else {
                    $status_class = 'active';
                    $status_text = __('✓ Activ', OC_TEXT_DOMAIN);
                }
                break;
        }
        
        ob_start();
        ?>
        <div class="oc-membership-card oc-card-<?php echo esc_attr($validation_status); ?>" 
             data-status="<?php echo esc_attr($validation_status); ?>"
             data-order-id="<?php echo esc_attr($membership['order_id'] ?? 0); ?>"
             data-user-id="<?php echo esc_attr($membership['user_id'] ?? 0); ?>"
             style="transition: opacity 0.3s ease-out, transform 0.3s ease-out;">
            <div class="oc-card-header">
                <h3><?php echo esc_html($membership['product_name']); ?></h3>
                <?php if ($admin_view && isset($membership['user_name'])): ?>
                    <div class="oc-user-info">
                        <small><?php echo esc_html($membership['user_name']); ?></small>
                        <?php if (!empty($membership['user_email'])): ?>
                            <small style="color: #666;"><?php echo esc_html($membership['user_email']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <span class="oc-status oc-status-<?php echo esc_attr($status_class); ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
            
            <?php if ($validation_status === 'pending'): ?>
                <!-- 🎯 PENDING CARD: Info despre când începe -->
                <div class="oc-card-progress">
                    <div class="oc-progress-info">
                        <span><?php printf(esc_html__('%d ședințe disponibile', OC_TEXT_DOMAIN), $sessions_total); ?></span>
                        <span class="oc-new-badge" style="background: #0073aa; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75em;">NOU</span>
                    </div>
                    <div class="oc-progress-bar">
                        <div class="oc-progress-fill oc-progress-pending" style="width: 100%; background: linear-gradient(90deg, #0073aa, #005a87);"></div>
                    </div>
                </div>
                <div class="oc-pending-info" style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 3px solid #0073aa; border-radius: 3px;">
                    <small style="display: block; margin-bottom: 5px;"><strong>🚀 Începe:</strong> <?php echo esc_html(wp_date(get_option('date_format'), strtotime((string) $start_date))); ?></small>
                    <small style="display: block;"><strong>🗓 Expiră:</strong> <?php echo esc_html(wp_date(get_option('date_format'), strtotime((string) $expiration_date))); ?></small>
                    <?php if ($is_renewal): ?>
                        <small style="display: block; margin-top: 5px; color: #0073aa;">🔄 Se activează automat după expirarea abonamentului curent</small>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($validation_status === 'expired'): ?>
                <!-- ✕ EXPIRED CARD: Minimal info -->
                <div class="oc-card-progress">
                    <div class="oc-progress-info">
                        <span style="color: #999;"><?php printf(esc_html__('Utilizat: %d/%d ședințe', OC_TEXT_DOMAIN), $sessions_used, $sessions_total); ?></span>
                    </div>
                </div>
                <?php if ($expiration_date): ?>
                <div class="oc-card-expiry" style="color: #999;">
                    <small><?php printf(esc_html__('Expirat: %s', OC_TEXT_DOMAIN), wp_date(get_option('date_format'), strtotime($expiration_date))); ?></small>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- ✓ ACTIVE CARD: Progress normal -->
                <div class="oc-card-progress">
                    <div class="oc-progress-info">
                        <span><?php printf(esc_html__('%d/%d ședințe', OC_TEXT_DOMAIN), $sessions_used, $sessions_total); ?></span>
                        <span class="oc-remaining"><?php printf(esc_html__('%d rămase', OC_TEXT_DOMAIN), $sessions_remaining); ?></span>
                    </div>
                    <div class="oc-progress-bar">
                        <div class="oc-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
                    </div>
                </div>
                
                <?php if ($expiration_date): ?>
                <div class="oc-card-expiry">
                    <small>🗓 <?php printf(esc_html__('Expiră: %s', OC_TEXT_DOMAIN), wp_date(get_option('date_format'), strtotime($expiration_date))); ?></small>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($show_qr && $membership['has_qr_code'] && $validation_status === 'active' && $sessions_remaining > 0): ?>
            <!-- 🎯 Secțiune QR Code cu Preview -->
            <div class="oc-qr-section">
                <?php if (!empty($membership['qr_url'])): ?>
                    <div class="oc-qr-preview-container">
                        <img src="<?php echo esc_url($membership['qr_url']); ?>" 
                             alt="QR Code Preview" 
                             class="oc-qr-preview-img"
                             loading="lazy">
                    </div>
                <?php endif; ?>
                <div class="oc-card-actions">
                    <button class="oc-btn-show-qr" 
                            data-validation-id="<?php echo esc_attr($membership['validation_id']); ?>" 
                            data-product-name="<?php echo esc_attr($membership['product_name']); ?>"
                            data-qr-url="<?php echo esc_attr($membership['qr_url'] ?? ''); ?>"
                            data-user-name="<?php echo esc_attr($membership['user_name'] ?? ''); ?>"
                            data-expires-at="<?php echo esc_attr($membership['expires_at'] ?? ''); ?>"
                            data-sessions-remaining="<?php echo esc_attr($sessions_remaining); ?>">
                        📱 Arată Cod QR Complet
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper: Render empty state
     * EXACT ca în versiunea originală - linia 552
     */
    public function render_empty_state(): string {
        return '<div class="oc-empty-memberships">
                    <div class="oc-empty-icon">🎫</div>
                    <h3>' . esc_html__('No Memberships Found', OC_TEXT_DOMAIN) . '</h3>
                    <p>' . esc_html__('You don\'t have any memberships yet.', OC_TEXT_DOMAIN) . '</p>
                </div>';
    }
    
    /**
     * Renderează modal-ul pentru QR codes - COMPLET și RESPONSIVE
     * 
     * Modal full-screen pentru afișare QR code cu toate detaliile:
     * - QR code mare, centrat
     * - Informații despre membership (nume, expirare, ședințe)
     * - Design mobile-friendly
     * - Close cu ESC, click overlay, sau buton X
     */
    private function render_qr_modal(): string {
        ob_start();
        ?>
        <!-- 🎯 QR Modal Full-Screen -->
        <div id="oc-qr-modal" class="oc-qr-modal">
            <div class="oc-qr-modal-overlay"></div>
            <div class="oc-qr-modal-content">
                <div class="oc-qr-modal-top-actions">
                    <a id="oc-qr-download-btn"
                       href="#"
                       download="qr-abonament.png"
                       class="oc-qr-download-btn"
                       title="<?php esc_attr_e('Descarcă QR Code', OC_TEXT_DOMAIN); ?>">&#8659;</a>
                    <button class="oc-qr-modal-close" aria-label="Close">&times;</button>
                </div>

                <div class="oc-qr-modal-header">
                    <h2 id="oc-qr-modal-title"><?php esc_html_e('Codul Tău QR', OC_TEXT_DOMAIN); ?></h2>
                    <p id="oc-qr-modal-subtitle" class="oc-qr-subtitle"></p>
                </div>
                
                <div class="oc-qr-modal-body">
                    <!-- QR Code Image -->
                    <div class="oc-qr-image-container">
                        <img id="oc-qr-modal-image" 
                             src="" 
                             alt="QR Code" 
                             class="oc-qr-image">
                    </div>

                    <!-- Membership Info -->
                    <div class="oc-qr-info-grid">
                        <div class="oc-qr-info-item">
                            <span class="oc-qr-info-label">👤 Membru:</span>
                            <span id="oc-qr-info-user" class="oc-qr-info-value"></span>
                        </div>
                        <div class="oc-qr-info-item">
                            <span class="oc-qr-info-label">🎫 Abonament:</span>
                            <span id="oc-qr-info-product" class="oc-qr-info-value"></span>
                        </div>
                        <div class="oc-qr-info-item">
                            <span class="oc-qr-info-label">📅 Expiră:</span>
                            <span id="oc-qr-info-expires" class="oc-qr-info-value"></span>
                        </div>
                        <div class="oc-qr-info-item">
                            <span class="oc-qr-info-label">💪 Ședințe rămase:</span>
                            <span id="oc-qr-info-sessions" class="oc-qr-info-value"></span>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="oc-qr-instructions">
                        <p><?php esc_html_e('📱 Arată acest cod QR pentru validarea ședinței.', OC_TEXT_DOMAIN); ?></p>
                        <p class="oc-qr-note"><?php esc_html_e('Codul este unic și securizat. Nu îl partaja cu alte persoane.', OC_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper: Obține membership-urile utilizatorului (sau toate dacă user_id = null)
     * EXACT ca în versiunea originală - linia 303
     */
    private function get_user_memberships(?int $user_id, bool $include_expired = false): array {
        $validator_instance = OC_Membership_Validator::get_instance();
        $validation_handler = $validator_instance->get_validator();
        
        if (!$validation_handler) {
            return [];
        }
        
        // Pentru admin view: obține toate membership-urile
        if ($user_id === null) {
            $memberships = $validation_handler->get_all_active_memberships();
        } else {
            $memberships = $validation_handler->get_user_active_memberships($user_id);
        }
        
        if (!$include_expired) {
            $memberships = array_filter($memberships, function($m) {
                return $m['sessions_remaining'] > 0 && !oc_membership_is_expired((string) ($m['expires_at'] ?? ''));
            });
        }
        
        return $memberships;
    }
    
    /**
     * Helper: Obține statistici pentru toate membership-urile (admin view)
     * EXACT ca în versiunea originală - linia 333
     */
    private function get_all_summary(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'membership_validations';
        
        // Statistici generale pentru admin
        $total_active = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE validation_status = 'active'");
        $total_expired = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE validation_status = 'expired'");
        $total_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE validation_status = 'active'");
        $total_sessions_used = $wpdb->get_var("SELECT SUM(used_sessions) FROM {$table_name} WHERE validation_status = 'active'");
        $next_expiry = $wpdb->get_var("SELECT MIN(expiration_date) FROM {$table_name} WHERE validation_status = 'active' AND expiration_date IS NOT NULL AND expiration_date > NOW()");
        
        return [
            'total_active' => (int)$total_active,
            'total_expired' => (int)$total_expired,
            'total_users' => (int)$total_users,
            'total_sessions_used' => (int)$total_sessions_used,
            'has_active_qr' => $total_active > 0,
            'next_expiry' => $next_expiry,
            'latest_validation_id' => null // Nu e relevant pentru admin view
        ];
    }
    
    /**
     * Helper: Obține summary pentru utilizator
     * EXACT ca în versiunea originală - linia 495
     */
    private function get_user_summary(int $user_id): array {
        $memberships = $this->get_user_memberships($user_id, false);
        $active_count = count($memberships);
        
        $next_expiry = null;
        $latest_validation_id = null;
        $has_active_qr = false;
        
        if (!empty($memberships)) {
            // Găsește următoarea expirare
            $expiry_dates = array_filter(array_column($memberships, 'expires_at'));
            if (!empty($expiry_dates)) {
                sort($expiry_dates);
                $next_expiry = $expiry_dates[0];
            }
            
            // Găsește cel mai recent validation_id cu QR activ
            foreach ($memberships as $membership) {
                if ($membership['has_qr_code'] && $membership['sessions_remaining'] > 0) {
                    $latest_validation_id = $membership['validation_id'];
                    $has_active_qr = true;
                    break;
                }
            }
        }
        
        return [
            'active_memberships' => $active_count,
            'next_expiry' => $next_expiry,
            'latest_validation_id' => $latest_validation_id,
            'has_active_qr' => $has_active_qr
        ];
    }
    
    /**
     * CSS pentru cards optimizat
     */
    private function render_cards_styles(): string {
        return '
        <style>
        .oc-membership-page { margin: 20px 0; }
        .oc-page-header { text-align: center; margin-bottom: 30px; }
        .oc-page-header h2 { margin-bottom: 15px; color: #333; }
        .oc-stats-overview { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; }
        .oc-stat { text-align: center; }
        .oc-stat-number, .oc-stat-date { display: block; font-size: 2em; font-weight: bold; color: #0073aa; }
        .oc-stat-label { font-size: 12px; color: #666; margin-top: 5px; }
        .oc-quick-qr-btn { background: #0073aa; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .oc-quick-qr-btn:hover { background: #005a87; }
        .oc-memberships-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-top: 20px; }
        .oc-membership-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: transform 0.2s ease; }
        .oc-membership-card:hover { transform: translateY(-2px); }
        .oc-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .oc-card-header h3 { margin: 0; font-size: 18px; color: #333; }
        .oc-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .oc-status-active { background: #28a745; color: #ffffff; }
        .oc-status-pending { background: #0073aa; color: #ffffff; }
        .oc-status-expires-soon { background: #ff9800; color: #ffffff; }
        .oc-status-expired { background: #dc3545; color: #ffffff; }
        .oc-status-completed { background: #28a745; color: #ffffff; }
        .oc-card-progress { margin-bottom: 20px; }
        .oc-progress-info { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .oc-remaining { font-weight: 600; color: #0073aa; }
        .oc-progress-bar { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .oc-progress-fill { height: 100%; background: linear-gradient(90deg, #0073aa, #005a87); transition: width 0.3s ease; }
        .oc-card-expiry { margin-bottom: 15px; }
        .oc-card-expiry small { color: #666; font-size: 12px; }
        .oc-card-actions { text-align: center; }
        .oc-qr-btn { background: #0073aa; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; }
        .oc-qr-btn:hover { background: #005a87; }
        .oc-empty-memberships { text-align: center; padding: 60px 20px; }
        .oc-empty-icon { font-size: 4em; display: block; margin-bottom: 20px; }
        .oc-modal { position: fixed !important; z-index: 10000 !important; inset: 0 !important; width: 100% !important; height: 100% !important; background: rgba(15,23,42,0.62) !important; backdrop-filter: blur(3px) !important; display: none; align-items: center !important; justify-content: center !important; padding: 20px !important; box-sizing: border-box !important; }
        .oc-modal[style*="display: block"], .oc-modal[style*="display:block"], .oc-modal[style*="display: flex"], .oc-modal[style*="display:flex"] { display: flex !important; }
        .oc-modal-content { background: #fff !important; border: 1px solid #e5e7eb !important; border-radius: 14px !important; width: min(560px, 100%) !important; max-width: 100% !important; max-height: min(88vh, 760px) !important; overflow: hidden !important; box-shadow: 0 24px 64px rgba(15,23,42,0.28) !important; padding: 0 !important; margin: 0 !important; }
        .oc-modal-header { padding: 22px 24px 16px !important; position: relative; border-bottom: 1px solid #e5e7eb !important; }
        .oc-modal-close { position: absolute; top: 14px; right: 14px; font-size: 24px; cursor: pointer; color: #374151; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 999px; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; }
        .oc-modal-close:hover { color: #000; }
        .oc-modal-body { padding: 18px 24px 22px !important; text-align: center; box-sizing: border-box !important; }
        .oc-qr-instructions { color: #666; font-size: 14px; margin: 0; }
        @media (max-width: 768px) {
            .oc-memberships-grid { grid-template-columns: 1fr; }
            .oc-stats-overview { flex-direction: column; align-items: center; gap: 15px; }
            .oc-card-header { flex-direction: column; align-items: flex-start; }
            .oc-status { margin-top: 10px; }
        }
        </style>';
    }
    
    /**
     * JavaScript pentru cards
     */
    private function render_cards_scripts(): string {
        return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            // QR button handlers
            document.querySelectorAll(".oc-qr-btn, .oc-quick-qr-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var validationId = this.dataset.validationId;
                    // Trigger QR modal or action (integration cu sistemul existent)
                    if (typeof window.ocShowQRCode === "function") {
                        window.ocShowQRCode(validationId);
                    }
                });
            });
        });
        </script>';
    }
}
