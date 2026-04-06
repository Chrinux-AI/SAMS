<?php

/**
 * Enhanced Users Management Page
 * Complete CRUD operations with filtering and bulk actions
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Get filter parameters
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$tenant_filter_sql = '';
$tenant_filter_params = [];

if (table_exists('tenant_users')) {
    $tenant_filter_sql = 'id IN (SELECT user_id FROM tenant_users WHERE tenant_id = :tenant_id AND is_active = 1)';
    $tenant_filter_params['tenant_id'] = current_tenant_id();
    $where_conditions[] = $tenant_filter_sql;
}

if ($filter_role !== 'all') {
    $where_conditions[] = 'role = :role';
    $params['role'] = $filter_role;
}

if ($filter_status !== 'all') {
    $where_conditions[] = 'status = :status';
    $params['status'] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = '(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)';
    $params['search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with filters
$users = db()->fetchAll("
    SELECT * FROM users
    $where_clause
    ORDER BY created_at DESC
", array_merge($tenant_filter_params, $params));

// Get counts for badges
$count_where_prefix = $tenant_filter_sql !== '' ? $tenant_filter_sql . ' AND ' : '';
$count_params = $tenant_filter_params;

$total_users = db()->count('users', $tenant_filter_sql !== '' ? $tenant_filter_sql : '1=1', $count_params);
$active_users = db()->count('users', $count_where_prefix . 'status = :status', array_merge($count_params, ['status' => 'active']));
$pending_users = db()->count('users', $count_where_prefix . 'status = :status', array_merge($count_params, ['status' => 'pending']));
$admins = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'admin']));
$teachers = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'teacher']));
$students = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'student']));

$page_title = 'Users Management';
$page_icon = 'users-cog';
$full_name = $_SESSION['full_name'];
?>
<// Master layout configuration
$page_title = 'Users Management';
$page_icon = 'fas fa-users-cog';

ob_start();
?>

<?php if ($message): ?>
    <div class="mb-6 rounded-lg p-4 border <?php echo $message_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'; ?> flex items-center gap-3">
        <span class="material-symbols-outlined"><?php echo $message_type === 'success' ? 'check_circle' : 'warning'; ?></span>
        <span class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-sky-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">group</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</div>
            <div class="text-xl font-headline font-bold text-slate-800"><?php echo number_format($total_users); ?></div>
        </div>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-emerald-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">how_to_reg</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Active</div>
            <div class="text-xl font-headline font-bold text-slate-800"><?php echo $active_users; ?></div>
        </div>
    </div>

    <?php if ($pending_users > 0): ?>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-amber-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">schedule</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pending</div>
            <div class="text-xl font-headline font-bold text-amber-600"><?php echo $pending_users; ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-violet-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-violet-50 text-violet-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">admin_panel_settings</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Admins</div>
            <div class="text-xl font-headline font-bold text-slate-800"><?php echo $admins; ?></div>
        </div>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-cyan-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-cyan-50 text-cyan-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">local_library</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Teachers</div>
            <div class="text-xl font-headline font-bold text-slate-800"><?php echo $teachers; ?></div>
        </div>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-indigo-300 transition-colors">
        <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined">school</span>
        </div>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Students</div>
            <div class="text-xl font-headline font-bold text-slate-800"><?php echo $students; ?></div>
        </div>
    </div>
</div>

<!-- Filters & Actions -->
<div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-wrap lg:flex-nowrap items-center gap-4">
        <!-- Search input -->
        <div class="relative flex-grow min-w-[250px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>" 
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
        </div>

        <select name="role" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 font-medium focus:border-primary outline-none min-w-[140px]">
            <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
            <option value="teacher" <?php echo $filter_role === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
            <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Students</option>
            <option value="parent" <?php echo $filter_role === 'parent' ? 'selected' : ''; ?>>Parents</option>
        </select>

        <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 font-medium focus:border-primary outline-none min-w-[140px]">
            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>

        <button type="submit" class="px-5 py-2.5 bg-primary text-white font-bold rounded-lg text-sm hover:bg-primary-hover transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">filter_list</span> Filter
        </button>

        <?php if (!empty($search) || $filter_role !== 'all' || $filter_status !== 'all'): ?>
            <a href="users.php" class="px-5 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-lg text-sm hover:bg-slate-200 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">close</span> Clear
            </a>
        <?php endif; ?>

        <button type="button" onclick="toggleSelectAll()" id="selectAllBtn" class="px-5 py-2.5 border border-slate-200 text-slate-700 font-bold rounded-lg text-sm hover:bg-slate-50 transition-colors flex items-center gap-2 ml-auto">
            <span class="material-symbols-outlined text-[18px]">checklist_rtl</span> <span>Select All</span>
        </button>
    </form>
</div>

<!-- Bulk Actions Panel (Hidden by default) -->
<div id="bulkActions" class="hidden mb-6 bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-amber-500">info</span>
        <div class="text-amber-800 text-sm"><strong id="selectedCount" class="font-black text-amber-900">0</strong> users selected</div>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="clearSelection()" class="px-4 py-2 border border-amber-200 bg-white text-amber-700 hover:bg-amber-100 font-bold rounded-lg text-xs transition-colors">
            Cancel
        </button>
        <button onclick="bulkDelete()" class="px-4 py-2 bg-rose-600 text-white hover:bg-rose-700 font-bold rounded-lg text-xs transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[16px]">delete</span> Delete Selected
        </button>
    </div>
</div>

<!-- Users List -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <!-- List Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <h3 class="font-headline font-bold text-slate-800 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[18px]">group</span> Users List (<?php echo count($users); ?>)
        </h3>
        
        <?php if ($pending_users > 0): ?>
            <button onclick="deleteAllPending()" class="px-4 py-2 text-rose-600 font-bold text-xs hover:bg-rose-50 rounded-lg transition-colors flex items-center gap-2 border border-transparent hover:border-rose-200">
                <span class="material-symbols-outlined text-[16px]">delete_sweep</span> Delete All Pending
            </button>
        <?php endif; ?>
    </div>

    <!-- The actual list -->
    <?php if (empty($users)): ?>
        <div class="p-12 text-center text-slate-400 flex flex-col items-center">
            <span class="material-symbols-outlined text-6xl opacity-20 mb-4">person_off</span>
            <p class="font-medium text-slate-600 mb-1">No users found</p>
            <p class="text-xs">Try adjusting your filters or search criteria to find who you're looking for.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                        <th class="py-3 px-6 w-[40px] text-center">
                            <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll()" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary/20 cursor-pointer">
                        </th>
                        <th class="py-3 px-6">User</th>
                        <th class="py-3 px-6">Email</th>
                        <th class="py-3 px-6">Role</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6 hidden md:table-cell">Joined</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="usersList">
                    <?php foreach ($users as $user): 
                        $roleColors = [
                            'admin' => 'bg-violet-100 text-violet-700',
                            'teacher' => 'bg-cyan-100 text-cyan-700',
                            'student' => 'bg-emerald-100 text-emerald-700',
                            'parent' => 'bg-amber-100 text-amber-700',
                        ];
                        $roleClass = $roleColors[$user['role']] ?? 'bg-slate-100 text-slate-700';
                        
                        $statusClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                        if ($user['status'] === 'active') {
                            $statusClass = 'bg-emerald-50 text-emerald-600 border border-emerald-200';
                        } elseif ($user['status'] === 'pending') {
                            $statusClass = 'bg-amber-50 text-amber-600 border border-amber-200';
                        } elseif ($user['status'] === 'inactive') {
                            $statusClass = 'bg-rose-50 text-rose-600 border border-rose-200';
                        }
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="py-3 px-6 text-center">
                            <input type="checkbox" class="user-checkbox w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary/20 cursor-pointer" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" onclick="updateSelection()">
                        </td>
                        <td class="py-3 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-sm border border-slate-200 flex-shrink-0">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-800 line-clamp-1 group-hover:text-primary transition-colors">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        ID: <?php echo $user['id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-6 text-slate-600 font-medium">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </td>
                        <td class="py-3 px-6">
                            <span class="inline-flex px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $roleClass; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-6">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $statusClass; ?>">
                                <?php echo ucfirst($user['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="py-3 px-6 text-slate-500 text-xs hidden md:table-cell">
                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                        </td>
                        <td class="py-3 px-6">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="viewUser(<?php echo $user['id']; ?>)" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-primary flex items-center justify-center transition-colors" title="View Details">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                                
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')" class="w-8 h-8 rounded-lg bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors" title="Delete User">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                <?php else: ?>
                                    <button class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 text-slate-300 flex items-center justify-center cursor-not-allowed" title="Cannot delete yourself" disabled>
                                        <span class="material-symbols-outlined text-[18px]">block</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-6 w-[90%] max-w-md shadow-2xl transform scale-95 transition-transform duration-300" id="deleteModalContent">
        <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mb-4 mx-auto">
            <span class="material-symbols-outlined text-2xl">warning</span>
        </div>
        
        <h2 class="text-xl font-headline font-bold text-center text-slate-800 mb-2">Confirm Deletion</h2>
        <p class="text-center text-sm text-slate-500 mb-6">This action cannot be undone.</p>
        
        <div class="bg-rose-50 border border-rose-100 rounded-lg p-4 mb-6 text-sm">
            <p id="deleteMessage" class="font-medium text-rose-800 mb-2 font-headline uppercase text-center"></p>
            <ul class="text-rose-600 list-disc list-inside space-y-1 text-xs opacity-80">
                <li>User account will be permanently deleted</li>
                <li>All attendance records will be removed</li>
                <li>Biometric data will be cleared</li>
                <li>Related data will be destroyed</li>
            </ul>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-sm transition-colors">
                Cancel
            </button>
            <button onclick="executeDelete()" class="flex-1 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-sm transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">delete_forever</span> Delete
            </button>
        </div>
    </div>
</div>

<script>
    let deleteUserId = null;
    let selectedUsers = [];

    function confirmDelete(userId, userName) {
        deleteUserId = userId;
        document.getElementById('deleteMessage').textContent = `Delete ${userName}?`;
        
        const modal = document.getElementById('deleteModal');
        const modalContent = document.getElementById('deleteModalContent');
        
        modal.classList.remove('hidden');
        // trigger reflow
        void modal.offsetWidth;
        
        modal.classList.add('flex');
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        const modalContent = document.getElementById('deleteModalContent');
        
        modal.classList.add('opacity-0');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            deleteUserId = null;
        }, 300);
    }

    async function executeDelete() {
        if (!deleteUserId) return;

        try {
            const response = await fetch('../api/delete-user.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: deleteUserId })
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('❌ Error: ' + result.error);
            }
        } catch (error) {
            alert('❌ Network error: ' + error.message);
        } finally {
            closeDeleteModal();
        }
    }

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked');
        selectedUsers = Array.from(checkboxes).map(cb => ({
            id: parseInt(cb.dataset.userId),
            name: cb.dataset.userName
        }));

        document.getElementById('selectedCount').textContent = selectedUsers.length;
        
        const bulkDiv = document.getElementById('bulkActions');
        if (selectedUsers.length > 0) {
            bulkDiv.classList.remove('hidden');
        } else {
            bulkDiv.classList.add('hidden');
        }
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');

        // Toggle the state
        const newState = typeof selectAllCheckbox.checked !== 'undefined' ? selectAllCheckbox.checked : true;
        // if called from the button, the checkbox isn't naturally updated
        if(event && event.currentTarget === selectAllBtn) {
           selectAllCheckbox.checked = !selectAllCheckbox.checked;
        }

        checkboxes.forEach(cb => { cb.checked = selectAllCheckbox.checked; });

        // Update button text
        if (selectAllBtn) {
            const btnText = selectAllBtn.querySelector('span:not(.material-symbols-outlined)');
            const icon = selectAllBtn.querySelector('.material-symbols-outlined');
            if (selectAllCheckbox.checked) {
                btnText.textContent = 'Deselect All';
                icon.textContent = 'close';
            } else {
                btnText.textContent = 'Select All';
                icon.textContent = 'checklist_rtl';
            }
        }

        updateSelection();
    }

    function clearSelection() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const selectAllBtn = document.getElementById('selectAllBtn');

        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        if(selectAllCheckbox) selectAllCheckbox.checked = false;

        // Reset button text
        if (selectAllBtn) {
            selectAllBtn.querySelector('span:not(.material-symbols-outlined)').textContent = 'Select All';
            selectAllBtn.querySelector('.material-symbols-outlined').textContent = 'checklist_rtl';
        }

        updateSelection();
    }

    async function bulkDelete() {
        if (selectedUsers.length === 0) return;

        if (!confirm(`⚠️ Delete ${selectedUsers.length} users?\n\nThis will permanently delete:\n• User accounts\n• Attendance records\n• Biometric data\n• All related files\n\nThis cannot be undone!`)) {
            return;
        }

        try {
            const response = await fetch('../api/delete-user.php?action=bulk_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_ids: selectedUsers.map(u => u.id) })
            });

            const result = await response.json();

            if (result.success) {
                alert(`✅ Successfully deleted ${result.deleted} users!`);
                location.reload();
            } else {
                alert('❌ Errors occurred:\n' + result.errors.join('\n'));
                location.reload();
            }
        } catch (error) {
            alert('❌ Network error: ' + error.message);
        }
    }

    async function deleteAllPending() {
        const confirmation = prompt('⚠️ DELETE ALL PENDING USERS?\n\nThis will permanently delete ALL pending registration requests.\nType "DELETE_ALL_PENDING" to confirm:');

        if (confirmation !== 'DELETE_ALL_PENDING') {
            return;
        }

        try {
            const response = await fetch('../api/delete-user.php?action=delete_pending', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ confirm: 'DELETE_ALL_PENDING' })
            });

            const result = await response.json();

            if (result.success) {
                alert(`✅ Successfully deleted ${result.deleted} pending users!`);
                location.reload();
            } else {
                alert('❌ Error: ' + result.error);
            }
        } catch (error) {
            alert('❌ Network error: ' + error.message);
        }
    }

    function viewUser(userId) {
        window.location.href = 'student-view.php?id=' + userId;
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
