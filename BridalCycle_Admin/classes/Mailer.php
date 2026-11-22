<?php
require_once dirname(__DIR__) . '/core/mail_cred.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    public function send(string $to, string $toName, string $subject, string $html): array
    {
        if (!MAIL_ENABLED || MAIL_USERNAME === '' || MAIL_APP_PASSWORD === '') {
            return ['status' => 'saved', 'error' => null];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'failed', 'error' => 'Invalid email address'];
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = str_replace(' ', '', MAIL_APP_PASSWORD);
            $mail->Timeout    = 10;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</tr>', '</p>', '</h1>', '</h2>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
            $mail->send();
            return ['status' => 'sent', 'error' => null];
        } catch (Throwable $e) {
            error_log('Email to ' . $to . ' failed: ' . $mail->ErrorInfo);
            return ['status' => 'failed', 'error' => mb_substr($mail->ErrorInfo ?: $e->getMessage(), 0, 250)];
        }
    }
}
