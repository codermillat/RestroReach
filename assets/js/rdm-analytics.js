/**
 * RestroReach Analytics Dashboard Scripts
 *
 * @package RestaurantDeliveryManager
 * @subpackage Assets
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Analytics Dashboard Class
    window.RDMAnalytics = {
        
        // Configuration
        config: {
            refreshInterval: 300000, // 5 minutes
            currentPeriod: 'month',
            charts: {},
            data: {},
        },
        
        // Initialize analytics dashboard
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },
        
        // Bind event handlers
        bindEvents: function() {
            const self = this;
            
            // Period selector
            $(document).on('click', '.rdm-period-btn', function(e) {
                e.preventDefault();
                const period = $(this).data('period');
                self.changePeriod(period);
            });
            
            // Export functionality
            $(document).on('click', '.rdm-export-btn', function(e) {
                e.preventDefault();
                const format = $(this).data('format') || 'pdf';
                self.exportReport(format);
            });
            
            // Refresh button
            $(document).on('click', '.rdm-refresh-analytics', function(e) {
                e.preventDefault();
                self.refreshData();
            });
            
            // Chart interaction
            $(document).on('click', '.rdm-chart-legend', function(e) {
                e.preventDefault();
                const chartId = $(this).data('chart');
                const datasetIndex = $(this).data('dataset');
                self.toggleChartDataset(chartId, datasetIndex);
            });
        },
        
        // Load initial analytics data
        loadInitialData: function() {
            this.loadAnalyticsData('overview', this.config.currentPeriod);
        },
        
        // Change time period
        changePeriod: function(period) {
            this.config.currentPeriod = period;
            
            // Update active state
            $('.rdm-period-btn').removeClass('active');
            $('.rdm-period-btn[data-period="' + period + '"]').addClass('active');
            
            // Reload data
            this.loadAnalyticsData('overview', period);
        },
        
        // Load analytics data via AJAX
        loadAnalyticsData: function(type, period) {
            const self = this;
            
            $.ajax({
                url: rdmAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_get_analytics_data',
                    nonce: rdmAnalytics.nonce,
                    type: type,
                    period: period
                },
                success: function(response) {
                    if (response.success) {
                        self.renderAnalytics(response.data);
                    }
                }
            });
        },
        
        // Render analytics data
        renderAnalytics: function(data) {
            this.renderRevenueCharts(data.revenue);
            this.renderAgentPerformance(data.agents);
            this.renderDeliveryTimes(data.delivery_times);
            this.renderPeakHours(data.peak_hours);
            this.renderSatisfactionMetrics(data.satisfaction);
            this.updateStatistics(data);
        },
        
        // Render revenue charts
        renderRevenueCharts: function(revenueData) {
            if (!revenueData || !revenueData.daily_revenue) return;
            
            // Revenue trend chart
            this.createLineChart('revenue-trend-chart', {
                labels: Object.keys(revenueData.daily_revenue),
                datasets: [{
                    label: 'Daily Revenue',
                    data: Object.values(revenueData.daily_revenue),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            }, {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Revenue Trend'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            });
            
            // Revenue by hour chart
            if (revenueData.revenue_by_hour) {
                const hourlyLabels = [];
                const hourlyData = [];
                
                for (let i = 0; i < 24; i++) {
                    hourlyLabels.push(i + ':00');
                    hourlyData.push(revenueData.revenue_by_hour[i] || 0);
                }
                
                this.createBarChart('revenue-hourly-chart', {
                    labels: hourlyLabels,
                    datasets: [{
                        label: 'Revenue by Hour',
                        data: hourlyData,
                        backgroundColor: '#2271b1',
                        borderColor: '#135e96',
                        borderWidth: 1
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Revenue by Hour of Day'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                });
            }
            
            // Payment methods pie chart
            if (revenueData.payment_methods) {
                const paymentLabels = [];
                const paymentData = [];
                const paymentColors = ['#2271b1', '#00a32a', '#dba617', '#d63638', '#8b5a3c'];
                
                Object.keys(revenueData.payment_methods).forEach((method, index) => {
                    paymentLabels.push(method.toUpperCase());
                    paymentData.push(revenueData.payment_methods[method].total);
                });
                
                this.createPieChart('payment-methods-chart', {
                    labels: paymentLabels,
                    datasets: [{
                        data: paymentData,
                        backgroundColor: paymentColors.slice(0, paymentLabels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Revenue by Payment Method'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                });
            }
        },
        
        // Render agent performance charts
        renderAgentPerformance: function(agentData) {
            if (!agentData || !agentData.agents) return;
            
            const agents = agentData.agents.slice(0, 10); // Top 10 agents
            const agentNames = agents.map(agent => agent.name);
            const deliveryCounts = agents.map(agent => agent.total_deliveries);
            const onTimeRates = agents.map(agent => agent.on_time_rate);
            
            // Agent deliveries chart
            this.createBarChart('agent-deliveries-chart', {
                labels: agentNames,
                datasets: [{
                    label: 'Total Deliveries',
                    data: deliveryCounts,
                    backgroundColor: '#00a32a',
                    borderColor: '#155724',
                    borderWidth: 1
                }]
            }, {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Agent Performance - Total Deliveries'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            });
            
            // On-time performance chart
            this.createBarChart('agent-ontime-chart', {
                labels: agentNames,
                datasets: [{
                    label: 'On-Time Rate (%)',
                    data: onTimeRates,
                    backgroundColor: onTimeRates.map(rate => {
                        if (rate >= 90) return '#00a32a';
                        if (rate >= 75) return '#dba617';
                        return '#d63638';
                    }),
                    borderWidth: 1
                }]
            }, {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Agent Performance - On-Time Delivery Rate'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            });
            
            // Render agent table
            this.renderAgentTable(agents);
        },
        
        // Render delivery time analytics
        renderDeliveryTimes: function(deliveryData) {
            if (!deliveryData) return;
            
            // Delivery time distribution
            if (deliveryData.distribution) {
                const labels = Object.keys(deliveryData.distribution);
                const data = Object.values(deliveryData.distribution);
                
                this.createBarChart('delivery-time-distribution-chart', {
                    labels: labels.map(label => label + ' min'),
                    datasets: [{
                        label: 'Number of Deliveries',
                        data: data,
                        backgroundColor: '#2271b1',
                        borderColor: '#135e96',
                        borderWidth: 1
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Delivery Time Distribution'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                });
            }
            
            // Average delivery time by hour
            if (deliveryData.by_hour) {
                const hourlyLabels = [];
                const hourlyAvgTimes = [];
                
                for (let i = 0; i < 24; i++) {
                    hourlyLabels.push(i + ':00');
                    hourlyAvgTimes.push(deliveryData.by_hour[i] ? deliveryData.by_hour[i].avg_time : 0);
                }
                
                this.createLineChart('delivery-time-hourly-chart', {
                    labels: hourlyLabels,
                    datasets: [{
                        label: 'Average Delivery Time (minutes)',
                        data: hourlyAvgTimes,
                        borderColor: '#dba617',
                        backgroundColor: 'rgba(219, 166, 23, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Average Delivery Time by Hour'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' min';
                                }
                            }
                        }
                    }
                });
            }
        },
        
        // Render peak hours analytics
        renderPeakHours: function(peakData) {
            if (!peakData) return;
            
            // Hourly orders chart
            if (peakData.hourly_orders) {
                const hourlyLabels = [];
                const hourlyOrders = [];
                
                for (let i = 0; i < 24; i++) {
                    hourlyLabels.push(i + ':00');
                    hourlyOrders.push(peakData.hourly_orders[i] || 0);
                }
                
                this.createBarChart('peak-hours-chart', {
                    labels: hourlyLabels,
                    datasets: [{
                        label: 'Number of Orders',
                        data: hourlyOrders,
                        backgroundColor: hourlyOrders.map((count, index) => {
                            return index === peakData.peak_hour ? '#d63638' : '#2271b1';
                        }),
                        borderWidth: 1
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Order Volume by Hour'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                });
            }
            
            // Daily orders chart
            if (peakData.daily_orders) {
                const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                const dailyOrders = [];
                
                for (let i = 1; i <= 7; i++) {
                    dailyOrders.push(peakData.daily_orders[i] || 0);
                }
                
                this.createBarChart('daily-orders-chart', {
                    labels: dayLabels,
                    datasets: [{
                        label: 'Number of Orders',
                        data: dailyOrders,
                        backgroundColor: dailyOrders.map((count, index) => {
                            return (index + 1) === peakData.peak_day ? '#d63638' : '#00a32a';
                        }),
                        borderWidth: 1
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Order Volume by Day of Week'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                });
            }
        },
        
        // Render satisfaction metrics
        renderSatisfactionMetrics: function(satisfactionData) {
            if (!satisfactionData) return;
            
            // Rating distribution
            if (satisfactionData.rating_distribution) {
                const ratingLabels = Object.keys(satisfactionData.rating_distribution).map(rating => rating + ' Star');
                const ratingData = Object.values(satisfactionData.rating_distribution);
                
                this.createBarChart('rating-distribution-chart', {
                    labels: ratingLabels,
                    datasets: [{
                        label: 'Number of Ratings',
                        data: ratingData,
                        backgroundColor: ['#d63638', '#dba617', '#646970', '#00a32a', '#2271b1'],
                        borderWidth: 1
                    }]
                }, {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Customer Rating Distribution'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                });
            }
        },
        
        // Render agent performance table
        renderAgentTable: function(agents) {
            const container = $('#agent-performance-table');
            if (!container.length || !agents.length) return;
            
            let html = `
                <div class="rdm-data-table-container">
                    <table class="rdm-data-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Deliveries</th>
                                <th>Avg Time</th>
                                <th>On-Time %</th>
                                <th>Success %</th>
                                <th>Rating</th>
                                <th>Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            agents.forEach(agent => {
                const performanceClass = this.getPerformanceClass(agent.on_time_rate);
                html += `
                    <tr>
                        <td>
                            <strong>${agent.name}</strong><br>
                            <small>${agent.email}</small>
                        </td>
                        <td class="rdm-number">${agent.total_deliveries}</td>
                        <td>${agent.avg_delivery_time} min</td>
                        <td class="rdm-percentage ${performanceClass}">${agent.on_time_rate}%</td>
                        <td class="rdm-percentage ${this.getPerformanceClass(agent.success_rate)}">${agent.success_rate}%</td>
                        <td>${agent.rating ? agent.rating.toFixed(1) : 'N/A'}</td>
                        <td class="rdm-number">$${agent.total_earnings ? agent.total_earnings.toFixed(2) : '0.00'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.html(html);
        },
        
        // Update statistics displays
        updateStatistics: function(data) {
            // Revenue statistics
            if (data.revenue) {
                $('#total-revenue').text('$' + data.revenue.total_revenue.toLocaleString());
                $('#average-order-value').text('$' + data.revenue.average_order_value.toFixed(2));
                $('#order-count').text(data.revenue.order_count.toLocaleString());
                $('#cod-collections').text('$' + data.revenue.cod_collections.toLocaleString());
            }
            
            // Agent statistics
            if (data.agents && data.agents.summary) {
                $('#active-agents').text(data.agents.summary.total_agents);
                $('#avg-delivery-time').text(data.agents.summary.avg_delivery_time.toFixed(1) + ' min');
                $('#on-time-rate').text(data.agents.summary.overall_on_time_rate.toFixed(1) + '%');
            }
            
            // Delivery statistics
            if (data.delivery_times) {
                $('#fastest-delivery').text(data.delivery_times.fastest_time + ' min');
                $('#slowest-delivery').text(data.delivery_times.slowest_time + ' min');
                $('#median-delivery-time').text(data.delivery_times.median_time + ' min');
            }
            
            // Satisfaction statistics
            if (data.satisfaction) {
                $('#avg-rating').text(data.satisfaction.average_rating.toFixed(1));
                $('#total-ratings').text(data.satisfaction.total_ratings.toLocaleString());
                $('#nps-score').text(data.satisfaction.nps_score.toFixed(1));
            }
        },
        
        // Chart creation helpers
        createLineChart: function(canvasId, data, options) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            this.destroyChart(canvasId);
            
            this.config.charts[canvasId] = new Chart(ctx, {
                type: 'line',
                data: data,
                options: options
            });
        },
        
        createBarChart: function(canvasId, data, options) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            this.destroyChart(canvasId);
            
            this.config.charts[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: options
            });
        },
        
        createPieChart: function(canvasId, data, options) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            this.destroyChart(canvasId);
            
            this.config.charts[canvasId] = new Chart(ctx, {
                type: 'pie',
                data: data,
                options: options
            });
        },
        
        destroyChart: function(chartId) {
            if (this.config.charts[chartId]) {
                this.config.charts[chartId].destroy();
                delete this.config.charts[chartId];
            }
        },
        
        // Export functionality
        exportReport: function(format) {
            const self = this;
            
            $.ajax({
                url: rdmAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_export_analytics',
                    nonce: rdmAnalytics.nonce,
                    format: format,
                    period: this.config.currentPeriod,
                    data: JSON.stringify(this.config.data)
                },
                beforeSend: function() {
                    $('.rdm-export-btn').prop('disabled', true).text('Exporting...');
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger download
                        const link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        self.showSuccess(rdmAnalytics.i18n.exportSuccess);
                    } else {
                        self.showError(response.data || rdmAnalytics.i18n.exportError);
                    }
                },
                error: function() {
                    self.showError(rdmAnalytics.i18n.exportError);
                },
                complete: function() {
                    $('.rdm-export-btn').prop('disabled', false).text('Export');
                }
            });
        },
        
        // Refresh data
        refreshData: function() {
            this.loadAnalyticsData('overview', this.config.currentPeriod);
        },
        
        // Helper functions
        getPerformanceClass: function(percentage) {
            if (percentage >= 90) return 'good';
            if (percentage >= 75) return 'average';
            return 'poor';
        },
        
        // UI feedback methods
        showLoading: function() {
            $('.rdm-analytics-grid').addClass('loading');
            $('.rdm-chart-container').html('<div class="rdm-chart-loading">' + rdmAnalytics.i18n.loading + '</div>');
        },
        
        hideLoading: function() {
            $('.rdm-analytics-grid').removeClass('loading');
        },
        
        showError: function(message) {
            $('.rdm-analytics-grid').prepend(
                '<div class="rdm-error"><span class="dashicons dashicons-warning"></span>' + message + '</div>'
            );
            setTimeout(function() {
                $('.rdm-error').fadeOut();
            }, 5000);
        },
        
        showSuccess: function(message) {
            $('.rdm-analytics-grid').prepend(
                '<div class="rdm-success"><span class="dashicons dashicons-yes"></span>' + message + '</div>'
            );
            setTimeout(function() {
                $('.rdm-success').fadeOut();
            }, 3000);
        },
        
        // Initialize tooltips
        initTooltips: function() {
            // Simple tooltip implementation
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const tooltip = $('<div class="rdm-tooltip">' + $(this).data('tooltip') + '</div>');
                $('body').append(tooltip);
                
                const offset = $(this).offset();
                tooltip.css({
                    top: offset.top - tooltip.outerHeight() - 10,
                    left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                });
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.rdm-tooltip').remove();
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.rdm-analytics-dashboard').length) {
            RDMAnalytics.init();
        }
    });
    
})(jQuery); 