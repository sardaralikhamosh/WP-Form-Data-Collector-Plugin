<?php
/**
 * Admin interface for WP Form Data Collector
 *
 * @package WPFDC
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFDC_Admin {
    
    private static $instance = null;
    
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }
    
    private function setup_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Dashboard widget
        if (get_option('wpfdc_enable_dashboard_widget', 'yes') === 'yes') {
            add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);
        }
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . WPFDC_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }
    
    /**
     * Add admin menu pages
     */
    public function admin_menu() {
        // Main menu
        $hook = add_menu_page(
            __('Form Submissions', 'wp-form-data-collector'),
            __('Form Submissions', 'wp-form-data-collector'),
            'manage_options',
            'wpfdc-submissions',
            [$this, 'submissions_page'],
            'dashicons-email-alt',
            30
        );
        
        // All submissions page (same as main)
        add_submenu_page(
            'wpfdc-submissions',
            __('All Submissions', 'wp-form-data-collector'),
            __('All Submissions', 'wp-form-data-collector'),
            'manage_options',
            'wpfdc-submissions',
            [$this, 'submissions_page']
        );
        
        // New submissions
        add_submenu_page(
            'wpfdc-submissions',
            __('New Submissions', 'wp-form-data-collector'),
            __('New', 'wp-form-data-collector') . $this->get_new_count_badge(),
            'manage_options',
            'wpfdc-submissions-new',
            [$this, 'new_submissions_page']
        );
        
        // Settings page
        add_submenu_page(
            'wpfdc-submissions',
            __('Settings', 'wp-form-data-collector'),
            __('Settings', 'wp-form-data-collector'),
            'manage_options',
            'wpfdc-settings',
            'WPFDC_Settings::settings_page'
        );
    }
    
    /**
     * Get new submissions count for badge
     */
    private function get_new_count_badge() {
        $count = WPFDC_Database::get_count(['status' => 'new']);
        if ($count > 0) {
            return ' <span class="awaiting-mod">' . $count . '</span>';
        }
        return '';
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wpfdc-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wpfdc-admin',
            WPFDC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPFDC_VERSION
        );
        
        wp_enqueue_script(
            'wpfdc-admin',
            WPFDC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPFDC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wpfdc-admin', 'wpfdc_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpfdc_admin_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this submission?', 'wp-form-data-collector'),
            'confirm_delete_bulk' => __('Are you sure you want to delete the selected submissions?', 'wp-form-data-collector'),
        ]);
    }
    
    /**
     * Submissions page
     */
    public function submissions_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-form-data-collector'));
        }
        
        // Handle actions
        $this->handle_actions();
        
        // Get current tab
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';
        
        ?>
        <div class="wrap wpfdc-admin">
            <h1 class="wp-heading-inline"><?php _e('Form Submissions', 'wp-form-data-collector'); ?></h1>
            
            <a href="<?php echo admin_url('admin.php?page=wpfdc-export'); ?>" class="page-title-action">
                <?php _e('Export CSV', 'wp-form-data-collector'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Stats Overview -->
            <div class="wpfdc-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo WPFDC_Database::get_count(['status' => 'new']); ?></span>
                    <span class="stat-label"><?php _e('New', 'wp-form-data-collector'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo WPFDC_Database::get_count(['status' => 'read']); ?></span>
                    <span class="stat-label"><?php _e('Read', 'wp-form-data-collector'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo WPFDC_Database::get_count(['status' => 'replied']); ?></span>
                    <span class="stat-label"><?php _e('Replied', 'wp-form-data-collector'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo WPFDC_Database::get_count(); ?></span>
                    <span class="stat-label"><?php _e('Total', 'wp-form-data-collector'); ?></span>
                </div>
            </div>
            
            <!-- Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions'); ?>" class="nav-tab <?php echo $tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('All', 'wp-form-data-collector'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions&status=new'); ?>" class="nav-tab <?php echo $tab === 'new' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('New', 'wp-form-data-collector'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions&status=read'); ?>" class="nav-tab <?php echo $tab === 'read' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Read', 'wp-form-data-collector'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions&status=replied'); ?>" class="nav-tab <?php echo $tab === 'replied' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Replied', 'wp-form-data-collector'); ?>
                </a>
            </h2>
            
            <!-- Submissions Table -->
            <form method="get">
                <input type="hidden" name="page" value="wpfdc-submissions">
                
                <?php
                $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
                if ($status) {
                    echo '<input type="hidden" name="status" value="' . esc_attr($status) . '">';
                }
                
                $table = new WPFDC_Submissions_Table();
                $table->prepare_items();
                $table->search_box(__('Search Submissions', 'wp-form-data-collector'), 'search_id');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * New submissions page
     */
    public function new_submissions_page() {
        // Redirect to main page with new filter
        wp_redirect(admin_url('admin.php?page=wpfdc-submissions&status=new'));
        exit;
    }
    
    /**
     * Handle form actions
     */
    private function handle_actions() {
        if (!isset($_GET['action']) || !isset($_GET['id'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        $id = absint($_GET['id']);
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'wpfdc_action_' . $id)) {
            wp_die(__('Security check failed.', 'wp-form-data-collector'));
        }
        
        switch ($action) {
            case 'view':
                $this->view_submission($id);
                break;
                
            case 'delete':
                WPFDC_Database::delete_submission($id);
                $this->add_notice('success', __('Submission deleted successfully.', 'wp-form-data-collector'));
                break;
                
            case 'mark_read':
                WPFDC_Database::update_status($id, 'read');
                $this->add_notice('success', __('Submission marked as read.', 'wp-form-data-collector'));
                break;
                
            case 'mark_replied':
                WPFDC_Database::update_status($id, 'replied');
                $this->add_notice('success', __('Submission marked as replied.', 'wp-form-data-collector'));
                break;
        }
    }
    
    /**
     * View single submission
     */
    private function view_submission($id) {
        $submission = WPFDC_Database::get_submission($id);
        
        if (!$submission) {
            $this->add_notice('error', __('Submission not found.', 'wp-form-data-collector'));
            return;
        }
        
        // Mark as read when viewed
        if ($submission->status === 'new') {
            WPFDC_Database::update_status($id, 'read');
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Submission Details', 'wp-form-data-collector'); ?></h1>
            
            <div class="wpfdc-submission-details">
                <div class="detail-section">
                    <h2><?php _e('Contact Information', 'wp-form-data-collector'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th width="150"><?php _e('Name', 'wp-form-data-collector'); ?></th>
                            <td><?php echo esc_html($submission->name); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'wp-form-data-collector'); ?></th>
                            <td><?php echo esc_html($submission->email); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Phone', 'wp-form-data-collector'); ?></th>
                            <td><?php echo esc_html($submission->phone); ?></td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($submission->message)): ?>
                <div class="detail-section">
                    <h2><?php _e('Message', 'wp-form-data-collector'); ?></h2>
                    <div class="message-content">
                        <?php echo nl2br(esc_html($submission->message)); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-section">
                    <h2><?php _e('Submission Details', 'wp-form-data-collector'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th width="150"><?php _e('Form', 'wp-form-data-collector'); ?></th>
                            <td><?php echo esc_html($submission->form_name); ?> (<?php echo esc_html($submission->form_type); ?>)</td>
                        </tr>
                        <tr>
                            <th><?php _e('Page', 'wp-form-data-collector'); ?></th>
                            <td>
                                <?php echo esc_html($submission->page_title); ?>
                                <?php if ($submission->page_url): ?>
                                    <br><a href="<?php echo esc_url($submission->page_url); ?>" target="_blank"><?php echo esc_url($submission->page_url); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Date', 'wp-form-data-collector'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->submission_date)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('IP Address', 'wp-form-data-collector'); ?></th>
                            <td><?php echo esc_html($submission->ip_address); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'wp-form-data-collector'); ?></th>
                            <td>
                                <span class="status-<?php echo esc_attr($submission->status); ?>">
                                    <?php echo esc_html(ucfirst($submission->status)); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($submission->custom_fields)): 
                    $custom_fields = json_decode($submission->custom_fields, true);
                    if (!empty($custom_fields)): ?>
                    <div class="detail-section">
                        <h2><?php _e('Additional Fields', 'wp-form-data-collector'); ?></h2>
                        <table class="widefat">
                            <?php foreach ($custom_fields as $key => $value): 
                                if (is_array($value)) {
                                    $value = implode(', ', $value);
                                }
                                if (!empty($value)): ?>
                                <tr>
                                    <th width="150"><?php echo esc_html(ucfirst(str_replace(['_', '-'], ' ', $key))); ?></th>
                                    <td><?php echo esc_html($value); ?></td>
                                </tr>
                                <?php endif;
                            endforeach; ?>
                        </table>
                    </div>
                    <?php endif;
                endif; ?>
                
                <div class="detail-section">
                    <h2><?php _e('Admin Notes', 'wp-form-data-collector'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('wpfdc_save_notes_' . $id); ?>
                        <textarea name="notes" rows="5" style="width: 100%;"><?php echo esc_textarea($submission->notes); ?></textarea>
                        <p class="submit">
                            <button type="submit" name="save_notes" class="button button-primary">
                                <?php _e('Save Notes', 'wp-form-data-collector'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <div class="detail-actions">
                    <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions'); ?>" class="button">
                        <?php _e('Back to List', 'wp-form-data-collector'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions&action=delete&id=' . $id . '&_wpnonce=' . wp_create_nonce('wpfdc_action_' . $id)); ?>" class="button button-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this submission?', 'wp-form-data-collector'); ?>')">
                        <?php _e('Delete', 'wp-form-data-collector'); ?>
                    </a>
                    <?php if ($submission->status !== 'replied'): ?>
                    <a href="<?php echo admin_url('admin.php?page=wpfdc-submissions&action=mark_replied&id=' . $id . '&_wpnonce=' . wp_create_nonce('wpfdc_action_' . $id)); ?>" class="button button-primary">
                        <?php _e('Mark as Replied', 'wp-form-data-collector'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add dashboard widget
     */
    public function dashboard_widget() {
        wp_add_dashboard_widget(
            'wpfdc_dashboard_widget',
            __('Recent Form Submissions', 'wp-form-data-collector'),
            [$this, 'dashboard_widget_content']
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        $submissions = WPFDC_Database::get_submissions([
            'per_page' => 5,
            'page' => 1,
        ]);
        
        if (empty($submissions)) {
            echo '<p>' . __('No form submissions yet.', 'wp-form-data-collector') . '</p>';
            return;
        }
        
        echo '<ul style="margin: 0; padding: 0;">';
        foreach ($submissions as $submission) {
            echo '<li style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">';
            echo '<strong>' . esc_html($submission->name ?: __('Anonymous', 'wp-form-data-collector')) . '</strong><br>';
            echo esc_html($submission->email) . '<br>';
            echo '<small>' . esc_html($submission->form_name) . ' • ' . human_time_diff(strtotime($submission->submission_date)) . ' ' . __('ago', 'wp-form-data-collector') . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        
        echo '<p><a href="' . admin_url('admin.php?page=wpfdc-submissions') . '">' . __('View all submissions', 'wp-form-data-collector') . ' →</a></p>';
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['wpfdc_notice'])) {
            $type = sanitize_text_field($_GET['wpfdc_notice']);
            $message = '';
            
            switch ($type) {
                case 'deleted':
                    $message = __('Submission deleted successfully.', 'wp-form-data-collector');
                    break;
                case 'marked_read':
                    $message = __('Submission marked as read.', 'wp-form-data-collector');
                    break;
                case 'marked_replied':
                    $message = __('Submission marked as replied.', 'wp-form-data-collector');
                    break;
                case 'notes_saved':
                    $message = __('Notes saved successfully.', 'wp-form-data-collector');
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            }
        }
    }
    
    /**
     * Add notice
     */
    private function add_notice($type, $message) {
        // Store in session or query parameter
        wp_redirect(add_query_arg('wpfdc_notice', $type, wp_get_referer()));
        exit;
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links($links) {
        $action_links = [
            'settings' => '<a href="' . admin_url('admin.php?page=wpfdc-settings') . '">' . __('Settings', 'wp-form-data-collector') . '</a>',
            'submissions' => '<a href="' . admin_url('admin.php?page=wpfdc-submissions') . '">' . __('Submissions', 'wp-form-data-collector') . '</a>',
        ];
        
        return array_merge($action_links, $links);
    }
}

// Include WP_List_Table class
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Submissions table class
 */
class WPFDC_Submissions_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Submission', 'wp-form-data-collector'),
            'plural' => __('Submissions', 'wp-form-data-collector'),
            'ajax' => false,
        ]);
    }
    
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $args = [
            'per_page' => $per_page,
            'page' => $current_page,
            'status' => $status,
        ];
        
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['search'] = sanitize_text_field($_REQUEST['s']);
        }
        
        $this->items = WPFDC_Database::get_submissions($args);
        
        $total_items = WPFDC_Database::get_count($args);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
    
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', 'wp-form-data-collector'),
            'email' => __('Email', 'wp-form-data-collector'),
            'phone' => __('Phone', 'wp-form-data-collector'),
            'form_name' => __('Form', 'wp-form-data-collector'),
            'submission_date' => __('Date', 'wp-form-data-collector'),
            'status' => __('Status', 'wp-form-data-collector'),
        ];
    }
    
    protected function get_sortable_columns() {
        return [
            'name' => ['name', false],
            'email' => ['email', false],
            'submission_date' => ['submission_date', true],
        ];
    }
    
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
                $actions = [
                    'view' => sprintf(
                        '<a href="%s">%s</a>',
                        admin_url('admin.php?page=wpfdc-submissions&action=view&id=' . $item->id),
                        __('View', 'wp-form-data-collector')
                    ),
                    'delete' => sprintf(
                        '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                        admin_url('admin.php?page=wpfdc-submissions&action=delete&id=' . $item->id . '&_wpnonce=' . wp_create_nonce('wpfdc_action_' . $item->id)),
                        __('Are you sure?', 'wp-form-data-collector'),
                        __('Delete', 'wp-form-data-collector')
                    ),
                ];
                
                return sprintf(
                    '<strong><a href="%s">%s</a></strong>%s',
                    admin_url('admin.php?page=wpfdc-submissions&action=view&id=' . $item->id),
                    esc_html($item->name ?: __('Anonymous', 'wp-form-data-collector')),
                    $this->row_actions($actions)
                );
                
            case 'email':
                return esc_html($item->email);
                
            case 'phone':
                return esc_html($item->phone);
                
            case 'form_name':
                return esc_html($item->form_name) . '<br><small>' . esc_html($item->form_type) . '</small>';
                
            case 'submission_date':
                return date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($item->submission_date)
                );
                
            case 'status':
                $status_classes = [
                    'new' => 'status-new',
                    'read' => 'status-read',
                    'replied' => 'status-replied',
                    'spam' => 'status-spam',
                ];
                
                $class = isset($status_classes[$item->status]) ? $status_classes[$item->status] : '';
                return '<span class="' . $class . '">' . esc_html(ucfirst($item->status)) . '</span>';
                
            default:
                return print_r($item, true);
        }
    }
    
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="submission[]" value="%s" />',
            $item->id
        );
    }
    
    protected function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wp-form-data-collector'),
            'mark_read' => __('Mark as Read', 'wp-form-data-collector'),
            'mark_replied' => __('Mark as Replied', 'wp-form-data-collector'),
        ];
    }
}