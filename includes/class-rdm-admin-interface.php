<?php
/**
 * RestroReach - Admin Interface
 *
 * @package RestroReach
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface class
 *
 * Handles all admin interface functionality including menu registration,
 * dashboard setup, and permission management.
 *
 * @class RDM_Admin_Interface
 * @version 1.0.0
 */
class RDM_Admin_Interface {
    
    /**
     * The single instance of the class
     *
     * @var RDM_Admin_Interface|null
     */
    private static ?RDM_Admin_Interface $instance = null;
    
    /**
     * Database instance
     *
     * @var RDM_Database
     */
    private RDM_Database $database;
    
    /**
     * Main RDM_Admin_Interface Instance
     *
     * @since 1.0.0
     * @return RDM_Admin_Interface Main instance
     */
    public static function instance(): RDM_Admin_Interface {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->database = RDM_Database::instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks(): void {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_rdm_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_rdm_get_recent_orders', array($this, 'ajax_get_recent_orders'));
        add_action('wp_ajax_rdm_get_agent_status', array($this, 'ajax_get_agent_status'));
        add_action('wp_ajax_rdm_fetch_orders', array($this, 'ajax_fetch_orders'));
        add_action('wp_ajax_rdm_update_order_status', array($this, 'ajax_update_order_status'));
        add_action('wp_ajax_rdm_get_available_agents', array($this, 'ajax_get_available_agents'));
        add_action('wp_ajax_rdm_assign_agent_to_order', array($this, 'ajax_assign_agent_to_order'));
        add_action('wp_ajax_rdm_add_order_note', array($this, 'ajax_add_order_note'));
        add_action('wp_ajax_rdm_test_geocode', array($this, 'ajax_test_geocode'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));

        // Settings API
        add_action('admin_init', array($this, 'register_plugin_settings'));

        // Restrict admin access for custom roles
        add_action('admin_init', array($this, 'restrict_custom_role_admin_access'), 1);
        
        // Add meta boxes for WooCommerce orders
        add_action('add_meta_boxes', array($this, 'add_order_route_meta_box'));
    }
    
    /**
     * Add admin menu items
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu item - capability will be dynamically checked in page render or managed by WordPress based on submenu capabilities
        add_menu_page(
            __('RestroReach', 'restaurant-delivery-manager'),
            __('RestroReach', 'restaurant-delivery-manager'),
            'manage_options', // Change to manage_options for main menu
            'restroreach-dashboard', // Main slug, often defaults to first submenu
            array($this, 'render_dashboard_page'),
            'dashicons-store',
            56
        );
        
        // Dashboard submenu (Restaurant Manager & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Dashboard', 'restaurant-delivery-manager'),
            __('Dashboard', 'restaurant-delivery-manager'),
            'rdm_access_manager_dashboard', // Custom capability
            'restroreach-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        // Orders submenu (Restaurant Manager & Admin, only if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                'restroreach-dashboard',
                __('Orders', 'restaurant-delivery-manager'),
                __('Orders', 'restaurant-delivery-manager'),
                'rdm_manage_orders', // Custom capability
                'restroreach-orders',
                array($this, 'render_orders_page')
            );
        }
        
        // Delivery Agents submenu (Restaurant Manager & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Delivery Agents', 'restaurant-delivery-manager'),
            __('Delivery Agents', 'restaurant-delivery-manager'),
            'rdm_manage_agents', // Custom capability
            'restroreach-agents',
            array($this, 'render_agents_page')
        );

        // Agent Portal submenu (Delivery Agent & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Agent Portal', 'restaurant-delivery-manager'),
            __('Agent Portal', 'restaurant-delivery-manager'),
            'rdm_access_agent_portal', // Custom capability for agents
            'restroreach-agent-portal',
            array($this, 'render_agent_portal_page')
        );

        // Agent Live View submenu (Restaurant Manager & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Agent Live View', 'restaurant-delivery-manager'),
            __('Agent Live View', 'restaurant-delivery-manager'),
            'rdm_manage_agents', // Custom capability
            'restroreach-agent-live-view',
            array($this, 'render_agent_live_view_page')
        );
        
        // Cash Reconciliation submenu (Restaurant Manager & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Cash Reconciliation', 'restaurant-delivery-manager'),
            __('Cash Reconciliation', 'restaurant-delivery-manager'),
            'manage_woocommerce', // WooCommerce management capability
            'rdm-cash-reconciliation',
            array($this, 'render_cash_reconciliation_page')
        );
        
        // Settings submenu (Admin only)
        add_submenu_page(
            'restroreach-dashboard',
            __('Settings', 'restaurant-delivery-manager'),
            __('Settings', 'restaurant-delivery-manager'),
            'manage_options', // Core WordPress capability for administrators
            'restroreach-settings',
            array($this, 'render_settings_page')
        );
        
        // Analytics submenu (Restaurant Manager & Admin)
        add_submenu_page(
            'restroreach-dashboard',
            __('Analytics & Reports', 'restaurant-delivery-manager'),
            __('Analytics', 'restaurant-delivery-manager'),
            'rdm_view_reports', // Custom capability for analytics access
            'restroreach-analytics',
            array($this, 'render_analytics_page')
        );
        
        // Database Tools submenu (Admin only)
        add_submenu_page(
            'restroreach-dashboard',
            __('Database Tools', 'restaurant-delivery-manager'),
            __('Database Tools', 'restaurant-delivery-manager'),
            'manage_options', // Core WordPress capability for administrators
            'restroreach-database-tools',
            array($this, 'render_database_tools_page')
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Load on plugin pages
        $is_plugin_page = strpos($hook_suffix, 'restroreach') !== false;
        
        // Also load on WooCommerce order edit screen for meta box
        $is_order_edit = ($hook_suffix === 'post.php' || $hook_suffix === 'post-new.php') && 
                        isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order' ||
                        (isset($_GET['post']) && get_post_type($_GET['post']) === 'shop_order');
        
        if (!$is_plugin_page && !$is_order_edit) {
            return;
        }
        
        // Enqueue admin styles
        wp_enqueue_style(
            'rdm-admin-dashboard',
            RDM_PLUGIN_URL . 'admin/css/admin-dashboard.css',
            array(),
            RDM_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'rdm-admin-dashboard',
            RDM_PLUGIN_URL . 'admin/js/admin-dashboard.js',
            array('jquery', 'wp-util'),
            RDM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('rdm-admin-dashboard', 'rdmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rdm-admin-nonce'),
            'i18n' => array(
                'error' => __('Error loading data', 'restaurant-delivery-manager'),
                'noData' => __('No data available', 'restaurant-delivery-manager'),
                'confirm' => __('Are you sure?', 'restaurant-delivery-manager'),
            ),
        ));
        
        // Enqueue order management assets only on Orders page
        if ($hook_suffix === 'restaurant-delivery_page_restroreach-orders' || $hook_suffix === 'restroreach_page_restroreach-orders') {
            wp_enqueue_style(
                'rdm-admin-orders',
                RDM_PLUGIN_URL . 'assets/css/rdm-admin-orders.css',
                array(),
                RDM_VERSION
            );
            wp_enqueue_script(
                'rdm-admin-orders',
                RDM_PLUGIN_URL . 'assets/js/rdm-admin-orders.js',
                array('jquery', 'wp-util'),
                RDM_VERSION,
                true
            );
            wp_localize_script('rdm-admin-orders', 'rdmOrders', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rdm-orders-nonce'),
                'i18n' => array(
                    'error' => __('Error loading data', 'restaurant-delivery-manager'),
                    'noData' => __('No orders found', 'restaurant-delivery-manager'),
                    'confirm' => __('Are you sure?', 'restaurant-delivery-manager'),
                ),
            ));
        }
        
        // Enqueue Google Maps assets for agent live view page
        if ($hook_suffix === 'restaurant-delivery_page_restroreach-agent-live-view' || $hook_suffix === 'restroreach_page_restroreach-agent-live-view') {
            // Enqueue agent live view specific CSS
            wp_enqueue_style(
                'rdm-agent-live-view',
                RDM_PLUGIN_URL . 'assets/css/rdm-agent-live-view.css',
                array(),
                RDM_VERSION
            );
            
            // Enqueue Google Maps integration
            if (class_exists('RDM_Google_Maps')) {
                RDM_Google_Maps::instance()->enqueue_admin_maps_script();
            }
            
            // Enqueue admin maps JavaScript
            wp_enqueue_script(
                'rdm-admin-maps',
                RDM_PLUGIN_URL . 'assets/js/rdm-admin-maps.js',
                array('jquery'),
                RDM_VERSION,
                true
            );
            
            // Prepare default map configuration
            $map_config = array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rdm-admin-maps-nonce'),
                'mapDefaults' => array(
                    'zoom' => 10,
                    'center' => array('lat' => 40.7128, 'lng' => -74.0060), // Default to NYC
                    'mapTypeId' => 'roadmap'
                )
            );
            
            // Localize map configuration
            wp_localize_script('rdm-admin-maps', 'rdmAdminMapsConfig', $map_config);
        }
        
        // Enqueue Google Maps assets for WooCommerce order edit screen
        if ($is_order_edit && class_exists('RDM_Google_Maps') && RDM_Google_Maps::is_enabled()) {
            // Enqueue admin maps JavaScript for order route meta box
            wp_enqueue_script(
                'rdm-admin-maps',
                RDM_PLUGIN_URL . 'assets/js/rdm-admin-maps.js',
                array('jquery'),
                RDM_VERSION,
                true
            );
            
            // Enqueue Google Maps API
            $api_key = RDM_Google_Maps::get_api_key();
            if ($api_key) {
                wp_enqueue_script(
                    'google-maps-api',
                    "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places,geometry&callback=rdmInitAdminMaps",
                    array(),
                    RDM_VERSION,
                    true
                );
            }
        }
    }
    
    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard_page(): void {
        // Check permissions
        // For dashboard, capability is rdm_access_manager_dashboard for managers, or manage_options for admin if no specific cap
        if (!current_user_can('rdm_access_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        // Display WooCommerce dependency notice if needed
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                /* translators: %s: WooCommerce link */
                __('RestroReach requires %s for full functionality. Some features may be limited without WooCommerce active.', 'restaurant-delivery-manager'),
                '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
            );
            echo '</p></div>';
        }
        
        // Make database instance available to template
        $database = $this->database;
        include RDM_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }
    
    /**
     * Render orders page
     *
     * @return void
     */
    public function render_orders_page(): void {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Orders', 'restaurant-delivery-manager') . '</h1>';
            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: WooCommerce link */
                __('Order management requires %s to be installed and activated. Please activate WooCommerce to access this feature.', 'restaurant-delivery-manager'),
                '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
            );
            echo '</p></div>';
            echo '</div>';
            return;
        }
        
        // Check permissions
        if (!current_user_can('rdm_manage_orders')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        include RDM_PLUGIN_DIR . 'templates/admin/order-management-page.php';
    }
    
    /**
     * Render agents page
     *
     * @return void
     */
    public function render_agents_page(): void {
        // Check permissions
        if (!current_user_can('rdm_manage_agents')) { // Assuming rdm_manage_agents is the correct capability
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        // Include agents template
        $agents_template = RDM_PLUGIN_DIR . 'templates/admin/agents-management-page.php';
        if (file_exists($agents_template)) {
            include $agents_template;
        } else {
            echo '<div class="wrap"><h2>' . esc_html__('Delivery Agents', 'restaurant-delivery-manager') . '</h2><p>' . esc_html__('Agents page template not found.', 'restaurant-delivery-manager') . '</p></div>';
        }
    }
    
    /**
     * Render cash reconciliation page
     *
     * @return void
     */
    public function render_cash_reconciliation_page(): void {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        // Include cash reconciliation template
        $template_path = RDM_PLUGIN_DIR . 'templates/admin/cash-reconciliation-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Cash Reconciliation', 'restaurant-delivery-manager') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Cash reconciliation template not found.', 'restaurant-delivery-manager') . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        // Check permissions - ensure this uses manage_options
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sorry, you do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'),
                403
            );
        }
        
        // Display settings errors
        settings_errors('rdm_plugin_options');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('rdm_settings_group');
                do_settings_sections('restroreach-settings');
                submit_button(__('Save Settings', 'restaurant-delivery-manager'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render analytics page
     *
     * @return void
     */
    public function render_analytics_page(): void {
        // Check permissions
        if (!current_user_can('rdm_view_reports') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        // Initialize analytics if available
        if (class_exists('RDM_Analytics')) {
            $analytics = RDM_Analytics::instance();
        }
        
        include RDM_PLUGIN_DIR . 'templates/admin/analytics-page.php';
    }

    /**
     * Render database tools page
     *
     * @return void
     */
    public function render_database_tools_page(): void {
        // Check permissions - ensure this uses manage_options (admin only)
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sorry, you do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'),
                403
            );
        }

        // Initialize database tools if available
        if (class_exists('RDM_Database_Tools')) {
            $database_tools = new RDM_Database_Tools();
            $database_tools->render_admin_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Database Tools class not found. Please ensure the plugin is properly installed.', 'restaurant-delivery-manager'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Register plugin settings using the Settings API
     * This method should be hooked to 'admin_init'
     *
     * @since 1.0.0
     * @return void
     */
    public function register_plugin_settings(): void {
        register_setting(
            'rdm_settings_group',       // Option group. Must match settings_fields() call.
            'rdm_plugin_options',       // Option name in wp_options table.
            array($this, 'sanitize_settings') // Sanitization callback.
        );

        // General Settings Section
        add_settings_section(
            'rdm_general_settings_section', // ID of the section.
            __('General Settings', 'restaurant-delivery-manager'), // Title of the section.
            array($this, 'general_settings_section_callback'), // Callback function to render the section description.
            'restroreach-settings'      // Page slug on which to show this section.
        );

        // Google Maps API Key field
        add_settings_field(
            'rdm_google_maps_api_key',  // ID of the field.
            __('Google Maps API Key', 'restaurant-delivery-manager'), // Title of the field.
            array($this, 'google_maps_api_key_callback'), // Callback function to render the field.
            'restroreach-settings',      // Page slug on which to show this field.
            'rdm_general_settings_section' // Section ID to which this field belongs.
        );

        // Integrations Settings Section
        add_settings_section(
            'rdm_integrations_section',
            __('Integrations', 'restaurant-delivery-manager'),
            array($this, 'integrations_section_callback'),
            'restroreach-settings'
        );

        // Additional Google Maps settings
        add_settings_field(
            'rdm_maps_default_zoom',
            __('Default Map Zoom Level', 'restaurant-delivery-manager'),
            array($this, 'maps_default_zoom_callback'),
            'restroreach-settings',
            'rdm_integrations_section'
        );

        add_settings_field(
            'rdm_maps_center_address',
            __('Default Map Center Address', 'restaurant-delivery-manager'),
            array($this, 'maps_center_address_callback'),
            'restroreach-settings',
            'rdm_integrations_section'
        );
        
        // Contact Information Settings Section
        add_settings_section(
            'rdm_contact_settings_section',
            __('Contact & Emergency Settings', 'restaurant-delivery-manager'),
            array($this, 'contact_settings_section_callback'),
            'restroreach-settings'
        );
        
        // Emergency Phone field
        add_settings_field(
            'rdm_emergency_phone',
            __('Emergency Contact Phone', 'restaurant-delivery-manager'),
            array($this, 'emergency_phone_callback'),
            'restroreach-settings',
            'rdm_contact_settings_section'
        );
        
        // Support Phone field
        add_settings_field(
            'rdm_support_phone',
            __('Customer Support Phone', 'restaurant-delivery-manager'),
            array($this, 'support_phone_callback'),
            'restroreach-settings',
            'rdm_contact_settings_section'
        );
        
        // Support Email field
        add_settings_field(
            'rdm_support_email',
            __('Customer Support Email', 'restaurant-delivery-manager'),
            array($this, 'support_email_callback'),
            'restroreach-settings',
            'rdm_contact_settings_section'
        );
    }

    /**
     * Sanitize settings input
     *
     * @param array $input Contains all settings fields as array keys
     * @return array Sanitized settings
     */
    public function sanitize_settings(array $input): array {
        $sanitized_input = array();
        
        // Google Maps API Key
        if (isset($input['rdm_google_maps_api_key'])) {
            $sanitized_input['rdm_google_maps_api_key'] = sanitize_text_field($input['rdm_google_maps_api_key']);
        }
        
        // Maps Default Zoom Level
        if (isset($input['rdm_maps_default_zoom'])) {
            $zoom = intval($input['rdm_maps_default_zoom']);
            $sanitized_input['rdm_maps_default_zoom'] = ($zoom >= 8 && $zoom <= 18) ? $zoom : 13;
        }
        
        // Maps Center Address
        if (isset($input['rdm_maps_center_address'])) {
            $sanitized_input['rdm_maps_center_address'] = sanitize_text_field($input['rdm_maps_center_address']);
        }
        
        // Emergency Contact Settings
        if (isset($input['rdm_emergency_phone'])) {
            $sanitized_input['rdm_emergency_phone'] = sanitize_text_field($input['rdm_emergency_phone']);
        }
        
        if (isset($input['rdm_support_phone'])) {
            $sanitized_input['rdm_support_phone'] = sanitize_text_field($input['rdm_support_phone']);
        }
        
        if (isset($input['rdm_support_email'])) {
            $sanitized_input['rdm_support_email'] = sanitize_email($input['rdm_support_email']);
        }
        
        return $sanitized_input;
    }

    /**
     * Callback for the general settings section description
     *
     * @return void
     */
    public function general_settings_section_callback(): void {
        echo '<p>' . esc_html__('Configure general settings for the RestroReach plugin.', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Callback for the Google Maps API Key field
     *
     * @return void
     */
    public function google_maps_api_key_callback(): void {
        $options = get_option('rdm_plugin_options', array()); // Provide a default empty array
        $api_key = isset($options['rdm_google_maps_api_key']) ? $options['rdm_google_maps_api_key'] : '';
        
        printf(
            '<input type="text" id="rdm_google_maps_api_key" name="rdm_plugin_options[rdm_google_maps_api_key]" value="%s" class="regular-text" />',
            esc_attr($api_key)
        );
        
        // Enhanced guidance and validation display
        echo '<div class="rdm-api-key-guidance">';
        echo '<p class="description">'. esc_html__('Enter your Google Maps JavaScript API key.', 'restaurant-delivery-manager') .'</p>';
        
        if (empty($api_key)) {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>' . esc_html__('API Key Required:', 'restaurant-delivery-manager') . '</strong> ' . 
                 esc_html__('Google Maps functionality requires a valid API key.', 'restaurant-delivery-manager') . '</p>';
            echo '<p>' . esc_html__('To get your API key:', 'restaurant-delivery-manager') . '</p>';
            echo '<ol>';
            echo '<li>' . esc_html__('Visit the Google Cloud Console', 'restaurant-delivery-manager') . ' (<a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a>)</li>';
            echo '<li>' . esc_html__('Create a new project or select an existing one', 'restaurant-delivery-manager') . '</li>';
            echo '<li>' . esc_html__('Enable the following APIs: Maps JavaScript API, Places API, Directions API, Geocoding API', 'restaurant-delivery-manager') . '</li>';
            echo '<li>' . esc_html__('Create credentials (API key) and restrict it to your domain', 'restaurant-delivery-manager') . '</li>';
            echo '<li>' . esc_html__('Copy the API key and paste it above', 'restaurant-delivery-manager') . '</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            // Display validation status if API key is present
            echo '<div id="rdm-api-validation-status">';
            echo '<button type="button" id="rdm-validate-api-key" class="button button-secondary" style="margin-top: 10px;">' . 
                 esc_html__('Validate API Key', 'restaurant-delivery-manager') . '</button>';
            echo '<div id="rdm-validation-result"></div>';
            echo '</div>';
            
            echo '<script>
            document.getElementById("rdm-validate-api-key").addEventListener("click", function() {
                const button = this;
                const resultDiv = document.getElementById("rdm-validation-result");
                const apiKey = document.getElementById("rdm_google_maps_api_key").value;
                
                if (!apiKey.trim()) {
                    resultDiv.innerHTML = "<div class=\"notice notice-error inline\"><p>Please enter an API key first.</p></div>";
                    return;
                }
                
                button.disabled = true;
                button.textContent = "Validating...";
                resultDiv.innerHTML = "<p>Testing API key...</p>";
                
                // Simple validation by trying to load Maps JavaScript API
                const script = document.createElement("script");
                script.src = "https://maps.googleapis.com/maps/api/js?key=" + encodeURIComponent(apiKey) + "&callback=rdmApiKeyValidated";
                script.onerror = function() {
                    resultDiv.innerHTML = "<div class=\"notice notice-error inline\"><p><strong>Invalid API Key:</strong> Unable to load Google Maps API. Please check your API key and ensure the Maps JavaScript API is enabled.</p></div>";
                    button.disabled = false;
                    button.textContent = "Validate API Key";
                };
                
                window.rdmApiKeyValidated = function() {
                    resultDiv.innerHTML = "<div class=\"notice notice-success inline\"><p><strong>Valid API Key:</strong> Google Maps API loaded successfully!</p></div>";
                    button.disabled = false;
                    button.textContent = "Validate API Key";
                    // Clean up
                    delete window.rdmApiKeyValidated;
                    document.head.removeChild(script);
                };
                
                document.head.appendChild(script);
            });
            </script>';
        }
        echo '</div>';
    }

    /**
     * Callback for the integrations settings section description
     *
     * @return void
     */
    public function integrations_section_callback(): void {
        echo '<p>' . esc_html__('Configure integration settings for external services and APIs.', 'restaurant-delivery-manager') . '</p>';
        echo '<p>' . esc_html__('These settings control how RestroReach integrates with Google Maps and other delivery tracking services.', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Callback for the maps default zoom level field
     *
     * @return void
     */
    public function maps_default_zoom_callback(): void {
        $options = get_option('rdm_plugin_options', array());
        $zoom_level = isset($options['rdm_maps_default_zoom']) ? intval($options['rdm_maps_default_zoom']) : 13;
        
        echo '<select id="rdm_maps_default_zoom" name="rdm_plugin_options[rdm_maps_default_zoom]">';
        for ($i = 8; $i <= 18; $i++) {
            printf(
                '<option value="%d" %s>%d - %s</option>',
                $i,
                selected($zoom_level, $i, false),
                $i,
                $this->get_zoom_description($i)
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Default zoom level for maps (8 = country level, 13 = city level, 18 = building level).', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Callback for the maps center address field
     *
     * @return void
     */
    public function maps_center_address_callback(): void {
        $options = get_option('rdm_plugin_options', array());
        $center_address = isset($options['rdm_maps_center_address']) ? $options['rdm_maps_center_address'] : '';
        
        printf(
            '<input type="text" id="rdm_maps_center_address" name="rdm_plugin_options[rdm_maps_center_address]" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($center_address),
            esc_attr__('e.g., New York, NY, USA', 'restaurant-delivery-manager')
        );
        echo '<p class="description">' . esc_html__('Default center address for maps. Leave empty to use automatic geolocation.', 'restaurant-delivery-manager') . '</p>';
        
        if (!empty($center_address)) {
            echo '<button type="button" id="rdm-test-center-address" class="button button-secondary" style="margin-top: 10px;">' . 
                 esc_html__('Test Address', 'restaurant-delivery-manager') . '</button>';
            echo '<div id="rdm-address-test-result"></div>';
            
            echo '<script>
            document.getElementById("rdm-test-center-address").addEventListener("click", function() {
                const button = this;
                const resultDiv = document.getElementById("rdm-address-test-result");
                const address = document.getElementById("rdm_maps_center_address").value;
                
                if (!address.trim()) {
                    resultDiv.innerHTML = "<div class=\"notice notice-error inline\"><p>Please enter an address first.</p></div>";
                    return;
                }
                
                button.disabled = true;
                button.textContent = "Testing...";
                resultDiv.innerHTML = "<p>Testing address...</p>";
                
                // Use WordPress AJAX to test geocoding
                fetch(ajaxurl, {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=rdm_test_geocode&address=" + encodeURIComponent(address) + "&nonce=" + "' . wp_create_nonce('rdm_test_geocode') . '"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = "<div class=\"notice notice-success inline\"><p><strong>Address Found:</strong> " + data.data.formatted_address + " (Lat: " + data.data.lat + ", Lng: " + data.data.lng + ")</p></div>";
                    } else {
                        resultDiv.innerHTML = "<div class=\"notice notice-error inline\"><p><strong>Address Not Found:</strong> " + (data.data || "Unable to geocode address") + "</p></div>";
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = "<div class=\"notice notice-error inline\"><p><strong>Error:</strong> Unable to test address. " + error.message + "</p></div>";
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = "Test Address";
                });
            });
            </script>';
        }
    }

    /**
     * Get zoom level description
     *
     * @param int $zoom_level The zoom level
     * @return string Description of the zoom level
     */
    private function get_zoom_description(int $zoom_level): string {
        $descriptions = array(
            8  => __('Country', 'restaurant-delivery-manager'),
            9  => __('Country', 'restaurant-delivery-manager'),
            10 => __('Region', 'restaurant-delivery-manager'),
            11 => __('Region', 'restaurant-delivery-manager'),
            12 => __('City', 'restaurant-delivery-manager'),
            13 => __('City', 'restaurant-delivery-manager'),
            14 => __('Town', 'restaurant-delivery-manager'),
            15 => __('Town', 'restaurant-delivery-manager'),
            16 => __('Street', 'restaurant-delivery-manager'),
            17 => __('Street', 'restaurant-delivery-manager'),
            18 => __('Building', 'restaurant-delivery-manager')
        );
        
        return isset($descriptions[$zoom_level]) ? $descriptions[$zoom_level] : __('Custom', 'restaurant-delivery-manager');
    }
    
    /**
     * Callback for the contact settings section description
     *
     * @return void
     */
    public function contact_settings_section_callback(): void {
        echo '<p>' . esc_html__('Configure contact information and emergency numbers for agents and customers.', 'restaurant-delivery-manager') . '</p>';
    }
    
    /**
     * Callback for the emergency phone field
     *
     * @return void
     */
    public function emergency_phone_callback(): void {
        $options = get_option('rdm_plugin_options', array());
        $emergency_phone = isset($options['rdm_emergency_phone']) ? $options['rdm_emergency_phone'] : '';
        
        printf(
            '<input type="tel" id="rdm_emergency_phone" name="rdm_plugin_options[rdm_emergency_phone]" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($emergency_phone),
            esc_attr__('e.g., +1-555-911 or 911', 'restaurant-delivery-manager')
        );
        echo '<p class="description">' . esc_html__('Emergency contact number for delivery agents in case of accidents or safety concerns.', 'restaurant-delivery-manager') . '</p>';
    }
    
    /**
     * Callback for the support phone field
     *
     * @return void
     */
    public function support_phone_callback(): void {
        $options = get_option('rdm_plugin_options', array());
        $support_phone = isset($options['rdm_support_phone']) ? $options['rdm_support_phone'] : get_option('woocommerce_store_phone', '');
        
        printf(
            '<input type="tel" id="rdm_support_phone" name="rdm_plugin_options[rdm_support_phone]" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($support_phone),
            esc_attr__('e.g., +1-555-123-4567', 'restaurant-delivery-manager')
        );
        echo '<p class="description">' . esc_html__('Customer support phone number shown on order tracking pages. Defaults to WooCommerce store phone if not set.', 'restaurant-delivery-manager') . '</p>';
    }
    
    /**
     * Callback for the support email field
     *
     * @return void
     */
    public function support_email_callback(): void {
        $options = get_option('rdm_plugin_options', array());
        $support_email = isset($options['rdm_support_email']) ? $options['rdm_support_email'] : get_option('admin_email', '');
        
        printf(
            '<input type="email" id="rdm_support_email" name="rdm_plugin_options[rdm_support_email]" value="%s" class="regular-text" placeholder="%s" />',
            esc_attr($support_email),
            esc_attr__('e.g., support@yourrestaurant.com', 'restaurant-delivery-manager')
        );
        echo '<p class="description">' . esc_html__('Customer support email address shown on order tracking pages. Defaults to WordPress admin email if not set.', 'restaurant-delivery-manager') . '</p>';
    }

    /**
     * Render Agent Portal page (placeholder)
     *
     * @return void
     */
    public function render_agent_portal_page(): void {
        // Check permissions
        if (!current_user_can('rdm_access_agent_portal')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agent Portal', 'restaurant-delivery-manager'); ?></h1>
            <p><?php esc_html_e('Your primary interface is the mobile PWA. This area is for specific administrative tasks if needed.', 'restaurant-delivery-manager'); ?></p>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"><?php esc_html_e('Log out', 'restaurant-delivery-manager'); ?></a></p>
        </div>
        <?php
    }

    /**
     * Render agent live view page
     *
     * @return void
     */
    public function render_agent_live_view_page(): void {
        // Check permissions
        if (!current_user_can('rdm_manage_agents')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
        }
        
        // Get and validate agent ID from URL
        $agent_id = isset($_GET['agent_id']) ? absint($_GET['agent_id']) : 0;
        
        if (!$agent_id) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Agent Live View', 'restaurant-delivery-manager') . '</h1>';
            echo '<div class="notice notice-warning"><p>' . esc_html__('Please select an agent to view their location.', 'restaurant-delivery-manager') . '</p></div>';
            $this->render_agent_selection_form();
            echo '</div>';
            return;
        }
        
        // Verify agent exists and has delivery agent capability
        $agent_user = get_userdata($agent_id);
        if (!$agent_user || !user_can($agent_user, 'rdm_access_agent_portal')) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Agent Live View', 'restaurant-delivery-manager') . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__('Invalid agent ID or user is not a delivery agent.', 'restaurant-delivery-manager') . '</p></div>';
            $this->render_agent_selection_form();
            echo '</div>';
            return;
        }
        
        // Get agent's latest location
        $location_data = RDM_GPS_Tracking::get_latest_agent_location($agent_id);
        
        // Prepare data for JavaScript
        $map_data = array(
            'agent_id' => $agent_id,
            'agent_name' => $agent_user->display_name,
            'agent_email' => $agent_user->user_email,
            'has_location' => !is_null($location_data),
            'lat' => $location_data['latitude'] ?? null,
            'lng' => $location_data['longitude'] ?? null,
            'accuracy' => $location_data['accuracy'] ?? null,
            'battery_level' => $location_data['battery_level'] ?? null,
            'timestamp' => $location_data['timestamp'] ?? null,
            'formatted_time' => $location_data ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($location_data['timestamp'])) : null
        );
        
        // Localize data for JavaScript
        wp_localize_script('rdm-admin-maps', 'rdmAgentLocationData', $map_data);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Agent Live View', 'restaurant-delivery-manager'); ?></h1>
            
            <!-- Agent Selection -->
            <div class="rdm-agent-selector">
                <p>
                    <label for="agent-selector"><?php esc_html_e('Select Agent:', 'restaurant-delivery-manager'); ?></label>
                    <?php $this->render_agent_dropdown($agent_id); ?>
                </p>
            </div>
            
            <!-- Agent Information -->
            <div class="rdm-agent-info-card">
                <h3><?php echo esc_html($agent_user->display_name); ?></h3>
                <p><strong><?php esc_html_e('Email:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($agent_user->user_email); ?></p>
                
                <?php if ($location_data): ?>
                    <p><strong><?php esc_html_e('Last Update:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($map_data['formatted_time']); ?></p>
                    <?php if ($location_data['accuracy']): ?>
                        <p><strong><?php esc_html_e('Accuracy:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html(number_format($location_data['accuracy'], 2)); ?>m</p>
                    <?php endif; ?>
                    <?php if ($location_data['battery_level']): ?>
                        <p><strong><?php esc_html_e('Battery Level:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($location_data['battery_level']); ?>%</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="rdm-no-location"><?php esc_html_e('No location data available for this agent.', 'restaurant-delivery-manager'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Map Container -->
            <div class="rdm-map-container">
                <div id="rdm-agent-live-map-canvas" style="height: 500px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
            </div>
            
            <!-- Location Details -->
            <?php if ($location_data): ?>
            <div class="rdm-location-details">
                <h3><?php esc_html_e('Location Details', 'restaurant-delivery-manager'); ?></h3>
                <table class="widefat">
                    <tr>
                        <th><?php esc_html_e('Latitude:', 'restaurant-delivery-manager'); ?></th>
                        <td><?php echo esc_html(number_format($location_data['latitude'], 6)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Longitude:', 'restaurant-delivery-manager'); ?></th>
                        <td><?php echo esc_html(number_format($location_data['longitude'], 6)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Timestamp:', 'restaurant-delivery-manager'); ?></th>
                        <td><?php echo esc_html($location_data['timestamp']); ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .rdm-agent-selector {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .rdm-agent-info-card {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .rdm-agent-info-card h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .rdm-no-location {
            color: #d63638;
            font-style: italic;
        }
        
        .rdm-map-container {
            margin: 20px 0;
        }
        
        .rdm-location-details {
            margin: 20px 0;
        }
        
        .rdm-location-details table {
            margin-top: 10px;
        }
        
        .rdm-location-details th {
            width: 150px;
            font-weight: bold;
        }
        
        #agent-selector {
            min-width: 250px;
            margin-left: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Render agent selection form for live view
     *
     * @return void
     */
    private function render_agent_selection_form(): void {
        echo '<div class="rdm-agent-selector">';
        echo '<p><label for="agent-selector">' . esc_html__('Select Agent to View:', 'restaurant-delivery-manager') . '</label></p>';
        $this->render_agent_dropdown(0);
        echo '</div>';
    }
    
    /**
     * Render agent dropdown
     *
     * @param int $selected_agent_id Currently selected agent ID
     * @return void
     */
    private function render_agent_dropdown(int $selected_agent_id = 0): void {
        // Get all delivery agents
        $agents = get_users(array(
            'role' => 'delivery_agent',
            'fields' => array('ID', 'display_name', 'user_email'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        if (empty($agents)) {
            echo '<p>' . esc_html__('No delivery agents found.', 'restaurant-delivery-manager') . '</p>';
            return;
        }
        
        echo '<select id="agent-selector" onchange="rdmSelectAgent(this.value)">';
        echo '<option value="">' . esc_html__('-- Select Agent --', 'restaurant-delivery-manager') . '</option>';
        
        foreach ($agents as $agent) {
            $selected = ($agent->ID == $selected_agent_id) ? 'selected' : '';
            echo '<option value="' . esc_attr($agent->ID) . '" ' . $selected . '>';
            echo esc_html($agent->display_name) . ' (' . esc_html($agent->user_email) . ')';
            echo '</option>';
        }
        
        echo '</select>';
        
        // Add JavaScript for dropdown change
        ?>
        <script>
        function rdmSelectAgent(agentId) {
            if (agentId) {
                window.location.href = '<?php echo admin_url('admin.php?page=restroreach-agent-live-view'); ?>&agent_id=' + agentId;
            }
        }
        </script>
        <?php
    }
    
    /**
     * Restrict admin access for custom roles.
     * Redirects them to their specific plugin pages if they try to access general wp-admin areas.
     *
     * @return void
     */
    public function restrict_custom_role_admin_access(): void {
        if (wp_doing_ajax() || wp_doing_cron() || !is_admin()) { // Added !is_admin() check
            return;
        }

        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) { // Ensure user is actually logged in
            return;
        }
        $user_roles = (array) $current_user->roles;

        // If user is admin or has manage_options, do not restrict
        if (in_array('administrator', $user_roles, true) || current_user_can('manage_options')) {
            return;
        }

        global $pagenow;
        $current_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        $is_admin_php = ($pagenow === 'admin.php');
        $is_profile_php = ($pagenow === 'profile.php');
        $is_dashboard_php = ($pagenow === 'index.php'); // WordPress dashboard

        // Allowed common pages for all logged-in users (e.g., for AJAX calls or specific admin-post actions)
        $always_allowed_pagenow = array('admin-ajax.php', 'admin-post.php');
        if (in_array($pagenow, $always_allowed_pagenow, true)) {
            return;
        }

        $redirect_url = '';

        if (in_array('restaurant_manager', $user_roles, true)) {
            $allowed_slugs_manager = array('restroreach-dashboard', 'restroreach-orders');
            // If it's profile.php, or the main WP dashboard, or an admin.php page not in their allowed list
            if ($is_profile_php || $is_dashboard_php || ($is_admin_php && !in_array($current_page_slug, $allowed_slugs_manager, true))) {
                $redirect_url = admin_url('admin.php?page=restroreach-dashboard');
            }
        } elseif (in_array('delivery_agent', $user_roles, true)) {
            $allowed_slugs_agent = array('restroreach-agent-portal');
            // If it's profile.php, or the main WP dashboard, or an admin.php page not in their allowed list
            if ($is_profile_php || $is_dashboard_php || ($is_admin_php && !in_array($current_page_slug, $allowed_slugs_agent, true))) {
                $redirect_url = admin_url('admin.php?page=restroreach-agent-portal');
            }
        }

        if ($redirect_url && $redirect_url !== admin_url($pagenow . (isset($_GET['page']) ? '?page=' . $_GET['page'] : '')) ) {
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Display admin notices
     *
     * @return void
     */
    public function display_admin_notices(): void {
        // Get current screen
        $screen = get_current_screen();
        
        // Only show on plugin pages
        if (strpos($screen->id, 'restroreach') === false) {
            return;
        }
        
        // Check for WooCommerce
        if (!class_exists('WooCommerce')) {
            $this->render_notice(
                __('WooCommerce is required for RestroReach to function properly.', 'restaurant-delivery-manager'),
                'error'
            );
        }
        
        // Check for Google Maps API key
        if (!RDM_Google_Maps::is_api_configured()) {
            $this->render_notice(
                __('Google Maps API key is required for location tracking. Please configure it in the settings.', 'restaurant-delivery-manager'),
                'warning'
            );
        }
    }
    
    /**
     * Render admin notice
     *
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     * @return void
     */
    private function render_notice(string $message, string $type = 'info'): void {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
    
    /**
     * AJAX handler for getting dashboard stats
     *
     * @return void
     */
    public function ajax_get_dashboard_stats(): void {
        // Security check
        if (!check_ajax_referer('rdm-admin-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Permission check
        $required_capability = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
        if (!current_user_can($required_capability)) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
            return;
        }
        
        $stats = array(
            'total_orders' => 0,
            'pending_orders' => 0,
            'active_agents' => 0,
            'completed_today' => 0,
            'revenue_today' => 0,
        );
        
        // Only get WooCommerce data if available
        if (class_exists('WooCommerce') && function_exists('wc_get_orders')) {
            try {
                // Get today's date range
                $today_start = date('Y-m-d 00:00:00');
                $today_end = date('Y-m-d 23:59:59');
                
                // Get pending orders
                $pending_orders = wc_get_orders(array(
                    'status' => array('processing', 'preparing', 'ready'),
                    'limit' => -1,
                ));
                $stats['pending_orders'] = count($pending_orders);
                
                // Get completed orders today
                $completed_today = wc_get_orders(array(
                    'status' => array('delivered', 'completed'),
                    'date_created' => $today_start . '...' . $today_end,
                    'limit' => -1,
                ));
                $stats['completed_today'] = count($completed_today);
                
                // Calculate today's revenue
                $stats['revenue_today'] = array_sum(array_map(function($order) {
                    return $order->get_total();
                }, $completed_today));
                
                // Format revenue
                $stats['revenue_today'] = wc_price($stats['revenue_today']);
                
            } catch (Exception $e) {
                error_log('RestroReach: Error getting dashboard stats - ' . $e->getMessage());
            }
        } else {
            $stats['message'] = __('WooCommerce is not active. Order statistics are not available.', 'restaurant-delivery-manager');
        }
        
        // Get active agents (not WooCommerce dependent)
        $agents = get_users(array(
            'role' => 'delivery_agent',
            'meta_key' => 'rdm_agent_status',
            'meta_value' => 'active',
        ));
        $stats['active_agents'] = count($agents);
        
        // Add system status indicators
        $stats['system_status'] = $this->get_system_status();
        
        // Security: Restrict financial data access to authorized users only
        if (!current_user_can('view_shop_reports') && !current_user_can('manage_woocommerce')) {
            // Remove sensitive financial information for unauthorized users
            unset($stats['revenue_today']);
            if (isset($stats['total_revenue'])) {
                unset($stats['total_revenue']);
            }
            if (isset($stats['average_order_value'])) {
                unset($stats['average_order_value']);
            }
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get recent orders for dashboard
     *
     * @param int $limit Number of orders to retrieve
     * @return array Recent orders data
     */
    private function get_recent_orders(int $limit = 5): array {
        // Return empty array if WooCommerce is not active
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return array();
        }
        
        try {
            $orders = wc_get_orders(array(
                'limit' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => array('processing', 'preparing', 'ready', 'out-for-delivery'),
            ));
            
            $recent_orders = array();
            foreach ($orders as $order) {
                $recent_orders[] = array(
                    'id' => $order->get_id(),
                    'status' => $order->get_status(),
                    'total' => wc_price($order->get_total()),
                    'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'date' => $order->get_date_created()->format('H:i'),
                );
            }
            
            return $recent_orders;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error getting recent orders - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * AJAX handler for getting recent orders
     *
     * @return void
     */
    public function ajax_get_recent_orders(): void {
        // Security check
        if (!check_ajax_referer('rdm-admin-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Permission check
        $required_capability = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
        if (!current_user_can($required_capability)) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
            return;
        }
        
        // Check WooCommerce availability
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array('message' => __('WooCommerce is not active. Orders are not available.', 'restaurant-delivery-manager')));
            return;
        }
        
        $orders = $this->get_recent_orders();
        wp_send_json_success($orders);
    }
    
    /**
     * AJAX handler for fetching orders
     *
     * @return void
     */
    public function ajax_fetch_orders() {
        // Security check
        if (!check_ajax_referer('rdm-admin-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Permission check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
            return;
        }
        
        // Check WooCommerce availability
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array('message' => __('WooCommerce is not active. Orders are not available.', 'restaurant-delivery-manager')));
            return;
        }
        
        $status = sanitize_text_field($_POST['status'] ?? '');
        $orders = $this->get_orders_for_management($status);
        
        wp_send_json_success($orders);
    }
    
    /**
     * AJAX handler for updating order status
     *
     * @return void
     */
    public function ajax_update_order_status() {
        // Security check
        if (!check_ajax_referer('rdm-admin-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'restaurant-delivery-manager')));
            return;
        }
        
        // Permission check
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'restaurant-delivery-manager')));
            return;
        }
        
        // Check WooCommerce availability
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(array('message' => __('WooCommerce is not active. Order management is not available.', 'restaurant-delivery-manager')));
            return;
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');
        
        if (!$order_id || !$new_status) {
            wp_send_json_error(array('message' => __('Invalid order ID or status', 'restaurant-delivery-manager')));
            return;
        }
        
        $result = $this->update_order_status($order_id, $new_status);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Order status updated successfully', 'restaurant-delivery-manager')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update order status', 'restaurant-delivery-manager')));
        }
    }
    
    /**
     * AJAX handler for agent status
     *
     * @return void
     */
    public function ajax_get_agent_status(): void {
        // Check nonce
        check_ajax_referer('rdm-admin-nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        
        // Get agent status
        $agents = $this->get_agent_status();
        
        wp_send_json_success($agents);
    }

    // Get available agents for assignment
    public function ajax_get_available_agents() {
        check_ajax_referer('rdm-orders-nonce', 'nonce');
        if (!current_user_can('rdm_manage_orders')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        $agents = $this->get_available_agents();
        wp_send_json_success($agents);
    }

    // Assign agent to order
    public function ajax_assign_agent_to_order() {
        check_ajax_referer('rdm-orders-nonce', 'nonce');
        if (!current_user_can('rdm_manage_orders')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        $agent_id = absint($_POST['agent_id'] ?? 0);
        $result = $this->assign_agent_to_order($order_id, $agent_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['order_id' => $order_id, 'agent_id' => $agent_id]);
    }

    // Add order note
    public function ajax_add_order_note() {
        check_ajax_referer('rdm-orders-nonce', 'nonce');
        if (!current_user_can('rdm_manage_orders')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        $note_text = sanitize_textarea_field($_POST['note_text'] ?? '');
        $result = $this->add_order_note($order_id, $note_text);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(['order_id' => $order_id]);
    }

    /**
     * Get orders for management interface
     *
     * @param string $status Optional status filter
     * @return array Orders data
     */
    private function get_orders_for_management($status = '') {
        // Return empty array if WooCommerce is not active
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return array();
        }
        
        try {
            $args = array(
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            );
            
            if (!empty($status)) {
                $args['status'] = $status;
            } else {
                // Default to active delivery statuses
                $args['status'] = array('processing', 'preparing', 'ready', 'out-for-delivery');
            }
            
            $orders = wc_get_orders($args);
            $formatted_orders = array();
            
            foreach ($orders as $order) {
                $formatted_orders[] = $this->format_order_card_data($order);
            }
            
            return $formatted_orders;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error getting orders for management - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Format order data for display
     *
     * @param WC_Order $order WooCommerce order object
     * @return array Formatted order data
     */
    private function format_order_card_data($order) {
        if (!class_exists('WooCommerce') || !is_a($order, 'WC_Order')) {
            return array();
        }
        
        return array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_name' => wc_get_order_status_name($order->get_status()),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $order->get_billing_email(),
            'billing_address' => $order->get_formatted_billing_address(),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'total' => $order->get_total(),
            'total_formatted' => wc_price($order->get_total()),
            'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'date_formatted' => $order->get_date_created()->format('M j, Y g:i A'),
            'items' => $this->get_order_items($order),
            'notes' => $this->get_order_notes($order->get_id()),
            'agent' => $this->get_order_agent($order->get_id()),
        );
    }
    
    /**
     * Get order items for display
     *
     * @param WC_Order $order WooCommerce order object
     * @return array Order items
     */
    private function get_order_items($order) {
        if (!class_exists('WooCommerce') || !is_a($order, 'WC_Order')) {
            return array();
        }
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product ? wc_price($product->get_price()) : '',
                'total' => wc_price($item->get_total()),
            );
        }
        
        return $items;
    }
    
    /**
     * Update order status
     *
     * @param int $order_id Order ID
     * @param string $new_status New status
     * @return bool Success status
     */
    private function update_order_status($order_id, $new_status) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return false;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }
            
            // Remove 'wc-' prefix if present
            $new_status = str_replace('wc-', '', $new_status);
            
            $order->update_status($new_status, __('Status updated by RestroReach', 'restaurant-delivery-manager'));
            
            return true;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error updating order status - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available delivery agents
     *
     * @return array Available agents
     */
    private function get_available_agents() {
        $agents = get_users(array(
            'role' => 'delivery_agent',
            'meta_query' => array(
                array(
                    'key' => 'rdm_agent_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));
        
        $available_agents = array();
        foreach ($agents as $agent) {
            $available_agents[] = array(
                'id' => $agent->ID,
                'name' => $agent->display_name,
                'email' => $agent->user_email,
                'phone' => get_user_meta($agent->ID, 'rdm_agent_phone', true),
                'current_orders' => get_user_meta($agent->ID, 'rdm_current_orders', true) ?: 0,
            );
        }
        
        return $available_agents;
    }
    
    /**
     * Assign agent to order
     *
     * @param int $order_id Order ID
     * @param int $agent_id Agent user ID
     * @return bool Success status
     */
    private function assign_agent_to_order($order_id, $agent_id) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return false;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }
            
            // Verify agent exists and has correct role
            $agent = get_user_by('ID', $agent_id);
            if (!$agent || !in_array('delivery_agent', $agent->roles)) {
                return false;
            }
            
            // Update order meta
            $order->update_meta_data('_rdm_assigned_agent', $agent_id);
            $order->update_meta_data('_rdm_assigned_at', current_time('mysql'));
            $order->save();
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Delivery agent %s assigned to this order.', 'restaurant-delivery-manager'),
                    $agent->display_name
                )
            );
            
            // Update agent meta
            $current_orders = get_user_meta($agent_id, 'rdm_current_orders', true) ?: 0;
            update_user_meta($agent_id, 'rdm_current_orders', $current_orders + 1);
            
            return true;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error assigning agent to order - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add note to order
     *
     * @param int $order_id Order ID
     * @param string $note_text Note content
     * @return bool Success status
     */
    private function add_order_note($order_id, $note_text) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return false;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }
            
            $order->add_order_note(sanitize_textarea_field($note_text));
            
            return true;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error adding order note - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order notes
     *
     * @param int $order_id Order ID
     * @return array Order notes
     */
    private function get_order_notes($order_id) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order_notes')) {
            return array();
        }
        
        try {
            $notes = wc_get_order_notes(array(
                'order_id' => $order_id,
                'limit' => 10,
            ));
            
            $formatted_notes = array();
            foreach ($notes as $note) {
                $formatted_notes[] = array(
                    'id' => $note->comment_ID,
                    'content' => $note->comment_content,
                    'date' => $note->comment_date,
                    'author' => $note->comment_author,
                );
            }
            
            return $formatted_notes;
            
        } catch (Exception $e) {
            error_log('RestroReach: Error getting order notes - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get assigned agent for order
     *
     * @param int $order_id Order ID
     * @return array|null Agent data or null
     */
    private function get_order_agent($order_id) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return null;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return null;
            }
            
            $agent_id = $order->get_meta('_rdm_assigned_agent');
            if (!$agent_id) {
                return null;
            }
            
            $agent = get_user_by('ID', $agent_id);
            if (!$agent) {
                return null;
            }
            
            return array(
                'id' => $agent->ID,
                'name' => $agent->display_name,
                'email' => $agent->user_email,
                'phone' => get_user_meta($agent->ID, 'rdm_agent_phone', true),
                'assigned_at' => $order->get_meta('_rdm_assigned_at'),
            );
            
        } catch (Exception $e) {
            error_log('RestroReach: Error getting order agent - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get system status for dashboard
     *
     * @return array System status data
     */
    private function get_system_status(): array {
        $status = array();
        
        // Database Tables Status
        $rdm_database = RDM_Database::instance();
        $tables_status = $rdm_database->get_tables_status();
        $all_tables_ok = $rdm_database->are_all_tables_created();
        
        $status['database_tables'] = array(
            'status' => $all_tables_ok ? 'Active' : 'Inactive',
            'label' => __('Database Tables', 'restaurant-delivery-manager'),
            'message' => $all_tables_ok 
                ? __('All required tables are created', 'restaurant-delivery-manager')
                : __('Some required tables are missing', 'restaurant-delivery-manager'),
            'details' => $tables_status
        );
        
        // Google Maps API Status
        if (class_exists('RDM_Google_Maps')) {
            $google_maps = RDM_Google_Maps::instance();
            $maps_status = $google_maps->get_api_status();
            
            $status['google_maps_api'] = array(
                'status' => $maps_status['status'],
                'label' => __('Google Maps API', 'restaurant-delivery-manager'),
                'message' => $maps_status['message'],
                'has_key' => $maps_status['has_api_key']
            );
        } else {
            $status['google_maps_api'] = array(
                'status' => 'Inactive',
                'label' => __('Google Maps API', 'restaurant-delivery-manager'),
                'message' => __('Google Maps integration not loaded', 'restaurant-delivery-manager'),
                'has_key' => false
            );
        }
        
        // WooCommerce Status
        $status['woocommerce'] = array(
            'status' => class_exists('WooCommerce') ? 'Active' : 'Inactive',
            'label' => __('WooCommerce', 'restaurant-delivery-manager'),
            'message' => class_exists('WooCommerce') 
                ? __('WooCommerce is active and integrated', 'restaurant-delivery-manager')
                : __('WooCommerce is not active', 'restaurant-delivery-manager')
        );
        
        return $status;
    }

    /**
     * AJAX handler for testing geocoding of addresses
     *
     * @return void
     */
    public function ajax_test_geocode(): void {
        // Disable PHP error output to prevent JSON corruption
        error_reporting(0);
        
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rdm_test_geocode')) {
            wp_send_json_error(__('Security check failed', 'restaurant-delivery-manager'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'restaurant-delivery-manager'));
            return;
        }

        $address = sanitize_text_field($_POST['address'] ?? '');
        if (empty($address)) {
            wp_send_json_error(__('Address is required', 'restaurant-delivery-manager'));
            return;
        }

        // Check if Google Maps is configured
        if (!RDM_Google_Maps::is_api_configured()) {
            wp_send_json_error(__('Google Maps API key is not configured. Please configure it in the settings first.', 'restaurant-delivery-manager'));
            return;
        }

        // Use Google Maps class if available
        if (class_exists('RDM_Google_Maps')) {
            try {
                $google_maps = RDM_Google_Maps::instance();
                $coordinates = $google_maps->geocode_address($address);
                
                if ($coordinates && isset($coordinates['lat'], $coordinates['lng'])) {
                    wp_send_json_success(array(
                        'formatted_address' => isset($coordinates['formatted_address']) ? $coordinates['formatted_address'] : $address,
                        'lat' => $coordinates['lat'],
                        'lng' => $coordinates['lng']
                    ));
                } else {
                    wp_send_json_error(__('Unable to geocode the address. Please check if the Google Maps API key is valid and the Geocoding API is enabled.', 'restaurant-delivery-manager'));
                }
            } catch (Exception $e) {
                error_log('RestroReach: Geocoding error - ' . $e->getMessage());
                wp_send_json_error(__('An error occurred while testing the address. Please try again.', 'restaurant-delivery-manager'));
            }
        } else {
            wp_send_json_error(__('Google Maps integration is not available', 'restaurant-delivery-manager'));
        }
    }

    /**
     * Add meta box for order route visualization
     *
     * @since 1.0.0
     * @return void
     */
    public function add_order_route_meta_box(): void {
        // Only add meta box for shop_order post type
        add_meta_box(
            'rdm_order_route_map',
            __('Delivery Route Map', 'restaurant-delivery-manager'),
            array($this, 'render_order_route_meta_box'),
            'shop_order',
            'normal',
            'default'
        );
    }

    /**
     * Render the order route meta box
     *
     * @since 1.0.0
     * @param WP_Post $post The order post object
     * @return void
     */
    public function render_order_route_meta_box($post): void {
        // Security check
        if (!current_user_can('edit_shop_order', $post->ID)) {
            echo '<p>' . esc_html__('You do not have permission to view this information.', 'restaurant-delivery-manager') . '</p>';
            return;
        }

        $order = wc_get_order($post->ID);
        if (!$order) {
            echo '<p>' . esc_html__('Invalid order.', 'restaurant-delivery-manager') . '</p>';
            return;
        }

        // Only show for orders that are out for delivery or in testing mode
        $order_status = $order->get_status();
        $valid_statuses = array('out-for-delivery', 'wc-out-for-delivery');
        
        // Allow testing mode for administrators in development
        $is_testing_mode = defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options');
        $testing_statuses = array('processing', 'on-hold', 'pending');
        
        if (!in_array($order_status, $valid_statuses) && !($is_testing_mode && in_array($order_status, $testing_statuses))) {
            echo '<p>' . esc_html__('Route map is only available for orders that are out for delivery.', 'restaurant-delivery-manager');
            if ($is_testing_mode) {
                echo '<br><small>' . esc_html__('(Testing mode: Some order statuses are allowed for administrators)', 'restaurant-delivery-manager') . '</small>';
            }
            echo '</p>';
            return;
        }

        // Check if Google Maps is enabled
        if (!class_exists('RDM_Google_Maps') || !RDM_Google_Maps::is_enabled()) {
            echo '<p>' . esc_html__('Google Maps integration is not configured. Please set up your Google Maps API key in the plugin settings.', 'restaurant-delivery-manager') . '</p>';
            return;
        }

        global $wpdb;

        // Get customer address
        $customer_address_string = $order->get_formatted_shipping_address();
        if (empty($customer_address_string)) {
            $customer_address_string = $order->get_formatted_billing_address();
        }

        if (empty($customer_address_string)) {
            echo '<p>' . esc_html__('No customer address available for this order.', 'restaurant-delivery-manager') . '</p>';
            return;
        }

        // Get assigned agent ID from order assignments table
        $agent_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT agent_id FROM {$wpdb->prefix}rr_order_assignments 
             WHERE order_id = %d AND status = %s 
             ORDER BY assigned_at DESC LIMIT 1",
            $order->get_id(),
            'assigned'
        ));

        // Fetch required data
        $restaurant_coords = RDM_Google_Maps::get_restaurant_coordinates();
        $agent_location = null;
        $agent_name = __('Unknown Agent', 'restaurant-delivery-manager');

        if ($agent_user_id) {
            if (class_exists('RDM_GPS_Tracking')) {
                $agent_location = RDM_GPS_Tracking::get_latest_agent_location((int)$agent_user_id);
            }
            $agent_data = get_userdata((int)$agent_user_id);
            if ($agent_data) {
                $agent_name = $agent_data->display_name ?: ($agent_data->first_name . ' ' . $agent_data->last_name);
                $agent_name = trim($agent_name) ?: __('Unknown Agent', 'restaurant-delivery-manager');
            }
        }

        // Prepare map data
        $map_data = array(
            'restaurantCoords'   => $restaurant_coords,
            'customerAddress'    => $customer_address_string,
            'agentLocation'      => $agent_location,
            'agentName'          => $agent_name,
            'googleMapsApiKey'   => RDM_Google_Maps::get_api_key(),
            'nonce'              => wp_create_nonce('rdm_admin_maps_nonce'),
            'defaultLat'         => get_option('rdm_default_map_lat', '40.7128'),
            'defaultLng'         => get_option('rdm_default_map_lng', '-74.0060'),
        );

        // Enqueue necessary scripts
        wp_enqueue_script(
            'rdm-admin-maps',
            RDM_PLUGIN_URL . 'assets/js/rdm-admin-maps.js',
            array('jquery'),
            RDM_VERSION,
            true
        );

        // Localize the map data
        wp_localize_script('rdm-admin-maps', 'rdmOrderRouteData', $map_data);

        // Enqueue Google Maps API script
        if (class_exists('RDM_Google_Maps') && RDM_Google_Maps::is_enabled()) {
            wp_enqueue_script(
                'rdm-google-maps-api',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr(RDM_Google_Maps::get_api_key()) . '&libraries=places,geometry,directions&callback=rdmInitAdminMaps&v=weekly',
                array(),
                null,
                true
            );
        }

        // Display the map container and order information
        ?>
        <div class="rdm-order-route-meta-box">
            <div class="rdm-order-route-info">
                <h4><?php esc_html_e('Delivery Information', 'restaurant-delivery-manager'); ?></h4>
                <p><strong><?php esc_html_e('Customer Address:', 'restaurant-delivery-manager'); ?></strong><br>
                   <?php echo esc_html($customer_address_string); ?></p>
                
                <?php if ($agent_user_id && $agent_name): ?>
                    <p><strong><?php esc_html_e('Assigned Agent:', 'restaurant-delivery-manager'); ?></strong><br>
                                                                                         <?php echo esc_html($agent_name); ?></p>
                    
                    <?php if ($agent_location): ?>
                        <p><strong><?php esc_html_e('Last Known Location:', 'restaurant-delivery-manager'); ?></strong><br>
                           <?php echo esc_html(sprintf(__('Updated: %s', 'restaurant-delivery-manager'), 
                               date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                               strtotime($agent_location['timestamp'])))); ?></p>
                    <?php else: ?>
                        <p><em><?php esc_html_e('No recent location data available for this agent.', 'restaurant-delivery-manager'); ?></em></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><em><?php esc_html_e('No agent assigned to this order.', 'restaurant-delivery-manager'); ?></em></p>
                <?php endif; ?>
            </div>
            
            <div class="rdm-order-route-map-container">
                <h4><?php esc_html_e('Route Map', 'restaurant-delivery-manager'); ?></h4>
                <div id="rdm-order-route-map-canvas" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
            </div>
        </div>

        <style>
        .rdm-order-route-meta-box {
            margin-top: 10px;
        }
        .rdm-order-route-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
        }
        .rdm-order-route-info h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        .rdm-order-route-info p {
            margin-bottom: 10px;
        }
        .rdm-order-route-info p:last-child {
            margin-bottom: 0;
        }
        .rdm-order-route-map-container h4 {
            margin-bottom: 10px;
            color: #333;
        }
        </style>
        <?php
    }
}