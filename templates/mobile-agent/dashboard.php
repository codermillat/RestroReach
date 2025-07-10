<?php
/**
 * Restaurant Delivery Manager - Mobile Agent Dashboard
 * Template for the delivery agent's mobile interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current agent data
$agent_id = get_current_user_id();
$agent_data = RDM_Mobile_Frontend::get_agent_data($agent_id);
?>

<div class="rrm-dashboard">
    <!-- GPS Status Section -->
    <div class="rrm-gps-section">
        <div class="rrm-gps-header">
            <h3>Location Sharing</h3>
            <div class="rrm-gps-status-wrapper">
                <span id="rdm-gps-status" class="text-danger">Location sharing inactive</span>
                <span id="rdm-last-update" class="rrm-last-update"></span>
            </div>
        </div>
        
        <div class="rrm-gps-controls">
            <label class="rrm-switch">
                <input type="checkbox" id="rdm-gps-toggle">
                <span class="rrm-slider"></span>
            </label>
            <span class="rrm-gps-label">Enable Location Sharing</span>
        </div>
        
        <div class="rrm-gps-info">
            <p class="rrm-gps-note">
                <i class="fas fa-info-circle"></i>
                Location updates are sent every 45 seconds to optimize battery life.
            </p>
        </div>
    </div>

    <!-- Orders Section -->
    <div class="rrm-orders-section">
        <h3>My Orders</h3>
        <div id="rrm-orders-list" class="rrm-orders-list">
            <!-- Orders will be loaded here via AJAX -->
            <div class="rrm-loading">
                <i class="fas fa-spinner fa-spin"></i>
                Loading orders...
            </div>
        </div>
    </div>

    <!-- Emergency Button -->
    <div class="rrm-emergency-section">
        <button id="rrm-emergency-btn" class="rrm-emergency-btn">
            <i class="fas fa-exclamation-triangle"></i>
            Emergency
        </button>
    </div>
</div>

<!-- Emergency Modal -->
<div id="rrm-emergency-modal" class="rrm-modal">
    <div class="rrm-modal-content">
        <span class="rrm-close">&times;</span>
        <h3>Emergency Contact</h3>
        <p>Please select the type of emergency:</p>
        <div class="rrm-emergency-options">
            <button class="rrm-emergency-option" data-type="accident">
                <i class="fas fa-car-crash"></i>
                Accident
            </button>
            <button class="rrm-emergency-option" data-type="medical">
                <i class="fas fa-ambulance"></i>
                Medical
            </button>
            <button class="rrm-emergency-option" data-type="safety">
                <i class="fas fa-shield-alt"></i>
                Safety Concern
            </button>
            <button class="rrm-emergency-option" data-type="other">
                <i class="fas fa-question-circle"></i>
                Other
            </button>
        </div>
    </div>
</div>

<!-- COD Payment Collection Modal -->
<div id="rrm-cod-modal" class="rrm-modal">
    <div class="rrm-modal-content rrm-cod-modal-content">
        <div class="rrm-modal-header">
            <h3><?php _e('Collect Payment', 'restaurant-delivery-manager'); ?></h3>
            <span class="rrm-close rrm-cod-close">&times;</span>
        </div>
        
        <div class="rrm-cod-content">
            <div class="rrm-order-details">
                <div class="rrm-order-info">
                    <span class="rrm-label"><?php _e('Order:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-cod-order-number" class="rrm-value">-</span>
                </div>
                <div class="rrm-order-total-info">
                    <span class="rrm-label"><?php _e('Total Amount:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-cod-order-total" class="rrm-value rrm-amount">$0.00</span>
                </div>
            </div>
            
            <div class="rrm-payment-form">
                <div class="rrm-input-group">
                    <label for="rrm-collected-amount"><?php _e('Amount Received:', 'restaurant-delivery-manager'); ?></label>
                    <div class="rrm-amount-input-wrapper">
                        <span class="rrm-currency-symbol">$</span>
                        <input type="number" 
                               id="rrm-collected-amount" 
                               class="rrm-amount-input" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00"
                               inputmode="decimal">
                    </div>
                </div>
                
                <div id="rrm-change-display" class="rrm-change-display" style="display: none;">
                    <div class="rrm-change-info">
                        <span class="rrm-label"><?php _e('Change to Give:', 'restaurant-delivery-manager'); ?></span>
                        <span id="rrm-change-amount" class="rrm-value rrm-amount rrm-change">$0.00</span>
                    </div>
                </div>
                
                <div class="rrm-payment-calculator">
                    <div class="rrm-calc-grid">
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="1">1</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="2">2</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="3">3</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="4">4</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="5">5</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="6">6</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="7">7</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="8">8</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="9">9</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-clear"><?php _e('Clear', 'restaurant-delivery-manager'); ?></button>
                        <button type="button" class="rrm-calc-btn rrm-calc-number" data-value="0">0</button>
                        <button type="button" class="rrm-calc-btn rrm-calc-decimal" data-value=".">.</button>
                    </div>
                </div>
                
                <div class="rrm-quick-amounts">
                    <h4><?php _e('Quick Amounts:', 'restaurant-delivery-manager'); ?></h4>
                    <div class="rrm-quick-buttons">
                        <button type="button" class="rrm-quick-amount" data-amount="20">$20</button>
                        <button type="button" class="rrm-quick-amount" data-amount="50">$50</button>
                        <button type="button" class="rrm-quick-amount" data-amount="100">$100</button>
                        <button type="button" class="rrm-quick-exact"><?php _e('Exact', 'restaurant-delivery-manager'); ?></button>
                    </div>
                </div>
                
                <div class="rrm-notes-section">
                    <label for="rrm-payment-notes"><?php _e('Notes (Optional):', 'restaurant-delivery-manager'); ?></label>
                    <textarea id="rrm-payment-notes" 
                              class="rrm-notes-input" 
                              placeholder="<?php esc_attr_e('Any additional notes about the payment...', 'restaurant-delivery-manager'); ?>"
                              rows="2"></textarea>
                </div>
            </div>
        </div>
        
        <div class="rrm-modal-footer">
            <button type="button" id="rrm-cancel-payment" class="rrm-btn rrm-btn-secondary">
                <?php _e('Cancel', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" id="rrm-confirm-payment" class="rrm-btn rrm-btn-primary" disabled>
                <?php _e('Confirm Payment', 'restaurant-delivery-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Cash Reconciliation Modal -->
<div id="rrm-reconciliation-modal" class="rrm-modal">
    <div class="rrm-modal-content rrm-reconciliation-modal-content">
        <div class="rrm-modal-header">
            <h3><?php _e('Daily Cash Reconciliation', 'restaurant-delivery-manager'); ?></h3>
            <span class="rrm-close rrm-reconciliation-close">&times;</span>
        </div>
        
        <div class="rrm-reconciliation-content">
            <div class="rrm-reconciliation-summary">
                <div class="rrm-summary-row">
                    <span class="rrm-label"><?php _e('Orders Completed:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-total-orders" class="rrm-value">0</span>
                </div>
                <div class="rrm-summary-row">
                    <span class="rrm-label"><?php _e('Total Collections:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-total-collections" class="rrm-value rrm-amount">$0.00</span>
                </div>
                <div class="rrm-summary-row">
                    <span class="rrm-label"><?php _e('Total Change Given:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-total-change" class="rrm-value rrm-amount">$0.00</span>
                </div>
                <div class="rrm-summary-row rrm-summary-total">
                    <span class="rrm-label"><?php _e('Expected Cash in Hand:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-expected-cash" class="rrm-value rrm-amount">$0.00</span>
                </div>
            </div>
            
            <div class="rrm-cash-count">
                <label for="rrm-actual-cash"><?php _e('Actual Cash Count:', 'restaurant-delivery-manager'); ?></label>
                <div class="rrm-amount-input-wrapper">
                    <span class="rrm-currency-symbol">$</span>
                    <input type="number" 
                           id="rrm-actual-cash" 
                           class="rrm-amount-input" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00"
                           inputmode="decimal">
                </div>
            </div>
            
            <div id="rrm-variance-display" class="rrm-variance-display" style="display: none;">
                <div class="rrm-variance-info">
                    <span class="rrm-label"><?php _e('Variance:', 'restaurant-delivery-manager'); ?></span>
                    <span id="rrm-variance-amount" class="rrm-value rrm-amount">$0.00</span>
                </div>
            </div>
            
            <div class="rrm-reconciliation-notes">
                <label for="rrm-reconciliation-notes"><?php _e('Notes/Explanation:', 'restaurant-delivery-manager'); ?></label>
                <textarea id="rrm-reconciliation-notes" 
                          class="rrm-notes-input" 
                          placeholder="<?php esc_attr_e('Explain any variance or additional notes...', 'restaurant-delivery-manager'); ?>"
                          rows="3"></textarea>
            </div>
        </div>
        
        <div class="rrm-modal-footer">
            <button type="button" id="rrm-cancel-reconciliation" class="rrm-btn rrm-btn-secondary">
                <?php _e('Cancel', 'restaurant-delivery-manager'); ?>
            </button>
            <button type="button" id="rrm-submit-reconciliation" class="rrm-btn rrm-btn-primary">
                <?php _e('Submit Reconciliation', 'restaurant-delivery-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="rrm-toast-container" class="rrm-toast-container"></div>

<!-- Floating Action Button for Reconciliation -->
<div class="rrm-fab-container">
    <button id="rrm-reconciliation-fab" class="rrm-fab" title="<?php esc_attr_e('Daily Reconciliation', 'restaurant-delivery-manager'); ?>">
        <i class="fas fa-calculator"></i>
    </button>
</div>

<!-- Emergency Modal -->
<div id="rrm-emergency-modal" class="rrm-modal">
    <div class="rrm-modal-content">
        <span class="rrm-close">&times;</span>
        <h3>Emergency Contact</h3>
        <p>Please select the type of emergency:</p>
        <div class="rrm-emergency-options">
            <button class="rrm-emergency-option" data-type="accident">
                <i class="fas fa-car-crash"></i>
                Accident
            </button>
            <button class="rrm-emergency-option" data-type="medical">
                <i class="fas fa-ambulance"></i>
                Medical
            </button>
            <button class="rrm-emergency-option" data-type="safety">
                <i class="fas fa-shield-alt"></i>
                Safety Concern
            </button>
            <button class="rrm-emergency-option" data-type="other">
                <i class="fas fa-question-circle"></i>
                Other
            </button>
        </div>
    </div>
</div>

<style>
/* GPS Section Styles */
.rrm-gps-section {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.rrm-gps-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.rrm-gps-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.rrm-gps-status-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

#rdm-gps-status {
    font-size: 14px;
    font-weight: 500;
}

.rrm-last-update {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

.rrm-gps-controls {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.rrm-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    margin-right: 10px;
}

.rrm-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.rrm-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.rrm-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .rrm-slider {
    background-color: #2196F3;
}

input:checked + .rrm-slider:before {
    transform: translateX(26px);
}

.rrm-gps-label {
    font-size: 16px;
    color: #333;
}

.rrm-gps-info {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}

.rrm-gps-note {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.rrm-gps-note i {
    color: #2196F3;
    margin-right: 5px;
}

/* COD Modal Styles */
.rrm-cod-modal-content {
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
}

.rrm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 20px 0;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.rrm-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.rrm-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
    line-height: 1;
}

.rrm-close:hover {
    color: #333;
}

.rrm-cod-content {
    padding: 0 20px;
}

.rrm-order-details {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.rrm-order-info,
.rrm-order-total-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.rrm-order-total-info {
    margin-bottom: 0;
    padding-top: 8px;
    border-top: 1px solid #dee2e6;
}

.rrm-label {
    font-weight: 500;
    color: #666;
}

.rrm-value {
    font-weight: 600;
    color: #333;
}

.rrm-amount {
    font-size: 16px;
    color: #28a745;
}

.rrm-change {
    color: #ffc107;
}

.rrm-input-group {
    margin-bottom: 20px;
}

.rrm-input-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.rrm-amount-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    overflow: hidden;
}

.rrm-currency-symbol {
    padding: 12px 15px;
    background: #f8f9fa;
    border-right: 1px solid #ddd;
    font-weight: 600;
    color: #666;
}

.rrm-amount-input {
    flex: 1;
    padding: 12px 15px;
    border: none;
    outline: none;
    font-size: 16px;
    font-weight: 500;
}

.rrm-amount-input:focus {
    border-color: #2271b1;
}

.rrm-change-display {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.rrm-change-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rrm-payment-calculator {
    margin-bottom: 20px;
}

.rrm-calc-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.rrm-calc-btn {
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.rrm-calc-btn:hover,
.rrm-calc-btn:focus {
    border-color: #2271b1;
    background: #f0f6ff;
}

.rrm-calc-btn:active {
    transform: scale(0.95);
}

.rrm-calc-clear {
    background: #f8f9fa;
    color: #666;
}

.rrm-quick-amounts {
    margin-bottom: 20px;
}

.rrm-quick-amounts h4 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rrm-quick-buttons {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.rrm-quick-amount,
.rrm-quick-exact {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.rrm-quick-amount:hover,
.rrm-quick-exact:hover {
    border-color: #2271b1;
    background: #f0f6ff;
}

.rrm-notes-section {
    margin-bottom: 20px;
}

.rrm-notes-section label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.rrm-notes-input {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
}

.rrm-notes-input:focus {
    border-color: #2271b1;
    outline: none;
}

.rrm-modal-footer {
    display: flex;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #eee;
}

.rrm-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.rrm-btn-primary {
    background: #2271b1;
    color: white;
}

.rrm-btn-primary:hover:not(:disabled) {
    background: #135e96;
}

.rrm-btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.rrm-btn-secondary {
    background: #f0f0f1;
    color: #333;
}

.rrm-btn-secondary:hover {
    background: #e0e0e1;
}

/* ========================================
   Cash Reconciliation Styles
   ======================================== */

.rrm-reconciliation-modal-content {
    max-width: 450px;
}

.rrm-reconciliation-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.rrm-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.rrm-summary-row:last-child {
    margin-bottom: 0;
}

.rrm-summary-total {
    padding-top: 12px;
    border-top: 2px solid #dee2e6;
    font-weight: 600;
}

.rrm-cash-count {
    margin-bottom: 20px;
}

.rrm-cash-count label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.rrm-variance-display {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.rrm-variance-display.positive {
    background: #d4edda;
    border-color: #c3e6cb;
}

.rrm-variance-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rrm-reconciliation-notes {
    margin-bottom: 20px;
}

.rrm-reconciliation-notes label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

/* ========================================
   Floating Action Button
   ======================================== */

.rrm-fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1000;
}

.rrm-fab {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #2271b1;
    color: white;
    border: none;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.3s;
}

.rrm-fab:hover {
    background: #135e96;
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.rrm-fab:active {
    transform: scale(0.95);
}

/* ========================================
   Toast Notifications
   ======================================== */

.rrm-toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 2000;
    max-width: 350px;
}

.rrm-toast {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-left: 4px solid #007cba;
    font-size: 14px;
    line-height: 1.4;
    animation: slideInRight 0.3s ease-out;
}

.rrm-toast.rrm-success {
    border-left-color: #28a745;
}

.rrm-toast.rrm-error {
    border-left-color: #dc3545;
}

.rrm-toast.rrm-warning {
    border-left-color: #ffc107;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* ========================================
   Modal Base Styles
   ======================================== */

.rrm-modal {
    display: none;
    position: fixed;
    z-index: 1500;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow: auto;
}

.rrm-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.rrm-modal-content {
    background-color: white;
    border-radius: 12px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: scale(0.8);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* ========================================
   Responsive Design
   ======================================== */

@media (max-width: 480px) {
    .rrm-modal-content {
        margin: 10px;
        max-width: none;
    }
    
    .rrm-calc-grid {
        gap: 8px;
    }
    
    .rrm-calc-btn {
        padding: 12px;
        font-size: 14px;
    }
    
    .rrm-quick-buttons {
        gap: 6px;
    }
    
    .rrm-quick-amount,
    .rrm-quick-exact {
        padding: 8px;
        font-size: 12px;
    }
    
    .rrm-fab-container {
        bottom: 60px;
        right: 15px;
    }
}
</style>

<script>
// Localize script data
var rdm_ajax = {
    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('rdm_mobile_nonce')); ?>'
};
</script>