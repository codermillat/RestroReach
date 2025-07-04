<?php
/**
 * Analytics Dashboard Template
 *
 * @package RestaurantDeliveryManager
 * @subpackage Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rdm-analytics-dashboard">
    <div class="rdm-analytics-header">
        <h1 class="rdm-analytics-title"><?php esc_html_e('Analytics & Reports', 'restaurant-delivery-manager'); ?></h1>
        <div class="rdm-analytics-controls">
            <!-- Period Selector -->
            <div class="rdm-period-selector">
                <button type="button" class="rdm-period-btn" data-period="today">Today</button>
                <button type="button" class="rdm-period-btn" data-period="week">Week</button>
                <button type="button" class="rdm-period-btn active" data-period="month">Month</button>
                <button type="button" class="rdm-period-btn" data-period="quarter">Quarter</button>
                <button type="button" class="rdm-period-btn" data-period="year">Year</button>
            </div>
            
            <!-- Export Options -->
                <button type="button" class="rdm-period-btn" data-period="today">
                    <?php esc_html_e('Today', 'restaurant-delivery-manager'); ?>
                </button>
                <button type="button" class="rdm-period-btn" data-period="week">
                    <?php esc_html_e('Week', 'restaurant-delivery-manager'); ?>
                </button>
                <button type="button" class="rdm-period-btn active" data-period="month">
                    <?php esc_html_e('Month', 'restaurant-delivery-manager'); ?>
                </button>
                <button type="button" class="rdm-period-btn" data-period="quarter">
                    <?php esc_html_e('Quarter', 'restaurant-delivery-manager'); ?>
                </button>
                <button type="button" class="rdm-period-btn" data-period="year">
                    <?php esc_html_e('Year', 'restaurant-delivery-manager'); ?>
                </button>
            </div>
            
            <!-- Export Options -->
            <button type="button" class="rdm-export-btn" data-format="pdf">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export PDF', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" class="rdm-export-btn" data-format="csv">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php esc_html_e('Export CSV', 'restaurant-delivery-manager'); ?>
            </button>
            
            <!-- Refresh Button -->
            <button type="button" class="rdm-refresh-analytics">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'restaurant-delivery-manager'); ?>
            </button>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="rdm-analytics-grid">
        <!-- Revenue Metrics -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Revenue Overview', 'restaurant-delivery-manager'); ?></h3>
                <div class="rdm-card-actions">
                    <button type="button" class="rdm-card-action" data-action="details">
                        <?php esc_html_e('Details', 'restaurant-delivery-manager'); ?>
                    </button>
                </div>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-stat-grid">
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="total-revenue">$0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Total Revenue', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+12.5%</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="average-order-value">$0.00</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Avg Order Value', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+5.2%</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="order-count">0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Total Orders', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+8.1%</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="cod-collections">$0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('COD Collections', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change neutral">
                            <span class="dashicons dashicons-minus"></span>
                            <span>0%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Performance -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Agent Performance', 'restaurant-delivery-manager'); ?></h3>
                <div class="rdm-card-actions">
                    <button type="button" class="rdm-card-action" data-action="individual">
                        <?php esc_html_e('Individual Reports', 'restaurant-delivery-manager'); ?>
                    </button>
                </div>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-stat-grid">
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="active-agents">0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Active Agents', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="avg-delivery-time">0 min</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Avg Delivery Time', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <span>-3.2 min</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="on-time-rate">0%</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('On-Time Rate', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+2.1%</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="agent-efficiency">0%</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Efficiency Rate', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+1.8%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Analytics -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Delivery Analytics', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-stat-grid">
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="fastest-delivery">0 min</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Fastest Delivery', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="slowest-delivery">0 min</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Slowest Delivery', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="median-delivery-time">0 min</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Median Time', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="peak-hour">12:00</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Peak Hour', 'restaurant-delivery-manager'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Satisfaction -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Customer Satisfaction', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-stat-grid">
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="avg-rating">0.0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Average Rating', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-rating-stars">
                            ★★★★☆
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="total-ratings">0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Total Ratings', 'restaurant-delivery-manager'); ?></p>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="nps-score">0</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('NPS Score', 'restaurant-delivery-manager'); ?></p>
                        <div class="rdm-stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+5 pts</span>
                        </div>
                    </div>
                    <div class="rdm-stat-item">
                        <h4 class="rdm-stat-value" id="repeat-customers">0%</h4>
                        <p class="rdm-stat-label"><?php esc_html_e('Repeat Customers', 'restaurant-delivery-manager'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="rdm-analytics-grid">
        <!-- Revenue Trend Chart -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Revenue Trend', 'restaurant-delivery-manager'); ?></h3>
                <div class="rdm-card-actions">
                    <button type="button" class="rdm-card-action" data-action="fullscreen">
                        <?php esc_html_e('Fullscreen', 'restaurant-delivery-manager'); ?>
                    </button>
                </div>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-chart-container">
                    <canvas id="revenue-trend-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Agent Performance Chart -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Agent Deliveries', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-chart-container">
                    <canvas id="agent-deliveries-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Delivery Time Distribution -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Delivery Time Distribution', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-chart-container">
                    <canvas id="delivery-time-distribution-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Peak Hours Chart -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Order Volume by Hour', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-chart-container">
                    <canvas id="peak-hours-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Tables Section -->
    <div class="rdm-analytics-grid">
        <!-- Agent Performance Table -->
        <div class="rdm-analytics-card" style="grid-column: 1 / -1;">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Agent Performance Details', 'restaurant-delivery-manager'); ?></h3>
                <div class="rdm-card-actions">
                    <button type="button" class="rdm-card-action" data-action="export-agents">
                        <?php esc_html_e('Export Table', 'restaurant-delivery-manager'); ?>
                    </button>
                </div>
            </div>
            <div class="rdm-card-content">
                <div id="agent-performance-table">
                    <!-- Agent table will be loaded here via JavaScript -->
                    <div class="rdm-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Loading agent performance data...', 'restaurant-delivery-manager'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Insights -->
    <div class="rdm-analytics-grid">
        <!-- Quick Insights -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Quick Insights', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-insights-list">
                    <div class="rdm-insight-item">
                        <span class="rdm-insight-icon good">📈</span>
                        <div class="rdm-insight-content">
                            <h4><?php esc_html_e('Peak Performance', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Lunch hours (12-2 PM) show highest order volume', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                    <div class="rdm-insight-item">
                        <span class="rdm-insight-icon warning">⚠️</span>
                        <div class="rdm-insight-content">
                            <h4><?php esc_html_e('Attention Needed', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Weekend delivery times are 15% higher than weekdays', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                    <div class="rdm-insight-item">
                        <span class="rdm-insight-icon good">💰</span>
                        <div class="rdm-insight-content">
                            <h4><?php esc_html_e('Revenue Growth', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Month-over-month revenue increased by 12.5%', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forecast -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Demand Forecast', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-forecast-grid">
                    <div class="rdm-forecast-item">
                        <h4><?php esc_html_e('Today', 'restaurant-delivery-manager'); ?></h4>
                        <p class="rdm-forecast-value">~45 orders</p>
                        <small class="rdm-forecast-confidence">85% confidence</small>
                    </div>
                    <div class="rdm-forecast-item">
                        <h4><?php esc_html_e('Tomorrow', 'restaurant-delivery-manager'); ?></h4>
                        <p class="rdm-forecast-value">~52 orders</p>
                        <small class="rdm-forecast-confidence">78% confidence</small>
                    </div>
                    <div class="rdm-forecast-item">
                        <h4><?php esc_html_e('This Weekend', 'restaurant-delivery-manager'); ?></h4>
                        <p class="rdm-forecast-value">~120 orders</p>
                        <small class="rdm-forecast-confidence">72% confidence</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="rdm-analytics-card">
            <div class="rdm-card-header">
                <h3 class="rdm-card-title"><?php esc_html_e('Recommendations', 'restaurant-delivery-manager'); ?></h3>
            </div>
            <div class="rdm-card-content">
                <div class="rdm-recommendations-list">
                    <div class="rdm-recommendation-item">
                        <span class="rdm-recommendation-priority high">HIGH</span>
                        <div class="rdm-recommendation-content">
                            <h4><?php esc_html_e('Add Peak Hour Staff', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Consider adding 2 more agents during 12-2 PM to reduce delivery times', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                    <div class="rdm-recommendation-item">
                        <span class="rdm-recommendation-priority medium">MED</span>
                        <div class="rdm-recommendation-content">
                            <h4><?php esc_html_e('Weekend Optimization', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Review weekend scheduling to improve delivery efficiency', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                    <div class="rdm-recommendation-item">
                        <span class="rdm-recommendation-priority low">LOW</span>
                        <div class="rdm-recommendation-content">
                            <h4><?php esc_html_e('Customer Feedback', 'restaurant-delivery-manager'); ?></h4>
                            <p><?php esc_html_e('Implement rating system to track customer satisfaction', 'restaurant-delivery-manager'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional inline styles for specific analytics elements */
.rdm-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 20px;
}

.rdm-stat-item {
    text-align: center;
}

.rdm-rating-stars {
    color: #ffb900;
    font-size: 16px;
    margin-top: 5px;
}

.rdm-insights-list,
.rdm-recommendations-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.rdm-insight-item,
.rdm-recommendation-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 4px;
}

.rdm-insight-icon {
    font-size: 24px;
    min-width: 32px;
}

.rdm-insight-content h4,
.rdm-recommendation-content h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #1d2327;
}

.rdm-insight-content p,
.rdm-recommendation-content p {
    margin: 0;
    font-size: 13px;
    color: #646970;
}

.rdm-forecast-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 15px;
    text-align: center;
}

.rdm-forecast-value {
    font-size: 18px;
    font-weight: 600;
    color: #2271b1;
    margin: 5px 0;
}

.rdm-forecast-confidence {
    color: #646970;
    font-size: 11px;
}

.rdm-recommendation-priority {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    min-width: 40px;
}

.rdm-recommendation-priority.high {
    background: #f8d7da;
    color: #721c24;
}

.rdm-recommendation-priority.medium {
    background: #fff3cd;
    color: #856404;
}

.rdm-recommendation-priority.low {
    background: #d1e7dd;
    color: #0f5132;
}

@media screen and (max-width: 768px) {
    .rdm-stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .rdm-forecast-grid {
        grid-template-columns: 1fr;
    }
}
</style> 