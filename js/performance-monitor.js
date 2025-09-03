// Web Performance Monitoring
(function() {
    'use strict';
    
    const PerformanceMonitor = {
        metrics: {},
        
        init: function() {
            this.collectBasicMetrics();
            this.setupPerformanceObserver();
            this.monitorResources();
            this.trackUserInteractions();
            this.reportMetrics();
        },
        
        // Collect basic performance metrics
        collectBasicMetrics: function() {
            if (!performance.timing) return;
            
            const timing = performance.timing;
            const navigation = performance.navigation;
            
            this.metrics.pageLoad = {
                domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
                windowLoad: timing.loadEventEnd - timing.navigationStart,
                firstByte: timing.responseStart - timing.navigationStart,
                domReady: timing.domComplete - timing.navigationStart,
                networkLatency: timing.responseEnd - timing.fetchStart,
                serverResponseTime: timing.responseEnd - timing.requestStart
            };
            
            this.metrics.navigation = {
                type: navigation.type === 0 ? 'navigate' : 
                      navigation.type === 1 ? 'reload' : 
                      navigation.type === 2 ? 'back_forward' : 'unknown',
                redirectCount: navigation.redirectCount
            };
        },
        
        // Setup Performance Observer for modern metrics
        setupPerformanceObserver: function() {
            if (!('PerformanceObserver' in window)) return;
            
            try {
                // Largest Contentful Paint (LCP)
                new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    this.metrics.lcp = lastEntry.startTime;
                }).observe({ entryTypes: ['largest-contentful-paint'] });
                
                // First Input Delay (FID)
                new PerformanceObserver((list) => {
                    const firstInput = list.getEntries()[0];
                    this.metrics.fid = firstInput.processingStart - firstInput.startTime;
                }).observe({ entryTypes: ['first-input'], buffered: true });
                
                // Cumulative Layout Shift (CLS)
                let clsScore = 0;
                new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) {
                            clsScore += entry.value;
                        }
                    }
                    this.metrics.cls = clsScore;
                }).observe({ entryTypes: ['layout-shift'], buffered: true });
                
                // First Contentful Paint (FCP)
                new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    for (const entry of entries) {
                        if (entry.name === 'first-contentful-paint') {
                            this.metrics.fcp = entry.startTime;
                        }
                    }
                }).observe({ entryTypes: ['paint'], buffered: true });
                
            } catch (error) {
                console.warn('Performance Observer error:', error);
            }
        },
        
        // Monitor resource loading
        monitorResources: function() {
            if (!performance.getEntriesByType) return;
            
            window.addEventListener('load', () => {
                const resources = performance.getEntriesByType('resource');
                const resourceMetrics = {
                    total: resources.length,
                    css: 0,
                    js: 0,
                    images: 0,
                    fonts: 0,
                    other: 0,
                    slowResources: []
                };
                
                resources.forEach(resource => {
                    const duration = resource.responseEnd - resource.startTime;
                    
                    // Categorize resources
                    if (resource.name.includes('.css')) {
                        resourceMetrics.css++;
                    } else if (resource.name.includes('.js')) {
                        resourceMetrics.js++;
                    } else if (/\.(jpg|jpeg|png|gif|webp|svg)/.test(resource.name)) {
                        resourceMetrics.images++;
                    } else if (/\.(woff|woff2|ttf|otf)/.test(resource.name)) {
                        resourceMetrics.fonts++;
                    } else {
                        resourceMetrics.other++;
                    }
                    
                    // Track slow resources (>2 seconds)
                    if (duration > 2000) {
                        resourceMetrics.slowResources.push({
                            name: resource.name,
                            duration: Math.round(duration),
                            size: resource.transferSize || 0
                        });
                    }
                });
                
                this.metrics.resources = resourceMetrics;
            });
        },
        
        // Track user interactions
        trackUserInteractions: function() {
            let interactionCount = 0;
            let timeToFirstInteraction = null;
            
            const trackInteraction = () => {
                if (timeToFirstInteraction === null) {
                    timeToFirstInteraction = performance.now();
                }
                interactionCount++;
            };
            
            ['click', 'touchstart', 'keydown'].forEach(event => {
                document.addEventListener(event, trackInteraction, { once: true, passive: true });
            });
            
            // Save metrics after 5 seconds
            setTimeout(() => {
                this.metrics.interactions = {
                    timeToFirstInteraction,
                    totalInteractions: interactionCount
                };
            }, 5000);
        },
        
        // Collect memory usage (if available)
        collectMemoryMetrics: function() {
            if (!performance.memory) return null;
            
            return {
                usedJSHeapSize: Math.round(performance.memory.usedJSHeapSize / 1048576), // MB
                totalJSHeapSize: Math.round(performance.memory.totalJSHeapSize / 1048576), // MB
                jsHeapSizeLimit: Math.round(performance.memory.jsHeapSizeLimit / 1048576) // MB
            };
        },
        
        // Get connection information
        getConnectionInfo: function() {
            if (!navigator.connection) return null;
            
            return {
                effectiveType: navigator.connection.effectiveType,
                downlink: navigator.connection.downlink,
                rtt: navigator.connection.rtt,
                saveData: navigator.connection.saveData
            };
        },
        
        // Report all metrics
        reportMetrics: function() {
            // Wait for page to fully load before reporting
            window.addEventListener('load', () => {
                setTimeout(() => {
                    // Add memory and connection info
                    this.metrics.memory = this.collectMemoryMetrics();
                    this.metrics.connection = this.getConnectionInfo();
                    
                    // Add device info
                    this.metrics.device = {
                        userAgent: navigator.userAgent,
                        viewport: {
                            width: window.innerWidth,
                            height: window.innerHeight
                        },
                        screen: {
                            width: screen.width,
                            height: screen.height
                        },
                        devicePixelRatio: window.devicePixelRatio || 1
                    };
                    
                    // Calculate performance score
                    this.metrics.score = this.calculatePerformanceScore();
                    
                    // Log to console (in development)
                    if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
                        this.logMetrics();
                    }
                    
                    // Send to analytics (if configured)
                    this.sendMetrics();
                    
                }, 2000); // Wait 2 seconds after load for accurate metrics
            });
        },
        
        // Calculate overall performance score (0-100)
        calculatePerformanceScore: function() {
            let score = 100;
            
            // Deduct for slow LCP (>2.5s is poor)
            if (this.metrics.lcp > 2500) score -= 20;
            else if (this.metrics.lcp > 1500) score -= 10;
            
            // Deduct for high FID (>100ms is poor)
            if (this.metrics.fid > 100) score -= 20;
            else if (this.metrics.fid > 50) score -= 10;
            
            // Deduct for high CLS (>0.1 is poor)
            if (this.metrics.cls > 0.25) score -= 20;
            else if (this.metrics.cls > 0.1) score -= 10;
            
            // Deduct for slow page load (>3s is poor)
            if (this.metrics.pageLoad?.windowLoad > 5000) score -= 15;
            else if (this.metrics.pageLoad?.windowLoad > 3000) score -= 8;
            
            // Deduct for slow resources
            if (this.metrics.resources?.slowResources.length > 0) {
                score -= Math.min(this.metrics.resources.slowResources.length * 5, 15);
            }
            
            return Math.max(score, 0);
        },
        
        // Log metrics to console
        logMetrics: function() {
            console.group('📊 Performance Metrics');
            console.log('🎯 Performance Score:', this.metrics.score + '/100');
            
            if (this.metrics.lcp) console.log('🎨 LCP:', Math.round(this.metrics.lcp) + 'ms');
            if (this.metrics.fcp) console.log('🎨 FCP:', Math.round(this.metrics.fcp) + 'ms');
            if (this.metrics.fid) console.log('⚡ FID:', Math.round(this.metrics.fid) + 'ms');
            if (this.metrics.cls) console.log('📐 CLS:', Math.round(this.metrics.cls * 1000) / 1000);
            
            if (this.metrics.pageLoad) {
                console.log('⏱️ Page Load:', Math.round(this.metrics.pageLoad.windowLoad) + 'ms');
                console.log('📡 First Byte:', Math.round(this.metrics.pageLoad.firstByte) + 'ms');
            }
            
            if (this.metrics.resources) {
                console.log('📦 Resources:', this.metrics.resources.total);
                if (this.metrics.resources.slowResources.length > 0) {
                    console.warn('🐌 Slow Resources:', this.metrics.resources.slowResources);
                }
            }
            
            if (this.metrics.memory) {
                console.log('💾 JS Memory:', this.metrics.memory.usedJSHeapSize + 'MB');
            }
            
            console.groupEnd();
        },
        
        // Send metrics to analytics service (implement as needed)
        sendMetrics: function() {
            // This is where you would send metrics to your analytics service
            // Example: Google Analytics, custom endpoint, etc.
            
            // For now, just store in sessionStorage for debugging
            try {
                sessionStorage.setItem('performanceMetrics', JSON.stringify(this.metrics));
            } catch (error) {
                console.warn('Could not store performance metrics:', error);
            }
        }
    };
    
    // Auto-initialize if not in test environment
    if (typeof window !== 'undefined' && !window.TEST_MODE) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => PerformanceMonitor.init());
        } else {
            PerformanceMonitor.init();
        }
    }
    
    // Make available globally
    window.PerformanceMonitor = PerformanceMonitor;
})();