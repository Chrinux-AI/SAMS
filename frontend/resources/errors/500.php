<?php

/**
 * 500 Error Page — Theme-aware, admin-debug-capable.
 *
 * Variables available:
 *   $error_message (string)
 *   $error_file    (string)
 *   $error_line    (int)
 *   $error_trace   (string)
 *   $show_debug    (bool) — true only for admins
 */
$error_message = $error_message ?? 'An unexpected error occurred.';
$error_file    = $error_file ?? '';
$error_line    = $error_line ?? 0;
$error_trace   = $error_trace ?? '';
$show_debug    = $show_debug ?? false;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Something Went Wrong</title>
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
      --code-bg: #f1f5f9;
    }

    :root[data-theme="dark"],
    :root[data-theme="midnight"] {
      --bg: #0f1117;
      --card: #1a1d27;
      --text: #f1f5f9;
      --muted: #94a3b8;
      --border: #2d3140;
      --code-bg: #1e2230;
    }

    :root[data-theme^="cyberpunk"] {
      --bg: #050510;
      --card: #0d0d1a;
      --text: #e0e0ff;
      --muted: #6666aa;
      --border: #1a1a3a;
      --code-bg: #0a0a18;
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
      max-width: 560px;
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

    .debug-panel {
      text-align: left;
      margin-top: 24px;
      border-top: 1px solid var(--border);
      padding-top: 20px;
    }

    .debug-panel summary {
      cursor: pointer;
      font-weight: 700;
      font-size: .9rem;
      color: var(--primary);
    }

    .debug-panel pre {
      background: var(--code-bg);
      color: var(--muted);
      padding: 14px;
      border-radius: 10px;
      overflow-x: auto;
      font-size: .78rem;
      line-height: 1.6;
      margin-top: 10px;
      white-space: pre-wrap;
      word-break: break-word;
    }

    .error-code {
      display: inline-block;
      background: var(--code-bg);
      color: var(--muted);
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
    <div class="error-icon">⚠️</div>
    <span class="error-code">Error 500</span>
    <h1>Something Went Wrong</h1>
    <p class="subtitle">We encountered an unexpected error while processing your request. The issue has been logged and our team will look into it.</p>
    <a href="/attendance/" class="btn">Return to Home</a>

    <?php if ($show_debug && $error_message): ?>
      <div class="debug-panel">
        <details>
          <summary>Admin Debug Information</summary>
          <pre><strong>Message:</strong> <?php echo htmlspecialchars($error_message); ?>

<strong>Location:</strong> <?php echo htmlspecialchars($error_file); ?>:<?php echo $error_line; ?>

<strong>Stack Trace:</strong>
<?php echo htmlspecialchars($error_trace); ?></pre>
        </details>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>
