<?php
$accountantProfileMenuId = $accountantProfileMenuId ?? 'accountant-profile-menu';
$accountantProfileSupportHref = $accountantProfileSupportHref ?? site_url('frontend/notices.php');
$accountantProfileSettingsHref = $accountantProfileSettingsHref ?? ($accountantBase . 'index.php?page=settings');
$accountantProfileLogoutHref = $accountantProfileLogoutHref ?? site_url('logout.php');
?>
<div class="relative ml-2 pl-3 border-l border-outline-variant/20" data-accountant-profile>
  <button
    type="button"
    class="accountant-profile-trigger group flex items-center gap-2 rounded-2xl border border-outline-variant/20 bg-white px-2 py-1.5 text-left shadow-sm transition-all"
    title="<?php echo htmlspecialchars($fullName); ?>"
    aria-label="Open account menu"
    aria-expanded="false"
    aria-controls="<?php echo htmlspecialchars($accountantProfileMenuId); ?>"
    data-accountant-dropdown-toggle="<?php echo htmlspecialchars($accountantProfileMenuId); ?>">
    <span class="relative block h-10 w-10 overflow-hidden rounded-2xl ring-1 ring-primary/10">
      <img
        alt="Accountant Profile"
        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCcSZiKPxSUNiwsx_DR01hoax9T0fogW7Vi0FZ5HSTD_4Cl2rtwCYaRTg0vp-ApfEhAa1l0buTL03yoADi6Ge6bH1moQMYGOqY5Uer2eq1DEHvmXWdcCREAfeRc7uTUklmwhbD1ubNpzko_isYwWXDxS6urpEvVdktbhp4nn7J8moBoz--4VpKQ3NP_FfoKgUt6Pit4-4VVud0THzefsKF5fv2ny7HKWtxFH9k0kNEY8HKCEd5Gy8ui2P66DRsrE8Wz9MkuON7w8oLZ"
        data-alt="professional portrait of an accountant in a modern office with soft natural lighting and minimalist background"
        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.03]" />
    </span>
    <span class="material-symbols-outlined text-[18px] text-outline transition-transform duration-200 group-aria-expanded:rotate-180">expand_more</span>
  </button>

  <div
    id="<?php echo htmlspecialchars($accountantProfileMenuId); ?>"
    class="accountant-dropdown-card accountant-profile-menu hidden absolute right-0 top-full mt-3 w-[19rem] overflow-hidden rounded-[1.35rem] border border-outline-variant/20 bg-white z-50"
    data-accountant-dropdown-menu>
    <div class="accountant-profile-menu__hero border-b border-outline-variant/10 px-4 py-4">
      <div class="flex items-center gap-3">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-container text-base font-black text-primary shadow-sm">
          <?php echo htmlspecialchars($initial); ?>
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-extrabold text-on-surface"><?php echo htmlspecialchars($fullName); ?></p>
          <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-secondary">Accountant workspace</p>
        </div>
      </div>
    </div>

    <div class="p-2">
      <a href="<?php echo htmlspecialchars($accountantProfileSettingsHref); ?>" class="accountant-profile-item flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-on-surface transition-colors">
        <span class="accountant-profile-item__icon material-symbols-outlined text-[20px] text-primary">settings</span>
        <span class="flex-1">Settings</span>
        <span class="material-symbols-outlined text-[18px] text-outline">chevron_right</span>
      </a>
      <a href="<?php echo htmlspecialchars($accountantProfileSupportHref); ?>" class="accountant-profile-item flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-on-surface transition-colors">
        <span class="accountant-profile-item__icon material-symbols-outlined text-[20px] text-primary">contact_support</span>
        <span class="flex-1">Support</span>
        <span class="material-symbols-outlined text-[18px] text-outline">open_in_new</span>
      </a>
    </div>

    <div class="border-t border-outline-variant/10 p-2">
      <a href="<?php echo htmlspecialchars($accountantProfileLogoutHref); ?>" class="accountant-profile-item accountant-profile-item-danger flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-error transition-colors">
        <span class="accountant-profile-item__icon material-symbols-outlined text-[20px]">logout</span>
        <span class="flex-1">Logout</span>
      </a>
    </div>
  </div>
</div>
