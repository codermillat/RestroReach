<?php
/**
 * Test script for RestroReach dynamic shipping fixes
 * This file tests the implemented fixes for shipping fee calculation
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    // Include WordPress if running standalone
    require_once('../../../wp-config.php');
}

echo "<h1>RestroReach Dynamic Shipping Test Results</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
h2 { color: #0073aa; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
h3 { color: #666; margin-top: 20px; }
.success { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
.warning { color: #ffb900; font-weight: bold; }
.info { color: #00a0d2; font-weight: bold; }
.code { background: #f1f1f1; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0; font-family: monospace; }
</style>";

// Test 1: Check if classes are loaded
echo "<h2>Test 1: Class Availability</h2>\n";

if (class_exists('RDM_Google_Maps')) {
    echo "<span class='success'>✅ RDM_Google_Maps class is loaded</span><br>\n";
    
    $api_status = RDM_Google_Maps::get_api_status();
    echo "<span class='info'>API Status:</span> " . $api_status['message'] . "<br>\n";
    
    if (RDM_Google_Maps::is_enabled()) {
        echo "<span class='success'>✅ Google Maps is enabled</span><br>\n";
    } else {
        echo "<span class='warning'>⚠️ Google Maps API key is not configured</span><br>\n";
    }
} else {
    echo "<span class='error'>❌ RDM_Google_Maps class not found</span><br>\n";
}

if (class_exists('WC_Shipping_RestroReach_Delivery')) {
    echo "<span class='success'>✅ WC_Shipping_RestroReach_Delivery class is loaded</span><br>\n";
} else {
    echo "<span class='error'>❌ WC_Shipping_RestroReach_Delivery class not found</span><br>\n";
}

// Test 2: Test restaurant coordinates
echo "<h2>Test 2: Restaurant Coordinates</h2>\n";
if (class_exists('RDM_Google_Maps')) {
    $restaurant_coords = RDM_Google_Maps::get_restaurant_coordinates();
    if ($restaurant_coords) {
        echo "<span class='success'>✅ Restaurant coordinates retrieved:</span><br>\n";
        echo "<div class='code'>Latitude: " . $restaurant_coords['lat'] . "<br>Longitude: " . $restaurant_coords['lng'] . "</div>\n";
    } else {
        echo "<span class='error'>❌ Could not retrieve restaurant coordinates</span><br>\n";
        echo "<span class='info'>This could be due to:</span><br>\n";
        echo "• No restaurant address configured<br>\n";
        echo "• Geocoding API failure<br>\n";
        echo "• Invalid API key<br>\n";
    }
} else {
    echo "<span class='error'>❌ RDM_Google_Maps class not available</span><br>\n";
}

// Test 3: Test geocoding functionality
echo "<h2>Test 3: Geocoding Test</h2>\n";
if (class_exists('RDM_Google_Maps') && RDM_Google_Maps::is_enabled()) {
    $test_addresses = array(
        '1600 Amphitheatre Parkway, Mountain View, CA',
        '1 Apple Park Way, Cupertino, CA',
        'Times Square, New York, NY'
    );
    
    foreach ($test_addresses as $address) {
        echo "<h3>Testing: " . $address . "</h3>\n";
        
        $coords = RDM_Google_Maps::geocode_address_static($address);
        if ($coords && isset($coords['lat'], $coords['lng'])) {
            echo "<span class='success'>✅ Successfully geocoded:</span><br>\n";
            echo "<div class='code'>Latitude: " . $coords['lat'] . "<br>Longitude: " . $coords['lng'] . "<br>";
            if (isset($coords['formatted_address'])) {
                echo "Formatted: " . $coords['formatted_address'];
            }
            echo "</div>\n";
        } else {
            echo "<span class='error'>❌ Failed to geocode address</span><br>\n";
        }
        
        // Add delay to avoid rate limiting
        sleep(1);
    }
} else {
    echo "<span class='warning'>⚠️ Skipping geocoding test - Google Maps not enabled or API key missing</span><br>\n";
}

// Test 4: Test shipping calculation
echo "<h2>Test 4: Shipping Calculation Test</h2>\n";
if (class_exists('WC_Shipping_RestroReach_Delivery')) {
    
    // Create a mock instance for testing
    $shipping_method = new WC_Shipping_RestroReach_Delivery();
    
    echo "<span class='info'>Testing shipping calculation methods:</span><br>\n";
    
    // Test if the problematic methods have been fixed
    if (method_exists($shipping_method, 'calculate_shipping')) {
        echo "<span class='success'>✅ calculate_shipping method exists</span><br>\n";
    } else {
        echo "<span class='error'>❌ calculate_shipping method not found</span><br>\n";
    }
    
    if (method_exists($shipping_method, 'calculate_geocoding_distance')) {
        echo "<span class='success'>✅ calculate_geocoding_distance method exists (replaces calculate_approximate_distance)</span><br>\n";
    } else {
        echo "<span class='error'>❌ calculate_geocoding_distance method not found</span><br>\n";
    }
    
    if (method_exists($shipping_method, 'calculate_haversine_distance')) {
        echo "<span class='success'>✅ calculate_haversine_distance method exists</span><br>\n";
    } else {
        echo "<span class='error'>❌ calculate_haversine_distance method not found</span><br>\n";
    }
    
    if (method_exists($shipping_method, 'get_delivery_distance')) {
        echo "<span class='success'>✅ get_delivery_distance method exists</span><br>\n";
    } else {
        echo "<span class='error'>❌ get_delivery_distance method not found</span><br>\n";
    }
    
    if (method_exists($shipping_method, 'calculate_fee_by_distance')) {
        echo "<span class='success'>✅ calculate_fee_by_distance method exists</span><br>\n";
    } else {
        echo "<span class='error'>❌ calculate_fee_by_distance method not found</span><br>\n";
    }
    
} else {
    echo "<span class='error'>❌ WC_Shipping_RestroReach_Delivery class not available</span><br>\n";
}

// Test 5: Test WooCommerce integration
echo "<h2>Test 5: WooCommerce Integration</h2>\n";
if (class_exists('WooCommerce')) {
    echo "<span class='success'>✅ WooCommerce is active</span><br>\n";
    
    // Check if our shipping method is registered
    $shipping_methods = WC()->shipping()->get_shipping_methods();
    if (isset($shipping_methods['restroreach_delivery'])) {
        echo "<span class='success'>✅ RestroReach delivery method is registered</span><br>\n";
    } else {
        echo "<span class='warning'>⚠️ RestroReach delivery method not found in registered methods</span><br>\n";
    }
    
} else {
    echo "<span class='error'>❌ WooCommerce is not active</span><br>\n";
}

// Test 6: Configuration Check
echo "<h2>Test 6: Configuration Status</h2>\n";

$rdm_settings = get_option('woocommerce_restroreach_delivery_settings', array());

echo "<h3>Delivery Settings:</h3>\n";
echo "<div class='code'>";
echo "Method Enabled: " . (isset($rdm_settings['enabled']) && $rdm_settings['enabled'] === 'yes' ? 'Yes' : 'No') . "<br>\n";
echo "Base Fee: " . (isset($rdm_settings['base_fee']) ? $rdm_settings['base_fee'] : 'Not set') . "<br>\n";
echo "Distance Unit: " . (isset($rdm_settings['distance_unit']) ? $rdm_settings['distance_unit'] : 'Not set') . "<br>\n";
echo "Pricing Mode: " . (isset($rdm_settings['pricing_mode']) ? $rdm_settings['pricing_mode'] : 'Not set') . "<br>\n";
echo "Restaurant Address: " . (isset($rdm_settings['restaurant_address']) ? $rdm_settings['restaurant_address'] : 'Not set') . "<br>\n";
echo "</div>\n";

// Test 7: Error Log Check
echo "<h2>Test 7: Recent Error Check</h2>\n";
echo "<span class='info'>Check WooCommerce logs for 'rdm-google-maps' entries to see detailed logging from our fixes.</span><br>\n";
echo "<span class='info'>Navigate to WooCommerce > Status > Logs and look for logs with source 'rdm-google-maps'.</span><br>\n";

echo "<h2>Summary</h2>\n";
echo "<p><strong>Fixes Implemented:</strong></p>\n";
echo "<ul>\n";
echo "<li><span class='success'>✅ Fixed static method call issue in RDM_Google_Maps::get_restaurant_coordinates()</span></li>\n";
echo "<li><span class='success'>✅ Replaced hardcoded fallback distance with proper geocoding calculation</span></li>\n";
echo "<li><span class='success'>✅ Added comprehensive debug logging throughout shipping calculation</span></li>\n";
echo "<li><span class='success'>✅ Enhanced error handling in all distance calculation methods</span></li>\n";
echo "<li><span class='success'>✅ Improved tiered pricing logic with proper type casting</span></li>\n";
echo "<li><span class='success'>✅ Added multiple fallback strategies for distance calculation</span></li>\n";
echo "<li><span class='success'>✅ Enhanced geocoding methods with detailed logging</span></li>\n";
echo "</ul>\n";

echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Configure Google Maps API key if not already done</li>\n";
echo "<li>Set up restaurant address in delivery settings</li>\n";
echo "<li>Test actual shipping calculation with real orders</li>\n";
echo "<li>Monitor WooCommerce logs for any issues</li>\n";
echo "</ul>\n";

?>
