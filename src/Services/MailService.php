<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class MailService
{
    private string $fromEmail;
    private string $fromName;
    /** @var array{address?:string,name?:string} */
    private array $replyTo;

    public function __construct(
        private PHPMailer $mailer,
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $replyTo = []
    ) {
        $this->fromEmail = $fromEmail ?? (string)app_config('noreply_email', 'noreply@example.com');
        $this->fromName = $fromName ?? (string)app_config('app_name', 'Our Team');
        $this->replyTo = $replyTo;

        $this->mailer->clearReplyTos();
        $replyToAddress = $replyTo['address'] ?? $replyTo['email'] ?? null;
        if (is_string($replyToAddress) && $replyToAddress !== '') {
            $replyToName = (string)($replyTo['name'] ?? $this->fromName);
            $this->mailer->addReplyTo($replyToAddress, $replyToName);
        }
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

        $subject = $variables['subject'] ?? sprintf('Message from %s', (string)app_config('app_name', 'Our Team'));

        return $this->send($to, $subject, $htmlBody);
    }

    private function logError(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
