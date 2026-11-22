<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class PasswordResetClass extends Database
{
    // How many reset links were asked for this user in the last hour?
    public function countRecent(int $userId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS n FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR',
            'i', $userId
        );
        return (int) ($row['n'] ?? 0);
    }

    // Save a new link (its fingerprint) valid for $minutes minutes
    public function create(int $userId, string $tokenHash, int $minutes): void
    {
        $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', 'i', $userId)->close();
        $this->run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)',
            'isi', $userId, $tokenHash, $minutes
        )->close();
    }

    // A link that exists, is not used and not expired, for an active user. Or null
    public function getValid(string $tokenHash): ?array
    {
        return $this->fetchOne(
            "SELECT pr.id, pr.user_id, u.email, u.full_name
               FROM password_resets pr JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                AND u.status = 'active' AND u.role <> 'admin'",
            's', $tokenHash
        );
    }

    // Use the link
    public function useLink(int $resetId, int $userId, string $passwordHash): void
    {
        $this->conn->begin_transaction();
        try {
            $this->run('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?',
                       'si', $passwordHash, $userId)->close();
            $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', 'i', $userId)->close();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}
