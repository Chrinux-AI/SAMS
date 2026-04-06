<!-- Account Overview Card -->
<div class="settings-card">
  <h2><i class="fas fa-chart-bar"></i> Account Overview</h2>
  <div class="stats-row">
    <div class="stat-box">
      <h3><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></h3>
      <p>Username</p>
    </div>
    <div class="stat-box">
      <h3><?php echo isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?></h3>
      <p>Member Since</p>
    </div>
    <div class="stat-box">
      <h3><?php echo ucfirst($user['status'] ?? 'active'); ?></h3>
      <p>Account Status</p>
    </div>
  </div>
</div>
