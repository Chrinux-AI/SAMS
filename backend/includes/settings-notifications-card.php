<!-- Notification Preferences Card -->
<div class="settings-card">
  <h2><i class="fas fa-bell"></i> Notifications</h2>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <label style="margin: 0;">Email Notifications</label>
        <small style="color: var(--text-secondary); display: block;">Receive updates via email</small>
      </div>
      <label class="toggle-switch">
        <input type="checkbox" name="email_notifications" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
        <span class="toggle-slider"></span>
      </label>
    </div>
    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <label style="margin: 0;">SMS Notifications</label>
        <small style="color: var(--text-secondary); display: block;">Receive text messages</small>
      </div>
      <label class="toggle-switch">
        <input type="checkbox" name="sms_notifications" <?php echo ($user['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
        <span class="toggle-slider"></span>
      </label>
    </div>
    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <label style="margin: 0;">Push Notifications</label>
        <small style="color: var(--text-secondary); display: block;">Browser notifications</small>
      </div>
      <label class="toggle-switch">
        <input type="checkbox" name="push_notifications" <?php echo ($user['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
        <span class="toggle-slider"></span>
      </label>
    </div>
    <button type="submit" name="update_notifications" class="btn btn-primary">
      <i class="fas fa-save"></i> Save Preferences
    </button>
  </form>
</div>
