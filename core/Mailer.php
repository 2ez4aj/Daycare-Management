<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

class Mailer {
    private array $emailConfig;

    public function __construct(array $appConfig)
    {
        $this->emailConfig = $appConfig['email'] ?? [];
    }

    public function send(array $recipient, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $this->configureTransport($mail);

            $fromAddress = $this->emailConfig['from'] ?? 'noreply@example.com';
            $fromName = $this->emailConfig['from_name'] ?? 'Gumamela Daycare';

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($recipient['email'], $recipient['name'] ?? '');

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    private function configureTransport(PHPMailer $mail): void
    {
        $host = $this->emailConfig['smtp_host'] ?? 'localhost';
        $username = $this->emailConfig['smtp_username'] ?? '';
        $password = $this->emailConfig['smtp_password'] ?? '';

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $this->emailConfig['smtp_port'] ?? 587;
        $mail->SMTPAuth = !empty($username);
        $mail->Username = $username;
        $mail->Password = $password;

        if (!empty($this->emailConfig['smtp_secure'])) {
            $mail->SMTPSecure = $this->emailConfig['smtp_secure'];
        }

        if (!empty($this->emailConfig['smtp_auth']) && !$mail->SMTPAuth) {
            $mail->SMTPAuth = true;
        }

        if (!empty($this->emailConfig['smtp_debug'])) {
            $mail->SMTPDebug = (int)$this->emailConfig['smtp_debug'];
            $mail->Debugoutput = function ($str, $level) {
                error_log('PHPMailer: [' . $level . '] ' . trim($str));
            };
        }
    }
}

