<?php
/**
 * Customer Order Tracking Class
 *
 * @package RestaurantDeliveryManager
 * @subpackage CustomerTracking
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RDM_Customer_Tracking
 * Handles customer order tracking functionality
 */
class RDM_Customer_Tracking {
    /**
     * Singleton instance
     *
     * @var RDM_Customer_Tracking|null
     */
    private static ?RDM_Customer_Tracking $instance = null;

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return RDM_Customer_Tracking
     */
    public static function get_instance(): RDM_Customer_Tracking {
        if (null === self::$instance) {
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
        $this->init();
    }

    /**
     * Initialize the Customer Tracking feature
     *
     * @since 1.0.0
     * @return void
     */
    public function init(): void {
        // Register shortcode
        add_shortcode('rdm_order_tracking', array($this, 'order_tracking_shortcode'));
        
        // Add AJAX handlers
        add_action('wp_ajax_rdm_get_order_status', array($this, 'handle_get_order_status'));
        add_action('wp_ajax_nopriv_rdm_get_order_status', array($this, 'handle_get_order_status'));
        
        // Add hooks to generate and store tracking keys on checkout
        add_action('woocommerce_checkout_order_processed', array($this, 'generate_order_tracking_key'), 10, 1);
        add_action('woocommerce_email_before_order_table', array($this, 'add_tracking_info_to_emails'), 10, 4);
        
        // Add settings registration
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Gets comprehensive tracking data for a specific order
     *
     * @since 1.0.0
     * @param int    $order_id      The WooCommerce order ID
     * @param string $tracking_key  The unique tracking key for security verification
     * @return array Tracking data array or error array if invalid
     */
    public function get_tracking_data(int $order_id, string $tracking_key): array {
        // 1. Validate the tracking key
        if (!$this->validate_tracking_key($order_id, $tracking_key)) {
            return [
                'error' => true,
                'message' => __('Invalid tracking key', 'restaurant-delivery-manager')
            ];
        }
        
        // 2. Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            return [
                'error' => true,
                'message' => __('Order not found', 'restaurant-delivery-manager')
            ];
        }
        
        // 3. Get order status and details
        $order_data = [
            'id' => $order_id,
            'status' => $order->get_status(),
            'status_name' => wc_get_order_status_name($order->get_status()),
            'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'total' => $order->get_formatted_order_total(),
            'items' => $this->format_order_items($order),
            'estimated_delivery' => $this->calculate_eta($order_id)['eta'] ?? __('Calculating...', 'restaurant-delivery-manager')
        ];
        
        // 4. Get restaurant and customer locations
        $locations = [
            'restaurant' => $this->get_restaurant_location(),
            'customer' => $this->get_customer_location($order)
        ];
        
        // 5. Get delivery agent information and location (if assigned)
        $agent_data = $this->get_agent_data($order_id);
        if ($agent_data) {
            $locations['agent'] = $agent_data;
        }
        
        // 6. Build status timeline
        $status_timeline = $this->build_status_timeline($order);
        
        // 7. Return the complete tracking data
        return [
            'error' => false,
            'order' => $order_data,
            'locations' => $locations,
            'status_timeline' => $status_timeline,
            'tracking_key' => $tracking_key,
            'refresh_interval' => get_option('rdm_tracking_refresh_interval', 30)
        ];
    }

    /**
     * Generates a unique tracking key for an order
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @return string The generated tracking key
     */
    public function generate_tracking_key(int $order_id): string {
        // Generate a unique, secure random key
        $tracking_key = wp_generate_password(16, false, false);
        
        // Store it in order meta
        update_post_meta($order_id, '_rdm_tracking_key', $tracking_key);
        
        return $tracking_key;
    }

    /**
     * Validates a tracking key for an order
     *
     * @since 1.0.0
     * @param int    $order_id     The WooCommerce order ID
     * @param string $tracking_key The tracking key to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_tracking_key(int $order_id, string $tracking_key): bool {
        // Get the stored tracking key
        $stored_key = get_post_meta($order_id, '_rdm_tracking_key', true);
        
        // Compare with provided key (secure comparison)
        return hash_equals($stored_key, $tracking_key);
    }

    /**
     * Calculates the estimated delivery time
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @return array ETA information array with minutes, timestamp and confidence level
     */
    public function calculate_eta(int $order_id): array {
        $order = wc_get_order($order_id);
        if (!$order) {
            return ['eta' => __('Unable to calculate', 'restaurant-delivery-manager')];
        }

        // Get agent and customer locations
        $agent_data = $this->get_agent_data($order_id);
        $customer_location = $this->get_customer_location($order);

        if (!$agent_data || !$customer_location) {
            // Fallback estimate based on order status
            return $this->get_fallback_eta($order);
        }

        // Calculate distance and time using Google Maps if available
        if (class_exists('RDM_Google_Maps')) {
            $google_maps = RDM_Google_Maps::get_instance();
            $route_data = $google_maps->calculate_route(
                $agent_data['lat'],
                $agent_data['lng'],
                $customer_location['lat'],
                $customer_location['lng']
            );

            if ($route_data) {
                return [
                    'eta' => $route_data['duration_text'],
                    'minutes' => $route_data['duration_minutes'],
                    'distance' => $route_data['distance_text'],
                    'confidence' => 'high'
                ];
            }
        }

        // Fallback calculation
        $distance = $this->calculate_distance(
            $agent_data['lat'],
            $agent_data['lng'],
            $customer_location['lat'],
            $customer_location['lng']
        );

        $estimated_minutes = round($distance / 0.5); // Assuming 30 km/h average
        
        return [
            'eta' => sprintf(__('%d minutes', 'restaurant-delivery-manager'), $estimated_minutes),
            'minutes' => $estimated_minutes,
            'distance' => sprintf('%.1f km', $distance),
            'confidence' => 'medium'
        ];
    }

    /**
     * Renders the order tracking interface via shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function order_tracking_shortcode(array $atts): string {
        // Handle attributes with defaults
        $atts = shortcode_atts(
            [
                'order_id' => 0,
                'tracking_key' => '',
            ],
            $atts,
            'rdm_order_tracking'
        );
        
        // Validate and sanitize input
        $order_id = absint($atts['order_id']);
        $tracking_key = sanitize_text_field($atts['tracking_key']);
        
        // Check URL parameters if not provided in shortcode
        if (empty($order_id) && isset($_GET['order_id'])) {
            $order_id = absint($_GET['order_id']);
        }
        
        if (empty($tracking_key) && isset($_GET['tracking_key'])) {
            $tracking_key = sanitize_text_field($_GET['tracking_key']);
        }
        
        // Early return if invalid parameters
        if (empty($order_id) || empty($tracking_key)) {
            return $this->render_tracking_form();
        }
        
        // Get tracking data
        $tracking_data = $this->get_tracking_data($order_id, $tracking_key);
        
        // Handle error case
        if (!empty($tracking_data['error'])) {
            return '<div class="rdm-tracking-error">' . esc_html($tracking_data['message']) . '</div>';
        }
        
        // Enqueue required scripts and styles
        $this->enqueue_tracking_assets();
        
        // Localize script with tracking data
        $tracking_data['nonce'] = wp_create_nonce('rdm_tracking_nonce');
        wp_localize_script('rdm-customer-tracking', 'rdmTrackingData', $tracking_data);
        wp_localize_script('rdm-customer-tracking', 'rdmParams', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_tracking_nonce')
        ]);
        
        // Render the tracking interface using a template
        ob_start();
        include RDM_PLUGIN_DIR . 'templates/customer-tracking.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for getting updated order status and agent location
     *
     * @since 1.0.0
     * @return void Sends JSON response
     */
    public function handle_get_order_status(): void {
        // Verify nonce
        check_ajax_referer('rdm_tracking_nonce', 'security');
        
        // Get and validate parameters
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $tracking_key = isset($_POST['tracking_key']) ? sanitize_text_field($_POST['tracking_key']) : '';
        
        // Validate tracking key
        if (!$this->validate_tracking_key($order_id, $tracking_key)) {
            wp_send_json_error(['message' => __('Invalid tracking information', 'restaurant-delivery-manager')]);
            return;
        }
        
        // Get updated tracking data
        $tracking_data = $this->get_tracking_data($order_id, $tracking_key);
        
        // Return the data
        if (isset($tracking_data['error'])) {
            wp_send_json_error($tracking_data);
        } else {
            wp_send_json_success($tracking_data);
        }
    }

    /**
     * Generate tracking key when order is processed
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return void
     */
    public function generate_order_tracking_key(int $order_id): void {
        // Check if tracking key already exists
        if (get_post_meta($order_id, '_rdm_tracking_key', true)) {
            return;
        }

        // Generate and store tracking key
        $this->generate_tracking_key($order_id);
    }

    /**
     * Add tracking information to order emails
     *
     * @since 1.0.0
     * @param WC_Order $order Order object
     * @param bool     $sent_to_admin Whether email is sent to admin
     * @param bool     $plain_text Whether email is plain text
     * @param WC_Email $email Email instance
     * @return void
     */
    public function add_tracking_info_to_emails($order, $sent_to_admin, $plain_text, $email): void {
        // Only add to specific customer emails
        if ($email->id !== 'customer_processing_order' && $email->id !== 'customer_completed_order') {
            return;
        }

        $order_id = $order->get_id();
        $tracking_key = get_post_meta($order_id, '_rdm_tracking_key', true);
        
        if (!$tracking_key) {
            return;
        }

        $tracking_url = add_query_arg([
            'order_id' => $order_id,
            'tracking_key' => $tracking_key
        ], get_permalink(get_option('rdm_tracking_page_id')));

        if ($plain_text) {
            echo "\n\n" . __('Track your order:', 'restaurant-delivery-manager') . " " . esc_url($tracking_url) . "\n\n";
        } else {
            echo '<p>' . __('You can track your order delivery using this link:', 'restaurant-delivery-manager') . '</p>';
            echo '<p><a href="' . esc_url($tracking_url) . '" class="button">' . __('Track Your Order', 'restaurant-delivery-manager') . '</a></p>';
        }
    }

    /**
     * Register admin settings for tracking feature
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings(): void {
        // Add settings section
        add_settings_section(
            'rdm_tracking_settings',
            __('Order Tracking Settings', 'restaurant-delivery-manager'),
            array($this, 'render_tracking_settings_description'),
            'rdm_settings'
        );
        
        // Add tracking page field
        add_settings_field(
            'rdm_tracking_page_id',
            __('Tracking Page', 'restaurant-delivery-manager'),
            array($this, 'render_tracking_page_field'),
            'rdm_settings',
            'rdm_tracking_settings'
        );
        
        // Register settings
        register_setting('rdm_settings', 'rdm_tracking_page_id', 'absint');
        register_setting('rdm_tracking_settings', 'rdm_tracking_page_url');
        register_setting('rdm_tracking_settings', 'rdm_tracking_refresh_interval');
        register_setting('rdm_tracking_settings', 'rdm_restaurant_address');
    }

    /**
     * Render tracking settings description
     *
     * @since 1.0.0
     * @return void
     */
    public function render_tracking_settings_description(): void {
        echo '<p>' . esc_html__('Settings for customer order tracking functionality.', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Render tracking page dropdown field
     *
     * @since 1.0.0
     * @return void
     */
    public function render_tracking_page_field(): void {
        $tracking_page_id = get_option('rdm_tracking_page_id');
        
        wp_dropdown_pages(array(
            'name' => 'rdm_tracking_page_id',
            'echo' => true,
            'show_option_none' => __('— Select —', 'restaurant-delivery-manager'),
            'option_none_value' => '0',
            'selected' => $tracking_page_id,
        ));
        
        echo '<p class="description">' . esc_html__('Select the page where customers can track their orders. Make sure to add the [rdm_order_tracking] shortcode to this page.', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Enqueue tracking assets
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_tracking_assets(): void {
        // Enqueue Google Maps API first if available
        if (class_exists('RDM_Google_Maps')) {
            RDM_Google_Maps::get_instance()->enqueue_maps_api();
        }

        // Enqueue CSS
        wp_enqueue_style(
            'rdm-customer-tracking',
            RDM_PLUGIN_URL . 'assets/css/rdm-customer-tracking.css',
            [],
            RDM_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'rdm-customer-tracking',
            RDM_PLUGIN_URL . 'assets/js/rdm-customer-tracking.js',
            ['jquery'],
            RDM_VERSION,
            true
        );

        // Add script parameters
        wp_localize_script(
            'rdm-customer-tracking',
            'rdmParams',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rdm_tracking_nonce')
            )
        );
    }

    /**
     * Render tracking form when no order data provided
     *
     * @since 1.0.0
     * @return string HTML form
     */
    private function render_tracking_form(): string {
        ob_start();
        ?>
        <div class="rdm-tracking-form-container">
            <h3><?php esc_html_e('Track Your Order', 'restaurant-delivery-manager'); ?></h3>
            <p><?php esc_html_e('Enter your order details below to track your delivery in real-time.', 'restaurant-delivery-manager'); ?></p>
            
            <form id="rdm-tracking-form" method="get">
                <div class="rdm-form-group">
                    <label for="order_id"><?php esc_html_e('Order Number:', 'restaurant-delivery-manager'); ?></label>
                    <input type="number" id="order_id" name="order_id" required 
                           placeholder="<?php esc_attr_e('e.g., 12345', 'restaurant-delivery-manager'); ?>">
                </div>
                
                <div class="rdm-form-group">
                    <label for="tracking_key"><?php esc_html_e('Tracking Key:', 'restaurant-delivery-manager'); ?></label>
                    <input type="text" id="tracking_key" name="tracking_key" required
                           placeholder="<?php esc_attr_e('Found in your order confirmation email', 'restaurant-delivery-manager'); ?>">
                </div>
                
                <button type="submit" class="rdm-track-button">
                    <?php esc_html_e('Track Order', 'restaurant-delivery-manager'); ?>
                </button>
            </form>
            
            <div class="rdm-tracking-help">
                <p><small><?php esc_html_e('Your tracking key can be found in your order confirmation email.', 'restaurant-delivery-manager'); ?></small></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get restaurant location data
     *
     * @since 1.0.0
     * @return array|null Location data
     */
    private function get_restaurant_location(): ?array {
        // Try to get from cache first
        $location = get_transient('rdm_restaurant_location');
        if ($location !== false) {
            return $location;
        }

        // Get restaurant address from settings
        $address = get_option('rdm_restaurant_address');
        if (!$address) {
            // Fallback to WooCommerce store address
            $address = sprintf('%s, %s, %s %s',
                get_option('woocommerce_store_address', ''),
                get_option('woocommerce_store_city', ''),
                get_option('woocommerce_store_postcode', ''),
                get_option('woocommerce_default_country', '')
            );
        }

        if (!trim($address)) {
            return null;
        }

        // Geocode the address if Google Maps is available
        if (class_exists('RDM_Google_Maps')) {
            $google_maps = RDM_Google_Maps::get_instance();
            $coords = $google_maps->geocode_address($address);
            
            if ($coords) {
                $location = [
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'name' => get_option('blogname'),
                    'address' => $address
                ];
                
                // Cache for 24 hours
                set_transient('rdm_restaurant_location', $location, DAY_IN_SECONDS);
                return $location;
            }
        }

        return null;
    }

    /**
     * Get customer location from order
     *
     * @since 1.0.0
     * @param WC_Order $order Order object
     * @return array|null Location data
     */
    private function get_customer_location(WC_Order $order): ?array {
        $address = sprintf('%s %s, %s, %s %s',
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_postcode(),
            $order->get_shipping_country()
        );

        if (class_exists('RDM_Google_Maps')) {
            $google_maps = RDM_Google_Maps::get_instance();
            $coords = $google_maps->geocode_address($address);
            
            if ($coords) {
                return [
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'address' => $order->get_formatted_shipping_address()
                ];
            }
        }

        return null;
    }

    /**
     * Get agent data if assigned to order
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return array|null Agent data
     */
    private function get_agent_data(int $order_id): ?array {
        global $wpdb;

        // Get agent assignment
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT agent_id, assigned_at FROM {$wpdb->prefix}rdm_order_assignments WHERE order_id = %d AND status IN ('assigned', 'picked_up')",
            $order_id
        ));

        if (!$assignment) {
            return null;
        }

        // Get agent user data
        $agent = get_userdata($assignment->agent_id);
        if (!$agent) {
            return null;
        }

        // Get latest location
        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT latitude, longitude, recorded_at FROM {$wpdb->prefix}rdm_location_tracking 
             WHERE agent_id = %d AND recorded_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY recorded_at DESC LIMIT 1",
            $assignment->agent_id
        ));

        if (!$location) {
            return null;
        }

        return [
            'id' => $assignment->agent_id,
            'name' => $agent->display_name,
            'phone' => get_user_meta($assignment->agent_id, 'billing_phone', true),
            'lat' => floatval($location->latitude),
            'lng' => floatval($location->longitude),
            'last_update' => $location->recorded_at
        ];
    }

    /**
     * Build status timeline for order
     *
     * @since 1.0.0
     * @param WC_Order $order Order object
     * @return array Timeline data
     */
    private function build_status_timeline(WC_Order $order): array {
        $timeline = [
            [
                'status' => 'pending',
                'label' => __('Order Received', 'restaurant-delivery-manager'),
                'completed' => true,
                'time' => $order->get_date_created()->format('H:i')
            ],
            [
                'status' => 'processing',
                'label' => __('Preparing Food', 'restaurant-delivery-manager'),
                'completed' => in_array($order->get_status(), ['processing', 'ready-for-pickup', 'out-for-delivery', 'completed']),
                'time' => $order->get_status() === 'processing' ? current_time('H:i') : ''
            ],
            [
                'status' => 'ready-for-pickup',
                'label' => __('Ready for Pickup', 'restaurant-delivery-manager'),
                'completed' => in_array($order->get_status(), ['ready-for-pickup', 'out-for-delivery', 'completed']),
                'time' => $order->get_status() === 'ready-for-pickup' ? current_time('H:i') : ''
            ],
            [
                'status' => 'out-for-delivery',
                'label' => __('Out for Delivery', 'restaurant-delivery-manager'),
                'completed' => in_array($order->get_status(), ['out-for-delivery', 'completed']),
                'time' => $order->get_status() === 'out-for-delivery' ? current_time('H:i') : ''
            ],
            [
                'status' => 'completed',
                'label' => __('Delivered', 'restaurant-delivery-manager'),
                'completed' => $order->get_status() === 'completed',
                'time' => $order->get_status() === 'completed' ? current_time('H:i') : ''
            ]
        ];

        return $timeline;
    }

    /**
     * Format order items for display
     *
     * @since 1.0.0
     * @param WC_Order $order Order object
     * @return array Formatted items
     */
    private function format_order_items(WC_Order $order): array {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product ? wc_price($product->get_price()) : '',
                'total' => wc_price($item->get_total())
            ];
        }
        
        return $items;
    }

    /**
     * Get fallback ETA when no agent location available
     *
     * @since 1.0.0
     * @param WC_Order $order Order object
     * @return array ETA data
     */
    private function get_fallback_eta(WC_Order $order): array {
        $status = $order->get_status();
        
        switch ($status) {
            case 'pending':
            case 'processing':
                return ['eta' => __('20-30 minutes', 'restaurant-delivery-manager')];
            case 'ready-for-pickup':
                return ['eta' => __('10-15 minutes', 'restaurant-delivery-manager')];
            case 'out-for-delivery':
                return ['eta' => __('5-10 minutes', 'restaurant-delivery-manager')];
            default:
                return ['eta' => __('Delivered', 'restaurant-delivery-manager')];
        }
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @since 1.0.0
     * @param float $lat1 Latitude 1
     * @param float $lng1 Longitude 1
     * @param float $lat2 Latitude 2
     * @param float $lng2 Longitude 2
     * @return float Distance in kilometers
     */
    private function calculate_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth_radius = 6371; // Earth's radius in kilometers

        $lat_delta = deg2rad($lat2 - $lat1);
        $lng_delta = deg2rad($lng2 - $lng1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_delta / 2) * sin($lng_delta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }
}
