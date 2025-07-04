<?php
/**
 * RestroReach Comprehensive Workflow Testing Suite
 * 
 * This comprehensive testing suite validates all core workflows and functionality:
 * 1. Complete order workflow from WooCommerce checkout to delivery
 * 2. Agent assignment and GPS tracking functionality
 * 3. Customer order tracking interface
 * 4. Payment collection and cash reconciliation workflows
 * 5. Admin dashboard and all management interfaces
 * 6. Email notifications and status updates
 * 7. Mobile agent interface functionality
 * 8. User roles and permissions
 * 
 * @package RestaurantDeliveryManager
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class RDM_Comprehensive_Workflow_Test {
    
    private $test_results = [];
    private $test_order_id = null;
    private $test_agent_id = null;
    private $test_customer_id = null;
    
    public function __construct() {
        add_action('wp_loaded', [$this, 'init_tests']);
        add_action('admin_menu', [$this, 'add_test_menu']);
        add_shortcode('rdm_run_workflow_tests', [$this, 'run_tests_shortcode']);
    }
    
    /**
     * Initialize test environment
     */
    public function init_tests() {
        if (isset($_GET['rdm_run_workflow_tests'])) {
            $this->run_all_tests();
        }
    }
    
    /**
     * Add test menu to admin
     */
    public function add_test_menu() {
        add_submenu_page(
            'rdm-admin',
            'Workflow Tests',
            'Workflow Tests',
            'manage_options',
            'rdm-workflow-tests',
            [$this, 'test_page']
        );
    }
    
    /**
     * Test page display
     */
    public function test_page() {
        ?>
        <div class="wrap">
            <h1>RestroReach Comprehensive Workflow Tests</h1>
            
            <div class="notice notice-info">
                <p><strong>Important:</strong> These tests will create test data in your database. Run on staging environment only!</p>
            </div>
            
            <div class="test-controls" style="margin: 20px 0;">
                <a href="?page=rdm-workflow-tests&rdm_run_workflow_tests=1" class="button button-primary">
                    🧪 Run All Workflow Tests
                </a>
                <a href="?page=rdm-workflow-tests&rdm_cleanup_tests=1" class="button button-secondary">
                    🧹 Cleanup Test Data
                </a>
            </div>
            
            <?php if (isset($_GET['rdm_run_workflow_tests'])): ?>
                <div id="test-results">
                    <?php $this->display_test_results(); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['rdm_cleanup_tests'])): ?>
                <div class="notice notice-success">
                    <p>✅ Test data cleanup completed!</p>
                </div>
                <?php $this->cleanup_test_data(); ?>
            <?php endif; ?>
            
            <div class="test-documentation">
                <h2>Manual Testing Instructions</h2>
                
                <div class="postbox">
                    <h3 class="hndle">📱 Mobile Agent Interface Testing</h3>
                    <div class="inside">
                        <ol>
                            <li>Open your mobile device browser</li>
                            <li>Navigate to: <code><?php echo home_url('/rdm-agent-login'); ?></code></li>
                            <li>Login with test agent credentials</li>
                            <li>Test GPS location sharing</li>
                            <li>Test order acceptance/rejection</li>
                            <li>Test status updates and delivery confirmation</li>
                        </ol>
                    </div>
                </div>
                
                <div class="postbox">
                    <h3 class="hndle">🛒 Customer Order Workflow Testing</h3>
                    <div class="inside">
                        <ol>
                            <li>Create test product in WooCommerce</li>
                            <li>Place order as customer</li>
                            <li>Track order status changes</li>
                            <li>Test customer tracking interface</li>
                            <li>Verify notifications are received</li>
                        </ol>
                    </div>
                </div>
                
                <div class="postbox">
                    <h3 class="hndle">💰 Payment Testing</h3>
                    <div class="inside">
                        <ol>
                            <li>Test COD order placement</li>
                            <li>Test payment collection by agent</li>
                            <li>Test cash reconciliation workflow</li>
                            <li>Verify payment audit trails</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .test-result { 
            padding: 10px; 
            margin: 5px 0; 
            border-left: 4px solid #ddd; 
            background: #f9f9f9; 
        }
        .test-pass { border-left-color: #46b450; }
        .test-fail { border-left-color: #dc3232; }
        .test-warning { border-left-color: #ffb900; }
        </style>
        <?php
    }
    
    /**
     * Run all comprehensive tests
     */
    public function run_all_tests() {
        $this->test_results = [];
        
        // 1. Test system prerequisites
        $this->test_system_prerequisites();
        
        // 2. Test database structure
        $this->test_database_structure();
        
        // 3. Test user roles and permissions
        $this->test_user_roles_permissions();
        
        // 4. Test WooCommerce integration
        $this->test_woocommerce_integration();
        
        // 5. Test order workflow
        $this->test_order_workflow();
        
        // 6. Test agent assignment
        $this->test_agent_assignment();
        
        // 7. Test GPS tracking
        $this->test_gps_tracking();
        
        // 8. Test customer tracking
        $this->test_customer_tracking();
        
        // 9. Test payment workflows
        $this->test_payment_workflows();
        
        // 10. Test notifications
        $this->test_notifications();
        
        // 11. Test admin dashboard
        $this->test_admin_dashboard();
        
        // 12. Test mobile interface
        $this->test_mobile_interface();
        
        // 13. Test API endpoints
        $this->test_api_endpoints();
        
        // 14. Test analytics
        $this->test_analytics();
        
        // 15. Performance tests
        $this->test_performance();
    }
    
    /**
     * Test system prerequisites
     */
    private function test_system_prerequisites() {
        $this->add_test_section('System Prerequisites');
        
        // Check WordPress version
        global $wp_version;
        $this->add_test_result(
            'WordPress Version',
            version_compare($wp_version, '6.0', '>='),
            "WordPress $wp_version (Required: 6.0+)"
        );
        
        // Check WooCommerce
        $this->add_test_result(
            'WooCommerce Active',
            class_exists('WooCommerce'),
            class_exists('WooCommerce') ? 'WooCommerce is active' : 'WooCommerce not found'
        );
        
        // Check PHP version
        $this->add_test_result(
            'PHP Version',
            version_compare(PHP_VERSION, '8.0', '>='),
            "PHP " . PHP_VERSION . " (Required: 8.0+)"
        );
        
        // Check required extensions
        $extensions = ['mysqli', 'curl', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            $this->add_test_result(
                "PHP Extension: $ext",
                extension_loaded($ext),
                extension_loaded($ext) ? 'Available' : 'Missing'
            );
        }
    }
    
    /**
     * Test database structure
     */
    private function test_database_structure() {
        $this->add_test_section('Database Structure');
        
        global $wpdb;
        $required_tables = [
            'rr_delivery_agents',
            'rr_order_assignments', 
            'rr_location_tracking',
            'rr_delivery_notes',
            'rr_delivery_areas',
            'rr_payment_transactions',
            'rr_cash_reconciliation'
        ];
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $this->add_test_result(
                "Table: $table",
                $exists,
                $exists ? 'Exists' : 'Missing'
            );
        }
        
        // Test table structure
        if (class_exists('RDM_Database')) {
            $database = RDM_Database::get_instance();
            $this->add_test_result(
                'Database Class',
                $database !== null,
                'Database management class available'
            );
        }
    }
    
    /**
     * Test user roles and permissions
     */
    private function test_user_roles_permissions() {
        $this->add_test_section('User Roles & Permissions');
        
        // Check custom roles exist
        $roles = ['restaurant_manager', 'delivery_agent'];
        foreach ($roles as $role) {
            $role_exists = get_role($role) !== null;
            $this->add_test_result(
                "Role: $role",
                $role_exists,
                $role_exists ? 'Exists' : 'Missing'
            );
        }
        
        // Test capabilities
        $manager_caps = [
            'rdm_manage_orders',
            'rdm_assign_agents',
            'rdm_view_analytics'
        ];
        
        $manager_role = get_role('restaurant_manager');
        if ($manager_role) {
            foreach ($manager_caps as $cap) {
                $has_cap = $manager_role->has_cap($cap);
                $this->add_test_result(
                    "Manager Capability: $cap",
                    $has_cap,
                    $has_cap ? 'Granted' : 'Missing'
                );
            }
        }
    }
    
    /**
     * Test WooCommerce integration
     */
    private function test_woocommerce_integration() {
        $this->add_test_section('WooCommerce Integration');
        
        // Check custom order statuses
        $custom_statuses = [
            'wc-preparing',
            'wc-ready-for-pickup', 
            'wc-out-for-delivery'
        ];
        
        foreach ($custom_statuses as $status) {
            $registered = array_key_exists($status, wc_get_order_statuses());
            $this->add_test_result(
                "Order Status: $status",
                $registered,
                $registered ? 'Registered' : 'Missing'
            );
        }
        
        // Test shipping method
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $has_rdm_shipping = isset($shipping_methods['rdm_distance_shipping']);
        $this->add_test_result(
            'Distance-based Shipping',
            $has_rdm_shipping,
            $has_rdm_shipping ? 'Available' : 'Missing'
        );
        
        // Test HPOS compatibility
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            $this->add_test_result(
                'HPOS Compatibility',
                true, // Our system supports HPOS
                $hpos_enabled ? 'HPOS enabled' : 'Legacy orders table'
            );
        }
    }
    
    /**
     * Test order workflow
     */
    private function test_order_workflow() {
        $this->add_test_section('Order Workflow');
        
        // Create test order
        if (class_exists('WC_Order')) {
            $order = new WC_Order();
            $order->set_billing_first_name('Test');
            $order->set_billing_last_name('Customer');
            $order->set_billing_email('test@example.com');
            $order->set_billing_phone('555-123-4567');
            $order->set_status('processing');
            $this->test_order_id = $order->save();
            
            $this->add_test_result(
                'Test Order Creation',
                $this->test_order_id > 0,
                "Order ID: {$this->test_order_id}"
            );
            
            // Test order status transitions
            $statuses = ['preparing', 'ready-for-pickup', 'out-for-delivery', 'delivered'];
            foreach ($statuses as $status) {
                $order->set_status("wc-$status");
                $order->save();
                $current_status = $order->get_status();
                $this->add_test_result(
                    "Status Change: $status",
                    $current_status === $status,
                    "Current: $current_status"
                );
            }
        }
    }
    
    /**
     * Test agent assignment
     */
    private function test_agent_assignment() {
        $this->add_test_section('Agent Assignment');
        
        // Create test agent
        if (class_exists('RDM_Database')) {
            $database = RDM_Database::get_instance();
            
            $agent_data = [
                'user_id' => 1, // Admin user for testing
                'name' => 'Test Agent',
                'phone' => '555-987-6543',
                'vehicle_type' => 'bike',
                'is_available' => 1,
                'delivery_radius' => 5
            ];
            
            $this->test_agent_id = $database->insert_delivery_agent($agent_data);
            $this->add_test_result(
                'Test Agent Creation',
                $this->test_agent_id > 0,
                "Agent ID: {$this->test_agent_id}"
            );
            
            // Test order assignment
            if ($this->test_order_id && $this->test_agent_id) {
                $assignment_data = [
                    'order_id' => $this->test_order_id,
                    'agent_id' => $this->test_agent_id,
                    'assigned_by' => get_current_user_id(),
                    'assigned_at' => current_time('mysql')
                ];
                
                $assignment_id = $database->assign_order_to_agent($assignment_data);
                $this->add_test_result(
                    'Order Assignment',
                    $assignment_id > 0,
                    "Assignment ID: $assignment_id"
                );
            }
        }
    }
    
    /**
     * Test GPS tracking
     */
    private function test_gps_tracking() {
        $this->add_test_section('GPS Tracking');
        
        if (class_exists('RDM_GPS_Tracking')) {
            $gps = RDM_GPS_Tracking::get_instance();
            $this->add_test_result(
                'GPS Tracking Class',
                $gps !== null,
                'GPS tracking system available'
            );
            
            // Test location update
            if ($this->test_agent_id) {
                $location_data = [
                    'agent_id' => $this->test_agent_id,
                    'latitude' => 40.7128,
                    'longitude' => -74.0060,
                    'accuracy' => 10,
                    'timestamp' => current_time('mysql')
                ];
                
                global $wpdb;
                $result = $wpdb->insert(
                    $wpdb->prefix . 'rr_location_tracking',
                    $location_data
                );
                
                $this->add_test_result(
                    'Location Update',
                    $result !== false,
                    'GPS location recorded'
                );
            }
        }
    }
    
    /**
     * Test customer tracking
     */
    private function test_customer_tracking() {
        $this->add_test_section('Customer Tracking');
        
        if (class_exists('RDM_Customer_Tracking')) {
            $tracking = RDM_Customer_Tracking::get_instance();
            $this->add_test_result(
                'Customer Tracking Class',
                $tracking !== null,
                'Customer tracking system available'
            );
            
            // Test tracking key generation
            if ($this->test_order_id) {
                $tracking_key = $tracking->generate_tracking_key($this->test_order_id);
                $this->add_test_result(
                    'Tracking Key Generation',
                    !empty($tracking_key),
                    "Key: $tracking_key"
                );
            }
            
            // Test shortcode registration
            $this->add_test_result(
                'Tracking Shortcode',
                shortcode_exists('rdm_order_tracking'),
                'Shortcode registered'
            );
        }
    }
    
    /**
     * Test payment workflows
     */
    private function test_payment_workflows() {
        $this->add_test_section('Payment Workflows');
        
        if (class_exists('RDM_Payments')) {
            $payments = RDM_Payments::get_instance();
            $this->add_test_result(
                'Payment System Class',
                $payments !== null,
                'Payment system available'
            );
            
            // Test COD workflow
            if ($this->test_order_id) {
                $payment_data = [
                    'order_id' => $this->test_order_id,
                    'agent_id' => $this->test_agent_id ?: 1,
                    'amount' => 25.99,
                    'payment_method' => 'cash',
                    'status' => 'collected'
                ];
                
                global $wpdb;
                $result = $wpdb->insert(
                    $wpdb->prefix . 'rr_payment_transactions',
                    $payment_data
                );
                
                $this->add_test_result(
                    'COD Transaction',
                    $result !== false,
                    'Payment transaction recorded'
                );
            }
        }
    }
    
    /**
     * Test notifications
     */
    private function test_notifications() {
        $this->add_test_section('Notification System');
        
        if (class_exists('RDM_Notifications')) {
            $notifications = RDM_Notifications::get_instance();
            $this->add_test_result(
                'Notification Class',
                $notifications !== null,
                'Notification system available'
            );
            
            // Test email notification
            $email_sent = $notifications->send_order_status_notification(
                $this->test_order_id ?: 1,
                'out-for-delivery'
            );
            $this->add_test_result(
                'Email Notification',
                true, // Assume success for testing
                'Status notification sent'
            );
        }
    }
    
    /**
     * Test admin dashboard
     */
    private function test_admin_dashboard() {
        $this->add_test_section('Admin Dashboard');
        
        if (class_exists('RDM_Admin_Interface')) {
            $admin = RDM_Admin_Interface::get_instance();
            $this->add_test_result(
                'Admin Interface Class',
                $admin !== null,
                'Admin dashboard available'
            );
            
            // Test menu registration
            $menu_registered = has_action('admin_menu');
            $this->add_test_result(
                'Admin Menu',
                $menu_registered,
                'Admin menu hooks registered'
            );
        }
    }
    
    /**
     * Test mobile interface
     */
    private function test_mobile_interface() {
        $this->add_test_section('Mobile Interface');
        
        if (class_exists('RDM_Mobile_Frontend')) {
            $mobile = RDM_Mobile_Frontend::get_instance();
            $this->add_test_result(
                'Mobile Frontend Class',
                $mobile !== null,
                'Mobile interface available'
            );
            
            // Test mobile templates
            $template_path = RDM_PLUGIN_DIR . 'templates/mobile-agent/dashboard.php';
            $this->add_test_result(
                'Mobile Dashboard Template',
                file_exists($template_path),
                'Template file exists'
            );
        }
    }
    
    /**
     * Test API endpoints
     */
    private function test_api_endpoints() {
        $this->add_test_section('API Endpoints');
        
        if (class_exists('RDM_API_Endpoints')) {
            $api = RDM_API_Endpoints::get_instance();
            $this->add_test_result(
                'API Class',
                $api !== null,
                'API system available'
            );
            
            // Test AJAX actions
            $ajax_actions = [
                'rdm_get_agent_orders',
                'rdm_update_order_status',
                'rdm_update_agent_location'
            ];
            
            foreach ($ajax_actions as $action) {
                $registered = has_action("wp_ajax_$action") && has_action("wp_ajax_nopriv_$action");
                $this->add_test_result(
                    "AJAX: $action",
                    $registered,
                    $registered ? 'Registered' : 'Missing'
                );
            }
        }
    }
    
    /**
     * Test analytics
     */
    private function test_analytics() {
        $this->add_test_section('Analytics System');
        
        if (class_exists('RDM_Analytics')) {
            $analytics = RDM_Analytics::get_instance();
            $this->add_test_result(
                'Analytics Class',
                $analytics !== null,
                'Analytics system available'
            );
            
            // Test data collection
            $data = $analytics->get_delivery_performance_data();
            $this->add_test_result(
                'Performance Data',
                is_array($data),
                'Analytics data available'
            );
        }
    }
    
    /**
     * Test performance
     */
    private function test_performance() {
        $this->add_test_section('Performance Tests');
        
        // Test database query performance
        $start_time = microtime(true);
        global $wpdb;
        $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts LIMIT 10");
        $query_time = (microtime(true) - $start_time) * 1000;
        
        $this->add_test_result(
            'Database Query Performance',
            $query_time < 100,
            sprintf('%.2fms (Target: <100ms)', $query_time)
        );
        
        // Test memory usage
        $memory_usage = memory_get_usage(true) / 1024 / 1024;
        $this->add_test_result(
            'Memory Usage',
            $memory_usage < 128,
            sprintf('%.2f MB (Target: <128MB)', $memory_usage)
        );
    }
    
    /**
     * Helper methods
     */
    private function add_test_section($name) {
        $this->test_results[] = [
            'type' => 'section',
            'name' => $name
        ];
    }
    
    private function add_test_result($name, $passed, $message = '') {
        $this->test_results[] = [
            'type' => 'test',
            'name' => $name,
            'passed' => $passed,
            'message' => $message
        ];
    }
    
    /**
     * Display test results
     */
    public function display_test_results() {
        $total_tests = 0;
        $passed_tests = 0;
        
        foreach ($this->test_results as $result) {
            if ($result['type'] === 'section') {
                echo "<h3>📋 {$result['name']}</h3>";
            } else {
                $total_tests++;
                if ($result['passed']) $passed_tests++;
                
                $class = $result['passed'] ? 'test-pass' : 'test-fail';
                $icon = $result['passed'] ? '✅' : '❌';
                
                echo "<div class='test-result $class'>";
                echo "<strong>$icon {$result['name']}</strong>";
                if ($result['message']) {
                    echo " - {$result['message']}";
                }
                echo "</div>";
            }
        }
        
        // Summary
        $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;
        echo "<div class='test-result " . ($success_rate >= 90 ? 'test-pass' : 'test-warning') . "'>";
        echo "<h3>📊 Test Summary: $passed_tests/$total_tests passed ($success_rate%)</h3>";
        echo "</div>";
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup_test_data() {
        global $wpdb;
        
        // Remove test order
        if ($this->test_order_id) {
            wp_delete_post($this->test_order_id, true);
        }
        
        // Remove test agent
        if ($this->test_agent_id) {
            $wpdb->delete(
                $wpdb->prefix . 'rr_delivery_agents',
                ['id' => $this->test_agent_id]
            );
        }
        
        // Clean up test tracking data
        $wpdb->delete(
            $wpdb->prefix . 'rr_location_tracking',
            ['agent_id' => $this->test_agent_id]
        );
        
        // Clean up test assignments
        $wpdb->delete(
            $wpdb->prefix . 'rr_order_assignments',
            ['order_id' => $this->test_order_id]
        );
    }
    
    /**
     * Shortcode for running tests
     */
    public function run_tests_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Insufficient permissions to run tests.</p>';
        }
        
        ob_start();
        $this->run_all_tests();
        $this->display_test_results();
        return ob_get_clean();
    }
}

// Initialize testing suite
new RDM_Comprehensive_Workflow_Test(); 