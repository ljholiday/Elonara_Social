<?php
/**
 * Compact metadata row partial.
 *
 * @var array<int,array<string,mixed>> $items List of metadata items with keys:
 *      - text: string (optional, preformatted text)
 *      - label: string (optional, shown before value)
 *      - value: string (optional)
 *      - icon: string (optional, trusted HTML for inline icon)
 *      - href: string (optional, wraps contents in a link)
 *      - emphasize: bool (optional, toggles bolder style)
 *      - class: string (optional, extra class for the item)
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

    $text = trim((string)($item['text'] ?? ''));
    $label = trim((string)($item['label'] ?? ''));
    $value = trim((string)($item['value'] ?? ''));

    return $text !== '' || $value !== '' || $label !== '';
}));

if ($items === []) {
    return;
}

$class = trim((string)($class ?? ''));
$rowClass = 'app-meta-row';
if ($class !== '') {
    $rowClass .= ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
}
?>
<div class="<?= $rowClass ?>">
  <?php foreach ($items as $index => $item): ?>
    <?php if ($index > 0): ?>
      <span class="app-meta-separator" aria-hidden="true">·</span>
    <?php endif; ?>
    <?php
      $text = trim((string)($item['text'] ?? ''));
      $label = trim((string)($item['label'] ?? ''));
      $value = trim((string)($item['value'] ?? ''));
      $icon = (string)($item['icon'] ?? '');
      $href = trim((string)($item['href'] ?? ''));
      $emphasize = !empty($item['emphasize']);
      $itemClass = trim((string)($item['class'] ?? ''));
      $itemClassAttr = $itemClass !== '' ? ' ' . htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') : '';

      $hasLink = $href !== '';

      $contentLabel = $label !== '' ? $label : '';
      $contentValue = $value !== '' ? $value : '';
      $contentText = $text !== '' ? $text : '';
    ?>
    <span class="app-meta-item<?= $itemClassAttr ?>">
      <?php if ($icon !== ''): ?>
        <span class="app-meta-icon"><?= $icon ?></span>
      <?php endif; ?>
      <?php if ($hasLink): ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="app-meta-link">
      <?php endif; ?>
        <?php if ($contentText !== ''): ?>
          <span class="<?= $emphasize ? 'app-meta-text is-strong' : 'app-meta-text'; ?>">
            <?= htmlspecialchars($contentText, ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php else: ?>
          <?php if ($contentLabel !== ''): ?>
            <span class="app-meta-label<?= $emphasize ? ' is-strong' : ''; ?>">
              <?= htmlspecialchars($contentLabel, ENT_QUOTES, 'UTF-8'); ?>
            </span>
          <?php endif; ?>
          <?php if ($contentValue !== ''): ?>
            <span class="app-meta-value<?= $emphasize ? ' is-strong' : ''; ?>">
              <?= htmlspecialchars($contentValue, ENT_QUOTES, 'UTF-8'); ?>
            </span>
          <?php endif; ?>
        <?php endif; ?>
      <?php if ($hasLink): ?>
        </a>
      <?php endif; ?>
    </span>
  <?php endforeach; ?>
</div>
