<?php
/**
 * Google Maps Integration Class
 *
 * Handles Google Maps JavaScript API integration for RestroReach delivery tracking
 * with secure API key management and optimized script loading
 *
 * @package    RestaurantDeliveryManager
 * @subpackage Includes
 * @since      1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class RDM_Google_Maps
 * 
 * Manages Google Maps API integration with cost-optimized approach,
 * secure API key handling, and conditional script loading.
 */
class RDM_Google_Maps {

    /**
     * Single instance of the class
     *
     * @var RDM_Google_Maps|null
     */
    private static ?RDM_Google_Maps $instance = null;

    /**
     * Google Maps API key
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * Whether Google Maps is enabled
     *
     * @var bool
     */
    private bool $maps_enabled = false;

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_instance();
    }

    /**
     * Main Google Maps Instance
     *
     * @return RDM_Google_Maps
     */
    public static function instance(): RDM_Google_Maps {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Alias for backward compatibility
     *
     * @return RDM_Google_Maps
     */
    public static function get_instance(): RDM_Google_Maps {
        return self::instance();
    }

    /**
     * Initialize the Google Maps integration
     *
     * @since 1.0.0
     * @return void
     */
    public static function init(): void {
        $instance = self::instance();
        
        // Hook into WordPress actions
        add_action('wp_enqueue_scripts', array($instance, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($instance, 'enqueue_scripts'));
        
        // Add script attributes for async/defer loading
        add_filter('script_loader_tag', array($instance, 'add_async_defer_attributes'), 10, 3);
    }

    /**
     * Get the saved Google Maps API key
     *
     * @since 1.0.0
     * @return string|null The API key or null if not set
     */
    public static function get_api_key(): ?string {
        $options = get_option('rdm_plugin_options', array());
        $api_key = isset($options['rdm_google_maps_api_key']) ? sanitize_text_field($options['rdm_google_maps_api_key']) : '';
        
        return !empty($api_key) ? $api_key : null;
    }

    /**
     * Check if Google Maps is properly configured and enabled
     *
     * @since 1.0.0
     * @return bool True if Google Maps is enabled with valid API key
     */
    public static function is_enabled(): bool {
        return !empty(self::get_api_key());
    }

    /**
     * Check if API key is configured (standardized check for the entire plugin)
     *
     * @since 1.0.0
     * @return bool True if API key is configured
     */
    public static function is_api_configured(): bool {
        $options = get_option('rdm_plugin_options', array());
        return !empty($options['rdm_google_maps_api_key']);
    }

    /**
     * Get API key validation status
     *
     * @since 1.0.0
     * @return array Status array with 'configured', 'valid', and 'message' keys
     */
    public static function get_api_status(): array {
        $options = get_option('rdm_plugin_options', array());
        $api_key = isset($options['rdm_google_maps_api_key']) ? $options['rdm_google_maps_api_key'] : '';
        
        if (empty($api_key)) {
            return array(
                'configured' => false,
                'valid' => false,
                'message' => __('Google Maps API key is not configured', 'restaurant-delivery-manager')
            );
        }
        
        // Check format
        if (!self::validate_api_key_format($api_key)) {
            return array(
                'configured' => true,
                'valid' => false,
                'message' => __('Google Maps API key format appears to be invalid', 'restaurant-delivery-manager')
            );
        }
        
        return array(
            'configured' => true,
            'valid' => true,
            'message' => __('Google Maps API key is configured and format is valid', 'restaurant-delivery-manager')
        );
    }

    /**
     * Initialize the maps integration instance
     *
     * @return void
     */
    private function init_instance(): void {
        // Get API key from settings
        $this->api_key = self::get_api_key() ?? '';
        $this->maps_enabled = !empty($this->api_key);

        // AJAX handlers for map functionality
        add_action('wp_ajax_rdm_get_directions', array($this, 'handle_get_directions'));
        add_action('wp_ajax_nopriv_rdm_get_directions', array($this, 'handle_get_directions'));
        add_action('wp_ajax_rdm_geocode_address', array($this, 'handle_geocode_address'));
        add_action('wp_ajax_nopriv_rdm_geocode_address', array($this, 'handle_geocode_address'));
        add_action('wp_ajax_rdm_get_agent_locations', array($this, 'handle_get_agent_locations'));
        add_action('wp_ajax_nopriv_rdm_get_agent_locations', array($this, 'handle_get_agent_locations'));
        add_action('wp_ajax_rdm_calculate_distance', array($this, 'handle_calculate_distance'));
        
        // Additional AJAX handlers for order tracking and analytics
        add_action('wp_ajax_rdm_get_order_status', array($this, 'handle_get_order_status'));
        add_action('wp_ajax_nopriv_rdm_get_order_status', array($this, 'handle_get_order_status'));
        add_action('wp_ajax_rdm_get_active_orders_map', array($this, 'handle_get_active_orders_map'));
        add_action('wp_ajax_rdm_get_delivery_analytics', array($this, 'handle_get_delivery_analytics'));
        add_action('wp_ajax_rdm_validate_api_key', array($this, 'handle_validate_api_key'));

        // Shortcode for customer tracking
        add_shortcode('rdm_order_tracking_map', array($this, 'render_order_tracking_shortcode'));
    }

    /**
     * Enqueue Google Maps scripts and styles with conditional loading
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_scripts(): void {
        if (!$this->maps_enabled) {
            // Log notice for administrators if API key is missing
            if (current_user_can('manage_options')) {
                error_log('RestroReach: Google Maps API key not configured. Please set the API key in plugin settings.');
            }
            return;
        }

        // Only load on specific pages that need maps
        if (!$this->should_load_maps()) {
            return;
        }

        // Different handling for admin vs frontend
        if (is_admin()) {
            $this->enqueue_admin_scripts();
        } else {
            $this->enqueue_frontend_scripts();
        }
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_frontend_scripts(): void {
        // Enqueue the Google Maps JavaScript API with optimized settings
        wp_enqueue_script(
            'rdm-google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($this->api_key) . '&libraries=places,geometry,directions&callback=rdmInitMap&v=weekly',
            array(),
            null, // Use Google's versioning
            true
        );

        // Enqueue main maps JavaScript
        wp_enqueue_script(
            'rdm-google-maps',
            RDM_PLUGIN_URL . 'assets/js/rdm-google-maps.js',
            array('jquery'),
            RDM_VERSION,
            true
        );

        // Enqueue maps CSS
        wp_enqueue_style(
            'rdm-google-maps',
            RDM_PLUGIN_URL . 'assets/css/rdm-google-maps.css',
            array(),
            RDM_VERSION
        );

        // Localize script with settings and AJAX URL
        wp_localize_script('rdm-google-maps', 'rdmMapsConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_maps_nonce'),
            'apiKey' => $this->api_key,
            'mapDefaults' => array(
                'zoom' => 13,
                'center' => array(
                    'lat' => 40.7128, // Default to NYC
                    'lng' => -74.0060
                ),
                'mapTypeId' => 'roadmap'
            ),
            'strings' => array(
                'locationNotFound' => __('Location not found', 'restaurant-delivery-manager'),
                'routeError' => __('Could not calculate route', 'restaurant-delivery-manager'),
                'loadingRoute' => __('Calculating route...', 'restaurant-delivery-manager'),
                'agentOffline' => __('Agent is offline', 'restaurant-delivery-manager'),
                'eta' => __('ETA:', 'restaurant-delivery-manager'),
                'distance' => __('Distance:', 'restaurant-delivery-manager'),
            ),
        ));
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_admin_scripts(): void {
        // Enqueue the Google Maps JavaScript API for admin
        wp_enqueue_script(
            'rdm-google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($this->api_key) . '&libraries=places,geometry,directions&callback=rdmInitAdminMaps&v=weekly',
            array(),
            null,
            true
        );

        // Enqueue admin maps JavaScript
        wp_enqueue_script(
            'rdm-admin-maps',
            RDM_PLUGIN_URL . 'assets/js/rdm-admin-maps.js',
            array('jquery'),
            RDM_VERSION,
            true
        );

        // Enqueue maps CSS
        wp_enqueue_style(
            'rdm-google-maps',
            RDM_PLUGIN_URL . 'assets/css/rdm-google-maps.css',
            array(),
            RDM_VERSION
        );

        // Localize script for admin
        wp_localize_script('rdm-admin-maps', 'rdmAdminMapsConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm_maps_nonce'),
            'apiKey' => $this->api_key,
            'isAdmin' => true,
            'mapDefaults' => array(
                'zoom' => 12,
                'center' => array(
                    'lat' => 40.7128,
                    'lng' => -74.0060
                ),
                'mapTypeId' => 'roadmap'
            ),
            'strings' => array(
                'locationNotFound' => __('Location not found', 'restaurant-delivery-manager'),
                'routeError' => __('Could not calculate route', 'restaurant-delivery-manager'),
                'loadingRoute' => __('Calculating route...', 'restaurant-delivery-manager'),
                'agentOffline' => __('Agent is offline', 'restaurant-delivery-manager'),
                'eta' => __('ETA:', 'restaurant-delivery-manager'),
                'distance' => __('Distance:', 'restaurant-delivery-manager'),
            ),
        ));
    }

    /**
     * Enqueue admin maps script specifically for agent live view
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_admin_maps_script(): void {
        if (!$this->maps_enabled) {
            if (current_user_can('manage_options')) {
                error_log('RestroReach: Google Maps API key not configured. Please set the API key in plugin settings.');
            }
            return;
        }

        // Enqueue the Google Maps JavaScript API for admin
        wp_enqueue_script(
            'rdm-google-maps-api',
            'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($this->api_key) . '&libraries=places,geometry,directions&callback=rdmInitAdminMaps&v=weekly',
            array(),
            null,
            true
        );

        // Enqueue maps CSS
        wp_enqueue_style(
            'rdm-google-maps',
            RDM_PLUGIN_URL . 'assets/css/rdm-google-maps.css',
            array(),
            RDM_VERSION
        );
    }

    /**
     * Add async and defer attributes to Google Maps API script for better performance
     *
     * @since 1.0.0
     * @param string $tag The script tag
     * @param string $handle The script handle
     * @param string $src The script source URL
     * @return string Modified script tag
     */
    public function add_async_defer_attributes(string $tag, string $handle, string $src): string {
        if ('rdm-google-maps-api' === $handle) {
            // Add async and defer attributes to Google Maps API script
            $tag = str_replace(' src', ' async defer src', $tag);
        }
        return $tag;
    }

    /**
     * Determine if maps should be loaded on current page
     *
     * @since 1.0.0
     * @return bool True if maps should be loaded
     */
    private function should_load_maps(): bool {
        // Admin: Load on specific admin pages
        if (is_admin()) {
            $screen = get_current_screen();
            if (!$screen) {
                return false;
            }
            
            // Load on RestroReach admin pages that use maps
            $map_pages = array(
                'restaurant-delivery-manager_page_rdm-dashboard',
                'restaurant-delivery-manager_page_rdm-orders',
                'restaurant-delivery-manager_page_rdm-agents',
                'restaurant-delivery-manager_page_rdm-analytics',
                'restaurant-delivery-manager_page_rdm-settings',
                'toplevel_page_rdm-dashboard',
                'admin_page_rdm-orders',
                'admin_page_rdm-agents',
                'admin_page_rdm-analytics',
            );
            
            return in_array($screen->id, $map_pages, true);
        }
        
        // Frontend: Load on specific pages/conditions
        global $post;
        
        // Load on order tracking pages
        if (is_page() && $post) {
            // Check if page contains order tracking shortcode
            if (has_shortcode($post->post_content, 'rdm_order_tracking_map')) {
                return true;
            }
            
            // Check if it's a WooCommerce account/order tracking page
            if (function_exists('is_wc_endpoint_url')) {
                if (is_wc_endpoint_url('view-order') || is_wc_endpoint_url('track-your-order')) {
                    return true;
                }
            }
        }
        
        // Load on mobile agent pages (custom endpoint or page)
        if (is_page('delivery-agent') || isset($_GET['rdm_agent_dashboard'])) {
            return true;
        }
        
        // Load if there's a tracking parameter in URL
        if (isset($_GET['track_order']) || isset($_GET['rdm_tracking'])) {
            return true;
        }
        
        // Allow filtering for custom implementations
        return apply_filters('rdm_should_load_maps', false);
    }

    /**
     * Handle AJAX request for directions
     *
     * @return void
     */
    public function handle_get_directions(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        $origin = sanitize_text_field($_POST['origin']);
        $destination = sanitize_text_field($_POST['destination']);

        if (empty($origin) || empty($destination)) {
            wp_send_json_error(__('Origin and destination are required', 'restaurant-delivery-manager'));
        }

        // Use Directions API via server-side call
        $directions = $this->get_directions($origin, $destination);

        if ($directions) {
            wp_send_json_success($directions);
        } else {
            wp_send_json_error(__('Could not get directions', 'restaurant-delivery-manager'));
        }
    }

    /**
     * Handle AJAX request for geocoding
     *
     * @return void
     */
    public function handle_geocode_address(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        $address = sanitize_text_field($_POST['address']);

        if (empty($address)) {
            wp_send_json_error(__('Address is required', 'restaurant-delivery-manager'));
        }

        $coordinates = $this->geocode_address($address);

        if ($coordinates) {
            wp_send_json_success($coordinates);
        } else {
            wp_send_json_error(__('Could not geocode address', 'restaurant-delivery-manager'));
        }
    }

    /**
     * Handle AJAX request for agent locations
     *
     * @return void
     */
    public function handle_get_agent_locations(): void {
        // Verify nonce for non-logged in users (customer tracking)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if ($order_id && !is_user_logged_in()) {
            // For non-logged in users, verify order tracking access
            $tracking_key = sanitize_text_field($_POST['tracking_key'] ?? '');
            if (!$this->verify_tracking_access($order_id, $tracking_key)) {
                wp_send_json_error(__('Invalid tracking access', 'restaurant-delivery-manager'));
            }
        }

        $locations = $this->get_agent_locations($order_id);
        wp_send_json_success($locations);
    }

    /**
     * Handle distance calculation AJAX request
     *
     * @return void
     */
    public function handle_calculate_distance(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        $origin_lat = floatval($_POST['origin_lat']);
        $origin_lng = floatval($_POST['origin_lng']);
        $dest_lat = floatval($_POST['dest_lat']);
        $dest_lng = floatval($_POST['dest_lng']);

        $distance = $this->calculate_distance($origin_lat, $origin_lng, $dest_lat, $dest_lng);

        wp_send_json_success(array(
            'distance_km' => $distance,
            'distance_miles' => $distance * 0.621371
        ));
    }

    /**
     * Get directions using Google Directions API
     *
     * @param string $origin Origin address or coordinates
     * @param string $destination Destination address or coordinates
     * @return array|false Directions data or false on failure
     */
    private function get_directions(string $origin, string $destination) {
        $url = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query(array(
            'origin' => $origin,
            'destination' => $destination,
            'key' => $this->api_key,
            'mode' => 'driving',
            'units' => 'metric'
        ));

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            error_log('RestroReach: Google Directions API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] !== 'OK') {
            error_log('RestroReach: Google Directions API status: ' . $data['status']);
            return false;
        }

        return $data;
    }

    /**
     * Geocode an address using Google Geocoding API (enhanced error handling)
     *
     * @param string $address Address to geocode
     * @param bool $use_cache Whether to use cache
     * @param int $retry_count Number of retries
     * @return array|false Coordinates or false on failure
     */
    public function geocode_address(string $address, bool $use_cache = true, int $retry_count = 3) {
        if (empty($address)) {
            error_log('RestroReach: Empty address provided for geocoding');
            return false;
        }
        
        $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
        $context = ['source' => 'rdm-google-maps'];
        
        // Validate API key first
        if (empty($this->api_key)) {
            $error_msg = 'Cannot geocode - Google Maps API key not configured';
            if ($logger) {
                $logger->error($error_msg, $context);
            } else {
                error_log('RestroReach: ' . $error_msg);
            }
            return $this->geocode_fallback($address);
        }
        
        if ($logger) {
            $logger->debug('Geocoding address: ' . $address, $context);
        }
        
        // Check cache first
        $cache_key = 'rdm_geocode_' . md5($address);
        if ($use_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if ($logger) {
                    $logger->debug('Using cached geocoding result for: ' . $address, $context);
                }
                return $cached;
            }
        }
        
        // Check rate limiting
        $rate_limit_key = 'rdm_geocode_rate_limit';
        $current_calls = get_transient($rate_limit_key) ?: 0;
        if ($current_calls > 40) { // Limit to 40 calls per minute
            $error_msg = 'Rate limit exceeded for geocoding API calls';
            if ($logger) {
                $logger->warning($error_msg, $context);
            }
            // Use cached fallback or basic coordinates
            return $this->geocode_fallback($address);
        }
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(array(
            'address' => $address,
            'key' => $this->api_key
        ));
        
        for ($attempt = 1; $attempt <= $retry_count; $attempt++) {
            if ($logger) {
                $logger->debug("Making geocoding API request (attempt $attempt/$retry_count)", $context);
            }
            
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'user-agent' => 'RestroReach/' . RDM_VERSION . ' (WordPress Plugin)',
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            // Increment rate limit counter
            set_transient($rate_limit_key, $current_calls + 1, 60);
            
            if (is_wp_error($response)) {
                $error_msg = 'Google Geocoding API error: ' . $response->get_error_message();
                if ($logger) {
                    $logger->error($error_msg, $context);
                } else {
                    error_log('RestroReach: ' . $error_msg);
                }
                
                // Don't retry on network errors
                if ($attempt === $retry_count) {
                    return $this->geocode_fallback($address);
                }
                
                // Wait before retry
                sleep(1);
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_msg = "Google Geocoding API returned status code: $response_code";
                if ($logger) {
                    $logger->warning($error_msg, $context);
                }
                
                if ($response_code === 429) { // Too Many Requests
                    // Implement exponential backoff
                    sleep(pow(2, $attempt - 1));
                    continue;
                } elseif ($response_code >= 500) { // Server errors
                    if ($attempt < $retry_count) {
                        sleep(2);
                        continue;
                    }
                }
                
                return $this->geocode_fallback($address);
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data) {
                $error_msg = 'Invalid JSON response from Google Geocoding API';
                if ($logger) {
                    $logger->error($error_msg, $context);
                }
                
                if ($attempt < $retry_count) {
                    sleep(1);
                    continue;
                }
                
                return $this->geocode_fallback($address);
            }
            
            // Check API response status
            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $result = array(
                    'lat' => $data['results'][0]['geometry']['location']['lat'],
                    'lng' => $data['results'][0]['geometry']['location']['lng'],
                    'formatted_address' => $data['results'][0]['formatted_address']
                );
                
                if ($logger) {
                    $logger->debug('Successfully geocoded: ' . $result['formatted_address'], $context);
                }
                
                // Cache successful result for 24 hours
                if ($use_cache) {
                    set_transient($cache_key, $result, DAY_IN_SECONDS);
                }
                
                return $result;
            } elseif ($data['status'] === 'OVER_QUERY_LIMIT') {
                if ($logger) {
                    $logger->error('Google API quota exceeded', $context);
                }
                // Use fallback immediately
                return $this->geocode_fallback($address);
            } elseif ($data['status'] === 'REQUEST_DENIED') {
                if ($logger) {
                    $logger->error('Google API request denied - check API key and permissions', $context);
                }
                return $this->geocode_fallback($address);
            } elseif ($data['status'] === 'INVALID_REQUEST') {
                if ($logger) {
                    $logger->error('Invalid request to Google Geocoding API for address: ' . $address, $context);
                }
                return false; // Don't retry for invalid requests
            } else {
                // Handle other status codes (ZERO_RESULTS, etc.)
                if ($logger) {
                    $logger->warning('Google Geocoding API status: ' . $data['status'] . ' for address: ' . $address, $context);
                }
                
                if ($attempt < $retry_count && in_array($data['status'], ['UNKNOWN_ERROR'])) {
                    sleep(2);
                    continue;
                }
                
                // Cache failed result for 1 hour to prevent repeated requests
                if ($use_cache) {
                    set_transient($cache_key, false, HOUR_IN_SECONDS);
                }
                
                return false;
            }
        }
        
        // All retries exhausted
        return $this->geocode_fallback($address);
    }
    
    /**
     * Fallback geocoding method when Google API fails
     *
     * @param string $address Address to geocode
     * @return array|false Basic coordinates or false
     */
    private function geocode_fallback(string $address) {
        // Try to extract coordinates from cached data or use defaults
        $fallback_coordinates = get_option('rdm_fallback_coordinates', array());
        
        // Look for similar addresses in cache
        $cache_pattern = 'rdm_geocode_*';
        $all_transients = wp_load_alloptions();
        
        foreach ($all_transients as $key => $value) {
            if (strpos($key, '_transient_rdm_geocode_') === 0 && $value !== false) {
                $cached_data = maybe_unserialize($value);
                if (is_array($cached_data) && isset($cached_data['formatted_address'])) {
                    // Simple similarity check
                    if (stripos($cached_data['formatted_address'], substr($address, 0, 20)) !== false) {
                        error_log('RestroReach: Using similar cached address for fallback: ' . $cached_data['formatted_address']);
                        return $cached_data;
                    }
                }
            }
        }
        
        // Use stored restaurant coordinates as last resort
        $restaurant_coords = get_option('rdm_restaurant_coordinates');
        if ($restaurant_coords && is_array($restaurant_coords)) {
            error_log('RestroReach: Using restaurant coordinates as fallback');
            return array(
                'lat' => $restaurant_coords['lat'],
                'lng' => $restaurant_coords['lng'],
                'formatted_address' => $address
            );
        }
        
        // Ultimate fallback: default coordinates (can be configured)
        $default_lat = get_option('rdm_default_latitude', 40.7128); // NYC
        $default_lng = get_option('rdm_default_longitude', -74.0060);
        
        error_log('RestroReach: Using default coordinates as final fallback');
        return array(
            'lat' => floatval($default_lat),
            'lng' => floatval($default_lng),
            'formatted_address' => $address
        );
    }

    /**
     * Static geocode method for use by other classes
     *
     * @param string $address Address to geocode
     * @return array|false Coordinates or false on failure
     */
    public static function geocode_address_static(string $address) {
        $instance = self::get_instance();
        return $instance->geocode_address($address);
    }

    /**
     * Get agent locations for tracking
     *
     * @param int $order_id Order ID to get agent location for (0 for all active agents)
     * @return array Agent location data
     */
    private function get_agent_locations(int $order_id = 0): array {
        global $wpdb;

        $locations = array();

        if ($order_id > 0) {
            // Get specific agent for this order
            $agent_id = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} 
                WHERE post_id = %d AND meta_key = '_rdm_assigned_agent'",
                $order_id
            ));

            if ($agent_id) {
                $location = RDM_GPS_Tracking::get_latest_agent_location($agent_id);
                if ($location) {
                    // Get agent user data for name escaping
                    $agent_user = get_userdata($agent_id);
                    $agent_display_name = $agent_user ? $agent_user->display_name : __('Unknown Agent', 'restaurant-delivery-manager');
                    
                    $locations[] = array(
                        'agent_id' => intval($agent_id),
                        'agent_name' => esc_html($agent_display_name),
                        'lat' => floatval($location['latitude']),
                        'lng' => floatval($location['longitude']),
                        'timestamp' => sanitize_text_field($location['timestamp']),
                        'battery_level' => isset($location['battery_level']) ? intval($location['battery_level']) : null
                    );
                }
            }
        } else {
            // Get all active agents
            $agents = get_users(array('role' => 'delivery_agent'));
            foreach ($agents as $agent) {
                $location = RDM_GPS_Tracking::get_latest_agent_location($agent->ID);
                if ($location && (time() - strtotime($location['timestamp'])) < 300) { // Last 5 minutes
                    $agent_display_name = $agent->display_name ?: ($agent->first_name . ' ' . $agent->last_name);
                    $agent_display_name = trim($agent_display_name) ?: __('Unknown Agent', 'restaurant-delivery-manager');
                    
                    $locations[] = array(
                        'agent_id' => intval($agent->ID),
                        'agent_name' => esc_html($agent_display_name),
                        'lat' => floatval($location['latitude']),
                        'lng' => floatval($location['longitude']),
                        'timestamp' => sanitize_text_field($location['timestamp']),
                        'battery_level' => isset($location['battery_level']) ? intval($location['battery_level']) : null
                    );
                }
            }
        }

        return $locations;
    }



    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in kilometers
     */
    private function calculate_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        return RDM_Location_Utilities::calculate_haversine_distance($lat1, $lng1, $lat2, $lng2);
    }

    /**
     * Render order tracking shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_order_tracking_shortcode(array $atts): string {
        if (!$this->maps_enabled) {
            return '<p>' . __('Google Maps is not configured.', 'restaurant-delivery-manager') . '</p>';
        }

        $atts = shortcode_atts(array(
            'order_id' => 0,
            'height' => '400px',
            'width' => '100%',
            'zoom' => 13
        ), $atts);

        $order_id = intval($atts['order_id']);
        if (!$order_id && isset($_GET['order_id'])) {
            $order_id = intval($_GET['order_id']);
        }

        if (!$order_id) {
            return '<p>' . __('No order specified for tracking.', 'restaurant-delivery-manager') . '</p>';
        }

        ob_start();
        ?>
        <div class="rdm-order-tracking-container">
            <div id="rdm-tracking-map" style="height: <?php echo esc_attr($atts['height']); ?>; width: <?php echo esc_attr($atts['width']); ?>;"></div>
            <div class="rdm-tracking-info">
                <div class="rdm-order-status">
                    <span id="rdm-order-status-text"><?php _e('Loading order status...', 'restaurant-delivery-manager'); ?></span>
                </div>
                <div class="rdm-delivery-info">
                    <span id="rdm-delivery-eta"></span>
                    <span id="rdm-delivery-distance"></span>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof rdmInitOrderTracking === 'function') {
                    rdmInitOrderTracking(<?php echo json_encode(array(
                        'orderId' => $order_id,
                        'zoom' => intval($atts['zoom']),
                        'trackingKey' => sanitize_text_field($_GET['tracking_key'] ?? '')
                    )); ?>);
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Check if Google Maps is enabled and configured
     *
     * @return bool
     */
    public function is_maps_enabled(): bool {
        return $this->maps_enabled;
    }



    /**
     * Validate API key format
     *
     * @param string $api_key The API key to validate
     * @return bool True if format is valid
     */
    public static function validate_api_key_format(string $api_key): bool {
        // Google API keys typically start with "AIza" and are 39 characters long
        // or can be other formats for different key types
        if (empty($api_key)) {
            return false;
        }

        // Check basic format - should be alphanumeric with possible hyphens and underscores
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $api_key)) {
            return false;
        }

        // Check minimum length (Google API keys are typically at least 32 characters)
        if (strlen($api_key) < 32) {
            return false;
        }

        return true;
    }

    /**
     * Test API key by making a simple request
     *
     * @param string $api_key Optional API key to test (uses current if not provided)
     * @return array Test result with success status and message
     */
    public function test_api_key(string $api_key = ''): array {
        if (empty($api_key)) {
            $api_key = $this->api_key;
        }

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('No API key provided', 'restaurant-delivery-manager')
            );
        }

        // Test with a simple geocoding request
        $test_address = 'New York, NY, USA';
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(array(
            'address' => $test_address,
            'key' => $api_key
        ));

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Request failed: %s', 'restaurant-delivery-manager'), $response->get_error_message())
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return array(
                'success' => false,
                'message' => __('Invalid response from Google Maps API', 'restaurant-delivery-manager')
            );
        }

        if ($data['status'] === 'OK') {
            return array(
                'success' => true,
                'message' => __('API key is valid and working', 'restaurant-delivery-manager')
            );
        } else {
            $error_message = isset($data['error_message']) ? $data['error_message'] : $data['status'];
            return array(
                'success' => false,
                'message' => sprintf(__('API Error: %s', 'restaurant-delivery-manager'), $error_message)
            );
        }
    }

    /**
     * Handle AJAX request for order status
     *
     * @return void
     */
    public function handle_get_order_status(): void {
        // Verify nonce for logged in users
        if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(__('Order ID is required', 'restaurant-delivery-manager'));
        }

        // For non-logged in users, verify tracking access
        if (!is_user_logged_in()) {
            $tracking_key = sanitize_text_field($_POST['tracking_key'] ?? '');
            if (!$this->verify_tracking_access($order_id, $tracking_key)) {
                wp_send_json_error(__('Invalid tracking access', 'restaurant-delivery-manager'));
            }
        }

        // Get order status
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found', 'restaurant-delivery-manager'));
        }

        $status_data = array(
            'order_id' => $order_id,
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'last_updated' => $order->get_date_modified()->format('Y-m-d H:i:s')
        );

        // Get agent info if assigned
        $agent_id = get_post_meta($order_id, '_rdm_assigned_agent', true);
        if ($agent_id) {
            $agent = get_userdata($agent_id);
            if ($agent) {
                $status_data['agent'] = array(
                    'id' => $agent_id,
                    'name' => $agent->display_name,
                    'phone' => get_user_meta($agent_id, 'billing_phone', true)
                );

                // Get latest location
                $location = RDM_GPS_Tracking::get_latest_agent_location($agent_id);
                if ($location) {
                    $status_data['agent']['location'] = $location;
                }
            }
        }

        wp_send_json_success($status_data);
    }

    /**
     * Handle AJAX request for active orders on map
     *
     * @return void
     */
    public function handle_get_active_orders_map(): void {
        // Security check - admin only
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }

        // Get active orders (out for delivery, preparing, ready)
        $active_statuses = array('wc-processing', 'wc-preparing', 'wc-ready', 'wc-out-for-delivery');
        
        $orders = wc_get_orders(array(
            'status' => $active_statuses,
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $orders_data = array();
        foreach ($orders as $order) {
            $delivery_address = array(
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country()
            );

            $formatted_address = implode(', ', array_filter($delivery_address));
            
            // Try to get stored coordinates first
            $stored_coords = get_post_meta($order->get_id(), '_rdm_delivery_coordinates', true);
            $coordinates = null;
            
            if ($stored_coords && isset($stored_coords['lat']) && isset($stored_coords['lng'])) {
                $coordinates = $stored_coords;
            } else if (!empty($formatted_address)) {
                // Geocode if not stored
                $coordinates = $this->geocode_address($formatted_address);
                if ($coordinates) {
                    update_post_meta($order->get_id(), '_rdm_delivery_coordinates', $coordinates);
                }
            }

            if ($coordinates) {
                $agent_id = get_post_meta($order->get_id(), '_rdm_assigned_agent', true);
                $agent_name = '';
                if ($agent_id) {
                    $agent = get_userdata($agent_id);
                    $agent_name = $agent ? $agent->display_name : '';
                }

                $orders_data[] = array(
                    'id' => $order->get_id(),
                    'status' => $order->get_status(),
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'delivery_address' => $formatted_address,
                    'coordinates' => $coordinates,
                    'agent_id' => $agent_id,
                    'agent_name' => $agent_name,
                    'total' => $order->get_total(),
                    'date_created' => $order->get_date_created()->format('Y-m-d H:i:s')
                );
            }
        }

        wp_send_json_success($orders_data);
    }

    /**
     * Handle AJAX request for delivery analytics
     *
     * @return void
     */
    public function handle_get_delivery_analytics(): void {
        // Security check - admin only
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_maps_nonce')) {
            wp_die(__('Security check failed', 'restaurant-delivery-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }

        global $wpdb;

        // Get delivery analytics data for heat map
        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '30';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$date_range} days"));

        // Get completed orders with delivery coordinates
        $query = $wpdb->prepare(
            "SELECT pm.meta_value as coordinates, COUNT(*) as delivery_count
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_rdm_delivery_coordinates'
             AND p.post_type = 'shop_order'
             AND p.post_status = 'wc-completed'
             AND p.post_date >= %s
             GROUP BY pm.meta_value
             HAVING delivery_count > 0
             ORDER BY delivery_count DESC
             LIMIT 100",
            $start_date
        );

        $results = $wpdb->get_results($query);
        $analytics_data = array();

        foreach ($results as $result) {
            $coordinates = maybe_unserialize($result->coordinates);
            if (is_array($coordinates) && isset($coordinates['lat']) && isset($coordinates['lng'])) {
                $analytics_data[] = array(
                    'lat' => floatval($coordinates['lat']),
                    'lng' => floatval($coordinates['lng']),
                    'weight' => intval($result->delivery_count)
                );
            }
        }

        wp_send_json_success($analytics_data);
    }

    /**
     * Verify tracking access for order
     *
     * @param int $order_id Order ID
     * @param string $tracking_key Tracking key from customer
     * @return bool True if access is valid
     */
    private function verify_tracking_access(int $order_id, string $tracking_key): bool {
        // For now, allow any tracking key that's not empty
        // In production, you might want to implement proper tracking key validation
        if (empty($tracking_key)) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // Verify tracking key matches order
        $order_tracking_key = get_post_meta($order_id, '_rdm_tracking_key', true);
        if (empty($order_tracking_key)) {
            // Generate tracking key if not exists
            $order_tracking_key = wp_generate_password(12, false);
            update_post_meta($order_id, '_rdm_tracking_key', $order_tracking_key);
        }

        return $tracking_key === $order_tracking_key;
    }

    /**
     * Handle AJAX request for API key validation
     *
     * @return void
     */
    public function handle_validate_api_key(): void {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_validate_api_key')) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'restaurant-delivery-manager'));
        }

        // Test the API key
        $test_result = $this->test_api_key($api_key);
        
        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result['message']);
        }
    }

    /**
     * Get restaurant coordinates with geocoding and caching
     *
     * @since 1.0.0
     * @return array|null Restaurant coordinates ['lat' => float, 'lng' => float] or null if not found
     */
    public static function get_restaurant_coordinates(): ?array {
        $logger = wc_get_logger();
        $context = ['source' => 'rdm-google-maps'];
        
        $logger->debug('Getting restaurant coordinates', $context);
        
        // Check cache first
        $cache_key = 'rdm_restaurant_coords';
        $cached_coords = get_transient($cache_key);
        
        if ($cached_coords !== false) {
            if ($cached_coords === null) {
                $logger->debug('Using cached null result for restaurant coordinates', $context);
            } else {
                $logger->debug('Using cached restaurant coordinates: lat=' . $cached_coords['lat'] . ', lng=' . $cached_coords['lng'], $context);
            }
            return $cached_coords;
        }

        // Get restaurant address from settings or WooCommerce
        $wc_settings = get_option('woocommerce_restroreach_delivery_settings', array());
        $restaurant_address = isset($wc_settings['restaurant_address']) ? $wc_settings['restaurant_address'] : '';
        
        $logger->debug('Restaurant address from delivery settings: ' . ($restaurant_address ?: 'empty'), $context);
        
        // Fallback to WooCommerce store address if no custom address set
        if (empty($restaurant_address)) {
            $store_address = array(
                get_option('woocommerce_store_address'),
                get_option('woocommerce_store_address_2'),
                get_option('woocommerce_store_city'),
                get_option('woocommerce_default_country')
            );
            $restaurant_address = implode(', ', array_filter($store_address));
            $logger->debug('Using WooCommerce store address as fallback: ' . $restaurant_address, $context);
        }

        // Final fallback to default address
        if (empty($restaurant_address)) {
            $restaurant_address = 'Default Restaurant Address';
            $logger->warning('No restaurant address configured, using default: ' . $restaurant_address, $context);
        }

        $logger->debug('Final restaurant address for geocoding: ' . $restaurant_address, $context);

        // Geocode the address
        $geocoded = self::geocode_address_static($restaurant_address);
        
        if ($geocoded && isset($geocoded['lat'], $geocoded['lng'])) {
            $coords = array(
                'lat' => floatval($geocoded['lat']),
                'lng' => floatval($geocoded['lng'])
            );
            
            $logger->debug('Successfully geocoded restaurant coordinates: lat=' . $coords['lat'] . ', lng=' . $coords['lng'], $context);
            
            // Cache for 24 hours
            set_transient($cache_key, $coords, DAY_IN_SECONDS);
            
            return $coords;
        }

        $logger->error('Failed to geocode restaurant address: ' . $restaurant_address, $context);

        // Cache null result for 1 hour to prevent repeated failed requests
        set_transient($cache_key, null, HOUR_IN_SECONDS);
        
        return null;
    }

    /**
     * Batch geocode multiple addresses with caching optimization
     *
     * @since 1.0.0
     * @param array $addresses Array of addresses to geocode
     * @param bool $use_cache Whether to use caching (default: true)
     * @param int $batch_size Maximum addresses per batch (default: 10)
     * @return array Associative array of address => coordinates results
     */
    public static function batch_geocode_addresses(array $addresses, bool $use_cache = true, int $batch_size = 10): array {
        $logger = wc_get_logger();
        $context = ['source' => 'rdm-google-maps-batch'];
        
        $results = array();
        $uncached_addresses = array();
        
        // First, check cache for all addresses
        if ($use_cache) {
            foreach ($addresses as $address) {
                $cache_key = 'rdm_geocode_' . md5($address);
                $cached_result = get_transient($cache_key);
                
                if ($cached_result !== false) {
                    $results[$address] = $cached_result;
                    $logger->debug('Using cached geocoding result for: ' . $address, $context);
                } else {
                    $uncached_addresses[] = $address;
                }
            }
        } else {
            $uncached_addresses = $addresses;
        }
        
        // Process uncached addresses in batches
        if (!empty($uncached_addresses)) {
            $batches = array_chunk($uncached_addresses, $batch_size);
            
            foreach ($batches as $batch_index => $batch) {
                $logger->debug('Processing geocoding batch ' . ($batch_index + 1) . ' of ' . count($batches), $context);
                
                foreach ($batch as $address) {
                    $geocoded = self::geocode_address_static($address);
                    $results[$address] = $geocoded;
                    
                    // Cache the result for 24 hours
                    if ($use_cache) {
                        $cache_key = 'rdm_geocode_' . md5($address);
                        set_transient($cache_key, $geocoded, DAY_IN_SECONDS);
                    }
                    
                    // Add small delay between requests to respect API rate limits
                    usleep(100000); // 100ms delay
                }
                
                // Longer delay between batches
                if ($batch_index < count($batches) - 1) {
                    sleep(1); // 1 second delay between batches
                }
            }
        }
        
        $logger->info('Batch geocoding completed: ' . count($addresses) . ' addresses processed', $context);
        
        return $results;
    }

    /**
     * Preload geocoding cache for common delivery areas
     *
     * @since 1.0.0
     * @param array $delivery_areas Array of delivery area addresses
     * @return array Results summary
     */
    public static function preload_delivery_area_cache(array $delivery_areas): array {
        $logger = wc_get_logger();
        $context = ['source' => 'rdm-google-maps-preload'];
        
        $logger->info('Starting delivery area cache preload for ' . count($delivery_areas) . ' areas', $context);
        
        $results = self::batch_geocode_addresses($delivery_areas, true, 5); // Smaller batches for preloading
        
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($results as $address => $result) {
            if ($result && isset($result['lat'], $result['lng'])) {
                $success_count++;
                $logger->debug('Successfully preloaded cache for: ' . $address, $context);
            } else {
                $failed_count++;
                $logger->warning('Failed to preload cache for: ' . $address, $context);
            }
        }
        
        $summary = array(
            'total_processed' => count($delivery_areas),
            'successful' => $success_count,
            'failed' => $failed_count,
            'cache_hits' => count($delivery_areas) - count(array_filter($delivery_areas, function($address) {
                return get_transient('rdm_geocode_' . md5($address)) === false;
            }))
        );
        
        $logger->info('Delivery area cache preload completed', array_merge($context, $summary));
        
        return $summary;
    }

    /**
     * Clear geocoding cache for specific addresses or all
     *
     * @since 1.0.0
     * @param array $addresses Specific addresses to clear, empty array to clear all
     * @return int Number of cache entries cleared
     */
    public static function clear_geocoding_cache(array $addresses = array()): int {
        global $wpdb;
        
        if (empty($addresses)) {
            // Clear all geocoding cache entries
            $deleted = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_rdm_geocode_%' 
                 OR option_name LIKE '_transient_timeout_rdm_geocode_%'"
            );
        } else {
            $deleted = 0;
            foreach ($addresses as $address) {
                $cache_key = 'rdm_geocode_' . md5($address);
                if (delete_transient($cache_key)) {
                    $deleted++;
                }
            }
        }
        
        return intval($deleted / 2); // Divide by 2 because WordPress creates two entries per transient
    }
}
