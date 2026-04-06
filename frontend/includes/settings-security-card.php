<!-- Security Settings Card -->
<div class="settings-card">
  <h2><i class="fas fa-shield-alt"></i> Security</h2>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-group">
      <label>Current Password</label>
      <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new_password" class="form-control" required>
      <small style="color: var(--text-secondary);">Minimum 8 characters</small>
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" class="form-control" required>
    </div>
    <button type="submit" name="change_password" class="btn btn-primary">
      <i class="fas fa-key"></i> Change Password
    </button>
  </form>
</div>
