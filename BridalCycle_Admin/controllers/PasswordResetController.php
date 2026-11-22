<?php
require_once dirname(__DIR__) . '/classes/PasswordResetClass.php';
require_once dirname(__DIR__) . '/classes/UserClass.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

class PasswordResetController
{
    public const LINK_MINUTES  = 30;
    public const MAX_PER_HOUR  = 3;

    private PasswordResetClass $resets;

    public function __construct()
    {
        $this->resets = new PasswordResetClass();
    }

    // Send the link if the account exists
    public function requestLink(string $email): void
    {
        $user = (new UserClass())->getUserByEmail($email);
        if ($user === null || $user['status'] !== 'active' || $user['role'] === 'admin') {
            return;
        }
        if ($this->resets->countRecent((int) $user['id']) >= self::MAX_PER_HOUR) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->resets->create((int) $user['id'], hash('sha256', $token), self::LINK_MINUTES);

        $link = BASE_URL . '/views/reset_password.php?token=' . $token;
        $html = '<div style="font-family:Georgia,serif;color:#3d0c1a;max-width:520px">'
              . '<h2 style="color:#5c1427">BridalCycle</h2>'
              . '<p>' . e(msg('reset.mail_hello', ['name' => $user['full_name']])) . '</p>'
              . '<p>' . e(msg('reset.mail_text', ['minutes' => self::LINK_MINUTES])) . '</p>'
              . '<p><a href="' . e($link) . '" style="display:inline-block;background:#5c1427;color:#fff;padding:12px 22px;text-decoration:none">'
              . e(msg('reset.mail_button')) . '</a></p>'
              . '<p style="font-size:13px;color:#777">' . e(msg('reset.mail_ignore')) . '</p></div>';

        (new Mailer())->send($user['email'], $user['full_name'], msg('reset.mail_subject'), $html);
    }

    // Is this link still valid? Returns the reset row or null
    public function checkLink(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->resets->getValid(hash('sha256', $token));
    }

    // Save the new password
    public function resetPassword(string $token, string $new, string $confirm): array
    {
        $reset = $this->checkLink($token);
        if ($reset === null) {
            return ['success' => false, 'expired' => true];
        }
        $errors = [];
        if (!preg_match('/^(?=.*\p{L})(?=.*\d).{' . PASSWORD_MIN_LENGTH . ',}$/u', $new)) {
            $errors['password'] = msg('reg.err_password', ['min' => PASSWORD_MIN_LENGTH]);
        } elseif (strlen($new) > 72) {
            $errors['password'] = msg('reg.err_password_long');
        }
        if ($new !== $confirm) {
            $errors['password_confirm'] = msg('reg.err_confirm');
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }
        $this->resets->useLink((int) $reset['id'], (int) $reset['user_id'], password_hash($new, PASSWORD_DEFAULT));
        return ['success' => true, 'email' => $reset['email']];
    }
}
