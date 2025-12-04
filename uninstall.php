<?php
/**
 * Uninstall WP Form Data Collector
 *
 * @package WP_Form_Data_Collector
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete options
$options = [
    'wpfdc_db_version',
    'wpfdc_enable_email_notification',
    'wpfdc_notification_email',
    'wpfdc_auto_purge_days',
    'wpfdc_enable_dashboard_widget',
    'wpfdc_enable_submission_limit',
    'wpfdc_max_submissions',
];

foreach ($options as $option) {
    delete_option($option);
}

// Delete database table
$table_name = $wpdb->prefix . 'wpfdc_submissions';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any scheduled events
wp_clear_scheduled_hook('wpfdc_daily_cleanup');