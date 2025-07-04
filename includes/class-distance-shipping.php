<?php
/**
 * Restaurant Delivery Manager - Distance Shipping Method
 *
 * @package RestaurantDeliveryManager
 * @subpackage Shipping
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Distance-based shipping method class
 *
 * Provides distance calculation, shipping rate logic, zone-based pricing,
 * and Google Maps integration for WooCommerce shipping.
 *
 * @class RDM_Distance_Shipping
 * @extends WC_Shipping_Method
 * @version 1.0.0
 */
class RDM_Distance_Shipping extends WC_Shipping_Method {
    
    /**
     * Database instance
     *
     * @var RDM_Database
     */
    private RDM_Database $database;
    
    /**
     * Google Maps instance
     *
     * @var RDM_Google_Maps|null
     */
    private ?RDM_Google_Maps $google_maps = null;
    
    /**
     * Constructor
     *
     * @param int $instance_id Instance ID
     */
    public function __construct($instance_id = 0) {
        $this->id = 'rdm_distance_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Distance-Based Delivery', 'restaurant-delivery-manager');
        $this->method_description = __('Calculate shipping costs based on distance from restaurant to delivery address.', 'restaurant-delivery-manager');
        $this->title = __('Delivery', 'restaurant-delivery-manager');
        
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->database = RDM_Database::instance();
        
        // Initialize Google Maps if available
        if (class_exists('RDM_Google_Maps')) {
            $this->google_maps = RDM_Google_Maps::instance();
        }
        
        $this->init();
    }
    
    /**
     * Initialize the shipping method
     *
     * @return void
     */
    public function init(): void {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->base_cost = $this->get_option('base_cost');
        $this->cost_per_km = $this->get_option('cost_per_km');
        $this->min_cost = $this->get_option('min_cost');
        $this->max_cost = $this->get_option('max_cost');
        $this->max_distance = $this->get_option('max_distance');
        $this->free_delivery_threshold = $this->get_option('free_delivery_threshold');
        $this->calculation_method = $this->get_option('calculation_method', 'haversine');
        
        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize form fields for settings
     *
     * @return void
     */
    public function init_form_fields(): void {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'restaurant-delivery-manager'),
                'type' => 'checkbox',
                'label' => __('Enable distance-based delivery', 'restaurant-delivery-manager'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Method Title', 'restaurant-delivery-manager'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'restaurant-delivery-manager'),
                'default' => __('Delivery', 'restaurant-delivery-manager'),
                'desc_tip' => true,
            ),
            'base_cost' => array(
                'title' => __('Base Cost', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Base delivery cost regardless of distance.', 'restaurant-delivery-manager'),
                'default' => '5.00',
                'desc_tip' => true,
            ),
            'cost_per_km' => array(
                'title' => __('Cost per KM', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Additional cost per kilometer.', 'restaurant-delivery-manager'),
                'default' => '1.50',
                'desc_tip' => true,
            ),
            'min_cost' => array(
                'title' => __('Minimum Cost', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Minimum delivery cost.', 'restaurant-delivery-manager'),
                'default' => '3.00',
                'desc_tip' => true,
            ),
            'max_cost' => array(
                'title' => __('Maximum Cost', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Maximum delivery cost (0 for no limit).', 'restaurant-delivery-manager'),
                'default' => '25.00',
                'desc_tip' => true,
            ),
            'max_distance' => array(
                'title' => __('Maximum Distance (KM)', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Maximum delivery distance in kilometers (0 for no limit).', 'restaurant-delivery-manager'),
                'default' => '15',
                'desc_tip' => true,
            ),
            'free_delivery_threshold' => array(
                'title' => __('Free Delivery Threshold', 'restaurant-delivery-manager'),
                'type' => 'decimal',
                'description' => __('Order total for free delivery (0 to disable).', 'restaurant-delivery-manager'),
                'default' => '50.00',
                'desc_tip' => true,
            ),
            'calculation_method' => array(
                'title' => __('Distance Calculation', 'restaurant-delivery-manager'),
                'type' => 'select',
                'description' => __('Method to calculate distance.', 'restaurant-delivery-manager'),
                'default' => 'haversine',
                'options' => array(
                    'haversine' => __('Haversine Formula (straight line)', 'restaurant-delivery-manager'),
                    'google_maps' => __('Google Maps API (driving distance)', 'restaurant-delivery-manager'),
                ),
                'desc_tip' => true,
            ),
            'restaurant_address' => array(
                'title' => __('Restaurant Address', 'restaurant-delivery-manager'),
                'type' => 'textarea',
                'description' => __('Full restaurant address for distance calculations.', 'restaurant-delivery-manager'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }
    
    /**
     * Calculate shipping costs
     *
     * @param array $package Shipping package
     * @return void
     */
    public function calculate_shipping($package = array()): void {
        // Get delivery address
        $delivery_address = $this->get_delivery_address($package);
        if (!$delivery_address) {
            return;
        }
        
        // Get restaurant coordinates
        $restaurant_coords = $this->get_restaurant_coordinates();
        if (!$restaurant_coords) {
            // Fallback to default shipping if no restaurant coordinates
            $this->add_rate(array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $this->base_cost,
                'package' => $package,
            ));
            return;
        }
        
        // Get delivery coordinates
        $delivery_coords = $this->get_delivery_coordinates($delivery_address);
        if (!$delivery_coords) {
            return; // Cannot calculate distance
        }
        
        // Calculate distance
        $distance = $this->calculate_distance($restaurant_coords, $delivery_coords);
        
        // Check maximum distance
        if ($this->max_distance > 0 && $distance > $this->max_distance) {
            return; // Outside delivery area
        }
        
        // Calculate cost
        $cost = $this->calculate_cost($distance, $package);
        
        // Check for free delivery
        if ($this->is_free_delivery($package)) {
            $cost = 0;
        }
        
        // Add shipping rate
        $this->add_rate(array(
            'id' => $this->get_rate_id(),
            'label' => $this->get_shipping_label($distance),
            'cost' => $cost,
            'package' => $package,
            'meta_data' => array(
                'distance' => $distance,
                'calculation_method' => $this->calculation_method,
            ),
        ));
    }
    
    /**
     * Get delivery address from package
     *
     * @param array $package Shipping package
     * @return string|null
     */
    private function get_delivery_address(array $package): ?string {
        $destination = $package['destination'] ?? array();
        
        $address_parts = array_filter(array(
            $destination['address_1'] ?? '',
            $destination['address_2'] ?? '',
            $destination['city'] ?? '',
            $destination['state'] ?? '',
            $destination['postcode'] ?? '',
            $destination['country'] ?? '',
        ));
        
        return !empty($address_parts) ? implode(', ', $address_parts) : null;
    }
    
    /**
     * Get restaurant coordinates
     *
     * @return array|null
     */
    private function get_restaurant_coordinates(): ?array {
        // Try to get cached coordinates
        $cached_coords = get_transient('rdm_restaurant_coordinates');
        if ($cached_coords) {
            return $cached_coords;
        }
        
        $restaurant_address = $this->get_option('restaurant_address');
        if (!$restaurant_address) {
            return null;
        }
        
        // Use Google Maps geocoding if available
        if ($this->google_maps && $this->google_maps->is_enabled()) {
            $coords = $this->google_maps->geocode_address($restaurant_address);
            if ($coords) {
                // Cache for 7 days
                set_transient('rdm_restaurant_coordinates', $coords, 7 * DAY_IN_SECONDS);
                return $coords;
            }
        }
        
        return null;
    }
    
    /**
     * Get delivery coordinates
     *
     * @param string $address Delivery address
     * @return array|null
     */
    private function get_delivery_coordinates(string $address): ?array {
        // Check cache first
        $cache_key = 'rdm_delivery_coords_' . md5($address);
        $cached_coords = get_transient($cache_key);
        if ($cached_coords) {
            return $cached_coords;
        }
        
        // Use Google Maps geocoding if available
        if ($this->google_maps && $this->google_maps->is_enabled()) {
            $coords = $this->google_maps->geocode_address($address);
            if ($coords) {
                // Cache for 24 hours
                set_transient($cache_key, $coords, DAY_IN_SECONDS);
                return $coords;
            }
        }
        
        return null;
    }
    
    /**
     * Calculate distance between two points
     *
     * @param array $from Origin coordinates
     * @param array $to Destination coordinates
     * @return float Distance in kilometers
     */
    private function calculate_distance(array $from, array $to): float {
        if ($this->calculation_method === 'google_maps' && $this->google_maps && $this->google_maps->is_enabled()) {
            // Use Google Maps Distance Matrix API for driving distance
            $distance = $this->google_maps->get_driving_distance($from, $to);
            if ($distance !== null) {
                return $distance;
            }
        }
        
        // Fallback to Haversine formula
        return $this->haversine_distance($from['lat'], $from['lng'], $to['lat'], $to['lng']);
    }
    
    /**
     * Calculate distance using Haversine formula
     *
     * @param float $lat1 Origin latitude
     * @param float $lng1 Origin longitude
     * @param float $lat2 Destination latitude
     * @param float $lng2 Destination longitude
     * @return float Distance in kilometers
     */
    private function haversine_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        return RDM_Location_Utilities::calculate_haversine_distance($lat1, $lng1, $lat2, $lng2);
    }
    
    /**
     * Calculate shipping cost based on distance
     *
     * @param float $distance Distance in kilometers
     * @param array $package Shipping package
     * @return float Shipping cost
     */
    private function calculate_cost(float $distance, array $package): float {
        $base_cost = floatval($this->base_cost);
        $cost_per_km = floatval($this->cost_per_km);
        $min_cost = floatval($this->min_cost);
        $max_cost = floatval($this->max_cost);
        
        // Calculate base cost + distance cost
        $cost = $base_cost + ($distance * $cost_per_km);
        
        // Apply minimum cost
        if ($min_cost > 0) {
            $cost = max($cost, $min_cost);
        }
        
        // Apply maximum cost
        if ($max_cost > 0) {
            $cost = min($cost, $max_cost);
        }
        
        // Apply zone-based pricing if configured
        $zone_adjustments = $this->get_zone_adjustments($package);
        if ($zone_adjustments) {
            $cost = $cost * $zone_adjustments['multiplier'] + $zone_adjustments['additional_cost'];
        }
        
        return round($cost, 2);
    }
    
    /**
     * Check if order qualifies for free delivery
     *
     * @param array $package Shipping package
     * @return bool
     */
    private function is_free_delivery(array $package): bool {
        $threshold = floatval($this->free_delivery_threshold);
        if ($threshold <= 0) {
            return false;
        }
        
        $cart_total = 0;
        if (isset($package['contents'])) {
            foreach ($package['contents'] as $item) {
                $cart_total += $item['line_total'];
            }
        }
        
        return $cart_total >= $threshold;
    }
    
    /**
     * Get zone-based pricing adjustments
     *
     * @param array $package Shipping package
     * @return array|null Zone adjustments
     */
    private function get_zone_adjustments(array $package): ?array {
        // Get delivery areas from database
        $delivery_areas = $this->database->get_delivery_areas();
        
        if (empty($delivery_areas)) {
            return null;
        }
        
        $destination = $package['destination'] ?? array();
        $postcode = $destination['postcode'] ?? '';
        
        // Find matching delivery area
        foreach ($delivery_areas as $area) {
            if ($this->is_postcode_in_area($postcode, $area->postcodes)) {
                return array(
                    'multiplier' => floatval($area->price_multiplier ?? 1.0),
                    'additional_cost' => floatval($area->additional_cost ?? 0.0),
                );
            }
        }
        
        return null;
    }
    
    /**
     * Check if postcode is in delivery area
     *
     * @param string $postcode Customer postcode
     * @param string $area_postcodes Area postcodes (comma-separated)
     * @return bool
     */
    private function is_postcode_in_area(string $postcode, string $area_postcodes): bool {
        if (empty($postcode) || empty($area_postcodes)) {
            return false;
        }
        
        $area_codes = array_map('trim', explode(',', $area_postcodes));
        $postcode = trim($postcode);
        
        foreach ($area_codes as $area_code) {
            // Support wildcard matching (e.g., "12345*" matches "12345xxx")
            if (str_contains($area_code, '*')) {
                $pattern = str_replace('*', '.*', preg_quote($area_code, '/'));
                if (preg_match('/^' . $pattern . '$/i', $postcode)) {
                    return true;
                }
            } elseif (strcasecmp($postcode, $area_code) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get shipping label with distance
     *
     * @param float $distance Distance in kilometers
     * @return string
     */
    private function get_shipping_label(float $distance): string {
        $label = $this->title;
        
        if ($distance > 0) {
            $distance_text = sprintf(
                /* translators: %s: distance in kilometers */
                __('(~%.1f km)', 'restaurant-delivery-manager'),
                $distance
            );
            $label .= ' ' . $distance_text;
        }
        
        return $label;
    }
    
    /**
     * Check if shipping method is available
     *
     * @param array $package Shipping package
     * @return bool
     */
    public function is_available($package): bool {
        if (!parent::is_available($package)) {
            return false;
        }
        
        // Check if Google Maps is required but not available
        if ($this->calculation_method === 'google_maps') {
            if (!$this->google_maps || !$this->google_maps->is_enabled()) {
                return false;
            }
        }
        
        // Check if restaurant coordinates are available
        $restaurant_coords = $this->get_restaurant_coordinates();
        if (!$restaurant_coords) {
            return false;
        }
        
        return true;
    }
}

// Initialize the shipping method
add_action('woocommerce_shipping_init', function() {
    if (class_exists('WC_Shipping_Method')) {
        // Register the shipping method
        add_filter('woocommerce_shipping_methods', function($methods) {
            $methods['rdm_distance_shipping'] = 'RDM_Distance_Shipping';
            return $methods;
        });
    }
}); 