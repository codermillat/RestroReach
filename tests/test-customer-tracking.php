<?php
/**
 * Customer Tracking Test File
 * 
 * This file tests the customer tracking functionality to ensure everything works correctly.
 * To use: Create a test page and add [rdm_order_tracking] shortcode
 */

// Test only if WordPress is loaded
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

// Simple test to verify customer tracking class
function test_customer_tracking_integration() {
    if (!class_exists('RDM_Customer_Tracking')) {
        return "❌ RDM_Customer_Tracking class not found";
    }
    
    $customer_tracking = RDM_Customer_Tracking::get_instance();
    
    if (!$customer_tracking) {
        return "❌ Failed to get RDM_Customer_Tracking instance";
    }
    
    // Test shortcode registration
    if (!shortcode_exists('rdm_order_tracking')) {
        return "❌ Shortcode 'rdm_order_tracking' not registered";
    }
    
    // Test AJAX actions
    if (!has_action('wp_ajax_rdm_get_order_status') || !has_action('wp_ajax_nopriv_rdm_get_order_status')) {
        return "❌ AJAX handlers not registered";
    }
    
    return "✅ Customer tracking system initialized successfully";
}

// Test asset enqueueing
function test_customer_tracking_assets() {
    $results = [];
    
    // Check if CSS file exists
    $css_path = RDM_PLUGIN_DIR . 'assets/css/rdm-customer-tracking.css';
    if (file_exists($css_path)) {
        $results[] = "✅ CSS file exists: rdm-customer-tracking.css";
    } else {
        $results[] = "❌ CSS file missing: rdm-customer-tracking.css";
    }
    
    // Check if JS file exists
    $js_path = RDM_PLUGIN_DIR . 'assets/js/rdm-customer-tracking.js';
    if (file_exists($js_path)) {
        $results[] = "✅ JavaScript file exists: rdm-customer-tracking.js";
    } else {
        $results[] = "❌ JavaScript file missing: rdm-customer-tracking.js";
    }
    
    // Check if template exists
    $template_path = RDM_PLUGIN_DIR . 'templates/customer-tracking.php';
    if (file_exists($template_path)) {
        $results[] = "✅ Template file exists: customer-tracking.php";
    } else {
        $results[] = "❌ Template file missing: customer-tracking.php";
    }
    
    return $results;
}

// Simple demo shortcode for testing
function rdm_customer_tracking_test_shortcode($atts) {
    $atts = shortcode_atts([
        'demo' => false,
    ], $atts);
    
    if (!$atts['demo']) {
        return '[rdm_customer_tracking_test demo="true"] - Add demo="true" to see test results';
    }
    
    ob_start();
    ?>
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; font-family: monospace;">
        <h3>🧪 Customer Tracking System Test Results</h3>
        
        <h4>Integration Test:</h4>
        <p><?php echo test_customer_tracking_integration(); ?></p>
        
        <h4>Asset Files Test:</h4>
        <?php foreach (test_customer_tracking_assets() as $result): ?>
            <p><?php echo $result; ?></p>
        <?php endforeach; ?>
        
        <h4>Google Maps Integration:</h4>
        <?php if (class_exists('RDM_Google_Maps')): ?>
            <p>✅ Google Maps class available</p>
            <?php
            $api_key = get_option('rdm_google_maps_api_key');
            if ($api_key) {
                echo '<p>✅ Google Maps API key configured</p>';
            } else {
                echo '<p>⚠️ Google Maps API key not configured</p>';
            }
            ?>
        <?php else: ?>
            <p>❌ Google Maps class not found</p>
        <?php endif; ?>
        
        <h4>Database Tables:</h4>
        <?php
        global $wpdb;
        $tables = [
            'rdm_location_tracking',
            'rdm_order_assignments',
        ];
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if ($exists) {
                echo "<p>✅ Table exists: $table</p>";
            } else {
                echo "<p>❌ Table missing: $table</p>";
            }
        }
        ?>
        
        <h4>Test Order Tracking Form:</h4>
        <p>To test the full tracking interface, use: <code>[rdm_order_tracking]</code></p>
        <p>Or test with specific order: <code>[rdm_order_tracking order_id="123" tracking_key="test-key"]</code></p>
        
        <hr style="margin: 20px 0;">
        <p><strong>Instructions:</strong></p>
        <ol>
            <li>Configure Google Maps API key in plugin settings</li>
            <li>Create a page with [rdm_order_tracking] shortcode</li>
            <li>Test with a real WooCommerce order</li>
            <li>Assign delivery agent to see live tracking</li>
        </ol>
    </div>
    <?php
    return ob_get_clean();
}

// Register test shortcode
add_shortcode('rdm_customer_tracking_test', 'rdm_customer_tracking_test_shortcode');

// Add admin notice for testing
add_action('admin_notices', function() {
    if (isset($_GET['rdm_test']) && $_GET['rdm_test'] === 'customer_tracking') {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<h3>Customer Tracking Test Results</h3>';
        echo '<p>' . test_customer_tracking_integration() . '</p>';
        echo '<h4>Asset Files:</h4>';
        foreach (test_customer_tracking_assets() as $result) {
            echo '<p>' . $result . '</p>';
        }
        echo '<p><strong>Add this shortcode to a page to test:</strong> <code>[rdm_order_tracking]</code></p>';
        echo '<p><strong>Add this shortcode to see detailed test results:</strong> <code>[rdm_customer_tracking_test demo="true"]</code></p>';
        echo '</div>';
    }
});

// Log successful test file load
if (function_exists('error_log')) {
    error_log('RestroReach: Customer tracking test file loaded successfully');
}
?> 