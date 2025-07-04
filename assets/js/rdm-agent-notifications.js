/**
 * Restaurant Delivery Manager - Agent Notifications
 *
 * @package RestaurantDeliveryManager
 * @subpackage Assets/JavaScript
 * @since 2.0.0
 * @version 2.0.0 - Mobile-optimized agent notifications
 */

(function($) {
    'use strict';

    /**
     * Agent Notifications Class
     */
    class RDMAgentNotifications {
        constructor() {
            this.settings = rdmAgentNotifications || {};
            this.lastCheck = Math.floor(Date.now() / 1000);
            this.pollInterval = null;
            this.notificationPermission = 'default';
            this.soundEnabled = this.getSetting('sound_enabled', true);
            this.vibrationEnabled = this.getSetting('vibration_enabled', true);
            this.isVisible = true;
            this.isOnline = navigator.onLine;
            this.sounds = {};
            this.activeAssignments = new Set();
            
            this.init();
        }

        /**
         * Initialize the agent notification system
         */
        init() {
            console.log('RestroReach: Initializing agent notifications');
            
            // Check device capabilities
            this.checkDeviceCapabilities();
            
            // Initialize UI
            this.initializeUI();
            
            // Load sounds
            this.loadSounds();
            
            // Start polling
            this.startPolling();
            
            // Bind events
            this.bindEvents();
            
            // Handle connectivity changes
            this.handleConnectivityChanges();
            
            // Request notification permission
            this.requestNotificationPermission();
        }

        /**
         * Check device capabilities
         */
        checkDeviceCapabilities() {
            // Check notification support
            this.notificationSupported = 'Notification' in window;
            
            // Check vibration support
            this.vibrationSupported = 'vibrate' in navigator;
            
            // Check if running on mobile
            this.isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Check if app is installed (PWA)
            this.isInstalled = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;

            console.log('RestroReach: Device capabilities:', {
                notifications: this.notificationSupported,
                vibration: this.vibrationSupported,
                mobile: this.isMobile,
                installed: this.isInstalled
            });
        }

        /**
         * Initialize UI elements
         */
        initializeUI() {
            // Add notification status indicator
            this.addStatusIndicator();
            
            // Add floating notification container
            this.addNotificationContainer();
            
            // Add quick actions panel
            this.addQuickActionsPanel();
            
            // Add connection status
            this.addConnectionStatus();
        }

        /**
         * Add status indicator
         */
        addStatusIndicator() {
            const statusHTML = `
                <div id="rdm-agent-status" class="rdm-agent-status">
                    <div class="rdm-status-indicator" id="rdm-status-indicator">
                        <span class="rdm-status-dot"></span>
                        <span class="rdm-status-text">Online</span>
                    </div>
                    <div class="rdm-notification-count" id="rdm-agent-notification-count">
                        <span class="rdm-count">0</span>
                    </div>
                </div>
            `;

            if ($('#rdm-agent-status').length === 0) {
                $('body').prepend(statusHTML);
            }
        }

        /**
         * Add notification container
         */
        addNotificationContainer() {
            const containerHTML = `
                <div id="rdm-agent-notifications" class="rdm-agent-notifications">
                    <!-- Agent notifications will appear here -->
                </div>
            `;

            if ($('#rdm-agent-notifications').length === 0) {
                $('body').append(containerHTML);
            }
        }

        /**
         * Add quick actions panel
         */
        addQuickActionsPanel() {
            const panelHTML = `
                <div id="rdm-quick-actions" class="rdm-quick-actions">
                    <button class="rdm-quick-action" id="rdm-toggle-availability">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="rdm-action-text">Available</span>
                    </button>
                    <button class="rdm-quick-action" id="rdm-view-assignments">
                        <span class="dashicons dashicons-list-view"></span>
                        <span class="rdm-action-text">Orders</span>
                    </button>
                    <button class="rdm-quick-action" id="rdm-notification-settings">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span class="rdm-action-text">Settings</span>
                    </button>
                </div>
            `;

            if ($('#rdm-quick-actions').length === 0) {
                $('body').append(panelHTML);
            }
        }

        /**
         * Add connection status
         */
        addConnectionStatus() {
            const statusHTML = `
                <div id="rdm-connection-status" class="rdm-connection-status hidden">
                    <span class="rdm-connection-text">Connection restored</span>
                </div>
            `;

            if ($('#rdm-connection-status').length === 0) {
                $('body').append(statusHTML);
            }
        }

        /**
         * Request notification permission
         */
        requestNotificationPermission() {
            if (!this.notificationSupported) return;

            if (Notification.permission === 'default') {
                Notification.requestPermission().then((permission) => {
                    this.notificationPermission = permission;
                    
                    if (permission === 'granted') {
                        this.showWelcomeNotification();
                    }
                });
            } else {
                this.notificationPermission = Notification.permission;
            }
        }

        /**
         * Show welcome notification
         */
        showWelcomeNotification() {
            const notification = new Notification(this.settings.strings.new_assignment, {
                body: 'You will now receive real-time delivery notifications.',
                icon: this.settings.pluginUrl + 'assets/images/icon-192x192.png',
                tag: 'rdm-welcome',
                requireInteraction: false
            });

            setTimeout(() => notification.close(), 3000);
        }

        /**
         * Load sound files
         */
        loadSounds() {
            if (!this.soundEnabled) return;

            Object.keys(this.settings.sounds || {}).forEach(soundKey => {
                const audio = new Audio(this.settings.sounds[soundKey]);
                audio.preload = 'auto';
                audio.volume = this.getSetting('volume', 70) / 100;
                this.sounds[soundKey] = audio;
            });
        }

        /**
         * Play sound with vibration
         */
        playAlertSound(soundKey = 'general', urgent = false) {
            // Play sound
            if (this.soundEnabled && this.sounds[soundKey]) {
                this.sounds[soundKey].currentTime = 0;
                this.sounds[soundKey].play().catch(error => {
                    console.warn('RestroReach: Failed to play sound:', error);
                });
            }

            // Vibrate for mobile devices
            if (this.vibrationEnabled && this.vibrationSupported) {
                if (urgent) {
                    // Urgent pattern: long-short-long
                    navigator.vibrate([500, 200, 500, 200, 500]);
                } else {
                    // Normal pattern: short vibration
                    navigator.vibrate([200]);
                }
            }
        }

        /**
         * Start real-time polling
         */
        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }

            const interval = this.settings.refresh_interval || 10000;
            
            this.pollInterval = setInterval(() => {
                if (this.isVisible && this.isOnline) {
                    this.checkForNotifications();
                }
            }, interval);

            // Initial check
            if (this.isOnline) {
                this.checkForNotifications();
            }
        }

        /**
         * Check for new notifications
         */
        checkForNotifications() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_get_realtime_notifications',
                    nonce: this.settings.nonce,
                    last_check: this.lastCheck,
                    user_role: this.settings.user_role
                },
                timeout: 10000,
                success: (response) => {
                    this.updateConnectionStatus(true);
                    
                    if (response.success && response.data.notifications.length > 0) {
                        this.processNewNotifications(response.data.notifications);
                        this.lastCheck = response.data.timestamp;
                    }
                },
                error: (xhr, status, error) => {
                    console.error('RestroReach: Failed to check notifications:', error);
                    this.updateConnectionStatus(false);
                }
            });
        }

        /**
         * Process new notifications
         */
        processNewNotifications(notifications) {
            notifications.forEach(notification => {
                this.displayAgentNotification(notification);
                
                // Play alert for urgent notifications or new assignments
                if (notification.is_urgent || notification.type === 'new_assignment') {
                    this.playAlertSound(this.getSoundForType(notification.type), notification.is_urgent);
                }
                
                // Show browser notification
                if (this.notificationPermission === 'granted') {
                    this.showBrowserNotification(notification);
                }
                
                // Track assignments
                if (notification.type === 'new_assignment' && notification.data.agent_id == this.settings.user_id) {
                    this.activeAssignments.add(notification.data.order_id);
                }
            });

            this.updateNotificationCount();
        }

        /**
         * Display agent notification
         */
        displayAgentNotification(notification) {
            const notificationHTML = `
                <div class="rdm-agent-notification ${notification.is_urgent ? 'urgent' : ''} ${notification.type}" 
                     data-id="${notification.id}" data-type="${notification.type}">
                    <div class="rdm-notification-header">
                        <div class="rdm-notification-icon">
                            <span class="dashicons ${this.getIconForType(notification.type)}"></span>
                        </div>
                        <div class="rdm-notification-title">${notification.title}</div>
                        <button class="rdm-notification-dismiss" data-id="${notification.id}">&times;</button>
                    </div>
                    <div class="rdm-notification-body">
                        <div class="rdm-notification-message">${notification.message}</div>
                        ${this.getNotificationMeta(notification)}
                    </div>
                    <div class="rdm-notification-actions">
                        ${this.getAgentActions(notification)}
                    </div>
                </div>
            `;

            const container = $('#rdm-agent-notifications');
            container.prepend(notificationHTML);

            // Animate in
            setTimeout(() => {
                container.find(`[data-id="${notification.id}"]`).addClass('show');
            }, 100);

            // Auto-dismiss non-urgent notifications
            if (!notification.is_urgent && notification.type !== 'new_assignment') {
                setTimeout(() => {
                    this.dismissNotification(notification.id);
                }, 10000);
            }

            // Limit displayed notifications
            this.limitDisplayedNotifications();
        }

        /**
         * Get notification metadata for display
         */
        getNotificationMeta(notification) {
            let meta = '';
            
            if (notification.data.order_id) {
                meta += `<div class="rdm-notification-meta">Order #${notification.data.order_id}</div>`;
            }
            
            if (notification.data.customer_name) {
                meta += `<div class="rdm-notification-meta">Customer: ${notification.data.customer_name}</div>`;
            }
            
            if (notification.data.order_total) {
                meta += `<div class="rdm-notification-meta">Total: ${notification.data.order_total}</div>`;
            }
            
            if (notification.data.estimated_time) {
                meta += `<div class="rdm-notification-meta">ETA: ${notification.data.estimated_time}</div>`;
            }
            
            return meta;
        }

        /**
         * Get actions for agent notifications
         */
        getAgentActions(notification) {
            const actions = [];

            switch (notification.type) {
                case 'new_assignment':
                    if (notification.data.agent_id == this.settings.user_id) {
                        actions.push(`
                            <button class="button-primary rdm-accept-assignment" data-order-id="${notification.data.order_id}">
                                <span class="dashicons dashicons-yes-alt"></span>
                                ${this.settings.strings.accept_assignment}
                            </button>
                        `);
                        actions.push(`
                            <button class="button-secondary rdm-view-details" data-order-id="${notification.data.order_id}">
                                <span class="dashicons dashicons-visibility"></span>
                                ${this.settings.strings.view_details}
                            </button>
                        `);
                    }
                    break;

                case 'order_ready':
                    if (this.activeAssignments.has(notification.data.order_id)) {
                        actions.push(`
                            <button class="button-primary rdm-mark-picked-up" data-order-id="${notification.data.order_id}">
                                <span class="dashicons dashicons-car"></span>
                                Mark Picked Up
                            </button>
                        `);
                    }
                    break;

                case 'payment_collected':
                    actions.push(`
                        <button class="button-secondary rdm-view-payment" data-order-id="${notification.data.order_id}">
                            <span class="dashicons dashicons-money-alt"></span>
                            View Payment
                        </button>
                    `);
                    break;

                default:
                    if (notification.data.order_id) {
                        actions.push(`
                            <button class="button-secondary rdm-view-order" data-order-id="${notification.data.order_id}">
                                <span class="dashicons dashicons-visibility"></span>
                                View Order
                            </button>
                        `);
                    }
                    break;
            }

            return actions.join(' ');
        }

        /**
         * Show browser notification
         */
        showBrowserNotification(notification) {
            if (!this.notificationSupported || this.notificationPermission !== 'granted') return;

            const browserNotification = new Notification(notification.title, {
                body: notification.message,
                icon: this.settings.pluginUrl + 'assets/images/icon-192x192.png',
                tag: `rdm-agent-${notification.id}`,
                requireInteraction: notification.is_urgent || notification.type === 'new_assignment',
                vibrate: notification.is_urgent ? [500, 200, 500] : [200]
            });

            browserNotification.onclick = () => {
                this.handleNotificationClick(notification);
                browserNotification.close();
            };

            // Auto-close after delay
            if (!notification.is_urgent) {
                setTimeout(() => browserNotification.close(), 8000);
            }
        }

        /**
         * Handle notification click
         */
        handleNotificationClick(notification) {
            if (notification.data.order_id) {
                // Navigate to order details or tracking page
                window.location.href = `#order-${notification.data.order_id}`;
            }
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Dismiss notifications
            $(document).on('click', '.rdm-notification-dismiss', function() {
                const notificationId = $(this).data('id');
                self.dismissNotification(notificationId);
            });

            // Accept assignment
            $(document).on('click', '.rdm-accept-assignment', function() {
                const orderId = $(this).data('order-id');
                self.acceptAssignment(orderId, $(this));
            });

            // Mark picked up
            $(document).on('click', '.rdm-mark-picked-up', function() {
                const orderId = $(this).data('order-id');
                self.markPickedUp(orderId, $(this));
            });

            // View order details
            $(document).on('click', '.rdm-view-details, .rdm-view-order', function() {
                const orderId = $(this).data('order-id');
                self.viewOrderDetails(orderId);
            });

            // Toggle availability
            $(document).on('click', '#rdm-toggle-availability', function() {
                self.toggleAvailability($(this));
            });

            // View assignments
            $(document).on('click', '#rdm-view-assignments', function() {
                self.viewAssignments();
            });

            // Settings
            $(document).on('click', '#rdm-notification-settings', function() {
                self.showSettings();
            });

            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                this.isVisible = !document.hidden;
                
                if (this.isVisible && this.isOnline) {
                    this.checkForNotifications();
                }
            });
        }

        /**
         * Handle connectivity changes
         */
        handleConnectivityChanges() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.updateConnectionStatus(true);
                this.checkForNotifications();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
                this.updateConnectionStatus(false);
            });
        }

        /**
         * Update connection status
         */
        updateConnectionStatus(isConnected) {
            const statusIndicator = $('#rdm-status-indicator');
            const connectionStatus = $('#rdm-connection-status');
            
            if (isConnected) {
                statusIndicator.removeClass('offline').addClass('online');
                statusIndicator.find('.rdm-status-text').text('Online');
                
                if (!this.isOnline) {
                    connectionStatus.removeClass('hidden').text('Connection restored');
                    setTimeout(() => connectionStatus.addClass('hidden'), 3000);
                }
            } else {
                statusIndicator.removeClass('online').addClass('offline');
                statusIndicator.find('.rdm-status-text').text('Offline');
                connectionStatus.removeClass('hidden').text('Connection lost');
            }
        }

        /**
         * Accept assignment
         */
        acceptAssignment(orderId, button) {
            button.prop('disabled', true).html('<span class="spinner"></span> Accepting...');

            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_accept_assignment',
                    nonce: this.settings.nonce,
                    order_id: orderId
                },
                success: (response) => {
                    if (response.success) {
                        button.closest('.rdm-agent-notification').addClass('accepted');
                        this.playAlertSound('payment_success');
                        
                        setTimeout(() => {
                            button.closest('.rdm-agent-notification').fadeOut();
                        }, 2000);
                    } else {
                        alert('Failed to accept assignment: ' + response.data);
                        button.prop('disabled', false).html(this.settings.strings.accept_assignment);
                    }
                },
                error: () => {
                    alert('Network error. Please try again.');
                    button.prop('disabled', false).html(this.settings.strings.accept_assignment);
                }
            });
        }

        /**
         * Mark order as picked up
         */
        markPickedUp(orderId, button) {
            button.prop('disabled', true).html('<span class="spinner"></span> Updating...');

            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_mark_picked_up',
                    nonce: this.settings.nonce,
                    order_id: orderId
                },
                success: (response) => {
                    if (response.success) {
                        button.closest('.rdm-agent-notification').addClass('picked-up');
                        this.playAlertSound('payment_success');
                        
                        setTimeout(() => {
                            button.closest('.rdm-agent-notification').fadeOut();
                        }, 2000);
                    } else {
                        alert('Failed to update status: ' + response.data);
                        button.prop('disabled', false).html('Mark Picked Up');
                    }
                },
                error: () => {
                    alert('Network error. Please try again.');
                    button.prop('disabled', false).html('Mark Picked Up');
                }
            });
        }

        /**
         * View order details
         */
        viewOrderDetails(orderId) {
            // Open order details in modal or navigate to details page
            window.location.href = `?page=rdm-order-details&order_id=${orderId}`;
        }

        /**
         * Toggle availability status
         */
        toggleAvailability(button) {
            const isAvailable = button.hasClass('available');
            const newStatus = !isAvailable;

            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_toggle_availability',
                    nonce: this.settings.nonce,
                    available: newStatus
                },
                success: (response) => {
                    if (response.success) {
                        if (newStatus) {
                            button.addClass('available').removeClass('unavailable');
                            button.find('.rdm-action-text').text('Available');
                            button.find('.dashicons').removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
                        } else {
                            button.removeClass('available').addClass('unavailable');
                            button.find('.rdm-action-text').text('Unavailable');
                            button.find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
                        }
                    }
                }
            });
        }

        /**
         * Utility methods
         */
        getIconForType(type) {
            const icons = {
                'new_assignment': 'dashicons-location',
                'order_ready': 'dashicons-yes-alt',
                'order_picked_up': 'dashicons-car',
                'payment_collected': 'dashicons-money-alt',
                'order_delivered': 'dashicons-yes'
            };

            return icons[type] || 'dashicons-bell';
        }

        getSoundForType(type) {
            const soundMap = {
                'new_assignment': 'new_assignment',
                'order_ready': 'order_ready',
                'payment_collected': 'payment_success'
            };

            return soundMap[type] || 'general';
        }

        dismissNotification(notificationId) {
            const notification = $(`.rdm-agent-notification[data-id="${notificationId}"]`);
            notification.addClass('dismissing');
            
            setTimeout(() => {
                notification.remove();
            }, 300);
        }

        limitDisplayedNotifications() {
            const notifications = $('.rdm-agent-notification');
            if (notifications.length > 5) {
                notifications.slice(5).remove();
            }
        }

        updateNotificationCount() {
            const count = $('.rdm-agent-notification:not(.accepted):not(.picked-up)').length;
            const countElement = $('#rdm-agent-notification-count .rdm-count');
            
            if (count > 0) {
                countElement.text(count).closest('#rdm-agent-notification-count').show();
            } else {
                countElement.closest('#rdm-agent-notification-count').hide();
            }
        }

        getSetting(key, defaultValue = null) {
            return localStorage.getItem(`rdm_agent_${key}`) || defaultValue;
        }

        saveSetting(key, value) {
            localStorage.setItem(`rdm_agent_${key}`, value);
        }

        viewAssignments() {
            window.location.href = '?page=rdm-my-orders';
        }

        showSettings() {
            // Simple settings modal - could be enhanced with a proper modal
            const soundEnabled = confirm('Enable sound alerts?');
            const vibrationEnabled = this.vibrationSupported ? confirm('Enable vibration alerts?') : false;
            
            this.soundEnabled = soundEnabled;
            this.vibrationEnabled = vibrationEnabled;
            
            this.saveSetting('sound_enabled', soundEnabled);
            this.saveSetting('vibration_enabled', vibrationEnabled);
            
            alert('Settings saved!');
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof rdmAgentNotifications !== 'undefined') {
            window.rdmAgentNotificationSystem = new RDMAgentNotifications();
        }
    });

})(jQuery); 