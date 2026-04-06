<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_post_csrf_if_present()) {
        $message = 'Security validation failed. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $result = ClassController::handle($_POST);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
}

$classes = ClassRepository::all();
$teachers = db()->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'teacher' ORDER BY first_name, last_name");
$total_classes = count($classes);
$total_students_enrolled = array_sum(array_column($classes, 'student_count'));

$page_title = 'Classes Management';
$page_icon = 'door_open';
$full_name = $_SESSION['full_name'];
$csrf_token = generate_csrf_token();

// Start capturing content for master layout
ob_start();
?>

<?php if ($message): ?>
    <div class="mb-6 rounded-lg p-4 border <?php echo $message_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700'; ?> flex items-center gap-3">
        <span class="material-symbols-outlined"><?php echo $message_type === 'success' ? 'check_circle' : 'warning'; ?></span>
        <span class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <h2 class="text-2xl font-headline font-bold text-slate-800">Class Overview</h2>
    <button onclick="document.getElementById('addClassModal').style.display='flex'" class="px-5 py-2.5 bg-primary text-white font-bold rounded-lg text-sm hover:bg-primary-hover shadow-md transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">add_circle</span> Add New Class
    </button>
</div>

<!-- Stats Dashboard -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-cyan-700 text-white p-6 rounded-xl border border-cyan-600 shadow-md relative overflow-hidden group">
        <div class="absolute -right-6 -bottom-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <span class="material-symbols-outlined text-8xl">door_open</span>
        </div>
        <div class="relative z-10 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-600 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-2xl">door_open</span>
            </div>
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-cyan-200 mb-1">Total Classes</div>
                <div class="text-3xl font-headline font-bold"><?php echo $total_classes; ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-emerald-300 transition-colors group">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-100 transition-colors">
            <span class="material-symbols-outlined text-2xl">school</span>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Total Enrollments</div>
            <div class="text-3xl font-headline font-bold text-slate-800"><?php echo $total_students_enrolled; ?></div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 hover:border-violet-300 transition-colors group">
        <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center flex-shrink-0 group-hover:bg-violet-100 transition-colors">
            <span class="material-symbols-outlined text-2xl">local_library</span>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Teachers Available</div>
            <div class="text-3xl font-headline font-bold text-slate-800"><?php echo count($teachers); ?></div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-col lg:flex-row gap-4">
    <div class="relative flex-grow min-w-[300px]">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
        <input type="text" id="searchBox" placeholder="Search by class name, code, teacher..." class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all font-medium">
    </div>

    <div class="flex flex-wrap gap-2 items-center" id="levelFilters">
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold rounded-lg text-xs transition-colors active-filter" onclick="filterLevel('all', this)">All Levels</button>
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-bold rounded-lg text-xs transition-colors" onclick="filterLevel('100', this)">100 Level</button>
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-bold rounded-lg text-xs transition-colors" onclick="filterLevel('200', this)">200 Level</button>
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-bold rounded-lg text-xs transition-colors" onclick="filterLevel('300', this)">300 Level</button>
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-bold rounded-lg text-xs transition-colors" onclick="filterLevel('400', this)">400 Level</button>
        <button class="filter-btn px-4 py-2 border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 font-bold rounded-lg text-xs transition-colors" onclick="filterLevel('500', this)">500 Level</button>
    </div>
</div>

<!-- Classes Grid -->
<div id="classesContainer" data-sync-table="classes" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php foreach ($classes as $class): ?>
        <div class="class-card bg-white rounded-xl border border-slate-200 shadow-sm hover:border-primary/50 hover:shadow-md transition-all flex flex-col" data-level="<?php echo $class['grade_level']; ?>" data-search="<?php echo strtolower($class['name'] . ' ' . $class['class_code'] . ' ' . ($class['teacher_name'] ?? '')); ?>">

            <div class="p-5 border-b border-slate-100">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h3 class="font-headline font-bold text-lg text-slate-800 line-clamp-1"><?php echo htmlspecialchars($class['name']); ?></h3>
                        <div class="inline-flex mt-1 px-2.5 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-mono font-bold tracking-wider">
                            <?php echo htmlspecialchars($class['class_code']); ?>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="editClass(<?php echo $class['id']; ?>)" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-sky-50 hover:text-sky-600 flex items-center justify-center transition-colors" title="Edit Class">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('WARNING: Are you sure you want to delete this class?');">
                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                            <button type="submit" name="delete_class" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition-colors" title="Delete Class">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="p-5 flex-grow grid grid-cols-2 gap-4">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">layer</span> Level
                    </div>
                    <div class="text-sm font-semibold text-slate-700"><?php echo $class['grade_level']; ?> Level</div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">groups</span> Students
                    </div>
                    <div class="text-sm font-semibold text-emerald-600"><?php echo $class['student_count']; ?> Enrolled</div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">person</span> Teacher
                    </div>
                    <div class="text-sm font-semibold text-slate-700 line-clamp-1"><?php echo htmlspecialchars($class['teacher_name'] ?? 'Unassigned'); ?></div>
                </div>

                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">meeting_room</span> Room
                    </div>
                    <div class="text-sm font-semibold text-slate-700 line-clamp-1"><?php echo htmlspecialchars($class['room_number'] ?? 'TBA'); ?></div>
                </div>
            </div>

            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 rounded-b-xl">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">schedule</span> Schedule
                </div>
                <div class="text-xs font-medium text-slate-600">
                    <?php echo htmlspecialchars($class['schedule_display'] ?? 'No schedule assigned'); ?>
                </div>
            </div>

        </div>
    <?php endforeach; ?>

    <?php if (empty($classes)): ?>
        <div class="col-span-full py-16 text-center bg-white rounded-xl border border-slate-200">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">door_sliding</span>
            <h3 class="text-lg font-bold text-slate-700 mb-2">No Classes Found</h3>
            <p class="text-sm text-slate-500 mb-6">There are no classes registered in the system yet.</p>
            <button onclick="document.getElementById('addClassModal').style.display='flex'" class="px-6 py-2.5 bg-primary text-white font-bold rounded-lg text-sm hover:bg-primary-hover shadow-md transition-all inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add_circle</span> Add Your First Class
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Add Class Modal -->
<div id="addClassModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
    <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle"></i> <span>Add New Class</span></div>
            <button onclick="document.getElementById('addClassModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form method="POST" style="display:grid;gap:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS CODE *</label>
                        <input type="text" name="class_code" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS NAME *</label>
                        <input type="text" name="name" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">LEVEL *</label>
                        <select name="grade_level" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <option value="">Select Level</option>
                            <option value="100">100 Level</option>
                            <option value="200">200 Level</option>
                            <option value="300">300 Level</option>
                            <option value="400">400 Level</option>
                            <option value="500">500 Level</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ACADEMIC YEAR *</label>
                        <input type="text" name="academic_year" required placeholder="2024/2025" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                </div>
                <div>
                    <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ASSIGN TEACHER</label>
                    <select name="teacher_id" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        <option value="">Assign Later</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ROOM NUMBER</label>
                        <input type="text" name="room_number" placeholder="e.g. Room 101" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">SCHEDULE</label>
                        <div id="addScheduleSlots">
                            <div class="schedule-slot" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;">
                                <select name="schedule_days[]" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                </select>
                                <input type="time" name="schedule_starts[]" value="08:00" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <input type="time" name="schedule_ends[]" value="09:00" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <input type="text" name="schedule_rooms[]" placeholder="Room" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <button type="button" onclick="this.closest('.schedule-slot').remove()" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <button type="button" onclick="addScheduleSlot('addScheduleSlots')" class="cyber-btn" style="padding:6px 14px;font-size:0.8rem;"><i class="fas fa-plus"></i> Add Time Slot</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                    <button type="button" onclick="document.getElementById('addClassModal').style.display='none'" class="cyber-btn">Cancel</button>
                    <button type="submit" name="add_class" class="cyber-btn primary"><i class="fas fa-save"></i> Add Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div id="editClassModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
    <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-edit"></i> <span>Edit Class</span></div>
            <button onclick="document.getElementById('editClassModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form method="POST" id="editClassForm" style="display:grid;gap:20px;">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS CODE *</label>
                        <input type="text" name="class_code" id="edit_class_code" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS NAME *</label>
                        <input type="text" name="name" id="edit_name" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">LEVEL *</label>
                        <select name="grade_level" id="edit_grade_level" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <option value="">Select Level</option>
                            <option value="100">100 Level</option>
                            <option value="200">200 Level</option>
                            <option value="300">300 Level</option>
                            <option value="400">400 Level</option>
                            <option value="500">500 Level</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ACADEMIC YEAR *</label>
                        <input type="text" name="academic_year" id="edit_academic_year" required placeholder="2024/2025" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                </div>
                <div>
                    <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ASSIGN TEACHER</label>
                    <select name="teacher_id" id="edit_teacher_id" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        <option value="">Assign Later</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ROOM NUMBER</label>
                        <input type="text" name="room_number" id="edit_room_number" placeholder="e.g. Room 101" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">SCHEDULE</label>
                        <div id="editScheduleSlots"></div>
                        <button type="button" onclick="addScheduleSlot('editScheduleSlots')" class="cyber-btn" style="padding:6px 14px;font-size:0.8rem;"><i class="fas fa-plus"></i> Add Time Slot</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                    <button type="button" onclick="document.getElementById('editClassModal').style.display='none'" class="cyber-btn">Cancel</button>
                    <button type="submit" name="edit_class" class="cyber-btn primary"><i class="fas fa-save"></i> Update Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editClass(id) {
        fetch('../api/class.php?id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('edit_class_id').value = data.id;
                document.getElementById('edit_class_code').value = data.class_code || data.section || '';
                document.getElementById('edit_name').value = data.name || data.class_name || '';
                document.getElementById('edit_grade_level').value = data.grade_level || '';
                document.getElementById('edit_academic_year').value = data.academic_year || '';
                document.getElementById('edit_teacher_id').value = data.teacher_id || '';
                document.getElementById('edit_room_number').value = data.room_number || '';
                document.getElementById('editClassModal').style.display = 'flex';
                // Load schedules for this class
                loadEditSchedules(id);
            })
            .catch(err => alert('Failed to load class: ' + err.message));
    }

    document.getElementById('searchBox').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.class-card').forEach(card => {
            const text = card.getAttribute('data-search');
            card.style.display = text.includes(search) ? 'block' : 'none';
        });
    });

    function filterLevel(level) {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        document.querySelectorAll('.class-card').forEach(card => {
            if (level === 'all' || card.getAttribute('data-level') === level) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function addScheduleSlot(containerId) {
        const container = document.getElementById(containerId);
        const slot = document.createElement('div');
        slot.className = 'schedule-slot';
        slot.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;';
        slot.innerHTML = `
                <select name="schedule_days[]" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                </select>
                <input type="time" name="schedule_starts[]" value="08:00" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                <input type="time" name="schedule_ends[]" value="09:00" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                <input type="text" name="schedule_rooms[]" placeholder="Room" style="padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                <button type="button" onclick="this.closest('.schedule-slot').remove()" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            `;
        container.appendChild(slot);
    }

    function loadEditSchedules(classId) {
        const container = document.getElementById('editScheduleSlots');
        container.innerHTML = '';
        fetch('../api/class-schedules.php?class_id=' + encodeURIComponent(classId))
            .then(r => r.json())
            .then(data => {
                if (data.schedules && data.schedules.length) {
                    data.schedules.forEach(s => {
                        addScheduleSlot('editScheduleSlots');
                        const slots = container.querySelectorAll('.schedule-slot');
                        const last = slots[slots.length - 1];
                        last.querySelector('select[name="schedule_days[]"]').value = s.day_of_week;
                        last.querySelector('input[name="schedule_starts[]"]').value = s.start_time;
                        last.querySelector('input[name="schedule_ends[]"]').value = s.end_time;
                        last.querySelector('input[name="schedule_rooms[]"]').value = s.room || '';
                    });
                }
            })
            .catch(() => {});
    }
</script>

<script src="../assets/js/auto-sync.js"></script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = <?php echo json_encode($csrf_token); ?>;
        document.querySelectorAll('form').forEach((form) => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });
    });
</script>
</body>

</html>

<?php
// Capture content and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
