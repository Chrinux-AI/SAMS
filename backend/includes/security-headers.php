<?php

/**
 * SAMS Security Headers
 * Sets security headers on every response.
 * Include early in config.php or bootstrap.
 */

function apply_security_headers(): void
{
  if (headers_sent()) {
    return;
  }

  // Prevent clickjacking
  header('X-Frame-Options: SAMEORIGIN');

  // XSS protection (legacy browsers)
  header('X-XSS-Protection: 1; mode=block');

  // Prevent MIME-type sniffing
  header('X-Content-Type-Options: nosniff');

  // Referrer policy — send origin only for cross-origin
  header('Referrer-Policy: strict-origin-when-cross-origin');

  // Permissions policy — restrict sensitive APIs
  header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

  // Content Security Policy — allow self, inline styles/scripts (legacy PHP app), CDN resources
  $csp = "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com; "
    . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
    . "img-src 'self' data: blob: https://lh3.googleusercontent.com; "
    . "connect-src 'self' https://cdn.tailwindcss.com; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self';";
  header("Content-Security-Policy: {$csp}");

  // Cache prevention for authenticated content
  if (isset($_SESSION['user_id'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
  }
}
