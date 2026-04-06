<!-- Theme Selection Card -->
<div class="settings-card">
  <h2><i class="fas fa-palette"></i> Theme Selection</h2>
  <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Choose your preferred appearance</p>
  <div class="theme-options" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem;">
    <button type="button" class="theme-btn" onclick="setTheme('light', event)" style="padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; background: #ffffff; text-align: center; transition: all 0.3s;"><i class="fas fa-sun" style="font-size: 2rem; color: #f59e0b; display: block; margin-bottom: 0.5rem;"></i><span style="color: #1e293b; font-weight: 600;">Light</span></button>
    <button type="button" class="theme-btn" onclick="setTheme('dark', event)" style="padding: 1.5rem; border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; background: #1a1d27; text-align: center; transition: all 0.3s;"><i class="fas fa-moon" style="font-size: 2rem; color: #818cf8; display: block; margin-bottom: 0.5rem;"></i><span style="color: #e2e8f0; font-weight: 600;">Dark</span></button>
  </div>
  <div id="theme-status" style="margin-top: 1rem; padding: 0.75rem; border-radius: 6px; display: none; text-align: center;"></div>
</div>
