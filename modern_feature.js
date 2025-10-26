// modern-features.js - Enhanced JavaScript for modern e-commerce features
class PerfumeStore {
    constructor() {
        this.init();
        this.bindEvents();
        this.initSearchSuggestions();
        this.initInfiniteScroll();
        this.initQuickView();
        this.initWishlist();
        this.initCartFeatures();
    }

    init() {
        console.log('🌸 Parfum Store initialized');
        this.showPageLoader();
        
        // Initialize tooltips and animations
        this.initAnimations();
        
        // Check for saved preferences
        this.loadUserPreferences();
        
        // Initialize notification system
        this.initNotifications();
        
        setTimeout(() => this.hidePageLoader(), 1000);
    }

    bindEvents() {
        // Search form enhancements
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.handleSearchInput.bind(this), 300));
            searchInput.addEventListener('focus', this.showSearchSuggestions.bind(this));
        }

        // Filter changes
        document.querySelectorAll('.filter-select, .filter-checkbox input').forEach(element => {
            element.addEventListener('change', this.handleFilterChange.bind(this));
        });

        // Product card interactions
        document.querySelectorAll('.product-card').forEach(card => {
            this.enhanceProductCard(card);
        });

        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', this.handleKeyboardNav.bind(this));

        // Scroll to top button
        this.initScrollToTop();
    }

    initSearchSuggestions() {
        const searchContainer = document.querySelector('.search-form');
        if (!searchContainer) return;

        // Create suggestions dropdown
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'search-suggestions';
        suggestionsDiv.innerHTML = '';
        searchContainer.appendChild(suggestionsDiv);

        this.suggestionIndex = -1;
        this.suggestions = [];
    }

    handleSearchInput(e) {
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            this.hideSearchSuggestions();
            return;
        }

        this.fetchSearchSuggestions(query);
    }

    async fetchSearchSuggestions(query) {
        try {
            const response = await fetch(`utils/ajax_endpoints.php?action=search_suggestions&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.suggestions && data.suggestions.length > 0) {
                this.displaySearchSuggestions(data.suggestions);
            } else {
                this.hideSearchSuggestions();
            }
        } catch (error) {
            console.error('Error fetching suggestions:', error);
        }
    }

    displaySearchSuggestions(suggestions) {
        const suggestionsDiv = document.querySelector('.search-suggestions');
        if (!suggestionsDiv) return;

        this.suggestions = suggestions;
        
        const html = suggestions.map((suggestion, index) => `
            <div class="suggestion-item ${index === this.suggestionIndex ? 'active' : ''}" 
                 data-index="${index}" 
                 data-value="${suggestion.suggestion}"
                 data-type="${suggestion.type}">
                <span class="suggestion-icon">
                    ${suggestion.type === 'product' ? '🧴' : suggestion.type === 'brand' ? '🏷️' : '🔖'}
                </span>
                <span class="suggestion-text">${suggestion.suggestion}</span>
                <span class="suggestion-type">${suggestion.type}</span>
            </div>
        `).join('');

        suggestionsDiv.innerHTML = html;
        suggestionsDiv.style.display = 'block';

        // Bind click events
        suggestionsDiv.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectSuggestion(item.dataset.value);
            });
        });
    }

    selectSuggestion(value) {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.value = value;
            searchInput.form.submit();
        }
        this.hideSearchSuggestions();
    }

    hideSearchSuggestions() {
        const suggestionsDiv = document.querySelector('.search-suggestions');
        if (suggestionsDiv) {
            suggestionsDiv.style.display = 'none';
        }
        this.suggestionIndex = -1;
    }

    showSearchSuggestions() {
        const suggestionsDiv = document.querySelector('.search-suggestions');
        if (suggestionsDiv && suggestionsDiv.innerHTML.trim()) {
            suggestionsDiv.style.display = 'block';
        }
    }

    initInfiniteScroll() {
        this.currentPage = 1;
        this.isLoading = false;
        this.hasMoreProducts = true;

        const loadMoreBtn = this.createLoadMoreButton();
        const productGrid = document.querySelector('.product-grid');
        
        if (productGrid && productGrid.parentNode) {
            productGrid.parentNode.appendChild(loadMoreBtn);
        }

        // Intersection Observer for auto-loading
        this.initScrollObserver();
    }

    createLoadMoreButton() {
        const button = document.createElement('button');
        button.className = 'btn load-more-btn';
        button.textContent = 'Muat Produk Lainnya';
        button.onclick = this.loadMoreProducts.bind(this);
        button.style.display = 'block';
        button.style.margin = '2rem auto';
        
        return button;
    }

    initScrollObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.isLoading && this.hasMoreProducts) {
                    this.loadMoreProducts();
                }
            });
        }, {
            root: null,
            rootMargin: '100px',
            threshold: 0.1
        });

        const loadMoreBtn = document.querySelector('.load-more-btn');
        if (loadMoreBtn) {
            observer.observe(loadMoreBtn);
        }
    }

    async loadMoreProducts() {
        if (this.isLoading || !this.hasMoreProducts) return;

        this.isLoading = true;
        this.currentPage++;

        const loadMoreBtn = document.querySelector('.load-more-btn');
        if (loadMoreBtn) {
            loadMoreBtn.textContent = 'Memuat...';
            loadMoreBtn.disabled = true;
        }

        try {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', this.currentPage);

            const response = await fetch(`utils/ajax_endpoints.php?action=load_more_products&${urlParams.toString()}`);
            const data = await response.json();

            if (data.products && data.products.length > 0) {
                this.appendProducts(data.products);
            } else {
                this.hasMoreProducts = false;
                if (loadMoreBtn) {
                    loadMoreBtn.textContent = 'Tidak ada produk lagi';
                    loadMoreBtn.disabled = true;
                }
            }
        } catch (error) {
            console.error('Error loading more products:', error);
            this.showNotification('Gagal memuat produk', 'error');
        } finally {
            this.isLoading = false;
            if (loadMoreBtn && this.hasMoreProducts) {
                loadMoreBtn.textContent = 'Muat Produk Lainnya';
                loadMoreBtn.disabled = false;
            }
        }
    }

    appendProducts(products) {
        const productGrid = document.querySelector('.product-grid');
        if (!productGrid) return;

        products.forEach(product => {
            const productCard = this.createProductCard(product);
            productGrid.appendChild(productCard);
        });

        // Re-initialize animations for new cards
        this.initAnimations();
    }

    createProductCard(product) {
        const images = product.all_images ? product.all_images.split('|') : [];
        const primaryImage = images[0] || product.gambar || '';
        const isNew = new Date(product.created_at) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        const isBestseller = product.total_sold > 100;

        const cardDiv = document.createElement('div');
        cardDiv.className = 'product-card';
        cardDiv.innerHTML = `
            <div class="product-image">
                ${primaryImage ? `
                    <img src="${primaryImage}" 
                         alt="${product.nama_parfum}" 
                         loading="lazy"
                         onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22><rect fill=%22%23f8f9fa%22 width=%22200%22 height=%22200%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22>🧴</text></svg>';">
                ` : `
                    <div style="width: 100%; height: 100%; background: linear-gradient(45deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">🧴</div>
                `}
                
                <div class="product-badges">
                    ${product.discount_percentage > 0 ? `<span class="badge badge-discount">-${product.discount_percentage}%</span>` : ''}
                    ${isNew ? '<span class="badge badge-new">Baru</span>' : ''}
                    ${isBestseller ? '<span class="badge badge-bestseller">Terlaris</span>' : ''}
                </div>
                
                <button class="wishlist-btn" onclick="toggleWishlist(${product.id})">
                    ♡
                </button>
            </div>
            
            <div class="product-info">
                <div class="product-brand">${product.brand} • ${product.volume_ml}ml</div>
                <h3 class="product-name">${product.nama_parfum}</h3>
                
                <div class="product-rating">
                    <div class="stars">${this.renderStars(product.rating_average)}</div>
                    <span class="rating-text">${product.rating_average.toFixed(1)} (${product.total_reviews})</span>
                </div>
                
                <div class="product-price">
                    <span class="current-price">${this.formatRupiah(product.final_price)}</span>
                    ${product.discount_percentage > 0 && product.display_original_price ? `
                        <span class="original-price">${this.formatRupiah(product.display_original_price)}</span>
                        <span class="discount-percentage">-${product.discount_percentage}%</span>
                    ` : ''}
                </div>
                
                <div class="product-meta">
                    <span>📦 ${product.total_sold} terjual</span>
                    <span>👁️ ${product.views_today || 0} dilihat hari ini</span>
                </div>
                
                <div class="product-actions">
                    ${product.stok > 0 ? `
                        <form method="POST" action="utils/add_to_cart.php" style="flex: 1;">
                            <input type="hidden" name="product_id" value="${product.id}">
                            <button type="submit" class="add-to-cart">
                                🛒 Tambah ke Keranjang
                            </button>
                        </form>
                    ` : `
                        <button class="add-to-cart" disabled>Stok Habis</button>
                    `}
                    <button class="quick-view" onclick="quickView(${product.id})">👁️</button>
                </div>
            </div>
        `;

        this.enhanceProductCard(cardDiv);
        return cardDiv;
    }

    enhanceProductCard(card) {
        // Add hover effects
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-8px)';
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });

        // Add click tracking
        const productImage = card.querySelector('.product-image');
        if (productImage) {
            productImage.addEventListener('click', () => {
                const productId = card.querySelector('input[name="product_id"]')?.value;
                if (productId) {
                    this.trackProductClick(productId);
                }
            });
        }
    }

    initQuickView() {
        // Create modal structure
        this.createQuickViewModal();
    }

    createQuickViewModal() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay quick-view-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close" onclick="closeQuickView()">&times;</button>
                <div class="modal-body">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Memuat detail produk...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    async showQuickView(productId) {
        const modal = document.querySelector('.quick-view-modal');
        if (!modal) return;

        modal.style.display = 'flex';
        document.body.classList.add('modal-open');

        try {
            const response = await fetch(`utils/ajax_endpoints.php?action=quick_view&product_id=${productId}`);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.displayQuickViewContent(data);
        } catch (error) {
            console.error('Error loading quick view:', error);
            this.showNotification('Gagal memuat detail produk', 'error');
            this.closeQuickView();
        }
    }

    displayQuickViewContent(data) {
        const modal = document.querySelector('.quick-view-modal .modal-body');
        if (!modal) return;

        const product = data.product;
        const reviews = data.reviews || [];
        const images = product.all_images ? product.all_images.split('|') : [product.gambar];

        modal.innerHTML = `
            <div class="quick-view-content">
                <div class="product-gallery">
                    <div class="main-image">
                        <img src="${images[0] || ''}" alt="${product.nama_parfum}" id="quickview-main-image">
                        <button class="wishlist-btn ${data.in_wishlist ? 'active' : ''}" 
                                onclick="toggleWishlist(${product.id})">
                            ${data.in_wishlist ? '♥' : '♡'}
                        </button>
                    </div>
                    ${images.length > 1 ? `
                        <div class="thumbnail-gallery">
                            ${images.map((img, index) => `
                                <img src="${img}" alt="Thumbnail ${index + 1}" 
                                     onclick="changeQuickViewImage('${img}')"
                                     class="thumbnail ${index === 0 ? 'active' : ''}">
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
                
                <div class="product-details">
                    <div class="product-brand">${product.brand} • ${product.volume_ml}ml</div>
                    <h2 class="product-name">${product.nama_parfum}</h2>
                    
                    <div class="product-rating">
                        <div class="stars">${this.renderStars(product.rating_average)}</div>
                        <span class="rating-text">${product.rating_average.toFixed(1)} (${product.total_reviews} review)</span>
                    </div>
                    
                    <div class="product-price">
                        <span class="current-price">${this.formatRupiah(product.final_price)}</span>
                        ${product.discount_percentage > 0 && product.display_original_price ? `
                            <span class="original-price">${this.formatRupiah(product.display_original_price)}</span>
                            <span class="discount-percentage">-${product.discount_percentage}%</span>
                        ` : ''}
                    </div>
                    
                    <div class="product-description">
                        <p>${product.deskripsi}</p>
                    </div>
                    
                    ${product.scent_notes ? `
                        <div class="scent-notes">
                            <h4>Notes Aroma:</h4>
                            <p>${product.scent_notes}</p>
                        </div>
                    ` : ''}
                    
                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="label">Kategori:</span>
                            <span class="value">${product.kategori}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Ketahanan:</span>
                            <span class="value">${product.longevity_hours || 'N/A'} jam</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Sillage:</span>
                            <span class="value">${product.sillage}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Stok:</span>
                            <span class="value ${product.stok > 0 ? 'in-stock' : 'out-of-stock'}">
                                ${product.stok > 0 ? `${product.stok} tersedia` : 'Habis'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="quick-actions">
                        ${product.stok > 0 ? `
                            <form method="POST" action="utils/add_to_cart.php" style="flex: 1;">
                                <input type="hidden" name="product_id" value="${product.id}">
                                <button type="submit" class="btn add-to-cart">
                                    🛒 Tambah ke Keranjang
                                </button>
                            </form>
                        ` : `
                            <button class="btn add-to-cart" disabled>Stok Habis</button>
                        `}
                        <button class="btn btn-secondary" onclick="window.location.href='product.php?id=${product.id}'">
                            Lihat Detail
                        </button>
                    </div>
                    
                    ${reviews.length > 0 ? `
                        <div class="recent-reviews">
                            <h4>Review Terbaru:</h4>
                            ${reviews.slice(0, 2).map(review => `
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="stars">${this.renderStars(review.rating)}</div>
                                        <span class="reviewer-name">${review.customer_name}</span>
                                        ${review.is_verified_purchase ? '<span class="verified">✅ Verified</span>' : ''}
                                    </div>
                                    ${review.review_title ? `<h5>${review.review_title}</h5>` : ''}
                                    <p>${review.review_text}</p>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    closeQuickView() {
        const modal = document.querySelector('.quick-view-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }

    initWishlist() {
        // Initialize wishlist functionality
        this.wishlistItems = this.getWishlistFromStorage();
    }

    async toggleWishlist(productId) {
        try {
            const formData = new FormData();
            formData.append('action', 'toggle_wishlist');
            formData.append('product_id', productId);

            const response = await fetch('utils/ajax_endpoints.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateWishlistUI(productId, data.in_wishlist);
                this.showNotification(data.message, 'success');
            } else {
                this.showNotification(data.error || 'Terjadi kesalahan', 'error');
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            this.showNotification('Terjadi kesalahan', 'error');
        }
    }

    updateWishlistUI(productId, inWishlist) {
        const wishlistBtns = document.querySelectorAll(`[onclick="toggleWishlist(${productId})"]`);
        wishlistBtns.forEach(btn => {
            btn.innerHTML = inWishlist ? '♥' : '♡';
            btn.classList.toggle('active', inWishlist);
        });
    }

    initCartFeatures() {
        // Enhanced cart functionality
        this.initQuickAdd();
        this.initCartPreview();
    }

    initQuickAdd() {
        document.addEventListener('submit', (e) => {
            if (e.target.action && e.target.action.includes('utils/add_to_cart.php')) {
                e.preventDefault();
                this.quickAddToCart(e.target);
            }
        });
    }

    async quickAddToCart(form) {
        const formData = new FormData(form);
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        
        button.textContent = 'Menambahkan...';
        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            // Assuming the response redirects, we'll handle it differently
            this.showNotification('Produk berhasil ditambahkan ke keranjang!', 'success');
            this.updateCartCount();
            
        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showNotification('Gagal menambahkan ke keranjang', 'error');
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    async updateCartCount() {
        try {
            // This would need to be implemented in your backend
            const response = await fetch('utils/ajax_endpoints.php?action=get_cart_count');
            const data = await response.json();
            
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement && data.count) {
                cartCountElement.textContent = data.count;
                cartCountElement.style.display = data.count > 0 ? 'flex' : 'none';
            }
        } catch (error) {
            console.error('Error updating cart count:', error);
        }
    }

    initNotifications() {
        // Create notification container
        const container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }

    showNotification(message, type = 'info', duration = 4000) {
        const container = document.querySelector('.notification-container');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">
                    ${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
                </span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;

        container.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
    }

    initAnimations() {
        // Animate elements on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('.product-card, .hero, .search-section').forEach(el => {
            observer.observe(el);
        });
    }

    initScrollToTop() {
        const scrollToTopBtn = document.createElement('button');
        scrollToTopBtn.className = 'scroll-to-top';
        scrollToTopBtn.innerHTML = '↑';
        scrollToTopBtn.style.display = 'none';
        scrollToTopBtn.onclick = () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        };
        document.body.appendChild(scrollToTopBtn);

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 500) {
                scrollToTopBtn.style.display = 'block';
            } else {
                scrollToTopBtn.style.display = 'none';
            }
        });
    }

    handleFilterChange(e) {
        // Show loading state
        this.showPageLoader();
        
        // Small delay to show loading effect
        setTimeout(() => {
            e.target.form.submit();
        }, 300);
    }

    handleKeyboardNav(e) {
        const suggestionsDiv = document.querySelector('.search-suggestions');
        if (!suggestionsDiv || suggestionsDiv.style.display === 'none') return;

        const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.suggestionIndex = Math.min(this.suggestionIndex + 1, suggestions.length - 1);
                this.updateSuggestionHighlight(suggestions);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.suggestionIndex = Math.max(this.suggestionIndex - 1, -1);
                this.updateSuggestionHighlight(suggestions);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.suggestionIndex >= 0 && suggestions[this.suggestionIndex]) {
                    this.selectSuggestion(suggestions[this.suggestionIndex].dataset.value);
                }
                break;
            case 'Escape':
                this.hideSearchSuggestions();
                break;
        }
    }

    updateSuggestionHighlight(suggestions) {
        suggestions.forEach((suggestion, index) => {
            suggestion.classList.toggle('active', index === this.suggestionIndex);
        });
    }

    showPageLoader() {
        let loader = document.querySelector('.page-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'page-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="loader-spinner"></div>
                    <p>Memuat...</p>
                </div>
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    }

    hidePageLoader() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    loadUserPreferences() {
        // Load saved preferences from localStorage
        const savedPrefs = localStorage.getItem('parfume_store_prefs');
        if (savedPrefs) {
            this.userPrefs = JSON.parse(savedPrefs);
        } else {
            this.userPrefs = {
                currency: 'IDR',
                itemsPerPage: 12,
                defaultSort: 'newest'
            };
        }
    }

    saveUserPreferences() {
        localStorage.setItem('parfume_store_prefs', JSON.stringify(this.userPrefs));
    }

    getWishlistFromStorage() {
        const wishlist = localStorage.getItem('parfume_wishlist');
        return wishlist ? JSON.parse(wishlist) : [];
    }

    saveWishlistToStorage() {
        localStorage.setItem('parfume_wishlist', JSON.stringify(this.wishlistItems));
    }

    trackProductClick(productId) {
        // Track product clicks for analytics
        const clickData = {
            product_id: productId,
            timestamp: new Date().toISOString(),
            page_url: window.location.href
        };

        // Store in sessionStorage for analytics
        let clicks = JSON.parse(sessionStorage.getItem('product_clicks') || '[]');
        clicks.push(clickData);
        sessionStorage.setItem('product_clicks', JSON.stringify(clicks));
    }

    formatRupiah(amount) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
    }

    renderStars(rating) {
        const fullStars = Math.floor(rating);
        const halfStar = (rating - fullStars) >= 0.5;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        
        let stars = '⭐'.repeat(fullStars);
        if (halfStar) stars += '⭐'; // Could use half star icon
        stars += '☆'.repeat(emptyStars);
        
        return stars;
    }

    debounce(func, wait) {
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

    // Additional utility methods
    async checkProductStock(productId) {
        try {
            const response = await fetch(`utils/ajax_endpoints.php?action=check_stock&product_id=${productId}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error checking stock:', error);
            return { error: 'Failed to check stock' };
        }
    }

    async validateDiscountCode(code, cartTotal) {
        try {
            const formData = new FormData();
            formData.append('action', 'validate_discount');
            formData.append('discount_code', code);
            formData.append('cart_total', cartTotal);

            const response = await fetch('utils/ajax_endpoints.php', {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error validating discount code:', error);
            return { valid: false, message: 'Terjadi kesalahan saat validasi kode diskon' };
        }
    }

    initCartPreview() {
        // Create mini cart preview on hover
        const cartIcon = document.querySelector('.cart-icon');
        if (!cartIcon) return;

        let previewTimeout;
        
        cartIcon.addEventListener('mouseenter', () => {
            previewTimeout = setTimeout(() => {
                this.showCartPreview();
            }, 500);
        });

        cartIcon.addEventListener('mouseleave', () => {
            clearTimeout(previewTimeout);
            setTimeout(() => {
                this.hideCartPreview();
            }, 300);
        });
    }

    showCartPreview() {
        // Implementation for cart preview popup
        console.log('Showing cart preview...');
    }

    hideCartPreview() {
        // Implementation for hiding cart preview
        console.log('Hiding cart preview...');
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.classList.remove('modal-open');
    }
}

// Global functions for backward compatibility
function toggleWishlist(productId) {
    if (window.perfumeStore) {
        window.perfumeStore.toggleWishlist(productId);
    }
}

function quickView(productId) {
    if (window.perfumeStore) {
        window.perfumeStore.showQuickView(productId);
    }
}

function closeQuickView() {
    if (window.perfumeStore) {
        window.perfumeStore.closeQuickView();
    }
}

function changeQuickViewImage(imageUrl) {
    const mainImage = document.getElementById('quickview-main-image');
    if (mainImage) {
        mainImage.src = imageUrl;
    }
    
    // Update thumbnail active state
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.toggle('active', thumb.src === imageUrl);
    });
}

function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('sort_by', value);
    window.location = url;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.perfumeStore = new PerfumeStore();
    
    // Add additional CSS for modern features
    const additionalCSS = `
        <style>
            /* Search Suggestions */
            .search-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-top: none;
                border-radius: 0 0 8px 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                max-height: 300px;
                overflow-y: auto;
            }
            
            .suggestion-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
                transition: background-color 0.2s;
            }
            
            .suggestion-item:hover,
            .suggestion-item.active {
                background-color: #f8f9fa;
            }
            
            .suggestion-item:last-child {
                border-bottom: none;
            }
            
            .suggestion-icon {
                font-size: 1.2em;
                opacity: 0.7;
            }
            
            .suggestion-text {
                flex: 1;
                font-weight: 500;
            }
            
            .suggestion-type {
                font-size: 0.75em;
                color: #666;
                text-transform: uppercase;
                background: #e9ecef;
                padding: 2px 6px;
                border-radius: 3px;
            }
            
            /* Quick View Modal */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2000;
                animation: fadeIn 0.3s ease;
            }
            
            .modal-content {
                background: white;
                border-radius: 12px;
                max-width: 90vw;
                max-height: 90vh;
                overflow-y: auto;
                position: relative;
                animation: slideUp 0.3s ease;
            }
            
            .modal-close {
                position: absolute;
                top: 15px;
                right: 20px;
                background: none;
                border: none;
                font-size: 2em;
                cursor: pointer;
                z-index: 10;
                color: #666;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            
            .modal-close:hover {
                background: #f0f0f0;
                color: #333;
            }
            
            .quick-view-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
                padding: 2rem;
                min-height: 400px;
            }
            
            .product-gallery .main-image {
                position: relative;
                margin-bottom: 1rem;
            }
            
            .product-gallery .main-image img {
                width: 100%;
                height: 400px;
                object-fit: cover;
                border-radius: 8px;
            }
            
            .thumbnail-gallery {
                display: flex;
                gap: 0.5rem;
                overflow-x: auto;
            }
            
            .thumbnail {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 4px;
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.2s;
                border: 2px solid transparent;
            }
            
            .thumbnail.active,
            .thumbnail:hover {
                opacity: 1;
                border-color: #667eea;
            }
            
            .product-details h2 {
                margin-bottom: 1rem;
                color: #333;
            }
            
            .scent-notes {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
            }
            
            .scent-notes h4 {
                margin-bottom: 0.5rem;
                color: #667eea;
            }
            
            .product-meta {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
                margin: 1rem 0;
            }
            
            .meta-item {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid #eee;
            }
            
            .meta-item .label {
                font-weight: 500;
                color: #666;
            }
            
            .meta-item .value {
                color: #333;
            }
            
            .value.in-stock {
                color: #27ae60;
            }
            
            .value.out-of-stock {
                color: #e74c3c;
            }
            
            .quick-actions {
                display: flex;
                gap: 1rem;
                margin: 1.5rem 0;
            }
            
            .recent-reviews {
                margin-top: 1.5rem;
                padding-top: 1.5rem;
                border-top: 1px solid #eee;
            }
            
            .review-item {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
                margin-bottom: 1rem;
            }
            
            .review-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .reviewer-name {
                font-weight: 500;
                color: #333;
            }
            
            .verified {
                font-size: 0.8em;
                color: #27ae60;
            }
            
            /* Notifications */
            .notification-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 3000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .notification {
                min-width: 300px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: translateX(100%);
                opacity: 0;
                transition: all 0.3s ease;
            }
            
            .notification.show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px;
                position: relative;
            }
            
            .notification-success {
                background: #d4edda;
                color: #155724;
                border-left: 4px solid #27ae60;
            }
            
            .notification-error {
                background: #f8d7da;
                color: #721c24;
                border-left: 4px solid #e74c3c;
            }
            
            .notification-warning {
                background: #fff3cd;
                color: #856404;
                border-left: 4px solid #ffc107;
            }
            
            .notification-info {
                background: #d1ecf1;
                color: #0c5460;
                border-left: 4px solid #17a2b8;
            }
            
            .notification-message {
                flex: 1;
            }
            
            .notification-close {
                background: none;
                border: none;
                font-size: 1.2em;
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.2s;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
            
            /* Loading States */
            .loading-spinner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 3rem;
            }
            
            .spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 1rem;
            }
            
            .page-loader {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255,255,255,0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 4000;
            }
            
            .loader-content {
                text-align: center;
            }
            
            .loader-spinner {
                width: 60px;
                height: 60px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            
            /* Scroll to Top */
            .scroll-to-top {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 50px;
                height: 50px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 50%;
                font-size: 1.5em;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
                z-index: 1000;
            }
            
            .scroll-to-top:hover {
                background: #5a67d8;
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(0,0,0,0.25);
            }
            
            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .animate-in {
                animation: slideUp 0.6s ease forwards;
            }
            
            /* Enhanced Product Cards */
            .wishlist-btn.active {
                color: #e74c3c;
                background: rgba(231, 76, 60, 0.1);
            }
            
            .product-card:hover .wishlist-btn {
                opacity: 1;
            }
            
            /* Mobile Responsiveness */
            @media (max-width: 768px) {
                .quick-view-content {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                }
                
                .product-gallery .main-image img {
                    height: 250px;
                }
                
                .notification {
                    min-width: 90vw;
                    margin: 0 5vw;
                }
                
                .modal-content {
                    max-width: 95vw;
                    margin: 0 2.5vw;
                }
                
                .quick-actions {
                    flex-direction: column;
                }
                
                .product-meta {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Modal Open State */
            body.modal-open {
                overflow: hidden;
            }
            
            /* Search Form Position */
            .search-form {
                position: relative;
            }
            
            .search-input {
                position: relative;
                z-index: 1;
            }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', additionalCSS);
});

// Service Worker Registration for PWA features (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}