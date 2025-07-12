    // Add real-time integration script if stream checking is enabled
    if ($check_stream_status && function_exists('should_display_ant_media_stream')) {
        $container_id = 'rce-container-' . substr($iframe_id, -8);
        $iframe_html .= sprintf('
        <script>
        (function() {
            let checkInterval;
            let containerEl = document.querySelector("#rce-container-%s");
            let offlineMessageShown = false;
            
            function checkStreamStatus() {
                // Simple check for any live streams
                fetch(ajaxurl, {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=check_any_streams_live&nonce=" + encodeURIComponent("%s")
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.any_live) {
                        // Show chat
                        if (containerEl && containerEl.style.display === "none") {
                            containerEl.style.display = "block";
                            console.log("💬 Chat shown - streams are live");
                        }
                        offlineMessageShown = false;
                    } else {
                        // Hide chat and show offline message
                        if (containerEl && containerEl.style.display !== "none") {
                            containerEl.style.display = "none";
                            if (!offlineMessageShown) {
                                console.log("💬 Chat hidden - no streams live");
                                offlineMessageShown = true;
                            }
                        }
                    }
                })
                .catch(err => console.log("Stream status check failed:", err));
            }
            
            // Listen for immediate stream status changes
            window.addEventListener("antMediaStreamStatusChange", function(event) {
                const { streamId, status, error } = event.detail;
                console.log("💬 Chat received immediate stream status:", streamId, status);
                
                if (status === "failed" || status === "stopped") {
                    // Immediately hide chat when stream fails
                    if (containerEl && containerEl.style.display !== "none") {
                        containerEl.style.display = "none";
                        console.log("💬 Chat hidden immediately - stream failed:", streamId);
                        offlineMessageShown = true;
                    }
                } else if (status === "playing") {
                    // Show chat when stream starts playing
                    if (containerEl && containerEl.style.display === "none") {
                        containerEl.style.display = "block";
                        console.log("💬 Chat shown immediately - stream playing:", streamId);
                        offlineMessageShown = false;
                    }
                }
            });
            
            // Check every 10 seconds (fallback for cases where immediate detection fails)
            checkInterval = setInterval(checkStreamStatus, 10000);
            
            // Initial check after 2 seconds
            setTimeout(checkStreamStatus, 2000);
        })();
        </script>',
        substr($iframe_id, -8),
        wp_create_nonce('ant_media_nonce')
        );
    } 