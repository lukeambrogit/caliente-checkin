<?php
/**
 * QR Code System pentru Membership Validator
 * 
 * CONFORMITATE .cursorrules:
 * - DOAR citire din sisteme existente
 * - QR codes generate și salvate în tabel propriu
 * - Tokens securizați cu wp_generate_password() și SHA-256
 * - NON-INTRUZIV: zero modificări în fișiere existente
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_QR
 * 
 * Gestionează generarea, salvarea și validarea QR codes pentru membership-uri
 * Folosește DOAR funcții WordPress și API-uri publice existente
 */
class OC_Membership_QR {
    
    /**
     * @var OC_Membership_DB Database handler
     */
    private OC_Membership_DB $db;
    
    /**
     * @var string QR codes directory path
     */
    private string $qr_directory;
    
    /**
     * @var string QR codes URL base
     */
    private string $qr_url_base;
    
    /**
     * @var int QR token length (securitate optimă)
     */
    private const QR_TOKEN_LENGTH = 32;
    
    /**
     * @var int QR secret length pentru hash
     */
    private const QR_SECRET_LENGTH = 64;
    
    /**
     * @var int QR code size in pixels
     */
    private const QR_SIZE = OC_QR_DEFAULT_SIZE;
    
    /**
     * Constructor
     * 
     * @param OC_Membership_DB $db Database handler
     */
    public function __construct(OC_Membership_DB $db) {
        $this->db = $db;
        $this->setup_directories();
    }
    
    /**
     * Setup QR codes directory și URL base
     * Conform .cursorrules: folosește doar funcții WordPress existente
     */
    private function setup_directories(): void {
        // Folosește WordPress upload directory (NON-INTRUZIV)
        $upload_dir = wp_upload_dir();
        
        $this->qr_directory = $upload_dir['basedir'] . '/membership-qr-codes/';
        $this->qr_url_base = $upload_dir['baseurl'] . '/membership-qr-codes/';
        
        // Creează directorul dacă nu există
        if (!is_dir($this->qr_directory)) {
            wp_mkdir_p($this->qr_directory);
            
            // Adaugă .htaccess pentru securitate
            $this->create_htaccess_protection();
        }
    }
    
    /**
     * Creează protecție .htaccess pentru directorul QR codes
     * Previne accesul direct la fișiere fără validare
     */
    private function create_htaccess_protection(): void {
        $htaccess_file = $this->qr_directory . '.htaccess';
        
        // 🔄 REGENEREAZĂ întotdeauna pentru a asigura suport WebP/AVIF
        $htaccess_content = "# Membership Validator QR Codes Protection\n";
        $htaccess_content .= "# Generated automatically - DO NOT EDIT\n\n";
        $htaccess_content .= "Order Deny,Allow\n";
        $htaccess_content .= "Deny from all\n";
        $htaccess_content .= "Allow from 127.0.0.1\n";
        $htaccess_content .= "Allow from ::1\n\n";
        $htaccess_content .= "# Allow QR code images (PNG, WebP, AVIF)\n";
        $htaccess_content .= "<Files ~ \"\\.(png|jpg|jpeg|gif|svg|webp|avif)$\">\n";
        $htaccess_content .= "    Order Allow,Deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($htaccess_file, $htaccess_content);
    }
    
    /**
     * 🎯 v2.0 - Generează QR code pentru membership validation
     * 
     * SISTEM NOU: Generează QR SIMPLU bazat pe user_id (NU mai folosește token-uri complexe)
     * Funcția păstrată pentru compatibilitate backward, dar folosește intern generate_simple_user_qr()
     * 
     * @param int $validation_id ID-ul validation-ului din DB
     * @param array $membership_data Date membership (trebuie să conțină user_id)
     * @return array|false QR data sau false la eroare
     */
    public function generate_qr_code(int $validation_id, array $membership_data): array|false {
        // Validează input-ul
        if (empty($membership_data['user_id'])) {
            return false;
        }
        
        $user_id = (int)$membership_data['user_id'];
        
        // 🎯 Generează QR SIMPLU pentru user (NU pentru validation specific)
        $qr_result = $this->generate_simple_user_qr($user_id);
        
        if (!$qr_result) {
            return false;
        }
        
        // Returnează în formatul așteptat de cod-ul existent
        return [
            'token' => null, // NU mai folosim token
            'filename' => $qr_result['filename'],
            'url' => $qr_result['url'],
            'validation_url' => null, // NU mai e nevoie
            'expires_at' => false // NU expiră
        ];
    }
    
    /**
     * Obține URL-ul pentru QR code image
     * 
     * @param string $filename Filename QR code
     * @return string URL complet
     */
    public function get_qr_url(string $filename): string {
        return $this->qr_url_base . $filename;
    }
    
    /**
     * 🎯 v2.0 - Validează QR cu user_id și returnează informații membership
     * 
     * SISTEM NOU: QR conține JSON cu user_id, NU token complex
     * Funcția adaptată pentru a funcționa cu noul sistem
     * 
     * @param string $qr_content Conținut QR (JSON cu user_id) sau user_id direct
     * @param array $context Context validare (opțional)
     * @return array|false Date validation sau false
     */
    public function validate_qr_token(string $qr_content, array $context = []): array|false {
        // Sanitizează input
        $qr_content = sanitize_text_field($qr_content);
        
        if (empty($qr_content)) {
            return false;
        }
        
        // 🎯 Parsare QR JSON nou format: {"user_id": 40, "type": "member_id"}
        $qr_data = json_decode($qr_content, true);
        
        if ($qr_data && isset($qr_data['user_id'])) {
            $user_id = (int)$qr_data['user_id'];
        } else {
            // Fallback: dacă e doar număr (user_id direct)
            $user_id = (int)$qr_content;
        }
        
        if ($user_id <= 0) {
            return false;
        }
        
        // Verifică că user-ul există
        $user_data = get_userdata($user_id);
        if (!$user_data) {
            return false;
        }

        $this->db->sync_membership_statuses($user_id);
        
        // 🎯 Caută membership ACTIV pentru user
        global $wpdb;
        $table = $wpdb->prefix . 'membership_validations';
        $today = oc_membership_current_business_date();
        
        $validation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE user_id = %d 
             AND validation_status = 'active'
             AND (expiration_date IS NULL OR expiration_date >= %s)
             AND (remaining_sessions > 0 OR is_unlimited = 1)
             ORDER BY expiration_date DESC
             LIMIT 1",
            $user_id,
            $today
        ));
        
        if (!$validation) {
            return false; // NU există membership activ
        }
        
        // Obține date produs
        $product_data = wc_get_product($validation->product_id);
        
        // Verifică ședințe disponibile
        $sessions_available = (int)$validation->remaining_sessions;
        
        if (!$validation->is_unlimited && $sessions_available <= 0) {
            return false;
        }
        
        // 🎯 Returnează date complete pentru validare
        return [
            'validation_id' => (int)$validation->id,
            'user_id' => $user_id,
            'user_name' => oc_membership_resolve_user_display_name($user_data, $validation),
            'user_email' => $user_data->user_email,
            'photo_url' => get_avatar_url($user_id, ['size' => OC_AVATAR_DEFAULT_SIZE]),
            'product_id' => (int)$validation->product_id,
            'product_name' => $product_data ? $product_data->get_name() : 'Abonament #' . $validation->product_id,
            'order_id' => (int)$validation->order_id,
            'sessions_total' => (int)$validation->total_sessions,
            'sessions_used' => (int)$validation->used_sessions,
            'sessions_available' => $sessions_available,
            'is_unlimited' => (bool)$validation->is_unlimited,
            'expires_at' => $validation->expiration_date,
            'created_at' => $validation->created_at,
            'last_validated' => current_time('mysql'),
            'validation_context' => $context
        ];
    }
    
    /**
     * Procesează o validare (consumă o ședință)
     * 
     * @param int $validation_id ID validation
     * @param array $session_data Date despre ședință
     * @return bool Success
     */
    /**
     * Procesează validare și consumă ședință
     * 
     * v1.2.0: UPDATE DIRECT în membership_validations cu tracking simplificat
     * - VIP unlimited: validare fără consum
     * - Regular: verifică + consumă 1 ședință DIRECT
     * 
     * @param int $validation_id ID validation membership
     * @param array $session_data Date ședință pentru log
     * @return bool Success
     */
    public function process_validation(int $validation_id, array $session_data = []): bool {
        global $wpdb;
        
        $table = $this->db->get_table_name('membership_validations');
        $today = oc_membership_current_business_date();
        
        // Citește DIRECT din membership_validations (fără JOIN!)
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id = %d
               AND validation_status = 'active'
               AND (expiration_date IS NULL OR expiration_date >= %s)",
            $validation_id,
            $today
        ), ARRAY_A);
        
        if (!$membership) {
            return false;
        }
        
        // 🎯 v1.2.0: VIP unlimited - validare fără consum
        if ($membership['is_unlimited']) {
            // Log validation pentru VIP (doar audit, nu consum)
            $this->log_validation($validation_id, array_merge($session_data, ['unlimited' => true]));
            
            return true;
        }
        
        // Verifică ședințe disponibile (folosim sessions_remaining sau remaining_sessions)
        $sessions_remaining = isset($membership['sessions_remaining']) ? (int)$membership['sessions_remaining'] : 
                             ((int)$membership['remaining_sessions']);
        
        if ($sessions_remaining <= 0) {
            return false;
        }
        
        // Consum atomic: condiția remaining_sessions > 0 previne race conditions.
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET used_sessions = used_sessions + 1,
                 remaining_sessions = remaining_sessions - 1,
                 last_validation_date = NOW(),
                 validation_count = validation_count + 1,
                 validation_status = CASE
                     WHEN remaining_sessions - 1 <= 0 THEN 'expired'
                     ELSE validation_status
                 END,
                 updated_at = NOW()
             WHERE id = %d
               AND validation_status = 'active'
                    AND (expiration_date IS NULL OR expiration_date >= %s)
               AND (is_unlimited = 1 OR remaining_sessions > 0)",
                $validation_id,
                $today
        ));
        
        if ($result === false || (int) $result < 1) {
            return false;
        }
        
        // Log validation în sistem (pentru audit trail)
        $this->log_validation($validation_id, $session_data);
        
        return true;
    }
    
    /**
     * Log validation pentru audit trail
     * 
     * @param int $validation_id ID validation
     * @param array $session_data Date ședință
     */
    private function log_validation(int $validation_id, array $session_data): void {
        global $wpdb;

        $membership_table = $this->db->get_table_name('membership_validations');
        $log_table = $this->db->get_table_name('membership_validation_log');
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$membership_table} WHERE id = %d LIMIT 1",
            $validation_id
        ));

        if (!$membership) {
            return;
        }

        $client_ip = function_exists('oc_get_client_ip')
            ? oc_get_client_ip()
            : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $wpdb->insert($log_table, [
            'membership_id' => $validation_id,
            'user_id' => (int) $membership->user_id,
            'validator_user_id' => get_current_user_id(),
            'validation_method' => 'qr_code',
            'validation_status' => 'success',
            'validation_date' => current_time('mysql'),
            'ip_address' => $client_ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'validation_metadata' => wp_json_encode($session_data)
        ]);
    }
    
    /**
     * Șterge QR code file de pe disk
     * 
     * @param string $filename Filename de șters
     * @return bool Success
     */
    public function delete_qr_file(string $filename): bool {
        $file_path = $this->qr_directory . $filename;
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true;
    }
    
    /**
     * Cleanup QR codes expirate
     * Rulează prin WordPress cron
     */
    public function cleanup_expired_qr_codes(): void {
        // Obține QR codes expirate din DB
        $expired_validations = $this->db->get_expired_validations();
        
        foreach ($expired_validations as $validation) {
            // Șterge fișierul QR dacă există
            if (!empty($validation->qr_filename)) {
                $this->delete_qr_file($validation->qr_filename);
            }
            
            // Update status în DB
            $this->db->update_membership_status($validation->id, 'expired');
        }
    }
    
    /**
     * Obține statistici QR usage pentru dashboard
     * 
     * @return array Statistici
     */
    public function get_qr_statistics(): array {
        return [
            'total_generated' => $this->db->count_qr_codes_generated(),
            'active_codes' => $this->db->count_active_qr_codes(),
            'expired_codes' => $this->db->count_expired_qr_codes(),
            'validation_count_today' => $this->db->count_validations_today(),
            'validation_count_week' => $this->db->count_validations_week(),
            'validation_count_month' => $this->db->count_validations_month()
        ];
    }
    
    /**
     * Get QR directory path pentru debug
     * 
     * @return string Directory path
     */
    public function get_qr_directory(): string {
        return $this->qr_directory;
    }
    
    /**
     * Verifică dacă QR directory este writable
     * 
     * @return bool Writable status
     */
    public function is_qr_directory_writable(): bool {
        return is_writable($this->qr_directory);
    }
    
    /**
     * 🎯 v2.0 - Generează QR code SIMPLU pentru user (FĂRĂ expirare)
     * 
     * Nou sistem pentru app React Native:
     * - QR conține DOAR user_id (JSON format)
     * - Valid PERMANENT atâta timp cât user-ul există în sistem
     * - NU depinde de membership-uri specifice
     * 
     * @param int $user_id ID utilizator
     * @return array|false QR data sau false
     */
    public function generate_simple_user_qr(int $user_id): array|false {
        // Verifică că user-ul există
        $user_data = get_userdata($user_id);
        if (!$user_data) {
            return false;
        }
        
        // 🎯 Date SIMPLE pentru QR (JSON format minimal)
        $qr_data = [
            'user_id' => $user_id,
            'type' => 'member_id'
        ];
        
        // Convert la JSON pentru QR
        $qr_content = json_encode($qr_data);
        
        // Generează QR code image
        $qr_filename = $this->create_simple_qr_image($user_id, $qr_content);
        
        if (!$qr_filename) {
            return false;
        }
        
        // Salvează în usermeta (NU în membership_validations!)
        update_user_meta($user_id, 'simple_qr_filename', $qr_filename);
        update_user_meta($user_id, 'simple_qr_generated_at', current_time('mysql'));
        
        return [
            'filename' => $qr_filename,
            'url' => $this->get_qr_url($qr_filename),
            'user_id' => $user_id,
            'user_name' => oc_membership_resolve_user_display_name($user_data),
            'expires' => false // NU expiră niciodată
        ];
    }
    
    /**
     * Creează QR image simplu pentru user (WebP format pentru economie spațiu)
     * 
     * @param int $user_id User ID
     * @param string $qr_content JSON content pentru QR
     * @return string|false Filename sau false
     */
    private function create_simple_qr_image(int $user_id, string $qr_content): string|false {
        require_once plugin_dir_path(__FILE__) . 'lib-qrcode.php';
        
        // 🎯 Filename WebP (economie spațiu ~30-50% față de PNG)
        $filename_webp = sprintf('user_qr_%d.webp', $user_id);
        $file_path_webp = $this->qr_directory . $filename_webp;
        
        // Șterge QR vechi dacă există (regenerare - atât PNG cât și WebP)
        $filename_png = sprintf('user_qr_%d.png', $user_id);
        $file_path_png = $this->qr_directory . $filename_png;
        
        if (file_exists($file_path_webp)) {
            unlink($file_path_webp);
        }
        if (file_exists($file_path_png)) {
            unlink($file_path_png); // Cleanup PNG-uri vechi
        }
        
        // Generează QR (biblioteca va converti automat PNG → WebP)
        $success = OC_Simple_QRCode::png($qr_content, $file_path_png, self::QR_SIZE);
        
        // Verifică dacă WebP a fost generat (conversie automată în lib-qrcode.php)
        if ($success && file_exists($file_path_webp)) {
            return $filename_webp;
        }
        
        // Fallback: Dacă WebP nu e disponibil, verifică PNG
        if ($success && file_exists($file_path_png)) {
            return $filename_png;
        }
        
        return false;
    }
    
    /**
     * Obține QR simplu pentru user (generează dacă nu există)
     * Verifică atât WebP cât și PNG pentru backwards compatibility
     * 
     * @param int $user_id User ID
     * @return array|false QR data sau false
     */
    public function get_or_generate_simple_qr(int $user_id): array|false {
        // Verifică dacă există deja QR salvat
        $existing_filename = get_user_meta($user_id, 'simple_qr_filename', true);
        
        if ($existing_filename) {
            $file_path = $this->qr_directory . $existing_filename;
            
            // Verifică dacă fișierul există fizic
            if (file_exists($file_path)) {
                $user_data = get_userdata($user_id);
                return [
                    'filename' => $existing_filename,
                    'url' => $this->get_qr_url($existing_filename),
                    'user_id' => $user_id,
                    'user_name' => oc_membership_resolve_user_display_name($user_data),
                    'expires' => false
                ];
            }
        }
        
        // Generează QR nou dacă nu există
        return $this->generate_simple_user_qr($user_id);
    }
}