<?php
/**
 * Restaurant Delivery Manager Professional
 *
 * @package           RestaurantDeliveryManager
 * @author            MD MILLAT HOSEN
 * @copyright         2025 MD MILLAT HOSEN
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Restaurant Delivery Manager Professional
 * Plugin URI:         https://github.com/codermillat/restaurant-delivery-manager-professional
 * Description:       Complete restaurant delivery management system with mobile agent interface, GPS tracking, and real-time order management for WordPress/WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            MD MILLAT HOSEN
 * Author URI:        https://github.com/codermillat
 * Text Domain:       restaurant-delivery-manager
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * WC requires at least: 8.0
 * WC tested up to: 9.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RDM_VERSION', '1.0.0');
define('RDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RDM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RDM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('RDM_TEXT_DOMAIN', 'restaurant-delivery-manager');

// Minimum requirements
define('RDM_MIN_PHP_VERSION', '8.0');
define('RDM_MIN_WP_VERSION', '6.0');
define('RDM_MIN_WC_VERSION', '8.0');

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function rdm_is_woocommerce_active(): bool {
    // Check for WooCommerce class and plugin activation
    return class_exists('WooCommerce') && function_exists('WC');
}

/**
 * Display admin notice when WooCommerce is not active
 *
 * @return void
 */
function rdm_woocommerce_missing_notice(): void {
    if (!rdm_is_woocommerce_active()) {
        $class = 'notice notice-error';
        $message = sprintf(
            /* translators: %1$s: Plugin name, %2$s: WooCommerce link */
            __('%1$s requires %2$s to be installed and activated for full functionality. Please activate WooCommerce.', 'restaurant-delivery-manager'),
            '<strong>RestroReach</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
        );
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    }
}
add_action('admin_notices', 'rdm_woocommerce_missing_notice');

/**
 * Main Restaurant Delivery Manager Class
 *
 * @class RestaurantDeliveryManager
 * @version 1.0.0
 */
final class RestaurantDeliveryManager {
    
    /**
     * The single instance of the class
     *
     * @var RestaurantDeliveryManager|null
     */
    private static ?RestaurantDeliveryManager $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database|null
     */
    public ?RDM_Database $database = null;
    
    /**
     * User roles instance
     *
     * @var RDM_User_Roles|null
     */
    public ?RDM_User_Roles $user_roles = null;
    
    /**
     * WooCommerce integration instance
     *
     * @var RDM_WooCommerce_Integration|null
     */
    public ?RDM_WooCommerce_Integration $woocommerce = null;
    
    /**
     * Mobile frontend instance
     *
     * @var RDM_Mobile_Frontend|null
     */
    public ?RDM_Mobile_Frontend $mobile_frontend = null;
    
    /**
     * Customer tracking instance
     *
     * @var RDM_Customer_Tracking|null
     */
    public ?RDM_Customer_Tracking $customer_tracking = null;
    
    /**
     * GPS tracking instance
     *
     * @var RDM_GPS_Tracking|null
     */
    public ?RDM_GPS_Tracking $gps_tracking = null;
    
    /**
     * Google Maps instance
     *
     * @var RDM_Google_Maps|null
     */
    public ?RDM_Google_Maps $google_maps = null;
    
    /**
     * Notifications instance
     *
     * @var RDM_Notifications|null
     */
    public ?RDM_Notifications $notifications = null;
    
    /**
     * Payments instance
     *
     * @var RDM_Payments|null
     */
    public ?RDM_Payments $payments = null;
    
    /**
     * Analytics instance
     *
     * @var RDM_Analytics|null
     */
    public ?RDM_Analytics $analytics = null;
    
    /**
     * Admin interface instance
     *
     * @var RDM_Admin_Interface|null
     */
    public ?RDM_Admin_Interface $admin_interface = null;
    
    /**
     * Main Restaurant Delivery Manager Instance
     *
     * Ensures only one instance of Restaurant Delivery Manager is loaded or can be loaded.
     *
     * @return RestaurantDeliveryManager Main instance
     */
    public static function instance(): RestaurantDeliveryManager {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init'), 10);
        
        // WooCommerce HPOS compatibility (only if WooCommerce is active)
        if (rdm_is_woocommerce_active()) {
            add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        }
    }
    
    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init(): void {
        // Check WooCommerce version if WooCommerce is active
        if (rdm_is_woocommerce_active() && defined('WC_VERSION') && version_compare(WC_VERSION, RDM_MIN_WC_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wc_version_notice'));
            return;
        }
        
        // Include required files
        $this->includes();
        
        // Initialize classes
        $this->init_classes();
        
        // Hook into WordPress
        $this->init_hooks();
    }
    
    /**
     * Include required files
     *
     * @return void
     */
    private function includes(): void {
        // Core includes (always needed)
        $core_files = array(
            'includes/class-database.php',
            'includes/class-user-roles.php',
            'includes/class-location-utilities.php',
            'includes/class-security-utilities.php',
            'includes/class-error-handling.php',
        );
        
        foreach ($core_files as $file) {
            $file_path = RDM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("RestroReach: Missing required file: {$file}");
            }
        }
        
        // WooCommerce-dependent includes (only if WooCommerce is active)
        if (rdm_is_woocommerce_active()) {
            $wc_file = RDM_PLUGIN_DIR . 'includes/class-woocommerce-integration.php';
            if (file_exists($wc_file)) {
                require_once $wc_file;
            }
        }
        
        // Admin tools and testing
        if (is_admin()) {
            $admin_files = array(

                'includes/class-rdm-admin-tools.php',
                'includes/class-rdm-database-tools.php',
            );
            
            foreach ($admin_files as $file) {
                $file_path = RDM_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
        
        // Other includes (using correct filenames)
        $other_files = array(
            'includes/class-rdm-admin-interface.php',
            'includes/class-rdm-mobile-frontend.php',
            'includes/class-rdm-gps-tracking.php',
            'includes/class-customer-tracking.php',
            'includes/class-notifications.php',
            'includes/class-rdm-google-maps.php',
            'includes/class-payments.php',
            'includes/class-analytics.php',
            'includes/class-rdm-api-endpoints.php',
        );
        
        foreach ($other_files as $file) {
            $file_path = RDM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("RestroReach: Missing file: {$file}");
            }
        }
    }
    
    /**
     * Initialize plugin classes
     *
     * @return void
     */
    private function init_classes(): void {
        // Initialize database
        if (class_exists('RDM_Database')) {
            $this->database = RDM_Database::instance();
        }
        
        // Initialize admin interface
        if (is_admin() && class_exists('RDM_Admin_Interface')) {
            $this->admin_interface = RDM_Admin_Interface::instance();
        }
        
        // Initialize other classes with existence checks
        if (class_exists('RDM_User_Roles')) {
            $this->user_roles = RDM_User_Roles::instance();
        }
        
        // Initialize WooCommerce-dependent classes only if WooCommerce is active
        if (rdm_is_woocommerce_active() && class_exists('RDM_WooCommerce_Integration')) {
            $this->woocommerce = RDM_WooCommerce_Integration::instance();
        }
        
        // Initialize mobile frontend (using standard singleton method)
        if (class_exists('RDM_Mobile_Frontend')) {
            $this->mobile_frontend = RDM_Mobile_Frontend::instance();
        }
        
        if (class_exists('RDM_Customer_Tracking')) {
            $this->customer_tracking = RDM_Customer_Tracking::instance();
        }
        
        // Initialize GPS tracking (using standard singleton method)
        if (class_exists('RDM_GPS_Tracking')) {
            $this->gps_tracking = RDM_GPS_Tracking::instance();
        }
        
        if (class_exists('RDM_Notifications')) {
            $this->notifications = RDM_Notifications::instance();
        }
        
        // Initialize Google Maps
        if (class_exists('RDM_Google_Maps')) {
            RDM_Google_Maps::init();
            $this->google_maps = RDM_Google_Maps::instance();
        }
        
        if (class_exists('RDM_Payments')) {
            $this->payments = RDM_Payments::instance();
        }
        
        // Initialize analytics
        if (class_exists('RDM_Analytics')) {
            $this->analytics = RDM_Analytics::instance();
        }
        
        if (class_exists('RDM_API_Endpoints')) {
            $this->api_endpoints = new RDM_API_Endpoints();
        }
    }
    
    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('RestaurantDeliveryManager', 'uninstall'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . RDM_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        $this->register_ajax_handlers();
        
        // Cron jobs
        add_action('rdm_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Add plugin loaded action
        do_action('rdm_loaded');
    }
    
    /**
     * Load plugin textdomain
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            RDM_TEXT_DOMAIN,
            false,
            dirname(RDM_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation - Enhanced with error handling and idempotency
     *
     * @return void
     */
    public function activate(): void {
        try {
            error_log('RestroReach: Starting activation process...');
            
            // Step 1: Check requirements first
            $this->check_activation_requirements();
            error_log('RestroReach: Requirements check passed');
            
            // Step 2: Include required files for activation
            $this->include_activation_files();
            error_log('RestroReach: Activation files included');
            
            // Step 3: Create database tables
            if (class_exists('RDM_Database')) {
                $database = RDM_Database::instance();
                $database->create_tables();
                error_log('RestroReach: Database tables created');
            } else {
                throw new Exception('Database class not available');
            }
            
            // Step 4: Create user roles (always create, even without WooCommerce)
            if (class_exists('RDM_User_Roles')) {
                $user_roles = RDM_User_Roles::instance();
                $user_roles->create_roles();
                error_log('RestroReach: User roles created');
            } else {
                throw new Exception('User roles class not available');
            }
            
            // Step 5: Set default options
            $this->set_default_options();
            error_log('RestroReach: Default options set');
            
            // Step 6: Schedule cron events
            if (!wp_next_scheduled('rdm_daily_cleanup')) {
                wp_schedule_event(time(), 'daily', 'rdm_daily_cleanup');
                error_log('RestroReach: Cron events scheduled');
            }
            
            // Step 7: Flush rewrite rules
            flush_rewrite_rules();
            error_log('RestroReach: Rewrite rules flushed');
            
            // Step 8: Set activation flag
            update_option('rdm_activation_completed', true);
            update_option('rdm_activation_time', current_time('mysql'));
            
            error_log('RestroReach: Plugin activated successfully');
            
        } catch (Exception $e) {
            error_log('RestroReach: Activation failed - ' . $e->getMessage());
            
            // Clean up on failure
            $this->cleanup_failed_activation();
            
            // Deactivate plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            wp_die(
                sprintf(
                    /* translators: %s: Error message */
                    __('RestroReach activation failed: %s', 'restaurant-delivery-manager'),
                    esc_html($e->getMessage())
                ),
                esc_html__('Plugin Activation Error', 'restaurant-delivery-manager'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Check activation requirements
     *
     * @throws Exception If requirements are not met
     */
    private function check_activation_requirements(): void {
        // Check PHP version
        if (version_compare(PHP_VERSION, RDM_MIN_PHP_VERSION, '<')) {
            throw new Exception(
                sprintf(
                    'PHP version %s or higher required. Current version: %s',
                    RDM_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            );
        }
        
        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], RDM_MIN_WP_VERSION, '<')) {
            throw new Exception(
                sprintf(
                    'WordPress version %s or higher required. Current version: %s',
                    RDM_MIN_WP_VERSION,
                    $GLOBALS['wp_version']
                )
            );
        }
        
        // Check if wp-admin/includes/upgrade.php is available
        if (!file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            throw new Exception('WordPress upgrade.php file not found');
        }
    }
    
    /**
     * Include files needed for activation
     *
     * @throws Exception If required files are missing
     */
    private function include_activation_files(): void {
        // Include WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Include core plugin files
        $required_files = array(
            'includes/class-database.php',
            'includes/class-user-roles.php',
        );
        
        foreach ($required_files as $file) {
            $file_path = RDM_PLUGIN_DIR . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Required file missing: {$file}");
            }
            require_once $file_path;
        }
    }
    
    /**
     * Clean up after failed activation
     *
     * @return void
     */
    private function cleanup_failed_activation(): void {
        // Remove any options that might have been set
        delete_option('rdm_activation_completed');
        delete_option('rdm_activation_time');
        
        // Clear any scheduled events
        $timestamp = wp_next_scheduled('rdm_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rdm_daily_cleanup');
        }
        
        error_log('RestroReach: Cleaned up after failed activation');
    }
    
    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear scheduled events
        $timestamp = wp_next_scheduled('rdm_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rdm_daily_cleanup');
        }
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('RestroReach plugin deactivated');
    }
    
    /**
     * Plugin uninstall
     *
     * @return void
     */
    public static function uninstall(): void {
        // Only run uninstall if the user has the proper permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Remove options
        delete_option('rdm_version');
        delete_option('rdm_settings');
        delete_option('rdm_activation_completed');
        delete_option('rdm_activation_time');
        
        // Remove user meta
        delete_metadata('user', 0, 'rdm_agent_id', '', true);
        delete_metadata('user', 0, 'rdm_last_location', '', true);
        
        // Remove transients
        delete_transient('rdm_dashboard_stats');
        delete_transient('rdm_agent_performance');
        
        // Drop database tables (optional - usually left for data preservation)
        // $database = RDM_Database::instance();
        // $database->drop_tables();
        
        // Log uninstall
        error_log('RestroReach plugin uninstalled');
    }
    
    /**
     * Set default plugin options
     *
     * @return void
     */
    private function set_default_options(): void {
        // Plugin version
        update_option('rdm_version', RDM_VERSION);
        
        // Default settings
        $default_settings = array(
            'google_maps_api_key' => '',
            'default_delivery_radius' => 10,
            'location_update_interval' => 45,
            'gps_retention_days' => 7,
            'notification_sound' => true,
            'auto_assign_agents' => false,
            'require_delivery_photo' => true,
        );
        
        if (!get_option('rdm_settings')) {
            update_option('rdm_settings', $default_settings);
        }
        
        // Default delivery areas
        if (!get_option('rdm_delivery_areas')) {
            $default_areas = array(
                array(
                    'name' => __('City Center', 'restaurant-delivery-manager'),
                    'radius' => 5,
                    'fee' => 2.50,
                    'min_order' => 15.00,
                ),
            );
            update_option('rdm_delivery_areas', $default_areas);
        }
    }
    
    /**
     * Get optimized asset URL (minified in production)
     */
    private function get_asset_url(string $file_path): string {
        $is_production = !defined('WP_DEBUG') || !WP_DEBUG;
        
        if ($is_production) {
            $min_file = str_replace(array('.css', '.js'), array('.min.css', '.min.js'), $file_path);
            $min_path = RDM_PLUGIN_PATH . $min_file;
            
            if (file_exists($min_path)) {
                return RDM_PLUGIN_URL . $min_file;
            }
        }
        
        return RDM_PLUGIN_URL . $file_path;
    }

    /**
     * Get asset version with cache busting
     */
    private function get_asset_version(string $file_path): string {
        $is_production = !defined('WP_DEBUG') || !WP_DEBUG;
        
        if ($is_production) {
            $min_file = str_replace(array('.css', '.js'), array('.min.css', '.min.js'), $file_path);
            $min_path = RDM_PLUGIN_PATH . $min_file;
            
            if (file_exists($min_path)) {
                return RDM_VERSION . '.' . filemtime($min_path);
            }
        }
        
        $full_path = RDM_PLUGIN_PATH . $file_path;
        if (file_exists($full_path)) {
            return RDM_VERSION . '.' . filemtime($full_path);
        }
        
        return RDM_VERSION;
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        // Only load on relevant pages
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // Frontend styles
        wp_enqueue_style(
            'rdm-frontend',
            $this->get_asset_url('assets/css/frontend.css'),
            array(),
            $this->get_asset_version('assets/css/frontend.css')
        );
        
        // Mobile agent styles
        if ($this->is_mobile_agent_page()) {
            wp_enqueue_style(
                'rdm-mobile-agent',
                $this->get_asset_url('assets/css/rdm-mobile-agent.css'),
                array('rdm-frontend'),
                $this->get_asset_version('assets/css/rdm-mobile-agent.css')
            );
        }
        
        // Frontend scripts
        wp_enqueue_script(
            'rdm-frontend',
            $this->get_asset_url('assets/js/frontend.js'),
            array('jquery'),
            $this->get_asset_version('assets/js/frontend.js'),
            true
        );
        
        // Mobile agent scripts
        if ($this->is_mobile_agent_page()) {
            wp_enqueue_script(
                'rdm-mobile-agent',
                $this->get_asset_url('assets/js/rdm-mobile-agent.js'),
                array('jquery', 'rdm-frontend'),
                $this->get_asset_version('assets/js/rdm-mobile-agent.js'),
                true
            );
        }
        
        // Localize scripts
        wp_localize_script('rdm-frontend', 'rdmFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm-frontend-nonce'),
            'settings' => array(
                'locationUpdateInterval' => get_option('rdm_location_update_interval', 45) * 1000,
                'googleMapsApiKey' => get_option('rdm_google_maps_api_key', ''),
            ),
        ));
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix): void {
        // Only load on plugin admin pages
        if (!$this->is_rdm_admin_page($hook_suffix)) {
            return;
        }
        
        // Admin styles
        wp_enqueue_style(
            'rdm-admin',
            $this->get_asset_url('assets/css/admin.css'),
            array(),
            $this->get_asset_version('assets/css/admin.css')
        );
        
        // Admin scripts
        wp_enqueue_script(
            'rdm-admin',
            $this->get_asset_url('assets/js/admin.js'),
            array('jquery', 'wp-util'),
            $this->get_asset_version('assets/js/admin.js'),
            true
        );
        
        // Localize admin script
        wp_localize_script('rdm-admin', 'rdmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm-admin-nonce'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'restaurant-delivery-manager'),
                'error' => __('An error occurred. Please try again.', 'restaurant-delivery-manager'),
                'success' => __('Operation completed successfully.', 'restaurant-delivery-manager'),
            ),
        ));
        
        // Google Maps API (only if API key is set)
        $api_key = get_option('rdm_google_maps_api_key');
        if (!empty($api_key)) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places',
                array(),
                null,
                true
            );
        }
    }
    
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function register_ajax_handlers(): void {
        // Admin AJAX
        add_action('wp_ajax_rdm_refresh_dashboard', array($this, 'ajax_refresh_dashboard'));
        
        // Frontend AJAX (logged in users)
        add_action('wp_ajax_rdm_update_location', array($this, 'ajax_update_location'));
        add_action('wp_ajax_rdm_get_order_tracking', array($this, 'ajax_get_order_tracking'));
        
        // Public AJAX (for customers)
        add_action('wp_ajax_nopriv_rdm_get_order_tracking', array($this, 'ajax_get_order_tracking'));
    }
    
    /**
     * AJAX: Refresh dashboard data
     *
     * @return void
     */
    public function ajax_refresh_dashboard(): void {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm-admin-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
            return;
        }
        
        // Get dashboard data
        $data = array(
            'orders_today' => 0,
            'pending_orders' => 0,
            'active_agents' => 0,
            'total_revenue' => 0,
        );
        
        // Only get WooCommerce data if available
        if (rdm_is_woocommerce_active()) {
            // Get today's orders
            $today = date('Y-m-d');
            $orders_today = wc_get_orders(array(
                'status' => array('wc-processing', 'wc-preparing', 'wc-ready', 'wc-out-for-delivery', 'wc-delivered'),
                'date_created' => $today,
                'limit' => -1,
            ));
            
            $data['orders_today'] = count($orders_today);
            $data['total_revenue'] = array_sum(array_map(function($order) {
                return $order->get_total();
            }, $orders_today));
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Update agent location
     *
     * @return void
     */
    public function ajax_update_location(): void {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm-frontend-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Delegate to GPS tracking class
        if ($this->gps_tracking) {
            $this->gps_tracking->handle_location_update();
        } else {
            wp_send_json_error(array('message' => __('GPS tracking not available', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX: Get order tracking information
     *
     * @return void
     */
    public function ajax_get_order_tracking(): void {
        // Security check for logged-in users (agents/managers)
        if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'rdm-frontend-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // For public tracking, use tracking key validation instead
        if (!is_user_logged_in()) {
            $tracking_key = sanitize_text_field($_POST['tracking_key'] ?? '');
            if (empty($tracking_key)) {
                wp_send_json_error(array('message' => __('Tracking key required', 'restaurant-delivery-manager')));
                return;
            }
        }
        
        // Delegate to customer tracking class
        if ($this->customer_tracking) {
            $order_id = absint($_POST['order_id'] ?? 0);
            if ($order_id) {
                $tracking_data = $this->customer_tracking->get_order_tracking_data($order_id);
                wp_send_json_success($tracking_data);
            } else {
                wp_send_json_error(array('message' => __('Invalid order ID', 'restaurant-delivery-manager')));
            }
        } else {
            wp_send_json_error(array('message' => __('Customer tracking not available', 'restaurant-delivery-manager')));
        }
    }

    /**
     * Daily cleanup tasks
     *
     * @return void
     */
    public function daily_cleanup(): void {
        // Clean up old location data
        global $wpdb;
        $retention_days = get_option('rdm_gps_retention_days', 7);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}rr_location_tracking WHERE timestamp < %s",
            $cutoff_date
        ));
        
        // Log cleanup
        error_log("RestroReach: Cleaned up {$deleted} old location records");
        
        // Clean up expired transients
        delete_expired_transients();
    }
    
    /**
     * Check if frontend assets should be loaded
     *
     * @return bool
     */
    private function should_load_frontend_assets(): bool {
        global $post;
        
        // Load on mobile agent pages
        if ($this->is_mobile_agent_page()) {
            return true;
        }
        
        // Load on customer tracking pages
        if (is_page() && $post && has_shortcode($post->post_content, 'rdm_order_tracking')) {
            return true;
        }
        
        // Load on WooCommerce pages (if WooCommerce is active)
        if (rdm_is_woocommerce_active() && (is_shop() || is_product() || is_cart() || is_checkout() || is_account_page())) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is a mobile agent page
     *
     * @return bool
     */
    private function is_mobile_agent_page(): bool {
        return isset($_GET['rdm_page']) && in_array($_GET['rdm_page'], array('agent-login', 'agent-dashboard'));
    }
    
    /**
     * Check if current admin page is a plugin page
     *
     * @param string $hook_suffix
     * @return bool
     */
    private function is_rdm_admin_page($hook_suffix): bool {
        return strpos($hook_suffix, 'restroreach') !== false || strpos($hook_suffix, 'restaurant-delivery') !== false;
    }
    
    /**
     * Add settings link to plugins page
     *
     * @param array $links
     * @return array
     */
    public function add_settings_link($links): array {
        $settings_link = '<a href="' . admin_url('admin.php?page=restroreach-settings') . '">' . 
                        __('Settings', 'restaurant-delivery-manager') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Declare HPOS compatibility
     *
     * @return void
     */
    public function declare_hpos_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * PHP version notice
     *
     * @return void
     */
    public function php_version_notice(): void {
        $message = sprintf(
            /* translators: %1$s: Plugin name, %2$s: Required PHP version, %3$s: Current PHP version */
            __('%1$s requires PHP version %2$s or higher. You are running version %3$s. Please update PHP.', 'restaurant-delivery-manager'),
            '<strong>RestroReach</strong>',
            RDM_MIN_PHP_VERSION,
            PHP_VERSION
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * WordPress version notice
     *
     * @return void
     */
    public function wp_version_notice(): void {
        $message = sprintf(
            /* translators: %1$s: Plugin name, %2$s: Required WordPress version, %3$s: Current WordPress version */
            __('%1$s requires WordPress version %2$s or higher. You are running version %3$s. Please update WordPress.', 'restaurant-delivery-manager'),
            '<strong>RestroReach</strong>',
            RDM_MIN_WP_VERSION,
            $GLOBALS['wp_version']
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * WooCommerce missing notice
     *
     * @return void
     */
    public function wc_missing_notice(): void {
        $message = sprintf(
            /* translators: %1$s: Plugin name */
            __('%1$s requires WooCommerce to be installed and activated. Please install WooCommerce.', 'restaurant-delivery-manager'),
            '<strong>RestroReach</strong>'
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
    
    /**
     * WooCommerce version notice
     *
     * @return void
     */
    public function wc_version_notice(): void {
        $message = sprintf(
            /* translators: %1$s: Plugin name, %2$s: Required WooCommerce version, %3$s: Current WooCommerce version */
            __('%1$s requires WooCommerce version %2$s or higher. You are running version %3$s. Please update WooCommerce.', 'restaurant-delivery-manager'),
            '<strong>RestroReach</strong>',
            RDM_MIN_WC_VERSION,
            defined('WC_VERSION') ? WC_VERSION : __('Unknown', 'restaurant-delivery-manager')
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
}

/**
 * Initialize the plugin only if WordPress and PHP requirements are met
 */
function RDM(): RestaurantDeliveryManager {
    return RestaurantDeliveryManager::instance();
}

// Ensure custom roles are always created/updated on init
add_action('init', function() {
    if (class_exists('RDM_User_Roles')) {
        $user_roles = RDM_User_Roles::instance();
        $user_roles->create_roles();
        $user_roles->add_admin_capabilities();
    }
});

// Initialize the plugin
RDM();