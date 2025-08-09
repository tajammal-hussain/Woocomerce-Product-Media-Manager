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
        add_shortcode('wc_pmm_product_gallery', array($this, 'product_gallery_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wc_pmm_load_more_images', array($this, 'ajax_load_more_images'));
        add_action('wp_ajax_nopriv_wc_pmm_load_more_images', array($this, 'ajax_load_more_images'));
    }
    
    /**
     * Enqueue scripts for infinite scroll
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        wp_enqueue_script( "isotope-grid-main", 
        WC_PMM_PLUGIN_URL . '/assets/js/isotope.pkgd.min.js', 
        array('jquery'), 
        '1.0.0', 
        true );

      
        wp_localize_script('jquery', 'wc_pmm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_pmm_nonce')
        ));
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
                <div class="wc-pmm-product-links">
                    <?php foreach ($products_with_media as $index => $product_data): ?>
                        <button type="button"
                                data-product-id="<?php echo esc_attr($product_data['product']->get_id()); ?>"
                                data-product-name="<?php echo esc_attr($product_data['product']->get_name()); ?>"
                                class="wc-pmm-product-link <?php echo $index === 0 ? 'active' : ''; ?>">
                            <?php echo esc_html($product_data['product']->get_name()); ?>
                            <!-- <span class="wc-pmm-media-count">(<?php echo $product_data['media_count']; ?> images)</span> -->
                        </button>
                       
                    <?php endforeach; ?>
                </div>
                <div class="wc-pmm-showing-from">
                    <?php _e('Showing images from:', 'wc-product-media-manager'); ?> 
                    <strong><?php echo esc_html($first_product_with_media->get_name()); ?></strong>
                </div>
            </div>
            
            <!-- Images Grid -->
            <div class="row large-columns-4 medium-columns-3 small-columns-2 row-full-width row-masonry isotope-grid">
                <?php foreach ($images as $image): ?>
                    <div class=" gallery-col col">
                    <div class="col-inner">
                        <a href="<?php echo esc_url($image['watermark_url']); ?>"
                        data-productid="<?php echo esc_attr($first_product_with_media->get_id()); ?>"
                        data-fancybox="products"
                        data-sku="<?php echo esc_attr($image['sku']); ?>"
                        data-caption="<?php echo esc_attr($first_product_with_media->get_name()); ?>"
                        aria-label="<?php echo esc_attr($first_product_with_media->get_name()); ?>"
                        data-attachment-id="<?php echo esc_attr($image['attachment_id']); ?>"
                        data-watermark-id="<?php echo esc_attr($image['watermark_id']); ?>"
                        data-watermark-url="<?php echo esc_attr($image['watermark_url']); ?>"

                         >
                        <img src="<?php echo esc_url($image['watermark_url']); ?>" 
                             alt="<?php echo esc_attr($image['sku']); ?>"
                             class="wc-pmm-watermarked-image" />
                        </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Loading indicator -->
            <div id="wc-pmm-loading" class="wc-pmm-loading" style="display: none;">
                <p><?php _e('Loading more images...', 'wc-product-media-manager'); ?></p>
            </div>
            
            <!-- No more images indicator -->
            <div id="wc-pmm-no-more" class="wc-pmm-no-more" style="display: none;">
                <p><?php _e('No more images to load.', 'wc-product-media-manager'); ?></p>
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
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        .wc-pmm-product-link {
            color: rgba(30, 30, 30, 0.6);
            font-weight: bold;
            padding: 0 !important;
            transition: all 0.3s ease;
            cursor: pointer;
            font-family: "inter";
            font-size: 11px;
        }
        .wc-pmm-product-link:hover {
            color: #000;
        }
        .wc-pmm-product-link.active {
            color: #000;
            font-weight:bold;
        }
        .wc-pmm-external-link {
            color: #666;
            text-decoration: none;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }
        .wc-pmm-external-link:hover {
            color: #0073aa;
            border-color: #0073aa;
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
        .wc-pmm-loading {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        .wc-pmm-no-more {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let loading = false;
            let noMore = false;
            const productId = <?php echo $first_product_with_media->get_id(); ?>;
            const categorySlug = '<?php echo esc_js($category_slug); ?>';
            const imagesGrid = $('.row-masonry');
            const loadingIndicator = $('#wc-pmm-loading');
            const noMoreIndicator = $('#wc-pmm-no-more');
            
            // Initialize Isotope on page load
            function initializeIsotope() {
                setTimeout(function() {
                    if (typeof imagesGrid.isotope === 'function') {
                        imagesGrid.isotope({
                            itemSelector: '.gallery-col',
                            layoutMode: 'masonry',
                            masonry: {
                                columnWidth: '.gallery-col'
                            }
                        });
                    }
                }, 100);
            }
            
            // Initialize Isotope immediately for existing images
            initializeIsotope();
            
            // Check if total images are more than 15 to enable infinite scroll
            const totalImages = <?php echo $media_response['total']; ?>;
            if (totalImages <= 15) {
                return; // No need for infinite scroll
            }
            
            // Function to append items to Isotope layout
            function appendToIsotope(newElements) {
                // Reinitialize Fancybox if it exists
                if (typeof $.fancybox !== 'undefined') {
                    $('[data-fancybox="products"]').fancybox();
                }
                
                // Append items to Isotope layout
                setTimeout(function() {
                    if (typeof imagesGrid.isotope === 'function') {
                        // Append to DOM first, then to Isotope
                        newElements.forEach(function(element) {
                            imagesGrid.append(element);
                        });
                        imagesGrid.isotope('appended', newElements);
                    }
                }, 200);
            }
            
            // Function to load product images
            function loadProductImages(productId, productName) {
                // Reset state
                currentPage = 1;
                loading = false;
                noMore = false;
                
                // Clear current images
                imagesGrid.empty();
                
                // Show loading
                loadingIndicator.show();
                noMoreIndicator.hide();
                
                // Update "showing from" text
                $('.wc-pmm-showing-from strong').text(productName);
                
                $.ajax({
                    url: wc_pmm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_pmm_load_more_images',
                        nonce: wc_pmm_ajax.nonce,
                        product_id: productId,
                        page: 1,
                        per_page: 15
                    },
                    success: function(response) {
                        if (response.success && response.data.images.length > 0) {
                            const images = response.data.images;
                            
                            let newElements = [];
                            images.forEach(function(image) {
                                const newElement = $(`
                                    <div class="gallery-col col">
                                        <div class="col-inner">
                                            <a href="${image.watermark_url}"
                                               data-productid="${productId}"
                                               data-fancybox="products"
                                               data-sku="${image.sku}"
                                               data-caption="${productName}"
                                               data-attachment-id = "${image.attachment_id}"
                                               data-watermark-id = "${image.watermark_id}"
                                               data-watermark-url = "${image.watermark_url}"
                                               aria-label="${productName}">
                                                <img src="${image.watermark_url}" 
                                                     alt="${image.sku}"
                                                     class="wc-pmm-watermarked-image" />
                                            </a>
                                        </div>
                                    </div>
                                `);
                                newElements.push(newElement[0]);
                                imagesGrid.append(newElement);
                            });
                            
                            // Wait for images to load
                            let imagesLoaded = 0;
                            const totalNewImages = images.length;
                            
                            $(newElements).find('img').each(function() {
                                const img = new Image();
                                img.onload = function() {
                                    imagesLoaded++;
                                    if (imagesLoaded === totalNewImages) {
                                        // Reinitialize Isotope layout for new images
                                        if (typeof imagesGrid.isotope === 'function') {
                                            imagesGrid.isotope('reloadItems').isotope();
                                        }
                                    }
                                };
                                img.src = this.src;
                            });
                            
                            // Check if we need to enable infinite scroll for this product
                            if (response.data.total > 15) {
                                // Enable infinite scroll for this product
                            } else {
                                noMore = true;
                            }
                        }
                        
                        loadingIndicator.hide();
                    },
                    error: function() {
                        loadingIndicator.hide();
                        console.log('Error loading product images');
                    }
                });
            }
            
            // Product click handler
            $('.wc-pmm-product-link').on('click', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const productId = button.data('product-id');
                const productName = button.data('product-name');
                
                // Update active state
                $('.wc-pmm-product-link').removeClass('active');
                button.addClass('active');
                
                // Load product images
                loadProductImages(productId, productName);
            });
            
            function loadMoreImages() {
                if (loading || noMore) return;
                
                // Get current active product
                const activeButton = $('.wc-pmm-product-link.active');
                const currentProductId = activeButton.data('product-id');
                const currentProductName = activeButton.data('product-name');
                
                if (!currentProductId) return;
                
                loading = true;
                loadingIndicator.show();
                
                $.ajax({
                    url: wc_pmm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_pmm_load_more_images',
                        nonce: wc_pmm_ajax.nonce,
                        product_id: currentProductId,
                        page: currentPage + 1,
                        per_page: 15
                    },
                    success: function(response) {
                        if (response.success && response.data.images.length > 0) {
                            const images = response.data.images;
                            
                            let newElements = [];
                            images.forEach(function(image) {
                                const newElement = $(`
                                    <div class="gallery-col col">
                                        <div class="col-inner">
                                            <a href="${image.watermark_url}"
                                               data-productid="${currentProductId}"
                                               data-fancybox="products"
                                               data-attachment-id = "${image.attachment_id}"
                                               data-watermark-id = "${image.watermark_id}"
                                               data-watermark-url = "${image.watermark_url}"
                                               data-sku="${image.sku}"
                                               data-caption="${currentProductName}"
                                               aria-label="${currentProductName}">
                                                <img src="${image.watermark_url}" 
                                                     alt="${image.sku}"
                                                     class="wc-pmm-watermarked-image" />
                                            </a>
                                        </div>
                                    </div>
                                `);
                                newElements.push(newElement[0]);
                            });
                            
                            // Wait for images to load before appending to layout
                            let imagesLoaded = 0;
                            const totalNewImages = images.length;
                            
                            $(newElements).find('img').each(function() {
                                const img = new Image();
                                img.onload = function() {
                                    imagesLoaded++;
                                    if (imagesLoaded === totalNewImages) {
                                        // All images loaded, now append to layout
                                        appendToIsotope(newElements);
                                    }
                                };
                                img.src = this.src;
                            });
                            
                            currentPage++;
                            
                            // Check if no more images
                            if (images.length < 15) {
                                noMore = true;
                                noMoreIndicator.show();
                            }
                            
                        } else {
                            noMore = true;
                            noMoreIndicator.show();
                        }
                        
                        loading = false;
                        loadingIndicator.hide();
                    },
                    error: function() {
                        loading = false;
                        loadingIndicator.hide();
                        console.log('Error loading more images');
                    }
                });
            }
            
            // Infinite scroll trigger
            $(window).scroll(function() {
                if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
                    loadMoreImages();
                }
            });
        });
        </script>
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

    /**
     * Shortcode: [wc_pmm_product_gallery id="123" per_page="15"]
     * Renders watermarked media for a single product.
     */
    public function product_gallery_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'per_page' => 13,
            ),
            $atts,
            'wc_pmm_product_gallery'
        );

        $product_id = intval($atts['id']);
        $per_page = max(1, intval($atts['per_page']));

        if (!$product_id) {
            return '<p>' . esc_html__('Please provide a valid product ID using id="...".', 'wc-product-media-manager') . '</p>';
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return '<p>' . esc_html__('Product not found.', 'wc-product-media-manager') . '</p>';
        }

        $media_response = $this->get_product_media_for_display($product_id, 1, $per_page);
        $images = $media_response['images'];

        if (empty($images)) {
            return '<div class="wc-pmm-no-products"><p>' . esc_html__('No watermarked images found for this product.', 'wc-product-media-manager') . '</p></div>';
        }

        $instance_id = 'wc-pmm-pg-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="wc-pmm-product-gallery">
            <!-- <div class="wc-pmm-product-header">
                <div class="wc-pmm-showing-from">
                    <?php _e('Showing images from:', 'wc-product-media-manager'); ?>
                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                </div>
            </div> -->

            <div class="row large-columns-4 medium-columns-3 small-columns-2 row-full-width grid_photography ">
                <?php foreach ($images as $image): ?>
                    <div class="gallery-col col">
                        <div class="col-inner">
                            <a href="<?php echo esc_url($image['watermark_url']); ?>"
                               data-productid="<?php echo esc_attr($product_id); ?>"
                               data-fancybox="products"
                               data-sku="<?php echo esc_attr($image['sku']); ?>"
                               data-caption="<?php echo esc_attr($product->get_name()); ?>"
                               aria-label="<?php echo esc_attr($product->get_name()); ?>"
                               data-attachment-id="<?php echo esc_attr($image['attachment_id']); ?>"
                               data-watermark-id="<?php echo esc_attr($image['watermark_id']); ?>"
                               data-watermark-url="<?php echo esc_attr($image['watermark_url']); ?>">
                                <img src="<?php echo esc_url($image['watermark_url']); ?>"
                                     alt="<?php echo esc_attr($image['sku']); ?>"
                                     class="wc-pmm-watermarked-image" />

                            </a>
                            <div class="wc-pmm-image-overlay">
                                <div class="wc-pmm-image-overlay-inner">
                                    <a class="wc-pmm-add-to-cart-button">
                                        <img src="<?php echo esc_url(WC_PMM_PLUGIN_URL . '/assets/images/bag.svg'); ?>" alt="Zoom" class="wc-pmm-image-overlay-icon">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="<?php echo esc_attr($instance_id); ?>-loading" class="wc-pmm-loading" style="display: none;">
                <p><?php _e('Loading more images...', 'wc-product-media-manager'); ?></p>
            </div>
            <div id="<?php echo esc_attr($instance_id); ?>-no-more" class="wc-pmm-no-more" style="display: none;">
                <p><?php _e('No more images to load.', 'wc-product-media-manager'); ?></p>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const container = $('#<?php echo esc_js($instance_id); ?>');
            const imagesGrid = container.find('.row-masonry');
            const loadingIndicator = $('#<?php echo esc_js($instance_id); ?>-loading');
            const noMoreIndicator = $('#<?php echo esc_js($instance_id); ?>-no-more');
            const productId = <?php echo json_encode($product_id); ?>;
            const perPage = <?php echo json_encode($per_page); ?>;
            let currentPage = 1;
            let loading = false;
            let noMore = false;

            function initializeIsotope() {
                setTimeout(function() {
                    if (typeof imagesGrid.isotope === 'function') {
                        imagesGrid.isotope({
                            itemSelector: '.gallery-col',
                            layoutMode: 'masonry',
                            masonry: { columnWidth: '.gallery-col' }
                        });
                    }
                }, 100);
            }

            initializeIsotope();

            if (<?php echo (int) $media_response['total']; ?> <= perPage) {
                noMore = true;
                return;
            }

            function appendToIsotope(newElements) {
                if (typeof $.fancybox !== 'undefined') {
                    $('[data-fancybox="products"]').fancybox();
                }
                setTimeout(function() {
                    if (typeof imagesGrid.isotope === 'function') {
                        newElements.forEach(function(element) { imagesGrid.append(element); });
                        imagesGrid.isotope('appended', newElements);
                    }
                }, 200);
            }

            function loadMoreImages() {
                if (loading || noMore) return;
                loading = true;
                loadingIndicator.show();

                $.ajax({
                    url: wc_pmm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_pmm_load_more_images',
                        nonce: wc_pmm_ajax.nonce,
                        product_id: productId,
                        page: currentPage + 1,
                        per_page: perPage
                    },
                    success: function(response) {
                        if (response.success && response.data.images.length > 0) {
                            const images = response.data.images;
                            let newElements = [];
                            images.forEach(function(image) {
                                const newElement = $(
                                    '<div class="gallery-col col">' +
                                        '<div class="col-inner">' +
                                            '<a href="' + image.watermark_url + '" ' +
                                               'data-productid="' + productId + '" ' +
                                               'data-fancybox="products" ' +
                                               'data-attachment-id="' + image.attachment_id + '" ' +
                                               'data-watermark-id="' + image.watermark_id + '" ' +
                                               'data-watermark-url="' + image.watermark_url + '" ' +
                                               'data-sku="' + image.sku + '">' +
                                                '<img src="' + image.watermark_url + '" alt="' + image.sku + '" class="wc-pmm-watermarked-image" />' +
                                            '</a>' +
                                        '</div>' +
                                    '</div>'
                                );
                                newElements.push(newElement[0]);
                            });

                            // Wait for images to load before appending
                            let imagesLoaded = 0;
                            const totalNewImages = images.length;
                            $(newElements).find('img').each(function() {
                                const img = new Image();
                                img.onload = function() {
                                    imagesLoaded++;
                                    if (imagesLoaded === totalNewImages) {
                                        appendToIsotope(newElements);
                                    }
                                };
                                img.src = this.src;
                            });

                            currentPage++;
                            if (images.length < perPage) {
                                noMore = true;
                                noMoreIndicator.show();
                            }
                        } else {
                            noMore = true;
                            noMoreIndicator.show();
                        }
                        loading = false;
                        loadingIndicator.hide();
                    },
                    error: function() {
                        loading = false;
                        loadingIndicator.hide();
                        // eslint-disable-next-line no-console
                        console.log('Error loading more images');
                    }
                });
            }

            $(window).on('scroll', function() {
                if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
                    loadMoreImages();
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }
    
    /**
     * AJAX handler for loading more images
     */
    public function ajax_load_more_images() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $page = intval($_POST['page']);
        $per_page = intval($_POST['per_page']);
        
        if (!$product_id || !$page || !$per_page) {
            wp_send_json_error('Invalid parameters');
        }
        
        $media_response = $this->get_product_media_for_display($product_id, $page, $per_page);
        
        if (empty($media_response['images'])) {
            wp_send_json_error('No more images');
        }
        
        wp_send_json_success($media_response);
    }
}
