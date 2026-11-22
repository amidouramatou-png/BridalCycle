<?php
require_once dirname(__DIR__) . '/core/db_class.php';

class UserClass extends Database
{
    // Is this email already registered? (prepared statement)
    public function emailExists(string $email): bool
    {
        return $this->fetchOne('SELECT id FROM users WHERE email = ?', 's', $email) !== null;
    }

    // One user by email, or null. Used by login
    public function getUserByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = ?', 's', $email);
    }

    // One user by Google account id (the "sub" number Google gives), or null
    public function getUserByGoogleId(string $googleId): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE google_id = ?', 's', $googleId);
    }

    // Connect a Google account to an existing user (never the admin)
    public function linkGoogleId(int $id, string $googleId): void
    {
        $this->run("UPDATE users SET google_id = ? WHERE id = ? AND role <> 'admin' AND google_id IS NULL",
                   'si', $googleId, $id)->close();
    }

    // One user by id, or null
    public function getUserById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE id = ?', 'i', $id);
    }

    // A wrong password: count it, and lock the account when there are too many
    public function registerFailedLogin(int $id, int $maxAttempts, int $lockMinutes): void
    {
        $this->run(
            // (locked_until is set FIRST
            'UPDATE users
                SET locked_until  = IF(failed_logins + 1 >= ?, NOW() + INTERVAL ? MINUTE, locked_until),
                    failed_logins = failed_logins + 1
              WHERE id = ?',
            'iii',
            $maxAttempts, $lockMinutes, $id
        )->close();
        // After a lock, start counting again from zero
        $this->run('UPDATE users SET failed_logins = 0 WHERE id = ? AND failed_logins >= ?', 'ii', $id, $maxAttempts)->close();
    }

    // A successful login: clear the counters and remember when and from where
    public function registerSuccessfulLogin(int $id, string $ip): void
    {
        $this->run(
            'UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
            'si',
            $ip, $id
        )->close();
    }

    // Save a new password hash (used when PHP recommends a stronger hash)
    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash = ? WHERE id = ?', 'si', $hash, $id)->close();
    }

    // Add a new user
    public function addUser(string $name, string $email, string $phone, string $region,
                            string $passwordHash, string $role, ?string $shopName, ?string $city,
                            ?string $googleId = null): int
    {
        if (!in_array($role, ['buyer', 'seller'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        $stmt = $this->run(
            'INSERT INTO users (full_name, email, phone, region, password_hash, role, shop_name, city, google_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'sssssssss',
            $name, $email, $phone, $region, $passwordHash, $role, $shopName, $city, $googleId
        );
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // My account: change details, password, delete

    // Change the details a user can edit herself
    public function updateProfile(int $id, string $name, string $phone, string $region, ?string $city, ?string $shopName, int $hideName = 0): void
    {
        $this->run('UPDATE users SET full_name = ?, phone = ?, region = ?, city = ?, shop_name = ?, hide_name = ? WHERE id = ?',
                   'sssssii', $name, $phone, $region, $city, $shopName, $hideName, $id)->close();
    }

    // Things still in progress for this user
    public function countOpenBusiness(int $id): int
    {
        $row = $this->fetchOne(
            "SELECT
               (SELECT COUNT(*) FROM rentals WHERE (borrower_id = ? OR seller_id = ?)
                   AND (status IN ('requested','approved','paid','picked_up') OR payout_status = 'due'
                        OR (status = 'returned' AND deposit_status = 'held')))
             + (SELECT COUNT(*) FROM bc_orders WHERE buyer_id = ? AND status IN ('pending_payment','paid','ready'))
             + (SELECT COUNT(*) FROM order_items oi JOIN bc_orders o ON o.id = oi.order_id
                 WHERE oi.seller_id = ? AND o.status IN ('paid','ready','completed') AND oi.payout_status IN ('not_due','due'))
             + (SELECT COUNT(*) FROM payments WHERE user_id = ? AND (status = 'pending' OR failure_reason LIKE 'REFUND DUE%')) AS n",
            'iiiii', $id, $id, $id, $id, $id
        );
        return (int) $row['n'];
    }

    // Has this user ever bought, borrowed, sold, lent or paid?
    public function hasHistory(int $id): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM bc_orders WHERE buyer_id = ? UNION SELECT 1 FROM order_items WHERE seller_id = ?
             UNION SELECT 1 FROM rentals WHERE borrower_id = ? OR seller_id = ? UNION SELECT 1 FROM payments WHERE user_id = ? LIMIT 1',
            'iiiii', $id, $id, $id, $id, $id
        ) !== null;
    }

    // Photo files of all her outfits
    public function getImageFiles(int $id): array
    {
        return array_column($this->fetchAll(
            'SELECT i.file_name FROM product_images i JOIN bc_products p ON p.id = i.product_id WHERE p.seller_id = ?', 'i', $id
        ), 'file_name');
    }

    // Delete an account with no history
    public function deleteUser(int $id): void
    {
        $this->run("DELETE FROM users WHERE id = ? AND role <> 'admin'", 'i', $id)->close();
    }

    // Close an account WITH history
    public function anonymize(int $id, string $randomHash): void
    {
        $this->run(
            "UPDATE users SET full_name = 'Deleted account', email = CONCAT('deleted-', id, '@bridalcycle.invalid'), phone = '00000000',
                    password_hash = ?, google_id = NULL, shop_name = NULL, city = NULL, status = 'suspended', last_login_ip = NULL
              WHERE id = ? AND role <> 'admin'",
            'si', $randomHash, $id
        )->close();
        $this->run("UPDATE bc_products SET status = 'archived' WHERE seller_id = ? AND status IN ('pending','approved','rejected')", 'i', $id)->close();
        $this->run('DELETE FROM cart_items WHERE user_id = ?', 'i', $id)->close();
    }
}
