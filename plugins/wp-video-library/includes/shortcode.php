<?php
if (!defined('ABSPATH')) exit;

/**
 * Modern Video Library Shortcode
 * Pulls videos directly from S3/Spaces with responsive design
 */

// Register the main shortcode
add_shortcode('video_library', 'video_library_modern_shortcode');

function video_library_modern_shortcode($atts) {
    // Don't display if conditions not met
    if (!should_display_video_library()) {
        return '<div class="video-library-disabled">Video library is currently unavailable.</div>';
    }
    
    $atts = shortcode_atts([
        'layout' => 'tile',                    // tile, tube, gallery
        'videos_per_page' => 12,               // Number of videos per page
        'paths' => '',                         // Comma-separated S3 paths (hidden from users)
        'category' => '',                      // User-visible category filter
        'search' => '',                        // Pre-filled search
        'show_search' => 'true',               // Show search box
        'show_categories' => 'true',           // Show category filter
        'show_info' => 'true',                 // Show video information
        'orderby' => 'date',                   // date, title, filename
        'order' => 'DESC',                     // ASC, DESC
        'autoplay' => 'false',                 // Auto-play videos
        'theme' => 'inherit'                   // inherit, dark, light
    ], $atts);
    
    video_library_log('Modern video library shortcode called with attributes: ' . json_encode($atts), 'debug');
    
    // Parse multiple paths
    $path_filters = [];
    if (!empty($atts['paths'])) {
        $path_filters = array_map('trim', explode(',', $atts['paths']));
        $path_filters = array_filter($path_filters); // Remove empty values
    }
    
    // Get videos from S3 using the existing working function
    $videos = get_videos_by_filter([
        'paths' => $path_filters,
        'category' => $atts['category'], 
        'search' => $atts['search'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
        'limit' => $atts['videos_per_page'],
        'offset' => 0
    ]);
    
    video_library_log('Retrieved ' . count($videos) . ' videos from S3', 'info');
    
    // Generate unique ID for this instance
    $library_id = 'video-library-' . wp_generate_uuid4();
    
    ob_start();
    ?>
    
    <!-- Tailwind CSS and Video.js for Professional Video Experience -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet">
    <script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>
    
    <!-- Modern Video Library with Tailwind CSS -->
    <div class="w-full max-w-none <?php echo esc_attr($atts['theme']); ?>" id="<?php echo esc_attr($library_id); ?>" data-layout="<?php echo esc_attr($atts['layout']); ?>">
        
        <?php if ($atts['show_search'] === 'true' || $atts['show_categories'] === 'true'): ?>
        <!-- Control Bar -->
        <div class="video-library-controls">
            <?php if ($atts['show_search'] === 'true'): ?>
            <div class="video-search-container">
                <input type="text" 
                       class="video-search-input" 
                       placeholder="🔍 Search videos..." 
                       value="<?php echo esc_attr($atts['search']); ?>"
                       data-library-id="<?php echo esc_attr($library_id); ?>">
                <button class="video-search-clear" style="display: none;">✕</button>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_categories'] === 'true'): ?>
            <div class="video-category-container">
                <select class="video-category-filter" data-library-id="<?php echo esc_attr($library_id); ?>">
                    <option value="">All Categories</option>
                    <?php 
                    $categories = get_video_categories_from_s3($videos);
                    foreach ($categories as $cat):
                    ?>
                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($atts['category'], $cat); ?>>
                        <?php echo esc_html($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="video-results-info">
                <span class="video-count"><?php echo count($videos); ?></span>
                <span class="video-count-label">videos</span>
            </div>
        </div>
        
        <!-- Initialize Search Right After Controls -->
        <script>
                 console.log('🔍 Search script loading right after controls...');
         console.log('🔍 Library ID from inline script:', '<?php echo esc_js($library_id); ?>');
         console.log('🔍 Current HTML structure check:');
         console.log('🔍 - Total inputs on page:', document.querySelectorAll('input').length);
         console.log('🔍 - Video search inputs:', document.querySelectorAll('.video-search-input').length);
         console.log('🔍 - Elements with data-video-url:', document.querySelectorAll('[data-video-url]').length);
        
                 // Try to initialize search immediately
         setTimeout(() => {
             console.log('🔍 Immediate search initialization attempt...');
             const searchInput = document.querySelector('.video-search-input');
             console.log('🔍 Search input found immediately:', !!searchInput);
             if (searchInput) {
                 console.log('🔍 Search input element:', searchInput);
                 console.log('🔍 Search input data-library-id:', searchInput.getAttribute('data-library-id'));
                 
                 // Add a simple test event listener
                 searchInput.addEventListener('input', function(e) {
                     console.log('🔍 SEARCH INPUT DETECTED:', e.target.value);
                     
                     // Simple search test - hide/show all video items
                     const searchTerm = e.target.value.toLowerCase();
                     const videoItems = document.querySelectorAll('[data-video-url]');
                     console.log('🔍 Found video items:', videoItems.length);
                     
                     videoItems.forEach((item, index) => {
                         const title = (item.getAttribute('data-video-title') || '').toLowerCase();
                         const shouldShow = !searchTerm || title.includes(searchTerm);
                         item.style.display = shouldShow ? '' : 'none';
                         console.log(`🔍 Video ${index}: "${title}" - ${shouldShow ? 'SHOW' : 'HIDE'}`);
                     });
                 });
                 
                 console.log('🔍 Simple search listener attached!');
             }
         }, 100);
        </script>
        
        <?php endif; ?>
        
        <!-- Video Display Area -->
        <div class="video-library-content" data-layout="<?php echo esc_attr($atts['layout']); ?>">
            
            <?php if ($atts['layout'] === 'tube' && !empty($videos)): ?>
            <!-- Professional Tube Layout with Proper Proportions -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Main Video Section (2/3 width on desktop) -->
                <div class="flex-1 lg:w-2/3">
                    <div class="video-section-container">
                        <?php echo render_main_video_player($videos[0], $atts); ?>
                    </div>
                </div>
                
                <!-- Sidebar (1/3 width on desktop) - Fixed Height Match -->
                <div class="lg:w-1/3 lg:max-w-sm">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4 flex flex-col aspect-video">
                        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200 flex-shrink-0">
                            <h4 class="text-lg font-semibold text-gray-900">Up Next</h4>
                            <span class="text-sm text-gray-500 font-medium"><?php echo count($videos) - 1; ?> videos</span>
                        </div>
                        <div class="space-y-3 overflow-y-auto pr-2 flex-1 min-h-0" id="up-next-container-<?php echo esc_attr($library_id); ?>">
                            <?php 
                            $remaining_videos = count($videos) - 1;
                            if ($remaining_videos > 0) {
                                for ($i = 1; $i < count($videos); $i++) {
                                    echo render_sidebar_video_item($videos[$i], $i, $atts);
                                }
                            } else {
                            ?>
                            <div class="text-center py-8 text-gray-500">
                                <div class="text-4xl mb-2">📺</div>
                                <p class="text-sm">No other videos available</p>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Video Switching JavaScript -->
            <script>


            </script>
            
            <?php elseif ($atts['layout'] === 'gallery' && !empty($videos)): ?>
            <!-- Gallery Layout -->
            <div class="video-gallery-layout">
                <div class="gallery-main-player">
                    <div class="gallery-player-container" id="gallery-main-player">
                        <?php echo render_gallery_main_player($videos[0], $atts); ?>
                    </div>
                </div>
                
                <div class="gallery-carousel">
                    <button class="gallery-nav gallery-prev" aria-label="Previous video">‹</button>
                    <div class="gallery-thumbnails-container">
                        <div class="gallery-thumbnails">
                            <?php foreach ($videos as $index => $video): ?>
                                <?php echo render_gallery_thumbnail($video, $index, $atts); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="gallery-nav gallery-next" aria-label="Next video">›</button>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Professional Tile Layout with Tailwind CSS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if (empty($videos)): ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-center">
                    <div class="text-6xl mb-4">🎬</div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No Videos Found</h3>
                    <p class="text-gray-600">
                        <?php if (!empty($atts['search'])): ?>
                            No videos match your search criteria.
                        <?php elseif (!empty($atts['category'])): ?>
                            No videos found in this category.
                        <?php else: ?>
                            Configure your S3 settings to display videos.
                        <?php endif; ?>
                    </p>
                </div>
                <?php else: ?>
                    <?php foreach ($videos as $video): ?>
                        <?php echo render_tile_video_item($video, $atts); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>
        

        
    </div>
    
    <!-- Global Video Player Functionality -->
    <script>
    // Global video player and switching functionality
    window.currentVideoPlayer = null;
    window.currentLayout = '<?php echo esc_js($atts['layout']); ?>';
    
    // Global switch video function that works for all layouts
    function switchVideo(element) {
        const videoUrl = element.getAttribute('data-video-url');
        const videoTitle = element.getAttribute('data-video-title');
        const videoDescription = element.getAttribute('data-video-description');
        const videoCategory = element.getAttribute('data-video-category');
        
        if (!videoUrl) {
            console.error('No video URL found');
            return;
        }
        
        if (window.currentLayout === 'tile') {
            // For tile layout, open video in modal or fullscreen
            openVideoModal(videoUrl, videoTitle, videoDescription, videoCategory);
        } else if (window.currentLayout === 'gallery') {
            // For gallery layout, update the main gallery player
            updateGalleryPlayer(videoUrl, videoTitle, videoDescription, videoCategory, element);
        } else if (window.currentLayout === 'tube') {
            // For tube layout, update the main video player (existing functionality)
            updateTubePlayer(videoUrl, videoTitle, videoDescription, videoCategory, element);
        }
    }
    
    // Open video in modal for tile layout
    function openVideoModal(videoUrl, videoTitle, videoDescription, videoCategory) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('video-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'video-modal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 id="modal-title" class="text-xl font-bold"></h2>
                        <button onclick="closeVideoModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                    </div>
                    <div class="aspect-video mb-4">
                        <video id="modal-video" class="w-full h-full" controls>
                            <source src="" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    <div class="space-y-2">
                        <div id="modal-category" class="inline-block px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium"></div>
                        <p id="modal-description" class="text-gray-600"></p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        // Update modal content
        document.getElementById('modal-title').textContent = videoTitle;
        document.getElementById('modal-category').textContent = videoCategory;
        document.getElementById('modal-description').textContent = videoDescription;
        
        const modalVideo = document.getElementById('modal-video');
        modalVideo.src = videoUrl;
        modalVideo.muted = false;
        modalVideo.volume = 1.0;
        
        // Show modal
        modal.style.display = 'flex';
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    
    // Close video modal
    function closeVideoModal() {
        const modal = document.getElementById('video-modal');
        if (modal) {
            modal.style.display = 'none';
            const video = document.getElementById('modal-video');
            if (video) {
                video.pause();
                video.src = '';
            }
        }
        document.body.style.overflow = '';
    }
    
    // Update gallery main player
    function updateGalleryPlayer(videoUrl, videoTitle, videoDescription, videoCategory, clickedElement) {
        const galleryPlayer = document.querySelector('.video-player-gallery');
        if (galleryPlayer) {
            galleryPlayer.src = videoUrl;
            galleryPlayer.muted = false;
            galleryPlayer.volume = 1.0;
            galleryPlayer.load();
        }
        
        // Update gallery title and info
        const titleElement = document.querySelector('.gallery-main-title');
        if (titleElement) {
            titleElement.textContent = videoTitle;
        }
        
        const categoryElement = document.querySelector('.video-category-badge');
        if (categoryElement) {
            categoryElement.textContent = videoCategory;
        }
        
        const descriptionElement = document.querySelector('.gallery-main-description p');
        if (descriptionElement) {
            descriptionElement.textContent = videoDescription;
        }
        
        // Update active state in gallery thumbnails if clickedElement is provided
        if (clickedElement) {
            const allThumbnails = document.querySelectorAll('.gallery-thumbnail');
            allThumbnails.forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            // Add active state to clicked thumbnail
            if (clickedElement.classList.contains('gallery-thumbnail')) {
                clickedElement.classList.add('active');
            }
        }
    }
    
    // Update tube layout player (existing functionality)
    function updateTubePlayer(videoUrl, videoTitle, videoDescription, videoCategory, clickedElement) {
        if (!window.mainVideoPlayer) {
            // Try to find and initialize the video player
            const videoElement = document.querySelector('.video-js');
            if (videoElement && typeof videojs !== 'undefined') {
                try {
                    window.mainVideoPlayer = videojs(videoElement.id, {
                        fluid: false,
                        responsive: false,
                        fill: true,
                        aspectRatio: '16:9'
                    });
                    
                    // Try the update again
                    setTimeout(() => updateTubePlayer(videoUrl, videoTitle, videoDescription, videoCategory, clickedElement), 100);
                    return;
                } catch (error) {
                    console.error('Emergency Video.js initialization failed:', error);
                    return;
                }
            }
            
            console.error('Video player not found');
            return;
        }
        
        try {
            // Update the video source
            window.mainVideoPlayer.src({
                src: videoUrl,
                type: 'video/mp4'
            });
            
            // Ensure audio is enabled
            window.mainVideoPlayer.muted(false);
            window.mainVideoPlayer.volume(1.0);
            
            // Update the video title in the info panel
            const titleElement = document.querySelector('.video-section-container h1');
            if (titleElement) {
                titleElement.textContent = videoTitle;
            }
            
            // Update the category badge
            const categoryElement = document.querySelector('.video-section-container .bg-gradient-to-r');
            if (categoryElement) {
                categoryElement.textContent = videoCategory;
            }
            
            // Update the description
            const descriptionElement = document.querySelector('.video-section-container .text-gray-700 p');
            if (descriptionElement) {
                descriptionElement.textContent = videoDescription || "A video about " + videoTitle;
            }
            
            // Update active state in sidebar if clickedElement is provided
            if (clickedElement) {
                const allSidebarItems = document.querySelectorAll('[id*="up-next-container"] > div[data-video-url]');
                allSidebarItems.forEach(item => {
                    item.classList.remove('bg-blue-50', 'border-blue-200');
                    item.classList.add('hover:bg-gray-50');
                });
                
                // Add active state to clicked item
                clickedElement.classList.remove('hover:bg-gray-50');
                clickedElement.classList.add('bg-blue-50', 'border-blue-200');
            }
            
            // Load the video
            window.mainVideoPlayer.load();
            
            // Ensure audio is enabled when new video loads
            window.mainVideoPlayer.one('loadedmetadata', function() {
                this.muted(false);
                this.volume(1.0);
            });
            
        } catch (error) {
            console.error('Error switching video:', error);
        }
    }
    
    // Initialize video functionality on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Add ESC key handler for modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVideoModal();
            }
        });
        
        // Add click outside modal to close
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('video-modal');
            if (modal && e.target === modal) {
                closeVideoModal();
            }
        });
    });
    </script>
    
    <!-- Professional Styling with Tailwind Utilities -->
    <style>
    @import url('https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    @keyframes fade-in {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
    
    /* Video.js custom styling */
    .vjs-default-skin {
        border-radius: 0.75rem !important;
    }
    
    .video-js .vjs-tech {
        border-radius: 0.75rem;
    }
    
    /* Minimal Video.js styling - let defaults work */
    .video-js .vjs-poster {
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }
    
    /* Video modal styling */
    #video-modal {
        z-index: 9999;
    }
    
    #video-modal video {
        border-radius: 8px;
        background: #000;
    }
    
    /* Gallery thumbnail active state */
    .gallery-thumbnail.active {
        border: 3px solid #3b82f6;
        transform: scale(1.05);
    }
    
    /* Search debugging */
    .search-debug {
        position: fixed;
        top: 10px;
        right: 10px;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 10px;
        border-radius: 5px;
        font-size: 12px;
        z-index: 1000;
    }
    </style>
    
    <!-- Additional CSS for Controls -->
    <style>
    .video-library-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.06);
    }
    
    .video-search-container {
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    
    .video-search-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid rgba(0,0,0,0.1);
        border-radius: 8px;
        font-size: 1rem;
        background: white;
        transition: all 0.2s ease;
    }
    
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102,126,234,0.1);
    }
    
    .video-search-clear {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    
    .video-search-clear:hover {
        opacity: 1;
    }
    
    .video-category-container {
        flex-shrink: 0;
    }
    
    .video-category-filter {
        padding: 0.75rem 1rem;
        border: 2px solid rgba(0,0,0,0.1);
        border-radius: 8px;
        background: white;
        font-size: 1rem;
        min-width: 150px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .video-category-filter:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102,126,234,0.1);
    }
    
    .video-results-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #666;
        flex-shrink: 0;
    }
    
    .video-count {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
    }
    
    /* Video.js Sizing Fixes */
    .video-js {
        width: 100% !important;
        height: 100% !important;
        max-height: none !important;
    }
    
    .video-js .vjs-tech {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }
    
    /* Custom Scrollbar for Up Next Section */
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }
    
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background-color: rgba(156, 163, 175, 0.7);
    }
    
    /* Tailwind aspect-video handles height matching automatically */
    
    /* Mobile First Responsive Design */
    @media (max-width: 1023px) {
        .lg\\:flex-row {
            flex-direction: column !important;
        }
        
        .lg\\:w-2\\/3,
        .lg\\:w-1\\/3 {
            width: 100% !important;
            max-width: none !important;
        }
        
        .lg\\:max-w-sm {
            max-width: none !important;
        }
        
        /* Mobile sidebar adjustments */
        .sidebar-height-match {
            height: auto !important;
            max-height: 60vh;
            min-height: 300px;
        }
    }
    
    @media (max-width: 768px) {
        .video-library-controls {
            flex-direction: column;
            align-items: stretch;
            padding: 1rem;
            gap: 0.75rem;
        }
        
        .video-search-container {
            min-width: auto;
        }
        
        .video-results-info {
            justify-content: center;
        }
        
        /* Mobile Gallery Adjustments */
        .gallery-title-section {
            padding: 1.5rem 1rem 1rem 1rem;
        }
        
        .gallery-main-title {
            font-size: 1.75rem;
        }
        
        .gallery-main-meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .space-y-4 {
            gap: 0.75rem !important;
        }
    }
    
    @media (max-width: 480px) {
        /* Extra small screens */
        .video-library-controls {
            padding: 0.75rem;
        }
        
        .text-2xl {
            font-size: 1.5rem !important;
        }
        
        .text-lg {
            font-size: 1.125rem !important;
        }
        
        .gallery-main-title {
            font-size: 1.5rem;
        }
        
        .p-6 {
            padding: 1rem !important;
        }
        
        .gap-6 {
            gap: 1rem !important;
        }
    }
    
    /* Professional Tile Layout */
    .video-tile-layout {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
        padding: 1rem 0;
    }
    
    .video-tile-item {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid rgba(0,0,0,0.04);
        position: relative;
    }
    
    .video-tile-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0,0,0,0.18);
        border-color: rgba(0,124,186,0.1);
    }
    
    .video-tile-clickable {
        cursor: pointer;
    }
    
    .video-tile-clickable:hover .video-play-overlay {
        opacity: 1;
    }
    
    .video-tile-clickable:hover .video-hover-gradient {
        opacity: 1;
    }
    
    .video-thumbnail {
        position: relative;
        width: 100%;
        height: 220px;
        background-size: cover;
        background-position: center;
        background-color: #f0f0f0;
        overflow: hidden;
    }
    
    .video-thumbnail::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(45deg, rgba(0,0,0,0.05), transparent);
    }
    
    .video-hover-gradient {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .video-play-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.4);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .video-play-button {
        background: rgba(255,255,255,0.95);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        border: none;
        cursor: pointer;
        padding: 15px;
    }
    
    .video-play-button:hover {
        transform: scale(1.1);
        background: rgba(255,255,255,1);
        box-shadow: 0 6px 24px rgba(0,0,0,0.3);
    }
    
    .video-duration-badge {
        position: absolute;
        bottom: 8px;
        right: 8px;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    /* Professional Video Info for Tile Layout */
    .video-info-professional {
        padding: 1.5rem;
        background: white;
    }
    
    .video-title-professional {
        margin: 0 0 0.75rem 0;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        color: #1a1a1a;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-meta-professional {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .video-date-professional {
        font-size: 0.85rem;
        color: #666;
        font-weight: 500;
    }
    
    .video-duration-professional {
        font-size: 0.85rem;
        color: #666;
        font-weight: 500;
    }
    
    .video-description-professional {
        font-size: 0.95rem;
        color: #444;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin: 0;
    }
    
    /* Professional Gallery Layout */
    .gallery-section-professional {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    
    .gallery-title-section {
        background: white;
        padding: 2rem 2rem 1.5rem 2rem;
        border-radius: 16px 16px 0 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid rgba(0,0,0,0.04);
        border-bottom: none;
    }
    
    .gallery-main-title {
        margin: 0 0 1rem 0;
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1.1;
        color: #1a1a1a;
        text-transform: none;
    }
    
    .gallery-main-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .gallery-main-description {
        margin: 0;
    }
    
    .gallery-main-description p {
        margin: 0;
        font-size: 1.1rem;
        line-height: 1.6;
        color: #555;
        font-weight: 400;
    }
    
    .gallery-player-wrapper {
        border-radius: 0 0 16px 16px;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.04);
        border-top: none;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    
    /* Enhanced Category Badge */
    .video-category-tag {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(102,126,234,0.3);
    }
    
    /* Tube Layout */
    .video-tube-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 2.5rem;
        margin-bottom: 2rem;
        align-items: start;
    }
    
    .video-main-player {
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        aspect-ratio: 16/9;
        position: relative;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    }
    
    .video-main-section {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .video-main-player-container {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 12px;
        overflow: hidden;
        background: #000;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .video-main-player-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }
    
    .video-player-main,
    .video-player-gallery {
        width: 100%;
        height: 100%;
        border: none;
        object-fit: cover;
        background: #000;
        display: block;
    }
    
    .video-player-main:focus,
    .video-player-gallery:focus {
        outline: none;
    }
    
    .video-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    
    .video-loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid rgba(255,255,255,0.3);
        border-top: 3px solid #fff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .video-fullscreen-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(0,0,0,0.8);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
        opacity: 0;
        transform: translateY(-10px);
    }
    
    .video-main-player-container:hover .video-fullscreen-btn {
        opacity: 1;
        transform: translateY(0);
    }
    
    .video-fullscreen-btn:hover {
        background: rgba(0,0,0,0.95);
        transform: scale(1.05);
    }
    
    /* Video Information Panel */
    .video-main-info {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 16px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.06);
    }
    
    .video-main-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 1rem;
    }
    
    .video-main-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.3;
        color: #1a1a1a;
        flex: 1;
    }
    
    .video-main-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    
    .video-action-btn {
        padding: 10px;
        border: 1px solid rgba(0,0,0,0.1);
        background: rgba(0,0,0,0.02);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #666;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .video-action-btn:hover {
        background: rgba(0,0,0,0.05);
        color: #333;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .video-like-btn:hover {
        color: #e74c3c;
        border-color: rgba(231,76,60,0.3);
        background: rgba(231,76,60,0.05);
    }
    
    .video-share-btn:hover {
        color: #3498db;
        border-color: rgba(52,152,219,0.3);
        background: rgba(52,152,219,0.05);
    }
    
    .video-main-meta {
        margin-bottom: 1rem;
    }
    
    .video-meta-primary {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }
    
    .video-category-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .video-duration-info,
    .video-date-info {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: #666;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .video-duration-info svg,
    .video-date-info svg {
        opacity: 0.7;
    }
    
    .video-main-description {
        margin-bottom: 1rem;
    }
    
    .video-main-description p {
        margin: 0;
        line-height: 1.6;
        color: #444;
        font-size: 1rem;
    }
    
    .video-details-toggle {
        margin-bottom: 1rem;
    }
    
    .video-details-btn {
        background: none;
        border: none;
        color: #007cba;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        padding: 0.5rem 0;
    }
    
    .video-details-btn:hover {
        color: #005a8b;
    }
    
    .toggle-icon {
        transition: transform 0.3s ease;
    }
    
    .video-details-btn.expanded .toggle-icon {
        transform: rotate(180deg);
    }
    
    .video-details-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .video-details-content.expanded {
        max-height: 200px;
    }
    
    .video-technical-info {
        padding: 1rem;
        background: rgba(0,0,0,0.02);
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.06);
    }
    
    .video-technical-info h4 {
        margin: 0 0 0.75rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #333;
    }
    
    .tech-info-grid {
        display: grid;
        gap: 0.5rem;
    }
    
    .tech-info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .tech-info-item:last-child {
        border-bottom: none;
    }
    
    .tech-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
    }
    
    .tech-value {
        color: #333;
        font-size: 0.9rem;
    }
    
    .video-sidebar {
        background: rgba(0,0,0,0.02);
        border-radius: 8px;
        padding: 1rem;
        max-height: 500px;
        overflow-y: auto;
    }
    
    .video-sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    
    .video-sidebar-header h4 {
        margin: 0;
        font-size: 1rem;
        color: inherit;
    }
    
    .video-sidebar-item {
        display: flex;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 0.5rem;
        border: 1px solid transparent;
        position: relative;
        overflow: hidden;
    }
    
    .video-sidebar-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
        opacity: 0.1;
    }
    
    .video-sidebar-item:hover {
        background: rgba(0,0,0,0.03);
        transform: translateX(4px);
        border-color: rgba(102,126,234,0.2);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .video-sidebar-item:hover::before {
        width: 4px;
    }
    
    .video-sidebar-thumb {
        width: 120px;
        height: 68px;
        background-size: cover;
        background-position: center;
        border-radius: 4px;
        flex-shrink: 0;
        background-color: #f0f0f0;
    }
    
    .video-sidebar-info {
        flex: 1;
    }
    
    .video-sidebar-title {
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0 0 0.25rem 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .video-sidebar-meta {
        font-size: 0.8rem;
        opacity: 0.6;
    }
    
    /* Gallery Layout */
    .video-gallery-layout {
        margin-bottom: 2rem;
    }
    
    .gallery-main-player {
        position: relative;
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }
    
    .gallery-player-container,
    .gallery-player-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
    }
    
    .gallery-video-info {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: white;
        padding: 20px;
        transform: translateY(100%);
        transition: transform 0.3s ease;
    }
    
    .gallery-main-player:hover .gallery-video-info {
        transform: translateY(0);
    }
    
    .gallery-video-info h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .gallery-carousel {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: rgba(0,0,0,0.02);
        border-radius: 8px;
        position: relative;
    }
    
    .gallery-nav {
        background: rgba(0,0,0,0.7);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        flex-shrink: 0;
    }
    
    .gallery-nav:hover {
        background: rgba(0,0,0,0.9);
        transform: scale(1.1);
    }
    
    .gallery-nav:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        transform: none;
    }
    
    .gallery-thumbnails-container {
        flex: 1;
        overflow: hidden;
        position: relative;
    }
    
    .gallery-thumbnails {
        display: flex;
        gap: 1rem;
        transition: transform 0.3s ease;
        will-change: transform;
    }
    
    .gallery-thumbnail {
        position: relative;
        width: 120px;
        height: 68px;
        background-size: cover;
        background-position: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 3px solid transparent;
        flex-shrink: 0;
        overflow: hidden;
    }
    
    .gallery-thumbnail:hover {
        transform: scale(1.05);
        border-color: rgba(255,255,255,0.5);
    }
    
    .gallery-thumbnail.active {
        border-color: #007cba;
        transform: scale(1.02);
        box-shadow: 0 0 0 2px rgba(0,124,186,0.3);
    }
    
    .gallery-thumbnail::before {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.3);
        transition: opacity 0.2s;
    }
    
    .gallery-thumbnail:hover::before {
        opacity: 0.1;
    }
    
    .gallery-thumbnail.active::before {
        opacity: 0;
    }
    
    .gallery-thumbnail-play {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 30px;
        height: 30px;
        background: rgba(255,255,255,0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #333;
        transition: all 0.2s;
    }
    
    .gallery-thumbnail:hover .gallery-thumbnail-play {
        transform: translate(-50%, -50%) scale(1.1);
    }
    
    .gallery-thumbnail-duration {
        position: absolute;
        bottom: 4px;
        right: 4px;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .gallery-thumbnail-title {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        color: white;
        padding: 8px 6px 4px;
        font-size: 11px;
        font-weight: 500;
        opacity: 0;
        transition: opacity 0.2s;
        line-height: 1.2;
    }
    
    .gallery-thumbnail:hover .gallery-thumbnail-title {
        opacity: 1;
    }
    
    /* Empty State */
    .video-library-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: inherit;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .video-library-empty h3 {
        margin: 0 0 0.5rem 0;
        color: inherit;
        opacity: 0.7;
    }
    
    .video-library-empty p {
        margin: 0;
        opacity: 0.5;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Load More */
    .video-load-more {
        text-align: center;
        margin-top: 2rem;
    }
    
    .video-load-more-btn {
        background: #007cba;
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 6px;
        font-size: inherit;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .video-load-more-btn:hover {
        background: #005a87;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .video-library-controls {
            flex-direction: column;
            align-items: stretch;
        }
        
        .video-search-container {
            max-width: none;
        }
        
        .video-results-info {
            margin-left: 0;
            text-align: center;
        }
        
        .video-tile-layout {
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 0.5rem;
        }
        
        .video-tile-item {
            border-radius: 12px;
        }
        
        .video-thumbnail {
            height: 200px;
        }
        
        .video-info-professional {
            padding: 1.25rem;
        }
        
        .video-title-professional {
            font-size: 1.1rem;
        }
        
        .gallery-title-section {
            padding: 1.5rem;
            border-radius: 12px 12px 0 0;
        }
        
        .gallery-main-title {
            font-size: 1.75rem;
            line-height: 1.2;
        }
        
        .gallery-main-meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .gallery-player-wrapper {
            border-radius: 0 0 12px 12px;
        }
        
        .video-tube-layout {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .video-main-section {
            order: 1;
        }
        
        .video-sidebar {
            order: 2;
        }
        
        .video-main-title {
            font-size: 1.25rem;
        }
        
        .video-main-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        
        .video-main-actions {
            align-self: flex-end;
        }
        
        .video-meta-primary {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .video-category-badge {
            align-self: flex-start;
        }
        
        .video-main-info {
            padding: 1.25rem;
        }
        
        .gallery-carousel {
            flex-direction: column;
            gap: 1rem;
        }
        
        .gallery-thumbnails {
            justify-content: center;
        }
        
        .gallery-nav {
            width: 40px;
            height: 40px;
            font-size: 20px;
        }
        
        .video-sidebar {
            max-height: 300px;
        }
        
        .video-sidebar-thumb {
            width: 80px;
            height: 45px;
        }
        
        .video-thumbnail {
            height: 140px;
        }
    }
    
    @media (max-width: 480px) {
        .video-tile-layout {
            grid-template-columns: 1fr;
        }
        
        .video-library-controls {
            padding: 0.75rem;
        }
        
        .gallery-thumbnails {
            gap: 0.5rem;
        }
        
        .gallery-thumbnail {
            width: 80px;
            height: 45px;
        }
        
        .gallery-nav {
            width: 35px;
            height: 35px;
            font-size: 18px;
        }
        
        .video-fullscreen-btn {
            top: 5px;
            right: 5px;
            padding: 6px;
        }
        
        .video-fullscreen-btn svg {
            width: 16px;
            height: 16px;
        }
        
        .video-library-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            padding: 0.75rem;
        }
        
        .video-search-container {
            min-width: auto;
            max-width: none;
        }
        
        .video-results-info {
            margin-left: 0;
            text-align: center;
        }
    }
    
    /* Dark theme support */
    .video-library-modern.dark {
        color: #e0e0e0;
    }
    
    .video-library-modern.dark .video-library-controls {
        background: rgba(255,255,255,0.05);
    }
    
    .video-library-modern.dark .video-tile-item {
        background: #1a1a1a;
        border-color: rgba(255,255,255,0.1);
    }
    
    .video-library-modern.dark .video-search-input,
    .video-library-modern.dark .video-category-filter {
        background: #2a2a2a;
        border-color: rgba(255,255,255,0.2);
        color: #e0e0e0;
    }
    </style>
    
    <!-- JavaScript for interactivity -->
    <script>
    (function() {
        const libraryId = '<?php echo esc_js($library_id); ?>';
        const library = document.getElementById(libraryId);
        
        if (!library) return;
        
        // Search functionality
        const searchInput = library.querySelector('.video-search-input');
        const searchClear = library.querySelector('.video-search-clear');
        const categoryFilter = library.querySelector('.video-category-filter');
        let searchTimeout;
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query && searchClear) {
                    searchClear.style.display = 'block';
                } else if (searchClear) {
                    searchClear.style.display = 'none';
                }
                
                searchTimeout = setTimeout(() => {
                    filterVideos(libraryId);
                }, 300);
            });
            
            // Show/hide clear button on focus/blur
            searchInput.addEventListener('focus', function() {
                if (this.value.trim() && searchClear) {
                    searchClear.style.display = 'block';
                }
            });
        }
        
        if (searchClear) {
            searchClear.addEventListener('click', function() {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                this.style.display = 'none';
                filterVideos(libraryId);
            });
        }
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                filterVideos(libraryId);
            });
        }
        
        // Video click handlers - now handled by onclick attributes
        // Keeping this for backward compatibility but onclick takes precedence
        library.addEventListener('click', function(e) {
            const videoItem = e.target.closest('[data-video-url]');
            if (videoItem && !videoItem.onclick) {
                // Only handle if no onclick handler is set
                console.log('Fallback click handler triggered for:', videoItem.dataset.videoTitle);
                window.switchVideo(videoItem);
            }
        });
        
        // Gallery navigation
        const galleryPrev = library.querySelector('.gallery-prev');
        const galleryNext = library.querySelector('.gallery-next');
        const galleryThumbnails = library.querySelector('.gallery-thumbnails');
        
        if (galleryPrev && galleryNext && galleryThumbnails) {
            let currentGalleryIndex = 0;
            const thumbnails = galleryThumbnails.querySelectorAll('.gallery-thumbnail');
            const visibleThumbnails = 5; // Number of thumbnails visible at once
            
            function updateGalleryNavigation() {
                galleryPrev.disabled = currentGalleryIndex === 0;
                galleryNext.disabled = currentGalleryIndex >= thumbnails.length - visibleThumbnails;
                
                const translateX = -currentGalleryIndex * (120 + 16); // thumbnail width + gap
                galleryThumbnails.style.transform = `translateX(${translateX}px)`;
            }
            
            galleryPrev.addEventListener('click', function() {
                if (currentGalleryIndex > 0) {
                    currentGalleryIndex--;
                    updateGalleryNavigation();
                }
            });
            
            galleryNext.addEventListener('click', function() {
                if (currentGalleryIndex < thumbnails.length - visibleThumbnails) {
                    currentGalleryIndex++;
                    updateGalleryNavigation();
                }
            });
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft' && galleryPrev && !galleryPrev.disabled) {
                    galleryPrev.click();
                } else if (e.key === 'ArrowRight' && galleryNext && !galleryNext.disabled) {
                    galleryNext.click();
                }
            });
            
            updateGalleryNavigation();
        }
        
        function playVideo(videoElement) {
            const videoUrl = videoElement.dataset.videoUrl;
            const videoTitle = videoElement.dataset.videoTitle;
            
            if (!videoUrl) return;
            
            // Create modal player
            const modal = document.createElement('div');
            modal.className = 'video-modal';
            modal.innerHTML = `
                <div class="video-modal-content">
                    <div class="video-modal-header">
                        <h3>${videoTitle}</h3>
                        <button class="video-modal-close">&times;</button>
                    </div>
                    <div class="video-modal-player">
                        <video controls autoplay style="width: 100%; height: 100%;">
                            <source src="${videoUrl}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            `;
            
            // Add modal styles
            const modalStyles = document.createElement('style');
            modalStyles.textContent = `
                .video-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                }
                .video-modal-content {
                    width: 90%;
                    max-width: 900px;
                    background: #000;
                    border-radius: 8px;
                    overflow: hidden;
                }
                .video-modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem;
                    background: #1a1a1a;
                    color: white;
                }
                .video-modal-header h3 {
                    margin: 0;
                    font-size: 1.2rem;
                }
                .video-modal-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 2rem;
                    cursor: pointer;
                    padding: 0;
                    line-height: 1;
                }
                .video-modal-player {
                    aspect-ratio: 16/9;
                    background: #000;
                }
            `;
            
            document.head.appendChild(modalStyles);
            document.body.appendChild(modal);
            
            // Close modal handlers
            modal.querySelector('.video-modal-close').addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
            
            function closeModal() {
                document.body.removeChild(modal);
                document.head.removeChild(modalStyles);
            }
            
            // ESC key to close
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', escHandler);
                }
            });
        }
        
        // Global video switching function for all layouts
        window.switchVideo = function(videoElement) {
            const videoUrl = videoElement.dataset.videoUrl;
            const videoTitle = videoElement.dataset.videoTitle;
            const videoDescription = videoElement.dataset.videoDescription || `A video about ${videoTitle}`;
            const videoCategory = videoElement.dataset.videoCategory;
            
            console.log('Switching video to:', videoTitle, videoUrl);
            
            if (!videoUrl) {
                console.error('No video URL found');
                return false;
            }
            
            // Check if we have a main video player (tube layout)
            if (window.mainVideoPlayer) {
                try {
                    // Switch main Video.js player
                    window.mainVideoPlayer.src([{
                        src: videoUrl,
                        type: 'video/mp4'
                    }]);
                    
                    // Update UI elements in tube layout
                    const titleElement = document.querySelector('.text-xl, .text-2xl, h1');
                    if (titleElement) {
                        titleElement.textContent = videoTitle;
                    }
                    
                    const categoryElement = document.querySelector('.inline-flex, .bg-gradient-to-r');
                    if (categoryElement) {
                        categoryElement.textContent = videoCategory;
                    }
                    
                    const descriptionElement = document.querySelector('.text-gray-700 p');
                    if (descriptionElement) {
                        descriptionElement.textContent = videoDescription;
                    }
                    
                    console.log('Video switched successfully to:', videoTitle);
                    return true;
                    
                } catch (error) {
                    console.error('Error switching video:', error);
                    return false;
                }
                         } else {
                // Check if this is a gallery layout
                const galleryPlayer = document.querySelector('.video-player-gallery');
                if (galleryPlayer) {
                    console.log('Switching gallery video to:', videoTitle);
                    // Update gallery video
                    const source = galleryPlayer.querySelector('source');
                    if (source) {
                        source.src = videoUrl;
                        galleryPlayer.load();
                    }
                    
                    // Update gallery info
                    const galleryTitle = document.querySelector('.gallery-main-title');
                    if (galleryTitle) {
                        galleryTitle.textContent = videoTitle;
                    }
                    
                    const galleryDescription = document.querySelector('.gallery-main-description p');
                    if (galleryDescription) {
                        galleryDescription.textContent = videoDescription;
                    }
                    
                    const galleryCategory = document.querySelector('.video-category-badge');
                    if (galleryCategory) {
                        galleryCategory.textContent = videoCategory;
                    }
                    
                    // Update active thumbnail
                    const allThumbnails = document.querySelectorAll('.gallery-thumbnail');
                    allThumbnails.forEach(thumb => thumb.classList.remove('active'));
                    if (videoElement.classList.contains('gallery-thumbnail')) {
                        videoElement.classList.add('active');
                    }
                    
                    return true;
                } else {
                    // No main player, open modal instead (pure tile layout)
                    console.log('No main player found, opening modal for:', videoTitle);
                    window.openProfessionalVideoModal(videoUrl, videoTitle, videoDescription, videoCategory);
                    return true;
                }
            }
        }
        
        // Legacy function for backward compatibility
        window.switchTubeVideo = window.switchVideo;

        function switchGalleryVideo(thumbnail) {
            const videoUrl = thumbnail.dataset.videoUrl;
            const videoTitle = thumbnail.dataset.videoTitle;
            const videoDescription = thumbnail.dataset.videoDescription;
            
            if (!videoUrl) return;
            
            // Update main player
            const mainPlayer = document.getElementById('gallery-main-player');
            if (mainPlayer) {
                const galleryVideo = mainPlayer.querySelector('.video-player-gallery');
                if (galleryVideo) {
                    // Update video source
                    const source = galleryVideo.querySelector('source');
                    if (source) {
                        source.src = videoUrl;
                        galleryVideo.load(); // Reload video with new source
                    }
                    
                    // Update video info
                    const videoInfo = mainPlayer.querySelector('.gallery-video-info h3');
                    if (videoInfo) {
                        videoInfo.textContent = videoTitle;
                    }
                    
                    // Update fullscreen button
                    const fullscreenBtn = mainPlayer.querySelector('.video-fullscreen-btn');
                    if (fullscreenBtn) {
                        fullscreenBtn.onclick = () => openVideoFullscreen(videoUrl, videoTitle);
                    }
                }
            }
            
            // Update active thumbnail
            const allThumbnails = thumbnail.closest('.gallery-thumbnails').querySelectorAll('.gallery-thumbnail');
            allThumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnail.classList.add('active');
        }
        
        function filterVideos(libraryId) {
            const library = document.getElementById(libraryId);
            if (!library) return;
            
            const searchQuery = library.querySelector('.video-search-input')?.value.toLowerCase().trim() || '';
            const categoryFilter = library.querySelector('.video-category-filter')?.value || '';
            
            // Get all video items regardless of layout
            const videoItems = library.querySelectorAll('[data-video-title]');
            let visibleCount = 0;
            
            videoItems.forEach(item => {
                const title = (item.dataset.videoTitle || '').toLowerCase();
                const description = (item.dataset.videoDescription || '').toLowerCase();
                const category = item.dataset.videoCategory || '';
                
                const matchesSearch = !searchQuery || 
                    title.includes(searchQuery) || 
                    description.includes(searchQuery) ||
                    category.toLowerCase().includes(searchQuery);
                
                const matchesCategory = !categoryFilter || category === categoryFilter;
                
                const shouldShow = matchesSearch && matchesCategory;
                
                // Handle different layout types
                if (item.closest('.video-tile-item')) {
                    // Tile layout
                    item.closest('.video-tile-item').style.display = shouldShow ? '' : 'none';
                } else if (item.classList.contains('gallery-thumbnail')) {
                    // Gallery layout
                    item.style.display = shouldShow ? '' : 'none';
                } else {
                    // Sidebar item or other layout
                    item.style.display = shouldShow ? '' : 'none';
                }
                
                if (shouldShow) visibleCount++;
            });
            
            // Update count display
            const countElement = library.querySelector('.video-count');
            if (countElement) {
                countElement.textContent = visibleCount;
            }
            
            // Show "no results" message if needed
            const layout = library.dataset.layout;
            if (visibleCount === 0 && (searchQuery || categoryFilter)) {
                showNoResultsMessage(library, layout);
            } else {
                hideNoResultsMessage(library);
            }
        }
        
        function showNoResultsMessage(library, layout) {
            // Remove existing message
            hideNoResultsMessage(library);
            
            const message = document.createElement('div');
            message.className = 'video-no-results col-span-full flex flex-col items-center justify-center py-16 text-center';
            message.innerHTML = `
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Videos Found</h3>
                <p class="text-gray-600">Try adjusting your search or filters</p>
            `;
            
            // Insert based on layout
            const contentArea = library.querySelector('.video-library-content');
            if (contentArea) {
                contentArea.appendChild(message);
            }
        }
        
        function hideNoResultsMessage(library) {
            const existingMessage = library.querySelector('.video-no-results');
            if (existingMessage) {
                existingMessage.remove();
            }
        }
        

        
    })();
    
    // Ensure all videos have sound enabled
    document.addEventListener('DOMContentLoaded', function() {
        const videos = document.querySelectorAll('.video-player-main, .video-player-gallery');
        videos.forEach(video => {
            video.muted = false;
            video.volume = 1.0;
            
            // Handle user interaction to unmute
            video.addEventListener('play', function() {
                this.muted = false;
            });
            
            video.addEventListener('click', function() {
                if (this.muted) {
                    this.muted = false;
                }
            });
        });
    });
    
    // Global function for expandable video details
    window.toggleVideoDetails = function(button) {
        const content = button.closest('.video-details-toggle').nextElementSibling;
        const icon = button.querySelector('.toggle-icon');
        const text = button.querySelector('.toggle-text');
        
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            button.classList.remove('expanded');
            text.textContent = 'Show more';
        } else {
            content.classList.add('expanded');
            button.classList.add('expanded');
            text.textContent = 'Show less';
        }
    };
    
    // Professional Video Modal for Tile Layout
    window.openProfessionalVideoModal = function(videoUrl, videoTitle, videoDescription, videoCategory) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 p-4';
        modal.innerHTML = `
            <div class="w-full max-w-4xl bg-white rounded-xl overflow-hidden shadow-2xl animate-fade-in">
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">${videoTitle}</h2>
                        <span class="inline-block mt-1 px-3 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-purple-500 to-pink-500 text-white">
                            ${videoCategory}
                        </span>
                    </div>
                    <button class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200" onclick="this.closest('.fixed').remove(); document.body.style.overflow = '';">
                        <svg class="w-6 h-6 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                
                <div class="aspect-video bg-black">
                    <video-js 
                        id="modal-video-${Date.now()}"
                        class="vjs-default-skin w-full h-full"
                        controls
                        autoplay
                        preload="metadata"
                        data-setup='{"fluid": true, "responsive": true, "playbackRates": [0.5, 1, 1.25, 1.5, 2]}'>
                        <source src="${videoUrl}" type="video/mp4">
                        <p class="vjs-no-js">To view this video please enable JavaScript.</p>
                    </video-js>
                </div>
                
                <div class="p-6">
                    <p class="text-gray-700 leading-relaxed">${videoDescription}</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        // Initialize Video.js
        setTimeout(() => {
            const videoId = modal.querySelector('video-js').id;
            const player = videojs(videoId, {
                fluid: true,
                responsive: true,
                volume: 1.0,
                muted: false
            });
            
            player.ready(function() {
                this.volume(1.0);
                this.muted(false);
            });
        }, 100);
        
        // Close on escape key
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                modal.remove();
                document.body.style.overflow = '';
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                document.body.style.overflow = '';
            }
        });
    };

    // Legacy function for compatibility
    window.openVideoModal = window.openProfessionalVideoModal;
        const modal = document.createElement('div');
        modal.className = 'video-tile-modal';
        modal.innerHTML = `
            <div class="video-tile-modal-content">
                <div class="video-tile-modal-header">
                    <div class="video-tile-modal-title-section">
                        <h2>${videoTitle}</h2>
                        <div class="video-tile-modal-meta">
                            <span class="video-category-badge">${videoCategory}</span>
                        </div>
                    </div>
                    <button class="video-tile-modal-close">&times;</button>
                </div>
                <div class="video-tile-modal-player">
                    <video controls playsinline style="width: 100%; height: 100%; background: #000;">
                        <source src="${videoUrl}" type="video/mp4">
                        <source src="${videoUrl}" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="video-tile-modal-info">
                    <p>${videoDescription}</p>
                </div>
            </div>
        `;
        
        // Add tile modal styles
        const modalStyles = document.createElement('style');
        modalStyles.textContent = `
            .video-tile-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                animation: modalFadeIn 0.3s ease;
            }
            
            @keyframes modalFadeIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
            
            .video-tile-modal-content {
                width: 90%;
                max-width: 1000px;
                background: #fff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 25px 80px rgba(0,0,0,0.9);
                animation: modalSlideUp 0.3s ease;
            }
            
            @keyframes modalSlideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            .video-tile-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 1.5rem;
                background: #fff;
                border-bottom: 1px solid rgba(0,0,0,0.1);
            }
            
            .video-tile-modal-title-section h2 {
                margin: 0 0 0.5rem 0;
                font-size: 1.5rem;
                font-weight: 700;
                color: #1a1a1a;
                line-height: 1.3;
            }
            
            .video-tile-modal-meta {
                display: flex;
                gap: 0.75rem;
                align-items: center;
            }
            
            .video-tile-modal-close {
                background: none;
                border: none;
                color: #666;
                font-size: 2.5rem;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                transition: color 0.2s;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .video-tile-modal-close:hover {
                color: #333;
            }
            
            .video-tile-modal-player {
                aspect-ratio: 16/9;
                background: #000;
                position: relative;
            }
            
            .video-tile-modal-player video {
                border: none;
                outline: none;
            }
            
            .video-tile-modal-info {
                padding: 1.5rem;
                background: #fff;
            }
            
            .video-tile-modal-info p {
                margin: 0;
                line-height: 1.6;
                color: #444;
                font-size: 1rem;
            }
            
            @media (max-width: 768px) {
                .video-tile-modal-content {
                    width: 95%;
                    max-height: 90vh;
                    overflow-y: auto;
                }
                
                .video-tile-modal-header {
                    padding: 1rem;
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .video-tile-modal-close {
                    align-self: flex-end;
                    margin-top: -10px;
                }
                
                .video-tile-modal-title-section h2 {
                    font-size: 1.25rem;
                }
                
                .video-tile-modal-info {
                    padding: 1rem;
                }
            }
        `;
        
        document.head.appendChild(modalStyles);
        document.body.appendChild(modal);
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
        
        // Close modal function
        function closeModal() {
            document.body.removeChild(modal);
            document.head.removeChild(modalStyles);
            document.body.style.overflow = '';
        }
        
        // Close modal handlers
        modal.querySelector('.video-tile-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        
        // ESC key to close
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
        
        // Auto-play the video when modal opens
        setTimeout(() => {
            const video = modal.querySelector('video');
            if (video) {
                video.muted = false;
                video.play().catch(e => {
                    console.log('Auto-play blocked, user interaction required');
                });
            }
        }, 100);
    };

    // Global fullscreen function
    window.openVideoFullscreen = function(videoUrl, videoTitle) {
        const modal = document.createElement('div');
        modal.className = 'video-fullscreen-modal';
        modal.innerHTML = `
            <div class="video-fullscreen-content">
                <div class="video-fullscreen-header">
                    <h3>${videoTitle}</h3>
                    <button class="video-fullscreen-close">&times;</button>
                </div>
                <div class="video-fullscreen-player">
                    <video controls playsinline style="width: 100%; height: 100%; background: #000;">
                        <source src="${videoUrl}" type="video/mp4">
                        <source src="${videoUrl}" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        `;
        
        // Add fullscreen modal styles
        const modalStyles = document.createElement('style');
        modalStyles.textContent = `
            .video-fullscreen-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.95);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            .video-fullscreen-content {
                width: 95%;
                max-width: 1200px;
                background: #000;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            }
            
            .video-fullscreen-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.5rem;
                background: #1a1a1a;
                color: white;
                border-bottom: 1px solid #333;
            }
            
            .video-fullscreen-header h3 {
                margin: 0;
                font-size: 1.2rem;
                font-weight: 600;
            }
            
            .video-fullscreen-close {
                background: none;
                border: none;
                color: white;
                font-size: 2rem;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                transition: opacity 0.2s;
            }
            
            .video-fullscreen-close:hover {
                opacity: 0.7;
            }
            
            .video-fullscreen-player {
                aspect-ratio: 16/9;
                background: #000;
                position: relative;
            }
            
            .video-fullscreen-player video {
                border: none;
                outline: none;
            }
            
            @media (max-width: 768px) {
                .video-fullscreen-content {
                    width: 100%;
                    height: 100%;
                    border-radius: 0;
                }
                
                .video-fullscreen-header {
                    padding: 0.75rem 1rem;
                }
                
                .video-fullscreen-header h3 {
                    font-size: 1rem;
                }
            }
        `;
        
        document.head.appendChild(modalStyles);
        document.body.appendChild(modal);
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
        
        // Close modal function
        function closeModal() {
            document.body.removeChild(modal);
            document.head.removeChild(modalStyles);
            document.body.style.overflow = '';
        }
        
        // Close modal handlers
        modal.querySelector('.video-fullscreen-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        
        // ESC key to close
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    };
    </script>
    
    <!-- Search Functionality -->
    <script>
    console.log('🔍 Search script is loading...');
    
    // Global debug function for manual testing
    window.debugVideoSearch = function() {
        console.log('🔍 Manual search debug triggered');
        console.log('🔍 All inputs on page:', document.querySelectorAll('input'));
        console.log('🔍 All elements with data-library-id:', document.querySelectorAll('[data-library-id]'));
        console.log('🔍 All elements with video-search-input class:', document.querySelectorAll('.video-search-input'));
        console.log('🔍 Library container:', document.querySelector('#<?php echo esc_js($library_id); ?>'));
        return initializeSearch();
    };
    
    // Initialize search immediately
    function initializeSearch() {
        console.log('🔍 Initializing search functionality...');
        
        const libraryId = '<?php echo esc_js($library_id); ?>';
        console.log('🔍 Library ID:', libraryId);
        
        // Try multiple selectors to find the search input
        let searchInput = document.querySelector(`[data-library-id="${libraryId}"].video-search-input`);
        if (!searchInput) {
            searchInput = document.querySelector('.video-search-input');
            console.log('🔍 Found search input with fallback selector:', !!searchInput);
        } else {
            console.log('🔍 Found search input with library ID selector:', !!searchInput);
        }
        
        const clearButton = document.querySelector('.video-search-clear');
        const videoCountElement = document.querySelector('.video-count');
        const videoCountLabel = document.querySelector('.video-count-label');
        
        console.log('🔍 Elements found:', {
            searchInput: !!searchInput,
            clearButton: !!clearButton,
            videoCountElement: !!videoCountElement,
            videoCountLabel: !!videoCountLabel
        });
        
        if (!searchInput) {
            console.error('🔍 Search input not found for library:', libraryId);
            console.log('🔍 Available inputs:', document.querySelectorAll('input').length);
            console.log('🔍 Available elements with data-library-id:', document.querySelectorAll('[data-library-id]').length);
            return false;
        }
        
        // Get all video items based on layout
        function getAllVideoItems() {
            const layout = document.querySelector(`#${libraryId}`).getAttribute('data-layout');
            
            if (layout === 'tube') {
                // For tube layout, get sidebar videos (main video doesn't change)
                return document.querySelectorAll(`#up-next-container-${libraryId} > div[data-video-url]`);
            } else if (layout === 'tile') {
                // For tile layout, get all video tiles from the grid
                return document.querySelectorAll(`#${libraryId} .grid div[data-video-url]`);
            } else if (layout === 'gallery') {
                // For gallery layout, get thumbnail items
                return document.querySelectorAll(`#${libraryId} .gallery-thumbnail[data-video-url]`);
            }
            
            return [];
        }
        
        // Filter videos based on search term
        function filterVideos(searchTerm) {
            const videoItems = getAllVideoItems();
            const layout = document.querySelector(`#${libraryId}`).getAttribute('data-layout');
            let visibleCount = 0;
            
            console.log('Filtering videos:', { 
                searchTerm, 
                layout, 
                videoItemsCount: videoItems.length,
                libraryId 
            });
            
            searchTerm = searchTerm.toLowerCase().trim();
            
            // Handle category filter if it exists
            const categoryFilter = document.querySelector(`[data-library-id="${libraryId}"].video-category-filter`);
            const selectedCategory = categoryFilter ? categoryFilter.value.toLowerCase() : '';
            
            videoItems.forEach(item => {
                const title = (item.getAttribute('data-video-title') || '').toLowerCase();
                const description = (item.getAttribute('data-video-description') || '').toLowerCase();
                const category = (item.getAttribute('data-video-category') || '').toLowerCase();
                
                const searchMatches = !searchTerm || 
                                     title.includes(searchTerm) || 
                                     description.includes(searchTerm) ||
                                     category.includes(searchTerm);
                
                const categoryMatches = !selectedCategory || category.includes(selectedCategory);
                
                const matches = searchMatches && categoryMatches;
                
                if (matches) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // For tube layout, add 1 for the main video if it matches
            if (layout === 'tube') {
                const mainVideoSection = document.querySelector('.video-section-container');
                if (mainVideoSection) {
                    const mainTitle = (mainVideoSection.querySelector('h1')?.textContent || '').toLowerCase();
                    const mainDescription = (mainVideoSection.querySelector('.text-gray-700 p')?.textContent || '').toLowerCase();
                    const mainCategory = (mainVideoSection.querySelector('.bg-gradient-to-r')?.textContent || '').toLowerCase();
                    
                    const mainSearchMatches = !searchTerm || 
                                             mainTitle.includes(searchTerm) || 
                                             mainDescription.includes(searchTerm) ||
                                             mainCategory.includes(searchTerm);
                    
                    const mainCategoryMatches = !selectedCategory || mainCategory.includes(selectedCategory);
                    
                    if (mainSearchMatches && mainCategoryMatches) {
                        visibleCount++;
                    }
                }
            }
            
            // Update video count
            if (videoCountElement) {
                videoCountElement.textContent = visibleCount;
            }
            
            // Update plural/singular
            if (videoCountLabel) {
                videoCountLabel.textContent = visibleCount === 1 ? 'video' : 'videos';
            }
            
            // Show/hide clear button
            if (clearButton) {
                clearButton.style.display = searchTerm ? 'block' : 'none';
            }
            
            // Show "no results" message for tile layout
            if (layout === 'tile') {
                showNoResultsMessage(visibleCount === 0 && searchTerm);
            }
        }
        
        // Show/hide no results message for tile layout
        function showNoResultsMessage(show) {
            let noResultsElement = document.querySelector('.no-search-results');
            
            if (show && !noResultsElement) {
                // Create no results message
                const gridContainer = document.querySelector('.grid');
                if (gridContainer) {
                    noResultsElement = document.createElement('div');
                    noResultsElement.className = 'no-search-results col-span-full flex flex-col items-center justify-center py-16 text-center';
                    noResultsElement.innerHTML = `
                        <div class="text-6xl mb-4">🔍</div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Videos Found</h3>
                        <p class="text-gray-600">No videos match your search criteria.</p>
                    `;
                    gridContainer.appendChild(noResultsElement);
                }
            } else if (!show && noResultsElement) {
                noResultsElement.remove();
            }
        }
        
        // Handle search input
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value;
            console.log('Search term:', searchTerm);
            filterVideos(searchTerm);
        });
        
        // Handle clear button
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                filterVideos('');
                searchInput.focus();
            });
        }
        
        // Handle category filter changes (if it exists)
        const categoryFilter = document.querySelector(`[data-library-id="${libraryId}"].video-category-filter`);
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                // When category changes, reapply filters
                filterVideos(searchInput.value);
            });
        }
        
        // Initial filter if there's a pre-filled search
        if (searchInput.value) {
            console.log('🔍 Pre-filled search value:', searchInput.value);
            filterVideos(searchInput.value);
        }
        
        console.log('🔍 Search functionality initialized successfully!');
        return true;
    }
    
    // Try to initialize search on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔍 DOM loaded, attempting search initialization...');
        initializeSearch();
    });
    
    // Also try after a delay in case DOM elements are added dynamically
    setTimeout(() => {
        console.log('🔍 Retry search initialization after delay...');
        initializeSearch();
    }, 1000);
    
    // And try on window load
    window.addEventListener('load', function() {
        console.log('🔍 Window loaded, attempting search initialization...');
        initializeSearch();
    });
    </script>
    
    <?php
    return ob_get_clean();
}



/**
 * Get video categories from S3 videos
 */
function get_video_categories_from_s3($videos) {
    $categories = [];
    foreach ($videos as $video) {
        if (!empty($video->video_category)) {
            $categories[] = $video->video_category;
        }
    }
    return array_unique($categories);
}

/**
 * Get video thumbnail with fallback
 */
function get_video_thumbnail($s3_key) {
    // Return placeholder for now - could be enhanced to look for thumbnail files
    return VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
}

/**
 * Get fallback video thumbnail as data URI
 */
function get_fallback_video_thumbnail() {
    // Professional video placeholder as SVG data URI
    return 'data:image/svg+xml;base64,' . base64_encode('
    <svg viewBox="0 0 400 225" xmlns="http://www.w3.org/2000/svg">
        <rect width="400" height="225" fill="url(#gradient)"/>
        <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
            </linearGradient>
        </defs>
        <circle cx="200" cy="112.5" r="40" fill="rgba(255,255,255,0.2)" stroke="rgba(255,255,255,0.4)" stroke-width="2"/>
        <polygon points="185,95 185,130 220,112.5" fill="white"/>
        <text x="200" y="175" text-anchor="middle" fill="white" font-family="system-ui, sans-serif" font-size="14" opacity="0.8">Video Content</text>
    </svg>
    ');
}

/**
 * Render main video player for Tube layout with Video.js and Tailwind
 */
function render_main_video_player($video, $atts) {
    // Generate fallback description if none exists
    $description = !empty($video->post_excerpt) ? $video->post_excerpt : "A video about " . $video->post_title;
    $video_id = 'video-' . wp_generate_uuid4();
    
    ob_start();
    ?>
    <div class="space-y-4">
        <!-- Video Player with Video.js -->
        <div class="relative rounded-xl overflow-hidden shadow-xl bg-black" style="aspect-ratio: 16/9;">
            <video
                id="<?php echo esc_attr($video_id); ?>"
                class="video-js vjs-default-skin w-full h-full"
                controls
                preload="metadata"
                poster="<?php echo get_fallback_video_thumbnail(); ?>"
                muted="false"
                playsinline
                data-setup='{"fluid": false, "responsive": false, "fill": true, "muted": false}'>
                <source src="<?php echo esc_url($video->video_url); ?>" type="video/mp4">
                <p class="vjs-no-js">
                    To view this video please enable JavaScript, and consider upgrading to a web browser that
                    <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                </p>
            </video>
        </div>
        

        
        <!-- Video Information Panel - Compact -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4">
            <!-- Title and Actions -->
            <div class="flex justify-between items-start mb-3 gap-4">
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900 leading-tight flex-1">
                    <?php echo esc_html($video->post_title); ?>
                </h1>
                <div class="flex gap-2 flex-shrink-0">
                    <button class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors duration-200 group">
                        <svg class="w-4 h-4 text-gray-600 group-hover:text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                    <button class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors duration-200 group">
                        <svg class="w-4 h-4 text-gray-600 group-hover:text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                            <polyline points="16,6 12,2 8,6"/>
                            <line x1="12" y1="2" x2="12" y2="15"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-purple-500 to-pink-500 text-white">
                    <?php echo esc_html($video->video_category); ?>
                </span>
                <?php if ($video->video_duration): ?>
                <div class="flex items-center text-gray-600 text-sm">
                    <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12,6 12,12 16,14"/>
                    </svg>
                    <?php echo esc_html($video->video_duration); ?>
                </div>
                <?php endif; ?>
                <div class="flex items-center text-gray-600 text-sm">
                    <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <?php echo date('M j, Y', strtotime($video->post_date)); ?>
                </div>
            </div>
            
            <!-- Description -->
            <div class="text-gray-700 leading-relaxed text-sm">
                <p><?php echo esc_html($description); ?></p>
            </div>
        </div>
    </div>
    
    <script>
    // Initialize Video.js player
    function initVideoPlayer() {
        const videoElement = document.getElementById('<?php echo esc_js($video_id); ?>');
        
        if (!videoElement || typeof videojs === 'undefined') {
            return false;
        }
        
        if (window.mainVideoPlayer) {
            return true;
        }
        
        try {
            // Check if Video.js has already initialized this element
            const existingPlayer = videojs.getPlayer('<?php echo esc_js($video_id); ?>');
            if (existingPlayer) {
                window.mainVideoPlayer = existingPlayer;
                existingPlayer.muted(false);
                existingPlayer.volume(1.0);
                return true;
            }
            
            // Create new Video.js player
            window.mainVideoPlayer = videojs('<?php echo esc_js($video_id); ?>', {
                fluid: false,
                responsive: false,
                fill: true,
                aspectRatio: '16:9',
                muted: false,
                preload: 'metadata',
                controls: true
            });
            
            // Set up player when ready
            window.mainVideoPlayer.ready(function() {
                this.muted(false);
                this.volume(1.0);
            });
            
            return true;
        } catch (error) {
            console.error('Video.js initialization failed:', error);
            return false;
        }
    }
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Try immediately
        if (!initVideoPlayer()) {
            // Try again after 1 second
            setTimeout(() => {
                if (!initVideoPlayer()) {
                    // Try again after 3 seconds
                    setTimeout(() => initVideoPlayer(), 3000);
                }
            }, 1000);
        }
        
        // Set up HTML5 video element directly
        const videoElement = document.getElementById('<?php echo esc_js($video_id); ?>');
        if (videoElement) {
            videoElement.muted = false;
            videoElement.volume = 1.0;
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * Render sidebar video item with Tailwind CSS
 */
function render_sidebar_video_item($video, $index, $atts) {
    ob_start();
    ?>
    <div class="flex gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200 cursor-pointer group" 
         data-video-url="<?php echo esc_attr($video->video_url); ?>"
         data-video-title="<?php echo esc_attr($video->post_title); ?>"
         data-video-description="<?php echo esc_attr($video->post_excerpt); ?>"
         data-video-category="<?php echo esc_attr($video->video_category); ?>"
         onclick="switchVideo(this)">
        
        <!-- Thumbnail -->
        <div class="relative flex-shrink-0 w-24 h-16 rounded-lg overflow-hidden bg-gray-200">
            <img src="<?php echo !empty($video->video_thumbnail) ? esc_url($video->video_thumbnail) : get_fallback_video_thumbnail(); ?>" 
                 alt="<?php echo esc_attr($video->post_title); ?>"
                 class="w-full h-full object-cover"
                 onerror="this.src='<?php echo get_fallback_video_thumbnail(); ?>'">
            
            <!-- Play icon overlay -->
            <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-20 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
            
            <?php if ($video->video_duration): ?>
            <div class="absolute bottom-1 right-1 bg-black bg-opacity-80 text-white text-xs px-1 py-0.5 rounded">
                <?php echo esc_html($video->video_duration); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Video Info -->
        <div class="flex-1 min-w-0">
            <h5 class="text-sm font-medium text-gray-900 line-clamp-2 group-hover:text-blue-600 transition-colors duration-200">
                <?php echo esc_html($video->post_title); ?>
            </h5>
            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full font-medium">
                    <?php echo esc_html($video->video_category); ?>
                </span>
                <span><?php echo date('M j, Y', strtotime($video->post_date)); ?></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render tile video item with Tailwind CSS and professional design
 */
function render_tile_video_item($video, $atts) {
    // Generate fallback description if none exists
    $description = !empty($video->post_excerpt) ? $video->post_excerpt : "A video about " . $video->post_title;
    
    ob_start();
    ?>
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer group" 
         data-video-url="<?php echo esc_attr($video->video_url); ?>"
         data-video-title="<?php echo esc_attr($video->post_title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-category="<?php echo esc_attr($video->video_category); ?>"
         onclick="switchVideo(this)">
        
        <!-- Video Thumbnail -->
        <div class="relative aspect-video bg-gray-200 overflow-hidden">
            <img src="<?php echo !empty($video->video_thumbnail) ? esc_url($video->video_thumbnail) : get_fallback_video_thumbnail(); ?>" 
                 alt="<?php echo esc_attr($video->post_title); ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                 onerror="this.src='<?php echo get_fallback_video_thumbnail(); ?>'">
            
            <!-- Play Overlay -->
            <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <div class="bg-white bg-opacity-90 rounded-full p-4 shadow-lg transform scale-90 group-hover:scale-100 transition-transform duration-300">
                    <svg class="w-8 h-8 text-gray-900 ml-1" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>
            
            <!-- Duration Badge -->
            <?php if ($video->video_duration): ?>
            <div class="absolute bottom-2 right-2 bg-black bg-opacity-80 text-white text-xs px-2 py-1 rounded-md font-medium">
                <?php echo esc_html($video->video_duration); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Video Information -->
        <div class="p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-blue-600 transition-colors duration-200">
                <?php echo esc_html($video->post_title); ?>
            </h3>
            
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-pink-500 text-white">
                    <?php echo esc_html($video->video_category); ?>
                </span>
                <span class="text-xs text-gray-500">
                    <?php echo date('M j, Y', strtotime($video->post_date)); ?>
                </span>
            </div>
            
            <p class="text-sm text-gray-600 line-clamp-2 leading-relaxed">
                <?php echo esc_html($description); ?>
            </p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render gallery main player with professional title display
 */
function render_gallery_main_player($video, $atts) {
    // Generate fallback description if none exists
    $description = !empty($video->post_excerpt) ? $video->post_excerpt : "A video about " . $video->post_title;
    
    ob_start();
    ?>
    <div class="gallery-section-professional">
        <!-- Professional Title Above Video -->
        <div class="gallery-title-section">
            <h1 class="gallery-main-title"><?php echo esc_html($video->post_title); ?></h1>
            <div class="gallery-main-meta">
                <span class="video-category-badge"><?php echo esc_html($video->video_category); ?></span>
                <?php if ($video->video_duration): ?>
                <span class="video-duration-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12,6 12,12 16,14"/>
                    </svg>
                    <?php echo esc_html($video->video_duration); ?>
                </span>
                <?php endif; ?>
                <span class="video-date-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <?php echo date('M j, Y', strtotime($video->post_date)); ?>
                </span>
            </div>
            <div class="gallery-main-description">
                <p><?php echo esc_html($description); ?></p>
            </div>
        </div>
        
        <!-- Main Video Player -->
        <div class="gallery-player-wrapper" data-video-url="<?php echo esc_attr($video->video_url); ?>" data-video-title="<?php echo esc_attr($video->post_title); ?>">
            <video class="video-player-gallery" 
                   controls 
                   preload="metadata"
                   playsinline
                   muted="false"
                   poster="<?php echo !empty($video->video_thumbnail) ? esc_url($video->video_thumbnail) : get_fallback_video_thumbnail(); ?>"
                   style="width: 100%; height: 100%; object-fit: cover; background: #000;"
                   onloadedmetadata="this.muted = false; this.volume = 1.0; console.log('🔊 Gallery video loaded - audio enabled');">
                <source src="<?php echo esc_url($video->video_url); ?>" type="video/mp4">
                <source src="<?php echo esc_url($video->video_url); ?>" type="video/webm">
                Your browser does not support the video tag.
            </video>
            
            <!-- Fullscreen button -->
            <button class="video-fullscreen-btn" onclick="openVideoFullscreen('<?php echo esc_js($video->video_url); ?>', '<?php echo esc_js($video->post_title); ?>')">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

/**
 * Render gallery thumbnail
 */
function render_gallery_thumbnail($video, $index, $atts) {
    ob_start();
    ?>
    <div class="gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
         style="background-image: url('<?php echo !empty($video->video_thumbnail) ? esc_url($video->video_thumbnail) : get_fallback_video_thumbnail(); ?>');"
         data-video-url="<?php echo esc_attr($video->video_url); ?>"
         data-video-title="<?php echo esc_attr($video->post_title); ?>"
         data-video-description="<?php echo esc_attr($video->post_excerpt); ?>"
         data-video-category="<?php echo esc_attr($video->video_category); ?>"
         data-video-index="<?php echo esc_attr($index); ?>"
         onclick="switchVideo(this)">
        
        <div class="gallery-thumbnail-play">▶</div>
        
        <?php if ($video->video_duration): ?>
        <div class="gallery-thumbnail-duration"><?php echo esc_html($video->video_duration); ?></div>
        <?php endif; ?>
        
        <div class="gallery-thumbnail-title"><?php echo esc_html($video->post_title); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render just the video player for tube layout
 */
function render_tube_video_player($video, $atts) {
    $video_id = 'video-' . wp_generate_uuid4();
    
    ob_start();
    ?>
    <div class="relative rounded-xl overflow-hidden shadow-xl bg-black w-full h-full">
        <video
            id="<?php echo esc_attr($video_id); ?>"
            class="video-js vjs-default-skin w-full h-full"
            controls
            preload="metadata"
            muted="false"
            playsinline
            poster="<?php echo get_fallback_video_thumbnail(); ?>"
            data-setup='{"muted": false, "fluid": false, "responsive": false, "fill": true}'>
            <source src="<?php echo esc_url($video->video_url); ?>" type="video/mp4">
            <p class="vjs-no-js">
                To view this video please enable JavaScript, and consider upgrading to a web browser that
                <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
            </p>
        </video>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Video.js player with proper sizing and audio settings
        window.mainVideoPlayer = videojs('<?php echo esc_js($video_id); ?>', {
            fluid: false,
            responsive: false,
            fill: true,
            aspectRatio: '16:9',
            muted: false,
            preload: 'metadata',
            playsinline: true,
            controls: true
        });
        
        window.mainVideoPlayer.ready(function() {
            console.log('Video.js player ready:', this.id());
            console.log('switchVideo function available:', typeof window.switchVideo === 'function');
            
            // Ensure proper sizing and audio settings
            this.dimensions('100%', '100%');
            this.muted(false);
            this.volume(1.0);
            
            console.log('🔊 Tube layout player - audio enabled');
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * Render video info panel for tube layout
 */
function render_tube_video_info($video, $atts) {
    // Generate fallback description if none exists
    $description = !empty($video->post_excerpt) ? $video->post_excerpt : "A video about " . $video->post_title;
    
    ob_start();
    ?>
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4 mt-4">
        <!-- Title and Actions -->
        <div class="flex justify-between items-start mb-3 gap-4">
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900 leading-tight flex-1">
                <?php echo esc_html($video->post_title); ?>
            </h1>
            <div class="flex gap-2 flex-shrink-0">
                <button class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors duration-200 group">
                    <svg class="w-4 h-4 text-gray-600 group-hover:text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
                <button class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors duration-200 group">
                    <svg class="w-4 h-4 text-gray-600 group-hover:text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16,6 12,2 8,6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Metadata -->
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-purple-500 to-pink-500 text-white">
                <?php echo esc_html($video->video_category); ?>
            </span>
            <?php if ($video->video_duration): ?>
            <div class="flex items-center text-gray-600 text-sm">
                <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
                <?php echo esc_html($video->video_duration); ?>
            </div>
            <?php endif; ?>
            <div class="flex items-center text-gray-600 text-sm">
                <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <?php echo date('M j, Y', strtotime($video->post_date)); ?>
            </div>
        </div>
        
        <!-- Description -->
        <div class="text-gray-700 leading-relaxed text-sm">
            <p><?php echo esc_html($description); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
} 