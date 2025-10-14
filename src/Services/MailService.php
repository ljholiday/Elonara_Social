<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class MailService
{
    private string $fromEmail;
    private string $fromName;

    public function __construct(
        private PHPMailer $mailer,
        string $fromEmail = 'noreply@social.elonara.com',
        string $fromName = 'Elonara Social'
    ) {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * @param string|array<string> $to
     */
    public function send($to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->setFrom($this->fromEmail, $this->fromName);

            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->mailer->addAddress($recipient);
                }
            }

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            $this->logError('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string,mixed> $variables
     */
    public function sendTemplate(string $to, string $template, array $variables = []): bool
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/emails/' . $template . '.php';

        if (!is_file($templatePath)) {
            $this->logError('Email template not found: ' . $template);
            return false;
        }

        extract($variables);

        ob_start();
        include $templatePath;
        $htmlBody = (string)ob_get_clean();

        $subject = $variables['subject'] ?? 'Message from Elonara Social';

        return $this->send($to, $subject, $htmlBody);
    }

    private function logError(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
