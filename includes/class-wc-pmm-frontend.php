<?php
/**
 * Frontend functionality for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('wc_pmm_simple_gallery', array($this, 'simple_gallery_shortcode'));
    }
    
    /**
     * Simple gallery shortcode - shows first product's watermarked images from category
     */
    public function simple_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
        ), $atts);
        
        if (empty($atts['category'])) {
            return '<p>' . __('Please specify a category.', 'wc-product-media-manager') . '</p>';
        }
        
        return $this->get_simple_gallery_html($atts['category']);
    }
    
    /**
     * Get simple gallery HTML - shows first product's watermarked images from category
     */
    private function get_simple_gallery_html($category_slug) {
        // Get category term
        $category = get_term_by('slug', $category_slug, 'product_cat');
        if (!$category) {
            return '<div class="wc-pmm-error"><p>' . __('Category not found.', 'wc-product-media-manager') . '</p></div>';
        }
        
        // Get all products in this category with watermarked media
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'category' => array($category_slug),
        ));
        
        $products_with_media = array();
        $first_product_with_media = null;
        
        foreach ($products as $product) {
            $media_data = get_post_meta($product->get_id(), '_wc_pmm_product_media', true);
            if (!empty($media_data) && is_array($media_data)) {
                // Filter watermarked images
                $watermarked = array_filter($media_data, function($item) {
                    return !empty($item['watermark_id']) && !empty($item['watermark_url']);
                });
                
                if (!empty($watermarked)) {
                    $products_with_media[] = array(
                        'product' => $product,
                        'media_count' => count($watermarked)
                    );
                    
                    // Set first product for displaying images
                    if ($first_product_with_media === null) {
                        $first_product_with_media = $product;
                    }
                }
            }
        }
        
        if (empty($products_with_media)) {
            return '<div class="wc-pmm-no-products"><p>' . __('No products with watermarked media found in this category.', 'wc-product-media-manager') . '</p></div>';
        }
        
        // Get first 15 watermarked images from first product
        $media_response = $this->get_product_media_for_display($first_product_with_media->get_id(), 1, 15);
        $images = $media_response['images'];
        
        if (empty($images)) {
            return '<div class="wc-pmm-no-products"><p>' . __('No watermarked images found for this product.', 'wc-product-media-manager') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="wc-pmm-simple-gallery">
            <!-- All Product Titles (Clickable) -->
            <div class="wc-pmm-product-header">
                <h3><?php _e('Products in this category:', 'wc-product-media-manager'); ?></h3>
                <div class="wc-pmm-product-links">
                    <?php foreach ($products_with_media as $product_data): ?>
                        <a href="<?php echo esc_url($product_data['product']->get_permalink()); ?>" 
                           target="_blank" 
                           class="wc-pmm-product-link">
                            <?php echo esc_html($product_data['product']->get_name()); ?>
                            <span class="wc-pmm-media-count">(<?php echo $product_data['media_count']; ?> images)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="wc-pmm-showing-from">
                    <?php _e('Showing images from:', 'wc-product-media-manager'); ?> 
                    <strong><?php echo esc_html($first_product_with_media->get_name()); ?></strong>
                </div>
            </div>
            
            <!-- Images Grid -->
            <div class="row large-columns-4 medium-columns-3 small-columns-2 row-full-width row-masonry" data-packery-options='{"itemSelector": ".gallery-col", "gutter": 10, "presentageWidth" : true}'>
                <?php foreach ($images as $image): ?>
                    <div class=" gallery-col col">
                    <div class="col-inner">
                                                 <a href="<?php echo esc_url($image['watermark_url']); ?>"
                         data-productid="<?php echo esc_attr($first_product_with_media->get_id()); ?>"
                         data-fancybox="products"
                         data-sku="<?php echo esc_attr($image['sku']); ?>"
                         data-caption="<?php echo esc_attr($first_product_with_media->get_name()); ?>"
                         aria-label="<?php echo esc_attr($first_product_with_media->get_name()); ?>"
                         >
                        <img src="<?php echo esc_url($image['watermark_url']); ?>" 
                             alt="<?php echo esc_attr($image['sku']); ?>"
                             class="wc-pmm-watermarked-image" />
                        </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .wc-pmm-simple-gallery {
            margin: 20px 0;
        }
        .wc-pmm-product-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .wc-pmm-product-header h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #333;
        }
        .wc-pmm-product-links {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .wc-pmm-product-link {
            color: #0073aa;
            text-decoration: none;
            font-weight: bold;
            padding: 8px 12px;
            border: 1px solid #0073aa;
            border-radius: 4px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .wc-pmm-product-link:hover {
            color: #fff;
            background-color: #0073aa;
            text-decoration: none;
        }
        .wc-pmm-media-count {
            color: inherit;
            font-size: 12px;
            font-weight: normal;
            margin-left: 5px;
        }
        .wc-pmm-showing-from {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }
        .wc-pmm-simple-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .wc-pmm-image-item {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background: #f9f9f9;
        }
       
        .wc-pmm-image-sku {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
            font-weight: bold;
        }
        .wc-pmm-error, .wc-pmm-no-products {
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            color: #856404;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get product media for display
     */
    private function get_product_media_for_display($product_id, $page = 1, $per_page = 15) {
        $media_data = get_post_meta($product_id, '_wc_pmm_product_media', true);
        
        if (empty($media_data) || !is_array($media_data)) {
            return array(
                'images' => array(),
                'total' => 0
            );
        }
        
        // Filter watermarked images only
        $watermarked_media = array_filter($media_data, function($item) {
            return !empty($item['watermark_id']) && !empty($item['watermark_url']);
        });
        
        // Calculate pagination
        $total = count($watermarked_media);
        $offset = ($page - 1) * $per_page;
        $paged_media = array_slice($watermarked_media, $offset, $per_page);
        
        // Format response
        $images = array();
        foreach ($paged_media as $item) {
            $images[] = array(
                'attachment_id' => $item['attachment_id'],
                'watermark_id' => $item['watermark_id'],
                'original_url' => wp_get_attachment_url($item['attachment_id']),
                'watermark_url' => wp_get_attachment_url($item['watermark_id']),
                'sku' => $item['sku'],
                'filename' => isset($item['filename']) ? $item['filename'] : ''
            );
        }
        
        return array(
            'images' => $images,
            'total' => $total
        );
    }
}
