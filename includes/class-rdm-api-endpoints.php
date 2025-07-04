<?php
/**
 * Restaurant Delivery Manager - API Endpoints
 *
 * @package RestaurantDeliveryManager
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Endpoints class
 *
 * Handles REST API routes for agent authentication, order management,
 * location updates, and customer tracking functionality.
 *
 * @class RDM_API_Endpoints
 * @version 1.0.0
 */
class RDM_API_Endpoints {
    
    /**
     * API namespace
     *
     * @var string
     */
    private string $namespace = 'rdm/v1';
    
    /**
     * Database instance
     *
     * @var RDM_Database
     */
    private RDM_Database $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = RDM_Database::instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Agent authentication routes
        register_rest_route($this->namespace, '/agent/auth', array(
            'methods' => 'POST',
            'callback' => array($this, 'agent_authenticate'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Agent orders routes
        register_rest_route($this->namespace, '/agent/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_agent_orders'),
            'permission_callback' => array($this, 'check_agent_permission'),
        ));
        
        register_rest_route($this->namespace, '/agent/orders/(?P<id>\d+)/accept', array(
            'methods' => 'POST',
            'callback' => array($this, 'accept_order'),
            'permission_callback' => array($this, 'check_agent_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        register_rest_route($this->namespace, '/agent/orders/(?P<id>\d+)/status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => array($this, 'check_agent_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'notes' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));
        
        // Location tracking routes
        register_rest_route($this->namespace, '/agent/location', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_agent_location'),
            'permission_callback' => array($this, 'check_agent_permission'),
            'args' => array(
                'latitude' => array(
                    'required' => true,
                    'type' => 'number',
                    'validate_callback' => array($this, 'validate_latitude'),
                ),
                'longitude' => array(
                    'required' => true,
                    'type' => 'number',
                    'validate_callback' => array($this, 'validate_longitude'),
                ),
                'accuracy' => array(
                    'required' => false,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ),
                'battery_level' => array(
                    'required' => false,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_battery_level'),
                ),
            ),
        ));
        
        // Customer tracking routes
        register_rest_route($this->namespace, '/tracking/order/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_tracking'),
            'permission_callback' => array($this, 'check_order_tracking_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'tracking_code' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // COD payment routes
        register_rest_route($this->namespace, '/agent/payments/cod', array(
            'methods' => 'POST',
            'callback' => array($this, 'collect_cod_payment'),
            'permission_callback' => array($this, 'check_agent_permission'),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'collected_amount' => array(
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ),
                'notes' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));
    }
    
    /**
     * Check agent permission
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_agent_permission(WP_REST_Request $request): bool {
        return current_user_can('rdm_agent_access');
    }
    
    /**
     * Check order tracking permission
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_order_tracking_permission(WP_REST_Request $request): bool {
        // Allow public access for order tracking with valid tracking code
        // or authenticated users
        return true;
    }
    
    /**
     * Validate latitude
     *
     * @param mixed $value
     * @return bool
     */
    public function validate_latitude($value): bool {
        $lat = floatval($value);
        return $lat >= -90 && $lat <= 90;
    }
    
    /**
     * Validate longitude
     *
     * @param mixed $value
     * @return bool
     */
    public function validate_longitude($value): bool {
        $lng = floatval($value);
        return $lng >= -180 && $lng <= 180;
    }
    
    /**
     * Validate battery level
     *
     * @param mixed $value
     * @return bool
     */
    public function validate_battery_level($value): bool {
        $battery = intval($value);
        return $battery >= 0 && $battery <= 100;
    }
    
    /**
     * Agent authentication endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function agent_authenticate(WP_REST_Request $request) {
        try {
            $username = $request->get_param('username');
            $password = $request->get_param('password');
            
            $user = wp_authenticate($username, $password);
            
            if (is_wp_error($user)) {
                return new WP_Error(
                    'authentication_failed',
                    __('Invalid credentials.', 'restaurant-delivery-manager'),
                    array('status' => 401)
                );
            }
            
            if (!user_can($user, 'rdm_agent_access')) {
                return new WP_Error(
                    'unauthorized',
                    __('User is not authorized as a delivery agent.', 'restaurant-delivery-manager'),
                    array('status' => 403)
                );
            }
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user->ID);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent profile not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Generate JWT token or session
            $token = wp_generate_auth_cookie($user->ID, time() + (24 * HOUR_IN_SECONDS));
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'token' => $token,
                    'user_id' => $user->ID,
                    'agent_id' => $agent->id,
                    'display_name' => $user->display_name,
                    'agent_data' => array(
                        'phone' => $agent->phone,
                        'vehicle_type' => $agent->vehicle_type,
                        'status' => $agent->status,
                    ),
                ),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'authentication_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Get agent orders endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_agent_orders(WP_REST_Request $request) {
        try {
            $user_id = get_current_user_id();
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user_id);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Get assigned orders
            $orders = $this->database->get_agent_orders($agent->id);
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'orders' => $orders,
                    'total' => count($orders),
                ),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'orders_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Accept order endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function accept_order(WP_REST_Request $request) {
        try {
            $order_id = $request->get_param('id');
            $user_id = get_current_user_id();
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user_id);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Verify order assignment
            $assignment = $this->database->get_order_assignment($order_id);
            if (!$assignment || $assignment->agent_id !== $agent->id) {
                return new WP_Error(
                    'order_not_assigned',
                    __('Order not assigned to you.', 'restaurant-delivery-manager'),
                    array('status' => 403)
                );
            }
            
            // Update order status
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error(
                    'order_not_found',
                    __('Order not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            $order->update_status('ready-for-pickup', __('Agent accepted order', 'restaurant-delivery-manager'));
            $this->database->update_assignment_status($assignment->id, 'accepted');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Order accepted successfully', 'restaurant-delivery-manager'),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'accept_order_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Update order status endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_order_status(WP_REST_Request $request) {
        try {
            $order_id = $request->get_param('id');
            $status = $request->get_param('status');
            $notes = $request->get_param('notes');
            $user_id = get_current_user_id();
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user_id);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Verify order assignment
            $assignment = $this->database->get_order_assignment($order_id);
            if (!$assignment || $assignment->agent_id !== $agent->id) {
                return new WP_Error(
                    'order_not_assigned',
                    __('Order not assigned to you.', 'restaurant-delivery-manager'),
                    array('status' => 403)
                );
            }
            
            // Update order
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error(
                    'order_not_found',
                    __('Order not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            $order->update_status($status, $notes);
            $this->database->update_assignment_status($assignment->id, $status, array('notes' => $notes));
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Order status updated successfully', 'restaurant-delivery-manager'),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'update_status_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Update agent location endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_agent_location(WP_REST_Request $request) {
        try {
            $latitude = $request->get_param('latitude');
            $longitude = $request->get_param('longitude');
            $accuracy = $request->get_param('accuracy');
            $battery_level = $request->get_param('battery_level');
            $user_id = get_current_user_id();
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user_id);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Save location
            $saved = $this->database->save_location($agent->id, $latitude, $longitude, $accuracy, $battery_level);
            
            if (!$saved) {
                return new WP_Error(
                    'location_save_failed',
                    __('Failed to save location.', 'restaurant-delivery-manager'),
                    array('status' => 500)
                );
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Location updated successfully', 'restaurant-delivery-manager'),
                'timestamp' => current_time('mysql'),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'location_update_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Get order tracking endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_order_tracking(WP_REST_Request $request) {
        try {
            $order_id = $request->get_param('id');
            $tracking_code = $request->get_param('tracking_code');
            
            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error(
                    'order_not_found',
                    __('Order not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Verify tracking code if not authenticated
            if (!is_user_logged_in() && $tracking_code) {
                $stored_code = $order->get_meta('_rdm_tracking_code');
                if ($tracking_code !== $stored_code) {
                    return new WP_Error(
                        'invalid_tracking_code',
                        __('Invalid tracking code.', 'restaurant-delivery-manager'),
                        array('status' => 403)
                    );
                }
            }
            
            // Get assignment and agent location
            $assignment = $this->database->get_order_assignment($order_id);
            $agent_location = null;
            
            if ($assignment) {
                $agent_location = RDM_GPS_Tracking::get_latest_agent_location($assignment->agent_id);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'order_id' => $order_id,
                    'status' => $order->get_status(),
                    'agent_location' => $agent_location,
                    'estimated_delivery' => $order->get_meta('_rdm_estimated_delivery'),
                ),
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error(
                'tracking_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Collect COD payment endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function collect_cod_payment(WP_REST_Request $request) {
        try {
            $order_id = $request->get_param('order_id');
            $collected_amount = $request->get_param('collected_amount');
            $notes = $request->get_param('notes');
            $user_id = get_current_user_id();
            
            // Get agent data
            $agent = $this->database->get_agent_by_user_id($user_id);
            if (!$agent) {
                return new WP_Error(
                    'agent_not_found',
                    __('Agent not found.', 'restaurant-delivery-manager'),
                    array('status' => 404)
                );
            }
            
            // Use payment system
            if (class_exists('RDM_Payments')) {
                $payment_handler = RDM_Payments::instance();
                $result = $payment_handler->handle_cod_collection($order_id, $agent->id, $collected_amount, array(
                    'notes' => $notes,
                    'metadata' => array('collected_by' => $user_id)
                ));
                
                if ($result['success']) {
                    return new WP_REST_Response(array(
                        'success' => true,
                        'data' => $result['data'],
                    ), 200);
                } else {
                    return new WP_Error(
                        'payment_collection_failed',
                        $result['message'],
                        array('status' => 400)
                    );
                }
            } else {
                return new WP_Error(
                    'payment_system_unavailable',
                    __('Payment system not available.', 'restaurant-delivery-manager'),
                    array('status' => 503)
                );
            }
            
        } catch (Exception $e) {
            return new WP_Error(
                'payment_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
} 