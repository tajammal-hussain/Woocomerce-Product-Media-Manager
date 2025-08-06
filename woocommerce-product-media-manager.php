<?php
/**
 * Plugin Name: WooCommerce Product Media Manager
 * Plugin URI: https://your-website.com
 * Description: Advanced media management for WooCommerce products with drag & drop functionality and watermarking
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wc-product-media-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_PMM_VERSION', '1.0.0');
define('WC_PMM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_PMM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class WC_Product_Media_Manager {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('wc-product-media-manager', false, dirname(WC_PMM_PLUGIN_BASENAME) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize classes
        $this->init_classes();
        
        // Add hooks
        $this->add_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-admin.php';
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-database.php';
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-watermark.php';
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-ajax.php';
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-settings.php';
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-frontend.php';
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        new WC_PMM_Admin();
        new WC_PMM_Database();
        new WC_PMM_Watermark();
        new WC_PMM_Ajax();
        new WC_PMM_Settings();
        new WC_PMM_Frontend();
    }
    
    /**
     * Add hooks
     */
    private function add_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Frontend scripts if needed
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type;
        
        if (('post.php' === $hook || 'post-new.php' === $hook) && 'product' === $post_type) {
            // Enqueue WordPress media library
            wp_enqueue_media();
            
            // Enqueue our custom scripts
            wp_enqueue_script(
                'wc-pmm-admin',
                WC_PMM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-sortable', 'wp-util'),
                WC_PMM_VERSION,
                true
            );
            
            // Enqueue styles
            wp_enqueue_style(
                'wc-pmm-admin',
                WC_PMM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WC_PMM_VERSION
            );
            
            // Localize script
            wp_localize_script('wc-pmm-admin', 'wc_pmm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_pmm_nonce'),
                'strings' => array(
                    'select_images' => __('Select Images', 'wc-product-media-manager'),
                    'drag_drop_here' => __('Drag & Drop Images Here or Click to Select', 'wc-product-media-manager'),
                    'processing' => __('Processing...', 'wc-product-media-manager'),
                    'error' => __('An error occurred', 'wc-product-media-manager'),
                    'confirm_delete' => __('Are you sure you want to delete this image?', 'wc-product-media-manager'),
                )
            ));
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Include required files for activation
        require_once WC_PMM_PLUGIN_DIR . 'includes/class-wc-pmm-database.php';
        
        // Create database tables
        WC_PMM_Database::create_tables();
        
        // Set default options
        add_option('wc_pmm_version', WC_PMM_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('WooCommerce Product Media Manager requires WooCommerce to be installed and active.', 'wc-product-media-manager');
        echo '</p></div>';
    }
}

// Initialize the plugin
WC_Product_Media_Manager::get_instance();