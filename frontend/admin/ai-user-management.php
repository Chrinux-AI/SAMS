<?php
/**
 * AI User Management Dashboard
 * Admin interface for AI-powered user creation from Google Form data
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'AI User Creator';
$page_icon = 'robot';

try {
    $total_users = db()->fetchOne("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
    $pending_users = db()->fetchOne("SELECT COUNT(*) as c FROM users WHERE is_active = 0 AND verification_token LIKE 'CONFIRM:%'")['c'] ?? 0;
    $active_users = db()->fetchOne("SELECT COUNT(*) as c FROM users WHERE is_active = 1")['c'] ?? 0;
    $ai_created = 0;
    if (table_exists('google_form_submissions')) {
        $ai_created = db()->fetchOne("SELECT COUNT(*) as c FROM google_form_submissions WHERE processing_status = 'completed'")['c'] ?? 0;
    }
    $recent_pending = db()->fetchAll("SELECT id, full_name, email, role, assigned_id, created_at FROM users WHERE is_active = 0 AND verification_token LIKE 'CONFIRM:%' ORDER BY created_at DESC LIMIT 20") ?? [];
} catch (Throwable $e) {
    $total_users = $pending_users = $active_users = $ai_created = 0;
    $recent_pending = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="../assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .ai-stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
        .ai-stat { background: var(--card-bg,#fff); border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.08); border-left: 4px solid #4F46E5; }
        .ai-stat .val { font-size: 2rem; font-weight: 700; color: #4F46E5; }
        .ai-stat .lbl { color: #6B7280; font-size: .85rem; margin-top: 4px; }
        .paste-area { width: 100%; min-height: 220px; padding: 14px; border: 2px dashed #D1D5DB; border-radius: 12px; font-family: 'Courier New',monospace; font-size: .9rem; resize: vertical; background: #F9FAFB; transition: border-color .2s; }
        .paste-area:focus { outline: none; border-color: #4F46E5; background: #fff; }
        .format-hint { background: #EEF2FF; border-radius: 8px; padding: 14px; margin-bottom: 16px; font-size: .85rem; color: #4338CA; }
        .format-hint code { background: #C7D2FE; padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .preview-table th { background: #F3F4F6; padding: 10px 14px; text-align: left; font-weight: 600; font-size: .85rem; }
        .preview-table td { padding: 10px 14px; border-bottom: 1px solid #F3F4F6; font-size: .9rem; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: .75rem; font-weight: 600; }
        .badge-student { background: #DBEAFE; color: #1E40AF; }
        .badge-teacher { background: #D1FAE5; color: #065F46; }
        .badge-parent { background: #FEF3C7; color: #92400E; }
        .badge-admin { background: #EDE9FE; color: #5B21B6; }
        .badge-staff { background: #F3F4F6; color: #374151; }
        .result-card { padding: 16px; border-radius: 10px; margin-top: 12px; }
        .result-success { background: #D1FAE5; border-left: 4px solid #10B981; }
        .result-fail { background: #FEE2E2; border-left: 4px solid #EF4444; }
        .result-card strong { display: block; margin-bottom: 4px; }
        .result-card .detail { font-size: .85rem; color: #374151; }
        #previewSection, #resultsSection { display: none; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .pending-table { width: 100%; border-collapse: collapse; }
        .pending-table th { background: #F9FAFB; padding: 10px; text-align: left; font-size: .85rem; color: #374151; border-bottom: 2px solid #E5E7EB; }
        .pending-table td { padding: 10px; font-size: .85rem; border-bottom: 1px solid #F3F4F6; }
    </style>
</head>
<body>

<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="cyber-header">
            <div class="page-title-section">
                <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                <h1 class="page-title"><?php echo $page_title; ?></h1>
            </div>
            <div class="header-actions">
                <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
            </div>
        </header>

        <!-- Stats -->
        <div class="ai-stats">
            <div class="ai-stat"><div class="val"><?php echo $ai_created; ?></div><div class="lbl">AI-Created</div></div>
            <div class="ai-stat"><div class="val"><?php echo $pending_users; ?></div><div class="lbl">Pending OTP</div></div>
            <div class="ai-stat"><div class="val"><?php echo $active_users; ?></div><div class="lbl">Active</div></div>
            <div class="ai-stat"><div class="val"><?php echo $total_users; ?></div><div class="lbl">Total Users</div></div>
        </div>

        <!-- Input Section -->
        <div class="card" style="padding:24px; margin-bottom:24px;">
            <h2 style="margin:0 0 16px;font-size:1.3rem;"><i class="fas fa-robot" style="color:#4F46E5"></i> Paste Google Form Data</h2>

            <div class="format-hint">
                <strong>Accepted formats:</strong><br>
                <code>JSON</code> &mdash; <code>[{"Name":"John Doe","Email":"john@x.com","Role":"student"}]</code><br>
                <code>CSV</code> &mdash; First row = headers, commas between fields<br>
                <code>Key: Value</code> &mdash; One field per line, e.g. <code>Name: John Doe</code>
            </div>

            <textarea id="rawData" class="paste-area" placeholder='Paste JSON, CSV, or "Field: Value" data from your Google Form here...'></textarea>

            <div class="btn-row">
                <button id="btnParse" class="btn btn-primary" onclick="parseData()">
                    <i class="fas fa-search"></i> Parse &amp; Preview
                </button>
                <button class="btn btn-secondary" onclick="clearAll()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>

        <!-- Preview Section -->
        <div id="previewSection" class="card" style="padding:24px; margin-bottom:24px;">
            <h2 style="margin:0 0 12px;font-size:1.2rem;"><i class="fas fa-eye" style="color:#4F46E5"></i> Preview Extracted Users</h2>
            <div id="parseErrors"></div>
            <table class="preview-table">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Class</th><th>Phone</th></tr>
                </thead>
                <tbody id="previewBody"></tbody>
            </table>
            <div class="btn-row">
                <button id="btnCreate" class="btn btn-primary" onclick="createAccounts()">
                    <i class="fas fa-user-plus"></i> Create All Accounts
                </button>
                <button class="btn btn-secondary" onclick="clearAll()">
                    <i class="fas fa-undo"></i> Cancel
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsSection" class="card" style="padding:24px; margin-bottom:24px;">
            <h2 style="margin:0 0 12px;font-size:1.2rem;"><i class="fas fa-check-double" style="color:#10B981"></i> Creation Results</h2>
            <div id="resultsContainer"></div>
        </div>

        <!-- Pending Confirmations -->
        <?php if (!empty($recent_pending)): ?>
        <div class="card" style="padding:24px;">
            <h2 style="margin:0 0 16px;font-size:1.2rem;"><i class="fas fa-clock" style="color:#F59E0B"></i> Pending Confirmations (<?php echo count($recent_pending); ?>)</h2>
            <div style="overflow-x:auto">
                <table class="pending-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>ID</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_pending as $u): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($u['role']); ?>"><?php echo ucfirst(htmlspecialchars($u['role'])); ?></span></td>
                            <td><?php echo htmlspecialchars($u['assigned_id'] ?? '-'); ?></td>
                            <td><?php echo date('M j, g:ia', strtotime($u['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script>
let parsedUsers = [];

async function parseData() {
    const raw = document.getElementById('rawData').value.trim();
    if (!raw) { notify('Please paste some data first', 'error'); return; }

    const btn = document.getElementById('btnParse');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Parsing...';
    btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'parse');
        fd.append('raw_data', raw);

        const resp = await fetch('ai-user-creator.php', { method: 'POST', body: fd });
        const data = await resp.json();

        const errBox = document.getElementById('parseErrors');
        errBox.innerHTML = '';
        if (data.errors && data.errors.length) {
            errBox.innerHTML = '<div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:.85rem;color:#92400E"><strong>Warnings:</strong><br>' + data.errors.map(e => '&bull; ' + escHtml(e)).join('<br>') + '</div>';
        }

        parsedUsers = data.users || [];
        const tbody = document.getElementById('previewBody');
        tbody.innerHTML = '';

        if (parsedUsers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#6B7280;padding:20px">No valid users extracted</td></tr>';
        } else {
            parsedUsers.forEach((u, i) => {
                tbody.innerHTML += `<tr>
                    <td>${i+1}</td>
                    <td>${escHtml(u.full_name || '')}</td>
                    <td>${escHtml(u.email || '')}</td>
                    <td><span class="badge badge-${u.role||'student'}">${ucfirst(u.role||'student')}</span></td>
                    <td>${escHtml(u.class || '-')}</td>
                    <td>${escHtml(u.phone || '-')}</td>
                </tr>`;
            });
        }

        document.getElementById('previewSection').style.display = 'block';
        document.getElementById('resultsSection').style.display = 'none';
        document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth' });
    } catch (e) {
        notify('Network error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-search"></i> Parse & Preview';
        btn.disabled = false;
    }
}

async function createAccounts() {
    if (!parsedUsers.length) { notify('No users to create', 'error'); return; }

    const btn = document.getElementById('btnCreate');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('users_json', JSON.stringify(parsedUsers));

        const resp = await fetch('ai-user-creator.php', { method: 'POST', body: fd });
        const data = await resp.json();

        const container = document.getElementById('resultsContainer');
        container.innerHTML = '';

        let html = `<div style="display:flex;gap:16px;margin-bottom:16px">
            <div style="flex:1;background:#D1FAE5;padding:16px;border-radius:10px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:#065F46">${(data.created||[]).length}</div>
                <div style="font-size:.85rem;color:#065F46">Created</div>
            </div>
            <div style="flex:1;background:#FEE2E2;padding:16px;border-radius:10px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:#991B1B">${(data.failed||[]).length}</div>
                <div style="font-size:.85rem;color:#991B1B">Failed</div>
            </div>
        </div>`;

        (data.created || []).forEach(r => {
            html += `<div class="result-card result-success">
                <strong>${escHtml(r.name)} (${escHtml(r.role)})</strong>
                <div class="detail">${escHtml(r.email)} &bull; ID: ${escHtml(r.assigned_id)} &bull; Email ${r.email_sent ? 'sent' : 'FAILED'}</div>
            </div>`;
        });
        (data.failed || []).forEach(r => {
            html += `<div class="result-card result-fail">
                <strong>${escHtml(r.name || r.email)}</strong>
                <div class="detail">Error: ${escHtml(r.error)}</div>
            </div>`;
        });

        container.innerHTML = html;
        document.getElementById('resultsSection').style.display = 'block';
        document.getElementById('previewSection').style.display = 'none';
        document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });

        if ((data.created||[]).length > 0) {
            notify(`${data.created.length} account(s) created! OTP emails sent.`, 'success');
        }
    } catch (e) {
        notify('Network error: ' + e.message, 'error');
    } finally {
        btn.innerHTML = '<i class="fas fa-user-plus"></i> Create All Accounts';
        btn.disabled = false;
    }
}

function clearAll() {
    document.getElementById('rawData').value = '';
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('resultsSection').style.display = 'none';
    parsedUsers = [];
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function ucfirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function notify(msg, type) {
    const el = document.createElement('div');
    el.style.cssText = `position:fixed;top:20px;right:20px;z-index:9999;padding:14px 20px;border-radius:10px;color:#fff;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.15);max-width:400px;font-size:.9rem;${type==='success'?'background:#10B981':'background:#EF4444'}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 5000);
}
</script>
</body>
</html>
