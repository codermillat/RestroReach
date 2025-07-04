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

/* Existing styles remain unchanged */
</style>

<script>
// Localize script data
var rdm_ajax = {
    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('rdm_mobile_nonce')); ?>'
};
</script> 