<?php

/**
 * Card Component — Reusable panel/card wrapper.
 *
 * Usage:
 *   <?php
 *   $card_title = 'Recent Activity';
 *   $card_icon  = 'fas fa-clock';
 *   $card_badge = ['text' => '5 new', 'class' => 'badge-info'];
 *   $card_actions = '<a href="#">View All</a>';
 *   ob_start();
 *   ?>
 *   <!-- card body content -->
 *   <?php
 *   $card_body = ob_get_clean();
 *   include BASE_PATH . '/resources/ui-core/components/cards.php';
 *   ?>
 */

$card_title   = $card_title ?? '';
$card_icon    = $card_icon ?? '';
$card_badge   = $card_badge ?? null;
$card_actions = $card_actions ?? '';
$card_body    = $card_body ?? '';
$card_class   = $card_class ?? '';
$card_id      = $card_id ?? '';
?>
<div class="panel <?php echo htmlspecialchars($card_class); ?>" <?php if ($card_id): ?> id="<?php echo htmlspecialchars($card_id); ?>" <?php endif; ?>
  style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--card-radius);overflow:hidden;margin-bottom:var(--space-lg);">
  <?php if ($card_title): ?>
    <div class="panel-header" style="padding:var(--space-lg);border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
      <h3 style="font-size:1.05rem;font-weight:var(--font-bold);display:flex;align-items:center;gap:var(--space-sm);">
        <?php if ($card_icon): ?><i class="<?php echo htmlspecialchars($card_icon); ?>" style="color:var(--primary);"></i><?php endif; ?>
        <?php echo htmlspecialchars($card_title); ?>
      </h3>
      <div style="display:flex;align-items:center;gap:var(--space-sm);">
        <?php if ($card_badge): ?>
          <span class="badge <?php echo htmlspecialchars($card_badge['class'] ?? ''); ?>"
            style="display:inline-block;padding:2px 10px;border-radius:var(--badge-radius);font-size:var(--text-xs);font-weight:var(--font-semibold);">
            <?php echo htmlspecialchars($card_badge['text'] ?? ''); ?>
          </span>
        <?php endif; ?>
        <?php echo $card_actions; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="panel-body" style="padding:var(--space-lg);">
    <?php echo $card_body; ?>
  </div>
</div>
<?php
// Reset variables to prevent bleed
$card_title = $card_icon = $card_body = $card_actions = $card_class = $card_id = '';
$card_badge = null;
?>
