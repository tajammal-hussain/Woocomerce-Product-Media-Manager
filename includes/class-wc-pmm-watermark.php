<?php
/**
 * Watermark functionality for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Watermark {
    
    /**
     * Constructor
     */
    public function __construct() {
        // No AJAX hooks here - they are handled in WC_PMM_Ajax class
    }
    
    /**
     * Generate watermark for an image
     */
    public function generate_watermark($attachment_id, $settings = array()) {
        // Get the original image path
        $original_path = get_attached_file($attachment_id);
        
        if (!$original_path || !file_exists($original_path)) {
            return new WP_Error('file_not_found', __('Original image file not found.', 'wc-product-media-manager'));
        }
        
        // Get watermark settings
        $watermark_settings = wp_parse_args($settings, $this->get_default_settings());
        
        // Check if watermarking is enabled
        if (!$watermark_settings['watermark_enabled']) {
            return new WP_Error('watermark_disabled', __('Watermarking is disabled.', 'wc-product-media-manager'));
        }
        
        // Create watermarked image
        $watermarked_path = $this->create_watermarked_image($original_path, $watermark_settings);
        
        if (is_wp_error($watermarked_path)) {
            return $watermarked_path;
        }
        
        // Upload the watermarked image to WordPress media library
        $watermark_attachment_id = $this->upload_watermarked_image($watermarked_path, $attachment_id);
        
        // Clean up temporary file
        if (file_exists($watermarked_path)) {
            unlink($watermarked_path);
        }
        
        return $watermark_attachment_id;
    }
    
    /**
     * Create watermarked image
     */
    private function create_watermarked_image($original_path, $settings) {
        // Get image info
        $image_info = getimagesize($original_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', __('Invalid image file.', 'wc-product-media-manager'));
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        // Create image resource based on type
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($original_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($original_path);
                // Preserve transparency
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($original_path);
                break;
            default:
                return new WP_Error('unsupported_format', __('Unsupported image format.', 'wc-product-media-manager'));
        }
        
        if (!$image) {
            return new WP_Error('image_creation_failed', __('Failed to create image resource.', 'wc-product-media-manager'));
        }
        
        // Add watermark
        $this->add_text_watermark($image, $width, $height, $settings);
        
        // Generate output path
        $path_info = pathinfo($original_path);
        $watermarked_path = $path_info['dirname'] . '/' . $path_info['filename'] . '_watermarked.' . $path_info['extension'];
        
        // Save watermarked image
        $quality = intval($settings['watermark_quality']);
        $saved = false;
        
        switch ($mime_type) {
            case 'image/jpeg':
                $saved = imagejpeg($image, $watermarked_path, $quality);
                break;
            case 'image/png':
                // PNG quality is 0-9, convert from 0-100
                $png_quality = floor((100 - $quality) / 10);
                $saved = imagepng($image, $watermarked_path, $png_quality);
                break;
            case 'image/gif':
                $saved = imagegif($image, $watermarked_path);
                break;
        }
        
        // Clean up
        imagedestroy($image);
        
        if (!$saved) {
            return new WP_Error('save_failed', __('Failed to save watermarked image.', 'wc-product-media-manager'));
        }
        
        return $watermarked_path;
    }
    
    /**
     * Add text watermark to image
     */
    private function add_text_watermark($image, $width, $height, $settings) {
        $text = $settings['watermark_text'];
        $font_size = intval($settings['watermark_font_size']);
        $opacity = intval($settings['watermark_opacity']);
        $padding = intval($settings['watermark_padding']);
        
        // Parse colors
        $font_color = $this->hex_to_rgb($settings['watermark_font_color']);
        $bg_color = $this->hex_to_rgb($settings['watermark_background_color']);
        
        // Calculate text dimensions (approximate)
        $text_width = strlen($text) * ($font_size * 0.6);
        $text_height = $font_size + 4;
        
        // Calculate position based on settings
        $position = $this->calculate_watermark_position($width, $height, $text_width, $text_height, $padding, $settings['watermark_position']);
        
        // Create colors with opacity
        $font_color_alpha = imagecolorallocatealpha($image, $font_color['r'], $font_color['g'], $font_color['b'], 127 - ($opacity * 1.27));
        $bg_color_alpha = imagecolorallocatealpha($image, $bg_color['r'], $bg_color['g'], $bg_color['b'], 127 - ($opacity * 1.27));
        
        // Draw background rectangle
        $bg_x1 = $position['x'] - $padding;
        $bg_y1 = $position['y'] - $text_height - $padding;
        $bg_x2 = $position['x'] + $text_width + $padding;
        $bg_y2 = $position['y'] + $padding;
        
        imagefilledrectangle($image, $bg_x1, $bg_y1, $bg_x2, $bg_y2, $bg_color_alpha);
        
        // Add text (using built-in font since TTF fonts may not be available)
        imagestring($image, 5, $position['x'], $position['y'] - $text_height, $text, $font_color_alpha);
    }
    
    /**
     * Calculate watermark position
     */
    private function calculate_watermark_position($img_width, $img_height, $text_width, $text_height, $padding, $position) {
        $positions = array(
            'top-left' => array('x' => $padding, 'y' => $padding + $text_height),
            'top-right' => array('x' => $img_width - $text_width - $padding, 'y' => $padding + $text_height),
            'bottom-left' => array('x' => $padding, 'y' => $img_height - $padding),
            'bottom-right' => array('x' => $img_width - $text_width - $padding, 'y' => $img_height - $padding),
            'center' => array('x' => ($img_width - $text_width) / 2, 'y' => ($img_height + $text_height) / 2)
        );
        
        return isset($positions[$position]) ? $positions[$position] : $positions['bottom-right'];
    }
    
    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Upload watermarked image to media library
     */
    private function upload_watermarked_image($watermarked_path, $original_attachment_id) {
        // Get original attachment info
        $original_attachment = get_post($original_attachment_id);
        if (!$original_attachment) {
            return new WP_Error('original_not_found', __('Original attachment not found.', 'wc-product-media-manager'));
        }
        
        // Prepare file array
        $file_array = array(
            'name' => $original_attachment->post_title . '_watermarked.' . pathinfo($watermarked_path, PATHINFO_EXTENSION),
            'tmp_name' => $watermarked_path
        );
        
        // Upload file
        $attachment_id = media_handle_sideload($file_array, 0);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Update attachment meta
        update_post_meta($attachment_id, '_wc_pmm_watermarked_from', $original_attachment_id);
        update_post_meta($attachment_id, '_wc_pmm_is_watermarked', '1');
        
        return $attachment_id;
    }
    
    /**
     * Get default watermark settings
     */
    private function get_default_settings() {
        return array(
            'watermark_enabled' => WC_PMM_Database::get_watermark_setting('watermark_enabled', '1'),
            'watermark_position' => WC_PMM_Database::get_watermark_setting('watermark_position', 'bottom-right'),
            'watermark_opacity' => WC_PMM_Database::get_watermark_setting('watermark_opacity', '70'),
            'watermark_size' => WC_PMM_Database::get_watermark_setting('watermark_size', '20'),
            'watermark_text' => WC_PMM_Database::get_watermark_setting('watermark_text', get_bloginfo('name')),
            'watermark_font_size' => WC_PMM_Database::get_watermark_setting('watermark_font_size', '12'),
            'watermark_font_color' => WC_PMM_Database::get_watermark_setting('watermark_font_color', '#ffffff'),
            'watermark_background_color' => WC_PMM_Database::get_watermark_setting('watermark_background_color', '#000000'),
            'watermark_padding' => WC_PMM_Database::get_watermark_setting('watermark_padding', '10'),
            'watermark_quality' => WC_PMM_Database::get_watermark_setting('watermark_quality', '90')
        );
    }
    

}