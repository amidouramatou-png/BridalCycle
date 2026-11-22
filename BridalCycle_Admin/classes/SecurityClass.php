<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class SecurityClass extends Database
{
    // Write down every login attempt
    public function logLoginAttempt(string $email, string $ip, bool $success): void
    {
        $this->run(
            'INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)',
            'ssi',
            $email, $ip, $success ? 1 : 0
        )->close();
    }

    // How many FAILED logins came from this IP address in the last $minutes minutes?
    public function countRecentFailuresByIp(string $ip, int $minutes): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM login_attempts
              WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
            'si',
            $ip, $minutes
        );
        return (int) $row['total'];
    }

    // Write one line in the admin activity log
    public function logAdminAction(int $adminId, string $action, string $ip,
                                   ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
    {
        $this->run(
            'INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            'ississ',
            $adminId, $action, $targetType, $targetId, $details, $ip
        )->close();
    }
}
