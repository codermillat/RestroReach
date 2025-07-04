<?php
/**
 * Print Order Ticket Template
 *
 * @package RestaurantDeliveryManager
 * @subpackage Admin
 * @since 1.0.0
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php esc_html_e('Order Ticket', 'restaurant-delivery-manager'); ?></title>
    <style>
        @media print {
            body { font-size: 16px; line-height: 1.4; }
            .rdm-print-ticket-container { max-width: 600px; margin: 0 auto; }
            .rdm-ticket-header { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
            .rdm-ticket-section { margin-bottom: 8px; }
            .rdm-ticket-items { margin-bottom: 10px; }
            .rdm-ticket-notes { font-style: italic; }
            .no-print { display: none !important; }
        }
        body { background: #fff; color: #000; font-family: Arial, sans-serif; }
        .rdm-print-ticket-container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; }
        .rdm-ticket-header { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
        .rdm-ticket-section { margin-bottom: 8px; }
        .rdm-ticket-items { margin-bottom: 10px; }
        .rdm-ticket-notes { font-style: italic; }
    </style>
</head>
<body>
<div class="rdm-print-ticket-container">
    <div class="rdm-ticket-header">
        <?php esc_html_e('Order Ticket', 'restaurant-delivery-manager'); ?>
    </div>
    <div class="rdm-ticket-section"><strong><?php esc_html_e('Order ID:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($id); ?></div>
    <div class="rdm-ticket-section"><strong><?php esc_html_e('Customer:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($customer); ?></div>
    <div class="rdm-ticket-section"><strong><?php esc_html_e('Order Time:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($date); ?></div>
    <div class="rdm-ticket-items">
        <strong><?php esc_html_e('Items:', 'restaurant-delivery-manager'); ?></strong>
        <ul>
            <?php foreach ($items as $item) : ?>
                <li><?php echo esc_html($item['name']); ?> x<?php echo esc_html($item['qty']); ?><?php if (!empty($item['variation'])) echo ' (' . esc_html($item['variation']) . ')'; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if (!empty($special_instructions)) : ?>
        <div class="rdm-ticket-notes"><strong><?php esc_html_e('Special Instructions:', 'restaurant-delivery-manager'); ?></strong> <?php echo esc_html($special_instructions); ?></div>
    <?php endif; ?>
</div>
</body>
</html> 