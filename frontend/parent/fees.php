<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent('../login.php');

// Get parent's linked children
$children = db()->fetchAll("
    SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as child_name
    FROM guardians g
    LEFT JOIN students s ON g.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE g.parent_id = (SELECT id FROM users WHERE id = ?)
", [$_SESSION['user_id']]);

// Get selected child
$selected_child_id = isset($_GET['child']) ? (int)$_GET['child'] : (count($children) > 0 ? $children[0]['id'] : null);
$selected_child = null;

if ($selected_child_id) {
    foreach ($children as $child) {
        if ($child['id'] == $selected_child_id) {
            $selected_child = $child;
            break;
        }
    }
}

// Get fees for selected child
$fees = [];
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;

if ($selected_child) {
    $fees = db()->fetchAll("
        SELECT * FROM fees
        WHERE student_id = ?
        ORDER BY due_date DESC
    ", [$selected_child['id']]);

    foreach ($fees as $fee) {
        $total_amount += $fee['amount'];
        if ($fee['status'] === 'paid') {
            $paid_amount += $fee['paid_amount'];
        } else {
            $pending_amount += $fee['amount'];
        }
    }
}

$page_title = "Children's Fees";
$page_icon = 'wallet';
$full_name = $_SESSION['full_name'];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/sams-core.css" rel="stylesheet">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    
</head>
<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Parent</div>
                        </div>
                    </div>
                </div>
            </header>
            <div style="display:grid;gap:24px;">
                <?php if (empty($children)): ?>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:40px;">
                            <i class="fas fa-user-friends" style="font-size:3rem;color:#cbd5e1;margin-bottom:15px;"></i>
                            <div style="color:#0f172a;font-weight:600;">No children linked to your account</div>
                            <div style="color:#64748b;font-size:0.9rem;margin-top:10px;">Please contact the administrator</div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Child Selection -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-child"></i> <span>Select Child</span></div>
                        </div>
                        <div class="card-body">
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;">
                                <?php foreach ($children as $child): ?>
                                    <a href="?child=<?php echo $child['id']; ?>" class="cyber-btn <?php echo $selected_child_id === $child['id'] ? 'primary' : ''; ?>" style="display:block;padding:15px;text-align:center;">
                                        <div style="font-weight:700;margin-bottom:5px;"><?php echo htmlspecialchars($child['child_name']); ?></div>
                                        <div style="font-size:0.85rem;opacity:0.8;">Grade <?php echo $child['grade']; ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($selected_child): ?>
                        <!-- Fee Statistics -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:25px;">
                            <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));">
                                <div class="card-body" style="text-align:center;padding:25px;">
                                    <i class="fas fa-coins" style="font-size:2.5rem;color:var(--cyber-cyan);margin-bottom:15px;"></i>
                                    <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;">$<?php echo number_format($total_amount, 2); ?></div>
                                    <div style="color:rgba(255,255,255,0.6);">Total Fees</div>
                                </div>
                            </div>
                            <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,255,127,0.1),rgba(0,191,255,0.1));">
                                <div class="card-body" style="text-align:center;padding:25px;">
                                    <i class="fas fa-check-circle" style="font-size:2.5rem;color:#00ff7f;margin-bottom:15px;"></i>
                                    <div style="font-size:2rem;font-weight:700;color:#00ff7f;margin-bottom:5px;">$<?php echo number_format($paid_amount, 2); ?></div>
                                    <div style="color:rgba(255,255,255,0.6);">Paid</div>
                                </div>
                            </div>
                            <div class="holo-card" style="background:linear-gradient(135deg,rgba(255,20,147,0.1),rgba(138,43,226,0.1));">
                                <div class="card-body" style="text-align:center;padding:25px;">
                                    <i class="fas fa-exclamation-circle" style="font-size:2.5rem;color:var(--cyber-pink);margin-bottom:15px;"></i>
                                    <div style="font-size:2rem;font-weight:700;color:var(--cyber-pink);margin-bottom:5px;">$<?php echo number_format($pending_amount, 2); ?></div>
                                    <div style="color:rgba(255,255,255,0.6);">Pending</div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee List -->
                        <div class="holo-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span><?php echo htmlspecialchars($selected_child['child_name']); ?>'s Fees</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($fees)): ?>
                                    <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                        <i class="fas fa-money-bill-wave" style="font-size:3rem;margin-bottom:15px;"></i>
                                        <div>No fees recorded</div>
                                    </div>
                                <?php else: ?>
                                    <div style="display:grid;gap:15px;">
                                        <?php foreach ($fees as $fee): ?>
                                            <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid <?php echo $fee['status'] === 'paid' ? '#00ff7f' : ($fee['status'] === 'overdue' ? 'var(--cyber-pink)' : 'rgba(0,191,255,0.2)'); ?>;border-radius:12px;padding:20px;">
                                                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;">
                                                    <div style="flex:1;">
                                                        <h3 style="color:var(--cyber-cyan);font-size:1.3rem;margin-bottom:8px;"><?php echo ucfirst($fee['fee_type']); ?> Fee</h3>
                                                        <?php if (!empty($fee['description'])): ?>
                                                            <p style="color:rgba(255,255,255,0.7);margin-bottom:10px;"><?php echo htmlspecialchars($fee['description']); ?></p>
                                                        <?php endif; ?>
                                                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                            <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                                <i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($fee['due_date'])); ?>
                                                            </span>
                                                            <?php if ($fee['status'] === 'paid'): ?>
                                                                <span style="padding:5px 12px;background:rgba(0,255,127,0.2);border:1px solid #00ff7f;border-radius:15px;font-size:0.85rem;color:#00ff7f;font-weight:700;">
                                                                    <i class="fas fa-check"></i> Paid on <?php echo date('M d, Y', strtotime($fee['payment_date'])); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($fee['payment_method'] && $fee['status'] === 'paid'): ?>
                                                                <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                                    <i class="fas fa-credit-card"></i> <?php echo ucfirst($fee['payment_method']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div style="text-align:center;padding:20px;background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));border:1px solid var(--cyber-cyan);border-radius:12px;min-width:150px;">
                                                        <div style="font-size:2rem;font-weight:700;color:var(--cyber-pink);">$<?php echo number_format($fee['amount'], 2); ?></div>
                                                        <?php if ($fee['status'] === 'paid'): ?>
                                                            <div style="margin-top:8px;">
                                                                <span style="padding:5px 12px;background:rgba(0,255,127,0.2);border:1px solid #00ff7f;border-radius:15px;font-weight:700;color:#00ff7f;font-size:0.85rem;">
                                                                    <i class="fas fa-check-circle"></i> PAID
                                                                </span>
                                                            </div>
                                                        <?php elseif ($fee['status'] === 'overdue'): ?>
                                                            <div style="margin-top:8px;">
                                                                <span style="padding:5px 12px;background:rgba(255,20,147,0.2);border:1px solid var(--cyber-pink);border-radius:15px;font-weight:700;color:var(--cyber-pink);font-size:0.85rem;">
                                                                    <i class="fas fa-exclamation-triangle"></i> OVERDUE
                                                                </span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div style="margin-top:8px;">
                                                                <span style="padding:5px 12px;background:rgba(0,191,255,0.2);border:1px solid var(--cyber-cyan);border-radius:15px;font-weight:700;font-size:0.85rem;">
                                                                    PENDING
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($fee['status'] !== 'paid'): ?>
                                                    <div style="padding:15px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;">
                                                        <div style="color:rgba(255,255,255,0.7);margin-bottom:10px;">
                                                            <i class="fas fa-info-circle"></i> Payment Instructions:
                                                        </div>
                                                        <div style="color:rgba(255,255,255,0.6);font-size:0.9rem;">
                                                            Please visit the school office or contact the administration for payment options.
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($fee['transaction_id']): ?>
                                                        <div style="padding:10px;background:rgba(0,255,127,0.05);border:1px solid #00ff7f;border-radius:8px;font-size:0.85rem;">
                                                            <strong>Transaction ID:</strong> <?php echo htmlspecialchars($fee['transaction_id']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
