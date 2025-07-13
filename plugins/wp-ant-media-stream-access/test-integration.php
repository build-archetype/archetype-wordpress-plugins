<?php
/**
 * Test Integration Demo
 * 
 * This file demonstrates the clean stream-chat integration system.
 * Access this page to see how simple the new system is.
 */

// This would normally be loaded automatically by WordPress
require_once 'includes/stream-sync.php';
require_once '../wp-rocket-chat-embed/includes/stream-integration.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Stream-Chat Integration Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .demo-container { max-width: 800px; margin: 0 auto; }
        .stream-container { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .chat-container { background: #e8f4f8; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .live { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .offline { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-live { background: #28a745; color: white; }
        .btn-offline { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="demo-container">
        <h1>Stream-Chat Integration Demo</h1>
        <p>This demonstrates the clean, simple integration between stream status and chat visibility.</p>

        <div class="stream-container">
            <h2>🎥 Stream Status</h2>
            <div id="stream-status" class="status offline">
                Stream is currently OFFLINE
            </div>
            <button class="btn-live" onclick="simulateStreamStart()">Start Stream</button>
            <button class="btn-offline" onclick="simulateStreamStop()">Stop Stream</button>
        </div>

        <div class="chat-container">
            <h2>💬 Chat</h2>
            <div id="chat-container" style="display: none;">
                <p><strong>Chat is now visible!</strong></p>
                <p>Users can now participate in live chat.</p>
                <div style="background: white; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    <em>Chat messages would appear here...</em>
                </div>
            </div>
            <div id="chat-offline" style="display: block;">
                <p><strong>💬 Chat is currently offline</strong></p>
                <p>Chat will appear when streaming begins.</p>
            </div>
        </div>

        <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 8px; color: #856404;">
            <h3>How It Works (WordPress Engineer's Approach)</h3>
            <ol>
                <li><strong>Single Source of Truth:</strong> WordPress option <code>amsa_streams_live</code></li>
                <li><strong>WordPress Hooks:</strong> Clean <code>amsa_stream_status_changed</code> action</li>
                <li><strong>Simple Detection:</strong> One API check with 30-second cache</li>
                <li><strong>Real-time Updates:</strong> WordPress Heartbeat (5-second intervals)</li>
                <li><strong>Clean Communication:</strong> Custom events for JavaScript</li>
            </ol>
        </div>
    </div>

    <script>
        // Register a chat container (simulating the real system)
        window.rocketChatContainers = [{
            show: function() {
                document.getElementById('chat-container').style.display = 'block';
                document.getElementById('chat-offline').style.display = 'none';
                console.log('Chat SHOWN');
            },
            hide: function() {
                document.getElementById('chat-container').style.display = 'none';
                document.getElementById('chat-offline').style.display = 'block';
                console.log('Chat HIDDEN');
            }
        }];

        // Listen for stream status changes (simulating the real system)
        document.addEventListener('amsaStreamStatusChanged', function(event) {
            const isLive = event.detail.isLive;
            const statusEl = document.getElementById('stream-status');
            
            if (isLive) {
                statusEl.textContent = 'Stream is LIVE';
                statusEl.className = 'status live';
            } else {
                statusEl.textContent = 'Stream is OFFLINE';
                statusEl.className = 'status offline';
            }
        });

        // Simulate stream status changes
        function simulateStreamStart() {
            console.log('🚀 Simulating stream start...');
            document.dispatchEvent(new CustomEvent('amsaStreamStatusChanged', {
                detail: { isLive: true }
            }));
        }

        function simulateStreamStop() {
            console.log('🛑 Simulating stream stop...');
            document.dispatchEvent(new CustomEvent('amsaStreamStatusChanged', {
                detail: { isLive: false }
            }));
        }

        console.log('Demo loaded. Try the Start/Stop Stream buttons!');
    </script>
</body>
</html> 