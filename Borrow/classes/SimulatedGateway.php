<?php
class SimulatedGateway
{
    public const TEST_NUMBERS = [
        '40410000000' => 'succeeded', '40410000001' => 'succeeded',
        '40410000002' => 'error',     '40410000003' => 'error',
        '40410000004' => 'funds',     '40410000005' => 'funds',
        '40410000006' => 'refused',   '40410000007' => 'refused',
        '40410000008' => 'pending',   '40410000009' => 'pending',
    ];

    public function name(): string { return 'simulation'; }

    // Ask for the payment
    public function requestPayment(int $amount, string $msisdn, string $ourRef, string $paymentType, string $customerName): array
    {
        $result = self::TEST_NUMBERS[$msisdn] ?? 'succeeded';
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
        $map = ['error' => 'pay.reason_error', 'funds' => 'pay.reason_funds', 'refused' => 'pay.reason_refused'];
        return [
            'status'    => isset($map[$result]) ? 'failed' : $result,
            'reference' => $reference,
            'reason'    => $map[$result] ?? null,
            'raw'       => json_encode(['simulation' => true, 'msisdn' => $msisdn, 'result' => $result, 'type' => $paymentType]),
        ];
    }

    // Check a waiting payment again
    public function checkPayment(string $reference, string $msisdn, ?string $createdAt = null): array
    {
        $result = self::TEST_NUMBERS[$msisdn] ?? 'succeeded';
        if ($result === 'pending') {
            $waited = $createdAt !== null ? time() - strtotime($createdAt) : 0;
            $result = $waited >= 90 ? 'succeeded' : 'pending';
        }
        return ['status' => $result === 'pending' ? 'pending' : ($result === 'succeeded' ? 'succeeded' : 'failed'), 'reason' => null, 'raw' => '{"simulation":true}'];
    }
}
