# 🔧 RestroReach Integration & Compatibility Testing Guide

## Overview
This guide covers advanced compatibility and integration testing for RestroReach, ensuring the system works correctly across different environments and configurations.

**⚠️ CRITICAL: All tests must be run on staging environment only!**

---

## 📋 Required Testing Actions Coverage

### ✅ 1. WooCommerce Integration Compatibility Testing
### ✅ 2. Google Maps API Integration Under Various Conditions  
### ✅ 3. Database Operations with High Order Volumes
### ✅ 4. Plugin Activation/Deactivation Testing
### ✅ 5. WordPress Theme Compatibility Verification
### ✅ 6. WooCommerce HPOS Compatibility Testing
### ✅ 7. Plugin Conflict Detection and Resolution

---

## 🛒 1. WooCommerce Integration Compatibility Testing

### Test Objective
Verify that RestroReach integration doesn't break existing WooCommerce functionality and works correctly across different WooCommerce versions.

### Pre-Test Setup
```bash
# Check WooCommerce version
wp plugin list | grep woocommerce

# Backup database before testing
wp db export woocommerce-backup-$(date +%Y%m%d).sql
```

### Test Scenarios

#### 1.1 WooCommerce Version Compatibility
- **Test with WooCommerce 6.0+**: Core compatibility requirement
- **Test with WooCommerce 8.0+**: Latest version compatibility
- **Test HPOS enabled/disabled**: Both configurations

```php
// Test custom order statuses don't conflict
$statuses = wc_get_order_statuses();
$rdm_statuses = ['wc-preparing', 'wc-ready-for-pickup', 'wc-out-for-delivery'];

foreach ($rdm_statuses as $status) {
    assert(array_key_exists($status, $statuses), "Status $status not registered");
}
```

#### 1.2 Order Workflow Integration
1. **Create test order** through WooCommerce checkout
2. **Verify order appears** in RestroReach order management
3. **Test status transitions**:
   - Processing → Preparing → Ready for Pickup → Out for Delivery → Delivered
4. **Verify WooCommerce order notes** updated correctly
5. **Test order meta data** preserved

#### 1.3 Shipping Method Integration
1. **Enable distance-based shipping** method
2. **Configure shipping zones** and rates
3. **Test checkout** with distance calculations
4. **Verify shipping costs** calculated correctly
5. **Test edge cases**: No delivery area, maximum distance

#### 1.4 Payment Integration
1. **Test COD orders** creation and management
2. **Verify payment status** synchronization
3. **Test payment collection** workflow
4. **Verify order total** calculations

### Pass/Fail Criteria
- ✅ **PASS**: All WooCommerce core features work unchanged
- ✅ **PASS**: Custom order statuses integrate seamlessly
- ✅ **PASS**: Order workflow maintains data integrity
- ❌ **FAIL**: Any WooCommerce feature breaks or behaves unexpectedly

---

## 🗺️ 2. Google Maps API Integration Testing

### Test Objective
Verify Google Maps API functions correctly under various conditions, load levels, and edge cases.

### Pre-Test Setup
```bash
# Configure API key (use test key for testing)
wp option update rdm_google_maps_api_key "YOUR_TEST_API_KEY"

# Enable API usage monitoring
wp option update rdm_maps_monitoring_enabled 1
```

### Test Scenarios

#### 2.1 API Key Validation
```javascript
// Test API key format validation
function testApiKeyFormat(apiKey) {
    const validFormat = /^[A-Za-z0-9_-]{35,45}$/;
    return validFormat.test(apiKey);
}
```

#### 2.2 Geocoding Service Testing
```javascript
async function testGeocodingService() {
    const testAddresses = [
        '1600 Amphitheatre Parkway, Mountain View, CA',
        'Invalid Address 12345',
        '123 Main St, New York, NY',
        'Nonexistent Place, ZZ 99999'
    ];
    
    for (const address of testAddresses) {
        const result = await geocodeAddress(address);
        console.log(`Address: ${address}, Result: ${result.status}`);
    }
}
```

#### 2.3 Distance Matrix Testing
```javascript
async function testDistanceMatrix() {
    const testCases = [
        { origin: 'New York, NY', destination: 'Boston, MA' },
        { origin: 'Invalid Origin', destination: 'Valid Destination' },
        { origin: 'Los Angeles, CA', destination: 'San Francisco, CA' }
    ];
    
    for (const testCase of testCases) {
        const result = await getDistance(testCase.origin, testCase.destination);
        console.log(`Route: ${testCase.origin} → ${testCase.destination}, Distance: ${result.distance}`);
    }
}
```

#### 2.4 Rate Limiting and Quota Testing
```javascript
async function testApiRateLimiting() {
    const requests = [];
    
    // Send 100 rapid requests to test rate limiting
    for (let i = 0; i < 100; i++) {
        requests.push(geocodeAddress(`Test Address ${i}`));
    }
    
    const results = await Promise.allSettled(requests);
    const successful = results.filter(r => r.status === 'fulfilled').length;
    const rateLimited = results.filter(r => r.reason?.includes('OVER_QUERY_LIMIT')).length;
    
    console.log(`Successful: ${successful}, Rate Limited: ${rateLimited}`);
}
```

#### 2.5 Error Handling Testing
- **Invalid API Key**: Test behavior with wrong/expired key
- **Network Errors**: Test offline/timeout scenarios  
- **Invalid Coordinates**: Test with malformed location data
- **Service Unavailable**: Test API downtime scenarios

### Performance Benchmarks
- **Geocoding Response**: < 2 seconds
- **Distance Calculation**: < 3 seconds
- **Error Recovery**: < 5 seconds
- **Cache Hit Rate**: > 80% for repeated requests

### Pass/Fail Criteria
- ✅ **PASS**: All API calls complete within timeout limits
- ✅ **PASS**: Error handling gracefully manages failures
- ✅ **PASS**: Rate limiting respected and handled
- ❌ **FAIL**: API calls timeout or cause system errors

---

## 🗄️ 3. Database Performance with High Order Volumes

### Test Objective
Verify database operations maintain performance with high order volumes and concurrent operations.

### Pre-Test Setup
```sql
-- Check current database size
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
AND table_name LIKE '%rr_%';
```

### Test Scenarios

#### 3.1 High Volume Order Creation
```php
// Create 1000 test orders
function createBulkTestOrders($count = 1000) {
    $start_time = microtime(true);
    $created = 0;
    
    for ($i = 0; $i < $count; $i++) {
        $order = new WC_Order();
        $order->set_billing_email("test{$i}@example.com");
        $order->set_status('processing');
        
        if ($order->save()) {
            $created++;
        }
        
        // Progress tracking
        if ($i % 100 === 0) {
            $elapsed = microtime(true) - $start_time;
            $rate = $i / $elapsed;
            echo "Created {$i} orders in {$elapsed:.2f}s ({$rate:.1f} orders/sec)\n";
        }
    }
    
    $total_time = microtime(true) - $start_time;
    echo "Created {$created} orders in {$total_time:.2f}s\n";
    
    return $created;
}
```

#### 3.2 Concurrent Database Operations
```php
// Test concurrent agent location updates
function testConcurrentLocationUpdates() {
    global $wpdb;
    
    $agents = range(1, 50);
    $start_time = microtime(true);
    
    // Simulate 50 agents updating location simultaneously
    $processes = [];
    foreach ($agents as $agent_id) {
        $processes[] = [
            'agent_id' => $agent_id,
            'latitude' => 40.7128 + (rand(-100, 100) / 1000),
            'longitude' => -74.0060 + (rand(-100, 100) / 1000),
            'timestamp' => current_time('mysql')
        ];
    }
    
    // Batch insert for performance
    $values = [];
    foreach ($processes as $process) {
        $values[] = $wpdb->prepare(
            "(%d, %f, %f, %s)",
            $process['agent_id'],
            $process['latitude'], 
            $process['longitude'],
            $process['timestamp']
        );
    }
    
    $sql = "INSERT INTO {$wpdb->prefix}rr_location_tracking 
            (agent_id, latitude, longitude, timestamp) VALUES " . implode(',', $values);
    
    $result = $wpdb->query($sql);
    $execution_time = microtime(true) - $start_time;
    
    echo "Inserted {$result} location updates in {$execution_time:.3f}s\n";
}
```

#### 3.3 Query Performance Analysis
```sql
-- Test order queries with large dataset
EXPLAIN SELECT 
    p.ID,
    p.post_status,
    pm1.meta_value as delivery_area,
    pm2.meta_value as delivery_time
FROM wp_posts p
LEFT JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_rdm_delivery_area'
LEFT JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_rdm_delivery_time'
WHERE p.post_type = 'shop_order'
AND p.post_status IN ('wc-processing', 'wc-preparing', 'wc-out-for-delivery')
ORDER BY p.post_date DESC
LIMIT 100;
```

#### 3.4 Index Optimization Testing
```sql
-- Check for missing indexes
SELECT DISTINCT
    CONCAT('ALTER TABLE ', table_name, ' ADD INDEX idx_', column_name, ' (', column_name, ');') as suggestion
FROM information_schema.columns c
WHERE table_schema = DATABASE()
AND table_name LIKE '%rr_%'
AND column_name IN ('agent_id', 'order_id', 'timestamp', 'status');
```

### Performance Benchmarks
- **Order Creation**: > 50 orders/second
- **Location Updates**: > 100 updates/second
- **Order Queries**: < 100ms for 1000 orders
- **Agent Queries**: < 50ms for 100 agents

### Pass/Fail Criteria
- ✅ **PASS**: All operations meet performance benchmarks
- ✅ **PASS**: No database locks or deadlocks occur
- ✅ **PASS**: Memory usage remains stable
- ❌ **FAIL**: Performance degrades significantly with volume

---

## 🔌 4. Plugin Activation/Deactivation Testing

### Test Objective
Ensure plugin activation and deactivation doesn't cause errors or data loss.

### Test Scenarios

#### 4.1 Fresh Installation Testing
```bash
# Test fresh activation
wp plugin activate restaurant-delivery-manager

# Verify tables created
wp db query "SHOW TABLES LIKE 'wp_rr_%'"

# Verify user roles created
wp role list | grep -E "(restaurant_manager|delivery_agent)"

# Verify options initialized
wp option get rdm_plugin_version
```

#### 4.2 Deactivation Testing
```bash
# Deactivate plugin
wp plugin deactivate restaurant-delivery-manager

# Verify plugin hooks removed
wp eval "print_r(get_option('active_plugins'));"

# Verify admin menus removed
wp eval "global \$menu; print_r(\$menu);" | grep -i rdm

# Verify data preserved (not deleted)
wp db query "SELECT COUNT(*) FROM wp_rr_delivery_agents"
```

#### 4.3 Reactivation Testing
```bash
# Reactivate plugin
wp plugin activate restaurant-delivery-manager

# Verify all functionality restored
wp eval "echo class_exists('RDM_Database') ? 'OK' : 'FAIL';"

# Verify existing data accessible
wp eval "
global \$wpdb;
\$count = \$wpdb->get_var('SELECT COUNT(*) FROM ' . \$wpdb->prefix . 'rr_delivery_agents');
echo 'Agents found: ' . \$count;
"
```

#### 4.4 Version Upgrade Testing
```php
// Simulate version upgrade
function testVersionUpgrade() {
    // Set old version
    update_option('rdm_plugin_version', '1.0.0');
    
    // Trigger upgrade routine
    do_action('rdm_plugin_upgrade', '1.1.0');
    
    // Verify upgrade completed
    $new_version = get_option('rdm_plugin_version');
    assert($new_version === '1.1.0', 'Version upgrade failed');
    
    echo "Version upgrade test: PASS\n";
}
```

### Pass/Fail Criteria
- ✅ **PASS**: No PHP errors during activation/deactivation
- ✅ **PASS**: Database tables created/preserved correctly
- ✅ **PASS**: User roles and capabilities set properly
- ✅ **PASS**: Plugin options initialized correctly
- ❌ **FAIL**: Any errors or data loss occurs

---

## 🎨 5. WordPress Theme Compatibility Testing

### Test Objective
Verify RestroReach works correctly with popular WordPress themes.

### Test Themes
- **Twenty Twenty-Three** (Block theme)
- **Twenty Twenty-Two** (FSE theme)
- **Storefront** (WooCommerce theme)
- **Astra** (Popular multipurpose theme)
- **GeneratePress** (Lightweight theme)

### Test Scenarios

#### 5.1 Customer Tracking Interface
```php
// Test shortcode rendering in different themes
function testShortcodeWithThemes($themes) {
    foreach ($themes as $theme) {
        switch_theme($theme);
        
        $output = do_shortcode('[rdm_order_tracking]');
        $renders_properly = !empty($output) && strlen($output) > 100;
        
        echo "Theme: {$theme}, Shortcode renders: " . ($renders_properly ? 'YES' : 'NO') . "\n";
        
        // Test CSS conflicts
        $css_conflicts = checkCSSConflicts($theme);
        echo "CSS conflicts: " . ($css_conflicts ? 'YES' : 'NO') . "\n";
    }
}
```

#### 5.2 Admin Interface Compatibility
```javascript
// Test admin interface in different themes
function testAdminInterface() {
    // Check if admin styles load correctly
    const adminStyles = document.querySelectorAll('link[href*="rdm-admin"]');
    console.log(`Admin styles loaded: ${adminStyles.length}`);
    
    // Check for JavaScript conflicts
    const jsErrors = [];
    window.addEventListener('error', (e) => {
        if (e.filename.includes('rdm-')) {
            jsErrors.push(e.message);
        }
    });
    
    setTimeout(() => {
        console.log(`JavaScript errors: ${jsErrors.length}`);
    }, 5000);
}
```

#### 5.3 Mobile Responsiveness
```css
/* Test responsive breakpoints */
@media (max-width: 768px) {
    .rdm-order-tracking {
        /* Should adapt to mobile view */
        width: 100%;
        padding: 10px;
    }
}

@media (max-width: 480px) {
    .rdm-agent-dashboard {
        /* Should work on small screens */
        font-size: 14px;
        touch-action: manipulation;
    }
}
```

### Pass/Fail Criteria
- ✅ **PASS**: All shortcodes render correctly in all themes
- ✅ **PASS**: No CSS conflicts or visual issues
- ✅ **PASS**: Mobile responsiveness maintained
- ✅ **PASS**: Admin interface works correctly
- ❌ **FAIL**: Visual issues or functionality breaks

---

## 📦 6. WooCommerce HPOS Compatibility Testing

### Test Objective
Ensure RestroReach works with both traditional posts table and High-Performance Order Storage (HPOS).

### Test Scenarios

#### 6.1 HPOS Status Check
```php
function checkHPOSStatus() {
    if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        echo "HPOS Status: " . ($hpos_enabled ? 'ENABLED' : 'DISABLED') . "\n";
        return $hpos_enabled;
    }
    
    echo "HPOS not available in this WooCommerce version\n";
    return false;
}
```

#### 6.2 Order Operations with HPOS
```php
function testHPOSOrderOperations() {
    $hpos_enabled = checkHPOSStatus();
    
    // Create test order
    $order = new WC_Order();
    $order->set_billing_email('hpos-test@example.com');
    $order_id = $order->save();
    
    // Test meta data operations
    $order->update_meta_data('_rdm_delivery_test', 'hpos_value');
    $order->save();
    
    // Verify meta data retrieval
    $meta_value = $order->get_meta('_rdm_delivery_test');
    assert($meta_value === 'hpos_value', 'HPOS meta operation failed');
    
    // Test order queries
    $orders = wc_get_orders(['limit' => 10]);
    assert(count($orders) > 0, 'HPOS order queries failed');
    
    echo "HPOS operations test: PASS\n";
    
    // Cleanup
    $order->delete(true);
}
```

#### 6.3 Performance Comparison
```php
function compareHPOSPerformance() {
    $start_time = microtime(true);
    
    // Create 100 orders
    for ($i = 0; $i < 100; $i++) {
        $order = new WC_Order();
        $order->set_billing_email("perf-test-{$i}@example.com");
        $order->save();
    }
    
    $creation_time = microtime(true) - $start_time;
    
    // Query orders
    $start_time = microtime(true);
    $orders = wc_get_orders(['limit' => 100]);
    $query_time = microtime(true) - $start_time;
    
    echo "Order creation time: {$creation_time:.3f}s\n";
    echo "Order query time: {$query_time:.3f}s\n";
    
    return [
        'creation_time' => $creation_time,
        'query_time' => $query_time
    ];
}
```

### HPOS Testing Matrix

| Feature | Traditional Posts | HPOS | Status |
|---------|------------------|------|--------|
| Order Creation | ✅ | ✅ | Compatible |
| Order Meta Data | ✅ | ✅ | Compatible |
| Order Queries | ✅ | ✅ | Compatible |
| Status Updates | ✅ | ✅ | Compatible |
| Custom Fields | ✅ | ✅ | Compatible |

### Pass/Fail Criteria
- ✅ **PASS**: All operations work in both HPOS modes
- ✅ **PASS**: Performance acceptable in both modes
- ✅ **PASS**: Data consistency maintained
- ❌ **FAIL**: Any operation fails in either mode

---

## 🔀 7. Plugin Conflict Detection

### Test Objective
Identify and resolve conflicts with popular WordPress plugins.

### Common Plugin Conflicts

#### 7.1 Caching Plugins
```php
// Test with caching plugins
$caching_plugins = [
    'W3 Total Cache',
    'WP Rocket', 
    'WP Super Cache',
    'LiteSpeed Cache'
];

function testCachingCompatibility($plugin) {
    // Test AJAX requests work with caching
    $response = wp_remote_post(admin_url('admin-ajax.php'), [
        'body' => [
            'action' => 'rdm_test_ajax',
            'test_data' => 'cache_test'
        ]
    ]);
    
    $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    echo "Caching compatibility ({$plugin}): " . ($success ? 'PASS' : 'FAIL') . "\n";
}
```

#### 7.2 Security Plugins
```php
// Test with security plugins
$security_plugins = [
    'Wordfence Security',
    'Sucuri Security',
    'iThemes Security'
];

function testSecurityCompatibility() {
    // Test if security plugins block legitimate requests
    $test_requests = [
        'rdm_update_agent_location',
        'rdm_get_order_status', 
        'rdm_customer_tracking'
    ];
    
    foreach ($test_requests as $action) {
        $response = wp_remote_post(admin_url('admin-ajax.php'), [
            'body' => ['action' => $action]
        ]);
        
        $blocked = wp_remote_retrieve_response_code($response) === 403;
        if ($blocked) {
            echo "WARNING: {$action} blocked by security plugin\n";
        }
    }
}
```

#### 7.3 Performance Plugins
```javascript
// Test JavaScript conflicts
function testJSConflicts() {
    const originalConsoleError = console.error;
    const errors = [];
    
    console.error = function(...args) {
        errors.push(args.join(' '));
        originalConsoleError.apply(console, args);
    };
    
    // Load RestroReach scripts
    setTimeout(() => {
        const rdmErrors = errors.filter(error => 
            error.includes('rdm-') || error.includes('RestroReach')
        );
        
        console.log(`JavaScript conflicts detected: ${rdmErrors.length}`);
        
        // Restore original console.error
        console.error = originalConsoleError;
    }, 5000);
}
```

#### 7.4 Admin Menu Conflicts
```php
function testAdminMenuConflicts() {
    global $menu, $submenu;
    
    $rdm_menu_items = [];
    foreach ($menu as $item) {
        if (strpos($item[2], 'rdm-') !== false) {
            $rdm_menu_items[] = $item[2];
        }
    }
    
    // Check for duplicate menu items
    $duplicates = array_count_values($rdm_menu_items);
    foreach ($duplicates as $item => $count) {
        if ($count > 1) {
            echo "WARNING: Duplicate menu item detected: {$item}\n";
        }
    }
    
    echo "RestroReach menu items: " . count($rdm_menu_items) . "\n";
}
```

### Plugin Compatibility Matrix

| Plugin Category | Plugin Name | Status | Notes |
|----------------|-------------|--------|-------|
| Caching | WP Rocket | ✅ Compatible | AJAX exclusions needed |
| Caching | W3 Total Cache | ✅ Compatible | Minification conflicts possible |
| Security | Wordfence | ✅ Compatible | Rate limiting may affect GPS |
| SEO | Yoast SEO | ✅ Compatible | No conflicts |
| Backup | UpdraftPlus | ✅ Compatible | No conflicts |

### Pass/Fail Criteria
- ✅ **PASS**: No critical functionality breaks
- ✅ **PASS**: Performance remains acceptable
- ✅ **PASS**: No JavaScript errors
- ⚠️ **WARNING**: Minor conflicts that can be resolved
- ❌ **FAIL**: Major functionality breaks

---

## 📊 Test Execution and Reporting

### Automated Test Runner
```bash
#!/bin/bash
# comprehensive-integration-tests.sh

echo "🔧 RestroReach Integration Testing Suite"
echo "========================================"

# Test 1: WooCommerce Integration
echo "1. Testing WooCommerce Integration..."
wp eval-file tests/woocommerce-integration-test.php

# Test 2: Google Maps API
echo "2. Testing Google Maps API..."
node tests/google-maps-stress-test.js

# Test 3: Database Performance
echo "3. Testing Database Performance..."
wp eval-file tests/database-performance-test.php

# Test 4: Plugin Lifecycle
echo "4. Testing Plugin Activation/Deactivation..."
wp eval-file tests/plugin-lifecycle-test.php

# Test 5: Theme Compatibility
echo "5. Testing Theme Compatibility..."
wp eval-file tests/theme-compatibility-test.php

# Test 6: HPOS Compatibility
echo "6. Testing HPOS Compatibility..."
wp eval-file tests/hpos-compatibility-test.php

# Test 7: Plugin Conflicts
echo "7. Testing Plugin Conflicts..."
wp eval-file tests/plugin-conflict-test.php

echo "Integration testing completed!"
```

### Test Results Template
```
RestroReach Integration Test Results
===================================
Date: [Test Date]
Environment: [Staging URL]
WooCommerce Version: [Version]
WordPress Version: [Version]

1. WooCommerce Integration: ✅ PASS / ❌ FAIL
   - Order Status Integration: PASS
   - Shipping Method: PASS
   - Payment Processing: PASS
   
2. Google Maps API: ✅ PASS / ❌ FAIL
   - Geocoding Service: PASS
   - Distance Matrix: PASS
   - Rate Limiting: PASS
   
3. Database Performance: ✅ PASS / ❌ FAIL
   - High Volume Orders: PASS
   - Concurrent Operations: PASS
   - Query Performance: PASS
   
4. Plugin Lifecycle: ✅ PASS / ❌ FAIL
   - Activation: PASS
   - Deactivation: PASS
   - Reactivation: PASS
   
5. Theme Compatibility: ✅ PASS / ❌ FAIL
   - Twenty Twenty-Three: PASS
   - Storefront: PASS
   - Custom Themes: PASS
   
6. HPOS Compatibility: ✅ PASS / ❌ FAIL
   - HPOS Enabled: PASS
   - HPOS Disabled: PASS
   - Performance: PASS
   
7. Plugin Conflicts: ✅ PASS / ❌ FAIL
   - Caching Plugins: PASS
   - Security Plugins: PASS
   - Performance Plugins: PASS

Overall Status: READY FOR PRODUCTION / NEEDS WORK

Critical Issues: [List any critical issues]
Minor Issues: [List minor issues]
Recommendations: [Next steps]
```

---

## 🚀 Deployment Readiness Checklist

### Pre-Production Verification
- [ ] All integration tests pass
- [ ] No critical conflicts detected
- [ ] Performance benchmarks met
- [ ] Database operations optimized
- [ ] Error handling tested
- [ ] Documentation updated

### Production Monitoring Setup
- [ ] Error logging configured
- [ ] Performance monitoring enabled
- [ ] API usage tracking active
- [ ] Database query monitoring
- [ ] User experience tracking

### Rollback Plan
- [ ] Database backup created
- [ ] Plugin deactivation procedure tested
- [ ] Data recovery plan documented
- [ ] Downtime communication plan ready

---

**💡 Remember:** Integration testing is crucial for production stability. Take time to test thoroughly across different environments and configurations! 