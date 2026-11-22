<?php
require_once dirname(__DIR__) . '/core/google_cred.php';

class GoogleAuthClass
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    // Are the Google keys filled in core/google_cred.php?
    public static function isConfigured(): bool
    {
        return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
    }

    // The page Google sends the user back to
    public static function redirectUri(): string
    {
        return BASE_URL . '/actions/google_callback.php';
    }

    // Step 1: the Google page where the user chooses her account
    public function authUrl(string $state, string $verifier): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'             => GOOGLE_CLIENT_ID,
            'redirect_uri'          => self::redirectUri(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'code_challenge'        => self::base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        ]);
    }

    // Step 3: exchange the one-time code for the profile
    public function getProfile(string $code, string $verifier): ?array
    {
        // 3a. Code -> access token
        $token = $this->request(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        if (empty($token['access_token']) || !is_string($token['access_token'])) {
            return null;
        }

        // 3b. Access token -> profile
        $info = $this->request(self::USERINFO_URL, null, $token['access_token']);
        if (empty($info['sub']) || empty($info['email'])) {
            return null;
        }

        return [
            'sub'            => (string) $info['sub'],
            'email'          => strtolower((string) $info['email']),
            'email_verified' => ($info['email_verified'] ?? false) === true,
            'name'           => trim((string) ($info['name'] ?? '')),
        ];
    }

    // Send a request to Google with cURL and return the JSON answer as an array
    private function request(string $url, ?array $post, ?string $bearer = null): ?array
    {
        if (!function_exists('curl_init')) {
            error_log('Google login: the PHP cURL extension is not enabled (php.ini: extension=curl).');
            return null;
        }
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ];
        if ($post !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        if ($bearer !== null) {
            $options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $bearer;
        }
        curl_setopt_array($ch, $options);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            error_log("Google login: request failed ($status) $error");
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
