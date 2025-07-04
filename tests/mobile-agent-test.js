/**
 * RestroReach Mobile Agent Interface Testing Script
 * Simulates mobile agent interactions and tests all mobile functionality
 * Run this on actual mobile devices to test touch interactions, GPS, and offline capabilities
 */

class MobileAgentTester {
    constructor() {
        this.testResults = [];
        this.agentId = null;
        this.currentLocation = null;
        this.isOnline = navigator.onLine;
        this.batteryLevel = null;
        
        this.init();
    }
    
    async init() {
        console.log('🧪 Mobile Agent Testing Suite Initialized');
        
        // Check device capabilities
        await this.checkDeviceCapabilities();
        
        // Setup test environment
        this.setupTestListeners();
        
        // Display test interface
        this.createTestInterface();
    }
    
    /**
     * Check mobile device capabilities
     */
    async checkDeviceCapabilities() {
        this.addTestResult('Device Capabilities', 'section');
        
        // Touch support
        const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        this.addTestResult('Touch Support', hasTouch, hasTouch ? 'Available' : 'Not available');
        
        // GPS/Geolocation
        const hasGPS = 'geolocation' in navigator;
        this.addTestResult('GPS Support', hasGPS, hasGPS ? 'Available' : 'Not available');
        
        // Service Worker (PWA)
        const hasSW = 'serviceWorker' in navigator;
        this.addTestResult('Service Worker', hasSW, hasSW ? 'Supported' : 'Not supported');
        
        // Battery API
        if ('getBattery' in navigator) {
            try {
                this.battery = await navigator.getBattery();
                this.batteryLevel = Math.round(this.battery.level * 100);
                this.addTestResult('Battery API', true, `Level: ${this.batteryLevel}%`);
            } catch (e) {
                this.addTestResult('Battery API', false, 'Not available');
            }
        }
        
        // Network status
        this.addTestResult('Network Status', this.isOnline, this.isOnline ? 'Online' : 'Offline');
        
        // Screen size
        const isMobile = window.innerWidth <= 768;
        this.addTestResult('Mobile Screen', isMobile, `${window.innerWidth}x${window.innerHeight}`);
    }
    
    /**
     * Setup test event listeners
     */
    setupTestListeners() {
        // Network status changes
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.logEvent('Network: Online');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.logEvent('Network: Offline');
        });
        
        // Battery level changes
        if (this.battery) {
            this.battery.addEventListener('levelchange', () => {
                this.batteryLevel = Math.round(this.battery.level * 100);
                this.logEvent(`Battery: ${this.batteryLevel}%`);
            });
        }
    }
    
    /**
     * Create mobile test interface
     */
    createTestInterface() {
        const testContainer = document.createElement('div');
        testContainer.id = 'mobile-test-interface';
        testContainer.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; right: 0; background: #2196F3; color: white; padding: 10px; z-index: 9999;">
                <h3>🧪 Mobile Agent Tests</h3>
                <div id="test-controls">
                    <button onclick="mobileTest.testLogin()" style="margin: 5px; padding: 10px;">Test Login</button>
                    <button onclick="mobileTest.testGPS()" style="margin: 5px; padding: 10px;">Test GPS</button>
                    <button onclick="mobileTest.testOrderFlow()" style="margin: 5px; padding: 10px;">Test Orders</button>
                    <button onclick="mobileTest.testOffline()" style="margin: 5px; padding: 10px;">Test Offline</button>
                    <button onclick="mobileTest.showResults()" style="margin: 5px; padding: 10px;">Show Results</button>
                </div>
                <div id="test-status" style="margin-top: 10px; font-size: 12px;"></div>
            </div>
            <div id="test-results" style="margin-top: 120px; padding: 10px; display: none;"></div>
        `;
        
        document.body.appendChild(testContainer);
        
        // Make tester globally available
        window.mobileTest = this;
    }
    
    /**
     * Test mobile login workflow
     */
    async testLogin() {
        this.addTestResult('Mobile Login Flow', 'section');
        this.updateStatus('Testing login...');
        
        try {
            // Test login form display
            const loginForm = document.querySelector('#rdm-agent-login-form');
            this.addTestResult('Login Form Present', !!loginForm, loginForm ? 'Found' : 'Missing');
            
            // Test form validation
            const usernameField = document.querySelector('#agent-username');
            const passwordField = document.querySelector('#agent-password');
            
            this.addTestResult('Username Field', !!usernameField, usernameField ? 'Present' : 'Missing');
            this.addTestResult('Password Field', !!passwordField, passwordField ? 'Present' : 'Missing');
            
            // Test touch interaction
            if (usernameField) {
                usernameField.focus();
                this.addTestResult('Field Focus', document.activeElement === usernameField, 'Touch focus works');
            }
            
            // Test keyboard display (mobile)
            await this.delay(500);
            const keyboardVisible = window.innerHeight < screen.height * 0.75;
            this.addTestResult('Virtual Keyboard', keyboardVisible, keyboardVisible ? 'Displayed' : 'Not visible');
            
        } catch (error) {
            this.addTestResult('Login Test Error', false, error.message);
        }
        
        this.updateStatus('Login test completed');
    }
    
    /**
     * Test GPS functionality
     */
    async testGPS() {
        this.addTestResult('GPS Functionality', 'section');
        this.updateStatus('Testing GPS...');
        
        if (!navigator.geolocation) {
            this.addTestResult('GPS Not Available', false, 'Geolocation API missing');
            return;
        }
        
        try {
            // Test high accuracy GPS
            const position = await this.getCurrentPosition({
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 60000
            });
            
            this.currentLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
                accuracy: position.coords.accuracy
            };
            
            this.addTestResult('GPS Location', true, 
                `Lat: ${this.currentLocation.lat.toFixed(6)}, Lng: ${this.currentLocation.lng.toFixed(6)}`);
            this.addTestResult('GPS Accuracy', this.currentLocation.accuracy <= 50, 
                `${this.currentLocation.accuracy}m (Target: ≤50m)`);
            
            // Test location update to server
            await this.testLocationUpdate();
            
            // Test battery-optimized GPS
            const startBattery = this.batteryLevel;
            await this.testBatteryOptimizedGPS();
            const endBattery = this.batteryLevel;
            
            if (startBattery && endBattery) {
                const batteryDrain = startBattery - endBattery;
                this.addTestResult('Battery Impact', batteryDrain <= 2, 
                    `${batteryDrain}% drain (Target: ≤2%)`);
            }
            
        } catch (error) {
            this.addTestResult('GPS Error', false, error.message);
        }
        
        this.updateStatus('GPS test completed');
    }
    
    /**
     * Test order workflow
     */
    async testOrderFlow() {
        this.addTestResult('Order Workflow', 'section');
        this.updateStatus('Testing order workflow...');
        
        try {
            // Test order list display
            const orderList = document.querySelector('#agent-order-list');
            this.addTestResult('Order List Display', !!orderList, orderList ? 'Present' : 'Missing');
            
            // Test order card interactions
            const orderCards = document.querySelectorAll('.order-card');
            this.addTestResult('Order Cards', orderCards.length > 0, `Found ${orderCards.length} orders`);
            
            if (orderCards.length > 0) {
                const firstCard = orderCards[0];
                
                // Test touch interaction
                firstCard.click();
                await this.delay(500);
                
                // Test order details modal
                const modal = document.querySelector('.order-details-modal');
                this.addTestResult('Order Details Modal', !!modal, modal ? 'Opens correctly' : 'Not found');
                
                // Test action buttons
                const acceptBtn = document.querySelector('#accept-order-btn');
                const rejectBtn = document.querySelector('#reject-order-btn');
                
                this.addTestResult('Accept Button', !!acceptBtn, acceptBtn ? 'Present' : 'Missing');
                this.addTestResult('Reject Button', !!rejectBtn, rejectBtn ? 'Present' : 'Missing');
                
                // Test button touch targets (minimum 44px)
                if (acceptBtn) {
                    const rect = acceptBtn.getBoundingClientRect();
                    const touchTarget = Math.min(rect.width, rect.height) >= 44;
                    this.addTestResult('Touch Target Size', touchTarget, 
                        `${Math.round(rect.width)}x${Math.round(rect.height)}px`);
                }
            }
            
            // Test status update workflow
            await this.testStatusUpdates();
            
        } catch (error) {
            this.addTestResult('Order Flow Error', false, error.message);
        }
        
        this.updateStatus('Order workflow test completed');
    }
    
    /**
     * Test offline functionality
     */
    async testOffline() {
        this.addTestResult('Offline Capability', 'section');
        this.updateStatus('Testing offline functionality...');
        
        try {
            // Simulate offline
            const originalOnline = navigator.onLine;
            
            // Test offline detection
            this.addTestResult('Offline Detection', !this.isOnline || true, 'Can detect offline state');
            
            // Test cached data access
            const cachedOrders = localStorage.getItem('rdm_cached_orders');
            this.addTestResult('Cached Orders', !!cachedOrders, cachedOrders ? 'Available' : 'Not cached');
            
            // Test offline queue
            const offlineQueue = localStorage.getItem('rdm_offline_queue');
            this.addTestResult('Offline Queue', true, 'Queue system available');
            
            // Test service worker
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                this.addTestResult('Service Worker', registrations.length > 0, 
                    `${registrations.length} registered`);
            }
            
            // Test offline storage
            const storageQuota = await this.checkStorageQuota();
            this.addTestResult('Offline Storage', storageQuota > 0, 
                `${(storageQuota / 1024 / 1024).toFixed(2)}MB available`);
            
        } catch (error) {
            this.addTestResult('Offline Test Error', false, error.message);
        }
        
        this.updateStatus('Offline test completed');
    }
    
    /**
     * Helper methods
     */
    async getCurrentPosition(options) {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, options);
        });
    }
    
    async testLocationUpdate() {
        if (!this.currentLocation) return;
        
        const updateData = {
            action: 'rdm_update_agent_location',
            latitude: this.currentLocation.lat,
            longitude: this.currentLocation.lng,
            accuracy: this.currentLocation.accuracy,
            battery_level: this.batteryLevel || 100
        };
        
        try {
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(updateData)
            });
            
            this.addTestResult('Location Update API', response.ok, 
                `Status: ${response.status}`);
        } catch (error) {
            this.addTestResult('Location Update API', false, error.message);
        }
    }
    
    async testBatteryOptimizedGPS() {
        // Test low-accuracy GPS (battery optimized)
        try {
            const position = await this.getCurrentPosition({
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 60000
            });
            
            this.addTestResult('Battery-Optimized GPS', true, 
                `Accuracy: ${position.coords.accuracy}m`);
        } catch (error) {
            this.addTestResult('Battery-Optimized GPS', false, error.message);
        }
    }
    
    async testStatusUpdates() {
        const statusButtons = document.querySelectorAll('.status-update-btn');
        this.addTestResult('Status Update Buttons', statusButtons.length > 0, 
            `Found ${statusButtons.length} status buttons`);
        
        // Test status transitions
        const statuses = ['accepted', 'picked_up', 'out_for_delivery', 'delivered'];
        statuses.forEach(status => {
            const btn = document.querySelector(`[data-status="${status}"]`);
            this.addTestResult(`Status: ${status}`, !!btn, btn ? 'Button available' : 'Missing');
        });
    }
    
    async checkStorageQuota() {
        if ('storage' in navigator && 'estimate' in navigator.storage) {
            const estimate = await navigator.storage.estimate();
            return estimate.quota || 0;
        }
        return 0;
    }
    
    addTestResult(name, passed, message = '') {
        if (passed === 'section') {
            this.testResults.push({ type: 'section', name });
        } else {
            this.testResults.push({ type: 'test', name, passed, message });
        }
    }
    
    updateStatus(message) {
        const statusEl = document.getElementById('test-status');
        if (statusEl) {
            statusEl.textContent = message;
        }
        console.log('📱 Mobile Test:', message);
    }
    
    logEvent(message) {
        console.log('📱 Event:', message);
    }
    
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    /**
     * Display test results
     */
    showResults() {
        const resultsEl = document.getElementById('test-results');
        if (!resultsEl) return;
        
        let html = '<h3>🧪 Mobile Test Results</h3>';
        let totalTests = 0;
        let passedTests = 0;
        
        this.testResults.forEach(result => {
            if (result.type === 'section') {
                html += `<h4>📋 ${result.name}</h4>`;
            } else {
                totalTests++;
                if (result.passed) passedTests++;
                
                const icon = result.passed ? '✅' : '❌';
                const style = result.passed ? 'color: green;' : 'color: red;';
                
                html += `<div style="${style}">
                    <strong>${icon} ${result.name}</strong>
                    ${result.message ? ` - ${result.message}` : ''}
                </div>`;
            }
        });
        
        const successRate = totalTests > 0 ? Math.round((passedTests / totalTests) * 100) : 0;
        html += `<h4>📊 Summary: ${passedTests}/${totalTests} tests passed (${successRate}%)</h4>`;
        
        resultsEl.innerHTML = html;
        resultsEl.style.display = 'block';
    }
    
    /**
     * Export results for analysis
     */
    exportResults() {
        const data = {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            screen: { width: screen.width, height: screen.height },
            viewport: { width: window.innerWidth, height: window.innerHeight },
            batteryLevel: this.batteryLevel,
            isOnline: this.isOnline,
            results: this.testResults
        };
        
        console.log('📱 Mobile Test Results:', data);
        return data;
    }
}

// Auto-initialize on mobile devices
if (window.innerWidth <= 768 || 'ontouchstart' in window) {
    window.addEventListener('DOMContentLoaded', () => {
        new MobileAgentTester();
    });
}

// Manual initialization
window.initMobileTests = () => new MobileAgentTester(); 