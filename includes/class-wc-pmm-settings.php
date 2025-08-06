<?php
/**
 * Settings page for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_wc_pmm_save_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Add settings page to WooCommerce menu
     */
    public function add_settings_page() {
        $hook = add_submenu_page(
            'woocommerce',
            __('Product Media Manager Settings', 'wc-product-media-manager'),
            __('Media Manager', 'wc-product-media-manager'),
            'manage_woocommerce',
            'wc-pmm-settings',
            array($this, 'render_settings_page')
        );
        
        // Enqueue media scripts on this page
        add_action('admin_print_scripts-' . $hook, array($this, 'enqueue_media_scripts'));
    }
    
    /**
     * Enqueue media scripts
     */
    public function enqueue_media_scripts() {
        wp_enqueue_media();
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Settings will be saved via AJAX to the custom database table
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get current settings with defaults
        $all_settings = WC_PMM_Database::get_all_watermark_settings();
        
        // Provide defaults for all settings
        $settings = wp_parse_args($all_settings, array(
            'watermark_enabled' => '1',
            'watermark_type' => 'text',
            'watermark_position' => 'bottom-right',
            'watermark_opacity' => '70',
            'watermark_size' => '20',
            'watermark_text' => get_bloginfo('name'),
            'watermark_font_size' => '12',
            'watermark_font_color' => '#ffffff',
            'watermark_background_color' => '#000000',
            'watermark_padding' => '10',
            'watermark_quality' => '90',
            'watermark_image_id' => '',
            'watermark_image_scale' => '25'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Product Media Manager Settings', 'wc-product-media-manager'); ?></h1>
            
            <form id="wc-pmm-settings-form" method="post">
                <?php wp_nonce_field('wc_pmm_save_settings', 'wc_pmm_settings_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="watermark_enabled"><?php _e('Enable Watermarks', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="watermark_enabled" name="watermark_enabled" value="1" <?php checked($settings['watermark_enabled'], '1'); ?>>
                                <p class="description"><?php _e('Enable automatic watermarking of product images.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="watermark_type"><?php _e('Watermark Type', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <select id="watermark_type" name="watermark_type">
                                    <option value="text" <?php selected($settings['watermark_type'], 'text'); ?>><?php _e('Text Watermark', 'wc-product-media-manager'); ?></option>
                                    <option value="image" <?php selected($settings['watermark_type'], 'image'); ?>><?php _e('Image Watermark', 'wc-product-media-manager'); ?></option>
                                </select>
                                <p class="description"><?php _e('Choose between text or image watermarks.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Text Watermark Settings -->
                        <tr class="watermark-text-setting">
                            <th scope="row">
                                <label for="watermark_text"><?php _e('Watermark Text', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="watermark_text" name="watermark_text" value="<?php echo esc_attr($settings['watermark_text']); ?>" class="regular-text">
                                <p class="description"><?php _e('Text to display as watermark.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="watermark-text-setting">
                            <th scope="row">
                                <label for="watermark_font_size"><?php _e('Font Size', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="watermark_font_size" name="watermark_font_size" value="<?php echo esc_attr($settings['watermark_font_size']); ?>" min="8" max="72">
                                <p class="description"><?php _e('Font size for text watermark.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="watermark-text-setting">
                            <th scope="row">
                                <label for="watermark_font_color"><?php _e('Font Color', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="watermark_font_color" name="watermark_font_color" value="<?php echo esc_attr($settings['watermark_font_color']); ?>">
                                <p class="description"><?php _e('Color of the watermark text.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="watermark-text-setting">
                            <th scope="row">
                                <label for="watermark_background_color"><?php _e('Background Color', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="watermark_background_color" name="watermark_background_color" value="<?php echo esc_attr($settings['watermark_background_color']); ?>">
                                <p class="description"><?php _e('Background color behind the text.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Image Watermark Settings -->
                        <tr class="watermark-image-setting">
                            <th scope="row">
                                <label for="watermark_image_id"><?php _e('Watermark Image', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <div class="watermark-image-selector">
                                    <input type="hidden" id="watermark_image_id" name="watermark_image_id" value="<?php echo esc_attr($settings['watermark_image_id']); ?>">
                                    <div class="watermark-image-preview">
                                        <?php if (!empty($settings['watermark_image_id'])): ?>
                                            <?php $image_url = wp_get_attachment_image_url($settings['watermark_image_id'], 'thumbnail'); ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="Watermark Preview" style="max-width: 150px; max-height: 150px;">
                                        <?php else: ?>
                                            <div class="no-image"><?php _e('No image selected', 'wc-product-media-manager'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="select-watermark-image" class="button"><?php _e('Select Image', 'wc-product-media-manager'); ?></button>
                                    <button type="button" id="remove-watermark-image" class="button" style="<?php echo empty($settings['watermark_image_id']) ? 'display:none;' : ''; ?>"><?php _e('Remove', 'wc-product-media-manager'); ?></button>
                                </div>
                                <p class="description"><?php _e('Select an image to use as watermark. PNG images with transparency work best.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="watermark-image-setting">
                            <th scope="row">
                                <label for="watermark_image_scale"><?php _e('Image Scale (%)', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="watermark_image_scale" name="watermark_image_scale" value="<?php echo esc_attr($settings['watermark_image_scale']); ?>" min="5" max="100">
                                <p class="description"><?php _e('Size of watermark image as percentage of main image width.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Common Settings -->
                        <tr>
                            <th scope="row">
                                <label for="watermark_position"><?php _e('Position', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <select id="watermark_position" name="watermark_position">
                                    <option value="top-left" <?php selected($settings['watermark_position'], 'top-left'); ?>><?php _e('Top Left', 'wc-product-media-manager'); ?></option>
                                    <option value="top-right" <?php selected($settings['watermark_position'], 'top-right'); ?>><?php _e('Top Right', 'wc-product-media-manager'); ?></option>
                                    <option value="bottom-left" <?php selected($settings['watermark_position'], 'bottom-left'); ?>><?php _e('Bottom Left', 'wc-product-media-manager'); ?></option>
                                    <option value="bottom-right" <?php selected($settings['watermark_position'], 'bottom-right'); ?>><?php _e('Bottom Right', 'wc-product-media-manager'); ?></option>
                                    <option value="center" <?php selected($settings['watermark_position'], 'center'); ?>><?php _e('Center', 'wc-product-media-manager'); ?></option>
                                </select>
                                <p class="description"><?php _e('Position of the watermark on the image.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="watermark_opacity"><?php _e('Opacity (%)', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="watermark_opacity" name="watermark_opacity" value="<?php echo esc_attr($settings['watermark_opacity']); ?>" min="10" max="100">
                                <p class="description"><?php _e('Transparency of the watermark (100 = fully opaque).', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="watermark_padding"><?php _e('Padding (px)', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="watermark_padding" name="watermark_padding" value="<?php echo esc_attr($settings['watermark_padding']); ?>" min="0" max="100">
                                <p class="description"><?php _e('Distance from image edge in pixels.', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="watermark_quality"><?php _e('Image Quality (%)', 'wc-product-media-manager'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="watermark_quality" name="watermark_quality" value="<?php echo esc_attr($settings['watermark_quality']); ?>" min="50" max="100">
                                <p class="description"><?php _e('Quality of the watermarked image (higher = better quality, larger file).', 'wc-product-media-manager'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('Save Settings', 'wc-product-media-manager')); ?>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle settings based on watermark type
            function toggleWatermarkSettings() {
                var type = $('#watermark_type').val();
                if (type === 'image') {
                    $('.watermark-text-setting').hide();
                    $('.watermark-image-setting').show();
                } else {
                    $('.watermark-text-setting').show();
                    $('.watermark-image-setting').hide();
                }
            }
            
            $('#watermark_type').on('change', toggleWatermarkSettings);
            toggleWatermarkSettings(); // Initial toggle
            
            // Media uploader for watermark image
            var mediaUploader;
            
            $('#select-watermark-image').on('click', function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: '<?php _e('Select Watermark Image', 'wc-product-media-manager'); ?>',
                    button: {
                        text: '<?php _e('Use this image', 'wc-product-media-manager'); ?>'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#watermark_image_id').val(attachment.id);
                    $('.watermark-image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" alt="Watermark Preview" style="max-width: 150px; max-height: 150px;">');
                    $('#remove-watermark-image').show();
                });
                
                mediaUploader.open();
            });
            
            $('#remove-watermark-image').on('click', function(e) {
                e.preventDefault();
                $('#watermark_image_id').val('');
                $('.watermark-image-preview').html('<div class="no-image"><?php _e('No image selected', 'wc-product-media-manager'); ?></div>');
                $(this).hide();
            });
            
            // Save settings via AJAX
            $('#wc-pmm-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=wc_pmm_save_settings';
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>').insertAfter('h1');
                        } else {
                            $('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>').insertAfter('h1');
                        }
                        
                        // Auto-hide notice after 3 seconds
                        setTimeout(function() {
                            $('.notice').fadeOut();
                        }, 3000);
                    }
                });
            });
        });
        </script>
        
        <style>
        .watermark-image-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .watermark-image-preview {
            border: 1px solid #ddd;
            padding: 10px;
            min-width: 150px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
        }
        
        .no-image {
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        // Check nonce
        if (!wp_verify_nonce($_POST['wc_pmm_settings_nonce'], 'wc_pmm_save_settings')) {
            wp_send_json_error(__('Security check failed.', 'wc-product-media-manager'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to save settings.', 'wc-product-media-manager'));
        }
        
        // Save settings
        $settings = array(
            'watermark_enabled' => isset($_POST['watermark_enabled']) ? '1' : '0',
            'watermark_type' => sanitize_text_field($_POST['watermark_type']),
            'watermark_text' => sanitize_text_field($_POST['watermark_text']),
            'watermark_font_size' => intval($_POST['watermark_font_size']),
            'watermark_font_color' => sanitize_hex_color($_POST['watermark_font_color']),
            'watermark_background_color' => sanitize_hex_color($_POST['watermark_background_color']),
            'watermark_image_id' => intval($_POST['watermark_image_id']),
            'watermark_image_scale' => intval($_POST['watermark_image_scale']),
            'watermark_position' => sanitize_text_field($_POST['watermark_position']),
            'watermark_opacity' => intval($_POST['watermark_opacity']),
            'watermark_padding' => intval($_POST['watermark_padding']),
            'watermark_quality' => intval($_POST['watermark_quality'])
        );
        
        foreach ($settings as $name => $value) {
            WC_PMM_Database::update_watermark_setting($name, $value);
        }
        
        wp_send_json_success(__('Settings saved successfully!', 'wc-product-media-manager'));
    }
}