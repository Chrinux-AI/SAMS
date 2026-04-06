<?php

/**
 * Developer Settings — Integrations Tab
 */

$integrations = [
  ['name' => 'SMTP Email', 'configured' => !empty(SMTP_HOST) && !empty(SMTP_USERNAME), 'icon' => 'fas fa-envelope'],
  ['name' => 'WhatsApp (Twilio)', 'configured' => !empty(TWILIO_ACCOUNT_SID), 'icon' => 'fab fa-whatsapp'],
  ['name' => 'Federation Engine', 'configured' => class_exists('FederationEngine'), 'icon' => 'fas fa-project-diagram'],
  ['name' => 'Knowledge Exchange', 'configured' => class_exists('KnowledgeExchange'), 'icon' => 'fas fa-exchange-alt'],
  ['name' => 'Event Bus', 'configured' => class_exists('EventBus'), 'icon' => 'fas fa-broadcast-tower'],
];
?>

<h3 style="margin-top:0;margin-bottom:1rem;"><i class="fas fa-plug"></i> Integrations</h3>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:1rem;">
  <?php foreach ($integrations as $int): ?>
    <div style="padding:1rem;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06);border-radius:10px;">
      <div style="font-size:1.5rem;margin-bottom:.6rem;color:<?= $int['configured'] ? '#00ff41' : '#ff4444' ?>;">
        <i class="<?= $int['icon'] ?>"></i>
      </div>
      <div style="font-weight:500;margin-bottom:.3rem;"><?= htmlspecialchars($int['name']) ?></div>
      <span class="status-badge <?= $int['configured'] ? 'status-ok' : 'status-err' ?>">
        <?= $int['configured'] ? 'Connected' : 'Not Configured' ?>
      </span>
    </div>
  <?php endforeach; ?>
</div>
