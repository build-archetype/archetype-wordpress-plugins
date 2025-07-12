// Ant Media Stream Plugin JavaScript
// Simplified - relies on WordPress backend API system
console.log('Ant Media Stream Plugin loaded'); 

/**
 * Simplified Ant Media Stream JavaScript Handler
 * Relies on WordPress backend API system instead of complex frontend detection
 */

(function() {
    'use strict';
    
    // Manual cache reset function (for debugging)
    window.clearStreamCache = function() {
        console.warn('🧹 MANUALLY clearing stream cache...');
        
        if (typeof antMediaAjax === 'undefined') {
            console.error('❌ antMediaAjax not available. Make sure you\'re on a page with stream shortcodes.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'amsa_reset_stream_cache');
        formData.append('nonce', antMediaAjax.nonce);
        
        fetch(antMediaAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.warn('✅ Stream cache reset successfully:', data);
            
            // Force update chat visibility based on fresh data
            if (data.success && typeof window.updateChatVisibility === 'function') {
                window.updateChatVisibility(data.data.any_live);
            }
            
            // Reload the page to reflect changes
            if (confirm('Cache cleared! Reload page to see changes?')) {
                window.location.reload();
            }
        })
        .catch(err => {
            console.error('❌ Failed to reset stream cache:', err);
        });
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎥 Ant Media Stream JavaScript initialized - using WordPress backend API for stream detection');
        
        // Add visible debug button if debug=1 in URL
        if (window.location.search.includes('debug=1')) {
            const resetButton = document.createElement('button');
            resetButton.textContent = '🧹 Reset Stream Cache';
            resetButton.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 99999; background: #ff6b35; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-size: 14px;';
            resetButton.onclick = window.clearStreamCache;
            document.body.appendChild(resetButton);
            console.log('🔧 Debug mode: Added cache reset button');
        }
        
        // Log that we're using the backend approach
        console.log('🎯 Stream status detection: WordPress Heartbeat (5-second) + Backend API (/rest/v2/broadcasts/)');
        console.log('✅ No complex JavaScript detection needed - backend is more reliable!');
    });
    
})(); 