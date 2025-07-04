<?php
/**
 * Restaurant Delivery Manager - Enhanced Notifications System
 *
 * @package RestaurantDeliveryManager
 * @subpackage Notifications
 * @since 1.0.0
 * @version 2.0.0 - Enhanced with real-time communication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Notifications System Class
 * Handles real-time notifications, browser push notifications, email templates, and sound alerts
 *
 * @class RDM_Notifications
 * @version 2.0.0
 */
class RDM_Notifications {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Notifications|null
     */
    private static ?self $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database|null
     */
    private ?RDM_Database $database = null;
    
    /**
     * Real-time notification queue
     *
     * @var array
     */
    private array $realtime_queue = array();
    
    /**
     * Notification types configuration
     *
     * @var array
     */
    private array $notification_types = array(
        'order_processing' => array(
            'title' => 'New Order Received',
            'sound' => 'new_order.mp3',
            'urgent' => false,
            'email_template' => 'order_processing'
        ),
        'order_preparing' => array(
            'title' => 'Order in Kitchen',
            'sound' => 'order_update.mp3',
            'urgent' => false,
            'email_template' => 'order_preparing'
        ),
        'order_ready' => array(
            'title' => 'Order Ready for Pickup',
            'sound' => 'urgent_alert.mp3',
            'urgent' => true,
            'email_template' => 'order_ready'
        ),
        'order_assigned' => array(
            'title' => 'Order Assigned',
            'sound' => 'assignment.mp3',
            'urgent' => false,
            'email_template' => 'order_assigned'
        ),
        'new_assignment' => array(
            'title' => 'New Delivery Assignment',
            'sound' => 'new_assignment.mp3',
            'urgent' => true,
            'email_template' => 'new_assignment'
        ),
        'order_picked_up' => array(
            'title' => 'Order Picked Up',
            'sound' => 'pickup_confirmation.mp3',
            'urgent' => false,
            'email_template' => 'order_picked_up'
        ),
        'order_delivered' => array(
            'title' => 'Order Delivered',
            'sound' => 'delivery_success.mp3',
            'urgent' => false,
            'email_template' => 'order_delivered'
        ),
        'payment_collected' => array(
            'title' => 'Payment Collected',
            'sound' => 'payment_success.mp3',
            'urgent' => false,
            'email_template' => 'payment_collected'
        ),
        'system_alert' => array(
            'title' => 'System Alert',
            'sound' => 'system_alert.mp3',
            'urgent' => true,
            'email_template' => 'system_alert'
        )
    );

    /**
     * Main Notifications Instance (ESTABLISHED SINGLETON PATTERN)
     *
     * @return static Main instance
     */
    public static function instance(): static {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Alias for backward compatibility
     *
     * @return static Main instance
     */
    public static function get_instance(): static {
        return self::instance();
    }
    
    /**
     * Constructor - Private for singleton
     *
     * @since 2.0.0
     */
    private function __construct() {
        // Get database instance
        $this->database = RDM_Database::instance();
        
        // Initialize hooks
        $this->init_hooks();
        
        // Database tables are now managed by the centralized database class
        // No need to create tables here as they're handled during plugin activation
    }
    
    /**
     * Initialize WordPress hooks (ENHANCED)
     *
     * @since 2.0.0
     * @return void
     */
    private function init_hooks(): void {
        // Order status change notifications
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Custom RDM notifications
        add_action('rdm_order_assigned', array($this, 'notify_order_assigned'), 10, 2);
        add_action('rdm_order_picked_up', array($this, 'notify_order_picked_up'), 10, 2);
        add_action('rdm_order_delivered', array($this, 'notify_order_delivered'), 10, 2);
        add_action('rdm_payment_collected', array($this, 'notify_payment_collected'), 10, 3);
        
        // Real-time AJAX handlers
        add_action('wp_ajax_rdm_get_realtime_notifications', array($this, 'ajax_get_realtime_notifications'));
        add_action('wp_ajax_rdm_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_rdm_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_rdm_update_notification_preferences', array($this, 'ajax_update_notification_preferences'));
        add_action('wp_ajax_rdm_test_notification', array($this, 'ajax_test_notification'));
        add_action('wp_ajax_rdm_get_notification_count', array($this, 'ajax_get_notification_count'));
        
        // Non-logged-in users (for customer notifications)
        add_action('wp_ajax_nopriv_rdm_get_customer_notifications', array($this, 'ajax_get_customer_notifications'));
        
        // Asset enqueuing
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Admin settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Cron jobs for cleanup
        add_action('rdm_cleanup_old_notifications', array($this, 'cleanup_old_notifications'));
        
        // Schedule cron if not exists
        if (!wp_next_scheduled('rdm_cleanup_old_notifications')) {
            wp_schedule_event(time(), 'daily', 'rdm_cleanup_old_notifications');
        }
    }
    
    /**
     * Database tables are now managed by the centralized database class
     * This method is kept for backward compatibility but no longer needed
     *
     * @since 2.0.0
     * @return void
     */
    private function create_database_tables(): void {
        // Tables are now managed by the centralized database class
        // No need to create tables here as they're handled during plugin activation
    }

    /**
     * Handle WooCommerce order status changes (ENHANCED)
     *
     * @since 2.0.0
     * @param int $order_id Order ID
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     * @return void
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order): void {
        try {
            $order_id = absint($order_id);
            if (!$order_id || !$order) {
                throw new Exception(__('Invalid order data', 'restaurant-delivery-manager'));
            }

            switch ($new_status) {
                case 'processing':
                    $this->send_enhanced_notification(
                        'order_processing',
                        __('New Order Received', 'restaurant-delivery-manager'),
                        sprintf(__('Order #%d is ready for preparation', 'restaurant-delivery-manager'), $order->get_order_number()),
                        array(
                            'order_id' => $order_id,
                            'order_total' => $order->get_total(),
                            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            'urgency' => 'normal'
                        ),
                        array('restaurant_manager', 'kitchen_staff')
                    );
                    break;
                    
                case 'wc-preparing':
                    $this->send_enhanced_notification(
                        'order_preparing',
                        __('Order in Kitchen', 'restaurant-delivery-manager'),
                        sprintf(__('Order #%d is being prepared', 'restaurant-delivery-manager'), $order->get_order_number()),
                        array(
                            'order_id' => $order_id,
                            'preparation_time' => $this->estimate_preparation_time($order),
                            'urgency' => 'normal'
                        ),
                        array('delivery_agent')
                    );
                    $this->send_customer_notification($order, 'order_preparing');
                    break;
                    
                case 'wc-ready-for-pickup':
                    $this->send_enhanced_notification(
                        'order_ready',
                        __('Order Ready for Pickup', 'restaurant-delivery-manager'),
                        sprintf(__('Order #%d is ready for delivery', 'restaurant-delivery-manager'), $order->get_order_number()),
                        array(
                            'order_id' => $order_id,
                            'pickup_address' => $this->get_restaurant_address(),
                            'urgency' => 'high'
                        ),
                        array('delivery_agent'),
                        true // Is urgent
                    );
                    break;
                    
                case 'wc-out-for-delivery':
                    $this->notify_customer_order_dispatched($order);
                    $this->send_enhanced_notification(
                        'order_dispatched',
                        __('Order Out for Delivery', 'restaurant-delivery-manager'),
                        sprintf(__('Order #%d is now out for delivery', 'restaurant-delivery-manager'), $order->get_order_number()),
                        array(
                            'order_id' => $order_id,
                            'agent_id' => $order->get_meta('_rdm_assigned_agent'),
                            'estimated_delivery' => $this->calculate_delivery_time($order),
                            'urgency' => 'normal'
                        ),
                        array('restaurant_manager')
                    );
                    break;
                    
                case 'completed':
                    $this->notify_customer_order_delivered($order);
                    $this->send_enhanced_notification(
                        'order_delivered',
                        __('Order Delivered Successfully', 'restaurant-delivery-manager'),
                        sprintf(__('Order #%d has been delivered', 'restaurant-delivery-manager'), $order->get_order_number()),
                        array(
                            'order_id' => $order_id,
                            'delivery_time' => current_time('mysql'),
                            'agent_id' => $order->get_meta('_rdm_assigned_agent'),
                            'urgency' => 'normal'
                        ),
                        array('restaurant_manager')
                    );
                    break;
            }
            
        } catch (Exception $e) {
            error_log('RestroReach: Notification error in ' . __METHOD__ . ' - ' . $e->getMessage());
        }
    }

    /**
     * Notify when order is assigned to agent
     *
     * @param int $order_id Order ID
     * @param int $agent_id Agent ID
     */
    public function notify_order_assigned($order_id, $agent_id) {
        $agent = get_userdata($agent_id);
        
        $this->send_enhanced_notification(
            'order_assigned',
            __('Order Assigned', 'restaurant-delivery-manager'),
            sprintf(__('Order #%d assigned to %s', 'restaurant-delivery-manager'), $order_id, $agent->display_name),
            array('order_id' => $order_id, 'agent_id' => $agent_id),
            array('delivery_agent')
        );

        // Send to specific agent
        $this->send_enhanced_notification(
            'new_assignment',
            __('New Delivery Assignment', 'restaurant-delivery-manager'),
            sprintf(__('You have been assigned order #%d', 'restaurant-delivery-manager'), $order_id),
            array('order_id' => $order_id, 'urgent' => true),
            array('delivery_agent')
        );
    }

    /**
     * Notify when order is picked up by agent
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param int $agent_id Agent ID (optional)
     * @return void
     */
    public function notify_order_picked_up($order_id, $agent_id = null) {
        // Validate order_id
        $order_id = absint($order_id);
        if (!$order_id) {
            error_log('RDM Notifications: Invalid order_id provided to notify_order_picked_up');
            return;
        }

        // Check if WooCommerce is available
        if (!function_exists('wc_get_order')) {
            error_log('RDM Notifications: WooCommerce not available for order pickup notification');
            return;
        }

        // If no agent_id provided, try to get it from order meta
        if (!$agent_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log("RDM Notifications: Order #{$order_id} not found for pickup notification");
                return;
            }
            $agent_id = $order->get_meta('_rdm_assigned_agent');
        }

        // Validate agent_id if provided
        if ($agent_id) {
            $agent_id = absint($agent_id);
        }

        if ($agent_id) {
            $agent = get_userdata($agent_id);
            $agent_name = $agent ? $agent->display_name : __('Unknown Agent', 'restaurant-delivery-manager');
        } else {
            $agent_name = __('Delivery Agent', 'restaurant-delivery-manager');
        }

        $this->send_enhanced_notification(
            'order_picked_up',
            __('Order Picked Up', 'restaurant-delivery-manager'),
            sprintf(__('Order #%d has been picked up by %s and is now out for delivery', 'restaurant-delivery-manager'), $order_id, $agent_name),
            array('order_id' => $order_id, 'agent_id' => $agent_id),
            array('delivery_agent')
        );

        // Send notification to agent if available
        if ($agent_id) {
            $this->send_enhanced_notification(
                'order_picked_up',
                __('Order Picked Up', 'restaurant-delivery-manager'),
                sprintf(__('You have picked up order #%d. Please proceed to delivery location.', 'restaurant-delivery-manager'), $order_id),
                array('order_id' => $order_id, 'urgent' => true),
                array('delivery_agent')
            );
        }
    }

    /**
     * Notify when order is delivered
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param int $agent_id Agent ID (optional)
     * @return void
     */
    public function notify_order_delivered($order_id, $agent_id = null) {
        // Validate order_id
        $order_id = absint($order_id);
        if (!$order_id) {
            error_log('RDM Notifications: Invalid order_id provided to notify_order_delivered');
            return;
        }

        // Check if WooCommerce is available
        if (!function_exists('wc_get_order')) {
            error_log('RDM Notifications: WooCommerce not available for delivery notification');
            return;
        }

        // Get order object (we'll need it multiple times)
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("RDM Notifications: Order #{$order_id} not found for delivery notification");
            return;
        }

        // If no agent_id provided, try to get it from order meta
        if (!$agent_id) {
            $agent_id = $order->get_meta('_rdm_assigned_agent');
        }

        // Validate agent_id if provided
        if ($agent_id) {
            $agent_id = absint($agent_id);
        }

        if ($agent_id) {
            $agent = get_userdata($agent_id);
            $agent_name = $agent ? $agent->display_name : __('Unknown Agent', 'restaurant-delivery-manager');
        } else {
            $agent_name = __('Delivery Agent', 'restaurant-delivery-manager');
        }

        $this->send_enhanced_notification(
            'order_delivered',
            __('Order Delivered', 'restaurant-delivery-manager'),
            sprintf(__('Order #%d has been successfully delivered by %s', 'restaurant-delivery-manager'), $order_id, $agent_name),
            array('order_id' => $order_id, 'agent_id' => $agent_id),
            array('delivery_agent')
        );

        // Send notification to agent if available
        if ($agent_id) {
            $this->send_enhanced_notification(
                'delivery_completed',
                __('Delivery Completed', 'restaurant-delivery-manager'),
                sprintf(__('You have successfully delivered order #%d. Great job!', 'restaurant-delivery-manager'), $order_id),
                array('order_id' => $order_id),
                array('delivery_agent')
            );
        }

        // Send customer notification (using the order object we already have)
        $this->notify_customer_order_delivered($order);
    }

    /**
     * Send enhanced notification with multiple delivery methods
     *
     * @since 2.0.0
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     * @param array $target_roles Array of user roles to target
     * @param bool $is_urgent Whether notification is urgent
     * @param int|null $user_id Specific user ID (overrides target_roles)
     * @return bool Success status
     */
    public function send_enhanced_notification(string $type, string $title, string $message, array $data = array(), array $target_roles = array(), bool $is_urgent = false, ?int $user_id = null): bool {
        try {
            // Input validation
            if (empty($type) || empty($title) || empty($message)) {
                throw new Exception(__('Missing required notification parameters', 'restaurant-delivery-manager'));
            }

            global $wpdb;
            
            // Get notification configuration
            $notification_config = $this->notification_types[$type] ?? array();
            $is_urgent = $is_urgent || ($notification_config['urgent'] ?? false);
            
            // Determine target users
            $target_users = array();
            if ($user_id) {
                // Validate specific user
                if (!get_userdata($user_id)) {
                    throw new Exception(__('Invalid user ID', 'restaurant-delivery-manager'));
                }
                $target_users = array($user_id);
            } elseif (!empty($target_roles)) {
                $target_users = $this->get_users_by_roles($target_roles);
            }
            
            $success_count = 0;
            
            if (empty($target_users)) {
                // General notification for all users
                $notification_id = $this->create_notification_record(null, $type, $title, $message, $data, $is_urgent);
                if ($notification_id) {
                    $this->queue_notification_delivery($notification_id, null, 'browser');
                    $success_count++;
                }
            } else {
                // Send to specific users
                foreach ($target_users as $user_id) {
                    // Check user notification preferences
                    if (!$this->should_send_notification($user_id, $type)) {
                        continue;
                    }
                    
                    $notification_id = $this->create_notification_record($user_id, $type, $title, $message, $data, $is_urgent);
                    if ($notification_id) {
                        $this->queue_multiple_delivery_methods($notification_id, $user_id, $type);
                        $success_count++;
                    }
                }
            }
            
            // Add to real-time queue for immediate delivery
            if ($success_count > 0) {
                $this->add_to_realtime_queue($type, $title, $message, $data, $is_urgent, $target_users);
            }
            
            return $success_count > 0;
            
        } catch (Exception $e) {
            error_log('RestroReach: Enhanced notification failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification (legacy method - now uses enhanced notification)
     *
     * @since 1.0.0
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     * @return bool Success status
     */
    public function send_notification($type, $title, $message, $data = array()) {
        // Use enhanced notification method for backward compatibility
        return $this->send_enhanced_notification($type, $title, $message, $data);
    }
    
    /**
     * Create notification record in database
     *
     * @since 2.0.0
     * @param int|null $user_id Target user ID
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     * @param bool $is_urgent Whether notification is urgent
     * @return int|false Notification ID or false on failure
     */
    private function create_notification_record(?int $user_id, string $type, string $title, string $message, array $data, bool $is_urgent) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->database->get_table_name('notifications'),
            array(
                'user_id' => $user_id,
                'type' => sanitize_text_field($type),
                'title' => sanitize_text_field($title),
                'message' => sanitize_textarea_field($message),
                'data' => wp_json_encode($data),
                'is_urgent' => $is_urgent ? 1 : 0,
                'created_at' => current_time('mysql'),
                'is_read' => 0
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
        );

        if (false === $result) {
            error_log('RestroReach: Failed to create notification record - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }
    
    /**
     * Get users by their roles
     *
     * @since 2.0.0
     * @param array $roles Array of role names
     * @return array Array of user IDs
     */
    private function get_users_by_roles(array $roles): array {
        $user_ids = array();
        
        foreach ($roles as $role) {
            $users = get_users(array('role' => $role, 'fields' => 'ID'));
            $user_ids = array_merge($user_ids, $users);
        }
        
        return array_unique($user_ids);
    }
    
    /**
     * Check if notification should be sent to user based on preferences
     *
     * @since 2.0.0
     * @param int $user_id User ID
     * @param string $notification_type Notification type
     * @return bool Whether to send notification
     */
    private function should_send_notification(int $user_id, string $notification_type): bool {
        global $wpdb;
        
        $preference = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->database->get_table_name('notification_preferences')} 
             WHERE user_id = %d AND notification_type = %s",
            $user_id,
            $notification_type
        ));
        
        // Default to enabled if no preference set
        return $preference ? (bool)$preference->enabled : true;
    }
    
    /**
     * Queue notification for multiple delivery methods
     *
     * @since 2.0.0
     * @param int $notification_id Notification ID
     * @param int $user_id User ID
     * @param string $type Notification type
     * @return void
     */
    private function queue_multiple_delivery_methods(int $notification_id, int $user_id, string $type): void {
        $preferences = $this->get_user_notification_preferences($user_id, $type);
        
        if ($preferences['browser_enabled']) {
            $this->queue_notification_delivery($notification_id, $user_id, 'browser');
        }
        
        if ($preferences['email_enabled']) {
            $this->queue_notification_delivery($notification_id, $user_id, 'email');
        }
        
        if ($preferences['whatsapp_enabled']) {
            $this->queue_notification_delivery($notification_id, $user_id, 'whatsapp');
        }
    }
    
    /**
     * Queue notification for delivery
     *
     * @since 2.0.0
     * @param int $notification_id Notification ID
     * @param int|null $user_id User ID
     * @param string $delivery_method Delivery method
     * @return bool Success status
     */
    private function queue_notification_delivery(int $notification_id, ?int $user_id, string $delivery_method): bool {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->database->get_table_name('notification_queue'),
            array(
                'user_id' => $user_id,
                'notification_id' => $notification_id,
                'delivery_method' => $delivery_method,
                'status' => 'pending',
                'scheduled_for' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Add notification to real-time queue
     *
     * @since 2.0.0
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     * @param bool $is_urgent Whether urgent
     * @param array $target_users Target user IDs
     * @return void
     */
    private function add_to_realtime_queue(string $type, string $title, string $message, array $data, bool $is_urgent, array $target_users): void {
        $notification_config = $this->notification_types[$type] ?? array();
        
        $realtime_notification = array(
            'id' => uniqid('rn_'),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_urgent' => $is_urgent,
            'target_users' => $target_users,
            'sound' => $notification_config['sound'] ?? 'default.mp3',
            'timestamp' => time(),
            'expires_at' => time() + 300 // 5 minutes
        );
        
        $this->realtime_queue[] = $realtime_notification;
        
        // Keep queue size manageable
        if (count($this->realtime_queue) > 50) {
            array_shift($this->realtime_queue);
        }
        
        // Update WordPress option for real-time retrieval
        update_option('rdm_realtime_notifications', $this->realtime_queue, false);
    }



    /**
     * Trigger browser notification
     *
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     */
    private function trigger_browser_notification($type, $title, $message, $data) {
        // This will be handled by JavaScript on the frontend
        // We store the notification for real-time retrieval
        update_option('rdm_latest_notification', array(
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ));
    }

    /**
     * Notify customer when order is dispatched
     *
     * @param WC_Order $order Order object
     */
    private function notify_customer_order_dispatched($order) {
        // Send email notification
        $customer_email = $order->get_billing_email();
        
        if ($customer_email) {
            $subject = sprintf(__('Your Order #%d is Out for Delivery', 'restaurant-delivery-manager'), $order->get_order_number());
            $message = sprintf(
                __('Good news! Your order #%d is now out for delivery and should arrive soon. You can track your order status at: %s', 'restaurant-delivery-manager'),
                $order->get_order_number(),
                home_url('/track-order/?order_id=' . $order->get_id() . '&order_key=' . $order->get_order_key())
            );
            
            wp_mail($customer_email, $subject, $message);
        }
    }

    /**
     * Notify customer when order is delivered
     *
     * @param WC_Order $order Order object
     */
    private function notify_customer_order_delivered($order) {
        // Send email notification
        $customer_email = $order->get_billing_email();
        
        if ($customer_email) {
            $subject = sprintf(__('Your Order #%d has been Delivered', 'restaurant-delivery-manager'), $order->get_order_number());
            $message = sprintf(
                __('Your order #%d has been successfully delivered. Thank you for choosing us! Please rate your experience.', 'restaurant-delivery-manager'),
                $order->get_order_number()
            );
            
            wp_mail($customer_email, $subject, $message);
        }
    }

    /**
     * Get user notification preferences
     *
     * @since 2.0.0
     * @param int $user_id User ID
     * @param string $notification_type Notification type
     * @return array Notification preferences
     */
    private function get_user_notification_preferences(int $user_id, string $notification_type): array {
        global $wpdb;
        
        $preferences = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->database->get_table_name('notification_preferences')} 
             WHERE user_id = %d AND notification_type = %s",
            $user_id,
            $notification_type
        ));
        
        if (!$preferences) {
            // Return default preferences
            return array(
                'enabled' => true,
                'email_enabled' => true,
                'browser_enabled' => true,
                'sound_enabled' => true,
                'whatsapp_enabled' => false
            );
        }
        
        return array(
            'enabled' => (bool)$preferences->enabled,
            'email_enabled' => (bool)$preferences->email_enabled,
            'browser_enabled' => (bool)$preferences->browser_enabled,
            'sound_enabled' => (bool)$preferences->sound_enabled,
            'whatsapp_enabled' => (bool)$preferences->whatsapp_enabled
        );
    }
    
    /**
     * Helper methods for order processing
     *
     * @since 2.0.0
     */
    private function estimate_preparation_time($order): string {
        // Basic estimation - can be enhanced based on order items
        $item_count = $order->get_item_count();
        $base_time = 15; // minutes
        $additional_time = max(0, ($item_count - 1) * 5);
        
        return ($base_time + $additional_time) . ' minutes';
    }
    
    private function get_restaurant_address(): string {
        return get_option('rdm_restaurant_address', get_option('woocommerce_store_address', ''));
    }
    
    private function calculate_delivery_time($order): string {
        // Basic estimation - can be enhanced with GPS/Maps integration
        return '25-35 minutes';
    }
    
    /**
     * Send customer notification
     *
     * @since 2.0.0
     * @param WC_Order $order Order object
     * @param string $type Notification type
     * @return void
     */
    private function send_customer_notification($order, string $type): void {
        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();
        
        if ($customer_email) {
            $this->send_email_notification($order, $type);
        }
        
        // Future: WhatsApp integration
        if ($customer_phone && $this->is_whatsapp_enabled()) {
            $this->send_whatsapp_notification($order, $type, $customer_phone);
        }
    }
    
    /**
     * Notify payment collected
     *
     * @since 2.0.0
     * @param int $order_id Order ID
     * @param int $agent_id Agent ID
     * @param float $amount Amount collected
     * @return void
     */
    public function notify_payment_collected(int $order_id, int $agent_id, float $amount): void {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found', 'restaurant-delivery-manager'));
            }
            
            $agent = get_userdata($agent_id);
            $agent_name = $agent ? $agent->display_name : __('Unknown Agent', 'restaurant-delivery-manager');
            
            $this->send_enhanced_notification(
                'payment_collected',
                __('Payment Collected', 'restaurant-delivery-manager'),
                sprintf(
                    __('Payment of %s collected for order #%d by %s', 'restaurant-delivery-manager'),
                    wc_price($amount),
                    $order->get_order_number(),
                    $agent_name
                ),
                array(
                    'order_id' => $order_id,
                    'agent_id' => $agent_id,
                    'amount' => $amount,
                    'payment_method' => $order->get_payment_method(),
                    'urgency' => 'normal'
                ),
                array('restaurant_manager', 'accountant')
            );
            
        } catch (Exception $e) {
            error_log('RestroReach: Payment notification error - ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler to get real-time notifications
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_get_realtime_notifications(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_notifications_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('read')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $user_id = get_current_user_id();
            $last_check = absint($_POST['last_check'] ?? 0);
            
            // Get notifications from queue
            $notifications = get_option('rdm_realtime_notifications', array());
            $filtered_notifications = array();
            
            foreach ($notifications as $notification) {
                // Filter by timestamp and user targeting
                if ($notification['timestamp'] > $last_check) {
                    // Check if notification targets this user
                    if (empty($notification['target_users']) || in_array($user_id, $notification['target_users'])) {
                        // Check user preferences
                        if ($this->should_send_notification($user_id, $notification['type'])) {
                            $filtered_notifications[] = $notification;
                        }
                    }
                }
            }
            
            wp_send_json_success(array(
                'notifications' => $filtered_notifications,
                'timestamp' => time(),
                'count' => count($filtered_notifications)
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: Real-time notifications error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler to mark notification as read (ENHANCED)
     * 
     * @since 2.0.0
     * @return void
     */
    public function ajax_mark_notification_read(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_notifications_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('read')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }

            $notification_id = absint($_POST['notification_id'] ?? 0);
            
            if (!$notification_id) {
                throw new Exception(__('Invalid notification ID', 'restaurant-delivery-manager'));
            }
            
            global $wpdb;
            $result = $wpdb->update(
                $this->database->get_table_name('notifications'),
                array(
                    'is_read' => 1,
                    'read_at' => current_time('mysql')
                ),
                array('id' => $notification_id),
                array('%d', '%s'),
                array('%d')
            );

            if ($result !== false) {
                wp_send_json_success(__('Notification marked as read', 'restaurant-delivery-manager'));
            } else {
                throw new Exception(__('Failed to update notification', 'restaurant-delivery-manager'));
            }
            
        } catch (Exception $e) {
            error_log('RestroReach: Mark notification read error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler to get notifications
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_notifications() {
        // Security checks
        check_ajax_referer('rdm_notifications_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
            return;
        }

        $user_id = get_current_user_id();
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
        
        // Ensure limit is reasonable
        $limit = max(1, min($limit, 100));
        
        global $wpdb;
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->database->get_table_name('notifications')} 
            WHERE (user_id = %d OR user_id IS NULL)
            ORDER BY created_at DESC 
            LIMIT %d
        ", $user_id, $limit));

        if (false === $notifications) {
            error_log('RDM Notifications: Failed to retrieve notifications: ' . $wpdb->last_error);
            wp_send_json_error(__('Failed to retrieve notifications', 'restaurant-delivery-manager'));
            return;
        }

        // Security: Escape notification content for safe JSON output
        foreach ($notifications as $key => $notification) {
            if (is_object($notification)) {
                $notifications[$key]->title = isset($notification->title) ? esc_html($notification->title) : '';
                $notifications[$key]->message = isset($notification->message) ? esc_html($notification->message) : '';
                // Keep data field as-is since it's JSON-encoded metadata, but ensure type is safe
                if (isset($notification->type)) {
                    $notifications[$key]->type = sanitize_text_field($notification->type);
                }
            }
        }

        wp_send_json_success($notifications);
    }

    /**
     * Additional AJAX handlers for enhanced notifications
     *
     * @since 2.0.0
     */
    
    /**
     * AJAX handler to update notification preferences
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_update_notification_preferences(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_notifications_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('edit_user', get_current_user_id())) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $user_id = get_current_user_id();
            $notification_type = sanitize_text_field($_POST['notification_type'] ?? '');
            $preferences = $_POST['preferences'] ?? array();
            
            if (empty($notification_type)) {
                throw new Exception(__('Invalid notification type', 'restaurant-delivery-manager'));
            }
            
            global $wpdb;
            
            // Sanitize preferences
            $clean_preferences = array(
                'enabled' => !empty($preferences['enabled']) ? 1 : 0,
                'email_enabled' => !empty($preferences['email_enabled']) ? 1 : 0,
                'browser_enabled' => !empty($preferences['browser_enabled']) ? 1 : 0,
                'sound_enabled' => !empty($preferences['sound_enabled']) ? 1 : 0,
                'whatsapp_enabled' => !empty($preferences['whatsapp_enabled']) ? 1 : 0
            );
            
            // Insert or update preferences
            $result = $wpdb->replace(
                $this->database->get_table_name('notification_preferences'),
                array_merge($clean_preferences, array(
                    'user_id' => $user_id,
                    'notification_type' => $notification_type
                )),
                array('%d', '%d', '%d', '%d', '%d', '%d', '%s')
            );
            
            if ($result === false) {
                throw new Exception(__('Failed to update preferences', 'restaurant-delivery-manager'));
            }
            
            wp_send_json_success(__('Preferences updated successfully', 'restaurant-delivery-manager'));
            
        } catch (Exception $e) {
            error_log('RestroReach: Update preferences error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler to test notification
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_test_notification(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_notifications_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_manage_orders')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $notification_type = sanitize_text_field($_POST['notification_type'] ?? 'system_alert');
            $user_id = get_current_user_id();
            
            $this->send_enhanced_notification(
                $notification_type,
                __('Test Notification', 'restaurant-delivery-manager'),
                __('This is a test notification to verify your notification settings are working correctly.', 'restaurant-delivery-manager'),
                array(
                    'test' => true,
                    'timestamp' => current_time('mysql')
                ),
                array(),
                true, // Urgent for testing
                $user_id
            );
            
            wp_send_json_success(__('Test notification sent successfully', 'restaurant-delivery-manager'));
            
        } catch (Exception $e) {
            error_log('RestroReach: Test notification error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler to get notification count
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_get_notification_count(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_notifications_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('read')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $user_id = get_current_user_id();
            
            global $wpdb;
            $unread_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->database->get_table_name('notifications')} 
                 WHERE (user_id = %d OR user_id IS NULL) AND is_read = 0",
                $user_id
            ));
            
            $urgent_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->database->get_table_name('notifications')} 
                 WHERE (user_id = %d OR user_id IS NULL) AND is_read = 0 AND is_urgent = 1",
                $user_id
            ));
            
            wp_send_json_success(array(
                'unread_count' => absint($unread_count),
                'urgent_count' => absint($urgent_count)
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: Get notification count error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for customer notifications (public)
     *
     * @since 2.0.0
     * @return void
     */
    public function ajax_get_customer_notifications(): void {
        try {
            $order_id = absint($_POST['order_id'] ?? 0);
            $order_key = sanitize_text_field($_POST['order_key'] ?? '');
            
            if (!$order_id || !$order_key) {
                throw new Exception(__('Invalid order information', 'restaurant-delivery-manager'));
            }
            
            $order = wc_get_order($order_id);
            if (!$order || $order->get_order_key() !== $order_key) {
                throw new Exception(__('Order not found', 'restaurant-delivery-manager'));
            }
            
            // Get order-specific notifications
            $notifications = $this->get_customer_order_notifications($order_id);
            
            wp_send_json_success(array(
                'notifications' => $notifications,
                'order_status' => $order->get_status(),
                'tracking_data' => $this->get_order_tracking_data($order)
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: Customer notifications error - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Email notification methods
     *
     * @since 2.0.0
     */
    
    /**
     * Send email notification
     *
     * @since 2.0.0
     * @param WC_Order $order Order object
     * @param string $type Notification type
     * @return bool Success status
     */
    private function send_email_notification($order, string $type): bool {
        try {
            $customer_email = $order->get_billing_email();
            if (!$customer_email) {
                return false;
            }
            
            $template_data = $this->get_email_template_data($order, $type);
            $email_content = $this->render_email_template($type, $template_data);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            );
            
            return wp_mail(
                $customer_email,
                $template_data['subject'],
                $email_content,
                $headers
            );
            
        } catch (Exception $e) {
            error_log('RestroReach: Email notification error - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template data
     *
     * @since 2.0.0
     * @param WC_Order $order Order object
     * @param string $type Notification type
     * @return array Template data
     */
    private function get_email_template_data($order, string $type): array {
        $restaurant_name = get_bloginfo('name');
        $order_number = $order->get_order_number();
        $customer_name = $order->get_billing_first_name();
        $tracking_url = home_url('/track-order/?order_id=' . $order->get_id() . '&order_key=' . $order->get_order_key());
        
        $templates = array(
            'order_preparing' => array(
                'subject' => sprintf(__('Your Order #%d is Being Prepared - %s', 'restaurant-delivery-manager'), $order_number, $restaurant_name),
                'title' => __('Your Order is Being Prepared!', 'restaurant-delivery-manager'),
                'message' => sprintf(__('Hi %s, good news! Your order #%d is now being prepared in our kitchen.', 'restaurant-delivery-manager'), $customer_name, $order_number),
                'estimated_time' => $this->estimate_preparation_time($order)
            ),
            'order_dispatched' => array(
                'subject' => sprintf(__('Your Order #%d is Out for Delivery - %s', 'restaurant-delivery-manager'), $order_number, $restaurant_name),
                'title' => __('Your Order is On the Way!', 'restaurant-delivery-manager'),
                'message' => sprintf(__('Hi %s, your order #%d is now out for delivery and should arrive soon.', 'restaurant-delivery-manager'), $customer_name, $order_number),
                'estimated_time' => $this->calculate_delivery_time($order)
            ),
            'order_delivered' => array(
                'subject' => sprintf(__('Your Order #%d has been Delivered - %s', 'restaurant-delivery-manager'), $order_number, $restaurant_name),
                'title' => __('Your Order has been Delivered!', 'restaurant-delivery-manager'),
                'message' => sprintf(__('Hi %s, your order #%d has been successfully delivered. Thank you for choosing us!', 'restaurant-delivery-manager'), $customer_name, $order_number),
                'estimated_time' => ''
            )
        );
        
        $template_data = $templates[$type] ?? $templates['order_preparing'];
        $template_data['tracking_url'] = $tracking_url;
        $template_data['restaurant_name'] = $restaurant_name;
        $template_data['order'] = $order;
        
        return $template_data;
    }
    
    /**
     * Render email template
     *
     * @since 2.0.0
     * @param string $type Template type
     * @param array $data Template data
     * @return string Rendered HTML
     */
    private function render_email_template(string $type, array $data): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($data['subject']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; }
                .order-info { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($data['restaurant_name']); ?></h1>
                </div>
                <div class="content">
                    <h2><?php echo esc_html($data['title']); ?></h2>
                    <p><?php echo esc_html($data['message']); ?></p>
                    
                    <?php if (!empty($data['estimated_time'])): ?>
                    <div class="order-info">
                        <strong><?php _e('Estimated Time:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($data['estimated_time']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-info">
                        <strong><?php _e('Order Total:', 'restaurant-delivery-manager'); ?></strong> <?php echo wc_price($data['order']->get_total()); ?>
                    </div>
                    
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($data['tracking_url']); ?>" class="button">
                            <?php _e('Track Your Order', 'restaurant-delivery-manager'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html($data['restaurant_name']); ?> - <?php _e('Thank you for your order!', 'restaurant-delivery-manager'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * WhatsApp integration methods
     *
     * @since 2.0.0
     */
    
    private function is_whatsapp_enabled(): bool {
        return !empty(get_option('rdm_whatsapp_api_key'));
    }
    
    private function send_whatsapp_notification($order, string $type, string $phone): bool {
        // Future implementation - WhatsApp Business API integration
        // This would require API key and webhook setup
        return false;
    }
    
    /**
     * Helper methods for customer notifications
     *
     * @since 2.0.0
     */
    
    private function get_customer_order_notifications(int $order_id): array {
        // Return order-specific notification history
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rr_notifications 
             WHERE JSON_EXTRACT(data, '$.order_id') = %d 
             ORDER BY created_at DESC",
            $order_id
        ), ARRAY_A);
    }
    
    private function get_order_tracking_data($order): array {
        return array(
            'status' => $order->get_status(),
            'agent_id' => $order->get_meta('_rdm_assigned_agent'),
            'estimated_delivery' => $order->get_meta('_rdm_estimated_delivery'),
            'last_location_update' => $order->get_meta('_rdm_last_location_update')
        );
    }
    
    /**
     * Admin menu for notification settings
     *
     * @since 2.0.0
     * @return void
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'rdm-dashboard',
            __('Notification Settings', 'restaurant-delivery-manager'),
            __('Notifications', 'restaurant-delivery-manager'),
            'rdm_manage_orders',
            'rdm-notifications',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin settings page
     *
     * @since 2.0.0
     * @return void
     */
    public function render_admin_page(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('Notification Settings', 'restaurant-delivery-manager'); ?></h1>
            
            <div class="rdm-notification-settings">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Real-time Notifications', 'restaurant-delivery-manager'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Configure how you receive notifications for order updates, assignments, and system alerts.', 'restaurant-delivery-manager'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Browser Notifications', 'restaurant-delivery-manager'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="rdm-browser-notifications" checked>
                                        <?php _e('Enable browser notifications', 'restaurant-delivery-manager'); ?>
                                    </label>
                                    <p class="description"><?php _e('Get instant notifications in your browser when new orders arrive.', 'restaurant-delivery-manager'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Sound Alerts', 'restaurant-delivery-manager'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="rdm-sound-alerts" checked>
                                        <?php _e('Enable sound alerts', 'restaurant-delivery-manager'); ?>
                                    </label>
                                    <button type="button" class="button" id="rdm-test-sound"><?php _e('Test Sound', 'restaurant-delivery-manager'); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Email Notifications', 'restaurant-delivery-manager'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="rdm-email-notifications" checked>
                                        <?php _e('Send email notifications to customers', 'restaurant-delivery-manager'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" class="button-primary" id="rdm-save-notification-settings">
                                <?php _e('Save Settings', 'restaurant-delivery-manager'); ?>
                            </button>
                            <button type="button" class="button" id="rdm-test-notification">
                                <?php _e('Send Test Notification', 'restaurant-delivery-manager'); ?>
                            </button>
                        </p>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Notification History', 'restaurant-delivery-manager'); ?></h2>
                    <div class="inside">
                        <div id="rdm-notification-history"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Cleanup old notifications (cron job)
     *
     * @since 2.0.0
     * @return void
     */
    public function cleanup_old_notifications(): void {
        global $wpdb;
        
        // Delete notifications older than 30 days
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->database->get_table_name('notifications')} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        ));
        
        if ($result !== false) {
            error_log("RestroReach: Cleaned up {$result} old notifications");
        }
        
        // Clean up delivery queue
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->database->get_table_name('notification_queue')} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ));
    }
    
    /**
     * Enqueue admin assets (ENHANCED)
     * 
     * @since 2.0.0
     * @return void
     */
    public function enqueue_admin_assets(): void {
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'rdm') === false && strpos($screen->id, 'shop_order') === false)) {
            return;
        }
        
        // Check if required constants are defined
        if (!defined('RDM_PLUGIN_URL') || !defined('RDM_VERSION')) {
            error_log('RestroReach: Required plugin constants not defined');
            return;
        }

        // Enqueue enhanced notification scripts
        wp_enqueue_script(
            'rdm-enhanced-notifications',
            RDM_PLUGIN_URL . 'assets/js/rdm-enhanced-notifications.js',
            array('jquery'),
            RDM_VERSION,
            true
        );

        wp_enqueue_style(
            'rdm-notification-styles',
            RDM_PLUGIN_URL . 'assets/css/rdm-notifications.css',
            array(),
            RDM_VERSION
        );

        // Localize script with enhanced data
        wp_localize_script('rdm-enhanced-notifications', 'rdmNotifications', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_notifications_nonce'),
            'refresh_interval' => 15000, // 15 seconds for real-time
            'user_id' => get_current_user_id(),
            'sounds' => array(
                'new_order' => RDM_PLUGIN_URL . 'assets/sounds/new_order.mp3',
                'urgent_alert' => RDM_PLUGIN_URL . 'assets/sounds/urgent_alert.mp3',
                'assignment' => RDM_PLUGIN_URL . 'assets/sounds/assignment.mp3',
                'payment_success' => RDM_PLUGIN_URL . 'assets/sounds/payment_success.mp3',
                'delivery_success' => RDM_PLUGIN_URL . 'assets/sounds/delivery_success.mp3'
            ),
            'strings' => array(
                'notification_permission_title' => __('Enable Notifications', 'restaurant-delivery-manager'),
                'notification_permission_text' => __('Please allow notifications to receive real-time order updates.', 'restaurant-delivery-manager'),
                'mark_all_read' => __('Mark All as Read', 'restaurant-delivery-manager'),
                'test_notification' => __('Test Notification', 'restaurant-delivery-manager'),
                'settings' => __('Notification Settings', 'restaurant-delivery-manager')
            )
        ));
    }

    /**
     * Enqueue frontend assets (ENHANCED)
     * 
     * @since 2.0.0
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        // Check if required constants are defined
        if (!defined('RDM_PLUGIN_URL') || !defined('RDM_VERSION')) {
            error_log('RestroReach: Required plugin constants not defined');
            return;
        }
        
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles ?? array();
        
        // Enqueue for logged-in users with relevant roles
        if (is_user_logged_in() && (
            in_array('delivery_agent', $user_roles) || 
            in_array('restaurant_manager', $user_roles) ||
            in_array('kitchen_staff', $user_roles)
        )) {
            $this->enqueue_agent_notifications($current_user);
        }
        
        // Enqueue for customer tracking pages
        if (is_page() && (strpos(get_the_content(), '[rdm_order_tracking]') !== false || 
                         get_query_var('rdm_tracking'))) {
            $this->enqueue_customer_notifications();
        }
    }
    
    /**
     * Enqueue agent notification assets
     *
     * @since 2.0.0
     * @param WP_User $user Current user
     * @return void
     */
    private function enqueue_agent_notifications(WP_User $user): void {
        wp_enqueue_script(
            'rdm-agent-notifications',
            RDM_PLUGIN_URL . 'assets/js/rdm-agent-notifications.js',
            array('jquery'),
            RDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'rdm-agent-notification-styles',
            RDM_PLUGIN_URL . 'assets/css/rdm-agent-notifications.css',
            array(),
            RDM_VERSION
        );

        wp_localize_script('rdm-agent-notifications', 'rdmAgentNotifications', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_notifications_nonce'),
            'refresh_interval' => 10000, // 10 seconds for agents (more frequent)
            'user_id' => $user->ID,
            'user_role' => $user->roles[0] ?? 'delivery_agent',
            'pluginUrl' => RDM_PLUGIN_URL,
            'sounds' => array(
                'new_assignment' => RDM_PLUGIN_URL . 'assets/sounds/new_assignment.mp3',
                'order_ready' => RDM_PLUGIN_URL . 'assets/sounds/urgent_alert.mp3',
                'payment_success' => RDM_PLUGIN_URL . 'assets/sounds/payment_success.mp3',
                'general' => RDM_PLUGIN_URL . 'assets/sounds/notification.mp3'
            ),
            'strings' => array(
                'new_assignment' => __('New Delivery Assignment', 'restaurant-delivery-manager'),
                'order_ready' => __('Order Ready for Pickup', 'restaurant-delivery-manager'),
                'accept_assignment' => __('Accept Assignment', 'restaurant-delivery-manager'),
                'view_details' => __('View Details', 'restaurant-delivery-manager'),
                'permission_denied' => __('Please enable notifications to receive real-time updates.', 'restaurant-delivery-manager')
            )
        ));
    }
    
    /**
     * Enqueue customer notification assets
     *
     * @since 2.0.0
     * @return void
     */
    private function enqueue_customer_notifications(): void {
        wp_enqueue_script(
            'rdm-customer-notifications',
            RDM_PLUGIN_URL . 'assets/js/rdm-customer-notifications.js',
            array('jquery'),
            RDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'rdm-customer-notification-styles',
            RDM_PLUGIN_URL . 'assets/css/rdm-customer-notifications.css',
            array(),
            RDM_VERSION
        );

        wp_localize_script('rdm-customer-notifications', 'rdmCustomerNotifications', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'refresh_interval' => 30000, // 30 seconds for customers
            'pluginUrl' => RDM_PLUGIN_URL,
            'strings' => array(
                'order_preparing' => __('Your order is being prepared', 'restaurant-delivery-manager'),
                'order_dispatched' => __('Your order is out for delivery', 'restaurant-delivery-manager'),
                'order_delivered' => __('Your order has been delivered', 'restaurant-delivery-manager'),
                'track_order' => __('Track Order', 'restaurant-delivery-manager')
            )
                 ));
     }
 }
 
 // Initialize the notifications system
 RDM_Notifications::instance();
