---
applyTo: '**'
---

# WooCommerce Integration Rules - Restaurant Delivery System

## 🎯 Context: Custom Order Workflow Integration
This rule applies when working with WooCommerce order processing, custom statuses, and delivery-specific workflows in the RestroReach system.

## 🏪 Restaurant Order Workflow
```
Customer Order → Processing → Preparing → Ready for Pickup → Out for Delivery → Delivered
                                ↓
                        [Agent Assignment Point]
```

## 📋 Custom Order Statuses (Already Implemented)

### Status Registration Pattern
```php
// Follow this exact pattern for order status registration
register_post_status('wc-preparing', array(
    'label'                     => _x('Preparing', 'Order status', 'restaurant-delivery-manager'),
    'public'                    => true,
    'exclude_from_search'       => false,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
    'label_count'               => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'restaurant-delivery-manager')
));
```

### HPOS Compatibility (Critical)
```php
// ALWAYS include HPOS compatibility for order operations
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Use HPOS-compatible order data access
$order = wc_get_order($order_id); // ✅ HPOS compatible
$order->get_id();                 // ✅ HPOS compatible  
$order->get_status();             // ✅ HPOS compatible
```

## 🚚 Distance-Based Shipping Integration

### Shipping Method Class Pattern
```php
class RDM_Distance_Shipping extends WC_Shipping_Method {
    
    public function __construct($instance_id = 0) {
        $this->id                 = 'rdm_distance_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Distance-Based Delivery', 'restaurant-delivery-manager');
        $this->method_description = __('Calculate delivery fee based on distance from restaurant', 'restaurant-delivery-manager');
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
    }
    
    public function calculate_shipping($package = array()) {
        // ALWAYS validate restaurant address first
        $restaurant_address = $this->get_restaurant_address();
        if (empty($restaurant_address)) {
            return; // No shipping rate if restaurant address not configured
        }
        
        // ALWAYS sanitize customer address
        $customer_address = $this->build_customer_address($package['destination']);
        
        // ALWAYS validate distance calculation
        $distance = $this->calculate_distance($restaurant_address, $customer_address);
        if (!$distance || $distance <= 0) {
            return; // No shipping rate if distance calculation fails
        }
        
        // ALWAYS check maximum delivery distance
        $max_distance = $this->get_option('max_distance', 10);
        if ($distance > $max_distance) {
            return; // No shipping rate if distance exceeds maximum
        }
        
        // Calculate fee using established pricing tiers
        $fee = $this->calculate_fee($distance, WC()->cart->get_subtotal());
        
        // ALWAYS validate calculated fee
        if ($fee <= 0) {
            return;
        }
        
        // Add shipping rate
        $this->add_rate(array(
            'id'       => $this->id . $this->instance_id,
            'label'    => $this->title,
            'cost'     => $fee,
            'meta_data' => array(
                'distance' => $distance,
                'restaurant_address' => $restaurant_address,
            ),
        ));
    }
}
```

## 🛒 Order Meta Integration

### Order Assignment Meta Pattern
```php
// ALWAYS use these meta keys for order assignments
define('RDM_ORDER_AGENT_META', '_rdm_assigned_agent_id');
define('RDM_ORDER_ASSIGNMENT_META', '_rdm_assignment_id');
define('RDM_ORDER_DISTANCE_META', '_rdm_delivery_distance');
define('RDM_ORDER_TRACKING_KEY_META', '_rdm_tracking_key');

// Order meta access pattern (HPOS compatible)
public function assign_agent_to_order(int $order_id, int $agent_id): bool {
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    // Update order meta (HPOS compatible)
    $order->update_meta_data(RDM_ORDER_AGENT_META, $agent_id);
    $order->update_meta_data(RDM_ORDER_ASSIGNMENT_META, time());
    $order->save();
    
    // Also update custom assignment table
    return $this->database->assign_order($order_id, $agent_id);
}
```

## 🔄 Order Status Transition Hooks

### Status Change Handler Pattern
```php
// Hook into order status changes for restaurant workflow
add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);

public function handle_order_status_change($order_id, $old_status, $new_status, $order): void {
    // ALWAYS validate order exists
    if (!$order || !$order_id) {
        return;
    }
    
    // Handle restaurant-specific status transitions
    switch ($new_status) {
        case 'preparing':
            $this->notify_kitchen($order_id);
            $this->start_preparation_timer($order_id);
            break;
            
        case 'ready-for-pickup':
            $this->notify_available_agents($order_id);
            $this->update_estimated_pickup_time($order_id);
            break;
            
        case 'out-for-delivery':
            $this->start_delivery_tracking($order_id);
            $this->notify_customer_dispatch($order_id);
            break;
            
        case 'delivered':
            $this->complete_delivery($order_id);
            $this->trigger_payment_reconciliation($order_id);
            break;
    }
    
    // ALWAYS log status changes for audit trail
    $this->log_status_change($order_id, $old_status, $new_status);
}
```

## 💳 COD Payment Integration

### COD Order Handling Pattern
```php
// Check if order uses COD payment method
public function is_cod_order(WC_Order $order): bool {
    return $order->get_payment_method() === 'cod';
}

// Handle COD collection workflow
public function handle_cod_collection(int $order_id, float $amount_collected): bool {
    $order = wc_get_order($order_id);
    if (!$order || !$this->is_cod_order($order)) {
        return false;
    }
    
    // ALWAYS validate collected amount
    $order_total = (float) $order->get_total();
    if ($amount_collected < $order_total) {
        throw new Exception('Collected amount is less than order total');
    }
    
    // Calculate change
    $change_amount = $amount_collected - $order_total;
    
    // Record payment transaction
    $transaction_id = $this->payments->record_cod_collection(
        $order_id,
        $amount_collected,
        $change_amount
    );
    
    // Update order payment status
    $order->payment_complete($transaction_id);
    $order->add_order_note(
        sprintf(
            __('COD payment collected: %s. Change given: %s', 'restaurant-delivery-manager'),
            wc_price($amount_collected),
            wc_price($change_amount)
        )
    );
    
    return true;
}
```

## 🎨 Admin Order Management Integration

### Order List Column Customization
```php
// Add delivery information columns to orders list
add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_columns'));
add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'populate_order_columns'), 10, 2);

public function add_order_columns($columns): array {
    // Insert delivery columns after order status
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns['delivery_agent'] = __('Delivery Agent', 'restaurant-delivery-manager');
            $new_columns['delivery_distance'] = __('Distance', 'restaurant-delivery-manager');
        }
    }
    return $new_columns;
}
```

## 📊 Restaurant Performance Metrics

### WooCommerce Analytics Integration
```php
// Extend WooCommerce analytics with restaurant metrics
add_filter('woocommerce_analytics_report_menu_items', array($this, 'add_analytics_menu_items'));

public function add_analytics_menu_items($menu_items): array {
    $menu_items[] = array(
        'id'     => 'delivery-performance',
        'title'  => __('Delivery Performance', 'restaurant-delivery-manager'),
        'parent' => 'woocommerce-analytics',
        'href'   => admin_url('admin.php?page=wc-admin&path=/analytics/delivery'),
    );
    
    return $menu_items;
}
```

## 🔒 Security Patterns for WooCommerce Integration

### Order Data Validation
```php
// ALWAYS validate order access permissions
public function can_user_access_order(int $user_id, int $order_id): bool {
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    // Admin can access all orders
    if (user_can($user_id, 'manage_woocommerce')) {
        return true;
    }
    
    // Restaurant manager can access all orders
    if (user_can($user_id, 'rdm_manage_orders')) {
        return true;
    }
    
    // Delivery agent can only access assigned orders
    if (user_can($user_id, 'rdm_view_own_orders')) {
        $assigned_agent = $order->get_meta(RDM_ORDER_AGENT_META);
        $agent_record = $this->database->get_agent_by_user_id($user_id);
        return $agent_record && $assigned_agent == $agent_record->id;
    }
    
    // Customer can access their own orders
    if ($order->get_customer_id() == $user_id) {
        return true;
    }
    
    return false;
}
```

## 🧪 Testing Patterns

### WooCommerce Integration Tests
```php
// Test order status transitions
public function test_order_status_transition() {
    $order = WC_Helper_Order::create_order();
    $order->set_status('preparing');
    
    // Verify restaurant workflow triggers
    $this->assertNotEmpty($order->get_meta('_rdm_preparation_started'));
    
    $order->set_status('ready-for-pickup');
    
    // Verify agent notification was triggered
    $this->assertTrue($this->was_agent_notified($order->get_id()));
}
```

## ⚠️ Common Pitfalls to Avoid

1. **HPOS Incompatibility**: Never use direct post meta access for orders
2. **Missing Validation**: Always validate order exists before operations
3. **Capability Bypass**: Never skip permission checks for order access
4. **Status Conflicts**: Don't override WooCommerce core order statuses
5. **Payment Inconsistency**: Always sync payment status with order status

## 💡 Example Implementation Prompts

**For Order Status Extension:**
```
"Add a new order status 'ready-for-packaging' between 'preparing' and 'ready-for-pickup' 
following the established WooCommerce integration patterns with HPOS compatibility"
```

**For Shipping Method Enhancement:**
```
"Extend the distance-based shipping to include time-based delivery slots 
following the existing shipping method patterns and restaurant workflow"
```

**For COD Enhancement:**
```
"Add tip collection capability to the COD workflow following the established 
payment processing patterns and agent reconciliation system"
``` 