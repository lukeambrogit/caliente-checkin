<?php
/**
 * Trait pentru logica cursurilor - EXTRAS din class-oc-membership-shortcodes.php
 * 
 * CONFORMITATE .cursorrules:
 * - Gestionează DOAR logica cursurilor și variațiilor
 * - Integrare cu ADD-ON #1 prin API non-intruzive
 * - PĂSTREAZĂ EXACT funcționalitățile existente
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OC_Membership_Courses
 * 
 * Conține toată logica pentru cursuri, variații și mapări
 * EXTRAS IDENTIC din class-oc-membership-shortcodes.php
 */
trait OC_Membership_Courses {
    
    /**
     * Obține cursurile incluse din comanda clientului pe baza variation_id
     * EXACT ca în versiunea originală - linia 3267
     * 
     * @param array $membership_data
     * @return string
     */
    public function get_courses_included_from_order(array $membership_data): string {
        if (!isset($membership_data['order_id']) || !$membership_data['order_id']) {
            return 'N/A';
        }
        
        $order = wc_get_order($membership_data['order_id']);
        if (!$order) {
            return 'N/A';
        }
        
        $courses = [];
        
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();
            $item_name = $item->get_name();
            
            // Pentru Pool Products: caută variation_id-urile (cursurile selectate)
            if ($item_variation_id && $item_product_id) {
                $variation = wc_get_product($item_variation_id);
                if ($variation && $variation->is_type('variation')) {
                    // Obține numele variației (cursul)
                    $variation_name = $variation->get_name();
                    
                    // Elimină prefixul părintelui și păstrează doar numele cursului
                    $parent_product = wc_get_product($item_product_id);
                    if ($parent_product) {
                        $parent_name = $parent_product->get_name();
                        // Elimină numele părintelui din numele variației
                        $clean_course_name = str_replace($parent_name . ' - ', '', $variation_name);
                        $clean_course_name = str_replace($parent_name, '', $clean_course_name);
                        $clean_course_name = trim($clean_course_name, ' -');
                        
                        if (!empty($clean_course_name)) {
                            $courses[] = $clean_course_name;
                        } else {
                            $courses[] = $variation_name; // Fallback la numele complet
                        }
                    } else {
                        $courses[] = $variation_name;
                    }
                }
            }
            // Pentru Simple Products: nume direct din item
            elseif (!$item_variation_id && $item_product_id) {
                $product = wc_get_product($item_product_id);
                if ($product && $product->is_type('simple')) {
                    // Verifică dacă nu e pachetul principal
                    $oc_pool_enabled = get_post_meta($item_product_id, '_oc_pool_enabled', true);
                    $mv_pack_enabled = get_post_meta($item_product_id, '_mv_pack_enabled', true);
                    
                    // Dacă nu e pachet, e probabil un curs individual
                    if (empty($oc_pool_enabled) && empty($mv_pack_enabled)) {
                        $courses[] = $item_name;
                    }
                }
            }
        }
        
        // Elimină duplicatele și formatează
        $courses = array_unique($courses);
        $result = !empty($courses) ? implode(', ', $courses) : 'N/A';
        
        return $result;
    }
    
    /**
     * Obține cursurile incluse pentru un membership
     * EXACT ca în versiunea originală - linia 2782
     * Pentru Pool packages: returnează variațiile selectate de client
     * Pentru membership-uri clasice: returnează mapările configurate
     */
    public function get_membership_courses($product_id, $membership_data = null): string {
        if (!$product_id) {
            return 'N/A';
        }
        
        // 1. Pentru Pool packages, încearcă să obții cursurile selectate din order
        if ($membership_data && isset($membership_data['order_id']) && $membership_data['order_id']) {
            $selected_courses = $this->get_selected_courses_from_order($membership_data['order_id'], $product_id);
            
            if (!empty($selected_courses)) {
                return $selected_courses;
            }
        }
        
        // 2. Fallback la mapările configurate (membership-uri clasice)
        $validator = OC_Membership_Validator::get_instance();
        if (!$validator || !$validator->get_db()) {
            return 'N/A';
        }
        
        $db = $validator->get_db();
        $mappings = $db->get_membership_course_mappings($product_id);
        
        if (empty($mappings)) {
            return 'Toate cursurile'; // Default pentru produse fără mapare specifică
        }
        
        // Obține numele cursurilor
        $course_names = [];
        foreach ($mappings as $mapping) {
            $variation = get_post($mapping['variation_id']);
            if ($variation) {
                $course_names[] = $variation->post_title;
            }
        }
        
        return !empty($course_names) ? implode(', ', $course_names) : 'N/A';
    }
    
    /**
     * Obține variațiile selectate de client din order 
     * EXACT ca în versiunea originală - linia 2825
     * Pe baza debug-ului: ia prima variație găsită pentru product_id
     */
    public function get_selected_courses_from_order($order_id, $product_id): string {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return '';
            }
            
            // Pe baza debug-ului: caută prima variație pentru product_id
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $item_variation_id = $item->get_variation_id();
                
                // Match exact pe product_id și ia prima variație găsită
                if ($item_product_id == $product_id && $item_variation_id > 0) {
                    $variation = wc_get_product($item_variation_id);
                    if ($variation) {
                        return $variation->get_name(); // Returnează prima găsită
                    }
                }
            }
            
            return ''; // Nu s-a găsit nimic
            
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * Generează opțiunile pentru dropdown-ul cursurilor în modul edit
     * EXACT ca în versiunea originală - linia 3340
     * 
     * @param array $member Datele membrului
     * @return string HTML options
     */
    public function get_courses_dropdown_options(array $member): string {
        $options = '<option value="">Selectează curs</option>';
        
        // Obține ID-ul produsului pentru a găsi cursurile disponibile
        // Încearcă să găsească product_id din diferite surse
        $product_id = $member['product_id'] ?? null;
        
        // Dacă nu există product_id, încearcă să-l obții din order_id
        if (!$product_id && isset($member['order_id']) && $member['order_id']) {
            $product_id = $this->get_product_id_from_order($member['order_id']);
        }
        
        if (!$product_id) {
            return '<option selected>' . esc_html($member['courses_included'] ?: 'N/A') . '</option>';
        }
        
        // Obține cursul selectat de client din comandă
        $selected_course = $member['courses_included'] ?? '';
        
        // Obține toate cursurile disponibile din Pool Product
        $available_courses = $this->get_available_courses_from_pool_product($product_id);
        
        if (empty($available_courses)) {
            // Fallback: afișează cursul selectat ca opțiune unică
            return '<option selected>' . esc_html($selected_course ?: 'N/A') . '</option>';
        }
        
        // Generează opțiunile
        foreach ($available_courses as $course) {
            $course_name = $course['name'];
            $course_id = $course['variation_id'];
            
            // Verifică dacă acest curs este selectat de client
            $is_selected = $this->is_course_selected($course_name, $selected_course) ? ' selected' : '';
            
            $options .= sprintf(
                '<option value="%d"%s>%s</option>',
                $course_id,
                $is_selected,
                esc_html($course_name)
            );
        }
        
        return $options;
    }
    
    /**
     * Obține toate cursurile disponibile din Pool Product
     * EXACT ca în versiunea originală - linia 3394
     * 
     * @param int $product_id
     * @return array
     */
    public function get_available_courses_from_pool_product(int $product_id): array {
        $courses = [];
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return $courses;
        }
        
        // Verifică dacă e Pool Product (variable product cu variații)
        if (!$product->is_type('variable')) {
            // Nu e variable product, returnează produsul simplu ca "curs"
            return [
                [
                    'name' => $product->get_name(),
                    'variation_id' => 0,
                    'product_id' => $product_id
                ]
            ];
        }
        
        // Pentru Pool Products: obține toate variațiile (cursurile)
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            
            foreach ($variations as $variation_data) {
                $variation_id = $variation_data['variation_id'];
                $variation = wc_get_product($variation_id);
                
                if ($variation) {
                    $variation_name = $variation->get_name();
                    
                    // Curăță numele (elimină prefixul părintelui)
                    $parent_name = $product->get_name();
                    $clean_name = str_replace($parent_name . ' - ', '', $variation_name);
                    $clean_name = str_replace($parent_name, '', $clean_name);
                    $clean_name = trim($clean_name, ' -');
                    
                    $courses[] = [
                        'name' => !empty($clean_name) ? $clean_name : $variation_name,
                        'variation_id' => $variation_id,
                        'product_id' => $product_id
                    ];
                }
            }
        }
        
        return $courses;
    }
    
    /**
     * Verifică dacă un curs este selectat
     * EXACT ca în versiunea originală - linia 3451
     * 
     * @param string $course_name
     * @param string $selected_courses
     * @return bool
     */
    public function is_course_selected(string $course_name, string $selected_courses): bool {
        if (empty($selected_courses) || empty($course_name)) {
            return false;
        }
        
        // Împarte cursurile selectate (pot fi multiple, separate prin virgulă)
        $selected_array = array_map('trim', explode(',', $selected_courses));
        
        // Verifică dacă cursul curent este în lista celor selectate
        return in_array($course_name, $selected_array, true);
    }
    
    /**
     * Obține Pool Product ID din comandă (produsul cu variațiile, nu pachetul)
     * EXACT ca în versiunea originală - linia 3469
     * 
     * @param int $order_id
     * @return int|null
     */
    public function get_product_id_from_order(int $order_id): ?int {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        // PRIORITATEA 1: Caută Pool Product (produsul cu variațiile)
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();
            
            // Dacă are variation_id, înseamnă că $item_product_id e Pool Product-ul
            if ($item_variation_id && $item_product_id) {
                $product = wc_get_product($item_product_id);
                if ($product && $product->is_type('variable')) {
                    return $item_product_id;
                }
            }
        }
        
        // PRIORITATEA 2: Dacă nu găsește Pool Product, caută pachetul principal
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $item_variation_id = $item->get_variation_id();
            
            if (!$item_variation_id) {
                return $item_product_id;
            }
        }
        
        return null;
    }
}
