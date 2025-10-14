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
$nonce = (string)($nonce ?? vt_service('security.service')->createNonce('guest_rsvp'));
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

function vt_field_value(array $source, string $key): string
{
    return isset($source[$key]) ? htmlspecialchars((string)$source[$key], ENT_QUOTES, 'UTF-8') : '';
}

?>

<?php if ($token === '' || empty($event) || empty($guest)): ?>
    <div class="vt-section vt-text-center">
        <h3 class="vt-heading vt-heading-md vt-text-primary vt-mb-4">RSVP invitation unavailable</h3>
        <p class="vt-text-muted vt-mb-4"><?= htmlspecialchars($error_message ?? 'This RSVP link may be invalid or expired.'); ?></p>
        <a href="/events" class="vt-btn">Browse events</a>
    </div>
    <?php return; ?>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
    <div class="vt-alert vt-alert-success vt-mb-4">
        <?= htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="vt-alert vt-alert-error vt-mb-4">
        <ul class="vt-list vt-list-unstyled vt-text-left">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars((string)$error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($statusNote) && empty($successMessage)): ?>
    <div class="vt-alert vt-alert-info vt-mb-4">
        <?= htmlspecialchars($statusNote); ?>
    </div>
<?php endif; ?>

<div class="vt-section vt-mb-6">
    <div class="vt-card">
        <?php if ($featuredImage !== ''): ?>
            <div class="vt-card-image">
                <img src="<?= htmlspecialchars($featuredImage); ?>" alt="<?= htmlspecialchars((string)($event['title'] ?? 'Event')); ?>" class="vt-card-image-img">
            </div>
        <?php endif; ?>

        <div class="vt-card-header">
            <h1 class="vt-heading vt-heading-lg vt-text-primary"><?= htmlspecialchars((string)($event['title'] ?? 'Event Invitation')); ?></h1>
        </div>

        <div class="vt-card-body">
            <div class="vt-event-meta vt-mb-4">
                <?php if ($eventDate): ?>
                    <div class="vt-flex vt-items-center vt-gap-2 vt-mb-2">
                        <strong><?= htmlspecialchars(date_fmt($eventDate, 'l, F j, Y')); ?></strong>
                        <?php if ($eventTime): ?>
                            <span><?= htmlspecialchars($eventTime); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($venueInfo): ?>
                    <div class="vt-flex vt-items-center vt-gap-2 vt-mb-2">
                        <span><?= htmlspecialchars($venueInfo); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($description): ?>
                <div class="vt-event-description vt-text-muted">
                    <?= nl2br(htmlspecialchars($description)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="vt-section">
    <div class="vt-section-header">
        <h2 class="vt-heading vt-heading-md vt-text-primary">How should we count you?</h2>
        <p class="vt-text-muted">Use the form below to update your RSVP. You can revisit this link any time to make changes.</p>
    </div>

    <form method="post" class="vt-form vt-stack-md">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
        <input type="hidden" name="nonce" value="<?= htmlspecialchars($nonce); ?>">

        <div class="vt-form-group">
            <label class="vt-form-label">RSVP</label>
            <div class="vt-flex vt-flex-wrap vt-gap-3">
                <label>
                    <input type="radio" name="rsvp_status" value="yes" <?= $selectedStatus === 'yes' ? 'checked' : ''; ?> required>
                    <span class="vt-btn vt-btn-lg vt-btn-primary">Yes, I'll be there</span>
                </label>
                <label>
                    <input type="radio" name="rsvp_status" value="maybe" <?= $selectedStatus === 'maybe' ? 'checked' : ''; ?> required>
                    <span class="vt-btn vt-btn-lg vt-btn-secondary">Maybe</span>
                </label>
                <label>
                    <input type="radio" name="rsvp_status" value="no" <?= $selectedStatus === 'no' ? 'checked' : ''; ?> required>
                    <span class="vt-btn vt-btn-lg vt-btn-danger">Can't make it</span>
                </label>
            </div>
        </div>

        <div class="vt-rsvp-details" data-rsvp-details>
            <div class="vt-form-group">
                <label class="vt-form-label" for="guest_name">
                    Your name <?= $selectedStatus === 'no' ? '' : '<span class="vt-required">*</span>'; ?>
                </label>
                <input
                    type="text"
                    id="guest_name"
                    name="guest_name"
                    class="vt-form-input"
                    value="<?= vt_field_value($formValues, 'guest_name'); ?>"
                    <?= $selectedStatus === 'no' ? '' : 'required'; ?>
                >
            </div>

            <div class="vt-form-group">
                <label class="vt-form-label" for="guest_phone">Phone (optional)</label>
                <input
                    type="tel"
                    id="guest_phone"
                    name="guest_phone"
                    class="vt-form-input"
                    value="<?= vt_field_value($formValues, 'guest_phone'); ?>"
                    placeholder="(555) 123-4567"
                >
            </div>

            <?php if ($isBluesky): ?>
                <p class="vt-text-xs vt-text-muted vt-mb-4">Invited via Bluesky (<?= htmlspecialchars((string)$guest['email']); ?>)</p>
            <?php endif; ?>

            <?php if ($allowPlusOnes): ?>
                <div class="vt-form-group">
                    <label class="vt-form-label">Plus One</label>
                    <div class="vt-flex vt-gap-4 vt-flex-wrap">
                        <label class="vt-flex vt-gap-2 vt-items-center">
                            <input type="radio" name="plus_one" value="0" <?= ((int)($formValues['plus_one'] ?? 0) === 0) ? 'checked' : ''; ?>>
                            <span>Just me</span>
                        </label>
                        <label class="vt-flex vt-gap-2 vt-items-center">
                            <input type="radio" name="plus_one" value="1" <?= ((int)($formValues['plus_one'] ?? 0) === 1) ? 'checked' : ''; ?>>
                            <span>I'm bringing someone</span>
                        </label>
                    </div>
                </div>

                <div class="vt-form-group vt-hidden" data-plus-one-name>
                    <label class="vt-form-label" for="plus_one_name">Guest name</label>
                    <input
                        type="text"
                        id="plus_one_name"
                        name="plus_one_name"
                        class="vt-form-input"
                        value="<?= vt_field_value($formValues, 'plus_one_name'); ?>"
                        placeholder="Guest name"
                    >
                </div>
            <?php endif; ?>

            <div class="vt-form-group">
                <label class="vt-form-label" for="dietary_restrictions">Dietary preferences</label>
                <input
                    type="text"
                    id="dietary_restrictions"
                    name="dietary_restrictions"
                    class="vt-form-input"
                    value="<?= vt_field_value($formValues, 'dietary_restrictions'); ?>"
                    placeholder="Let the host know about allergies or preferences"
                >
            </div>

            <div class="vt-form-group">
                <label class="vt-form-label" for="guest_notes">Notes for the host</label>
                <textarea
                    id="guest_notes"
                    name="guest_notes"
                    class="vt-form-textarea"
                    rows="4"
                    placeholder="Message the host (optional)"
                ><?= vt_field_value($formValues, 'guest_notes'); ?></textarea>
            </div>
        </div>

        <div class="vt-form-actions">
            <button type="submit" class="vt-btn vt-btn-primary vt-btn-lg">Save RSVP</button>
            <a href="/events" class="vt-btn vt-btn-link">Browse other events</a>
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
            detailsSection?.classList.add('vt-hidden');
            nameInput?.removeAttribute('required');
        } else {
            detailsSection?.classList.remove('vt-hidden');
            if (nameInput) {
                nameInput.setAttribute('required', 'required');
            }
        }
    }

    function togglePlusOne() {
        const selected = document.querySelector('input[name="plus_one"]:checked');
        plusOneContainers.forEach(function (container) {
            if (selected && selected.value === '1') {
                container.classList.remove('vt-hidden');
            } else {
                container.classList.add('vt-hidden');
            }
        });
    }

    statusRadios.forEach(radio => radio.addEventListener('change', toggleDetails));
    plusOneRadios.forEach(radio => radio.addEventListener('change', togglePlusOne));

    toggleDetails();
    togglePlusOne();
})();
</script>
