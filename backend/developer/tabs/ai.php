<?php

/**
 * Developer Settings — AI Tab
 */

// AI Service status
$aiServices = [
  ['name' => 'CoreAIService', 'class' => 'CoreAIService', 'desc' => 'Central AI engine'],
  ['name' => 'AIRouter', 'class' => 'AIRouter', 'desc' => 'Role-based AI routing'],
  ['name' => 'CognitiveKernel', 'class' => 'CognitiveKernel', 'desc' => 'Institutional cognition'],
  ['name' => 'IntelligenceKernel', 'class' => 'IntelligenceKernel', 'desc' => 'Platform intelligence'],
  ['name' => 'EcosystemKernel', 'class' => 'EcosystemKernel', 'desc' => 'Distributed ecosystem'],
  ['name' => 'PredictionEngine', 'class' => 'PredictionEngine', 'desc' => 'Predictive analytics'],
  ['name' => 'AcademicReasoner', 'class' => 'AcademicReasoner', 'desc' => 'Academic analysis'],
  ['name' => 'EthicalGuard', 'class' => 'EthicalGuard', 'desc' => 'AI safety system'],
];

$cogScore = 0;
try {
  $cogFile = BASE_PATH . '/storage/cognitive-summary.json';
  if (is_file($cogFile)) {
    $cog = json_decode(file_get_contents($cogFile), true);
    $cogScore = $cog['cognitive_score'] ?? 0;
  }
} catch (\Throwable $e) {
}
?>

<h3 style="margin-top:0;margin-bottom:1rem;"><i class="fas fa-robot"></i> AI Configuration</h3>

<div style="text-align:center;padding:1.5rem;background:rgba(0,229,255,.05);border-radius:12px;margin-bottom:1.5rem;">
  <div style="font-size:2.5rem;font-weight:800;color:#00e5ff;"><?= $cogScore ?>/100</div>
  <div style="font-size:.8rem;opacity:.6;">Last Cognitive Score</div>
</div>

<h4 style="margin-bottom:.8rem;">AI Services</h4>
<table style="width:100%;border-collapse:collapse;font-size:.85rem;">
  <thead>
    <tr style="border-bottom:1px solid rgba(255,255,255,.1);">
      <th style="text-align:left;padding:.5rem;">Service</th>
      <th style="text-align:left;padding:.5rem;">Status</th>
      <th style="text-align:left;padding:.5rem;">Description</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($aiServices as $svc): ?>
      <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
        <td style="padding:.5rem;font-weight:500;"><?= htmlspecialchars($svc['name']) ?></td>
        <td style="padding:.5rem;">
          <span class="status-badge <?= class_exists($svc['class']) ? 'status-ok' : 'status-err' ?>">
            <?= class_exists($svc['class']) ? 'LOADED' : 'MISSING' ?>
          </span>
        </td>
        <td style="padding:.5rem;opacity:.7;"><?= htmlspecialchars($svc['desc']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
