<?php
/**
 * SAMS Footer Layout
 * Shared footer for all role panels
 */

// Prevent direct access
if (!defined('SAMS_BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../../core/bootstrap.php';
}
?>

    </main>

    <!-- Footer -->
    <footer class="sams-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> SAMS - School Attendance Management System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        Version <?php echo APP_VERSION; ?> | 
                        <a href="#" class="text-white text-decoration-none">Help</a> | 
                        <a href="#" class="text-white text-decoration-none">Support</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SAMS JavaScript -->
    <script>
        // Theme Management
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.classList.contains('theme-dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.classList.remove('theme-' + currentTheme);
            body.classList.add('theme-' + newTheme);
            
            // Save theme preference
            localStorage.setItem('sams-theme', newTheme);
            
            // Update server
            fetch('<?php echo APP_URL; ?>/api/theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: newTheme })
            });
        }
        
        // Load saved theme
        function loadTheme() {
            const savedTheme = localStorage.getItem('sams-theme') || 'light';
            document.body.classList.add('theme-' + savedTheme);
        }
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sams-sidebar');
            sidebar.classList.toggle('show');
        }
        
        // AJAX helper
        function samsAjax(url, data = null, method = 'GET') {
            return fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: data ? JSON.stringify(data) : null
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
        }
        
        // Show loading state
        function showLoading(element) {
            const originalContent = element.innerHTML;
            element.innerHTML = '<span class="loading-spinner"></span> Loading...';
            element.disabled = true;
            return originalContent;
        }
        
        // Hide loading state
        function hideLoading(element, originalContent) {
            element.innerHTML = originalContent;
            element.disabled = false;
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert sams-alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }
        
        // Confirm dialog
        function samsConfirm(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Format time
        function formatTime(timeString) {
            const time = new Date('1970-01-01T' + timeString);
            return time.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Load theme
            loadTheme();
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.navbar-toggler');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', toggleSidebar);
            }
            
            // Close mobile sidebar when clicking outside
            document.addEventListener('click', function(e) {
                const sidebar = document.querySelector('.sams-sidebar');
                const navbarToggler = document.querySelector('.navbar-toggler');
                
                if (window.innerWidth <= 768 && 
                    sidebar.classList.contains('show') && 
                    !sidebar.contains(e.target) && 
                    !navbarToggler.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Handle form submissions with AJAX
            document.querySelectorAll('form[data-ajax]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalContent = showLoading(submitBtn);
                    
                    const formData = new FormData(form);
                    const data = {};
                    formData.forEach((value, key) => {
                        data[key] = value;
                    });
                    
                    samsAjax(form.action, data, form.method)
                        .then(function(response) {
                            if (response.success) {
                                showNotification(response.message || 'Operation completed successfully', 'success');
                                
                                // Reset form if needed
                                if (form.dataset.reset === 'true') {
                                    form.reset();
                                }
                                
                                // Redirect if needed
                                if (response.redirect) {
                                    window.location.href = response.redirect;
                                }
                            } else {
                                showNotification(response.message || 'Operation failed', 'danger');
                            }
                        })
                        .catch(function(error) {
                            showNotification('An error occurred: ' + error.message, 'danger');
                        })
                        .finally(function() {
                            hideLoading(submitBtn, originalContent);
                        });
                });
            });
            
            // Handle delete confirmations
            document.querySelectorAll('[data-confirm]').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const message = element.dataset.confirm;
                    const url = element.href || element.dataset.url;
                    
                    samsConfirm(message, function() {
                        window.location.href = url;
                    });
                });
            });
            
            // Auto-refresh functionality
            document.querySelectorAll('[data-refresh]').forEach(function(element) {
                const interval = parseInt(element.dataset.refresh);
                if (interval > 0) {
                    setInterval(function() {
                        const url = element.dataset.url || window.location.href;
                        samsAjax(url)
                            .then(function(response) {
                                if (response.html) {
                                    element.innerHTML = response.html;
                                }
                            })
                            .catch(function(error) {
                                console.error('Auto-refresh error:', error);
                            });
                    }, interval * 1000);
                }
            });
        });
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.error);
            showNotification('An unexpected error occurred. Please refresh the page.', 'danger');
        });
        
        // Global unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            showNotification('An unexpected error occurred. Please refresh the page.', 'danger');
        });
    </script>
    
    </body>
</html>
?>
