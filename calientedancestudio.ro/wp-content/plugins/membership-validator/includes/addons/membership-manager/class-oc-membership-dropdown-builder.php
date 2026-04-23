<?php
/**
 * Dropdown Builder - REFACTORED din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR construirea dropdown-urilor pentru pachete și cursuri
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - PĂSTREAZĂ EXACT funcționalitățile existente pentru dropdown-uri
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OC_Membership_Dropdown_Builder
 * 
 * Gestionează construirea tuturor dropdown-urilor:
 * - Dropdown pachete (OC + MV)
 * - Dropdown cursuri din Pool Products
 * - Dropdown reduceri membru (coupons)
 * - Dropdown payment methods
 */
class OC_Membership_Dropdown_Builder {
    
    use OC_Membership_Courses;
    use OC_Membership_WooCommerce;
    
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
     * Obține opțiunile pentru dropdown-ul de pachete (toate pachetele disponibile)
     * EXACT ca în versiunea originală - linia 3088
     * 
     * @param string $selected_package Numele pachetului selectat (pentru a fi marcat ca selected)
     * @return string HTML options pentru dropdown
     */
    public function get_packages_dropdown_options(string $selected_package = ''): string {
        $options = '<option value="">Selectează abonament</option>';

        global $wpdb;
        $packages = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm_enabled.meta_key IN ('_oc_pool_enabled', '_mv_pack_enabled')
            AND pm_enabled.meta_value IN ('yes', '1', 'on')
            ORDER BY p.post_title
        ");

        foreach ($packages as $package) {
            $is_selected = ($selected_package && $selected_package === $package->post_title) ? 'selected' : '';
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($package->post_title),
                $is_selected,
                esc_html($package->post_title)
            );
        }

        return $options;
    }
    
    /**
     * Construiește dropdown pentru reduceri membri (coupons WooCommerce)
     * 
     * @param string $selected_coupon Coupon-ul selectat
     * @return string HTML options
     */
    public function get_member_discounts_dropdown_options(string $selected_coupon = ''): string {
        $options = '<option value="">Fără reducere</option>';
        
        // Get WooCommerce coupons for discount dropdown
        $coupons = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        foreach ($coupons as $coupon) {
            $is_selected = ($selected_coupon === $coupon->post_title) ? 'selected' : '';
            
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($coupon->post_title),
                $is_selected,
                esc_html($coupon->post_title)
            );
        }
        
        return $options;
    }
    
    /**
     * Construiește dropdown pentru metodele de plată
     * 
     * @param string $selected_method Metoda selectată
     * @return string HTML options
     */
    public function get_payment_methods_dropdown_options(string $selected_method = ''): string {
        $methods = $this->get_available_payment_methods();
        $options = '';
        
        foreach ($methods as $method_id => $method_title) {
            $is_selected = ($selected_method === $method_id) ? 'selected' : '';
            
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($method_id),
                $is_selected,
                esc_html($method_title)
            );
        }
        
        return $options;
    }
    
    /**
     * Construiește dropdown pentru statusurile de plată
     * 
     * @param string $selected_status Status-ul selectat
     * @return string HTML options
     */
    public function get_payment_status_dropdown_options(string $selected_status = ''): string {
        $statuses = [
            'paid' => 'Achitat',
            'unpaid' => 'Neachitat', 
            'partial' => 'Parțial',
        ];
        
        $options = '';
        
        foreach ($statuses as $status_id => $status_title) {
            $is_selected = ($selected_status === $status_id) ? 'selected' : '';
            
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($status_id),
                $is_selected,
                esc_html($status_title)
            );
        }
        
        return $options;
    }
    
    /**
     * Construiește dropdown pentru statusurile membership-ului
     * 
     * @param string $selected_status Status-ul selectat
     * @return string HTML options
     */
    public function get_membership_status_dropdown_options(string $selected_status = ''): string {
        $statuses = [
            'active' => 'Activ',
            'expired' => 'Expirat',
            'completed' => 'Completat', 
            'inactive' => 'Inactiv',
            'suspended' => 'Suspendat',
            'cancelled' => 'Anulat'
        ];
        
        $options = '';
        
        foreach ($statuses as $status_id => $status_title) {
            $is_selected = ($selected_status === $status_id) ? 'selected' : '';
            
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($status_id),
                $is_selected,
                esc_html($status_title)
            );
        }
        
        return $options;
    }
    
    /**
     * Construiește dropdown complex pentru cursuri cu validare
     * Metoda centralizată care combină logica din trait-ul Courses
     * 
     * @param array $member Datele membrului
     * @param bool $include_all Dacă să includă toate cursurile sau doar cele selectate
     * @return string HTML select complet
     */
    public function get_courses_dropdown_html(array $member, bool $include_all = true): string {
        if (!$include_all) {
            // Doar cursurile selectate (read-only view)
            $selected_courses = $member['courses_included'] ?? 'N/A';
            return sprintf(
                '<div class="oc-courses-display oc-field-readonly">%s</div>',
                esc_html($selected_courses)
            );
        }
        
        // Dropdown complet editable
        $user_id = $member['user_id'] ?? '';
        $courses_options = $this->get_courses_dropdown_options($member);
        
        ob_start();
        ?>
        <select id="courses-<?php echo esc_attr($user_id); ?>" disabled class="oc-field-readonly">
            <?php echo $courses_options; ?>
        </select>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Construiește dropdown pentru produse noi (pentru adăugare comenzi noi)
     * 
     * @param string $selected_product Produsul selectat
     * @return string HTML options
     */
    public function get_new_order_products_dropdown_options(string $selected_product = ''): string {
        $options = '<option value="">Selectează produs pentru comandă nouă</option>';
        
        // Obține toate produsele din magazin care pot fi comandate
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish', 
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_oc_pool_enabled',
                    'value' => ['1', 'yes'],
                    'compare' => 'IN'
                ],
                [
                    'key' => '_mv_pack_enabled',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product && ($wc_product->is_purchasable() || $wc_product->is_type('variable'))) {
                $is_selected = ($selected_product === $product->post_title) ? 'selected' : '';
                
                // 🔧 FIX: Pentru produse variable, extrage prețul din prima variantă disponibilă
                if ($wc_product->is_type('variable')) {
                    $variations = $wc_product->get_available_variations();
                    if (!empty($variations)) {
                        // Folosește prețul primei variante
                        $first_variation = reset($variations);
                        $price = isset($first_variation['display_price']) ? $first_variation['display_price'] : 0;
                    } else {
                        $price = 0;
                    }
                } else {
                    $price = $wc_product->get_price();
                }
                
                $price_display = $price > 0 ? ' - ' . number_format($price, 2, '.', '') . ' lei' : ' - Gratis';
                
                $options .= sprintf(
                    '<option value="%d" data-price="%s" %s>%s%s</option>',
                    $product->ID,
                    esc_attr($price),
                    $is_selected,
                    esc_html($product->post_title),
                    esc_html($price_display)
                );
            }
        }
        
        return $options;
    }
    
    /**
     * Generează dropdown-uri pentru bulk operations
     * 
     * @return array Array cu opțiunile pentru bulk operations
     */
    public function get_bulk_operations_options(): array {
        return [
            '' => 'Acțiuni în masă...',
            'activate' => 'Activează selecțiile',
            'deactivate' => 'Dezactivează selecțiile', 
            'suspend' => 'Suspendă selecțiile',
            'extend_expiry' => 'Prelungește cu 30 zile',
            'extend_sessions' => 'Adaugă 5 ședințe',
            'export_selected' => 'Export CSV selecțiile',
            'send_email' => 'Trimite email selecțiilor',
            'delete_selected' => 'Șterge selecțiile'
        ];
    }
    
    /**
     * Construiește HTML pentru dropdown bulk operations
     * 
     * @param string $selected_action Acțiunea selectată
     * @return string HTML select complet
     */
    public function get_bulk_operations_dropdown_html(string $selected_action = ''): string {
        $options_data = $this->get_bulk_operations_options();
        $options = '';
        
        foreach ($options_data as $action_id => $action_title) {
            $is_selected = ($selected_action === $action_id) ? 'selected' : '';
            
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($action_id),
                $is_selected,
                esc_html($action_title)
            );
        }
        
        return sprintf(
            '<select name="bulk_action" id="oc-bulk-action">%s</select>',
            $options
        );
    }
    
    /**
     * Validează și sanitizează datele din dropdown-uri înainte de salvare
     * 
     * @param array $form_data Datele din formular
     * @return array Datele sanitizate
     */
    public function sanitize_dropdown_data(array $form_data): array {
        $sanitized = [];
        
        // Sanitizează fiecare câmp cu validare specifică
        if (isset($form_data['product_name'])) {
            $sanitized['product_name'] = sanitize_text_field($form_data['product_name']);
        }
        
        if (isset($form_data['member_discount'])) {
            $sanitized['member_discount'] = sanitize_text_field($form_data['member_discount']);
        }
        
        if (isset($form_data['payment_method'])) {
            $allowed_methods = array_keys($this->get_available_payment_methods());
            $sanitized['payment_method'] = in_array($form_data['payment_method'], $allowed_methods) 
                ? $form_data['payment_method'] 
                : 'unknown';
        }
        
        if (isset($form_data['payment_status'])) {
            $allowed_statuses = ['paid', 'unpaid', 'partial'];
            $sanitized['payment_status'] = in_array($form_data['payment_status'], $allowed_statuses)
                ? $form_data['payment_status']
                : 'unpaid';
        }
        
        if (isset($form_data['membership_status'])) {
            $allowed_statuses = ['active', 'expired', 'completed', 'inactive', 'suspended', 'cancelled'];
            $sanitized['membership_status'] = in_array($form_data['membership_status'], $allowed_statuses)
                ? $form_data['membership_status'] 
                : 'active';
        }
        
        return $sanitized;
    }
}
