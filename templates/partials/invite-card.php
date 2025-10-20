<?php
/**
 * Standard invitation-style card.
 *
 * Expected structure:
 * $card = [
 *   'title' => string,
 *   'title_url' => string|null,
 *   'subtitle' => string|null,
 *   'meta' => string|null,
 *   'body_html' => string|null, // already sanitized HTML
 *   'badges' => array<int,array{label:string,class?:string}>,
 *   'actions' => array<int,string>, // pre-rendered HTML (sanitized)
 *   'attributes' => array<string,string|int>,
 *   'class' => string|null,
 * ];
 */

$card = $card ?? [];
$badges = is_array($card['badges'] ?? null) ? $card['badges'] : [];
$actions = is_array($card['actions'] ?? null) ? $card['actions'] : [];
$attributes = is_array($card['attributes'] ?? null) ? $card['attributes'] : [];
$extraClass = trim((string)($card['class'] ?? ''));

$title = (string)($card['title'] ?? '');
$titleUrl = isset($card['title_url']) ? (string)$card['title_url'] : null;
$subtitle = (string)($card['subtitle'] ?? '');
$meta = (string)($card['meta'] ?? '');
$bodyHtml = (string)($card['body_html'] ?? '');

$attrString = '';
foreach ($attributes as $attrName => $value) {
    if ($value === null) {
        continue;
    }
    $attrString .= ' ' . htmlspecialchars((string)$attrName, ENT_QUOTES, 'UTF-8') .
        '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
}
?>
<div class="app-invitation-item<?= $extraClass !== '' ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : '' ?>"<?= $attrString ?>>
  <div class="app-invitation-main">
    <?php if ($badges !== []): ?>
      <div class="app-invitation-badges">
        <?php foreach ($badges as $badge): ?>
          <?php
            $badgeLabel = isset($badge['label']) ? (string)$badge['label'] : '';
            if ($badgeLabel === '') {
                continue;
            }
            $badgeClass = isset($badge['class']) ? (string)$badge['class'] : 'app-badge-secondary';
          ?>
          <span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="app-invitation-details">
      <?php if ($title !== ''): ?>
        <strong>
          <?php if ($titleUrl !== null): ?>
            <a href="<?= htmlspecialchars($titleUrl, ENT_QUOTES, 'UTF-8'); ?>" class="app-text-primary">
              <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
          <?php endif; ?>
        </strong>
      <?php endif; ?>

      <?php if ($subtitle !== ''): ?>
        <div class="app-text-muted app-text-sm">
          <?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <?php if ($meta !== ''): ?>
        <small class="app-text-muted">
          <?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?>
        </small>
      <?php endif; ?>

      <?php if ($bodyHtml !== ''): ?>
        <?= $bodyHtml ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($actions !== []): ?>
    <div class="app-invitation-actions">
      <?php foreach ($actions as $actionHtml): ?>
        <?= $actionHtml ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
