<?php
/**
 * Restaurant Delivery Manager - Database Handler
 *
 * @package RestaurantDeliveryManager
 * @subpackage Database
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler class
 *
 * Manages all database operations including table creation, CRUD operations,
 * and database maintenance for the restaurant delivery management system.
 *
 * @class RDM_Database
 * @version 1.0.0
 */
class RDM_Database {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Database|null
     */
    private static ?RDM_Database $instance = null;
    
    /**
     * Database version
     *
     * @var string
     */
    private string $db_version = '1.0.0';
    
    /**
     * Table names
     *
     * @var array
     */
    private array $tables;
    
    /**
     * Main RDM_Database Instance
     *
     * @return RDM_Database Main instance
     */
    public static function instance(): RDM_Database {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        global $wpdb;
        
        // Define table names with proper prefix (using rr_ prefix)
        $this->tables = array(
            'delivery_agents' => $wpdb->prefix . 'rr_delivery_agents',
            'order_assignments' => $wpdb->prefix . 'rr_order_assignments',
            'location_tracking' => $wpdb->prefix . 'rr_location_tracking',
            'delivery_notes' => $wpdb->prefix . 'rr_delivery_notes',
            'delivery_areas' => $wpdb->prefix . 'rr_delivery_areas',
        );
    }
    
    /**
     * Get table name
     *
     * @since 1.0.0
     * @param string $table Table identifier
     * @return string|null Full table name or null if not found
     */
    public function get_table_name(string $table): ?string {
        return $this->tables[$table] ?? null;
    }
    
    /**
     * Create all database tables
     *
     * @since 1.0.0
     * @return void
     */
    public function create_tables(): void {
        global $wpdb;
        
        try {
            // Include WordPress upgrade functions
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            error_log('RestroReach: Starting database table creation...');
            
            // Create delivery agents table
            $this->create_delivery_agents_table($charset_collate);
            error_log('RestroReach: Delivery agents table created');
            
            // Create order assignments table
            $this->create_order_assignments_table($charset_collate);
            error_log('RestroReach: Order assignments table created');
            
            // Create location tracking table
            $this->create_location_tracking_table($charset_collate);
            error_log('RestroReach: Location tracking table created');
            
            // Create delivery notes table
            $this->create_delivery_notes_table($charset_collate);
            error_log('RestroReach: Delivery notes table created');
            
            // Create delivery areas table
            $this->create_delivery_areas_table($charset_collate);
            error_log('RestroReach: Delivery areas table created');
            
            // Update database version
            update_option('rdm_db_version', $this->db_version);
            error_log('RestroReach: Database version updated');
            
            // Verify tables were created
            $this->verify_tables_created();
            
            // Fire action after tables created
            do_action('rdm_database_tables_created');
            
            error_log('RestroReach: All database tables created successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: Database creation failed - ' . $e->getMessage());
            throw new Exception('Database table creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify that all tables were created successfully
     *
     * @since 1.0.0
     * @throws Exception If tables are missing
     */
    private function verify_tables_created(): void {
        global $wpdb;
        
        $missing_tables = array();
        
        foreach ($this->tables as $table_key => $table_name) {
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            
            if ($table_exists !== $table_name) {
                $missing_tables[] = $table_name;
                error_log("RestroReach: Table verification failed for: {$table_name}");
            } else {
                error_log("RestroReach: Table verified successfully: {$table_name}");
            }
        }
        
        if (!empty($missing_tables)) {
            $missing_list = implode(', ', $missing_tables);
            throw new Exception("Failed to create tables: {$missing_list}");
        }
    }
    
    /**
     * Create delivery agents table
     *
     * @since 1.0.0
     * @param string $charset_collate Charset collation
     * @return void
     */
    private function create_delivery_agents_table(string $charset_collate): void {
        global $wpdb;
        
        $table_name = $this->tables['delivery_agents'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'active',
            phone varchar(20) NOT NULL,
            vehicle_type varchar(50) DEFAULT 'bike',
            availability tinyint(1) DEFAULT 1,
            current_capacity int(3) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY availability (availability)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log('RestroReach: Delivery agents table dbDelta result: ' . print_r($result, true));
    }
    
    /**
     * Create order assignments table
     *
     * @since 1.0.0
     * @param string $charset_collate Charset collation
     * @return void
     */
    private function create_order_assignments_table(string $charset_collate): void {
        global $wpdb;
        
        $table_name = $this->tables['order_assignments'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            agent_id mediumint(9) NOT NULL,
            status varchar(20) DEFAULT 'assigned',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            picked_up_at datetime NULL,
            delivered_at datetime NULL,
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY agent_id (agent_id),
            KEY status (status)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log('RestroReach: Order assignments table dbDelta result: ' . print_r($result, true));
    }
    
    /**
     * Create location tracking table
     *
     * @since 1.0.0
     * @param string $charset_collate Charset collation
     * @return void
     */
    private function create_location_tracking_table(string $charset_collate): void {
        global $wpdb;
        
        $table_name = $this->tables['location_tracking'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            agent_id mediumint(9) NOT NULL,
            latitude decimal(10, 8) NOT NULL,
            longitude decimal(11, 8) NOT NULL,
            accuracy float DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            battery_level int(3) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY agent_id (agent_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log('RestroReach: Location tracking table dbDelta result: ' . print_r($result, true));
    }
    
    /**
     * Create delivery notes table
     *
     * @since 1.0.0
     * @param string $charset_collate Charset collation
     * @return void
     */
    private function create_delivery_notes_table(string $charset_collate): void {
        global $wpdb;
        
        $table_name = $this->tables['delivery_notes'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            note_text text NOT NULL,
            note_type varchar(20) DEFAULT 'general',
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_customer_visible tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY note_type (note_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log('RestroReach: Delivery notes table dbDelta result: ' . print_r($result, true));
    }
    
    /**
     * Create delivery areas table
     *
     * @since 1.0.0
     * @param string $charset_collate Charset collation
     * @return void
     */
    private function create_delivery_areas_table(string $charset_collate): void {
        global $wpdb;
        
        $table_name = $this->tables['delivery_areas'];
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            area_name varchar(100) NOT NULL,
            area_coordinates text NOT NULL,
            delivery_fee decimal(10, 2) DEFAULT 0.00,
            min_order_amount decimal(10, 2) DEFAULT 0.00,
            max_delivery_time int(5) DEFAULT 60,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY area_name (area_name),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log('RestroReach: Delivery areas table dbDelta result: ' . print_r($result, true));
    }
    /**
     * Drop all plugin tables
     *
     * @since 1.0.0
     * @return void
     */
    public function drop_tables(): void {
        global $wpdb;

        foreach ($this->tables as $table) {
            // Validate $table against the plugin's known table names
            if (!empty($this->tables) && is_array($this->tables) && in_array($table, array_values($this->tables))) {
                // $table is a known table name, proceed with caution
                // $wpdb->prepare() cannot use placeholders for table names directly.
                // The validation above is crucial.
                $sql = "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`";
                $wpdb->query($sql);
                $this->log_db_query($sql, "Table dropped: $table");
            } else {
                // Log error: attempt to drop an unknown table
                $this->log_db_error("Attempted to drop an unknown or invalid table: " . esc_html($table));
            }
        }

        delete_option('rdm_db_version');
    }

    /**
     * Force recreate all tables (for debugging)
     *
     * @since 1.0.0
     * @return array Results of table creation
     */
    public function force_recreate_tables(): array {
        global $wpdb;
        
        $results = array(
            'success' => false,
            'message' => '',
            'tables_created' => array(),
            'errors' => array()
        );
        
        try {
            // Include WordPress upgrade functions
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Drop existing tables first
            error_log('RestroReach: Dropping existing tables for recreation...');
            $this->drop_tables();
            
            // Create tables one by one with individual error handling
            $table_methods = array(
                'delivery_agents' => 'create_delivery_agents_table',
                'order_assignments' => 'create_order_assignments_table',
                'location_tracking' => 'create_location_tracking_table',
                'delivery_notes' => 'create_delivery_notes_table',
                'delivery_areas' => 'create_delivery_areas_table'
            );
            
            foreach ($table_methods as $table_key => $method) {
                try {
                    $this->$method($charset_collate);
                    
                    // Verify this specific table was created
                    $table_name = $this->tables[$table_key];
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                    
                    if ($exists === $table_name) {
                        $results['tables_created'][] = $table_key;
                        error_log("RestroReach: Successfully created table: {$table_key}");
                    } else {
                        $results['errors'][] = "Failed to create table: {$table_key}";
                        error_log("RestroReach: Failed to create table: {$table_key}");
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Error creating {$table_key}: " . $e->getMessage();
                    error_log("RestroReach: Error creating {$table_key}: " . $e->getMessage());
                }
            }
            
            // Update database version
            update_option('rdm_db_version', $this->db_version);
            
            // Check final status
            $all_created = $this->are_all_tables_created();
            $results['success'] = $all_created;
            $results['message'] = $all_created 
                ? 'All tables created successfully' 
                : 'Some tables failed to create';
            
            if ($all_created) {
                // Fire action after tables created
                do_action('rdm_database_tables_created');
            }
            
        } catch (Exception $e) {
            $results['errors'][] = 'Fatal error: ' . $e->getMessage();
            error_log('RestroReach: Fatal error in force_recreate_tables: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    // ========================================
    // CRUD Operations for Delivery Agents
    // ========================================
    
    /**
     * Create a new delivery agent
     *
     * @since 1.0.0
     * @param int $user_id WordPress user ID
     * @param string $phone Phone number
     * @param string $vehicle_type Vehicle type (bike, car, etc.)
     * @return int|false Agent ID on success, false on failure
     */
    public function create_agent(int $user_id, string $phone, string $vehicle_type = 'bike') {
        global $wpdb;
        
        // Validate input
        if (!$user_id || !$phone) {
            return false;
        }
        
        // Sanitize data
        $data = array(
            'user_id' => absint($user_id),
            'phone' => sanitize_text_field($phone),
            'vehicle_type' => sanitize_text_field($vehicle_type),
            'status' => 'active',
            'availability' => 1,
        );
        
        $result = $wpdb->insert(
            $this->tables['delivery_agents'],
            $data,
            array('%d', '%s', '%s', '%s', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get delivery agent by ID
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @return object|null Agent object or null if not found
     */
    public function get_agent(int $agent_id): ?object {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['delivery_agents']} WHERE id = %d",
            $agent_id
        ));
    }
    
    /**
     * Get delivery agent by user ID
     *
     * @since 1.0.0
     * @param int $user_id WordPress user ID
     * @return object|null Agent object or null if not found
     */
    public function get_agent_by_user_id(int $user_id): ?object {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['delivery_agents']} WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Update delivery agent
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param array $data Array of agent data to update. Accepted keys: 'status' (string), 'phone' (string), 'vehicle_type' (string), 'availability' (int 0|1), 'current_capacity' (int)
     * @return bool True on success, false on failure
     */
    public function update_agent(int $agent_id, array $data): bool {
        global $wpdb;
        
        // Allowed fields for update
        $allowed_fields = array('status', 'phone', 'vehicle_type', 'availability', 'current_capacity');
        
        // Filter and sanitize data
        $update_data = array();
        $format = array();
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields, true)) {
                switch ($key) {
                    case 'status':
                    case 'phone':
                    case 'vehicle_type':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'availability':
                        $update_data[$key] = (int) $value;
                        $format[] = '%d';
                        break;
                    case 'current_capacity':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->tables['delivery_agents'],
            $update_data,
            array('id' => $agent_id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get available agents
     *
     * @since 1.0.0
     * @param string $area_id Optional area ID to filter by
     * @return array Array of available agents
     */
    public function get_available_agents(string $area_id = ''): array {
        global $wpdb;
        
        $query = "SELECT da.*, u.display_name, u.user_email 
                  FROM {$this->tables['delivery_agents']} da
                  INNER JOIN {$wpdb->users} u ON da.user_id = u.ID
                  WHERE da.availability = 1 
                  AND da.status = 'active'";
        
        // Add area filter if provided
        if ($area_id) {
            // This would require a join with area assignments table
            // Placeholder for now
        }
        
        $query .= " ORDER BY da.id ASC";
        
        return $wpdb->get_results($query);
    }
    
    // ========================================
    // CRUD Operations for Order Assignments
    // ========================================
    
    /**
     * Assign order to agent
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID
     * @param int $agent_id Agent ID
     * @return int|false Assignment ID on success, false on failure
     */
    public function assign_order(int $order_id, int $agent_id) {
        global $wpdb;
        
        // Check if order is already assigned
        $existing = $this->get_order_assignment($order_id);
        if ($existing) {
            return false;
        }
        
        $data = array(
            'order_id' => $order_id,
            'agent_id' => $agent_id,
            'status' => 'assigned',
            'assigned_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert(
            $this->tables['order_assignments'],
            $data,
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            $assignment_id = $wpdb->insert_id;
            
            // Fire action hook
            do_action('rdm_order_assigned', $order_id, $agent_id);
            
            return $assignment_id;
        }
        
        return false;
    }
    
    /**
     * Get order assignment
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return object|null Assignment object or null if not found
     */
    public function get_order_assignment(int $order_id): ?object {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['order_assignments']} WHERE order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Update assignment status
     *
     * @since 1.0.0
     * @param int $assignment_id Assignment ID
     * @param string $status New status
     * @param array $additional_data Additional data to update
     * @return bool True on success, false on failure
     */
    public function update_assignment_status(int $assignment_id, string $status, array $additional_data = array()): bool {
        global $wpdb;
        
        $update_data = array('status' => sanitize_text_field($status));
        $format = array('%s');
        
        // Handle status-specific updates
        switch ($status) {
            case 'picked_up':
                $update_data['picked_up_at'] = current_time('mysql');
                $format[] = '%s';
                break;
            case 'delivered':
                $update_data['delivered_at'] = current_time('mysql');
                $format[] = '%s';
                break;
        }
        
        // Add any additional data
        if (isset($additional_data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($additional_data['notes']);
            $format[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->tables['order_assignments'],
            $update_data,
            array('id' => $assignment_id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get agent's active orders
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param string $status Optional status filter
     * @return array Array of orders
     */
    public function get_agent_orders(int $agent_id, string $status = ''): array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT oa.*, p.post_date as order_date
             FROM {$this->tables['order_assignments']} oa
             INNER JOIN {$wpdb->posts} p ON oa.order_id = p.ID
             WHERE oa.agent_id = %d",
            $agent_id
        );
        
        if ($status) {
            $query .= $wpdb->prepare(" AND oa.status = %s", $status);
        }
        
        $query .= " ORDER BY oa.assigned_at DESC";
        
        return $wpdb->get_results($query);
    }
    
    // ========================================
    // CRUD Operations for Location Tracking
    // ========================================
    
    /**
     * Save agent location
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param float|null $accuracy GPS accuracy
     * @param int|null $battery Battery level percentage
     * @return bool True on success, false on failure
     */
    public function save_location(int $agent_id, float $latitude, float $longitude, ?float $accuracy = null, ?int $battery = null): bool {
        global $wpdb;
        
        $data = array(
            'agent_id' => $agent_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timestamp' => current_time('mysql'),
        );
        
        $format = array('%d', '%f', '%f', '%s');
        
        if (!is_null($accuracy)) {
            $data['accuracy'] = $accuracy;
            $format[] = '%f';
        }
        
        if (!is_null($battery)) {
            $data['battery_level'] = $battery;
            $format[] = '%d';
        }
        
        $result = $wpdb->insert(
            $this->tables['location_tracking'],
            $data,
            $format
        );
        
        if ($result) {
            // Fire action hook
            do_action('rdm_agent_location_updated', $agent_id, $latitude, $longitude);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get agent's latest location
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @return object|null Location object or null if not found
     */
    public function get_agent_location(int $agent_id): ?object {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['location_tracking']} 
             WHERE agent_id = %d 
             ORDER BY timestamp DESC 
             LIMIT 1",
            $agent_id
        ));
    }
    
    /**
     * Get location history
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param int $hours Number of hours to retrieve
     * @return array Array of location records
     */
    public function get_location_history(int $agent_id, int $hours = 24): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tables['location_tracking']} 
             WHERE agent_id = %d 
             AND timestamp >= DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY timestamp DESC",
            $agent_id,
            $hours
        ));
    }
    
    // ========================================
    // CRUD Operations for Delivery Notes
    // ========================================
    
    /**
     * Add order note
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param string $note_text Note content
     * @param string $note_type Note type
     * @param bool $customer_visible Whether note is visible to customer
     * @return int|false Note ID on success, false on failure
     */
    public function add_order_note(int $order_id, string $note_text, string $note_type = 'general', bool $customer_visible = true) {
        global $wpdb;
        
        $data = array(
            'order_id' => $order_id,
            'note_text' => sanitize_textarea_field($note_text),
            'note_type' => sanitize_text_field($note_type),
            'created_by' => get_current_user_id(),
            'is_customer_visible' => $customer_visible ? 1 : 0,
        );
        
        $result = $wpdb->insert(
            $this->tables['delivery_notes'],
            $data,
            array('%d', '%s', '%s', '%d', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get order notes
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param bool $customer_visible_only Only get customer visible notes
     * @return array Array of notes
     */
    public function get_order_notes(int $order_id, bool $customer_visible_only = false): array {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT dn.*, u.display_name as author_name
             FROM {$this->tables['delivery_notes']} dn
             LEFT JOIN {$wpdb->users} u ON dn.created_by = u.ID
             WHERE dn.order_id = %d",
            $order_id
        );
        
        if ($customer_visible_only) {
            $query .= " AND dn.is_customer_visible = 1";
        }
        
        $query .= " ORDER BY dn.created_at DESC";
        
        return $wpdb->get_results($query);
    }
    
    // ========================================
    // CRUD Operations for Delivery Areas
    // ========================================
    
    /**
     * Create delivery area
     *
     * @since 1.0.0
     * @param array $area_data Area data
     * @return int|false Area ID on success, false on failure
     */
    public function create_delivery_area(array $area_data) {
        global $wpdb;
        
        $data = array(
            'area_name' => sanitize_text_field($area_data['area_name']),
            'area_coordinates' => wp_json_encode($area_data['area_coordinates']),
            'delivery_fee' => floatval($area_data['delivery_fee']),
            'min_order_amount' => floatval($area_data['min_order_amount']),
            'max_delivery_time' => absint($area_data['max_delivery_time']),
            'is_active' => isset($area_data['is_active']) ? 1 : 0,
        );
        
        $result = $wpdb->insert(
            $this->tables['delivery_areas'],
            $data,
            array('%s', '%s', '%f', '%f', '%d', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get all active delivery areas
     *
     * @since 1.0.0
     * @return array Array of delivery areas
     */
    public function get_delivery_areas(): array {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->tables['delivery_areas']} 
             WHERE is_active = 1 
             ORDER BY area_name ASC"
        );
    }
    
    /**
     * Update delivery area
     *
     * @since 1.0.0
     * @param int $area_id Area ID
     * @param array $area_data Updated area data
     * @return bool True on success, false on failure
     */
    public function update_delivery_area(int $area_id, array $area_data): bool {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        // Allowed fields for update
        $allowed_fields = array('area_name', 'area_coordinates', 'delivery_fee', 'min_order_amount', 'max_delivery_time', 'is_active');
        
        foreach ($area_data as $key => $value) {
            if (in_array($key, $allowed_fields, true)) {
                switch ($key) {
                    case 'area_name':
                        $update_data[$key] = sanitize_text_field($value);
                        $format[] = '%s';
                        break;
                    case 'area_coordinates':
                        $update_data[$key] = wp_json_encode($value);
                        $format[] = '%s';
                        break;
                    case 'delivery_fee':
                    case 'min_order_amount':
                        $update_data[$key] = floatval($value);
                        $format[] = '%f';
                        break;
                    case 'max_delivery_time':
                        $update_data[$key] = absint($value);
                        $format[] = '%d';
                        break;
                    case 'is_active':
                        $update_data[$key] = $value ? 1 : 0;
                        $format[] = '%d';
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->tables['delivery_areas'],
            $update_data,
            array('id' => $area_id),
            $format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    // ========================================
    // Utility Methods
    // ========================================
    
    /**
     * Get nearby agents based on location
     *
     * @since 1.0.0
     * @param float $latitude Center latitude
     * @param float $longitude Center longitude
     * @param float $radius_km Radius in kilometers
     * @return array Array of nearby agents with distance
     */
    public function get_nearby_agents(float $latitude, float $longitude, float $radius_km = 5): array {
        global $wpdb;
        
        // Using Haversine formula for distance calculation
        $query = $wpdb->prepare(
            "SELECT da.*, lt.latitude, lt.longitude, u.display_name,
                (6371 * acos(cos(radians(%f)) * cos(radians(lt.latitude)) * 
                cos(radians(lt.longitude) - radians(%f)) + sin(radians(%f)) * 
                sin(radians(lt.latitude)))) AS distance
             FROM {$this->tables['delivery_agents']} da
             INNER JOIN {$this->tables['location_tracking']} lt ON da.id = lt.agent_id
             INNER JOIN {$wpdb->users} u ON da.user_id = u.ID
             WHERE da.availability = 1 
             AND da.status = 'active'
             AND lt.timestamp >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             AND lt.timestamp = (
                 SELECT MAX(timestamp) 
                 FROM {$this->tables['location_tracking']} 
                 WHERE agent_id = da.id
             )
             HAVING distance <= %f
             ORDER BY distance ASC",
            $latitude,
            $longitude,
            $latitude,
            $radius_km
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get agent performance metrics
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param int $days Number of days to analyze
     * @return array Performance metrics
     */
    public function get_agent_performance(int $agent_id, int $days = 30): array {
        global $wpdb;
        
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_deliveries,
                AVG(TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at)) as avg_delivery_time,
                SUM(CASE WHEN delivered_at <= DATE_ADD(assigned_at, INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) as on_time_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                SUM(CASE WHEN status IN ('cancelled', 'failed') THEN 1 ELSE 0 END) as failed_deliveries
             FROM {$this->tables['order_assignments']}
             WHERE agent_id = %d
             AND assigned_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $agent_id,
            $days
        ), ARRAY_A);
        
        // Calculate on-time percentage
        if ($metrics['total_deliveries'] > 0) {
            $metrics['on_time_percentage'] = round(($metrics['on_time_deliveries'] / $metrics['total_deliveries']) * 100, 2);
        } else {
            $metrics['on_time_percentage'] = 0;
        }
        
        return $metrics;
    }
    
    /**
     * Clean old location data
     *
     * @since 1.0.0
     * @param int $days Number of days to keep
     * @return int Number of records deleted
     */
    public function cleanup_old_locations(int $days = 7): int {
        global $wpdb;
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['location_tracking']} 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return $deleted;
    }
    
    /**
     * Check if coordinates are within delivery area
     *
     * @since 1.0.0
     * @param float $latitude Latitude to check
     * @param float $longitude Longitude to check
     * @param int $area_id Area ID to check against
     * @return bool True if within area, false otherwise
     */
    public function is_location_in_area(float $latitude, float $longitude, int $area_id): bool {
        global $wpdb;
        
        $area = $wpdb->get_row($wpdb->prepare(
            "SELECT area_coordinates FROM {$this->tables['delivery_areas']} WHERE id = %d AND is_active = 1",
            $area_id
        ));
        
        if (!$area) {
            return false;
        }
        
        $coordinates = json_decode($area->area_coordinates, true);
        if (!$coordinates || !is_array($coordinates)) {
            return false;
        }
        
        // Point-in-polygon algorithm
        return $this->point_in_polygon($latitude, $longitude, $coordinates);
    }
    
    /**
     * Check if point is within polygon
     *
     * @since 1.0.0
     * @param float $latitude Point latitude
     * @param float $longitude Point longitude
     * @param array $polygon Array of coordinates forming polygon
     * @return bool True if point is inside polygon
     */
    private function point_in_polygon(float $latitude, float $longitude, array $polygon): bool {
        $inside = false;
        $count = count($polygon);
        
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lng'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lng'];
            
            $intersect = (($yi > $longitude) != ($yj > $longitude))
                && ($latitude < ($xj - $xi) * ($longitude - $yi) / ($yj - $yi) + $xi);
                
            if ($intersect) {
                $inside = !$inside;
            }
        }
        
        return $inside;
    }
    
    // ========================================
    // Database Version Management
    // ========================================
    
    /**
     * Get current database version
     *
     * @since 1.0.0
     * @return string Current database version
     */
    public function get_db_version(): string {
        return get_option('rdm_db_version', '0.0.0');
    }
    
    /**
     * Check if database needs upgrade
     *
     * @since 1.0.0
     * @return bool True if upgrade needed
     */
    public function needs_upgrade(): bool {
        return version_compare($this->get_db_version(), $this->db_version, '<');
    }
    
    /**
     * Run database migrations
     *
     * @since 1.0.0
     * @return array Migration results
     */
    public function run_migrations(): array {
        $current_version = $this->get_db_version();
        $target_version = $this->db_version;
        $results = array();
        
        // Log migration start
        $this->log_database_event('migration_start', array(
            'from_version' => $current_version,
            'to_version' => $target_version,
        ));
        
        // Get all migrations between current and target version
        $migrations = $this->get_pending_migrations($current_version, $target_version);
        
        foreach ($migrations as $version => $migration_callback) {
            try {
                // Start transaction
                $this->start_transaction();
                
                // Run migration
                $result = call_user_func($migration_callback);
                
                if ($result === true) {
                    // Commit transaction
                    $this->commit_transaction();
                    
                    // Update version
                    update_option('rdm_db_version', $version);
                    
                    $results[$version] = array(
                        'status' => 'success',
                        'message' => sprintf(__('Successfully migrated to version %s', 'restaurant-delivery-manager'), $version),
                    );
                    
                    $this->log_database_event('migration_success', array('version' => $version));
                } else {
                    // Rollback transaction
                    $this->rollback_transaction();
                    
                    $results[$version] = array(
                        'status' => 'failed',
                        'message' => sprintf(__('Failed to migrate to version %s', 'restaurant-delivery-manager'), $version),
                        'error' => $result,
                    );
                    
                    $this->log_database_event('migration_failed', array(
                        'version' => $version,
                        'error' => $result,
                    ));
                    
                    // Stop further migrations
                    break;
                }
            } catch (Exception $e) {
                // Rollback transaction
                $this->rollback_transaction();
                
                $results[$version] = array(
                    'status' => 'error',
                    'message' => sprintf(__('Error during migration to version %s', 'restaurant-delivery-manager'), $version),
                    'error' => $e->getMessage(),
                );
                
                $this->log_database_event('migration_error', array(
                    'version' => $version,
                    'error' => $e->getMessage(),
                ));
                
                // Stop further migrations
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Get pending migrations
     *
     * @since 1.0.0
     * @param string $from_version Current version
     * @param string $to_version Target version
     * @return array Array of migrations to run
     */
    private function get_pending_migrations(string $from_version, string $to_version): array {
        $migrations = array(
            '1.0.1' => array($this, 'migrate_to_1_0_1'),
            '1.1.0' => array($this, 'migrate_to_1_1_0'),
            // Add new migrations here
        );
        
        $pending = array();
        
        foreach ($migrations as $version => $callback) {
            if (version_compare($version, $from_version, '>') && 
                version_compare($version, $to_version, '<=')) {
                $pending[$version] = $callback;
            }
        }
        
        return $pending;
    }
    
    /**
     * Example migration to version 1.0.1
     *
     * @since 1.0.0
     * @return bool|string True on success, error message on failure
     */
    private function migrate_to_1_0_1() {
        global $wpdb;
        
        // Example: Add a new column to delivery_agents table
        $table_name = $this->tables['delivery_agents'];
        
        // Validate table name against known tables
        if (!in_array($table_name, array_values($this->tables))) {
            return 'Invalid table name for migration: ' . esc_html($table_name);
        }
        
        $safe_table_name = str_replace('`', '``', $table_name);
        $column_name_to_check = 'rating'; // This is static
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `" . $safe_table_name . "` LIKE %s",
            $column_name_to_check
        ));
        
        if (empty($column_exists)) {
            // Assuming column definition is static/safe:
            $sql = "ALTER TABLE `" . $safe_table_name . "` ADD COLUMN rating DECIMAL(3,2) DEFAULT 0.00 AFTER current_capacity";
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                return $wpdb->last_error;
            }
            
            $this->log_db_query($sql, "Added rating column to delivery_agents table");
        }
        
        return true;
    }
    
    /**
     * Example migration to version 1.1.0
     *
     * @since 1.0.0
     * @return bool|string True on success, error message on failure
     */
    private function migrate_to_1_1_0() {
        global $wpdb;
        
        // Example: Add index for performance
        $table_name = $this->tables['location_tracking'];
        
        // Validate table name against known tables
        if (!in_array($table_name, array_values($this->tables))) {
            return 'Invalid table name for migration: ' . esc_html($table_name);
        }
        
        $safe_table_name = str_replace('`', '``', $table_name);
        $index_name = 'idx_agent_timestamp'; // Static index name
        $columns = 'agent_id, timestamp'; // Static column definition
        
        $sql = "ALTER TABLE `" . $safe_table_name . "` ADD INDEX " . $index_name . " (" . $columns . ")";
        $result = $wpdb->query($sql);
        
        if ($result === false && !strpos($wpdb->last_error, 'Duplicate key name')) {
            return $wpdb->last_error;
        }
        
        $this->log_db_query($sql, "Added index to location_tracking table");
        
        return true;
    }
    
    // ========================================
    // Transaction Support
    // ========================================
    
    /**
     * Start database transaction
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function start_transaction(): bool {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        return true;
    }
    
    /**
     * Commit database transaction
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function commit_transaction(): bool {
        global $wpdb;
        
        $wpdb->query('COMMIT');
        return true;
    }
    
    /**
     * Rollback database transaction
     *
     * @since 1.0.0
     * @return bool True on success
     */
    public function rollback_transaction(): bool {
        global $wpdb;
        
        $wpdb->query('ROLLBACK');
        return true;
    }
    
    // ========================================
    // Batch Operations
    // ========================================
    
    /**
     * Batch assign orders to agents
     *
     * @since 1.0.0
     * @param array $assignments Array of order_id => agent_id pairs
     * @return array Results array with success/failure for each assignment
     */
    public function batch_assign_orders(array $assignments): array {
        $results = array();
        
        $this->start_transaction();
        $all_success = true;
        
        foreach ($assignments as $order_id => $agent_id) {
            $result = $this->assign_order($order_id, $agent_id);
            
            if ($result) {
                $results[$order_id] = array(
                    'status' => 'success',
                    'assignment_id' => $result,
                );
            } else {
                $results[$order_id] = array(
                    'status' => 'failed',
                    'error' => __('Failed to assign order', 'restaurant-delivery-manager'),
                );
                $all_success = false;
            }
        }
        
        if ($all_success) {
            $this->commit_transaction();
        } else {
            $this->rollback_transaction();
        }
        
        return $results;
    }
    
    /**
     * Batch update agent availability
     *
     * @since 1.0.0
     * @param array $agent_ids Array of agent IDs
     * @param bool $available Availability status
     * @return int Number of agents updated
     */
    public function batch_update_availability(array $agent_ids, bool $available): int {
        global $wpdb;
        
        if (empty($agent_ids)) {
            return 0;
        }
        
        $agent_ids = array_map('intval', $agent_ids);
        $placeholders = implode(',', array_fill(0, count($agent_ids), '%d'));
        
        $query = $wpdb->prepare(
            "UPDATE {$this->tables['delivery_agents']} 
             SET availability = %d 
             WHERE id IN ($placeholders)",
            array_merge(array($available ? 1 : 0), $agent_ids)
        );
        
        $result = $wpdb->query($query);
        
        return $result === false ? 0 : $result;
    }
    
    /**
     * Batch delete old locations
     *
     * @since 1.0.0
     * @param array $agent_ids Agent IDs to clean
     * @param int $days Days to keep
     * @return int Number of records deleted
     */
    public function batch_cleanup_locations(array $agent_ids, int $days = 7): int {
        global $wpdb;
        
        if (empty($agent_ids)) {
            return $this->cleanup_old_locations($days);
        }
        
        $agent_ids = array_map('intval', $agent_ids);
        $placeholders = implode(',', array_fill(0, count($agent_ids), '%d'));
        
        $query = $wpdb->prepare(
            "DELETE FROM {$this->tables['location_tracking']} 
             WHERE agent_id IN ($placeholders)
             AND timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            array_merge($agent_ids, array($days))
        );
        
        $deleted = $wpdb->query($query);
        
        return $deleted === false ? 0 : $deleted;
    }
    
    // ========================================
    // Error Logging and Recovery
    // ========================================
    
    /**
     * Log database event
     *
     * @since 1.0.0
     * @param string $event_type Event type
     * @param array $data Event data
     * @return void
     */
    private function log_database_event(string $event_type, array $data = array()): void {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'data' => $data,
            'user_id' => get_current_user_id(),
        );
        
        // Store in options table (could be moved to custom log table)
        $logs = get_option('rdm_database_logs', array());
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        $logs[] = $log_entry;
        update_option('rdm_database_logs', $logs);
        
        // Also log to error_log if it's an error
        if (in_array($event_type, array('migration_failed', 'migration_error', 'health_check_failed'), true)) {
            error_log('RestroReach Database Event: ' . wp_json_encode($log_entry));
        }
    }
    
    /**
     * Get database logs
     *
     * @since 1.0.0
     * @param string $event_type Optional event type filter
     * @param int $limit Number of logs to retrieve
     * @return array Array of log entries
     */
    public function get_database_logs(string $event_type = '', int $limit = 50): array {
        $logs = get_option('rdm_database_logs', array());
        
        if ($event_type) {
            $logs = array_filter($logs, function($log) use ($event_type) {
                return $log['event_type'] === $event_type;
            });
        }
        
        // Return most recent first
        $logs = array_reverse($logs);
        
        return array_slice($logs, 0, $limit);
    }
    
    // ========================================
    // Database Health Checks
    // ========================================
    
    /**
     * Perform database health check
     *
     * @since 1.0.0
     * @return array Health check results
     */
    public function health_check(): array {
        $results = array(
            'status' => 'healthy',
            'checks' => array(),
            'timestamp' => current_time('mysql'),
        );
        
        // Check 1: Table existence
        $table_check = $this->check_tables_exist();
        $results['checks']['tables'] = $table_check;
        if ($table_check['status'] !== 'passed') {
            $results['status'] = 'unhealthy';
        }
        
        // Check 2: Table structure
        $structure_check = $this->check_table_structure();
        $results['checks']['structure'] = $structure_check;
        if ($structure_check['status'] !== 'passed') {
            $results['status'] = 'warning';
        }
        
        // Check 3: Indexes
        $index_check = $this->check_indexes();
        $results['checks']['indexes'] = $index_check;
        if ($index_check['status'] !== 'passed') {
            $results['status'] = 'warning';
        }
        
        // Check 4: Data integrity
        $integrity_check = $this->check_data_integrity();
        $results['checks']['integrity'] = $integrity_check;
        if ($integrity_check['status'] !== 'passed') {
            $results['status'] = 'unhealthy';
        }
        
        // Check 5: Performance
        $performance_check = $this->check_performance();
        $results['checks']['performance'] = $performance_check;
        if ($performance_check['status'] !== 'passed') {
            $results['status'] = 'warning';
        }
        
        // Check 6: Storage
        $storage_check = $this->check_storage();
        $results['checks']['storage'] = $storage_check;
        
        // Log health check results
        $this->log_database_event('health_check', $results);
        
        return $results;
    }
    
    /**
     * Check if all tables exist
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_tables_exist(): array {
        global $wpdb;
        
        $missing_tables = array();
        
        foreach ($this->tables as $table_key => $table_name) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if (!$exists) {
                $missing_tables[] = $table_key;
            }
        }
        
        if (empty($missing_tables)) {
            return array(
                'status' => 'passed',
                'message' => __('All tables exist', 'restaurant-delivery-manager'),
            );
        } else {
            return array(
                'status' => 'failed',
                'message' => sprintf(
                    __('Missing tables: %s', 'restaurant-delivery-manager'),
                    implode(', ', $missing_tables)
                ),
                'missing_tables' => $missing_tables,
            );
        }
    }
    
    /**
     * Simple check if all tables exist (returns boolean)
     *
     * @since 1.0.0
     * @return bool True if all tables exist, false otherwise
     */
    public function check_tables(): bool {
        $check_result = $this->check_tables_exist();
        return $check_result['status'] === 'passed';
    }
    
    /**
     * Check table structure
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_table_structure(): array {
        global $wpdb;
        
        $issues = array();
        
        // Define expected columns for each table
        $expected_structure = array(
            'delivery_agents' => array('id', 'user_id', 'status', 'phone', 'vehicle_type', 'availability', 'current_capacity', 'created_at', 'updated_at'),
            'order_assignments' => array('id', 'order_id', 'agent_id', 'status', 'assigned_at', 'picked_up_at', 'delivered_at', 'notes'),
            'location_tracking' => array('id', 'agent_id', 'latitude', 'longitude', 'accuracy', 'timestamp', 'battery_level'),
            'delivery_notes' => array('id', 'order_id', 'note_text', 'note_type', 'created_by', 'created_at', 'is_customer_visible'),
            'delivery_areas' => array('id', 'area_name', 'area_coordinates', 'delivery_fee', 'min_order_amount', 'max_delivery_time', 'is_active', 'created_at'),
        );
        
        foreach ($expected_structure as $table_key => $expected_columns) {
            // Validate table key exists in our known tables
            if (!isset($this->tables[$table_key])) {
                $issues[$table_key] = array('error' => 'Unknown table key: ' . esc_html($table_key));
                continue;
            }
            
            $table_name = $this->tables[$table_key];
            
            // Double-check table name is in our known tables list
            if (!in_array($table_name, array_values($this->tables))) {
                $issues[$table_key] = array('error' => 'Invalid table name: ' . esc_html($table_name));
                continue;
            }
            
            $safe_table_name = str_replace('`', '``', $table_name);
            $actual_columns = $wpdb->get_col("SHOW COLUMNS FROM `" . $safe_table_name . "`");
            
            $missing_columns = array_diff($expected_columns, $actual_columns);
            if (!empty($missing_columns)) {
                $issues[$table_key] = $missing_columns;
            }
        }
        
        if (empty($issues)) {
            return array(
                'status' => 'passed',
                'message' => __('Table structure is correct', 'restaurant-delivery-manager'),
            );
        } else {
            return array(
                'status' => 'failed',
                'message' => __('Table structure issues found', 'restaurant-delivery-manager'),
                'issues' => $issues,
            );
        }
    }
    
    /**
     * Check indexes
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_indexes(): array {
        global $wpdb;
        
        $missing_indexes = array();
        
        // Define expected indexes
        $expected_indexes = array(
            'delivery_agents' => array('user_id', 'status', 'availability'),
            'order_assignments' => array('order_id', 'agent_id', 'status'),
            'location_tracking' => array('agent_id', 'timestamp'),
            'delivery_notes' => array('order_id', 'note_type'),
            'delivery_areas' => array('area_name', 'is_active'),
        );
        
        foreach ($expected_indexes as $table_key => $indexes) {
            $table_name = $this->tables[$table_key];
            $actual_indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
            $index_names = array_unique(wp_list_pluck($actual_indexes, 'Key_name'));
            
            foreach ($indexes as $index) {
                if (!in_array($index, $index_names, true)) {
                    $missing_indexes[] = "$table_key.$index";
                }
            }
        }
        
        if (empty($missing_indexes)) {
            return array(
                'status' => 'passed',
                'message' => __('All indexes are present', 'restaurant-delivery-manager'),
            );
        } else {
            return array(
                'status' => 'warning',
                'message' => sprintf(
                    __('Missing indexes: %s', 'restaurant-delivery-manager'),
                    implode(', ', $missing_indexes)
                ),
                'missing_indexes' => $missing_indexes,
            );
        }
    }
    
    /**
     * Check data integrity
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_data_integrity(): array {
        global $wpdb;
        
        $issues = array();
        
        // Check for orphaned records
        
        // 1. Agents without users
        $orphaned_agents = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['delivery_agents']} da
             LEFT JOIN {$wpdb->users} u ON da.user_id = u.ID
             WHERE u.ID IS NULL"
        );
        
        if ($orphaned_agents > 0) {
            $issues[] = sprintf(
                __('%d agents without corresponding users', 'restaurant-delivery-manager'),
                $orphaned_agents
            );
        }
        
        // 2. Assignments without agents
        $orphaned_assignments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['order_assignments']} oa
             LEFT JOIN {$this->tables['delivery_agents']} da ON oa.agent_id = da.id
             WHERE da.id IS NULL"
        );
        
        if ($orphaned_assignments > 0) {
            $issues[] = sprintf(
                __('%d assignments without corresponding agents', 'restaurant-delivery-manager'),
                $orphaned_assignments
            );
        }
        
        // 3. Locations without agents
        $orphaned_locations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['location_tracking']} lt
             LEFT JOIN {$this->tables['delivery_agents']} da ON lt.agent_id = da.id
             WHERE da.id IS NULL"
        );
        
        if ($orphaned_locations > 0) {
            $issues[] = sprintf(
                __('%d locations without corresponding agents', 'restaurant-delivery-manager'),
                $orphaned_locations
            );
        }
        
        if (empty($issues)) {
            return array(
                'status' => 'passed',
                'message' => __('Data integrity check passed', 'restaurant-delivery-manager'),
            );
        } else {
            return array(
                'status' => 'warning',
                'message' => __('Data integrity issues found', 'restaurant-delivery-manager'),
                'issues' => $issues,
            );
        }
    }
    
    /**
     * Check database performance
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_performance(): array {
        global $wpdb;
        
        $slow_queries = array();
        
        // Test query 1: Get nearby agents
        $start_time = microtime(true);
        $this->get_nearby_agents(40.7128, -74.0060, 10);
        $query_time = microtime(true) - $start_time;
        
        if ($query_time > 0.5) {
            $slow_queries[] = array(
                'query' => 'get_nearby_agents',
                'time' => round($query_time, 3),
            );
        }
        
        // Test query 2: Get agent performance
        $agents = $wpdb->get_col("SELECT id FROM {$this->tables['delivery_agents']} LIMIT 1");
        if (!empty($agents)) {
            $start_time = microtime(true);
            $this->get_agent_performance($agents[0], 30);
            $query_time = microtime(true) - $start_time;
            
            if ($query_time > 0.5) {
                $slow_queries[] = array(
                    'query' => 'get_agent_performance',
                    'time' => round($query_time, 3),
                );
            }
        }
        
        if (empty($slow_queries)) {
            return array(
                'status' => 'passed',
                'message' => __('Database performance is good', 'restaurant-delivery-manager'),
            );
        } else {
            return array(
                'status' => 'warning',
                'message' => __('Some queries are running slowly', 'restaurant-delivery-manager'),
                'slow_queries' => $slow_queries,
            );
        }
    }
    
    /**
     * Check storage usage
     *
     * @since 1.0.0
     * @return array Check results
     */
    private function check_storage(): array {
        global $wpdb;
        
        $table_sizes = array();
        $total_size = 0;
        
        foreach ($this->tables as $table_key => $table_name) {
            $size_query = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                        table_rows as row_count
                     FROM information_schema.TABLES 
                     WHERE table_schema = %s 
                     AND table_name = %s",
                    DB_NAME,
                    $table_name
                )
            );
            
            if ($size_query) {
                $table_sizes[$table_key] = array(
                    'size_mb' => $size_query->size_mb,
                    'rows' => $size_query->row_count,
                );
                $total_size += $size_query->size_mb;
            }
        }
        
        return array(
            'status' => 'info',
            'message' => sprintf(
                __('Total database size: %.2f MB', 'restaurant-delivery-manager'),
                $total_size
            ),
            'table_sizes' => $table_sizes,
            'total_size_mb' => $total_size,
        );
    }
    
    /**
     * Repair database issues
     *
     * @since 1.0.0
     * @param array $issues Issues to repair
     * @return array Repair results
     */
    public function repair_database(array $issues = array()): array {
        $results = array();
        
        // If no specific issues provided, run health check first
        if (empty($issues)) {
            $health_check = $this->health_check();
            
            // Identify issues from health check
            if ($health_check['checks']['tables']['status'] === 'failed') {
                $issues['missing_tables'] = $health_check['checks']['tables']['missing_tables'];
            }
            
            if ($health_check['checks']['integrity']['status'] === 'warning') {
                $issues['integrity'] = $health_check['checks']['integrity']['issues'];
            }
        }
        
        // Repair missing tables
        if (!empty($issues['missing_tables'])) {
            $this->create_tables();
            $results['tables_created'] = true;
        }
        
        // Clean up orphaned records
        if (!empty($issues['integrity'])) {
            $results['orphaned_records_cleaned'] = $this->cleanup_orphaned_records();
        }
        
        // Rebuild indexes
        if (!empty($issues['missing_indexes'])) {
            $results['indexes_rebuilt'] = $this->rebuild_indexes();
        }
        
        // Log repair action
        $this->log_database_event('database_repair', array(
            'issues' => $issues,
            'results' => $results,
        ));
        
        return $results;
    }
    
    /**
     * Clean up orphaned records
     *
     * @since 1.0.0
     * @return array Cleanup results
     */
    private function cleanup_orphaned_records(): array {
        global $wpdb;
        
        $results = array();
        
        // Start transaction
        $this->start_transaction();
        
        try {
            // Clean orphaned locations
            $locations_deleted = $wpdb->query(
                "DELETE lt FROM {$this->tables['location_tracking']} lt
                 LEFT JOIN {$this->tables['delivery_agents']} da ON lt.agent_id = da.id
                 WHERE da.id IS NULL"
            );
            $results['orphaned_locations'] = $locations_deleted;
            
            // Clean orphaned assignments
            $assignments_deleted = $wpdb->query(
                "DELETE oa FROM {$this->tables['order_assignments']} oa
                 LEFT JOIN {$this->tables['delivery_agents']} da ON oa.agent_id = da.id
                 WHERE da.id IS NULL"
            );
            $results['orphaned_assignments'] = $assignments_deleted;
            
            // Clean orphaned agents
            $agents_deleted = $wpdb->query(
                "DELETE da FROM {$this->tables['delivery_agents']} da
                 LEFT JOIN {$wpdb->users} u ON da.user_id = u.ID
                 WHERE u.ID IS NULL"
            );
            $results['orphaned_agents'] = $agents_deleted;
            
            // Commit transaction
            $this->commit_transaction();
            
        } catch (Exception $e) {
            // Rollback on error
            $this->rollback_transaction();
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Rebuild database indexes
     *
     * @since 1.0.0
     * @return bool True on success
     */
    private function rebuild_indexes(): bool {
        global $wpdb;
        
        // Define all indexes
        $indexes = array(
            'delivery_agents' => array(
                'idx_user_id' => 'user_id',
                'idx_status' => 'status',
                'idx_availability' => 'availability',
            ),
            'order_assignments' => array(
                'idx_order_id' => 'order_id',
                'idx_agent_id' => 'agent_id',
                'idx_status' => 'status',
            ),
            'location_tracking' => array(
                'idx_agent_id' => 'agent_id',
                'idx_timestamp' => 'timestamp',
                'idx_agent_timestamp' => 'agent_id, timestamp',
            ),
            'delivery_notes' => array(
                'idx_order_id' => 'order_id',
                'idx_note_type' => 'note_type',
                'idx_created_at' => 'created_at',
            ),
            'delivery_areas' => array(
                'idx_area_name' => 'area_name',
                'idx_is_active' => 'is_active',
            ),
        );
        
        foreach ($indexes as $table_key => $table_indexes) {
            // Validate table key exists in our known tables
            if (!isset($this->tables[$table_key])) {
                $this->log_db_error("Unknown table key in rebuild_indexes: " . esc_html($table_key));
                continue;
            }
            
            $table_name = $this->tables[$table_key];
            
            // Double-check table name is in our known tables list
            if (!in_array($table_name, array_values($this->tables))) {
                $this->log_db_error("Invalid table name in rebuild_indexes: " . esc_html($table_name));
                continue;
            }
            
            $safe_table_name = str_replace('`', '``', $table_name);
            
            foreach ($table_indexes as $index_name => $columns) {
                // Validate index name is alphanumeric with underscores only
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $index_name)) {
                    $this->log_db_error("Invalid index name: " . esc_html($index_name));
                    continue;
                }
                
                // Validate columns contain only allowed characters (alphanumeric, underscores, spaces, commas)
                if (!preg_match('/^[a-zA-Z0-9_, ]+$/', $columns)) {
                    $this->log_db_error("Invalid column specification: " . esc_html($columns));
                    continue;
                }
                
                // Escape index name and columns for safe SQL construction
                $safe_index_name = str_replace('`', '``', $index_name);
                $safe_columns = str_replace('`', '``', $columns);
                
                // Drop existing index if exists
                $drop_sql = "DROP INDEX IF EXISTS `" . $safe_index_name . "` ON `" . $safe_table_name . "`";
                $wpdb->query($drop_sql);
                $this->log_db_query($drop_sql, "Dropped index: $index_name");
                
                // Create index
                $create_sql = "CREATE INDEX `" . $safe_index_name . "` ON `" . $safe_table_name . "` (" . $safe_columns . ")";
                $result = $wpdb->query($create_sql);
                
                if ($result === false) {
                    $this->log_db_error("Failed to create index $index_name: " . $wpdb->last_error);
                } else {
                    $this->log_db_query($create_sql, "Created index: $index_name");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get database statistics
     *
     * @since 1.0.0
     * @return array Database statistics
     */
    public function get_statistics(): array {
        global $wpdb;
        
        $stats = array(
            'version' => $this->get_db_version(),
            'tables' => array(),
            'totals' => array(
                'agents' => 0,
                'active_agents' => 0,
                'total_deliveries' => 0,
                'active_deliveries' => 0,
                'delivery_areas' => 0,
            ),
        );
        
        // Get table statistics
        foreach ($this->tables as $table_key => $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $stats['tables'][$table_key] = intval($count);
        }
        
        // Get specific statistics
        $stats['totals']['agents'] = $stats['tables']['delivery_agents'];
        
        $stats['totals']['active_agents'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['delivery_agents']} 
             WHERE status = 'active' AND availability = 1"
        ));
        
        $stats['totals']['total_deliveries'] = $stats['tables']['order_assignments'];
        
        $stats['totals']['active_deliveries'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['order_assignments']} 
             WHERE status IN ('assigned', 'picked_up')"
        ));
        
        $stats['totals']['delivery_areas'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['delivery_areas']} 
             WHERE is_active = 1"
        ));
        
        // Get performance metrics
        $stats['performance'] = array(
            'avg_delivery_time' => $wpdb->get_var(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at)) 
                 FROM {$this->tables['order_assignments']} 
                 WHERE delivered_at IS NOT NULL"
            ),
            'deliveries_today' => intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['order_assignments']} 
                 WHERE DATE(assigned_at) = CURDATE()"
            )),
        );
        
        return $stats;
    }
    
    /**
     * Get status of all plugin tables
     *
     * @since 1.0.0
     * @return array Array of table statuses with table name as key and status info as value
     */
    public function get_tables_status(): array {
        global $wpdb;
        
        $status = array();
        
        foreach ($this->tables as $table_key => $table_name) {
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )) === $table_name;
            
            $status[$table_key] = array(
                'name' => str_replace($wpdb->prefix, '', $table_name),
                'full_name' => $table_name,
                'status' => $table_exists ? 'OK' : 'Missing',
                'exists' => $table_exists
            );
        }
        
        return $status;
    }

    /**
     * Check if all required tables exist
     *
     * @since 1.0.0
     * @return bool True if all tables exist, false otherwise
     */
    public function are_all_tables_created(): bool {
        $table_status = $this->get_tables_status();
        
        foreach ($table_status as $table) {
            if (!$table['exists']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get valid column names for a specific table
     *
     * @since 1.0.0
     * @param string $prefixed_table_name The full table name with prefix
     * @return array Array of valid column names for the table
     */
    private function get_valid_columns_for_table(string $prefixed_table_name): array {
        // Define valid columns for each table based on their schema
        $table_columns = array(
            $this->tables['delivery_agents'] => array(
                'id', 'user_id', 'status', 'phone', 'vehicle_type', 
                'availability', 'current_capacity', 'rating', 'created_at', 'updated_at'
            ),
            $this->tables['order_assignments'] => array(
                'id', 'order_id', 'agent_id', 'status', 'assigned_at', 
                'picked_up_at', 'delivered_at', 'notes'
            ),
            $this->tables['location_tracking'] => array(
                'id', 'agent_id', 'latitude', 'longitude', 'accuracy', 
                'timestamp', 'battery_level'
            ),
            $this->tables['delivery_notes'] => array(
                'id', 'order_id', 'note_text', 'note_type', 'created_by', 
                'created_at', 'is_customer_visible'
            ),
            $this->tables['delivery_areas'] => array(
                'id', 'area_name', 'area_coordinates', 'delivery_fee', 
                'min_order_amount', 'max_delivery_time', 'is_active', 'created_at'
            ),
        );

        return $table_columns[$prefixed_table_name] ?? array();
    }

    /**
     * Log database query for debugging
     *
     * @since 1.0.0
     * @param string $sql The SQL query executed
     * @param string $context Additional context about the query
     * @return void
     */
    private function log_db_query(string $sql, string $context = ''): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = 'RDM Database Query: ' . $sql;
            if ($context) {
                $log_message .= ' | Context: ' . $context;
            }
            error_log($log_message);
        }
    }

    /**
     * Log database error
     *
     * @since 1.0.0
     * @param string $error_message The error message to log
     * @return void
     */
    private function log_db_error(string $error_message): void {
        error_log('RDM Database Error: ' . $error_message);
    }
}