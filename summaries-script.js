// Summaries Page Specific JavaScript

// DOM Elements
const datePresetButtons = document.querySelectorAll('.date-preset');
const customDateInputs = document.querySelector('.custom-date-inputs');
const startDateInput = document.getElementById('startDate');
const endDateInput = document.getElementById('endDate');
const channelFilter = document.getElementById('channelFilter');
const typeFilter = document.getElementById('typeFilter');
const generateReportBtn = document.querySelector('.generate-report-btn');
const timelineViews = document.querySelectorAll('.timeline-view');
const viewButtons = document.querySelectorAll('.view-btn');
const summariesGrid = document.getElementById('summariesGrid');

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Authentication is handled server-side in PHP version
    
    // Set default date range
    setDefaultDates();
    
    // Initialize filters
    setupEventListeners();
    
    // Load initial data
    loadSummaryData();
    
    // Start real-time updates
    startRealTimeUpdates();
});

// Date Range Handling
function handleDatePresetChange(event) {
    const range = event.target.getAttribute('data-range');
    
    // Update active state
    datePresetButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Show/hide custom date inputs
    if (range === 'custom') {
        customDateInputs.style.display = 'flex';
    } else {
        customDateInputs.style.display = 'none';
        setDateRange(range);
    }
    
    // Reload data with new date range
    loadSummaryData();
}

function setDateRange(range) {
    const today = new Date();
    let startDate = new Date();
    let endDate = new Date();
    
    switch (range) {
        case 'today':
            startDate = new Date(today);
            endDate = new Date(today);
            break;
        case 'week':
            startDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            endDate = new Date(today);
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today);
            break;
    }
    
    startDateInput.value = formatDateForInput(startDate);
    endDateInput.value = formatDateForInput(endDate);
}

function setDefaultDates() {
    const today = new Date();
    startDateInput.value = formatDateForInput(today);
    endDateInput.value = formatDateForInput(today);
}

function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

// Filter Handling
function handleFilterChange() {
    const channel = channelFilter.value;
    const type = typeFilter.value;
    
    // Apply filters to summary cards
    filterSummaryCards(channel, type);
    
    // Update statistics
    updateStatistics(channel, type);
    
    // Update timeline
    updateTimeline(channel, type);
    
    showToast(`Filters applied: ${channel === 'all' ? 'All Channels' : channel}, ${type === 'all' ? 'All Types' : type}`, 'info');
}

function filterSummaryCards(channel, type) {
    const summaryCards = document.querySelectorAll('.summary-card');
    
    summaryCards.forEach(card => {
        const cardChannel = card.querySelector('.summary-channel span').textContent.toLowerCase();
        let shouldShow = true;
        
        if (channel !== 'all') {
            shouldShow = cardChannel.includes(channel.toLowerCase());
        }
        
        if (shouldShow) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.3s ease';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateStatistics(channel, type) {
    // Simulate statistics update based on filters
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach((statNumber, index) => {
        const currentValue = parseInt(statNumber.textContent.replace(',', ''));
        let newValue = currentValue;
        
        // Apply filter-based modifications (simulation)
        if (channel !== 'all' || type !== 'all') {
            newValue = Math.floor(currentValue * (0.3 + Math.random() * 0.7));
        }
        
        animateNumber(statNumber, newValue);
    });
}

function animateNumber(element, targetValue) {
    const startValue = parseInt(element.textContent.replace(',', ''));
    const duration = 1000;
    const startTime = performance.now();
    
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);
        element.textContent = currentValue.toLocaleString();
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    }
    
    requestAnimationFrame(animate);
}

function updateTimeline(channel, type) {
    const timelineItems = document.querySelectorAll('.timeline-item');
    
    timelineItems.forEach(item => {
        const itemChannel = item.querySelector('.timeline-channel').textContent;
        const itemBadges = item.querySelectorAll('.timeline-badge');
        let shouldShow = true;
        
        if (channel !== 'all') {
            shouldShow = itemChannel.toLowerCase().includes(channel.toLowerCase());
        }
        
        if (shouldShow && type !== 'all') {
            const hasMatchingType = Array.from(itemBadges).some(badge => 
                badge.classList.contains(type) || badge.textContent.toLowerCase().includes(type)
            );
            shouldShow = hasMatchingType || type === 'all';
        }
        
        if (shouldShow) {
            item.style.display = 'flex';
            item.style.animation = 'slideInLeft 0.3s ease';
        } else {
            item.style.display = 'none';
        }
    });
}

// Timeline View Handling
function handleTimelineViewChange(event) {
    const view = event.target.getAttribute('data-view');
    
    // Update active state
    timelineViews.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update timeline display
    updateTimelineView(view);
    
    showToast(`Timeline view changed to: ${view === 'day' ? 'Day View' : 'Week View'}`, 'info');
}

function updateTimelineView(view) {
    const timeline = document.querySelector('.timeline');
    const timelineItems = document.querySelectorAll('.timeline-item');
    
    if (view === 'week') {
        // Group items by day for week view
        timeline.classList.add('week-view');
        
        // Add day headers (simulation)
        addDayHeaders();
    } else {
        timeline.classList.remove('week-view');
        removeDayHeaders();
    }
}

function addDayHeaders() {
    const timeline = document.querySelector('.timeline');
    const days = ['Today', 'Yesterday', 'Tuesday', 'Monday', 'Sunday'];
    
    // Clear existing day headers
    removeDayHeaders();
    
    days.forEach((day, index) => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'timeline-day-header';
        dayHeader.innerHTML = `
            <div class="day-header-content">
                <h4>${day}</h4>
                <span class="day-message-count">${Math.floor(Math.random() * 50) + 10} messages</span>
            </div>
        `;
        
        // Insert day header before appropriate timeline items
        const itemIndex = index * 2; // Rough distribution
        if (timeline.children[itemIndex]) {
            timeline.insertBefore(dayHeader, timeline.children[itemIndex]);
        }
    });
}

function removeDayHeaders() {
    document.querySelectorAll('.timeline-day-header').forEach(header => {
        header.remove();
    });
}

// View Toggle Handling
function handleViewToggle(event) {
    const view = event.target.getAttribute('data-view');
    
    // Update active state
    viewButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update grid layout
    if (view === 'list') {
        summariesGrid.classList.add('list-view');
    } else {
        summariesGrid.classList.remove('list-view');
    }
    
    showToast(`View changed to: ${view === 'list' ? 'List View' : 'Card View'}`, 'info');
}

// Report Generation
async function generateReport() {
    const btn = generateReportBtn;
    const originalText = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Generating...</span>';
    btn.disabled = true;
    
    try {
        // Simulate report generation
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Create and download mock report
        const reportData = generateReportData();
        downloadReport(reportData);
        
        showToast('Report generated and downloaded successfully!', 'success');
        
    } catch (error) {
        showToast('Error generating report. Please try again.', 'error');
        console.error('Report generation error:', error);
    } finally {
        // Reset button state
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function generateReportData() {
    const startDate = startDateInput.value;
    const endDate = endDateInput.value;
    const channel = channelFilter.value;
    const type = typeFilter.value;
    
    return {
        title: 'Teams Activity Summary Report',
        dateRange: `${startDate} to ${endDate}`,
        filters: {
            channel: channel === 'all' ? 'All Channels' : channel,
            type: type === 'all' ? 'All Types' : type
        },
        statistics: {
            totalMessages: Math.floor(Math.random() * 1000) + 500,
            urgentMessages: Math.floor(Math.random() * 50) + 10,
            mentions: Math.floor(Math.random() * 100) + 20,
            filesShared: Math.floor(Math.random() * 200) + 50
        },
        topContributors: [
            { name: 'Sarah Johnson', messages: 45 },
            { name: 'Mike Chen', messages: 38 },
            { name: 'Lisa Wang', messages: 32 },
            { name: 'Alex Rodriguez', messages: 28 }
        ],
        keyHighlights: [
            'Project deadline moved to Friday - requires immediate action',
            'New client onboarding process approved',
            'Q4 planning meeting scheduled for next week',
            'Security policy updates require team acknowledgment'
        ]
    };
}

function downloadReport(data) {
    // Create CSV content
    let csvContent = 'Teams Activity Summary Report\n\n';
    csvContent += `Date Range: ${data.dateRange}\n`;
    csvContent += `Channel Filter: ${data.filters.channel}\n`;
    csvContent += `Type Filter: ${data.filters.type}\n\n`;
    
    csvContent += 'STATISTICS\n';
    csvContent += `Total Messages,${data.statistics.totalMessages}\n`;
    csvContent += `Urgent Messages,${data.statistics.urgentMessages}\n`;
    csvContent += `Mentions,${data.statistics.mentions}\n`;
    csvContent += `Files Shared,${data.statistics.filesShared}\n\n`;
    
    csvContent += 'TOP CONTRIBUTORS\n';
    csvContent += 'Name,Messages\n';
    data.topContributors.forEach(contributor => {
        csvContent += `${contributor.name},${contributor.messages}\n`;
    });
    
    csvContent += '\nKEY HIGHLIGHTS\n';
    data.keyHighlights.forEach((highlight, index) => {
        csvContent += `${index + 1}. ${highlight}\n`;
    });
    
    // Create and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `teams-activity-report-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

// Summary Card Actions
function handleViewDetails(event) {
    const card = event.target.closest('.summary-card');
    const channelName = card.querySelector('.summary-channel span').textContent;
    
    showToast(`Opening detailed view for ${channelName}...`, 'info');
    
    // In a real app, this would open a detailed modal or navigate to a detail page
    setTimeout(() => {
        showDetailModal(channelName);
    }, 500);
}

function showDetailModal(channelName) {
    // Create modal overlay
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'modal-overlay';
    modalOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    `;
    
    // Create modal content
    const modal = document.createElement('div');
    modal.className = 'detail-modal';
    modal.style.cssText = `
        background: white;
        border-radius: 16px;
        padding: 32px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    `;
    
    modal.innerHTML = `
        <div class="modal-header">
            <h3>${channelName} - Detailed Summary</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-content">
            <p>This would show detailed information about the ${channelName} channel activity, including:</p>
            <ul>
                <li>Complete message timeline</li>
                <li>File attachments and links</li>
                <li>Participant analysis</li>
                <li>Response times and engagement metrics</li>
                <li>Topic analysis and keyword extraction</li>
            </ul>
            <p><em>This is a demo modal. In a real application, this would contain actual detailed data.</em></p>
        </div>
    `;
    
    modalOverlay.appendChild(modal);
    document.body.appendChild(modalOverlay);
    
    // Close modal on overlay click
    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) {
            modalOverlay.remove();
        }
    });
}

// Data Loading Simulation
async function loadSummaryData() {
    // Show loading state
    showLoadingState();
    
    try {
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Update UI with new data
        updateSummaryCards();
        updateTimeline();
        
        hideLoadingState();
        
    } catch (error) {
        showToast('Error loading summary data. Please refresh the page.', 'error');
        console.error('Data loading error:', error);
    }
}

function showLoadingState() {
    // Add loading class to summary cards
    document.querySelectorAll('.summary-card').forEach(card => {
        card.classList.add('loading');
    });
    
    // Show loading spinner on statistics
    document.querySelectorAll('.stat-number').forEach(stat => {
        stat.style.opacity = '0.5';
    });
}

function hideLoadingState() {
    document.querySelectorAll('.summary-card').forEach(card => {
        card.classList.remove('loading');
    });
    
    document.querySelectorAll('.stat-number').forEach(stat => {
        stat.style.opacity = '1';
    });
}

function updateSummaryCards() {
    // Simulate updating summary cards with new data
    document.querySelectorAll('.metric-value').forEach(metric => {
        const newValue = Math.floor(Math.random() * 100) + 10;
        animateNumber(metric, newValue);
    });
}

// Real-time Updates
function startRealTimeUpdates() {
    setInterval(() => {
        // Update notification badge
        const notificationBadge = document.querySelector('.notification-badge');
        if (notificationBadge && Math.random() > 0.8) {
            const currentCount = parseInt(notificationBadge.textContent);
            notificationBadge.textContent = currentCount + 1;
            notificationBadge.style.animation = 'pulse 0.5s ease';
            setTimeout(() => {
                notificationBadge.style.animation = '';
            }, 500);
        }
        
        // Occasionally add new timeline items
        if (Math.random() > 0.9) {
            addNewTimelineItem();
        }
    }, 30000); // Every 30 seconds
}

function addNewTimelineItem() {
    const timeline = document.querySelector('.timeline');
    const newItem = document.createElement('div');
    newItem.className = 'timeline-item normal';
    
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
    
    newItem.innerHTML = `
        <div class="timeline-time">${timeString}</div>
        <div class="timeline-content">
            <div class="timeline-header">
                <span class="timeline-channel">#general</span>
            </div>
            <p class="timeline-message">New activity detected in the channel.</p>
            <div class="timeline-meta">
                <span class="timeline-author">System</span>
                <span class="timeline-reactions">1 reaction</span>
            </div>
        </div>
    `;
    
    // Insert at the beginning
    timeline.insertBefore(newItem, timeline.firstChild);
    
    // Add animation
    newItem.style.opacity = '0';
    newItem.style.transform = 'translateY(-10px)';
    setTimeout(() => {
        newItem.style.transition = 'all 0.3s ease';
        newItem.style.opacity = '1';
        newItem.style.transform = 'translateY(0)';
    }, 100);
}

// Event Listeners Setup
function setupEventListeners() {
    // Date presets
    datePresetButtons.forEach(button => {
        button.addEventListener('click', handleDatePresetChange);
    });
    
    // Custom date inputs
    startDateInput?.addEventListener('change', loadSummaryData);
    endDateInput?.addEventListener('change', loadSummaryData);
    
    // Filters
    channelFilter?.addEventListener('change', handleFilterChange);
    typeFilter?.addEventListener('change', handleFilterChange);
    
    // Timeline views
    timelineViews.forEach(button => {
        button.addEventListener('click', handleTimelineViewChange);
    });
    
    // View toggle
    viewButtons.forEach(button => {
        button.addEventListener('click', handleViewToggle);
    });
    
    // Generate report
    generateReportBtn?.addEventListener('click', generateReport);
    
    // Summary card actions
    document.querySelectorAll('.action-btn.primary').forEach(button => {
        button.addEventListener('click', handleViewDetails);
    });
    
    document.querySelectorAll('.action-btn.secondary').forEach(button => {
        button.addEventListener('click', (e) => {
            const card = e.target.closest('.summary-card');
            const channelName = card.querySelector('.summary-channel span').textContent;
            showToast(`Exporting ${channelName} summary...`, 'info');
        });
    });
}

// Add additional CSS for animations
const additionalStyles = document.createElement('style');
additionalStyles.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .timeline-day-header {
        padding: 16px 0;
        border-bottom: 2px solid #e2e8f0;
        margin: 16px 0;
    }
    
    .day-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .day-header-content h4 {
        font-size: 18px;
        font-weight: 700;
        color: #374151;
    }
    
    .day-message-count {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }
    
    .modal-overlay {
        animation: fadeIn 0.3s ease;
    }
    
    .detail-modal {
        animation: slideInUp 0.3s ease;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 20px;
        color: #64748b;
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    
    .modal-close:hover {
        background: #f1f5f9;
        color: #374151;
    }
    
    @keyframes slideInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(additionalStyles);