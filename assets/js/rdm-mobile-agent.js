/**
 * Restaurant Delivery Manager - Enhanced Mobile Agent Interface
 * Production-ready mobile application with full order management capabilities
 */

(function($) {
    'use strict';

    // ========================================
    // Configuration & State Management
    // ========================================

    const RDM_CONFIG = {
        locationUpdateInterval: 45000, // 45 seconds - battery optimized
        offlineRetryInterval: 30000, // 30 seconds
        maxRetryAttempts: 3,
        cacheExpiry: 300000, // 5 minutes
        geolocationOptions: {
            enableHighAccuracy: false, // Battery optimization
            timeout: 15000,
            maximumAge: 60000
        }
    };

    const RDM_STATE = {
        isOnline: navigator.onLine,
        isTracking: false,
        watchId: null,
        batteryLevel: null,
        currentOrders: [],
        offlineQueue: [],
        lastSync: null,
        currentOrderId: null,
        retryAttempts: 0
    };

    // ========================================
    // Utility Functions
    // ========================================

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info', duration = 4000) {
        const $container = $('#rrm-toast-container');
        const $toast = $(`
            <div class="rrm-toast rrm-${type}">
                ${escapeHtml(message)}
            </div>
        `);
        
        $container.append($toast);
        
        setTimeout(() => {
            $toast.fadeOut(300, () => $toast.remove());
        }, duration);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    /**
     * Store data in localStorage with timestamp
     */
    function storeOfflineData(key, data) {
        try {
            const item = {
                data: data,
                timestamp: Date.now()
            };
            localStorage.setItem('rdm_' + key, JSON.stringify(item));
        } catch (e) {
            console.warn('Failed to store offline data:', e);
        }
    }

    /**
     * Get data from localStorage if not expired
     */
    function getOfflineData(key, maxAge = RDM_CONFIG.cacheExpiry) {
        try {
            const stored = localStorage.getItem('rdm_' + key);
            if (stored) {
                const item = JSON.parse(stored);
                if (Date.now() - item.timestamp < maxAge) {
                    return item.data;
                }
            }
        } catch (e) {
            console.warn('Failed to get offline data:', e);
        }
        return null;
    }

    /**
     * Add action to offline queue
     */
    function queueOfflineAction(action, data) {
        RDM_STATE.offlineQueue.push({
            action: action,
            data: data,
            timestamp: Date.now(),
            attempts: 0
        });
        storeOfflineData('queue', RDM_STATE.offlineQueue);
    }

    /**
     * Process offline queue when back online
     */
    function processOfflineQueue() {
        if (!RDM_STATE.isOnline || RDM_STATE.offlineQueue.length === 0) {
            return;
        }

        const queue = [...RDM_STATE.offlineQueue];
        RDM_STATE.offlineQueue = [];

        queue.forEach(item => {
            if (item.attempts < RDM_CONFIG.maxRetryAttempts) {
                executeOfflineAction(item);
            }
        });

        storeOfflineData('queue', RDM_STATE.offlineQueue);
    }

    /**
     * Execute queued offline action
     */
    function executeOfflineAction(item) {
        item.attempts++;

        const actionMap = {
            'update_location': sendLocationUpdate,
            'update_order_status': updateOrderStatus,
            'collect_payment': collectCODPayment
        };

        const handler = actionMap[item.action];
        if (handler) {
            handler(item.data)
                .fail(() => {
                    if (item.attempts < RDM_CONFIG.maxRetryAttempts) {
                        RDM_STATE.offlineQueue.push(item);
                    }
                });
        }
    }

    // ========================================
    // Network & Connectivity Management
    // ========================================

    /**
     * Handle online/offline status changes
     */
    function handleNetworkChange() {
        RDM_STATE.isOnline = navigator.onLine;
        updateNetworkUI();

        if (RDM_STATE.isOnline) {
            processOfflineQueue();
            syncData();
        }
    }

    /**
     * Update network status UI
     */
    function updateNetworkUI() {
        const $status = $('#rrm-network-status');
        const $indicator = $status.find('.rrm-status-indicator');
        const $text = $status.find('.rrm-status-text');
        const $offline = $('#rrm-offline-indicator');

        if (RDM_STATE.isOnline) {
            $indicator.removeClass('rrm-offline').addClass('rrm-online');
            $text.text('Online');
            $offline.hide();
        } else {
            $indicator.removeClass('rrm-online').addClass('rrm-offline');
            $text.text('Offline');
            $offline.show();
        }
    }

    /**
     * Sync data when coming back online
     */
    function syncData() {
        if (!RDM_STATE.isOnline) return;

        loadOrders();
        RDM_STATE.lastSync = Date.now();
    }

    // ========================================
    // GPS Location Tracking
    // ========================================

    /**
     * Initialize GPS tracking
     */
    function initGPSTracking() {
        // Get battery level if supported
        if ('getBattery' in navigator) {
            navigator.getBattery().then(battery => {
                RDM_STATE.batteryLevel = Math.round(battery.level * 100);
                
                battery.addEventListener('levelchange', () => {
                    RDM_STATE.batteryLevel = Math.round(battery.level * 100);
                    adjustTrackingFrequency();
                });
            });
        }

        // GPS toggle handler
        $('#rrm-gps-toggle').on('change', function() {
            const isEnabled = $(this).prop('checked');
            if (isEnabled) {
                startLocationTracking();
            } else {
                stopLocationTracking();
            }
        });
    }

    /**
     * Adjust tracking frequency based on battery level
     */
    function adjustTrackingFrequency() {
        if (RDM_STATE.batteryLevel < 20) {
            RDM_CONFIG.locationUpdateInterval = 120000; // 2 minutes
        } else if (RDM_STATE.batteryLevel < 50) {
            RDM_CONFIG.locationUpdateInterval = 60000; // 1 minute
        } else {
            RDM_CONFIG.locationUpdateInterval = 45000; // 45 seconds
        }
    }

    /**
     * Start location tracking
     */
    function startLocationTracking() {
        if (!navigator.geolocation) {
            showToast('Geolocation not supported by your device', 'error');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                RDM_STATE.isTracking = true;
                RDM_STATE.watchId = navigator.geolocation.watchPosition(
                    handleLocationUpdate,
                    handleLocationError,
                    RDM_CONFIG.geolocationOptions
                );
                updateGPSToggleUI(true);
                showToast('Location sharing started', 'success');
            },
            error => {
                handleLocationError(error);
                updateGPSToggleUI(false);
            },
            RDM_CONFIG.geolocationOptions
        );
    }

    /**
     * Stop location tracking
     */
    function stopLocationTracking() {
        if (RDM_STATE.watchId !== null) {
            navigator.geolocation.clearWatch(RDM_STATE.watchId);
            RDM_STATE.watchId = null;
        }
        RDM_STATE.isTracking = false;
        updateGPSToggleUI(false);
        showToast('Location sharing stopped', 'info');
    }

    /**
     * Handle location updates
     */
    function handleLocationUpdate(position) {
        const locationData = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            battery_level: RDM_STATE.batteryLevel
        };

        if (RDM_STATE.isOnline) {
            sendLocationUpdate(locationData);
        } else {
            queueOfflineAction('update_location', locationData);
        }
    }

    /**
     * Send location update to server
     */
    function sendLocationUpdate(locationData) {
        return $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_update_agent_location',
                nonce: rrmAgent.nonce,
                ...locationData
            },
            success: function(response) {
                if (response.success) {
                    updateGPSStatusUI('Active');
                } else {
                    console.warn('Location update failed:', response.data);
                }
            },
            error: function() {
                if (RDM_STATE.isOnline) {
                    queueOfflineAction('update_location', locationData);
                }
            }
        });
    }

    /**
     * Handle location errors
     */
    function handleLocationError(error) {
        let errorMessage = 'Location error: ';
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                errorMessage += 'Permission denied. Please enable location access.';
                break;
            case error.POSITION_UNAVAILABLE:
                errorMessage += 'Location unavailable. Check your GPS.';
                break;
            case error.TIMEOUT:
                errorMessage += 'Location request timed out.';
                break;
            default:
                errorMessage += 'Unknown error occurred.';
        }

        showToast(errorMessage, 'error');
        stopLocationTracking();
    }

    /**
     * Update GPS toggle UI
     */
    function updateGPSToggleUI(isEnabled) {
        const $toggle = $('#rrm-gps-toggle');
        $toggle.prop('checked', isEnabled);
        updateGPSStatusUI(isEnabled ? 'Active' : 'Inactive');
    }

    /**
     * Update GPS status text
     */
    function updateGPSStatusUI(status) {
        $('#rrm-gps-status').text(status);
    }

    // ========================================
    // Order Management
    // ========================================

    /**
     * Load orders from server
     */
    function loadOrders() {
        const $loading = $('#rrm-order-list-loading');
        const $container = $('#rrm-order-list-container');
        const $noOrders = $('#rrm-no-orders');

        $loading.show();
        $container.hide();
        $noOrders.hide();

        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_get_agent_orders',
                nonce: rrmAgent.nonce
            },
            success: function(response) {
                $loading.hide();
                
                if (response.success && response.data.orders) {
                    RDM_STATE.currentOrders = response.data.orders;
                    storeOfflineData('orders', RDM_STATE.currentOrders);
                    renderOrders(response.data.orders);
                } else {
                    showNoOrdersState();
                }
            },
            error: function() {
                $loading.hide();
                
                // Try to load from offline cache
                const cachedOrders = getOfflineData('orders');
                if (cachedOrders) {
                    renderOrders(cachedOrders);
                    showToast('Showing cached orders (offline)', 'warning');
                } else {
                    showNoOrdersState();
                    showToast('Failed to load orders', 'error');
                }
            }
        });
    }

    /**
     * Render orders in the UI
     */
    function renderOrders(orders) {
        const $container = $('#rrm-order-list-container');
        const $stats = $('#rrm-order-stats .rrm-stat');
        
        if (!orders || orders.length === 0) {
            showNoOrdersState();
            return;
        }

        $container.empty().show();
        $stats.text(`${orders.length} orders`);

        orders.forEach(order => {
            const $orderItem = createOrderElement(order);
            $container.append($orderItem);
        });

        $('#rrm-no-orders').hide();
    }

    /**
     * Create order element
     */
    function createOrderElement(order) {
        const statusClass = getStatusClass(order.status);
        const isCOD = order.payment_method === 'cod';
        
        return $(`
            <div class="rrm-order-item ${isCOD ? 'rrm-cod' : ''}" data-order-id="${order.id}">
                <div class="rrm-order-header">
                    <span class="rrm-order-id">#${order.id}</span>
                    <span class="rrm-order-status ${statusClass}">${order.status}</span>
                </div>
                <div class="rrm-order-info">
                    <div class="rrm-order-customer">${escapeHtml(order.customer)}</div>
                    <div class="rrm-order-address">${escapeHtml(order.address)}</div>
                </div>
                <div class="rrm-order-meta">
                    <span class="rrm-order-total">${order.formatted_total || formatCurrency(order.total)}</span>
                    <span class="rrm-order-payment">${order.payment_method_title || order.payment_method}</span>
                    ${isCOD ? '<span class="rrm-cod-badge">COD</span>' : ''}
                </div>
                <div class="rrm-order-actions">
                    ${generateOrderActions(order)}
                </div>
            </div>
        `);
    }

    /**
     * Generate action buttons based on order status
     */
    function generateOrderActions(order) {
        const actions = [];
        
        switch (order.status) {
            case 'assigned':
                actions.push('<button class="rrm-btn-small rrm-btn-accept" data-action="accept">Accept</button>');
                break;
            case 'accepted':
            case 'preparing':
                actions.push('<button class="rrm-btn-small rrm-btn-pickup" data-action="pickup">Mark Picked Up</button>');
                break;
            case 'ready-for-pickup':
                actions.push('<button class="rrm-btn-small rrm-btn-pickup" data-action="pickup">Mark Picked Up</button>');
                break;
            case 'out-for-delivery':
                if (order.payment_method === 'cod') {
                    actions.push('<button class="rrm-btn-small rrm-btn-secondary" data-action="collect-payment">Collect Payment</button>');
                }
                actions.push('<button class="rrm-btn-small rrm-btn-delivered" data-action="delivered">Mark Delivered</button>');
                break;
        }
        
        actions.push('<button class="rrm-btn-small rrm-btn-secondary" data-action="view-details">View Details</button>');
        
        return actions.join('');
    }

    /**
     * Get CSS class for order status
     */
    function getStatusClass(status) {
        const statusMap = {
            'preparing': 'rrm-preparing',
            'ready-for-pickup': 'rrm-ready',
            'out-for-delivery': 'rrm-delivering'
        };
        return statusMap[status] || '';
    }

    /**
     * Show no orders state
     */
    function showNoOrdersState() {
        $('#rrm-order-list-container').hide();
        $('#rrm-no-orders').show();
        $('#rrm-order-stats .rrm-stat').text('0 orders');
    }

    /**
     * Handle order actions
     */
    function handleOrderAction(orderId, action) {
        RDM_STATE.currentOrderId = orderId;
        
        switch (action) {
            case 'accept':
                acceptOrder(orderId);
                break;
            case 'pickup':
                updateOrderStatus(orderId, 'out-for-delivery', 'Order picked up');
                break;
            case 'delivered':
                showPhotoModal(orderId);
                break;
            case 'collect-payment':
                showCODModal(orderId);
                break;
            case 'view-details':
                showOrderDetails(orderId);
                break;
        }
    }

    /**
     * Accept order
     */
    function acceptOrder(orderId) {
        $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_accept_order',
                nonce: rrmAgent.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    showToast('Order accepted successfully', 'success');
                    loadOrders();
                } else {
                    showToast(response.data.message || 'Failed to accept order', 'error');
                }
            },
            error: function() {
                showToast('Network error. Try again.', 'error');
            }
        });
    }

    /**
     * Update order status
     */
    function updateOrderStatus(orderId, status, notes = '') {
        const data = {
            order_id: orderId,
            status: status,
            notes: notes
        };

        if (RDM_STATE.isOnline) {
            sendOrderStatusUpdate(data);
        } else {
            queueOfflineAction('update_order_status', data);
            showToast('Order status queued for sync', 'warning');
        }
    }

    /**
     * Send order status update to server
     */
    function sendOrderStatusUpdate(data) {
        return $.ajax({
            url: rrmAgent.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_update_order_status',
                nonce: rrmAgent.nonce,
                ...data
            },
            success: function(response) {
                if (response.success) {
                    showToast('Order status updated', 'success');
                    loadOrders();
                } else {
                    showToast(response.data.message || 'Failed to update status', 'error');
                }
            },
            error: function() {
                showToast('Network error. Status queued for sync.', 'warning');
                queueOfflineAction('update_order_status', data);
            }
        });
    }

    // ========================================
    // COD Payment Collection
    // ========================================

    /**
     * Show COD collection modal
     */
    function showCODModal(orderId) {
        const order = RDM_STATE.currentOrders.find(o => o.id == orderId);
        if (!order) {
            showToast('Order not found', 'error');
            return;
        }

        // Set modal data
        const $modal = $('#rrm-cod-modal');
        $modal.data('order-id', orderId);
        
        // Update order information
        $('#rrm-cod-order-number').text(order.order_number || `#${order.id}`);
        $('#rrm-cod-order-total').text(formatCurrency(parseFloat(order.total)));
        
        // Reset form
        clearAmount();
        $('#rrm-payment-notes').val('');
        $('#rrm-change-display').hide();
        $('#rrm-confirm-payment').prop('disabled', true);
        
        // Show modal
        $modal.addClass('active');
        
        // Focus on amount input after animation
        setTimeout(() => {
            $('#rrm-collected-amount').focus();
        }, 300);
    }

    /**
     * Close COD modal
     */
    function closeCODModal() {
        $('#rrm-cod-modal').removeClass('active');
    }

    /**
     * Add number to amount input
     */
    function addToAmount(value) {
        const $input = $('#rrm-collected-amount');
        const currentValue = $input.val();
        let newValue = currentValue + value;
        
        // Validate decimal places
        if (newValue.includes('.')) {
            const parts = newValue.split('.');
            if (parts[1] && parts[1].length > 2) {
                return; // Prevent more than 2 decimal places
            }
        }
        
        $input.val(newValue).trigger('input');
    }

    /**
     * Add decimal point to amount
     */
    function addDecimalToAmount() {
        const $input = $('#rrm-collected-amount');
        const currentValue = $input.val();
        
        if (!currentValue.includes('.')) {
            $input.val(currentValue + '.').trigger('input');
        }
    }

    /**
     * Clear amount input
     */
    function clearAmount() {
        $('#rrm-collected-amount').val('').trigger('input');
    }

    /**
     * Set specific amount
     */
    function setAmount(amount) {
        $('#rrm-collected-amount').val(amount.toFixed(2)).trigger('input');
    }

    /**
     * Set exact order amount
     */
    function setExactAmount() {
        const orderTotal = parseFloat($('#rrm-cod-order-total').text().replace('$', ''));
        setAmount(orderTotal);
    }

    /**
     * Calculate and display change
     */
    function calculateChange() {
        const $collectedInput = $('#rrm-collected-amount');
        const $changeDisplay = $('#rrm-change-display');
        const $changeAmount = $('#rrm-change-amount');
        const $confirmBtn = $('#rrm-confirm-payment');
        
        const collected = parseFloat($collectedInput.val()) || 0;
        const orderTotal = parseFloat($('#rrm-cod-order-total').text().replace('$', ''));
        
        if (collected >= orderTotal && collected > 0) {
            const change = collected - orderTotal;
            $changeAmount.text(formatCurrency(change));
            $changeDisplay.show();
            $confirmBtn.prop('disabled', false);
            
            // Update display colors
            if (change > 0) {
                $changeAmount.addClass('rrm-change');
            } else {
                $changeAmount.removeClass('rrm-change');
            }
        } else {
            $changeDisplay.hide();
            $confirmBtn.prop('disabled', true);
        }
    }

    /**
     * Enhanced COD payment confirmation with better error handling
     */
    function confirmCODPayment() {
        const orderId = $('#rrm-cod-modal').data('order-id');
        const collectedAmount = parseFloat($('#rrm-collected-amount').val());
        const notes = $('#rrm-payment-notes').val().trim();
        const orderTotal = parseFloat($('#rrm-cod-order-total').text().replace('$', ''));
        
        // Validation
        if (!collectedAmount || collectedAmount <= 0) {
            showToast('Please enter a valid amount', 'error');
            return;
        }
        
        if (collectedAmount < orderTotal) {
            showToast('Collected amount cannot be less than order total', 'error');
            return;
        }
        
        const changeAmount = collectedAmount - orderTotal;
        
        // Show confirmation dialog for large change amounts
        if (changeAmount > 50) {
            if (!confirm(`Change amount is $${changeAmount.toFixed(2)}. Please confirm this is correct.`)) {
                return;
            }
        }
        
        const data = {
            action: 'rdm_collect_cod_payment',
            nonce: rdm_ajax.nonce,
            order_id: orderId,
            collected_amount: collectedAmount,
            change_amount: changeAmount,
            notes: notes,
            timestamp: new Date().toISOString()
        };
        
        // Disable button and show loading
        const $confirmBtn = $('#rrm-confirm-payment');
        const originalText = $confirmBtn.text();
        $confirmBtn.prop('disabled', true).text('Processing...');
        
        if (!RDM_STATE.isOnline) {
            // Queue for offline sync
            queueOfflineAction('collect_payment', data);
            showToast('Payment queued for sync when online', 'warning');
            closeCODModal();
            updateOrderStatusLocally(orderId, 'delivered');
            return;
        }
        
        $.ajax({
            url: rdm_ajax.ajax_url,
            type: 'POST',
            data: data,
            timeout: 15000,
            success: function(response) {
                if (response.success) {
                    showToast('Payment collected successfully!', 'success');
                    closeCODModal();
                    
                    // Update order status in local data
                    updateOrderStatusLocally(orderId, 'delivered');
                    loadOrders(); // Refresh order list
                    
                    // Update daily totals
                    updateDailyTotals(collectedAmount, changeAmount);
                } else {
                    showToast(response.data?.message || 'Payment collection failed', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('COD payment collection error:', error);
                
                if (status === 'timeout') {
                    showToast('Request timeout. Payment queued for retry.', 'warning');
                } else {
                    showToast('Network error. Payment queued for sync.', 'warning');
                }
                
                // Queue for offline sync
                queueOfflineAction('collect_payment', data);
                closeCODModal();
                updateOrderStatusLocally(orderId, 'delivered');
            },
            complete: function() {
                $confirmBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Update order status locally
     */
    function updateOrderStatusLocally(orderId, newStatus) {
        const orderIndex = RDM_STATE.currentOrders.findIndex(o => o.id == orderId);
        if (orderIndex !== -1) {
            RDM_STATE.currentOrders[orderIndex].status = newStatus;
            if (newStatus === 'delivered') {
                RDM_STATE.currentOrders[orderIndex].completed_at = new Date().toISOString();
            }
        }
    }

    // ========================================
    // Cash Reconciliation System
    // ========================================

    /**
     * Show daily cash reconciliation modal
     */
    function showReconciliationModal() {
        loadReconciliationData();
        $('#rrm-reconciliation-modal').addClass('active');
    }

    /**
     * Close reconciliation modal
     */
    function closeReconciliationModal() {
        $('#rrm-reconciliation-modal').removeClass('active');
    }

    /**
     * Load reconciliation data for today
     */
    function loadReconciliationData() {
        const data = {
            action: 'rdm_get_agent_reconciliation',
            nonce: rdm_ajax.nonce,
            date: new Date().toISOString().split('T')[0] // Today's date
        };
        
        $.ajax({
            url: rdm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    populateReconciliationData(response.data);
                } else {
                    // Initialize with empty data
                    populateReconciliationData({
                        total_orders: 0,
                        total_collections: 0,
                        total_change: 0,
                        expected_cash: 0
                    });
                }
            },
            error: function() {
                showToast('Failed to load reconciliation data', 'error');
            }
        });
    }

    /**
     * Populate reconciliation modal with data
     */
    function populateReconciliationData(data) {
        $('#rrm-total-orders').text(data.total_orders || 0);
        $('#rrm-total-collections').text(formatCurrency(data.total_collections || 0));
        $('#rrm-total-change').text(formatCurrency(data.total_change || 0));
        $('#rrm-expected-cash').text(formatCurrency(data.expected_cash || (data.total_collections - data.total_change) || 0));
        
        // Reset form
        $('#rrm-actual-cash').val('');
        $('#rrm-reconciliation-notes').val('');
        $('#rrm-variance-display').hide();
    }

    /**
     * Calculate variance between expected and actual cash
     */
    function calculateVariance() {
        const $actualInput = $('#rrm-actual-cash');
        const $varianceDisplay = $('#rrm-variance-display');
        const $varianceAmount = $('#rrm-variance-amount');
        
        const actualCash = parseFloat($actualInput.val()) || 0;
        const expectedCash = parseFloat($('#rrm-expected-cash').text().replace('$', ''));
        
        if (actualCash > 0) {
            const variance = actualCash - expectedCash;
            $varianceAmount.text(formatCurrency(Math.abs(variance)));
            
            // Update display style based on variance
            $varianceDisplay.removeClass('positive negative');
            if (variance > 0) {
                $varianceDisplay.addClass('positive');
                $varianceAmount.css('color', '#28a745');
            } else if (variance < 0) {
                $varianceDisplay.addClass('negative');
                $varianceAmount.css('color', '#dc3545');
            } else {
                $varianceAmount.css('color', '#333');
            }
            
            $varianceDisplay.show();
        } else {
            $varianceDisplay.hide();
        }
    }

    /**
     * Submit daily cash reconciliation
     */
    function submitReconciliation() {
        const actualCash = parseFloat($('#rrm-actual-cash').val());
        const expectedCash = parseFloat($('#rrm-expected-cash').text().replace('$', ''));
        const notes = $('#rrm-reconciliation-notes').val().trim();
        
        // Validation
        if (!actualCash || actualCash < 0) {
            showToast('Please enter the actual cash amount', 'error');
            return;
        }
        
        const variance = actualCash - expectedCash;
        
        // Require notes for significant variances
        if (Math.abs(variance) > 5 && !notes) {
            showToast('Please provide notes explaining the variance', 'error');
            return;
        }
        
        const data = {
            action: 'rdm_submit_reconciliation',
            nonce: rdm_ajax.nonce,
            actual_cash: actualCash,
            expected_cash: expectedCash,
            variance: variance,
            notes: notes,
            date: new Date().toISOString().split('T')[0]
        };
        
        // Show loading state
        const $submitBtn = $('#rrm-submit-reconciliation');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Submitting...');
        
        if (!RDM_STATE.isOnline) {
            queueOfflineAction('submit_reconciliation', data);
            showToast('Reconciliation queued for sync when online', 'warning');
            closeReconciliationModal();
            return;
        }
        
        $.ajax({
            url: rdm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    showToast('Reconciliation submitted successfully!', 'success');
                    closeReconciliationModal();
                } else {
                    showToast(response.data?.message || 'Reconciliation submission failed', 'error');
                }
            },
            error: function() {
                showToast('Network error. Reconciliation queued for sync.', 'warning');
                queueOfflineAction('submit_reconciliation', data);
                closeReconciliationModal();
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Update daily totals (called after successful payment collection)
     */
    function updateDailyTotals(collectedAmount, changeAmount) {
        // Update stored daily totals for reconciliation
        const dailyTotals = getOfflineData('daily_totals') || {
            total_orders: 0,
            total_collections: 0,
            total_change: 0,
            date: new Date().toISOString().split('T')[0]
        };
        
        // Check if it's a new day
        const today = new Date().toISOString().split('T')[0];
        if (dailyTotals.date !== today) {
            // Reset for new day
            dailyTotals.total_orders = 0;
            dailyTotals.total_collections = 0;
            dailyTotals.total_change = 0;
            dailyTotals.date = today;
        }
        
        // Update totals
        dailyTotals.total_orders += 1;
        dailyTotals.total_collections += collectedAmount;
        dailyTotals.total_change += changeAmount;
        
        storeOfflineData('daily_totals', dailyTotals);
    }

    // ========================================
    // Pull to Refresh
    // ========================================

    /**
     * Initialize pull to refresh
     */
    function initPullToRefresh() {
        let startY = 0;
        let currentY = 0;
        let pulling = false;
        const threshold = 80;
        
        const $container = $('#rrm-order-list-container');
        
        $container.on('touchstart', function(e) {
            if ($container.scrollTop() === 0) {
                startY = e.originalEvent.touches[0].pageY;
                pulling = true;
            }
        });
        
        $container.on('touchmove', function(e) {
            if (pulling) {
                currentY = e.originalEvent.touches[0].pageY;
                const deltaY = currentY - startY;
                
                if (deltaY > 0 && deltaY < threshold * 2) {
                    e.preventDefault();
                    
                    if (deltaY > threshold) {
                        // Show ready to refresh state
                        showPullRefreshIndicator(true);
                    } else {
                        showPullRefreshIndicator(false);
                    }
                }
            }
        });
        
        $container.on('touchend', function() {
            if (pulling) {
                const deltaY = currentY - startY;
                
                if (deltaY > threshold) {
                    loadOrders();
                    showToast('Refreshing orders...', 'info');
                }
                
                hidePullRefreshIndicator();
                pulling = false;
            }
        });
    }

    /**
     * Show pull refresh indicator
     */
    function showPullRefreshIndicator(ready) {
        // Implementation for pull refresh visual feedback
        console.log('Pull refresh:', ready ? 'Ready' : 'Pulling');
    }

    /**
     * Hide pull refresh indicator
     */
    function hidePullRefreshIndicator() {
        // Implementation for hiding pull refresh indicator
        console.log('Hide pull refresh');
    }

    // ========================================
    // Event Handlers & Initialization
    // ========================================

    /**
     * Initialize all functionality
     */
    function init() {
        // Network event listeners
        window.addEventListener('online', handleNetworkChange);
        window.addEventListener('offline', handleNetworkChange);
        
        // Load stored offline queue
        const storedQueue = getOfflineData('queue', Infinity);
        if (storedQueue) {
            RDM_STATE.offlineQueue = storedQueue;
        }

        // Initialize components
        initGPSTracking();
        initPullToRefresh();
        
        // Load initial data
        loadOrders();
        
        // Update network UI
        handleNetworkChange();
        
        // Set up auto-refresh
        setInterval(() => {
            if (RDM_STATE.isOnline) {
                loadOrders();
            }
        }, 60000); // Refresh every minute
        
        console.log('Mobile Agent Interface initialized');
    }

    // ========================================
    // jQuery Document Ready
    // ========================================

    $(document).ready(function() {
        // Initialize main functionality
        init();
        
        // Initialize COD payment system
        initializeCODSystem();

        // Refresh button
        $('#rrm-refresh-btn').on('click', function() {
            loadOrders();
            showToast('Refreshing...', 'info');
        });

        // Logout button
        $('#rrm-logout-btn').on('click', function() {
            // Clear auth cookie
            document.cookie = 'rdm_agent_logged_in=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            // Clear offline data
            localStorage.removeItem('rdm_orders');
            localStorage.removeItem('rdm_queue');
            
            // Redirect to login
            window.location.href = rrmAgent.loginUrl;
        });

        // Emergency button
        $('#rrm-emergency-btn').on('click', function() {
            $('#rrm-emergency-modal').show();
        });

        // Modal close buttons
        $('.rrm-modal-close').on('click', function() {
            $(this).closest('.rrm-modal').hide();
        });

        // Modal backdrop click to close
        $('.rrm-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Order action handlers
        $(document).on('click', '.rrm-order-item .rrm-btn-small', function(e) {
            e.stopPropagation();
            const orderId = $(this).closest('.rrm-order-item').data('order-id');
            const action = $(this).data('action');
            handleOrderAction(orderId, action);
        });

        // Order item click for details
        $(document).on('click', '.rrm-order-item', function() {
            const orderId = $(this).data('order-id');
            showOrderDetails(orderId);
        });

        // Photo capture handlers
        $('#rrm-take-photo').on('click', handlePhotoCapture);
        $('#rrm-upload-photo').on('click', uploadDeliveryPhoto);
        $('#rrm-retake-photo').on('click', retakePhoto);

        // Photo input change handler
        $('#rrm-photo-input').on('change', function() {
            const file = this.files[0];
            if (file) {
                handlePhotoSelection(file);
            }
        });

        // Bottom navigation (future tabs)
        $('.rrm-nav-item').on('click', function() {
            $('.rrm-nav-item').removeClass('rrm-active');
            $(this).addClass('rrm-active');
            
            const tab = $(this).data('tab');
            // Future implementation for different tabs
            console.log('Navigate to tab:', tab);
        });
    });

})(jQuery);