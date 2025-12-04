<?php
/**
 * Form handler for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFDC_Form_Handler {
    
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }
    
    private function setup_hooks() {
        // Elementor Forms
        add_action('elementor_pro/forms/new_record', [$this, 'handle_elementor_form'], 10, 2);
        
        // Contact Form 7
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_form']);
        
        // Gravity Forms
        if (class_exists('GFForms')) {
            add_action('gform_after_submission', [$this, 'handle_gravity_form'], 10, 2);
        }
        
        // WPForms
        add_action('wpforms_process_complete', [$this, 'handle_wpforms_form'], 10, 4);
        
        // Ninja Forms
        add_action('ninja_forms_after_submission', [$this, 'handle_ninja_form']);
        
        // Fluent Forms
        add_action('fluentform/before_insert_submission', [$this, 'handle_fluent_form'], 10, 3);
        
        // Generic form handler via filter
        add_filter('wpfdc_capture_form', [$this, 'capture_generic_form'], 10, 2);
    }
    
    /**
     * Handle Elementor Form submission
     */
    public function handle_elementor_form($record, $handler) {
        $raw_fields = $record->get('fields');
        $form_settings = $record->get_form_settings();
        
        $fields = [];
        foreach ($raw_fields as $id => $field) {
            if (isset($field['value'])) {
                $fields[$id] = $field['value'];
            }
        }
        
        $form_name = isset($form_settings['form_name']) ? $form_settings['form_name'] : 'Elementor Form';
        $form_id = isset($form_settings['id']) ? $form_settings['id'] : '';
        
        $this->process_form_data($fields, $form_name, 'elementor', $form_id);
    }
    
    /**
     * Handle Contact Form 7 submission
     */
    public function handle_cf7_form($contact_form) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        
        $posted_data = $submission->get_posted_data();
        $form_name = $contact_form->title();
        $form_id = $contact_form->id();
        
        $this->process_form_data($posted_data, $form_name, 'cf7', $form_id);
    }
    
    /**
     * Handle Gravity Forms submission
     */
    public function handle_gravity_form($entry, $form) {
        $form_name = $form['title'];
        $form_id = $form['id'];
        $fields = [];
        
        foreach ($entry as $key => $value) {
            if (is_numeric($key)) {
                $fields[$key] = $value;
            }
        }
        
        $this->process_form_data($fields, $form_name, 'gravity', $form_id);
    }
    
    /**
     * Handle WPForms submission
     */
    public function handle_wpforms_form($fields, $entry, $form_data, $entry_id) {
        $form_name = $form_data['settings']['form_title'];
        $form_id = $form_data['id'];
        $field_values = [];
        
        foreach ($fields as $field) {
            $field_values[$field['name']] = $field['value'];
        }
        
        $this->process_form_data($field_values, $form_name, 'wpforms', $form_id);
    }
    
    /**
     * Handle Ninja Forms submission
     */
    public function handle_ninja_form($form_data) {
        $form_name = isset($form_data['form']['form_title']) ? $form_data['form']['form_title'] : 'Ninja Form';
        $form_id = isset($form_data['form']['id']) ? $form_data['form']['id'] : '';
        $fields = [];
        
        foreach ($form_data['fields'] as $field) {
            $fields[$field['key']] = $field['value'];
        }
        
        $this->process_form_data($fields, $form_name, 'ninja', $form_id);
    }
    
    /**
     * Handle Fluent Forms submission
     */
    public function handle_fluent_form($insertData, $data, $form) {
        $form_name = $form->title;
        $form_id = $form->id;
        
        $this->process_form_data($data, $form_name, 'fluent', $form_id);
    }
    
    /**
     * Process form data and save to database
     */
    private function process_form_data($fields, $form_name, $form_type, $form_id = '') {
        global $post;
        
        // Extract common fields
        $name = '';
        $email = '';
        $phone = '';
        $message = '';
        $custom_fields = [];
        
        // Common field patterns
        $name_patterns = ['name', 'full_name', 'first_name', 'your_name', 'fname', 'contact_name'];
        $email_patterns = ['email', 'your_email', 'e-mail', 'contact_email', 'email_address'];
        $phone_patterns = ['phone', 'telephone', 'mobile', 'cell', 'contact_phone', 'phone_number'];
        $message_patterns = ['message', 'comments', 'description', 'inquiry', 'details', 'note'];
        
        foreach ($fields as $field_key => $field_value) {
            $field_key_lower = strtolower($field_key);
            
            // Detect name
            if (empty($name)) {
                foreach ($name_patterns as $pattern) {
                    if (strpos($field_key_lower, $pattern) !== false) {
                        $name = sanitize_text_field($field_value);
                        continue 2;
                    }
                }
            }
            
            // Detect email
            if (empty($email)) {
                foreach ($email_patterns as $pattern) {
                    if (strpos($field_key_lower, $pattern) !== false || is_email($field_value)) {
                        $email = sanitize_email($field_value);
                        continue 2;
                    }
                }
            }
            
            // Detect phone
            if (empty($phone)) {
                foreach ($phone_patterns as $pattern) {
                    if (strpos($field_key_lower, $pattern) !== false) {
                        $phone = sanitize_text_field($field_value);
                        continue 2;
                    }
                }
            }
            
            // Detect message
            if (empty($message)) {
                foreach ($message_patterns as $pattern) {
                    if (strpos($field_key_lower, $pattern) !== false) {
                        $message = sanitize_textarea_field($field_value);
                        continue 2;
                    }
                }
            }
            
            // Store other fields as custom fields
            if (!empty($field_value)) {
                $custom_fields[$field_key] = is_array($field_value) ? 
                    array_map('sanitize_text_field', $field_value) : 
                    sanitize_text_field($field_value);
            }
        }
        
        // Get page information
        $page_id = 0;
        $page_title = '';
        $page_url = '';
        
        if ($post && is_a($post, 'WP_Post')) {
            $page_id = $post->ID;
            $page_title = $post->post_title;
            $page_url = get_permalink($post->ID);
        } elseif (isset($_SERVER['HTTP_REFERER'])) {
            $page_url = esc_url_raw($_SERVER['HTTP_REFERER']);
            $page_from_url = url_to_postid($page_url);
            if ($page_from_url) {
                $page_id = $page_from_url;
                $page_post = get_post($page_from_url);
                if ($page_post) {
                    $page_title = $page_post->post_title;
                }
            }
        }
        
        // Prepare data for database
        $submission_data = [
            'form_name' => sanitize_text_field($form_name),
            'form_type' => sanitize_text_field($form_type),
            'form_id' => sanitize_text_field($form_id),
            'page_id' => absint($page_id),
            'page_title' => sanitize_text_field($page_title),
            'page_url' => $page_url,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'custom_fields' => !empty($custom_fields) ? json_encode($custom_fields, JSON_UNESCAPED_UNICODE) : '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
        
        // Save to database
        $submission_id = WPFDC_Database::save_submission($submission_data);
        
        // Send email notification if enabled
        if ($submission_id && get_option('wpfdc_enable_email_notification', 'yes') === 'yes') {
            $this->send_notification($submission_data);
        }
        
        return $submission_id;
    }
    
    /**
     * Send email notification
     */
    private function send_notification($data) {
        $to = get_option('wpfdc_notification_email', get_option('admin_email'));
        $subject = sprintf(__('New Form Submission: %s', 'wp-form-data-collector'), $data['form_name']);
        
        $message = "New form submission received:\n\n";
        $message .= "Form: {$data['form_name']}\n";
        $message .= "Type: {$data['form_type']}\n";
        $message .= "Date: " . current_time('mysql') . "\n";
        $message .= "Page: {$data['page_title']} ({$data['page_url']})\n\n";
        
        if (!empty($data['name'])) {
            $message .= "Name: {$data['name']}\n";
        }
        if (!empty($data['email'])) {
            $message .= "Email: {$data['email']}\n";
        }
        if (!empty($data['phone'])) {
            $message .= "Phone: {$data['phone']}\n";
        }
        if (!empty($data['message'])) {
            $message .= "Message: {$data['message']}\n";
        }
        
        // Add custom fields
        if (!empty($data['custom_fields'])) {
            $custom_fields = json_decode($data['custom_fields'], true);
            if (!empty($custom_fields)) {
                $message .= "\nAdditional Fields:\n";
                foreach ($custom_fields as $key => $value) {
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $message .= ucfirst(str_replace(['_', '-'], ' ', $key)) . ": $value\n";
                }
            }
        }
        
        $message .= "\nIP Address: {$data['ip_address']}\n";
        $message .= "User Agent: {$data['user_agent']}\n";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Capture generic form via filter
     */
    public function capture_generic_form($fields, $form_name = 'Contact Form') {
        return $this->process_form_data($fields, $form_name, 'generic');
    }
}