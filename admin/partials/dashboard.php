<?php
/**
 * RestroReach Dashboard Template
 *
 * @package RestroReach
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rdm-dashboard">
    <div class="rdm-dashboard-header">
        <h1 class="rdm-dashboard-title"><?php esc_html_e('RestroReach Dashboard', 'restaurant-delivery-manager'); ?></h1>
        <div class="rdm-dashboard-actions">
            <button type="button" class="rdm-button rdm-button-secondary rdm-refresh-dashboard">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'restaurant-delivery-manager'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=restroreach-settings')); ?>" class="rdm-button rdm-button-secondary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Settings', 'restaurant-delivery-manager'); ?>
            </a>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="rdm-stats-grid">
        <!-- Stats will be loaded dynamically via JavaScript -->
    </div>

    <!-- Recent Orders -->
    <div class="rdm-recent-orders">
        <!-- Orders will be loaded dynamically via JavaScript -->
    </div>

    <!-- Delivery Agents -->
    <div class="rdm-section-header">
        <h2 class="rdm-section-title"><?php esc_html_e('Delivery Agents', 'restaurant-delivery-manager'); ?></h2>
        <a href="<?php echo esc_url(admin_url('admin.php?page=restroreach-agents')); ?>" class="rdm-button rdm-button-secondary">
            <?php esc_html_e('Manage Agents', 'restaurant-delivery-manager'); ?>
        </a>
    </div>
    <div class="rdm-agents-grid">
        <!-- Agents will be loaded dynamically via JavaScript -->
    </div>

    <!-- Quick Actions -->
    <div class="rdm-quick-actions">
        <div class="rdm-section-header">
            <h2 class="rdm-section-title"><?php esc_html_e('Quick Actions', 'restaurant-delivery-manager'); ?></h2>
        </div>
        <div class="rdm-actions-grid">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=shop_order')); ?>" class="rdm-button rdm-button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('New Order', 'restaurant-delivery-manager'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=restroreach-agents&action=new')); ?>" class="rdm-button rdm-button-primary">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e('Add Agent', 'restaurant-delivery-manager'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wc-reports&tab=orders&report=sales_by_date')); ?>" class="rdm-button rdm-button-primary">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e('View Reports', 'restaurant-delivery-manager'); ?>
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="rdm-system-status">
        <div class="rdm-section-header">
            <h2 class="rdm-section-title"><?php esc_html_e('System Status', 'restaurant-delivery-manager'); ?></h2>
        </div>
        <div class="rdm-status-grid">
            <?php
            // Check WooCommerce status
            $wc_status = class_exists('WooCommerce') ? 'active' : 'inactive';
            $wc_message = class_exists('WooCommerce') 
                ? __('WooCommerce is active and integrated', 'restaurant-delivery-manager')
                : __('WooCommerce is not active', 'restaurant-delivery-manager');
            ?>
            <div class="rdm-status-card status-<?php echo esc_attr($wc_status); ?>">
                <div class="rdm-status-header">
                    <h4 class="rdm-status-title"><?php esc_html_e('WooCommerce', 'restaurant-delivery-manager'); ?></h4>
                    <span class="rdm-status-indicator <?php echo esc_attr($wc_status); ?>">
                        <?php echo esc_html(ucfirst($wc_status)); ?>
                    </span>
                </div>
                <p class="rdm-status-message"><?php echo esc_html($wc_message); ?></p>
            </div>

            <?php
            // Check Google Maps API key using the proper method
            $maps_status = 'inactive';
            $maps_message = __('Google Maps integration not loaded', 'restaurant-delivery-manager');
            if (class_exists('RDM_Google_Maps')) {
                $google_maps = RDM_Google_Maps::instance();
                $api_status = $google_maps->get_api_status();
                
                // Derive status string from existing keys
                if (isset($api_status['configured']) && isset($api_status['valid'])) {
                    if ($api_status['configured'] && $api_status['valid']) {
                        $display_status_string = 'active';
                    } elseif ($api_status['configured']) {
                        $display_status_string = 'configured';
                    } else {
                        $display_status_string = 'inactive';
                    }
                    $maps_status = strtolower($display_status_string);
                } else {
                    $maps_status = 'inactive';
                }
                
                $maps_message = isset($api_status['message']) ? $api_status['message'] : __('Status unknown', 'restaurant-delivery-manager');
            }
            ?>
            <div class="rdm-status-card status-<?php echo esc_attr($maps_status); ?>">
                <div class="rdm-status-header">
                    <h4 class="rdm-status-title"><?php esc_html_e('Google Maps API', 'restaurant-delivery-manager'); ?></h4>
                    <span class="rdm-status-indicator <?php echo esc_attr($maps_status); ?>">
                        <?php echo esc_html(ucfirst($maps_status)); ?>
                    </span>
                </div>
                <p class="rdm-status-message"><?php echo esc_html($maps_message); ?></p>
            </div>

            <?php
            // Check database tables using the proper method with detailed status
            $db_status = 'inactive';
            $db_message = __('Database not loaded', 'restaurant-delivery-manager');
            $detailed_status = array();
            
            if (class_exists('RDM_Database')) {
                $database = RDM_Database::instance();
                $tables_status = $database->get_tables_status();
                $all_tables_ok = $database->are_all_tables_created();
                
                $db_status = $all_tables_ok ? 'active' : 'inactive';
                
                // Use the database class method to get accurate status
                foreach ($tables_status as $table_key => $table_info) {
                    $table_labels = array(
                        'delivery_agents' => __('Delivery Agents', 'restaurant-delivery-manager'),
                        'order_assignments' => __('Order Assignments', 'restaurant-delivery-manager'),
                        'location_tracking' => __('Location Tracking', 'restaurant-delivery-manager'),
                        'delivery_notes' => __('Delivery Notes', 'restaurant-delivery-manager'),
                        'delivery_areas' => __('Delivery Areas', 'restaurant-delivery-manager')
                    );
                    
                    $detailed_status[] = array(
                        'name' => isset($table_labels[$table_key]) ? $table_labels[$table_key] : ucfirst(str_replace('_', ' ', $table_key)),
                        'status' => $table_info['exists'] ? 'exists' : 'missing',
                        'table_name' => $table_info['full_name']
                    );
                }
                
                $missing_count = count(array_filter($detailed_status, function($table) {
                    return $table['status'] === 'missing';
                }));
                
                if ($missing_count === 0) {
                    $db_message = __('All 5 required tables are created', 'restaurant-delivery-manager');
                } else {
                    $db_message = sprintf(
                        _n('%d table is missing', '%d tables are missing', $missing_count, 'restaurant-delivery-manager'),
                        $missing_count
                    );
                }
            }
            ?>
            <div class="rdm-status-card status-<?php echo esc_attr($db_status); ?>">
                <div class="rdm-status-header">
                    <h4 class="rdm-status-title"><?php esc_html_e('Database Tables', 'restaurant-delivery-manager'); ?></h4>
                    <span class="rdm-status-indicator <?php echo esc_attr($db_status); ?>">
                        <?php echo esc_html(ucfirst($db_status)); ?>
                    </span>
                </div>
                <p class="rdm-status-message"><?php echo esc_html($db_message); ?></p>
                
                <?php if (!empty($detailed_status)): ?>
                <div class="rdm-table-details" style="margin-top: 15px;">
                    <details style="cursor: pointer;">
                        <summary style="font-weight: 600; margin-bottom: 10px;">
                            <?php esc_html_e('Table Details', 'restaurant-delivery-manager'); ?>
                        </summary>
                        <div style="padding-left: 15px;">
                            <?php foreach ($detailed_status as $table): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #eee;">
                                <span style="font-weight: 500;"><?php echo esc_html($table['name']); ?></span>
                                <span class="rdm-table-status status-<?php echo esc_attr($table['status']); ?>" 
                                      style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                    <?php echo $table['status'] === 'exists' ? esc_html__('✓ EXISTS', 'restaurant-delivery-manager') : esc_html__('✗ MISSING', 'restaurant-delivery-manager'); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php if ($db_status === 'inactive'): ?>
                    <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                        <p style="margin: 0; font-size: 13px;">
                            <?php esc_html_e('To fix missing tables, go to:', 'restaurant-delivery-manager'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=restroreach-database-tools')); ?>" style="font-weight: bold;">
                                <?php esc_html_e('RestroReach → Database Tools', 'restaurant-delivery-manager'); ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles for dashboard template */
.rdm-quick-actions {
    margin-bottom: 30px;
}

/* Table status styles */
.rdm-table-status.status-exists {
    background-color: #d4edda;
    color: #155724;
}

.rdm-table-status.status-missing {
    background-color: #f8d7da;
    color: #721c24;
}

.rdm-table-details summary:hover {
    color: #0073aa;
}

.rdm-table-details .rdm-table-status {
    font-family: monospace;
}

.rdm-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.rdm-actions-grid .rdm-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    text-align: center;
}

.rdm-system-status {
    margin-bottom: 30px;
}

.rdm-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.rdm-status-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.rdm-status-title {
    font-size: 14px;
    color: #646970;
    margin: 0 0 10px;
}

.rdm-status-value {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.rdm-status-value.positive {
    color: #00a32a;
}

.rdm-status-value.negative {
    color: #d63638;
}

@media screen and (max-width: 782px) {
    .rdm-actions-grid,
    .rdm-status-grid {
        grid-template-columns: 1fr;
    }
}
</style>