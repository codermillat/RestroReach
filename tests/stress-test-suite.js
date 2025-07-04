/**
 * RestroReach Stress Testing Suite
 * 
 * Tests system behavior under high load conditions:
 * - High order volumes (1000+ orders)
 * - Concurrent agent operations
 * - Google Maps API rate limiting
 * - Database performance under load
 * - Memory usage monitoring
 * 
 * @package RestaurantDeliveryManager
 * @subpackage Tests
 */

class RestroReachStressTester {
    constructor(config = {}) {
        this.config = {
            maxOrders: config.maxOrders || 1000,
            concurrentAgents: config.concurrentAgents || 50,
            testDuration: config.testDuration || 300000, // 5 minutes
            apiCallsPerMinute: config.apiCallsPerMinute || 100,
            ...config
        };
        
        this.results = {
            orderCreation: [],
            databaseQueries: [],
            apiCalls: [],
            memoryUsage: [],
            errors: []
        };
        
        this.startTime = null;
        this.activeTests = new Set();
    }
    
    /**
     * Run complete stress test suite
     */
    async runStressTests() {
        console.log('🔥 RestroReach Stress Testing Suite');
        console.log('===================================');
        console.log(`Max Orders: ${this.config.maxOrders}`);
        console.log(`Concurrent Agents: ${this.config.concurrentAgents}`);
        console.log(`Test Duration: ${this.config.testDuration / 1000}s`);
        console.log('');
        
        this.startTime = Date.now();
        
        // Start monitoring
        this.startMemoryMonitoring();
        
        try {
            // Test 1: High Volume Order Creation
            await this.testHighVolumeOrderCreation();
            
            // Test 2: Concurrent Agent Operations
            await this.testConcurrentAgentOperations();
            
            // Test 3: Database Performance Under Load
            await this.testDatabasePerformance();
            
            // Test 4: Google Maps API Stress Testing
            await this.testGoogleMapsStress();
            
            // Test 5: Memory Leak Detection
            await this.testMemoryLeaks();
            
            // Test 6: Recovery After Stress
            await this.testRecoveryBehavior();
            
        } catch (error) {
            this.logError('Stress test failed', error);
        } finally {
            this.stopAllTests();
            this.generateReport();
        }
    }
    
    /**
     * Test 1: High Volume Order Creation
     */
    async testHighVolumeOrderCreation() {
        console.log('\n📦 Testing High Volume Order Creation');
        console.log('─────────────────────────────────────');
        
        const batchSize = 50;
        const totalBatches = Math.ceil(this.config.maxOrders / batchSize);
        
        const startTime = performance.now();
        let createdOrders = 0;
        let errors = 0;
        
        for (let batch = 0; batch < totalBatches; batch++) {
            const batchStart = performance.now();
            const promises = [];
            
            for (let i = 0; i < batchSize; i++) {
                promises.push(this.createTestOrder(batch * batchSize + i));
            }
            
            try {
                const results = await Promise.allSettled(promises);
                
                results.forEach(result => {
                    if (result.status === 'fulfilled') {
                        createdOrders++;
                    } else {
                        errors++;
                        this.logError('Order creation failed', result.reason);
                    }
                });
                
                const batchTime = performance.now() - batchStart;
                const ordersPerSecond = batchSize / (batchTime / 1000);
                
                this.results.orderCreation.push({
                    batch: batch + 1,
                    ordersCreated: batchSize - (results.filter(r => r.status === 'rejected').length),
                    timeMs: batchTime,
                    ordersPerSecond: ordersPerSecond
                });
                
                console.log(`Batch ${batch + 1}/${totalBatches}: ${batchSize} orders in ${batchTime.toFixed(2)}ms (${ordersPerSecond.toFixed(1)} orders/sec)`);
                
                // Brief pause between batches to prevent overwhelming
                await this.delay(100);
                
            } catch (error) {
                this.logError(`Batch ${batch + 1} failed`, error);
                errors += batchSize;
            }
        }
        
        const totalTime = performance.now() - startTime;
        const averageRate = createdOrders / (totalTime / 1000);
        
        console.log(`\n📊 Order Creation Summary:`);
        console.log(`   Total Orders Created: ${createdOrders}/${this.config.maxOrders}`);
        console.log(`   Total Time: ${(totalTime / 1000).toFixed(2)}s`);
        console.log(`   Average Rate: ${averageRate.toFixed(1)} orders/sec`);
        console.log(`   Error Rate: ${((errors / this.config.maxOrders) * 100).toFixed(2)}%`);
        
        return {
            success: errors < this.config.maxOrders * 0.05, // Allow 5% error rate
            ordersCreated,
            errors,
            averageRate
        };
    }
    
    /**
     * Test 2: Concurrent Agent Operations
     */
    async testConcurrentAgentOperations() {
        console.log('\n👥 Testing Concurrent Agent Operations');
        console.log('──────────────────────────────────────');
        
        const agents = [];
        const operations = ['location_update', 'order_fetch', 'status_update'];
        
        // Create concurrent agent simulators
        for (let i = 0; i < this.config.concurrentAgents; i++) {
            agents.push(new AgentSimulator(i + 1, this.config));
        }
        
        console.log(`Starting ${this.config.concurrentAgents} concurrent agents...`);
        
        const startTime = performance.now();
        const agentPromises = agents.map(agent => agent.startOperations());
        
        // Run for specified duration
        await this.delay(this.config.testDuration);
        
        // Stop all agents
        agents.forEach(agent => agent.stop());
        await Promise.allSettled(agentPromises);
        
        const totalTime = performance.now() - startTime;
        
        // Collect results
        const totalOperations = agents.reduce((sum, agent) => sum + agent.operationCount, 0);
        const totalErrors = agents.reduce((sum, agent) => sum + agent.errorCount, 0);
        const averageResponseTime = agents.reduce((sum, agent) => sum + agent.averageResponseTime, 0) / agents.length;
        
        console.log(`\n📊 Agent Operations Summary:`);
        console.log(`   Total Operations: ${totalOperations}`);
        console.log(`   Operations/sec: ${(totalOperations / (totalTime / 1000)).toFixed(1)}`);
        console.log(`   Error Rate: ${((totalErrors / totalOperations) * 100).toFixed(2)}%`);
        console.log(`   Avg Response Time: ${averageResponseTime.toFixed(2)}ms`);
        
        return {
            success: totalErrors < totalOperations * 0.1, // Allow 10% error rate under stress
            totalOperations,
            totalErrors,
            averageResponseTime
        };
    }
    
    /**
     * Test 3: Database Performance Under Load
     */
    async testDatabasePerformance() {
        console.log('\n🗄️ Testing Database Performance Under Load');
        console.log('──────────────────────────────────────────');
        
        const queries = [
            'order_list',
            'agent_list', 
            'location_history',
            'order_assignments',
            'analytics_data'
        ];
        
        const concurrentQueries = 20;
        const queryRounds = 50;
        
        for (const queryType of queries) {
            console.log(`Testing ${queryType} queries...`);
            
            const queryResults = [];
            
            for (let round = 0; round < queryRounds; round++) {
                const roundStart = performance.now();
                const promises = [];
                
                for (let i = 0; i < concurrentQueries; i++) {
                    promises.push(this.executeTestQuery(queryType));
                }
                
                const results = await Promise.allSettled(promises);
                const roundTime = performance.now() - roundStart;
                
                const successful = results.filter(r => r.status === 'fulfilled').length;
                const failed = results.filter(r => r.status === 'rejected').length;
                
                queryResults.push({
                    round: round + 1,
                    successful,
                    failed,
                    timeMs: roundTime,
                    queriesPerSecond: concurrentQueries / (roundTime / 1000)
                });
                
                if (failed > 0) {
                    console.log(`   Round ${round + 1}: ${failed} queries failed`);
                }
            }
            
            const avgTime = queryResults.reduce((sum, r) => sum + r.timeMs, 0) / queryResults.length;
            const avgQPS = queryResults.reduce((sum, r) => sum + r.queriesPerSecond, 0) / queryResults.length;
            const totalFailed = queryResults.reduce((sum, r) => sum + r.failed, 0);
            
            this.results.databaseQueries.push({
                queryType,
                averageTimeMs: avgTime,
                averageQPS: avgQPS,
                totalFailed,
                successRate: ((queryRounds * concurrentQueries - totalFailed) / (queryRounds * concurrentQueries)) * 100
            });
            
            console.log(`   ${queryType}: ${avgQPS.toFixed(1)} QPS, ${avgTime.toFixed(2)}ms avg, ${((totalFailed / (queryRounds * concurrentQueries)) * 100).toFixed(2)}% error rate`);
        }
        
        return {
            success: this.results.databaseQueries.every(q => q.successRate > 95),
            queryResults: this.results.databaseQueries
        };
    }
    
    /**
     * Test 4: Google Maps API Stress Testing
     */
    async testGoogleMapsStress() {
        console.log('\n🗺️ Testing Google Maps API Under Load');
        console.log('─────────────────────────────────────────');
        
        const apiKey = await this.getGoogleMapsApiKey();
        if (!apiKey) {
            console.log('❌ Google Maps API key not configured - skipping API stress test');
            return { success: false, reason: 'No API key' };
        }
        
        const testScenarios = [
            { name: 'Geocoding Burst', type: 'geocoding', count: 50 },
            { name: 'Distance Matrix Batch', type: 'distance', count: 30 },
            { name: 'Directions API', type: 'directions', count: 20 }
        ];
        
        for (const scenario of testScenarios) {
            console.log(`Testing ${scenario.name}...`);
            
            const startTime = performance.now();
            const promises = [];
            
            for (let i = 0; i < scenario.count; i++) {
                promises.push(this.callGoogleMapsAPI(scenario.type, apiKey, i));
            }
            
            const results = await Promise.allSettled(promises);
            const endTime = performance.now();
            
            const successful = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
            const failed = results.filter(r => r.status === 'rejected' || !r.value.success).length;
            const totalTime = endTime - startTime;
            
            this.results.apiCalls.push({
                scenario: scenario.name,
                successful,
                failed,
                totalTime,
                requestsPerSecond: scenario.count / (totalTime / 1000),
                successRate: (successful / scenario.count) * 100
            });
            
            console.log(`   ${scenario.name}: ${successful}/${scenario.count} successful, ${(totalTime / 1000).toFixed(2)}s total`);
            
            // Rate limiting delay
            await this.delay(1000);
        }
        
        return {
            success: this.results.apiCalls.every(call => call.successRate > 80),
            apiResults: this.results.apiCalls
        };
    }
    
    /**
     * Test 5: Memory Leak Detection
     */
    async testMemoryLeaks() {
        console.log('\n🧠 Testing Memory Leak Detection');
        console.log('─────────────────────────────');
        
        const initialMemory = this.getCurrentMemoryUsage();
        console.log(`Initial memory usage: ${initialMemory.toFixed(2)} MB`);
        
        // Perform memory-intensive operations
        for (let cycle = 0; cycle < 10; cycle++) {
            await this.performMemoryIntensiveOperations();
            
            // Force garbage collection if available
            if (global.gc) {
                global.gc();
            }
            
            const currentMemory = this.getCurrentMemoryUsage();
            this.results.memoryUsage.push({
                cycle: cycle + 1,
                memoryMB: currentMemory,
                memoryDelta: currentMemory - initialMemory
            });
            
            console.log(`Cycle ${cycle + 1}: ${currentMemory.toFixed(2)} MB (+${(currentMemory - initialMemory).toFixed(2)} MB)`);
            
            await this.delay(1000);
        }
        
        const finalMemory = this.getCurrentMemoryUsage();
        const memoryIncrease = finalMemory - initialMemory;
        const memoryLeakDetected = memoryIncrease > 50; // Alert if >50MB increase
        
        console.log(`\nMemory Analysis:`);
        console.log(`   Initial: ${initialMemory.toFixed(2)} MB`);
        console.log(`   Final: ${finalMemory.toFixed(2)} MB`);
        console.log(`   Increase: ${memoryIncrease.toFixed(2)} MB`);
        console.log(`   Leak Detected: ${memoryLeakDetected ? 'YES ⚠️' : 'NO ✅'}`);
        
        return {
            success: !memoryLeakDetected,
            memoryIncrease,
            memoryLeakDetected
        };
    }
    
    /**
     * Test 6: Recovery After Stress
     */
    async testRecoveryBehavior() {
        console.log('\n🔄 Testing Recovery After Stress');
        console.log('─────────────────────────────────');
        
        // Wait for system to settle
        await this.delay(5000);
        
        // Test normal operations after stress
        const recoveryTests = [
            this.testSingleOrderCreation(),
            this.testSingleAgentOperation(),
            this.testSimpleQuery(),
            this.testCustomerTracking()
        ];
        
        const results = await Promise.allSettled(recoveryTests);
        const successful = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
        
        console.log(`Recovery test: ${successful}/${results.length} operations successful`);
        
        return {
            success: successful === results.length,
            recoveryRate: (successful / results.length) * 100
        };
    }
    
    /**
     * Helper Methods
     */
    async createTestOrder(index) {
        const orderData = {
            billing_email: `stress-test-${index}@example.com`,
            billing_first_name: 'Stress',
            billing_last_name: `Test${index}`,
            status: 'processing',
            total: Math.random() * 100 + 10
        };
        
        return this.makeApiCall('create_order', orderData);
    }
    
    async executeTestQuery(queryType) {
        const queries = {
            order_list: 'SELECT * FROM wp_posts WHERE post_type="shop_order" LIMIT 50',
            agent_list: 'SELECT * FROM wp_rr_delivery_agents LIMIT 20',
            location_history: 'SELECT * FROM wp_rr_location_tracking WHERE timestamp > NOW() - INTERVAL 1 HOUR',
            order_assignments: 'SELECT * FROM wp_rr_order_assignments WHERE assigned_at > NOW() - INTERVAL 1 DAY',
            analytics_data: 'SELECT COUNT(*) as total_orders FROM wp_posts WHERE post_type="shop_order"'
        };
        
        return this.makeApiCall('execute_query', { query: queries[queryType] });
    }
    
    async callGoogleMapsAPI(type, apiKey, index) {
        const apis = {
            geocoding: `https://maps.googleapis.com/maps/api/geocode/json?address=Test+Address+${index}&key=${apiKey}`,
            distance: `https://maps.googleapis.com/maps/api/distancematrix/json?origins=Test+Origin+${index}&destinations=Test+Dest+${index}&key=${apiKey}`,
            directions: `https://maps.googleapis.com/maps/api/directions/json?origin=Test+Start+${index}&destination=Test+End+${index}&key=${apiKey}`
        };
        
        try {
            const response = await fetch(apis[type]);
            const data = await response.json();
            
            return {
                success: response.ok && data.status === 'OK',
                status: data.status
            };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    async makeApiCall(action, data) {
        try {
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: `rdm_stress_test_${action}`,
                    ...data
                })
            });
            
            return await response.json();
        } catch (error) {
            throw new Error(`API call failed: ${error.message}`);
        }
    }
    
    async getGoogleMapsApiKey() {
        try {
            const response = await this.makeApiCall('get_api_key', {});
            return response.api_key;
        } catch {
            return null;
        }
    }
    
    startMemoryMonitoring() {
        this.memoryMonitor = setInterval(() => {
            const usage = this.getCurrentMemoryUsage();
            this.results.memoryUsage.push({
                timestamp: Date.now(),
                memoryMB: usage
            });
        }, 5000);
    }
    
    getCurrentMemoryUsage() {
        if (typeof process !== 'undefined' && process.memoryUsage) {
            return process.memoryUsage().heapUsed / 1024 / 1024;
        } else if (performance.memory) {
            return performance.memory.usedJSHeapSize / 1024 / 1024;
        }
        return 0;
    }
    
    async performMemoryIntensiveOperations() {
        // Create large arrays and objects to test memory management
        const largeArray = new Array(100000).fill(0).map((_, i) => ({
            id: i,
            data: `test_data_${i}`,
            timestamp: Date.now(),
            random: Math.random()
        }));
        
        // Perform operations on the array
        largeArray.forEach(item => {
            item.processed = true;
            item.hash = btoa(item.data);
        });
        
        // Simulate async operations
        await Promise.all(
            largeArray.slice(0, 1000).map(async item => {
                await this.delay(1);
                return item.id * 2;
            })
        );
    }
    
    async testSingleOrderCreation() {
        try {
            await this.createTestOrder(999999);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    async testSingleAgentOperation() {
        try {
            await this.makeApiCall('agent_location_update', {
                agent_id: 1,
                latitude: 40.7128,
                longitude: -74.0060
            });
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    async testSimpleQuery() {
        try {
            await this.executeTestQuery('order_list');
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    async testCustomerTracking() {
        try {
            await this.makeApiCall('customer_track_order', {
                order_id: 1,
                tracking_key: 'test_key'
            });
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
    
    stopAllTests() {
        if (this.memoryMonitor) {
            clearInterval(this.memoryMonitor);
        }
        
        this.activeTests.forEach(test => {
            if (test.stop) test.stop();
        });
    }
    
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    logError(message, error) {
        this.results.errors.push({
            timestamp: Date.now(),
            message,
            error: error?.message || error
        });
        console.error(`❌ ${message}:`, error);
    }
    
    /**
     * Generate comprehensive test report
     */
    generateReport() {
        const totalTime = (Date.now() - this.startTime) / 1000;
        
        console.log('\n' + '='.repeat(50));
        console.log('🔥 STRESS TEST REPORT');
        console.log('='.repeat(50));
        console.log(`Total Test Duration: ${totalTime.toFixed(2)}s`);
        console.log(`Total Errors: ${this.results.errors.length}`);
        console.log('');
        
        // Order Creation Summary
        if (this.results.orderCreation.length > 0) {
            const avgRate = this.results.orderCreation.reduce((sum, r) => sum + r.ordersPerSecond, 0) / this.results.orderCreation.length;
            console.log(`📦 Order Creation: ${avgRate.toFixed(1)} orders/sec average`);
        }
        
        // Database Performance Summary
        if (this.results.databaseQueries.length > 0) {
            const avgSuccessRate = this.results.databaseQueries.reduce((sum, r) => sum + r.successRate, 0) / this.results.databaseQueries.length;
            console.log(`🗄️ Database Performance: ${avgSuccessRate.toFixed(1)}% success rate`);
        }
        
        // API Performance Summary
        if (this.results.apiCalls.length > 0) {
            const avgSuccessRate = this.results.apiCalls.reduce((sum, r) => sum + r.successRate, 0) / this.results.apiCalls.length;
            console.log(`🗺️ API Performance: ${avgSuccessRate.toFixed(1)}% success rate`);
        }
        
        // Memory Usage Summary
        if (this.results.memoryUsage.length > 0) {
            const maxMemory = Math.max(...this.results.memoryUsage.map(m => m.memoryMB || 0));
            console.log(`🧠 Peak Memory Usage: ${maxMemory.toFixed(2)} MB`);
        }
        
        console.log('');
        console.log('📊 Detailed results available in this.results object');
        console.log('='.repeat(50));
        
        return this.results;
    }
}

/**
 * Agent Simulator for concurrent testing
 */
class AgentSimulator {
    constructor(agentId, config) {
        this.agentId = agentId;
        this.config = config;
        this.isRunning = false;
        this.operationCount = 0;
        this.errorCount = 0;
        this.responseTimes = [];
        this.averageResponseTime = 0;
    }
    
    async startOperations() {
        this.isRunning = true;
        
        while (this.isRunning) {
            try {
                const operation = this.getRandomOperation();
                const startTime = performance.now();
                
                await this.executeOperation(operation);
                
                const responseTime = performance.now() - startTime;
                this.responseTimes.push(responseTime);
                this.operationCount++;
                
                // Calculate rolling average
                this.averageResponseTime = this.responseTimes.reduce((sum, time) => sum + time, 0) / this.responseTimes.length;
                
            } catch (error) {
                this.errorCount++;
            }
            
            // Random delay between operations
            await this.delay(Math.random() * 2000 + 500);
        }
    }
    
    stop() {
        this.isRunning = false;
    }
    
    getRandomOperation() {
        const operations = ['location_update', 'order_fetch', 'status_update', 'order_accept'];
        return operations[Math.floor(Math.random() * operations.length)];
    }
    
    async executeOperation(operation) {
        const operations = {
            location_update: () => this.updateLocation(),
            order_fetch: () => this.fetchOrders(),
            status_update: () => this.updateOrderStatus(),
            order_accept: () => this.acceptOrder()
        };
        
        return operations[operation]();
    }
    
    async updateLocation() {
        const data = {
            agent_id: this.agentId,
            latitude: 40.7128 + (Math.random() - 0.5) * 0.1,
            longitude: -74.0060 + (Math.random() - 0.5) * 0.1,
            accuracy: Math.random() * 20 + 5
        };
        
        return this.makeApiCall('agent_location_update', data);
    }
    
    async fetchOrders() {
        return this.makeApiCall('agent_get_orders', { agent_id: this.agentId });
    }
    
    async updateOrderStatus() {
        const statuses = ['accepted', 'picked_up', 'out_for_delivery', 'delivered'];
        const data = {
            order_id: Math.floor(Math.random() * 1000) + 1,
            status: statuses[Math.floor(Math.random() * statuses.length)],
            agent_id: this.agentId
        };
        
        return this.makeApiCall('update_order_status', data);
    }
    
    async acceptOrder() {
        const data = {
            order_id: Math.floor(Math.random() * 1000) + 1,
            agent_id: this.agentId
        };
        
        return this.makeApiCall('accept_order', data);
    }
    
    async makeApiCall(action, data) {
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: `rdm_${action}`,
                ...data
            })
        });
        
        return response.json();
    }
    
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { RestroReachStressTester, AgentSimulator };
}

// Auto-run if executed directly
if (typeof window !== 'undefined') {
    window.RestroReachStressTester = RestroReachStressTester;
    window.AgentSimulator = AgentSimulator;
}

// Example usage:
// const tester = new RestroReachStressTester({
//     maxOrders: 2000,
//     concurrentAgents: 100,
//     testDuration: 600000 // 10 minutes
// });
// 
// tester.runStressTests().then(results => {
//     console.log('Stress testing completed:', results);
// }); 