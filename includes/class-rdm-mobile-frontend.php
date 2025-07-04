<?php
/**
 * Mobile Frontend for Delivery Agents
 *
 * @package RestaurantDeliveryManager
 * @subpackage MobileFrontend
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class RDM_Mobile_Frontend {
    private static $instance = null;

    /**
     * Main Mobile Frontend Instance
     *
     * @return RDM_Mobile_Frontend
     */
    public static function instance(): RDM_Mobile_Frontend {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Alias for backward compatibility
     *
     * @return RDM_Mobile_Frontend
     */
    public static function get_instance(): RDM_Mobile_Frontend {
        return self::instance();
    }

    private function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'template_loader'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_head', array($this, 'add_pwa_meta_tags'));
        // AJAX handlers
        add_action('wp_ajax_nopriv_rdm_agent_login', array($this, 'ajax_agent_login'));
        add_action('wp_ajax_rdm_get_agent_orders', array($this, 'ajax_get_agent_orders'));
        add_action('wp_ajax_rdm_accept_order', array($this, 'ajax_accept_order'));
        add_action('wp_ajax_rdm_update_order_status', array($this, 'ajax_update_order_status'));
        add_action('wp_ajax_rdm_upload_delivery_photo', array($this, 'ajax_upload_delivery_photo'));
        add_action('wp_ajax_rdm_collect_cod_payment', array($this, 'ajax_collect_cod_payment'));
        add_action('wp_ajax_rdm_calculate_change', array($this, 'ajax_calculate_change'));
        add_action('wp_ajax_rdm_update_agent_location', array($this, 'ajax_update_agent_location'));
        add_action('wp_ajax_rdm_get_order_details', array($this, 'ajax_get_order_details'));
    }

    /**
     * Add rewrite rules for mobile agent pages
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^delivery-agent/login/?$', 'index.php?rdm_agent_page=login', 'top');
        add_rewrite_rule('^delivery-agent/dashboard/?$', 'index.php?rdm_agent_page=dashboard', 'top');
    }

    /**
     * Register custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'rdm_agent_page';
        return $vars;
    }

    /**
     * Load the correct template for mobile agent pages
     */
    public function template_loader() {
        $page = get_query_var('rdm_agent_page');
        if ($page === 'login') {
            include plugin_dir_path(__FILE__) . '../templates/mobile/login-page.php';
            exit;
        } elseif ($page === 'dashboard') {
            // Check authentication (simple cookie/session for now)
            if (!$this->is_agent_logged_in()) {
                wp_redirect(home_url('/delivery-agent/login'));
                exit;
            }
            include plugin_dir_path(__FILE__) . '../templates/mobile/agent-dashboard.php';
            exit;
        }
    }

    /**
     * Enqueue mobile assets only on agent pages
     */
    public function enqueue_assets() {
        $page = get_query_var('rdm_agent_page');
        if ($page === 'login' || $page === 'dashboard') {
                    wp_enqueue_style('rdm-mobile-agent', plugin_dir_url(__FILE__) . '../assets/css/rdm-mobile-agent.css', array(), '1.0.0');
        wp_enqueue_script('rdm-mobile-agent', plugin_dir_url(__FILE__) . '../assets/js/rdm-mobile-agent.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('rdm-service-worker-registration', plugin_dir_url(__FILE__) . '../assets/js/service-worker-registration.js', array('rdm-mobile-agent'), '1.0.0', true);
                         wp_localize_script('rdm-mobile-agent', 'rdmAgent', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rdm_agent_mobile'),
                'dashboardUrl' => home_url('/delivery-agent/dashboard'),
                'loginUrl' => home_url('/delivery-agent/login'),
                'serviceWorkerUrl' => plugin_dir_url(__FILE__) . '../assets/js/rdm-service-worker.js',
                'pluginUrl' => plugin_dir_url(__FILE__) . '../',
                'manifestUrl' => home_url('/manifest.json'),
                'strings' => array(
                    'loading' => __('Loading...', 'restaurant-delivery-manager'),
                    'error' => __('An error occurred', 'restaurant-delivery-manager'),
                    'success' => __('Success', 'restaurant-delivery-manager'),
                    'confirm' => __('Are you sure?', 'restaurant-delivery-manager'),
                    'offline' => __('Working offline', 'restaurant-delivery-manager'),
                    'online' => __('Back online', 'restaurant-delivery-manager'),
                    'networkError' => __('Network error. Please try again.', 'restaurant-delivery-manager'),
                    'locationError' => __('Location access required for delivery tracking', 'restaurant-delivery-manager'),
                    'photoRequired' => __('Photo confirmation required for delivery', 'restaurant-delivery-manager'),
                    'paymentCollected' => __('Payment collected successfully', 'restaurant-delivery-manager'),
                    'orderUpdated' => __('Order status updated', 'restaurant-delivery-manager'),
                    'refreshing' => __('Refreshing orders...', 'restaurant-delivery-manager'),
                    'noOrders' => __('No active orders', 'restaurant-delivery-manager'),
                    'emergencyContact' => __('Emergency contact', 'restaurant-delivery-manager'),
                    'insufficientPayment' => __('Collected amount is less than order total', 'restaurant-delivery-manager'),
                    'invalidAmount' => __('Please enter a valid amount', 'restaurant-delivery-manager'),
                    'photoUploadSuccess' => __('Photo uploaded successfully', 'restaurant-delivery-manager'),
                    'photoUploadError' => __('Failed to upload photo', 'restaurant-delivery-manager'),
                    'orderAccepted' => __('Order accepted successfully', 'restaurant-delivery-manager'),
                    'orderRejected' => __('Order rejected', 'restaurant-delivery-manager'),
                    'gpsStarted' => __('Location sharing started', 'restaurant-delivery-manager'),
                    'gpsStopped' => __('Location sharing stopped', 'restaurant-delivery-manager'),
                    'batteryLow' => __('Low battery detected. Location updates reduced.', 'restaurant-delivery-manager')
                )
            ));
        }
    }

    /**
     * AJAX: Authenticate delivery agent login
     */
    public function ajax_agent_login() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'restaurant-delivery-manager')));
        }
        
        // Rate limiting check
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_limit_key = 'rdm_login_attempts_' . md5($ip);
        $attempts = get_transient($rate_limit_key) ?: 0;
        
        if ($attempts >= 5) {
            wp_send_json_error(array('message' => __('Too many login attempts. Please try again later.', 'restaurant-delivery-manager')));
        }
        
        $username = sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            wp_send_json_error(array('message' => __('Username and password are required.', 'restaurant-delivery-manager')));
        }
        
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            // Increment failed attempts
            set_transient($rate_limit_key, $attempts + 1, 300); // 5 minutes
            wp_send_json_error(array('message' => __('Invalid credentials.', 'restaurant-delivery-manager')));
        }
        
        // Check if user has delivery agent role
        if (!user_can($user, 'delivery_agent')) {
            wp_send_json_error(array('message' => __('You are not authorized as a delivery agent.', 'restaurant-delivery-manager')));
        }
        
        // Clear failed attempts on successful login
        delete_transient($rate_limit_key);
        
        // Generate secure session token
        $session_token = wp_generate_password(32, false);
        $session_data = array(
            'user_id' => $user->ID,
            'created' => time(),
            'ip' => $ip
        );
        
        // Store session data securely
        set_transient('rdm_agent_session_' . $session_token, $session_data, 3600); // 1 hour
        
        // Set secure session cookie
        setcookie(
            'rdm_agent_session',
            $session_token,
            [
                'expires' => time() + 3600,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
        
        wp_send_json_success(array('redirect' => home_url('/delivery-agent/dashboard')));
    }

    /**
     * Check if agent is logged in (secure session check)
     */
    private function is_agent_logged_in() {
        return $this->get_authenticated_user_id() > 0;
    }
    
    /**
     * Get authenticated user ID from secure session
     */
    private function get_authenticated_user_id() {
        if (!empty($_COOKIE['rdm_agent_session'])) {
            $session_token = sanitize_text_field($_COOKIE['rdm_agent_session']);
            $session_data = get_transient('rdm_agent_session_' . $session_token);
            
            if ($session_data && isset($session_data['user_id'])) {
                $user = get_userdata($session_data['user_id']);
                if ($user && user_can($user, 'delivery_agent')) {
                    // Check if session is not expired and IP matches
                    if ($session_data['created'] > (time() - 3600) && 
                        $session_data['ip'] === ($_SERVER['REMOTE_ADDR'] ?? '')) {
                        return $session_data['user_id'];
                    }
                }
            }
            
            // Invalid session, clear cookie
            setcookie('rdm_agent_session', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
        return 0;
    }

    /**
     * AJAX: Get assigned orders for logged-in agent
     */
    public function ajax_get_agent_orders() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'restaurant-delivery-manager')));
        }
        
        $user_id = $this->get_authenticated_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Not authenticated.', 'restaurant-delivery-manager')));
        }
        
        $orders = $this->get_assigned_orders($user_id);
        wp_send_json_success(array('orders' => $orders));
    }

    /**
     * AJAX: Accept order assignment
     */
    public function ajax_accept_order() {
        try {
            // Security check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
                throw new Exception(__('Security check failed.', 'restaurant-delivery-manager'));
            }
            
            $user_id = $this->get_authenticated_user_id();
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            $order_id = absint($_POST['order_id'] ?? 0);
            if (!$order_id) {
                throw new Exception(__('Invalid order ID.', 'restaurant-delivery-manager'));
            }

            // Get agent data
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            if (!$agent) {
                throw new Exception(__('Agent not found.', 'restaurant-delivery-manager'));
            }

            // Update order status
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found.', 'restaurant-delivery-manager'));
            }

            $order->update_status('ready-for-pickup', __('Agent accepted order', 'restaurant-delivery-manager'));
            
            // Update assignment status
            $assignment = $database->get_order_assignment($order_id);
            if ($assignment) {
                $database->update_assignment_status($assignment->id, 'accepted');
            }

            wp_send_json_success(array(
                'message' => __('Order accepted successfully', 'restaurant-delivery-manager'),
                'order_id' => $order_id
            ));

        } catch (Exception $e) {
            error_log('RestroReach: Order acceptance failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Update order status
     */
    public function ajax_update_order_status() {
        try {
            // Security check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
                throw new Exception(__('Security check failed.', 'restaurant-delivery-manager'));
            }
            
            $user_id = $this->get_authenticated_user_id();
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            $order_id = absint($_POST['order_id'] ?? 0);
            $new_status = sanitize_text_field($_POST['status'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$order_id || !$new_status) {
                throw new Exception(__('Invalid order ID or status.', 'restaurant-delivery-manager'));
            }

            // Get agent data
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            if (!$agent) {
                throw new Exception(__('Agent not found.', 'restaurant-delivery-manager'));
            }

            // Verify agent is assigned to this order
            $assignment = $database->get_order_assignment($order_id);
            if (!$assignment || $assignment->agent_id !== $agent->id) {
                throw new Exception(__('Order not assigned to you.', 'restaurant-delivery-manager'));
            }

            // Update order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found.', 'restaurant-delivery-manager'));
            }

            $order->update_status($new_status, $notes);

            // Update assignment status
            $database->update_assignment_status($assignment->id, $new_status, array('notes' => $notes));

            wp_send_json_success(array(
                'message' => __('Order status updated successfully', 'restaurant-delivery-manager'),
                'order_id' => $order_id,
                'status' => $new_status
            ));

        } catch (Exception $e) {
            error_log('RestroReach: Order status update failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Upload delivery photo
     */
    public function ajax_upload_delivery_photo() {
        try {
            check_ajax_referer('rdm_agent_mobile', 'nonce');
            
            $user_id = isset($_COOKIE['rdm_agent_logged_in']) ? intval($_COOKIE['rdm_agent_logged_in']) : 0;
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            $order_id = absint($_POST['order_id'] ?? 0);
            if (!$order_id) {
                throw new Exception(__('Invalid order ID.', 'restaurant-delivery-manager'));
            }

            if (empty($_FILES['delivery_photo']) || $_FILES['delivery_photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(__('No photo uploaded or upload error.', 'restaurant-delivery-manager'));
            }

            // Validate file type
            $allowed_types = array('image/jpeg', 'image/jpg', 'image/png');
            $file_type = $_FILES['delivery_photo']['type'];
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception(__('Invalid file type. Only JPEG and PNG allowed.', 'restaurant-delivery-manager'));
            }

            // Validate file size (max 5MB)
            if ($_FILES['delivery_photo']['size'] > 5 * 1024 * 1024) {
                throw new Exception(__('File too large. Maximum 5MB allowed.', 'restaurant-delivery-manager'));
            }

            // Upload file
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload = wp_handle_upload($_FILES['delivery_photo'], array('test_form' => false));
            
            if (isset($upload['error'])) {
                throw new Exception($upload['error']);
            }

            // Create attachment
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title' => 'Delivery Photo - Order #' . $order_id,
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file']);
            
            if (is_wp_error($attachment_id)) {
                throw new Exception(__('Failed to create attachment.', 'restaurant-delivery-manager'));
            }

            wp_generate_attachment_metadata($attachment_id, $upload['file']);

            // Associate with order
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_meta_data('_rdm_delivery_photo', $attachment_id);
                $order->save();
                
                $order->add_order_note(
                    __('Delivery photo uploaded by agent.', 'restaurant-delivery-manager'),
                    true
                );
            }

            wp_send_json_success(array(
                'message' => __('Photo uploaded successfully', 'restaurant-delivery-manager'),
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id)
            ));

        } catch (Exception $e) {
            error_log('RestroReach: Photo upload failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get assigned orders for agent (basic info)
     */
    private function get_assigned_orders($user_id) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT oa.order_id, p.post_date, pm.meta_value as address
             FROM {$wpdb->prefix}order_assignments oa
             INNER JOIN {$wpdb->posts} p ON oa.order_id = p.ID
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_shipping_address_1'
             WHERE oa.agent_id = %d AND p.post_status IN ('wc-processing','wc-preparing','wc-ready','wc-out-for-delivery')
             ORDER BY p.post_date DESC",
            $user_id
        ));
        $orders = array();
        foreach ($results as $row) {
            // Escape address data for safe JSON output
            $safe_address = !empty($row->address) ? esc_html($row->address) : esc_html__('Address not available', 'restaurant-delivery-manager');
            
            $orders[] = array(
                'id' => intval($row->order_id),
                'customer' => esc_html__('Customer', 'restaurant-delivery-manager'), // For privacy, just show 'Customer'
                'address' => $safe_address,
            );
        }
        return $orders;
    }

    /**
     * AJAX: Collect COD payment
     */
    public function ajax_collect_cod_payment() {
        try {
            check_ajax_referer('rdm_agent_mobile', 'nonce');
            
            $user_id = isset($_COOKIE['rdm_agent_logged_in']) ? intval($_COOKIE['rdm_agent_logged_in']) : 0;
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            // Get agent data
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            if (!$agent) {
                throw new Exception(__('Agent not found.', 'restaurant-delivery-manager'));
            }

            $order_id = absint($_POST['order_id'] ?? 0);
            $collected_amount = floatval($_POST['collected_amount'] ?? 0);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$order_id || $collected_amount <= 0) {
                throw new Exception(__('Invalid order ID or collection amount.', 'restaurant-delivery-manager'));
            }

            // Use payment system
            $payment_handler = RDM_Payments::instance();
            $result = $payment_handler->handle_cod_collection($order_id, $agent->id, $collected_amount, array(
                'notes' => $notes,
                'metadata' => array('collected_by' => $user_id)
            ));

            if ($result['success']) {
                wp_send_json_success($result['data']);
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }

        } catch (Exception $e) {
            error_log('RestroReach: COD collection failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Calculate change amount
     */
    public function ajax_calculate_change() {
        try {
            check_ajax_referer('rdm_agent_mobile', 'nonce');
            
            $order_total = floatval($_POST['order_total'] ?? 0);
            $collected_amount = floatval($_POST['collected_amount'] ?? 0);

            if ($order_total <= 0 || $collected_amount <= 0) {
                throw new Exception(__('Invalid amounts.', 'restaurant-delivery-manager'));
            }

            $payment_handler = RDM_Payments::instance();
            $change = $payment_handler->calculate_change($order_total, $collected_amount);

            wp_send_json_success(array(
                'change_amount' => $change,
                'formatted_change' => wc_price($change),
                'sufficient_payment' => $collected_amount >= $order_total
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Update agent location
     */
    public function ajax_update_agent_location() {
        try {
            check_ajax_referer('rdm_agent_mobile', 'nonce');
            
            $user_id = isset($_COOKIE['rdm_agent_logged_in']) ? intval($_COOKIE['rdm_agent_logged_in']) : 0;
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            // Get agent data
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            if (!$agent) {
                throw new Exception(__('Agent not found.', 'restaurant-delivery-manager'));
            }

            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $accuracy = floatval($_POST['accuracy'] ?? 0);
            $battery_level = absint($_POST['battery_level'] ?? 100);

            // Validate coordinates
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                throw new Exception(__('Invalid coordinates.', 'restaurant-delivery-manager'));
            }

            // Save location
            $saved = $database->save_location($agent->id, $latitude, $longitude, $accuracy, $battery_level);

            if ($saved) {
                wp_send_json_success(array(
                    'message' => __('Location updated successfully', 'restaurant-delivery-manager'),
                    'timestamp' => current_time('mysql')
                ));
            } else {
                throw new Exception(__('Failed to save location.', 'restaurant-delivery-manager'));
            }

        } catch (Exception $e) {
            error_log('RestroReach: Location update failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Get detailed order information
     */
    public function ajax_get_order_details() {
        try {
            check_ajax_referer('rdm_agent_mobile', 'nonce');
            
            $user_id = isset($_COOKIE['rdm_agent_logged_in']) ? intval($_COOKIE['rdm_agent_logged_in']) : 0;
            if (!$user_id) {
                throw new Exception(__('Not authenticated.', 'restaurant-delivery-manager'));
            }

            $order_id = absint($_POST['order_id'] ?? 0);
            if (!$order_id) {
                throw new Exception(__('Invalid order ID.', 'restaurant-delivery-manager'));
            }

            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found.', 'restaurant-delivery-manager'));
            }

            // Verify agent assignment
            $database = RDM_Database::instance();
            $agent = $database->get_agent_by_user_id($user_id);
            $assignment = $database->get_order_assignment($order_id);
            
            if (!$agent || !$assignment || $assignment->agent_id !== $agent->id) {
                throw new Exception(__('Order not assigned to you.', 'restaurant-delivery-manager'));
            }

            // Build order details
            $order_data = array(
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'formatted_total' => wc_price($order->get_total()),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'customer' => array(
                    'name' => $order->get_formatted_billing_full_name(),
                    'phone' => $order->get_billing_phone(),
                    'email' => $order->get_billing_email()
                ),
                'billing_address' => $order->get_formatted_billing_address(),
                'shipping_address' => $order->get_formatted_shipping_address(),
                'items' => array(),
                'notes' => $order->get_customer_note(),
                'assignment' => array(
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at,
                    'notes' => $assignment->notes
                )
            );

            // Add items
            foreach ($order->get_items() as $item) {
                $order_data['items'][] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total()
                );
            }

            wp_send_json_success($order_data);

        } catch (Exception $e) {
            error_log('RestroReach: Get order details failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Add PWA meta tags to mobile agent pages
     */
    public function add_pwa_meta_tags() {
        $page = get_query_var('rdm_agent_page');
        if ($page === 'login' || $page === 'dashboard') {
            ?>
            <!-- PWA Meta Tags -->
            <meta name="application-name" content="RestroReach Agent">
            <meta name="apple-mobile-web-app-title" content="RestroReach">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="default">
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="theme-color" content="#2271b1">
            <meta name="msapplication-TileColor" content="#2271b1">
            <meta name="msapplication-config" content="<?php echo esc_url(home_url('/browserconfig.xml')); ?>">
            
            <!-- Manifest -->
            <link rel="manifest" href="<?php echo esc_url(home_url('/manifest.json')); ?>">
            
            <!-- Apple Touch Icons -->
            <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/icon-180x180.png'); ?>">
            <link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/icon-152x152.png'); ?>">
            <link rel="apple-touch-icon" sizes="144x144" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/icon-144x144.png'); ?>">
            
            <!-- Favicon -->
            <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/icon-32x32.png'); ?>">
            <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/images/icon-16x16.png'); ?>">
            
            <!-- Mobile optimizations -->
            <meta name="format-detection" content="telephone=yes">
            <meta name="HandheldFriendly" content="true">
            <meta name="MobileOptimized" content="320">
            <?php
        }
    }
}

// Initialize the mobile frontend
RDM_Mobile_Frontend::instance(); 