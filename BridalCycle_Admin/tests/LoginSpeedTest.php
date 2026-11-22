<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/controllers/UserController.php';

final class LoginSpeedTest extends TestCase
{
    private const LIMIT_SECONDS = 10.0;
    private const PASSWORD = 'SpeedTest2026ok';

    private static TestDatabase $db;
    private static string $email;
    private static int $userId = 0;
    private string $cookieFile = '';

    // Create ONE test bride for all the tests
    public static function setUpBeforeClass(): void
    {
        self::$db = new TestDatabase();
        self::$email = 'speedtest' . bin2hex(random_bytes(4)) . '@test.ne';
        $result = (new UserController())->register([
            'full_name' => 'Speed Test', 'email' => self::$email, 'phone' => '90000001', 'region' => 'Niamey',
            'city' => '', 'role' => 'buyer', 'shop_name' => '', 'password' => self::PASSWORD,
        ]);
        self::$userId = $result['success'] ? (int) $result['user']['id'] : 0;
    }

    // Delete the test bride and her login attempts: the database is clean again
    public static function tearDownAfterClass(): void
    {
        self::$db->exec('DELETE FROM login_attempts WHERE email = ?', 's', self::$email);
        self::$db->exec("DELETE FROM users WHERE id = ? AND role <> 'admin'", 'i', self::$userId);
    }

    protected function tearDown(): void
    {
        if ($this->cookieFile !== '' && is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    public function testTestUserWasCreated(): void
    {
        $this->assertGreaterThan(0, self::$userId, 'The test bride could not be created.');
    }

    public function testValidLoginWithTheClassesIsFast(): void
    {
        $start = microtime(true);
        $result = (new UserController())->login(self::$email, self::PASSWORD, '127.0.0.1');
        $seconds = microtime(true) - $start;

        $this->assertTrue($result['success'], 'A valid email and password must log in.');
        $this->assertLessThan(self::LIMIT_SECONDS, $seconds, sprintf('Login took %.3f s', $seconds));
        fwrite(STDERR, sprintf("\n  [A] Login with the classes: %.3f s\n", $seconds));
    }

    public function testWrongPasswordIsRefusedQuickly(): void
    {
        $start = microtime(true);
        $result = (new UserController())->login(self::$email, 'WrongPassword2026', '127.0.0.1');
        $seconds = microtime(true) - $start;

        $this->assertFalse($result['success'], 'A wrong password must be refused.');
        $this->assertSame('login.err_wrong', $result['error']);
        $this->assertLessThan(self::LIMIT_SECONDS, $seconds);
    }

    public function testUnknownEmailGivesTheSameAnswerAsAWrongPassword(): void
    {
        $result = (new UserController())->login('nobody' . bin2hex(random_bytes(3)) . '@test.ne', self::PASSWORD, '127.0.0.1');
        $this->assertFalse($result['success']);
        $this->assertSame('login.err_wrong', $result['error'], 'Nobody can find out which emails exist.');
    }

    public function testPasswordIsStoredAsAHashNeverInClearText(): void
    {
        $row = self::$db->one('SELECT password_hash FROM users WHERE id = ?', 'i', self::$userId);
        $this->assertNotSame(self::PASSWORD, $row['password_hash']);
        $this->assertTrue(password_verify(self::PASSWORD, $row['password_hash']));
    }

    public function testLoginAndLogoutOnTheWebsiteAreFast(): void
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'bc');
        $this->skipIfSiteIsDown();

        // 1. Open the login page (gets the CSRF token)
        $start = microtime(true);
        $page = $this->http('views/login.php');
        $token = $this->csrf($page['body']);
        $this->assertNotSame('', $token, 'The login page must contain a CSRF token.');

        // 2. Log in, then open "My account"
        $login = $this->http('actions/login_action.php', ['csrf_token' => $token, 'email' => self::$email, 'password' => self::PASSWORD]);
        $account = $this->http('views/account/my_account.php');
        $loginSeconds = microtime(true) - $start;

        $this->assertSame(302, $login['code'], 'Login must redirect.');
        $this->assertStringContainsString('my_account.php', $account['url'], 'After login, My account must open (not the login page).');
        $this->assertLessThan(self::LIMIT_SECONDS, $loginSeconds, sprintf('Website login took %.3f s', $loginSeconds));

        // 3. Log out (form with CSRF), then My account must ask to log in again
        $start = microtime(true);
        $logout = $this->http('logout.php', ['csrf_token' => $this->csrf($account['body'])]);
        $after = $this->http('views/account/my_account.php');
        $logoutSeconds = microtime(true) - $start;

        $this->assertSame(302, $logout['code']);
        $this->assertStringContainsString('login.php', $after['url'], 'After logout, My account must ask to log in.');
        $this->assertLessThan(self::LIMIT_SECONDS, $logoutSeconds, sprintf('Logout took %.3f s', $logoutSeconds));
        fwrite(STDERR, sprintf("\n  [B] Website login: %.3f s · logout: %.3f s\n", $loginSeconds, $logoutSeconds));
    }

    public function testCustomerCannotOpenAnAdminPage(): void
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'bc');
        $this->skipIfSiteIsDown();
        $page = $this->http('views/login.php');
        $this->http('actions/login_action.php', ['csrf_token' => $this->csrf($page['body']), 'email' => self::$email, 'password' => self::PASSWORD]);

        // A customer pastes the admin address into her browser
        $admin = $this->http('views/admin/admin_dashboard.php');
        $this->assertStringNotContainsString('admin_dashboard.php', $admin['url'], 'A customer must be sent away from the admin pages.');
        $this->assertStringContainsString('index.php', $admin['url']);
    }

    public function testAdminLoginIsFast(): void
    {
        $email = getenv('BC_ADMIN_EMAIL') ?: '';
        $password = getenv('BC_ADMIN_PASSWORD') ?: '';
        if ($email === '' || $password === '') {
            $this->markTestSkipped('Set BC_ADMIN_EMAIL and BC_ADMIN_PASSWORD to also time the admin login.');
        }
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'bc');
        $this->skipIfSiteIsDown();

        $start = microtime(true);
        $page = $this->http('views/login.php');
        $this->http('actions/login_action.php', ['csrf_token' => $this->csrf($page['body']), 'email' => $email, 'password' => $password]);
        $dashboard = $this->http('views/admin/admin_dashboard.php');
        $seconds = microtime(true) - $start;

        $this->assertStringContainsString('admin_dashboard.php', $dashboard['url'], 'The admin must reach her dashboard.');
        $this->assertLessThan(self::LIMIT_SECONDS, $seconds, sprintf('Admin login took %.3f s', $seconds));
        fwrite(STDERR, sprintf("\n  [B] Admin login + dashboard: %.3f s\n", $seconds));
    }

    // GET (or POST when $post is given) a page of the site
    private function http(string $path, ?array $post = null): array
    {
        $ch = curl_init(BASE_URL . '/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $post === null,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_USERAGENT => 'BridalCycle-PHPUnit',
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = (string) curl_exec($ch);
        $result = ['body' => $body, 'code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'url' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)];
        curl_close($ch);
        return $result;
    }

    private function csrf(string $html): string
    {
        return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }

    private function skipIfSiteIsDown(): void
    {
        $page = $this->http('index.php');
        if ($page['code'] !== 200) {
            $this->markTestSkipped('The website is not running at ' . BASE_URL . ' (start Apache in XAMPP).');
        }
    }
}
