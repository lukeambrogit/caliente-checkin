<?php
/**
 * WooCommerce integration class
 * 
 * Handles all WooCommerce-specific functionality
 * including attributes and terms management.
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce integration class
 */
class OC_WooCommerce {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor logic if needed
    }
    
    /**
     * Get all WooCommerce variable products
     * 
     * @return array Array of variable products with ID and name
     */
    public function get_variable_products() {
        if (!$this->is_woocommerce_active()) {
            return [];
        }
        
        $products = [];
        
        try {
            // Get all variable products
            $variable_products = wc_get_products([
                'type' => 'variable',
                'status' => 'publish',
                'limit' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            
            if (empty($variable_products)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // No variable products found');
                }
                return [];
            }
            
            foreach ($variable_products as $product) {
                if (!is_a($product, 'WC_Product_Variable')) {
                    continue;
                }
                
                $products[$product->get_id()] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'slug' => $product->get_slug(),
                    'description' => $product->get_short_description(),
                    'variations_count' => count($product->get_children()),
                    'status' => $product->get_status()
                ];
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Error getting variable products: ' . $e->getMessage());
            }
            return [];
        }
        
        return $products;
    }
    
    /**
     * Fallback method to get attributes directly from database
     * 
     * @return array
     */
    private function get_attributes_fallback() {
        global $wpdb;
        
        if (!class_exists('WooCommerce')) {
            return [];
        }
        
        $attributes = [];
        
        try {
            // Get attributes directly from database
            $results = $wpdb->get_results("
                SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
                ORDER BY attribute_name
            ");
            
            if (empty($results)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // No attributes found in database');
                }
                return [];
            }
            
            foreach ($results as $attribute) {
                $taxonomy = 'pa_' . $attribute->attribute_name;
                
                $attributes[$taxonomy] = [
                    'label' => $attribute->attribute_label ?: $attribute->attribute_name,
                    'name' => $attribute->attribute_name,
                    'slug' => $attribute->attribute_name,
                    'type' => $attribute->attribute_type ?: 'select',
                    'orderby' => $attribute->attribute_orderby ?: 'menu_order',
                    'public' => $attribute->attribute_public ?: 0
                ];
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Error in fallback method: ' . $e->getMessage());
            }
        }
        
        return $attributes;
    }
    
    /**
     * Get variations for a specific variable product
     * 
     * @param int $product_id The variable product ID
     * @param array $args Additional arguments
     * @return array Array of variations
     */
    public function get_product_variations($product_id, $args = []) {
        if (!$this->is_woocommerce_active()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // WooCommerce not active in get_product_variations');
            }
            return [];
        }
        
        if (empty($product_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Product ID is empty');
            }
            return [];
        }
        
        $default_args = [
            'status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        try {
            // Get the variable product
            $product = wc_get_product($product_id);
            
            if (!$product || !is_a($product, 'WC_Product_Variable')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // Product not found or not variable: ' . $product_id);
                }
                return [];
            }
            
            // Get variation IDs
            $variation_ids = $product->get_children();
            
            if (empty($variation_ids)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // No variations found for product: ' . $product_id);
                }
                return [];
            }
            
            $formatted_variations = [];
            foreach ($variation_ids as $variation_id) {
                $variation = wc_get_product($variation_id);
                
                if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
                    continue;
                }
                
                // Skip non-published variations based on args
                if ($args['status'] === 'publish' && $variation->get_status() !== 'publish') {
                    continue;
                }
                
                $formatted_variations[] = [
                    'id' => $variation->get_id(),
                    'name' => $this->get_clean_variation_name($variation),
                    'slug' => $variation->get_slug(),
                    'description' => $variation->get_description(),
                    'price' => $variation->get_price(),
                    'price_html' => $variation->get_price_html(),
                    'sku' => $variation->get_sku(),
                    'stock_status' => $variation->get_stock_status(),
                    'attributes' => $variation->get_variation_attributes(),
                    'status' => $variation->get_status()
                ];
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Successfully loaded ' . count($formatted_variations) . ' variations for product ' . $product_id);
            }
            
            return $formatted_variations;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Exception in get_product_variations: ' . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Fallback method to get terms directly from database
     * 
     * @param string $taxonomy The attribute taxonomy name
     * @return array Array of terms
     */
    private function get_terms_fallback($taxonomy) {
        global $wpdb;
        
        try {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT t.term_id, t.name, t.slug, tt.description, tt.count
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = %s
                ORDER BY t.name ASC
            ", $taxonomy));
            
            if (empty($results)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // No terms found in fallback for taxonomy: ' . $taxonomy);
                }
                return [];
            }
            
            $formatted_terms = [];
            foreach ($results as $term) {
                $formatted_terms[] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description ?: '',
                    'count' => (int) $term->count
                ];
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Fallback successfully loaded ' . count($formatted_terms) . ' terms for ' . $taxonomy);
            }
            
            return $formatted_terms;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Exception in get_terms_fallback: ' . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Get variation information by variation ID
     * 
     * @param int $variation_id
     * @return array|false
     */
    public function get_variation_info($variation_id) {
        if (!$this->is_woocommerce_active()) {
            return false;
        }
        
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
            return false;
        }
        
        return [
            'id' => $variation->get_id(),
            'parent_id' => $variation->get_parent_id(),
            'name' => $this->get_clean_variation_name($variation),
            'slug' => $variation->get_slug(),
            'description' => $variation->get_description(),
            'price' => $variation->get_price(),
            'price_html' => $variation->get_price_html(),
            'sku' => $variation->get_sku(),
            'stock_status' => $variation->get_stock_status(),
            'attributes' => $variation->get_variation_attributes(),
            'status' => $variation->get_status()
        ];
    }
    
    /**
     * Validate if product is a valid WooCommerce variable product
     * 
     * @param int $product_id
     * @return bool
     */
    public function is_valid_variable_product($product_id) {
        if (!$this->is_woocommerce_active()) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        return $product->is_type('variable');
    }
    
    /**
     * Get term by ID with additional validation
     * 
     * @param int $term_id
     * @param string $taxonomy
     * @return WP_Term|false
     */
    public function get_validated_term($term_id, $taxonomy = '') {
        $term = get_term($term_id);
        
        if (is_wp_error($term) || !$term) {
            return false;
        }
        
        // If taxonomy is specified, validate it matches
        if (!empty($taxonomy) && $term->taxonomy !== $taxonomy) {
            return false;
        }
        
        // Ensure it's a WooCommerce attribute taxonomy
        if (!$this->is_valid_attribute_taxonomy($term->taxonomy)) {
            return false;
        }
        
        return $term;
    }
    
    /**
     * Create a new attribute term
     * 
     * @param string $taxonomy
     * @param string $name
     * @param array $args
     * @return array|WP_Error
     */
    public function create_attribute_term($taxonomy, $name, $args = []) {
        if (!$this->is_valid_attribute_taxonomy($taxonomy)) {
            return new WP_Error('invalid_taxonomy', __('Taxonomie invalidă.', OC_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_product_terms')) {
            return new WP_Error('insufficient_permissions', __('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
        }
        
        $default_args = [
            'description' => '',
            'slug' => '',
            'parent' => 0
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        return wp_insert_term($name, $taxonomy, $args);
    }
    
    /**
     * Update an attribute term
     * 
     * @param int $term_id
     * @param string $taxonomy
     * @param array $args
     * @return array|WP_Error
     */
    public function update_attribute_term($term_id, $taxonomy, $args = []) {
        if (!$this->is_valid_attribute_taxonomy($taxonomy)) {
            return new WP_Error('invalid_taxonomy', __('Taxonomie invalidă.', OC_TEXT_DOMAIN));
        }
        
        if (!current_user_can('manage_product_terms')) {
            return new WP_Error('insufficient_permissions', __('Permisiuni insuficiente.', OC_TEXT_DOMAIN));
        }
        
        return wp_update_term($term_id, $taxonomy, $args);
    }
    
    /**
     * Get products that use a specific attribute term
     * 
     * @param int $term_id
     * @param string $taxonomy
     * @return array
     */
    public function get_products_by_attribute_term($term_id, $taxonomy) {
        if (!$this->is_valid_attribute_taxonomy($taxonomy)) {
            return [];
        }
        
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_id
                ]
            ],
            'fields' => 'ids'
        ]);
        
        return $products;
    }
    
    /**
     * Get attribute usage statistics
     * 
     * @param string $taxonomy
     * @return array
     */
    public function get_attribute_stats($taxonomy) {
        if (!$this->is_valid_attribute_taxonomy($taxonomy)) {
            return [
                'total_terms' => 0,
                'terms_with_products' => 0,
                'total_products' => 0
            ];
        }
        
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ]);
        
        $total_terms = count($terms);
        $terms_with_products = 0;
        $total_products = 0;
        
        foreach ($terms as $term) {
            if ($term->count > 0) {
                $terms_with_products++;
                $total_products += $term->count;
            }
        }
        
        return [
            'total_terms' => $total_terms,
            'terms_with_products' => $terms_with_products,
            'total_products' => $total_products
        ];
    }
    
    /**
     * Check if WooCommerce is active and loaded
     * 
     * @return bool
     */
    private function is_woocommerce_active() {
        // Check if WooCommerce class exists
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Check if essential WooCommerce functions are available
        if (!function_exists('wc_get_attribute_taxonomies') || 
            !function_exists('wc_attribute_taxonomy_name')) {
            // If functions don't exist but WooCommerce class does, we can still try fallback
            return true;
        }
        
        return true;
    }
    
    /**
     * Get attribute taxonomy name from attribute name
     * 
     * @param string $attribute_name
     * @return string
     */
    public function get_attribute_taxonomy($attribute_name) {
        return wc_attribute_taxonomy_name($attribute_name);
    }
    
    /**
     * Get attribute name from taxonomy
     * 
     * @param string $taxonomy
     * @return string
     */
    public function get_attribute_name_from_taxonomy($taxonomy) {
        return wc_sanitize_taxonomy_name(str_replace('pa_', '', $taxonomy));
    }
    
    /**
     * Get clean variation name (without parent product name)
     * 
     * @param WC_Product_Variation $variation
     * @return string
     */
    private function get_clean_variation_name($variation) {
        $full_name = $variation->get_name();
        $parent_product = wc_get_product($variation->get_parent_id());
        
        if (!$parent_product) {
            return $full_name;
        }
        
        $parent_name = $parent_product->get_name();
        
        // Remove parent name with common separators
        $separators = [' - ', ': ', ' – ', ' — '];
        
        foreach ($separators as $separator) {
            $prefix = $parent_name . $separator;
            if (strpos($full_name, $prefix) === 0) {
                return substr($full_name, strlen($prefix));
            }
        }
        
        // If no separator found, try to extract from attributes
        $attributes = $variation->get_variation_attributes();
        if (!empty($attributes)) {
            // Get the first attribute value and try to match it in the name
            $first_attribute = reset($attributes);
            if (!empty($first_attribute)) {
                // Convert slug to title case for better matching
                $attribute_title = str_replace('-', ' ', $first_attribute);
                $attribute_title = ucwords($attribute_title);
                
                // Check if this matches part of the full name
                if (stripos($full_name, $attribute_title) !== false) {
                    return $attribute_title;
                }
            }
        }
        
        // Fallback: return the full name
        return $full_name;
    }
    
    /**
     * Format variation for display
     * 
     * @param int $variation_id
     * @param string $format
     * @return string
     */
    public function format_variation_display($variation_id, $format = 'name') {
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
            return '';
        }
        
        switch ($format) {
            case 'name':
                return $this->get_clean_variation_name($variation);
            case 'name_with_price':
                return $this->get_clean_variation_name($variation) . ' - ' . $variation->get_price_html();
            case 'price':
                return $variation->get_price_html();
            case 'sku':
                return $variation->get_sku();
            default:
                return $this->get_clean_variation_name($variation);
        }
    }
    
    /**
     * Get all variable products (cached)
     * 
     * @return array
     */
    public function get_cached_variable_products() {
        $cache_key = 'oc_wc_variable_products';
        $cached = wp_cache_get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $products = $this->get_variable_products();
        wp_cache_set($cache_key, $products, '', HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Clear products cache
     */
    public function clear_products_cache() {
        wp_cache_delete('oc_wc_variable_products');
    }
    
    /**
     * Get cached variations for a product
     * 
     * @param int $product_id
     * @return array
     */
    public function get_cached_product_variations($product_id) {
        $cache_key = 'oc_wc_variations_' . $product_id;
        $cached = wp_cache_get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $variations = $this->get_product_variations($product_id);
        wp_cache_set($cache_key, $variations, '', HOUR_IN_SECONDS);
        
        return $variations;
    }
    
    /**
     * Clear variations cache for a product
     * 
     * @param int $product_id
     */
    public function clear_variations_cache($product_id) {
        wp_cache_delete('oc_wc_variations_' . $product_id);
    }
}
