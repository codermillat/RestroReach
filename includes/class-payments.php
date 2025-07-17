<?php
/**
 * Restaurant Delivery Manager - Payment Processing System
 *
 * @package RestaurantDeliveryManager
 * @subpackage Payments
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment processing class for restaurant delivery management
 *
 * Handles Cash on Delivery (COD), change calculation, payment tracking,
 * and cash reconciliation reports for delivery agents and restaurant managers.
 *
 * @class RDM_Payments
 * @version 1.0.0
 */
class RDM_Payments {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Payments|null
     */
    private static ?RDM_Payments $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database|null
     */
    private ?RDM_Database $database = null;
    
    /**
     * Payment types
     *
     * @var array
     */
    private array $payment_types = array(
        'cod' => 'Cash on Delivery',
        'online' => 'Online Payment',
        'card' => 'Card Payment',
        'digital_wallet' => 'Digital Wallet',
    );
    
    /**
     * Payment statuses
     *
     * @var array
     */
    private array $payment_statuses = array(
        'pending' => 'Pending',
        'collected' => 'Collected',
        'verified' => 'Verified',
        'reconciled' => 'Reconciled',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    );
    
    /**
     * Main RDM_Payments Instance
     *
     * @return RDM_Payments Main instance
     */
    public static function instance(): RDM_Payments {
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
    public function __construct() {
        // Get database instance
        $this->database = RDM_Database::instance();
        
        // Initialize hooks
        $this->init_hooks();
        
        // Initialize database tables for payments
        $this->maybe_create_payment_tables();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks(): void {
        // AJAX handlers for COD collection
        add_action('wp_ajax_rdm_collect_cod_payment', array($this, 'ajax_collect_cod_payment'));
        add_action('wp_ajax_rdm_calculate_change', array($this, 'ajax_calculate_change'));
        add_action('wp_ajax_rdm_get_agent_payments', array($this, 'ajax_get_agent_payments'));
        add_action('wp_ajax_rdm_reconcile_cash', array($this, 'ajax_reconcile_cash'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_rdm_generate_cash_report', array($this, 'ajax_generate_cash_report'));
        add_action('wp_ajax_rdm_verify_payment', array($this, 'ajax_verify_payment'));
        add_action('wp_ajax_rdm_verify_reconciliation', array($this, 'ajax_verify_reconciliation'));
        add_action('wp_ajax_rdm_get_reconciliation_report', array($this, 'ajax_get_reconciliation_report'));
        add_action('wp_ajax_rdm_get_reconciliation_details', array($this, 'ajax_get_reconciliation_details'));
        add_action('wp_ajax_rdm_export_reconciliation_csv', array($this, 'ajax_export_reconciliation_csv'));
        
        // Admin POST handlers for non-AJAX requests
        add_action('admin_post_rdm_export_reconciliation_csv', array($this, 'handle_csv_export'));

        // WooCommerce integration hooks
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        add_action('woocommerce_thankyou', array($this, 'create_payment_record'), 10, 1);
        
        // Enqueue payment assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_payment_assets'));
        
        // Add payment metabox to admin orders
        add_action('add_meta_boxes', array($this, 'add_payment_meta_boxes'));
        
        // Daily cash reconciliation cron
        add_action('rdm_daily_cash_reconciliation', array($this, 'daily_cash_reconciliation'));
        
        // Schedule daily reconciliation if not scheduled
        if (!wp_next_scheduled('rdm_daily_cash_reconciliation')) {
            wp_schedule_event(time(), 'daily', 'rdm_daily_cash_reconciliation');
        }
    }
    
    /**
     * Check if payment tables exist and create them if needed
     *
     * @since 1.0.0
     */
    private function maybe_create_payment_tables(): void {
        // Tables are now managed by the centralized database class
        // This method is kept for backward compatibility but no longer needed
        // The database class handles all table creation during plugin activation
    }
    
    /**
     * Enqueue payment assets
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_payment_assets(): void {
        // Only load on pages that need payment functionality
        if (!$this->should_load_payment_assets()) {
            return;
        }
        
        // Check if required constants are defined
        if (!defined('RDM_PLUGIN_URL') || !defined('RDM_VERSION')) {
            error_log('RestroReach: Required plugin constants not defined for payment assets');
            return;
        }
        
        // Enqueue payment styles
        wp_enqueue_style(
            'rdm-payments',
            RDM_PLUGIN_URL . 'assets/css/rdm-payments.css',
            array(),
            RDM_VERSION
        );
        
        // Enqueue payment scripts
        wp_enqueue_script(
            'rdm-payments',
            RDM_PLUGIN_URL . 'assets/js/rdm-payments.js',
            array('jquery'),
            RDM_VERSION,
            true
        );
        
        // Localize script with payment configuration
        wp_localize_script('rdm-payments', 'rdmPayments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_payments_nonce'),
            'currency' => get_woocommerce_currency_symbol(),
            'i18n' => array(
                'confirmCollection' => __('Are you sure you want to collect this payment?', 'restaurant-delivery-manager'),
                'invalidAmount' => __('Please enter a valid amount', 'restaurant-delivery-manager'),
                'collectionSuccess' => __('Payment collected successfully', 'restaurant-delivery-manager'),
                'collectionError' => __('Failed to collect payment', 'restaurant-delivery-manager'),
                'calculating' => __('Calculating...', 'restaurant-delivery-manager'),
            ),
        ));
    }
    
    /**
     * Add payment meta boxes to WooCommerce orders
     *
     * @since 1.0.0
     * @return void
     */
    public function add_payment_meta_boxes(): void {
        global $post;
        
        // Only add to shop orders
        if (!$post || get_post_type($post->ID) !== 'shop_order') {
            return;
        }
        
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        // Only add for COD orders
        if ($order->get_payment_method() === 'cod') {
            add_meta_box(
                'rdm-payment-details',
                __('Delivery Payment Details', 'restaurant-delivery-manager'),
                array($this, 'render_payment_meta_box'),
                'shop_order',
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render payment meta box content
     *
     * @since 1.0.0
     * @param WP_Post $post Order post object
     * @return void
     */
    public function render_payment_meta_box($post): void {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        $payment = $this->get_payment_record($order->get_id());
        
        echo '<div class="rdm-payment-meta-box">';
        
        if ($payment) {
            echo '<table class="form-table">';
            echo '<tr><td><strong>' . esc_html__('Payment Status:', 'restaurant-delivery-manager') . '</strong></td>';
            echo '<td>' . esc_html(ucfirst($payment->status)) . '</td></tr>';
            
            if ($payment->status === 'collected') {
                echo '<tr><td><strong>' . esc_html__('Collected Amount:', 'restaurant-delivery-manager') . '</strong></td>';
                echo '<td>' . wc_price($payment->collected_amount) . '</td></tr>';
                
                echo '<tr><td><strong>' . esc_html__('Change Given:', 'restaurant-delivery-manager') . '</strong></td>';
                echo '<td>' . wc_price($payment->change_amount) . '</td></tr>';
                
                if ($payment->collected_at) {
                    echo '<tr><td><strong>' . esc_html__('Collected At:', 'restaurant-delivery-manager') . '</strong></td>';
                    echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->collected_at))) . '</td></tr>';
                }
                
                if ($payment->agent_id) {
                    $agent = $this->database->get_agent($payment->agent_id);
                    if ($agent) {
                        echo '<tr><td><strong>' . esc_html__('Collected By:', 'restaurant-delivery-manager') . '</strong></td>';
                        echo '<td>' . esc_html($agent->display_name) . '</td></tr>';
                    }
                }
            }
            echo '</table>';
        } else {
            echo '<p>' . esc_html__('No payment record found for this order.', 'restaurant-delivery-manager') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Handle WooCommerce order status changes
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status  
     * @param WC_Order $order Order object
     * @return void
     */
    public function handle_order_status_change(int $order_id, string $old_status, string $new_status, $order): void {
        // Only handle COD orders
        if ($order->get_payment_method() !== 'cod') {
            return;
        }
        
        // Create payment record when order is placed
        if ($new_status === 'processing' || $new_status === 'preparing') {
            $this->create_payment_record($order_id);
        }
        
        // Handle completion status
        if ($new_status === 'completed' && $old_status !== 'completed') {
            $payment = $this->get_payment_record($order_id);
            if ($payment && $payment->status === 'pending') {
                // Auto-collect for manual completions
                $this->auto_collect_payment($order_id);
            }
        }
    }
    
    /**
     * Auto-collect payment for manually completed orders
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return void
     */
    private function auto_collect_payment(int $order_id): void {
        global $wpdb;
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        $wpdb->update(
            $payment_table,
            array(
                'status' => 'collected',
                'collected_amount' => $order->get_total(),
                'change_amount' => 0,
                'collected_at' => current_time('mysql'),
                'notes' => __('Auto-collected on manual completion', 'restaurant-delivery-manager')
            ),
            array('order_id' => $order_id),
            array('%s', '%f', '%f', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Check if payment assets should be loaded
     *
     * @since 1.0.0
     * @return bool
     */
    private function should_load_payment_assets(): bool {
        global $post;
        
        // Load on admin order pages
        if (is_admin()) {
            $screen = get_current_screen();
            if ($screen && ($screen->post_type === 'shop_order' || strpos($screen->id, 'restroreach') !== false)) {
                return true;
            }
        }
        
        // Load on mobile agent pages
        if (isset($_GET['rdm_page']) && $_GET['rdm_page'] === 'agent-dashboard') {
            return true;
        }
        
        // Load on customer tracking pages
        if ($post && has_shortcode($post->post_content, 'rdm_order_tracking')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get payment statistics for a given date range
     *
     * @since 1.0.0
     * @param array $filters Date and filter options
     * @return array Payment statistics
     */
    public function get_payment_statistics(array $filters = array()): array {
        global $wpdb;
        
        // Default filters
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'agent_id' => null,
            'status' => null,
            'payment_method' => 'cod'
        );
        
        $filters = wp_parse_args($filters, $defaults);
        
        // Sanitize inputs
        $date_from = sanitize_text_field($filters['date_from']);
        $date_to = sanitize_text_field($filters['date_to']);
        $agent_id = $filters['agent_id'] ? absint($filters['agent_id']) : null;
        $status = $filters['status'] ? sanitize_text_field($filters['status']) : null;
        $payment_method = sanitize_text_field($filters['payment_method']);
        
        try {
            $payment_table = $this->database->get_table_name('payment_transactions');
            
            // Build base query
            $where_conditions = array();
            $where_values = array();
            
            // Date range filter
            $where_conditions[] = "DATE(pt.created_at) >= %s";
            $where_values[] = $date_from;
            
            $where_conditions[] = "DATE(pt.created_at) <= %s";
            $where_values[] = $date_to;
            
            // Payment method filter
            $where_conditions[] = "pt.payment_type = %s";
            $where_values[] = $payment_method;
            
            // Agent filter
            if ($agent_id) {
                $where_conditions[] = "pt.agent_id = %d";
                $where_values[] = $agent_id;
            }
            
            // Status filter
            if ($status) {
                $where_conditions[] = "pt.status = %s";
                $where_values[] = $status;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get summary statistics
            $summary_query = "
                SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN pt.status = 'collected' THEN 1 END) as collected_count,
                    COUNT(CASE WHEN pt.status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN pt.status = 'failed' THEN 1 END) as failed_count,
                    COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.amount ELSE 0 END), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.collected_amount ELSE 0 END), 0) as total_received,
                    COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.change_amount ELSE 0 END), 0) as total_change,
                    COALESCE(AVG(CASE WHEN pt.status = 'collected' THEN pt.amount ELSE NULL END), 0) as avg_order_value,
                    COALESCE(MAX(pt.amount), 0) as highest_order,
                    COALESCE(MIN(CASE WHEN pt.amount > 0 THEN pt.amount ELSE NULL END), 0) as lowest_order
                FROM {$payment_table} pt
                WHERE {$where_clause}
            ";
            
            $summary = $wpdb->get_row($wpdb->prepare($summary_query, ...$where_values));
            
            // Get daily breakdown
            $daily_query = "
                SELECT 
                    DATE(pt.created_at) as date,
                    COUNT(*) as transactions,
                    COUNT(CASE WHEN pt.status = 'collected' THEN 1 END) as collected,
                    COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.amount ELSE 0 END), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.change_amount ELSE 0 END), 0) as total_change
                FROM {$payment_table} pt
                WHERE {$where_clause}
                GROUP BY DATE(pt.created_at)
                ORDER BY DATE(pt.created_at) DESC
                LIMIT 30
            ";
            
            $daily_breakdown = $wpdb->get_results($wpdb->prepare($daily_query, ...$where_values));
            
            // Get agent breakdown if not filtering by specific agent
            $agent_breakdown = array();
            if (!$agent_id) {
                $agent_query = "
                    SELECT 
                        pt.agent_id,
                        u.display_name as agent_name,
                        COUNT(*) as transactions,
                        COUNT(CASE WHEN pt.status = 'collected' THEN 1 END) as collected,
                        COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.amount ELSE 0 END), 0) as total_amount,
                        COALESCE(SUM(CASE WHEN pt.status = 'collected' THEN pt.change_amount ELSE 0 END), 0) as total_change
                    FROM {$payment_table} pt
                    LEFT JOIN {$this->database->get_table_name('delivery_agents')} da ON pt.agent_id = da.id
                    LEFT JOIN {$wpdb->users} u ON da.user_id = u.ID
                    WHERE {$where_clause}
                    GROUP BY pt.agent_id, u.display_name
                    ORDER BY total_amount DESC
                ";
                
                $agent_breakdown = $wpdb->get_results($wpdb->prepare($agent_query, ...$where_values));
            }
            
            // Calculate derived metrics
            $collection_rate = $summary->total_transactions > 0 ? 
                round(($summary->collected_count / $summary->total_transactions) * 100, 2) : 0;
            
            $variance_amount = $summary->total_received - $summary->total_collected;
            $variance_percentage = $summary->total_collected > 0 ? 
                round(($variance_amount / $summary->total_collected) * 100, 2) : 0;
            
            return array(
                'summary' => array(
                    'total_transactions' => intval($summary->total_transactions),
                    'collected_count' => intval($summary->collected_count),
                    'pending_count' => intval($summary->pending_count),
                    'failed_count' => intval($summary->failed_count),
                    'total_collected' => floatval($summary->total_collected),
                    'total_received' => floatval($summary->total_received),
                    'total_change' => floatval($summary->total_change),
                    'avg_order_value' => floatval($summary->avg_order_value),
                    'highest_order' => floatval($summary->highest_order),
                    'lowest_order' => floatval($summary->lowest_order),
                    'collection_rate' => $collection_rate,
                    'variance_amount' => $variance_amount,
                    'variance_percentage' => $variance_percentage
                ),
                'daily_breakdown' => $daily_breakdown ?: array(),
                'agent_breakdown' => $agent_breakdown ?: array(),
                'filters_applied' => $filters,
                'date_range' => array(
                    'from' => $date_from,
                    'to' => $date_to,
                    'days' => (strtotime($date_to) - strtotime($date_from)) / (24 * 60 * 60) + 1
                )
            );
            
        } catch (Exception $e) {
            error_log('RestroReach: Payment statistics error - ' . $e->getMessage());
            
            // Return empty statistics on error
            return array(
                'summary' => array(
                    'total_transactions' => 0,
                    'collected_count' => 0,
                    'pending_count' => 0,
                    'failed_count' => 0,
                    'total_collected' => 0,
                    'total_received' => 0,
                    'total_change' => 0,
                    'avg_order_value' => 0,
                    'highest_order' => 0,
                    'lowest_order' => 0,
                    'collection_rate' => 0,
                    'variance_amount' => 0,
                    'variance_percentage' => 0
                ),
                'daily_breakdown' => array(),
                'agent_breakdown' => array(),
                'filters_applied' => $filters,
                'date_range' => array(
                    'from' => $date_from,
                    'to' => $date_to,
                    'days' => 1
                ),
                'error' => $e->getMessage()
            );
        }
    }

    // ========================================
    // COD Payment Collection Methods
    // ========================================
    
    /**
     * Handle COD payment collection by delivery agent
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID
     * @param int $agent_id Delivery agent ID
     * @param float $collected_amount Amount collected from customer
     * @param array $options Additional options
     * @return array Result of collection attempt
     */
    public function handle_cod_collection(int $order_id, int $agent_id, float $collected_amount, array $options = array()): array {
        // Debug logging
        error_log('[RDM] COD Collection initiated - Order: ' . $order_id . ', Agent: ' . $agent_id . ', Amount: ' . $collected_amount);
        
        // Security check
        if (!current_user_can('rdm_handle_cod_payment')) {
            error_log('[RDM] COD Collection failed - Insufficient permissions for user: ' . get_current_user_id());
            return array(
                'success' => false,
                'message' => __('Insufficient permissions to handle COD payments', 'restaurant-delivery-manager')
            );
        }
        
        try {
            global $wpdb;
            
            // Validate order
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log('[RDM] COD Collection failed - Invalid order ID: ' . $order_id);
                throw new Exception(__('Invalid order ID', 'restaurant-delivery-manager'));
            }
            
            // Validate agent
            $agent = $this->database->get_agent($agent_id);
            if (!$agent) {
                throw new Exception(__('Invalid agent ID', 'restaurant-delivery-manager'));
            }
            
            // Get payment record
            $payment = $this->get_payment_record($order_id);
            if (!$payment) {
                // Create payment record if it doesn't exist
                $payment_id = $this->create_payment_record($order_id);
                $payment = $this->get_payment_record($order_id);
            }
            
            // Validate payment type
            if ($payment->payment_type !== 'cod') {
                throw new Exception(__('This order is not a COD payment', 'restaurant-delivery-manager'));
            }
            
            // Calculate change
            $order_total = floatval($order->get_total());
            $change_amount = $this->calculate_change($order_total, $collected_amount);
            
            // Validate collection amount
            if ($collected_amount < $order_total) {
                throw new Exception(
                    sprintf(
                        __('Collected amount (%.2f) is less than order total (%.2f)', 'restaurant-delivery-manager'),
                        $collected_amount,
                        $order_total
                    )
                );
            }
            
            // Update payment record
            $payment_table = $this->database->get_table_name('payment_transactions');
            $update_result = $wpdb->update(
                $payment_table,
                array(
                    'agent_id' => $agent_id,
                    'collected_amount' => $collected_amount,
                    'change_amount' => $change_amount,
                    'status' => 'collected',
                    'collected_at' => current_time('mysql'),
                    'notes' => sanitize_textarea_field($options['notes'] ?? ''),
                    'metadata' => wp_json_encode($options['metadata'] ?? array())
                ),
                array('order_id' => $order_id),
                array('%d', '%f', '%f', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                throw new Exception(__('Failed to update payment record', 'restaurant-delivery-manager'));
            }
            
            // Update order status
            $order->update_status('completed', 
                sprintf(
                    __('COD payment collected by agent. Amount: %.2f, Change: %.2f', 'restaurant-delivery-manager'),
                    $collected_amount,
                    $change_amount
                )
            );
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Payment collected: %.2f (Change given: %.2f)', 'restaurant-delivery-manager'),
                    $collected_amount,
                    $change_amount
                ),
                true // Customer note
            );
            
            // Update agent's daily reconciliation
            $this->update_agent_reconciliation($agent_id, $collected_amount, $change_amount);
            
            // Fire action hook
            do_action('rdm_cod_payment_collected', $order_id, $agent_id, $collected_amount, $change_amount);
            
            return array(
                'success' => true,
                'message' => __('Payment collected successfully', 'restaurant-delivery-manager'),
                'data' => array(
                    'order_id' => $order_id,
                    'collected_amount' => $collected_amount,
                    'change_amount' => $change_amount,
                    'order_total' => $order_total
                )
            );
            
        } catch (Exception $e) {
            error_log('RestroReach: COD collection failed - ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Calculate change amount
     *
     * @since 1.0.0
     * @param float $order_total Order total amount
     * @param float $collected_amount Amount collected from customer
     * @return float Change amount
     */
    public function calculate_change(float $order_total, float $collected_amount): float {
        $change = $collected_amount - $order_total;
        return max(0, round($change, 2));
    }
    
    /**
     * Get payment record for order
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID
     * @return object|null Payment record or null if not found
     */
    public function get_payment_record(int $order_id): ?object {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $payment_table WHERE order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Create payment record for order
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID
     * @return int|false Payment record ID on success, false on failure
     */
    public function create_payment_record(int $order_id) {
        global $wpdb;
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Determine payment type
        $payment_method = $order->get_payment_method();
        $payment_type = ($payment_method === 'cod') ? 'cod' : 'online';
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        $result = $wpdb->insert(
            $payment_table,
            array(
                'order_id' => $order_id,
                'payment_type' => $payment_type,
                'payment_method' => $payment_method,
                'amount' => floatval($order->get_total()),
                'status' => ($payment_type === 'cod') ? 'pending' : 'collected'
            ),
            array('%d', '%s', '%s', '%f', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    // ========================================
    // Cash Reconciliation Methods
    // ========================================
    
    /**
     * Update agent's daily cash reconciliation
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param float $collected_amount Amount collected
     * @param float $change_amount Change given
     * @return bool Success status
     */
    public function update_agent_reconciliation(int $agent_id, float $collected_amount, float $change_amount): bool {
        global $wpdb;
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        $today = current_time('Y-m-d');
        
        // Check if reconciliation record exists for today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reconciliation_table WHERE agent_id = %d AND reconciliation_date = %s",
            $agent_id,
            $today
        ));
        
        if ($existing) {
            // Update existing record
            $new_collections = floatval($existing->total_collections) + $collected_amount;
            $new_change = floatval($existing->total_change_given) + $change_amount;
            $new_closing_balance = floatval($existing->opening_balance) + $new_collections - $new_change;
            
            return $wpdb->update(
                $reconciliation_table,
                array(
                    'total_collections' => $new_collections,
                    'total_change_given' => $new_change,
                    'closing_balance' => $new_closing_balance
                ),
                array('id' => $existing->id),
                array('%f', '%f', '%f'),
                array('%d')
            ) !== false;
        } else {
            // Create new record
            $closing_balance = $collected_amount - $change_amount;
            
            return $wpdb->insert(
                $reconciliation_table,
                array(
                    'agent_id' => $agent_id,
                    'reconciliation_date' => $today,
                    'total_collections' => $collected_amount,
                    'total_change_given' => $change_amount,
                    'closing_balance' => $closing_balance
                ),
                array('%d', '%s', '%f', '%f', '%f')
            ) !== false;
        }
    }
    
    /**
     * Generate daily cash report for agent
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param string $date Date in Y-m-d format
     * @return array|false Cash report data or false on failure
     */
    public function generate_cash_report(int $agent_id, string $date = '') {
        global $wpdb;
        
        if (empty($date)) {
            $date = current_time('Y-m-d');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        
        // Get payment transactions for the day
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT pt.*, o.post_title as order_number 
             FROM $payment_table pt
             LEFT JOIN {$wpdb->posts} o ON pt.order_id = o.ID
             WHERE pt.agent_id = %d 
             AND pt.payment_type = 'cod' 
             AND DATE(pt.collected_at) = %s
             ORDER BY pt.collected_at ASC",
            $agent_id,
            $date
        ));
        
        // Get reconciliation summary
        $reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reconciliation_table 
             WHERE agent_id = %d AND reconciliation_date = %s",
            $agent_id,
            $date
        ));
        
        // Calculate totals
        $total_collections = 0;
        $total_change = 0;
        $transaction_count = 0;
        
        foreach ($transactions as $transaction) {
            $total_collections += floatval($transaction->collected_amount);
            $total_change += floatval($transaction->change_amount);
            $transaction_count++;
        }
        
        return array(
            'agent_id' => $agent_id,
            'date' => $date,
            'transactions' => $transactions,
            'reconciliation' => $reconciliation,
            'summary' => array(
                'transaction_count' => $transaction_count,
                'total_collections' => $total_collections,
                'total_change' => $total_change,
                'net_amount' => $total_collections - $total_change
            )
        );
    }
    
    /**
     * Perform daily cash reconciliation for all agents
     *
     * @since 1.0.0
     */
    public function daily_cash_reconciliation(): void {
        global $wpdb;
        
        $agents_table = $this->database->get_table_name('delivery_agents');
        $active_agents = $wpdb->get_results(
            "SELECT id, user_id FROM $agents_table WHERE status = 'active'"
        );
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        foreach ($active_agents as $agent) {
            $report = $this->generate_cash_report($agent->id, $yesterday);
            
            if ($report && $report['summary']['transaction_count'] > 0) {
                // Send reconciliation notification to agent
                $this->send_reconciliation_notification($agent->user_id, $report);
                
                // Log reconciliation completion
                error_log("RestroReach: Daily reconciliation completed for agent {$agent->id} on {$yesterday}");
            }
        }
    }
    
    // ========================================
    // AJAX Handlers for COD Collection
    // ========================================
    
    /**
     * AJAX handler for COD payment collection (Enhanced Security)
     *
     * @since 1.0.0
     */
    public function ajax_collect_cod_payment(): void {
        try {
            // Rate limiting check
            $user_id = get_current_user_id();
            $rate_limit_key = 'rdm_cod_collection_' . $user_id;
            $collection_attempts = get_transient($rate_limit_key) ?: 0;
            
            if ($collection_attempts > 10) { // Max 10 attempts per hour
                wp_send_json_error(array(
                    'message' => __('Too many collection attempts. Please try again later.', 'restaurant-delivery-manager'),
                    'error_code' => 'rate_limit_exceeded'
                ));
                return;
            }
            
            // Increment attempt counter
            set_transient($rate_limit_key, $collection_attempts + 1, HOUR_IN_SECONDS);
            
            // Enhanced nonce verification with specific action
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'rdm_mobile_nonce') && !wp_verify_nonce($nonce, 'rdm_agent_mobile')) {
                wp_send_json_error(array(
                    'message' => __('Security check failed', 'restaurant-delivery-manager'),
                    'error_code' => 'nonce_failed'
                ));
                return;
            }
            
            // Enhanced capability check
            if (!current_user_can('rdm_handle_cod_payment')) {
                wp_send_json_error(array(
                    'message' => __('Insufficient permissions', 'restaurant-delivery-manager'),
                    'error_code' => 'insufficient_permissions'
                ));
                return;
            }
            
            // Enhanced input validation and sanitization
            $order_id = $this->validate_order_id($_POST['order_id'] ?? 0);
            if (!$order_id) {
                wp_send_json_error(array(
                    'message' => __('Invalid order ID', 'restaurant-delivery-manager'),
                    'error_code' => 'invalid_order_id'
                ));
                return;
            }
            
            $collected_amount = $this->validate_amount($_POST['collected_amount'] ?? 0);
            if ($collected_amount === false) {
                wp_send_json_error(array(
                    'message' => __('Invalid collection amount', 'restaurant-delivery-manager'),
                    'error_code' => 'invalid_amount'
                ));
                return;
            }
            
            $change_amount = $this->validate_amount($_POST['change_amount'] ?? 0, true); // Allow zero
            $notes = $this->sanitize_payment_notes($_POST['notes'] ?? '');
            $timestamp = $this->validate_timestamp($_POST['timestamp'] ?? '');
            
            // Get current agent with enhanced validation
            $agent_id = $this->get_current_agent_id();
            if (!$agent_id) {
                wp_send_json_error(array(
                    'message' => __('Agent not found or not authenticated', 'restaurant-delivery-manager'),
                    'error_code' => 'agent_not_found'
                ));
                return;
            }
            
            // Enhanced order assignment validation
            if (!$this->is_order_assigned_to_agent($order_id, $agent_id)) {
                wp_send_json_error(array(
                    'message' => __('Order not assigned to current agent', 'restaurant-delivery-manager'),
                    'error_code' => 'order_not_assigned'
                ));
                return;
            }
            
            // Validate order amount consistency
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error(array(
                    'message' => __('Order not found', 'restaurant-delivery-manager'),
                    'error_code' => 'order_not_found'
                ));
                return;
            }
            
            $order_total = floatval($order->get_total());
            
            // Enhanced amount validation
            if (!$this->validate_collection_amounts($order_total, $collected_amount, $change_amount)) {
                wp_send_json_error(array(
                    'message' => __('Invalid amount configuration', 'restaurant-delivery-manager'),
                    'error_code' => 'invalid_amounts'
                ));
                return;
            }
            
            // Check for duplicate payment collection
            if ($this->is_payment_already_collected($order_id)) {
                wp_send_json_error(array(
                    'message' => __('Payment already collected for this order', 'restaurant-delivery-manager'),
                    'error_code' => 'payment_already_collected'
                ));
                return;
            }
            
            // Process the payment with enhanced security
            $options = array(
                'notes' => $notes,
                'metadata' => array(
                    'timestamp' => $timestamp,
                    'collected_via' => 'mobile_app',
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    'ip_address' => $this->get_client_ip(),
                    'session_id' => session_id() ?: 'unknown'
                )
            );
            
            $result = $this->handle_cod_collection($order_id, $agent_id, $collected_amount, $options);
            
            if ($result['success']) {
                // Log successful collection
                $this->log_payment_security_event('cod_collection_success', array(
                    'order_id' => $order_id,
                    'agent_id' => $agent_id,
                    'amount' => $collected_amount
                ));
                
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'data' => array_merge($result['data'], array(
                        'collection_time' => current_time('c'),
                        'collection_id' => uniqid('cod_')
                    ))
                ));
            } else {
                // Log failed collection
                $this->log_payment_security_event('cod_collection_failed', array(
                    'order_id' => $order_id,
                    'agent_id' => $agent_id,
                    'error' => $result['message']
                ));
                
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'error_code' => 'collection_failed'
                ));
            }
            
        } catch (Exception $e) {
            // Log security exception
            $this->log_payment_security_event('cod_collection_exception', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            error_log('RestroReach COD Collection Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while processing payment', 'restaurant-delivery-manager'),
                'error_code' => 'exception'
            ));
        }
    }
    
    /**
     * AJAX handler for change calculation
     *
     * @since 1.0.0
     */
    public function ajax_calculate_change(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $order_total = floatval($_POST['order_total'] ?? 0);
        $collected_amount = floatval($_POST['collected_amount'] ?? 0);
        
        if ($order_total <= 0 || $collected_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid amounts', 'restaurant-delivery-manager')));
        }
        
        $change = $this->calculate_change($order_total, $collected_amount);
        
        wp_send_json_success(array(
            'change_amount' => $change,
            'formatted_change' => wc_price($change),
            'sufficient_payment' => $collected_amount >= $order_total
        ));
    }
    
    /**
     * AJAX handler for getting agent payments
     *
     * @since 1.0.0
     */
    public function ajax_get_agent_payments(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $agent_id = $this->get_current_agent_id();
        if (!$agent_id) {
            wp_send_json_error(array('message' => __('Not authenticated as delivery agent', 'restaurant-delivery-manager')));
        }
        
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        $report = $this->generate_cash_report($agent_id, $date);
        
        if ($report) {
            wp_send_json_success($report);
        } else {
            wp_send_json_error(array('message' => __('Failed to generate cash report', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for cash reconciliation
     *
     * @since 1.0.0
     */
    public function ajax_reconcile_cash(): void {
        // Debug logging
        error_log('[RDM] Cash reconciliation initiated');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            error_log('[RDM] Cash reconciliation failed - Security check failed');
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $agent_id = $this->get_current_agent_id();
        if (!$agent_id) {
            error_log('[RDM] Cash reconciliation failed - No agent ID found');
            wp_send_json_error(array('message' => __('Not authenticated as delivery agent', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $submitted_amount = floatval($_POST['submitted_amount'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        
        error_log('[RDM] Cash reconciliation data - Agent: ' . $agent_id . ', Amount: ' . $submitted_amount . ', Date: ' . $date);
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        
        // Update reconciliation record
        $result = $wpdb->update(
            $reconciliation_table,
            array(
                'submitted_amount' => $submitted_amount,
                'variance' => $submitted_amount - $wpdb->get_var($wpdb->prepare(
                    "SELECT closing_balance FROM $reconciliation_table WHERE agent_id = %d AND reconciliation_date = %s",
                    $agent_id,
                    $date
                )),
                'status' => 'submitted',
                'notes' => $notes
            ),
            array('agent_id' => $agent_id, 'reconciliation_date' => $date),
            array('%f', '%f', '%s', '%s'),
            array('%d', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Cash reconciliation submitted successfully', 'restaurant-delivery-manager')));
        } else {
            wp_send_json_error(array('message' => __('Failed to submit cash reconciliation', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for generating cash reports (admin)
     *
     * @since 1.0.0
     */
    public function ajax_generate_cash_report(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $agent_id = absint($_POST['agent_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        
        if (!$agent_id) {
            wp_send_json_error(array('message' => __('Invalid agent ID', 'restaurant-delivery-manager')));
        }
        
        $report = $this->generate_cash_report($agent_id, $date);
        
        if ($report) {
            wp_send_json_success($report);
        } else {
            wp_send_json_error(array('message' => __('Failed to generate cash report', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for payment verification
     *
     * @since 1.0.0
     */
    public function ajax_verify_payment(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $payment_id = absint($_POST['payment_id'] ?? 0);
        $verified = (bool) $_POST['verified'];
        
        if (!$payment_id) {
            wp_send_json_error(array('message' => __('Invalid payment ID', 'restaurant-delivery-manager')));
        }
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        $result = $wpdb->update(
            $payment_table,
            array(
                'status' => $verified ? 'verified' : 'collected',
                'verified_at' => $verified ? current_time('mysql') : null
            ),
            array('id' => $payment_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $verified 
                    ? __('Payment verified successfully', 'restaurant-delivery-manager')
                    : __('Payment verification removed', 'restaurant-delivery-manager')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update payment status', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for reconciliation verification
     *
     * @since 1.0.0
     */
    public function ajax_verify_reconciliation(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $reconciliation_id = absint($_POST['reconciliation_id'] ?? 0);
        
        if (!$reconciliation_id) {
            wp_send_json_error(array('message' => __('Invalid reconciliation ID', 'restaurant-delivery-manager')));
        }
        
        // Get verify action and admin notes
        $verify_action = sanitize_text_field($_POST['verify_action'] ?? 'approve');
        $admin_notes = sanitize_textarea_field($_POST['admin_notes'] ?? '');
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        
        // Check if reconciliation exists and get current data
        $reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reconciliation_table WHERE id = %d",
            $reconciliation_id
        ));
        
        if (!$reconciliation) {
            wp_send_json_error(array('message' => __('Reconciliation not found', 'restaurant-delivery-manager')));
        }
        
        // Determine status based on action
        $new_status = ($verify_action === 'approve') ? 'verified' : 'rejected';
        
        // Check for large discrepancy and set flag
        $variance = abs((float) $reconciliation->variance);
        $discrepancy_flag = $variance > 50 ? 1 : 0;
        
        $update_data = array(
            'status' => $new_status,
            'admin_notes' => $admin_notes,
            'discrepancy_flag' => $discrepancy_flag,
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $reconciliation_table,
            $update_data,
            array('id' => $reconciliation_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $action_text = ($verify_action === 'approve') ? 'approved' : 'rejected';
            wp_send_json_success(array(
                'message' => sprintf(__('Cash reconciliation %s successfully', 'restaurant-delivery-manager'), $action_text)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update reconciliation', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for getting reconciliation details
     *
     * @since 1.1.0
     */
    public function ajax_get_reconciliation_details(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $reconciliation_id = absint($_POST['reconciliation_id'] ?? 0);
        
        if (!$reconciliation_id) {
            wp_send_json_error(array('message' => __('Invalid reconciliation ID', 'restaurant-delivery-manager')));
        }
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        $agents_table = $this->database->get_table_name('delivery_agents');
        
        $reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.display_name as agent_name
             FROM $reconciliation_table r
             INNER JOIN $agents_table a ON r.agent_id = a.id
             INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE r.id = %d",
            $reconciliation_id
        ));
        
        if (!$reconciliation) {
            wp_send_json_error(array('message' => __('Reconciliation not found', 'restaurant-delivery-manager')));
        }
        
        $response_data = array(
            'agent_name' => $reconciliation->agent_name,
            'date' => wp_date(get_option('date_format'), strtotime($reconciliation->reconciliation_date)),
            'collections' => wc_price($reconciliation->total_collections),
            'variance' => wc_price($reconciliation->variance ?? 0),
            'agent_notes' => $reconciliation->notes,
            'admin_notes' => $reconciliation->admin_notes ?? ''
        );
        
        wp_send_json_success($response_data);
    }
    
    // ========================================
    // CSV Export Methods
    // ========================================
    
    /**
     * AJAX handler for CSV export
     *
     * @since 1.0.0
     */
    public function ajax_export_reconciliation_csv(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $this->export_reconciliation_csv();
    }
    
    /**
     * Handle CSV export via admin-post
     *
     * @since 1.0.0
     */
    public function handle_csv_export(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'restaurant-delivery-manager'), 403);
        }
        
        // Verify nonce
        if (!check_admin_referer('rdm_export_csv', 'nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'), 403);
        }
        
        $this->export_reconciliation_csv();
    }
    
    /**
     * Export reconciliation data to CSV
     *
     * @since 1.0.0
     */
    private function export_reconciliation_csv(): void {
        global $wpdb;
        
        // Sanitize inputs
        $date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')));
        $date_to = sanitize_text_field($_GET['date_to'] ?? current_time('Y-m-d'));
        $agent_id = !empty($_GET['agent_id']) ? absint($_GET['agent_id']) : 0;
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            wp_die(__('Invalid date format', 'restaurant-delivery-manager'), 400);
        }
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        $agents_table = $this->database->get_table_name('delivery_agents');
        
        // Build query
        $sql = "SELECT 
                    u.display_name as agent_name,
                    pt.order_id,
                    pt.amount as expected_amount,
                    pt.collected_amount as received_amount,
                    (pt.collected_amount - pt.amount) as discrepancy,
                    pt.collected_at as submission_date,
                    pt.status,
                    pt.notes,
                    r.variance as reconciliation_variance,
                    r.status as reconciliation_status,
                    r.admin_notes,
                    r.discrepancy_flag
                FROM $payment_table pt
                INNER JOIN $agents_table a ON pt.agent_id = a.id
                INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
                LEFT JOIN $reconciliation_table r ON (pt.agent_id = r.agent_id AND DATE(pt.collected_at) = r.reconciliation_date)
                WHERE pt.payment_type = 'cod'
                AND pt.status = 'collected'
                AND DATE(pt.collected_at) BETWEEN %s AND %s";
        
        $params = array($date_from, $date_to);
        
        if ($agent_id > 0) {
            $sql .= " AND pt.agent_id = %d";
            $params[] = $agent_id;
        }
        
        $sql .= " ORDER BY pt.collected_at DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        // Generate filename
        $filename = 'reconciliation-report-' . $date_from . '-to-' . $date_to . '.csv';
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Add UTF-8 BOM for Excel compatibility
        echo chr(239) . chr(187) . chr(191);
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        $csv_headers = array(
            __('Agent Name', 'restaurant-delivery-manager'),
            __('Order ID', 'restaurant-delivery-manager'),
            __('Expected Amount', 'restaurant-delivery-manager'),
            __('Received Amount', 'restaurant-delivery-manager'),
            __('Discrepancy', 'restaurant-delivery-manager'),
            __('Submission Date', 'restaurant-delivery-manager'),
            __('Payment Status', 'restaurant-delivery-manager'),
            __('Agent Notes', 'restaurant-delivery-manager'),
            __('Reconciliation Variance', 'restaurant-delivery-manager'),
            __('Reconciliation Status', 'restaurant-delivery-manager'),
            __('Admin Notes', 'restaurant-delivery-manager'),
            __('High Discrepancy Flag', 'restaurant-delivery-manager')
        );
        
        fputcsv($output, $csv_headers);
        
        // Add data rows
        foreach ($results as $row) {
            $csv_row = array(
                $row['agent_name'],
                $row['order_id'],
                number_format((float)$row['expected_amount'], 2),
                number_format((float)$row['received_amount'], 2),
                number_format((float)$row['discrepancy'], 2),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['submission_date'])),
                ucfirst($row['status']),
                $row['notes'] ?? '',
                $row['reconciliation_variance'] ? number_format((float)$row['reconciliation_variance'], 2) : '',
                $row['reconciliation_status'] ? ucfirst($row['reconciliation_status']) : '',
                $row['admin_notes'] ?? '',
                $row['discrepancy_flag'] == 1 ? 'YES' : 'NO'
            );
            
            fputcsv($output, $csv_row);
        }
        
        // Add summary row
        if (!empty($results)) {
            $total_expected = array_sum(array_column($results, 'expected_amount'));
            $total_received = array_sum(array_column($results, 'received_amount'));
            $total_discrepancy = $total_received - $total_expected;
            
            // Add empty row
            fputcsv($output, array());
            
            // Add summary
            fputcsv($output, array(
                __('SUMMARY', 'restaurant-delivery-manager'),
                count($results) . ' ' . __('orders', 'restaurant-delivery-manager'),
                number_format($total_expected, 2),
                number_format($total_received, 2),
                number_format($total_discrepancy, 2),
                '',
                '',
                '',
                '',
                ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX handler for getting reconciliation report data
     *
     * @since 1.0.0
     */
    public function ajax_get_reconciliation_report(): void {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => __('Invalid date format', 'restaurant-delivery-manager')));
        }
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        $agents_table = $this->database->get_table_name('delivery_agents');
        
        $sql = "SELECT 
                    r.id,
                    u.display_name as agent_name,
                    COUNT(pt.id) as total_orders,
                    COALESCE(SUM(pt.collected_amount), 0) as total_collections,
                    COALESCE(SUM(pt.change_amount), 0) as total_change,
                    (COALESCE(SUM(pt.collected_amount), 0) - COALESCE(SUM(pt.change_amount), 0)) as expected_cash,
                    r.submitted_amount,
                    r.variance,
                    r.status,
                    r.notes
                FROM $reconciliation_table r
                INNER JOIN $agents_table a ON r.agent_id = a.id
                INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
                LEFT JOIN $payment_table pt ON (pt.agent_id = r.agent_id AND DATE(pt.collected_at) = r.reconciliation_date AND pt.payment_type = 'cod' AND pt.status = 'collected')
                WHERE r.reconciliation_date = %s
                GROUP BY r.id, u.display_name, r.submitted_amount, r.variance, r.status, r.notes
                ORDER BY u.display_name";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $date));
        
        // Format results for frontend
        $formatted_results = array();
        foreach ($results as $row) {
            $formatted_results[] = array(
                'id' => $row->id,
                'agent_name' => $row->agent_name,
                'total_orders' => $row->total_orders,
                'total_collections' => wc_price($row->total_collections),
                'total_change' => wc_price($row->total_change),
                'expected_cash' => wc_price($row->expected_cash),
                'submitted_amount' => $row->submitted_amount ? wc_price($row->submitted_amount) : '-',
                'variance' => $row->variance ? wc_price($row->variance) : '-',
                'status' => ucfirst($row->status),
                'notes' => $row->notes ?: ''
            );
        }
        
        wp_send_json_success($formatted_results);
    }

    // ========================================
    // Helper Methods
    // ========================================
    
    /**
     * Get current agent ID from secure session
     *
     * @since 1.0.0
     * @return int|false Agent ID or false if not found
     */
    private function get_current_agent_id() {
        // Use secure session authentication from mobile frontend
        if (class_exists('RDM_Mobile_Frontend')) {
            $mobile_frontend = RDM_Mobile_Frontend::instance();
            $user_id = $mobile_frontend->get_authenticated_user_id();
            
            if (!$user_id) {
                return false;
            }
            
            // Get agent record from database
            $agent = $this->database->get_agent_by_user_id($user_id);
            return $agent ? $agent->id : false;
        }
        
        // Fallback for admin context
        if (is_admin() && current_user_can('rdm_handle_cod_payment')) {
            $current_user = wp_get_current_user();
            if ($current_user && user_can($current_user, 'delivery_agent')) {
                $agent = $this->database->get_agent_by_user_id($current_user->ID);
                return $agent ? $agent->id : false;
            }
        }
        
        return false;
    }
    
    /**
     * Check if order is assigned to agent
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param int $agent_id Agent ID
     * @return bool True if assigned
     */
    private function is_order_assigned_to_agent(int $order_id, int $agent_id): bool {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $assigned_agent_id = $order->get_meta('_rdm_assigned_agent_id');
        return intval($assigned_agent_id) === $agent_id;
    }
    
    /**
     * Get device information for logging
     *
     * @since 1.0.0
     * @return array Device info
     */
    private function get_device_info(): array {
        return array(
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'timestamp' => current_time('c')
        );
    }
    
    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string IP address
     */
    private function get_client_ip(): string {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Get current location (if available)
     *
     * @since 1.0.0
     * @return array|null Location data
     */
    private function get_current_location(): ?array {
        // This would be populated from GPS tracking data
        // For now, we'll return null and implement later
        return null;
    }
    
    /**
     * Get agent reconciliation data for a specific date
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param string $date Date in Y-m-d format
     * @return array Reconciliation data
     */
    public function get_agent_reconciliation_data(int $agent_id, string $date): array {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        // Get COD transactions for the date
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT COUNT(*) as total_orders,
                    COALESCE(SUM(collected_amount), 0) as total_collections,
                    COALESCE(SUM(change_amount), 0) as total_change
             FROM $payment_table
             WHERE agent_id = %d
             AND payment_type = 'cod'
             AND status = 'collected'
             AND DATE(collected_at) = %s",
            $agent_id,
            $date
        ));
        
        $data = $transactions[0] ?? (object) array(
            'total_orders' => 0,
            'total_collections' => 0,
            'total_change' => 0
        );
        
        return array(
            'total_orders' => intval($data->total_orders),
            'total_collections' => floatval($data->total_collections),
            'total_change' => floatval($data->total_change),
            'expected_cash' => floatval($data->total_collections) - floatval($data->total_change),
            'date' => $date
        );
    }
    
    /**
     * Submit cash reconciliation
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID
     * @param string $date Date
     * @param float $actual_cash Actual cash amount
     * @param float $expected_cash Expected cash amount
     * @param float $variance Variance amount
     * @param string $notes Notes
     * @return int|false Reconciliation ID or false on failure
     */
    public function submit_cash_reconciliation(int $agent_id, string $date, float $actual_cash, float $expected_cash, float $variance, string $notes = '') {
        global $wpdb;
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        
        // Check if reconciliation already exists for this date
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $reconciliation_table WHERE agent_id = %d AND reconciliation_date = %s",
            $agent_id,
            $date
        ));
        
        $data = array(
            'submitted_amount' => $actual_cash,
            'variance' => $variance,
            'status' => abs($variance) <= 2 ? 'approved' : 'pending_review',
            'notes' => $notes,
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $reconciliation_table,
                $data,
                array('id' => $existing->id),
                array('%f', '%f', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $result !== false ? $existing->id : false;
        } else {
            // Create new record
            $reconciliation_data = $this->get_agent_reconciliation_data($agent_id, $date);
            
            $insert_data = array_merge($data, array(
                'agent_id' => $agent_id,
                'reconciliation_date' => $date,
                'total_collections' => $reconciliation_data['total_collections'],
                'total_change_given' => $reconciliation_data['total_change'],
                'closing_balance' => $reconciliation_data['expected_cash']
            ));
            
            $result = $wpdb->insert(
                $reconciliation_table,
                $insert_data,
                array('%d', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    // ========================================
    // Helper Methods for Payment Status
    // ========================================
    
    /**
     * Get payment status by order ID
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return array Payment status data
     */
    public function get_payment_status_by_order_id(int $order_id): array {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $payment_table WHERE order_id = %d",
            $order_id
        ));
        
        if (!$payment) {
            return array(
                'status' => 'unknown',
                'label' => __('Unknown', 'restaurant-delivery-manager'),
                'class' => 'unknown'
            );
        }
        
        switch ($payment->status) {
            case 'collected':
                return array(
                    'status' => 'collected',
                    'label' => __('COD Received', 'restaurant-delivery-manager'),
                    'class' => 'received',
                    'collected_at' => $payment->collected_at,
                    'collected_amount' => $payment->collected_amount,
                    'change_amount' => $payment->change_amount
                );
                
            case 'pending':
                return array(
                    'status' => 'pending',
                    'label' => __('COD Pending', 'restaurant-delivery-manager'),
                    'class' => 'pending'
                );
                
            case 'discrepancy':
                return array(
                    'status' => 'discrepancy',
                    'label' => __('COD Discrepancy', 'restaurant-delivery-manager'),
                    'class' => 'discrepancy',
                    'notes' => $payment->notes
                );
                
            default:
                return array(
                    'status' => $payment->status,
                    'label' => ucfirst($payment->status),
                    'class' => 'other'
                );
        }
    }
    
    /**
     * Add missing helper methods referenced in existing code
     */
    
    /**
     * Log payment action for audit trail
     *
     * @since 1.0.0
     */
    private function log_payment_action(int $order_id, int $agent_id, string $action, array $data = array()): void {
        error_log("RestroReach Payment Action: Order {$order_id}, Agent {$agent_id}, Action: {$action}, Data: " . wp_json_encode($data));
    }
    
    /**
     * Send payment notifications
     *
     * @since 1.0.0
     */
    private function send_payment_notifications(int $order_id, int $agent_id, string $type): void {
        // Placeholder for notification system
        do_action('rdm_payment_notification', $order_id, $agent_id, $type);
    }
    
    /**
     * Get last transaction ID for order
     *
     * @since 1.0.0
     */
    private function get_last_transaction_id(int $order_id): ?string {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        $transaction = $wpdb->get_var($wpdb->prepare(
            "SELECT transaction_reference FROM $payment_table WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
            $order_id
        ));
        
        return $transaction ?: uniqid('rdm_');
    }
    
    /**
     * Send variance notification for large discrepancies
     *
     * @since 1.0.0
     */
    private function send_variance_notification(int $agent_id, float $variance, string $date): void {
        // Placeholder for variance notification system
        do_action('rdm_variance_notification', $agent_id, $variance, $date);
    }

    /**
     * Enhanced order ID validation
     *
     * @param mixed $order_id Raw order ID input
     * @return int|false Valid order ID or false
     */
    private function validate_order_id($order_id) {
        $order_id = absint($order_id);
        
        if ($order_id <= 0) {
            return false;
        }
        
        // Check if order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Check if order is valid for COD collection
        if (!in_array($order->get_status(), array('processing', 'out-for-delivery', 'preparing'))) {
            return false;
        }
        
        return $order_id;
    }

    /**
     * Enhanced amount validation
     *
     * @param mixed $amount Raw amount input
     * @param bool $allow_zero Whether to allow zero values
     * @return float|false Valid amount or false
     */
    private function validate_amount($amount, bool $allow_zero = false) {
        if (!is_numeric($amount)) {
            return false;
        }
        
        $amount = floatval($amount);
        
        if (!$allow_zero && $amount <= 0) {
            return false;
        }
        
        if ($allow_zero && $amount < 0) {
            return false;
        }
        
        // Prevent unreasonably large amounts (over $10,000)
        if ($amount > 10000) {
            return false;
        }
        
        // Round to 2 decimal places
        return round($amount, 2);
    }

    /**
     * Sanitize payment notes
     *
     * @param string $notes Raw notes input
     * @return string Sanitized notes
     */
    private function sanitize_payment_notes(string $notes): string {
        $notes = sanitize_textarea_field($notes);
        
        // Limit length to prevent database overflow
        if (strlen($notes) > 500) {
            $notes = substr($notes, 0, 500);
        }
        
        return $notes;
    }

    /**
     * Validate timestamp
     *
     * @param string $timestamp Raw timestamp input
     * @return string Valid timestamp
     */
    private function validate_timestamp(string $timestamp): string {
        if (empty($timestamp)) {
            return current_time('c');
        }
        
        // Validate timestamp format
        $parsed_time = strtotime($timestamp);
        if ($parsed_time === false) {
            return current_time('c');
        }
        
        // Ensure timestamp is not in the future (with 5 minute tolerance)
        $now = time();
        if ($parsed_time > ($now + 300)) {
            return current_time('c');
        }
        
        // Ensure timestamp is not too old (24 hours)
        if ($parsed_time < ($now - 86400)) {
            return current_time('c');
        }
        
        return date('c', $parsed_time);
    }

    /**
     * Validate collection amounts consistency
     *
     * @param float $order_total Order total amount
     * @param float $collected_amount Amount collected from customer
     * @param float $change_amount Change given to customer
     * @return bool Whether amounts are valid
     */
    private function validate_collection_amounts(float $order_total, float $collected_amount, float $change_amount): bool {
        // Collected amount must be at least the order total
        if ($collected_amount < $order_total) {
            return false;
        }
        
        // Change amount should be reasonable
        $expected_change = $collected_amount - $order_total;
        $change_difference = abs($change_amount - $expected_change);
        
        // Allow small rounding differences (1 cent)
        if ($change_difference > 0.01) {
            return false;
        }
        
        // Prevent excessive overpayment (more than $100 over order total)
        if (($collected_amount - $order_total) > 100) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if payment is already collected
     *
     * @param int $order_id Order ID
     * @return bool Whether payment is already collected
     */
    private function is_payment_already_collected(int $order_id): bool {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $payment_table WHERE order_id = %d AND status IN ('collected', 'verified', 'reconciled')",
            $order_id
        ));
        
        return $count > 0;
    }

    /**
     * Log payment security events
     *
     * @param string $event_type Event type
     * @param array $data Event data
     * @return void
     */
    private function log_payment_security_event(string $event_type, array $data): void {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'data' => $data
        );
        
        // Store in WordPress options (could be moved to dedicated log table)
        $logs = get_option('rdm_payment_security_logs', array());
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        $logs[] = $log_entry;
        update_option('rdm_payment_security_logs', $logs, false);
        
        // Log critical events to error log
        if (in_array($event_type, array('cod_collection_exception', 'payment_fraud_detected'))) {
            error_log('RestroReach Payment Security Event: ' . wp_json_encode($log_entry));
        }
    }
}