<?php
/**
 * Elonara Social Welcome Email Template
 * Welcome email for new users with inline CSS
 * Ported from PartyMinder WordPress plugin
 */

// Set subject for email (can be overridden by template variables)
if (!isset($subject)) {
	$subject = 'Welcome to ' . $site_name;
}

// Create inline CSS for better email client compatibility
$styles = array(
	'container' => 'max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;',
	'header' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center;',
	'body' => 'background: #ffffff; padding: 40px 20px;',
	'card' => 'background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin: 24px 0;',
	'btn_primary' => 'display: inline-block; background: #667eea; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 12px 0;',
	'feature_grid' => 'display: flex; flex-wrap: wrap; gap: 20px; margin: 30px 0;',
	'feature_item' => 'flex: 1; min-width: 150px; text-align: center; padding: 20px; background: #f8f9ff; border-radius: 6px;',
	'footer' => 'background: #f7fafc; color: #718096; padding: 20px; text-align: center; font-size: 12px;',
);

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
			<h1 style="margin: 0; font-size: 28px;">Welcome to <?php echo htmlspecialchars($site_name); ?>!</h1>
			<p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 18px;">Let's get you started with amazing events</p>
		</div>

		<!-- Body -->
		<div style="<?php echo $styles['body']; ?>">
			<p style="font-size: 16px; margin-bottom: 20px;">
				Hi <?php echo htmlspecialchars($user_name); ?>!
			</p>

			<p style="font-size: 16px; margin-bottom: 24px;">
				Thanks for joining <?php echo htmlspecialchars($site_name); ?>! We're excited to help you create memorable events and connect with your community.
			</p>

			<!-- Get Started Card -->
			<div style="<?php echo $styles['card']; ?>">
				<h2 style="margin: 0 0 16px 0; color: #2d3748; font-size: 22px;">Get Started</h2>
				<p style="margin-bottom: 20px;">Here are some quick steps to make the most of your <?php echo htmlspecialchars($site_name); ?> experience:</p>

				<div style="text-align: center; margin: 24px 0;">
					<a href="<?php echo htmlspecialchars($site_url); ?>/profile?edit=1" style="<?php echo $styles['btn_primary']; ?>">
						Complete Your Profile
					</a>
				</div>
			</div>

			<!-- Features Overview -->
			<div style="<?php echo $styles['card']; ?>">
				<h2 style="margin: 0 0 20px 0; color: #2d3748; font-size: 22px; text-align: center;">What You Can Do</h2>

				<!-- Feature Items -->
				<div style="margin: 24px 0;">
					<div style="<?php echo $styles['feature_item']; ?> margin-bottom: 16px;">
						<h3 style="margin: 0 0 8px 0; color: #2d3748; font-size: 16px;">Host Events</h3>
						<p style="margin: 0; font-size: 14px; color: #718096;">Create and manage amazing parties with our easy-to-use tools</p>
					</div>

					<div style="<?php echo $styles['feature_item']; ?> margin-bottom: 16px;">
						<h3 style="margin: 0 0 8px 0; color: #2d3748; font-size: 16px;">Join Events</h3>
						<p style="margin: 0; font-size: 14px; color: #718096;">Discover local events and connect with your community</p>
					</div>

					<div style="<?php echo $styles['feature_item']; ?> margin-bottom: 16px;">
						<h3 style="margin: 0 0 8px 0; color: #2d3748; font-size: 16px;">Connect</h3>
						<p style="margin: 0; font-size: 14px; color: #718096;">Share tips, recipes, and stories with fellow party enthusiasts</p>
					</div>
				</div>
			</div>

			<!-- Call to Action -->
			<div style="text-align: center; margin: 32px 0;">
				<h3 style="color: #2d3748; margin-bottom: 16px;">Ready to get started?</h3>
				<div>
					<a href="<?php echo htmlspecialchars($site_url); ?>/dashboard" style="<?php echo $styles['btn_primary']; ?> margin-right: 12px;">
						Go to Dashboard
					</a>
					<a href="<?php echo htmlspecialchars($site_url); ?>/events" style="<?php echo $styles['btn_primary']; ?>">
						Browse Events
					</a>
				</div>
			</div>

			<!-- Help Section -->
			<div style="text-align: center; margin-top: 40px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
				<p style="color: #718096; font-size: 14px; margin: 0 0 12px 0;">
					Need help getting started? We're here for you!
				</p>
				<p style="margin: 0;">
					<a href="<?php echo htmlspecialchars($site_url); ?>/help" style="color: #667eea; font-size: 14px;">Visit our Help Center</a>
				</p>
			</div>
		</div>

		<!-- Footer -->
		<div style="<?php echo $styles['footer']; ?>">
			<p style="margin: 0 0 8px 0;">
				You're receiving this email because you signed up for <?php echo htmlspecialchars($site_name); ?>.
			</p>
			<p style="margin: 0;">
				© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
			</p>
		</div>
	</div>
</body>
</html>