<?php
/**
 * Delivery Agent Dashboard Page
 *
 * @package RestaurantDeliveryManager
 * @subpackage Mobile
 * @since 1.0.0
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#2271b1">
    <title><?php esc_html_e('Agent Dashboard', 'restaurant-delivery-manager'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="rrm-mobile-dashboard">
    <!-- Header -->
    <header class="rrm-dashboard-header">
        <div class="rrm-header-content">
            <span class="rrm-dashboard-title"><?php esc_html_e('My Deliveries', 'restaurant-delivery-manager'); ?></span>
            <div class="rrm-header-actions">
                <button id="rrm-refresh-btn" class="rrm-btn-icon" type="button" title="<?php esc_attr_e('Refresh', 'restaurant-delivery-manager'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <button id="rrm-logout-btn" class="rrm-btn rrm-btn-logout" type="button"><?php esc_html_e('Logout', 'restaurant-delivery-manager'); ?></button>
            </div>
        </div>
    </header>

    <!-- Status Bar -->
    <div class="rrm-status-bar">
        <!-- Network Status -->
        <div class="rrm-network-status" id="rrm-network-status">
            <span class="rrm-status-indicator rrm-online"></span>
            <span class="rrm-status-text"><?php esc_html_e('Online', 'restaurant-delivery-manager'); ?></span>
        </div>

        <!-- GPS Toggle -->
        <div class="rrm-gps-toggle-row">
            <label class="rrm-switch">
                <input type="checkbox" id="rrm-gps-toggle">
                <span class="rrm-slider"></span>
            </label>
            <span class="rrm-gps-label"><?php esc_html_e('GPS Sharing', 'restaurant-delivery-manager'); ?></span>
            <span class="rrm-gps-status" id="rrm-gps-status"><?php esc_html_e('Inactive', 'restaurant-delivery-manager'); ?></span>
        </div>
    </div>

    <!-- Main Content -->
    <main class="rrm-main-content">
        <!-- Action Buttons -->
        <div class="rrm-action-buttons">
            <button id="rrm-emergency-btn" class="rrm-btn rrm-btn-emergency" type="button">
                <span class="dashicons dashicons-sos"></span>
                <?php esc_html_e('Emergency', 'restaurant-delivery-manager'); ?>
            </button>
        </div>

        <!-- Order List -->
        <div class="rrm-orders-section">
            <div class="rrm-section-header">
                <h2><?php esc_html_e('Active Orders', 'restaurant-delivery-manager'); ?></h2>
                <div class="rrm-order-stats" id="rrm-order-stats">
                    <span class="rrm-stat">0 <?php esc_html_e('orders', 'restaurant-delivery-manager'); ?></span>
                </div>
            </div>
            
            <div id="rrm-order-list-container" class="rrm-order-list">
                <!-- Orders will be loaded here by JS -->
            </div>
            
            <div id="rrm-order-list-loading" class="rrm-loading-indicator" style="display:none;">
                <div class="rrm-spinner"></div>
                <span><?php esc_html_e('Loading orders...', 'restaurant-delivery-manager'); ?></span>
            </div>
            
            <div id="rrm-no-orders" class="rrm-no-orders" style="display:none;">
                <div class="rrm-no-orders-icon">📦</div>
                <h3><?php esc_html_e('No Active Orders', 'restaurant-delivery-manager'); ?></h3>
                <p><?php esc_html_e('Pull down to refresh and check for new orders.', 'restaurant-delivery-manager'); ?></p>
            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="rrm-bottom-nav">
        <button class="rrm-nav-item rrm-active" data-tab="orders">
            <span class="dashicons dashicons-clipboard"></span>
            <span><?php esc_html_e('Orders', 'restaurant-delivery-manager'); ?></span>
        </button>
        <button class="rrm-nav-item" data-tab="payments">
            <span class="dashicons dashicons-money-alt"></span>
            <span><?php esc_html_e('Payments', 'restaurant-delivery-manager'); ?></span>
        </button>
        <button class="rrm-nav-item" data-tab="profile">
            <span class="dashicons dashicons-admin-users"></span>
            <span><?php esc_html_e('Profile', 'restaurant-delivery-manager'); ?></span>
        </button>
    </nav>

    <!-- Order Details Modal -->
    <div id="rrm-order-modal" class="rrm-modal" style="display:none;">
        <div class="rrm-modal-content rrm-order-details">
            <div class="rrm-modal-header">
                <h3 id="rrm-order-title"><?php esc_html_e('Order Details', 'restaurant-delivery-manager'); ?></h3>
                <button class="rrm-modal-close" type="button">&times;</button>
            </div>
            <div class="rrm-modal-body" id="rrm-order-details-content">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- COD Collection Modal -->
    <div id="rrm-cod-modal" class="rrm-modal" style="display:none;">
        <div class="rrm-modal-content rrm-cod-collection">
            <div class="rrm-modal-header">
                <h3><?php esc_html_e('Collect Payment', 'restaurant-delivery-manager'); ?></h3>
                <button class="rrm-modal-close" type="button">&times;</button>
            </div>
            <div class="rrm-modal-body">
                <div class="rrm-payment-summary">
                    <div class="rrm-order-total">
                        <label><?php esc_html_e('Order Total:', 'restaurant-delivery-manager'); ?></label>
                        <span id="rrm-payment-total" class="rrm-amount">$0.00</span>
                    </div>
                </div>

                <div class="rrm-payment-form">
                    <div class="rrm-input-group">
                        <label for="rrm-collected-amount"><?php esc_html_e('Amount Received:', 'restaurant-delivery-manager'); ?></label>
                        <input type="number" id="rrm-collected-amount" class="rrm-input rrm-amount-input" 
                               step="0.01" min="0" placeholder="0.00" inputmode="decimal">
                    </div>

                    <div class="rrm-change-display" id="rrm-change-section" style="display:none;">
                        <label><?php esc_html_e('Change to Give:', 'restaurant-delivery-manager'); ?></label>
                        <span id="rrm-change-amount" class="rrm-amount rrm-change">$0.00</span>
                    </div>

                    <div class="rrm-input-group">
                        <label for="rrm-payment-notes"><?php esc_html_e('Notes (Optional):', 'restaurant-delivery-manager'); ?></label>
                        <textarea id="rrm-payment-notes" class="rrm-input" rows="2" 
                                  placeholder="<?php esc_attr_e('Payment notes...', 'restaurant-delivery-manager'); ?>"></textarea>
                    </div>

                    <div class="rrm-payment-actions">
                        <button id="rrm-confirm-payment" class="rrm-btn rrm-btn-primary" type="button" disabled>
                            <?php esc_html_e('Confirm Payment', 'restaurant-delivery-manager'); ?>
                        </button>
                        <button class="rrm-btn rrm-btn-secondary rrm-modal-close" type="button">
                            <?php esc_html_e('Cancel', 'restaurant-delivery-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div id="rrm-photo-modal" class="rrm-modal" style="display:none;">
        <div class="rrm-modal-content rrm-photo-upload">
            <div class="rrm-modal-header">
                <h3><?php esc_html_e('Delivery Confirmation', 'restaurant-delivery-manager'); ?></h3>
                <button class="rrm-modal-close" type="button">&times;</button>
            </div>
            <div class="rrm-modal-body">
                <div class="rrm-photo-instructions">
                    <p><?php esc_html_e('Take a photo to confirm delivery:', 'restaurant-delivery-manager'); ?></p>
                </div>

                <div class="rrm-camera-section">
                    <input type="file" id="rrm-photo-input" accept="image/*" capture="environment" style="display:none;">
                    <button id="rrm-take-photo" class="rrm-btn rrm-btn-camera" type="button">
                        <span class="dashicons dashicons-camera"></span>
                        <?php esc_html_e('Take Photo', 'restaurant-delivery-manager'); ?>
                    </button>
                </div>

                <div id="rrm-photo-preview" class="rrm-photo-preview" style="display:none;">
                    <img id="rrm-preview-image" src="" alt="<?php esc_attr_e('Delivery photo preview', 'restaurant-delivery-manager'); ?>">
                    <div class="rrm-photo-actions">
                        <button id="rrm-upload-photo" class="rrm-btn rrm-btn-primary" type="button">
                            <?php esc_html_e('Upload Photo', 'restaurant-delivery-manager'); ?>
                        </button>
                        <button id="rrm-retake-photo" class="rrm-btn rrm-btn-secondary" type="button">
                            <?php esc_html_e('Retake', 'restaurant-delivery-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Contact Modal -->
    <div id="rrm-emergency-modal" class="rrm-modal" style="display:none;">
        <div class="rrm-modal-content rrm-emergency-contact">
            <div class="rrm-modal-header">
                <h3><?php esc_html_e('Emergency Contact', 'restaurant-delivery-manager'); ?></h3>
                <button class="rrm-modal-close" type="button">&times;</button>
            </div>
            <div class="rrm-modal-body">
                <div class="rrm-emergency-options">
                    <button class="rrm-emergency-option" type="button" onclick="window.location.href='tel:911'">
                        <span class="dashicons dashicons-phone"></span>
                        <div>
                            <strong><?php esc_html_e('Emergency Services', 'restaurant-delivery-manager'); ?></strong>
                            <span>911</span>
                        </div>
                    </button>
                    
                    <button class="rrm-emergency-option" type="button" onclick="window.location.href='tel:+1234567890'">
                        <span class="dashicons dashicons-building"></span>
                        <div>
                            <strong><?php esc_html_e('Restaurant Manager', 'restaurant-delivery-manager'); ?></strong>
                            <span><?php esc_html_e('Support line', 'restaurant-delivery-manager'); ?></span>
                        </div>
                    </button>
                </div>
                
                <button class="rrm-btn rrm-btn-secondary rrm-modal-close" type="button">
                    <?php esc_html_e('Close', 'restaurant-delivery-manager'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="rrm-toast-container" class="rrm-toast-container"></div>

    <!-- Offline Indicator -->
    <div id="rrm-offline-indicator" class="rrm-offline-indicator" style="display:none;">
        <span class="dashicons dashicons-wifi"></span>
        <span><?php esc_html_e('Working offline', 'restaurant-delivery-manager'); ?></span>
    </div>

    <?php wp_footer(); ?>
</body>
</html> 