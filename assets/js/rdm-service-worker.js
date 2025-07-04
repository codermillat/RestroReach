/**
 * RestroReach Service Worker - PWA Offline Capabilities
 * Handles caching, offline functionality, and background sync for delivery agents
 */

const CACHE_VERSION = 'rdm-v1.0.0';
const STATIC_CACHE = 'rdm-static-v1.0.0';
const DYNAMIC_CACHE = 'rdm-dynamic-v1.0.0';
const OFFLINE_CACHE = 'rdm-offline-v1.0.0';

// Get plugin URL from URL parameters
const urlParams = new URLSearchParams(location.search);
const PLUGIN_URL = urlParams.get('pluginUrl') || '/wp-content/plugins/restaurant-delivery-manager/';

// Critical resources to cache for offline functionality
const STATIC_RESOURCES = [
    // Core mobile agent interface
    PLUGIN_URL + 'templates/mobile-agent/dashboard.php',
    PLUGIN_URL + 'assets/css/rdm-mobile-agent.css',
    PLUGIN_URL + 'assets/js/rdm-mobile-agent.js',
    PLUGIN_URL + 'assets/css/rdm-mobile-agent.min.css',
    PLUGIN_URL + 'assets/js/rdm-mobile-agent.min.js',
    
    // Essential styles and scripts
    PLUGIN_URL + 'assets/css/rdm-notifications.css',
    PLUGIN_URL + 'assets/js/rdm-agent-notifications.js',
    
    // Offline fallback page
    PLUGIN_URL + 'templates/offline.html'
];

// API endpoints that should be cached for offline access
const CACHEABLE_API_ENDPOINTS = [
    '/wp-admin/admin-ajax.php?action=rdm_get_agent_orders',
    '/wp-admin/admin-ajax.php?action=rdm_get_agent_status',
    '/wp-admin/admin-ajax.php?action=rdm_get_delivery_areas'
];

// Background sync tags
const SYNC_TAGS = {
    LOCATION_UPDATE: 'rdm-location-sync',
    ORDER_STATUS: 'rdm-order-status-sync',
    COD_PAYMENT: 'rdm-payment-sync'
};

// ========================================
// Service Worker Event Handlers
// ========================================

/**
 * Install Event - Cache critical resources
 */
self.addEventListener('install', event => {
    console.log('RestroReach Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('RestroReach Service Worker: Caching static resources');
                return cache.addAll(STATIC_RESOURCES);
            })
            .then(() => {
                console.log('RestroReach Service Worker: Installation complete');
                // Skip waiting to activate immediately
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('RestroReach Service Worker: Installation failed', error);
            })
    );
});

/**
 * Activate Event - Clean up old caches
 */
self.addEventListener('activate', event => {
    console.log('RestroReach Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        // Delete old versions of caches
                        if (cacheName.startsWith('rdm-') && 
                            cacheName !== STATIC_CACHE && 
                            cacheName !== DYNAMIC_CACHE && 
                            cacheName !== OFFLINE_CACHE) {
                            console.log('RestroReach Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('RestroReach Service Worker: Activation complete');
                // Take control of all pages immediately
                return self.clients.claim();
            })
    );
});

/**
 * Fetch Event - Handle network requests with caching strategies
 */
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Only handle GET requests for now
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests with appropriate strategies
    if (isStaticResource(request)) {
        event.respondWith(cacheFirstStrategy(request));
    } else if (isAPIRequest(request)) {
        event.respondWith(networkFirstStrategy(request));
    } else if (isNavigationRequest(request)) {
        event.respondWith(handleNavigation(request));
    } else {
        event.respondWith(staleWhileRevalidateStrategy(request));
    }
});

/**
 * Background Sync Event - Handle offline actions
 */
self.addEventListener('sync', event => {
    console.log('RestroReach Service Worker: Background sync triggered', event.tag);
    
    switch (event.tag) {
        case SYNC_TAGS.LOCATION_UPDATE:
            event.waitUntil(syncLocationUpdates());
            break;
        case SYNC_TAGS.ORDER_STATUS:
            event.waitUntil(syncOrderStatusUpdates());
            break;
        case SYNC_TAGS.COD_PAYMENT:
            event.waitUntil(syncCODPayments());
            break;
        default:
            console.log('RestroReach Service Worker: Unknown sync tag', event.tag);
    }
});

/**
 * Push Event - Handle push notifications
 */
self.addEventListener('push', event => {
    console.log('RestroReach Service Worker: Push notification received');
    
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            console.error('RestroReach Service Worker: Failed to parse push data', error);
            data = { title: 'RestroReach', body: event.data.text() };
        }
    }
    
    const options = {
        title: data.title || 'RestroReach',
        body: data.body || 'New notification',
        icon: PLUGIN_URL + 'assets/images/icon-192x192.png',
        badge: PLUGIN_URL + 'assets/images/badge-72x72.png',
        data: data,
        actions: data.actions || [],
        requireInteraction: data.urgent || false,
        vibrate: data.vibrate || [200, 100, 200]
    };
    
    event.waitUntil(
        self.registration.showNotification(options.title, options)
    );
});

/**
 * Notification Click Event - Handle notification interactions
 */
self.addEventListener('notificationclick', event => {
    console.log('RestroReach Service Worker: Notification clicked');
    
    event.notification.close();
    
    const data = event.notification.data || {};
    let targetUrl = data.url || '/wp-admin/admin.php?page=rdm-agent-dashboard';
    
    // Handle action clicks
    if (event.action) {
        switch (event.action) {
            case 'accept':
                targetUrl = data.acceptUrl || targetUrl;
                break;
            case 'reject':
                targetUrl = data.rejectUrl || targetUrl;
                break;
            case 'view':
                targetUrl = data.viewUrl || targetUrl;
                break;
        }
    }
    
    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then(clientList => {
                // Check if a window is already open
                for (const client of clientList) {
                    if (client.url.includes('rdm-agent') && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // Open new window if none exists
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});

// ========================================
// Caching Strategies
// ========================================

/**
 * Cache First Strategy - For static resources
 */
async function cacheFirstStrategy(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('RestroReach Service Worker: Cache first strategy failed', error);
        return await caches.match(PLUGIN_URL + 'templates/offline.html');
    }
}

/**
 * Network First Strategy - For API requests
 */
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful API responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('RestroReach Service Worker: Network failed, trying cache');
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline indicator for API requests
        return new Response(JSON.stringify({
            success: false,
            data: { 
                message: 'Offline - request will be retried when connection is restored',
                offline: true
            }
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

/**
 * Stale While Revalidate Strategy - For dynamic content
 */
async function staleWhileRevalidateStrategy(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(() => cachedResponse);
    
    return cachedResponse || fetchPromise;
}

/**
 * Handle Navigation Requests
 */
async function handleNavigation(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(STATIC_CACHE);
        return await cache.match(PLUGIN_URL + 'templates/mobile-agent/dashboard.php') ||
               await cache.match(PLUGIN_URL + 'templates/offline.html');
    }
}

// ========================================
// Background Sync Functions
// ========================================

/**
 * Sync Location Updates
 */
async function syncLocationUpdates() {
    try {
        const db = await openIndexedDB();
        const locationUpdates = await getStoredData(db, 'locationUpdates');
        
        for (const update of locationUpdates) {
            try {
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rdm_update_agent_location',
                        latitude: update.latitude,
                        longitude: update.longitude,
                        accuracy: update.accuracy,
                        battery_level: update.battery_level,
                        timestamp: update.timestamp,
                        nonce: update.nonce
                    })
                });
                
                if (response.ok) {
                    await removeStoredData(db, 'locationUpdates', update.id);
                    console.log('RestroReach Service Worker: Location update synced', update.id);
                }
            } catch (error) {
                console.error('RestroReach Service Worker: Location sync failed', error);
            }
        }
    } catch (error) {
        console.error('RestroReach Service Worker: Location sync process failed', error);
    }
}

/**
 * Sync Order Status Updates
 */
async function syncOrderStatusUpdates() {
    try {
        const db = await openIndexedDB();
        const statusUpdates = await getStoredData(db, 'statusUpdates');
        
        for (const update of statusUpdates) {
            try {
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rdm_update_order_status',
                        order_id: update.order_id,
                        status: update.status,
                        notes: update.notes,
                        timestamp: update.timestamp,
                        nonce: update.nonce
                    })
                });
                
                if (response.ok) {
                    await removeStoredData(db, 'statusUpdates', update.id);
                    console.log('RestroReach Service Worker: Status update synced', update.id);
                }
            } catch (error) {
                console.error('RestroReach Service Worker: Status sync failed', error);
            }
        }
    } catch (error) {
        console.error('RestroReach Service Worker: Status sync process failed', error);
    }
}

/**
 * Sync COD Payments
 */
async function syncCODPayments() {
    try {
        const db = await openIndexedDB();
        const payments = await getStoredData(db, 'codPayments');
        
        for (const payment of payments) {
            try {
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'rdm_collect_cod_payment',
                        order_id: payment.order_id,
                        amount_received: payment.amount_received,
                        change_given: payment.change_given,
                        notes: payment.notes,
                        timestamp: payment.timestamp,
                        nonce: payment.nonce
                    })
                });
                
                if (response.ok) {
                    await removeStoredData(db, 'codPayments', payment.id);
                    console.log('RestroReach Service Worker: COD payment synced', payment.id);
                }
            } catch (error) {
                console.error('RestroReach Service Worker: Payment sync failed', error);
            }
        }
    } catch (error) {
        console.error('RestroReach Service Worker: Payment sync process failed', error);
    }
}

// ========================================
// Utility Functions
// ========================================

/**
 * Check if request is for static resource
 */
function isStaticResource(request) {
    const url = new URL(request.url);
    return url.pathname.includes('/assets/') || 
           url.pathname.endsWith('.css') || 
           url.pathname.endsWith('.js') || 
           url.pathname.endsWith('.png') || 
           url.pathname.endsWith('.jpg') || 
           url.pathname.endsWith('.svg');
}

/**
 * Check if request is API request
 */
function isAPIRequest(request) {
    const url = new URL(request.url);
    return url.pathname.includes('admin-ajax.php') || 
           url.pathname.includes('/wp-json/');
}

/**
 * Check if request is navigation request
 */
function isNavigationRequest(request) {
    return request.mode === 'navigate' || 
           (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
}

/**
 * Open IndexedDB for offline storage
 */
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('RestroReachOfflineDB', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            // Create object stores for different types of offline data
            if (!db.objectStoreNames.contains('locationUpdates')) {
                const locationStore = db.createObjectStore('locationUpdates', { keyPath: 'id', autoIncrement: true });
                locationStore.createIndex('timestamp', 'timestamp', { unique: false });
            }
            
            if (!db.objectStoreNames.contains('statusUpdates')) {
                const statusStore = db.createObjectStore('statusUpdates', { keyPath: 'id', autoIncrement: true });
                statusStore.createIndex('timestamp', 'timestamp', { unique: false });
            }
            
            if (!db.objectStoreNames.contains('codPayments')) {
                const paymentStore = db.createObjectStore('codPayments', { keyPath: 'id', autoIncrement: true });
                paymentStore.createIndex('timestamp', 'timestamp', { unique: false });
            }
        };
    });
}

/**
 * Get stored data from IndexedDB
 */
function getStoredData(db, storeName) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const request = store.getAll();
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

/**
 * Remove stored data from IndexedDB
 */
function removeStoredData(db, storeName, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.delete(id);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

console.log('RestroReach Service Worker: Script loaded'); 