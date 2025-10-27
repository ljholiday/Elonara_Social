<?php
/**
 * Compact stats row partial.
 *
 * @var array<int,array<string,mixed>> $items List of stats with keys:
 *      - value: scalar|string (required)
 *      - label: string (optional)
 *      - hint: string (optional, small text under label)
 *      - icon: string (optional, trusted HTML for an inline icon)
 *      - class: string (optional, extra class on the stat container)
 * @var string|null $class Optional additional class for the wrapper row.
 */

declare(strict_types=1);

$rawItems = $items ?? [];
if (!is_array($rawItems)) {
    $rawItems = [];
}

$items = array_values(array_filter($rawItems, static function ($item): bool {
    if (!is_array($item)) {
        return false;
    }

    $value = $item['value'] ?? null;

    return $value !== null && trim((string)$value) !== '';
}));

if ($items === []) {
    return;
}

$class = trim((string)($class ?? ''));
$rowClass = 'app-stats-row';
if ($class !== '') {
    $rowClass .= ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
}
?>
<div class="<?= $rowClass ?>">
  <?php foreach ($items as $item): ?>
    <?php
      $value = trim((string)($item['value'] ?? ''));
      $label = trim((string)($item['label'] ?? ''));
      $hint = trim((string)($item['hint'] ?? ''));
      $icon = (string)($item['icon'] ?? '');
      $statClass = trim((string)($item['class'] ?? ''));
      $statClassAttr = $statClass !== '' ? ' ' . htmlspecialchars($statClass, ENT_QUOTES, 'UTF-8') : '';
    ?>
    <div class="app-stat<?= $statClassAttr ?>">
      <div class="app-stat-number">
        <?php if ($icon !== ''): ?>
          <span class="app-stat-icon"><?= $icon ?></span>
        <?php endif; ?>
        <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
      </div>
      <?php if ($label !== ''): ?>
        <div class="app-stat-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($hint !== ''): ?>
        <div class="app-stat-hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
