// Optimized JavaScript Loader
(function() {
    'use strict';
    
    const LoaderManager = {
        // Track loaded resources
        loadedScripts: new Set(),
        loadedStyles: new Set(),
        
        // Performance monitoring
        startTime: performance.now(),
        
        // Load CSS asynchronously
        loadCSS: function(href, media = 'all') {
            return new Promise((resolve, reject) => {
                if (this.loadedStyles.has(href)) {
                    resolve();
                    return;
                }
                
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.media = media;
                link.onload = () => {
                    this.loadedStyles.add(href);
                    resolve();
                };
                link.onerror = reject;
                document.head.appendChild(link);
            });
        },
        
        // Load JavaScript asynchronously
        loadScript: function(src, defer = true) {
            return new Promise((resolve, reject) => {
                if (this.loadedScripts.has(src)) {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = src;
                if (defer) script.defer = true;
                script.onload = () => {
                    this.loadedScripts.add(src);
                    resolve();
                };
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },
        
        // Load multiple resources in parallel
        loadParallel: function(resources) {
            return Promise.allSettled(resources.map(resource => {
                if (resource.type === 'css') {
                    return this.loadCSS(resource.src, resource.media);
                } else if (resource.type === 'js') {
                    return this.loadScript(resource.src, resource.defer);
                }
            }));
        },
        
        // Initialize performance monitoring
        initPerformanceTracking: function() {
            // Log core web vitals
            if ('web-vital' in window) {
                new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        console.log('Performance:', entry.name, entry.value);
                    }
                }).observe({ entryTypes: ['measure'] });
            }
            
            // Track load time
            window.addEventListener('load', () => {
                const loadTime = performance.now() - this.startTime;
                console.log(`Page load time: ${loadTime.toFixed(2)}ms`);
            });
        },
        
        // Initialize critical features first
        initCritical: function() {
            // Mobile navigation
            this.initMobileNav();
            
            // Touch device detection
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
            }
        },
        
        // Initialize mobile navigation immediately
        initMobileNav: function() {
            const toggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.mobile-nav-overlay');
            
            if (!toggle || !sidebar) return;
            
            const toggleNav = () => {
                sidebar.classList.toggle('mobile-open');
                if (overlay) overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            };
            
            const closeNav = () => {
                sidebar.classList.remove('mobile-open');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            };
            
            toggle.addEventListener('click', toggleNav);
            if (overlay) overlay.addEventListener('click', closeNav);
            
            // Close on resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) closeNav();
            });
        }
    };
    
    // Initialize immediately for critical features
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => LoaderManager.initCritical());
    } else {
        LoaderManager.initCritical();
    }
    
    // Make loader available globally
    window.LoaderManager = LoaderManager;
    
    // Start performance tracking
    LoaderManager.initPerformanceTracking();
})();