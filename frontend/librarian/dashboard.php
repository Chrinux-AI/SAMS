<?php

/**
 * SAMS Librarian Dashboard - Modern Library Management Interface
 * Professional dashboard with library insights and AI-powered features
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');
if (!has_role('librarian') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Librarian privileges required.', 'error');
}

$librarian_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get library statistics
$library_stats = [
    'total_books' => lib_count('library_books', 'tenant_id = ?', [$tenantId]),
    'borrowed_books' => lib_count('library_loans', 'tenant_id = ? AND status = "active"', [$tenantId]),
    'overdue_books' => lib_count('library_loans', 'tenant_id = ? AND status = "active" AND due_date < CURDATE()', [$tenantId]),
    'available_books' => lib_count('library_books', 'tenant_id = ? AND status = "available"', [$tenantId]),
];

// Get recent loans
$recent_loans = db()->fetchAll("
    SELECT ll.*, u.first_name, u.last_name, lb.title, lb.author, lb.isbn
    FROM library_loans ll
    JOIN users u ON ll.student_id = u.id
    JOIN library_books lb ON ll.book_id = lb.id
    WHERE ll.tenant_id = ?
    ORDER BY ll.loan_date DESC
    LIMIT 10
", [$tenantId]);

// Get overdue books
$overdue_books = db()->fetchAll("
    SELECT ll.*, u.first_name, u.last_name, lb.title, lb.author,
           DATEDIFF(CURDATE(), ll.due_date) as days_overdue
    FROM library_loans ll
    JOIN users u ON ll.student_id = u.id
    JOIN library_books lb ON ll.book_id = lb.id
    WHERE ll.tenant_id = ? AND ll.status = 'active' AND ll.due_date < CURDATE()
    ORDER BY ll.due_date ASC
", [$tenantId]);

// Get popular books
$popular_books = db()->fetchAll("
    SELECT lb.*, COUNT(ll.id) as loan_count
    FROM library_books lb
    LEFT JOIN library_loans ll ON lb.id = ll.book_id
    WHERE lb.tenant_id = ?
    GROUP BY lb.id
    HAVING loan_count > 0
    ORDER BY loan_count DESC
    LIMIT 5
", [$tenantId]);

// AI Library Insights
$ai_insights = [];
try {
    require_once '../includes/sams-init.php';
    try {
        if (class_exists('SAMS_LibraryBot')) {
            $libraryBot = new SAMS_LibraryBot();
            $ai_insights = $libraryBot->getLibraryInsights($tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'library_health' => $library_stats['overdue_books'] > 5 ? 'needs_attention' : 'good',
            'reading_trend' => 'stable',
            'recommendation' => $library_stats['overdue_books'] > 0 ? 'Follow up on overdue books to improve circulation' : 'Library operations are running smoothly'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'library_health' => 'good',
        'reading_trend' => 'stable',
        'recommendation' => 'Continue regular library monitoring and book maintenance'
    ];
}

$csrf = generate_csrf_token();

// Safe count helper
function lib_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}

$stats = [
    'total_books'   => lib_count('library_books'),
    'issued_books'  => lib_count('library_transactions', "status = 'issued'"),
    'overdue_books' => lib_count('library_transactions', "status = 'issued' AND due_date < CURDATE()"),
    'total_members' => lib_count('library_members'),
    'categories'    => lib_count('library_categories'),
    'fines_pending' => lib_count('library_fines', "status = 'pending'"),
];

// Recent transactions
$recent = [];
try {
    if (table_exists('library_transactions') && table_exists('library_books')) {
        $recent = db()->fetchAll("
            SELECT lt.*, lb.title AS book_title, u.first_name, u.last_name
            FROM library_transactions lt
            LEFT JOIN library_books lb ON lt.book_id = lb.id
            LEFT JOIN users u ON lt.user_id = u.id
            ORDER BY lt.created_at DESC LIMIT 10
        ") ?: [];
    }
} catch (Throwable $e) {}
?>
// Master layout configuration
$page_title = 'Library Dashboard';
$page_icon = 'fas fa-book-open';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>

<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

  <!-- Welcome Banner & AI Insights (Top Full Width) -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Welcome Banner (2 cols wide) -->
    <div class="lg:col-span-2 bg-violet-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #6D28D9 0%, #5B21B6 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">local_library</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-violet-200">Library Control Center</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-violet-100 mt-2 max-w-lg opacity-90">Manage book inventory, track student loans, process returns, and oversee the institution's reading resources.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="issue-return.php" class="px-5 py-2.5 bg-white text-violet-800 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">sync_alt</span> Issue / Return Books
            </a>
            <a href="add-book.php" class="px-5 py-2.5 bg-violet-800 border border-violet-500/50 text-white font-bold rounded-lg text-sm hover:bg-violet-900 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">library_add</span> Add New Book
            </a>
        </div>
      </div>
    </div>
    
    <!-- AI Library Advisor Widget -->
    <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-100 p-6 rounded-xl flex flex-col shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-indigo-600">smart_toy</span>
            <h3 class="font-headline font-bold text-indigo-800">AI Library Advisor</h3>
        </div>
        <p class="text-sm font-medium text-indigo-900/80 mb-4 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Continue regular library monitoring and book maintenance.'); ?></p>
        
        <div class="mb-4 space-y-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Reading Trend</span>
            <p class="text-xs text-indigo-800 font-medium leading-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px] text-indigo-400">auto_graph</span> Student reading trend is <?php echo htmlspecialchars(strtoupper($ai_insights['reading_trend'] ?? 'stable')); ?>
            </p>
        </div>

        <div class="space-y-3 pt-3 border-t border-indigo-200/50">
            <div class="flex justify-between items-center text-sm">
                <span class="text-indigo-700 font-semibold text-xs tracking-wide uppercase">Library Health</span>
                <?php $lh = $ai_insights['library_health'] ?? 'good'; ?>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php 
                    echo $lh === 'needs_attention' ? 'bg-rose-100 text-rose-700' : ($lh === 'excellent' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700');
                ?>">
                    <?php echo htmlspecialchars(str_replace('_', ' ', $lh)); ?>
                </span>
            </div>
        </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-6 gap-4">
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Books</span>
          <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">menu_book</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo number_format($stats['total_books']); ?></span>
    </div>
    
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-sky-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Issued</span>
          <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">how_to_reg</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-sky-600 transition-colors"><?php echo number_format($stats['issued_books']); ?></span>
    </div>
    
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-rose-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Overdue</span>
          <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">warning</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-rose-600"><?php echo number_format($stats['overdue_books']); ?></span>
    </div>
    
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Members</span>
          <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">group</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo number_format($stats['total_members']); ?></span>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-amber-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Categories</span>
          <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">category</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-amber-600 transition-colors"><?php echo number_format($stats['categories']); ?></span>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-orange-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Pending Fines</span>
          <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">payments</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-orange-600 transition-colors"><?php echo number_format($stats['fines_pending']); ?></span>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Quick Actions & Popular Books (Left) -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="font-headline font-bold text-sm text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-violet-600">bolt</span> Quick Actions
            </h3>
            <div class="flex flex-col gap-3">
                <a href="overdue.php" class="px-4 py-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-bold hover:bg-rose-100 transition-colors flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined">warning</span>
                        <span class="text-sm">Overdue Books</span>
                    </div>
                    <?php if($stats['overdue_books'] > 0): ?>
                        <span class="px-2 py-0.5 bg-white rounded-md text-xs"><?php echo $stats['overdue_books']; ?></span>
                    <?php endif; ?>
                </a>

                <a href="books.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-violet-600">search</span>
                    <span class="text-sm">Search Catalog</span>
                </a>
                
                <a href="fines.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-violet-600">price_check</span>
                    <span class="text-sm">Manage Fines</span>
                </a>
            </div>
        </div>

        <!-- Popular Books (Using $popular_books) -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="font-headline font-bold text-sm text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-amber-500">star</span> Popular Books
            </h3>
            
            <div class="space-y-4">
            <?php if (!empty($popular_books)): ?>
                <?php foreach (array_slice($popular_books, 0, 4) as $book): ?>
                <div class="flex items-start gap-3">
                    <div class="w-10 h-14 bg-slate-100 rounded border border-slate-200 flex items-center justify-center flex-shrink-0 relative overflow-hidden">
                        <span class="material-symbols-outlined text-slate-400 text-[20px]">menu_book</span>
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-violet-500"></div>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-on-surface line-clamp-1 break-all"><?php echo htmlspecialchars($book['title']); ?></h4>
                        <p class="text-xs text-slate-500 line-clamp-1 mt-0.5"><?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></p>
                        <p class="text-[10px] font-bold text-violet-600 mt-1 uppercase tracking-wider">
                            <?php echo $book['loan_count']; ?> Loans
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-6 text-slate-400">
                    <span class="material-symbols-outlined text-3xl mb-2 opacity-50">book</span>
                    <p class="text-xs">No popular books data available.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Transactions / Loans (Right) -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <div class="flex justify-between items-center mb-6">
          <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">history</span> Recent Transactions
          </h3>
          <a href="transactions.php" class="text-xs font-bold text-violet-600 hover:text-violet-800 uppercase tracking-wider">View All</a>
      </div>
      
      <div class="flex-grow">
        <?php if (!empty($recent)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                            <th class="pb-3 font-bold">Book Title</th>
                            <th class="pb-3 font-bold">Member</th>
                            <th class="pb-3 font-bold">Date</th>
                            <th class="pb-3 font-bold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (array_slice($recent, 0, 8) as $t): 
                            $status = strtolower($t['status'] ?? 'issued');
                            $type = strtolower($t['type'] ?? 'issue');
                            
                            $badgeClass = 'bg-slate-100 text-slate-600';
                            $icon = 'circle';
                            
                            if ($status === 'returned') {
                                $badgeClass = 'bg-emerald-100 text-emerald-700';
                                $icon = 'check_circle';
                            } elseif ($status === 'issued') {
                                $badgeClass = 'bg-amber-100 text-amber-700';
                                $icon = 'schedule';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="py-3 pr-4">
                                <div class="font-bold text-primary line-clamp-1"><?php echo htmlspecialchars($t['book_title'] ?? 'N/A'); ?></div>
                                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5"><?php echo htmlspecialchars($type); ?></div>
                            </td>
                            <td class="py-3 text-slate-600 font-medium"><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></td>
                            <td class="py-3 text-slate-500 text-xs"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                            <td class="py-3 text-right">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $badgeClass; ?>">
                                    <span class="material-symbols-outlined text-[12px]"><?php echo $icon; ?></span> <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-10">
                <span class="material-symbols-outlined text-5xl mb-4 opacity-20">library_books</span>
                <p class="text-sm max-w-[300px] mb-2 font-medium text-slate-500">No library transactions found.</p>
                <p class="text-xs max-w-[300px]">Run the library setup SQL to enable full functionality and see transactions here.</p>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
