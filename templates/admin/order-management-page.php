<?php
/**
 * Order Management Page Template
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;
?>
<div class="rdm-order-management-wrapper">
    <!-- Filter Section -->
    <div class="rdm-order-filters">
        <label for="rdm-order-status-filter"><?php esc_html_e('Order Status:', 'restaurant-delivery-manager'); ?></label>
        <select id="rdm-order-status-filter">
            <option value=""><?php esc_html_e('All', 'restaurant-delivery-manager'); ?></option>
            <option value="processing"><?php esc_html_e('New', 'restaurant-delivery-manager'); ?></option>
            <option value="preparing"><?php esc_html_e('Preparing', 'restaurant-delivery-manager'); ?></option>
            <option value="ready"><?php esc_html_e('Ready', 'restaurant-delivery-manager'); ?></option>
            <option value="out-for-delivery"><?php esc_html_e('Out for Delivery', 'restaurant-delivery-manager'); ?></option>
            <option value="delivered"><?php esc_html_e('Delivered', 'restaurant-delivery-manager'); ?></option>
        </select>
    </div>
    <!-- Loading Indicator -->
    <div id="rdm-order-list-loading" class="rdm-loading-indicator" style="display:none;">
        <span class="spinner"></span> <?php esc_html_e('Refreshing orders...', 'restaurant-delivery-manager'); ?>
    </div>
    <!-- Order List Container -->
    <div id="rdm-order-list-container" class="rdm-order-list-grid">
        <!-- Order cards will be loaded here by JS -->
    </div>
    <!-- Agent Assignment Modal Placeholder -->
    <div id="rdm-agent-modal-container"></div>
</div> 