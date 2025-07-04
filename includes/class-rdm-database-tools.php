<?php
/**
 * RestroReach Database Tools Admin Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RDM_Database_Tools {
    
    /**
     * Initialize the database tools
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('wp_ajax_rdm_recreate_tables', array(__CLASS__, 'ajax_recreate_tables'));
        add_action('wp_ajax_rdm_check_table_status', array(__CLASS__, 'ajax_check_table_status'));
    }

    /**
     * Instance method for admin page (called from RDM_Admin_Interface)
     * 
     * @return void
     */
    public function render_admin_page(): void {
        // Call the static method
        self::admin_page();
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'restaurant-delivery-manager',
            __('Database Tools', 'restaurant-delivery-manager'),
            __('Database Tools', 'restaurant-delivery-manager'),
            'manage_options',
            'rdm-database-tools',
            array(__CLASS__, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     */
    public static function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('RestroReach Database Tools', 'restaurant-delivery-manager'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Table Status', 'restaurant-delivery-manager'); ?></h2>
                <div id="rdm-table-status">
                    <p><?php esc_html_e('Loading...', 'restaurant-delivery-manager'); ?></p>
                </div>
                <button type="button" id="rdm-check-status" class="button">
                    <?php esc_html_e('Check Status', 'restaurant-delivery-manager'); ?>
                </button>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Manual Table Creation', 'restaurant-delivery-manager'); ?></h2>
                <p><?php esc_html_e('If tables are missing, you can manually recreate them here.', 'restaurant-delivery-manager'); ?></p>
                <p><strong><?php esc_html_e('Warning:', 'restaurant-delivery-manager'); ?></strong> 
                   <?php esc_html_e('This will drop existing tables and recreate them. All data will be lost!', 'restaurant-delivery-manager'); ?></p>
                
                <button type="button" id="rdm-recreate-tables" class="button button-primary">
                    <?php esc_html_e('Recreate All Tables', 'restaurant-delivery-manager'); ?>
                </button>
                
                <div id="rdm-recreate-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Check status on page load
            checkTableStatus();
            
            // Check status button
            $('#rdm-check-status').on('click', checkTableStatus);
            
            // Recreate tables button
            $('#rdm-recreate-tables').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure? This will delete all existing data!', 'restaurant-delivery-manager')); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'restaurant-delivery-manager')); ?>');
                $('#rdm-recreate-results').html('<p><?php echo esc_js(__('Creating tables...', 'restaurant-delivery-manager')); ?></p>');
                
                $.post(ajaxurl, {
                    action: 'rdm_recreate_tables',
                    nonce: '<?php echo wp_create_nonce('rdm_database_tools'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#rdm-recreate-results').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        checkTableStatus(); // Refresh status
                    } else {
                        $('#rdm-recreate-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#rdm-recreate-tables').prop('disabled', false).text('<?php echo esc_js(__('Recreate All Tables', 'restaurant-delivery-manager')); ?>');
                }).fail(function() {
                    $('#rdm-recreate-results').html('<div class="notice notice-error"><p><?php echo esc_js(__('AJAX request failed', 'restaurant-delivery-manager')); ?></p></div>');
                    $('#rdm-recreate-tables').prop('disabled', false).text('<?php echo esc_js(__('Recreate All Tables', 'restaurant-delivery-manager')); ?>');
                });
            });
            
            function checkTableStatus() {
                $('#rdm-table-status').html('<p><?php echo esc_js(__('Checking...', 'restaurant-delivery-manager')); ?></p>');
                
                $.post(ajaxurl, {
                    action: 'rdm_check_table_status',
                    nonce: '<?php echo wp_create_nonce('rdm_database_tools'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Table</th><th>Status</th></tr></thead><tbody>';
                        $.each(response.data.tables, function(key, table) {
                            var statusClass = table.exists ? 'text-success' : 'text-danger';
                            var statusText = table.exists ? '✓ EXISTS' : '✗ MISSING';
                            html += '<tr><td>' + table.name + '</td><td class="' + statusClass + '">' + statusText + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        html += '<p><strong>Overall Status:</strong> ' + (response.data.all_created ? '<span style="color: green;">✓ ACTIVE</span>' : '<span style="color: red;">✗ INACTIVE</span>') + '</p>';
                        $('#rdm-table-status').html(html);
                    } else {
                        $('#rdm-table-status').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $('#rdm-table-status').html('<div class="notice notice-error"><p><?php echo esc_js(__('Failed to check table status', 'restaurant-delivery-manager')); ?></p></div>');
                });
            }
        });
        </script>
        
        <style>
        .text-success { color: #46b450; }
        .text-danger { color: #dc3232; }
        .card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; }
        .card h2 { margin-top: 0; }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for recreating tables
     */
    public static function ajax_recreate_tables() {
        check_ajax_referer('rdm_database_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        
        if (!class_exists('RDM_Database')) {
            wp_send_json_error(__('Database class not available', 'restaurant-delivery-manager'));
        }
        
        try {
            $database = RDM_Database::instance();
            $results = $database->force_recreate_tables();
            
            if ($results['success']) {
                wp_send_json_success($results);
            } else {
                wp_send_json_error($results['message'] . ' Errors: ' . implode(', ', $results['errors']));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Error: ', 'restaurant-delivery-manager') . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for checking table status
     */
    public static function ajax_check_table_status() {
        check_ajax_referer('rdm_database_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'restaurant-delivery-manager'));
        }
        
        if (!class_exists('RDM_Database')) {
            wp_send_json_error(__('Database class not available', 'restaurant-delivery-manager'));
        }
        
        try {
            $database = RDM_Database::instance();
            $tables = $database->get_tables_status();
            $all_created = $database->are_all_tables_created();
            
            wp_send_json_success(array(
                'tables' => $tables,
                'all_created' => $all_created
            ));
        } catch (Exception $e) {
            wp_send_json_error(__('Error: ', 'restaurant-delivery-manager') . $e->getMessage());
        }
    }
}

// Initialize if in admin and WordPress functions are available
if (function_exists('is_admin') && is_admin()) {
    RDM_Database_Tools::init();
}
?>
