/**
 * RestroReach Service Worker Registration
 * Handles PWA installation and service worker lifecycle
 */

(function() {
    'use strict';

    // Check if service workers are supported
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            registerServiceWorker();
            initPWAInstallPrompt();
        });
    }

    /**
     * Register service worker
     */
    async function registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(rdmAgent.serviceWorkerUrl, {
                scope: '/'
            });

            console.log('RestroReach: Service Worker registered successfully', registration);

            // Handle updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New service worker available
                        showUpdateNotification();
                    }
                });
            });

            // Show success notification
            if (typeof rdmAgent !== 'undefined' && rdmAgent.strings) {
                showNotification(rdmAgent.strings.serviceWorkerRegistered || 'Offline support enabled', 'success');
            }

        } catch (error) {
            console.error('RestroReach: Service Worker registration failed', error);
            
            if (typeof rdmAgent !== 'undefined' && rdmAgent.strings) {
                showNotification(rdmAgent.strings.serviceWorkerFailed || 'Offline support unavailable', 'warning');
            }
        }
    }

    /**
     * Initialize PWA install prompt
     */
    function initPWAInstallPrompt() {
        let deferredPrompt;

        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('RestroReach: PWA install prompt available');
            
            // Prevent default mini-infobar
            e.preventDefault();
            
            // Save event for later use
            deferredPrompt = e;
            
            // Show custom install button
            showInstallButton(deferredPrompt);
        });

        // Listen for app installed event
        window.addEventListener('appinstalled', (e) => {
            console.log('RestroReach: PWA installed successfully');
            
            if (typeof rdmAgent !== 'undefined' && rdmAgent.strings) {
                showNotification(rdmAgent.strings.appInstalled || 'App installed successfully', 'success');
            }
            
            // Hide install button
            hideInstallButton();
            
            // Clear the deferredPrompt
            deferredPrompt = null;
        });

        // Check if app is already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('RestroReach: PWA is running in standalone mode');
            hideInstallButton();
        }
    }

    /**
     * Show PWA install button
     */
    function showInstallButton(deferredPrompt) {
        // Create install button if it doesn't exist
        let installButton = document.getElementById('rdm-pwa-install-btn');
        
        if (!installButton) {
            installButton = document.createElement('button');
            installButton.id = 'rdm-pwa-install-btn';
            installButton.className = 'rdm-install-button';
            installButton.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                </svg>
                <span>${rdmAgent.strings?.appInstallPrompt || 'Install App'}</span>
            `;
            
            // Add styles
            installButton.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #2271b1;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 12px 16px;
                display: flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                z-index: 1000;
                transition: all 0.3s ease;
            `;
            
            // Add hover effect
            installButton.addEventListener('mouseenter', () => {
                installButton.style.background = '#135e96';
                installButton.style.transform = 'translateY(-2px)';
            });
            
            installButton.addEventListener('mouseleave', () => {
                installButton.style.background = '#2271b1';
                installButton.style.transform = 'translateY(0)';
            });
            
            // Add click handler
            installButton.addEventListener('click', async () => {
                if (deferredPrompt) {
                    // Show install prompt
                    deferredPrompt.prompt();
                    
                    // Wait for user choice
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log('RestroReach: PWA install outcome:', outcome);
                    
                    if (outcome === 'accepted') {
                        console.log('RestroReach: User accepted PWA install');
                    } else {
                        console.log('RestroReach: User dismissed PWA install');
                    }
                    
                    // Clear the deferredPrompt
                    deferredPrompt = null;
                    hideInstallButton();
                }
            });
            
            // Add to page
            document.body.appendChild(installButton);
        }
        
        // Show the button
        installButton.style.display = 'flex';
    }

    /**
     * Hide PWA install button
     */
    function hideInstallButton() {
        const installButton = document.getElementById('rdm-pwa-install-btn');
        if (installButton) {
            installButton.style.display = 'none';
        }
    }

    /**
     * Show service worker update notification
     */
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'rdm-sw-update-notification';
        notification.innerHTML = `
            <div class="rdm-sw-update-content">
                <span>New version available!</span>
                <button onclick="window.location.reload()" class="rdm-sw-update-btn">Refresh</button>
                <button onclick="this.parentElement.parentElement.remove()" class="rdm-sw-dismiss-btn">×</button>
            </div>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #2271b1;
            color: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1001;
            font-size: 14px;
        `;
        
        // Style the content
        const content = notification.querySelector('.rdm-sw-update-content');
        content.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
        `;
        
        // Style the refresh button
        const refreshBtn = notification.querySelector('.rdm-sw-update-btn');
        refreshBtn.style.cssText = `
            background: white;
            color: #2271b1;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        `;
        
        // Style the dismiss button
        const dismissBtn = notification.querySelector('.rdm-sw-dismiss-btn');
        dismissBtn.style.cssText = `
            background: transparent;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 18px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);
    }

    /**
     * Show notification (if available)
     */
    function showNotification(message, type = 'info') {
        // Try to use existing notification system
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        
        // Fallback: simple console log
        console.log(`RestroReach ${type}: ${message}`);
        
        // Optional: Show a simple toast
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: ${type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
            color: white;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Fade in
        setTimeout(() => toast.style.opacity = '1', 100);
        
        // Fade out and remove
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Handle network status changes
     */
    function initNetworkStatusHandling() {
        window.addEventListener('online', () => {
            console.log('RestroReach: Back online');
            if (typeof rdmAgent !== 'undefined' && rdmAgent.strings) {
                showNotification(rdmAgent.strings.online || 'Back online', 'success');
            }
            
            // Trigger background sync if available
            if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
                navigator.serviceWorker.ready.then(registration => {
                    return registration.sync.register('rdm-background-sync');
                }).catch(console.error);
            }
        });

        window.addEventListener('offline', () => {
            console.log('RestroReach: Gone offline');
            if (typeof rdmAgent !== 'undefined' && rdmAgent.strings) {
                showNotification(rdmAgent.strings.offline || 'Working offline', 'warning');
            }
        });
    }

    // Initialize network status handling
    initNetworkStatusHandling();

})(); 