/**
 * Restaurant Delivery Manager - Customer Notifications
 *
 * @package RestaurantDeliveryManager
 * @subpackage Assets/JavaScript
 * @since 2.0.0
 * @version 2.0.0 - Customer order tracking notifications
 */

(function($) {
    'use strict';

    /**
     * Customer Notifications Class
     */
    class RDMCustomerNotifications {
        constructor() {
            this.settings = rdmCustomerNotifications || {};
            this.orderId = this.getOrderIdFromURL();
            this.orderKey = this.getOrderKeyFromURL();
            this.lastUpdate = Math.floor(Date.now() / 1000);
            this.pollInterval = null;
            this.currentStatus = null;
            this.statusHistory = [];
            this.isVisible = true;
            
            this.init();
        }

        /**
         * Initialize the customer notification system
         */
        init() {
            console.log('RestroReach: Initializing customer notifications');
            
            if (!this.orderId || !this.orderKey) {
                console.log('RestroReach: No valid order information found');
                return;
            }
            
            // Initialize UI
            this.initializeUI();
            
            // Start polling for updates
            this.startPolling();
            
            // Bind events
            this.bindEvents();
            
            // Handle page visibility changes
            this.handleVisibilityChange();
            
            // Initial load
            this.loadOrderStatus();
        }

        /**
         * Initialize UI elements
         */
        initializeUI() {
            // Add status timeline if it doesn't exist
            this.addStatusTimeline();
            
            // Add notification banner
            this.addNotificationBanner();
            
            // Add live updates indicator
            this.addLiveUpdatesIndicator();
        }

        /**
         * Add status timeline
         */
        addStatusTimeline() {
            if ($('#rdm-status-timeline').length > 0) return;

            const timelineHTML = `
                <div id="rdm-status-timeline" class="rdm-status-timeline">
                    <h3>Order Status Timeline</h3>
                    <div class="rdm-timeline-container">
                        <div class="rdm-timeline-loading">Loading order status...</div>
                    </div>
                </div>
            `;

            // Find a good place to insert the timeline
            const trackingContainer = $('.rdm-order-tracking, #rdm-order-tracking, .order-tracking');
            if (trackingContainer.length > 0) {
                trackingContainer.append(timelineHTML);
            } else {
                $('body').append(timelineHTML);
            }
        }

        /**
         * Add notification banner
         */
        addNotificationBanner() {
            const bannerHTML = `
                <div id="rdm-notification-banner" class="rdm-notification-banner hidden">
                    <div class="rdm-banner-content">
                        <span class="rdm-banner-icon"></span>
                        <span class="rdm-banner-message"></span>
                    </div>
                    <button class="rdm-banner-close">&times;</button>
                </div>
            `;

            if ($('#rdm-notification-banner').length === 0) {
                $('body').prepend(bannerHTML);
            }
        }

        /**
         * Add live updates indicator
         */
        addLiveUpdatesIndicator() {
            const indicatorHTML = `
                <div id="rdm-live-indicator" class="rdm-live-indicator">
                    <span class="rdm-live-dot"></span>
                    <span class="rdm-live-text">Live updates active</span>
                </div>
            `;

            const trackingContainer = $('.rdm-order-tracking, #rdm-order-tracking, .order-tracking');
            if (trackingContainer.length > 0) {
                trackingContainer.prepend(indicatorHTML);
            }
        }

        /**
         * Start polling for order updates
         */
        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }

            const interval = this.settings.refresh_interval || 30000; // 30 seconds

            this.pollInterval = setInterval(() => {
                if (this.isVisible) {
                    this.checkForUpdates();
                }
            }, interval);
        }

        /**
         * Check for order updates
         */
        checkForUpdates() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_get_customer_notifications',
                    order_id: this.orderId,
                    order_key: this.orderKey,
                    last_update: this.lastUpdate
                },
                success: (response) => {
                    this.updateLiveIndicator(true);
                    
                    if (response.success) {
                        this.processOrderUpdate(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('RestroReach: Failed to check for updates:', error);
                    this.updateLiveIndicator(false);
                }
            });
        }

        /**
         * Load initial order status
         */
        loadOrderStatus() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_get_customer_notifications',
                    order_id: this.orderId,
                    order_key: this.orderKey
                },
                success: (response) => {
                    if (response.success) {
                        this.processOrderUpdate(response.data, true);
                    } else {
                        this.showError('Failed to load order status');
                    }
                },
                error: () => {
                    this.showError('Network error. Please refresh the page.');
                }
            });
        }

        /**
         * Process order update
         */
        processOrderUpdate(data, isInitialLoad = false) {
            const statusChanged = this.currentStatus !== data.order_status;
            this.currentStatus = data.order_status;
            
            // Update timeline
            this.updateStatusTimeline(data);
            
            // Show notification for status changes (not on initial load)
            if (statusChanged && !isInitialLoad) {
                this.showStatusChangeNotification(data.order_status);
            }
            
            // Update tracking data if available
            if (data.tracking_data) {
                this.updateTrackingData(data.tracking_data);
            }
            
            // Process new notifications
            if (data.notifications && data.notifications.length > 0) {
                this.processNotifications(data.notifications);
            }
            
            this.lastUpdate = Math.floor(Date.now() / 1000);
        }

        /**
         * Update status timeline
         */
        updateStatusTimeline(data) {
            const container = $('#rdm-status-timeline .rdm-timeline-container');
            const statuses = this.getOrderStatusFlow();
            
            let timelineHTML = '';
            
            statuses.forEach((status, index) => {
                const isCompleted = this.isStatusCompleted(status.key, data.order_status);
                const isCurrent = status.key === data.order_status;
                const timestamp = this.getStatusTimestamp(status.key, data.notifications);
                
                timelineHTML += `
                    <div class="rdm-timeline-item ${isCompleted ? 'completed' : ''} ${isCurrent ? 'current' : ''}">
                        <div class="rdm-timeline-marker">
                            <span class="dashicons ${status.icon}"></span>
                        </div>
                        <div class="rdm-timeline-content">
                            <div class="rdm-timeline-title">${status.title}</div>
                            <div class="rdm-timeline-description">${status.description}</div>
                            ${timestamp ? `<div class="rdm-timeline-time">${this.formatTimestamp(timestamp)}</div>` : ''}
                            ${this.getStatusEstimate(status.key, data)}
                        </div>
                    </div>
                `;
            });
            
            container.html(timelineHTML);
        }

        /**
         * Get order status flow
         */
        getOrderStatusFlow() {
            return [
                {
                    key: 'processing',
                    title: 'Order Confirmed',
                    description: 'Your order has been received and confirmed',
                    icon: 'dashicons-yes-alt'
                },
                {
                    key: 'wc-preparing',
                    title: 'Preparing Your Food',
                    description: 'Our kitchen is preparing your delicious meal',
                    icon: 'dashicons-clock'
                },
                {
                    key: 'wc-ready-for-pickup',
                    title: 'Ready for Pickup',
                    description: 'Your order is ready and waiting for our delivery agent',
                    icon: 'dashicons-store'
                },
                {
                    key: 'wc-out-for-delivery',
                    title: 'Out for Delivery',
                    description: 'Your order is on its way to you',
                    icon: 'dashicons-car'
                },
                {
                    key: 'completed',
                    title: 'Delivered',
                    description: 'Your order has been delivered. Enjoy your meal!',
                    icon: 'dashicons-thumbs-up'
                }
            ];
        }

        /**
         * Check if status is completed
         */
        isStatusCompleted(statusKey, currentStatus) {
            const statuses = ['processing', 'wc-preparing', 'wc-ready-for-pickup', 'wc-out-for-delivery', 'completed'];
            const statusIndex = statuses.indexOf(statusKey);
            const currentIndex = statuses.indexOf(currentStatus);
            
            return currentIndex > statusIndex;
        }

        /**
         * Get status estimate
         */
        getStatusEstimate(statusKey, data) {
            let estimate = '';
            
            switch (statusKey) {
                case 'wc-preparing':
                    if (data.tracking_data && data.tracking_data.preparation_time) {
                        estimate = `<div class="rdm-timeline-estimate">Est. ${data.tracking_data.preparation_time}</div>`;
                    }
                    break;
                case 'wc-out-for-delivery':
                    if (data.tracking_data && data.tracking_data.estimated_delivery) {
                        estimate = `<div class="rdm-timeline-estimate">Est. delivery: ${data.tracking_data.estimated_delivery}</div>`;
                    }
                    break;
            }
            
            return estimate;
        }

        /**
         * Get status timestamp from notifications
         */
        getStatusTimestamp(statusKey, notifications) {
            if (!notifications) return null;
            
            const relevantNotification = notifications.find(notification => {
                const data = JSON.parse(notification.data || '{}');
                return data.status === statusKey || notification.type.includes(statusKey);
            });
            
            return relevantNotification ? relevantNotification.created_at : null;
        }

        /**
         * Show status change notification
         */
        showStatusChangeNotification(newStatus) {
            const statusMessages = {
                'processing': this.settings.strings.order_confirmed || 'Your order has been confirmed!',
                'wc-preparing': this.settings.strings.order_preparing || 'Your order is being prepared',
                'wc-ready-for-pickup': 'Your order is ready for pickup',
                'wc-out-for-delivery': this.settings.strings.order_dispatched || 'Your order is out for delivery',
                'completed': this.settings.strings.order_delivered || 'Your order has been delivered'
            };

            const message = statusMessages[newStatus] || 'Order status updated';
            const isUrgent = ['wc-out-for-delivery', 'completed'].includes(newStatus);
            
            this.showNotificationBanner(message, isUrgent ? 'success' : 'info');
            
            // Try to show browser notification if supported
            this.showBrowserNotification('Order Update', message);
        }

        /**
         * Show notification banner
         */
        showNotificationBanner(message, type = 'info') {
            const banner = $('#rdm-notification-banner');
            const icon = banner.find('.rdm-banner-icon');
            const messageElement = banner.find('.rdm-banner-message');
            
            // Set icon based on type
            const icons = {
                'info': 'dashicons-info',
                'success': 'dashicons-yes-alt',
                'warning': 'dashicons-warning',
                'error': 'dashicons-dismiss'
            };
            
            icon.removeClass().addClass('rdm-banner-icon dashicons ' + (icons[type] || icons.info));
            messageElement.text(message);
            banner.removeClass('hidden info success warning error').addClass(type);
            
            // Auto-hide after delay
            setTimeout(() => {
                banner.addClass('hidden');
            }, type === 'success' ? 8000 : 5000);
        }

        /**
         * Show browser notification
         */
        showBrowserNotification(title, message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                const notification = new Notification(title, {
                    body: message,
                    icon: rdmCustomerNotifications.pluginUrl + 'assets/images/icon-192x192.png',
                    tag: 'rdm-order-update'
                });
                
                setTimeout(() => notification.close(), 5000);
            } else if ('Notification' in window && Notification.permission === 'default') {
                // Request permission for future notifications
                Notification.requestPermission();
            }
        }

        /**
         * Update tracking data
         */
        updateTrackingData(trackingData) {
            // Update delivery agent information if available
            if (trackingData.agent_id) {
                this.updateAgentInfo(trackingData);
            }
            
            // Update estimated delivery time
            if (trackingData.estimated_delivery) {
                this.updateEstimatedDelivery(trackingData.estimated_delivery);
            }
            
            // Update location if available (future feature)
            if (trackingData.last_location_update) {
                this.updateDeliveryLocation(trackingData.last_location_update);
            }
        }

        /**
         * Update agent information
         */
        updateAgentInfo(trackingData) {
            let agentInfo = $('#rdm-agent-info');
            
            if (agentInfo.length === 0) {
                agentInfo = $(`
                    <div id="rdm-agent-info" class="rdm-agent-info">
                        <h4>Your Delivery Agent</h4>
                        <div class="rdm-agent-details"></div>
                    </div>
                `);
                
                $('#rdm-status-timeline').after(agentInfo);
            }
            
            // This would be populated with actual agent data from the server
            agentInfo.find('.rdm-agent-details').html(`
                <div class="rdm-agent-name">Delivery Agent assigned</div>
                <div class="rdm-agent-status">On the way to you</div>
            `);
        }

        /**
         * Process notifications
         */
        processNotifications(notifications) {
            notifications.forEach(notification => {
                if (notification.created_at > this.lastUpdate) {
                    this.displayCustomerNotification(notification);
                }
            });
        }

        /**
         * Display customer notification
         */
        displayCustomerNotification(notification) {
            const data = JSON.parse(notification.data || '{}');
            
            // Show as banner notification
            this.showNotificationBanner(notification.message, 'info');
        }

        /**
         * Update live indicator
         */
        updateLiveIndicator(isConnected) {
            const indicator = $('#rdm-live-indicator');
            
            if (isConnected) {
                indicator.removeClass('disconnected').addClass('connected');
                indicator.find('.rdm-live-text').text('Live updates active');
            } else {
                indicator.removeClass('connected').addClass('disconnected');
                indicator.find('.rdm-live-text').text('Connection lost');
            }
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Close notification banner
            $(document).on('click', '.rdm-banner-close', function() {
                $('#rdm-notification-banner').addClass('hidden');
            });

            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                this.isVisible = !document.hidden;
                
                if (this.isVisible) {
                    // Page became visible, check for updates immediately
                    this.checkForUpdates();
                }
            });

            // Refresh button (if exists)
            $(document).on('click', '.rdm-refresh-status', () => {
                this.loadOrderStatus();
            });
        }

        /**
         * Handle page visibility changes
         */
        handleVisibilityChange() {
            document.addEventListener('visibilitychange', () => {
                this.isVisible = !document.hidden;
            });
        }

        /**
         * Show error message
         */
        showError(message) {
            this.showNotificationBanner(message, 'error');
        }

        /**
         * Utility methods
         */
        getOrderIdFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('order_id') || 
                   document.querySelector('[data-order-id]')?.getAttribute('data-order-id') ||
                   window.rdmOrderId;
        }

        getOrderKeyFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('order_key') || 
                   document.querySelector('[data-order-key]')?.getAttribute('data-order-key') ||
                   window.rdmOrderKey;
        }

        formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if we're on an order tracking page
        if (typeof rdmCustomerNotifications !== 'undefined' || 
            $('.rdm-order-tracking').length > 0 || 
            window.location.search.includes('order_id')) {
            
            window.rdmCustomerNotificationSystem = new RDMCustomerNotifications();
        }
    });

})(jQuery); 