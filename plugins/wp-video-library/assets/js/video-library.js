/* ==========================================================================
   Video Library - Frontend JavaScript
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    const videoLibrary = {
        modal: null,
        modalVideo: null,
        modalVideoInfo: null,
        searchInput: null,
        categoryFilter: null,
        typeFilter: null,
        pathFilter: null,
        currentPage: 1,
        isLoading: false,
        
        // YouTube layout elements
        mainPlayer: null,
        mainVideo: null,
        currentVideoData: null,

        init: function() {
            this.createModal();
            this.bindEvents();
            this.initializeFilters();
            this.initializeYouTubeLayout();
        },

        initializeYouTubeLayout: function() {
            // Initialize main player for YouTube layout
            this.mainPlayer = document.querySelector('.video-library-main-video');
            this.mainVideo = document.getElementById('main-video-player');
            
            if (this.mainPlayer) {
                console.log('YouTube layout detected, initializing...');
                this.bindYouTubeEvents();
                
                // Set initial video data from the featured video
                this.currentVideoData = this.extractVideoData(this.mainPlayer);
                console.log('Initial featured video:', this.currentVideoData);
            }
        },

        bindYouTubeEvents: function() {
            const self = this;
            
            // Main player thumbnail click - play inline
            const mainThumbnail = this.mainPlayer?.querySelector('.video-thumbnail');
            if (mainThumbnail) {
                mainThumbnail.addEventListener('click', function() {
                    self.playMainVideo();
                });
            }
            
            // Sidebar item clicks - switch main video
            document.addEventListener('click', function(e) {
                const sidebarItem = e.target.closest('.video-sidebar-item');
                if (sidebarItem) {
                    e.preventDefault();
                    console.log('Sidebar video clicked:', sidebarItem);
                    self.switchMainVideo(sidebarItem);
                }
            });
            
            // Video events for main player
            if (this.mainVideo) {
                this.mainVideo.addEventListener('loadeddata', function() {
                    console.log('Main video loaded successfully');
                });
                
                this.mainVideo.addEventListener('error', function(e) {
                    console.error('Main video error:', e);
                    self.handleMainVideoError();
                });
                
                this.mainVideo.addEventListener('play', function() {
                    console.log('Main video started playing');
                    // Hide thumbnail when video starts playing
                    const thumbnail = self.mainPlayer?.querySelector('.video-thumbnail');
                    if (thumbnail) {
                        thumbnail.classList.add('hidden');
                    }
                });
                
                this.mainVideo.addEventListener('pause', function() {
                    console.log('Main video paused');
                });
                
                this.mainVideo.addEventListener('ended', function() {
                    console.log('Main video ended');
                    self.playNextVideo();
                });
            }
        },

        playMainVideo: function() {
            if (!this.mainVideo || !this.currentVideoData) {
                console.log('No main video or video data available');
                return;
            }
            
            console.log('Playing main video:', this.currentVideoData);
            
            // Check if we need to refresh presigned URL
            if (this.currentVideoData.isVirtual === 'true' && this.currentVideoData.videoUrl.includes('Expires=')) {
                const urlParams = new URLSearchParams(this.currentVideoData.videoUrl.split('?')[1]);
                const expires = parseInt(urlParams.get('Expires'));
                const now = Math.floor(Date.now() / 1000);
                
                if (expires && expires < now) {
                    console.warn('Presigned URL expired, refreshing...');
                    this.refreshMainVideoUrl();
                    return;
                }
            }
            
            // Set video source and show player
            this.mainVideo.src = this.currentVideoData.videoUrl;
            this.mainVideo.style.display = 'block';
            
            // Play video
            this.mainVideo.play().catch(e => {
                console.log('Auto-play prevented:', e);
            });
        },

        switchMainVideo: function(sidebarItem) {
            console.log('Switching to video:', sidebarItem);
            
            // Extract video data from sidebar item
            const newVideoData = this.extractVideoData(sidebarItem);
            console.log('New video data:', newVideoData);
            
            // Update main player with new video data
            this.updateMainPlayer(newVideoData);
            
            // Update active state in sidebar
            this.updateSidebarActiveState(sidebarItem);
            
            // Store current video data
            this.currentVideoData = newVideoData;
        },

        updateMainPlayer: function(videoData) {
            if (!this.mainPlayer || !videoData) return;
            
            // Update main player data attributes
            Object.keys(videoData).forEach(key => {
                const dataKey = key.replace(/([A-Z])/g, '-$1').toLowerCase();
                this.mainPlayer.setAttribute(`data-${dataKey}`, videoData[key]);
            });
            
            // Update thumbnail
            const thumbnail = this.mainPlayer.querySelector('.video-thumbnail');
            if (thumbnail) {
                thumbnail.style.backgroundImage = `url('${videoData.thumbnailUrl || ''}')`;
                thumbnail.classList.remove('hidden');
            }
            
            // Update duration overlay
            const duration = this.mainPlayer.querySelector('.video-duration');
            if (duration) {
                duration.textContent = videoData.videoDuration || 'Unknown';
            }
            
            // Update video info section
            const infoSection = document.querySelector('.video-library-main-info');
            if (infoSection) {
                // Update title
                const title = infoSection.querySelector('h2');
                if (title) title.textContent = videoData.videoTitle || 'Untitled';
                
                // Update meta info
                const metaSpans = infoSection.querySelectorAll('.video-meta span');
                if (metaSpans.length >= 3) {
                    metaSpans[0].innerHTML = `📅 ${videoData.videoDate || 'Unknown'}`;
                    metaSpans[1].innerHTML = `⏱ ${videoData.videoDuration || 'Unknown'}`;
                    metaSpans[2].innerHTML = `💾 ${videoData.videoSize || 'Unknown'}`;
                }
                
                // Update description
                const description = infoSection.querySelector('.video-description');
                if (description) {
                    description.textContent = videoData.videoDescription || 'No description available.';
                }
            }
            
            // Pause current video and hide player
            if (this.mainVideo) {
                this.mainVideo.pause();
                this.mainVideo.style.display = 'none';
                this.mainVideo.src = '';
            }
            
            console.log('Main player updated with new video data');
        },

        updateSidebarActiveState: function(activeItem) {
            // Remove active class from all sidebar items
            const sidebarItems = document.querySelectorAll('.video-sidebar-item');
            sidebarItems.forEach(item => item.classList.remove('active'));
            
            // Add active class to clicked item
            if (activeItem) {
                activeItem.classList.add('active');
            }
        },

        playNextVideo: function() {
            const activeItem = document.querySelector('.video-sidebar-item.active');
            const nextItem = activeItem ? activeItem.nextElementSibling : document.querySelector('.video-sidebar-item');
            
            if (nextItem) {
                console.log('Auto-playing next video');
                this.switchMainVideo(nextItem);
                setTimeout(() => this.playMainVideo(), 500);
            } else {
                console.log('No next video available');
                // Show thumbnail again
                const thumbnail = this.mainPlayer?.querySelector('.video-thumbnail');
                if (thumbnail) {
                    thumbnail.classList.remove('hidden');
                }
            }
        },

        refreshMainVideoUrl: function() {
            if (!this.currentVideoData) return;
            
            console.log('Refreshing presigned URL for main video');
            
            const formData = new FormData();
            formData.append('action', 'get_fresh_presigned_url');
            formData.append('nonce', videoLibraryAjax.nonce);
            formData.append('s3_key', this.currentVideoData.s3Key);

            fetch(videoLibraryAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.url) {
                    console.log('Fresh presigned URL received for main player');
                    this.currentVideoData.videoUrl = data.data.url;
                    this.mainPlayer.setAttribute('data-video-url', data.data.url);
                    this.playMainVideo();
                } else {
                    console.error('Failed to refresh presigned URL:', data);
                    alert('Unable to refresh video URL. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error refreshing presigned URL:', error);
            });
        },

        handleMainVideoError: function() {
            console.error('Main video player error');
            const thumbnail = this.mainPlayer?.querySelector('.video-thumbnail');
            if (thumbnail) {
                thumbnail.classList.remove('hidden');
            }
            
            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'main-video-error';
            errorDiv.innerHTML = `
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                           background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4>Video Playback Error</h4>
                    <p>Unable to load this video. Click to try again or select another video.</p>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">
                        Close
                    </button>
                </div>
            `;
            
            this.mainPlayer?.appendChild(errorDiv);
            
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        },

        extractVideoData: function(element) {
            if (!element) return null;
            
            return {
                videoId: element.getAttribute('data-video-id'),
                videoUrl: element.getAttribute('data-video-url'),
                videoTitle: element.getAttribute('data-video-title'),
                videoDescription: element.getAttribute('data-video-description'),
                videoDuration: element.getAttribute('data-video-duration'),
                videoSize: element.getAttribute('data-video-size'),
                videoDate: element.getAttribute('data-video-date'),
                s3Key: element.getAttribute('data-s3-key'),
                isVirtual: element.getAttribute('data-is-virtual'),
                thumbnailUrl: this.extractThumbnailUrl(element)
            };
        },

        extractThumbnailUrl: function(element) {
            const thumbnail = element.querySelector('.video-thumbnail, .sidebar-thumbnail');
            if (thumbnail) {
                const bgImage = window.getComputedStyle(thumbnail).getPropertyValue('background-image');
                const match = bgImage.match(/url\(["']?([^"']*)["']?\)/);
                return match ? match[1] : '';
            }
            return '';
        },

        createModal: function() {
            // Create modal HTML
            const modalHTML = `
                <div id="video-modal" class="video-modal">
                    <div class="video-modal-content">
                        <button class="video-modal-close" id="modal-close">
                            <span>&times;</span>
                        </button>
                        <div class="video-modal-player">
                            <video id="modal-video" controls playsinline>
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <div class="video-modal-info">
                            <h3 id="modal-video-title">Video Title</h3>
                            <p id="modal-video-description">Video description...</p>
                            <div class="video-modal-meta">
                                <span id="modal-video-duration">Duration: --:--</span>
                                <span id="modal-video-size">Size: --</span>
                                <span id="modal-video-date">Date: --</span>
                                <button id="modal-video-favorite">♡ Add to Favorites</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Get modal elements
            this.modal = document.getElementById('video-modal');
            this.modalVideo = document.getElementById('modal-video');
            this.modalVideoInfo = {
                title: document.getElementById('modal-video-title'),
                description: document.getElementById('modal-video-description'),
                duration: document.getElementById('modal-video-duration'),
                size: document.getElementById('modal-video-size'),
                date: document.getElementById('modal-video-date'),
                favorite: document.getElementById('modal-video-favorite')
            };
        },

        bindEvents: function() {
            const self = this;

            // Video card clicks (for grid layout) with enhanced debugging
            document.addEventListener('click', function(e) {
                const videoCard = e.target.closest('.video-card');
                if (videoCard && !videoCard.classList.contains('skeleton') && !e.target.closest('.video-sidebar-item')) {
                    console.log('Video card clicked:', videoCard);
                    console.log('Video URL:', videoCard.dataset.videoUrl);
                    console.log('Video Title:', videoCard.dataset.videoTitle);
                    
                    // Prevent skeleton cards from being clickable
                    if (videoCard.classList.contains('skeleton')) {
                        e.preventDefault();
                        return;
                    }
                    
                    self.openVideo(videoCard);
                }
            });

            // Modal close events
            if (this.modal) {
                const closeBtn = this.modal.querySelector('.video-modal-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => this.closeModal());
                }

                // Close on backdrop click
                this.modal.addEventListener('click', function(e) {
                    if (e.target === self.modal) {
                        self.closeModal();
                    }
                });

                // Close on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && self.modal.classList.contains('active')) {
                        self.closeModal();
                    }
                });

                // Favorite button
                if (this.modalVideoInfo.favorite) {
                    this.modalVideoInfo.favorite.addEventListener('click', function() {
                        self.toggleFavorite(this);
                    });
                }
            }

            // Search functionality
            this.searchInput = document.querySelector('#video-search');
            if (this.searchInput) {
                let searchTimeout;
                this.searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        self.filterVideos();
                    }, 300);
                });
            }

            // Search button
            const searchBtn = document.querySelector('#video-search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', () => this.filterVideos());
            }

            // Filter dropdowns
            this.categoryFilter = document.querySelector('#video-category-filter');
            this.typeFilter = document.querySelector('#filter-type');
            this.pathFilter = document.querySelector('#filter-path');

            [this.categoryFilter, this.typeFilter, this.pathFilter].forEach(filter => {
                if (filter) {
                    filter.addEventListener('change', () => this.filterVideos());
                }
            });

            // Load more button
            const loadMoreBtn = document.querySelector('#load-more-videos');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', () => this.loadMoreVideos());
            }

            // Prevent body scroll when modal is open
            this.modal?.addEventListener('wheel', function(e) {
                e.preventDefault();
            });
        },

        initializeFilters: function() {
            // Initialize search from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const searchTerm = urlParams.get('search');
            if (searchTerm && this.searchInput) {
                this.searchInput.value = searchTerm;
                this.filterVideos();
            }
        },

        openVideo: function(videoCard) {
            console.log('Opening video modal...');
            const videoUrl = videoCard.dataset.videoUrl;
            const videoTitle = videoCard.dataset.videoTitle;
            const videoDescription = videoCard.dataset.videoDescription;
            const videoDuration = videoCard.dataset.videoDuration;
            const videoSize = videoCard.dataset.videoSize;
            const videoDate = videoCard.dataset.videoDate;
            const videoId = videoCard.dataset.videoId;
            const s3Key = videoCard.dataset.s3Key;
            const isVirtual = videoCard.dataset.isVirtual === 'true';

            console.log('Video data:', {
                url: videoUrl,
                title: videoTitle,
                s3Key: s3Key,
                isVirtual: isVirtual
            });

            if (!videoUrl || !this.modal) {
                console.error('Missing video URL or modal not found');
                alert('Unable to play video - missing video URL');
                return;
            }

            // For S3 virtual videos, we might need to refresh the presigned URL if it's expired
            if (isVirtual && videoUrl.includes('Expires=')) {
                const urlParams = new URLSearchParams(videoUrl.split('?')[1]);
                const expires = parseInt(urlParams.get('Expires'));
                const now = Math.floor(Date.now() / 1000);
                
                if (expires && expires < now) {
                    console.warn('Presigned URL appears to be expired, attempting to refresh...');
                    this.refreshPresignedUrl(s3Key, videoCard);
                    return;
                }
            }

            // Set video source
            this.modalVideo.src = videoUrl;
            this.modalVideo.currentTime = 0;
            
            console.log('Video source set to:', videoUrl);
            console.log('Video element src attribute:', this.modalVideo.src);
            console.log('Video element current src:', this.modalVideo.currentSrc);

            // Set video info
            this.modalVideoInfo.title.textContent = videoTitle || 'Untitled Video';
            this.modalVideoInfo.description.textContent = videoDescription || 'No description available.';
            this.modalVideoInfo.duration.textContent = `Duration: ${videoDuration || '--:--'}`;
            this.modalVideoInfo.size.textContent = `Size: ${videoSize || '--'}`;
            this.modalVideoInfo.date.textContent = `Date: ${videoDate || '--'}`;

            // Set favorite state
            const isFavorited = this.isFavorited(videoId);
            this.updateFavoriteButton(isFavorited);

            // Show modal with animation
            this.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Trigger animation
            setTimeout(() => {
                this.modal.classList.add('active');
            }, 10);

            // Add video event listeners for debugging
            this.modalVideo.addEventListener('loadstart', () => {
                console.log('Video loading started');
            });
            
            this.modalVideo.addEventListener('loadeddata', () => {
                console.log('Video data loaded successfully');
            });
            
            this.modalVideo.addEventListener('canplay', () => {
                console.log('Video can start playing');
            });
            
            this.modalVideo.addEventListener('error', (e) => {
                console.error('Video loading error:', e);
                console.error('Video error details:', this.modalVideo.error);
                if (this.modalVideo.error) {
                    console.error('Error code:', this.modalVideo.error.code);
                    console.error('Error message:', this.modalVideo.error.message);
                }
                this.handleVideoError();
            });

            // Test if URL is accessible
            console.log('Testing if video URL is accessible...');
            fetch(videoUrl, { method: 'HEAD' })
                .then(response => {
                    console.log('URL fetch test - Status:', response.status);
                    console.log('URL fetch test - Content-Type:', response.headers.get('content-type'));
                    console.log('URL fetch test - Content-Length:', response.headers.get('content-length'));
                })
                .catch(error => {
                    console.error('URL fetch test failed:', error);
                });

            // Auto-play video
            setTimeout(() => {
                console.log('Attempting to play video...');
                this.modalVideo.play().catch(e => {
                    console.log('Auto-play was prevented:', e);
                    // Show play button overlay if autoplay fails
                    this.showPlayButton();
                });
            }, 300);
        },

        refreshPresignedUrl: function(s3Key, videoCard) {
            console.log('Refreshing presigned URL for:', s3Key);
            
            // Make AJAX request to get fresh presigned URL
            const formData = new FormData();
            formData.append('action', 'get_fresh_presigned_url');
            formData.append('nonce', videoLibraryAjax.nonce);
            formData.append('s3_key', s3Key);

            fetch(videoLibraryAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.url) {
                    console.log('Fresh presigned URL received');
                    videoCard.dataset.videoUrl = data.data.url;
                    this.openVideo(videoCard);
                } else {
                    console.error('Failed to refresh presigned URL:', data);
                    alert('Unable to refresh video URL. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error refreshing presigned URL:', error);
                alert('Network error while refreshing video URL.');
            });
        },

        handleVideoError: function() {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'video-error-message';
            errorDiv.innerHTML = `
                <div style="text-align: center; padding: 40px; color: white;">
                    <h3>Video Playback Error</h3>
                    <p>Unable to load this video. This could be due to:</p>
                    <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                        <li>Expired access URL</li>
                        <li>Network connectivity issues</li>
                        <li>Video file format not supported</li>
                        <li>S3 bucket permissions</li>
                    </ul>
                    <button onclick="window.videoLibrary.closeModal()" style="margin-top: 20px; padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer;">Close</button>
                </div>
            `;
            
            this.modalVideo.style.display = 'none';
            this.modal.querySelector('.video-modal-player').appendChild(errorDiv);
        },

        showPlayButton: function() {
            const playOverlay = document.createElement('div');
            playOverlay.className = 'video-play-overlay';
            playOverlay.innerHTML = `
                <div class="large-play-button">
                    <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                        <circle cx="40" cy="40" r="40" fill="rgba(255,255,255,0.9)"/>
                        <path d="M30 25L55 40L30 55V25Z" fill="#0073aa"/>
                    </svg>
                </div>
            `;
            
            playOverlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0,0,0,0.3);
                cursor: pointer;
                z-index: 5;
            `;
            
            playOverlay.addEventListener('click', () => {
                this.modalVideo.play();
                playOverlay.remove();
            });
            
            this.modal.querySelector('.video-modal-player').appendChild(playOverlay);
        },

        closeModal: function() {
            if (!this.modal) return;

            // Pause video
            this.modalVideo.pause();
            this.modalVideo.src = '';

            // Hide modal with animation
            this.modal.classList.remove('active');
            document.body.style.overflow = '';

            setTimeout(() => {
                this.modal.style.display = 'none';
            }, 300);
        },

        filterVideos: function() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.currentPage = 1;

            const searchTerm = this.searchInput?.value || '';
            const category = this.categoryFilter?.value || '';
            const type = this.typeFilter?.value || '';
            const path = this.pathFilter?.value || '';

            // Show loading state
            const videosGrid = document.querySelector('.video-library-grid');
            const youtubeLayout = document.querySelector('.video-library-youtube-layout');
            const targetElement = youtubeLayout || videosGrid;
            
            if (targetElement) {
                targetElement.style.opacity = '0.6';
            }

            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'filter_videos');
            formData.append('nonce', videoLibraryAjax.nonce);
            formData.append('search', searchTerm);
            formData.append('category', category);
            formData.append('type', type);
            formData.append('path', path);
            formData.append('page', this.currentPage);

            // Make AJAX request
            fetch(videoLibraryAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateVideoGrid(data.data.html);
                    this.updateLoadMoreButton(data.data.hasMore);
                    this.updateResultsCount(data.data.total);
                } else {
                    console.error('Filter error:', data.data);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
            })
            .finally(() => {
                this.isLoading = false;
                if (targetElement) {
                    targetElement.style.opacity = '1';
                }
            });
        },

        loadMoreVideos: function() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.currentPage++;

            const searchTerm = this.searchInput?.value || '';
            const category = this.categoryFilter?.value || '';
            const type = this.typeFilter?.value || '';
            const path = this.pathFilter?.value || '';

            // Show loading state
            const loadMoreBtn = document.querySelector('#load-more-videos');
            if (loadMoreBtn) {
                loadMoreBtn.textContent = 'Loading...';
                loadMoreBtn.disabled = true;
            }

            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'filter_videos');
            formData.append('nonce', videoLibraryAjax.nonce);
            formData.append('search', searchTerm);
            formData.append('category', category);
            formData.append('type', type);
            formData.append('path', path);
            formData.append('page', this.currentPage);

            // Make AJAX request
            fetch(videoLibraryAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.appendVideoGrid(data.data.html);
                    this.updateLoadMoreButton(data.data.hasMore);
                } else {
                    console.error('Load more error:', data.data);
                    this.currentPage--; // Revert page increment
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                this.currentPage--; // Revert page increment
            })
            .finally(() => {
                this.isLoading = false;
                if (loadMoreBtn) {
                    loadMoreBtn.textContent = 'Load More Videos';
                    loadMoreBtn.disabled = false;
                }
            });
        },

        updateVideoGrid: function(html) {
            const videosGrid = document.querySelector('.video-library-grid');
            if (videosGrid) {
                videosGrid.innerHTML = html;
                
                // Trigger animation for new cards
                const videoCards = videosGrid.querySelectorAll('.video-card');
                videoCards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 50);
                });
            }
        },

        appendVideoGrid: function(html) {
            const videosGrid = document.querySelector('.video-library-grid');
            if (videosGrid) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                const newCards = Array.from(tempDiv.querySelectorAll('.video-card'));
                newCards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    videosGrid.appendChild(card);
                    
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 50);
                });
            }
        },

        updateLoadMoreButton: function(hasMore) {
            const loadMoreBtn = document.querySelector('#load-more-videos');
            const loadMoreContainer = document.querySelector('.video-library-load-more');
            
            if (loadMoreContainer) {
                loadMoreContainer.style.display = hasMore ? 'block' : 'none';
            }
        },

        updateResultsCount: function(total) {
            const countElement = document.querySelector('.video-library-sidebar-count');
            if (countElement && total !== undefined) {
                countElement.textContent = `${total} videos`;
            }
        },

        toggleFavorite: function(button) {
            const videoId = this.currentVideoData?.videoId;
            if (!videoId) return;

            const isFavorited = button.textContent.includes('♥');
            const newState = !isFavorited;

            // Update button immediately for better UX
            this.updateFavoriteButton(newState);

            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'toggle_video_favorite');
            formData.append('nonce', videoLibraryAjax.nonce);
            formData.append('video_id', videoId);
            formData.append('favorited', newState ? '1' : '0');

            fetch(videoLibraryAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Revert button state on error
                    this.updateFavoriteButton(isFavorited);
                    console.error('Failed to update favorite status');
                }
            })
            .catch(error => {
                // Revert button state on error
                this.updateFavoriteButton(isFavorited);
                console.error('Error updating favorite status:', error);
            });
        },

        updateFavoriteButton: function(isFavorited) {
            if (this.modalVideoInfo.favorite) {
                this.modalVideoInfo.favorite.innerHTML = isFavorited 
                    ? '♥ Remove from Favorites'
                    : '♡ Add to Favorites';
            }
        },

        isFavorited: function(videoId) {
            // This would typically check against stored favorites
            // For now, return false as placeholder
            return false;
        }
    };

    // Initialize the video library
    videoLibrary.init();
    
    // Make it globally accessible for debugging
    window.videoLibrary = videoLibrary;
});

// Additional utility functions for external use
function playVideo(videoCard) {
    if (window.videoLibrary) {
        window.videoLibrary.openVideo(videoCard);
    }
}

function closeVideoModal() {
    if (window.videoLibrary) {
        window.videoLibrary.closeModal();
    }
} 