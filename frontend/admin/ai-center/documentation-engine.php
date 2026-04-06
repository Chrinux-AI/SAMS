<?php

/**
 * SAMS AI Documentation Engine
 * Generate reports and documentation from system data
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_login('../../login.php');
if (!has_role('admin')) {
  redirect('../../login.php', 'Admin access required.', 'error');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$message = '';

// Handle POST document generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'generate') {
    $docType = $_POST['doc_type'] ?? 'custom';
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $docGen = new SAMS_DocumentGenerator();
      $result = $docGen->generate($docType, $_POST['params'] ?? [], $tenantId);
      $message = ($result['success'] ?? false) ? "Document generated: " . htmlspecialchars($result['filename'] ?? '') : "Error: " . htmlspecialchars($result['error'] ?? 'Unknown');
    } catch (Throwable $e) {
      $message = "Document generation service unavailable.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_doc_generate', 'document', null, "Generated $docType document");
    } catch (Throwable $e) {
    }
  } elseif ($action === 'delete' && !empty($_POST['doc_id'])) {
    try {
      log_activity($_SESSION['user_id'], 'ai_doc_delete', 'document', (int)$_POST['doc_id'], 'Deleted document');
    } catch (Throwable $e) {
    }
    $message = "Document deleted.";
  }
}

// Safe defaults
$documents = [];
$templates = [];

// Try AI service
try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $docGen = new SAMS_DocumentGenerator();
    $documents = $docGen->getDocuments($tenantId, 20);
    $templates = $docGen->getTemplates();
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Documentation Engine - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .docs-header {
      background: linear-gradient(135deg, #0369A1, #0EA5E9);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem
    }

    .template-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .template-card {
      background: var(--color-surface, #fff);
      border: 2px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      cursor: pointer;
      transition: all .2s
    }

    .template-card:hover {
      border-color: #4F46E5;
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, .1)
    }

    .template-icon {
      width: 48px;
      height: 48px;
      border-radius: var(--radius-lg, 12px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem
    }

    .template-icon.pdf {
      background: #FEE2E2;
      color: #DC2626
    }

    .template-icon.excel {
      background: #D1FAE5;
      color: #059669
    }

    .template-icon.word {
      background: #DBEAFE;
      color: #2563EB
    }

    .template-icon.csv {
      background: #FEF3C7;
      color: #D97706
    }

    .template-card h3 {
      font-weight: 600;
      margin-bottom: .5rem
    }

    .template-card p {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-bottom: 1rem
    }

    .template-meta {
      display: flex;
      gap: 1rem;
      font-size: .75rem;
      color: var(--color-text-muted, #9ca3af)
    }

    .docs-list {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      overflow: hidden
    }

    .docs-list-header {
      padding: 1rem 1.5rem;
      background: var(--color-background-secondary, #f9fafb);
      border-bottom: 1px solid var(--color-border, #e5e7eb);
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .docs-list table {
      width: 100%;
      border-collapse: collapse
    }

    .docs-list th {
      padding: .875rem 1.5rem;
      text-align: left;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--color-text-secondary, #6b7280);
      font-weight: 600;
      background: var(--color-background-secondary, #f9fafb)
    }

    .docs-list td {
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--color-border, #e5e7eb)
    }

    .doc-type-badge {
      display: inline-flex;
      align-items: center;
      gap: .375rem;
      padding: .25rem .75rem;
      border-radius: var(--radius-md, 8px);
      font-size: .75rem;
      font-weight: 600
    }

    .doc-type-badge.pdf {
      background: #FEE2E2;
      color: #DC2626
    }

    .doc-type-badge.excel {
      background: #D1FAE5;
      color: #059669
    }

    .doc-type-badge.word {
      background: #DBEAFE;
      color: #2563EB
    }

    .doc-type-badge.csv {
      background: #FEF3C7;
      color: #D97706
    }

    .btn-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-md, 8px);
      border: none;
      background: var(--color-background-secondary, #f3f4f6);
      color: var(--color-text-secondary, #6b7280);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all .2s
    }

    .btn-icon:hover {
      background: #4F46E5;
      color: #fff
    }

    .ai-summary-box {
      background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
      border: 1px solid #93C5FD;
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      margin-bottom: 2rem
    }

    .ai-summary-box h3 {
      display: flex;
      align-items: center;
      gap: .75rem;
      color: #1E40AF;
      margin-bottom: .75rem
    }

    .ai-summary-box p {
      color: #1E40AF;
      line-height: 1.6
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-file-alt"></i></div>
        <div>
          <h1>AI Documentation Engine</h1>
          <p>Intelligent report and document generation</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:<?php echo strpos($message, 'Error') !== false ? '#FEE2E2' : '#D1FAE5'; ?>;border:1px solid <?php echo strpos($message, 'Error') !== false ? '#EF4444' : '#22C55E'; ?>;border-radius:8px;color:<?php echo strpos($message, 'Error') !== false ? '#991B1B' : '#065F46'; ?>;">
            <i class="fas fa-<?php echo strpos($message, 'Error') !== false ? 'exclamation-circle' : 'check-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="docs-header">
          <h1><i class="fas fa-file-alt"></i> AI Documentation Engine</h1>
          <p>Generate intelligent reports, PDFs, and documentation from your school data</p>
        </div>

        <div class="ai-summary-box">
          <h3><i class="fas fa-brain"></i> AI Document Assistant</h3>
          <p>Select a template below to generate reports from attendance trends, grade distributions, financial summaries, and more.</p>
        </div>

        <!-- Templates (each triggers a POST form) -->
        <h2 style="margin-bottom:1rem;">Document Templates</h2>
        <div class="template-grid">
          <?php
          $docTemplates = [
            ['attendance-report', 'pdf', 'fa-file-pdf', 'Attendance Report', 'Monthly attendance summary with AI-detected anomalies and trends', 'PDF', '2 min'],
            ['grade-analysis', 'excel', 'fa-file-excel', 'Grade Analysis', 'Grade distribution with AI predictions for at-risk students', 'Excel', '3 min'],
            ['financial-summary', 'word', 'fa-file-word', 'Financial Summary', 'Fee collection report with outstanding balances', 'Word', '2 min'],
            ['teacher-workload', 'csv', 'fa-file-csv', 'Teacher Workload', 'Workload distribution analysis with AI recommendations', 'CSV', '1 min'],
            ['security-audit', 'pdf', 'fa-shield-alt', 'Security Audit', 'AI-generated security report with threat analysis', 'PDF', '1 min'],
          ];
          foreach ($docTemplates as $tpl): ?>
            <form method="POST" class="template-card-form">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="generate">
              <input type="hidden" name="doc_type" value="<?php echo $tpl[0]; ?>">
              <div class="template-card" onclick="this.closest('form').submit();">
                <div class="template-icon <?php echo $tpl[1]; ?>"><i class="fas <?php echo $tpl[2]; ?>"></i></div>
                <h3><?php echo htmlspecialchars($tpl[3]); ?></h3>
                <p><?php echo htmlspecialchars($tpl[4]); ?></p>
                <div class="template-meta">
                  <span><i class="fas fa-file"></i> <?php echo $tpl[5]; ?></span>
                  <span><i class="fas fa-clock"></i> <?php echo $tpl[6]; ?></span>
                </div>
              </div>
            </form>
          <?php endforeach; ?>
        </div>

        <!-- Recent Documents -->
        <div class="docs-list">
          <div class="docs-list-header"><span>Recently Generated Documents</span></div>
          <table>
            <thead>
              <tr>
                <th>Document</th>
                <th>Type</th>
                <th>Generated</th>
                <th>Size</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($documents)): ?>
                <tr>
                  <td colspan="5" style="text-align:center;padding:3rem;">
                    <p style="color:var(--color-text-secondary,#6b7280);">No documents generated yet</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                  <tr>
                    <td>
                      <strong><?php echo htmlspecialchars($doc['name'] ?? ''); ?></strong>
                      <br><small style="color:var(--color-text-muted,#9ca3af);"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></small>
                    </td>
                    <td><span class="doc-type-badge <?php echo htmlspecialchars($doc['type'] ?? 'pdf'); ?>"><?php echo htmlspecialchars(strtoupper($doc['type'] ?? 'PDF')); ?></span></td>
                    <td><?php echo htmlspecialchars($doc['created_at'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($doc['size_formatted'] ?? ''); ?></td>
                    <td>
                      <a href="../../docs/<?php echo htmlspecialchars($doc['filename'] ?? ''); ?>" class="btn-icon" title="Download"><i class="fas fa-download"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
