<?php
/**
 * RestroReach - Admin Tools
 *
 * @package RestroReach
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin tools class
 *
 * Provides database management tools including status viewer, table reset,
 * sample data generation, and database repair functionality.
 *
 * @class RDM_Admin_Tools
 * @version 1.0.0
 */
class RDM_Admin_Tools {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Admin_Tools|null
     */
    private static ?RDM_Admin_Tools $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database
     */
    private RDM_Database $database;
    
    /**
     * Database test functionality status
     *
     * @var bool
     */
    private bool $database_test_available = false;
    
    /**
     * Main RDM_Admin_Tools Instance
     *
     * @return RDM_Admin_Tools Main instance
     */
    public static function instance(): RDM_Admin_Tools {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->database = RDM_Database::instance();
        $this->database_test_available = false; // Database test class not implemented yet
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers
        add_action('wp_ajax_rdm_run_database_test', array($this, 'ajax_run_database_test'));
        add_action('wp_ajax_rdm_generate_sample_data', array($this, 'ajax_generate_sample_data'));
        add_action('wp_ajax_rdm_reset_tables', array($this, 'ajax_reset_tables'));
        add_action('wp_ajax_rdm_repair_database', array($this, 'ajax_repair_database'));
        add_action('wp_ajax_rdm_run_health_check', array($this, 'ajax_run_health_check'));
        add_action('wp_ajax_rdm_cleanup_data', array($this, 'ajax_cleanup_data'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'restroreach-dashboard',
            __('Database Tools', 'restaurant-delivery-manager'),
            __('Database Tools', 'restaurant-delivery-manager'),
            'manage_options',
            'restroreach-database-tools',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook_suffix Current admin page
     * @return void
     */
    public function enqueue_admin_scripts(string $hook_suffix): void {
        if (strpos($hook_suffix, 'restroreach-database-tools') === false) {
            return;
        }
        
        wp_enqueue_script(
            'rdm-admin-tools',
            RDM_PLUGIN_URL . 'assets/js/admin-tools.js',
            array('jquery'),
            RDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'rdm-admin-tools',
            RDM_PLUGIN_URL . 'assets/css/admin-tools.css',
            array(),
            RDM_VERSION
        );
        
        wp_localize_script('rdm-admin-tools', 'rdmAdminTools', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_admin_tools'),
            'strings' => array(
                'confirmReset' => __('Are you sure you want to reset all database tables? This action cannot be undone!', 'restaurant-delivery-manager'),
                'confirmCleanup' => __('Are you sure you want to clean up old data? This action cannot be undone!', 'restaurant-delivery-manager'),
                'processing' => __('Processing...', 'restaurant-delivery-manager'),
                'success' => __('Operation completed successfully!', 'restaurant-delivery-manager'),
                'error' => __('An error occurred. Please try again.', 'restaurant-delivery-manager'),
            ),
        ));
    }
    
    /**
     * Render admin page
     *
     * @return void
     */
    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Get database statistics
        $stats = $this->database->get_statistics();
        $db_version = $this->database->get_db_version();
        $needs_upgrade = $this->database->needs_upgrade();
        
        ?>
        <div class="wrap rr-admin-tools">
            <h1><?php esc_html_e('RestroReach Database Tools', 'restaurant-delivery-manager'); ?></h1>
            
            <div class="rr-admin-tools-container">
                <!-- Database Status Section -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Database Status', 'restaurant-delivery-manager'); ?></h2>
                    
                    <div class="rr-status-grid">
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Database Version:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($db_version); ?></span>
                            <?php if ($needs_upgrade): ?>
                                <span class="rr-badge rr-badge-warning"><?php esc_html_e('Upgrade Available', 'restaurant-delivery-manager'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Total Agents:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($stats['totals']['agents']); ?></span>
                        </div>
                        
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Active Agents:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($stats['totals']['active_agents']); ?></span>
                        </div>
                        
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Total Deliveries:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($stats['totals']['total_deliveries']); ?></span>
                        </div>
                        
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Active Deliveries:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($stats['totals']['active_deliveries']); ?></span>
                        </div>
                        
                        <div class="rr-status-item">
                            <span class="label"><?php esc_html_e('Delivery Areas:', 'restaurant-delivery-manager'); ?></span>
                            <span class="value"><?php echo esc_html($stats['totals']['delivery_areas']); ?></span>
                        </div>
                    </div>
                    
                    <h3><?php esc_html_e('Table Statistics', 'restaurant-delivery-manager'); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table', 'restaurant-delivery-manager'); ?></th>
                                <th><?php esc_html_e('Records', 'restaurant-delivery-manager'); ?></th>
                                <th><?php esc_html_e('Status', 'restaurant-delivery-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['tables'] as $table => $count): ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $table))); ?></td>
                                <td><?php echo esc_html(number_format($count)); ?></td>
                                <td>
                                    <span class="rr-badge rr-badge-success"><?php esc_html_e('Active', 'restaurant-delivery-manager'); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Database Health Check -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Database Health Check', 'restaurant-delivery-manager'); ?></h2>
                    
                    <p><?php esc_html_e('Run a comprehensive health check on your database to identify any issues.', 'restaurant-delivery-manager'); ?></p>
                    
                    <button type="button" class="button button-secondary" id="rr-run-health-check">
                        <?php esc_html_e('Run Health Check', 'restaurant-delivery-manager'); ?>
                    </button>
                    
                    <div id="rr-health-check-results" class="rr-results-container" style="display: none;">
                        <h3><?php esc_html_e('Health Check Results', 'restaurant-delivery-manager'); ?></h3>
                        <div class="rr-results-content"></div>
                    </div>
                </div>
                
                <!-- Database Testing -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Database Testing', 'restaurant-delivery-manager'); ?></h2>
                    
                    <p><?php esc_html_e('Run comprehensive tests on database operations including CRUD operations, foreign keys, and data integrity.', 'restaurant-delivery-manager'); ?></p>
                    
                    <button type="button" class="button button-secondary" id="rr-run-database-test">
                        <?php esc_html_e('Run Database Tests', 'restaurant-delivery-manager'); ?>
                    </button>
                    
                    <div id="rr-test-results" class="rr-results-container" style="display: none;">
                        <h3><?php esc_html_e('Test Results', 'restaurant-delivery-manager'); ?></h3>
                        <div class="rr-results-content"></div>
                    </div>
                </div>
                
                <!-- Sample Data Generator -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Sample Data Generator', 'restaurant-delivery-manager'); ?></h2>
                    
                    <p><?php esc_html_e('Generate sample data for testing purposes. This will create test agents, delivery areas, and sample orders.', 'restaurant-delivery-manager'); ?></p>
                    
                    <div class="rr-warning-box">
                        <p><strong><?php esc_html_e('Warning:', 'restaurant-delivery-manager'); ?></strong> <?php esc_html_e('This will create test data in your database. Use only in development environments.', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    
                    <button type="button" class="button button-secondary" id="rr-generate-sample-data">
                        <?php esc_html_e('Generate Sample Data', 'restaurant-delivery-manager'); ?>
                    </button>
                    
                    <div id="rr-sample-data-results" class="rr-results-container" style="display: none;">
                        <h3><?php esc_html_e('Sample Data Created', 'restaurant-delivery-manager'); ?></h3>
                        <div class="rr-results-content"></div>
                    </div>
                </div>
                
                <!-- Database Maintenance -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Database Maintenance', 'restaurant-delivery-manager'); ?></h2>
                    
                    <h3><?php esc_html_e('Cleanup Old Data', 'restaurant-delivery-manager'); ?></h3>
                    <p><?php esc_html_e('Remove old location tracking data and other temporary records.', 'restaurant-delivery-manager'); ?></p>
                    
                    <form id="rr-cleanup-form" class="rr-inline-form">
                        <label>
                            <?php esc_html_e('Delete data older than:', 'restaurant-delivery-manager'); ?>
                            <input type="number" id="rr-cleanup-days" value="30" min="1" max="365" />
                            <?php esc_html_e('days', 'restaurant-delivery-manager'); ?>
                        </label>
                        <button type="button" class="button button-secondary" id="rr-cleanup-data">
                            <?php esc_html_e('Clean Up Data', 'restaurant-delivery-manager'); ?>
                        </button>
                    </form>
                    
                    <h3><?php esc_html_e('Repair Database', 'restaurant-delivery-manager'); ?></h3>
                    <p><?php esc_html_e('Attempt to repair any database issues including missing tables, indexes, or orphaned records.', 'restaurant-delivery-manager'); ?></p>
                    
                    <button type="button" class="button button-secondary" id="rr-repair-database">
                        <?php esc_html_e('Repair Database', 'restaurant-delivery-manager'); ?>
                    </button>
                    
                    <div id="rr-maintenance-results" class="rr-results-container" style="display: none;">
                        <h3><?php esc_html_e('Maintenance Results', 'restaurant-delivery-manager'); ?></h3>
                        <div class="rr-results-content"></div>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="rr-card rr-danger-zone">
                    <h2><?php esc_html_e('Danger Zone', 'restaurant-delivery-manager'); ?></h2>
                    
                    <div class="rr-danger-box">
                        <h3><?php esc_html_e('Reset Database Tables', 'restaurant-delivery-manager'); ?></h3>
                        <p><?php esc_html_e('This will drop all RestroReach tables and recreate them. All data will be lost!', 'restaurant-delivery-manager'); ?></p>
                        
                        <button type="button" class="button button-danger" id="rr-reset-tables">
                            <?php esc_html_e('Reset All Tables', 'restaurant-delivery-manager'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Database Logs -->
                <div class="rr-card">
                    <h2><?php esc_html_e('Database Logs', 'restaurant-delivery-manager'); ?></h2>
                    
                    <?php
                    $logs = $this->database->get_database_logs('', 20);
                    if (!empty($logs)):
                    ?>
                    <div class="rr-logs-container">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Timestamp', 'restaurant-delivery-manager'); ?></th>
                                    <th><?php esc_html_e('Event Type', 'restaurant-delivery-manager'); ?></th>
                                    <th><?php esc_html_e('Details', 'restaurant-delivery-manager'); ?></th>
                                    <th><?php esc_html_e('User', 'restaurant-delivery-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['timestamp']))); ?></td>
                                    <td>
                                        <span class="rr-badge rr-badge-<?php echo esc_attr($this->get_log_badge_class($log['event_type'])); ?>">
                                            <?php echo esc_html($log['event_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['data'])): ?>
                                            <details>
                                                <summary><?php esc_html_e('View Details', 'restaurant-delivery-manager'); ?></summary>
                                                <pre><?php echo esc_html(wp_json_encode($log['data'], JSON_PRETTY_PRINT)); ?></pre>
                                            </details>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($log['user_id']) {
                                            $user = get_userdata($log['user_id']);
                                            echo $user ? esc_html($user->display_name) : __('Unknown', 'restaurant-delivery-manager');
                                        } else {
                                            echo __('System', 'restaurant-delivery-manager');
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p><?php esc_html_e('No database logs found.', 'restaurant-delivery-manager'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get badge class for log event type
     *
     * @param string $event_type Event type
     * @return string Badge class
     */
    private function get_log_badge_class(string $event_type): string {
        $error_types = array('migration_failed', 'migration_error', 'health_check_failed');
        $success_types = array('migration_success', 'database_repair', 'health_check');
        
        if (in_array($event_type, $error_types, true)) {
            return 'error';
        } elseif (in_array($event_type, $success_types, true)) {
            return 'success';
        } else {
            return 'info';
        }
    }
    
    /**
     * AJAX handler for running database tests
     *
     * @return void
     */
    public function ajax_run_database_test(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Check if database test functionality is available
        if (!$this->database_test_available) {
            wp_send_json_error(__('Database test functionality is not yet implemented.', 'restaurant-delivery-manager'));
            return;
        }
        
        // TODO: Implement database testing functionality
        wp_send_json_error(__('Database test functionality coming soon.', 'restaurant-delivery-manager'));
    }
    
    /**
     * AJAX handler for generating sample data
     *
     * @return void
     */
    public function ajax_generate_sample_data(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Check if database test functionality is available
        if (!$this->database_test_available) {
            wp_send_json_error(__('Sample data generation functionality is not yet implemented.', 'restaurant-delivery-manager'));
            return;
        }
        
        // TODO: Implement sample data generation functionality
        wp_send_json_error(__('Sample data generation functionality coming soon.', 'restaurant-delivery-manager'));
    }
    
    /**
     * AJAX handler for resetting tables
     *
     * @return void
     */
    public function ajax_reset_tables(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Drop and recreate tables
        $this->database->drop_tables();
        $this->database->create_tables();
        
        wp_send_json_success(array(
            'message' => __('All tables have been reset successfully.', 'restaurant-delivery-manager'),
        ));
    }
    
    /**
     * AJAX handler for repairing database
     *
     * @return void
     */
    public function ajax_repair_database(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Repair database
        $results = $this->database->repair_database();
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for running health check
     *
     * @return void
     */
    public function ajax_run_health_check(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        // Run health check
        $results = $this->database->health_check();
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cleaning up old data
     *
     * @return void
     */
    public function ajax_cleanup_data(): void {
        // Security check
        if (!check_ajax_referer('rdm_admin_tools', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        
        // Cleanup old locations
        $deleted = $this->database->cleanup_old_locations($days);
        
        wp_send_json_success(array(
            'locations_deleted' => $deleted,
            'message' => sprintf(
                __('Deleted %d location records older than %d days.', 'restaurant-delivery-manager'),
                $deleted,
                $days
            ),
        ));
    }
} 