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
        $this->database_test_available = true; // Database test functionality now implemented
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers
        add_action('wp_ajax_rdm_run_database_test', array($this, 'ajax_run_database_test'));
        add_action('wp_ajax_rdm_generate_sample_data', array($this, 'ajax_generate_sample_data'));
        add_action('wp_ajax_rdm_reset_tables', array($this, 'ajax_reset_tables'));
        add_action('wp_ajax_rdm_repair_database', array($this, 'ajax_repair_database'));
        add_action('wp_ajax_rdm_run_health_check', array($this, 'ajax_run_health_check'));
        add_action('wp_ajax_rdm_cleanup_data', array($this, 'ajax_cleanup_data'));
        add_action('wp_ajax_rdm_initialize_user_roles', array($this, 'ajax_initialize_user_roles'));
        add_action('wp_ajax_rdm_create_test_agent', array($this, 'ajax_create_test_agent'));
        add_action('wp_ajax_rdm_create_missing_agents', array($this, 'ajax_create_missing_agents'));
        
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
                
                <!-- User Setup Tools -->
                <div class="rr-card">
                    <h2><?php esc_html_e('User Setup Tools', 'restaurant-delivery-manager'); ?></h2>
                    
                    <p><?php esc_html_e('Initialize user roles and create test accounts for development and testing.', 'restaurant-delivery-manager'); ?></p>
                    
                    <div class="rr-user-setup-actions">
                        <div class="rr-action-group">
                            <h3><?php esc_html_e('Initialize User Roles', 'restaurant-delivery-manager'); ?></h3>
                            <p><?php esc_html_e('Create the delivery agent and restaurant manager user roles with proper capabilities.', 'restaurant-delivery-manager'); ?></p>
                            <button type="button" class="button button-secondary" id="rr-initialize-user-roles">
                                <?php esc_html_e('Initialize User Roles', 'restaurant-delivery-manager'); ?>
                            </button>
                        </div>
                        
                        <div class="rr-action-group">
                            <h3><?php esc_html_e('Create Test Agent', 'restaurant-delivery-manager'); ?></h3>
                            <p><?php esc_html_e('Create a test delivery agent account for testing the mobile interface.', 'restaurant-delivery-manager'); ?></p>
                            <button type="button" class="button button-secondary" id="rr-create-test-agent">
                                <?php esc_html_e('Create Test Agent', 'restaurant-delivery-manager'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div id="rr-user-setup-results" class="rr-results-container" style="display: none;">
                        <h3><?php esc_html_e('User Setup Results', 'restaurant-delivery-manager'); ?></h3>
                        <div class="rr-results-content"></div>
                    </div>
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
        
        // Implement basic database connectivity test
        global $wpdb;
        
        $results = array();
        
        // Test 1: Basic connectivity
        $test_query = $wpdb->get_var("SELECT 1");
        if ($test_query === '1') {
            $results['connectivity'] = array(
                'status' => 'success',
                'message' => __('Database connection successful', 'restaurant-delivery-manager')
            );
        } else {
            $results['connectivity'] = array(
                'status' => 'error',
                'message' => __('Database connection failed', 'restaurant-delivery-manager')
            );
        }
        
        // Test 2: Check plugin tables
        $plugin_tables = array(
            $wpdb->prefix . 'rr_location_tracking',
            $wpdb->prefix . 'rr_order_assignments',
            $wpdb->prefix . 'rr_delivery_agents',
            $wpdb->prefix . 'rr_payment_transactions',
            $wpdb->prefix . 'rr_cash_reconciliation'
        );
        
        foreach ($plugin_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($table_exists) {
                $results['table_' . str_replace($wpdb->prefix, '', $table)] = array(
                    'status' => 'success',
                    'message' => sprintf(__('Table %s exists', 'restaurant-delivery-manager'), $table)
                );
            } else {
                $results['table_' . str_replace($wpdb->prefix, '', $table)] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Table %s missing', 'restaurant-delivery-manager'), $table)
                );
            }
        }
        
        // Test 3: Check WooCommerce integration
        if (class_exists('WooCommerce')) {
            $wc_tables = array(
                $wpdb->prefix . 'wc_order_stats',
                $wpdb->prefix . 'wc_product_meta_lookup'
            );
            
            foreach ($wc_tables as $table) {
                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                if ($table_exists) {
                    $results['wc_' . str_replace($wpdb->prefix, '', $table)] = array(
                        'status' => 'success',
                        'message' => sprintf(__('WooCommerce table %s exists', 'restaurant-delivery-manager'), $table)
                    );
                } else {
                    $results['wc_' . str_replace($wpdb->prefix, '', $table)] = array(
                        'status' => 'warning',
                        'message' => sprintf(__('WooCommerce table %s missing (HPOS mode?)', 'restaurant-delivery-manager'), $table)
                    );
                }
            }
        }
        
        wp_send_json_success($results);
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
        
        // Generate sample data for testing
        $results = array();
        
        try {
            // Generate sample delivery agents
            $agent_count = 3;
            for ($i = 1; $i <= $agent_count; $i++) {
                $username = 'agent' . $i;
                $email = 'agent' . $i . '@example.com';
                
                // Check if user already exists
                $existing_user = get_user_by('email', $email);
                if (!$existing_user) {
                    $user_id = wp_create_user($username, 'password123', $email);
                    if (!is_wp_error($user_id)) {
                        $user = get_userdata($user_id);
                        $user->set_role('delivery_agent');
                        
                        // Add agent metadata
                        update_user_meta($user_id, 'first_name', 'Delivery');
                        update_user_meta($user_id, 'last_name', 'Agent ' . $i);
                        update_user_meta($user_id, 'billing_phone', '+1-555-123-' . str_pad($i, 4, '0', STR_PAD_LEFT));
                        
                        $results['agents'][] = array(
                            'id' => $user_id,
                            'name' => 'Delivery Agent ' . $i,
                            'email' => $email
                        );
                    }
                } else {
                    $results['agents'][] = array(
                        'id' => $existing_user->ID,
                        'name' => $existing_user->display_name,
                        'email' => $email,
                        'note' => __('Already exists', 'restaurant-delivery-manager')
                    );
                }
            }
            
            // Generate sample location data for agents
            global $wpdb;
            $agents = get_users(array('role' => 'delivery_agent'));
            
            foreach ($agents as $agent) {
                // Generate 5 sample location points around NYC
                $base_lat = 40.7128;
                $base_lng = -74.0060;
                
                for ($j = 0; $j < 5; $j++) {
                    $lat = $base_lat + (rand(-100, 100) / 1000); // Small random offset
                    $lng = $base_lng + (rand(-100, 100) / 1000);
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'rr_location_tracking',
                        array(
                            'agent_id' => $agent->ID,
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'accuracy' => rand(5, 25),
                            'battery_level' => rand(20, 95),
                            'created_at' => date('Y-m-d H:i:s', time() - ($j * 3600)) // Last 5 hours
                        ),
                        array('%d', '%f', '%f', '%f', '%d', '%s')
                    );
                }
            }
            
            $results['locations'] = array(
                'count' => count($agents) * 5,
                'message' => __('Sample location data generated', 'restaurant-delivery-manager')
            );
            
            // Generate sample orders if WooCommerce is active
            if (class_exists('WooCommerce')) {
                $order_count = 5;
                for ($i = 1; $i <= $order_count; $i++) {
                    $order = wc_create_order();
                    
                    // Add sample product
                    $product = wc_get_product_by_sku('sample-product');
                    if (!$product) {
                        // Create sample product if it doesn't exist
                        $product = new WC_Product_Simple();
                        $product->set_name('Sample Pizza');
                        $product->set_sku('sample-product');
                        $product->set_price(15.99);
                        $product->save();
                    }
                    
                    $order->add_product($product, 1);
                    
                    // Set sample address
                    $order->set_address(array(
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'address_1' => '123 Sample St',
                        'city' => 'New York',
                        'state' => 'NY',
                        'postcode' => '10001',
                        'country' => 'US'
                    ), 'shipping');
                    
                    $order->set_status('processing');
                    $order->save();
                    
                    $results['orders'][] = array(
                        'id' => $order->get_id(),
                        'status' => $order->get_status(),
                        'total' => $order->get_total()
                    );
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Sample data generated successfully', 'restaurant-delivery-manager'),
                'data' => $results
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error generating sample data', 'restaurant-delivery-manager'),
                'error' => $e->getMessage()
            ));
        }
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
    
    /**
     * AJAX handler for initializing user roles
     *
     * @return void
     */
    public function ajax_initialize_user_roles(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_tools')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        try {
            // Initialize user roles
            $user_roles = RDM_User_Roles::instance();
            $user_roles->create_roles();
            
            wp_send_json_success(array(
                'message' => __('User roles created successfully', 'restaurant-delivery-manager'),
                'roles_created' => array('restaurant_manager', 'delivery_agent')
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: User roles initialization failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for creating a test delivery agent
     *
     * @return void
     */
    public function ajax_create_test_agent(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_tools')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        try {
            $username = 'agent1';
            $email = 'agent1@example.com';
            $password = 'password123';
            
            // Check if user already exists
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                wp_send_json_success(array(
                    'message' => __('Test agent already exists', 'restaurant-delivery-manager'),
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'note' => __('User already exists', 'restaurant-delivery-manager')
                ));
                return;
            }
            
            // Create user
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Set user role to delivery agent
            $user = new WP_User($user_id);
            $user->set_role('delivery_agent');
            
            // Add user metadata
            update_user_meta($user_id, 'first_name', 'Test');
            update_user_meta($user_id, 'last_name', 'Agent');
            update_user_meta($user_id, 'billing_phone', '+1-555-123-0001');
            
            // Create agent record in database
            $agent_id = $this->database->create_agent($user_id, '+1-555-123-0001', 'bike');
            
            wp_send_json_success(array(
                'message' => __('Test delivery agent created successfully', 'restaurant-delivery-manager'),
                'user_id' => $user_id,
                'agent_id' => $agent_id,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'login_url' => home_url('/delivery-agent/login')
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: Test agent creation failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Create missing agent records
     */
    public function ajax_create_missing_agents() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_tools')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'restaurant-delivery-manager')));
        }
        
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'restaurant-delivery-manager')));
        }
        
        try {
            $database = RDM_Database::instance();
            $created_count = 0;
            
            // Get all users with delivery_agent role
            $delivery_users = get_users(array(
                'role' => 'delivery_agent',
                'fields' => array('ID', 'display_name', 'user_email')
            ));
            
            foreach ($delivery_users as $user) {
                // Check if agent record exists
                $existing_agent = $database->get_agent_by_user_id($user->ID);
                
                if (!$existing_agent) {
                    // Create agent record with default values
                    $phone = get_user_meta($user->ID, 'rdm_agent_phone', true) ?: '';
                    $vehicle_type = get_user_meta($user->ID, 'rdm_vehicle_type', true) ?: 'bike';
                    
                    if (empty($phone)) {
                        $phone = '000-000-0000'; // Default placeholder
                    }
                    
                    $agent_id = $database->create_agent($user->ID, $phone, $vehicle_type);
                    
                    if ($agent_id) {
                        $created_count++;
                        error_log("RestroReach: Created agent record for user {$user->display_name} (ID: {$user->ID})");
                    }
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d missing agent records created successfully.', 'restaurant-delivery-manager'), $created_count),
                'created_count' => $created_count
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: Error creating missing agent records - ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Error creating agent records: ', 'restaurant-delivery-manager') . $e->getMessage()));
        }
    }
} 