/**
 * RestroReach Service Worker - PWA Offline Capabilities
 * Handles caching, offline functionality, and background sync for delivery agents
 */

const CACHE_VERSION = 'rdm-v1.0.0';
const STATIC_CACHE = 'rdm-static-v1.0.0';
const DYNAMIC_CACHE = 'rdm-dynamic-v1.0.0';
const OFFLINE_CACHE = 'rdm-offline-v1.0.0';

// Critical resources to cache for offline functionality
const STATIC_RESOURCES = [
    // Core mobile agent interface
    '/wp-content/plugins/restaurant-delivery-manager/templates/mobile-agent/dashboard.php',
    '/wp-content/plugins/restaurant-delivery-manager/assets/css/rdm-mobile-agent.css',
    '/wp-content/plugins/restaurant-delivery-manager/assets/js/rdm-mobile-agent.js',
    '/wp-content/plugins/restaurant-delivery-manager/assets/css/rdm-mobile-agent.min.css',
    '/wp-content/plugins/restaurant-delivery-manager/assets/js/rdm-mobile-agent.min.js',
    
    // Essential styles and scripts
    '/wp-content/plugins/restaurant-delivery-manager/assets/css/rdm-notifications.css',
    '/wp-content/plugins/restaurant-delivery-manager/assets/js/rdm-agent-notifications.js'
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
                return self.clients.claim();
            })
    );
});

/**
 * Fetch Event - Handle network requests with caching strategies
 */
self.addEventListener('fetch', event => {
    const { request } = event;
    
    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests
    if (isStaticResource(request)) {
        event.respondWith(cacheFirstStrategy(request));
    } else if (isAPIRequest(request)) {
        event.respondWith(networkFirstStrategy(request));
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
            data = { title: 'RestroReach', body: event.data.text() };
        }
    }
    
    const options = {
        title: data.title || 'RestroReach',
        body: data.body || 'New notification',
        icon: '/wp-content/plugins/restaurant-delivery-manager/assets/images/icon-192x192.png',
        data: data,
        requireInteraction: data.urgent || false,
        vibrate: [200, 100, 200]
    };
    
    event.waitUntil(
        self.registration.showNotification(options.title, options)
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
        return new Response('Offline', { status: 503 });
    }
}

/**
 * Network First Strategy - For API requests
 */
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
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
 * Stale While Revalidate Strategy
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

// ========================================
// Background Sync Functions
// ========================================

async function syncLocationUpdates() {
    // Sync queued location updates when network is available
    console.log('RestroReach Service Worker: Syncing location updates');
}

async function syncOrderStatusUpdates() {
    // Sync queued order status updates when network is available
    console.log('RestroReach Service Worker: Syncing order status updates');
}

async function syncCODPayments() {
    // Sync queued COD payments when network is available
    console.log('RestroReach Service Worker: Syncing COD payments');
}

// ========================================
// Utility Functions
// ========================================

function isStaticResource(request) {
    const url = new URL(request.url);
    return url.pathname.includes('/assets/') || 
           url.pathname.endsWith('.css') || 
           url.pathname.endsWith('.js') || 
           url.pathname.endsWith('.png') || 
           url.pathname.endsWith('.jpg');
}

function isAPIRequest(request) {
    const url = new URL(request.url);
    return url.pathname.includes('admin-ajax.php');
}

console.log('RestroReach Service Worker: Script loaded'); 