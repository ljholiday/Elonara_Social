<?php
declare(strict_types=1);

/** @var array<string,mixed> $event */
/** @var array<string,mixed> $guest */
/** @var array<string,mixed> $form_values */

$event = $event ?? [];
$guest = $guest ?? [];
$formValues = $form_values ?? [];
$errors = $errors ?? [];
$successMessage = $success_message ?? '';
$preselect = strtolower((string)($preselect ?? ''));
$token = (string)($token ?? '');
$nonce = (string)($nonce ?? app_service('security.service')->createNonce('guest_rsvp'));
$isBluesky = (bool)($is_bluesky ?? false);

$selectedStatus = $preselect;
if ($selectedStatus === '' && isset($guest['status'])) {
    $status = strtolower((string)$guest['status']);
    $selectedStatus = match ($status) {
        'confirmed' => 'yes',
        'declined' => 'no',
        'maybe' => 'maybe',
        default => '',
    };
}

$allowPlusOnes = (bool)($event['allow_plus_ones'] ?? true);

$eventDate = $event['event_date'] ?? '';
$eventTime = $event['event_time'] ?? '';
$venueInfo = $event['venue_info'] ?? '';
$description = $event['description'] ?? '';
$featuredImage = $event['featured_image'] ?? '';

$currentStatus = strtolower((string)($guest['status'] ?? 'pending'));
$statusNote = '';
if (in_array($currentStatus, ['confirmed', 'declined', 'maybe'], true)) {
    $statusNote = match ($currentStatus) {
        'confirmed' => 'You\'re currently marked as attending.',
        'declined' => 'You\'ve let the host know you can\'t attend. You can update your response below.',
        'maybe' => 'You\'re currently marked as a “maybe”. Feel free to update your RSVP.',
        default => '',
    };
}

if ($selectedStatus === '' && $currentStatus !== 'pending') {
    $selectedStatus = match ($currentStatus) {
        'confirmed' => 'yes',
        'declined' => 'no',
        'maybe' => 'maybe',
        default => '',
    };
}

function app_field_value(array $source, string $key): string
{
    return isset($source[$key]) ? htmlspecialchars((string)$source[$key], ENT_QUOTES, 'UTF-8') : '';
}

?>

<?php if ($token === '' || empty($event) || empty($guest)): ?>
    <div class="app-section app-text-center">
        <h3 class="app-heading app-heading-md app-text-primary app-mb-4">RSVP invitation unavailable</h3>
        <p class="app-text-muted app-mb-4"><?= htmlspecialchars($error_message ?? 'This RSVP link may be invalid or expired.'); ?></p>
        <a href="/events" class="app-btn">Browse events</a>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
    <div class="app-alert app-alert-success app-mb-4">
        <?= htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="app-alert app-alert-error app-mb-4">
        <ul class="app-list app-list-unstyled app-text-left">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars((string)$error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($statusNote) && empty($successMessage)): ?>
    <div class="app-alert app-alert-info app-mb-4">
        <?= htmlspecialchars($statusNote); ?>
    </div>
<?php endif; ?>

<div class="app-section app-mb-6">
    <div class="app-card">
        <?php if ($featuredImage !== ''): ?>
            <div class="app-card-image">
                <img src="<?= htmlspecialchars($featuredImage); ?>" alt="<?= htmlspecialchars((string)($event['title'] ?? 'Event')); ?>" class="app-card-image-img">
            </div>
        <?php endif; ?>

        <div class="app-card-header">
            <h1 class="app-heading app-heading-lg app-text-primary"><?= htmlspecialchars((string)($event['title'] ?? 'Event Invitation')); ?></h1>
        </div>

        <div class="app-card-body">
            <div class="app-event-meta app-mb-4">
                <?php if ($eventDate): ?>
                    <div class="app-flex app-items-center app-gap-2 app-mb-2">
                        <strong><?= htmlspecialchars(date_fmt($eventDate, 'l, F j, Y')); ?></strong>
                        <?php if ($eventTime): ?>
                            <span><?= htmlspecialchars($eventTime); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($venueInfo): ?>
                    <div class="app-flex app-items-center app-gap-2 app-mb-2">
                        <span><?= htmlspecialchars($venueInfo); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($description): ?>
                <div class="app-event-description app-text-muted">
                    <?= nl2br(htmlspecialchars($description)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="app-section">
    <div class="app-section-header">
        <h2 class="app-heading app-heading-md app-text-primary">How should we count you?</h2>
        <p class="app-text-muted">Use the form below to update your RSVP. You can revisit this link any time to make changes.</p>
    </div>

    <form method="post" class="app-form app-stack-md">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
        <input type="hidden" name="nonce" value="<?= htmlspecialchars($nonce); ?>">

        <div class="app-form-group">
            <label class="app-form-label">RSVP</label>
            <div class="app-flex app-flex-wrap app-gap-3">
                <label>
                    <input type="radio" name="rsvp_status" value="yes" <?= $selectedStatus === 'yes' ? 'checked' : ''; ?> required>
                    <span class="app-btn app-btn-lg app-btn-primary">Yes, I'll be there</span>
                </label>
                <label>
                    <input type="radio" name="rsvp_status" value="maybe" <?= $selectedStatus === 'maybe' ? 'checked' : ''; ?> required>
                    <span class="app-btn app-btn-lg app-btn-secondary">Maybe</span>
                </label>
                <label>
                    <input type="radio" name="rsvp_status" value="no" <?= $selectedStatus === 'no' ? 'checked' : ''; ?> required>
                    <span class="app-btn app-btn-lg app-btn-danger">Can't make it</span>
                </label>
            </div>
        </div>

        <div class="app-rsvp-details" data-rsvp-details>
            <div class="app-form-group">
                <label class="app-form-label" for="guest_name">
                    Your name <?= $selectedStatus === 'no' ? '' : '<span class="app-required">*</span>'; ?>
                </label>
                <input
                    type="text"
                    id="guest_name"
                    name="guest_name"
                    class="app-form-input"
                    value="<?= app_field_value($formValues, 'guest_name'); ?>"
                    <?= $selectedStatus === 'no' ? '' : 'required'; ?>
                >
            </div>

            <div class="app-form-group">
                <label class="app-form-label" for="guest_phone">Phone (optional)</label>
                <input
                    type="tel"
                    id="guest_phone"
                    name="guest_phone"
                    class="app-form-input"
                    value="<?= app_field_value($formValues, 'guest_phone'); ?>"
                    placeholder="(555) 123-4567"
                >
            </div>

            <?php if ($isBluesky): ?>
                <p class="app-text-xs app-text-muted app-mb-4">Invited via Bluesky (<?= htmlspecialchars((string)$guest['email']); ?>)</p>
            <?php endif; ?>

            <?php if ($allowPlusOnes): ?>
                <div class="app-form-group">
                    <label class="app-form-label">Plus One</label>
                    <div class="app-flex app-gap-4 app-flex-wrap">
                        <label class="app-flex app-gap-2 app-items-center">
                            <input type="radio" name="plus_one" value="0" <?= ((int)($formValues['plus_one'] ?? 0) === 0) ? 'checked' : ''; ?>>
                            <span>Just me</span>
                        </label>
                        <label class="app-flex app-gap-2 app-items-center">
                            <input type="radio" name="plus_one" value="1" <?= ((int)($formValues['plus_one'] ?? 0) === 1) ? 'checked' : ''; ?>>
                            <span>I'm bringing someone</span>
                        </label>
                    </div>
                </div>

                <div class="app-form-group app-hidden" data-plus-one-name>
                    <label class="app-form-label" for="plus_one_name">Guest name</label>
                    <input
                        type="text"
                        id="plus_one_name"
                        name="plus_one_name"
                        class="app-form-input"
                        value="<?= app_field_value($formValues, 'plus_one_name'); ?>"
                        placeholder="Guest name"
                    >
                </div>
            <?php endif; ?>

            <div class="app-form-group">
                <label class="app-form-label" for="dietary_restrictions">Dietary preferences</label>
                <input
                    type="text"
                    id="dietary_restrictions"
                    name="dietary_restrictions"
                    class="app-form-input"
                    value="<?= app_field_value($formValues, 'dietary_restrictions'); ?>"
                    placeholder="Let the host know about allergies or preferences"
                >
            </div>

            <div class="app-form-group">
                <label class="app-form-label" for="guest_notes">Notes for the host</label>
                <textarea
                    id="guest_notes"
                    name="guest_notes"
                    class="app-form-textarea"
                    rows="4"
                    placeholder="Message the host (optional)"
                ><?= app_field_value($formValues, 'guest_notes'); ?></textarea>
            </div>
        </div>

        <div class="app-form-actions">
            <button type="submit" class="app-btn app-btn-primary app-btn-lg">Save RSVP</button>
            <a href="/events" class="app-btn app-btn-link">Browse other events</a>
        </div>
    </form>
</div>

<script>
(function () {
    const statusRadios = document.querySelectorAll('input[name="rsvp_status"]');
    const detailsSection = document.querySelector('[data-rsvp-details]');
    const plusOneRadios = document.querySelectorAll('input[name="plus_one"]');
    const plusOneContainers = document.querySelectorAll('[data-plus-one-name]');
    const nameInput = document.getElementById('guest_name');

    function toggleDetails() {
        const selected = document.querySelector('input[name="rsvp_status"]:checked');
        if (!selected || selected.value === 'no') {
            detailsSection?.classList.add('app-hidden');
            nameInput?.removeAttribute('required');
        } else {
            detailsSection?.classList.remove('app-hidden');
            if (nameInput) {
                nameInput.setAttribute('required', 'required');
            }
        }
    }

    function togglePlusOne() {
        const selected = document.querySelector('input[name="plus_one"]:checked');
        plusOneContainers.forEach(function (container) {
            if (selected && selected.value === '1') {
                container.classList.remove('app-hidden');
            } else {
                container.classList.add('app-hidden');
            }
        });
    }

    statusRadios.forEach(radio => radio.addEventListener('change', toggleDetails));
    plusOneRadios.forEach(radio => radio.addEventListener('change', togglePlusOne));

    toggleDetails();
    togglePlusOne();
})();
</script>
