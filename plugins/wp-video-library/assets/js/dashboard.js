/* ==========================================================================
   Video Library - Dashboard JavaScript
   ========================================================================== */

const VideoLibraryDashboard = {
    // Core elements
    mainPlayer: null,
    mainVideo: null,
    currentVideoData: null,
    sidebarList: null,
    modal: null,
    modalVideo: null,
    
    // State management
    isLoading: false,
    currentPage: 1,
    hasMoreVideos: true,
    currentFilters: {
        search: '',
        category: '',
        path: ''
    },

    // Initialize the dashboard
    init() {
        console.log('🎬 Video Library Dashboard initializing...');
        
        this.bindElements();
        this.bindEvents();
        this.initializePlayer();
        this.setupKeyboardShortcuts();
        
        console.log('✅ Dashboard initialized successfully');
    },

    // Bind DOM elements
    bindElements() {
        this.mainPlayer = document.getElementById('dashboard-main-player');
        this.mainVideo = document.getElementById('main-dashboard-video');
        this.sidebarList = document.getElementById('dashboard-video-list');
        this.modal = document.getElementById('video-dashboard-modal');
        this.modalVideo = document.getElementById('dashboard-modal-video');
        
        // Search and filter elements
        this.searchInput = document.getElementById('video-dashboard-search');
        this.searchBtn = document.getElementById('video-dashboard-search-btn');
        this.categoryFilter = document.getElementById('video-dashboard-category');
        this.pathFilter = document.getElementById('video-dashboard-path');
        this.loadMoreBtn = document.getElementById('load-more-dashboard-videos');
    },

    // Bind all event listeners
    bindEvents() {
        // Main player events
        this.bindPlayerEvents();
        
        // Sidebar events
        this.bindSidebarEvents();
        
        // Search and filter events
        this.bindFilterEvents();
        
        // Modal events
        this.bindModalEvents();
        
        // Load more events
        this.bindLoadMoreEvents();
    },

    // Bind main player events
    bindPlayerEvents() {
        if (!this.mainPlayer) return;

        // Thumbnail click to play
        const thumbnail = this.mainPlayer.querySelector('.main-video-thumbnail');
        if (thumbnail) {
            thumbnail.addEventListener('click', () => {
                this.playMainVideo();
            });
        }

        // Video events
        if (this.mainVideo) {
            this.mainVideo.addEventListener('loadeddata', () => {
                console.log('📹 Main video loaded');
                this.hideLoader();
            });

            this.mainVideo.addEventListener('error', (e) => {
                console.error('❌ Main video error:', e);
                this.handleVideoError();
            });

            this.mainVideo.addEventListener('play', () => {
                console.log('▶️ Main video playing');
                this.hideThumbnail();
                this.trackVideoPlay();
            });

            this.mainVideo.addEventListener('pause', () => {
                console.log('⏸️ Main video paused');
            });

            this.mainVideo.addEventListener('ended', () => {
                console.log('🏁 Main video ended');
                this.playNextVideo();
            });
        }

        // Fullscreen button
        const fullscreenBtn = document.getElementById('play-fullscreen-btn');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => {
                this.openFullscreen();
            });
        }
    },

    // Bind sidebar events
    bindSidebarEvents() {
        if (!this.sidebarList) return;

        // Delegate click events for sidebar items
        this.sidebarList.addEventListener('click', (e) => {
            const sidebarItem = e.target.closest('.sidebar-video-item');
            if (sidebarItem) {
                e.preventDefault();
                this.switchMainVideo(sidebarItem);
            }
        });

        // Smooth scrolling for sidebar
        this.sidebarList.addEventListener('scroll', this.throttle(() => {
            this.handleSidebarScroll();
        }, 100));
    },

    // Bind search and filter events
    bindFilterEvents() {
        // Search input with debouncing
        if (this.searchInput) {
            let searchTimeout;
            this.searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch();
                }, 300);
            });

            // Enter key support
            this.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.performSearch();
                }
            });
        }

        // Search button
        if (this.searchBtn) {
            this.searchBtn.addEventListener('click', () => {
                this.performSearch();
            });
        }

        // Filter dropdowns
        [this.categoryFilter, this.pathFilter].forEach(filter => {
            if (filter) {
                filter.addEventListener('change', () => {
                    this.applyFilters();
                });
            }
        });
    },

    // Bind modal events
    bindModalEvents() {
        if (!this.modal) return;

        // Close button
        const closeBtn = document.getElementById('dashboard-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.closeModal();
            });
        }

        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Modal video events
        if (this.modalVideo) {
            this.modalVideo.addEventListener('loadeddata', () => {
                console.log('📹 Modal video loaded');
            });

            this.modalVideo.addEventListener('error', (e) => {
                console.error('❌ Modal video error:', e);
                this.showError('Unable to load video. Please try again.');
            });
        }
    },

    // Bind load more events
    bindLoadMoreEvents() {
        if (this.loadMoreBtn) {
            this.loadMoreBtn.addEventListener('click', () => {
                this.loadMoreVideos();
            });
        }
    },

    // Initialize player with first video
    initializePlayer() {
        const firstVideo = this.sidebarList?.querySelector('.sidebar-video-item');
        if (firstVideo) {
            this.setActiveVideo(firstVideo);
            this.currentVideoData = this.extractVideoData(firstVideo);
        }
    },

    // Play main video
    playMainVideo() {
        if (!this.mainVideo || !this.currentVideoData) {
            console.warn('⚠️ No video data available');
            return;
        }

        this.showLoader();
        
        // Check if URL needs refreshing (for S3 presigned URLs)
        if (this.needsUrlRefresh(this.currentVideoData.videoUrl)) {
            this.refreshVideoUrl();
            return;
        }

        // Set video source and play
        this.mainVideo.src = this.currentVideoData.videoUrl;
        this.mainVideo.style.display = 'block';
        
        this.mainVideo.play().catch(e => {
            console.warn('⚠️ Auto-play prevented or failed:', e);
            this.hideLoader();
        });
    },

    // Switch main video when sidebar item is clicked
    switchMainVideo(sidebarItem) {
        if (!sidebarItem) return;

        console.log('🔄 Switching video:', sidebarItem.dataset.videoTitle);
        
        // Extract new video data
        const newVideoData = this.extractVideoData(sidebarItem);
        
        // Update main player
        this.updateMainPlayer(newVideoData);
        
        // Update sidebar active state
        this.setActiveVideo(sidebarItem);
        
        // Store current video data
        this.currentVideoData = newVideoData;
        
        // Smooth scroll to top of main player
        this.smoothScrollToPlayer();
    },

    // Update main player with new video data
    updateMainPlayer(videoData) {
        if (!this.mainPlayer || !videoData) return;

        // Stop current video
        if (this.mainVideo) {
            this.mainVideo.pause();
            this.mainVideo.style.display = 'none';
            this.mainVideo.src = '';
        }

        // Update thumbnail
        const thumbnail = this.mainPlayer.querySelector('.main-video-thumbnail');
        if (thumbnail) {
            thumbnail.style.backgroundImage = `url('${videoData.thumbnailUrl || ''}')`;
            thumbnail.classList.remove('hidden');
        }

        // Update duration
        const duration = this.mainPlayer.querySelector('.main-video-duration');
        if (duration) {
            duration.textContent = videoData.videoDuration || 'Unknown';
        }

        // Update video info
        this.updateVideoInfo(videoData);
    },

    // Update video information panel
    updateVideoInfo(videoData) {
        const infoPanel = document.getElementById('dashboard-video-info');
        if (!infoPanel) return;

        // Update title
        const title = infoPanel.querySelector('.video-info-title');
        if (title) {
            title.textContent = videoData.videoTitle || 'Untitled';
        }

        // Update meta information
        const metaItems = infoPanel.querySelectorAll('.video-meta-item');
        if (metaItems.length >= 3) {
            metaItems[0].innerHTML = `<span class="dashicons dashicons-calendar-alt"></span> ${videoData.videoDate || 'Unknown'}`;
            metaItems[1].innerHTML = `<span class="dashicons dashicons-clock"></span> ${videoData.videoDuration || 'Unknown'}`;
            metaItems[2].innerHTML = `<span class="dashicons dashicons-database"></span> ${videoData.videoSize || 'Unknown'}`;
        }

        // Update description
        const description = infoPanel.querySelector('.video-info-description p');
        if (description) {
            description.textContent = videoData.videoDescription || 'No description available.';
        }
    },

    // Set active video in sidebar
    setActiveVideo(videoItem) {
        // Remove active class from all items
        const allItems = this.sidebarList?.querySelectorAll('.sidebar-video-item');
        allItems?.forEach(item => item.classList.remove('active'));
        
        // Add active class to selected item
        if (videoItem) {
            videoItem.classList.add('active');
        }
    },

    // Play next video automatically
    playNextVideo() {
        const activeItem = this.sidebarList?.querySelector('.sidebar-video-item.active');
        const nextItem = activeItem?.nextElementSibling;
        
        if (nextItem && nextItem.classList.contains('sidebar-video-item')) {
            console.log('⏭️ Auto-playing next video');
            setTimeout(() => {
                this.switchMainVideo(nextItem);
                setTimeout(() => this.playMainVideo(), 500);
            }, 1000);
        } else {
            console.log('🏁 No more videos to play');
            this.showThumbnail();
        }
    },

    // Perform search
    performSearch() {
        const searchTerm = this.searchInput?.value.trim() || '';
        this.currentFilters.search = searchTerm;
        this.currentPage = 1;
        this.loadVideos(true);
    },

    // Apply filters
    applyFilters() {
        this.currentFilters.category = this.categoryFilter?.value || '';
        this.currentFilters.path = this.pathFilter?.value || '';
        this.currentPage = 1;
        this.loadVideos(true);
    },

    // Load videos with current filters
    loadVideos(replace = false) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoader();

        const formData = new FormData();
        formData.append('action', 'filter_videos');
        formData.append('nonce', videoLibraryDashboard.nonce);
        formData.append('search', this.currentFilters.search);
        formData.append('category', this.currentFilters.category);
        formData.append('path', this.currentFilters.path);
        formData.append('page', this.currentPage);

        fetch(videoLibraryDashboard.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.handleVideosLoaded(data.data, replace);
            } else {
                this.showError(data.data || 'Failed to load videos');
            }
        })
        .catch(error => {
            console.error('❌ Error loading videos:', error);
            this.showError('Network error. Please try again.');
        })
        .finally(() => {
            this.isLoading = false;
            this.hideLoader();
        });
    },

    // Handle loaded videos
    handleVideosLoaded(data, replace = false) {
        if (!this.sidebarList) return;

        if (replace) {
            this.sidebarList.innerHTML = data.html;
        } else {
            this.sidebarList.insertAdjacentHTML('beforeend', data.html);
        }

        // Update video count
        this.updateVideoCount(data.total || 0);
        
        // Update load more button
        this.updateLoadMoreButton(data.hasMore);
        
        // If this is the first load, initialize player
        if (replace && !this.currentVideoData) {
            this.initializePlayer();
        }
    },

    // Load more videos
    loadMoreVideos() {
        if (!this.hasMoreVideos || this.isLoading) return;
        
        this.currentPage++;
        this.loadVideos(false);
    },

    // Update video count display
    updateVideoCount(count) {
        const countDisplay = document.getElementById('video-count-display');
        if (countDisplay) {
            countDisplay.textContent = `${count} video${count !== 1 ? 's' : ''}`;
        }
    },

    // Update load more button
    updateLoadMoreButton(hasMore) {
        this.hasMoreVideos = hasMore;
        if (this.loadMoreBtn) {
            this.loadMoreBtn.style.display = hasMore ? 'block' : 'none';
        }
    },

    // Open fullscreen modal
    openFullscreen() {
        if (!this.modal || !this.currentVideoData) return;

        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Set modal video source
        this.modalVideo.src = this.currentVideoData.videoUrl;
        this.modalVideo.play().catch(e => {
            console.warn('⚠️ Modal auto-play prevented:', e);
        });
    },

    // Close modal
    closeModal() {
        if (!this.modal) return;

        this.modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Stop modal video
        if (this.modalVideo) {
            this.modalVideo.pause();
            this.modalVideo.src = '';
        }
    },

    // Setup keyboard shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Only handle shortcuts when not in input fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            switch (e.key) {
                case 'Escape':
                    if (this.modal?.classList.contains('active')) {
                        this.closeModal();
                    }
                    break;
                case ' ':
                case 'k':
                    e.preventDefault();
                    this.togglePlayPause();
                    break;
                case 'f':
                    e.preventDefault();
                    this.openFullscreen();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.selectPreviousVideo();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectNextVideo();
                    break;
            }
        });
    },

    // Toggle play/pause
    togglePlayPause() {
        const video = this.modal?.classList.contains('active') ? this.modalVideo : this.mainVideo;
        if (!video) return;

        if (video.paused) {
            video.play();
        } else {
            video.pause();
        }
    },

    // Select previous video
    selectPreviousVideo() {
        const activeItem = this.sidebarList?.querySelector('.sidebar-video-item.active');
        const prevItem = activeItem?.previousElementSibling;
        
        if (prevItem && prevItem.classList.contains('sidebar-video-item')) {
            this.switchMainVideo(prevItem);
        }
    },

    // Select next video
    selectNextVideo() {
        const activeItem = this.sidebarList?.querySelector('.sidebar-video-item.active');
        const nextItem = activeItem?.nextElementSibling;
        
        if (nextItem && nextItem.classList.contains('sidebar-video-item')) {
            this.switchMainVideo(nextItem);
        }
    },

    // Utility functions
    extractVideoData(element) {
        if (!element) return null;
        
        return {
            videoId: element.dataset.videoId,
            videoUrl: element.dataset.videoUrl,
            videoTitle: element.dataset.videoTitle,
            videoDescription: element.dataset.videoDescription,
            videoDuration: element.dataset.videoDuration,
            videoSize: element.dataset.videoSize,
            videoDate: element.dataset.videoDate,
            s3Key: element.dataset.s3Key,
            thumbnailUrl: this.extractThumbnailUrl(element)
        };
    },

    extractThumbnailUrl(element) {
        const thumbnail = element.querySelector('.sidebar-video-thumbnail, .main-video-thumbnail');
        if (thumbnail) {
            const bgImage = window.getComputedStyle(thumbnail).backgroundImage;
            const match = bgImage.match(/url\(["']?([^"']*)["']?\)/);
            return match ? match[1] : '';
        }
        return '';
    },

    needsUrlRefresh(url) {
        if (!url || !url.includes('Expires=')) return false;
        
        try {
            const urlParams = new URLSearchParams(url.split('?')[1]);
            const expires = parseInt(urlParams.get('Expires'));
            const now = Math.floor(Date.now() / 1000);
            return expires && expires < now;
        } catch (e) {
            return false;
        }
    },

    refreshVideoUrl() {
        if (!this.currentVideoData?.s3Key) return;

        const formData = new FormData();
        formData.append('action', 'get_fresh_presigned_url');
        formData.append('nonce', videoLibraryDashboard.nonce);
        formData.append('s3_key', this.currentVideoData.s3Key);

        fetch(videoLibraryDashboard.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.currentVideoData.videoUrl = data.data.url;
                this.playMainVideo();
            } else {
                this.showError('Unable to refresh video URL');
            }
        })
        .catch(error => {
            console.error('❌ Error refreshing URL:', error);
            this.showError('Network error');
        });
    },

    // UI helper functions
    showLoader() {
        // Add loading state to relevant elements
        const elements = [this.mainPlayer, this.sidebarList];
        elements.forEach(el => {
            if (el) el.classList.add('loading');
        });
    },

    hideLoader() {
        const elements = [this.mainPlayer, this.sidebarList];
        elements.forEach(el => {
            if (el) el.classList.remove('loading');
        });
    },

    hideThumbnail() {
        const thumbnail = this.mainPlayer?.querySelector('.main-video-thumbnail');
        if (thumbnail) {
            thumbnail.classList.add('hidden');
        }
    },

    showThumbnail() {
        const thumbnail = this.mainPlayer?.querySelector('.main-video-thumbnail');
        if (thumbnail) {
            thumbnail.classList.remove('hidden');
        }
    },

    handleVideoError() {
        this.showError('Video playback error. Please try selecting another video.');
        this.showThumbnail();
    },

    showError(message) {
        // Create temporary error notification
        const error = document.createElement('div');
        error.className = 'video-error-notification';
        error.textContent = message;
        error.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #d32f2f;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 10000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        `;
        
        document.body.appendChild(error);
        
        setTimeout(() => {
            error.remove();
        }, 5000);
    },

    handleSidebarScroll() {
        // Implement infinite scroll if needed
        const sidebar = this.sidebarList;
        if (!sidebar) return;
        
        const scrollTop = sidebar.scrollTop;
        const scrollHeight = sidebar.scrollHeight;
        const clientHeight = sidebar.clientHeight;
        
        // If near bottom and has more videos, load more
        if (scrollTop + clientHeight >= scrollHeight - 100 && this.hasMoreVideos && !this.isLoading) {
            this.loadMoreVideos();
        }
    },

    smoothScrollToPlayer() {
        if (this.mainPlayer) {
            this.mainPlayer.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    },

    trackVideoPlay() {
        // Send analytics event if analytics is enabled
        if (this.currentVideoData && typeof gtag !== 'undefined') {
            gtag('event', 'video_play', {
                'event_category': 'video_library',
                'event_label': this.currentVideoData.videoTitle,
                'value': this.currentVideoData.videoId
            });
        }
    },

    // Throttle function for performance
    throttle(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof videoLibraryDashboard !== 'undefined') {
        VideoLibraryDashboard.init();
    }
});

// Export for global access
window.VideoLibraryDashboard = VideoLibraryDashboard; 