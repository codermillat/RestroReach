<?php
/**
 * Order List Item Partial (Mobile)
 *
 * @package RestaurantDeliveryManager
 * @subpackage Mobile
 * @since 1.0.0
 */
if (!defined('ABSPATH')) exit;

// Get order data
$order = wc_get_order($id);
$order_total = $order ? $order->get_total() : 0;
$payment_method = $order ? $order->get_payment_method() : '';
$order_status = $order ? $order->get_status() : '';

// Get payment record
$payments_class = RDM_Payments::instance();
$payment_record = $payments_class->get_payment_record($id);
$payment_status = $payment_record ? $payment_record->status : 'unknown';
?>
<div class="rrm-order-list-item rrm-swipe-action" data-order-id="<?php echo esc_attr($id); ?>">
    <div class="rrm-order-main">
        <span class="rrm-order-id">#<?php echo esc_html($id); ?></span>
        <span class="rrm-order-customer"><?php echo esc_html($customer); ?></span>
        <span class="rrm-order-address"><?php echo esc_html($address); ?></span>
        
        <!-- Order Total -->
        <div class="rrm-order-total">
            <strong><?php echo wc_price($order_total); ?></strong>
            
            <!-- Payment Status -->
            <?php if ($payment_method === 'cod'): ?>
                <?php if ($payment_status === 'pending'): ?>
                    <span class="rdm-order-payment-status cod-pending">COD Pending</span>
                <?php elseif ($payment_status === 'collected'): ?>
                    <span class="rdm-order-payment-status cod-collected">Payment Collected</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="rdm-order-payment-status online-paid">Paid Online</span>
            <?php endif; ?>
        </div>
        
        <!-- Payment Collection Button (only for COD pending orders) -->
        <?php if ($payment_method === 'cod' && $payment_status === 'pending' && $order_status === 'out-for-delivery'): ?>
            <button type="button" 
                    class="rdm-payment-collect-btn rdm-collect-payment-btn" 
                    data-order-id="<?php echo esc_attr($id); ?>"
                    data-order-total="<?php echo esc_attr($order_total); ?>"
                    data-customer-name="<?php echo esc_attr($customer); ?>"
                    data-order-number="<?php echo esc_attr($id); ?>">
                <?php esc_html_e('Collect Payment', 'restaurant-delivery-manager'); ?>
            </button>
        <?php endif; ?>
    </div>
    <!-- Placeholder for swipe actions -->
</div> 