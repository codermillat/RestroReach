<?php
/**
 * Restaurant Delivery Manager - User Roles Management
 *
 * @package RestaurantDeliveryManager
 * @subpackage UserRoles
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * User roles management class
 *
 * Handles creation and management of custom user roles and capabilities
 * for restaurant managers and delivery agents.
 *
 * @class RDM_User_Roles
 * @version 1.0.0
 */
class RDM_User_Roles {
    
    /**
     * The single instance of the class
     *
     * @var RDM_User_Roles|null
     */
    private static ?RDM_User_Roles $instance = null;
    
    /**
     * Main RDM_User_Roles Instance
     *
     * @return RDM_User_Roles Main instance
     */
    public static function instance(): RDM_User_Roles {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into user registration
        add_action('user_register', array($this, 'handle_user_registration'));
        
        // Add custom user fields
        add_action('show_user_profile', array($this, 'add_custom_user_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_fields'));
        add_action('personal_options_update', array($this, 'save_custom_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));
        
        // Add user columns in admin
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);
        
        // Filter user queries
        add_action('pre_user_query', array($this, 'filter_users_by_role'));
    }
    
    /**
     * Create all custom roles
     *
     * @return void
     */
    public function create_roles(): void {
        try {
            error_log('RestroReach: Starting user roles creation...');
            
            $this->create_restaurant_manager_role();
            error_log('RestroReach: Restaurant manager role created');
            
            $this->create_delivery_agent_role();
            error_log('RestroReach: Delivery agent role created');
            
            $this->add_admin_capabilities();
            error_log('RestroReach: Admin capabilities added');
            
            // Verify roles were created
            $this->verify_roles_created();
            
            error_log('RestroReach: All user roles created successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: User roles creation failed - ' . $e->getMessage());
            throw new Exception('User roles creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify that all roles were created successfully
     *
     * @throws Exception If roles are missing
     */
    private function verify_roles_created(): void {
        $required_roles = array('restaurant_manager', 'delivery_agent');
        
        foreach ($required_roles as $role_name) {
            $role = get_role($role_name);
            
            if (!$role) {
                throw new Exception("Failed to create user role: {$role_name}");
            }
            
            error_log("RestroReach: Verified role exists: {$role_name}");
        }
    }
    
    /**
     * Create restaurant manager role
     *
     * @return void
     */
    private function create_restaurant_manager_role(): void {
        // Check if role already exists
        $existing_role = get_role('restaurant_manager');
        if ($existing_role) {
            error_log('RestroReach: Restaurant manager role already exists, updating capabilities...');
        } else {
            error_log('RestroReach: Creating new restaurant manager role...');
        }
        
        // Remove existing role to ensure clean creation
        remove_role('restaurant_manager');
        
        // Define capabilities for restaurant manager
        $capabilities = array(
            // WordPress capabilities
            'read' => true,

            // Custom capabilities
            'rdm_manage_orders' => true,
            'rdm_view_all_orders' => true,
            'rdm_assign_agents' => true,
            'rdm_manage_agents' => true,
            'rdm_view_analytics' => true,
            'rdm_manage_delivery_areas' => true,
            'rdm_manage_notes' => true,
            'rdm_export_data' => true,
            'rdm_access_manager_dashboard' => true,
            
            'upload_files' => true,
            
            // WooCommerce capabilities (only if WooCommerce is active)
        );
        
        // Add WooCommerce capabilities if WooCommerce is active
        if (rdm_is_woocommerce_active()) {
            $wc_capabilities = array(
                'read_shop_order' => true,
                'edit_shop_order' => true,
                'edit_shop_orders' => true,
                'edit_others_shop_orders' => true,
                'read_private_shop_orders' => true,
                'edit_private_shop_orders' => true,
                'edit_published_shop_orders' => true,
            );
            $capabilities = array_merge($capabilities, $wc_capabilities);
        }
        
        // Add explicit denials
        $capabilities = array_merge($capabilities, array(
            'manage_options' => false,
            'edit_posts' => false,
            'edit_pages' => false,
            'manage_woocommerce' => false,
        ));
        
        // Create the role
        $result = add_role(
            'restaurant_manager',
            __('Restaurant Manager', 'restaurant-delivery-manager'),
            $capabilities
        );
        
        if (!$result) {
            error_log('RestroReach: Failed to create restaurant manager role');
            throw new Exception('Failed to create restaurant manager role');
        }
        
        error_log('RestroReach: Restaurant manager role created successfully');
    }
    
    /**
     * Create delivery agent role
     *
     * @return void
     */
    private function create_delivery_agent_role(): void {
        // Check if role already exists
        $existing_role = get_role('delivery_agent');
        if ($existing_role) {
            error_log('RestroReach: Delivery agent role already exists, updating capabilities...');
        } else {
            error_log('RestroReach: Creating new delivery agent role...');
        }
        
        // Remove existing role to ensure clean creation
        remove_role('delivery_agent');
        
        // Define capabilities for delivery agent
        $capabilities = array(
            // WordPress capabilities
            'read' => true,

            // Custom capabilities
            'rdm_view_assigned_orders' => true,
            'rdm_update_order_status' => true,
            'rdm_update_location' => true,
            'rdm_handle_cod_payment' => true,
            'rdm_add_delivery_notes' => true,
            'rdm_view_customer_info' => true,
            'rdm_upload_delivery_photo' => true,
            'rdm_access_agent_portal' => true,
            
            'upload_files' => true,
            
            // Explicitly deny admin and order management access
            'manage_options' => false,
            'edit_posts' => false,
            'edit_pages' => false,
            'manage_woocommerce' => false,
            'edit_shop_order' => false,
            'edit_shop_orders' => false,
        );
        
        // Create the role
        $result = add_role(
            'delivery_agent',
            __('Delivery Agent', 'restaurant-delivery-manager'),
            $capabilities
        );
        
        if (!$result) {
            error_log('RestroReach: Failed to create delivery agent role');
            throw new Exception('Failed to create delivery agent role');
        }
        
        error_log('RestroReach: Delivery agent role created successfully');
    }
    
    /**
     * Add custom capabilities to administrator role
     *
     * @return void
     */
    public function add_admin_capabilities(): void {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            error_log('RestroReach: Administrator role not found');
            return;
        }
        
        // Define all unique custom capabilities for the plugin
        $custom_caps = array(
            // Core plugin capabilities
            'manage_options',                // WordPress core capability
            'rdm_manage_plugin_settings',    // Plugin settings management
            'rdm_access_manager_dashboard',  // Access to main dashboard
            
            // Order management capabilities
            'rdm_manage_orders',
            'rdm_view_all_orders',
            'rdm_update_order_status',
            'rdm_manage_notes',
            'rdm_view_customer_info',
            
            // Agent management capabilities
            'rdm_manage_agents',
            'rdm_assign_agents',
            'rdm_access_agent_portal',
            
            // Location and delivery capabilities
            'rdm_manage_delivery_areas',
            'rdm_view_assigned_orders',
            'rdm_update_location',
            'rdm_handle_cod_payment',
            'rdm_add_delivery_notes',
            'rdm_upload_delivery_photo',
            
            // Analytics and reporting
            'rdm_view_analytics',
            'rdm_export_data'
        );
        
        $added_count = 0;
        foreach ($custom_caps as $cap) {
            if (!$admin_role->has_cap($cap)) {
                $admin_role->add_cap($cap);
                $added_count++;
                error_log("RestroReach: Added capability '{$cap}' to administrator role");
            }
        }
        
        error_log("RestroReach: Ensured administrator role has all " . count($custom_caps) . " custom capabilities. Added: {$added_count}.");
        
        // Force refresh capabilities
        wp_cache_delete($admin_role->name . '_capabilities', 'user_meta');
    }
    
    /**
     * Remove all custom roles
     *
     * @return void
     */
    public function remove_roles(): void {
        remove_role('restaurant_manager');
        remove_role('delivery_agent');
        
        // Remove custom capabilities from admin
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $custom_caps = array(
                'rdm_manage_orders',
                'rdm_view_all_orders',
                'rdm_assign_agents',
                'rdm_manage_agents',
                'rdm_view_analytics',
                'rdm_manage_delivery_areas',
                'rdm_manage_notes',
                'rdm_export_data',
                'rdm_view_assigned_orders',
                'rdm_update_order_status',
                'rdm_update_location',
                'rdm_handle_cod_payment',
                'rdm_add_delivery_notes',
                'rdm_view_customer_info',
                'rdm_upload_delivery_photo',
                'rdm_manage_plugin_settings',
                'rdm_access_manager_dashboard',
                'rdm_access_agent_portal',
            );
            
            foreach ($custom_caps as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
    
    /**
     * Handle user registration
     *
     * @param int $user_id User ID
     * @return void
     */
    public function handle_user_registration(int $user_id): void {
        // Check if user is being registered as delivery agent
        if (isset($_POST['rdm_user_role']) && $_POST['rdm_user_role'] === 'delivery_agent') {
            $user = new WP_User($user_id);
            $user->set_role('delivery_agent');
            
            // Create delivery agent record
            if (isset($_POST['rdm_phone'])) {
                $phone = sanitize_text_field($_POST['rdm_phone']);
                $vehicle_type = isset($_POST['rdm_vehicle_type']) ? sanitize_text_field($_POST['rdm_vehicle_type']) : 'bike';
                
                $database = RDM_Database::instance();
                $database->create_agent($user_id, $phone, $vehicle_type);
            }
        }
    }
    
    /**
     * Add custom user fields
     *
     * @param WP_User $user User object
     * @return void
     */
    public function add_custom_user_fields($user): void {
        // Only show for users who can manage agents
        if (!current_user_can('rdm_manage_agents')) {
            return;
        }
        
        // Check if user is a delivery agent
        if (!in_array('delivery_agent', (array) $user->roles, true)) {
            return;
        }
        
        // Get agent data
        $database = RDM_Database::instance();
        $agent = $database->get_agent_by_user_id($user->ID);
        ?>
        
        <h3><?php esc_html_e('Delivery Agent Information', 'restaurant-delivery-manager'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th><label for="rdm_phone"><?php esc_html_e('Phone Number', 'restaurant-delivery-manager'); ?></label></th>
                <td>
                    <input type="text" 
                           name="rdm_phone" 
                           id="rdm_phone" 
                           value="<?php echo $agent ? esc_attr($agent->phone) : ''; ?>" 
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('Contact phone number for the delivery agent.', 'restaurant-delivery-manager'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="rdm_vehicle_type"><?php esc_html_e('Vehicle Type', 'restaurant-delivery-manager'); ?></label></th>
                <td>
                    <select name="rdm_vehicle_type" id="rdm_vehicle_type">
                        <option value="bike" <?php selected($agent && $agent->vehicle_type === 'bike'); ?>><?php esc_html_e('Bike', 'restaurant-delivery-manager'); ?></option>
                        <option value="scooter" <?php selected($agent && $agent->vehicle_type === 'scooter'); ?>><?php esc_html_e('Scooter', 'restaurant-delivery-manager'); ?></option>
                        <option value="car" <?php selected($agent && $agent->vehicle_type === 'car'); ?>><?php esc_html_e('Car', 'restaurant-delivery-manager'); ?></option>
                        <option value="van" <?php selected($agent && $agent->vehicle_type === 'van'); ?>><?php esc_html_e('Van', 'restaurant-delivery-manager'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="rdm_status"><?php esc_html_e('Agent Status', 'restaurant-delivery-manager'); ?></label></th>
                <td>
                    <select name="rdm_status" id="rdm_status">
                        <option value="active" <?php selected($agent && $agent->status === 'active'); ?>><?php esc_html_e('Active', 'restaurant-delivery-manager'); ?></option>
                        <option value="inactive" <?php selected($agent && $agent->status === 'inactive'); ?>><?php esc_html_e('Inactive', 'restaurant-delivery-manager'); ?></option>
                        <option value="suspended" <?php selected($agent && $agent->status === 'suspended'); ?>><?php esc_html_e('Suspended', 'restaurant-delivery-manager'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="rdm_availability"><?php esc_html_e('Available for Delivery', 'restaurant-delivery-manager'); ?></label></th>
                <td>
                    <label for="rdm_availability">
                        <input type="checkbox" 
                               name="rdm_availability" 
                               id="rdm_availability" 
                               value="1" 
                               <?php checked($agent && $agent->availability); ?> />
                        <?php esc_html_e('Agent is available to accept new deliveries', 'restaurant-delivery-manager'); ?>
                    </label>
                </td>
            </tr>
            
            <?php if ($agent): ?>
            <tr>
                <th><?php esc_html_e('Performance Metrics', 'restaurant-delivery-manager'); ?></th>
                <td>
                    <?php
                    $performance = $database->get_agent_performance($agent->id, 30);
                    ?>
                    <p>
                        <?php
                        printf(
                            __('Total Deliveries: %d | Average Delivery Time: %s minutes | On-Time Rate: %s%%', 'restaurant-delivery-manager'),
                            $performance['total_deliveries'],
                            number_format($performance['avg_delivery_time'], 1),
                            number_format($performance['on_time_percentage'], 1)
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php wp_nonce_field('rdm_update_agent_info', 'rdm_agent_nonce'); ?>
        
        <?php
    }
    
    /**
     * Save custom user fields
     *
     * @param int $user_id User ID
     * @return void
     */
    public function save_custom_user_fields(int $user_id): void {
        // Check permissions
        if (!current_user_can('rdm_manage_agents')) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['rdm_agent_nonce']) || !wp_verify_nonce($_POST['rdm_agent_nonce'], 'rdm_update_agent_info')) {
            return;
        }
        
        // Check if user is a delivery agent
        $user = get_userdata($user_id);
        if (!in_array('delivery_agent', (array) $user->roles, true)) {
            return;
        }
        
        // Get database instance
        $database = RDM_Database::instance();
        
        // Get or create agent record
        $agent = $database->get_agent_by_user_id($user_id);
        
        if (!$agent && isset($_POST['rdm_phone'])) {
            // Create new agent record
            $phone = sanitize_text_field($_POST['rdm_phone']);
            $vehicle_type = isset($_POST['rdm_vehicle_type']) ? sanitize_text_field($_POST['rdm_vehicle_type']) : 'bike';
            
            $agent_id = $database->create_agent($user_id, $phone, $vehicle_type);
            if ($agent_id) {
                $agent = $database->get_agent($agent_id);
            }
        }
        
        if ($agent) {
            // Update existing agent record
            $update_data = array();
            
            if (isset($_POST['rdm_phone'])) {
                $update_data['phone'] = sanitize_text_field($_POST['rdm_phone']);
            }
            
            if (isset($_POST['rdm_vehicle_type'])) {
                $update_data['vehicle_type'] = sanitize_text_field($_POST['rdm_vehicle_type']);
            }
            
            if (isset($_POST['rdm_status'])) {
                $update_data['status'] = sanitize_text_field($_POST['rdm_status']);
            }
            
            $update_data['availability'] = isset($_POST['rdm_availability']) ? 1 : 0;
            
            $database->update_agent($agent->id, $update_data);
        }
    }
    
    /**
     * Add custom columns to users list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_user_columns(array $columns): array {
        // Only add for users who can manage agents
        if (!current_user_can('rdm_manage_agents')) {
            return $columns;
        }
        
        $new_columns = array();
        
        // Reorder columns
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add custom columns after role
            if ($key === 'role') {
                $new_columns['rdm_agent_status'] = __('Agent Status', 'restaurant-delivery-manager');
                $new_columns['rdm_availability'] = __('Availability', 'restaurant-delivery-manager');
                $new_columns['rdm_active_orders'] = __('Active Orders', 'restaurant-delivery-manager');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Show custom column content
     *
     * @param string $value Column value
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string Column content
     */
    public function show_user_column_content(string $value, string $column_name, int $user_id): string {
        $user = get_userdata($user_id);
        
        // Only show for delivery agents
        if (!in_array('delivery_agent', (array) $user->roles, true)) {
            return $value;
        }
        
        $database = RDM_Database::instance();
        $agent = $database->get_agent_by_user_id($user_id);
        
        if (!$agent) {
            return '—';
        }
        
        switch ($column_name) {
            case 'rdm_agent_status':
                $status_labels = array(
                    'active' => '<span style="color: green;">●</span> ' . __('Active', 'restaurant-delivery-manager'),
                    'inactive' => '<span style="color: gray;">●</span> ' . __('Inactive', 'restaurant-delivery-manager'),
                    'suspended' => '<span style="color: red;">●</span> ' . __('Suspended', 'restaurant-delivery-manager'),
                );
                return $status_labels[$agent->status] ?? $agent->status;
                
            case 'rdm_availability':
                if ($agent->availability) {
                    return '<span style="color: green;">✓</span> ' . __('Available', 'restaurant-delivery-manager');
                }
                return '<span style="color: red;">✗</span> ' . __('Unavailable', 'restaurant-delivery-manager');
                
            case 'rdm_active_orders':
                $active_orders = $database->get_agent_orders($agent->id, 'assigned');
                $count = count($active_orders);
                
                if ($count > 0) {
                    $url = admin_url('admin.php?page=restaurant-delivery-manager&agent_id=' . $agent->id);
                    return sprintf('<a href="%s">%d</a>', esc_url($url), $count);
                }
                return '0';
                
            default:
                return $value;
        }
    }
    
    /**
     * Filter users by role in admin
     *
     * @param WP_User_Query $query User query
     * @return void
     */
    public function filter_users_by_role($query): void {
        global $pagenow;
        
        if ($pagenow !== 'users.php' || !isset($_GET['rdm_role_filter'])) {
            return;
        }
        
        $role = sanitize_text_field($_GET['rdm_role_filter']);
        
        if (in_array($role, array('restaurant_manager', 'delivery_agent'), true)) {
            $query->query_vars['role'] = $role;
        }
    }
    
    // ========================================
    // Helper Functions for Capability Checks
    // ========================================
    
    /**
     * Check if user can manage orders
     *
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function can_manage_orders(?int $user_id = null): bool {
        if (is_null($user_id)) {
            return current_user_can('rdm_manage_orders');
        }
        
        return user_can($user_id, 'rdm_manage_orders');
    }
    
    /**
     * Check if user can assign agents
     *
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function can_assign_agents(?int $user_id = null): bool {
        if (is_null($user_id)) {
            return current_user_can('rdm_assign_agents');
        }
        
        return user_can($user_id, 'rdm_assign_agents');
    }
    
    /**
     * Check if user can view specific order
     *
     * @param int $order_id Order ID
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function can_view_order(int $order_id, ?int $user_id = null): bool {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        
        // Admins and managers can view all orders
        if (user_can($user_id, 'rdm_view_all_orders')) {
            return true;
        }
        
        // Delivery agents can only view assigned orders
        if (user_can($user_id, 'rdm_view_assigned_orders')) {
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            
            if ($agent) {
                $assignment = $database->get_order_assignment($order_id);
                return $assignment && $assignment->agent_id === $agent->id;
            }
        }
        
        // Customers can view their own orders
        $order = wc_get_order($order_id);
        if ($order && $order->get_customer_id() === $user_id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can update location
     *
     * @param int $agent_id Agent ID
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function can_update_location(int $agent_id, ?int $user_id = null): bool {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        
        // Admins can update any location
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Agents can only update their own location
        if (user_can($user_id, 'rdm_update_location')) {
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            
            return $agent && $agent->id === $agent_id;
        }
        
        return false;
    }
    
    /**
     * Get users by role
     *
     * @param string $role Role slug
     * @return array Array of WP_User objects
     */
    public static function get_users_by_role(string $role): array {
        $args = array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );
        
        return get_users($args);
    }
    
    /**
     * Create restaurant manager user
     *
     * @param int $user_id User ID
     * @param array $permissions Additional permissions
     * @return bool True on success
     */
    public static function create_restaurant_manager(int $user_id, array $permissions = array()): bool {
        $user = new WP_User($user_id);
        
        if (!$user->exists()) {
            return false;
        }
        
        // Set role
        $user->set_role('restaurant_manager');
        
        // Add additional capabilities if provided
        foreach ($permissions as $cap => $grant) {
            if ($grant) {
                $user->add_cap($cap);
            } else {
                $user->remove_cap($cap);
            }
        }
        
        return true;
    }
    
    /**
     * Create delivery agent user
     *
     * @param int $user_id User ID
     * @param array $agent_data Agent data (phone, vehicle_type)
     * @return int|false Agent ID on success, false on failure
     */
    public static function create_delivery_agent(int $user_id, array $agent_data) {
        $user = new WP_User($user_id);
        
        if (!$user->exists()) {
            return false;
        }
        
        // Set role
        $user->set_role('delivery_agent');
        
        // Create agent record
        $database = RDM_Database::instance();
        
        $phone = isset($agent_data['phone']) ? sanitize_text_field($agent_data['phone']) : '';
        $vehicle_type = isset($agent_data['vehicle_type']) ? sanitize_text_field($agent_data['vehicle_type']) : 'bike';
        
        return $database->create_agent($user_id, $phone, $vehicle_type);
    }
    
    /**
     * Check if user is restaurant manager
     *
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function is_restaurant_manager(?int $user_id = null): bool {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('restaurant_manager', (array) $user->roles, true);
    }
    
    /**
     * Check if user is delivery agent
     *
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public static function is_delivery_agent(?int $user_id = null): bool {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('delivery_agent', (array) $user->roles, true);
    }
} 