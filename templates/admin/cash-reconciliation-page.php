<?php
/**
 * Admin Cash Reconciliation Page
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'restaurant-delivery-manager'));
}

// Get payment statistics
$payments_class = RDM_Payments::instance();
$today = current_time('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Get payment statistics for today and yesterday
$today_stats = $payments_class->get_payment_statistics(array('date_from' => $today, 'date_to' => $today));
$yesterday_stats = $payments_class->get_payment_statistics(array('date_from' => $yesterday, 'date_to' => $yesterday));

// Get agents with pending reconciliations
global $wpdb;
$reconciliation_table = $wpdb->prefix . 'rr_cash_reconciliation';
$agents_table = $wpdb->prefix . 'rr_delivery_agents';

$pending_reconciliations = $wpdb->get_results(
    "SELECT r.*, u.display_name as agent_name
     FROM $reconciliation_table r
     INNER JOIN $agents_table a ON r.agent_id = a.id
     INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
     WHERE r.status IN ('pending', 'submitted')
     ORDER BY r.reconciliation_date DESC
     LIMIT 10"
);

?>
<div class="wrap rdm-admin-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Cash Reconciliation', 'restaurant-delivery-manager'); ?>
    </h1>
    
    <hr class="wp-header-end">

    <!-- Payment Statistics Cards -->
    <div class="rdm-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <!-- Today's Stats -->
        <div class="rdm-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #007cba;">
            <h3 style="margin: 0 0 10px; color: #333; font-size: 16px;"><?php esc_html_e("Today's Payments", 'restaurant-delivery-manager'); ?></h3>
            <div style="font-size: 24px; font-weight: 600; color: #007cba; margin-bottom: 10px;">
                <?php echo wc_price($today_stats['summary']['total_collected']); ?>
            </div>
            <div style="font-size: 14px; color: #666;">
                <?php echo sprintf(__('%d transactions', 'restaurant-delivery-manager'), $today_stats['summary']['total_transactions']); ?>
            </div>
        </div>

        <!-- Yesterday's Stats -->
        <div class="rdm-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
            <h3 style="margin: 0 0 10px; color: #333; font-size: 16px;"><?php esc_html_e("Yesterday's Payments", 'restaurant-delivery-manager'); ?></h3>
            <div style="font-size: 24px; font-weight: 600; color: #28a745; margin-bottom: 10px;">
                <?php echo wc_price($yesterday_stats['summary']['total_collected']); ?>
            </div>
            <div style="font-size: 14px; color: #666;">
                <?php echo sprintf(__('%d transactions', 'restaurant-delivery-manager'), $yesterday_stats['summary']['total_transactions']); ?>
            </div>
        </div>

        <!-- COD Pending -->
        <div class="rdm-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
            <h3 style="margin: 0 0 10px; color: #333; font-size: 16px;"><?php esc_html_e('COD Pending', 'restaurant-delivery-manager'); ?></h3>
            <div style="font-size: 24px; font-weight: 600; color: #ffc107; margin-bottom: 10px;">
                <?php 
                // Calculate pending amount from pending count and average order value
                $pending_amount = ($today_stats['summary']['pending_count'] ?? 0) * ($today_stats['summary']['avg_order_value'] ?? 0);
                echo wc_price($pending_amount); 
                ?>
            </div>
            <div style="font-size: 14px; color: #666;">
                <?php echo sprintf(__('%d orders', 'restaurant-delivery-manager'), $today_stats['summary']['pending_count'] ?? 0); ?>
            </div>
        </div>

        <!-- Total Change Given -->
        <div class="rdm-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;">
            <h3 style="margin: 0 0 10px; color: #333; font-size: 16px;"><?php esc_html_e("Today's Change", 'restaurant-delivery-manager'); ?></h3>
            <div style="font-size: 24px; font-weight: 600; color: #dc3545; margin-bottom: 10px;">
                <?php echo wc_price($today_stats['summary']['total_change']); ?>
            </div>
            <div style="font-size: 14px; color: #666;">
                <?php esc_html_e('Given to customers', 'restaurant-delivery-manager'); ?>
            </div>
        </div>
    </div>

    <!-- Pending Reconciliations -->
    <div class="rdm-reconciliation-section">
        <h2><?php esc_html_e('Pending Cash Reconciliations', 'restaurant-delivery-manager'); ?></h2>
        
        <?php if (empty($pending_reconciliations)): ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('No pending cash reconciliations. All agents are up to date!', 'restaurant-delivery-manager'); ?></p>
            </div>
        <?php else: ?>
            <div class="rdm-reconciliation-table-container" style="background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Agent', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Date', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Collections', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Change Given', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Net Amount', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Variance', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Status', 'restaurant-delivery-manager'); ?></th>
                            <th><?php esc_html_e('Actions', 'restaurant-delivery-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_reconciliations as $reconciliation): 
                            $variance = abs((float) ($reconciliation->variance ?? 0));
                            $is_high_discrepancy = $variance > 50;
                            $row_class = $is_high_discrepancy ? 'rdm-high-discrepancy' : '';
                        ?>
                            <tr class="<?php echo esc_attr($row_class); ?>">
                                <td>
                                    <strong><?php echo esc_html($reconciliation->agent_name); ?></strong>
                                    <?php if ($is_high_discrepancy): ?>
                                        <span class="rdm-discrepancy-flag" title="<?php esc_attr_e('High discrepancy detected', 'restaurant-delivery-manager'); ?>">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(wp_date(get_option('date_format'), strtotime($reconciliation->reconciliation_date))); ?>
                                </td>
                                <td>
                                    <?php echo wc_price($reconciliation->total_collections); ?>
                                </td>
                                <td>
                                    <?php echo wc_price($reconciliation->total_change_given); ?>
                                </td>
                                <td>
                                    <strong><?php echo wc_price($reconciliation->closing_balance); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $variance_amount = $reconciliation->variance ?? 0;
                                    $variance_class = $variance_amount > 0 ? 'text-success' : ($variance_amount < 0 ? 'text-danger' : '');
                                    ?>
                                    <span class="<?php echo esc_attr($variance_class); ?>">
                                        <?php echo wc_price($variance_amount); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = $reconciliation->status === 'submitted' ? 'submitted' : 'pending';
                                    $status_text = $reconciliation->status === 'submitted' ? __('Submitted', 'restaurant-delivery-manager') : __('Pending', 'restaurant-delivery-manager');
                                    ?>
                                    <span class="rdm-cash-status <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reconciliation->status === 'submitted'): ?>
                                        <button type="button" 
                                                class="button button-primary rdm-verify-reconciliation-btn"
                                                data-reconciliation-id="<?php echo esc_attr($reconciliation->id); ?>">
                                            <?php esc_html_e('Verify', 'restaurant-delivery-manager'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #666;"><?php esc_html_e('Waiting for agent', 'restaurant-delivery-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Reports Section -->
    <div class="rdm-reports-section" style="margin-top: 30px;">
        <h2><?php esc_html_e('Payment Reports', 'restaurant-delivery-manager'); ?></h2>
        
        <div class="rdm-report-controls" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <form method="get" id="rdm-payment-report-form">
                <input type="hidden" name="page" value="rdm-cash-reconciliation">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <div>
                        <label for="report-date-from"><?php esc_html_e('From Date', 'restaurant-delivery-manager'); ?></label>
                        <input type="date" 
                               id="report-date-from" 
                               name="date_from" 
                               value="<?php echo esc_attr($_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'))); ?>"
                               class="regular-text">
                    </div>
                    
                    <div>
                        <label for="report-date-to"><?php esc_html_e('To Date', 'restaurant-delivery-manager'); ?></label>
                        <input type="date" 
                               id="report-date-to" 
                               name="date_to" 
                               value="<?php echo esc_attr($_GET['date_to'] ?? current_time('Y-m-d')); ?>"
                               class="regular-text">
                    </div>
                    
                    <div>
                        <label for="report-agent"><?php esc_html_e('Agent', 'restaurant-delivery-manager'); ?></label>
                        <select id="report-agent" name="agent_id" class="regular-text">
                            <option value=""><?php esc_html_e('All Agents', 'restaurant-delivery-manager'); ?></option>
                            <?php
                            $agents = $wpdb->get_results(
                                "SELECT a.id, u.display_name 
                                 FROM $agents_table a 
                                 INNER JOIN {$wpdb->users} u ON a.user_id = u.ID 
                                 WHERE a.status = 'active' 
                                 ORDER BY u.display_name"
                            );
                            foreach ($agents as $agent):
                            ?>
                                <option value="<?php echo esc_attr($agent->id); ?>" <?php selected($_GET['agent_id'] ?? '', $agent->id); ?>>
                                    <?php echo esc_html($agent->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Generate Report', 'restaurant-delivery-manager'); ?>
                        </button>
                        <button type="button" class="button" onclick="window.print()">
                            <?php esc_html_e('Print', 'restaurant-delivery-manager'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="export-csv-button">
                            <?php esc_html_e('Export CSV', 'restaurant-delivery-manager'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Results -->
        <?php if (!empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
            <?php
            $report_filters = array();
            if (!empty($_GET['date_from'])) $report_filters['date_from'] = sanitize_text_field($_GET['date_from']);
            if (!empty($_GET['date_to'])) $report_filters['date_to'] = sanitize_text_field($_GET['date_to']);
            if (!empty($_GET['agent_id'])) $report_filters['agent_id'] = absint($_GET['agent_id']);
            
            $report_stats = $payments_class->get_payment_statistics($report_filters);
            ?>
            
            <div class="rdm-report-results" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e('Report Results', 'restaurant-delivery-manager'); ?></h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div class="rdm-report-stat">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;"><?php esc_html_e('Total Transactions', 'restaurant-delivery-manager'); ?></div>
                        <div style="font-size: 20px; font-weight: 600; color: #333;"><?php echo esc_html($report_stats['summary']['total_transactions']); ?></div>
                    </div>
                    
                    <div class="rdm-report-stat">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;"><?php esc_html_e('Total Amount', 'restaurant-delivery-manager'); ?></div>
                        <div style="font-size: 20px; font-weight: 600; color: #333;"><?php echo wc_price($report_stats['summary']['total_amount']); ?></div>
                    </div>
                    
                    <div class="rdm-report-stat">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;"><?php esc_html_e('Total Collected', 'restaurant-delivery-manager'); ?></div>
                        <div style="font-size: 20px; font-weight: 600; color: #007cba;"><?php echo wc_price($report_stats['summary']['total_collected']); ?></div>
                    </div>
                    
                    <div class="rdm-report-stat">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;"><?php esc_html_e('Total Change', 'restaurant-delivery-manager'); ?></div>
                        <div style="font-size: 20px; font-weight: 600; color: #dc3545;"><?php echo wc_price($report_stats['summary']['total_change']); ?></div>
                    </div>
                </div>

                <!-- Breakdown by Payment Type -->
                <?php if (!empty($report_stats['summary']['by_type'])): ?>
                    <h4><?php esc_html_e('By Payment Type', 'restaurant-delivery-manager'); ?></h4>
                    <table class="wp-list-table widefat fixed striped" style="margin: 10px 0;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Payment Type', 'restaurant-delivery-manager'); ?></th>
                                <th><?php esc_html_e('Count', 'restaurant-delivery-manager'); ?></th>
                                <th><?php esc_html_e('Amount', 'restaurant-delivery-manager'); ?></th>
                                <th><?php esc_html_e('Collected', 'restaurant-delivery-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_stats['summary']['by_type'] as $type => $data): ?>
                                <tr>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?></td>
                                    <td><?php echo esc_html($data['count']); ?></td>
                                    <td><?php echo wc_price($data['amount']); ?></td>
                                    <td><?php echo wc_price($data['collected']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for admin functionality -->
<script>
jQuery(document).ready(function($) {
    // Handle reconciliation verification
    $('.rdm-verify-reconciliation-btn').on('click', function() {
        const button = $(this);
        const reconciliationId = button.data('reconciliation-id');
        
        if (confirm('Are you sure you want to verify this cash reconciliation?')) {
            button.prop('disabled', true).text('Verifying...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_verify_reconciliation',
                    reconciliation_id: reconciliationId,
                    nonce: '<?php echo wp_create_nonce('rdm_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'An error occurred');
                    }
                },
                complete: function() {
                    button.prop('disabled', false).text('Verify');
                }
            });
        }
    });
});
</script>

<style>
/* Print styles */
@media print {
    .rdm-report-controls,
    .button,
    .wp-header-end,
    .wrap h1 {
        display: none !important;
    }
    
    .rdm-report-results {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}

.rdm-cash-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.rdm-cash-status.pending {
    background: #fff3cd;
    color: #856404;
}

.rdm-cash-status.submitted {
    background: #d4edda;
    color: #155724;
}

.rdm-cash-status.verified {
    background: #d1ecf1;
    color: #0c5460;
}

/* High discrepancy styling */
.rdm-high-discrepancy {
    background-color: #ffeaa7 !important;
    border-left: 4px solid #e17055 !important;
}

.rdm-discrepancy-flag {
    font-size: 16px;
    margin-left: 8px;
    cursor: help;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // CSV Export functionality
    $('#export-csv-button').on('click', function(e) {
        e.preventDefault();
        
        // Get current filter values
        const dateFrom = $('#report-date-from').val();
        const dateTo = $('#report-date-to').val();
        const agentId = $('#report-agent').val();
        
        // Build export URL
        const exportUrl = new URL(ajaxurl.replace('/admin-ajax.php', '/admin-post.php'));
        exportUrl.searchParams.set('action', 'rdm_export_reconciliation_csv');
        exportUrl.searchParams.set('date_from', dateFrom);
        exportUrl.searchParams.set('date_to', dateTo);
        if (agentId) {
            exportUrl.searchParams.set('agent_id', agentId);
        }
        exportUrl.searchParams.set('nonce', '<?php echo wp_create_nonce('rdm_export_csv'); ?>');
        
        // Show loading state
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('<?php esc_attr_e('Exporting...', 'restaurant-delivery-manager'); ?>');
        
        // Create hidden iframe for download
        const iframe = $('<iframe>')
            .attr('src', exportUrl.toString())
            .css({
                position: 'absolute',
                left: '-9999px',
                width: '1px',
                height: '1px'
            })
            .appendTo('body');
        
        // Reset button after download
        setTimeout(function() {
            $button.prop('disabled', false).text(originalText);
            iframe.remove();
        }, 2000);
    });
    
    // Form validation for report generation
    $('#reconciliation-report-form').on('submit', function(e) {
        const dateFrom = $('#report-date-from').val();
        const dateTo = $('#report-date-to').val();
        
        if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
            e.preventDefault();
            alert('<?php esc_attr_e('Start date cannot be after end date.', 'restaurant-delivery-manager'); ?>');
            return false;
        }
    });
});
</script>

<!-- Admin Verification Modal -->
<div id="rdm-admin-verify-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3><?php esc_html_e('Verify Cash Reconciliation', 'restaurant-delivery-manager'); ?></h3>
        
        <div id="rdm-verify-details" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
            <!-- Details will be populated by JavaScript -->
        </div>
        
        <div style="margin: 20px 0;">
            <label for="rdm-admin-notes" style="display: block; margin-bottom: 5px; font-weight: 500;">
                <?php esc_html_e('Admin Notes (Optional):', 'restaurant-delivery-manager'); ?>
            </label>
            <textarea id="rdm-admin-notes" 
                      style="width: 100%; height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"
                      placeholder="<?php esc_attr_e('Add any notes about this verification...', 'restaurant-delivery-manager'); ?>"></textarea>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" id="rdm-cancel-verify" class="button" style="margin-right: 10px;">
                <?php esc_html_e('Cancel', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" id="rdm-approve-reconciliation" class="button button-primary" style="margin-right: 10px;">
                <?php esc_html_e('Approve', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" id="rdm-reject-reconciliation" class="button" style="background: #dc3545; color: white; border-color: #dc3545;">
                <?php esc_html_e('Reject', 'restaurant-delivery-manager'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Enhanced verify reconciliation with admin notes
    $('.rdm-verify-reconciliation-btn').on('click', function() {
        const $button = $(this);
        const reconciliationId = $button.data('reconciliation-id');
        
        // Get reconciliation details
        $.post(ajaxurl, {
            action: 'rdm_get_reconciliation_details',
            reconciliation_id: reconciliationId,
            nonce: '<?php echo wp_create_nonce('rdm_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const data = response.data;
                $('#rdm-verify-details').html(`
                    <div><strong><?php esc_html_e('Agent:', 'restaurant-delivery-manager'); ?></strong> ${data.agent_name}</div>
                    <div><strong><?php esc_html_e('Date:', 'restaurant-delivery-manager'); ?></strong> ${data.date}</div>
                    <div><strong><?php esc_html_e('Collections:', 'restaurant-delivery-manager'); ?></strong> ${data.collections}</div>
                    <div><strong><?php esc_html_e('Variance:', 'restaurant-delivery-manager'); ?></strong> ${data.variance}</div>
                    ${data.agent_notes ? `<div><strong><?php esc_html_e('Agent Notes:', 'restaurant-delivery-manager'); ?></strong> ${data.agent_notes}</div>` : ''}
                `);
                $('#rdm-admin-verify-modal').show();
                $('#rdm-admin-verify-modal').data('reconciliation-id', reconciliationId);
            }
        });
    });
    
    // Cancel verification
    $('#rdm-cancel-verify').on('click', function() {
        $('#rdm-admin-verify-modal').hide();
        $('#rdm-admin-notes').val('');
    });
    
    // Approve reconciliation
    $('#rdm-approve-reconciliation').on('click', function() {
        const reconciliationId = $('#rdm-admin-verify-modal').data('reconciliation-id');
        const adminNotes = $('#rdm-admin-notes').val();
        
        $.post(ajaxurl, {
            action: 'rdm_verify_reconciliation',
            reconciliation_id: reconciliationId,
            admin_notes: adminNotes,
            verify_action: 'approve',
            nonce: '<?php echo wp_create_nonce('rdm_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Error processing verification');
            }
        });
    });
    
    // Reject reconciliation
    $('#rdm-reject-reconciliation').on('click', function() {
        const reconciliationId = $('#rdm-admin-verify-modal').data('reconciliation-id');
        const adminNotes = $('#rdm-admin-notes').val();
        
        if (!adminNotes.trim()) {
            alert('<?php esc_js_e('Please provide a reason for rejection in the admin notes.', 'restaurant-delivery-manager'); ?>');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'rdm_verify_reconciliation',
            reconciliation_id: reconciliationId,
            admin_notes: adminNotes,
            verify_action: 'reject',
            nonce: '<?php echo wp_create_nonce('rdm_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Error processing verification');
            }
        });
    });
});
</script> 