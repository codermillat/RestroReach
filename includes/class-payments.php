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
        // Security check
        if (!current_user_can('rdm_handle_cod_payment')) {
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
    // AJAX Handlers
    // ========================================
    
    /**
     * AJAX handler for COD payment collection
     *
     * @since 1.0.0
     */
    public function ajax_collect_cod_payment(): void {
        // Use centralized security validation
        $security_result = RDM_Security_Utilities::validate_agent_ajax_request('rdm_agent_mobile');
        if (is_wp_error($security_result)) {
            return; // Error already sent by validation method
        }
        
        // Get current agent
        $agent_id = $this->get_current_agent_id();
        if (!$agent_id) {
            wp_send_json_error(array('message' => __('Not authenticated as delivery agent', 'restaurant-delivery-manager')));
        }
        
        // Use centralized input validation
        $order_id = RDM_Security_Utilities::validate_order_id($_POST['order_id'] ?? 0);
        if (is_wp_error($order_id)) {
            return; // Error already sent by validation method
        }
        
        $collected_amount = RDM_Security_Utilities::validate_amount($_POST['collected_amount'] ?? 0);
        if (is_wp_error($collected_amount)) {
            return; // Error already sent by validation method
        }
        
        $notes = RDM_Security_Utilities::sanitize_textarea($_POST['notes'] ?? '');
        
        if ($collected_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid collection amount', 'restaurant-delivery-manager')));
        }
        
        // Process collection
        $result = $this->handle_cod_collection($order_id, $agent_id, $collected_amount, array('notes' => $notes));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_agent_mobile')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
        }
        
        $agent_id = $this->get_current_agent_id();
        if (!$agent_id) {
            wp_send_json_error(array('message' => __('Not authenticated as delivery agent', 'restaurant-delivery-manager')));
        }
        
        global $wpdb;
        
        $submitted_amount = floatval($_POST['submitted_amount'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
        
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
        
        $reconciliation_table = $this->database->get_table_name('cash_reconciliation');
        
        $result = $wpdb->update(
            $reconciliation_table,
            array(
                'status' => 'verified',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $reconciliation_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Cash reconciliation verified successfully', 'restaurant-delivery-manager')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to verify reconciliation', 'restaurant-delivery-manager')));
        }
    }
    
    // ========================================
    // Helper Methods
    // ========================================
    
    /**
     * Get current agent ID from session/cookie
     *
     * @since 1.0.0
     * @return int|false Agent ID or false if not found
     */
    private function get_current_agent_id() {
        // Get user ID from cookie (as implemented in mobile frontend)
        $user_id = isset($_COOKIE['rdm_agent_logged_in']) ? intval($_COOKIE['rdm_agent_logged_in']) : 0;
        
        if (!$user_id) {
            return false;
        }
        
        // Get agent record from database
        $agent = $this->database->get_agent_by_user_id($user_id);
        return $agent ? $agent->id : false;
    }
    
    /**
     * Send reconciliation notification to agent
     *
     * @since 1.0.0
     * @param int $user_id WordPress user ID
     * @param array $report Cash report data
     */
    private function send_reconciliation_notification(int $user_id, array $report): void {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Use notification system if available
        if (class_exists('RDM_Notifications')) {
            $notifications = RDM_Notifications::instance();
            $notifications->send_notification(
                $user_id,
                'cash_reconciliation',
                sprintf(
                    __('Daily cash reconciliation required for %s. Net amount: %s', 'restaurant-delivery-manager'),
                    $report['date'],
                    wc_price($report['summary']['net_amount'])
                ),
                array('report' => $report)
            );
        }
    }
    
    /**
     * Enqueue payment-related assets
     *
     * @since 1.0.0
     */
    public function enqueue_payment_assets(): void {
        $page = get_query_var('rdm_agent_page');
        
        if ($page === 'dashboard') {
            wp_enqueue_style(
                'rdm-payments',
                plugin_dir_url(__FILE__) . '../assets/css/rdm-payments.css',
                array(),
                RDM_VERSION
            );
            
            wp_enqueue_script(
                'rdm-payments',
                plugin_dir_url(__FILE__) . '../assets/js/rdm-payments.js',
                array('jquery'),
                RDM_VERSION,
                true
            );
            
            wp_localize_script('rdm-payments', 'rdmPayments', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rdm_agent_mobile'),
                'currency' => get_woocommerce_currency_symbol(),
                'texts' => array(
                    'collectPayment' => __('Collect Payment', 'restaurant-delivery-manager'),
                    'changeCalculated' => __('Change calculated', 'restaurant-delivery-manager'),
                    'insufficientPayment' => __('Insufficient payment amount', 'restaurant-delivery-manager'),
                    'paymentCollected' => __('Payment collected successfully', 'restaurant-delivery-manager'),
                    'error' => __('An error occurred', 'restaurant-delivery-manager')
                )
            ));
        }
    }
    
    /**
     * Add payment meta boxes to admin order edit page
     *
     * @since 1.0.0
     */
    public function add_payment_meta_boxes(): void {
        add_meta_box(
            'rdm_payment_details',
            __('Delivery Payment Details', 'restaurant-delivery-manager'),
            array($this, 'render_payment_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render payment details meta box
     *
     * @since 1.0.0
     * @param WP_Post $post Order post object
     */
    public function render_payment_meta_box($post): void {
        $order_id = $post->ID;
        $payment = $this->get_payment_record($order_id);
        
        if (!$payment) {
            echo '<p>' . esc_html__('No payment record found.', 'restaurant-delivery-manager') . '</p>';
            return;
        }
        
        echo '<div class="rdm-payment-details">';
        echo '<p><strong>' . esc_html__('Payment Type:', 'restaurant-delivery-manager') . '</strong> ' . esc_html($this->payment_types[$payment->payment_type] ?? $payment->payment_type) . '</p>';
        echo '<p><strong>' . esc_html__('Status:', 'restaurant-delivery-manager') . '</strong> ' . esc_html($this->payment_statuses[$payment->status] ?? $payment->status) . '</p>';
        echo '<p><strong>' . esc_html__('Amount:', 'restaurant-delivery-manager') . '</strong> ' . wc_price($payment->amount) . '</p>';
        
        if ($payment->collected_amount) {
            echo '<p><strong>' . esc_html__('Collected:', 'restaurant-delivery-manager') . '</strong> ' . wc_price($payment->collected_amount) . '</p>';
            echo '<p><strong>' . esc_html__('Change:', 'restaurant-delivery-manager') . '</strong> ' . wc_price($payment->change_amount) . '</p>';
        }
        
        if ($payment->collected_at) {
            echo '<p><strong>' . esc_html__('Collected At:', 'restaurant-delivery-manager') . '</strong> ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->collected_at))) . '</p>';
        }
        
        if ($payment->notes) {
            echo '<p><strong>' . esc_html__('Notes:', 'restaurant-delivery-manager') . '</strong></p>';
            echo '<p>' . esc_html($payment->notes) . '</p>';
        }
        echo '</div>';
    }
    
    /**
     * Handle order status changes
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public function handle_order_status_change(int $order_id, string $old_status, string $new_status, $order): void {
        // Create payment record when order is confirmed
        if ($new_status === 'processing' || $new_status === 'preparing') {
            $this->create_payment_record($order_id);
        }
    }
    
    /**
     * Get payment statistics for dashboard
     *
     * @since 1.0.0
     * @param array $filters Optional filters
     * @return array Payment statistics
     */
    public function get_payment_statistics(array $filters = array()): array {
        global $wpdb;
        
        $payment_table = $this->database->get_table_name('payment_transactions');
        $where_clauses = array('1=1');
        $values = array();
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $values[] = $filters['date_to'];
        }
        
        if (!empty($filters['agent_id'])) {
            $where_clauses[] = 'agent_id = %d';
            $values[] = $filters['agent_id'];
        }
        
        if (!empty($filters['payment_type'])) {
            $where_clauses[] = 'payment_type = %s';
            $values[] = $filters['payment_type'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Build query
        $query = "SELECT 
            payment_type,
            status,
            COUNT(*) as transaction_count,
            SUM(amount) as total_amount,
            SUM(collected_amount) as total_collected,
            SUM(change_amount) as total_change
            FROM $payment_table 
            WHERE $where_sql 
            GROUP BY payment_type, status";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Process results
        $statistics = array(
            'total_transactions' => 0,
            'total_amount' => 0,
            'total_collected' => 0,
            'total_change' => 0,
            'by_type' => array(),
            'by_status' => array()
        );
        
        foreach ($results as $row) {
            $statistics['total_transactions'] += $row->transaction_count;
            $statistics['total_amount'] += floatval($row->total_amount);
            $statistics['total_collected'] += floatval($row->total_collected);
            $statistics['total_change'] += floatval($row->total_change);
            
            if (!isset($statistics['by_type'][$row->payment_type])) {
                $statistics['by_type'][$row->payment_type] = array(
                    'count' => 0,
                    'amount' => 0,
                    'collected' => 0
                );
            }
            
            $statistics['by_type'][$row->payment_type]['count'] += $row->transaction_count;
            $statistics['by_type'][$row->payment_type]['amount'] += floatval($row->total_amount);
            $statistics['by_type'][$row->payment_type]['collected'] += floatval($row->total_collected);
            
            if (!isset($statistics['by_status'][$row->status])) {
                $statistics['by_status'][$row->status] = array(
                    'count' => 0,
                    'amount' => 0
                );
            }
            
            $statistics['by_status'][$row->status]['count'] += $row->transaction_count;
            $statistics['by_status'][$row->status]['amount'] += floatval($row->total_amount);
        }
        
        return $statistics;
    }
} 