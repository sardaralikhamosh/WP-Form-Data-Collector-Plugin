<?php
/**
 * Export handler for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFDC_Export {
    
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }
    
    private function setup_hooks() {
        // Add export page
        add_action('admin_menu', [$this, 'add_export_page']);
        
        // Handle export requests
        add_action('admin_init', [$this, 'handle_export']);
        
        // AJAX export
        add_action('wp_ajax_wpfdc_export', [$this, 'ajax_export']);
    }
    
    /**
     * Add export page to admin menu
     */
    public function add_export_page() {
        add_submenu_page(
            'wpfdc-submissions',
            __('Export Submissions', 'wp-form-data-collector'),
            __('Export', 'wp-form-data-collector'),
            'manage_options',
            'wpfdc-export',
            [$this, 'export_page']
        );
    }
    
    /**
     * Export page content
     */
    public function export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-form-data-collector'));
        }
        
        $total_submissions = WPFDC_Database::get_count();
        ?>
        <div class="wrap">
            <h1><?php _e('Export Form Submissions', 'wp-form-data-collector'); ?></h1>
            
            <div class="card" style="max-width: 600px;">
                <h2><?php _e('Export to CSV', 'wp-form-data-collector'); ?></h2>
                
                <p>
                    <?php printf(
                        __('You have %d form submissions in your database.', 'wp-form-data-collector'),
                        $total_submissions
                    ); ?>
                </p>
                
                <form method="post" action="<?php echo admin_url('admin.php?page=wpfdc-export'); ?>">
                    <?php wp_nonce_field('wpfdc_export'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Export Format', 'wp-form-data-collector'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="export_format" value="csv" checked="checked">
                                    <?php _e('CSV (Comma Separated Values)', 'wp-form-data-collector'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Compatible with Excel, Google Sheets, and most spreadsheet applications.', 'wp-form-data-collector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Date Range', 'wp-form-data-collector'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="date_range" value="all" checked="checked">
                                    <?php _e('All submissions', 'wp-form-data-collector'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="date_range" value="last30">
                                    <?php _e('Last 30 days', 'wp-form-data-collector'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="date_range" value="last7">
                                    <?php _e('Last 7 days', 'wp-form-data-collector'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="date_range" value="custom">
                                    <?php _e('Custom range', 'wp-form-data-collector'); ?>
                                </label>
                                
                                <div id="custom-date-range" style="display: none; margin-top: 10px;">
                                    <label>
                                        <?php _e('From:', 'wp-form-data-collector'); ?>
                                        <input type="date" name="date_from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                                    </label>
                                    <label style="margin-left: 20px;">
                                        <?php _e('To:', 'wp-form-data-collector'); ?>
                                        <input type="date" name="date_to" value="<?php echo date('Y-m-d'); ?>">
                                    </label>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Filter by Status', 'wp-form-data-collector'); ?></th>
                            <td>
                                <select name="status_filter">
                                    <option value=""><?php _e('All statuses', 'wp-form-data-collector'); ?></option>
                                    <option value="new"><?php _e('New', 'wp-form-data-collector'); ?></option>
                                    <option value="read"><?php _e('Read', 'wp-form-data-collector'); ?></option>
                                    <option value="replied"><?php _e('Replied', 'wp-form-data-collector'); ?></option>
                                    <option value="spam"><?php _e('Spam', 'wp-form-data-collector'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Filter by Form Type', 'wp-form-data-collector'); ?></th>
                            <td>
                                <select name="form_type_filter">
                                    <option value=""><?php _e('All form types', 'wp-form-data-collector'); ?></option>
                                    <option value="elementor"><?php _e('Elementor', 'wp-form-data-collector'); ?></option>
                                    <option value="cf7"><?php _e('Contact Form 7', 'wp-form-data-collector'); ?></option>
                                    <option value="gravity"><?php _e('Gravity Forms', 'wp-form-data-collector'); ?></option>
                                    <option value="wpforms"><?php _e('WPForms', 'wp-form-data-collector'); ?></option>
                                    <option value="ninja"><?php _e('Ninja Forms', 'wp-form-data-collector'); ?></option>
                                    <option value="fluent"><?php _e('Fluent Forms', 'wp-form-data-collector'); ?></option>
                                    <option value="generic"><?php _e('Generic', 'wp-form-data-collector'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Columns to Include', 'wp-form-data-collector'); ?></th>
                            <td>
                                <?php $default_columns = $this->get_export_columns(); ?>
                                <?php foreach ($default_columns as $key => $label): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="columns[]" value="<?php echo esc_attr($key); ?>" checked="checked">
                                    <?php echo esc_html($label); ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="wpfdc_export" class="button button-primary button-large">
                            <?php _e('Export Submissions', 'wp-form-data-collector'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px; max-width: 600px;">
                <h2><?php _e('Export Tips', 'wp-form-data-collector'); ?></h2>
                <ul>
                    <li><?php _e('CSV exports are UTF-8 encoded for compatibility with international characters.', 'wp-form-data-collector'); ?></li>
                    <li><?php _e('For large exports, consider filtering by date range to reduce file size.', 'wp-form-data-collector'); ?></li>
                    <li><?php _e('Exports include all custom fields stored with each submission.', 'wp-form-data-collector'); ?></li>
                    <li><?php _e('You can import the CSV file into Excel, Google Sheets, or any CRM system.', 'wp-form-data-collector'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="date_range"]').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom-date-range').show();
                } else {
                    $('#custom-date-range').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle export request
     */
    public function handle_export() {
        if (!isset($_POST['wpfdc_export']) || !check_admin_referer('wpfdc_export')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'wp-form-data-collector'));
        }
        
        // Get filter parameters
        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : 'all';
        $status_filter = isset($_POST['status_filter']) ? sanitize_text_field($_POST['status_filter']) : '';
        $form_type_filter = isset($_POST['form_type_filter']) ? sanitize_text_field($_POST['form_type_filter']) : '';
        $columns = isset($_POST['columns']) ? array_map('sanitize_text_field', (array)$_POST['columns']) : [];
        
        // Build query based on filters
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        $where = ['1=1'];
        $values = [];
        
        // Status filter
        if ($status_filter) {
            $where[] = 'status = %s';
            $values[] = $status_filter;
        }
        
        // Form type filter
        if ($form_type_filter) {
            $where[] = 'form_type = %s';
            $values[] = $form_type_filter;
        }
        
        // Date range filter
        if ($date_range !== 'all') {
            switch ($date_range) {
                case 'last7':
                    $date = date('Y-m-d H:i:s', strtotime('-7 days'));
                    break;
                case 'last30':
                    $date = date('Y-m-d H:i:s', strtotime('-30 days'));
                    break;
                case 'custom':
                    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) . ' 00:00:00' : '';
                    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) . ' 23:59:59' : '';
                    
                    if ($date_from && $date_to) {
                        $where[] = 'submission_date BETWEEN %s AND %s';
                        $values[] = $date_from;
                        $values[] = $date_to;
                    }
                    break;
            }
            
            if ($date_range !== 'custom' && isset($date)) {
                $where[] = 'submission_date >= %s';
                $values[] = $date;
            }
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Build query
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY submission_date DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $submissions = $wpdb->get_results($query);
        
        if (empty($submissions)) {
            wp_die(__('No submissions found matching your criteria.', 'wp-form-data-collector'));
        }
        
        // Set headers for download
        $filename = 'form-submissions-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Get column headers
        $all_columns = $this->get_export_columns();
        $selected_columns = $columns ?: array_keys($all_columns);
        
        // Write header row
        $headers = [];
        foreach ($selected_columns as $column) {
            if (isset($all_columns[$column])) {
                $headers[] = $all_columns[$column];
            }
        }
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($submissions as $submission) {
            $row = [];
            
            foreach ($selected_columns as $column) {
                switch ($column) {
                    case 'id':
                        $row[] = $submission->id;
                        break;
                    case 'form_name':
                        $row[] = $submission->form_name;
                        break;
                    case 'form_type':
                        $row[] = $submission->form_type;
                        break;
                    case 'name':
                        $row[] = $submission->name;
                        break;
                    case 'email':
                        $row[] = $submission->email;
                        break;
                    case 'phone':
                        $row[] = $submission->phone;
                        break;
                    case 'message':
                        $row[] = $submission->message;
                        break;
                    case 'page_title':
                        $row[] = $submission->page_title;
                        break;
                    case 'page_url':
                        $row[] = $submission->page_url;
                        break;
                    case 'submission_date':
                        $row[] = $submission->submission_date;
                        break;
                    case 'ip_address':
                        $row[] = $submission->ip_address;
                        break;
                    case 'status':
                        $row[] = $submission->status;
                        break;
                    case 'custom_fields':
                        $custom_fields = '';
                        if (!empty($submission->custom_fields)) {
                            $fields = json_decode($submission->custom_fields, true);
                            if (is_array($fields)) {
                                $pairs = [];
                                foreach ($fields as $key => $value) {
                                    if (is_array($value)) {
                                        $value = implode(', ', $value);
                                    }
                                    $pairs[] = $key . ': ' . $value;
                                }
                                $custom_fields = implode('; ', $pairs);
                            }
                        }
                        $row[] = $custom_fields;
                        break;
                    case 'notes':
                        $row[] = $submission->notes;
                        break;
                }
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get available export columns
     */
    private function get_export_columns() {
        return [
            'id' => __('ID', 'wp-form-data-collector'),
            'form_name' => __('Form Name', 'wp-form-data-collector'),
            'form_type' => __('Form Type', 'wp-form-data-collector'),
            'name' => __('Name', 'wp-form-data-collector'),
            'email' => __('Email', 'wp-form-data-collector'),
            'phone' => __('Phone', 'wp-form-data-collector'),
            'message' => __('Message', 'wp-form-data-collector'),
            'page_title' => __('Page Title', 'wp-form-data-collector'),
            'page_url' => __('Page URL', 'wp-form-data-collector'),
            'submission_date' => __('Submission Date', 'wp-form-data-collector'),
            'ip_address' => __('IP Address', 'wp-form-data-collector'),
            'status' => __('Status', 'wp-form-data-collector'),
            'custom_fields' => __('Custom Fields', 'wp-form-data-collector'),
            'notes' => __('Notes', 'wp-form-data-collector'),
        ];
    }
    
    /**
     * AJAX export handler
     */
    public function ajax_export() {
        check_ajax_referer('wpfdc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'wp-form-data-collector'));
        }
        
        // Simple export without filters for AJAX
        $result = WPFDC_Database::export_to_csv();
        
        if ($result) {
            wp_send_json_success(['message' => __('Export completed.', 'wp-form-data-collector')]);
        } else {
            wp_send_json_error(['message' => __('No data to export.', 'wp-form-data-collector')]);
        }
    }
}