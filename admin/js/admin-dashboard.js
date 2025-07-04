/**
 * RestroReach Admin Dashboard Scripts
 *
 * @package RestroReach
 * @subpackage Admin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Dashboard refresh interval (30 seconds)
    const REFRESH_INTERVAL = 30000;

    // Cache DOM elements
    const $dashboard = $('.rdm-dashboard');
    const $statsGrid = $('.rdm-stats-grid');
    const $recentOrders = $('.rdm-recent-orders');
    const $agentsGrid = $('.rdm-agents-grid');

    /**
     * Initialize dashboard
     */
    function initDashboard() {
        // Load initial data
        loadDashboardData();

        // Set up refresh interval
        setInterval(loadDashboardData, REFRESH_INTERVAL);

        // Set up event listeners
        setupEventListeners();
    }

    /**
     * Load dashboard data via AJAX
     */
    function loadDashboardData() {
        // Show loading state
        showLoading();

        // Make AJAX request
        $.ajax({
            url: rdmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'rdm_get_dashboard_stats',
                nonce: rdmAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboard(response.data);
                } else {
                    showError(rdmAdmin.i18n.error);
                }
            },
            error: function() {
                showError(rdmAdmin.i18n.error);
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    /**
     * Update dashboard with new data
     *
     * @param {Object} data Dashboard data
     */
    function updateDashboard(data) {
        // Update statistics
        updateStats(data.stats);

        // Update system status
        updateSystemStatus(data.stats.system_status);

        // Update recent orders
        updateRecentOrders(data.recent_orders);

        // Update agent status
        updateAgentStatus(data.agent_status);
    }

    /**
     * Update statistics cards
     *
     * @param {Object} stats Statistics data
     */
    function updateStats(stats) {
        if (!$statsGrid.length) return;

        // Filter out system_status from regular stats
        const filteredStats = Object.fromEntries(
            Object.entries(stats).filter(([key]) => key !== 'system_status')
        );

        const statsHtml = Object.entries(filteredStats).map(([key, value]) => {
            // Handle simple values vs objects
            if (typeof value === 'object' && value.label) {
                const trend = value.trend || 0;
                const trendClass = trend > 0 ? 'positive' : trend < 0 ? 'negative' : '';
                const trendIcon = trend > 0 ? '↑' : trend < 0 ? '↓' : '';
                const trendText = trend ? `${trendIcon} ${Math.abs(trend)}%` : '';

                return `
                    <div class="rdm-stat-card">
                        <h3 class="rdm-stat-title">${value.label}</h3>
                        <p class="rdm-stat-value">${value.value}</p>
                        ${trendText ? `<p class="rdm-stat-trend ${trendClass}">${trendText}</p>` : ''}
                    </div>
                `;
            } else {
                // Handle simple key-value pairs
                return `
                    <div class="rdm-stat-card">
                        <h3 class="rdm-stat-title">${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</h3>
                        <p class="rdm-stat-value">${value}</p>
                    </div>
                `;
            }
        }).join('');

        $statsGrid.html(statsHtml);
    }

    /**
     * Update system status indicators
     *
     * @param {Object} systemStatus System status data
     */
    function updateSystemStatus(systemStatus) {
        if (!systemStatus) return;

        // Create or update system status section
        let $systemStatusSection = $('.rdm-system-status');
        
        if (!$systemStatusSection.length) {
            $systemStatusSection = $('<div class="rdm-system-status"></div>');
            $statsGrid.after($systemStatusSection);
        }

        const statusHtml = `
            <div class="rdm-section-header">
                <h2 class="rdm-section-title">System Status</h2>
            </div>
            <div class="rdm-status-grid">
                ${Object.entries(systemStatus).map(([key, status]) => `
                    <div class="rdm-status-card">
                        <div class="rdm-status-header">
                            <h4 class="rdm-status-title">${status.label}</h4>
                            <span class="rdm-status-indicator ${status.status.toLowerCase()}">
                                ${status.status}
                            </span>
                        </div>
                        <p class="rdm-status-message">${status.message}</p>
                        ${status.details ? `
                            <details class="rdm-status-details">
                                <summary>View Details</summary>
                                <ul>
                                    ${Object.entries(status.details).map(([tableName, tableStatus]) => `
                                        <li class="${tableStatus.exists ? 'status-ok' : 'status-error'}">
                                            ${tableStatus.name}: ${tableStatus.status}
                                        </li>
                                    `).join('')}
                                </ul>
                            </details>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
        `;

        $systemStatusSection.html(statusHtml);
    }

    /**
     * Update recent orders table
     *
     * @param {Array} orders Recent orders data
     */
    function updateRecentOrders(orders) {
        if (!$recentOrders.length) return;

        if (!orders.length) {
            $recentOrders.html(`<p>${rdmAdmin.i18n.noData}</p>`);
            return;
        }

        const ordersHtml = `
            <div class="rdm-section-header">
                <h2 class="rdm-section-title">${rdmAdmin.i18n.recentOrders}</h2>
                <a href="${rdmAdmin.ordersUrl}" class="rdm-button rdm-button-secondary">
                    ${rdmAdmin.i18n.viewAll}
                </a>
            </div>
            <table class="rdm-orders-table">
                <thead>
                    <tr>
                        <th>${rdmAdmin.i18n.orderId}</th>
                        <th>${rdmAdmin.i18n.customer}</th>
                        <th>${rdmAdmin.i18n.amount}</th>
                        <th>${rdmAdmin.i18n.status}</th>
                        <th>${rdmAdmin.i18n.agent}</th>
                        <th>${rdmAdmin.i18n.actions}</th>
                    </tr>
                </thead>
                <tbody>
                    ${orders.map(order => `
                        <tr>
                            <td>#${order.order_id}</td>
                            <td>${order.customer_name}</td>
                            <td>${order.amount}</td>
                            <td>
                                <span class="rdm-order-status ${order.status.toLowerCase()}">
                                    ${order.status}
                                </span>
                            </td>
                            <td>${order.agent_name || '-'}</td>
                            <td>
                                <a href="${rdmAdmin.orderUrl.replace('%id%', order.order_id)}" 
                                   class="rdm-button rdm-button-secondary">
                                    ${rdmAdmin.i18n.view}
                                </a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        $recentOrders.html(ordersHtml);
    }

    /**
     * Update agent status grid
     *
     * @param {Array} agents Agent status data
     */
    function updateAgentStatus(agents) {
        if (!$agentsGrid.length) return;

        if (!agents.length) {
            $agentsGrid.html(`<p>${rdmAdmin.i18n.noData}</p>`);
            return;
        }

        const agentsHtml = agents.map(agent => {
            const statusClass = agent.availability === 'online' ? 'online' : 
                              agent.availability === 'busy' ? 'busy' : 'offline';
            const statusText = agent.availability.charAt(0).toUpperCase() + 
                             agent.availability.slice(1);

            return `
                <div class="rdm-agent-card">
                    <div class="rdm-agent-header">
                        <div class="rdm-agent-avatar">
                            ${agent.display_name.charAt(0)}
                        </div>
                        <div class="rdm-agent-info">
                            <h3 class="rdm-agent-name">${agent.display_name}</h3>
                            <p class="rdm-agent-email">${agent.user_email}</p>
                        </div>
                    </div>
                    <div class="rdm-agent-status">
                        <span class="rdm-agent-status-dot ${statusClass}"></span>
                        ${statusText}
                        ${agent.active_deliveries ? `(${agent.active_deliveries} ${rdmAdmin.i18n.activeDeliveries})` : ''}
                    </div>
                </div>
            `;
        }).join('');

        $agentsGrid.html(agentsHtml);
    }

    /**
     * Show loading state
     */
    function showLoading() {
        const loadingHtml = `
            <div class="rdm-loading">
                <div class="rdm-loading-spinner"></div>
            </div>
        `;

        $dashboard.append(loadingHtml);
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('.rdm-loading').remove();
    }

    /**
     * Show error message
     *
     * @param {string} message Error message
     */
    function showError(message) {
        const errorHtml = `
            <div class="notice notice-error">
                <p>${message}</p>
            </div>
        `;

        $dashboard.prepend(errorHtml);

        // Remove error after 5 seconds
        setTimeout(() => {
            $('.notice-error').fadeOut(() => {
                $('.notice-error').remove();
            });
        }, 5000);
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        // Manual refresh button
        $('.rdm-refresh-dashboard').on('click', function(e) {
            e.preventDefault();
            loadDashboardData();
        });

        // Order status change
        $dashboard.on('click', '.rdm-order-status-change', function(e) {
            e.preventDefault();
            const $button = $(this);
            const orderId = $button.data('order-id');
            const newStatus = $button.data('status');

            if (!confirm(rdmAdmin.i18n.confirm)) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true);

            // Make AJAX request
            $.ajax({
                url: rdmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_update_order_status',
                    nonce: rdmAdmin.nonce,
                    order_id: orderId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        loadDashboardData();
                    } else {
                        showError(response.data.message || rdmAdmin.i18n.error);
                    }
                },
                error: function() {
                    showError(rdmAdmin.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Agent status change
        $dashboard.on('click', '.rdm-agent-status-change', function(e) {
            e.preventDefault();
            const $button = $(this);
            const agentId = $button.data('agent-id');
            const newStatus = $button.data('status');

            if (!confirm(rdmAdmin.i18n.confirm)) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true);

            // Make AJAX request
            $.ajax({
                url: rdmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_update_agent_status',
                    nonce: rdmAdmin.nonce,
                    agent_id: agentId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        loadDashboardData();
                    } else {
                        showError(response.data.message || rdmAdmin.i18n.error);
                    }
                },
                error: function() {
                    showError(rdmAdmin.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    // Initialize dashboard when document is ready
    $(document).ready(initDashboard);

})(jQuery); 