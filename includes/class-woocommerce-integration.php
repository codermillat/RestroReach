<?php
/**
 * Restaurant Delivery Manager - WooCommerce Integration
 *
 * @package RestaurantDeliveryManager
 * @subpackage WooCommerce
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent execution if WooCommerce is not active
if (!class_exists('WooCommerce')) {
    return;
}

/**
 * WooCommerce integration class
 *
 * Handles all WooCommerce-specific functionality including custom order statuses,
 * shipping methods, order workflows, and HPOS compatibility.
 *
 * @class RDM_WooCommerce_Integration
 * @version 1.0.0
 */
class RDM_WooCommerce_Integration {
    
    /**
     * The single instance of the class
     *
     * @var RDM_WooCommerce_Integration|null
     */
    private static ?RDM_WooCommerce_Integration $instance = null;
    
    /**
     * Main RDM_WooCommerce_Integration Instance
     *
     * @return RDM_WooCommerce_Integration Main instance
     */
    public static function instance(): RDM_WooCommerce_Integration {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Double-check WooCommerce is available before setting up hooks
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Register custom order statuses
        add_action('init', array($this, 'register_order_statuses'));
        
        // Add custom statuses to WooCommerce
        add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
        
        // Add custom status colors and icons
        add_action('admin_head', array($this, 'add_status_styles'));
        
        // Register shipping method
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Initialize shipping method
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        
        // Add order meta boxes
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        
        // Save order meta
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_order_meta'));
        
        // Handle order status transitions
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Add custom order actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_rdm_assign_agent', array($this, 'handle_assign_agent_action'));
        
        // Add bulk actions
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        
        // HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Custom order columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_columns'), 10, 2);
        
        // HPOS custom columns
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_columns'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_order_columns_hpos'), 10, 2);
    }
    
    /**
     * Register custom order statuses
     *
     * @return void
     */
    public function register_order_statuses(): void {
        // Ensure WooCommerce is active before registering statuses
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Preparing status
        register_post_status('wc-preparing', array(
            'label' => _x('Preparing Food', 'Order status', 'restaurant-delivery-manager'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Preparing <span class="count">(%s)</span>',
                'Preparing <span class="count">(%s)</span>',
                'restaurant-delivery-manager'
            ),
        ));
        
        // Ready status
        register_post_status('wc-ready', array(
            'label' => _x('Ready for Pickup', 'Order status', 'restaurant-delivery-manager'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Ready <span class="count">(%s)</span>',
                'Ready <span class="count">(%s)</span>',
                'restaurant-delivery-manager'
            ),
        ));
        
        // Out for delivery status
        register_post_status('wc-out-for-delivery', array(
            'label' => _x('Out for Delivery', 'Order status', 'restaurant-delivery-manager'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Out for Delivery <span class="count">(%s)</span>',
                'Out for Delivery <span class="count">(%s)</span>',
                'restaurant-delivery-manager'
            ),
        ));
        
        // Delivered status
        register_post_status('wc-delivered', array(
            'label' => _x('Delivered', 'Order status', 'restaurant-delivery-manager'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Delivered <span class="count">(%s)</span>',
                'Delivered <span class="count">(%s)</span>',
                'restaurant-delivery-manager'
            ),
        ));
    }
    
    /**
     * Add custom order statuses to WooCommerce
     *
     * @param array $order_statuses Existing order statuses
     * @return array Modified order statuses
     */
    public function add_order_statuses(array $order_statuses): array {
        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return $order_statuses;
        }
        
        $new_order_statuses = array();
        
        // Add custom statuses after processing
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            
            if ('wc-processing' === $key) {
                $new_order_statuses['wc-preparing'] = _x('Preparing Food', 'Order status', 'restaurant-delivery-manager');
                $new_order_statuses['wc-ready'] = _x('Ready for Pickup', 'Order status', 'restaurant-delivery-manager');
                $new_order_statuses['wc-out-for-delivery'] = _x('Out for Delivery', 'Order status', 'restaurant-delivery-manager');
            }
        }
        
        // Add delivered status before completed
        $temp_statuses = array();
        foreach ($new_order_statuses as $key => $status) {
            if ('wc-completed' === $key) {
                $temp_statuses['wc-delivered'] = _x('Delivered', 'Order status', 'restaurant-delivery-manager');
            }
            $temp_statuses[$key] = $status;
        }
        
        return $temp_statuses;
    }
    
    /**
     * Add custom status styles and icons
     *
     * @return void
     */
    public function add_status_styles(): void {
        // Ensure WooCommerce is active and we're on the right page
        if (!class_exists('WooCommerce') || !$this->is_order_admin_page()) {
            return;
        }
        ?>
        <style>
            /* Custom order status colors and icons */
            .order-status.status-preparing {
                color: #ff9800;
                background: #fff3e0;
                border-color: #ff9800;
            }
            .order-status.status-ready {
                color: #2196f3;
                background: #e3f2fd;
                border-color: #2196f3;
            }
            .order-status.status-out-for-delivery {
                color: #9c27b0;
                background: #f3e5f5;
                border-color: #9c27b0;
            }
            .order-status.status-delivered {
                color: #4caf50;
                background: #e8f5e8;
                border-color: #4caf50;
            }
            
            /* Order list icons */
            .widefat .column-order_status mark.preparing::after {
                content: '\f309';
                color: #ff9800;
            }
            .widefat .column-order_status mark.ready::after {
                content: '\f147';
                color: #2196f3;
            }
            .widefat .column-order_status mark.out-for-delivery::after {
                content: '\f343';
                color: #9c27b0;
            }
            .widefat .column-order_status mark.delivered::after {
                content: '\f147';
                color: #4caf50;
            }
        </style>
        <?php
    }
    
    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods Existing shipping methods
     * @return array Modified shipping methods
     */
    public function add_shipping_method(array $methods): array {
        $methods['rdm_distance_shipping'] = 'RDM_Distance_Shipping';
        return $methods;
    }
    
    /**
     * Initialize shipping method class
     *
     * @return void
     */
    public function init_shipping_method(): void {
        require_once RDM_PLUGIN_DIR . 'includes/class-distance-shipping.php';
    }
    
    /**
     * Add order meta boxes
     *
     * @return void
     */
    public function add_order_meta_boxes(): void {
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'rdm_delivery_info',
            __('Delivery Information', 'restaurant-delivery-manager'),
            array($this, 'render_delivery_info_meta_box'),
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Render delivery information meta box
     *
     * @param WP_Post|WC_Order $post_or_order_object Post or order object
     * @return void
     */
    public function render_delivery_info_meta_box($post_or_order_object): void {
        $order = ($post_or_order_object instanceof WP_Post) 
            ? wc_get_order($post_or_order_object->ID) 
            : $post_or_order_object;
            
        if (!$order) {
            return;
        }
        
        // Get delivery information
        $database = RDM_Database::instance();
        $assignment = $database->get_order_assignment($order->get_id());
        $delivery_distance = $order->get_meta('_rdm_delivery_distance');
        $delivery_fee = $order->get_meta('_rdm_delivery_fee');
        $preparation_time = $order->get_meta('_rdm_preparation_time');
        
        wp_nonce_field('rdm_save_delivery_info', 'rdm_delivery_info_nonce');
        ?>
        
        <div class="rdm-delivery-info">
            <?php if ($assignment): ?>
                <?php
                $agent = $database->get_agent($assignment->agent_id);
                $user = get_userdata($agent->user_id);
                ?>
                <div class="rdm-delivery-info-row">
                    <span class="rdm-delivery-info-label"><?php esc_html_e('Delivery Agent:', 'restaurant-delivery-manager'); ?></span>
                    <span><?php echo esc_html($user->display_name); ?></span>
                </div>
                
                <div class="rdm-delivery-info-row">
                    <span class="rdm-delivery-info-label"><?php esc_html_e('Agent Phone:', 'restaurant-delivery-manager'); ?></span>
                    <span><?php echo esc_html($agent->phone); ?></span>
                </div>
                
                <div class="rdm-delivery-info-row">
                    <span class="rdm-delivery-info-label"><?php esc_html_e('Assignment Status:', 'restaurant-delivery-manager'); ?></span>
                    <span><?php echo esc_html(ucfirst($assignment->status)); ?></span>
                </div>
                
                <?php if ($assignment->assigned_at): ?>
                <div class="rdm-delivery-info-row">
                    <span class="rdm-delivery-info-label"><?php esc_html_e('Assigned At:', 'restaurant-delivery-manager'); ?></span>
                    <span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($assignment->assigned_at))); ?></span>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e('No delivery agent assigned yet.', 'restaurant-delivery-manager'); ?></p>
                
                <?php if (current_user_can('rdm_assign_agents')): ?>
                    <button type="button" class="button rdm-assign-agent-button" onclick="rdmOpenAgentAssignment(<?php echo esc_attr($order->get_id()); ?>)">
                        <?php esc_html_e('Assign Agent', 'restaurant-delivery-manager'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($delivery_distance): ?>
            <div class="rdm-delivery-info-row">
                <span class="rdm-delivery-info-label"><?php esc_html_e('Distance:', 'restaurant-delivery-manager'); ?></span>
                <span><?php echo esc_html(number_format($delivery_distance, 1) . ' km'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($delivery_fee): ?>
            <div class="rdm-delivery-info-row">
                <span class="rdm-delivery-info-label"><?php esc_html_e('Delivery Fee:', 'restaurant-delivery-manager'); ?></span>
                <span><?php echo wc_price($delivery_fee); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="rdm-delivery-info-row">
                <label for="rdm_preparation_time" class="rdm-delivery-info-label">
                    <?php esc_html_e('Prep Time (min):', 'restaurant-delivery-manager'); ?>
                </label>
                <input type="number" 
                       id="rdm_preparation_time" 
                       name="rdm_preparation_time" 
                       value="<?php echo esc_attr($preparation_time ?: 15); ?>" 
                       min="1" 
                       max="120" 
                       style="width: 60px;" />
            </div>
        </div>
        
        <script>
        function rdmOpenAgentAssignment(orderId) {
            // This will be implemented in the admin interface class
            if (typeof rdmAdminInterface !== 'undefined') {
                rdmAdminInterface.openAgentAssignmentModal(orderId);
            }
        }
        </script>
        <?php
    }
    
    /**
     * Save order meta data
     *
     * @param int $order_id Order ID
     * @return void
     */
    public function save_order_meta(int $order_id): void {
        // Security check
        if (!isset($_POST['rdm_delivery_info_nonce']) || 
            !wp_verify_nonce($_POST['rdm_delivery_info_nonce'], 'rdm_save_delivery_info')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_order', $order_id)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Save preparation time
        if (isset($_POST['rdm_preparation_time'])) {
            $prep_time = absint($_POST['rdm_preparation_time']);
            $order->update_meta_data('_rdm_preparation_time', $prep_time);
            $order->save();
        }
    }
    
    /**
     * Handle order status changes
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     * @return void
     */
    public function handle_order_status_change(int $order_id, string $old_status, string $new_status, WC_Order $order): void {
        // Remove 'wc-' prefix for comparison
        $old_status = str_replace('wc-', '', $old_status);
        $new_status = str_replace('wc-', '', $new_status);
        
        // Handle status-specific actions
        switch ($new_status) {
            case 'preparing':
                // Set preparation start time
                $order->update_meta_data('_rdm_preparation_started', current_time('mysql'));
                $order->save();
                
                // Send notification to customer
                do_action('rdm_order_status_preparing', $order_id);
                break;
                
            case 'ready':
                // Order is ready for pickup
                $order->update_meta_data('_rdm_ready_at', current_time('mysql'));
                $order->save();
                
                // Notify available agents
                do_action('rdm_order_ready_for_pickup', $order_id);
                break;
                
            case 'out-for-delivery':
                // Update assignment status
                $database = RDM_Database::instance();
                $assignment = $database->get_order_assignment($order_id);
                
                if ($assignment) {
                    $database->update_assignment_status($assignment->id, 'picked_up');
                    
                    // Notify that order has been picked up
                    do_action('rdm_order_picked_up', $order_id, $assignment->agent_id);
                }
                
                // Start delivery tracking
                do_action('rdm_order_out_for_delivery', $order_id);
                break;
                
            case 'delivered':
                // Mark order as delivered
                $database = RDM_Database::instance();
                $assignment = $database->get_order_assignment($order_id);
                
                if ($assignment) {
                    $database->update_assignment_status($assignment->id, 'delivered');
                }
                
                // Calculate delivery metrics
                $this->calculate_delivery_metrics($order);
                
                // Send delivery confirmation
                do_action('rdm_order_delivered', $order_id, $assignment ? $assignment->agent_id : null);
                break;
        }
        
        // Fire general status change action
        do_action('rdm_order_status_changed', $order_id, $old_status, $new_status);
    }
    
    /**
     * Add custom order actions
     *
     * @param array $actions Existing order actions
     * @return array Modified order actions
     */
    public function add_order_actions(array $actions): array {
        global $theorder;
        
        if (!$theorder) {
            return $actions;
        }
        
        // Add assign agent action for ready orders
        if (in_array($theorder->get_status(), array('processing', 'preparing', 'ready'), true)) {
            $actions['rdm_assign_agent'] = __('Assign Delivery Agent', 'restaurant-delivery-manager');
        }
        
        return $actions;
    }
    
    /**
     * Handle assign agent action
     *
     * @param WC_Order $order Order object
     * @return void
     */
    public function handle_assign_agent_action(WC_Order $order): void {
        // This will trigger the agent assignment modal in the admin interface
        $order->add_order_note(__('Agent assignment requested. Please select an available agent.', 'restaurant-delivery-manager'));
        
        // Set a transient to trigger the modal on page reload
        set_transient('rdm_show_agent_assignment_' . $order->get_id(), true, 60);
    }
    
    /**
     * Add bulk actions
     *
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function add_bulk_actions(array $actions): array {
        $actions['rdm_mark_preparing'] = __('Change status to preparing', 'restaurant-delivery-manager');
        $actions['rdm_mark_ready'] = __('Change status to ready', 'restaurant-delivery-manager');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $doaction Action being performed
     * @param array $post_ids Post IDs
     * @return string Redirect URL
     */
    public function handle_bulk_actions(string $redirect_to, string $doaction, array $post_ids): string {
        if ('rdm_mark_preparing' === $doaction) {
            foreach ($post_ids as $post_id) {
                $order = wc_get_order($post_id);
                if ($order) {
                    $order->update_status('preparing', __('Bulk status update to preparing.', 'restaurant-delivery-manager'));
                }
            }
            $redirect_to = add_query_arg('bulk_preparing_updated', count($post_ids), $redirect_to);
        }
        
        if ('rdm_mark_ready' === $doaction) {
            foreach ($post_ids as $post_id) {
                $order = wc_get_order($post_id);
                if ($order) {
                    $order->update_status('ready', __('Bulk status update to ready.', 'restaurant-delivery-manager'));
                }
            }
            $redirect_to = add_query_arg('bulk_ready_updated', count($post_ids), $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * Declare HPOS compatibility
     *
     * @return void
     */
    public function declare_hpos_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                RDM_PLUGIN_BASENAME,
                true
            );
        }
    }
    
    /**
     * Add custom columns to orders list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_order_columns(array $columns): array {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Add delivery agent column after order status
            if ('order_status' === $key) {
                $new_columns['delivery_agent'] = __('Delivery Agent', 'restaurant-delivery-manager');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom order columns
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    public function render_order_columns(string $column, int $post_id): void {
        if ('delivery_agent' === $column) {
            $order = wc_get_order($post_id);
            if (!$order) {
                echo '—';
                return;
            }
            
            $this->render_delivery_agent_column($order);
        }
    }
    
    /**
     * Render custom order columns for HPOS
     *
     * @param string $column Column name
     * @param WC_Order $order Order object
     * @return void
     */
    public function render_order_columns_hpos(string $column, WC_Order $order): void {
        if ('delivery_agent' === $column) {
            $this->render_delivery_agent_column($order);
        }
    }
    
    /**
     * Render delivery agent column content
     *
     * @param WC_Order $order Order object
     * @return void
     */
    private function render_delivery_agent_column(WC_Order $order): void {
        $database = RDM_Database::instance();
        $assignment = $database->get_order_assignment($order->get_id());
        
        if ($assignment) {
            $agent = $database->get_agent($assignment->agent_id);
            if ($agent) {
                $user = get_userdata($agent->user_id);
                echo '<span class="rdm-agent-name">' . esc_html($user->display_name) . '</span>';
                
                // Show agent status
                if ($assignment->status === 'delivered') {
                    echo ' <span class="dashicons dashicons-yes-alt" style="color: #2ecc71;" title="' . esc_attr__('Delivered', 'restaurant-delivery-manager') . '"></span>';
                } elseif ($assignment->status === 'picked_up') {
                    echo ' <span class="dashicons dashicons-location" style="color: #3498db;" title="' . esc_attr__('Out for delivery', 'restaurant-delivery-manager') . '"></span>';
                }
            } else {
                echo '—';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
    
    /**
     * Calculate delivery metrics after order completion
     *
     * @param WC_Order $order Order object
     * @return void
     */
    private function calculate_delivery_metrics(WC_Order $order): void {
        $preparation_started = $order->get_meta('_rdm_preparation_started');
        $ready_at = $order->get_meta('_rdm_ready_at');
        $delivered_at = current_time('mysql');
        
        if ($preparation_started && $ready_at) {
            $prep_time = strtotime($ready_at) - strtotime($preparation_started);
            $order->update_meta_data('_rdm_actual_preparation_time', round($prep_time / 60));
        }
        
        if ($ready_at) {
            $delivery_time = strtotime($delivered_at) - strtotime($ready_at);
            $order->update_meta_data('_rdm_actual_delivery_time', round($delivery_time / 60));
        }
        
        $order->update_meta_data('_rdm_delivered_at', $delivered_at);
        $order->save();
    }
    
    /**
     * Check if current page is order admin page
     *
     * @return bool
     */
    private function is_order_admin_page(): bool {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        
        // Check for traditional orders page or HPOS orders page
        return in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'), true) ||
               strpos($screen->id, 'shop_order') !== false;
    }
    
    /**
     * Get custom order statuses for restaurant workflow
     *
     * @return array Array of custom statuses with details
     */
    public static function get_custom_statuses(): array {
        return array(
            'wc-preparing' => array(
                'label' => _x('Preparing Food', 'Order status', 'restaurant-delivery-manager'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'icon' => 'dashicons-clock',
                'color' => '#f39c12',
            ),
            'wc-ready' => array(
                'label' => _x('Ready for Pickup', 'Order status', 'restaurant-delivery-manager'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'icon' => 'dashicons-yes',
                'color' => '#27ae60',
            ),
            'wc-out-for-delivery' => array(
                'label' => _x('Out for Delivery', 'Order status', 'restaurant-delivery-manager'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'icon' => 'dashicons-location',
                'color' => '#3498db',
            ),
            'wc-delivered' => array(
                'label' => _x('Delivered', 'Order status', 'restaurant-delivery-manager'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'icon' => 'dashicons-yes-alt',
                'color' => '#2ecc71',
            ),
        );
    }
    
    /**
     * Get valid status transitions for restaurant workflow
     *
     * @return array Array of valid transitions
     */
    public static function get_status_transitions(): array {
        return array(
            'pending' => array('processing', 'cancelled', 'failed'),
            'processing' => array('preparing', 'cancelled', 'refunded'),
            'preparing' => array('ready', 'cancelled', 'refunded'),
            'ready' => array('out-for-delivery', 'cancelled', 'refunded'),
            'out-for-delivery' => array('delivered', 'failed'),
            'delivered' => array('completed', 'refunded'),
            'completed' => array('refunded'),
        );
    }
    
    /**
     * Check if status transition is valid
     *
     * @param string $from_status Current status
     * @param string $to_status New status
     * @return bool
     */
    public static function is_valid_transition(string $from_status, string $to_status): bool {
        $transitions = self::get_status_transitions();
        
        // Remove 'wc-' prefix
        $from_status = str_replace('wc-', '', $from_status);
        $to_status = str_replace('wc-', '', $to_status);
        
        return isset($transitions[$from_status]) && in_array($to_status, $transitions[$from_status], true);
    }
} 