<?php
/**
 * Plugin Name: WP Form Data Collector
 * Plugin URI: https://wordpress.org/plugins/wp-form-data-collector/
 * Description: Save all WordPress form submissions to your database. Works with Elementor, Contact Form 7, WPForms, Gravity Forms, and all major form plugins. Never lose a lead again!
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: wp-form-data-collector
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPFDC_VERSION', '2.0.0');
define('WPFDC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFDC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPFDC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class WP_Form_Data_Collector {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init_plugin']);
        
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);
    }
    
    public function activate() {
        $this->create_database_table();
        $this->set_default_options();
        
        // Schedule cleanup hook
        if (!wp_next_scheduled('wpfdc_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wpfdc_daily_cleanup');
        }
    }
    
    public function deactivate() {
        // Clear scheduled hook
        wp_clear_scheduled_hook('wpfdc_daily_cleanup');
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-form-data-collector',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function init_plugin() {
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
    }
    
    private function includes() {
        // Core functionality
        require_once WPFDC_PLUGIN_DIR . 'includes/class-database.php';
        require_once WPFDC_PLUGIN_DIR . 'includes/class-form-handler.php';
        require_once WPFDC_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPFDC_PLUGIN_DIR . 'includes/class-export.php';
        require_once WPFDC_PLUGIN_DIR . 'includes/class-settings.php';
        
        // Helper functions
        require_once WPFDC_PLUGIN_DIR . 'includes/helpers.php';
    }
    
    private function init_components() {
        // Initialize database
        WPFDC_Database::init();
        
        // Initialize form handlers
        WPFDC_Form_Handler::init();
        
        // Initialize admin interface
        if (is_admin()) {
            WPFDC_Admin::init();
            WPFDC_Settings::init();
            WPFDC_Export::init();
        }
    }
    
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_name VARCHAR(255) NOT NULL DEFAULT 'Contact Form',
            form_type VARCHAR(50) NOT NULL DEFAULT 'elementor',
            form_id VARCHAR(100),
            page_id BIGINT(20) UNSIGNED DEFAULT 0,
            page_title VARCHAR(255),
            page_url VARCHAR(500),
            name VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(100),
            message LONGTEXT,
            custom_fields LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('new', 'read', 'replied', 'spam') DEFAULT 'new',
            notes TEXT,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_status (status),
            KEY idx_date (submission_date),
            KEY idx_form_type (form_type),
            KEY idx_form_name (form_name(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add version option
        update_option('wpfdc_db_version', WPFDC_VERSION);
    }
    
    private function set_default_options() {
        $defaults = [
            'wpfdc_enable_email_notification' => 'yes',
            'wpfdc_notification_email' => get_option('admin_email'),
            'wpfdc_auto_purge_days' => 0,
            'wpfdc_enable_dashboard_widget' => 'yes',
            'wpfdc_enable_submission_limit' => 'no',
            'wpfdc_max_submissions' => 10000,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
}

// Initialize plugin
function wpfdc_init() {
    return WP_Form_Data_Collector::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'wpfdc_init');

// Global helper function
function wpfdc() {
    return WP_Form_Data_Collector::get_instance();
}