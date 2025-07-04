<?php
/**
 * Restaurant Delivery Manager - Location Utilities
 *
 * Centralized location calculation utilities to eliminate code duplication
 * across the RestroReach plugin.
 *
 * @package RestaurantDeliveryManager
 * @subpackage Utilities
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RDM_Location_Utilities
 * 
 * Centralized location calculation utilities including distance calculations,
 * coordinate validation, and geocoding helpers.
 */
class RDM_Location_Utilities {

    /**
     * Earth's radius in kilometers
     */
    private const EARTH_RADIUS_KM = 6371;

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in kilometers
     */
    public static function calculate_haversine_distance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $lat_delta = deg2rad($lat2 - $lat1);
        $lng_delta = deg2rad($lng2 - $lng1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_delta / 2) * sin($lng_delta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Validate latitude coordinate
     *
     * @param float $latitude Latitude to validate
     * @return bool True if valid
     */
    public static function is_valid_latitude(float $latitude): bool {
        return $latitude >= -90 && $latitude <= 90;
    }

    /**
     * Validate longitude coordinate
     *
     * @param float $longitude Longitude to validate
     * @return bool True if valid
     */
    public static function is_valid_longitude(float $longitude): bool {
        return $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Validate coordinate pair
     *
     * @param float $latitude Latitude to validate
     * @param float $longitude Longitude to validate
     * @return bool True if both coordinates are valid
     */
    public static function is_valid_coordinates(float $latitude, float $longitude): bool {
        return self::is_valid_latitude($latitude) && self::is_valid_longitude($longitude);
    }

    /**
     * Calculate ETA based on distance and average speed
     *
     * @param float $distance_km Distance in kilometers
     * @param float $avg_speed_kmh Average speed in km/h (default: 30 km/h for city driving)
     * @return int Estimated time in minutes
     */
    public static function calculate_eta_minutes(float $distance_km, float $avg_speed_kmh = 30): int {
        if ($avg_speed_kmh <= 0) {
            return 0;
        }
        
        $time_hours = $distance_km / $avg_speed_kmh;
        return (int) round($time_hours * 60);
    }

    /**
     * Format distance for display
     *
     * @param float $distance_km Distance in kilometers
     * @param string $locale Locale for formatting (default: 'en_US')
     * @return string Formatted distance string
     */
    public static function format_distance(float $distance_km, string $locale = 'en_US'): string {
        if ($distance_km < 1) {
            $meters = round($distance_km * 1000);
            return sprintf(_n('%d meter', '%d meters', $meters, 'restaurant-delivery-manager'), $meters);
        } else {
            return sprintf(_n('%.1f kilometer', '%.1f kilometers', $distance_km, 'restaurant-delivery-manager'), $distance_km);
        }
    }

    /**
     * Calculate bearing between two points
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Bearing in degrees (0-360)
     */
    public static function calculate_bearing(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $lat1_rad = deg2rad($lat1);
        $lng1_rad = deg2rad($lng1);
        $lat2_rad = deg2rad($lat2);
        $lng2_rad = deg2rad($lng2);

        $delta_lng = $lng2_rad - $lng1_rad;

        $y = sin($delta_lng) * cos($lat2_rad);
        $x = cos($lat1_rad) * sin($lat2_rad) - sin($lat1_rad) * cos($lat2_rad) * cos($delta_lng);

        $bearing = atan2($y, $x);
        $bearing_deg = rad2deg($bearing);

        return ($bearing_deg + 360) % 360;
    }
} 