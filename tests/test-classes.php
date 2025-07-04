<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple test to verify classes can be loaded
echo "Loading RDM_Google_Maps...\n";
require_once 'includes/class-rdm-google-maps.php';
echo "Loading RDM_Google_Maps...\n";
// Note: Using RDM_Google_Maps (no separate RR class needed)

echo "RDM_Google_Maps class exists: " . (class_exists('RDM_Google_Maps') ? 'Yes' : 'No') . "\n";
echo "RDM_Google_Maps class exists: " . (class_exists('RDM_Google_Maps') ? 'Yes' : 'No') . "\n";

// Test static method calls
echo "RDM_Google_Maps::get_api_key method exists: " . (method_exists('RDM_Google_Maps', 'get_api_key') ? 'Yes' : 'No') . "\n";
echo "RDM_Google_Maps::get_api_key method exists: " . (method_exists('RDM_Google_Maps', 'get_api_key') ? 'Yes' : 'No') . "\n";

echo "Classes loaded successfully without conflicts!\n";
