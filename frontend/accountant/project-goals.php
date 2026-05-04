<?php

/**
 * SAMS Accountant Project Goals Hub
 * Stores blockchain/security/product goals in JSON for planning visibility.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
  redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$storageDir = BASE_PATH . '/storage/data';
$storageFile = $storageDir . '/project-goals.json';

if (!is_dir($storageDir)) {
  @mkdir($storageDir, 0775, true);
}

$goals = [];
if (is_file($storageFile)) {
  $raw = @file_get_contents($storageFile);
  $parsed = json_decode((string)$raw, true);
  if (is_array($parsed)) {
    $goals = $parsed;
  }
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!verify_csrf_token($token)) {
    $errors[] = 'Security validation failed. Please refresh and try again.';
  }

  $goalTitle = trim((string)($_POST['goal_title'] ?? ''));
  $goalIdea = trim((string)($_POST['goal_idea'] ?? ''));
  $goalType = trim((string)($_POST['goal_type'] ?? 'Project'));
  $priority = trim((string)($_POST['priority'] ?? 'Medium'));
  $targetDate = trim((string)($_POST['target_date'] ?? ''));
  $fingerprintRequired = isset($_POST['fingerprint_required']) ? 'yes' : 'no';
  $timeoutMinutes = (int)($_POST['timeout_minutes'] ?? 15);

  if ($goalTitle === '') {
    $errors[] = 'Goal title is required.';
  }
  if ($goalIdea === '') {
    $errors[] = 'Goal details are required.';
  }
  if ($timeoutMinutes < 1 || $timeoutMinutes > 180) {
    $errors[] = 'Timeout must be between 1 and 180 minutes.';
  }

  if (empty($errors)) {
    $goals[] = [
      'id' => bin2hex(random_bytes(8)),
      'goal_title' => $goalTitle,
      'goal_idea' => $goalIdea,
      'goal_type' => $goalType,
      'priority' => $priority,
      'target_date' => $targetDate,
      'fingerprint_required' => $fingerprintRequired,
      'timeout_minutes' => $timeoutMinutes,
      'created_by' => $_SESSION['full_name'] ?? 'Unknown',
      'created_at' => date('Y-m-d H:i:s')
    ];

    $encoded = json_encode($goals, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (@file_put_contents($storageFile, $encoded) === false) {
      $errors[] = 'Could not save goal right now. Please check file permissions.';
    } else {
      $success = 'Goal added successfully.';
    }
  }
}

$page_title = 'Project Goals & Ideas';
$page_icon = 'flag';
$page_subtitle = 'Plan blockchain adoption, fingerprint capture, session timeout policy, and accountant UX improvements.';
$csrf = generate_csrf_token();

$activeTab = 'project-goals';
require_once __DIR__ . '/partials/header.php';
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-7 bg-surface-container-low border border-outline-variant/10 rounded-xl p-6 shadow-sm">
    <h3 class="text-lg font-headline font-bold text-primary mb-2">Add New Goal</h3>
    <p class="text-sm text-on-surface-variant mb-6">Capture what the team wants to build, why it matters, and how security should behave.</p>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
        <ul class="list-disc ml-5">
          <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

      <div>
        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Goal Title</label>
        <input type="text" name="goal_title" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest" placeholder="e.g. Blockchain proof for fee transactions" required>
      </div>

      <div>
        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Goal Details / Idea</label>
        <textarea name="goal_idea" rows="4" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest" placeholder="Describe what we want to build and expected impact..." required></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold text-on-surface-variant mb-1">Type</label>
          <select name="goal_type" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest">
            <option>Project</option>
            <option>Blockchain</option>
            <option>Biometric</option>
            <option>Security</option>
            <option>UI/UX</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-on-surface-variant mb-1">Priority</label>
          <select name="priority" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest">
            <option>High</option>
            <option selected>Medium</option>
            <option>Low</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-on-surface-variant mb-1">Target Date</label>
          <input type="date" name="target_date" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="flex items-center gap-2 text-sm text-on-surface-variant border border-outline-variant/15 rounded-lg p-3 bg-surface-container-lowest">
          <input type="checkbox" name="fingerprint_required" value="1" class="rounded border-outline-variant/30">
          Require fingerprint verification for this goal workflow
        </label>

        <div>
          <label class="block text-sm font-semibold text-on-surface-variant mb-1">Session Timeout (minutes)</label>
          <input type="number" min="1" max="180" value="15" name="timeout_minutes" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest">
        </div>
      </div>

      <div class="pt-2 flex gap-3">
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-semibold hover:opacity-90">Save Goal</button>
        <a href="index.php?page=dashboard" class="px-5 py-2.5 rounded-lg border border-outline-variant/30 text-on-surface-variant font-semibold hover:bg-surface-container-high">Back to Dashboard</a>
      </div>
    </form>
  </div>

  <div class="col-span-12 lg:col-span-5 space-y-6">
    <div class="bg-surface-container-high border border-outline-variant/10 rounded-xl p-6 shadow-sm">
      <h3 class="text-base font-headline font-bold text-primary mb-2">Blockchain Mission</h3>
      <p class="text-sm text-on-surface-variant">Track immutable proofs for fee records, attendance events, and audit actions while keeping user privacy protected.</p>
      <a class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary mt-4" href="../../PROJECT_BLOCKCHAIN_GOALS.md" target="_blank" rel="noopener">
        <span class="material-symbols-outlined text-sm">description</span> Open full roadmap
      </a>
    </div>

    <div class="bg-surface-container-low border border-outline-variant/10 rounded-xl p-6 shadow-sm">
      <h3 class="text-base font-headline font-bold text-primary mb-3">Saved Goals (<?php echo count($goals); ?>)</h3>

      <?php if (empty($goals)): ?>
        <p class="text-sm text-on-surface-variant">No goals saved yet. Add one from the form to start the roadmap.</p>
      <?php else: ?>
        <div class="space-y-3 max-h-[470px] overflow-auto pr-1">
          <?php foreach (array_reverse($goals) as $goal): ?>
            <div class="border border-outline-variant/15 rounded-lg p-3 bg-surface-container-lowest">
              <div class="flex items-center justify-between gap-2 mb-1">
                <h4 class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($goal['goal_title'] ?? 'Untitled'); ?></h4>
                <span class="text-[10px] px-2 py-1 rounded bg-surface-container-high text-on-surface-variant uppercase font-semibold tracking-wide"><?php echo htmlspecialchars($goal['priority'] ?? 'Medium'); ?></span>
              </div>
              <p class="text-xs text-on-surface-variant mb-2"><?php echo htmlspecialchars($goal['goal_idea'] ?? ''); ?></p>
              <div class="text-[11px] text-outline flex flex-wrap gap-3">
                <span>Type: <?php echo htmlspecialchars($goal['goal_type'] ?? 'Project'); ?></span>
                <span>Fingerprint: <?php echo htmlspecialchars($goal['fingerprint_required'] ?? 'no'); ?></span>
                <span>Timeout: <?php echo (int)($goal['timeout_minutes'] ?? 15); ?>m</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
require_once __DIR__ . '/partials/footer.php';
