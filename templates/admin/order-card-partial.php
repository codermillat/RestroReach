<?php
/**
 * Order Card Partial Template
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;
?>
<div class="rdm-order-card order-card-status-<?php echo esc_attr($status); ?>" data-order-id="<?php echo esc_attr($id); ?>">
    <div class="rdm-order-card-header">
        <span class="rdm-order-id">#<?php echo esc_html($id); ?></span>
        <span class="rdm-order-status-badge status-<?php echo esc_attr($status); ?>"><?php echo esc_html(ucwords(str_replace('-', ' ', $status))); ?></span>
        <span class="rdm-order-time"><?php echo esc_html($date); ?></span>
    </div>
    <div class="rdm-order-card-body">
        <div class="rdm-order-customer"><strong><?php esc_html_e('Customer:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($customer); ?></div>
        <div class="rdm-order-items"><strong><?php esc_html_e('Items:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($item_summary); ?></div>
        <div class="rdm-order-total"><strong><?php esc_html_e('Total:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($total); ?></div>
        <div class="rdm-order-address"><strong><?php esc_html_e('Address:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($address); ?></div>
        <div class="rdm-order-agent"><strong><?php esc_html_e('Agent:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($agent); ?></div>
    </div>
    <div class="rdm-order-card-actions">
        <?php if ($status === 'processing'): ?>
            <button class="button rdm-btn-start-preparing" data-action="start-preparing"><?php esc_html_e('Start Preparing', 'restaurant-delivery-manager'); ?></button>
        <?php endif; ?>
        <?php if ($status === 'preparing'): ?>
            <button class="button rdm-btn-mark-ready" data-action="mark-ready"><?php esc_html_e('Mark as Ready', 'restaurant-delivery-manager'); ?></button>
        <?php endif; ?>
        <?php if ($status === 'ready'): ?>
            <button class="button rdm-btn-assign-agent" data-action="assign-agent"><?php esc_html_e('Assign Agent', 'restaurant-delivery-manager'); ?></button>
        <?php endif; ?>
        <button class="button rdm-btn-print-ticket" data-action="print-ticket"><?php esc_html_e('Print Ticket', 'restaurant-delivery-manager'); ?></button>
    </div>
    <div class="rdm-order-notes-section">
        <div class="rdm-order-notes-list">
            <?php if (!empty($notes)) : ?>
                <?php foreach ($notes as $note) : ?>
                    <div class="rdm-order-note"><?php echo esc_html($note['note_text']); ?> <span class="rdm-note-meta"><?php echo esc_html($note['created_at']); ?></span></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <form class="rdm-add-note-form" data-order-id="<?php echo esc_attr($id); ?>">
            <textarea name="note_text" class="rdm-note-text" rows="1" placeholder="<?php esc_attr_e('Add note...', 'restaurant-delivery-manager'); ?>"></textarea>
            <button type="submit" class="button rdm-btn-add-note"><?php esc_html_e('Save Note', 'restaurant-delivery-manager'); ?></button>
        </form>
    </div>
</div> 