/**
 * Ant Media Stream Access - Admin JavaScript
 * Version: 2.0.0
 * Handles admin interface interactions, real-time updates, and testing
 */

jQuery(document).ready(function($) {
    'use strict';

    // Admin controller object
    const AMSAAdmin = {
        config: window.amsaAdmin || {},
        timers: {
            realtime: null,
            heartbeat: null
        },
        cache: {
            analytics: null,
            lastUpdate: 0
        },
        ui: {
            loading: false,
            activeTab: 'overview'
        }
    };

    /**
     * Initialize admin interface
     */
    function initAdmin() {
        console.log('AMSA Admin initialized');
        
        // Initialize components
        initTabs();
        initRealtimeUpdates();
        initTestButtons();
        initFormValidation();
        initExportButtons();
        initLicenseActivation();
        
        // Start periodic updates
        startRealtimeUpdates();
    }

    /**
     * Initialize tab navigation
     */
    function initTabs() {
        $('.amsa-nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).data('tab');
            
            // Update active tab
            $('.amsa-nav-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show/hide content
            $('.amsa-tab-content').removeClass('active');
            $('#amsa-tab-' + targetTab).addClass('active');
            
            AMSAAdmin.ui.activeTab = targetTab;
            
            // Load tab-specific content
            loadTabContent(targetTab);
        });
    }

    /**
     * Load content for specific tab
     */
    function loadTabContent(tab) {
        switch (tab) {
            case 'analytics':
                loadAnalyticsDashboard();
                break;
            case 'realtime':
                loadRealtimeData();
                break;
            case 'streams':
                loadStreamData();
                break;
        }
    }

    /**
     * Initialize real-time updates
     */
    function initRealtimeUpdates() {
        // Check if real-time updates are enabled
        if ($('.amsa-realtime-indicator').length === 0) {
            return;
        }
        
        console.log('Real-time updates enabled');
    }

    /**
     * Start real-time updates
     */
    function startRealtimeUpdates() {
        // Update every 30 seconds
        AMSAAdmin.timers.realtime = setInterval(function() {
            if (AMSAAdmin.ui.activeTab === 'realtime' || AMSAAdmin.ui.activeTab === 'analytics') {
                loadRealtimeData();
            }
        }, 30000);
        
        // Initial load
        setTimeout(loadRealtimeData, 1000);
    }

    /**
     * Load real-time analytics data
     */
    function loadRealtimeData() {
        if (AMSAAdmin.ui.loading) {
            return; // Prevent multiple simultaneous requests
        }
        
        AMSAAdmin.ui.loading = true;
        
        $.ajax({
            url: AMSAAdmin.config.ajaxurl,
            type: 'POST',
            data: {
                action: 'amsa_get_realtime_analytics',
                nonce: AMSAAdmin.config.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateRealtimeDisplay(response.data);
                    AMSAAdmin.cache.analytics = response.data;
                    AMSAAdmin.cache.lastUpdate = Date.now();
                } else {
                    console.warn('Failed to load real-time data:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Real-time data request failed:', error);
            },
            complete: function() {
                AMSAAdmin.ui.loading = false;
                updateLastUpdated();
            }
        });
    }

    /**
     * Update real-time display
     */
    function updateRealtimeDisplay(data) {
        // Update live viewer count
        $('.amsa-live-viewers .amsa-metric-value').text(data.current_live_viewers || 0);
        
        // Update active streams
        if (data.active_streams && data.active_streams.length > 0) {
            updateActiveStreams(data.active_streams);
        }
        
        // Update recent events
        if (data.recent_events && data.recent_events.length > 0) {
            updateRecentEvents(data.recent_events);
        }
        
        console.log('Real-time data updated:', data);
    }

    /**
     * Update active streams display
     */
    function updateActiveStreams(streams) {
        const container = $('#amsa-active-streams');
        if (container.length === 0) return;
        
        let html = '<h4>Active Streams</h4>';
        
        if (streams.length === 0) {
            html += '<p>No active streams</p>';
        } else {
            html += '<div class="amsa-streams-grid">';
            streams.forEach(function(stream) {
                html += `
                    <div class="amsa-stream-card">
                        <div class="stream-id">${escapeHtml(stream.stream_id)}</div>
                        <div class="viewer-count">${stream.current_viewers} viewers</div>
                        <div class="status-indicator live">LIVE</div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        container.html(html);
    }

    /**
     * Update recent events display
     */
    function updateRecentEvents(events) {
        const container = $('#amsa-recent-events');
        if (container.length === 0) return;
        
        let html = '<h4>Recent Events</h4><div class="amsa-events-list">';
        
        events.forEach(function(event) {
            const timeAgo = getTimeAgo(event.timestamp);
            const eventClass = getEventClass(event.event_type);
            
            html += `
                <div class="amsa-event-item ${eventClass}">
                    <div class="event-icon">${getEventIcon(event.event_type)}</div>
                    <div class="event-details">
                        <div class="event-type">${escapeHtml(event.event_type)}</div>
                        <div class="event-meta">
                            ${escapeHtml(event.stream_id)} • ${escapeHtml(event.tier || 'Unknown')} • ${timeAgo}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.html(html);
    }

    /**
     * Initialize test buttons
     */
    function initTestButtons() {
        // Test token generation
        $('#amsa-test-token').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('Testing...');
            
            // This form submit will be handled by PHP
            setTimeout(function() {
                button.prop('disabled', false).text(originalText);
            }, 3000);
        });
        
        // Test connection
        $('#amsa-test-connection').on('click', function(e) {
            e.preventDefault();
            testConnection();
        });
        
        // Clear logs
        $('#amsa-clear-logs').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to clear all logs?')) {
                // This will be handled by the form submission
                $(this).closest('form').submit();
            }
        });
    }

    /**
     * Test server connection
     */
    function testConnection() {
        const button = $('#amsa-test-connection');
        const originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: AMSAAdmin.config.ajaxurl,
            type: 'POST',
            data: {
                action: 'amsa_test_connection',
                nonce: AMSAAdmin.config.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Connection test successful!', 'success');
                } else {
                    showNotice('Connection test failed: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotice('Connection test failed: Network error', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Real-time validation for server URL
        $('input[name="ant_media_server_url"]').on('blur', function() {
            const url = $(this).val();
            if (url && !isValidUrl(url)) {
                showFieldError($(this), 'Please enter a valid URL');
            } else {
                clearFieldError($(this));
            }
        });
        
        // Validation for JWT secret
        $('input[name="ant_media_jwt_secret"]').on('input', function() {
            const secret = $(this).val();
            if (secret.length > 0 && secret.length < 16) {
                showFieldError($(this), 'JWT secret should be at least 16 characters long');
            } else {
                clearFieldError($(this));
            }
        });
        
        // JSON validation for streams config
        $('textarea[name="ant_media_streams_config"]').on('blur', function() {
            const json = $(this).val();
            try {
                JSON.parse(json);
                clearFieldError($(this));
            } catch (e) {
                showFieldError($(this), 'Invalid JSON format');
            }
        });
    }

    /**
     * Initialize export buttons
     */
    function initExportButtons() {
        $('.amsa-export-btn').on('click', function(e) {
            e.preventDefault();
            
            const type = $(this).data('type') || 'overview';
            const days = $(this).data('days') || 30;
            
            const url = AMSAAdmin.config.ajaxurl + 
                       '?action=amsa_export_analytics' +
                       '&type=' + encodeURIComponent(type) +
                       '&days=' + encodeURIComponent(days) +
                       '&nonce=' + encodeURIComponent(AMSAAdmin.config.nonce);
            
            // Create temporary download link
            const link = document.createElement('a');
            link.href = url;
            link.download = `amsa-analytics-${type}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotice('Export started. Download should begin shortly.', 'info');
        });
    }

    /**
     * Initialize license activation
     */
    function initLicenseActivation() {
        $('#amsa-activate-license').on('click', function(e) {
            e.preventDefault();
            
            const licenseKey = $('#amsa-license-key').val().trim();
            if (!licenseKey) {
                showNotice('Please enter a license key', 'error');
                return;
            }
            
            const button = $(this);
            const originalText = button.text();
            
            button.prop('disabled', true).text('Activating...');
            
            $.ajax({
                url: AMSAAdmin.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'amsa_activate_license',
                    license_key: licenseKey,
                    nonce: AMSAAdmin.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('License activated successfully!', 'success');
                        updateLicenseStatus(response.data);
                    } else {
                        showNotice('License activation failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    showNotice('License activation failed: Network error', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Load analytics dashboard
     */
    function loadAnalyticsDashboard() {
        // This would load comprehensive analytics data
        console.log('Loading analytics dashboard...');
        
        // For now, trigger real-time updates
        loadRealtimeData();
    }

    /**
     * Load stream data
     */
    function loadStreamData() {
        console.log('Loading stream data...');
        // Implementation would load detailed stream analytics
    }

    /**
     * Update license status display
     */
    function updateLicenseStatus(statusData) {
        const container = $('#amsa-license-status');
        if (container.length === 0) return;
        
        let statusClass = 'info';
        if (statusData.status === 'active') statusClass = 'success';
        if (statusData.status === 'invalid') statusClass = 'error';
        
        const html = `
            <div class="amsa-status-indicator ${statusClass}">
                <div class="amsa-status-dot"></div>
                ${escapeHtml(statusData.message)}
            </div>
        `;
        
        container.html(html);
    }

    /**
     * Update last updated timestamp
     */
    function updateLastUpdated() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        
        $('.amsa-last-updated').text('Last updated: ' + timeString);
    }

    /**
     * Show field error
     */
    function showFieldError(field, message) {
        clearFieldError(field);
        
        field.addClass('error');
        field.after('<div class="amsa-field-error">' + escapeHtml(message) + '</div>');
    }

    /**
     * Clear field error
     */
    function clearFieldError(field) {
        field.removeClass('error');
        field.siblings('.amsa-field-error').remove();
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type = 'info') {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible amsa-notice ${type}">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Handle manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }

    /**
     * Utility functions
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function getTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffMs = now - time;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return diffHours + 'h ago';
        
        const diffDays = Math.floor(diffHours / 24);
        return diffDays + 'd ago';
    }

    function getEventClass(eventType) {
        const classMap = {
            'play': 'success',
            'pause': 'warning',
            'stop': 'info',
            'error': 'error',
            'session_start': 'success',
            'session_end': 'info'
        };
        
        return classMap[eventType] || 'info';
    }

    function getEventIcon(eventType) {
        const iconMap = {
            'play': '▶️',
            'pause': '⏸️',
            'stop': '⏹️',
            'error': '❌',
            'session_start': '🟢',
            'session_end': '🔴',
            'buffering': '⏳',
            'quality_change': '⚙️'
        };
        
        return iconMap[eventType] || '📊';
    }

    /**
     * Cleanup on page unload
     */
    $(window).on('beforeunload', function() {
        // Clear timers
        if (AMSAAdmin.timers.realtime) {
            clearInterval(AMSAAdmin.timers.realtime);
        }
        if (AMSAAdmin.timers.heartbeat) {
            clearInterval(AMSAAdmin.timers.heartbeat);
        }
    });

    // Initialize everything
    initAdmin();

    // Add some styling for errors
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .form-table input.error,
            .form-table textarea.error {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
            }
            .amsa-field-error {
                color: #dc3545;
                font-size: 12px;
                margin-top: 5px;
                display: block;
            }
            .amsa-streams-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
                margin: 15px 0;
            }
            .amsa-stream-card {
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #28a745;
            }
            .amsa-events-list {
                max-height: 300px;
                overflow-y: auto;
            }
            .amsa-event-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .amsa-event-item:last-child {
                border-bottom: none;
            }
            .event-icon {
                font-size: 16px;
            }
            .event-details {
                flex: 1;
            }
            .event-type {
                font-weight: 600;
                font-size: 14px;
            }
            .event-meta {
                font-size: 12px;
                color: #6c757d;
            }
            .amsa-last-updated {
                font-size: 12px;
                color: #6c757d;
                margin-left: 10px;
            }
        `)
        .appendTo('head');
}); 