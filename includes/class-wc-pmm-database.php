<?php
/**
 * Database functionality for WooCommerce Product Media Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PMM_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress init to check for database updates
        add_action('init', array($this, 'check_database_version'));
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for storing product media metadata
        $table_name = $wpdb->prefix . 'wc_pmm_product_media';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            watermark_attachment_id bigint(20) unsigned DEFAULT NULL,
            sku varchar(100) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY attachment_id (attachment_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Table for storing watermark settings
        $watermark_table = $wpdb->prefix . 'wc_pmm_watermark_settings';
        
        $watermark_sql = "CREATE TABLE $watermark_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";
        
        dbDelta($watermark_sql);
        
        // Insert default watermark settings
        self::insert_default_settings();
        
        // Update database version
        update_option('wc_pmm_db_version', WC_PMM_VERSION);
    }
    
    /**
     * Insert default watermark settings
     */
    private static function insert_default_settings() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_watermark_settings';
        
        $default_settings = array(
            'watermark_enabled' => '1',
            'watermark_type' => 'text', // 'text' or 'image'
            'watermark_position' => 'bottom-right',
            'watermark_opacity' => '70',
            'watermark_size' => '20',
            'watermark_text' => get_bloginfo('name'),
            'watermark_font_size' => '12',
            'watermark_font_color' => '#ffffff',
            'watermark_background_color' => '#000000',
            'watermark_padding' => '10',
            'watermark_quality' => '90',
            'watermark_image_id' => '', // Attachment ID of watermark image
            'watermark_image_scale' => '25' // Percentage of image width
        );
        
        foreach ($default_settings as $name => $value) {
            $wpdb->replace(
                $table_name,
                array(
                    'setting_name' => $name,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $current_version = get_option('wc_pmm_db_version', '0.0.0');
        
        if (version_compare($current_version, WC_PMM_VERSION, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Get product media
     */
    public static function get_product_media($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_product_media';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_id = %d ORDER BY sort_order ASC",
                $product_id
            ),
            ARRAY_A
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Save product media
     */
    public static function save_product_media($product_id, $media_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_product_media';
        
        // First, delete existing media for this product
        $wpdb->delete($table_name, array('product_id' => $product_id), array('%d'));
        
        // Insert new media data
        if (!empty($media_data) && is_array($media_data)) {
            foreach ($media_data as $index => $media) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'product_id' => $product_id,
                        'attachment_id' => intval($media['attachment_id']),
                        'watermark_attachment_id' => !empty($media['watermark_id']) ? intval($media['watermark_id']) : null,
                        'sku' => sanitize_text_field($media['sku']),
                        'sort_order' => $index
                    ),
                    array('%d', '%d', '%d', '%s', '%d')
                );
            }
        }
        
        return true;
    }
    
    /**
     * Delete product media
     */
    public static function delete_product_media($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_product_media';
        
        return $wpdb->delete($table_name, array('product_id' => $product_id), array('%d'));
    }
    
    /**
     * Get watermark setting
     */
    public static function get_watermark_setting($setting_name, $default = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_watermark_settings';
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT setting_value FROM $table_name WHERE setting_name = %s",
                $setting_name
            )
        );
        
        return $result !== null ? $result : $default;
    }
    
    /**
     * Update watermark setting
     */
    public static function update_watermark_setting($setting_name, $setting_value) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_watermark_settings';
        
        return $wpdb->replace(
            $table_name,
            array(
                'setting_name' => $setting_name,
                'setting_value' => $setting_value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Get all watermark settings
     */
    public static function get_all_watermark_settings() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_pmm_watermark_settings';
        
        $results = $wpdb->get_results(
            "SELECT setting_name, setting_value FROM $table_name",
            ARRAY_A
        );
        
        $settings = array();
        if ($results) {
            foreach ($results as $row) {
                $settings[$row['setting_name']] = $row['setting_value'];
            }
        }
        
        return $settings;
    }
    
    /**
     * Drop database tables (used during uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wc_pmm_product_media',
            $wpdb->prefix . 'wc_pmm_watermark_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove options
        delete_option('wc_pmm_db_version');
    }
}