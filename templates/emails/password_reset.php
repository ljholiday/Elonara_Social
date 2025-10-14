<?php
/**
 * Password Reset Email Template
 * Sent when user requests password reset
 */

if (!isset($subject)) {
	$subject = 'Reset Your Password';
}

$styles = array(
	'container' => 'max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333;',
	'header' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center;',
	'body' => 'background: #ffffff; padding: 40px 20px;',
	'card' => 'background: #f8f9ff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin: 24px 0;',
	'btn_primary' => 'display: inline-block; background: #667eea; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 12px 0;',
	'footer' => 'background: #f7fafc; color: #718096; padding: 20px; text-align: center; font-size: 12px;',
	'warning' => 'background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 16px; margin: 20px 0; color: #856404;',
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
			<h1 style="margin: 0; font-size: 28px;">Reset Your Password</h1>
			<p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;"><?php echo htmlspecialchars($site_name); ?></p>
		</div>

		<!-- Body -->
		<div style="<?php echo $styles['body']; ?>">
			<p style="font-size: 16px; margin-bottom: 20px;">
				Hello,
			</p>

			<p style="font-size: 16px; margin-bottom: 24px;">
				We received a request to reset your password for your <?php echo htmlspecialchars($site_name); ?> account.
			</p>

			<!-- Reset Button Card -->
			<div style="<?php echo $styles['card']; ?>">
				<p style="margin: 0 0 20px 0; font-size: 16px;">
					Click the button below to reset your password. This link will expire in 1 hour.
				</p>

				<div style="text-align: center; margin: 24px 0;">
					<a href="<?php echo htmlspecialchars($reset_url); ?>" style="<?php echo $styles['btn_primary']; ?>">
						Reset Password
					</a>
				</div>

				<p style="margin: 20px 0 0 0; font-size: 14px; color: #718096;">
					Or copy and paste this URL into your browser:
				</p>
				<p style="margin: 8px 0 0 0; font-size: 12px; word-break: break-all; color: #667eea;">
					<?php echo htmlspecialchars($reset_url); ?>
				</p>
			</div>

			<!-- Security Warning -->
			<div style="<?php echo $styles['warning']; ?>">
				<p style="margin: 0 0 8px 0; font-weight: bold; font-size: 14px;">⚠️ Security Notice</p>
				<p style="margin: 0; font-size: 14px;">
					If you didn't request a password reset, please ignore this email. Your password will remain unchanged.
				</p>
			</div>

			<!-- Help Section -->
			<div style="text-align: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
				<p style="color: #718096; font-size: 14px; margin: 0;">
					Need help? Contact us at support@social.elonara.com
				</p>
			</div>
		</div>

		<!-- Footer -->
		<div style="<?php echo $styles['footer']; ?>">
			<p style="margin: 0 0 8px 0;">
				This is an automated message from <?php echo htmlspecialchars($site_name); ?>.
			</p>
			<p style="margin: 0;">
				© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
			</p>
		</div>
	</div>
</body>
</html>
