<?php
// Sidebar
$activeTab = $activeTab ?? 'dashboard';
?>
<aside class="h-screen w-64 fixed left-0 top-0 border-r border-outline-variant/30 bg-white flex flex-col p-4 space-y-2 z-50 overflow-hidden">
        <div class="px-2 py-4 mb-4">
          <div class="flex items-center gap-3">
            <picture class="block w-12 h-12 shrink-0 overflow-hidden rounded-2xl">
              <source media="(prefers-color-scheme: dark)" srcset="<?php echo htmlspecialchars($root . '/assets/logo/logo4.png'); ?>" />
              <img src="<?php echo htmlspecialchars($root . '/assets/logo/logo5.png'); ?>" alt="SAMS Logo" class="w-full h-full object-cover scale-[1.18] origin-center" data-accountant-brand-img data-brand-light="<?php echo htmlspecialchars($root . '/assets/logo/logo5.png'); ?>" data-brand-dark="<?php echo htmlspecialchars($root . '/assets/logo/logo4.png'); ?>" />
            </picture>
            <div>
              <h1 class="font-headline font-extrabold text-on-surface leading-tight tracking-tight">SAMS</h1>
              <p class="text-[10px] text-primary font-bold uppercase tracking-widest">Financial Architect</p>
            </div>
          </div>
        </div>

        <nav class="flex-1 min-h-0 space-y-1 overflow-y-auto pr-1">
          <?php foreach ($tabs as $key => $item):
            $isActive = $activeTab === $key;
          ?>
            <a class="flex items-center gap-3 px-4 py-3 transition-all group <?php echo $isActive ? 'bg-primary-container text-on-primary-container rounded-lg font-bold' : 'text-secondary hover:bg-surface-container rounded-lg'; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
              <span class="material-symbols-outlined <?php echo $isActive ? 'text-primary' : ''; ?>" <?php echo $isActive ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>><?php echo htmlspecialchars($item['icon']); ?></span>
              <span class="text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?>"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

      </aside>
