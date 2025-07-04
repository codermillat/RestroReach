<?php
/**
 * Agents Management Page Template
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rdm-agents-management">
    <div class="rdm-page-header">
        <h1 class="rdm-page-title"><?php esc_html_e('Delivery Agents', 'restaurant-delivery-manager'); ?></h1>
        <div class="rdm-page-actions">
            <button type="button" id="rdm-add-agent" class="rdm-button rdm-button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e('Add New Agent', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" id="rdm-refresh-agents" class="rdm-button rdm-button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'restaurant-delivery-manager'); ?>
            </button>
        </div>
    </div>

    <!-- Agent Statistics -->
    <div class="rdm-agent-stats">
        <div class="rdm-stat-card">
            <div class="rdm-stat-number" id="rdm-total-agents">-</div>
            <div class="rdm-stat-label"><?php esc_html_e('Total Agents', 'restaurant-delivery-manager'); ?></div>
        </div>
        <div class="rdm-stat-card">
            <div class="rdm-stat-number" id="rdm-active-agents">-</div>
            <div class="rdm-stat-label"><?php esc_html_e('Active Now', 'restaurant-delivery-manager'); ?></div>
        </div>
        <div class="rdm-stat-card">
            <div class="rdm-stat-number" id="rdm-busy-agents">-</div>
            <div class="rdm-stat-label"><?php esc_html_e('On Delivery', 'restaurant-delivery-manager'); ?></div>
        </div>
        <div class="rdm-stat-card">
            <div class="rdm-stat-number" id="rdm-available-agents">-</div>
            <div class="rdm-stat-label"><?php esc_html_e('Available', 'restaurant-delivery-manager'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="rdm-agent-filters">
        <div class="rdm-filter-group">
            <label for="rdm-agent-status-filter"><?php esc_html_e('Status:', 'restaurant-delivery-manager'); ?></label>
            <select id="rdm-agent-status-filter">
                <option value=""><?php esc_html_e('All Statuses', 'restaurant-delivery-manager'); ?></option>
                <option value="active"><?php esc_html_e('Active', 'restaurant-delivery-manager'); ?></option>
                <option value="inactive"><?php esc_html_e('Inactive', 'restaurant-delivery-manager'); ?></option>
                <option value="busy"><?php esc_html_e('On Delivery', 'restaurant-delivery-manager'); ?></option>
                <option value="available"><?php esc_html_e('Available', 'restaurant-delivery-manager'); ?></option>
            </select>
        </div>
        <div class="rdm-filter-group">
            <label for="rdm-agent-search"><?php esc_html_e('Search:', 'restaurant-delivery-manager'); ?></label>
            <input type="text" id="rdm-agent-search" placeholder="<?php esc_attr_e('Search by name, email, or phone...', 'restaurant-delivery-manager'); ?>">
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="rdm-agents-loading" class="rdm-loading-indicator" style="display:none;">
        <span class="spinner is-active"></span>
        <?php esc_html_e('Loading agents...', 'restaurant-delivery-manager'); ?>
    </div>

    <!-- Agents List -->
    <div id="rdm-agents-container" class="rdm-agents-grid">
        <!-- Agent cards will be loaded here by JavaScript -->
    </div>

    <!-- Empty State -->
    <div id="rdm-agents-empty" class="rdm-empty-state" style="display:none;">
        <div class="rdm-empty-icon">
            <span class="dashicons dashicons-businessperson"></span>
        </div>
        <h3><?php esc_html_e('No Agents Found', 'restaurant-delivery-manager'); ?></h3>
        <p><?php esc_html_e('Start by adding your first delivery agent to manage orders.', 'restaurant-delivery-manager'); ?></p>
        <button type="button" id="rdm-add-first-agent" class="rdm-button rdm-button-primary">
            <?php esc_html_e('Add First Agent', 'restaurant-delivery-manager'); ?>
        </button>
    </div>
</div>

<!-- Agent Modal Container -->
<div id="rdm-agent-modal-container"></div>

<script type="text/javascript">
// Initialize agents management when document is ready
jQuery(document).ready(function($) {
    if (typeof RDM_Agents !== 'undefined') {
        RDM_Agents.init();
    } else {
        console.warn('RDM_Agents not loaded - agents management functionality may not work');
        
        // Basic fallback functionality
        $('#rdm-add-agent, #rdm-add-first-agent').on('click', function() {
            alert('<?php echo esc_js(__("Agent management functionality is loading. Please try again in a moment.", "restaurant-delivery-manager")); ?>');
        });
        
        $('#rdm-refresh-agents').on('click', function() {
            location.reload();
        });
    }
});
</script>
