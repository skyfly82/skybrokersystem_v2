/**
 * Sky Broker Admin Dashboard - Advanced JavaScript Controller
 * Handles real-time updates, interactive components, and modern UI features
 */

class SkyBrokerDashboard {
    constructor() {
        this.charts = new Map();
        this.realTimeEnabled = false;
        this.updateInterval = null;
        this.websocket = null;
        this.config = {
            updateFrequency: 30000, // 30 seconds
            animationDuration: 350,
            chartColors: {
                primary: '#2F7DFF',
                success: '#10B981',
                warning: '#F59E0B',
                error: '#EF4444',
                info: '#2F7DFF'
            }
        };

        this.init();
    }

    /**
     * Initialize the dashboard
     */
    init() {
        console.log('🚀 Sky Broker Admin Dashboard initializing...');

        this.setupEventListeners();
        this.initializeCharts();
        this.initializeComponents();
        this.startPerformanceMonitoring();

        // Add loading completion animation
        setTimeout(() => {
            this.animateCardEntrance();
            this.animateCounters();
        }, 500);

        console.log('✅ Dashboard initialization complete');
    }

    /**
     * Setup global event listeners
     */
    setupEventListeners() {
        // Real-time toggle
        window.toggleRealTime = () => {
            this.toggleRealTimeUpdates();
        };

        // Dashboard period updates
        window.updateDashboard = (period) => {
            this.updateDashboardPeriod(period);
        };

        // Chart updates
        window.updateChart = (type, period) => {
            this.updateChart(type, period);
        };

        // Export functionality
        window.exportReport = () => {
            this.exportDashboardReport();
        };

        // Activity loading
        window.loadMoreActivity = () => {
            this.loadMoreActivity();
        };

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });

        // Window resize handling
        window.addEventListener('resize', () => {
            this.handleResize();
        });

        // Visibility change handling
        document.addEventListener('visibilitychange', () => {
            this.handleVisibilityChange();
        });
    }

    /**
     * Initialize all charts
     */
    initializeCharts() {
        this.createMainChart();
        this.createMiniCharts();
    }

    /**
     * Create the main dashboard chart
     */
    createMainChart() {
        const canvas = document.getElementById('main-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Generate sample data
        const labels = this.generateDateLabels(30);
        const revenueData = this.generateTrendData(30, 1500, 3500, 0.1);
        const ordersData = this.generateTrendData(30, 15, 65, 0.15);

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue (PLN)',
                        data: revenueData,
                        borderColor: this.config.chartColors.primary,
                        backgroundColor: this.hexToRgba(this.config.chartColors.primary, 0.1),
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: this.config.chartColors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                        pointRadius: 0,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Orders',
                        data: ordersData,
                        borderColor: this.config.chartColors.success,
                        backgroundColor: this.hexToRgba(this.config.chartColors.success, 0.1),
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: this.config.chartColors.success,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 3,
                        pointRadius: 0,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3,
                        yAxisID: 'orders'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {\n                    mode: 'index',\n                    intersect: false,\n                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',\n                        titleColor: '#fff',\n                        bodyColor: '#fff',\n                        borderColor: this.hexToRgba(this.config.chartColors.primary, 0.3),\n                        borderWidth: 1,\n                        cornerRadius: 12,\n                        displayColors: true,\n                        padding: 12,\n                        titleFont: {\n                            family: 'Be Vietnam Pro',\n                            size: 14,\n                            weight: '600'\n                        },\n                        bodyFont: {\n                            family: 'Be Vietnam Pro',\n                            size: 13\n                        },
                        callbacks: {
                            title: function(tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 0) {
                                    label += new Intl.NumberFormat('pl-PL', {
                                        style: 'currency',
                                        currency: 'PLN'
                                    }).format(context.parsed.y);
                                } else {
                                    label += context.parsed.y + ' orders';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Be Vietnam Pro',
                                size: 12
                            },
                            color: '#6B7280'
                        }
                    },
                    y: {
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (PLN)',
                            font: {
                                family: 'Be Vietnam Pro',
                                weight: '600',
                                size: 13
                            },
                            color: '#374151'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('pl-PL').format(value) + ' PLN';
                            },
                            font: {
                                family: 'Be Vietnam Pro',
                                size: 11
                            },
                            color: '#6B7280'
                        }
                    },
                    orders: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders',
                            font: {
                                family: 'Be Vietnam Pro',
                                weight: '600',
                                size: 13
                            },
                            color: '#374151'
                        },
                        grid: {
                            drawOnChartArea: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                family: 'Be Vietnam Pro',
                                size: 11
                            },
                            color: '#6B7280'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    },
                    line: {
                        borderJoinStyle: 'round',
                        borderCapStyle: 'round'
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });

        this.charts.set('main', chart);

        // Add hover effects
        canvas.addEventListener('mousemove', (e) => {
            canvas.style.cursor = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true).length > 0 ? 'pointer' : 'default';
        });
    }

    /**
     * Create mini dashboard charts
     */
    createMiniCharts() {
        // Implementation for smaller charts in cards
        const miniChartElements = document.querySelectorAll('.mini-chart');
        miniChartElements.forEach(element => {
            this.createMiniChart(element);
        });
    }

    /**
     * Initialize interactive components
     */
    initializeComponents() {
        this.initializeNotifications();
        this.initializeModals();
        this.initializeTooltips();
        this.initializeProgressBars();
    }

    /**
     * Real-time updates management
     */
    toggleRealTimeUpdates() {
        this.realTimeEnabled = !this.realTimeEnabled;

        if (this.realTimeEnabled) {
            this.startRealTimeUpdates();
            this.showToast('Real-time updates enabled', 'success');
            this.updateRealTimeIndicators(true);
        } else {
            this.stopRealTimeUpdates();
            this.showToast('Real-time updates disabled', 'info');
            this.updateRealTimeIndicators(false);
        }
    }

    startRealTimeUpdates() {
        this.updateInterval = setInterval(() => {
            this.fetchLiveData();
        }, this.config.updateFrequency);

        // Initialize WebSocket if available
        this.initializeWebSocket();
    }

    stopRealTimeUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }

        if (this.websocket) {
            this.websocket.close();
            this.websocket = null;
        }
    }

    /**
     * WebSocket initialization for real-time data
     */
    initializeWebSocket() {
        try {
            // This would connect to your actual WebSocket server
            // this.websocket = new WebSocket('wss://your-websocket-server');
            //
            // this.websocket.onmessage = (event) => {
            //     const data = JSON.parse(event.data);
            //     this.handleRealtimeData(data);
            // };

            console.log('📡 WebSocket connection would be initialized here');
        } catch (error) {
            console.warn('WebSocket not available:', error);
        }
    }

    /**
     * Fetch live dashboard data
     */
    async fetchLiveData() {
        try {
            console.log('🔄 Fetching live dashboard data...');

            // Simulate API call
            const data = await this.simulateAPICall('/api/dashboard/live-data');

            // Update components with new data
            this.updateStatisticsCards(data.statistics);
            this.updateActivityFeed(data.activities);
            this.updateCharts(data.chartData);

        } catch (error) {
            console.error('Failed to fetch live data:', error);
            this.showToast('Failed to update live data', 'error');
        }
    }

    /**
     * Update dashboard for different time periods
     */
    async updateDashboardPeriod(period) {
        this.showLoadingState(true);

        try {
            const data = await this.simulateAPICall(`/api/dashboard/data?period=${period}`);

            // Update charts
            this.updateMainChart(data.chartData, period);

            // Update statistics
            this.updateStatisticsCards(data.statistics);

            this.showToast(`Dashboard updated for ${period}`, 'info');
        } catch (error) {
            this.showToast('Failed to update dashboard', 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    /**
     * Export dashboard report
     */
    async exportDashboardReport() {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;

        button.classList.add('loading');
        button.disabled = true;

        try {
            // Simulate export process
            await this.simulateAPICall('/api/dashboard/export', 'POST');

            // Create and download file
            this.downloadFile('dashboard-report.pdf', 'application/pdf');

            this.showToast('Report exported successfully', 'success');
        } catch (error) {
            this.showToast('Export failed', 'error');
        } finally {
            button.classList.remove('loading');
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    }

    /**
     * Animation and UI helpers
     */
    animateCardEntrance() {
        const cards = document.querySelectorAll('.stat-card, .dashboard-card, .chart-container');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';

                requestAnimationFrame(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                });
            }, index * 100);
        });
    }

    animateCounters() {
        const counters = document.querySelectorAll('.stat-value');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        });

        counters.forEach(counter => observer.observe(counter));
    }

    animateCounter(element) {
        const target = this.parseNumber(element.textContent);
        const duration = 2000;
        const steps = 60;
        const increment = target / steps;
        let current = 0;
        let stepCount = 0;

        const timer = setInterval(() => {
            current += increment;
            stepCount++;

            if (current >= target || stepCount >= steps) {
                current = target;
                clearInterval(timer);
            }

            element.textContent = this.formatNumber(current, element.dataset.format || 'number');
        }, duration / steps);
    }

    /**
     * Toast notification system
     */
    showToast(message, type = 'info', duration = 5000) {
        const toast = this.createToastElement(message, type);
        const container = document.getElementById('toast-container');

        if (container) {
            container.appendChild(toast);

            // Trigger entrance animation
            requestAnimationFrame(() => {
                toast.classList.add('scale-in');
            });

            // Auto remove
            if (duration > 0) {
                setTimeout(() => {
                    this.removeToast(toast);
                }, duration);
            }
        }
    }

    createToastElement(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    ${this.getToastIcon(type)}
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">${message}</p>
                </div>
                <button onclick="this.closest('.toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `;
        return toast;
    }

    /**
     * Performance monitoring
     */
    startPerformanceMonitoring() {
        if ('performance' in window && 'getEntriesByType' in performance) {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = Math.round(perfData.loadEventEnd - perfData.loadEventStart);

            console.log(`⚡ Dashboard loaded in ${loadTime}ms`);

            // Monitor Core Web Vitals
            this.monitorCoreWebVitals();
        }
    }

    monitorCoreWebVitals() {
        // Monitor Largest Contentful Paint (LCP)
        new PerformanceObserver((entryList) => {
            const entries = entryList.getEntries();
            const lastEntry = entries[entries.length - 1];
            console.log('LCP:', lastEntry.startTime);
        }).observe({ entryTypes: ['largest-contentful-paint'] });

        // Monitor First Input Delay (FID)
        new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                console.log('FID:', entry.processingStart - entry.startTime);
            }
        }).observe({ entryTypes: ['first-input'] });
    }

    /**
     * Utility functions
     */
    generateDateLabels(days) {
        const labels = [];
        const today = new Date();
        for (let i = days - 1; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('pl-PL', {
                month: 'short',
                day: 'numeric'
            }));
        }
        return labels;
    }

    generateTrendData(count, min, max, volatility = 0.1) {
        const data = [];
        let prev = min + (max - min) * Math.random();

        for (let i = 0; i < count; i++) {
            const change = (Math.random() - 0.5) * (max - min) * volatility;
            prev = Math.max(min, Math.min(max, prev + change));
            data.push(prev);
        }
        return data;
    }

    hexToRgba(hex, alpha = 1) {
        const result = /^#?([a-f\\d]{2})([a-f\\d]{2})([a-f\\d]{2})$/i.exec(hex);
        if (!result) return hex;

        const r = parseInt(result[1], 16);
        const g = parseInt(result[2], 16);
        const b = parseInt(result[3], 16);

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    parseNumber(str) {
        return parseFloat(str.replace(/[^\\d.-]/g, '')) || 0;
    }

    formatNumber(num, format = 'number') {
        switch (format) {
            case 'currency':
                return new Intl.NumberFormat('pl-PL', {
                    style: 'currency',
                    currency: 'PLN'
                }).format(num);
            case 'percent':
                return `${num.toFixed(1)}%`;
            case 'rating':
                return `${num.toFixed(1)}/5.0`;
            default:
                return new Intl.NumberFormat('pl-PL').format(Math.round(num));
        }
    }

    async simulateAPICall(url, method = 'GET') {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    success: true,
                    data: this.generateMockData()
                });
            }, Math.random() * 1000 + 500);
        });
    }

    generateMockData() {
        return {
            statistics: {
                total_customers: Math.floor(Math.random() * 100) + 1200,
                orders: Math.floor(Math.random() * 20) + 520,
                revenue: (Math.random() * 1000 + 2500).toFixed(2),
                shipments: Math.floor(Math.random() * 50) + 1800
            },
            chartData: {
                revenue: this.generateTrendData(30, 1000, 4000),
                orders: this.generateTrendData(30, 10, 70)
            },
            activities: []
        };
    }

    getToastIcon(type) {
        const icons = {
            success: '<svg class="w-5 h-5 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            error: '<svg class="w-5 h-5 text-error-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            warning: '<svg class="w-5 h-5 text-warning-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>',
            info: '<svg class="w-5 h-5 text-skywave-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        };
        return icons[type] || icons.info;
    }

    /**
     * Cleanup
     */
    destroy() {
        this.stopRealTimeUpdates();
        this.charts.forEach(chart => chart.destroy());
        this.charts.clear();
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.skyDashboard = new SkyBrokerDashboard();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.skyDashboard) {
        window.skyDashboard.destroy();
    }
});