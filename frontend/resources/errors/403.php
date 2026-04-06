<?php

/**
 * 403 Forbidden Page — Theme-aware.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied</title>
  <script>
    (function() {
      var t = localStorage.getItem('sams_theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <style>
    :root {
      --primary: #4f46e5;
      --bg: #f8fafc;
      --card: #ffffff;
      --text: #1e293b;
      --muted: #64748b;
      --border: #e2e8f0;
    }

    :root[data-theme="dark"],
    :root[data-theme="midnight"] {
      --bg: #0f1117;
      --card: #1a1d27;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --border: #2d3140;
    }

    :root[data-theme^="cyberpunk"] {
      --bg: #050510;
      --card: #0d0d1a;
      --text: #e0e0ff;
      --muted: #6666aa;
      --border: #1a1a3a;
      --primary: #00f0ff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    .error-container {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 48px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0, 0, 0, .08);
    }

    .error-icon {
      font-size: 3.5rem;
      margin-bottom: 16px;
    }

    h1 {
      font-size: 1.5rem;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .subtitle {
      color: var(--muted);
      line-height: 1.7;
      margin-bottom: 28px;
    }

    .btn {
      display: inline-block;
      padding: 12px 28px;
      background: var(--primary);
      color: #fff;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      transition: opacity .2s;
    }

    .btn:hover {
      opacity: .85;
    }

    .error-code {
      display: inline-block;
      background: rgba(239, 68, 68, .1);
      color: #ef4444;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .8rem;
      font-weight: 600;
      margin-bottom: 16px;
    }
  </style>
</head>

<body>
  <div class="error-container">
    <div class="error-icon">🔒</div>
    <span class="error-code">Error 403</span>
    <h1>Access Denied</h1>
    <p class="subtitle">You don't have permission to access this page. If you believe this is an error, contact your administrator.</p>
    <a href="/attendance/" class="btn">Return to Home</a>
  </div>
</body>

</html>
