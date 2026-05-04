<?php

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once PROJECT_ROOT . '/backend/modules/admin/AdminUserManager.php';

require_admin('../login.php');

$userId = (int)($_GET['id'] ?? 0);
$adminUserManager = new AdminUserManager(current_tenant_id(), (int)($_SESSION['user_id'] ?? 0));
$user = $adminUserManager->getUserDetails($userId);

if (!$user) {
    redirect('users.php', 'User record was not found in the active tenant.', 'error');
}

$displayName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
$page_title = $displayName !== '' ? $displayName : 'User Details';
$page_icon = 'visibility';

ob_start();
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Account Overview</p>
            <h1 class="text-3xl font-headline font-bold text-slate-900"><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="users.php" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors">Back to Users</a>
            <?php if (!empty($user['student_profile']['id'])): ?>
                <a href="student-view.php?id=<?php echo (int)$user['student_profile']['id']; ?>" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover transition-colors">Open Student Record</a>
            <?php elseif (!empty($user['teacher_profile']['id'])): ?>
                <a href="teachers.php" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover transition-colors">Open Teacher Management</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Role</p>
            <p class="mt-2 text-lg font-bold text-slate-800"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($user['role'] ?? 'unknown')))); ?></p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</p>
            <p class="mt-2 text-lg font-bold text-slate-800"><?php echo htmlspecialchars(ucfirst((string)($user['status'] ?? 'unknown'))); ?></p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned ID</p>
            <p class="mt-2 text-lg font-bold text-slate-800"><?php echo htmlspecialchars((string)($user['assigned_id'] ?? 'Not assigned')); ?></p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Joined</p>
            <p class="mt-2 text-lg font-bold text-slate-800"><?php echo htmlspecialchars(format_datetime((string)($user['created_at'] ?? ''))); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-2 bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-headline font-bold text-slate-900 mb-5">Profile Details</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">First Name</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['first_name'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Last Name</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['last_name'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Email</dt>
                    <dd class="mt-1 font-semibold text-slate-800 break-all"><?php echo htmlspecialchars((string)($user['email'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Phone</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['phone'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Approved</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo !empty($user['approved']) ? 'Yes' : 'No'; ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Email Verified</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo !empty($user['email_verified']) ? 'Yes' : 'No'; ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Approved At</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars(format_datetime((string)($user['approved_at'] ?? ''))); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Last Updated</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars(format_datetime((string)($user['updated_at'] ?? ''))); ?></dd>
                </div>
            </dl>
        </section>

        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-headline font-bold text-slate-900 mb-5">Tenant Membership</h2>
            <?php if (!empty($user['tenant_memberships'])): ?>
                <div class="space-y-3">
                    <?php foreach ($user['tenant_memberships'] as $membership): ?>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tenant #<?php echo (int)$membership['tenant_id']; ?></p>
                            <p class="mt-1 text-sm font-semibold text-slate-800"><?php echo !empty($membership['role_override']) ? htmlspecialchars((string)$membership['role_override']) : 'No override'; ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?php echo !empty($membership['is_active']) ? 'Active membership' : 'Inactive membership'; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">No explicit tenant memberships recorded for this user.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if (!empty($user['student_profile'])): ?>
        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-headline font-bold text-slate-900 mb-5">Student Profile</h2>
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Admission Number</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['student_profile']['admission_number'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned Student ID</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['student_profile']['assigned_student_id'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Class</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['student_profile']['class_name'] ?? '-')); ?></dd>
                </div>
            </dl>
        </section>
    <?php endif; ?>

    <?php if (!empty($user['teacher_profile'])): ?>
        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-headline font-bold text-slate-900 mb-5">Teacher Profile</h2>
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Employee ID</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['teacher_profile']['employee_id'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Qualification</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['teacher_profile']['qualification'] ?? '-')); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Specialization</dt>
                    <dd class="mt-1 font-semibold text-slate-800"><?php echo htmlspecialchars((string)($user['teacher_profile']['specialization'] ?? '-')); ?></dd>
                </div>
            </dl>
        </section>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
