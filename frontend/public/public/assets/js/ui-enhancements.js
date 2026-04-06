/**
 * SAMS UI Enhancements - JavaScript Module
 * Modern UI interactions and enhancements for the SAMS system
 */

// Global SAMS UI object
window.SAMS_UI = {
    // Initialize all UI enhancements
    init: function() {
        this.initSidebar();
        this.initNavbar();
        this.initForms();
        this.initTables();
        this.initCards();
        this.initAnimations();
        this.initResponsive();
        this.initTooltips();
        this.initModals();
        this.initNotifications();
        this.initTheme();
    },

    // Sidebar functionality
    initSidebar: function() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        const mainContent = document.querySelector('.main-content');

        if (!sidebar) return;

        // Toggle sidebar on mobile
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('active');
                }
            });
        }

        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Handle sidebar navigation
        const sidebarLinks = sidebar.querySelectorAll('.sidebar-nav .nav-link');
        sidebarLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Remove active class from all links
                sidebarLinks.forEach(function(l) {
                    l.classList.remove('active');
                });
                // Add active class to clicked link
                this.classList.add('active');
            });
        });

        // Auto-hide sidebar on mobile after selection
        if (window.innerWidth <= 640) {
            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    setTimeout(function() {
                        sidebar.classList.remove('active');
                        if (sidebarOverlay) {
                            sidebarOverlay.classList.remove('active');
                        }
                    }, 300);
                });
            });
        }
    },

    // Navbar functionality
    initNavbar: function() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;

        // Handle navbar scroll effect
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });

        // Handle dropdown menus
        const dropdowns = navbar.querySelectorAll('.dropdown');
        dropdowns.forEach(function(dropdown) {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    menu.classList.toggle('show');
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!navbar.contains(e.target)) {
                const openDropdowns = navbar.querySelectorAll('.dropdown-menu.show');
                openDropdowns.forEach(function(menu) {
                    menu.classList.remove('show');
                });
            }
        });
    },

    // Form enhancements
    initForms: function() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(function(form) {
            // Add form validation
            form.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    this.classList.add('was-validated');
                    
                    // Focus first invalid field
                    const firstInvalid = this.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            });

            // Handle input focus effects
            const inputs = form.querySelectorAll('.form-control, .form-select');
            inputs.forEach(function(input) {
                // Add focus effects
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });

                // Add input validation feedback
                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                });
            });

            // Handle file inputs
            const fileInputs = form.querySelectorAll('input[type="file"]');
            fileInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    const fileName = this.files[0]?.name || '';
                    const label = this.parentElement.querySelector('.file-name');
                    if (label) {
                        label.textContent = fileName;
                    }
                });
            });
        });

        // Initialize floating labels
        const floatingLabels = document.querySelectorAll('.form-floating');
        floatingLabels.forEach(function(floatingLabel) {
            const input = floatingLabel.querySelector('.form-control');
            const label = floatingLabel.querySelector('.form-label');
            
            if (input && label) {
                // Handle input focus
                input.addEventListener('focus', function() {
                    floatingLabel.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        floatingLabel.classList.remove('focused');
                    }
                });
                
                // Handle input value
                input.addEventListener('input', function() {
                    if (this.value) {
                        floatingLabel.classList.add('focused');
                    } else {
                        floatingLabel.classList.remove('focused');
                    }
                });
            }
        });
    },

    // Table enhancements
    initTables: function() {
        const tables = document.querySelectorAll('.table');
        
        tables.forEach(function(table) {
            // Add table sorting
            const sortableHeaders = table.querySelectorAll('th[data-sortable]');
            sortableHeaders.forEach(function(header) {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    sortTable(table, header);
                });
            });

            // Add table search functionality
            const tableSearch = table.parentElement.querySelector('.table-search');
            if (tableSearch) {
                tableSearch.addEventListener('input', function() {
                    searchTable(table, this.value);
                });
            }

            // Add table pagination
            const tablePagination = table.parentElement.querySelector('.table-pagination');
            if (tablePagination) {
                initTablePagination(table, tablePagination);
            }
        });
    },

    // Card enhancements
    initCards: function() {
        const cards = document.querySelectorAll('.card');
        
        cards.forEach(function(card) {
            // Add hover effects
            card.addEventListener('mouseenter', function() {
                this.classList.add('hover-lift');
            });
            
            card.addEventListener('mouseleave', function() {
                this.classList.remove('hover-lift');
            });

            // Handle card actions
            const cardActions = card.querySelectorAll('.card-action');
            cardActions.forEach(function(action) {
                action.addEventListener('click', function(e) {
                    e.preventDefault();
                    handleCardAction(this, card);
                });
            });
        });
    },

    // Animation enhancements
    initAnimations: function() {
        // Add intersection observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe elements with animation classes
        const animatedElements = document.querySelectorAll('.animate-on-scroll');
        animatedElements.forEach(function(element) {
            observer.observe(element);
        });

        // Add loading animations
        const loadingElements = document.querySelectorAll('.loading');
        loadingElements.forEach(function(element) {
            element.innerHTML = '<div class="spinner"></div> Loading...';
        });
    },

    // Responsive enhancements
    initResponsive: function() {
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResponsiveLayout();
            }, 250);
        });

        // Initial responsive layout
        handleResponsiveLayout();
    },

    // Tooltip functionality
    initTooltips: function() {
        const tooltips = document.querySelectorAll('[data-tooltip]');
        
        tooltips.forEach(function(element) {
            element.addEventListener('mouseenter', function() {
                showTooltip(this);
            });
            
            element.addEventListener('mouseleave', function() {
                hideTooltip(this);
            });
        });
    },

    // Modal functionality
    initModals: function() {
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(function(modal) {
            // Handle modal triggers
            const triggers = document.querySelectorAll('[data-target="#' + modal.id + '"]');
            triggers.forEach(function(trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    showModal(modal);
                });
            });

            // Handle modal close
            const closeButtons = modal.querySelectorAll('.modal-close, [data-dismiss="modal"]');
            closeButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    hideModal(modal);
                });
            });

            // Close modal on backdrop click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    hideModal(modal);
                }
            });

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    hideModal(modal);
                }
            });
        });
    },

    // Notification system
    initNotifications: function() {
        // Auto-hide notifications after 5 seconds
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(function(notification) {
            setTimeout(function() {
                hideNotification(notification);
            }, 5000);
        });

        // Handle notification close buttons
        const closeButtons = document.querySelectorAll('.notification-close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                hideNotification(this.parentElement);
            });
        });
    },

    // Theme management
    initTheme: function() {
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                toggleTheme();
            });
        }

        // Load saved theme preference
        const savedTheme = localStorage.getItem('sams-theme');
        if (savedTheme) {
            document.body.classList.add(savedTheme);
        }
    }
};

// Helper functions
function sortTable(table, header) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentElement.children).indexOf(header);
    const isAscending = !header.classList.contains('sort-asc');

    // Remove existing sort classes
    header.parentElement.querySelectorAll('th').forEach(function(th) {
        th.classList.remove('sort-asc', 'sort-desc');
    });

    // Add sort class to current header
    header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');

    // Sort rows
    rows.sort(function(a, b) {
        const aValue = a.children[columnIndex].textContent.trim();
        const bValue = b.children[columnIndex].textContent.trim();
        
        if (isAscending) {
            return aValue.localeCompare(bValue);
        } else {
            return bValue.localeCompare(aValue);
        }
    });

    // Re-append sorted rows
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

function searchTable(table, searchTerm) {
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        
        row.style.display = matches ? '' : 'none';
    });
}

function initTablePagination(table, pagination) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const rowsPerPage = 10;
    let currentPage = 1;
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        const startIndex = (page - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        
        rows.forEach(function(row, index) {
            row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
        });

        // Update pagination info
        const info = pagination.querySelector('.pagination-info');
        if (info) {
            info.textContent = `Showing ${startIndex + 1} to ${Math.min(endIndex, rows.length)} of ${rows.length} entries`;
        }

        // Update pagination buttons
        const buttons = pagination.querySelectorAll('.pagination-button');
        buttons.forEach(function(button, index) {
            button.classList.toggle('active', index + 1 === currentPage);
        });
    }

    // Create pagination buttons
    const paginationButtons = pagination.querySelector('.pagination-buttons');
    if (paginationButtons) {
        paginationButtons.innerHTML = '';
        
        for (let i = 1; i <= totalPages; i++) {
            const button = document.createElement('button');
            button.className = 'pagination-button btn btn-sm';
            button.textContent = i;
            button.addEventListener('click', function() {
                currentPage = i;
                showPage(currentPage);
            });
            paginationButtons.appendChild(button);
        }
    }

    // Show first page
    showPage(1);
}

function handleCardAction(action, card) {
    const actionType = action.dataset.action;
    
    switch (actionType) {
        case 'expand':
            toggleCardExpand(card);
            break;
        case 'refresh':
            refreshCardContent(card);
            break;
        case 'delete':
            deleteCard(card);
            break;
        case 'edit':
            editCardContent(card);
            break;
        default:
            console.log('Unknown action:', actionType);
    }
}

function toggleCardExpand(card) {
    const content = card.querySelector('.card-content');
    if (content) {
        content.classList.toggle('expanded');
        const expandIcon = card.querySelector('.expand-icon');
        if (expandIcon) {
            expandIcon.classList.toggle('fa-expand-up');
            expandIcon.classList.toggle('fa-expand-down');
        }
    }
}

function refreshCardContent(card) {
    const content = card.querySelector('.card-content');
    if (content) {
        content.innerHTML = '<div class="spinner"></div> Loading...';
        
        // Simulate content refresh
        setTimeout(function() {
            content.innerHTML = 'Content refreshed successfully!';
            setTimeout(function() {
                location.reload();
            }, 1000);
        }, 1000);
    }
}

function deleteCard(card) {
    if (confirm('Are you sure you want to delete this item?')) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        
        setTimeout(function() {
            card.remove();
            showNotification('Item deleted successfully', 'success');
        }, 300);
    }
}

function editCardContent(card) {
    const content = card.querySelector('.card-content');
    if (content) {
        const currentContent = content.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentContent;
        input.className = 'form-control';
        
        content.innerHTML = '';
        content.appendChild(input);
        input.focus();
        
        input.addEventListener('blur', function() {
            content.textContent = input.value;
            showNotification('Content updated successfully', 'success');
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                input.blur();
            }
        });
    }
}

function handleResponsiveLayout() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 640) {
        if (sidebar) {
            sidebar.classList.remove('desktop');
            sidebar.classList.add('mobile');
        }
        if (mainContent) {
            mainContent.style.marginLeft = '0';
        }
    } else {
        if (sidebar) {
            sidebar.classList.remove('mobile');
            sidebar.classList.add('desktop');
        }
        if (mainContent) {
            mainContent.style.marginLeft = '250px';
        }
    }
}

function showTooltip(element) {
    const text = element.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.position = 'absolute';
    tooltip.style.background = '#333';
    tooltip.style.color = '#fff';
    tooltip.style.padding = '0.5rem';
    tooltip.style.borderRadius = '0.25rem';
    tooltip.style.fontSize = '0.875rem';
    tooltip.style.zIndex = '1000';
    tooltip.style.whiteSpace = 'nowrap';
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    element.tooltip = tooltip;
}

function hideTooltip(element) {
    if (element.tooltip) {
        element.tooltip.remove();
        element.tooltip = null;
    }
}

function showModal(modal) {
    modal.classList.add('show');
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
    
    // Focus first input
    const firstInput = modal.querySelector('input, textarea, select');
    if (firstInput) {
        setTimeout(function() {
            firstInput.focus();
        }, 100);
    }
}

function hideModal(modal) {
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.padding = '1rem';
    notification.style.borderRadius = '0.5rem';
    notification.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    notification.style.maxWidth = '300px';
    
    // Set background color based on type
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.style.color = '#fff';
    
    document.body.appendChild(notification);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        hideNotification(notification);
    }, 5000);
    
    // Handle close button
    const closeButton = notification.querySelector('.notification-close');
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            hideNotification(notification);
        });
    }
}

function hideNotification(notification) {
    notification.style.transition = 'all 0.3s ease';
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100%)';
    
    setTimeout(function() {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

function toggleTheme() {
    const body = document.body;
    const isDark = body.classList.contains('dark-theme');
    
    if (isDark) {
        body.classList.remove('dark-theme');
        localStorage.setItem('sams-theme', 'light-theme');
    } else {
        body.classList.add('dark-theme');
        localStorage.setItem('sams-theme', 'dark-theme');
    }
}

// Initialize UI enhancements when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    SAMS_UI.init();
});

// Re-initialize on dynamic content
window.SAMS_UI_reinit = function() {
    SAMS_UI.init();
};

// Export for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SAMS_UI;
}
