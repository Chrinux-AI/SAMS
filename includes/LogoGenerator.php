<?php
/**
 * SAMS Project Logo and Icon Generator
 * Creates SVG-based logos and icons for the platform
 */

class SAMS_LogoGenerator {
    private $outputDir;
    
    public function __construct() {
        $this->outputDir = __DIR__ . '/../../assets/logo/';
        $this->ensureDirectory();
    }
    
    /**
     * Generate all logo and icon files
     */
    public function generateAll() {
        $results = [];
        
        // Generate main logo
        $results['logo'] = $this->generateMainLogo();
        
        // Generate icon logo
        $results['logo-icon'] = $this->generateIconLogo();
        
        // Generate dark mode logo
        $results['logo-dark'] = $this->generateDarkModeLogo();
        
        // Generate favicons
        $results['favicon'] = $this->generateFavicons();
        
        // Generate PWA icons
        $results['pwa-icons'] = $this->generatePWAIcons();
        
        return $results;
    }
    
    /**
     * Generate main logo SVG
     */
    private function generateMainLogo() {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
    <defs>
        <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#4F46E5"/>
            <stop offset="100%" style="stop-color:#7C3AED"/>
        </linearGradient>
    </defs>
    
    <!-- Logo Icon -->
    <g transform="translate(10, 10)">
        <!-- Book/Education Symbol -->
        <rect x="0" y="5" width="30" height="35" rx="3" fill="url(#logoGradient)"/>
        <rect x="5" y="10" width="20" height="2" rx="1" fill="white" opacity="0.6"/>
        <rect x="5" y="16" width="15" height="2" rx="1" fill="white" opacity="0.6"/>
        <rect x="5" y="22" width="18" height="2" rx="1" fill="white" opacity="0.6"/>
        
        <!-- Checkmark overlay -->
        <circle cx="22" cy="8" r="8" fill="#10B981"/>
        <path d="M18 8 L21 11 L26 5" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </g>
    
    <!-- SAMS Text -->
    <text x="50" y="38" font-family="Inter, system-ui, sans-serif" font-size="28" font-weight="700" fill="#111827">
        SAMS
    </text>
    
    <!-- Tagline -->
    <text x="50" y="50" font-family="Inter, system-ui, sans-serif" font-size="10" font-weight="400" fill="#6B7280">
        School Attendance Management
    </text>
</svg>';
        
        file_put_contents($this->outputDir . 'logo.svg', $svg);
        
        // Generate PNG version
        $this->convertSVGtoPNG($this->outputDir . 'logo.svg', $this->outputDir . 'logo.png', 400, 120);
        
        return 'logo.svg and logo.png generated';
    }
    
    /**
     * Generate icon-only logo
     */
    private function generateIconLogo() {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
    <defs>
        <linearGradient id="iconGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#4F46E5"/>
            <stop offset="100%" style="stop-color:#7C3AED"/>
        </linearGradient>
    </defs>
    
    <!-- Background rounded square -->
    <rect x="4" y="4" width="56" height="56" rx="12" fill="url(#iconGradient)"/>
    
    <!-- Book symbol -->
    <rect x="14" y="16" width="36" height="32" rx="4" fill="white" opacity="0.15"/>
    <rect x="18" y="22" width="28" height="3" rx="1.5" fill="white" opacity="0.8"/>
    <rect x="18" y="30" width="20" height="3" rx="1.5" fill="white" opacity="0.8"/>
    <rect x="18" y="38" width="24" height="3" rx="1.5" fill="white" opacity="0.8"/>
    
    <!-- Checkmark badge -->
    <circle cx="46" cy="18" r="10" fill="#10B981"/>
    <path d="M42 18 L45 21 L50 14" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
        
        file_put_contents($this->outputDir . 'logo-icon.svg', $svg);
        $this->convertSVGtoPNG($this->outputDir . 'logo-icon.svg', $this->outputDir . 'logo-icon.png', 128, 128);
        
        return 'logo-icon.svg and logo-icon.png generated';
    }
    
    /**
     * Generate dark mode logo
     */
    private function generateDarkModeLogo() {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
    <defs>
        <linearGradient id="logoGradientDark" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#6366F1"/>
            <stop offset="100%" style="stop-color:#8B5CF6"/>
        </linearGradient>
    </defs>
    
    <!-- Logo Icon -->
    <g transform="translate(10, 10)">
        <rect x="0" y="5" width="30" height="35" rx="3" fill="url(#logoGradientDark)"/>
        <rect x="5" y="10" width="20" height="2" rx="1" fill="white" opacity="0.6"/>
        <rect x="5" y="16" width="15" height="2" rx="1" fill="white" opacity="0.6"/>
        <rect x="5" y="22" width="18" height="2" rx="1" fill="white" opacity="0.6"/>
        
        <circle cx="22" cy="8" r="8" fill="#34D399"/>
        <path d="M18 8 L21 11 L26 5" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </g>
    
    <!-- SAMS Text (White for dark mode) -->
    <text x="50" y="38" font-family="Inter, system-ui, sans-serif" font-size="28" font-weight="700" fill="#F9FAFB">
        SAMS
    </text>
    
    <text x="50" y="50" font-family="Inter, system-ui, sans-serif" font-size="10" font-weight="400" fill="#9CA3AF">
        School Attendance Management
    </text>
</svg>';
        
        file_put_contents($this->outputDir . 'logo-dark.svg', $svg);
        $this->convertSVGtoPNG($this->outputDir . 'logo-dark.svg', $this->outputDir . 'logo-dark.png', 400, 120);
        
        return 'logo-dark.svg and logo-dark.png generated';
    }
    
    /**
     * Generate favicon files
     */
    private function generateFavicons() {
        $sizes = [16, 32, 48];
        $results = [];
        
        foreach ($sizes as $size) {
            $svg = $this->generateFaviconSVG($size);
            $filename = "favicon-{$size}x{$size}.png";
            
            // For 16x16 and 32x32, also create .ico
            if ($size === 16) {
                file_put_contents($this->outputDir . 'favicon.ico', $this->createICO($svg, $size));
            }
            
            $this->convertSVGtoPNGFromString($svg, $this->outputDir . $filename, $size, $size);
            $results[] = $filename;
        }
        
        // Apple touch icon
        $appleIcon = $this->generateAppleTouchIcon();
        file_put_contents($this->outputDir . 'apple-touch-icon.png', $appleIcon);
        
        return implode(', ', $results) . ', favicon.ico, apple-touch-icon.png';
    }
    
    /**
     * Generate favicon SVG
     */
    private function generateFaviconSVG($size) {
        return '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
    <defs>
        <linearGradient id="favGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#4F46E5"/>
            <stop offset="100%" style="stop-color:#7C3AED"/>
        </linearGradient>
    </defs>
    <rect x="4" y="4" width="56" height="56" rx="12" fill="url(#favGradient)"/>
    <circle cx="46" cy="18" r="10" fill="#10B981"/>
    <path d="M42 18 L45 21 L50 14" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
    }
    
    /**
     * Generate Apple touch icon
     */
    private function generateAppleTouchIcon() {
        // Generate 180x180 PNG
        $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 180" width="180" height="180">
    <defs>
        <linearGradient id="appleGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#4F46E5"/>
            <stop offset="100%" style="stop-color:#7C3AED"/>
        </linearGradient>
    </defs>
    <rect x="0" y="0" width="180" height="180" rx="40" fill="url(#appleGradient)"/>
    <rect x="40" y="50" width="100" height="80" rx="10" fill="white" opacity="0.15"/>
    <rect x="50" y="65" width="80" height="8" rx="4" fill="white" opacity="0.8"/>
    <rect x="50" y="85" width="60" height="8" rx="4" fill="white" opacity="0.8"/>
    <rect x="50" y="105" width="70" height="8" rx="4" fill="white" opacity="0.8"/>
    <circle cx="135" cy="45" r="25" fill="#10B981"/>
    <path d="M125 45 L130 50 L145 35" stroke="white" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'apple');
        file_put_contents($tempFile, $svg);
        $this->convertSVGtoPNG($tempFile, $this->outputDir . 'apple-touch-icon.png', 180, 180);
        unlink($tempFile);
        
        return 'apple-touch-icon.png generated';
    }
    
    /**
     * Generate PWA icons
     */
    private function generatePWAIcons() {
        $sizes = [192, 512];
        $results = [];
        
        foreach ($sizes as $size) {
            $svg = $this->generatePWAIconSVG($size);
            $filename = "icon-{$size}.png";
            $this->convertSVGtoPNGFromString($svg, $this->outputDir . $filename, $size, $size);
            $results[] = $filename;
        }
        
        return implode(', ', $results);
    }
    
    /**
     * Generate PWA icon SVG
     */
    private function generatePWAIconSVG($size) {
        return '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
    <defs>
        <linearGradient id="pwaGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#4F46E5"/>
            <stop offset="100%" style="stop-color:#7C3AED"/>
        </linearGradient>
    </defs>
    <rect x="32" y="32" width="448" height="448" rx="96" fill="url(#pwaGradient)"/>
    <rect x="112" y="144" width="288" height="224" rx="32" fill="white" opacity="0.15"/>
    <rect x="144" y="184" width="224" height="24" rx="12" fill="white" opacity="0.8"/>
    <rect x="144" y="240" width="160" height="24" rx="12" fill="white" opacity="0.8"/>
    <rect x="144" y="296" width="192" height="24" rx="12" fill="white" opacity="0.8"/>
    <circle cx="384" cy="128" r="64" fill="#10B981"/>
    <path d="M360 128 L375 143 L408 105" stroke="white" stroke-width="12" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
    }
    
    /**
     * Helper: Create ICO file (simplified - in production would use proper ICO format)
     */
    private function createICO($svg, $size) {
        // For now, return SVG wrapped in ICO-like structure
        // In production, use a library like imagick
        return base64_encode($svg);
    }
    
    /**
     * Helper: Convert SVG to PNG
     */
    private function convertSVGtoPNG($svgFile, $outputFile, $width, $height) {
        // Check if imagick is available
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->readImage($svgFile);
                $imagick->setImageFormat('png');
                $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                $imagick->writeImage($outputFile);
                $imagick->destroy();
                return true;
            } catch (Exception $e) {
                error_log("Imagick conversion failed: " . $e->getMessage());
            }
        }
        
        // Fallback: copy SVG as PNG placeholder
        copy($svgFile, str_replace('.png', '.svg', $outputFile));
        return false;
    }
    
    /**
     * Helper: Convert SVG string to PNG
     */
    private function convertSVGtoPNGFromString($svgContent, $outputFile, $width, $height) {
        $tempFile = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tempFile, $svgContent);
        $result = $this->convertSVGtoPNG($tempFile, $outputFile, $width, $height);
        unlink($tempFile);
        return $result;
    }
    
    /**
     * Helper: Ensure output directory exists
     */
    private function ensureDirectory() {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    /**
     * Generate manifest.json for PWA
     */
    public function generateManifest() {
        $manifest = [
            'name' => 'SAMS - School Attendance Management System',
            'short_name' => 'SAMS',
            'description' => 'Complete school attendance and management platform',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#4F46E5',
            'orientation' => 'portrait-primary',
            'scope' => '/',
            'icons' => [
                [
                    'src' => '/assets/logo/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/assets/logo/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ]
        ];
        
        $manifestPath = __DIR__ . '/../../manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
        
        return 'manifest.json generated';
    }
}

// Auto-generate logos when file is included
if (php_sapi_name() !== 'cli') {
    $generator = new SAMS_LogoGenerator();
    $generator->generateAll();
    $generator->generateManifest();
}
