<?php

/**
 * Create New Tenant - School Registration Form
 * Multi-tenant platform school registration
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/sams-multi-tenant.php';

// Only super admins can create tenants
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['super_admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$success_message = '';
$error_message = '';
$created_tenant = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request token. Please refresh and try again.';
    }

    if ($error_message === '') {
        $tenant_manager = new SAMS_MultiTenant();

        $tenant_data = [
            'institution_name' => $_POST['institution_name'],
            'admin_email' => $_POST['admin_email'],
            'custom_domain' => $_POST['custom_domain'] ?: null,
            'plan_type' => $_POST['plan_type'],
            'separate_database' => isset($_POST['separate_database']),
            'admin_name' => $_POST['admin_name'],
            'admin_phone' => $_POST['admin_phone'],
            'institution_type' => $_POST['institution_type'],
            'student_capacity' => $_POST['student_capacity'],
            'address' => $_POST['address'],
            'city' => $_POST['city'],
            'country' => $_POST['country']
        ];

        $result = $tenant_manager->createTenant($tenant_data);

        if ($result['success']) {
            $success_message = "School created successfully! Subdomain: {$result['subdomain']}";
            $created_tenant = [
                'subdomain' => $result['subdomain'] ?? '',
                'tenant_id' => $result['tenant_id'] ?? null,
                'setup_url' => $result['setup_url'] ?? '',
                'admin_email' => $tenant_data['admin_email'] ?? '',
                'admin_name' => $tenant_data['admin_name'] ?? '',
                'institution_name' => $tenant_data['institution_name'] ?? ''
            ];
            // Clear form
            $_POST = [];
        } else {
            $error_message = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New School - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .form-body {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section h3 {
            color: #374151;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .plan-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .plan-option {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .plan-option:hover {
            border-color: #4F46E5;
        }

        .plan-option.selected {
            border-color: #4F46E5;
            background: #f0f4ff;
        }

        .plan-option h4 {
            margin-bottom: 8px;
            color: #374151;
        }

        .plan-option .price {
            font-size: 24px;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 8px;
        }

        .plan-option .features {
            font-size: 14px;
            color: #6b7280;
        }

        .submit-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-submit {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title-area">
                    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active'); document.querySelector('.sidebar-overlay').classList.toggle('active');">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-icon"><i class="fas fa-school"></i></div>
                    <div>
                        <h1>Create New School</h1>
                        <p class="page-subtitle">Register a new institution on the platform</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="super-admin-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </header>

            <div class="content-area">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                    <?php if ($created_tenant): ?>
                        <div class="alert alert-success" style="background:#eef8ff;border-color:#bfdbfe;color:#1e3a8a;">
                            <h3 style="margin:0 0 10px 0;"><i class="fas fa-key"></i> Tenant Login & Setup Details</h3>
                            <p style="margin:6px 0;"><strong>Institution:</strong> <?php echo htmlspecialchars($created_tenant['institution_name']); ?></p>
                            <p style="margin:6px 0;"><strong>Subdomain:</strong> <?php echo htmlspecialchars($created_tenant['subdomain']); ?></p>
                            <p style="margin:6px 0;"><strong>Admin Name:</strong> <?php echo htmlspecialchars($created_tenant['admin_name']); ?></p>
                            <p style="margin:6px 0;"><strong>Admin Email (login):</strong> <?php echo htmlspecialchars($created_tenant['admin_email']); ?></p>
                            <?php if (!empty($created_tenant['setup_url'])): ?>
                                <p style="margin:6px 0;"><strong>Setup URL:</strong> <a href="<?php echo htmlspecialchars($created_tenant['setup_url']); ?>"><?php echo htmlspecialchars($created_tenant['setup_url']); ?></a></p>
                            <?php endif; ?>
                            <p style="margin:10px 0 0 0;">Redirecting to dashboard in <span id="redirectCountdown">8</span>s…</p>
                        </div>
                        <script>
                            (function() {
                                let remaining = 8;
                                const el = document.getElementById('redirectCountdown');
                                const timer = setInterval(function() {
                                    remaining -= 1;
                                    if (el) el.textContent = String(remaining);
                                    if (remaining <= 0) {
                                        clearInterval(timer);
                                        window.location.href = 'super-admin-dashboard.php?tenant_created=1';
                                    }
                                }, 1000);
                            })();
                        </script>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="form-header">
                        <h2><i class="fas fa-graduation-cap"></i> Register New School</h2>
                        <p>Add a new educational institution to the SAMS platform</p>
                    </div>

                    <form method="POST" class="form-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="institution_name">Institution Name *</label>
                                    <input type="text" id="institution_name" name="institution_name" required
                                        value="<?php echo htmlspecialchars($_POST['institution_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="institution_type">Institution Type *</label>
                                    <select id="institution_type" name="institution_type" required>
                                        <option value="">Select Type</option>
                                        <option value="primary" <?php echo ($_POST['institution_type'] ?? '') === 'primary' ? 'selected' : ''; ?>>Primary School</option>
                                        <option value="secondary" <?php echo ($_POST['institution_type'] ?? '') === 'secondary' ? 'selected' : ''; ?>>Secondary School</option>
                                        <option value="university" <?php echo ($_POST['institution_type'] ?? '') === 'university' ? 'selected' : ''; ?>>University</option>
                                        <option value="college" <?php echo ($_POST['institution_type'] ?? '') === 'college' ? 'selected' : ''; ?>>College</option>
                                        <option value="vocational" <?php echo ($_POST['institution_type'] ?? '') === 'vocational' ? 'selected' : ''; ?>>Vocational School</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="student_capacity">Expected Student Capacity</label>
                                    <input type="number" id="student_capacity" name="student_capacity"
                                        value="<?php echo htmlspecialchars($_POST['student_capacity'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="custom_domain">Custom Domain (Optional)</label>
                                    <input type="text" id="custom_domain" name="custom_domain"
                                        placeholder="www.schoolname.edu"
                                        value="<?php echo htmlspecialchars($_POST['custom_domain'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-tie"></i> Administrator Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="admin_name">Admin Name *</label>
                                    <input type="text" id="admin_name" name="admin_name" required
                                        value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="admin_email">Admin Email *</label>
                                    <input type="email" id="admin_email" name="admin_email" required
                                        value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="admin_phone">Phone Number</label>
                                    <input type="tel" id="admin_phone" name="admin_phone"
                                        value="<?php echo htmlspecialchars($_POST['admin_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address"
                                        value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city"
                                        value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country"
                                        value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Plan Selection -->
                        <div class="form-section">
                            <h3><i class="fas fa-credit-card"></i> Subscription Plan</h3>
                            <div class="plan-options">
                                <div class="plan-option" onclick="selectPlan(this, 'basic')">
                                    <h4>Basic</h4>
                                    <div class="price">$99/mo</div>
                                    <div class="features">
                                        • Up to 100 users<br>
                                        • Core features<br>
                                        • Basic AI<br>
                                        • Email support
                                    </div>
                                    <input type="radio" name="plan_type" value="basic" style="display: none;"
                                        <?php echo ($_POST['plan_type'] ?? '') === 'basic' ? 'checked' : ''; ?>>
                                </div>
                                <div class="plan-option" onclick="selectPlan(this, 'professional')">
                                    <h4>Professional</h4>
                                    <div class="price">$299/mo</div>
                                    <div class="features">
                                        • Up to 500 users<br>
                                        • Advanced features<br>
                                        • Full AI suite<br>
                                        • Priority support
                                    </div>
                                    <input type="radio" name="plan_type" value="professional" style="display: none;"
                                        <?php echo ($_POST['plan_type'] ?? '') === 'professional' ? 'checked' : ''; ?>>
                                </div>
                                <div class="plan-option" onclick="selectPlan(this, 'enterprise')">
                                    <h4>Enterprise</h4>
                                    <div class="price">$799/mo</div>
                                    <div class="features">
                                        • Unlimited users<br>
                                        • All features<br>
                                        • Custom AI<br>
                                        • Dedicated support
                                    </div>
                                    <input type="radio" name="plan_type" value="enterprise" style="display: none;"
                                        <?php echo ($_POST['plan_type'] ?? '') === 'enterprise' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Options -->
                        <div class="form-section">
                            <h3><i class="fas fa-cog"></i> Technical Configuration</h3>
                            <div class="checkbox-group">
                                <input type="checkbox" id="separate_database" name="separate_database"
                                    <?php echo isset($_POST['separate_database']) ? 'checked' : ''; ?>>
                                <label for="separate_database">Use separate database (recommended for large schools)</label>
                            </div>
                        </div>

                        <div class="submit-section">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-plus-circle"></i>
                                Create School
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Plan selection
        function selectPlan(element, planType) {
            document.querySelectorAll('.plan-option').forEach(option => {
                option.classList.remove('selected');
            });
            element.classList.add('selected');
            const input = element.querySelector('input[type="radio"]');
            if (input) input.checked = true;
        }

        // Initialize selected plan
        document.addEventListener('DOMContentLoaded', function() {
            const selectedPlan = document.querySelector('input[name="plan_type"]:checked');
            if (selectedPlan) {
                selectedPlan.closest('.plan-option').classList.add('selected');
            }
        });
    </script>
</body>

</html>
