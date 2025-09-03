// DOM Elements - Lazy loaded for performance
let mobileMenuToggle, sidebar, mobileNavOverlay, filterButtons, expandAllBtn, 
    channelCards, refreshBtn, logTypeFilter, notificationBtn;

// Initialize DOM elements when needed
function initDOMElements() {
    if (!mobileMenuToggle) {
        mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        sidebar = document.querySelector('.sidebar');
        mobileNavOverlay = document.querySelector('.mobile-nav-overlay');
        filterButtons = document.querySelectorAll('.filter-btn');
        expandAllBtn = document.querySelector('.expand-all-btn');
        channelCards = document.querySelectorAll('.channel-card');
        refreshBtn = document.querySelector('.refresh-btn');
        logTypeFilter = document.querySelector('.log-type-filter');
        notificationBtn = document.querySelector('.notification-btn');
    }
}

// Mobile Navigation
function toggleMobileNav() {
    sidebar.classList.toggle('mobile-open');
    mobileNavOverlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
}

function closeMobileNav() {
    sidebar.classList.remove('mobile-open');
    mobileNavOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Channel Toggle Functionality
function toggleChannel(channelHeader) {
    const channelCard = channelHeader.closest('.channel-card');
    const channelContent = channelCard.querySelector('.channel-content');
    const expandIcon = channelHeader.querySelector('.expand-icon');
    
    // Toggle expanded state
    channelHeader.classList.toggle('expanded');
    channelContent.classList.toggle('expanded');
    
    // Update expand/collapse state
    if (channelContent.classList.contains('expanded')) {
        channelContent.style.maxHeight = channelContent.scrollHeight + 'px';
    } else {
        channelContent.style.maxHeight = '0';
    }
}

// Expand/Collapse All Channels
function toggleAllChannels() {
    const allChannelHeaders = document.querySelectorAll('.channel-header');
    const allChannelContents = document.querySelectorAll('.channel-content');
    
    // Check if any channel is expanded
    const hasExpandedChannels = Array.from(allChannelContents).some(content => 
        content.classList.contains('expanded')
    );
    
    allChannelHeaders.forEach((header, index) => {
        const content = allChannelContents[index];
        
        if (hasExpandedChannels) {
            // Collapse all
            header.classList.remove('expanded');
            content.classList.remove('expanded');
            content.style.maxHeight = '0';
            expandAllBtn.textContent = 'Expand All';
        } else {
            // Expand all
            header.classList.add('expanded');
            content.classList.add('expanded');
            content.style.maxHeight = content.scrollHeight + 'px';
            expandAllBtn.textContent = 'Collapse All';
        }
    });
}

// Filter Functionality
function handleFilterChange(event) {
    const targetFilter = event.target.getAttribute('data-filter');
    
    // Update active filter button
    filterButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter channel cards based on selected filter
    channelCards.forEach(card => {
        const urgentBadge = card.querySelector('.badge.urgent');
        const mentionsBadge = card.querySelector('.badge.mentions');
        
        let shouldShow = false;
        
        switch (targetFilter) {
            case 'all':
                shouldShow = true;
                break;
            case 'urgent':
                shouldShow = urgentBadge && parseInt(urgentBadge.textContent) > 0;
                break;
            case 'mentions':
                shouldShow = mentionsBadge && parseInt(mentionsBadge.textContent) > 0;
                break;
        }
        
        if (shouldShow) {
            card.style.display = 'block';
            // Add fade in animation
            card.style.opacity = '0';
            setTimeout(() => {
                card.style.opacity = '1';
            }, 100);
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update section header to show filter status
    const sectionHeader = document.querySelector('.section-header h3');
    if (targetFilter === 'all') {
        sectionHeader.textContent = 'Channels & Chats';
    } else {
        const filterName = targetFilter.charAt(0).toUpperCase() + targetFilter.slice(1);
        sectionHeader.textContent = `Channels & Chats - ${filterName}`;
    }
}

// Delivery Logs Filter
function handleLogFilter() {
    const selectedFilter = logTypeFilter.value;
    const logItems = document.querySelectorAll('.log-item');
    
    logItems.forEach(item => {
        const logTitle = item.querySelector('.log-title').textContent.toLowerCase();
        let shouldShow = false;
        
        switch (selectedFilter) {
            case 'all':
                shouldShow = true;
                break;
            case 'email':
                shouldShow = logTitle.includes('email');
                break;
            case 'teams':
                shouldShow = logTitle.includes('teams') || logTitle.includes('webhook');
                break;
        }
        
        item.style.display = shouldShow ? 'flex' : 'none';
    });
}

// Refresh Logs
function refreshLogs() {
    // Add spinning animation to refresh button
    const refreshIcon = refreshBtn.querySelector('i');
    refreshIcon.classList.add('fa-spin');
    
    // Simulate API call with timeout
    setTimeout(() => {
        refreshIcon.classList.remove('fa-spin');
        
        // Add a new log item to simulate refresh
        const logsContainer = document.querySelector('.logs-list');
        const newLogItem = document.createElement('div');
        newLogItem.className = 'log-item success';
        newLogItem.innerHTML = `
            <div class="log-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="log-content">
                <div class="log-title">Real-time update sent via Teams webhook</div>
                <div class="log-meta">
                    <span class="log-time">Just now</span>
                    <span class="log-recipient">General channel</span>
                </div>
            </div>
            <div class="log-status success">Delivered</div>
        `;
        
        // Insert at the beginning
        logsContainer.insertBefore(newLogItem, logsContainer.firstChild);
        
        // Add fade in animation
        newLogItem.style.opacity = '0';
        newLogItem.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            newLogItem.style.transition = 'all 0.3s ease';
            newLogItem.style.opacity = '1';
            newLogItem.style.transform = 'translateY(0)';
        }, 100);
        
        // Show toast notification
        showToast('Logs refreshed successfully', 'success');
    }, 1000);
}

// Toast Notification System
function showToast(message, type = 'info') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add toast styles
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#6366f1'};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 9999;
        font-weight: 500;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// Navigation Active State Management
function updateNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop() || 'index.html';
    
    navLinks.forEach(link => {
        const parentItem = link.closest('.nav-item');
        parentItem.classList.remove('active');
        
        // Check if this link matches the current page
        const linkHref = link.getAttribute('href');
        if (linkHref === currentPage || 
            (currentPage === '' && linkHref === 'index.php') ||
            (currentPage === 'index.php' && linkHref === 'index.php') ||
            (currentPage === 'summaries.php' && linkHref === 'summaries.php')) {
            parentItem.classList.add('active');
        }
    });
}

// Check authentication status
function checkAuthStatus() {
    // For PHP version, authentication is handled server-side
    // This function is kept for compatibility with client-side features
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    return true; // Server-side handles auth redirects
}

// Logout functionality
function handleLogout() {
    // For PHP version, logout is handled by logout.php
    // Show logout message
    showToast('Logging out...', 'info');
    
    // Redirect to logout.php immediately
    window.location.href = 'logout.php';
}

// Real-time Updates Simulation
function simulateRealTimeUpdates() {
    const stats = document.querySelectorAll('.stat-number');
    const timestamp = document.querySelector('.timestamp');
    
    // Update stats periodically
    setInterval(() => {
        stats.forEach(stat => {
            const currentValue = parseInt(stat.textContent);
            const change = Math.floor(Math.random() * 3) - 1; // -1, 0, or 1
            const newValue = Math.max(0, currentValue + change);
            
            if (change !== 0) {
                stat.style.transform = 'scale(1.1)';
                stat.textContent = newValue;
                
                setTimeout(() => {
                    stat.style.transform = 'scale(1)';
                }, 200);
            }
        });
        
        // Update timestamp
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        timestamp.textContent = `Last updated: ${timeString}`;
        
    }, 30000); // Update every 30 seconds
}

// Notification System
function updateNotificationCount() {
    const notificationBadge = document.querySelector('.notification-badge');
    let count = parseInt(notificationBadge.textContent);
    
    // Simulate new notifications
    setInterval(() => {
        if (Math.random() > 0.7) { // 30% chance of new notification
            count++;
            notificationBadge.textContent = count;
            notificationBadge.style.animation = 'pulse 0.5s ease';
            
            setTimeout(() => {
                notificationBadge.style.animation = '';
            }, 500);
        }
    }, 45000); // Check every 45 seconds
}

// Keyboard Navigation
function handleKeyboardNavigation(event) {
    // Escape key closes mobile navigation
    if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
        closeMobileNav();
    }
    
    // Space or Enter on channel headers
    if ((event.key === ' ' || event.key === 'Enter') && event.target.classList.contains('channel-header')) {
        event.preventDefault();
        toggleChannel(event.target);
    }
}

// Intersection Observer for animations
function setupScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe cards for scroll animations
    document.querySelectorAll('.card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
}

// Event Listeners - Optimized with RequestIdleCallback
document.addEventListener('DOMContentLoaded', function() {
    // Check authentication first
    if (!checkAuthStatus()) {
        return; // Stop initialization if redirecting
    }
    
    // Initialize DOM elements
    initDOMElements();
    
    // Critical initialization first
    initCriticalFeatures();
    
    // Use requestIdleCallback for non-critical features
    if ('requestIdleCallback' in window) {
        requestIdleCallback(initNonCriticalFeatures, { timeout: 2000 });
    } else {
        // Fallback for browsers without requestIdleCallback
        setTimeout(initNonCriticalFeatures, 100);
    }
});

function initCriticalFeatures() {
    // Mobile navigation (critical for UX)
    mobileMenuToggle?.addEventListener('click', toggleMobileNav);
    mobileNavOverlay?.addEventListener('click', closeMobileNav);
    
    // Keyboard navigation (accessibility)
    document.addEventListener('keydown', handleKeyboardNavigation);
    
    // Update navigation state
    updateNavigation();
}

function initNonCriticalFeatures() {
    // Filter buttons
    filterButtons?.forEach(button => {
        button.addEventListener('click', handleFilterChange);
    });
    
    // Expand/collapse all
    expandAllBtn?.addEventListener('click', toggleAllChannels);
    
    // Refresh logs
    refreshBtn?.addEventListener('click', refreshLogs);
    
    // Log filter
    logTypeFilter?.addEventListener('change', handleLogFilter);
    
    // Logout functionality
    document.querySelectorAll('.logout').forEach(logoutBtn => {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleLogout();
        });
    });
    
    // Make channel headers focusable for accessibility
    document.querySelectorAll('.channel-header').forEach(header => {
        header.setAttribute('tabindex', '0');
        header.setAttribute('role', 'button');
        header.setAttribute('aria-expanded', 'false');
        
        header.addEventListener('click', function() {
            const isExpanded = this.classList.contains('expanded');
            this.setAttribute('aria-expanded', !isExpanded);
        });
    });
    
    // Initialize non-critical features
    simulateRealTimeUpdates();
    updateNotificationCount();
    setupScrollAnimations();
    addTouchSupport();
    
    // Show welcome message (delayed)
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    if (currentPage === 'index.php') {
        setTimeout(() => {
            showToast('Welcome to Teams Activity Dashboard!', 'success');
        }, 1500);
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    // Close mobile navigation on resize to desktop
    if (window.innerWidth > 768) {
        closeMobileNav();
    }
    
    // Recalculate channel content heights
    document.querySelectorAll('.channel-content.expanded').forEach(content => {
        content.style.maxHeight = content.scrollHeight + 'px';
    });
});

// Handle orientation change on mobile
window.addEventListener('orientationchange', function() {
    // Close mobile nav on orientation change
    closeMobileNav();
    
    setTimeout(() => {
        // Recalculate layouts after orientation change
        document.querySelectorAll('.channel-content.expanded').forEach(content => {
            content.style.maxHeight = content.scrollHeight + 'px';
        });
        
        // Adjust viewport height for mobile browsers
        document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
    }, 100);
});

// Set initial viewport height
document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);

// Add touch event support for better mobile interaction
function addTouchSupport() {
    // Add touch class to body for CSS targeting
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        document.body.classList.add('touch-device');
    }
    
    // Prevent double-tap zoom on buttons
    document.querySelectorAll('button, .nav-link, .channel-header').forEach(element => {
        element.addEventListener('touchend', function(e) {
            e.preventDefault();
            // Trigger click after preventing default
            setTimeout(() => {
                if (this.click) {
                    this.click();
                }
            }, 10);
        });
    });
}

// Add CSS animations for pulse effect
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);