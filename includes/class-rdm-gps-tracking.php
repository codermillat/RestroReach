<?php
/**
 * Restaurant Delivery Manager - GPS Tracking Class
 *
 * @package RestaurantDeliveryManager
 * @subpackage GPS
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RDM_GPS_Tracking
 * Handles GPS tracking functionality for delivery agents
 */
class RDM_GPS_Tracking {
    /**
     * Singleton instance
     *
     * @var RDM_GPS_Tracking
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return RDM_GPS_Tracking
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_rdm_update_agent_location', array($this, 'handle_location_update'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Schedule GPS data cleanup on plugin load
        register_activation_hook(__FILE__, function() {
            if (!wp_next_scheduled('rdm_cleanup_location_data')) {
                wp_schedule_event(time(), 'daily', 'rdm_cleanup_location_data');
            }
        });

        register_deactivation_hook(__FILE__, function() {
            $timestamp = wp_next_scheduled('rdm_cleanup_location_data');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'rdm_cleanup_location_data');
            }
        });

        add_action('rdm_cleanup_location_data', function() {
            global $wpdb;
            $table = $wpdb->prefix . 'rr_location_tracking';
            $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE timestamp < %s",
                $cutoff
            ));
        });
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('rdm/v1', '/agent/location', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_location_update'),
            'permission_callback' => array($this, 'check_rest_permission'),
        ));
    }

    /**
     * Check REST API permission
     *
     * @return bool
     */
    public function check_rest_permission() {
        return current_user_can('delivery_agent');
    }

    /**
     * Handle AJAX location update
     */
    public function handle_location_update() {
        // Verify nonce
        if (!check_ajax_referer('rdm_agent_location', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Verify user is a delivery agent
        if (!current_user_can('delivery_agent')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }

        // Get and validate location data
        $location_data = $this->validate_location_data($_POST);
        if (is_wp_error($location_data)) {
            wp_send_json_error(array('message' => $location_data->get_error_message()));
        }

        // Save location data
        $result = $this->save_location_data($location_data);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Location updated successfully'));
    }

    /**
     * Handle REST API location update
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_rest_location_update($request) {
        // Get and validate location data
        $location_data = $this->validate_location_data($request->get_params());
        if (is_wp_error($location_data)) {
            return $location_data;
        }

        // Save location data
        $result = $this->save_location_data($location_data);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(array('message' => 'Location updated successfully'), 200);
    }

    /**
     * Validate location data
     *
     * @param array $data
     * @return array|WP_Error
     */
    private function validate_location_data($data) {
        // Required fields
        $required_fields = array('latitude', 'longitude');
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field])) {
                return new WP_Error('invalid_data', "Missing or invalid {$field}");
            }
        }

        // Validate latitude (-90 to 90)
        if ($data['latitude'] < -90 || $data['latitude'] > 90) {
            return new WP_Error('invalid_latitude', 'Latitude must be between -90 and 90');
        }

        // Validate longitude (-180 to 180)
        if ($data['longitude'] < -180 || $data['longitude'] > 180) {
            return new WP_Error('invalid_longitude', 'Longitude must be between -180 and 180');
        }

        // Validate accuracy if provided
        if (isset($data['accuracy']) && (!is_numeric($data['accuracy']) || $data['accuracy'] < 0)) {
            return new WP_Error('invalid_accuracy', 'Accuracy must be a positive number');
        }

        // Validate battery level if provided (0-100)
        if (isset($data['battery_level']) && (!is_numeric($data['battery_level']) || $data['battery_level'] < 0 || $data['battery_level'] > 100)) {
            return new WP_Error('invalid_battery', 'Battery level must be between 0 and 100');
        }

        return array(
            'agent_id' => get_current_user_id(),
            'latitude' => floatval($data['latitude']),
            'longitude' => floatval($data['longitude']),
            'accuracy' => isset($data['accuracy']) ? floatval($data['accuracy']) : null,
            'battery_level' => isset($data['battery_level']) ? intval($data['battery_level']) : null,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Save location data to database
     *
     * @param array $data
     * @return bool|WP_Error
     */
    private function save_location_data($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rr_location_tracking';

        // Insert location data
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%f', '%f', '%f', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save location data');
        }

        // Trigger action for other integrations
        do_action('rdm_agent_location_updated', $data['agent_id'], $data['latitude'], $data['longitude']);

        return true;
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    public function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371; // Radius of the earth in km

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earth_radius * $c;
    }

    /**
     * Get the latest location for a specific agent
     *
     * @param int $agent_user_id WordPress User ID of the delivery agent
     * @return array|null Array with location data or null if not found
     */
    public static function get_latest_agent_location(int $agent_user_id): ?array {
        global $wpdb;

        // Validate agent user ID
        if ($agent_user_id <= 0) {
            return null;
        }

        // Verify user exists and has delivery agent capability
        $user = get_userdata($agent_user_id);
        if (!$user || !user_can($user, 'delivery_agent')) {
            return null;
        }

        $table_name = $wpdb->prefix . 'rr_location_tracking';

        // Get the latest location record for this agent
        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT latitude, longitude, accuracy, battery_level, timestamp 
             FROM {$table_name} 
             WHERE agent_id = %d 
             ORDER BY timestamp DESC 
             LIMIT 1",
            $agent_user_id
        ));

        if (!$location) {
            return null;
        }

        return array(
            'latitude' => floatval($location->latitude),
            'longitude' => floatval($location->longitude),
            'accuracy' => $location->accuracy ? floatval($location->accuracy) : null,
            'battery_level' => $location->battery_level ? intval($location->battery_level) : null,
            'timestamp' => $location->timestamp,
            'agent_id' => $agent_user_id,
            'agent_name' => $user->display_name
        );
    }
}

// Initialize the class
RDM_GPS_Tracking::get_instance();