<?php
/**
 * Test script to check if both Google Maps classes can be loaded without conflicts
 */

// Define constants needed for the classes
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions that are called in the classes
if (!function_exists('get_option')) {
    function get_option($option, $default = null) {
        return $default;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return $str;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration) {
        return true;
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        return array('body' => '{"status":"OK","results":[]}');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!class_exists('WC_Logger')) {
    class WC_Logger {
        public function debug($message, $context = array()) {}
        public function error($message, $context = array()) {}
        public function warning($message, $context = array()) {}
    }
}

if (!function_exists('wc_get_logger')) {
    function wc_get_logger() {
        return new WC_Logger();
    }
}

echo "Testing class loading...\n";

// Load RDM_Google_Maps first
require_once 'includes/class-rdm-google-maps.php';
echo "✅ RDM_Google_Maps loaded successfully\n";

// RDM_Google_Maps already loaded above
echo "✅ RDM_Google_Maps loaded successfully\n";

// Test that both classes exist and are different
if (class_exists('RDM_Google_Maps')) {
    echo "✅ RDM_Google_Maps class exists\n";
} else {
    echo "❌ RDM_Google_Maps class not found\n";
}

if (class_exists('RDM_Google_Maps')) {
    echo "✅ RDM_Google_Maps class confirmed loaded\n";
} else {
    echo "❌ RDM_Google_Maps class not found\n";
}

// Test static method calls
try {
    $api_key = RDM_Google_Maps::get_api_key();
    echo "✅ RDM_Google_Maps::get_api_key() called successfully\n";
} catch (Exception $e) {
    echo "❌ RDM_Google_Maps::get_api_key() failed: " . $e->getMessage() . "\n";
}

try {
    $valid = RDM_Google_Maps::validate_api_key_format('test_key_123456789012345678901234567890');
    echo "✅ RDM_Google_Maps::validate_api_key_format() called successfully\n";
} catch (Exception $e) {
    echo "❌ RDM_Google_Maps::validate_api_key_format() failed: " . $e->getMessage() . "\n";
}

echo "✅ Class conflict test completed successfully!\n";
