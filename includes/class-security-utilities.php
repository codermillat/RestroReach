<?php
/**
 * Security Utilities for RestroReach
 * Centralized security validation and sanitization functions
 *
 * @package RestaurantDeliveryManager
 * @subpackage Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Utilities Class
 *
 * @since 1.0.0
 */
class RDM_Security_Utilities {

    /**
     * Singleton instance
     *
     * @var RDM_Security_Utilities|null
     */
    private static ?RDM_Security_Utilities $instance = null;

    /**
     * Get singleton instance
     *
     * @return RDM_Security_Utilities
     */
    public static function instance(): RDM_Security_Utilities {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton
    }

    /**
     * Validate AJAX request with nonce and capability check
     *
     * @since 1.0.0
     * @param string $nonce_action Nonce action name
     * @param string $capability Required capability
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function validate_ajax_request(string $nonce_action, string $capability, bool $die_on_failure = true) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $nonce_action)) {
            $error = new WP_Error('security_failed', __('Security check failed', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Check capability
        if (!current_user_can($capability)) {
            $error = new WP_Error('insufficient_permissions', __('Insufficient permissions', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return true;
    }

    /**
     * Validate AJAX request for delivery agents
     *
     * @since 1.0.0
     * @param string $nonce_action Nonce action name
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function validate_agent_ajax_request(string $nonce_action, bool $die_on_failure = true) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $nonce_action)) {
            $error = new WP_Error('security_failed', __('Security check failed', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Check if user is a delivery agent
        if (!current_user_can('delivery_agent')) {
            $error = new WP_Error('not_agent', __('Not authenticated as delivery agent', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return true;
    }

    /**
     * Validate admin AJAX request
     *
     * @since 1.0.0
     * @param string $nonce_action Nonce action name
     * @param string $capability Required capability (default: 'manage_woocommerce')
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function validate_admin_ajax_request(string $nonce_action, string $capability = 'manage_woocommerce', bool $die_on_failure = true) {
        // Verify nonce
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            $error = new WP_Error('security_failed', __('Security check failed', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Check capability
        if (!current_user_can($capability)) {
            $error = new WP_Error('insufficient_permissions', __('Insufficient permissions', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return true;
    }

    /**
     * Validate WooCommerce availability
     *
     * @since 1.0.0
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function validate_woocommerce_available(bool $die_on_failure = true) {
        if (!class_exists('WooCommerce')) {
            $error = new WP_Error('woocommerce_missing', __('WooCommerce is not active. Orders are not available.', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return true;
    }

    /**
     * Sanitize and validate order ID
     *
     * @since 1.0.0
     * @param mixed $order_id Order ID from request
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return int|WP_Error Order ID on success, WP_Error on failure
     */
    public static function validate_order_id($order_id, bool $die_on_failure = true) {
        $order_id = absint($order_id);
        
        if (!$order_id) {
            $error = new WP_Error('invalid_order_id', __('Invalid order ID', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return $order_id;
    }

    /**
     * Sanitize and validate agent ID
     *
     * @since 1.0.0
     * @param mixed $agent_id Agent ID from request
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return int|WP_Error Agent ID on success, WP_Error on failure
     */
    public static function validate_agent_id($agent_id, bool $die_on_failure = true) {
        $agent_id = absint($agent_id);
        
        if (!$agent_id) {
            $error = new WP_Error('invalid_agent_id', __('Invalid agent ID', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return $agent_id;
    }

    /**
     * Sanitize and validate user ID
     *
     * @since 1.0.0
     * @param mixed $user_id User ID from request
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return int|WP_Error User ID on success, WP_Error on failure
     */
    public static function validate_user_id($user_id, bool $die_on_failure = true) {
        $user_id = absint($user_id);
        
        if (!$user_id) {
            $error = new WP_Error('invalid_user_id', __('Invalid user ID', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Verify user exists
        if (!get_userdata($user_id)) {
            $error = new WP_Error('user_not_found', __('User not found', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return $user_id;
    }

    /**
     * Sanitize and validate amount
     *
     * @since 1.0.0
     * @param mixed $amount Amount from request
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return float|WP_Error Amount on success, WP_Error on failure
     */
    public static function validate_amount($amount, bool $die_on_failure = true) {
        $amount = floatval($amount);
        
        if ($amount < 0) {
            $error = new WP_Error('invalid_amount', __('Invalid amount', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return $amount;
    }

    /**
     * Sanitize and validate coordinates
     *
     * @since 1.0.0
     * @param mixed $latitude Latitude from request
     * @param mixed $longitude Longitude from request
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return array|WP_Error Coordinates array on success, WP_Error on failure
     */
    public static function validate_coordinates($latitude, $longitude, bool $die_on_failure = true) {
        $lat = floatval($latitude);
        $lng = floatval($longitude);

        // Validate latitude (-90 to 90)
        if ($lat < -90 || $lat > 90) {
            $error = new WP_Error('invalid_latitude', __('Latitude must be between -90 and 90', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Validate longitude (-180 to 180)
        if ($lng < -180 || $lng > 180) {
            $error = new WP_Error('invalid_longitude', __('Longitude must be between -180 and 180', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return array('latitude' => $lat, 'longitude' => $lng);
    }

    /**
     * Sanitize text input
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @return string Sanitized text
     */
    public static function sanitize_text($input): string {
        return sanitize_text_field($input ?? '');
    }

    /**
     * Sanitize textarea input
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @return string Sanitized textarea
     */
    public static function sanitize_textarea($input): string {
        return sanitize_textarea_field($input ?? '');
    }

    /**
     * Sanitize email input
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @return string Sanitized email
     */
    public static function sanitize_email($input): string {
        return sanitize_email($input ?? '');
    }

    /**
     * Sanitize URL input
     *
     * @since 1.0.0
     * @param mixed $input Input to sanitize
     * @return string Sanitized URL
     */
    public static function sanitize_url($input): string {
        return esc_url_raw($input ?? '');
    }

    /**
     * Validate file upload
     *
     * @since 1.0.0
     * @param array $file File upload array
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @param bool $die_on_failure Whether to die on failure (default: true)
     * @return array|WP_Error File data on success, WP_Error on failure
     */
    public static function validate_file_upload(array $file, array $allowed_types, int $max_size, bool $die_on_failure = true) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = new WP_Error('upload_error', __('File upload error', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Validate file type
        if (!in_array($file['type'], $allowed_types, true)) {
            $error = new WP_Error('invalid_file_type', __('Invalid file type', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        // Validate file size
        if ($file['size'] > $max_size) {
            $error = new WP_Error('file_too_large', __('File too large', 'restaurant-delivery-manager'));
            if ($die_on_failure) {
                wp_send_json_error(array('message' => $error->get_error_message()));
            }
            return $error;
        }

        return $file;
    }

    /**
     * Log security event
     *
     * @since 1.0.0
     * @param string $event Event type
     * @param array $data Event data
     * @return void
     */
    public static function log_security_event(string $event, array $data = array()): void {
        error_log(sprintf(
            'RestroReach Security: %s - %s - %s',
            $event,
            current_time('mysql'),
            wp_json_encode($data)
        ));
    }
} 