<?php
require_once dirname(__DIR__) . '/classes/UserClass.php';
require_once dirname(__DIR__) . '/classes/SecurityClass.php';

class UserController
{
    // The 8 regions of Niger (the same list as in the database)
    public const REGIONS = ['Agadez', 'Diffa', 'Dosso', 'Maradi', 'Niamey', 'Tahoua', 'Tillabéri', 'Zinder'];

    private UserClass $users;

    public function __construct()
    {
        $this->users = new UserClass();
    }

    // One user (for "My account")
    public function getUser(int $id): ?array
    {
        return $this->users->getUserById($id);
    }

    // Register a new user
    public function register(array $data): array
    {
        if ($this->users->emailExists($data['email'])) {
            return ['success' => false, 'error' => 'reg.err_email_taken'];
        }

        // Hash the password: the real password is NEVER stored (lab: password_hash)
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $id = $this->users->addUser(
                $data['full_name'], $data['email'], $data['phone'], $data['region'], $hash,
                $data['role'], $data['shop_name'] ?: null, $data['city'] ?: null
            );
        } catch (mysqli_sql_exception $e) {
            // Two people registering the same email at the same second
            if ($e->getCode() === 1062) {
                return ['success' => false, 'error' => 'reg.err_email_taken'];
            }
            error_log('Registration failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }

        return ['success' => true, 'user' => $this->users->getUserById($id)];
    }

    // Log in with email + password, with protection against password guessing
    public function login(string $email, string $password, string $ip): array
    {
        $security = new SecurityClass();
        $fail = fn (string $key, array $values = []) => ['success' => false, 'error' => $key, 'values' => $values];

        // 1. Too many failed logins from this computer/phone in 15 minutes? Stop it for a while
        if ($security->countRecentFailuresByIp($ip, 15) >= MAX_ATTEMPTS_PER_IP) {
            return $fail('login.err_too_many');
        }

        $user = $this->users->getUserByEmail($email);

        // 2. Unknown email: still run password_verify, so the answer takes the same time
        if ($user === null) {
            password_verify($password, '$2y$10$rK4YiYJY3pglNcxV/edDkeZs1Rm6UFulUbzOlFpbWWPxX9T1UAn/S');
            $security->logLoginAttempt($email, $ip, false);
            return $fail('login.err_wrong');
        }

        // 3. Account locked after too many wrong passwords?
        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            $security->logLoginAttempt($email, $ip, false);
            return $fail('login.err_locked', ['minutes' => $minutes]);
        }

        // 4. Check the password against the hash (lab: password_verify)
        if (!password_verify($password, $user['password_hash'])) {
            $this->users->registerFailedLogin((int) $user['id'], MAX_LOGIN_ATTEMPTS, LOCK_MINUTES);
            $security->logLoginAttempt($email, $ip, false);
            return $fail('login.err_wrong');
        }

        // 5. Account suspended by the admin? (checked only after a correct password)
        if ($user['status'] !== 'active') {
            $security->logLoginAttempt($email, $ip, false);
            return $fail('login.err_suspended');
        }

        // 6. Success
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->users->registerSuccessfulLogin((int) $user['id'], $ip);
        $security->logLoginAttempt($email, $ip, true);
        if ($user['role'] === 'admin') {
            $security->logAdminAction((int) $user['id'], 'admin_login', $ip);
        }

        return ['success' => true, 'user' => $user];
    }

    // "Continue with Google": $profile comes from GoogleAuthClass->getProfile()
    public function googleLogin(array $profile, string $ip): array
    {
        $security = new SecurityClass();
        $fail = fn (string $key) => ['success' => false, 'error' => $key];

        if ($security->countRecentFailuresByIp($ip, 15) >= MAX_ATTEMPTS_PER_IP) {
            return $fail('login.err_too_many');
        }
        // Only an email that Google has verified can be trusted
        if (!$profile['email_verified']) {
            return $fail('google.err_unverified');
        }

        // 1. Already connected with this Google account? 2. Otherwise, same email?
        $user = $this->users->getUserByGoogleId($profile['sub'])
             ?? $this->users->getUserByEmail($profile['email']);

        if ($user === null) {
            return ['success' => false, 'needs_profile' => true];
        }

        // SECURITY: the admin can NEVER log in with Google (password only)
        if ($user['role'] === 'admin') {
            $security->logLoginAttempt($profile['email'], $ip, false);
            $security->logAdminAction((int) $user['id'], 'blocked_google_login', $ip, null, null, 'Google login refused for the admin');
            return $fail('google.err_admin');
        }
        // This email is already connected to a DIFFERENT Google account
        if ($user['google_id'] !== null && !hash_equals($user['google_id'], $profile['sub'])) {
            $security->logLoginAttempt($profile['email'], $ip, false);
            return $fail('google.err_other_account');
        }
        if ($user['status'] !== 'active') {
            $security->logLoginAttempt($profile['email'], $ip, false);
            return $fail('login.err_suspended');
        }

        // First Google login for an account made with a password: connect them
        if ($user['google_id'] === null) {
            $this->users->linkGoogleId((int) $user['id'], $profile['sub']);
        }
        $this->users->registerSuccessfulLogin((int) $user['id'], $ip);
        $security->logLoginAttempt($user['email'], $ip, true);

        return ['success' => true, 'user' => $this->users->getUserById((int) $user['id'])];
    }

    // Create the account of a NEW Google user, after she completed her profile
    public function registerWithGoogle(array $data, array $google): array
    {
        if ($this->users->emailExists($google['email']) || $this->users->getUserByGoogleId($google['sub'])) {
            return ['success' => false, 'error' => 'reg.err_email_taken'];
        }

        // She logs in with Google, so she has no password yet
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        try {
            $id = $this->users->addUser(
                $data['full_name'], $google['email'], $data['phone'], $data['region'], $hash,
                $data['role'], $data['shop_name'] ?: null, $data['city'] ?: null, $google['sub']
            );
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                return ['success' => false, 'error' => 'reg.err_email_taken'];
            }
            error_log('Google registration failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }

        return ['success' => true, 'user' => $this->users->getUserById($id)];
    }

    // Check the "My details" form (the same rules as the registration)
    public static function validateProfile(array $post, bool $isSeller): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));
        $data = [
            'full_name' => preg_replace('/\s+/', ' ', $clean('full_name')),
            'phone'     => preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $clean('phone'))),
            'region'    => $clean('region'),
            'city'      => preg_replace('/\s+/', ' ', $clean('city')),
            'shop_name' => $isSeller ? preg_replace('/\s+/', ' ', $clean('shop_name')) : '',
            'hide_name' => isset($post['hide_name']) ? 1 : 0,
        ];
        $errors = [];
        if (!preg_match("/^[\p{L}][\p{L}\s'’.-]{1,119}$/u", $data['full_name'])) {
            $errors['full_name'] = msg('reg.err_name');
        }
        if (!preg_match('/^\d{8}$/', $data['phone'])) {
            $errors['phone'] = msg('reg.err_phone');
        }
        if (!in_array($data['region'], self::REGIONS, true)) {
            $errors['region'] = msg('reg.err_region');
        }
        if (mb_strlen($data['city']) > 80) {
            $errors['city'] = msg('reg.err_city');
        }
        if (mb_strlen($data['shop_name']) > 120) {
            $errors['shop_name'] = msg('reg.err_shop');
        }
        return [$data, $errors];
    }

    public function updateProfile(int $id, array $data): void
    {
        $this->users->updateProfile($id, $data['full_name'], $data['phone'], $data['region'],
                                    $data['city'] !== '' ? $data['city'] : null, $data['shop_name'] !== '' ? $data['shop_name'] : null,
                                    (int) ($data['hide_name'] ?? 0));
    }

    // Change the password: the CURRENT password is required
    public function changePassword(int $id, string $current, string $new, string $confirm): array
    {
        $user = $this->users->getUserById($id);
        $errors = [];
        if ($user === null || !password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = msg('acc.err_current');
        }
        if (!preg_match('/^(?=.*\p{L})(?=.*\d).{' . PASSWORD_MIN_LENGTH . ',}$/u', $new)) {
            $errors['password'] = msg('reg.err_password', ['min' => PASSWORD_MIN_LENGTH]);
        } elseif (strlen($new) > 72) {
            $errors['password'] = msg('reg.err_password_long');
        } elseif ($current === $new) {
            $errors['password'] = msg('acc.err_same');
        }
        if ($new !== $confirm) {
            $errors['password_confirm'] = msg('reg.err_confirm');
        }
        if (!$errors) {
            $this->users->updatePasswordHash($id, password_hash($new, PASSWORD_DEFAULT));
        }
        return $errors;
    }

    // Delete my account
    public function deleteAccount(int $id, string $typedEmail, string $password): array
    {
        $user = $this->users->getUserById($id);
        if ($user === null || $user['role'] === 'admin') {
            return ['success' => false, 'error' => 'acc.err_admin'];
        }
        $passwordOk = password_verify($password, $user['password_hash']);
        if (strtolower(trim($typedEmail)) !== strtolower($user['email']) || (!$passwordOk && $user['google_id'] === null)) {
            return ['success' => false, 'error' => 'acc.err_confirm_delete'];
        }
        if ($this->users->countOpenBusiness($id) > 0) {
            return ['success' => false, 'error' => 'acc.err_open'];
        }
        if ($this->users->hasHistory($id)) {
            $this->users->anonymize($id, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
        } else {
            $files = $this->users->getImageFiles($id);
            $this->users->deleteUser($id);
            foreach ($files as $file) {
                $path = dirname(__DIR__) . '/images/products/' . basename($file);
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        return ['success' => true];
    }
}
