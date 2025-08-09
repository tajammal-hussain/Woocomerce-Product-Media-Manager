<?php
/**
 * AJAX functionality for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX hooks for logged-in users
        add_action('wp_ajax_wc_pmm_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_wc_pmm_delete_image', array($this, 'ajax_delete_image'));
        add_action('wp_ajax_wc_pmm_update_media_order', array($this, 'ajax_update_media_order'));
        add_action('wp_ajax_wc_pmm_generate_watermark', array($this, 'ajax_generate_watermark'));
        add_action('wp_ajax_wc_pmm_update_image_sku', array($this, 'ajax_update_image_sku'));
        add_action('wp_ajax_wc_pmm_get_media_library', array($this, 'ajax_get_media_library'));
        add_action('wp_ajax_wc_pmm_add_to_cart_with_image', array($this, 'ajax_add_to_cart_with_image'));
        add_action('wp_ajax_wc_pmm_clear_all_images', array($this, 'ajax_clear_all_images'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_custom_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_custom_data'), 10, 4);
    }
    
    /**
     * Handle image upload via AJAX
     */
    public function ajax_upload_image() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to upload files.', 'wc-product-media-manager'));
        }
        
        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded.', 'wc-product-media-manager'));
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Invalid file type. Only JPEG, PNG, and GIF images are allowed.', 'wc-product-media-manager'));
        }
        
        // Check file size (default WordPress limit)
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            wp_send_json_error(sprintf(__('File is too large. Maximum size is %s.', 'wc-product-media-manager'), size_format($max_size)));
        }
        
        // Handle the upload
        $upload_overrides = array(
            'test_form' => false,
            'test_type' => true,
        );
        
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'guid' => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title' => sanitize_file_name(pathinfo($movefile['file'], PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attachment_id)) {
                // Generate attachment metadata
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                // Get image info
                $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                $image_full_url = wp_get_attachment_url($attachment_id);
                $attachment_post = get_post($attachment_id);
                $filename = $attachment_post->post_title;
                $filesize = size_format(filesize($movefile['file']));
                
                wp_send_json_success(array(
                    'attachment_id' => $attachment_id,
                    'url' => $image_url,
                    'full_url' => $image_full_url,
                    'filename' => $filename,
                    'filesize' => $filesize
                ));
            } else {
                wp_send_json_error(__('Failed to create attachment.', 'wc-product-media-manager'));
            }
        } else {
            wp_send_json_error($movefile['error']);
        }
    }
    
    /**
     * Handle image deletion via AJAX
     */
    public function ajax_delete_image() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('You do not have permission to delete files.', 'wc-product-media-manager'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $watermark_id = intval($_POST['watermark_id']);
        
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID.', 'wc-product-media-manager'));
        }
        
        // Delete watermarked image if exists
        if ($watermark_id) {
            wp_delete_attachment($watermark_id, true);
        }
        
        // Delete original image (optional - you might want to keep it)
        // wp_delete_attachment($attachment_id, true);
        
        wp_send_json_success(__('Image deleted successfully.', 'wc-product-media-manager'));
    }
    
    /**
     * Update media order via AJAX
     */
    public function ajax_update_media_order() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to edit posts.', 'wc-product-media-manager'));
        }
        
        $product_id = intval($_POST['product_id']);
        $media_order = $_POST['media_order'];
        
        if (!$product_id || !is_array($media_order)) {
            wp_send_json_error(__('Invalid data provided.', 'wc-product-media-manager'));
        }
        
        // Update media order in database
        $result = WC_PMM_Database::save_product_media($product_id, $media_order);
        
        if ($result) {
            wp_send_json_success(__('Media order updated successfully.', 'wc-product-media-manager'));
        } else {
            wp_send_json_error(__('Failed to update media order.', 'wc-product-media-manager'));
        }
    }
    
    /**
     * Generate watermark via AJAX
     */
    public function ajax_generate_watermark() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-product-media-manager'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID.', 'wc-product-media-manager'));
        }
        
        // Generate watermark using the watermark class
        $watermark_generator = new WC_PMM_Watermark();
        $watermark_id = $watermark_generator->generate_watermark($attachment_id);
        
        if (is_wp_error($watermark_id)) {
            wp_send_json_error($watermark_id->get_error_message());
        }
        
        // Return success with watermark info
        $watermark_url = wp_get_attachment_image_url($watermark_id, 'thumbnail');
        $watermark_full_url = wp_get_attachment_url($watermark_id);
        
        wp_send_json_success(array(
            'watermark_id' => $watermark_id,
            'watermark_url' => $watermark_url,
            'watermark_full_url' => $watermark_full_url
        ));
    }
    
    /**
     * Update image SKU via AJAX
     */
    public function ajax_update_image_sku() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to edit posts.', 'wc-product-media-manager'));
        }
        
        $product_id = intval($_POST['product_id']);
        $attachment_id = intval($_POST['attachment_id']);
        $sku = sanitize_text_field($_POST['sku']);
        
        if (!$product_id || !$attachment_id) {
            wp_send_json_error(__('Invalid data provided.', 'wc-product-media-manager'));
        }
        
        // Get current product media
        $product_media = get_post_meta($product_id, '_wc_pmm_product_media', true);
        if (!is_array($product_media)) {
            $product_media = array();
        }
        
        // Update SKU for the specific attachment
        foreach ($product_media as &$media) {
            if ($media['attachment_id'] == $attachment_id) {
                $media['sku'] = $sku;
                break;
            }
        }
        
        // Save updated media data
        update_post_meta($product_id, '_wc_pmm_product_media', $product_media);
        
        wp_send_json_success(__('SKU updated successfully.', 'wc-product-media-manager'));
    }
    
    /**
     * Get media library images via AJAX
     */
    public function ajax_get_media_library() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to access media library.', 'wc-product-media-manager'));
        }
        
        $page = intval($_POST['page']) ?: 1;
        $per_page = 20;
        $search = sanitize_text_field($_POST['search']);
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        if ($search) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        $images = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $attachment_id = get_the_ID();
                
                $images[] = array(
                    'id' => $attachment_id,
                    'title' => get_the_title(),
                    'url' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                    'full_url' => wp_get_attachment_url($attachment_id),
                    'filename' => basename(get_attached_file($attachment_id)),
                    'filesize' => size_format(filesize(get_attached_file($attachment_id)))
                );
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(array(
            'images' => $images,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page
        ));
    }


    /**
 * AJAX handler for adding to cart with specific image
 */
public function ajax_add_to_cart_with_image() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
        wp_die('Security check failed');
    }
    
    $product_id = intval($_POST['product_id']);
    $attachment_id = intval($_POST['attachment_id']);
    $watermark_id = intval($_POST['watermark_id']);
    $watermark_url = sanitize_url($_POST['watermark_url']);
    $sku = sanitize_text_field($_POST['sku']);
    $quantity = intval($_POST['quantity']);
    
    if (!$product_id || !$attachment_id || !$watermark_id) {
        wp_send_json_error('Invalid parameters');
    }
    
    // Create unique cart item data for this image variation
    $cart_item_data = array(
        'wc_pmm_image_variation' => array(
            'attachment_id' => $attachment_id,
            'watermark_id' => $watermark_id,
            'watermark_url' => $watermark_url,
            'sku' => $sku,
            'variation_key' => md5($product_id . '_' . $watermark_id . '_' . $sku)
        )
    );
    
    // Add to cart with custom variation data
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
    
    if ($cart_item_key) {
        WC_AJAX::get_refreshed_fragments();
    } else {
        wp_send_json_error('Failed to add to cart');
    }
}

    /**
     * Clear all product media (and delete watermarked attachments) via AJAX
     */
    public function ajax_clear_all_images() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_pmm_nonce')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-product-media-manager'));
        }

        $product_id = intval($_POST['product_id']);
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID.', 'wc-product-media-manager'));
        }

        $product_media = get_post_meta($product_id, '_wc_pmm_product_media', true);
        if (!is_array($product_media)) {
            $product_media = array();
        }

        // Delete watermarked attachments only (keep original uploads)
        foreach ($product_media as $media) {
            if (!empty($media['watermark_id'])) {
                $watermark_id = intval($media['watermark_id']);
                if ($watermark_id) {
                    wp_delete_attachment($watermark_id, true);
                }
            }
        }

        // Clear meta
        update_post_meta($product_id, '_wc_pmm_product_media', array());

        wp_send_json_success(__('All images cleared.', 'wc-product-media-manager'));
    }

/**
 * Add custom data to cart item to make each image unique
 */
public function add_cart_item_custom_data($cart_item_data, $product_id, $variation_id) {
    return $cart_item_data;
}

/**
 * Display custom image variation data in cart
 */
public function display_cart_item_custom_data($item_data, $cart_item) {
    if (isset($cart_item['wc_pmm_image_variation'])) {
        $image_data = $cart_item['wc_pmm_image_variation'];
        
        // Use watermarked image URL for cart display
        $watermarked_url = $image_data['watermark_url'];
        
        $item_data[] = array(
            'key' => __('Selected Image', 'wc-product-media-manager'),
            'value' => $image_data['sku'],
            'display' => '<img src="' . esc_url($watermarked_url) . '" width="50" height="50" style="border-radius: 3px; margin-right: 10px; vertical-align: middle;"> ' . esc_html($image_data['sku'])
        );
    }
    return $item_data;
}

/**
 * Save custom image variation data to order
 */
public function save_order_item_custom_data($item, $cart_item_key, $values, $order) {
    if (isset($values['wc_pmm_image_variation'])) {
        $image_data = $values['wc_pmm_image_variation'];
        
        // Get the original image URL instead of watermarked URL
        $original_url = wp_get_attachment_url($image_data['attachment_id']);
        
        // Update the image data with original URL for order confirmation
        $image_data['original_url'] = $original_url;
        
        $item->add_meta_data('_wc_pmm_selected_image', $image_data);
        $item->add_meta_data('Selected Image SKU', $image_data['sku']);
        $item->add_meta_data('Selected Image URL', $original_url); // Use original URL instead of watermarked
    }
}
}