<script>
  function setTheme(theme, evt) {
    if (theme !== 'light' && theme !== 'dark') {
      theme = 'light';
    }
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.classList.toggle('light', theme !== 'dark');
    localStorage.setItem('sams_theme', theme);
    fetch('../api/save-theme.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        theme: theme
      })
    }).catch(function() {});
    var status = document.getElementById('theme-status');
    status.style.display = 'block';
    status.style.background = 'rgba(16, 185, 129, 0.1)';
    status.style.border = '1px solid #10b981';
    status.style.color = '#10b981';
    status.innerHTML = '<i class="fas fa-check-circle"></i> Theme updated to ' + theme.charAt(0).toUpperCase() + theme.slice(1).replace('-', ' ');
    setTimeout(function() {
      status.style.display = 'none';
    }, 3000);
    document.querySelectorAll('.theme-btn').forEach(function(b) {
      b.style.outline = 'none';
    });
    if (evt && evt.currentTarget) {
      evt.currentTarget.style.outline = '3px solid var(--primary)';
    }
  }
  document.addEventListener('DOMContentLoaded', function() {
    var current = localStorage.getItem('sams_theme') || 'dark';
    if (current !== 'light' && current !== 'dark') {
      current = 'light';
      localStorage.setItem('sams_theme', current);
    }
    document.documentElement.setAttribute('data-theme', current);
    document.documentElement.classList.toggle('dark', current === 'dark');
    document.documentElement.classList.toggle('light', current !== 'dark');
    document.querySelectorAll('.theme-btn').forEach(function(b) {
      var onClickAttr = b.getAttribute('onclick') || '';
      if (onClickAttr.indexOf("setTheme('" + current + "'") === 0) {
        b.style.outline = '3px solid var(--primary)';
      }
    });
  });
</script>
