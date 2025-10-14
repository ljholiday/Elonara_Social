<?php
/**
 * Elonara Social RSVP Confirmation Email Template
 * RSVP confirmation email with inline CSS
 * Ported from PartyMinder WordPress plugin
 */

require_once dirname(__DIR__) . '/_helpers.php';

// Set subject for email (can be overridden by template variables)
if (!isset($subject)) {
	$subject = 'RSVP Confirmation: ' . $event_title;
}

// Create inline CSS for better email client compatibility
$styles = array(
	'container' => 'max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;',
	'header' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center;',
	'body' => 'background: #ffffff; padding: 30px 20px;',
	'event_card' => 'background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin: 24px 0;',
	'status_card' => 'text-align: center; padding: 20px; margin: 20px 0; border-radius: 8px;',
	'status_yes' => 'background: #d4edda; border: 1px solid #c3e6cb; color: #155724;',
	'status_maybe' => 'background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;',
	'status_no' => 'background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;',
	'btn_primary' => 'display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 12px 0;',
	'footer' => 'background: #f7fafc; color: #718096; padding: 20px; text-align: center; font-size: 12px;',
);

// Format event date/time
$event_day = date('l', strtotime($event_date ?? ''));
$event_date_formatted = date('F j, Y', strtotime($event_date ?? ''));
$event_time_formatted = date('g:i A', strtotime($event_date ?? ''));

// Status display logic
$status_class = 'status_maybe';
$status_text = 'Maybe';
$status_message = 'Thanks for letting us know you might attend!';

switch (strtolower($status)) {
	case 'yes':
	case 'attending':
		$status_class = 'status_yes';
		$status_text = 'Yes, I\'ll be there!';
		$status_message = 'Great! We\'re excited to see you at the event.';
		break;
	case 'no':
	case 'not_attending':
		$status_class = 'status_no';
		$status_text = 'Can\'t make it';
		$status_message = 'Thanks for letting us know. Maybe next time!';
		break;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($subject); ?></title>
</head>
<body style="margin: 0; padding: 20px; background-color: #f7fafc;">
	<div style="<?php echo $styles['container']; ?>">
		<!-- Header -->
		<div style="<?php echo $styles['header']; ?>">
			<h1 style="margin: 0; font-size: 24px;">RSVP Confirmed!</h1>
			<p style="margin: 10px 0 0 0; opacity: 0.9;">Your response has been recorded</p>
		</div>

		<!-- Body -->
		<div style="<?php echo $styles['body']; ?>">
			<!-- RSVP Status -->
			<div style="<?php echo $styles['status_card'] . ' ' . $styles[$status_class]; ?>">
				<h2 style="margin: 0 0 8px 0; font-size: 20px;"><?php echo $status_text; ?></h2>
				<p style="margin: 0; font-size: 16px;"><?php echo $status_message; ?></p>
			</div>

			<!-- Event Details -->
			<div style="<?php echo $styles['event_card']; ?>">
				<h2 style="margin: 0 0 15px 0; color: #2d3748; font-size: 20px;"><?php echo htmlspecialchars($event_title); ?></h2>

				<div style="margin-bottom: 12px;">
					<strong>When:</strong> <?php echo $event_day; ?>, <?php echo $event_date_formatted; ?> at <?php echo $event_time_formatted; ?>
				</div>

				<?php if (!empty($venue_info)) : ?>
				<div style="margin-bottom: 12px;">
					<strong>Where:</strong> <?php echo htmlspecialchars($venue_info); ?>
				</div>
				<?php endif; ?>

				<?php if (!empty($event_description)) : ?>
				<div style="margin-top: 16px;">
					<strong>About:</strong><br>
					<?php echo htmlspecialchars(vt_truncate_words($event_description, 25)); ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Action Buttons -->
			<div style="text-align: center; margin: 32px 0;">
				<?php if (strtolower($status) !== 'no') : ?>
					<p style="margin-bottom: 20px; color: #2d3748; font-size: 16px;">
						<?php if (strtolower($status) === 'yes') : ?>
							Looking forward to seeing you there!
						<?php else : ?>
							Still deciding? You can update your RSVP anytime.
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<div>
					<a href="<?php echo htmlspecialchars($event_url ?? $site_url . '/events/' . $event_id); ?>" style="<?php echo $styles['btn_primary']; ?>">
						View Event Details
					</a>
				</div>

				<p style="margin-top: 16px; font-size: 14px; color: #718096;">
					Need to change your RSVP? <a href="<?php echo htmlspecialchars($rsvp_url ?? $event_url); ?>" style="color: #667eea;">Click here to update</a>
				</p>
			</div>

			<?php if (strtolower($status) === 'yes') : ?>
			<!-- Attendee Tips -->
			<div style="<?php echo $styles['event_card']; ?>">
				<h3 style="margin: 0 0 16px 0; color: #2d3748; font-size: 18px;">Before the Event</h3>
				<ul style="margin: 0; padding-left: 20px; color: #4a5568;">
					<li style="margin-bottom: 8px;">Add this event to your calendar</li>
					<li style="margin-bottom: 8px;">Check the event page for any updates</li>
					<li style="margin-bottom: 8px;">Connect with other attendees in the event discussions</li>
				</ul>
			</div>
			<?php endif; ?>

			<!-- Footer Info -->
			<div style="text-align: center; margin-top: 32px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
				<p style="color: #718096; font-size: 14px; margin: 0;">
					This confirmation was sent from <strong><?php echo htmlspecialchars($site_name); ?></strong>.
				</p>
			</div>
		</div>

		<!-- Footer -->
		<div style="<?php echo $styles['footer']; ?>">
			<p style="margin: 0;">
				© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
			</p>
		</div>
	</div>
</body>
</html>
