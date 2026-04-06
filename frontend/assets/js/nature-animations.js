/**
 * Nature-Themed Advanced Animations & Interactions
 * Using GSAP-style animations and Three.js concepts
 * Version: 2.1.0
 */

class NatureUI {
    constructor() {
        this.init();
    }

    init() {
        this.createLeafParticles();
        this.initCardAnimations();
        this.initButtonAnimations();
        this.initScrollAnimations();
        this.initModalHandlers();
        this.initTabSystem();
        this.initToasts();
        this.initSearchAnimations();
    }

    /**
     * Create falling leaf particles for background effect
     */
    createLeafParticles() {
        const particleCount = 15;
        const colors = ['#4CAF50', '#66BB6A', '#81C784', '#A5D6A7'];

        for (let i = 0; i < particleCount; i++) {
            const leaf = document.createElement('div');
            leaf.className = 'leaf-particle';
            leaf.style.left = `${Math.random() * 100}%`;
            leaf.style.animationDelay = `${Math.random() * 10}s`;
            leaf.style.animationDuration = `${10 + Math.random() * 10}s`;
            leaf.style.background = colors[Math.floor(Math.random() * colors.length)];
            leaf.style.opacity = 0.3 + Math.random() * 0.3;

            document.body.appendChild(leaf);
        }
    }

    /**
     * Initialize 3D card tilt effect on hover
     */
    initCardAnimations() {
        const cards = document.querySelectorAll('.card-3d, .nature-card');

        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;

                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
            });
        });
    }

    /**
     * Button growth animation on click
     */
    initButtonAnimations() {
        const buttons = document.querySelectorAll('.btn, .btn-primary, .btn-gold, .btn-earth');

        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Add growth animation class
                this.classList.add('btn-grow');

                // Create ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = `${size}px`;
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                ripple.classList.add('ripple');

                this.appendChild(ripple);

                // Remove animation class after completion
                setTimeout(() => {
                    this.classList.remove('btn-grow');
                    ripple.remove();
                }, 600);
            });
        });
    }

    /**
     * Scroll-triggered fade-in animations
     */
    initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('slide-up');
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        // Observe all cards, sections, and tables
        const elements = document.querySelectorAll('.nature-card, .stat-card, .nature-table-container, .icon-card');
        elements.forEach(el => {
            el.style.opacity = '0';
            observer.observe(el);
        });
    }

    /**
     * Modal system with backdrop animations
     */
    initModalHandlers() {
        // Open modal
        document.querySelectorAll('[data-modal-target]').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const modalId = trigger.dataset.modalTarget;
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            });
        });

        // Close modal
        document.querySelectorAll('.modal-close, [data-modal-close]').forEach(closeBtn => {
            closeBtn.addEventListener('click', () => {
                const modal = closeBtn.closest('.modal-backdrop');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    backdrop.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    /**
     * Tab system with smooth transitions
     */
    initTabSystem() {
        const tabButtons = document.querySelectorAll('.tab-button');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabGroup = button.closest('.nature-tabs');
                const targetId = button.dataset.tab;

                // Remove active from all tabs in this group
                tabGroup.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                tabGroup.querySelectorAll('.tab-panel').forEach(panel => {
                    panel.classList.remove('active');
                });

                // Add active to clicked tab
                button.classList.add('active');
                const targetPanel = tabGroup.querySelector(`#${targetId}`);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
            });
        });
    }

    /**
     * Toast notification system
     */
    initToasts() {
        window.showToast = (message, type = 'success', duration = 3000) => {
            const container = document.querySelector('.toast-container') || this.createToastContainer();

            const toast = document.createElement('div');
            toast.className = `toast alert-${type}`;

            const icon = this.getToastIcon(type);
            toast.innerHTML = `
                <span class="alert-icon">${icon}</span>
                <div class="alert-content">
                    <div class="alert-message">${message}</div>
                </div>
                <button class="modal-close" style="width: 28px; height: 28px; font-size: 14px;" onclick="this.parentElement.remove()">×</button>
            `;

            container.appendChild(toast);

            // Auto-remove after duration
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 300ms ease-in-out';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        };
    }

    createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    }

    getToastIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    /**
     * Search bar animations
     */
    initSearchAnimations() {
        const searchInputs = document.querySelectorAll('.search-input');

        searchInputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.05)';
                this.parentElement.style.transition = 'transform 300ms ease';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    }

    /**
     * Progress bar animation
     */
    static animateProgress(element, targetValue, duration = 1000) {
        const start = parseFloat(element.style.width) || 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const currentValue = start + (targetValue - start) * easeOutCubic;

            element.style.width = `${currentValue}%`;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    /**
     * Counter animation for stat cards
     */
    static animateCounter(element, targetValue, duration = 2000) {
        const start = parseInt(element.textContent) || 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const easeOutQuad = 1 - Math.pow(1 - progress, 2);
            const currentValue = Math.floor(start + (targetValue - start) * easeOutQuad);

            element.textContent = currentValue.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    /**
     * Sidebar toggle for mobile
     */
    static toggleSidebar() {
        const sidebar = document.querySelector('.nature-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
    }

    /**
     * Dynamic gradient shift based on scroll position
     */
    initDynamicGradients() {
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollPercent = scrollTop / (document.documentElement.scrollHeight - window.innerHeight);

            // Update hero gradient based on scroll
            const heroes = document.querySelectorAll('.hero-nature');
            heroes.forEach(hero => {
                const hue = 120 + (scrollPercent * 60); // Shift from green to teal
                hero.style.background = `linear-gradient(135deg,
                    hsl(${hue}, 50%, 50%) 0%,
                    hsl(${hue - 20}, 60%, 40%) 100%)`;
            });

            lastScroll = scrollTop;
        });
    }

    /**
     * Form validation with nature-themed feedback
     */
    static validateForm(formElement) {
        const inputs = formElement.querySelectorAll('.form-input, .form-select, .form-textarea');
        let isValid = true;

        inputs.forEach(input => {
            if (input.hasAttribute('required') && !input.value.trim()) {
                isValid = false;
                input.style.borderColor = 'var(--error)';
                input.style.animation = 'shake 0.5s';

                // Remove shake animation after completion
                setTimeout(() => {
                    input.style.animation = '';
                }, 500);
            } else {
                input.style.borderColor = 'var(--nature-green-500)';
            }
        });

        return isValid;
    }

    /**
     * Copy to clipboard with toast notification
     */
    static copyToClipboard(text, successMessage = 'Copied to clipboard!') {
        navigator.clipboard.writeText(text).then(() => {
            if (window.showToast) {
                window.showToast(successMessage, 'success');
            }
        }).catch(err => {
            console.error('Failed to copy:', err);
            if (window.showToast) {
                window.showToast('Failed to copy text', 'error');
            }
        });
    }

    /**
     * Confirm dialog with nature theme
     */
    static confirm(message, onConfirm, onCancel) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop active';

        backdrop.innerHTML = `
            <div class="modal-container" style="max-width: 400px;">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Action</h3>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" data-action="cancel">Cancel</button>
                    <button class="btn btn-primary" data-action="confirm">Confirm</button>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);

        backdrop.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            backdrop.remove();
            if (onConfirm) onConfirm();
        });

        backdrop.querySelector('[data-action="cancel"]').addEventListener('click', () => {
            backdrop.remove();
            if (onCancel) onCancel();
        });
    }
}

// Shake animation for validation errors
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: scale(0);
        animation: rippleEffect 0.6s ease-out;
        pointer-events: none;
    }

    @keyframes rippleEffect {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize on DOM load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new NatureUI();
    });
} else {
    new NatureUI();
}

// Export for use in other scripts
window.NatureUI = NatureUI;
