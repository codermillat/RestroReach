#!/usr/bin/env node

/**
 * RestroReach Performance Testing Suite
 * Simulates multiple concurrent delivery agents to test system performance
 */

const https = require('https');
const http = require('http');
const { performance } = require('perf_hooks');

// Configuration
const CONFIG = {
    // Test target (change to your actual WordPress site)
    host: 'localhost',
    port: 80,
    protocol: 'http:', // Change to https: for SSL sites
    basePath: '/wp-admin/admin-ajax.php',
    
    // Test parameters
    concurrent_agents: 10,
    test_duration_minutes: 5,
    location_update_interval: 45000, // 45 seconds (matches production)
    
    // WordPress credentials (for testing authenticated requests)
    test_username: 'delivery_agent_1', // Create test agents first
    test_password: 'test_password',
    
    // Test scenarios
    scenarios: {
        location_updates: true,
        order_fetching: true,
        status_updates: true,
        payment_collection: false // Set to true to test COD workflows
    }
};

// Test results storage
const results = {
    total_requests: 0,
    successful_requests: 0,
    failed_requests: 0,
    response_times: [],
    errors: [],
    start_time: null,
    end_time: null
};

// Agent simulation class
class DeliveryAgentSimulator {
    constructor(agentId) {
        this.agentId = agentId;
        this.isRunning = false;
        this.currentLocation = {
            lat: 40.7128 + (Math.random() - 0.5) * 0.1, // NYC area with random offset
            lng: -74.0060 + (Math.random() - 0.5) * 0.1
        };
        this.sessionCookie = null;
        this.wpNonce = null;
    }

    /**
     * Authenticate agent and get session cookie
     */
    async authenticate() {
        return new Promise((resolve, reject) => {
            const loginData = new URLSearchParams({
                log: CONFIG.test_username + '_' + this.agentId,
                pwd: CONFIG.test_password,
                wp_submit: 'Log In',
                redirect_to: '',
                testcookie: '1'
            });
            const loginDataString = loginData.toString();

            const options = {
                hostname: CONFIG.host,
                port: CONFIG.port,
                path: '/wp-login.php',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Content-Length': Buffer.byteLength(loginDataString)
                }
            };

            const req = this.makeRequest(options);
            
            req.on('response', (res) => {
                // Extract session cookie
                const cookies = res.headers['set-cookie'];
                if (cookies) {
                    this.sessionCookie = cookies.join('; ');
                }
                resolve(true);
            });

            req.on('error', reject);
            req.write(loginDataString);
            req.end();
        });
    }

    /**
     * Simulate location update
     */
    async sendLocationUpdate() {
        // Simulate movement (small random change)
        this.currentLocation.lat += (Math.random() - 0.5) * 0.001;
        this.currentLocation.lng += (Math.random() - 0.5) * 0.001;

        const formData = new URLSearchParams({
            action: 'rdm_update_agent_location',
            nonce: this.wpNonce || 'test_nonce',
            latitude: this.currentLocation.lat,
            longitude: this.currentLocation.lng,
            accuracy: Math.random() * 10 + 5, // 5-15 meters
            battery_level: Math.floor(Math.random() * 100)
        });

        return this.makeAjaxRequest(formData, 'Location Update');
    }

    /**
     * Simulate fetching orders
     */
    async fetchOrders() {
        const formData = new URLSearchParams({
            action: 'rdm_get_agent_orders',
            nonce: this.wpNonce || 'test_nonce'
        });

        return this.makeAjaxRequest(formData, 'Fetch Orders');
    }

    /**
     * Simulate order status update
     */
    async updateOrderStatus() {
        const formData = new URLSearchParams({
            action: 'rdm_update_order_status',
            nonce: this.wpNonce || 'test_nonce',
            order_id: Math.floor(Math.random() * 1000) + 1,
            status: ['accepted', 'picked_up', 'delivered'][Math.floor(Math.random() * 3)],
            notes: 'Test status update from agent ' + this.agentId
        });

        return this.makeAjaxRequest(formData, 'Status Update');
    }

    /**
     * Make AJAX request to WordPress
     */
    async makeAjaxRequest(formData, requestType) {
        return new Promise((resolve, reject) => {
            const startTime = performance.now();
            const formDataString = formData.toString();

            const options = {
                hostname: CONFIG.host,
                port: CONFIG.port,
                path: CONFIG.basePath,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Content-Length': Buffer.byteLength(formDataString),
                    'Cookie': this.sessionCookie || '',
                    'User-Agent': 'RestroReach-Performance-Test/1.0'
                }
            };

            const req = this.makeRequest(options);

            req.on('response', (res) => {
                const endTime = performance.now();
                const responseTime = endTime - startTime;

                let body = '';
                res.on('data', chunk => body += chunk);
                res.on('end', () => {
                    results.total_requests++;
                    results.response_times.push(responseTime);

                    if (res.statusCode === 200) {
                        results.successful_requests++;
                        console.log(`Agent ${this.agentId}: ${requestType} - ${responseTime.toFixed(2)}ms`);
                    } else {
                        results.failed_requests++;
                        results.errors.push({
                            agent: this.agentId,
                            type: requestType,
                            status: res.statusCode,
                            response: body.substring(0, 200)
                        });
                        console.error(`Agent ${this.agentId}: ${requestType} FAILED - Status ${res.statusCode}`);
                    }

                    resolve(responseTime);
                });
            });

            req.on('error', (error) => {
                const endTime = performance.now();
                results.total_requests++;
                results.failed_requests++;
                results.errors.push({
                    agent: this.agentId,
                    type: requestType,
                    error: error.message
                });
                reject(error);
            });

            req.write(formDataString);
            req.end();
        });
    }

    /**
     * Create HTTP/HTTPS request based on config
     */
    makeRequest(options) {
        const requestModule = CONFIG.protocol === 'https:' ? https : http;
        return requestModule.request(options);
    }

    /**
     * Start agent simulation
     */
    start() {
        this.isRunning = true;
        console.log(`🚀 Agent ${this.agentId} started simulation`);

        // Authenticate first (in real scenario)
        // For testing, we'll skip authentication and use direct AJAX calls

        // Location updates
        if (CONFIG.scenarios.location_updates) {
            setInterval(() => {
                if (this.isRunning) {
                    this.sendLocationUpdate().catch(console.error);
                }
            }, CONFIG.location_update_interval);
        }

        // Order fetching (every 30 seconds)
        if (CONFIG.scenarios.order_fetching) {
            setInterval(() => {
                if (this.isRunning) {
                    this.fetchOrders().catch(console.error);
                }
            }, 30000);
        }

        // Random status updates (every 2-5 minutes)
        if (CONFIG.scenarios.status_updates) {
            setInterval(() => {
                if (this.isRunning && Math.random() > 0.7) {
                    this.updateOrderStatus().catch(console.error);
                }
            }, 60000 + Math.random() * 180000);
        }
    }

    /**
     * Stop agent simulation
     */
    stop() {
        this.isRunning = false;
        console.log(`⏹️ Agent ${this.agentId} stopped simulation`);
    }
}

/**
 * Calculate performance statistics
 */
function calculateStats() {
    if (results.response_times.length === 0) {
        return null;
    }

    const sorted = results.response_times.sort((a, b) => a - b);
    const avg = sorted.reduce((a, b) => a + b, 0) / sorted.length;
    const median = sorted[Math.floor(sorted.length / 2)];
    const p95 = sorted[Math.floor(sorted.length * 0.95)];
    const p99 = sorted[Math.floor(sorted.length * 0.99)];
    const min = sorted[0];
    const max = sorted[sorted.length - 1];

    return {
        total_requests: results.total_requests,
        successful_requests: results.successful_requests,
        failed_requests: results.failed_requests,
        success_rate: (results.successful_requests / results.total_requests * 100).toFixed(2),
        response_times: {
            min: min.toFixed(2),
            max: max.toFixed(2),
            avg: avg.toFixed(2),
            median: median.toFixed(2),
            p95: p95.toFixed(2),
            p99: p99.toFixed(2)
        },
        duration_minutes: ((results.end_time - results.start_time) / 1000 / 60).toFixed(2),
        requests_per_minute: (results.total_requests / ((results.end_time - results.start_time) / 1000 / 60)).toFixed(2)
    };
}

/**
 * Main test execution
 */
async function runPerformanceTest() {
    console.log('🧪 RestroReach Performance Testing Suite');
    console.log('=========================================');
    console.log(`Target: ${CONFIG.protocol}//${CONFIG.host}:${CONFIG.port}`);
    console.log(`Concurrent Agents: ${CONFIG.concurrent_agents}`);
    console.log(`Test Duration: ${CONFIG.test_duration_minutes} minutes`);
    console.log('');

    // Initialize agents
    const agents = [];
    for (let i = 1; i <= CONFIG.concurrent_agents; i++) {
        agents.push(new DeliveryAgentSimulator(i));
    }

    // Start test
    results.start_time = performance.now();
    console.log('⏱️ Starting performance test...');

    // Start all agents
    agents.forEach(agent => agent.start());

    // Wait for test duration
    await new Promise(resolve => {
        setTimeout(resolve, CONFIG.test_duration_minutes * 60 * 1000);
    });

    // Stop all agents
    agents.forEach(agent => agent.stop());
    results.end_time = performance.now();

    console.log('\n🏁 Performance test completed!');
    console.log('================================');

    // Calculate and display results
    const stats = calculateStats();
    if (stats) {
        console.log(`📊 PERFORMANCE RESULTS:`);
        console.log(`   Total Requests: ${stats.total_requests}`);
        console.log(`   Successful: ${stats.successful_requests} (${stats.success_rate}%)`);
        console.log(`   Failed: ${stats.failed_requests}`);
        console.log(`   Duration: ${stats.duration_minutes} minutes`);
        console.log(`   Requests/min: ${stats.requests_per_minute}`);
        console.log('');
        console.log(`⚡ RESPONSE TIMES (ms):`);
        console.log(`   Min: ${stats.response_times.min}ms`);
        console.log(`   Max: ${stats.response_times.max}ms`);
        console.log(`   Average: ${stats.response_times.avg}ms`);
        console.log(`   Median: ${stats.response_times.median}ms`);
        console.log(`   95th percentile: ${stats.response_times.p95}ms`);
        console.log(`   99th percentile: ${stats.response_times.p99}ms`);

        // Performance assessment
        console.log('\n🎯 PERFORMANCE ASSESSMENT:');
        const avgTime = parseFloat(stats.response_times.avg);
        const successRate = parseFloat(stats.success_rate);

        if (successRate >= 99 && avgTime <= 500) {
            console.log('   ✅ EXCELLENT - System performs exceptionally well under load');
        } else if (successRate >= 95 && avgTime <= 1000) {
            console.log('   ✅ GOOD - System performs well under load');
        } else if (successRate >= 90 && avgTime <= 2000) {
            console.log('   ⚠️ ACCEPTABLE - System shows some stress under load');
        } else {
            console.log('   ❌ NEEDS OPTIMIZATION - System struggles under load');
        }

        // Show errors if any
        if (results.errors.length > 0) {
            console.log('\n❌ ERRORS ENCOUNTERED:');
            results.errors.slice(0, 5).forEach(error => {
                console.log(`   Agent ${error.agent}: ${error.type} - ${error.error || error.status}`);
            });
            if (results.errors.length > 5) {
                console.log(`   ... and ${results.errors.length - 5} more errors`);
            }
        }
    } else {
        console.log('❌ No data collected during test');
    }

    process.exit(0);
}

// Run the test
if (require.main === module) {
    runPerformanceTest().catch(console.error);
} 