<?php
/**
 * Agent Assignment Modal Partial
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;
?>
<div id="rdm-agent-assignment-modal" class="rdm-modal" style="display:none;">
    <div class="rdm-modal-content">
        <h2><?php esc_html_e('Assign Delivery Agent', 'restaurant-delivery-manager'); ?></h2>
        <div class="rdm-agent-list-container">
            <!-- Agent list will be populated here by JS -->
            <select id="rdm-agent-select"></select>
        </div>
        <div class="rdm-modal-actions">
            <button class="button button-primary" id="rdm-confirm-assign-agent"><?php esc_html_e('Confirm Assignment', 'restaurant-delivery-manager'); ?></button>
            <button class="button" id="rdm-cancel-assign-agent"><?php esc_html_e('Cancel', 'restaurant-delivery-manager'); ?></button>
        </div>
    </div>
    <div class="rdm-modal-overlay"></div>
</div> 