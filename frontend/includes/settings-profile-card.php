<!-- Profile Settings Card -->
<?php
$hasAvatar = !empty($user['profile_picture']);
$avatarUrl = $hasAvatar ? '/attendance/' . htmlspecialchars($user['profile_picture']) : '';
$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
?>
<div class="settings-card">
  <h2><i class="fas fa-user-circle"></i> Profile Information</h2>

  <!-- Avatar with upload -->
  <div class="avatar-upload-wrapper" style="text-align:center;margin-bottom:1.5rem;">
    <div class="profile-avatar" id="profileAvatar" style="position:relative;cursor:pointer;" title="Click to change photo">
      <?php if ($hasAvatar): ?>
        <img src="<?php echo $avatarUrl; ?>" alt="Avatar" id="avatarImg"
          style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      <?php else: ?>
        <span id="avatarInitials"><?php echo $initials; ?></span>
      <?php endif; ?>
      <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;background:var(--primary,#4F46E5);border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg-white,#fff);">
        <i class="fas fa-camera" style="font-size:12px;color:#fff;"></i>
      </div>
    </div>
    <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
    <div style="margin-top:8px;">
      <button type="button" id="avatarUploadBtn" class="btn btn-sm" style="font-size:12px;padding:4px 12px;background:var(--primary,#4F46E5);color:#fff;border:none;border-radius:6px;cursor:pointer;">
        <i class="fas fa-upload"></i> Change Photo
      </button>
      <?php if ($hasAvatar): ?>
        <button type="button" id="avatarRemoveBtn" class="btn btn-sm" style="font-size:12px;padding:4px 12px;background:var(--danger,#ef4444);color:#fff;border:none;border-radius:6px;cursor:pointer;margin-left:4px;">
          <i class="fas fa-trash"></i> Remove
        </button>
      <?php endif; ?>
    </div>
    <div id="avatarStatus" style="margin-top:6px;font-size:12px;display:none;"></div>
    <small style="color:var(--text-muted,#888);font-size:11px;">JPG, PNG, or WEBP. Max 2 MB.</small>
  </div>

  <div style="text-align: center; margin-bottom: 1.5rem;">
    <h3 style="color: var(--text-primary);"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h3>
    <span class="role-badge role-<?php echo htmlspecialchars($user_role); ?>"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></span>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-group">
      <label>First Name</label>
      <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
    </div>
    <div class="form-group">
      <label>Last Name</label>
      <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
    </div>
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
    </div>
    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
    </div>
    <button type="submit" name="update_profile" class="btn btn-primary">
      <i class="fas fa-save"></i> Save Changes
    </button>
  </form>
</div>

<script>
  (function() {
    const avatarEl = document.getElementById('profileAvatar');
    const fileInput = document.getElementById('avatarFileInput');
    const uploadBtn = document.getElementById('avatarUploadBtn');
    const removeBtn = document.getElementById('avatarRemoveBtn');
    const statusEl = document.getElementById('avatarStatus');
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    function showStatus(msg, isError) {
      statusEl.style.display = 'block';
      statusEl.style.color = isError ? 'var(--danger,#ef4444)' : 'var(--success,#22c55e)';
      statusEl.textContent = msg;
      if (!isError) setTimeout(() => statusEl.style.display = 'none', 3000);
    }

    avatarEl.addEventListener('click', () => fileInput.click());
    uploadBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async function() {
      const file = this.files[0];
      if (!file) return;

      if (file.size > 2 * 1024 * 1024) {
        showStatus('File too large. Maximum 2 MB.', true);
        return;
      }
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        showStatus('Invalid file type. Use JPG, PNG, or WEBP.', true);
        return;
      }

      const fd = new FormData();
      fd.append('avatar', file);
      fd.append('csrf_token', csrfToken);

      showStatus('Uploading...', false);
      try {
        const resp = await fetch('/attendance/api/upload-avatar.php', {
          method: 'POST',
          body: fd
        });
        const data = await resp.json();
        if (data.success) {
          showStatus(data.message, false);
          // Update avatar display
          avatarEl.innerHTML = '<img src="' + data.avatar_url + '?t=' + Date.now() + '" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">' +
            avatarEl.querySelector('div:last-child').outerHTML;
          if (!removeBtn) location.reload();
        } else {
          showStatus(data.error || 'Upload failed', true);
        }
      } catch (e) {
        showStatus('Upload failed. Please try again.', true);
      }
      this.value = '';
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', async function() {
        if (!confirm('Remove your profile picture?')) return;
        const fd = new FormData();
        fd.append('remove_avatar', '1');
        fd.append('csrf_token', csrfToken);
        try {
          const resp = await fetch('/attendance/api/upload-avatar.php', {
            method: 'POST',
            body: fd
          });
          const data = await resp.json();
          if (data.success) {
            location.reload();
          } else {
            showStatus(data.error || 'Failed to remove', true);
          }
        } catch (e) {
          showStatus('Failed to remove. Please try again.', true);
        }
      });
    }
  })();
</script>
