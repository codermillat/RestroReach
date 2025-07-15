<?php
/**
 * Restaurant Delivery Manager - Analytics
 *
 * @package RestaurantDeliveryManager
 * @subpackage Analytics
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics and reporting class
 *
 * Provides comprehensive business intelligence including revenue tracking,
 * agent performance metrics, delivery time analysis, and automated reporting.
 *
 * @class RDM_Analytics
 * @version 1.0.0
 */
class RDM_Analytics {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Analytics|null
     */
    private static ?RDM_Analytics $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database|null
     */
    private ?RDM_Database $database = null;
    
    /**
     * Cache duration for analytics data (in seconds)
     *
     * @var int
     */
    private int $cache_duration = 3600; // 1 hour
    
    /**
     * Main Analytics Instance
     *
     * @return RDM_Analytics Main instance
     */
    public static function instance(): RDM_Analytics {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Private for singleton
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Get database instance
        $this->database = RDM_Database::instance();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks(): void {
        // AJAX handlers
        add_action('wp_ajax_rdm_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_rdm_get_revenue_chart', array($this, 'ajax_get_revenue_chart'));
        add_action('wp_ajax_rdm_get_agent_performance', array($this, 'ajax_get_agent_performance'));
        add_action('wp_ajax_rdm_get_delivery_times', array($this, 'ajax_get_delivery_times'));
        add_action('wp_ajax_rdm_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_rdm_export_analytics', array($this, 'ajax_export_analytics'));
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Schedule automated reports
        add_action('init', array($this, 'schedule_reports'));
        add_action('rdm_daily_analytics_report', array($this, 'send_daily_report'));
        add_action('rdm_weekly_analytics_report', array($this, 'send_weekly_report'));
        add_action('rdm_monthly_analytics_report', array($this, 'send_monthly_report'));
    }
    
    /**
     * Enqueue analytics assets
     *
     * @since 1.0.0
     * @param string $hook_suffix Current admin page
     * @return void
     */
    public function enqueue_assets(string $hook_suffix): void {
        // Only load on analytics pages
        if (strpos($hook_suffix, 'restroreach-analytics') === false) {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            array(),
            '4.4.0',
            true
        );
        
        // Enqueue analytics styles
        wp_enqueue_style(
            'rdm-analytics',
            RDM_PLUGIN_URL . 'assets/css/rdm-analytics.css',
            array(),
            RDM_VERSION
        );
        
        // Enqueue analytics scripts
        wp_enqueue_script(
            'rdm-analytics',
            RDM_PLUGIN_URL . 'assets/js/rdm-analytics.js',
            array('jquery', 'chartjs'),
            RDM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('rdm-analytics', 'rdmAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_analytics_nonce'),
            'i18n' => array(
                'error' => __('Error loading analytics data', 'restaurant-delivery-manager'),
                'noData' => __('No data available for selected period', 'restaurant-delivery-manager'),
                'loading' => __('Loading analytics...', 'restaurant-delivery-manager'),
                'exportSuccess' => __('Report exported successfully', 'restaurant-delivery-manager'),
                'exportError' => __('Failed to export report', 'restaurant-delivery-manager'),
            ),
        ));
    }
    
    // ========================================
    // Revenue Analytics
    // ========================================
    
    /**
     * Get revenue data for specified period
     *
     * @since 1.0.0
     * @param string $period Period: 'today', 'week', 'month', 'quarter', 'year'
     * @param string $start_date Optional start date (Y-m-d format)
     * @param string $end_date Optional end date (Y-m-d format)
     * @return array Revenue analytics data
     */
    public function get_revenue_analytics(string $period = 'month', string $start_date = '', string $end_date = ''): array {
        $cache_key = 'rdm_revenue_analytics_' . md5($period . $start_date . $end_date);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period, $start_date, $end_date);
        
        global $wpdb;
        
        $data = array(
            'total_revenue' => 0,
            'order_count' => 0,
            'average_order_value' => 0,
            'delivery_fees' => 0,
            'cod_collections' => 0,
            'refunds' => 0,
            'daily_revenue' => array(),
            'revenue_by_hour' => array(),
            'top_items' => array(),
            'payment_methods' => array(),
        );
        
        if (!class_exists('WooCommerce')) {
            return $data;
        }
        
        try {
            // Get orders for the period
            $orders = wc_get_orders(array(
                'status' => array('completed', 'delivered'),
                'date_created' => $date_range['start'] . '...' . $date_range['end'],
                'limit' => -1,
            ));
            
            $data['order_count'] = count($orders);
            
            foreach ($orders as $order) {
                $total = $order->get_total();
                $data['total_revenue'] += $total;
                
                // Daily revenue breakdown
                $order_date = $order->get_date_created()->format('Y-m-d');
                if (!isset($data['daily_revenue'][$order_date])) {
                    $data['daily_revenue'][$order_date] = 0;
                }
                $data['daily_revenue'][$order_date] += $total;
                
                // Hourly revenue breakdown
                $order_hour = $order->get_date_created()->format('H');
                if (!isset($data['revenue_by_hour'][$order_hour])) {
                    $data['revenue_by_hour'][$order_hour] = 0;
                }
                $data['revenue_by_hour'][$order_hour] += $total;
                
                // Payment method tracking
                $payment_method = $order->get_payment_method();
                if (!isset($data['payment_methods'][$payment_method])) {
                    $data['payment_methods'][$payment_method] = array('count' => 0, 'total' => 0);
                }
                $data['payment_methods'][$payment_method]['count']++;
                $data['payment_methods'][$payment_method]['total'] += $total;
                
                // COD collections
                if ($payment_method === 'cod') {
                    $data['cod_collections'] += $total;
                }
                
                // Delivery fees
                $delivery_fee = $order->get_shipping_total();
                $data['delivery_fees'] += $delivery_fee;
            }
            
            // Calculate average order value
            if ($data['order_count'] > 0) {
                $data['average_order_value'] = $data['total_revenue'] / $data['order_count'];
            }
            
            // Get refunds
            $data['refunds'] = $this->get_refunds_for_period($date_range);
            
            // Get top-selling items
            $data['top_items'] = $this->get_top_selling_items($date_range, 10);
            
        } catch (Exception $e) {
            error_log('RestroReach: Error getting revenue analytics - ' . $e->getMessage());
        }
        
        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);
        
        return $data;
    }
    
    // ========================================
    // Agent Performance Analytics
    // ========================================
    
    /**
     * Get agent performance metrics (optimized)
     *
     * @since 1.0.0
     * @param int $agent_id Agent ID (0 for all agents)
     * @param string $period Period for analysis
     * @return array Performance data
     */
    public function get_agent_performance(int $agent_id = 0, string $period = 'month'): array {
        $cache_key = 'rdm_agent_performance_' . $agent_id . '_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        
        global $wpdb;
        
        // Build WHERE clause for agent filter
        $where_conditions = array(
            'assigned_at >= %s',
            'assigned_at <= %s'
        );
        $query_params = array($date_range['start'], $date_range['end']);
        
        if ($agent_id > 0) {
            $where_conditions[] = 'agent_id = %d';
            $query_params[] = $agent_id;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Optimized single query with all metrics
        $query = $wpdb->prepare(
            "SELECT 
                agent_id,
                COUNT(*) as total_deliveries,
                AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at) END) as avg_delivery_time,
                SUM(CASE WHEN delivered_at IS NOT NULL AND delivered_at <= DATE_ADD(assigned_at, INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) as on_time_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                SUM(CASE WHEN status IN ('cancelled', 'failed') THEN 1 ELSE 0 END) as failed_deliveries,
                MIN(assigned_at) as first_delivery,
                MAX(CASE WHEN status = 'delivered' THEN delivered_at END) as last_delivery,
                COUNT(CASE WHEN delivered_at IS NOT NULL THEN 1 END) as total_completed
             FROM {$this->database->get_table_name('order_assignments')} USE INDEX (idx_agent_assigned_date)
             {$where_clause}
             GROUP BY agent_id
             ORDER BY total_deliveries DESC",
            ...$query_params
        );
        
        $results = $wpdb->get_results($query);
        
        $data = array(
            'agents' => array(),
            'summary' => array(
                'total_agents' => 0,
                'total_deliveries' => 0,
                'avg_delivery_time' => 0,
                'overall_on_time_rate' => 0,
            ),
        );
        
        $total_deliveries = 0;
        $total_on_time = 0;
        $total_completed = 0;
        $total_delivery_time = 0;
        
        foreach ($results as $row) {
            $agent_data = array(
                'agent_id' => $row->agent_id,
                'total_deliveries' => intval($row->total_deliveries),
                'completed_deliveries' => intval($row->completed_deliveries),
                'failed_deliveries' => intval($row->failed_deliveries),
                'on_time_deliveries' => intval($row->on_time_deliveries),
                'avg_delivery_time' => round(floatval($row->avg_delivery_time), 2),
                'on_time_rate' => 0,
                'completion_rate' => 0,
                'first_delivery' => $row->first_delivery,
                'last_delivery' => $row->last_delivery,
            );
            
            // Calculate rates
            if ($row->total_completed > 0) {
                $agent_data['on_time_rate'] = round(($row->on_time_deliveries / $row->total_completed) * 100, 2);
            }
            
            if ($row->total_deliveries > 0) {
                $agent_data['completion_rate'] = round(($row->completed_deliveries / $row->total_deliveries) * 100, 2);
            }
            
            $data['agents'][] = $agent_data;
            
            // Accumulate totals for summary
            $total_deliveries += $row->total_deliveries;
            $total_on_time += $row->on_time_deliveries;
            $total_completed += $row->total_completed;
            $total_delivery_time += ($row->avg_delivery_time * $row->total_completed);
        }
        
        // Calculate summary metrics
        $data['summary']['total_agents'] = count($results);
        $data['summary']['total_deliveries'] = $total_deliveries;
        
        if ($total_completed > 0) {
            $data['summary']['avg_delivery_time'] = round($total_delivery_time / $total_completed, 2);
            $data['summary']['overall_on_time_rate'] = round(($total_on_time / $total_completed) * 100, 2);
        }
        
        // Cache for 10 minutes
        set_transient($cache_key, $data, 600);
        
        return $data;
    }
    
    // ========================================
    // Delivery Time Analytics
    // ========================================
    
    /**
     * Get delivery time analysis
     *
     * @since 1.0.0
     * @param string $period Period for analysis
     * @return array Delivery time analytics
     */
    public function get_delivery_time_analytics(string $period = 'month'): array {
        $cache_key = 'rdm_delivery_times_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT 
                TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at) as delivery_time,
                HOUR(assigned_at) as order_hour,
                DAYOFWEEK(assigned_at) as order_day,
                DATE(assigned_at) as order_date
             FROM {$this->database->get_table_name('order_assignments')}
             WHERE assigned_at >= %s 
             AND assigned_at <= %s
             AND delivered_at IS NOT NULL
             AND status = 'delivered'",
            $date_range['start'],
            $date_range['end']
        );
        
        $results = $wpdb->get_results($query);
        
        $data = array(
            'average_time' => 0,
            'median_time' => 0,
            'fastest_time' => 0,
            'slowest_time' => 0,
            'by_hour' => array_fill(0, 24, array('count' => 0, 'total_time' => 0, 'avg_time' => 0)),
            'by_day' => array_fill(1, 7, array('count' => 0, 'total_time' => 0, 'avg_time' => 0)),
            'distribution' => array(
                '0-15' => 0, '16-30' => 0, '31-45' => 0, '46-60' => 0, '60+' => 0
            ),
            'trends' => array(),
        );
        
        if (empty($results)) {
            set_transient($cache_key, $data, $this->cache_duration);
            return $data;
        }
        
        $delivery_times = array();
        $daily_stats = array();
        
        foreach ($results as $row) {
            $time = intval($row->delivery_time);
            $delivery_times[] = $time;
            
            // By hour analysis
            $hour = intval($row->order_hour);
            $data['by_hour'][$hour]['count']++;
            $data['by_hour'][$hour]['total_time'] += $time;
            
            // By day analysis
            $day = intval($row->order_day);
            $data['by_day'][$day]['count']++;
            $data['by_day'][$day]['total_time'] += $time;
            
            // Distribution
            if ($time <= 15) {
                $data['distribution']['0-15']++;
            } elseif ($time <= 30) {
                $data['distribution']['16-30']++;
            } elseif ($time <= 45) {
                $data['distribution']['31-45']++;
            } elseif ($time <= 60) {
                $data['distribution']['46-60']++;
            } else {
                $data['distribution']['60+']++;
            }
            
            // Daily trends
            $date = $row->order_date;
            if (!isset($daily_stats[$date])) {
                $daily_stats[$date] = array('times' => array(), 'count' => 0);
            }
            $daily_stats[$date]['times'][] = $time;
            $daily_stats[$date]['count']++;
        }
        
        // Calculate statistics
        if (!empty($delivery_times)) {
            sort($delivery_times);
            $data['average_time'] = round(array_sum($delivery_times) / count($delivery_times), 2);
            $data['median_time'] = $this->calculate_median($delivery_times);
            $data['fastest_time'] = min($delivery_times);
            $data['slowest_time'] = max($delivery_times);
        }
        
        // Calculate hourly averages
        for ($hour = 0; $hour < 24; $hour++) {
            if ($data['by_hour'][$hour]['count'] > 0) {
                $data['by_hour'][$hour]['avg_time'] = round($data['by_hour'][$hour]['total_time'] / $data['by_hour'][$hour]['count'], 2);
            }
        }
        
        // Calculate daily averages
        for ($day = 1; $day <= 7; $day++) {
            if ($data['by_day'][$day]['count'] > 0) {
                $data['by_day'][$day]['avg_time'] = round($data['by_day'][$day]['total_time'] / $data['by_day'][$day]['count'], 2);
            }
        }
        
        // Calculate daily trends
        foreach ($daily_stats as $date => $stats) {
            $data['trends'][$date] = array(
                'date' => $date,
                'count' => $stats['count'],
                'avg_time' => round(array_sum($stats['times']) / count($stats['times']), 2),
                'min_time' => min($stats['times']),
                'max_time' => max($stats['times']),
            );
        }
        
        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);
        
        return $data;
    }
    
    // ========================================
    // Customer Satisfaction Analytics
    // ========================================
    
    /**
     * Get customer satisfaction metrics
     *
     * @since 1.0.0
     * @param string $period Period for analysis
     * @return array Customer satisfaction data
     */
    public function get_customer_satisfaction(string $period = 'month'): array {
        $cache_key = 'rdm_customer_satisfaction_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        
        global $wpdb;
        
        $data = array(
            'average_rating' => 0,
            'total_ratings' => 0,
            'rating_distribution' => array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0),
            'repeat_customers' => 0,
            'complaint_rate' => 0,
            'resolution_time' => 0,
            'nps_score' => 0, // Net Promoter Score
        );
        
        // Get ratings from WooCommerce reviews if available
        if (class_exists('WooCommerce')) {
            $rating_query = $wpdb->prepare(
                "SELECT 
                    meta_value as rating,
                    COUNT(*) as count
                 FROM {$wpdb->commentmeta} cm
                 INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
                 WHERE cm.meta_key = 'rating'
                 AND c.comment_date >= %s
                 AND c.comment_date <= %s
                 AND c.comment_approved = '1'
                 GROUP BY meta_value",
                $date_range['start'],
                $date_range['end']
            );
            
            $ratings = $wpdb->get_results($rating_query);
            
            $total_ratings = 0;
            $total_score = 0;
            
            foreach ($ratings as $rating) {
                $score = intval($rating->rating);
                $count = intval($rating->count);
                
                $data['rating_distribution'][$score] = $count;
                $total_ratings += $count;
                $total_score += ($score * $count);
            }
            
            $data['total_ratings'] = $total_ratings;
            if ($total_ratings > 0) {
                $data['average_rating'] = round($total_score / $total_ratings, 2);
                
                // Calculate NPS Score (ratings 4-5 are promoters, 1-2 are detractors)
                $promoters = $data['rating_distribution'][4] + $data['rating_distribution'][5];
                $detractors = $data['rating_distribution'][1] + $data['rating_distribution'][2];
                $data['nps_score'] = round((($promoters - $detractors) / $total_ratings) * 100, 2);
            }
        }
        
        // Get repeat customer data
        $data['repeat_customers'] = $this->get_repeat_customers_count($date_range);
        
        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);
        
        return $data;
    }
    
    // ========================================
    // Peak Hours Analytics
    // ========================================
    
    /**
     * Get peak hours analysis
     *
     * @since 1.0.0
     * @param string $period Period for analysis
     * @return array Peak hours data
     */
    public function get_peak_hours_analytics(string $period = 'month'): array {
        $cache_key = 'rdm_peak_hours_' . $period;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $date_range = $this->get_date_range($period);
        
        $data = array(
            'hourly_orders' => array_fill(0, 24, 0),
            'daily_orders' => array_fill(1, 7, 0), // 1=Monday, 7=Sunday
            'peak_hour' => 0,
            'peak_day' => 1,
            'busiest_periods' => array(),
            'demand_forecast' => array(),
        );
        
        if (!class_exists('WooCommerce')) {
            set_transient($cache_key, $data, $this->cache_duration);
            return $data;
        }
        
        // Get orders for the period
        $orders = wc_get_orders(array(
            'status' => array('completed', 'delivered', 'processing', 'preparing'),
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1,
        ));
        
        foreach ($orders as $order) {
            $order_time = $order->get_date_created();
            $hour = intval($order_time->format('H'));
            $day = intval($order_time->format('N')); // 1=Monday, 7=Sunday
            
            $data['hourly_orders'][$hour]++;
            $data['daily_orders'][$day]++;
        }
        
        // Find peak hour and day
        $data['peak_hour'] = array_search(max($data['hourly_orders']), $data['hourly_orders']);
        $data['peak_day'] = array_search(max($data['daily_orders']), $data['daily_orders']);
        
        // Identify busiest periods (3-hour blocks)
        for ($i = 0; $i < 24; $i += 3) {
            $period_orders = $data['hourly_orders'][$i] + $data['hourly_orders'][$i + 1] + $data['hourly_orders'][$i + 2];
            $data['busiest_periods'][] = array(
                'start_hour' => $i,
                'end_hour' => $i + 2,
                'order_count' => $period_orders,
                'label' => sprintf('%02d:00 - %02d:00', $i, $i + 3),
            );
        }
        
        // Sort busiest periods
        usort($data['busiest_periods'], function($a, $b) {
            return $b['order_count'] - $a['order_count'];
        });
        
        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);
        
        return $data;
    }
    
    // ========================================
    // Helper Methods
    // ========================================
    
    /**
     * Get date range for analysis period
     *
     * @since 1.0.0
     * @param string $period Period identifier
     * @param string $start_date Optional start date override
     * @param string $end_date Optional end date override
     * @return array Date range with start and end
     */
    private function get_date_range(string $period, string $start_date = '', string $end_date = ''): array {
        if ($start_date && $end_date) {
            return array(
                'start' => $start_date . ' 00:00:00',
                'end' => $end_date . ' 23:59:59',
            );
        }
        
        $end = current_time('mysql');
        
        switch ($period) {
            case 'today':
                $start = date('Y-m-d 00:00:00');
                $end = date('Y-m-d 23:59:59');
                break;
                
            case 'yesterday':
                $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $end = date('Y-m-d 23:59:59', strtotime('-1 day'));
                break;
                
            case 'week':
                $start = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
                
            case 'month':
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
                
            case 'quarter':
                $start = date('Y-m-d 00:00:00', strtotime('-90 days'));
                break;
                
            case 'year':
                $start = date('Y-m-d 00:00:00', strtotime('-365 days'));
                break;
                
            default:
                $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
        
        return array(
            'start' => $start,
            'end' => $end,
        );
    }
    
    /**
     * Calculate median from array of numbers
     *
     * @since 1.0.0
     * @param array $numbers Array of numbers
     * @return float Median value
     */
    private function calculate_median(array $numbers): float {
        if (empty($numbers)) {
            return 0;
        }
        
        sort($numbers);
        $count = count($numbers);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        } else {
            return $numbers[$middle];
        }
    }
    
    // ========================================
    // AJAX Handlers
    // ========================================
    
    /**
     * AJAX handler for getting analytics data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_analytics_data(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_view_analytics') && !current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            // Get and validate parameters
            $type = sanitize_text_field($_POST['type'] ?? 'overview');
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $agent_id = absint($_POST['agent_id'] ?? 0);
            
            $data = array();
            
            switch ($type) {
                case 'revenue':
                    $data = $this->get_revenue_analytics($period);
                    break;
                    
                case 'agents':
                    $data = $this->get_agent_performance($agent_id, $period);
                    break;
                    
                case 'delivery_times':
                    $data = $this->get_delivery_time_analytics($period);
                    break;
                    
                case 'satisfaction':
                    $data = $this->get_customer_satisfaction($period);
                    break;
                    
                case 'peak_hours':
                    $data = $this->get_peak_hours_analytics($period);
                    break;
                    
                case 'overview':
                default:
                    $data = array(
                        'revenue' => $this->get_revenue_analytics($period),
                        'agents' => $this->get_agent_performance(0, $period),
                        'delivery_times' => $this->get_delivery_time_analytics($period),
                        'satisfaction' => $this->get_customer_satisfaction($period),
                        'peak_hours' => $this->get_peak_hours_analytics($period),
                    );
                    break;
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for exporting analytics data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_export_analytics(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_export_data') && !current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            // Get parameters
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            
            // Generate export
            $export_data = $this->generate_export_data($period);
            $filename = $this->create_export_file($export_data, $format, $period);
            
            wp_send_json_success(array(
                'download_url' => wp_upload_dir()['url'] . '/' . $filename,
                'filename' => $filename,
            ));
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Generate export data
     *
     * @since 1.0.0
     * @param string $period Period for export
     * @return array Export data
     */
    private function generate_export_data(string $period): array {
        return array(
            'revenue' => $this->get_revenue_analytics($period),
            'agents' => $this->get_agent_performance(0, $period),
            'delivery_times' => $this->get_delivery_time_analytics($period),
            'satisfaction' => $this->get_customer_satisfaction($period),
            'peak_hours' => $this->get_peak_hours_analytics($period),
            'generated_at' => current_time('mysql'),
            'period' => $period,
        );
    }
    
    /**
     * Create export file
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $format Export format
     * @param string $period Period
     * @return string Filename
     */
    private function create_export_file(array $data, string $format, string $period): string {
        $upload_dir = wp_upload_dir();
        $filename = 'analytics-export-' . $period . '-' . date('Y-m-d-H-i-s') . '.' . $format;
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        if ($format === 'csv') {
            $this->create_csv_export($data, $filepath);
        } else {
            $this->create_json_export($data, $filepath);
        }
        
        return $filename;
    }
    
    /**
     * Create CSV export
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $filepath File path
     * @return void
     */
    private function create_csv_export(array $data, string $filepath): void {
        $file = fopen($filepath, 'w');
        
        // Revenue data
        if (isset($data['revenue'])) {
            fputcsv($file, array('Revenue Analytics'));
            fputcsv($file, array('Total Revenue', 'Order Count', 'Avg Order Value', 'COD Collections'));
            fputcsv($file, array(
                $data['revenue']['total_revenue'],
                $data['revenue']['order_count'],
                $data['revenue']['average_order_value'],
                $data['revenue']['cod_collections']
            ));
            fputcsv($file, array()); // Empty row
        }
        
        // Agent performance data
        if (isset($data['agents']['agents'])) {
            fputcsv($file, array('Agent Performance'));
            fputcsv($file, array('Name', 'Total Deliveries', 'Avg Delivery Time', 'On-Time Rate', 'Success Rate'));
            
            foreach ($data['agents']['agents'] as $agent) {
                fputcsv($file, array(
                    $agent['name'],
                    $agent['total_deliveries'],
                    $agent['avg_delivery_time'],
                    $agent['on_time_rate'],
                    $agent['success_rate']
                ));
            }
        }
        
        fclose($file);
    }
    
    /**
     * Create JSON export
     *
     * @since 1.0.0
     * @param array $data Export data
     * @param string $filepath File path
     * @return void
     */
    private function create_json_export(array $data, string $filepath): void {
        file_put_contents($filepath, wp_json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * AJAX handler for getting revenue chart data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_revenue_chart(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_view_analytics')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $data = $this->get_revenue_analytics($period);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting agent performance data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_agent_performance(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_view_analytics')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $agent_id = absint($_POST['agent_id'] ?? 0);
            $data = $this->get_agent_performance($agent_id, $period);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting delivery times data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_delivery_times(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_view_analytics')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $data = $this->get_delivery_time_analytics($period);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for generating reports
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_generate_report(): void {
        try {
            // Security validation
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_analytics_nonce')) {
                throw new Exception(__('Security check failed', 'restaurant-delivery-manager'));
            }
            
            if (!current_user_can('rdm_view_analytics')) {
                throw new Exception(__('Insufficient permissions', 'restaurant-delivery-manager'));
            }
            
            $type = sanitize_text_field($_POST['type'] ?? 'overview');
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            
            $report_data = $this->generate_report($type, $period);
            
            wp_send_json_success($report_data);
            
        } catch (Exception $e) {
            error_log('RestroReach: ' . __METHOD__ . ' failed - ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Generate comprehensive report
     *
     * @since 1.0.0
     * @param string $type Report type
     * @param string $period Period
     * @return array Report data
     */
    private function generate_report(string $type, string $period): array {
        $data = array(
            'type' => $type,
            'period' => $period,
            'generated_at' => current_time('mysql'),
            'summary' => array(),
            'details' => array(),
            'recommendations' => array(),
        );
        
        switch ($type) {
            case 'revenue':
                $revenue_data = $this->get_revenue_analytics($period);
                $data['summary'] = array(
                    'total_revenue' => $revenue_data['total_revenue'],
                    'order_count' => $revenue_data['order_count'],
                    'average_order_value' => $revenue_data['average_order_value'],
                );
                $data['details'] = $revenue_data;
                break;
                
            case 'agents':
                $agent_data = $this->get_agent_performance(0, $period);
                $data['summary'] = $agent_data['summary'];
                $data['details'] = $agent_data['agents'];
                break;
                
            default:
                $data['summary'] = array(
                    'revenue' => $this->get_revenue_analytics($period),
                    'agents' => $this->get_agent_performance(0, $period),
                    'delivery_times' => $this->get_delivery_time_analytics($period),
                );
                break;
        }
        
        return $data;
    }
    
    /**
     * Get refunds for a specific date range
     *
     * @since 1.0.0
     * @param array $date_range Date range array with 'start' and 'end'
     * @return float Total refunds amount
     */
    private function get_refunds_for_period(array $date_range): float {
        if (!class_exists('WooCommerce')) {
            return 0.0;
        }
        
        global $wpdb;
        
        $refund_amount = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(p.post_excerpt), 0)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order_refund'
            AND p.post_date >= %s
            AND p.post_date <= %s
        ", $date_range['start'], $date_range['end']));
        
        return abs(floatval($refund_amount));
    }
    
    /**
     * Get top selling items for a date range
     *
     * @since 1.0.0
     * @param array $date_range Date range array
     * @param int $limit Number of items to return
     * @return array Top selling items
     */
    private function get_top_selling_items(array $date_range, int $limit): array {
        if (!class_exists('WooCommerce')) {
            return array();
        }
        
        global $wpdb;
        
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT 
                oi.order_item_name as name,
                SUM(oim.meta_value) as quantity_sold,
                COUNT(oi.order_item_id) as times_ordered
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oi.order_item_type = 'line_item'
            AND oim.meta_key = '_qty'
            AND p.post_date >= %s
            AND p.post_date <= %s
            AND p.post_status IN ('wc-completed', 'wc-delivered')
            GROUP BY oi.order_item_name
            ORDER BY quantity_sold DESC
            LIMIT %d
        ", $date_range['start'], $date_range['end'], $limit));
        
        return array_map(function($item) {
            return array(
                'name' => $item->name,
                'quantity_sold' => intval($item->quantity_sold),
                'times_ordered' => intval($item->times_ordered)
            );
        }, $items);
    }
    
    /**
     * Calculate agent rating based on delivery performance
     *
     * @since 1.0.0
     * @param int $agent_id Agent user ID
     * @param array $date_range Date range array
     * @return float Agent rating (0-5 scale)
     */
    private function get_agent_rating(int $agent_id, array $date_range): float {
        global $wpdb;
        
        // Get agent delivery stats
        $assignments_table = $this->database->get_table_name('order_assignments');
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_deliveries,
                AVG(TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at)) as avg_delivery_time,
                SUM(CASE WHEN delivered_at <= pickup_time + INTERVAL 30 MINUTE THEN 1 ELSE 0 END) as on_time_deliveries
            FROM {$assignments_table}
            WHERE agent_id = %d
            AND delivered_at IS NOT NULL
            AND assigned_at >= %s
            AND assigned_at <= %s
        ", $agent_id, $date_range['start'], $date_range['end']));
        
        if (!$stats || $stats->total_deliveries == 0) {
            return 0.0;
        }
        
        // Calculate rating based on:
        // - On-time delivery rate (40%)
        // - Average delivery speed (30%)
        // - Total deliveries completed (30%)
        
        $on_time_rate = $stats->on_time_deliveries / $stats->total_deliveries;
        $speed_score = max(0, (60 - $stats->avg_delivery_time) / 60); // Better score for faster delivery
        $volume_score = min(1, $stats->total_deliveries / 50); // Max score at 50+ deliveries
        
        $rating = ($on_time_rate * 0.4) + ($speed_score * 0.3) + ($volume_score * 0.3);
        
        return round($rating * 5, 1); // Convert to 5-star scale
    }
    
    /**
     * Calculate agent earnings for a date range
     *
     * @since 1.0.0
     * @param int $agent_id Agent user ID
     * @param array $date_range Date range array
     * @return float Total earnings
     */
    private function get_agent_earnings(int $agent_id, array $date_range): float {
        // For now, return 0 as earnings calculation requires business rules
        // This could be extended to include:
        // - Base delivery fee per order
        // - Distance-based bonuses
        // - Time-based multipliers
        // - Performance bonuses
        
        return 0.0;
    }
    
    /**
     * Get count of repeat customers for a date range
     *
     * @since 1.0.0
     * @param array $date_range Date range array
     * @return int Number of repeat customers
     */
    private function get_repeat_customers_count(array $date_range): int {
        if (!class_exists('WooCommerce')) {
            return 0;
        }
        
        // Check if HPOS is enabled - if so, use WooCommerce order query API
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
            method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS is enabled - use WooCommerce API instead of direct queries
            return $this->get_repeat_customers_count_hpos($date_range);
        }
        
        global $wpdb;
        
        $repeat_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM (
                SELECT 
                    CASE 
                        WHEN p.meta_value != '' THEN p.meta_value 
                        ELSE CONCAT(b.meta_value, '_', e.meta_value) 
                    END as customer_id,
                    COUNT(*) as order_count
                FROM {$wpdb->posts} posts
                LEFT JOIN {$wpdb->postmeta} p ON posts.ID = p.post_id AND p.meta_key = '_customer_user'
                LEFT JOIN {$wpdb->postmeta} b ON posts.ID = b.post_id AND b.meta_key = '_billing_email'
                LEFT JOIN {$wpdb->postmeta} e ON posts.ID = e.post_id AND e.meta_key = '_billing_phone'
                WHERE posts.post_type = 'shop_order'
                AND posts.post_status IN ('wc-completed', 'wc-delivered')
                AND posts.post_date >= %s
                AND posts.post_date <= %s
                GROUP BY customer_id
                HAVING order_count > 1
            ) repeat_customers
        ", $date_range['start'], $date_range['end']));
        
        return intval($repeat_customers);
    }
    
    /**
     * Get repeat customers count using HPOS-compatible queries
     *
     * @param array $date_range Date range for analysis
     * @return int Number of repeat customers
     */
    private function get_repeat_customers_count_hpos(array $date_range): int {
        // For HPOS compatibility, use WooCommerce order query API instead of direct database queries
        // This is a safer approach when custom order tables are enabled
        
        $orders = wc_get_orders(array(
            'status' => array('wc-completed', 'wc-delivered'),
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1, // Get all orders
            'return' => 'ids'
        ));
        
        if (empty($orders)) {
            return 0;
        }
        
        $customer_orders = array();
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            // Get customer identifier (user ID or email)
            $customer_id = $order->get_customer_id();
            if (empty($customer_id)) {
                $customer_id = $order->get_billing_email();
                if (empty($customer_id)) {
                    continue; // Skip orders without customer info
                }
            }
            
            if (!isset($customer_orders[$customer_id])) {
                $customer_orders[$customer_id] = 0;
            }
            $customer_orders[$customer_id]++;
        }
        
        // Count customers with more than one order
        $repeat_customers = 0;
        foreach ($customer_orders as $order_count) {
            if ($order_count > 1) {
                $repeat_customers++;
            }
        }
        
        return $repeat_customers;
    }
    
    /**
     * Schedule automated reports
     *
     * @since 1.0.0
     * @return void
     */
    public function schedule_reports(): void {
        // Schedule daily report
        if (!wp_next_scheduled('rdm_daily_analytics_report')) {
            wp_schedule_event(strtotime('tomorrow 8:00 AM'), 'daily', 'rdm_daily_analytics_report');
        }
        
        // Schedule weekly report (Mondays)
        if (!wp_next_scheduled('rdm_weekly_analytics_report')) {
            wp_schedule_event(strtotime('next Monday 8:00 AM'), 'weekly', 'rdm_weekly_analytics_report');
        }
        
        // Schedule monthly report (1st of month)
        if (!wp_next_scheduled('rdm_monthly_analytics_report')) {
            wp_schedule_event(strtotime('first day of next month 8:00 AM'), 'monthly', 'rdm_monthly_analytics_report');
        }
    }
    
    /**
     * Send daily analytics report
     * 
     * Generates and emails a daily summary of delivery metrics to management.
     * Includes order counts, revenue, agent performance, and delivery times.
     *
     * @since 1.0.0
     * @return void
     */
    public function send_daily_report(): void {
        try {
            $report_data = $this->generate_report('overview', 'today');
            
            // Get recipient email addresses from settings
            $recipients = get_option('rdm_daily_report_recipients', get_option('admin_email'));
            $recipients = is_array($recipients) ? $recipients : array($recipients);
            
            $subject = sprintf(
                __('[%s] Daily Delivery Report - %s', 'restaurant-delivery-manager'),
                get_bloginfo('name'),
                date('Y-m-d')
            );
            
            $message = $this->format_email_report($report_data, 'daily');
            
            foreach ($recipients as $email) {
                wp_mail($email, $subject, $message);
            }
            
            error_log('RestroReach: Daily analytics report sent successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: Daily report failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Send weekly analytics report
     * 
     * Generates and emails a comprehensive weekly summary including trends,
     * agent rankings, customer satisfaction metrics, and performance insights.
     *
     * @since 1.0.0
     * @return void
     */
    public function send_weekly_report(): void {
        try {
            $report_data = $this->generate_report('overview', 'week');
            
            $recipients = get_option('rdm_weekly_report_recipients', get_option('admin_email'));
            $recipients = is_array($recipients) ? $recipients : array($recipients);
            
            $subject = sprintf(
                __('[%s] Weekly Delivery Report - Week of %s', 'restaurant-delivery-manager'),
                get_bloginfo('name'),
                date('Y-m-d', strtotime('monday this week'))
            );
            
            $message = $this->format_email_report($report_data, 'weekly');
            
            foreach ($recipients as $email) {
                wp_mail($email, $subject, $message);
            }
            
            error_log('RestroReach: Weekly analytics report sent successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: Weekly report failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Send monthly analytics report
     * 
     * Generates and emails a detailed monthly business intelligence report
     * with strategic insights, growth metrics, and operational recommendations.
     *
     * @since 1.0.0
     * @return void
     */
    public function send_monthly_report(): void {
        try {
            $report_data = $this->generate_report('overview', 'month');
            
            $recipients = get_option('rdm_monthly_report_recipients', get_option('admin_email'));
            $recipients = is_array($recipients) ? $recipients : array($recipients);
            
            $subject = sprintf(
                __('[%s] Monthly Delivery Report - %s', 'restaurant-delivery-manager'),
                get_bloginfo('name'),
                date('F Y', strtotime('first day of this month'))
            );
            
            $message = $this->format_email_report($report_data, 'monthly');
            
            foreach ($recipients as $email) {
                wp_mail($email, $subject, $message);
            }
            
            error_log('RestroReach: Monthly analytics report sent successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: Monthly report failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Format report data for email delivery
     *
     * @since 1.0.0
     * @param array $report_data Report data from generate_report()
     * @param string $frequency Report frequency (daily/weekly/monthly)
     * @return string Formatted HTML email content
     */
    private function format_email_report(array $report_data, string $frequency): string {
        $html = '<html><body style="font-family: Arial, sans-serif;">';
        $html .= '<h2>' . sprintf(__('%s Delivery Report', 'restaurant-delivery-manager'), ucfirst($frequency)) . '</h2>';
        
        if (isset($report_data['summary']['revenue'])) {
            $revenue = $report_data['summary']['revenue'];
            $html .= '<h3>' . __('Revenue Summary', 'restaurant-delivery-manager') . '</h3>';
            $html .= '<ul>';
            $html .= '<li>' . sprintf(__('Total Revenue: %s', 'restaurant-delivery-manager'), wc_price($revenue['total_revenue'])) . '</li>';
            $html .= '<li>' . sprintf(__('Order Count: %d', 'restaurant-delivery-manager'), $revenue['order_count']) . '</li>';
            $html .= '<li>' . sprintf(__('Average Order Value: %s', 'restaurant-delivery-manager'), wc_price($revenue['average_order_value'])) . '</li>';
            $html .= '</ul>';
        }
        
        if (isset($report_data['summary']['agents'])) {
            $agents = $report_data['summary']['agents'];
            $html .= '<h3>' . __('Agent Performance', 'restaurant-delivery-manager') . '</h3>';
            $html .= '<ul>';
            $html .= '<li>' . sprintf(__('Total Deliveries: %d', 'restaurant-delivery-manager'), $agents['total_deliveries']) . '</li>';
            $html .= '<li>' . sprintf(__('Average Delivery Time: %.1f minutes', 'restaurant-delivery-manager'), $agents['avg_delivery_time']) . '</li>';
            $html .= '<li>' . sprintf(__('On-Time Rate: %.1f%%', 'restaurant-delivery-manager'), $agents['on_time_percentage']) . '</li>';
            $html .= '</ul>';
        }
        
        $html .= '<p><small>' . __('Generated by RestroReach Delivery Management System', 'restaurant-delivery-manager') . '</small></p>';
        $html .= '</body></html>';
        
        return $html;
    }
} 