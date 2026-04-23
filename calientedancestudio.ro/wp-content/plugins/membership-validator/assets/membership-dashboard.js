/**
 * Membership Dashboard JavaScript - BEST PRACTICES 2025
 * 
 * Modern ES6+, async/await, fetch API, classes
 * Accessibility, Progressive Enhancement, Error Handling
 * 
 * @package MembershipValidator
 * @since 1.2.0
 */

'use strict';

/**
 * Main Membership Dashboard Class
 * 
 * Handles all client-side functionality for membership dashboard
 * - Auto-refresh session data
 * - Card interactions
 * - Keyboard navigation
 * - Lazy loading
 * - Intersection Observer for animations
 */
class MembershipDashboard {
    /**
     * Constructor
     * Initialize dashboard with configuration from wp_localize_script
     */
    constructor() {
        // Configuration from WordPress (wp_localize_script)
        this.config = window.ocMembershipData || this.getDefaultConfig();
        
        // State management
        this.state = {
            isRefreshing: false,
            lastRefresh: null,
            refreshInterval: null
        };
        
        // Initialize when DOM is ready
        this.init();
    }

    /**
     * Default configuration fallback
     * @returns {Object} Default config
     */
    getDefaultConfig() {
        return {
            ajaxUrl: (typeof ajaxurl !== 'undefined') ? ajaxurl : '',  // use WP global, never hardcode
            nonce: '',
            userId: 0,
            autoRefresh: false,
            debug: false,
            dateFormat: 'Y-m-d',
            timeFormat: 'H:i',
            locale: 'en-US',
            translations: {
                loading: 'Loading...',
                error: 'Error loading data',
                noSessions: 'No sessions remaining',
                updated: 'Data updated'
            }
        };
    }

    /**
     * Initialize dashboard
     * Wait for DOM ready and setup all features
     */
    async init() {
        await this.domReady();
        
        if (this.config.debug) {
        }
        
        this.bindEvents();
        this.setupLazyLoading();
        this.setupIntersectionObserver();
        this.setupAutoRefresh();
        
        if (this.config.debug) {
        }
    }

    /**
     * Wait for DOM to be ready
     * @returns {Promise} Resolves when DOM is ready
     */
    domReady() {
        return new Promise(resolve => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', resolve);
            } else {
                resolve();
            }
        });
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        this.setupCardInteractions();
        this.setupKeyboardNav();
        this.setupVisibilityChange();
    }

    /**
     * Setup auto-refresh functionality
     * Refreshes session data every 30 seconds if enabled
     */
    setupAutoRefresh() {
        if (!this.config.autoRefresh) {
            if (this.config.debug) {
            }
            return;
        }

        // Initial refresh after 5 seconds
        setTimeout(() => this.refreshSessionData(), 5000);

        // Then every 30 seconds
        this.state.refreshInterval = setInterval(async () => {
            if (!document.hidden) {
                await this.refreshSessionData();
            }
        }, 30000);

        if (this.config.debug) {
        }
    }

    /**
     * Refresh session data via AJAX
     * Updates session counts and progress bars without page reload
     */
    async refreshSessionData() {
        if (this.state.isRefreshing) {
            if (this.config.debug) {
            }
            return;
        }

        this.state.isRefreshing = true;

        // 🎯 FIX: Salvează poziția de scroll înainte de refresh
        const scrollY = window.scrollY || window.pageYOffset;
        const scrollX = window.scrollX || window.pageXOffset;

        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'oc_get_membership_sessions',
                    nonce: this.config.nonce,
                    user_id: this.config.userId
                }),
                signal: AbortSignal.timeout(10000) // 10s timeout
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.success && data.data) {
                this.updateSessionDisplays(data.data);
                this.state.lastRefresh = new Date();
                
                // 🎯 FIX: Restabilește poziția de scroll după update
                // Așteaptă un frame pentru ca DOM-ul să se actualizeze
                requestAnimationFrame(() => {
                    window.scrollTo({
                        top: scrollY,
                        left: scrollX,
                        behavior: 'instant' // Fără animație pentru a evita jump-uri
                    });
                });
                
                if (this.config.debug) {
                }
            } else {
                throw new Error(data.data?.message || 'Unknown error');
            }
        } catch (error) {
            if (this.config.debug) {
                console.warn('[Membership Dashboard] Refresh failed:', error.message);
            }
            // Fail silently in production, don't disturb user
        } finally {
            this.state.isRefreshing = false;
        }
    }

    /**
     * Update session displays with new data
     * @param {Array} sessions Session data from server
     */
    updateSessionDisplays(sessions) {
        if (!Array.isArray(sessions)) {
            if (this.config.debug) {
                console.warn('[Membership Dashboard] Invalid sessions data:', sessions);
            }
            return;
        }

        sessions.forEach(session => {
            const card = document.querySelector(`[data-validation-id="${session.validation_id}"]`);
            if (!card || card.classList.contains('vip-unlimited')) return;

            // Update remaining sessions number
            const remainingEl = card.querySelector('.sessions-remaining');
            if (remainingEl) {
                const oldValue = parseInt(remainingEl.textContent) || 0;
                const newValue = parseInt(session.sessions_remaining) || 0;
                
                if (oldValue !== newValue) {
                    this.animateNumberChange(remainingEl, oldValue, newValue);
                    remainingEl.setAttribute('data-remaining', newValue);
                }
            }

            // Update progress bar
            const progressEl = card.querySelector('.oc-progress-bar');
            if (progressEl) {
                progressEl.value = session.sessions_used || 0;
            }

            // Update used sessions label
            const usedLabelEl = card.querySelector('.sessions-used-label small');
            if (usedLabelEl) {
                const sessionsUsed = session.sessions_used || 0;
                usedLabelEl.textContent = `${sessionsUsed} ședințe folosite`;
            }

            // Animație update
            card.classList.add('updated');
            setTimeout(() => card.classList.remove('updated'), 2000);
        });
    }

    /**
     * Animate number change with counting effect
     * @param {HTMLElement} element Element to animate
     * @param {number} from Start value
     * @param {number} to End value
     */
    animateNumberChange(element, from, to) {
        const duration = 500; // ms
        const steps = 20;
        const stepDuration = duration / steps;
        const increment = (to - from) / steps;
        
        let current = from;
        let step = 0;

        const interval = setInterval(() => {
            step++;
            current += increment;
            
            if (step >= steps) {
                element.textContent = to;
                clearInterval(interval);
            } else {
                element.textContent = Math.round(current);
            }
        }, stepDuration);
    }

    /**
     * Setup card interaction handlers
     */
    setupCardInteractions() {
        const cards = document.querySelectorAll('.oc-course-card');
        
        cards.forEach(card => {
            // Mouse events
            card.addEventListener('mouseenter', () => {
                this.handleCardHover(card, true);
            });
            
            card.addEventListener('mouseleave', () => {
                this.handleCardHover(card, false);
            });

            // Click events
            card.addEventListener('click', (e) => {
                // Don't trigger if clicking buttons or links inside
                if (e.target.closest('button, a')) return;
                this.handleCardClick(card);
            });
        });

        if (this.config.debug) {
        }
    }

    /**
     * Handle card hover
     * @param {HTMLElement} card Card element
     * @param {boolean} isHovering Is hovering
     */
    handleCardHover(card, isHovering) {
        const progressBar = card.querySelector('.oc-progress-bar');
        if (!progressBar) return;

        if (isHovering) {
            // Smooth transition on hover
            progressBar.style.transition = 'all 0.3s ease';
        }
    }

    /**
     * Handle card click
     * @param {HTMLElement} card Card element
     */
    handleCardClick(card) {
        const validationId = card.getAttribute('data-validation-id');
        if (!validationId) return;

        if (this.config.debug) {
        }

        // Future: Open details modal or navigate to details page
        // For now, just log the interaction
    }

    /**
     * Setup keyboard navigation for accessibility
     */
    setupKeyboardNav() {
        const cards = document.querySelectorAll('.oc-course-card');
        
        cards.forEach((card, index) => {
            // Make focusable
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
            
            // Accessible label
            const courseName = card.querySelector('.course-name strong');
            const label = courseName ? courseName.textContent : `Curs ${index + 1}`;
            card.setAttribute('aria-label', label);

            // Keyboard events
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleCardClick(card);
                }
                
                // Arrow navigation
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.focusNextCard(cards, index);
                }
                
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.focusPrevCard(cards, index);
                }
            });
        });

        if (this.config.debug) {
        }
    }

    /**
     * Focus next card
     * @param {NodeList} cards All cards
     * @param {number} currentIndex Current index
     */
    focusNextCard(cards, currentIndex) {
        const nextIndex = (currentIndex + 1) % cards.length;
        cards[nextIndex].focus();
    }

    /**
     * Focus previous card
     * @param {NodeList} cards All cards
     * @param {number} currentIndex Current index
     */
    focusPrevCard(cards, currentIndex) {
        const prevIndex = currentIndex === 0 ? cards.length - 1 : currentIndex - 1;
        cards[prevIndex].focus();
    }

    /**
     * Setup lazy loading for images
     */
    setupLazyLoading() {
        const images = document.querySelectorAll('.oc-course-card img[data-src]');
        
        if (!images.length) return;

        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px'
            });

            images.forEach(img => imageObserver.observe(img));
            
            if (this.config.debug) {
            }
        } else {
            // Fallback pentru browsere vechi
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
        }
    }

    /**
     * Setup Intersection Observer for scroll animations
     */
    setupIntersectionObserver() {
        const cards = document.querySelectorAll('.oc-course-card');
        
        if (!cards.length || !('IntersectionObserver' in window)) return;

        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Unobserve after animation to improve performance
                    cardObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });

        cards.forEach(card => {
            card.classList.add('animate-on-scroll');
            cardObserver.observe(card);
        });

        if (this.config.debug) {
        }
    }

    /**
     * Setup visibility change handler
     * Pause auto-refresh when tab is hidden
     */
    setupVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (this.config.debug) {
                }
            } else {
                if (this.config.debug) {
                }
                // Refresh immediately when tab becomes visible
                if (this.config.autoRefresh) {
                    this.refreshSessionData();
                }
            }
        });
    }

    /**
     * Cleanup on destroy
     */
    destroy() {
        if (this.state.refreshInterval) {
            clearInterval(this.state.refreshInterval);
        }
        
        if (this.config.debug) {
        }
    }

    /**
     * Debug helper
     * @param {...any} args Arguments to log
     */
    debug(...args) {
        if (this.config.debug) {
        }
    }
}

// ========================================
// 🎯 QR CODE MODAL HANDLER
// ========================================

/**
 * QR Code Modal Manager
 * Handles showing/hiding QR code modal with full member information
 */
class QRCodeModal {
    constructor() {
        this.modal = null;
        this.overlay = null;
        this.closeBtn = null;
        this.isOpen = false;
        
        this.init();
    }

    /**
     * Initialize modal
     */
    init() {
        // Wait for DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    /**
     * Setup modal elements and event listeners
     */
    setup() {
        this.modal = document.getElementById('oc-qr-modal');
        if (!this.modal) return;

        this.overlay = this.modal.querySelector('.oc-qr-modal-overlay');
        this.closeBtn = this.modal.querySelector('.oc-qr-modal-close');

        // Bind events
        this.bindEvents();
    }

    /**
     * Bind all modal event listeners
     */
    bindEvents() {
        // Bind click events on all "Show QR" buttons
        document.addEventListener('click', (e) => {
            const button = e.target.closest('.oc-btn-show-qr');
            if (button) {
                e.preventDefault();
                this.openModal(button);
            }
        });

        // Close button
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.closeModal());
        }

        // Overlay click
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeModal());
        }

        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeModal();
            }
        });
    }

    /**
     * Open modal with QR data
     * @param {HTMLElement} button Button element that triggered open
     */
    openModal(button) {
        if (!this.modal) return;

        // Extract data from button attributes
        const data = {
            validationId: button.dataset.validationId,
            productName: button.dataset.productName,
            qrUrl: button.dataset.qrUrl,
            userName: button.dataset.userName || 'Utilizator',
            expiresAt: button.dataset.expiresAt,
            sessionsRemaining: button.dataset.sessionsRemaining
        };

        // Populate modal with data
        this.populateModal(data);

        // Show modal
        this.modal.style.display = 'flex';
        this.isOpen = true;

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Focus trap
        this.setupFocusTrap();

        // Trigger animation
        requestAnimationFrame(() => {
            this.modal.classList.add('oc-qr-modal-open');
        });
    }

    /**
     * Populate modal with membership data
     * @param {Object} data Membership data
     */
    populateModal(data) {
        // Set title
        const titleEl = document.getElementById('oc-qr-modal-title');
        if (titleEl) {
            titleEl.textContent = 'Codul Tău QR';
        }

        // Set QR image
        const imageEl = document.getElementById('oc-qr-modal-image');
        if (imageEl && data.qrUrl) {
            imageEl.src = data.qrUrl;
            imageEl.alt = `QR Code pentru ${data.productName}`;
        }

        // Set download link
        const downloadBtn = document.getElementById('oc-qr-download-btn');
        if (downloadBtn && data.qrUrl) {
            downloadBtn.href = data.qrUrl;
            const safeName = (data.productName || 'abonament').replace(/[^a-z0-9]/gi, '-').toLowerCase();
            downloadBtn.download = `qr-${safeName}.png`;
        }

        // Set user name
        const userEl = document.getElementById('oc-qr-info-user');
        if (userEl) {
            userEl.textContent = data.userName;
        }

        // Set product name
        const productEl = document.getElementById('oc-qr-info-product');
        if (productEl) {
            productEl.textContent = data.productName;
        }

        // Set expiration date
        const expiresEl = document.getElementById('oc-qr-info-expires');
        if (expiresEl && data.expiresAt) {
            expiresEl.textContent = this.formatDateForDisplay(data.expiresAt);
        }

        // Set sessions remaining
        const sessionsEl = document.getElementById('oc-qr-info-sessions');
        if (sessionsEl) {
            sessionsEl.textContent = `${data.sessionsRemaining} ședințe`;
            
            // Add warning color if low
            if (parseInt(data.sessionsRemaining) < 3) {
                sessionsEl.style.color = '#e74c3c';
                sessionsEl.style.fontWeight = 'bold';
            }
        }
    }

    formatDateForDisplay(dateValue) {
        if (!dateValue) {
            return '';
        }

        const normalized = `${dateValue}`.includes('T') ? `${dateValue}` : `${dateValue}T00:00:00`;
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return `${dateValue}`;
        }

        const config = window.ocMembershipData || {};
        const locale = (config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
        const wpFormat = (config.dateFormat || 'Y-m-d').toString();

        const day = date.getDate();
        const month = date.getMonth() + 1;
        const year = date.getFullYear();

        const tokenMap = {
            d: String(day).padStart(2, '0'),
            j: String(day),
            m: String(month).padStart(2, '0'),
            n: String(month),
            Y: String(year),
            y: String(year).slice(-2),
            F: date.toLocaleString(locale, { month: 'long' }),
            M: date.toLocaleString(locale, { month: 'short' }),
            l: date.toLocaleString(locale, { weekday: 'long' }),
            D: date.toLocaleString(locale, { weekday: 'short' })
        };

        let formatted = '';
        let escaped = false;

        for (const ch of wpFormat) {
            if (escaped) {
                formatted += ch;
                escaped = false;
                continue;
            }

            if (ch === '\\') {
                escaped = true;
                continue;
            }

            formatted += tokenMap[ch] ?? ch;
        }

        return formatted;
    }

    /**
     * Close modal
     */
    closeModal() {
        if (!this.modal || !this.isOpen) return;

        // Trigger closing animation
        this.modal.classList.remove('oc-qr-modal-open');

        // Wait for animation to finish
        setTimeout(() => {
            this.modal.style.display = 'none';
            this.isOpen = false;

            // Restore body scroll
            document.body.style.overflow = '';

            // Clear focus trap
            this.removeFocusTrap();
        }, 300); // Match CSS transition duration
    }

    /**
     * Setup focus trap inside modal
     */
    setupFocusTrap() {
        const focusableElements = this.modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        this.handleFocusTrap = (e) => {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        };

        this.modal.addEventListener('keydown', this.handleFocusTrap);

        // Focus first element
        firstElement.focus();
    }

    /**
     * Remove focus trap
     */
    removeFocusTrap() {
        if (this.handleFocusTrap) {
            this.modal.removeEventListener('keydown', this.handleFocusTrap);
            this.handleFocusTrap = null;
        }
    }
}

// Initialize QR Modal
const qrModal = new QRCodeModal();

// ========================================
// INITIALIZATION
// ========================================

// Initialize dashboard when DOM is ready
(function() {
    'use strict';
    
    // Check if we're on a page with membership dashboard
    const hasDashboard = document.querySelector('.oc-membership-dashboard');
    if (!hasDashboard) {
        return;
    }

    // Initialize
    const dashboard = new MembershipDashboard();
    
    // Expose to global scope for debugging
    if (window.ocMembershipData && window.ocMembershipData.debug) {
        window.ocMembershipDashboard = dashboard;
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        dashboard.destroy();
    });
})();

