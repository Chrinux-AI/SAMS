<?php

/**
 * SAMS Theme Manager — Centralized Theme Utilities
 *
 * Provides:
 * - Shared Tailwind dark mode configuration
 * - Consistent color palette across all dashboards
 * - Theme injection helpers
 *
 * Usage in dashboard:
 *   <?php require __DIR__ . '/theme-manager.php'; ?>
 *   <script>
 *     tailwind.config = {
 *       ...<?php echo themeGetTailwindConfig(); ?>...
 *     };
 *   </script>
 */

/**
 * Get SAMS Tailwind color configuration
 * Returns consistent color palette for light and dark modes
 */
function themeGetTailwindConfig()
{
  return [
    'darkMode' => 'class',
    'theme' => [
      'extend' => [
        'colors' => [
          // Primary Colors
          'primary' => '#1868DB',
          'primary-container' => '#D6E4FF',
          'on-primary' => '#FFFFFF',
          'on-primary-container' => '#001B3D',

          // Secondary Colors
          'secondary' => '#545F71',
          'secondary-container' => '#D9E3F8',
          'on-secondary' => '#FFFFFF',
          'on-secondary-container' => '#111C2B',

          // Tertiary Colors
          'tertiary' => '#8F4C00',
          'tertiary-container' => '#FFDCC0',
          'on-tertiary' => '#FFFFFF',

          // Error Colors
          'error' => '#BA1A1A',
          'error-container' => '#FFDAD6',
          'on-error' => '#FFFFFF',
          'on-error-container' => '#410E0B',

          // Surface Colors
          'surface' => '#FDFBFF',
          'surface-container-low' => '#F7F9FF',
          'surface-container' => '#F1F4FA',
          'surface-container-high' => '#EBEFF5',
          'surface-container-highest' => '#E2E7EF',

          // Outline Colors
          'outline' => '#73777F',
          'outline-variant' => '#C3C7CF',

          // Background
          'background' => '#FDFBFF',
          'on-background' => '#1A1C1E',
          'on-surface' => '#1A1C1E',
          'on-surface-variant' => '#43474E',

          // Dark Mode Surface (for CSS var fallbacks)
          'dark-surface' => '#1A1C1E',
          'dark-on-surface' => '#F4EFF4',
        ],
        'borderRadius' => [
          'DEFAULT' => '0.75rem',
          'lg' => '1rem',
          'xl' => '1.25rem',
        ],
        'fontFamily' => [
          'headline' => ['Manrope', 'sans-serif'],
          'body' => ['Manrope', 'sans-serif'],
          'label' => ['Manrope', 'sans-serif'],
        ],
      ],
    ],
  ];
}

/**
 * Get theme configuration as JSON string for inline script
 */
function themeGetTailwindConfigJSON()
{
  $config = themeGetTailwindConfig();
  return json_encode($config);
}

/**
 * Generate Tailwind config script tag for dashboard <head>
 * Handles JSON serialization and proper JavaScript injection
 */
function themeInjectTailwindConfig()
{
  $config = themeGetTailwindConfig();
?>
  <script>
    tailwind.config = {
      darkMode: "<?php echo $config['darkMode']; ?>",
      theme: {
        extend: {
          colors: {
            primary: "<?php echo $config['theme']['extend']['colors']['primary']; ?>",
            "primary-container": "<?php echo $config['theme']['extend']['colors']['primary-container']; ?>",
            "on-primary": "<?php echo $config['theme']['extend']['colors']['on-primary']; ?>",
            "on-primary-container": "<?php echo $config['theme']['extend']['colors']['on-primary-container']; ?>",
            secondary: "<?php echo $config['theme']['extend']['colors']['secondary']; ?>",
            "secondary-container": "<?php echo $config['theme']['extend']['colors']['secondary-container']; ?>",
            "on-secondary": "<?php echo $config['theme']['extend']['colors']['on-secondary']; ?>",
            "on-secondary-container": "<?php echo $config['theme']['extend']['colors']['on-secondary-container']; ?>",
            tertiary: "<?php echo $config['theme']['extend']['colors']['tertiary']; ?>",
            "tertiary-container": "<?php echo $config['theme']['extend']['colors']['tertiary-container']; ?>",
            "on-tertiary": "<?php echo $config['theme']['extend']['colors']['on-tertiary']; ?>",
            error: "<?php echo $config['theme']['extend']['colors']['error']; ?>",
            "error-container": "<?php echo $config['theme']['extend']['colors']['error-container']; ?>",
            "on-error": "<?php echo $config['theme']['extend']['colors']['on-error']; ?>",
            "on-error-container": "<?php echo $config['theme']['extend']['colors']['on-error-container']; ?>",
            surface: "<?php echo $config['theme']['extend']['colors']['surface']; ?>",
            "surface-container-low": "<?php echo $config['theme']['extend']['colors']['surface-container-low']; ?>",
            "surface-container": "<?php echo $config['theme']['extend']['colors']['surface-container']; ?>",
            "surface-container-high": "<?php echo $config['theme']['extend']['colors']['surface-container-high']; ?>",
            "surface-container-highest": "<?php echo $config['theme']['extend']['colors']['surface-container-highest']; ?>",
            outline: "<?php echo $config['theme']['extend']['colors']['outline']; ?>",
            "outline-variant": "<?php echo $config['theme']['extend']['colors']['outline-variant']; ?>",
            background: "<?php echo $config['theme']['extend']['colors']['background']; ?>",
            "on-background": "<?php echo $config['theme']['extend']['colors']['on-background']; ?>",
            "on-surface": "<?php echo $config['theme']['extend']['colors']['on-surface']; ?>",
            "on-surface-variant": "<?php echo $config['theme']['extend']['colors']['on-surface-variant']; ?>",
          },
          borderRadius: {
            DEFAULT: "<?php echo $config['theme']['extend']['borderRadius']['DEFAULT']; ?>",
            lg: "<?php echo $config['theme']['extend']['borderRadius']['lg']; ?>",
            xl: "<?php echo $config['theme']['extend']['borderRadius']['xl']; ?>",
          },
          fontFamily: {
            headline: ['Manrope', 'sans-serif'],
            body: ['Manrope', 'sans-serif'],
            label: ['Manrope', 'sans-serif'],
          },
        },
      },
    };
  </script>
<?php
}

/**
 * Get shared favicon meta tags HTML
 * Uses existing logo files: logo3.png (light), logo2.png (dark)
 *
 * @param string $basePath Optional base path for assets (default: /)
 */
function themeGetFaviconMeta($basePath = '/attendance/assets/images/icons/')
{
  return <<<HTML
<!-- Favicon: Light and Dark Mode -->
<link rel="icon" type="image/png" href="${basePath}logo3.png" />
<link rel="icon" media="(prefers-color-scheme: dark)" type="image/png" href="${basePath}logo2.png" />
<link rel="shortcut icon" href="${basePath}logo3.png" />
<link rel="apple-touch-icon" href="${basePath}logo3.png" />
<link rel="apple-touch-icon" sizes="152x152" href="${basePath}logo3.png" />
<link rel="apple-touch-icon" sizes="180x180" href="${basePath}logo3.png" />
<link rel="apple-touch-icon" sizes="192x192" href="${basePath}logo3.png" />
HTML;
}

/**
 * Inject favicon meta tags
 */
function themeInjectFaviconMeta($basePath = '/attendance/assets/images/icons/')
{
  echo themeGetFaviconMeta($basePath);
}

/**
 * Get theme initialization script
 * Call this at the bottom of <body> before closing tag
 *
 * @param string $role Dashboard role (e.g., 'admin', 'student', 'teacher')
 * @param array $options Configuration options
 */
function themeGetInitScript($role = 'general', $options = [])
{
  $defaults = [
    'storageKeys' => ['sams_theme', 'sams-theme'],
    'defaultTheme' => 'light',
    'autoCloseMobileMenu' => true,
  ];
  $config = array_merge($defaults, $options);
  $storageKeysJSON = json_encode($config['storageKeys']);
  $defaultTheme = htmlspecialchars($config['defaultTheme']);
?>
  <script>
    (function() {
      const ROLE = '<?php echo htmlspecialchars($role); ?>';
      const STORAGE_KEYS = <?php echo $storageKeysJSON; ?>;
      const DEFAULT_THEME = '<?php echo $defaultTheme; ?>';

      const toolbar = document.querySelector('[data-' + ROLE + '-toolbar]');
      if (!toolbar) return;

      const toggles = Array.from(toolbar.querySelectorAll('[data-' + ROLE + '-dropdown-toggle]'));
      const menus = Array.from(toolbar.querySelectorAll('[data-' + ROLE + '-dropdown-menu]'));
      const themeChoices = Array.from(toolbar.querySelectorAll('[data-' + ROLE + '-theme-choice]'));
      const themeIcon = toolbar.querySelector('[data-' + ROLE + '-theme-icon]');

      const normalizeTheme = (theme) => theme === 'dark' ? 'dark' : 'light';

      const readTheme = () => {
        try {
          for (const key of STORAGE_KEYS) {
            const preferred = localStorage.getItem(key);
            if (preferred) return normalizeTheme(preferred);
          }
        } catch (error) {}

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          return 'dark';
        }
        return DEFAULT_THEME;
      };

      const persistTheme = (theme) => {
        try {
          for (const key of STORAGE_KEYS) {
            localStorage.setItem(key, theme);
          }
        } catch (error) {}
      };

      const updateThemeButtons = (theme) => {
        themeChoices.forEach((button) => {
          const selected = button.getAttribute('data-' + ROLE + '-theme-choice') === theme;
          button.classList.toggle('bg-primary-container', selected);
          button.classList.toggle('text-on-primary-container', selected);
          button.classList.toggle('text-on-surface', !selected);
          const check = button.querySelector('[data-' + ROLE + '-theme-check]');
          if (check) {
            check.classList.toggle('hidden', !selected);
          }
        });

        if (themeIcon) {
          themeIcon.textContent = theme === 'dark' ? 'dark_mode' : 'light_mode';
        }
      };

      const applyTheme = (theme) => {
        const normalizedTheme = normalizeTheme(theme);
        document.documentElement.setAttribute('data-theme', normalizedTheme);
        document.documentElement.classList.toggle('dark', normalizedTheme === 'dark');
        document.documentElement.classList.toggle('light', normalizedTheme !== 'dark');
        persistTheme(normalizedTheme);
        updateThemeButtons(normalizedTheme);
      };

      const closeMenus = () => {
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        menus.forEach((menu) => menu.classList.add('hidden'));
      };

      const openMenu = (menuId) => {
        const menu = document.getElementById(menuId);
        if (!menu) return;

        const isHidden = menu.classList.contains('hidden');
        closeMenus();
        if (isHidden) {
          menu.classList.remove('hidden');
          const toggle = toolbar.querySelector('[data-' + ROLE + '-dropdown-toggle="' + menuId + '"]');
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
          }
        }
      };

      toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          openMenu(toggle.getAttribute('data-' + ROLE + '-dropdown-toggle'));
        });
      });

      themeChoices.forEach((choice) => {
        choice.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          applyTheme(choice.getAttribute('data-' + ROLE + '-theme-choice') || DEFAULT_THEME);
          closeMenus();
        });
      });

      applyTheme(readTheme());

      document.addEventListener('click', (event) => {
        if (!toolbar.contains(event.target)) {
          closeMenus();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeMenus();
        }
      });
    })();
  </script>
<?php
}

?>
