<?php
/**
 * Membership Validator - AJAX Handlers
 * 
 * @package MembershipValidator
 * @subpackage Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_AJAX
 * 
 * AJAX handlers pentru toate operațiunile membership
 * Implementare NON-INTRUZIVĂ conform .cursorrules
 */
class OC_Membership_AJAX {

    private const PUBLIC_QR_RATE_LIMIT_PER_MINUTE = 100;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_ajax_hooks();
    }
    
    /**
     * Inițializează hook-urile AJAX
     */
    private function init_ajax_hooks(): void {
        // Validare QR code
        add_action('wp_ajax_oc_validate_qr_token', [$this, 'validate_qr_token']);
        add_action('wp_ajax_nopriv_oc_validate_qr_token', [$this, 'validate_qr_token']);
        
        // Validare manuală (admin)
        add_action('wp_ajax_oc_manual_validation', [$this, 'manual_validation']);
        
        // Generare QR nou
        add_action('wp_ajax_oc_regenerate_qr', [$this, 'regenerate_qr']);
        
        // Download QR code
        add_action('wp_ajax_oc_download_qr', [$this, 'download_qr']);
        add_action('wp_ajax_nopriv_oc_download_qr', [$this, 'download_qr_public']);
        
        // Dashboard utilizator
        add_action('wp_ajax_oc_get_user_memberships', [$this, 'get_user_memberships']);
        add_action('wp_ajax_oc_get_membership_details', [$this, 'get_membership_details']);
        
    }
    
    /**
     * Validează QR token (pentru app React și scanări externe)
     * 
     * Endpoint: wp-admin/admin-ajax.php?action=oc_validate_qr_token
     * Suportă atât POST cu nonce (pentru frontend WP) cât și GET fără nonce (pentru app extern)
     */
    public function validate_qr_token(): void {
        // Pentru app extern (nopriv) - verifică API key
        // Pentru utilizatori autentificați - verifică nonce
        $is_authenticated = is_user_logged_in();

        if ($is_authenticated) {
            $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'oc_membership_validation')) {
                wp_send_json_error([
                    'message' => 'Security check failed',
                    'code' => 'INVALID_NONCE'
                ]);
            }
        } else {
            // Callers neautentificați trebuie să trimită API key-ul global.
            $api_key  = sanitize_text_field($_SERVER['HTTP_X_API_KEY'] ?? '');
            $expected = defined('OC_MEMBERSHIP_API_KEY')
                ? OC_MEMBERSHIP_API_KEY
                : get_option('oc_membership_api_key', '');

            if (empty($api_key) || empty($expected) || !hash_equals($expected, $api_key)) {
                wp_send_json_error([
                    'message' => 'Unauthorized',
                    'code'    => 'UNAUTHORIZED',
                ], 401);
                return;
            }

            if (!$this->check_public_qr_rate_limit($api_key)) {
                wp_send_json_error([
                    'message' => 'Too many attempts. Please retry later.',
                    'code' => 'RATE_LIMIT',
                    'friendly_message' => 'Too many attempts. Please retry later.',
                ], 429);
                return;
            }
        }

        // Suportă atât POST cât și GET pentru flexibilitate
        $qr_token = sanitize_text_field($_POST['qr_token'] ?? $_GET['token'] ?? $_GET['qr_token'] ?? '');
        
        if (empty($qr_token)) {
            wp_send_json_error([
                'message' => 'QR token is required',
                'code' => 'MISSING_TOKEN'
            ]);
        }
        
        // Obține instance QR system din validator
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            wp_send_json_error([
                'message' => 'Validator system not available',
                'code' => 'SYSTEM_ERROR'
            ]);
        }
        
        $qr_system = $validator->get_qr_system();
        $membership_validator = $validator->get_validator();
        if (!$qr_system || !$membership_validator) {
            wp_send_json_error([
                'message' => 'QR validation system not available',
                'code' => 'SYSTEM_ERROR'
            ]);
        }
        
        // Validează token prin QR system
        $result = $qr_system->validate_qr_token($qr_token, [
            'source' => 'ajax',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        if ($result && isset($result['user_id'])) {
            $check_in_result = $membership_validator->check_in_user((int) $result['user_id'], [
                'source' => 'ajax_qr_validation',
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '')
            ]);

            if (empty($check_in_result['success'])) {
                wp_send_json_error([
                    'message' => (string) ($check_in_result['message'] ?? 'Validation failed'),
                    'code' => (string) ($check_in_result['code'] ?? 'VALIDATION_FAILED')
                ], 409);
            }

            wp_send_json_success([
                'success' => true,
                'user_id' => $result['user_id'],
                'user_name' => $result['user_name'],
                'photo_url' => $result['photo_url'],
                'validation_id' => (int) ($check_in_result['membership_id'] ?? $result['validation_id']),
                'product_name' => $result['product_name'],
                'sessions_remaining' => (int) ($check_in_result['sessions_remaining'] ?? $result['sessions_available']),
                'sessions_total' => (int) ($check_in_result['sessions_total'] ?? $result['sessions_total']),
                'sessions_used' => (int) ($check_in_result['sessions_used'] ?? $result['sessions_used']),
                'expires_at' => $check_in_result['expires_at'] ?? $result['expires_at'],
                'status' => (string) ($check_in_result['status'] ?? 'active'),
                'validated_at' => current_time('mysql')
            ]);
        } else {
            // Failed - QR invalid, expirat sau fără ședințe
            wp_send_json_error([
                'message' => 'QR code invalid, expired or no sessions remaining',
                'code' => 'VALIDATION_FAILED'
            ]);
        }
    }
    
    /**
     * Validare manuală (pentru admin/staff)
     */
    public function manual_validation(): void {
        // Verifică permisiuni
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => 'Insufficient permissions',
                'code' => 'NO_PERMISSION'
            ]);
        }
        
        // Verificare nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_manual_validation')) {
            wp_send_json_error([
                'message' => 'Security check failed',
                'code' => 'INVALID_NONCE'
            ]);
        }
        
        $access_code = sanitize_text_field($_POST['access_code'] ?? '');
        $membership_id = intval($_POST['membership_id'] ?? 0);
        
        if (empty($access_code) && !$membership_id) {
            wp_send_json_error([
                'message' => 'Access code or membership ID required',
                'code' => 'MISSING_IDENTIFIER'
            ]);
        }
        
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            wp_send_json_error([
                'message' => 'Validator system not available',
                'code' => 'SYSTEM_ERROR'
            ]);
        }
        
        $membership = null;

        // Validează prin access code sau membership ID
        if ($access_code) {
            $result = $validator->get_validator()->validate_access_code($access_code);
        } else {
            // Găsește membership și validează
            global $wpdb;
            $table_name = $wpdb->prefix . 'membership_validations';
            $membership = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$table_name} WHERE id = %d
            ", $membership_id), ARRAY_A);
            
            if (!$membership) {
                wp_send_json_error([
                    'message' => 'Membership not found',
                    'code' => 'NOT_FOUND'
                ]);
            }
            
            $result = $validator->get_validator()->validate_membership($membership);
        }
        
        if ($result['success']) {
            $membership_id_for_log = 0;
            if (is_array($membership) && isset($membership['id'])) {
                $membership_id_for_log = (int) $membership['id'];
            } elseif (isset($result['membership_id'])) {
                $membership_id_for_log = (int) $result['membership_id'];
            } elseif (isset($result['validation_id'])) {
                $membership_id_for_log = (int) $result['validation_id'];
            }

            // Log validarea manuală
            if ($membership_id_for_log > 0) {
                oc_log_membership_validation($membership_id_for_log, 'manual', [
                    'validated_by' => get_current_user_id(),
                    'access_method' => $access_code ? 'access_code' : 'membership_id'
                ]);
            }
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Regenerează QR code
     */
    public function regenerate_qr(): void {
        // Verifică permisiuni
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Authentication required']);
        }
        
        // Verificare nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'oc_regenerate_qr')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $membership_id = intval($_POST['membership_id'] ?? 0);
        if (!$membership_id) {
            wp_send_json_error(['message' => 'Invalid membership ID']);
        }
        
        // Verifică că utilizatorul poate gestiona acest membership
        if (!oc_can_user_manage_membership($membership_id)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            wp_send_json_error(['message' => 'System error']);
        }
        
        // Generează token nou
        $new_token = bin2hex(random_bytes(32));
        $new_hash = hash('sha256', $new_token);
        
        // Actualizează în DB
        $updated = $validator->get_db()->update_validation($membership_id, [
            'qr_token' => $new_token,
            'qr_token_hash' => $new_hash,
            'qr_token_revoked_at' => null
        ]);
        
        if ($updated) {
            // Generează QR nou
            $validator->get_qr_system()->generate_qr_code($membership_id, $new_token);
            
            // Șterge token-ul din DB după generare
            $validator->get_db()->update_validation($membership_id, ['qr_token' => null]);
            
            $qr_url = oc_get_membership_qr_url($membership_id);
            
            wp_send_json_success([
                'message' => 'QR code regenerated successfully',
                'qr_url' => $qr_url,
                'download_link' => oc_get_qr_download_link($membership_id)
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to regenerate QR code']);
        }
    }
    
    /**
     * Download QR code (pentru utilizatori autentificați)
     */
    public function download_qr(): void {
        // Verificare nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'oc_download_qr')) {
            wp_die('Security check failed');
        }
        
        $membership_id = intval($_GET['membership_id'] ?? 0);
        if (!$membership_id) {
            wp_die('Invalid membership ID');
        }
        
        // Verifică permisiuni
        if (!oc_can_user_manage_membership($membership_id)) {
            wp_die('Access denied');
        }
        
        $this->serve_qr_download($membership_id);
    }
    
    /**
     * Download QR public (cu token special)
     */
    public function download_qr_public(): void {
        $token = sanitize_text_field($_GET['token'] ?? '');
        $membership_id = intval($_GET['membership_id'] ?? 0);
        
        if (!$token || !$membership_id) {
            wp_die('Invalid parameters');
        }
        
        // Verifică token-ul special pentru download public
        $expected_token = hash('sha256', $membership_id . wp_salt('nonce'));
        if (!hash_equals($expected_token, $token)) {
            wp_die('Invalid download token');
        }
        
        $this->serve_qr_download($membership_id);
    }
    
    /**
     * Servește download-ul QR
     */
    private function serve_qr_download(int $membership_id): void {
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator) {
            wp_die('System error');
        }
        
        $qr_url = oc_get_membership_qr_url($membership_id);
        if (!$qr_url) {
            wp_die('QR code not found');
        }
        
        // Convertește URL în path
        $upload_dir = wp_upload_dir();
        $qr_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $qr_url);
        
        if (!file_exists($qr_path)) {
            wp_die('QR code file not found');
        }
        
        $extension = strtolower((string) pathinfo($qr_path, PATHINFO_EXTENSION));
        $mime_types = [
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $download_extension = $extension !== '' ? $extension : 'png';

        // Setează headers
        header('Content-Type: ' . ($mime_types[$download_extension] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="membership-qr-' . $membership_id . '.' . $download_extension . '"');
        header('Content-Length: ' . filesize($qr_path));
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($qr_path);
        exit;
    }
    
    /**
     * Obține membership-urile utilizatorului
     */
    public function get_user_memberships(): void {
        check_ajax_referer('oc_membership_validator_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Authentication required']);
        }
        
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_GET['status'] ?? 'active');
        
        $memberships = oc_get_user_active_memberships($user_id);
        
        // Formatează datele pentru frontend
        $formatted_memberships = array_map(function($membership) {
            return [
                'id' => $membership['id'],
                'product_name' => oc_get_membership_product_name($membership),
                'access_code' => $membership['access_code'],
                'total_sessions' => $membership['total_sessions'],
                'used_sessions' => $membership['used_sessions'],
                'remaining_sessions' => $membership['remaining_sessions'],
                'expiration_date' => oc_format_membership_date($membership['expiration_date']),
                'status' => oc_get_membership_status_label($membership['validation_status']),
                'qr_url' => oc_get_membership_qr_url($membership['id']),
                'download_link' => oc_get_qr_download_link($membership['id']),
                'can_validate' => oc_can_validate_membership_now($membership)
            ];
        }, $memberships);
        
        wp_send_json_success([
            'memberships' => $formatted_memberships,
            'total' => count($formatted_memberships)
        ]);
    }
    
    /**
     * Obține detalii membership specific
     */
    public function get_membership_details(): void {
        check_ajax_referer('oc_membership_validator_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Authentication required']);
        }
        
        $membership_id = intval($_GET['membership_id'] ?? 0);
        if (!$membership_id) {
            wp_send_json_error(['message' => 'Invalid membership ID']);
        }
        
        // Verifică permisiuni
        if (!oc_can_user_manage_membership($membership_id)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_validations';
        
        $membership = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table_name} WHERE id = %d
        ", $membership_id), ARRAY_A);
        
        if (!$membership) {
            wp_send_json_error(['message' => 'Membership not found']);
        }
        
        // Obține cursurile disponibile
        $available_courses = oc_get_membership_available_courses($membership);
        
        // Obține istoric validări
        $log_table = $wpdb->prefix . 'membership_validation_log';
        $validation_history = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$log_table}
            WHERE membership_id = %d
            ORDER BY validation_date DESC
            LIMIT 20
        ", $membership_id), ARRAY_A);
        
        wp_send_json_success([
            'membership' => $membership,
            'available_courses' => $available_courses,
            'validation_history' => $validation_history,
            'product_name' => oc_get_membership_product_name($membership),
            'stats' => oc_get_user_membership_stats($membership['user_id'])
        ]);
    }
    

    private function check_public_qr_rate_limit(string $api_key): bool {
        $client_ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $rate_key = 'oc_ajax_qr_rl_' . md5($client_ip . '|' . $api_key);
        $current_count = (int) get_transient($rate_key);

        if ($current_count >= self::PUBLIC_QR_RATE_LIMIT_PER_MINUTE) {
            return false;
        }

        set_transient($rate_key, $current_count + 1, MINUTE_IN_SECONDS);

        return true;
    }
}

// Inițializare AJAX handlers
new OC_Membership_AJAX();
