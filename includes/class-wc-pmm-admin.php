<?php
/**
 * Admin functionality for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_product_metaboxes'));
        add_action('save_post', array($this, 'save_product_media'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add metaboxes to product edit screen
     */
    public function add_product_metaboxes() {
        add_meta_box(
            'wc_pmm_media_manager',
            __('Product Media Manager', 'wc-product-media-manager'),
            array($this, 'render_media_metabox'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Render the media manager metabox
     */
    public function render_media_metabox($post) {
        wp_nonce_field('wc_pmm_save_media', 'wc_pmm_media_nonce');
        
        // Get existing media for this product
        $product_media = get_post_meta($post->ID, '_wc_pmm_product_media', true);
        if (!is_array($product_media)) {
            $product_media = array();
        }
        
        ?>
        <div id="wc-pmm-container" class="wc-pmm-container">
            <!-- Drag and Drop Upload Area -->
            <div class="wc-pmm-upload-section">
                <h4><?php _e('Add Product Images', 'wc-product-media-manager'); ?></h4>
                <div id="wc-pmm-drop-zone" class="wc-pmm-drop-zone">
                    <div class="wc-pmm-drop-zone-content">
                        <div class="wc-pmm-upload-icon">
                            <span class="dashicons dashicons-cloud-upload"></span>
                        </div>
                        <p class="wc-pmm-drop-text">
                            <?php _e('Drag & Drop Images Here or Click to Select', 'wc-product-media-manager'); ?>
                        </p>
                        <button type="button" id="wc-pmm-select-images" class="button button-secondary">
                            <?php _e('Select Images from Media Library', 'wc-product-media-manager'); ?>
                        </button>
                    </div>
                    <input type="file" id="wc-pmm-file-input" multiple accept="image/*" style="display: none;">
                </div>
                
                <!-- Progress Bar -->
                <div id="wc-pmm-progress" class="wc-pmm-progress" style="display: none;">
                    <div class="wc-pmm-progress-bar">
                        <div class="wc-pmm-progress-fill"></div>
                    </div>
                    <span class="wc-pmm-progress-text">0%</span>
                </div>
            </div>
            
            <!-- Media Table -->
            <div class="wc-pmm-media-section">
                <div class="wc-pmm-media-header">
                    
                    <h4><?php _e('Product Media Gallery', 'wc-product-media-manager'); ?></h4>
                    <div class="wc-pmm-media-header-buttons">
                    <button type="button" id="wc-pmm-bulk-watermark" class="button button-secondary">
                        <?php _e('Generate All Watermarks', 'wc-product-media-manager'); ?>
                    </button>
                    <button type="button" id="wc-pmm-clear-all" class="button button-secondary" style="margin-left:8px;">
                        <?php _e('Clear All Images', 'wc-product-media-manager'); ?>
                    </button>
                    </div>
                </div>
                <div class="wc-pmm-table-container">
                    <table id="wc-pmm-media-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="wc-pmm-col-sort"><?php _e('Order', 'wc-product-media-manager'); ?></th>
                                <th class="wc-pmm-col-original"><?php _e('Original Image', 'wc-product-media-manager'); ?></th>
                                <th class="wc-pmm-col-watermark"><?php _e('Watermarked Image', 'wc-product-media-manager'); ?></th>
                                <th class="wc-pmm-col-sku"><?php _e('Image SKU', 'wc-product-media-manager'); ?></th>
                                <th class="wc-pmm-col-actions"><?php _e('Actions', 'wc-product-media-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="wc-pmm-media-tbody">
                            <?php if (!empty($product_media)): ?>
                                <?php foreach ($product_media as $index => $media): ?>
                                    <?php $this->render_media_row($media, $index); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="wc-pmm-no-media">
                                    <td colspan="5" class="wc-pmm-no-media-text">
                                        <?php _e('No images added yet. Use the upload area above to add images.', 'wc-product-media-manager'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Hidden input to store media data -->
            <input type="hidden" id="wc-pmm-media-data" name="wc_pmm_media_data" value="<?php echo esc_attr(json_encode($product_media)); ?>">
        </div>
        
        <!-- Media Row Template -->
        <script type="text/html" id="wc-pmm-row-template">
            <tr class="wc-pmm-media-row" data-index="{{index}}">
                <td class="wc-pmm-sort-handle">
                    <span class="dashicons dashicons-menu"></span>
                    <span class="wc-pmm-order-number">{{order}}</span>
                </td>
                <td class="wc-pmm-original-image">
                    <div class="wc-pmm-image-preview">
                        <img src="{{original_url}}" alt="Original" class="wc-pmm-thumbnail">
                        <div class="wc-pmm-image-info">
                            <span class="wc-pmm-filename">{{filename}}</span>
                            <span class="wc-pmm-filesize">{{filesize}}</span>
                        </div>
                    </div>
                </td>
                <td class="wc-pmm-watermark-image">
                    <div class="wc-pmm-image-preview">
                        {{#if watermark_url}}
                            <img src="{{watermark_url}}" alt="Watermarked" class="wc-pmm-thumbnail">
                            <span class="wc-pmm-status wc-pmm-status-ready"><?php _e('Ready', 'wc-product-media-manager'); ?></span>
                        {{else}}
                            <div class="wc-pmm-watermark-placeholder">
                                <span class="dashicons dashicons-image-filter"></span>
                                <button type="button" class="button button-small wc-pmm-generate-watermark" data-index="{{index}}">
                                    <?php _e('Generate Watermark', 'wc-product-media-manager'); ?>
                                </button>
                            </div>
                        {{/if}}
                    </div>
                </td>
                <td class="wc-pmm-image-sku">
                    <input type="text" class="wc-pmm-sku-input" value="{{sku}}" placeholder="<?php _e('Enter SKU', 'wc-product-media-manager'); ?>">
                </td>
                <td class="wc-pmm-actions">
                    <button type="button" class="button button-small wc-pmm-delete-image" data-index="{{index}}" title="<?php _e('Delete Image', 'wc-product-media-manager'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        </script>
        <?php
    }
    
    /**
     * Render a single media row
     */
    private function render_media_row($media, $index) {
        $order = $index + 1;
        $original_url = wp_get_attachment_image_url($media['attachment_id'], 'thumbnail');
        $watermark_url = !empty($media['watermark_id']) ? wp_get_attachment_image_url($media['watermark_id'], 'thumbnail') : '';
        $attachment = get_post($media['attachment_id']);
        $filename = $attachment ? $attachment->post_title : '';
        $filesize = size_format(filesize(get_attached_file($media['attachment_id'])));
        $sku = isset($media['sku']) ? $media['sku'] : '';
        ?>
        <tr class="wc-pmm-media-row" data-index="<?php echo esc_attr($index); ?>">
            <td class="wc-pmm-sort-handle">
                <span class="dashicons dashicons-menu"></span>
                <span class="wc-pmm-order-number"><?php echo esc_html($order); ?></span>
            </td>
            <td class="wc-pmm-original-image">
                <div class="wc-pmm-image-preview">
                    <img src="<?php echo esc_url($original_url); ?>" alt="Original" class="wc-pmm-thumbnail">
                    <div class="wc-pmm-image-info">
                        <span class="wc-pmm-filename"><?php echo esc_html($filename); ?></span>
                        <span class="wc-pmm-filesize"><?php echo esc_html($filesize); ?></span>
                    </div>
                </div>
            </td>
            <td class="wc-pmm-watermark-image">
                <div class="wc-pmm-image-preview">
                    <?php if ($watermark_url): ?>
                        <img src="<?php echo esc_url($watermark_url); ?>" alt="Watermarked" class="wc-pmm-thumbnail">
                        <span class="wc-pmm-status wc-pmm-status-ready"><?php _e('Ready', 'wc-product-media-manager'); ?></span>
                    <?php else: ?>
                        <div class="wc-pmm-watermark-placeholder">
                            <span class="dashicons dashicons-image-filter"></span>
                            <button type="button" class="button button-small wc-pmm-generate-watermark" data-index="<?php echo esc_attr($index); ?>">
                                <?php _e('Generate Watermark', 'wc-product-media-manager'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="wc-pmm-image-sku">
                <input type="text" class="wc-pmm-sku-input" value="<?php echo esc_attr($sku ?: $filename); ?>" placeholder="<?php _e('Enter SKU', 'wc-product-media-manager'); ?>">
            </td>
            <td class="wc-pmm-actions">
                <button type="button" class="button button-small wc-pmm-delete-image" data-index="<?php echo esc_attr($index); ?>" title="<?php _e('Delete Image', 'wc-product-media-manager'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save product media data
     */
    public function save_product_media($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['wc_pmm_media_nonce']) || !wp_verify_nonce($_POST['wc_pmm_media_nonce'], 'wc_pmm_save_media')) {
            return;
        }
        
        // Check if this is a product
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        // Save media data
        if (isset($_POST['wc_pmm_media_data'])) {
            $media_data = json_decode(stripslashes($_POST['wc_pmm_media_data']), true);
            if (is_array($media_data)) {
                update_post_meta($post_id, '_wc_pmm_product_media', $media_data);
            }
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Display any admin notices if needed
    }
}