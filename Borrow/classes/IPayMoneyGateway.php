<?php
require_once dirname(__DIR__) . '/core/payment_cred.php';

class IPayMoneyGateway
{
    private const BASE = 'https://i-pay.money/api/v1';

    public function name(): string { return 'ipaymoney'; }

    // Only the SECRET key is needed
    public static function isConfigured(): bool
    {
        return IPAY_SECRET_KEY !== '';
    }

    // Payment types this gateway can do directly
    public static function supportsDirect(string $paymentType): bool
    {
        return $paymentType === 'mobile';
    }

    public function requestPayment(int $amount, string $msisdn, string $ourRef, string $paymentType, string $customerName): array
    {
        if (!self::supportsDirect($paymentType)) {
            return ['status' => 'failed', 'reference' => null, 'reason' => 'pay.reason_gateway', 'raw' => '{"error":"payment type needs a payment link"}'];
        }
        [$code, $answer] = $this->call('POST', '/payments', [
            'amount'         => $amount,
            'country'        => 'NE',
            'currency'       => 'XOF',
            'msisdn'         => $msisdn,
            'transaction_id' => $ourRef,
            'customer_name'  => $customerName !== '' ? $customerName : 'BridalCycle',
        ]);

        if ($code === 0) {
            // Network cut or timeout: the payment MAY exist
            return ['status' => 'pending', 'reference' => $ourRef, 'reason' => null, 'raw' => '{"network":"no answer"}'];
        }
        if ($code !== 200 || empty($answer['reference'])) {
            $reason = ($code === 400 && str_contains((string) ($answer['message'] ?? ''), 'MSISDN')) ? 'pay.reason_msisdn' : 'pay.reason_gateway';
            return ['status' => 'failed', 'reference' => null, 'reason' => $reason, 'raw' => json_encode(['http' => $code, 'body' => $answer])];
        }
        // The creation answer is not final
        return ['status' => 'pending', 'reference' => (string) $answer['reference'], 'reason' => null, 'raw' => json_encode($answer)];
    }

    public function checkPayment(string $reference, string $msisdn, ?string $createdAt = null): array
    {
        [$code, $answer] = $this->call('GET', '/payments/' . rawurlencode($reference), null);
        if ($code !== 200) {
            return ['status' => 'pending', 'reason' => null, 'raw' => json_encode(['http' => $code, 'body' => $answer])];
        }
        $status = (string) ($answer['status'] ?? '');
        $mapped = match ($status) {
            'succeeded'          => 'succeeded',
            'failed', 'refunded' => 'failed',
            default              => 'pending',
        };
        return ['status' => $mapped, 'reason' => $mapped === 'failed' ? 'pay.reason_refused' : null, 'raw' => json_encode($answer)];
    }

    private function call(string $method, string $path, ?array $body): array
    {
        if (!function_exists('curl_init')) {
            error_log('iPay Money: the PHP cURL extension is not enabled.');
            return [0, null];
        }
        $ch = curl_init(self::BASE . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . IPAY_SECRET_KEY,
                'Ipay-Target-Environment: ' . IPAY_ENVIRONMENT,
                'Ipay-Payment-Type: mobile',
            ],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $raw  = curl_exec($ch);
        $code = $raw === false ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            error_log("iPay Money: $method $path → HTTP $code " . mb_substr((string) $raw, 0, 200));
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return [$code, is_array($data) ? $data : null];
    }
}
