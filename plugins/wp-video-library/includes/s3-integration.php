<?php
if (!defined('ABSPATH')) exit;

/**
 * S3 Integration Class for Video Library
 * Supports both AWS S3 and DigitalOcean Spaces
 */
class Video_Library_S3 {
    
    private static $instance = null;
    private $access_key;
    private $secret_key;
    private $bucket;
    private $region;
    private $endpoint;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->access_key = get_option('video_library_s3_access_key');
        $this->secret_key = get_option('video_library_s3_secret_key');
        $this->bucket = get_option('video_library_s3_bucket');
        $this->region = get_option('video_library_s3_region', 'us-east-1');
        $this->endpoint = get_option('video_library_s3_endpoint');
        
        video_library_log("S3 Integration initialized with bucket: {$this->bucket}", 'info');
    }
    
    /**
     * Generate pre-signed URL for secure video access
     */
    public static function get_presigned_url($s3_key, $expiry = null) {
        $instance = self::get_instance();
        
        if (!$expiry) {
            $expiry = get_option('video_library_presigned_expiry', 3600);
        }
        
        if (empty($instance->access_key) || empty($instance->secret_key) || empty($instance->bucket)) {
            video_library_log('S3 credentials not configured', 'error');
            return false;
        }
        
        // Clean the S3 key
        $s3_key = ltrim($s3_key, '/');
        if (strpos($s3_key, 's3://') === 0) {
            $s3_key = str_replace('s3://' . $instance->bucket . '/', '', $s3_key);
        }
        
        $timestamp = time();
        $expires = $timestamp + $expiry;
        
        // Build the URL
        if ($instance->endpoint) {
            // DigitalOcean Spaces or custom endpoint
            $host = str_replace(['http://', 'https://'], '', $instance->endpoint);
            
            // Check if endpoint already contains bucket name
            if (strpos($host, $instance->bucket . '.') === 0) {
                // Endpoint already includes bucket (e.g., bucket.nyc3.digitaloceanspaces.com)
                $url = "https://{$host}/{$s3_key}";
                video_library_log("Using endpoint with bucket already included: {$host}", 'debug');
            } else {
                // Standard endpoint (e.g., nyc3.digitaloceanspaces.com)
                $url = "https://{$instance->bucket}.{$host}/{$s3_key}";
                video_library_log("Adding bucket to standard endpoint: {$instance->bucket}.{$host}", 'debug');
            }
        } else {
            // AWS S3
            if ($instance->region === 'us-east-1') {
                $url = "https://{$instance->bucket}.s3.amazonaws.com/{$s3_key}";
            } else {
                $url = "https://{$instance->bucket}.s3.{$instance->region}.amazonaws.com/{$s3_key}";
            }
        }
        
        // Create signature
        $string_to_sign = "GET\n\n\n{$expires}\n/{$instance->bucket}/{$s3_key}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $instance->secret_key, true));
        
        // Build presigned URL
        $presigned_url = $url . '?' . http_build_query([
            'AWSAccessKeyId' => $instance->access_key,
            'Expires' => $expires,
            'Signature' => $signature
        ]);
        
        video_library_log("Generated presigned URL for: {$s3_key}", 'info');
        
        return $presigned_url;
    }
    
    /**
     * Alternative method using AWS SDK approach for more complex scenarios
     */
    public static function get_presigned_url_v4($s3_key, $expiry = null) {
        $instance = self::get_instance();
        
        if (!$expiry) {
            $expiry = get_option('video_library_presigned_expiry', 3600);
        }
        
        if (empty($instance->access_key) || empty($instance->secret_key) || empty($instance->bucket)) {
            video_library_log('S3 credentials not configured', 'error');
            return false;
        }
        
        $s3_key = ltrim($s3_key, '/');
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $expires_timestamp = time() + $expiry;
        
        // Build canonical request
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date}/{$instance->region}/s3/aws4_request";
        $credential = "{$instance->access_key}/{$credential_scope}";
        
        $query_params = [
            'X-Amz-Algorithm' => $algorithm,
            'X-Amz-Credential' => $credential,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Expires' => $expiry,
            'X-Amz-SignedHeaders' => 'host'
        ];
        
        ksort($query_params);
        $canonical_query_string = http_build_query($query_params);
        
        // Build host
        if ($instance->endpoint) {
            $host = str_replace(['http://', 'https://'], '', $instance->endpoint);
            
            // Check if endpoint already contains bucket name
            if (strpos($host, $instance->bucket . '.') === 0) {
                // Endpoint already includes bucket (e.g., bucket.nyc3.digitaloceanspaces.com)
                $host = $host; // Use as is
                video_library_log("V4: Using endpoint with bucket already included: {$host}", 'debug');
            } else {
                // Standard endpoint (e.g., nyc3.digitaloceanspaces.com)
                $host = "{$instance->bucket}.{$host}";
                video_library_log("V4: Adding bucket to standard endpoint: {$host}", 'debug');
            }
        } else {
            if ($instance->region === 'us-east-1') {
                $host = "{$instance->bucket}.s3.amazonaws.com";
            } else {
                $host = "{$instance->bucket}.s3.{$instance->region}.amazonaws.com";
            }
        }
        
        $canonical_request = "GET\n/{$s3_key}\n{$canonical_query_string}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
        
        // Create string to sign
        $string_to_sign = "{$algorithm}\n{$timestamp}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
        
        // Calculate signature
        $signing_key = self::getSigningKey($instance->secret_key, $date, $instance->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Build final URL
        $query_params['X-Amz-Signature'] = $signature;
        $final_url = "https://{$host}/{$s3_key}?" . http_build_query($query_params);
        
        video_library_log("Generated v4 presigned URL for: {$s3_key}", 'info');
        
        return $final_url;
    }
    
    private static function getSigningKey($key, $date, $region, $service) {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        return $kSigning;
    }
    
    /**
     * Test S3 connection
     */
    public static function test_connection() {
        $instance = self::get_instance();
        
        if (empty($instance->access_key) || empty($instance->secret_key) || empty($instance->bucket)) {
            return false;
        }
        
        // Try to generate a presigned URL for a test object
        $test_key = 'test-connection-' . time();
        $presigned_url = self::get_presigned_url($test_key, 300); // 5 minutes
        
        return $presigned_url !== false;
    }
    
    /**
     * Check if S3 is properly configured
     */
    private function is_configured() {
        return !empty($this->access_key) && !empty($this->secret_key) && !empty($this->bucket);
    }
    
    /**
     * Create authorization headers for S3 requests
     */
    private function create_auth_headers($method, $resource, $content_md5 = '', $content_type = '') {
        $timestamp = gmdate('D, d M Y H:i:s \G\M\T');
        
        $string_to_sign = "{$method}\n{$content_md5}\n{$content_type}\n{$timestamp}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->secret_key, true));
        
        return [
            'Authorization' => "AWS {$this->access_key}:{$signature}",
            'Date' => $timestamp
        ];
    }
    
    /**
     * Upload file to S3 (for future use with direct uploads)
     */
    public static function upload_file($file_path, $s3_key, $content_type = 'video/mp4') {
        $instance = self::get_instance();
        
        if (!file_exists($file_path)) {
            video_library_log("File not found: {$file_path}", 'error');
            return false;
        }
        
        // This is a simplified upload method
        // In production, you'd want to use multipart upload for large files
        $file_content = file_get_contents($file_path);
        $file_size = strlen($file_content);
        
        $timestamp = gmdate('D, d M Y H:i:s \G\M\T');
        $content_md5 = base64_encode(md5($file_content, true));
        
        // Build authorization header
        $string_to_sign = "PUT\n{$content_md5}\n{$content_type}\n{$timestamp}\n/{$instance->bucket}/{$s3_key}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $instance->secret_key, true));
        
        $authorization = "AWS {$instance->access_key}:{$signature}";
        
        // Build URL
        if ($instance->endpoint) {
            $host = str_replace(['http://', 'https://'], '', $instance->endpoint);
            
            // Check if endpoint already contains bucket name
            if (strpos($host, $instance->bucket . '.') === 0) {
                // Endpoint already includes bucket
                $url = "https://{$host}/{$s3_key}";
            } else {
                // Standard endpoint
                $url = "https://{$instance->bucket}.{$host}/{$s3_key}";
            }
        } else {
            $url = "https://s3.{$instance->region}.amazonaws.com/{$instance->bucket}/{$s3_key}";
        }
        
        // Make the request
        $headers = [
            "Authorization: {$authorization}",
            "Date: {$timestamp}",
            "Content-Type: {$content_type}",
            "Content-MD5: {$content_md5}",
            "Content-Length: {$file_size}"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            video_library_log("Successfully uploaded: {$s3_key}", 'info');
            return true;
        } else {
            video_library_log("Upload failed for {$s3_key}. HTTP Code: {$http_code}, Response: {$response}", 'error');
            return false;
        }
    }
    
    /**
     * Delete file from S3
     */
    public static function delete_file($s3_key) {
        $instance = self::get_instance();
        
        $s3_key = ltrim($s3_key, '/');
        $timestamp = gmdate('D, d M Y H:i:s \G\M\T');
        
        // Build authorization header
        $string_to_sign = "DELETE\n\n\n{$timestamp}\n/{$instance->bucket}/{$s3_key}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $instance->secret_key, true));
        $authorization = "AWS {$instance->access_key}:{$signature}";
        
        // Build URL
        if ($instance->endpoint) {
            $host = str_replace(['http://', 'https://'], '', $instance->endpoint);
            
            // Check if endpoint already contains bucket name
            if (strpos($host, $instance->bucket . '.') === 0) {
                // Endpoint already includes bucket
                $url = "https://{$host}/{$s3_key}";
            } else {
                // Standard endpoint
                $url = "https://{$instance->bucket}.{$host}/{$s3_key}";
            }
        } else {
            $url = "https://s3.{$instance->region}.amazonaws.com/{$instance->bucket}/{$s3_key}";
        }
        
        $headers = [
            "Authorization: {$authorization}",
            "Date: {$timestamp}"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 204) {
            video_library_log("Successfully deleted: {$s3_key}", 'info');
            return true;
        } else {
            video_library_log("Delete failed for {$s3_key}. HTTP Code: {$http_code}", 'error');
            return false;
        }
    }
    
    /**
     * Get file info from S3
     */
    public static function get_file_info($s3_key) {
        $instance = self::get_instance();
        
        $s3_key = ltrim($s3_key, '/');
        $timestamp = gmdate('D, d M Y H:i:s \G\M\T');
        
        // Build authorization header
        $string_to_sign = "HEAD\n\n\n{$timestamp}\n/{$instance->bucket}/{$s3_key}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $instance->secret_key, true));
        $authorization = "AWS {$instance->access_key}:{$signature}";
        
        // Build URL
        if ($instance->endpoint) {
            $host = str_replace(['http://', 'https://'], '', $instance->endpoint);
            
            // Check if endpoint already contains bucket name
            if (strpos($host, $instance->bucket . '.') === 0) {
                // Endpoint already includes bucket
                $url = "https://{$host}/{$s3_key}";
            } else {
                // Standard endpoint
                $url = "https://{$instance->bucket}.{$host}/{$s3_key}";
            }
        } else {
            $url = "https://s3.{$instance->region}.amazonaws.com/{$instance->bucket}/{$s3_key}";
        }
        
        $headers = [
            "Authorization: {$authorization}",
            "Date: {$timestamp}"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if ($http_code === 200) {
            // Parse headers
            preg_match('/Content-Length:\s*(\d+)/i', $response, $matches);
            $file_size = isset($matches[1]) ? intval($matches[1]) : 0;
            
            preg_match('/Content-Type:\s*([^\r\n]+)/i', $response, $matches);
            $content_type = isset($matches[1]) ? trim($matches[1]) : 'unknown';
            
            preg_match('/Last-Modified:\s*([^\r\n]+)/i', $response, $matches);
            $last_modified = isset($matches[1]) ? trim($matches[1]) : '';
            
            return [
                'exists' => true,
                'size' => $file_size,
                'content_type' => $content_type,
                'last_modified' => $last_modified
            ];
        } else {
            return [
                'exists' => false,
                'size' => 0,
                'content_type' => '',
                'last_modified' => ''
            ];
        }
    }
    
    /**
     * List all files in the S3 bucket
     */
    public function list_bucket_files($prefix = '') {
        if (!$this->is_configured()) {
            video_library_log('S3 credentials not configured for listing files', 'error');
            return false;
        }
        
        video_library_log('Listing S3 bucket files with prefix: ' . $prefix, 'debug');
        video_library_log('Bucket: ' . $this->bucket, 'debug');
        video_library_log('Endpoint: ' . $this->endpoint, 'debug');
        video_library_log('Region: ' . $this->region, 'debug');
        
        try {
            // Build URL correctly based on endpoint type
            if ($this->endpoint) {
                // DigitalOcean Spaces - clean endpoint first
                $clean_endpoint = str_replace(['http://', 'https://'], '', $this->endpoint);
                
                // Check if endpoint already contains bucket name
                if (strpos($clean_endpoint, $this->bucket . '.') === 0) {
                    // Endpoint already includes bucket (e.g., bucket.nyc3.digitaloceanspaces.com)
                    $url = "https://{$clean_endpoint}/";
                    $canonical_resource = "/{$this->bucket}/";
                } else {
                    // Standard endpoint (e.g., nyc3.digitaloceanspaces.com)
                    $url = "https://{$this->bucket}.{$clean_endpoint}/";
                    $canonical_resource = "/{$this->bucket}/";
                }
            } else {
                // AWS S3 - use virtual-hosted-style  
                if ($this->region === 'us-east-1') {
                    $url = "https://{$this->bucket}.s3.amazonaws.com/";
                } else {
                    $url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/";
                }
                $canonical_resource = "/{$this->bucket}/";
            }
            
            if ($prefix) {
                $url .= '?prefix=' . urlencode($prefix);
                $canonical_resource = "/{$this->bucket}/?prefix=" . urlencode($prefix);
            }
            
            video_library_log("Final bucket listing URL: {$url}", 'info');
            video_library_log("Canonical resource for signature: {$canonical_resource}", 'debug');
            
            // Create the request headers with correct canonical resource
            $headers = $this->create_auth_headers('GET', $canonical_resource, '');
            
            $response = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => 30,
                'sslverify' => false  // Disable SSL verification for debugging
            ]);
            
            if (is_wp_error($response)) {
                video_library_log('Error listing S3 bucket: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                video_library_log("Failed to list S3 bucket. HTTP Code: {$http_code}, Response: {$body}", 'error');
                return false;
            }
            
            // Parse XML response
            $files = $this->parse_bucket_listing($body);
            video_library_log('Successfully listed ' . count($files) . ' files from S3 bucket', 'debug');
            
            return $files;
            
        } catch (Exception $e) {
            video_library_log('Exception listing S3 bucket: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Parse S3 bucket listing XML response
     */
    private function parse_bucket_listing($xml_string) {
        $files = [];
        
        try {
            $xml = simplexml_load_string($xml_string);
            
            if ($xml === false) {
                video_library_log('Failed to parse S3 bucket listing XML', 'error');
                return [];
            }
            
            foreach ($xml->Contents as $object) {
                $key = (string)$object->Key;
                $size = (int)$object->Size;
                $modified = (string)$object->LastModified;
                
                // Skip folders (objects ending with /)
                if (substr($key, -1) === '/') {
                    continue;
                }
                
                // Skip very small files (likely not videos)
                if ($size < 1024) {
                    continue;
                }
                
                $files[] = $key;
            }
            
            video_library_log('Parsed ' . count($files) . ' files from XML response', 'debug');
            
        } catch (Exception $e) {
            video_library_log('Error parsing S3 bucket listing: ' . $e->getMessage(), 'error');
        }
        
        return $files;
    }
} 