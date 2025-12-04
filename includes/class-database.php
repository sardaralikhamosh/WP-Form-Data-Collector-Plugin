<?php
/**
 * Database handler for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFDC_Database {
    
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }
    
    private function setup_hooks() {
        // Daily cleanup
        add_action('wpfdc_daily_cleanup', [$this, 'daily_cleanup']);
        
        // Register table with WP_List_Table if needed
        add_filter('wpfdc_table_name', [$this, 'get_table_name']);
    }
    
    /**
     * Get the submissions table name
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wpfdc_submissions';
    }
    
    /**
     * Save a form submission
     */
    public static function save_submission($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        // Set default values
        $defaults = [
            'form_name' => 'Contact Form',
            'form_type' => 'elementor',
            'form_id' => '',
            'page_id' => 0,
            'page_title' => '',
            'page_url' => '',
            'name' => '',
            'email' => '',
            'phone' => '',
            'message' => '',
            'custom_fields' => '',
            'ip_address' => '',
            'user_agent' => '',
            'status' => 'new',
            'notes' => '',
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $sanitized_data = [
            'form_name' => sanitize_text_field($data['form_name']),
            'form_type' => sanitize_text_field($data['form_type']),
            'form_id' => sanitize_text_field($data['form_id']),
            'page_id' => absint($data['page_id']),
            'page_title' => sanitize_text_field($data['page_title']),
            'page_url' => esc_url_raw($data['page_url']),
            'name' => sanitize_text_field($data['name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'message' => sanitize_textarea_field($data['message']),
            'custom_fields' => is_array($data['custom_fields']) ? 
                wp_json_encode(array_map('sanitize_text_field', $data['custom_fields'])) : 
                sanitize_text_field($data['custom_fields']),
            'ip_address' => sanitize_text_field($data['ip_address']),
            'user_agent' => sanitize_text_field($data['user_agent']),
            'notes' => sanitize_textarea_field($data['notes']),
        ];
        
        // Insert into database
        $result = $wpdb->insert($table_name, $sanitized_data);
        
        if ($result === false) {
            error_log('WPFDC Database Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get submissions with pagination
     */
    public static function get_submissions($args = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'form_type' => '',
            'search' => '',
            'orderby' => 'submission_date',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $values = [];
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        // Form type filter
        if (!empty($args['form_type'])) {
            $where[] = 'form_type = %s';
            $values[] = $args['form_type'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR email LIKE %s OR message LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Prepare query
        $query = "SELECT * FROM $table_name WHERE $where_clause";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Add order
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add pagination
        if ($args['per_page'] > 0) {
            $offset = ($args['page'] - 1) * $args['per_page'];
            $query .= " LIMIT {$args['per_page']} OFFSET $offset";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get submission count
     */
    public static function get_count($args = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        $where = ['1=1'];
        $values = [];
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        // Form type filter
        if (!empty($args['form_type'])) {
            $where[] = 'form_type = %s';
            $values[] = $args['form_type'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Get a single submission by ID
     */
    public static function get_submission($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            absint($id)
        ));
    }
    
    /**
     * Update submission status
     */
    public static function update_status($id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        return $wpdb->update(
            $table_name,
            ['status' => sanitize_text_field($status)],
            ['id' => absint($id)],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Update submission notes
     */
    public static function update_notes($id, $notes) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        return $wpdb->update(
            $table_name,
            ['notes' => sanitize_textarea_field($notes)],
            ['id' => absint($id)],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Delete a submission
     */
    public static function delete_submission($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        return $wpdb->delete(
            $table_name,
            ['id' => absint($id)],
            ['%d']
        );
    }
    
    /**
     * Daily cleanup of old submissions
     */
    public function daily_cleanup() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        $auto_purge_days = get_option('wpfdc_auto_purge_days', 0);
        
        if ($auto_purge_days > 0) {
            $date = date('Y-m-d H:i:s', strtotime("-{$auto_purge_days} days"));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE submission_date < %s",
                $date
            ));
        }
        
        // Clean up spam submissions older than 30 days
        $spam_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE status = 'spam' AND submission_date < %s",
            $spam_date
        ));
    }
    
    /**
     * Export submissions to CSV
     */
    public static function export_to_csv($args = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpfdc_submissions';
        
        $query = "SELECT * FROM $table_name ORDER BY submission_date DESC";
        $submissions = $wpdb->get_results($query);
        
        if (empty($submissions)) {
            return false;
        }
        
        // Create CSV content
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Header row
        $headers = [
            'ID',
            'Form Name',
            'Form Type',
            'Name',
            'Email',
            'Phone',
            'Message',
            'Page Title',
            'Page URL',
            'Submission Date',
            'IP Address',
            'Status',
            'Custom Fields',
        ];
        
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($submissions as $submission) {
            $custom_fields = '';
            if (!empty($submission->custom_fields)) {
                $fields = json_decode($submission->custom_fields, true);
                if (is_array($fields)) {
                    $custom_fields = http_build_query($fields, '', '; ');
                }
            }
            
            $row = [
                $submission->id,
                $submission->form_name,
                $submission->form_type,
                $submission->name,
                $submission->email,
                $submission->phone,
                $submission->message,
                $submission->page_title,
                $submission->page_url,
                $submission->submission_date,
                $submission->ip_address,
                $submission->status,
                $custom_fields,
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        return true;
    }
}