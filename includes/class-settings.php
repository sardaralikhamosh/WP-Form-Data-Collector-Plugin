<?php
/**
 * Settings handler for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFDC_Settings {
    
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }
    
    private function setup_hooks() {
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Settings page is handled by admin menu in class-admin.php
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General settings section
        add_settings_section(
            'wpfdc_general_section',
            __('General Settings', 'wp-form-data-collector'),
            [$this, 'general_section_callback'],
            'wpfdc_settings'
        );
        
        // Email settings section
        add_settings_section(
            'wpfdc_email_section',
            __('Email Notifications', 'wp-form-data-collector'),
            [$this, 'email_section_callback'],
            'wpfdc_settings'
        );
        
        // Data management section
        add_settings_section(
            'wpfdc_data_section',
            __('Data Management', 'wp-form-data-collector'),
            [$this, 'data_section_callback'],
            'wpfdc_settings'
        );
        
        // Register settings fields
        
        // General settings
        register_setting('wpfdc_settings', 'wpfdc_enable_dashboard_widget');
        add_settings_field(
            'wpfdc_enable_dashboard_widget',
            __('Dashboard Widget', 'wp-form-data-collector'),
            [$this, 'checkbox_field_callback'],
            'wpfdc_settings',
            'wpfdc_general_section',
            [
                'label_for' => 'wpfdc_enable_dashboard_widget',
                'description' => __('Show recent form submissions on the WordPress dashboard.', 'wp-form-data-collector'),
            ]
        );
        
        // Email settings
        register_setting('wpfdc_settings', 'wpfdc_enable_email_notification');
        add_settings_field(
            'wpfdc_enable_email_notification',
            __('Enable Email Notifications', 'wp-form-data-collector'),
            [$this, 'checkbox_field_callback'],
            'wpfdc_settings',
            'wpfdc_email_section',
            [
                'label_for' => 'wpfdc_enable_email_notification',
                'description' => __('Send email notifications when new forms are submitted.', 'wp-form-data-collector'),
            ]
        );
        
        register_setting('wpfdc_settings', 'wpfdc_notification_email', 'sanitize_email');
        add_settings_field(
            'wpfdc_notification_email',
            __('Notification Email', 'wp-form-data-collector'),
            [$this, 'text_field_callback'],
            'wpfdc_settings',
            'wpfdc_email_section',
            [
                'label_for' => 'wpfdc_notification_email',
                'description' => __('Email address to receive notifications. Defaults to admin email.', 'wp-form-data-collector'),
                'type' => 'email',
            ]
        );
        
        // Data management
        register_setting('wpfdc_settings', 'wpfdc_auto_purge_days', 'absint');
        add_settings_field(
            'wpfdc_auto_purge_days',
            __('Auto-purge Old Submissions', 'wp-form-data-collector'),
            [$this, 'number_field_callback'],
            'wpfdc_settings',
            'wpfdc_data_section',
            [
                'label_for' => 'wpfdc_auto_purge_days',
                'description' => __('Automatically delete submissions older than X days (0 = never delete).', 'wp-form-data-collector'),
                'min' => 0,
                'max' => 365,
                'step' => 1,
            ]
        );
        
        register_setting('wpfdc_settings', 'wpfdc_enable_submission_limit');
        add_settings_field(
            'wpfdc_enable_submission_limit',
            __('Enable Submission Limit', 'wp-form-data-collector'),
            [$this, 'checkbox_field_callback'],
            'wpfdc_settings',
            'wpfdc_data_section',
            [
                'label_for' => 'wpfdc_enable_submission_limit',
                'description' => __('Limit the number of submissions stored in the database.', 'wp-form-data-collector'),
            ]
        );
        
        register_setting('wpfdc_settings', 'wpfdc_max_submissions', 'absint');
        add_settings_field(
            'wpfdc_max_submissions',
            __('Maximum Submissions', 'wp-form-data-collector'),
            [$this, 'number_field_callback'],
            'wpfdc_settings',
            'wpfdc_data_section',
            [
                'label_for' => 'wpfdc_max_submissions',
                'description' => __('Maximum number of submissions to keep (oldest will be deleted first).', 'wp-form-data-collector'),
                'min' => 100,
                'max' => 100000,
                'step' => 100,
            ]
        );
    }
    
    /**
     * Settings page
     */
    public static function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-form-data-collector'));
        }
        
        // Handle database reset if requested
        if (isset($_POST['wpfdc_reset_database']) && check_admin_referer('wpfdc_reset_database')) {
            self::reset_database();
        }
        
        // Get current counts for display
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        $total_count = WPFDC_Database::get_count();
        $database_size = $wpdb->get_var("SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '$table_name'");
        ?>
        <div class="wrap">
            <h1><?php _e('WP Form Data Collector Settings', 'wp-form-data-collector'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wpfdc_settings');
                do_settings_sections('wpfdc_settings');
                submit_button();
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px; max-width: 600px;">
                <h2><?php _e('Database Information', 'wp-form-data-collector'); ?></h2>
                <table class="widefat">
                    <tr>
                        <th width="200"><?php _e('Total Submissions', 'wp-form-data-collector'); ?></th>
                        <td><?php echo number_format($total_count); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Database Size', 'wp-form-data-collector'); ?></th>
                        <td><?php echo $database_size ? $database_size . ' MB' : __('N/A', 'wp-form-data-collector'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Table Name', 'wp-form-data-collector'); ?></th>
                        <td><code><?php echo $table_name; ?></code></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="margin-top: 20px; max-width: 600px;">
                <h2><?php _e('Advanced Tools', 'wp-form-data-collector'); ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('wpfdc_reset_database'); ?>
                    <p>
                        <button type="submit" name="wpfdc_reset_database" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure? This will delete ALL form submissions and cannot be undone.', 'wp-form-data-collector'); ?>')">
                            <?php _e('Reset Database', 'wp-form-data-collector'); ?>
                        </button>
                        <span class="description">
                            <?php _e('Delete all submissions and reset the database table.', 'wp-form-data-collector'); ?>
                        </span>
                    </p>
                </form>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wpfdc-export'); ?>" class="button">
                        <?php _e('Export All Data', 'wp-form-data-collector'); ?>
                    </a>
                    <span class="description">
                        <?php _e('Export all submissions to a CSV file for backup.', 'wp-form-data-collector'); ?>
                    </span>
                </p>
            </div>
            
            <div class="card" style="margin-top: 20px; max-width: 600px;">
                <h2><?php _e('Plugin Information', 'wp-form-data-collector'); ?></h2>
                <table class="widefat">
                    <tr>
                        <th width="200"><?php _e('Version', 'wp-form-data-collector'); ?></th>
                        <td><?php echo WPFDC_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Database Version', 'wp-form-data-collector'); ?></th>
                        <td><?php echo get_option('wpfdc_db_version', '1.0'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Supported Form Plugins', 'wp-form-data-collector'); ?></th>
                        <td>
                            Elementor, Contact Form 7, Gravity Forms, WPForms, Ninja Forms, Fluent Forms, Generic Forms
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('General plugin settings and behavior.', 'wp-form-data-collector') . '</p>';
    }
    
    /**
     * Email section callback
     */
    public function email_section_callback() {
        echo '<p>' . __('Configure email notifications for new form submissions.', 'wp-form-data-collector') . '</p>';
    }
    
    /**
     * Data section callback
     */
    public function data_section_callback() {
        echo '<p>' . __('Manage data storage and retention settings.', 'wp-form-data-collector') . '</p>';
    }
    
    /**
     * Checkbox field callback
     */
    public function checkbox_field_callback($args) {
        $option = get_option($args['label_for'], 'yes');
        $checked = checked($option, 'yes', false);
        
        printf(
            '<input type="checkbox" id="%1$s" name="%1$s" value="yes" %2$s>',
            esc_attr($args['label_for']),
            $checked
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Text field callback
     */
    public function text_field_callback($args) {
        $value = get_option($args['label_for'], '');
        $type = isset($args['type']) ? $args['type'] : 'text';
        
        printf(
            '<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="regular-text">',
            esc_attr($type),
            esc_attr($args['label_for']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Number field callback
     */
    public function number_field_callback($args) {
        $value = get_option($args['label_for'], 0);
        
        $min = isset($args['min']) ? ' min="' . esc_attr($args['min']) . '"' : '';
        $max = isset($args['max']) ? ' max="' . esc_attr($args['max']) . '"' : '';
        $step = isset($args['step']) ? ' step="' . esc_attr($args['step']) . '"' : '';
        
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text"%3$s%4$s%5$s>',
            esc_attr($args['label_for']),
            esc_attr($value),
            $min,
            $max,
            $step
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Reset database
     */
    private static function reset_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Recreate table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
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
        
        // Reset version
        update_option('wpfdc_db_version', WPFDC_VERSION);
        
        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Database has been reset successfully.', 'wp-form-data-collector') . 
                 '</p></div>';
        });
    }
}