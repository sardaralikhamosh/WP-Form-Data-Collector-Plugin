<?php
/**
 * Helper functions for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin option with default
 */
function wpfdc_get_option($option, $default = '') {
    return get_option('wpfdc_' . $option, $default);
}

/**
 * Update plugin option
 */
function wpfdc_update_option($option, $value) {
    return update_option('wpfdc_' . $option, $value);
}

/**
 * Delete plugin option
 */
function wpfdc_delete_option($option) {
    return delete_option('wpfdc_' . $option);
}

/**
 * Check if form plugin is active
 */
function wpfdc_is_form_plugin_active($plugin) {
    switch ($plugin) {
        case 'elementor':
            return defined('ELEMENTOR_PRO_VERSION');
        case 'cf7':
            return defined('WPCF7_VERSION');
        case 'gravity':
            return class_exists('GFForms');
        case 'wpforms':
            return defined('WPFORMS_VERSION');
        case 'ninja':
            return defined('NF_PLUGIN_VERSION');
        case 'fluent':
            return defined('FLUENTFORM_VERSION');
        default:
            return false;
    }
}

/**
 * Get supported form plugins
 */
function wpfdc_get_supported_plugins() {
    $plugins = [
        'elementor' => [
            'name' => 'Elementor Pro',
            'active' => wpfdc_is_form_plugin_active('elementor'),
            'icon' => 'dashicons-schedule',
        ],
        'cf7' => [
            'name' => 'Contact Form 7',
            'active' => wpfdc_is_form_plugin_active('cf7'),
            'icon' => 'dashicons-email',
        ],
        'gravity' => [
            'name' => 'Gravity Forms',
            'active' => wpfdc_is_form_plugin_active('gravity'),
            'icon' => 'dashicons-feedback',
        ],
        'wpforms' => [
            'name' => 'WPForms',
            'active' => wpfdc_is_form_plugin_active('wpforms'),
            'icon' => 'dashicons-welcome-write-blog',
        ],
        'ninja' => [
            'name' => 'Ninja Forms',
            'active' => wpfdc_is_form_plugin_active('ninja'),
            'icon' => 'dashicons-list-view',
        ],
        'fluent' => [
            'name' => 'Fluent Forms',
            'active' => wpfdc_is_form_plugin_active('fluent'),
            'icon' => 'dashicons-admin-comments',
        ],
    ];
    
    return $plugins;
}

/**
 * Get status label with color
 */
function wpfdc_get_status_label($status) {
    $labels = [
        'new' => [
            'label' => __('New', 'wp-form-data-collector'),
            'color' => '#d63638',
            'bg_color' => '#f8d7da',
        ],
        'read' => [
            'label' => __('Read', 'wp-form-data-collector'),
            'color' => '#00a32a',
            'bg_color' => '#d4edda',
        ],
        'replied' => [
            'label' => __('Replied', 'wp-form-data-collector'),
            'color' => '#007cba',
            'bg_color' => '#d1ecf1',
        ],
        'spam' => [
            'label' => __('Spam', 'wp-form-data-collector'),
            'color' => '#856404',
            'bg_color' => '#fef3cd',
        ],
    ];
    
    return isset($labels[$status]) ? $labels[$status] : $labels['new'];
}

/**
 * Format date for display
 */
function wpfdc_format_date($date_string) {
    return date_i18n(
        get_option('date_format') . ' ' . get_option('time_format'),
        strtotime($date_string)
    );
}

/**
 * Get human readable time difference
 */
function wpfdc_time_ago($date_string) {
    return human_time_diff(strtotime($date_string), current_time('timestamp')) . ' ' . __('ago', 'wp-form-data-collector');
}

/**
 * Sanitize array of data
 */
function wpfdc_sanitize_array($array) {
    if (!is_array($array)) {
        return sanitize_text_field($array);
    }
    
    return array_map('sanitize_text_field', $array);
}

/**
 * Escape array of data for output
 */
function wpfdc_escape_array($array) {
    if (!is_array($array)) {
        return esc_html($array);
    }
    
    return array_map('esc_html', $array);
}

/**
 * Log debug information
 */
function wpfdc_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_message = '[' . current_time('mysql') . '] WPFDC: ' . $message;
        
        if ($data !== null) {
            $log_message .= ' - ' . print_r($data, true);
        }
        
        error_log($log_message);
    }
}

/**
 * Get plugin asset URL
 */
function wpfdc_asset_url($path) {
    return WPFDC_PLUGIN_URL . 'assets/' . ltrim($path, '/');
}

/**
 * Get plugin template path
 */
function wpfdc_template_path($template) {
    $template = ltrim($template, '/');
    
    // Check in theme first
    $theme_template = get_stylesheet_directory() . '/wpfdc/' . $template;
    if (file_exists($theme_template)) {
        return $theme_template;
    }
    
    // Use plugin template
    return WPFDC_PLUGIN_DIR . 'templates/' . $template;
}

/**
 * Load template
 */
function wpfdc_load_template($template, $args = []) {
    $template_path = wpfdc_template_path($template);
    
    if (!file_exists($template_path)) {
        wpfdc_log('Template not found: ' . $template);
        return;
    }
    
    if (!empty($args)) {
        extract($args);
    }
    
    include $template_path;
}

/**
 * Check if current page is plugin admin page
 */
function wpfdc_is_admin_page() {
    $screen = get_current_screen();
    return $screen && strpos($screen->id, 'wpfdc') !== false;
}

/**
 * Get submission statistics
 */
function wpfdc_get_statistics() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpfdc_submissions';
    
    $stats = [
        'total' => 0,
        'new' => 0,
        'read' => 0,
        'replied' => 0,
        'spam' => 0,
        'by_form_type' => [],
        'by_day' => [],
    ];
    
    // Total counts
    $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $stats['new'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'new'");
    $stats['read'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'read'");
    $stats['replied'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'replied'");
    $stats['spam'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'spam'");
    
    // Count by form type
    $form_types = $wpdb->get_results("
        SELECT form_type, COUNT(*) as count 
        FROM $table_name 
        GROUP BY form_type 
        ORDER BY count DESC
    ");
    
    foreach ($form_types as $type) {
        $stats['by_form_type'][$type->form_type] = $type->count;
    }
    
    // Count by day (last 7 days)
    $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
    $daily_counts = $wpdb->get_results($wpdb->prepare("
        SELECT DATE(submission_date) as date, COUNT(*) as count 
        FROM $table_name 
        WHERE submission_date >= %s 
        GROUP BY DATE(submission_date) 
        ORDER BY date
    ", $seven_days_ago));
    
    foreach ($daily_counts as $day) {
        $stats['by_day'][$day->date] = $day->count;
    }
    
    return $stats;
}

/**
 * Send test email
 */
function wpfdc_send_test_email($email) {
    $subject = __('Test Email from WP Form Data Collector', 'wp-form-data-collector');
    $message = __('This is a test email to confirm that email notifications are working correctly.', 'wp-form-data-collector');
    $message .= "\n\n" . __('If you receive this email, your notification settings are configured correctly.', 'wp-form-data-collector');
    $message .= "\n\n" . __('Sent: ', 'wp-form-data-collector') . current_time('mysql');
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    return wp_mail($email, $subject, $message, $headers);
}