/**
 * Restaurant Delivery Manager - Enhanced Admin Notifications
 *
 * @package RestaurantDeliveryManager
 * @subpackage Assets/JavaScript
 * @since 2.0.0
 * @version 2.0.0 - Enhanced with real-time communication
 */

(function($) {
    'use strict';

    /**
     * Enhanced Notifications Class
     */
    class RDMEnhancedNotifications {
        constructor() {
            this.settings = rdmNotifications || {};
            this.lastCheck = Math.floor(Date.now() / 1000);
            this.pollInterval = null;
            this.notificationPermission = 'default';
            this.soundEnabled = this.getSetting('sound_enabled', true);
            this.browserEnabled = this.getSetting('browser_enabled', true);
            this.isVisible = true;
            this.unreadCount = 0;
            this.urgentCount = 0;
            this.audioContext = null;
            this.sounds = {};
            
            this.init();
        }

        /**
         * Initialize the notification system
         */
        init() {
            console.log('RestroReach: Initializing enhanced notifications system');
            
            // Check browser notification support
            this.checkNotificationSupport();
            
            // Initialize UI elements
            this.initializeUI();
            
            // Load sound files
            this.loadSounds();
            
            // Start real-time polling
            this.startPolling();
            
            // Set up event listeners
            this.bindEvents();
            
            // Get initial notification count
            this.updateNotificationCount();
            
            // Handle page visibility changes
            this.handleVisibilityChange();
        }

        /**
         * Check browser notification support and request permission
         */
        checkNotificationSupport() {
            if (!('Notification' in window)) {
                console.warn('RestroReach: Browser notifications not supported');
                this.browserEnabled = false;
                return;
            }

            this.notificationPermission = Notification.permission;

            if (this.notificationPermission === 'default') {
                this.requestNotificationPermission();
            } else if (this.notificationPermission === 'denied') {
                console.warn('RestroReach: Notification permission denied');
                this.browserEnabled = false;
            }
        }

        /**
         * Request notification permission
         */
        requestNotificationPermission() {
            if (!this.browserEnabled) return;

            Notification.requestPermission().then((permission) => {
                this.notificationPermission = permission;
                if (permission === 'granted') {
                    console.log('RestroReach: Notification permission granted');
                    this.showPermissionGrantedNotification();
                } else {
                    console.warn('RestroReach: Notification permission denied');
                    this.browserEnabled = false;
                }
            }).catch((error) => {
                console.error('RestroReach: Error requesting notification permission:', error);
            });
        }

        /**
         * Show confirmation notification when permission is granted
         */
        showPermissionGrantedNotification() {
            const notification = new Notification(this.settings.strings.notification_permission_title, {
                body: 'You will now receive real-time order notifications.',
                icon: this.settings.restaurant_logo || '/wp-admin/images/wordpress-logo.svg',
                tag: 'rdm-permission-granted'
            });

            setTimeout(() => notification.close(), 3000);
        }

        /**
         * Initialize UI elements
         */
        initializeUI() {
            // Add notification center to admin bar
            this.addNotificationCenter();
            
            // Add floating notification container
            this.addFloatingContainer();
            
            // Add settings panel
            this.addSettingsPanel();
        }

        /**
         * Add notification center to admin bar
         */
        addNotificationCenter() {
            const adminBar = $('#wpadminbar');
            if (adminBar.length === 0) return;

            const notificationHTML = `
                <li id="rdm-notification-center">
                    <a href="#" class="ab-item" id="rdm-notification-toggle">
                        <span class="ab-icon dashicons dashicons-bell"></span>
                        <span id="rdm-notification-count" class="rdm-count hidden">0</span>
                        <span class="ab-label">Notifications</span>
                    </a>
                    <div class="ab-sub-wrapper" id="rdm-notification-dropdown">
                        <ul class="ab-submenu">
                            <li class="rdm-notification-header">
                                <div class="rdm-notification-title">Recent Notifications</div>
                                <div class="rdm-notification-actions">
                                    <button class="button-link" id="rdm-mark-all-read">Mark All Read</button>
                                    <button class="button-link" id="rdm-notification-settings">Settings</button>
                                </div>
                            </li>
                            <li id="rdm-notification-list">
                                <div class="rdm-loading">Loading notifications...</div>
                            </li>
                        </ul>
                    </div>
                </li>
            `;

            adminBar.find('#wp-admin-bar-root-default').append(notificationHTML);
        }

        /**
         * Add floating notification container
         */
        addFloatingContainer() {
            const containerHTML = `
                <div id="rdm-notification-container" class="rdm-notification-container">
                    <!-- Real-time notifications will appear here -->
                </div>
            `;

            $('body').append(containerHTML);
        }

        /**
         * Add settings panel
         */
        addSettingsPanel() {
            const panelHTML = `
                <div id="rdm-notification-settings-panel" class="rdm-settings-panel hidden">
                    <div class="rdm-settings-header">
                        <h3>Notification Settings</h3>
                        <button class="rdm-close-settings">&times;</button>
                    </div>
                    <div class="rdm-settings-content">
                        <div class="rdm-setting-group">
                            <label>
                                <input type="checkbox" id="rdm-setting-browser" ${this.browserEnabled ? 'checked' : ''}>
                                Browser Notifications
                            </label>
                            <p class="description">Show desktop notifications for new orders and updates</p>
                        </div>
                        <div class="rdm-setting-group">
                            <label>
                                <input type="checkbox" id="rdm-setting-sound" ${this.soundEnabled ? 'checked' : ''}>
                                Sound Alerts
                            </label>
                            <p class="description">Play sound alerts for important notifications</p>
                        </div>
                        <div class="rdm-setting-group">
                            <label for="rdm-setting-volume">Volume Level:</label>
                            <input type="range" id="rdm-setting-volume" min="0" max="100" value="70">
                        </div>
                        <div class="rdm-settings-actions">
                            <button class="button-primary" id="rdm-save-settings">Save Settings</button>
                            <button class="button" id="rdm-test-notification">Test Notification</button>
                            <button class="button" id="rdm-test-sound">Test Sound</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(panelHTML);
        }

        /**
         * Load sound files
         */
        loadSounds() {
            if (!this.soundEnabled) return;

            // Initialize Web Audio API
            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            } catch (error) {
                console.warn('RestroReach: Web Audio API not supported, falling back to HTML5 audio');
            }

            // Load sound files
            Object.keys(this.settings.sounds || {}).forEach(soundKey => {
                const soundUrl = this.settings.sounds[soundKey];
                this.loadSound(soundKey, soundUrl);
            });
        }

        /**
         * Load individual sound file
         */
        loadSound(key, url) {
            if (this.audioContext) {
                // Web Audio API method for better performance
                fetch(url)
                    .then(response => response.arrayBuffer())
                    .then(data => this.audioContext.decodeAudioData(data))
                    .then(audioBuffer => {
                        this.sounds[key] = audioBuffer;
                    })
                    .catch(error => {
                        console.warn(`RestroReach: Failed to load sound ${key}:`, error);
                        // Fallback to HTML5 audio
                        this.sounds[key] = new Audio(url);
                    });
            } else {
                // HTML5 audio fallback
                this.sounds[key] = new Audio(url);
                this.sounds[key].preload = 'auto';
            }
        }

        /**
         * Play sound alert
         */
        playSound(soundKey = 'general') {
            if (!this.soundEnabled || !this.sounds[soundKey]) return;

            try {
                const sound = this.sounds[soundKey];
                const volume = this.getSetting('volume', 70) / 100;

                if (this.audioContext && sound instanceof AudioBuffer) {
                    // Web Audio API playback
                    const source = this.audioContext.createBufferSource();
                    const gainNode = this.audioContext.createGain();
                    
                    source.buffer = sound;
                    gainNode.gain.value = volume;
                    
                    source.connect(gainNode);
                    gainNode.connect(this.audioContext.destination);
                    source.start();
                } else if (sound instanceof Audio) {
                    // HTML5 audio playback
                    sound.volume = volume;
                    sound.currentTime = 0;
                    sound.play().catch(error => {
                        console.warn('RestroReach: Failed to play sound:', error);
                    });
                }
            } catch (error) {
                console.error('RestroReach: Error playing sound:', error);
            }
        }

        /**
         * Start real-time polling
         */
        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }

            const interval = this.settings.refresh_interval || 15000;
            
            this.pollInterval = setInterval(() => {
                if (this.isVisible) {
                    this.checkForNotifications();
                }
            }, interval);

            // Initial check
            this.checkForNotifications();
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
                    last_check: this.lastCheck
                },
                success: (response) => {
                    if (response.success && response.data.notifications.length > 0) {
                        this.processNewNotifications(response.data.notifications);
                        this.lastCheck = response.data.timestamp;
                    }
                },
                error: (xhr, status, error) => {
                    console.error('RestroReach: Failed to check notifications:', error);
                }
            });
        }

        /**
         * Process new notifications
         */
        processNewNotifications(notifications) {
            notifications.forEach(notification => {
                this.displayNotification(notification);
                
                if (notification.is_urgent || notification.sound) {
                    this.playSound(this.getSoundForType(notification.type));
                }
                
                if (this.browserEnabled && this.notificationPermission === 'granted') {
                    this.showBrowserNotification(notification);
                }
            });

            // Update notification count
            this.updateNotificationCount();
        }

        /**
         * Display notification in floating container
         */
        displayNotification(notification) {
            const notificationHTML = `
                <div class="rdm-floating-notification ${notification.is_urgent ? 'urgent' : ''}" 
                     data-id="${notification.id}" data-type="${notification.type}">
                    <div class="rdm-notification-icon">
                        <span class="dashicons ${this.getIconForType(notification.type)}"></span>
                    </div>
                    <div class="rdm-notification-content">
                        <div class="rdm-notification-title">${notification.title}</div>
                        <div class="rdm-notification-message">${notification.message}</div>
                        ${notification.data.order_id ? `<div class="rdm-notification-meta">Order #${notification.data.order_id}</div>` : ''}
                    </div>
                    <div class="rdm-notification-actions">
                        ${this.getActionsForType(notification)}
                        <button class="rdm-dismiss-notification" data-id="${notification.id}">&times;</button>
                    </div>
                </div>
            `;

            const container = $('#rdm-notification-container');
            container.append(notificationHTML);

            // Auto-dismiss after delay (unless urgent)
            if (!notification.is_urgent) {
                setTimeout(() => {
                    this.dismissNotification(notification.id);
                }, 8000);
            }

            // Animate in
            setTimeout(() => {
                container.find(`[data-id="${notification.id}"]`).addClass('show');
            }, 100);
        }

        /**
         * Show browser notification
         */
        showBrowserNotification(notification) {
            if (!this.browserEnabled || this.notificationPermission !== 'granted') return;

            const browserNotification = new Notification(notification.title, {
                body: notification.message,
                icon: this.settings.restaurant_logo || '/wp-admin/images/wordpress-logo.svg',
                tag: `rdm-${notification.id}`,
                requireInteraction: notification.is_urgent,
                actions: this.getBrowserActionsForType(notification)
            });

            // Handle notification click
            browserNotification.onclick = (event) => {
                event.preventDefault();
                this.handleNotificationClick(notification);
                browserNotification.close();
            };

            // Auto-close after delay
            if (!notification.is_urgent) {
                setTimeout(() => browserNotification.close(), 10000);
            }
        }

        /**
         * Handle notification click
         */
        handleNotificationClick(notification) {
            if (notification.data.order_id) {
                // Navigate to order page
                const orderUrl = `${this.settings.admin_url}post.php?post=${notification.data.order_id}&action=edit`;
                window.open(orderUrl, '_blank');
            }
        }

        /**
         * Get icon for notification type
         */
        getIconForType(type) {
            const icons = {
                'order_processing': 'dashicons-cart',
                'order_preparing': 'dashicons-clock',
                'order_ready': 'dashicons-yes-alt',
                'order_assigned': 'dashicons-admin-users',
                'new_assignment': 'dashicons-location',
                'order_picked_up': 'dashicons-car',
                'order_delivered': 'dashicons-yes',
                'payment_collected': 'dashicons-money-alt',
                'system_alert': 'dashicons-warning'
            };

            return icons[type] || 'dashicons-bell';
        }

        /**
         * Get sound for notification type
         */
        getSoundForType(type) {
            const soundMap = {
                'order_processing': 'new_order',
                'order_ready': 'urgent_alert',
                'new_assignment': 'assignment',
                'payment_collected': 'payment_success',
                'order_delivered': 'delivery_success'
            };

            return soundMap[type] || 'general';
        }

        /**
         * Get actions for notification type
         */
        getActionsForType(notification) {
            const actions = [];

            if (notification.data.order_id) {
                actions.push(`<button class="button-link rdm-view-order" data-order-id="${notification.data.order_id}">View Order</button>`);
            }

            if (notification.type === 'new_assignment' && notification.data.agent_id == this.settings.user_id) {
                actions.push(`<button class="button-primary rdm-accept-assignment" data-order-id="${notification.data.order_id}">Accept</button>`);
            }

            return actions.join(' ');
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Notification center toggle
            $(document).on('click', '#rdm-notification-toggle', function(e) {
                e.preventDefault();
                $('#rdm-notification-dropdown').toggle();
                self.loadNotificationHistory();
            });

            // Dismiss floating notifications
            $(document).on('click', '.rdm-dismiss-notification', function() {
                const notificationId = $(this).data('id');
                self.dismissNotification(notificationId);
            });

            // Mark all as read
            $(document).on('click', '#rdm-mark-all-read', function() {
                self.markAllAsRead();
            });

            // Settings panel
            $(document).on('click', '#rdm-notification-settings', function(e) {
                e.preventDefault();
                $('#rdm-notification-settings-panel').removeClass('hidden');
            });

            $(document).on('click', '.rdm-close-settings', function() {
                $('#rdm-notification-settings-panel').addClass('hidden');
            });

            // Save settings
            $(document).on('click', '#rdm-save-settings', function() {
                self.saveSettings();
            });

            // Test notification
            $(document).on('click', '#rdm-test-notification', function() {
                self.sendTestNotification();
            });

            // Test sound
            $(document).on('click', '#rdm-test-sound', function() {
                self.playSound('new_order');
            });

            // View order
            $(document).on('click', '.rdm-view-order', function() {
                const orderId = $(this).data('order-id');
                const orderUrl = `${self.settings.admin_url}post.php?post=${orderId}&action=edit`;
                window.open(orderUrl, '_blank');
            });

            // Accept assignment
            $(document).on('click', '.rdm-accept-assignment', function() {
                const orderId = $(this).data('order-id');
                self.acceptAssignment(orderId);
            });

            // Setting changes
            $(document).on('change', '#rdm-setting-browser', function() {
                if ($(this).is(':checked')) {
                    self.requestNotificationPermission();
                } else {
                    self.browserEnabled = false;
                }
            });

            $(document).on('change', '#rdm-setting-sound', function() {
                self.soundEnabled = $(this).is(':checked');
            });
        }

        /**
         * Handle page visibility changes
         */
        handleVisibilityChange() {
            document.addEventListener('visibilitychange', () => {
                this.isVisible = !document.hidden;
                
                if (this.isVisible) {
                    // Page became visible, check for notifications immediately
                    this.checkForNotifications();
                }
            });
        }

        /**
         * Update notification count display
         */
        updateNotificationCount() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_get_notification_count',
                    nonce: this.settings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.unreadCount = response.data.unread_count;
                        this.urgentCount = response.data.urgent_count;
                        this.updateCountDisplay();
                    }
                }
            });
        }

        /**
         * Update count display in UI
         */
        updateCountDisplay() {
            const countElement = $('#rdm-notification-count');
            
            if (this.unreadCount > 0) {
                countElement.text(this.unreadCount).removeClass('hidden');
                
                if (this.urgentCount > 0) {
                    countElement.addClass('urgent');
                } else {
                    countElement.removeClass('urgent');
                }
            } else {
                countElement.addClass('hidden');
            }

            // Update page title
            this.updatePageTitle();
        }

        /**
         * Update page title with notification count
         */
        updatePageTitle() {
            let title = document.title;
            
            // Remove existing notification count
            title = title.replace(/^\(\d+\)\s*/, '');
            
            if (this.unreadCount > 0) {
                title = `(${this.unreadCount}) ${title}`;
            }
            
            document.title = title;
        }

        /**
         * Dismiss notification
         */
        dismissNotification(notificationId) {
            const notification = $(`.rdm-floating-notification[data-id="${notificationId}"]`);
            
            notification.removeClass('show').addClass('dismissing');
            
            setTimeout(() => {
                notification.remove();
            }, 300);
        }

        /**
         * Mark all notifications as read
         */
        markAllAsRead() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_mark_all_notifications_read',
                    nonce: this.settings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.unreadCount = 0;
                        this.urgentCount = 0;
                        this.updateCountDisplay();
                        this.loadNotificationHistory();
                    }
                }
            });
        }

        /**
         * Load notification history
         */
        loadNotificationHistory() {
            const listElement = $('#rdm-notification-list');
            listElement.html('<div class="rdm-loading">Loading notifications...</div>');

            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_get_notifications',
                    nonce: this.settings.nonce,
                    limit: 10
                },
                success: (response) => {
                    if (response.success) {
                        this.renderNotificationHistory(response.data);
                    } else {
                        listElement.html('<div class="rdm-error">Failed to load notifications</div>');
                    }
                },
                error: () => {
                    listElement.html('<div class="rdm-error">Failed to load notifications</div>');
                }
            });
        }

        /**
         * Render notification history
         */
        renderNotificationHistory(notifications) {
            const listElement = $('#rdm-notification-list');
            
            if (notifications.length === 0) {
                listElement.html('<div class="rdm-empty">No notifications found</div>');
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                html += `
                    <div class="rdm-notification-item ${notification.is_read ? 'read' : 'unread'}">
                        <div class="rdm-notification-icon">
                            <span class="dashicons ${this.getIconForType(notification.type)}"></span>
                        </div>
                        <div class="rdm-notification-content">
                            <div class="rdm-notification-title">${notification.title}</div>
                            <div class="rdm-notification-message">${notification.message}</div>
                            <div class="rdm-notification-time">${this.formatTime(notification.created_at)}</div>
                        </div>
                        ${!notification.is_read ? `<button class="rdm-mark-read" data-id="${notification.id}">Mark Read</button>` : ''}
                    </div>
                `;
            });

            listElement.html(html);
        }

        /**
         * Send test notification
         */
        sendTestNotification() {
            $.ajax({
                url: this.settings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rdm_test_notification',
                    nonce: this.settings.nonce,
                    notification_type: 'system_alert'
                },
                success: (response) => {
                    if (response.success) {
                        alert('Test notification sent successfully!');
                    } else {
                        alert('Failed to send test notification: ' + response.data);
                    }
                }
            });
        }

        /**
         * Save notification settings
         */
        saveSettings() {
            const settings = {
                browser_enabled: $('#rdm-setting-browser').is(':checked'),
                sound_enabled: $('#rdm-setting-sound').is(':checked'),
                volume: $('#rdm-setting-volume').val()
            };

            // Save to localStorage
            Object.keys(settings).forEach(key => {
                this.saveSetting(key, settings[key]);
            });

            this.browserEnabled = settings.browser_enabled;
            this.soundEnabled = settings.sound_enabled;

            $('#rdm-notification-settings-panel').addClass('hidden');
            alert('Settings saved successfully!');
        }

        /**
         * Accept assignment
         */
        acceptAssignment(orderId) {
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
                        $(`.rdm-accept-assignment[data-order-id="${orderId}"]`).closest('.rdm-floating-notification').fadeOut();
                        alert('Assignment accepted successfully!');
                    } else {
                        alert('Failed to accept assignment: ' + response.data);
                    }
                }
            });
        }

        /**
         * Utility methods
         */
        getSetting(key, defaultValue = null) {
            return localStorage.getItem(`rdm_notification_${key}`) || defaultValue;
        }

        saveSetting(key, value) {
            localStorage.setItem(`rdm_notification_${key}`, value);
        }

        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) { // Less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // Less than 1 hour
                return Math.floor(diff / 60000) + ' minutes ago';
            } else if (diff < 86400000) { // Less than 1 day
                return Math.floor(diff / 3600000) + ' hours ago';
            } else {
                return date.toLocaleDateString();
            }
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof rdmNotifications !== 'undefined') {
            window.rdmNotificationSystem = new RDMEnhancedNotifications();
        }
    });

})(jQuery); 