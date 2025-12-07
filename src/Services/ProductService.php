<?php

namespace Forooshyar\Services;

use WP_Query;
use WC_Product;

class ProductService 
{
    private ConfigService $configService;
    private TitleBuilder $titleBuilder;
    
    public function __construct(ConfigService $configService, TitleBuilder $titleBuilder)
    {
        $this->configService = $configService;
        $this->titleBuilder = $titleBuilder;
    }
    
    /**
     * Main entry point: used by get_products_v2 (and can be reused anywhere)
     */
    public function getProducts($args)
    {
        $show_variations = isset($args['show_variations']) ? (bool) $args['show_variations'] : true;
        $limit           = isset($args['limit']) ? (int) $args['limit'] : 100;
        $page            = isset($args['page']) ? (int) $args['page'] : 1;
        $page_unique     = isset($args['page_unique']) ? $args['page_unique'] : null;
        $page_url        = isset($args['page_url']) ? $args['page_url'] : null;

        // Validate parameters
        if ($limit <= 0 || $limit > 1000) {
            throw new \InvalidArgumentException('حد محصولات باید بین 1 تا 1000 باشد');
        }
        
        if ($page <= 0) {
            throw new \InvalidArgumentException('شماره صفحه باید بزرگتر از 0 باشد');
        }

        // Check if WooCommerce is available
        if (!function_exists('wc_get_product')) {
            throw new \Exception('WooCommerce در دسترس نیست');
        }

        return $this->getAllProducts(
            $show_variations,
            $limit,
            $page,
            $page_unique,
            $page_url
        );
    }

    /**
     * Get products by IDs with error handling
     */
    public function getProductsFromIds($product_list)
    {
        if (!is_array($product_list)) {
            throw new \InvalidArgumentException('لیست محصولات باید آرایه باشد');
        }
        
        // Check if WooCommerce is available
        if (!function_exists('wc_get_product')) {
            throw new \Exception('WooCommerce در دسترس نیست');
        }
        
        $data['products'] = array();

        foreach ($product_list as $pid) {
            if (!is_numeric($pid)) {
                continue; // Skip invalid IDs
            }
            
            try {
                $product = wc_get_product($pid);
                if ($product && $product->get_status() === "publish") {
                    $parent_id = $product->get_parent_id();
                    // Process for parent product
                    if ($parent_id == 0) {
                        $temp_product = $this->extractProductValues($product);
                        $data['products'][] = $temp_product;
                    } else {
                        // Process for visible child
                        if ($product->get_price()) {
                            $temp_product = $this->extractProductValues($product, TRUE);
                            $data['products'][] = $temp_product;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log individual product errors but continue processing others
                error_log("خطا در پردازش محصول {$pid}: " . $e->getMessage());
                continue;
            }
        }

        return $data;
    }

    /**
     * Equivalent of old get_list_slugs()
     * (Note: original code didn't push into array; behavior preserved)
     */
    public function getProductsFromSlugs($slug_list)
    {
        if (!is_array($slug_list)) {
            throw new \InvalidArgumentException('لیست نام‌های محصول باید آرایه باشد');
        }
        
        // Check if WooCommerce is available
        if (!function_exists('wc_get_product')) {
            throw new \Exception('WooCommerce در دسترس نیست');
        }
        
        $data['products'] = array();

        foreach ($slug_list as $sid) {
            try {
                $product = get_page_by_path($sid, OBJECT, 'product');
                if ($product && $product->post_status === "publish") {
                    $temp_product = $this->extractProductValues(wc_get_product($product->ID));
                    $data['products'][] = $temp_product; // Fixed: actually add to array
                }
            } catch (\Exception $e) {
                // Log individual product errors but continue processing others
                error_log("خطا در پردازش محصول با نام {$sid}: " . $e->getMessage());
                continue;
            }
        }

        return $data;
    }

    /**
     * Find matching variation (moved from find_matching_variation)
     */
    private function findMatchingVariation($product, $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                continue;
            }
            unset($attributes[$key]);
            $attributes[sprintf('attribute_%s', $key)] = $value;
        }
        if (class_exists('\WC_Data_Store')) {
            $data_store = \WC_Data_Store::load('product');
            return $data_store->find_matching_product_variation($product, $attributes);
        } else {
            return $product->get_matching_variation($attributes);
        }
    }



    /**
     * Get single product values with field filtering and clean architecture
     */
    private function extractProductValues($product, $is_child = FALSE)
    {
        try {
            $temp_product = new \stdClass();
            $temp_product->spec = array(); // Initialize spec array
            
            // Get field configuration
            $fieldConfig = $this->configService->get('fields', []);
            
            $parent = NULL;
            if ($is_child) {
                $parent = wc_get_product($product->get_parent_id());
                if (!$parent) {
                    throw new \Exception('محصول والد یافت نشد');
                }
                
                // Use TitleBuilder for consistent title generation
                $temp_product->title = $this->titleBuilder->build($parent, $product);
                $temp_product->subtitle = get_post_meta($product->get_parent_id(), 'product_english_name', true);
                $cat_ids = $parent->get_category_ids();
                $temp_product->parent_id = $parent->get_id();
            } else {
                // For simple products, use TitleBuilder without variation
                $temp_product->title = $this->titleBuilder->build($product);
                $temp_product->subtitle = get_post_meta($product->get_id(), 'product_english_name', true);
                $cat_ids = $product->get_category_ids();
                $temp_product->parent_id = 0;
            }

            // Build all product data first, then filter based on configuration
            $allData = new \stdClass();
            
            $allData->page_unique = $product->get_id();
            $allData->current_price = (string) $product->get_price();
            $allData->old_price = (string) $product->get_regular_price();
            $allData->availability = $product->get_stock_status();

            // Safe category handling
            if (!empty($cat_ids)) {
                $category = get_term_by('id', end($cat_ids), 'product_cat', 'ARRAY_A');
                $allData->category_name = $category ? $category['name'] : '';
            } else {
                $allData->category_name = '';
            }
            
            // Image handling
            $allData->image_links = [];
            $attachment_ids = $product->get_gallery_image_ids();
            foreach ($attachment_ids as $attachment_id) {
                $t_link = wp_get_attachment_image_src($attachment_id, 'full');
                if ($t_link) {
                    $allData->image_links[] = $t_link[0];
                }
            }
            
            $t_image = wp_get_attachment_image_src($product->get_image_id(), 'full');
            if ($t_image) {
                $allData->image_link = $t_image[0];
                if (!in_array($t_image[0], $allData->image_links)) {
                    $allData->image_links[] = $t_image[0];
                }
            } else {
                $allData->image_link = '';
            }
            
            $allData->page_url = get_permalink($product->get_id());
            
            // Short description handling
            $post = get_post($product->get_id());
            $allData->short_desc = !empty($post->post_excerpt) ? $post->post_excerpt : '';
            $allData->short_desc = trim(urldecode($allData->short_desc));
            $allData->short_desc = preg_replace('/\bpa_(\w+)/i', '$1', $allData->short_desc);
            
            $allData->spec = array();
            $allData->date = $product->get_date_created();
            $allData->registry = '';
            $allData->guarantee = '';
            
            // Copy title and subtitle from temp_product
            $allData->title = $temp_product->title;
            $allData->subtitle = $temp_product->subtitle;
            $allData->parent_id = $temp_product->parent_id;
        if (!$is_child) {
            if ($product->is_type('variable')) {

                // Set prices to 0 then calcualte them
                $temp_product->current_price = 0;
                $temp_product->old_price = 0;

                // Find price for default attributes. If can't find return max price of variations
                $variation_id = $this->findMatchingVariation($product, $product->get_default_attributes());
                if ($variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && $variation->exists() && $variation->get_price() !== '') {
                        $temp_product->current_price = $variation->get_price();
                        $temp_product->old_price = $variation->get_regular_price();
                        $temp_product->availability = $variation->get_stock_status();
                        $temp_product->title = $this->titleBuilder->build($product, $variation); // نمایش تنوع در عنوان
                    } else {
                        // اگر تنوع پیش‌فرض پیدا نشد، استفاده از اولین تنوع موجود
                        $variations = $product->get_children();
                        if (!empty($variations)) {
                            $first_variation = wc_get_product($variations[0]);
                            if ($first_variation && $first_variation->exists()) {
                                $temp_product->current_price = $first_variation->get_price();
                                $temp_product->old_price = $first_variation->get_regular_price();
                                $temp_product->availability = $first_variation->get_stock_status();
                                $temp_product->title = $this->titleBuilder->build($product, $first_variation);
                            } else {
                                $temp_product->current_price = $product->get_variation_price('min');
                                $temp_product->old_price = $product->get_variation_regular_price('min');
                                $temp_product->availability = 'outofstock'; // تغییر از instock به outofstock
                            }
                        } else {
                            $temp_product->current_price = $product->get_variation_price('min');
                            $temp_product->old_price = $product->get_variation_regular_price('min');
                            $temp_product->availability = 'outofstock'; // تغییر از instock به outofstock
                        }
                    }
                } else {
                    // اگر تطابق پیدا نشد، بررسی تنوعات موجود
                    $variations = $product->get_children();
                    $available_variation = null;

                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->exists() && $variation->get_stock_status() === 'instock') {
                            $available_variation = $variation;
                            break;
                        }
                    }

                    if ($available_variation) {
                        $temp_product->current_price = $available_variation->get_price();
                        $temp_product->old_price = $available_variation->get_regular_price();
                        $temp_product->availability = $available_variation->get_stock_status();
                        $temp_product->title = $this->titleBuilder->build($product, $available_variation);
                    } else {
                        $temp_product->current_price = $product->get_variation_price('min');
                        $temp_product->old_price = $product->get_variation_regular_price('min');
                        $temp_product->availability = 'outofstock'; // تغییر از instock به outofstock
                    }
                }

                // Extract default attributes
                foreach ($product->get_default_attributes() as $key => $value) {
                    if (!empty($value)) {
                        // Clean up the key - decode URL encoding and remove pa_ prefix
                        $clean_key = urldecode($key);
                        if (strpos($clean_key, 'pa_') === 0) {
                            $clean_key = substr($clean_key, 3);
                        }
                        
                        if (substr($key, 0, 3) === 'pa_') {
                            $value = get_term_by('slug', $value, $key);
                            if ($value) {
                                $value = $value->name;
                            } else {
                                $value = '';
                            }
                            $attribute_label = wc_attribute_label($key);
                            // Clean attribute label - remove pa_ prefix and decode
                            if (empty($attribute_label)) {
                                $attribute_label = ucfirst(str_replace(['-', '_'], ' ', $clean_key));
                            }
                            $attribute_label = trim(urldecode($attribute_label));
                            $attribute_label = preg_replace('/^pa_/i', '', $attribute_label);
                            
                            // Only add if value is not empty
                            if (!empty($value)) {
                                $temp_product->spec[$attribute_label] = rawurldecode($value);
                            }
                        } else {
                            // For non-pa_ attributes, also clean the key
                            $attribute_label = ucfirst(str_replace(['-', '_'], ' ', $clean_key));
                            if (!empty($value)) {
                                $temp_product->spec[$attribute_label] = rawurldecode($value);
                            }
                        }
                    }
                }
            }
            // add remain attributes
            foreach ($product->get_attributes() as $attribute) {
                if ($attribute['visible'] == 1) {
                    $attribute_name = $attribute['name'];
                    
                    // Clean attribute name - decode URL encoding and remove pa_ prefix
                    $clean_name = urldecode($attribute_name);
                    if (strpos($clean_name, 'pa_') === 0) {
                        $clean_name = substr($clean_name, 3);
                    }
                    
                    $name = wc_attribute_label($attribute_name);
                    if (empty($name)) {
                        $name = ucfirst(str_replace(['-', '_'], ' ', $clean_name));
                    }
                    // Clean the name further
                    $name = trim(urldecode($name));
                    $name = preg_replace('/^pa_/i', '', $name);
                    
                    if (substr($attribute_name, 0, 3) === 'pa_') {
                        $values = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'names'));
                    } else {
                        $values = $attribute['options'];
                    }
                    
                    // Only add if name doesn't exist and values are not empty
                    if (!array_key_exists($name, $temp_product->spec) && !empty($values)) {
                        $temp_product->spec[$name] = implode(', ', $values);
                    }
                }
            }
        } else {
            foreach ($product->get_attributes() as $key => $value) {
                if (!empty($value)) {
                    // Clean up the key - decode URL encoding and remove pa_ prefix
                    $clean_key = urldecode($key);
                    if (strpos($clean_key, 'pa_') === 0) {
                        $clean_key = substr($clean_key, 3);
                    }
                    
                    if (substr($key, 0, 3) === 'pa_') {
                        $value = get_term_by('slug', $value, $key);
                        if ($value) {
                            $value = $value->name;
                        } else {
                            $value = '';
                        }
                        $attribute_label = wc_attribute_label($key);
                        if (empty($attribute_label)) {
                            $attribute_label = ucfirst(str_replace(['-', '_'], ' ', $clean_key));
                        }
                        // Clean attribute label
                        $attribute_label = trim(urldecode($attribute_label));
                        $attribute_label = preg_replace('/^pa_/i', '', $attribute_label);
                        
                        // Only add if value is not empty
                        if (!empty($value)) {
                            $temp_product->spec[$attribute_label] = rawurldecode($value);
                        }
                    } else {
                        // For non-pa_ attributes, also clean the key  
                        $attribute_label = ucfirst(str_replace(['-', '_'], ' ', $clean_key));
                        if (!empty($value)) {
                            $temp_product->spec[$attribute_label] = rawurldecode($value);
                        }
                    }
                }
            }
        }

        // Set registry and guarantee
        if (!empty($temp_product->spec['رجیستری'])) {
            $temp_product->registry = $temp_product->spec['رجیستری'];
        } elseif (!empty($temp_product->spec['registry'])) {
            $temp_product->registry = $temp_product->spec['registry'];
        } elseif (!empty($temp_product->spec['ریجیستری'])) {
            $temp_product->registry = $temp_product->spec['ریجیستری'];
        } elseif (!empty($temp_product->spec['ریجستری'])) {
            $temp_product->registry = $temp_product->spec['ریجستری'];
        }

        $guarantee_keys = [
            "گارانتی",
            "guarantee",
            "warranty",
            "garanty",
            "گارانتی:",
            "گارانتی محصول",
            "گارانتی محصول:",
            "ضمانت",
            "ضمانت:"
        ];

        foreach ($guarantee_keys as $guarantee) {
            if (!empty($temp_product->spec[$guarantee])) {
                $temp_product->guarantee = $temp_product->spec[$guarantee];
            }
        }

        if (!array_key_exists('شناسه کالا', $temp_product->spec)) {
            $sku = $product->get_sku();
            if ($sku != "") {
                $temp_product->spec['شناسه کالا'] = $sku;
            }
        }

            if (count($allData->spec) > 0) {
                $allData->spec = [$allData->spec];
            }

            // Apply field filtering based on configuration
            return $this->filterFields($allData, $fieldConfig);
            
        } catch (\Exception $e) {
            // Log error and return minimal product data
            error_log('خطا در استخراج اطلاعات محصول: ' . $e->getMessage());
            
            $minimal = new \stdClass();
            $minimal->page_unique = $product->get_id();
            $minimal->title = $product->get_name();
            $minimal->parent_id = $is_child ? $product->get_parent_id() : 0;
            
            return $this->filterFields($minimal, $fieldConfig);
        }
    }
    
    /**
     * Filter product fields based on configuration
     */
    private function filterFields($productData, $fieldConfig)
    {
        $filtered = new \stdClass();
        
        // Default fields that should always be enabled if not configured
        $defaultFields = [
            'title' => true,
            'subtitle' => true,
            'parent_id' => true,
            'page_unique' => true,
            'current_price' => true,
            'old_price' => true,
            'availability' => true,
            'category_name' => true,
            'image_links' => true,
            'image_link' => true,
            'page_url' => true,
            'short_desc' => true,
            'spec' => true,
            'date' => true,
            'registry' => true,
            'guarantee' => true
        ];
        
        // Merge with configuration, defaulting to enabled
        $effectiveConfig = array_merge($defaultFields, $fieldConfig);
        
        foreach ($effectiveConfig as $field => $enabled) {
            if ($enabled && property_exists($productData, $field)) {
                $filtered->$field = $productData->$field;
            }
        }
        
        return $filtered;
    }

    /**
     * URL → post ID (moved from url_to_post_id)
     */
    private function urlToPostId($permalink)
    {
        // Decode URL and clean up tracking parameters
        $permalink = urldecode($permalink);
        $permalink = remove_query_arg('utm_medium', $permalink);
        $permalink = remove_query_arg('utm_source', $permalink);
        $permalink = rtrim($permalink, '/');

        // First try WordPress's built-in url_to_postid
        $post_id = url_to_postid($permalink);
        if ($post_id) {
            return (int) $post_id;
        }

        // Parse URL components
        $parsed_url = wp_parse_url($permalink);
        $path = trim(isset($parsed_url['path']) ? $parsed_url['path'] : '', '/');
        $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';

        // Get slug from path
        $path_parts = explode('/', $path);
        $slug = end($path_parts);

        // Try to get product by slug first
        $product = get_page_by_path($slug, OBJECT, 'product');
        if ($product instanceof \WP_Post) {
            return (int) $product->ID;
        }

        // Handle variable products with attributes
        parse_str($query, $query_params);
        $attributes = [];
        foreach ($query_params as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $attributes[sanitize_title(str_replace('attribute_', '', $key))] = sanitize_title($value);
            }
        }

        global $wpdb;

        // First try to find main product
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts 
                WHERE post_type = 'product' 
                AND post_status = 'publish' 
                AND post_name = %s",
                $slug
            )
        );

        if ($product_id && !empty($attributes)) {
            // Try to find variation
            $placeholders = implode(',', array_fill(0, count($attributes), '%s'));

            $sql = "
                SELECT post_id FROM $wpdb->postmeta pm
                JOIN $wpdb->posts p ON p.ID = pm.post_id
                WHERE p.post_type = 'product_variation'
                AND p.post_status = 'publish'
                AND p.post_parent = %d
                AND pm.meta_key LIKE 'attribute_%%'
                AND pm.meta_value IN ($placeholders)
                GROUP BY post_id
                HAVING COUNT(DISTINCT pm.meta_key) = %d
            ";

            $params = array_merge(
                array($product_id),
                array_values($attributes),
                array(count($attributes))
            );

            $variation_id = $wpdb->get_var(
                $wpdb->prepare($sql, $params)
            );

            return $variation_id ? (int) $variation_id : (int) $product_id;
        }

        return $product_id ? (int) $product_id : 0;
    }

    /**
     * Core query logic (moved from get_all_products)
     */
    private function getAllProducts($show_variations, $limit, $page, $page_unique = null, $permalink = null)
    {
        // 1) Build the WP_Query arguments
        $query_args = [
            'post_type'      => $show_variations
                ? ['product', 'product_variation']
                : ['product'],
            'posts_per_page' => $limit,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            // optional but faster if you just need IDs:
            'fields'         => 'ids',
        ];

        // 2) (Optional) filter by single ID or permalink if requested
        if ($page_unique) {
            $query_args['post__in'] = [intval($page_unique)];
        } elseif ($permalink) {
            $pid = $this->urlToPostId($permalink);
            if ($pid) {
                $query_args['post__in'] = [$pid];
            } else {
                return [
                    'count'     => 0,
                    'max_pages' => 0,
                    'products'  => [],
                ];
            }
        }

        // 3) Run the query
        $q = new WP_Query($query_args);

        // 4) Prepare the response
        $data = [
            'count'     => $q->found_posts,
            'max_pages' => $q->max_num_pages,
            'products'  => [],
        ];

        // 5) Loop *only* over the IDs
        foreach ($q->posts as $post_id) {
            $product = wc_get_product($post_id);
            if (!$product || $product->get_status() !== 'publish') {
                continue;
            }

            $is_variation = $product->is_type('variation');

            // if user asked NOT to see variations, skip them
            if ($is_variation && !$show_variations) {
                continue;
            }

            // now build your payload
            $data['products'][] = $this->extractProductValues($product, $is_variation);
        }

        return $data;
    }
}