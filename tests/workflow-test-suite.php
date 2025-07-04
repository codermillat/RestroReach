<?php
/**
 * RestroReach Workflow Testing Suite
 * Tests all core functionality systematically
 * 
 * @package RestaurantDeliveryManager
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class RDM_Workflow_Test_Suite {
    
    private $results = [];
    private $test_order_id = null;
    private $test_agent_id = null;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_test_menu']);
        add_shortcode('rdm_workflow_tests', [$this, 'display_tests']);
    }
    
    public function add_test_menu() {
        add_submenu_page(
            'rdm-admin',
            'Workflow Tests',
            'Workflow Tests', 
            'manage_options',
            'rdm-workflow-tests',
            [$this, 'test_admin_page']
        );
    }
    
    public function test_admin_page() {
        ?>
        <div class="wrap">
            <h1>🧪 RestroReach Workflow Tests</h1>
            
            <div class="notice notice-warning">
                <p><strong>⚠️ Important:</strong> Run these tests on staging environment only!</p>
            </div>
            
            <div style="margin: 20px 0;">
                <button onclick="runAllTests()" class="button button-primary">Run All Tests</button>
                <button onclick="cleanupTests()" class="button button-secondary">Cleanup Test Data</button>
            </div>
            
            <div id="test-results"></div>
            
            <script>
            async function runAllTests() {
                document.getElementById('test-results').innerHTML = '<p>🔄 Running tests...</p>';
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=rdm_run_workflow_tests&nonce=<?php echo wp_create_nonce('rdm_workflow_tests'); ?>'
                });
                
                const result = await response.text();
                document.getElementById('test-results').innerHTML = result;
            }
            
            async function cleanupTests() {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=rdm_cleanup_test_data&nonce=<?php echo wp_create_nonce('rdm_cleanup_tests'); ?>'
                });
                
                const result = await response.text();
                alert('Test data cleaned up!');
            }
            </script>
            
            <style>
            .test-section { margin: 20px 0; }
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
        </div>
        <?php
    }
    
    public function run_all_tests() {
        $this->results = [];
        
        // Test 1: System Prerequisites
        $this->test_prerequisites();
        
        // Test 2: Database Structure
        $this->test_database();
        
        // Test 3: User Roles & Permissions
        $this->test_user_roles();
        
        // Test 4: WooCommerce Integration
        $this->test_woocommerce();
        
        // Test 5: Order Workflow
        $this->test_order_workflow();
        
        // Test 6: Agent Assignment
        $this->test_agent_assignment();
        
        // Test 7: GPS Tracking
        $this->test_gps_tracking();
        
        // Test 8: Customer Tracking
        $this->test_customer_tracking();
        
        // Test 9: Payment Workflows
        $this->test_payments();
        
        // Test 10: Notifications
        $this->test_notifications();
        
        return $this->format_results();
    }
    
    private function test_prerequisites() {
        $this->add_section('System Prerequisites');
        
        // WordPress version
        global $wp_version;
        $this->add_result(
            'WordPress Version',
            version_compare($wp_version, '6.0', '>='),
            "Current: $wp_version (Required: 6.0+)"
        );
        
        // WooCommerce
        $this->add_result(
            'WooCommerce Active',
            class_exists('WooCommerce'),
            class_exists('WooCommerce') ? 'Active' : 'Not found'
        );
        
        // PHP version
        $this->add_result(
            'PHP Version',
            version_compare(PHP_VERSION, '8.0', '>='),
            "Current: " . PHP_VERSION . " (Required: 8.0+)"
        );
    }
    
    private function test_database() {
        $this->add_section('Database Structure');
        
        global $wpdb;
        $tables = [
            'rr_delivery_agents',
            'rr_order_assignments',
            'rr_location_tracking',
            'rr_payment_transactions'
        ];
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}$table'") === $wpdb->prefix . $table;
            $this->add_result("Table: $table", $exists, $exists ? 'Exists' : 'Missing');
        }
    }
    
    private function test_user_roles() {
        $this->add_section('User Roles & Permissions');
        
        $roles = ['restaurant_manager', 'delivery_agent'];
        foreach ($roles as $role) {
            $exists = get_role($role) !== null;
            $this->add_result("Role: $role", $exists, $exists ? 'Exists' : 'Missing');
        }
    }
    
    private function test_woocommerce() {
        $this->add_section('WooCommerce Integration');
        
        // Custom order statuses
        $statuses = ['wc-preparing', 'wc-ready-for-pickup', 'wc-out-for-delivery'];
        foreach ($statuses as $status) {
            $exists = array_key_exists($status, wc_get_order_statuses());
            $this->add_result("Status: $status", $exists, $exists ? 'Registered' : 'Missing');
        }
    }
    
    private function test_order_workflow() {
        $this->add_section('Order Workflow');
        
        // Create test order
        $order = new WC_Order();
        $order->set_billing_email('test@example.com');
        $order->set_status('processing');
        $this->test_order_id = $order->save();
        
        $this->add_result(
            'Test Order Creation',
            $this->test_order_id > 0,
            "Order ID: {$this->test_order_id}"
        );
        
        // Test status transitions
        $order->set_status('wc-preparing');
        $order->save();
        $this->add_result(
            'Status Change to Preparing',
            $order->get_status() === 'preparing',
            'Status updated'
        );
    }
    
    private function test_agent_assignment() {
        $this->add_section('Agent Assignment');
        
        if (class_exists('RDM_Database')) {
            $database = RDM_Database::get_instance();
            
            $agent_data = [
                'user_id' => 1,
                'name' => 'Test Agent',
                'phone' => '555-123-4567',
                'vehicle_type' => 'bike',
                'is_available' => 1
            ];
            
            $this->test_agent_id = $database->insert_delivery_agent($agent_data);
            $this->add_result(
                'Create Test Agent',
                $this->test_agent_id > 0,
                "Agent ID: {$this->test_agent_id}"
            );
        }
    }
    
    private function test_gps_tracking() {
        $this->add_section('GPS Tracking');
        
        $this->add_result(
            'GPS Class Available',
            class_exists('RDM_GPS_Tracking'),
            'Class loaded'
        );
        
        // Test location update
        if ($this->test_agent_id) {
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'rr_location_tracking',
                [
                    'agent_id' => $this->test_agent_id,
                    'latitude' => 40.7128,
                    'longitude' => -74.0060,
                    'accuracy' => 10,
                    'timestamp' => current_time('mysql')
                ]
            );
            
            $this->add_result(
                'Location Update',
                $result !== false,
                'GPS data recorded'
            );
        }
    }
    
    private function test_customer_tracking() {
        $this->add_section('Customer Tracking');
        
        $this->add_result(
            'Customer Tracking Class',
            class_exists('RDM_Customer_Tracking'),
            'Class available'
        );
        
        $this->add_result(
            'Tracking Shortcode',
            shortcode_exists('rdm_order_tracking'),
            'Shortcode registered'
        );
    }
    
    private function test_payments() {
        $this->add_section('Payment System');
        
        $this->add_result(
            'Payment Class',
            class_exists('RDM_Payments'),
            'Payment system available'
        );
        
        // Test COD transaction
        if ($this->test_order_id) {
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'rr_payment_transactions',
                [
                    'order_id' => $this->test_order_id,
                    'agent_id' => $this->test_agent_id ?: 1,
                    'amount' => 25.99,
                    'payment_method' => 'cash',
                    'status' => 'collected'
                ]
            );
            
            $this->add_result(
                'COD Transaction',
                $result !== false,
                'Payment recorded'
            );
        }
    }
    
    private function test_notifications() {
        $this->add_section('Notifications');
        
        $this->add_result(
            'Notification Class',
            class_exists('RDM_Notifications'),
            'Notification system available'
        );
        
        // Test AJAX endpoints
        $endpoints = [
            'rdm_get_agent_orders',
            'rdm_update_order_status',
            'rdm_update_agent_location'
        ];
        
        foreach ($endpoints as $endpoint) {
            $exists = has_action("wp_ajax_$endpoint");
            $this->add_result(
                "AJAX: $endpoint",
                $exists,
                $exists ? 'Registered' : 'Missing'
            );
        }
    }
    
    private function add_section($name) {
        $this->results[] = ['type' => 'section', 'name' => $name];
    }
    
    private function add_result($name, $passed, $message = '') {
        $this->results[] = [
            'type' => 'result',
            'name' => $name,
            'passed' => $passed,
            'message' => $message
        ];
    }
    
    private function format_results() {
        $output = '';
        $total = 0;
        $passed = 0;
        
        foreach ($this->results as $result) {
            if ($result['type'] === 'section') {
                $output .= "<h3>📋 {$result['name']}</h3>";
            } else {
                $total++;
                if ($result['passed']) $passed++;
                
                $class = $result['passed'] ? 'test-pass' : 'test-fail';
                $icon = $result['passed'] ? '✅' : '❌';
                
                $output .= "<div class='test-result $class'>";
                $output .= "<strong>$icon {$result['name']}</strong>";
                if ($result['message']) {
                    $output .= " - {$result['message']}";
                }
                $output .= "</div>";
            }
        }
        
        $rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        $output .= "<h3>📊 Summary: $passed/$total tests passed ($rate%)</h3>";
        
        return $output;
    }
    
    public function cleanup_test_data() {
        global $wpdb;
        
        if ($this->test_order_id) {
            wp_delete_post($this->test_order_id, true);
        }
        
        if ($this->test_agent_id) {
            $wpdb->delete($wpdb->prefix . 'rr_delivery_agents', ['id' => $this->test_agent_id]);
            $wpdb->delete($wpdb->prefix . 'rr_location_tracking', ['agent_id' => $this->test_agent_id]);
        }
    }
    
    public function display_tests($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Insufficient permissions.</p>';
        }
        
        return '<p>Visit Admin → RestroReach → Workflow Tests to run comprehensive tests.</p>';
    }
}

// Initialize
$rdm_test_suite = new RDM_Workflow_Test_Suite();

// AJAX handlers
add_action('wp_ajax_rdm_run_workflow_tests', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'rdm_workflow_tests')) {
        wp_die('Security check failed');
    }
    
    global $rdm_test_suite;
    echo $rdm_test_suite->run_all_tests();
    wp_die();
});

add_action('wp_ajax_rdm_cleanup_test_data', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'rdm_cleanup_tests')) {
        wp_die('Security check failed');
    }
    
    global $rdm_test_suite;
    $rdm_test_suite->cleanup_test_data();
    wp_die();
}); 