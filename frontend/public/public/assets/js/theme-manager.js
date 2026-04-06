/**
 * SAMS Theme Manager
 * Centralized theme management with instant application
 * Handles dark/light/system theme modes with database and localStorage synchronization
 */

window.SAMS_ThemeManager = (function() {
    'use strict';
    
    // Private variables
    let currentTheme = 'system';
    let customColors = {};
    let systemTheme = 'light';
    let userId = null;
    let isInitialized = false;
    
    // Theme configuration
    const config = {
        apiEndpoint: '/admin/api/theme',
        supportedThemes: ['light', 'dark', 'system'],
        defaultTheme: 'system',
        localStorageKey: 'sams_theme_preference',
        customColorsKey: 'sams_custom_colors',
        syncInterval: 30000, // 30 seconds
        transitionDuration: 300
    };
    
    // CSS variables mapping
    const cssVariables = {
        primary: '--primary',
        secondary: '--secondary',
        accent: '--accent',
        success: '--success',
        warning: '--warning',
        error: '--error',
        bgPrimary: '--bg-primary',
        bgSecondary: '--bg-secondary',
        bgTertiary: '--bg-tertiary',
        textPrimary: '--text-primary',
        textSecondary: '--text-secondary',
        textMuted: '--text-muted',
        textInverse: '--text-inverse',
        borderPrimary: '--border-primary',
        borderSecondary: '--border-secondary'
    };
    
    /**
     * Initialize theme manager
     */
    function init(options = {}) {
        if (isInitialized) return;
        
        // Merge configuration
        Object.assign(config, options);
        
        // Get user ID from page
        userId = window.SAMS_USER_ID || null;
        
        // Detect system theme
        detectSystemTheme();
        
        // Load theme from localStorage
        loadFromLocalStorage();
        
        // Load theme from server if user is logged in
        if (userId) {
            loadFromServer();
        }
        
        // Apply theme
        applyTheme();
        
        // Start periodic sync
        startPeriodicSync();
        
        // Listen for system theme changes
        listenForSystemThemeChanges();
        
        isInitialized = true;
        
        // Dispatch initialization event
        dispatchEvent('theme:initialized', {
            theme: currentTheme,
            systemTheme: systemTheme,
            customColors: customColors
        });
    }
    
    /**
     * Detect system theme preference
     */
    function detectSystemTheme() {
        // Check CSS media query
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemTheme = mediaQuery.matches ? 'dark' : 'light';
        
        // Listen for changes
        mediaQuery.addEventListener('change', (e) => {
            systemTheme = e.matches ? 'dark' : 'light';
            if (currentTheme === 'system') {
                applyTheme();
                dispatchEvent('theme:system-changed', { systemTheme });
            }
        });
    }
    
    /**
     * Load theme from localStorage
     */
    function loadFromLocalStorage() {
        try {
            const stored = localStorage.getItem(config.localStorageKey);
            const colors = localStorage.getItem(config.customColorsKey);
            
            if (stored) {
                currentTheme = JSON.parse(stored);
            }
            
            if (colors) {
                customColors = JSON.parse(colors);
            }
        } catch (error) {
            console.warn('Error loading theme from localStorage:', error);
        }
    }
    
    /**
     * Save theme to localStorage
     */
    function saveToLocalStorage() {
        try {
            localStorage.setItem(config.localStorageKey, JSON.stringify(currentTheme));
            localStorage.setItem(config.customColorsKey, JSON.stringify(customColors));
        } catch (error) {
            console.warn('Error saving theme to localStorage:', error);
        }
    }
    
    /**
     * Load theme from server
     */
    function loadFromServer() {
        if (!userId) return;
        
        fetch(`${config.apiEndpoint}?user_id=${userId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentTheme = data.theme_data.user_preference.theme;
                customColors = data.theme_data.user_preference.custom_colors || {};
                
                // Apply server theme
                applyTheme();
                
                // Update localStorage
                saveToLocalStorage();
                
                dispatchEvent('theme:loaded-from-server', data.theme_data);
            }
        })
        .catch(error => {
            console.warn('Error loading theme from server:', error);
        });
    }
    
    /**
     * Save theme to server
     */
    function saveToServer(theme, colors = {}) {
        if (!userId) return Promise.resolve({ success: false, message: 'User not logged in' });
        
        return fetch(config.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                user_id: userId,
                theme: theme,
                custom_colors: colors
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dispatchEvent('theme:saved-to-server', data);
            }
            return data;
        })
        .catch(error => {
            console.warn('Error saving theme to server:', error);
            return { success: false, message: 'Network error' };
        });
    }
    
    /**
     * Apply theme to the page
     */
    function applyTheme() {
        const effectiveTheme = getEffectiveTheme();
        
        // Remove all theme classes
        document.body.classList.remove('theme-light', 'theme-dark', 'theme-system');
        
        // Add current theme class
        document.body.classList.add(`theme-${effectiveTheme}`);
        
        // Apply custom colors
        applyCustomColors();
        
        // Update CSS variables
        updateCSSVariables();
        
        // Add transition class for smooth changes
        document.body.classList.add('theme-transitioning');
        
        // Remove transition class after animation
        setTimeout(() => {
            document.body.classList.remove('theme-transitioning');
        }, config.transitionDuration);
        
        // Dispatch theme applied event
        dispatchEvent('theme:applied', {
            theme: currentTheme,
            effectiveTheme: effectiveTheme,
            systemTheme: systemTheme,
            customColors: customColors
        });
    }
    
    /**
     * Get effective theme (resolves 'system' to actual theme)
     */
    function getEffectiveTheme() {
        if (currentTheme === 'system') {
            return systemTheme;
        }
        return currentTheme;
    }
    
    /**
     * Apply custom colors
     */
    function applyCustomColors() {
        const root = document.documentElement;
        
        Object.entries(customColors).forEach(([key, value]) => {
            const cssVar = cssVariables[key];
            if (cssVar) {
                root.style.setProperty(cssVar, value);
            }
        });
    }
    
    /**
     * Update CSS variables
     */
    function updateCSSVariables() {
        const root = document.documentElement;
        const effectiveTheme = getEffectiveTheme();
        
        // Update theme-specific variables
        if (effectiveTheme === 'dark') {
            root.style.setProperty('--bg-primary', '#111827');
            root.style.setProperty('--bg-secondary', '#1a202A2');
            root.style.setProperty('--bg-tertiary', '#374151');
            root.style.setProperty('--text-primary', '#E2E8F0');
            root.style.setProperty('--text-secondary', '#A0AEC0');
            root.style.setProperty('--text-muted', '#6B7280');
            root.style.setProperty('--text-inverse', '#FFFFFF');
            root.style.setProperty('--border-primary', '#4B5563');
            root.style.setProperty('--border-secondary', '#495057');
        } else {
            root.style.setProperty('--bg-primary', '#FFFFFF');
            root.style.setProperty('--bg-secondary', '#F8F9FA');
            root.style.setProperty('--bg-tertiary', '#E9ECEF');
            root.style.setProperty('--text-primary', '#1F2937');
            root.style.setProperty('--text-secondary', '#6B7280');
            root.style.setProperty('--text-muted', '#6B7280');
            root.style.setProperty('--text-inverse', '#FFFFFF');
            root.style.setProperty('--border-primary', '#E5E7EB');
            root.style.setProperty('--border-secondary', '#DEE2E6');
        }
    }
    
    /**
     * Set theme
     */
    function setTheme(theme, colors = {}) {
        if (!config.supportedThemes.includes(theme)) {
            throw new Error(`Unsupported theme: ${theme}`);
        }
        
        const oldTheme = currentTheme;
        currentTheme = theme;
        
        // Update custom colors
        if (Object.keys(colors).length > 0) {
            customColors = { ...customColors, ...colors };
        }
        
        // Apply theme
        applyTheme();
        
        // Save to localStorage
        saveToLocalStorage();
        
        // Save to server if user is logged in
        if (userId) {
            saveToServer(currentTheme, customColors);
        }
        
        // Dispatch theme changed event
        dispatchEvent('theme:changed', {
            oldTheme: oldTheme,
            newTheme: currentTheme,
            customColors: customColors
        });
    }
    
    /**
     * Get current theme
     */
    function getTheme() {
        return {
            theme: currentTheme,
            systemTheme: systemTheme,
            effectiveTheme: getEffectiveTheme(),
            customColors: customColors
        };
    }
    
    /**
     * Toggle theme
     */
    function toggleTheme() {
        const themes = ['light', 'dark', 'system'];
        const currentIndex = themes.indexOf(currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        const nextTheme = themes[nextIndex];
        
        setTheme(nextTheme);
        
        return nextTheme;
    }
    
    /**
     * Set custom color
     */
    function setCustomColor(key, value) {
        if (!cssVariables[key]) {
            throw new Error(`Invalid color key: ${key}`);
        }
        
        customColors[key] = value;
        applyCustomColors();
        saveToLocalStorage();
        
        if (userId) {
            saveToServer(currentTheme, customColors);
        }
        
        dispatchEvent('theme:color-changed', { key, value });
    }
    
    /**
     * Remove custom color
     */
    function removeCustomColor(key) {
        if (customColors[key]) {
            delete customColors[key];
            applyCustomColors();
            saveToLocalStorage();
            
            if (userId) {
                saveToServer(currentTheme, customColors);
            }
            
            dispatchEvent('theme:color-removed', { key });
        }
    }
    
    /**
     * Reset theme to default
     */
    function resetTheme() {
        currentTheme = config.defaultTheme;
        customColors = {};
        
        applyTheme();
        saveToLocalStorage();
        
        if (userId) {
            saveToServer(currentTheme, customColors);
        }
        
        dispatchEvent('theme:reset');
    }
    
    /**
     * Start periodic sync with server
     */
    function startPeriodicSync() {
        if (!userId) return;
        
        setInterval(() => {
            loadFromServer();
        }, config.syncInterval);
    }
    
    /**
     * Listen for system theme changes
     */
    function listenForSystemThemeChanges() {
        // Already handled in detectSystemTheme()
    }
    
    /**
     * Dispatch custom event
     */
    function dispatchEvent(eventName, data = {}) {
        const event = new CustomEvent(`sams:${eventName}`, {
            detail: data,
            bubbles: true,
            cancelable: true
        });
        
        document.dispatchEvent(event);
        
        // Also call global event handler if it exists
        if (typeof window.SAMS_THEME_HANDLER === 'function') {
            window.SAMS_THEME_HANDLER(eventName, data);
        }
    }
    
    /**
     * Add event listener
     */
    function addEventListener(eventName, handler) {
        document.addEventListener(`sams:${eventName}`, (event) => {
            handler(event.detail, event);
        });
    }
    
    /**
     * Remove event listener
     */
    function removeEventListener(eventName, handler) {
        document.removeEventListener(`sams:${eventName}`, handler);
    }
    
    /**
     * Get theme statistics
     */
    function getStatistics() {
        return {
            supportedThemes: config.supportedThemes,
            currentTheme: currentTheme,
            systemTheme: systemTheme,
            effectiveTheme: getEffectiveTheme(),
            customColorsCount: Object.keys(customColors).length,
            hasLocalStorage: typeof Storage !== 'undefined',
            hasUserId: !!userId,
            isInitialized: isInitialized
        };
    }
    
    /**
     * Export theme configuration
     */
    function exportTheme() {
        return {
            theme: currentTheme,
            customColors: customColors,
            systemTheme: systemTheme,
            timestamp: new Date().toISOString(),
            version: '1.0.0'
        };
    }
    
    /**
     * Import theme configuration
     */
    function importTheme(config) {
        if (!config.theme || !config.supportedThemes.includes(config.theme)) {
            throw new Error('Invalid theme configuration');
        }
        
        setTheme(config.theme, config.customColors || {});
        
        dispatchEvent('theme:imported', config);
    }
    
    /**
     * Get available color presets
     */
    function getColorPresets() {
        return {
            default: {
                primary: '#4F46E5',
                secondary: '#64748B',
                accent: '#10B981',
                success: '#059669',
                warning: '#F59E0B',
                error: '#EF4444'
            },
            blue: {
                primary: '#2563EB',
                secondary: '#64748B',
                accent: '#0891B2',
                success: '#059669',
                warning: '#F59E0B',
                error: '#DC2626'
            },
            green: {
                primary: '#059669',
                secondary: '#64748B',
                accent: '#10B981',
                success: '#047857',
                warning: '#F59E0B',
                error: '#DC2626'
            },
            purple: {
                primary: '#7C3AED',
                secondary: '#64748B',
                accent: '#A855F7',
                success: '#059669',
                warning: '#F59E0B',
                error: '#DC2626'
            },
            red: {
                primary: '#DC2626',
                secondary: '#64748B',
                accent: '#EF4444',
                success: '#059669',
                warning: '#F59E0B',
                error: '#991B1B'
            }
        };
    }
    
    /**
     * Apply color preset
     */
    function applyColorPreset(presetName) {
        const presets = getColorPresets();
        
        if (!presets[presetName]) {
            throw new Error(`Invalid preset: ${presetName}`);
        }
        
        const preset = presets[presetName];
        Object.entries(preset).forEach(([key, value]) => {
            setCustomColor(key, value);
        });
        
        dispatchEvent('theme:preset-applied', { preset: presetName });
    }
    
    // Public API
    return {
        init,
        setTheme,
        getTheme,
        toggleTheme,
        setCustomColor,
        removeCustomColor,
        resetTheme,
        getStatistics,
        exportTheme,
        importTheme,
        getColorPresets,
        applyColorPreset,
        addEventListener,
        removeEventListener,
        getEffectiveTheme
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.SAMS_ThemeManager.init();
    });
} else {
    window.SAMS_ThemeManager.init();
}

// Make available globally
window.SAMS_ThemeManager = window.SAMS_ThemeManager || window.SAMS_ThemeManager;
