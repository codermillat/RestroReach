<?php
/**
 * Customer order tracking template
 *
 * @package RestaurantDeliveryManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get order data for easier access
$order = $tracking_data['order'];
$locations = $tracking_data['locations'];
$timeline = $tracking_data['status_timeline'];
?>

<div class="rdm-tracking-container">
    <h2 class="rdm-tracking-title">
        <?php echo esc_html__('Order Tracking', 'restaurant-delivery-manager'); ?> #<?php echo esc_html($order['id']); ?>
    </h2>
    
    <!-- Controls and Status -->
    <div class="rdm-controls">
        <button id="rdm-refresh-button" class="rdm-refresh-button">
            <?php echo esc_html__('Refresh', 'restaurant-delivery-manager'); ?>
        </button>
        <div id="rdm-last-update" class="rdm-last-update">
            <?php echo esc_html__('Last updated:', 'restaurant-delivery-manager'); ?> <?php echo current_time('H:i:s'); ?>
        </div>
    </div>
    
    <!-- Error Message Container -->
    <div id="rdm-error-message" class="rdm-error-message rdm-hidden"></div>
    
    <div class="rdm-tracking-columns">
        <!-- Status Timeline Column -->
        <div class="rdm-tracking-column rdm-status-column">
            <div class="rdm-section-title">
                <?php echo esc_html__('Order Status', 'restaurant-delivery-manager'); ?>
            </div>
            
            <div id="rdm-status-timeline" class="rdm-timeline">
                <?php foreach ($timeline as $step) : ?>
                    <div id="rdm-status-<?php echo esc_attr($step['status']); ?>" 
                         class="rdm-timeline-step <?php echo $step['completed'] ? 'completed' : ''; ?>">
                        <div class="rdm-step-icon"></div>
                        <div class="rdm-step-content">
                            <div class="rdm-step-title"><?php echo esc_html($step['label']); ?></div>
                            <div class="rdm-step-time">
                                <?php echo $step['completed'] ? esc_html($step['time']) : ''; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- ETA Information -->
            <div class="rdm-eta-container">
                <div class="rdm-section-title">
                    <?php echo esc_html__('Estimated Delivery', 'restaurant-delivery-manager'); ?>
                </div>
                <div id="rdm-eta-time" class="rdm-eta-time">
                    <?php echo esc_html($order['estimated_delivery']); ?>
                </div>
                <?php if (!empty($locations['agent'])) : ?>
                    <div id="rdm-distance" class="rdm-distance rdm-distance-info">
                        <!-- Distance will be populated by JavaScript -->
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Details -->
            <div class="rdm-order-details">
                <div class="rdm-section-title">
                    <?php echo esc_html__('Order Details', 'restaurant-delivery-manager'); ?>
                </div>
                <div class="rdm-order-items">
                    <?php foreach ($order['items'] as $item) : ?>
                        <div class="rdm-order-item">
                            <span class="rdm-item-name"><?php echo esc_html($item['name']); ?></span>
                            <span class="rdm-item-quantity">×<?php echo esc_html($item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="rdm-order-total">
                    <?php echo esc_html__('Total:', 'restaurant-delivery-manager'); ?> 
                    <?php echo esc_html($order['total']); ?>
                </div>
                
                <!-- Payment Status -->
                <?php 
                // Check if the order uses COD payment method
                $wc_order = wc_get_order($order['id']);
                if ($wc_order && $wc_order->get_payment_method() === 'cod') :
                    $payments_class = RDM_Payments::instance();
                    $payment_status = $payments_class->get_payment_status_by_order_id($order['id']);
                ?>
                    <div class="rdm-payment-status">
                        <div class="rdm-payment-label">
                            <?php echo esc_html__('Payment Status:', 'restaurant-delivery-manager'); ?>
                        </div>
                        <div class="rdm-payment-badge rdm-payment-<?php echo esc_attr($payment_status['class']); ?>">
                            <?php echo esc_html($payment_status['label']); ?>
                        </div>
                        <?php if ($payment_status['status'] === 'collected' && !empty($payment_status['collected_at'])) : ?>
                            <div class="rdm-payment-details">
                                <?php echo esc_html__('Collected:', 'restaurant-delivery-manager'); ?> 
                                <?php echo esc_html(wp_date(get_option('time_format'), strtotime($payment_status['collected_at']))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Contact Information -->
            <?php if (!empty($locations['agent'])) : ?>
                <div class="rdm-contact-section">
                    <div class="rdm-section-title">
                        <?php echo esc_html__('Delivery Agent', 'restaurant-delivery-manager'); ?>
                    </div>
                    <div class="rdm-agent-info">
                        <div class="rdm-agent-name">
                            <?php echo esc_html($locations['agent']['name']); ?>
                        </div>
                        <?php if (!empty($locations['agent']['phone'])) : ?>
                            <div class="rdm-agent-phone">
                                <?php echo esc_html($locations['agent']['phone']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="rdm-contact-buttons">
                        <?php if (!empty($locations['agent']['phone'])) : ?>
                            <a href="tel:<?php echo esc_attr($locations['agent']['phone']); ?>" 
                               class="rdm-contact-button rdm-call-agent">
                                📞 <?php echo esc_html__('Call Driver', 'restaurant-delivery-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Customer Support -->
            <div class="rdm-support-section">
                <div class="rdm-support-title">
                    <?php echo esc_html__('Need Help?', 'restaurant-delivery-manager'); ?>
                </div>
                <div class="rdm-support-description">
                    <?php echo esc_html__('Contact our support team if you have any questions about your order.', 'restaurant-delivery-manager'); ?>
                </div>
                <?php 
                $support_phone = get_option('rdm_support_phone', get_option('woocommerce_store_phone', ''));
                $support_email = get_option('rdm_support_email', get_option('admin_email', ''));
                ?>
                <?php if ($support_phone) : ?>
                    <div class="rdm-support-contact">
                        <a href="tel:<?php echo esc_attr($support_phone); ?>" class="rdm-support-link">
                            📞 <?php echo esc_html($support_phone); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($support_email) : ?>
                    <div class="rdm-support-contact">
                        <a href="mailto:<?php echo esc_attr($support_email); ?>" class="rdm-support-link">
                            ✉️ <?php echo esc_html($support_email); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Map Column -->
        <div class="rdm-tracking-column rdm-map-column">
            <div id="rdm-tracking-map" class="rdm-tracking-map">
                <div class="rdm-map-loading">
                    <div class="rdm-map-loading-content">
                        <div class="rdm-map-loading-text">Loading map...</div>
                        <div class="rdm-loading-spinner"></div>
                    </div>
                </div>
            </div>
            
            <!-- Map Legend -->
            <div class="rdm-locations-legend">
                <div class="rdm-legend-item rdm-restaurant">
                    <span class="rdm-legend-icon restaurant"></span>
                    <span class="rdm-legend-text">
                        <?php echo esc_html($locations['restaurant']['name'] ?? get_option('blogname')); ?>
                    </span>
                </div>
                
                <div class="rdm-legend-item rdm-customer">
                    <span class="rdm-legend-icon customer"></span>
                    <span class="rdm-legend-text">
                        <?php echo esc_html__('Delivery Address', 'restaurant-delivery-manager'); ?>
                    </span>
                </div>
                
                <?php if (!empty($locations['agent'])) : ?>
                    <div class="rdm-legend-item rdm-agent">
                        <span class="rdm-legend-icon agent"></span>
                        <span class="rdm-legend-text">
                            <?php echo esc_html($locations['agent']['name']); ?>
                            <small class="rdm-legend-agent-role">
                                <?php echo esc_html__('Delivery Agent', 'restaurant-delivery-manager'); ?>
                            </small>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Map Instructions -->
            <div class="rdm-map-instructions">
                <strong><?php echo esc_html__('Map Tips:', 'restaurant-delivery-manager'); ?></strong>
                <ul class="rdm-instructions-list">
                    <li><?php echo esc_html__('Click markers for more information', 'restaurant-delivery-manager'); ?></li>
                    <li><?php echo esc_html__('Use zoom controls to get a better view', 'restaurant-delivery-manager'); ?></li>
                    <?php if (!empty($locations['agent'])) : ?>
                        <li><?php echo esc_html__('Green marker shows your delivery agent\'s live location', 'restaurant-delivery-manager'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Order Information Footer -->
    <div class="rdm-order-footer">
        <div class="rdm-footer-text">
            <strong><?php echo esc_html__('Order placed:', 'restaurant-delivery-manager'); ?></strong>
            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order['date_created']))); ?>
        </div>
        
        <div class="rdm-support-description">
            <?php echo esc_html__('Thank you for choosing us! We appreciate your business.', 'restaurant-delivery-manager'); ?>
        </div>
        
        <!-- Social Sharing (Optional) -->
        <?php if (get_option('rdm_enable_social_sharing', false)) : ?>
            <div class="rdm-social-sharing">
                <span class="rdm-sharing-label">
                    <?php echo esc_html__('Share your experience:', 'restaurant-delivery-manager'); ?>
                </span>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Great food delivery experience! Tracking my order in real-time.'); ?>" 
                   target="_blank" 
                   class="rdm-social-link rdm-twitter-link">
                    Twitter
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(home_url()); ?>" 
                   target="_blank" 
                   class="rdm-social-link rdm-facebook-link">
                    Facebook
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Google Maps API Script -->
<?php 
$google_maps_api_key = get_option('rdm_google_maps_api_key');
if ($google_maps_api_key && class_exists('RDM_Google_Maps')) : 
?>
<script>
    // Google Maps configuration
    window.rdmMapsConfig = {
        apiKey: '<?php echo esc_js($google_maps_api_key); ?>',
        markerIcons: {
            restaurant: '<?php echo esc_url(RDM_PLUGIN_URL . 'assets/images/marker-restaurant.png'); ?>',
            customer: '<?php echo esc_url(RDM_PLUGIN_URL . 'assets/images/marker-customer.png'); ?>',
            agent: '<?php echo esc_url(RDM_PLUGIN_URL . 'assets/images/marker-agent.png'); ?>'
        }
    };
</script>
<script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($google_maps_api_key); ?>&callback=rdmInitMap&libraries=geometry">
</script>
<?php else : ?>
<script>
    // Fallback when Google Maps is not available
    document.addEventListener('DOMContentLoaded', function() {
        const mapContainer = document.getElementById('rdm-tracking-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="rdm-map-error">
                    <div style="text-align: center; padding: 40px;">
                        <div style="font-size: 18px; margin-bottom: 10px;">📍</div>
                        <div style="font-weight: 500; margin-bottom: 10px;">
                            <?php echo esc_js(__('Map View Unavailable', 'restaurant-delivery-manager')); ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">
                            <?php echo esc_js(__('Please check the status timeline above for order updates.', 'restaurant-delivery-manager')); ?>
                        </div>
                    </div>
                </div>
            `;
        }
    });
</script>
<?php endif; ?>
