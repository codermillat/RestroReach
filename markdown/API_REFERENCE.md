# API Reference Guide
## Restaurant Delivery Management System - Functions & Endpoints

### 🎯 QUICK REFERENCE

This document provides a comprehensive reference to all implemented custom functions, WordPress hooks, database operations, and API endpoints used in the Restaurant Delivery Manager system.

---

## 📡 IMPLEMENTED REST API ENDPOINTS

### Payment & COD APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_collect_cod_payment
// Collects COD payment from customer
// Implemented in class-payments.php and class-rdm-mobile-frontend.php
rdm_collect_cod_payment($order_id, $amount_received)

// AJAX: wp-admin/admin-ajax.php?action=rdm_calculate_change
// Calculates change for COD payment
// Implemented in class-payments.php and class-rdm-mobile-frontend.php
rdm_calculate_change($order_total, $amount_received)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_agent_payments
// Gets agent payment history and daily totals
// Implemented in class-payments.php
rdm_get_agent_payments($agent_id, $date)

// AJAX: wp-admin/admin-ajax.php?action=rdm_reconcile_cash
// Submits daily cash reconciliation for agent
// Implemented in class-payments.php
rdm_reconcile_cash($agent_id, $submitted_amount, $notes)

// AJAX: wp-admin/admin-ajax.php?action=rdm_generate_cash_report
// Generates agent cash reports (Admin only)
// Implemented in class-payments.php
rdm_generate_cash_report($agent_id, $date)

// AJAX: wp-admin/admin-ajax.php?action=rdm_verify_payment
// Admin payment verification (Admin only)
// Implemented in class-payments.php
rdm_verify_payment($payment_id, $status)

// AJAX: wp-admin/admin-ajax.php?action=rdm_verify_reconciliation
// Admin reconciliation verification (Admin only)
// Implemented in class-payments.php
rdm_verify_reconciliation($reconciliation_id, $status)
```

### Admin Management APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_get_dashboard_stats
// Gets real-time dashboard statistics
// Implemented in class-rdm-admin-interface.php
rdm_get_dashboard_stats()

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_recent_orders
// Gets recent orders for dashboard
// Implemented in class-rdm-admin-interface.php
rdm_get_recent_orders()

// AJAX: wp-admin/admin-ajax.php?action=rdm_fetch_orders
// Fetches filtered orders
// Implemented in class-rdm-admin-interface.php and class-rdm-mobile-frontend.php
rdm_fetch_orders()

// AJAX: wp-admin/admin-ajax.php?action=rdm_update_order_status
// Updates order delivery status
// Implemented in class-rdm-admin-interface.php
rdm_update_order_status($order_id, $new_status)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_available_agents
// Gets available delivery agents
// Implemented in class-rdm-admin-interface.php
rdm_get_available_agents()

// AJAX: wp-admin/admin-ajax.php?action=rdm_assign_agent_to_order
// Assigns delivery agent to order
// Implemented in class-rdm-admin-interface.php
rdm_assign_agent_to_order($order_id, $agent_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_add_order_note
// Adds delivery note to order
// Implemented in class-rdm-admin-interface.php
rdm_add_order_note($order_id, $note)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_agent_status
// Gets delivery agent status
// Implemented in class-rdm-admin-interface.php
rdm_get_agent_status()

// AJAX: wp-admin/admin-ajax.php?action=rdm_test_geocode
// Tests geocoding functionality
// Implemented in class-rdm-admin-interface.php
rdm_test_geocode($address)
```

### Mobile Agent APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_agent_login
// Agent login for mobile interface
// Implemented in class-rdm-mobile-frontend.php
rdm_agent_login($username, $password)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_agent_orders
// Gets orders assigned to specific agent
// Implemented in class-rdm-mobile-frontend.php
rdm_get_agent_orders($agent_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_accept_order
// Agent accepts assigned order
// Implemented in class-rdm-mobile-frontend.php
rdm_accept_order($order_id, $agent_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_upload_delivery_photo
// Uploads delivery confirmation photo
// Implemented in class-rdm-mobile-frontend.php
rdm_upload_delivery_photo($order_id, $photo)

// AJAX: wp-admin/admin-ajax.php?action=rdm_update_agent_location
// Updates agent GPS location
// Implemented in class-rdm-mobile-frontend.php and class-rdm-gps-tracking.php
rdm_update_agent_location($agent_id, $latitude, $longitude)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_order_details
// Gets detailed order information for mobile
// Implemented in class-rdm-mobile-frontend.php
rdm_get_order_details($order_id)
```

### Google Maps & Location APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_get_agent_locations
// Gets all agent GPS locations
// Implemented in class-rdm-google-maps.php
rdm_get_agent_locations()

// AJAX: wp-admin/admin-ajax.php?action=rdm_geocode_address
// Converts address to coordinates
// Implemented in class-rdm-google-maps.php
rdm_geocode_address($address)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_directions
// Gets GPS directions between points
// Implemented in class-rdm-google-maps.php
rdm_get_directions($origin, $destination)

// AJAX: wp-admin/admin-ajax.php?action=rdm_calculate_distance
// Calculates distance between points
// Implemented in class-rdm-google-maps.php
rdm_calculate_distance($point1, $point2)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_order_status
// Gets order delivery status with tracking
// Implemented in class-rdm-google-maps.php
rdm_get_order_status($order_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_active_orders_map
// Gets active orders for map display
// Implemented in class-rdm-google-maps.php and class-customer-tracking.php
rdm_get_active_orders_map()

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_delivery_analytics
// Gets delivery performance analytics
// Implemented in class-rdm-google-maps.php
rdm_get_delivery_analytics()

// AJAX: wp-admin/admin-ajax.php?action=rdm_validate_api_key
// Validates Google Maps API key
// Implemented in class-rdm-google-maps.php
rdm_validate_api_key($api_key)
```

### Customer Tracking APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_get_tracking_data
// Gets comprehensive tracking data for customer order tracking
// IMPLEMENTED in class-customer-tracking.php
rdm_get_tracking_data($order_id, $tracking_key)

// AJAX: wp-admin/admin-ajax.php?action=rdm_calculate_eta
// Calculates estimated time of arrival for delivery
// IMPLEMENTED in class-customer-tracking.php
rdm_calculate_eta($order_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_validate_tracking_key
// Validates a tracking key for an order
// IMPLEMENTED in class-customer-tracking.php
rdm_validate_tracking_key($order_id, $tracking_key)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_order_agent_location
// Gets the location of an agent assigned to a specific order
// IMPLEMENTED in class-customer-tracking.php
rdm_get_order_agent_location($order_id, $tracking_key)
```

### Notification APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_mark_notification_read
// Marks notification as read
// Implemented in class-notifications.php
rdm_mark_notification_read($notification_id)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_notifications
// Gets user notifications
// Implemented in class-notifications.php
rdm_get_notifications($user_id, $unread_only = false)
```

### Database Tools APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_recreate_tables
// Recreates database tables (Admin only)
// Implemented in class-rdm-database-tools.php
rdm_recreate_tables()

// AJAX: wp-admin/admin-ajax.php?action=rdm_check_table_status
// Checks database table status (Admin only)
// Implemented in class-rdm-database-tools.php
rdm_check_table_status()

// AJAX: wp-admin/admin-ajax.php?action=rdm_run_database_test
// Runs database connectivity test
// Implemented in class-rdm-admin-tools.php
rdm_run_database_test()

// AJAX: wp-admin/admin-ajax.php?action=rdm_generate_sample_data
// Generates sample data for testing
// Implemented in class-rdm-admin-tools.php
rdm_generate_sample_data()

// AJAX: wp-admin/admin-ajax.php?action=rdm_reset_tables
// Resets database tables
// Implemented in class-rdm-admin-tools.php
rdm_reset_tables()

// AJAX: wp-admin/admin-ajax.php?action=rdm_repair_database
// Repairs database issues
// Implemented in class-rdm-admin-tools.php
rdm_repair_database()

// AJAX: wp-admin/admin-ajax.php?action=rdm_run_health_check
// Runs system health check
// Implemented in class-rdm-admin-tools.php
rdm_run_health_check()

// AJAX: wp-admin/admin-ajax.php?action=rdm_cleanup_data
// Cleans up old data
// Implemented in class-rdm-admin-tools.php
rdm_cleanup_data()
```

### Main Plugin APIs (IMPLEMENTED)

```php
// AJAX: wp-admin/admin-ajax.php?action=rdm_refresh_dashboard
// Refreshes the admin dashboard data
// Implemented in restaurant-delivery-manager.php
rdm_refresh_dashboard()

// AJAX: wp-admin/admin-ajax.php?action=rdm_update_location
// Updates location data
// Implemented in restaurant-delivery-manager.php
rdm_update_location($agent_id, $latitude, $longitude)

// AJAX: wp-admin/admin-ajax.php?action=rdm_get_order_tracking
// Gets order tracking information
// Implemented in restaurant-delivery-manager.php
rdm_get_order_tracking($order_id, $tracking_key)
```

---

## 🔧 IMPLEMENTED CORE FUNCTIONS

### Google Maps Functions

```php
// Gets singleton instance of Google Maps class
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::instance(): RDM_Google_Maps
// Backward compatibility alias
RDM_Google_Maps::get_instance(): RDM_Google_Maps

// Gets Google Maps API key from settings
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::get_api_key(): ?string

// Checks if Google Maps API is enabled
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::is_enabled(): bool

// Gets API key validation status
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::get_api_status(): array

// Validates an API key format
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::validate_api_key_format(string $api_key): bool

// Tests API key by making a geocoding request
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::test_api_key(string $api_key = ''): array

// Gets restaurant coordinates from settings
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::get_restaurant_coordinates(): ?array
// Example:
// $coords = RDM_Google_Maps::get_restaurant_coordinates();
// if ($coords) {
//     $lat = $coords['lat'];
//     $lng = $coords['lng'];
// }

// Geocodes an address to coordinates
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::geocode_address(string $address): array
// Example:
// $result = RDM_Google_Maps::geocode_address('123 Main St, New York, NY');
// if (!isset($result['error'])) {
//     $lat = $result['lat'];
//     $lng = $result['lng'];
//     $formatted_address = $result['formatted_address'];
// }

// Static version of geocode_address for use without instance
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::geocode_address_static(string $address): array

// Calculates distance between two points in kilometers
// Implemented in class-rdm-google-maps.php
RDM_Google_Maps::calculate_distance_between_points(float $lat1, float $lng1, float $lat2, float $lng2): float
// Example:
// $distance_km = RDM_Google_Maps::calculate_distance_between_points(37.7749, -122.4194, 37.3382, -121.8863);
```

### GPS Tracking Functions

```php
// Gets singleton instance of GPS Tracking class
// Implemented in class-rdm-gps-tracking.php
RDM_GPS_Tracking::instance(): RDM_GPS_Tracking
// Backward compatibility alias
RDM_GPS_Tracking::get_instance(): RDM_GPS_Tracking

// Saves agent location to database
// Implemented in class-rdm-gps-tracking.php
RDM_GPS_Tracking::save_location(int $agent_id, float $latitude, float $longitude, ?float $accuracy = null, ?int $battery_level = null): bool
// Example:
// $location_saved = RDM_GPS_Tracking::save_location(
//     $agent_id,
//     $latitude,
//     $longitude,
//     $accuracy,
//     $battery_level
// );

// Gets latest agent location
// Implemented in class-rdm-gps-tracking.php
RDM_GPS_Tracking::get_latest_agent_location(int $agent_id): ?array
// Example:
// $location = RDM_GPS_Tracking::get_latest_agent_location($agent_id);
// if ($location) {
//     $lat = $location['latitude'];
//     $lng = $location['longitude'];
//     $timestamp = $location['recorded_at'];
// }

// Gets location history for an agent
// Implemented in class-rdm-gps-tracking.php
RDM_GPS_Tracking::get_location_history(int $agent_id, int $limit = 10): array
// Example:
// $history = RDM_GPS_Tracking::get_location_history($agent_id, 20);
// foreach ($history as $location) {
//     // Process each location point
// }
```

### Customer Tracking Functions (PLANNED - NOT IMPLEMENTED)

```php
// Gets tracking data for an order
// PLANNED - NOT IMPLEMENTED (stub exists in class-customer-tracking.php)
RDM_Customer_Tracking::get_tracking_data(int $order_id, string $tracking_key): array

// Validates a tracking key for an order
// PLANNED - NOT IMPLEMENTED (stub exists in class-customer-tracking.php)
RDM_Customer_Tracking::validate_tracking_key(int $order_id, string $tracking_key): bool

// Calculates ETA for delivery
// PLANNED - NOT IMPLEMENTED (stub exists in class-customer-tracking.php)
RDM_Customer_Tracking::calculate_eta(int $order_id): array

// Generates a tracking key for an order
// PLANNED - NOT IMPLEMENTED (stub exists in class-customer-tracking.php)
RDM_Customer_Tracking::generate_tracking_key(int $order_id): string
```

### Database Functions

```php
// Gets singleton instance of Database class
// Implemented in class-database.php
RDM_Database::instance(): RDM_Database

// Creates all required database tables
// Implemented in class-database.php
RDM_Database::create_tables(): bool

// Checks if database tables exist
// Implemented in class-database.php
RDM_Database::tables_exist(): bool

// Gets delivery agents
// Implemented in class-database.php
RDM_Database::get_delivery_agents(?string $status = null, bool $available_only = false): array
// Example:
// $available_agents = RDM_Database::get_delivery_agents('active', true);

// Gets order assignments
// Implemented in class-database.php
RDM_Database::get_order_assignments(?int $order_id = null, ?int $agent_id = null): array
// Example:
// $assignment = RDM_Database::get_order_assignments($order_id);
// $agent_orders = RDM_Database::get_order_assignments(null, $agent_id);

// Assigns order to agent
// Implemented in class-database.php
RDM_Database::assign_order(int $order_id, int $agent_id): bool
// Example:
// $assigned = RDM_Database::assign_order($order_id, $agent_id);
// if (!$assigned) {
//     // Handle assignment failure
// }

// Gets order delivery status
// Implemented in class-database.php
RDM_Database::get_order_delivery_status(int $order_id): ?string
// Example:
// $status = RDM_Database::get_order_delivery_status($order_id);
// if ($status === 'out-for-delivery') {
//     // Order is being delivered
// }

// Get all active orders with their assignments
// Implemented in class-database.php
RDM_Database::get_active_orders(): array
// Example:
// $active_orders = RDM_Database::get_active_orders();
// foreach ($active_orders as $order) {
//     // Process each active order
// }
```

### WooCommerce Integration Functions

```php
// Registers custom order statuses
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::register_order_statuses(): void

// Gets orders with specific status
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::get_orders_by_status(string $status, int $limit = 20): array
// Example:
// $ready_orders = RDM_WooCommerce_Integration::get_orders_by_status('rdm-ready-for-pickup', 10);

// Gets order delivery data
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::get_order_delivery_data(int $order_id): ?array
// Example:
// $delivery_data = RDM_WooCommerce_Integration::get_order_delivery_data($order_id);
// $customer_address = $delivery_data['address'];
// $delivery_notes = $delivery_data['notes'];

// Updates order status
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::update_order_status(int $order_id, string $status): bool
// Example:
// $updated = RDM_WooCommerce_Integration::update_order_status($order_id, 'rdm-out-for-delivery');

// Adds delivery note to order
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::add_delivery_note(int $order_id, string $note, bool $is_customer_note = false): bool
// Example:
// RDM_WooCommerce_Integration::add_delivery_note(
//     $order_id,
//     'Delivery delayed due to traffic',
//     true // Will be visible to customer
// );

// Gets delivery fee for an order
// Implemented in class-woocommerce-integration.php
RDM_WooCommerce_Integration::get_delivery_fee(int $order_id): float
// Example:
// $fee = RDM_WooCommerce_Integration::get_delivery_fee($order_id);
```

### User Role Functions

```php
// Gets singleton instance of User Roles class
// Implemented in class-user-roles.php
RDM_User_Roles::instance(): RDM_User_Roles

// Gets all delivery agents
// Implemented in class-user-roles.php
RDM_User_Roles::get_delivery_agents(): array
// Example:
// $agents = RDM_User_Roles::get_delivery_agents();
// foreach ($agents as $agent) {
//     echo $agent->display_name;
// }

// Checks if user is a delivery agent
// Implemented in class-user-roles.php
RDM_User_Roles::is_delivery_agent(int $user_id): bool
// Example:
// if (RDM_User_Roles::is_delivery_agent($user_id)) {
//     // User is a delivery agent
// }

// Gets all restaurant managers
// Implemented in class-user-roles.php
RDM_User_Roles::get_restaurant_managers(): array

// Checks if user is a restaurant manager
// Implemented in class-user-roles.php
RDM_User_Roles::is_restaurant_manager(int $user_id): bool
// Example:
// if (RDM_User_Roles::is_restaurant_manager($user_id)) {
//     // User is a restaurant manager
// }

// Creates a new delivery agent user
// Implemented in class-user-roles.php
RDM_User_Roles::create_delivery_agent(string $username, string $email, string $password, array $metadata = []): int|WP_Error
// Example:
// $agent_id = RDM_User_Roles::create_delivery_agent(
//     'john_delivery',
//     'john@example.com',
//     wp_generate_password(),
//     [
//         'first_name' => 'John',
//         'last_name' => 'Delivery',
//         'phone' => '555-123-4567'
//     ]
// );
```

---

## 🪝 IMPLEMENTED WORDPRESS HOOKS

### Actions

```php
// Fires when an order status is changed
// Defined in class-woocommerce-integration.php
do_action('rdm_order_status_changed', int $order_id, string $new_status, string $old_status)
// Example:
// add_action('rdm_order_status_changed', function($order_id, $new_status, $old_status) {
//     if ($new_status === 'rdm-out-for-delivery' && $old_status === 'rdm-ready-for-pickup') {
//         // Order is now out for delivery
//     }
// }, 10, 3);

// Fires when an order is assigned to a delivery agent
// Defined in class-database.php
do_action('rdm_order_assigned', int $order_id, int $agent_id)
// Example:
// add_action('rdm_order_assigned', function($order_id, $agent_id) {
//     // Order has been assigned to an agent
//     $agent = get_userdata($agent_id);
//     $order = wc_get_order($order_id);
// }, 10, 2);

// Fires when an agent location is updated
// Defined in class-rdm-gps-tracking.php
do_action('rdm_agent_location_updated', int $agent_id, float $latitude, float $longitude, ?float $accuracy)
// Example:
// add_action('rdm_agent_location_updated', function($agent_id, $lat, $lng, $accuracy) {
//     // Agent location has been updated
//     // Check if agent has active orders and update ETA
// }, 10, 4);

// Fires when an order is delivered
// Defined in class-woocommerce-integration.php
do_action('rdm_order_delivered', int $order_id, int $agent_id)
// Example:
// add_action('rdm_order_delivered', function($order_id, $agent_id) {
//     // Order has been delivered
//     // Send thank you email, update stats, etc.
// }, 10, 2);
```

### Filters

```php
// Filter the delivery fee calculation
// Defined in class-woocommerce-integration.php
apply_filters('rdm_delivery_fee_calculation', float $fee, int $order_id, float $distance_km)
// Example:
// add_filter('rdm_delivery_fee_calculation', function($fee, $order_id, $distance_km) {
//     // Custom fee calculation logic
//     if ($distance_km > 10) {
//         $fee += 5.00; // Add surcharge for long distances
//     }
//     return $fee;
// }, 10, 3);

// Filter the estimated delivery time
// Defined in class-customer-tracking.php
apply_filters('rdm_estimated_delivery_time', int $minutes, int $order_id, float $distance_km)
// Example:
// add_filter('rdm_estimated_delivery_time', function($minutes, $order_id, $distance_km) {
//     // Adjust ETA based on custom logic
//     $order = wc_get_order($order_id);
//     if ($order->get_item_count() > 5) {
//         $minutes += 10; // Add time for large orders
//     }
//     return $minutes;
// }, 10, 3);

// Filter the tracking data before sending to frontend
// Defined in class-customer-tracking.php
apply_filters('rdm_tracking_data', array $tracking_data, int $order_id)
// Example:
// add_filter('rdm_tracking_data', function($tracking_data, $order_id) {
//     // Modify or enhance tracking data
//     $tracking_data['custom_field'] = get_post_meta($order_id, '_custom_field', true);
//     return $tracking_data;
// }, 10, 2);

// Filter the agent selection for an order
// Defined in class-database.php
apply_filters('rdm_agent_selection', int $agent_id, int $order_id)
// Example:
// add_filter('rdm_agent_selection', function($agent_id, $order_id) {
//     // Custom logic to select the best agent for this order
//     $order_data = wc_get_order($order_id);
//     $customer_location = RDM_WooCommerce_Integration::get_order_delivery_data($order_id)['coordinates'];
//     
//     // Find the closest available agent
//     $agents = RDM_User_Roles::get_delivery_agents();
//     // ... custom selection logic
//     
//     return $best_agent_id;
// }, 10, 2);
```

---

## 🗃️ DATABASE SCHEMA REFERENCE

### Table: {prefix}rdm_delivery_agents

```sql
CREATE TABLE {$wpdb->prefix}rdm_delivery_agents (
    agent_id bigint(20) UNSIGNED NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    available tinyint(1) NOT NULL DEFAULT 0,
    current_lat decimal(10,7) DEFAULT NULL,
    current_lng decimal(10,7) DEFAULT NULL,
    battery_level tinyint(3) UNSIGNED DEFAULT NULL,
    last_active datetime DEFAULT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY (agent_id),
    KEY status (status),
    KEY available (available),
    KEY last_active (last_active)
) {$charset_collate};
```

### Table: {prefix}rdm_order_assignments

```sql
CREATE TABLE {$wpdb->prefix}rdm_order_assignments (
    assignment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id bigint(20) UNSIGNED NOT NULL,
    agent_id bigint(20) UNSIGNED NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'assigned',
    assigned_at datetime NOT NULL,
    accepted_at datetime DEFAULT NULL,
    picked_up_at datetime DEFAULT NULL,
    delivered_at datetime DEFAULT NULL,
    PRIMARY KEY (assignment_id),
    UNIQUE KEY order_id (order_id),
    KEY agent_id (agent_id),
    KEY status (status)
) {$charset_collate};
```

### Table: {prefix}rdm_location_tracking

```sql
CREATE TABLE {$wpdb->prefix}rdm_location_tracking (
    location_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id bigint(20) UNSIGNED NOT NULL,
    latitude decimal(10,7) NOT NULL,
    longitude decimal(10,7) NOT NULL,
    accuracy float UNSIGNED DEFAULT NULL,
    battery_level tinyint(3) UNSIGNED DEFAULT NULL,
    recorded_at datetime NOT NULL,
    PRIMARY KEY (location_id),
    KEY agent_id (agent_id),
    KEY recorded_at (recorded_at)
) {$charset_collate};
```

### Table: {prefix}rdm_delivery_notes

```sql
CREATE TABLE {$wpdb->prefix}rdm_delivery_notes (
    note_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    note_type varchar(20) NOT NULL DEFAULT 'general',
    note_content text NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY (note_id),
    KEY order_id (order_id),
    KEY user_id (user_id),
    KEY note_type (note_type)
) {$charset_collate};
```

### Table: {prefix}rdm_delivery_areas

```sql
CREATE TABLE {$wpdb->prefix}rdm_delivery_areas (
    area_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    boundaries text NOT NULL,
    base_fee decimal(10,2) NOT NULL DEFAULT 0.00,
    min_order_amount decimal(10,2) NOT NULL DEFAULT 0.00,
    max_distance decimal(5,1) NOT NULL DEFAULT 0.0,
    active tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (area_id),
    KEY active (active)
) {$charset_collate};
```