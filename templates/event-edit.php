<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'description' => '', 'event_date' => ''];
$event = $event ?? null;
$recurrenceType = strtolower((string)($input['recurrence_type'] ?? 'none'));
if (!in_array($recurrenceType, ['none', 'daily', 'weekly', 'monthly'], true)) {
  $recurrenceType = 'none';
}
$recurrenceIntervalValue = (string)($input['recurrence_interval'] ?? '1');
$recurrenceIntervalValue = $recurrenceIntervalValue !== '' ? $recurrenceIntervalValue : '1';
$recurrenceDays = $input['recurrence_days'] ?? [];
if (!is_array($recurrenceDays)) {
  $recurrenceDays = [];
}
$monthlyType = strtolower((string)($input['monthly_type'] ?? 'date'));
if (!in_array($monthlyType, ['date', 'weekday'], true)) {
  $monthlyType = 'date';
}
$monthlyDayNumber = (string)($input['monthly_day_number'] ?? '');
$monthlyWeek = strtolower((string)($input['monthly_week'] ?? ''));
$monthlyWeekday = strtolower((string)($input['monthly_weekday'] ?? ''));
$weekdayShortLabels = [
  'mon' => 'Mon',
  'tue' => 'Tue',
  'wed' => 'Wed',
  'thu' => 'Thu',
  'fri' => 'Fri',
  'sat' => 'Sat',
  'sun' => 'Sun',
];
$weekdayLongLabels = [
  'mon' => 'Monday',
  'tue' => 'Tuesday',
  'wed' => 'Wednesday',
  'thu' => 'Thursday',
  'fri' => 'Friday',
  'sat' => 'Saturday',
  'sun' => 'Sunday',
];
$recurrenceDays = array_map(static fn ($day) => strtolower((string)$day), $recurrenceDays);
$recurrenceDays = array_values(array_filter(
  $recurrenceDays,
  static fn ($day) => array_key_exists($day, $weekdayShortLabels)
));
$monthlyWeekLabels = [
  'first' => 'First',
  'second' => 'Second',
  'third' => 'Third',
  'fourth' => 'Fourth',
  'last' => 'Last',
];
if (!array_key_exists($monthlyWeek, $monthlyWeekLabels)) {
  $monthlyWeek = '';
}
if (!array_key_exists($monthlyWeekday, $weekdayLongLabels)) {
  $monthlyWeekday = '';
}
$recurrenceOptions = [
  'none' => 'Does not repeat',
  'daily' => 'Daily',
  'weekly' => 'Weekly',
  'monthly' => 'Monthly',
];
$recurrenceIntervalSuffix = match ($recurrenceType) {
  'daily' => 'day(s)',
  'weekly' => 'week(s)',
  'monthly' => 'month(s)',
  default => 'day(s)',
};
$showRecurrenceInterval = $recurrenceType !== 'none' || isset($errors['recurrence_interval']) || isset($errors['recurrence_days']) || isset($errors['monthly_day_number']) || isset($errors['monthly_week']) || isset($errors['monthly_weekday']);
$showRecurrenceWeekly = $recurrenceType === 'weekly' || isset($errors['recurrence_days']);
$showRecurrenceMonthly = $recurrenceType === 'monthly' || isset($errors['monthly_day_number']) || isset($errors['monthly_week']) || isset($errors['monthly_weekday']);
$showMonthlyDate = ($recurrenceType === 'monthly' && $monthlyType === 'date') || isset($errors['monthly_day_number']);
$showMonthlyWeekday = ($recurrenceType === 'monthly' && $monthlyType === 'weekday') || isset($errors['monthly_week']) || isset($errors['monthly_weekday']);
?>
<section class="app-section app-event-edit">
  <?php if (!$event): ?>
    <h1 class="app-heading">Event not found</h1>
    <p class="app-text-muted">We couldn’t find that event.</p>
  <?php else: ?>
    <h1 class="app-heading">Edit Event</h1>
    <p class="app-text-muted">Editing <strong><?= e($event['title'] ?? '') ?></strong></p>

    <?php if ($errors): ?>
      <div class="app-alert app-alert-error app-mb-4">
        <p>Please fix the issues below:</p>
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/edit" class="app-form app-stack" enctype="multipart/form-data">
      <div class="app-field">
        <label class="app-label" for="title">Title</label>
        <input
          class="app-input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
          type="text"
          id="title"
          name="title"
          value="<?= e($input['title'] ?? '') ?>"
          required
        >
      </div>

      <div class="app-field">
        <label class="app-label" for="event_date">Start Date &amp; Time</label>
        <input
          class="app-input<?= isset($errors['event_date']) ? ' is-invalid' : '' ?>"
          type="datetime-local"
          id="event_date"
          name="event_date"
          value="<?= e($input['event_date'] ?? '') ?>"
        >
        <p class="app-field-help">Leave blank for TBD. Default time is 6:00 PM.</p>
      </div>

      <div class="app-field">
        <label class="app-label" for="end_date">End Date &amp; Time</label>
        <input
          class="app-input<?= isset($errors['end_date']) ? ' is-invalid' : '' ?>"
          type="datetime-local"
          id="end_date"
          name="end_date"
          value="<?= e($input['end_date'] ?? '') ?>"
        >
        <p class="app-field-help">Optional. Leave blank for single-day event.</p>
      </div>

      <div class="app-field">
        <label class="app-label" for="recurrence_type">Repeats</label>
        <select
          class="app-input"
          id="recurrence_type"
          name="recurrence_type"
        >
          <?php foreach ($recurrenceOptions as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $recurrenceType === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="app-field-help">Choose how often this event repeats.</p>
      </div>

      <div class="app-field app-recurrence-section" data-recurrence-section="interval"<?= $showRecurrenceInterval ? '' : ' style="display:none;"' ?>>
        <label class="app-label" for="recurrence_interval">Repeat every</label>
        <div class="app-recurrence-interval">
          <input
            class="app-input app-recurrence-interval-input<?= isset($errors['recurrence_interval']) ? ' is-invalid' : '' ?>"
            type="number"
            min="1"
            max="30"
            id="recurrence_interval"
            name="recurrence_interval"
            value="<?= e($recurrenceIntervalValue) ?>"
          >
          <span class="app-text-muted" id="recurrence_interval_suffix"><?= e($recurrenceIntervalSuffix) ?></span>
        </div>
        <p class="app-field-help">Common choices are every 1, 2, or 4 intervals.</p>
        <?php if (isset($errors['recurrence_interval'])): ?>
          <div class="app-field-error"><?= e($errors['recurrence_interval']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field app-recurrence-section" data-recurrence-section="weekly"<?= $showRecurrenceWeekly ? '' : ' style="display:none;"' ?>>
        <span class="app-label">Repeat on</span>
        <div class="app-recurrence-weekdays">
          <?php foreach ($weekdayShortLabels as $dayKey => $dayLabel): ?>
            <label class="app-checkbox app-recurrence-weekday">
              <input
                type="checkbox"
                name="recurrence_days[]"
                value="<?= e($dayKey) ?>"
                <?= in_array($dayKey, $recurrenceDays, true) ? ' checked' : '' ?>
              >
              <span><?= e($dayLabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($errors['recurrence_days'])): ?>
          <div class="app-field-error"><?= e($errors['recurrence_days']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field app-recurrence-section" data-recurrence-section="monthly"<?= $showRecurrenceMonthly ? '' : ' style="display:none;"' ?>>
        <label class="app-label">Monthly pattern</label>
        <div class="app-radio-group app-recurrence-monthly-type">
          <label class="app-radio-label">
            <input type="radio" name="monthly_type" value="date"<?= $monthlyType === 'date' ? ' checked' : '' ?>>
            <span>On day</span>
          </label>
          <label class="app-radio-label">
            <input type="radio" name="monthly_type" value="weekday"<?= $monthlyType === 'weekday' ? ' checked' : '' ?>>
            <span>On the</span>
          </label>
        </div>
        <div class="app-recurrence-monthly-mode" data-monthly-mode="date"<?= $showMonthlyDate ? '' : ' style="display:none;"' ?>>
          <label class="app-label" for="monthly_day_number">Day of month</label>
          <input
            class="app-input<?= isset($errors['monthly_day_number']) ? ' is-invalid' : '' ?>"
            type="number"
            min="1"
            max="31"
            id="monthly_day_number"
            name="monthly_day_number"
            value="<?= e($monthlyDayNumber) ?>"
          >
          <?php if (isset($errors['monthly_day_number'])): ?>
            <div class="app-field-error"><?= e($errors['monthly_day_number']) ?></div>
          <?php endif; ?>
        </div>
        <div class="app-recurrence-monthly-mode" data-monthly-mode="weekday"<?= $showMonthlyWeekday ? '' : ' style="display:none;"' ?>>
          <div class="app-recurrence-monthly-grid">
            <div class="app-recurrence-monthly-cell">
              <label class="app-label" for="monthly_week">Week</label>
              <select
                class="app-input<?= isset($errors['monthly_week']) ? ' is-invalid' : '' ?>"
                id="monthly_week"
                name="monthly_week"
              >
                <option value="">Select week</option>
                <?php foreach ($monthlyWeekLabels as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $monthlyWeek === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['monthly_week'])): ?>
                <div class="app-field-error"><?= e($errors['monthly_week']) ?></div>
              <?php endif; ?>
            </div>
            <div class="app-recurrence-monthly-cell">
              <label class="app-label" for="monthly_weekday">Weekday</label>
              <select
                class="app-input<?= isset($errors['monthly_weekday']) ? ' is-invalid' : '' ?>"
                id="monthly_weekday"
                name="monthly_weekday"
              >
                <option value="">Select day</option>
                <?php foreach ($weekdayLongLabels as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $monthlyWeekday === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['monthly_weekday'])): ?>
                <div class="app-field-error"><?= e($errors['monthly_weekday']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="app-field">
        <label class="app-label" for="location">Location</label>
        <input
          class="app-input<?= isset($errors['location']) ? ' is-invalid' : '' ?>"
          type="text"
          id="location"
          name="location"
          value="<?= e($input['location'] ?? '') ?>"
          placeholder="Enter event location"
        >
        <p class="app-field-help">Optional. e.g., "Central Park" or "123 Main St, City"</p>
      </div>

      <div class="app-field">
        <label class="app-label" for="description">Description</label>
        <textarea
          class="app-textarea"
          id="description"
          name="description"
          rows="5"
        ><?= e($input['description'] ?? '') ?></textarea>
      </div>

      <div class="app-field">
        <label class="app-label">Featured Image</label>
        <div class="app-mb-3" id="featured-image-preview-container">
          <?php if (!empty($event['featured_image'])): ?>
            <?php
              $featuredUrl = getImageUrl($event['featured_image'], 'mobile', 'original');
              if ($featuredUrl):
            ?>
              <img src="<?= e($featuredUrl) ?>" alt="<?= e($event['featured_image_alt'] ?? 'Current featured image') ?>" class="app-img" style="max-width: 400px;" id="featured-image-preview">
              <div class="app-text-muted app-mt-1">Current featured image</div>
            <?php else: ?>
              <img src="" alt="Featured image preview" class="app-img" style="max-width: 400px; display: none;" id="featured-image-preview">
            <?php endif; ?>
          <?php else: ?>
            <img src="" alt="Featured image preview" class="app-img" style="max-width: 400px; display: none;" id="featured-image-preview">
          <?php endif; ?>
        </div>
        <button type="button" class="app-btn app-btn-primary" onclick="window.appOpenImageLibrary({ imageType: 'featured', targetPreview: 'featured-image-preview', targetAltInput: 'featured-image-alt', targetUrlInput: 'featured-image-url' })">
          Select Image
        </button>
        <input type="hidden" id="featured-image-alt" name="featured_image_alt" value="<?= e($input['featured_image_alt'] ?? '') ?>">
        <input type="hidden" id="featured-image-url" name="featured_image_url_uploaded" value="">
        <small class="app-help-text" style="display: block; margin-top: 0.5rem;">Click to upload a new image or choose from your library. Recommended size: 1200x630px.</small>
        <?php if (isset($errors['featured_image'])): ?>
          <div class="app-field-error"><?= e($errors['featured_image']) ?></div>
        <?php endif; ?>
        <?php if (isset($errors['featured_image_alt'])): ?>
          <div class="app-field-error"><?= e($errors['featured_image_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-actions">
        <button type="submit" class="app-btn app-btn-primary">Save Changes</button>
        <a class="app-btn" href="/events/<?= e($event['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="app-danger-zone app-mt-6">
      <h2 class="app-heading-sm">Danger Zone</h2>
      <p class="app-text-muted">Deleting an event cannot be undone.</p>
      <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/delete" class="app-inline-form" onsubmit="return confirm('Delete this event?');">
        <button type="submit" class="app-btn app-btn-danger">Delete Event</button>
      </form>
    </div>

  <?php endif; ?>
</section>

<?php if ($event): ?>
<?php
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>
<script src="<?= htmlspecialchars($assetBase . '/js/event-form.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
