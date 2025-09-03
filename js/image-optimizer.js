// Image Optimization and Lazy Loading
(function() {
    'use strict';
    
    const ImageOptimizer = {
        // Intersection Observer for lazy loading
        observer: null,
        
        // Initialize image optimization
        init: function() {
            this.setupLazyLoading();
            this.optimizeExistingImages();
            this.preloadCriticalImages();
        },
        
        // Set up intersection observer for lazy loading
        setupLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImage(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px',
                    threshold: 0.01
                });
                
                // Observe all lazy images
                document.querySelectorAll('img[data-src]').forEach(img => {
                    this.observer.observe(img);
                });
            } else {
                // Fallback for browsers without IntersectionObserver
                document.querySelectorAll('img[data-src]').forEach(img => {
                    this.loadImage(img);
                });
            }
        },
        
        // Load individual image
        loadImage: function(img) {
            const src = img.getAttribute('data-src');
            if (!src) return;
            
            const image = new Image();
            image.onload = () => {
                img.src = src;
                img.classList.add('loaded');
                img.removeAttribute('data-src');
            };
            image.onerror = () => {
                // Fallback to placeholder or default image
                img.src = this.generatePlaceholder(img.getAttribute('alt') || 'Image');
                img.classList.add('error');
            };
            image.src = src;
        },
        
        // Optimize existing images
        optimizeExistingImages: function() {
            document.querySelectorAll('img').forEach(img => {
                // Add loading attribute for native lazy loading
                if (!img.hasAttribute('loading')) {
                    img.loading = 'lazy';
                }
                
                // Add error handling
                if (!img.hasAttribute('data-error-handled')) {
                    img.addEventListener('error', (e) => {
                        this.handleImageError(e.target);
                    });
                    img.setAttribute('data-error-handled', 'true');
                }
                
                // Optimize avatar images
                if (img.src.includes('ui-avatars.com')) {
                    this.optimizeAvatarUrl(img);
                }
            });
        },
        
        // Handle image loading errors
        handleImageError: function(img) {
            if (img.classList.contains('user-avatar') || img.classList.contains('member-avatar')) {
                // For avatars, generate a text-based placeholder
                const name = img.alt || 'User';
                img.src = this.generatePlaceholder(name);
            } else {
                // For other images, use a generic placeholder
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.createPlaceholderSVG());
            }
        },
        
        // Generate placeholder for avatars
        generatePlaceholder: function(name) {
            const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
            const colors = ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
            const color = colors[name.length % colors.length];
            
            return `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=${color.slice(1)}&color=fff&size=120&format=svg`;
        },
        
        // Create placeholder SVG
        createPlaceholderSVG: function() {
            return `
                <svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#f1f5f9"/>
                    <rect x="150" y="100" width="100" height="100" rx="8" fill="#e2e8f0"/>
                    <circle cx="200" cy="130" r="15" fill="#cbd5e1"/>
                    <path d="M185 155 Q200 145 215 155 L215 175 L185 175 Z" fill="#cbd5e1"/>
                    <text x="200" y="220" text-anchor="middle" font-family="system-ui" font-size="14" fill="#64748b">Image not available</text>
                </svg>
            `;
        },
        
        // Optimize avatar URLs
        optimizeAvatarUrl: function(img) {
            if (!img.src.includes('ui-avatars.com')) return;
            
            const url = new URL(img.src);
            
            // Add format=svg for better compression and scaling
            if (!url.searchParams.has('format')) {
                url.searchParams.set('format', 'svg');
            }
            
            // Optimize size based on actual display size
            const rect = img.getBoundingClientRect();
            if (rect.width > 0) {
                const optimalSize = Math.ceil(rect.width * window.devicePixelRatio);
                url.searchParams.set('size', Math.min(optimalSize, 200)); // Cap at 200px
            }
            
            // Update src if changed
            if (url.toString() !== img.src) {
                img.src = url.toString();
            }
        },
        
        // Preload critical images
        preloadCriticalImages: function() {
            const criticalImages = [
                // Add any critical images that should be preloaded
            ];
            
            criticalImages.forEach(src => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = src;
                document.head.appendChild(link);
            });
        },
        
        // Add lazy loading to new images
        observeNewImages: function() {
            if (!this.observer) return;
            
            // Use MutationObserver to watch for new images
            const mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) { // Element node
                            const images = node.tagName === 'IMG' ? [node] : node.querySelectorAll('img[data-src]');
                            images.forEach(img => {
                                if (img.hasAttribute('data-src')) {
                                    this.observer.observe(img);
                                }
                            });
                        }
                    });
                });
            });
            
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ImageOptimizer.init());
    } else {
        ImageOptimizer.init();
    }
    
    // Make available globally
    window.ImageOptimizer = ImageOptimizer;
})();