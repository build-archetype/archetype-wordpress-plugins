<?php
/**
 * Stream Status Test Script
 * 
 * This script tests our clean stream detection system
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');

// Test the API call directly
function test_stream_status($stream_id = 'asx6N6h2KfK0jmE42665303919153337') {
    $server_url = 'https://stream.triplepointtrading.net';
    $app_name = 'TriplePointTradingStreaming';
    
    $api_url = rtrim($server_url, '/') . '/' . $app_name . '/rest/v2/broadcasts/' . $stream_id;
    
    echo "🌐 Testing API: {$api_url}\n";
    
    $response = file_get_contents($api_url);
    if ($response === false) {
        echo "❌ API call failed\n";
        return false;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['status'])) {
        echo "❌ Invalid response\n";
        return false;
    }
    
    $status = $data['status'];
    echo "📊 Stream status: '{$status}'\n";
    
    // Our clean logic
    $live_statuses = ['broadcasting', 'live', 'playing', 'active', 'started', 'publish_started', 'stream_started', 'online', 'ready', 'created', 'publishing'];
    $is_live = in_array(strtolower($status), $live_statuses);
    
    echo "✅ Result: " . ($is_live ? 'LIVE' : 'OFFLINE') . "\n";
    
    // Show some metrics for reference (but don't use them for decision)
    if (isset($data['bitrate'])) echo "📈 Bitrate: {$data['bitrate']} (for info only)\n";
    if (isset($data['speed'])) echo "⚡ Speed: {$data['speed']} (for info only)\n";
    if (isset($data['hlsViewerCount'])) echo "👥 HLS Viewers: {$data['hlsViewerCount']} (for info only)\n";
    
    return $is_live;
}

echo "🧪 TESTING STREAM STATUS DETECTION\n";
echo "=====================================\n";

$result = test_stream_status();

echo "\n🎯 EXPECTED RESULT: OFFLINE (since status is 'finished')\n";
echo "🎯 ACTUAL RESULT: " . ($result ? 'LIVE' : 'OFFLINE') . "\n";

if (!$result) {
    echo "✅ SUCCESS: Stream correctly detected as OFFLINE\n";
    echo "💬 Chat should now be hidden instead of showing loading\n";
} else {
    echo "❌ PROBLEM: Stream incorrectly detected as LIVE\n";
    echo "🔧 Need to check the detection logic\n";
} 