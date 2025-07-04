<?php
/**
 * Error Handling Utilities for RestroReach
 * Centralized error handling and logging functions
 *
 * @package RestaurantDeliveryManager
 * @subpackage ErrorHandling
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error Handling Utilities Class
 *
 * @since 1.0.0
 */
class RDM_Error_Handling {

    /**
     * Singleton instance
     *
     * @var RDM_Error_Handling|null
     */
    private static ?RDM_Error_Handling $instance = null;

    /**
     * Get singleton instance
     *
     * @return RDM_Error_Handling
     */
    public static function instance(): RDM_Error_Handling {
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
     * Execute function with error handling
     *
     * @since 1.0.0
     * @param callable $callback Function to execute
     * @param string $context Context for error logging
     * @param mixed $default_return Default return value on error
     * @param bool $log_error Whether to log errors (default: true)
     * @return mixed Function result or default value on error
     */
    public static function execute_with_error_handling(callable $callback, string $context, $default_return = null, bool $log_error = true) {
        try {
            return $callback();
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            return $default_return;
        }
    }

    /**
     * Execute AJAX function with error handling
     *
     * @since 1.0.0
     * @param callable $callback Function to execute
     * @param string $context Context for error logging
     * @param bool $log_error Whether to log errors (default: true)
     * @return void
     */
    public static function execute_ajax_with_error_handling(callable $callback, string $context, bool $log_error = true): void {
        try {
            $result = $callback();
            if ($result !== false) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(__('Operation failed', 'restaurant-delivery-manager'));
            }
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Execute REST API function with error handling
     *
     * @since 1.0.0
     * @param callable $callback Function to execute
     * @param string $context Context for error logging
     * @param int $success_status HTTP status code for success (default: 200)
     * @param bool $log_error Whether to log errors (default: true)
     * @return WP_REST_Response|WP_Error Response or error
     */
    public static function execute_rest_with_error_handling(callable $callback, string $context, int $success_status = 200, bool $log_error = true) {
        try {
            $result = $callback();
            return new WP_REST_Response($result, $success_status);
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            return new WP_Error(
                'rest_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Execute database operation with error handling
     *
     * @since 1.0.0
     * @param callable $callback Database operation to execute
     * @param string $context Context for error logging
     * @param mixed $default_return Default return value on error
     * @param bool $log_error Whether to log errors (default: true)
     * @return mixed Operation result or default value on error
     */
    public static function execute_db_with_error_handling(callable $callback, string $context, $default_return = null, bool $log_error = true) {
        global $wpdb;

        try {
            $result = $callback();
            
            // Check for database errors
            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }
            
            return $result;
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            return $default_return;
        }
    }

    /**
     * Execute WooCommerce operation with error handling
     *
     * @since 1.0.0
     * @param callable $callback WooCommerce operation to execute
     * @param string $context Context for error logging
     * @param mixed $default_return Default return value on error
     * @param bool $log_error Whether to log errors (default: true)
     * @return mixed Operation result or default value on error
     */
    public static function execute_wc_with_error_handling(callable $callback, string $context, $default_return = null, bool $log_error = true) {
        // Check if WooCommerce is available
        if (!class_exists('WooCommerce')) {
            if ($log_error) {
                self::log_error($context, 'WooCommerce is not active');
            }
            return $default_return;
        }

        try {
            return $callback();
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            return $default_return;
        }
    }

    /**
     * Execute file operation with error handling
     *
     * @since 1.0.0
     * @param callable $callback File operation to execute
     * @param string $context Context for error logging
     * @param mixed $default_return Default return value on error
     * @param bool $log_error Whether to log errors (default: true)
     * @return mixed Operation result or default value on error
     */
    public static function execute_file_with_error_handling(callable $callback, string $context, $default_return = null, bool $log_error = true) {
        try {
            return $callback();
        } catch (Exception $e) {
            if ($log_error) {
                self::log_error($context, $e->getMessage());
            }
            return $default_return;
        }
    }

    /**
     * Log error message
     *
     * @since 1.0.0
     * @param string $context Error context
     * @param string $message Error message
     * @param array $data Additional error data
     * @return void
     */
    public static function log_error(string $context, string $message, array $data = array()): void {
        $log_message = sprintf(
            'RestroReach: %s - %s',
            $context,
            $message
        );

        if (!empty($data)) {
            $log_message .= ' - ' . wp_json_encode($data);
        }

        error_log($log_message);
    }

    /**
     * Log warning message
     *
     * @since 1.0.0
     * @param string $context Warning context
     * @param string $message Warning message
     * @param array $data Additional warning data
     * @return void
     */
    public static function log_warning(string $context, string $message, array $data = array()): void {
        $log_message = sprintf(
            'RestroReach Warning: %s - %s',
            $context,
            $message
        );

        if (!empty($data)) {
            $log_message .= ' - ' . wp_json_encode($data);
        }

        error_log($log_message);
    }

    /**
     * Log info message
     *
     * @since 1.0.0
     * @param string $context Info context
     * @param string $message Info message
     * @param array $data Additional info data
     * @return void
     */
    public static function log_info(string $context, string $message, array $data = array()): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                'RestroReach Info: %s - %s',
                $context,
                $message
            );

            if (!empty($data)) {
                $log_message .= ' - ' . wp_json_encode($data);
            }

            error_log($log_message);
        }
    }

    /**
     * Handle AJAX error response
     *
     * @since 1.0.0
     * @param string $context Error context
     * @param string $message Error message
     * @param array $data Additional error data
     * @param bool $log_error Whether to log the error (default: true)
     * @return void
     */
    public static function handle_ajax_error(string $context, string $message, array $data = array(), bool $log_error = true): void {
        if ($log_error) {
            self::log_error($context, $message, $data);
        }
        wp_send_json_error(array('message' => $message));
    }

    /**
     * Handle REST API error response
     *
     * @since 1.0.0
     * @param string $context Error context
     * @param string $message Error message
     * @param int $status HTTP status code (default: 500)
     * @param array $data Additional error data
     * @param bool $log_error Whether to log the error (default: true)
     * @return WP_Error
     */
    public static function handle_rest_error(string $context, string $message, int $status = 500, array $data = array(), bool $log_error = true): WP_Error {
        if ($log_error) {
            self::log_error($context, $message, $data);
        }
        return new WP_Error('rest_error', $message, array('status' => $status));
    }

    /**
     * Validate and handle WP_Error
     *
     * @since 1.0.0
     * @param mixed $result Result to check
     * @param string $context Error context
     * @param mixed $default_return Default return value on error
     * @param bool $log_error Whether to log errors (default: true)
     * @return mixed Original result or default value if WP_Error
     */
    public static function handle_wp_error($result, string $context, $default_return = null, bool $log_error = true) {
        if (is_wp_error($result)) {
            if ($log_error) {
                self::log_error($context, $result->get_error_message());
            }
            return $default_return;
        }
        return $result;
    }

    /**
     * Create user-friendly error message
     *
     * @since 1.0.0
     * @param string $technical_message Technical error message
     * @param string $user_friendly_message User-friendly message (optional)
     * @return string User-friendly error message
     */
    public static function create_user_friendly_message(string $technical_message, string $user_friendly_message = ''): string {
        if (!empty($user_friendly_message)) {
            return $user_friendly_message;
        }

        // Map common technical errors to user-friendly messages
        $error_mappings = array(
            'database' => __('A database error occurred. Please try again.', 'restaurant-delivery-manager'),
            'permission' => __('You do not have permission to perform this action.', 'restaurant-delivery-manager'),
            'network' => __('A network error occurred. Please check your connection and try again.', 'restaurant-delivery-manager'),
            'file' => __('A file operation failed. Please try again.', 'restaurant-delivery-manager'),
            'timeout' => __('The operation timed out. Please try again.', 'restaurant-delivery-manager'),
        );

        foreach ($error_mappings as $key => $message) {
            if (stripos($technical_message, $key) !== false) {
                return $message;
            }
        }

        // Default user-friendly message
        return __('An unexpected error occurred. Please try again.', 'restaurant-delivery-manager');
    }

    /**
     * Get error context from backtrace
     *
     * @since 1.0.0
     * @param int $depth Backtrace depth (default: 2)
     * @return string Error context
     */
    public static function get_error_context(int $depth = 2): string {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
        
        if (isset($backtrace[$depth])) {
            $caller = $backtrace[$depth];
            $class = $caller['class'] ?? '';
            $function = $caller['function'] ?? '';
            
            if ($class) {
                return $class . '::' . $function;
            } else {
                return $function;
            }
        }
        
        return 'unknown';
    }
} 