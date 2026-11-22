<?php
require_once dirname(__DIR__) . '/classes/PaymentClass.php';
require_once dirname(__DIR__) . '/classes/SimulatedGateway.php';
require_once dirname(__DIR__) . '/classes/IPayMoneyGateway.php';
require_once __DIR__ . '/ReceiptController.php';

class PaymentController
{
    // Payment methods in Niger: key => [name, iPay Money payment type, available now?]
    public const METHODS = [
        'airtel_money' => ['Airtel Money', 'mobile', true],
        'zamani_cash'  => ['Zamani Cash', 'mobile', true],
        'moov_money'   => ['Moov Money', 'mobile', true],
        'mynita'       => ['MyNita', 'nita_online', true],
        'amanata'      => ['Amanata', 'amanata', true],
        'wave'         => ['Wave', 'mobile', false],
    ];
    // Note (official iPay docs)
    public const TYPES = ['order', 'rental', 'feature'];

    private PaymentClass $payments;
    private SimulatedGateway|IPayMoneyGateway $gateway;

    public function __construct()
    {
        $this->payments = new PaymentClass();
        $this->gateway = (PAYMENT_MODE === 'ipaymoney' && IPayMoneyGateway::isConfigured())
            ? new IPayMoneyGateway() : new SimulatedGateway();
    }

    public function isSimulation(): bool
    {
        return $this->gateway instanceof SimulatedGateway;
    }

    // Test mode: our simulation, OR real iPay in sandbox
    public function isTestMode(): bool
    {
        return $this->isSimulation() || IPAY_ENVIRONMENT === 'sandbox';
    }

    // Can this method be used now? (Wave: never yet
    public function isAvailable(string $method): bool
    {
        if (!isset(self::METHODS[$method]) || !self::METHODS[$method][2]) {
            return false;
        }
        return $this->isSimulation() || IPayMoneyGateway::supportsDirect(self::METHODS[$method][1]);
    }

    // The order or borrowing to pay, with 'payable' = can it be paid now?
    public function getPayable(string $type, int $id, int $userId): ?array
    {
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }
        $p = $this->payments->getPayable($type, $id, $userId);
        if ($p !== null) {
            $p['type'] = $type;
            $p['payable'] = ($type === 'order' && $p['status'] === 'pending_payment') || ($type === 'rental' && $p['status'] === 'approved')
                         || ($type === 'feature' && $p['status'] === 'approved');
        }
        return $p;
    }

    // Phone that pays
    public function cleanPhone(string $phone): ?string
    {
        $digits = preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $phone));
        if (preg_match('/^\d{8}$/', $digits)) {
            return $digits;
        }
        $testMode = $this->isSimulation() || IPAY_ENVIRONMENT === 'sandbox';
        return ($testMode && isset(SimulatedGateway::TEST_NUMBERS[$digits])) ? $digits : null;
    }

    // Start a payment
    public function start(string $type, int $id, int $userId, string $method, string $phone): array
    {
        $payable = $this->getPayable($type, $id, $userId);
        if ($payable === null || !$payable['payable']) {
            return ['status' => 'error', 'error' => 'pay.err_not_payable'];
        }
        if (!$this->isAvailable($method)) {
            return ['status' => 'error', 'error' => 'pay.err_method'];
        }
        $msisdn = $this->cleanPhone($phone);
        if ($msisdn === null) {
            return ['status' => 'error', 'error' => 'reg.err_phone'];
        }
        if ($this->payments->hasRecentPending($type, $id)) {
            return ['status' => 'error', 'error' => 'pay.err_pending'];
        }

        $ref = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $paymentId = $this->payments->addPayment([
            'transaction_ref' => $ref, 'payable_type' => $type, 'payable_id' => $id, 'user_id' => $userId,
            'provider' => $this->gateway->name(), 'method' => $method,
            'amount' => (int) $payable['amount'],
            'msisdn' => $msisdn,
        ]);

        // Number sent as 8 digits (iPay's documentation example: "97000000")
        $answer = $this->gateway->requestPayment((int) $payable['amount'], $msisdn, $ref,
                                                 self::METHODS[$method][1], $this->payments->getUserName($userId));
        $this->payments->saveGatewayAnswer($paymentId, $answer['reference'], $answer['raw']);

        if ($answer['status'] === 'failed') {
            $this->payments->markFailed($paymentId, $answer['reason'] ?? 'pay.reason_error');
            return ['status' => 'failed', 'ref' => $ref];
        }
        if ($answer['status'] === 'succeeded') {
            $this->finalize($paymentId);
        }
        return ['status' => $answer['status'], 'ref' => $ref];
    }

    // The money arrived: mark the order/borrowing paid
    public function finalize(int $paymentId): void
    {
        $this->payments->begin();
        try {
            $payment = $this->payments->lockPayment($paymentId);
            if ($payment === null || $payment['status'] === 'succeeded') {
                $this->payments->commit();
                return;
            }
            $this->payments->markSucceeded($paymentId);

            if ($payment['payable_type'] === 'order') {
                $order = $this->payments->lockOrder((int) $payment['payable_id']);
                $items = $this->payments->lockOrderItems((int) $payment['payable_id']);
                $enough = $order && $order['status'] === 'pending_payment';
                foreach ($items as $item) {
                    $enough = $enough && (int) $item['stock'] >= (int) $item['quantity'];
                }
                if ($enough) {
                    foreach ($items as $item) {
                        $this->payments->takeStock((int) $item['product_id'], (int) $item['quantity']);
                    }
                    $this->payments->setOrderStatus((int) $payment['payable_id'], 'paid');
                } else {
                    // Sold to another bride a moment before: cancel and note the refund for the admin
                    if ($order && $order['status'] === 'pending_payment') {
                        $this->payments->setOrderStatus((int) $payment['payable_id'], 'cancelled');
                    }
                    $this->payments->setFailureReason($paymentId, 'REFUND DUE: outfit no longer available when the payment arrived');
                }
            } elseif ($payment['payable_type'] === 'feature') {
                $product = $this->payments->lockProduct((int) $payment['payable_id']);
                if ($product && $product['status'] === 'approved') {
                    $this->payments->extendFeature((int) $payment['payable_id'], FEATURE_DAYS);
                } else {
                    $this->payments->setFailureReason($paymentId, 'REFUND DUE: outfit no longer in the shop when the payment arrived');
                }
            } else {
                $rental = $this->payments->lockRental((int) $payment['payable_id']);
                if ($rental && $rental['status'] === 'approved') {
                    $this->payments->markRentalPaid((int) $payment['payable_id']);
                } else {
                    $this->payments->setFailureReason($paymentId, 'REFUND DUE: borrowing no longer waiting for payment');
                }
            }
            $this->payments->commit();
        } catch (Throwable $e) {
            $this->payments->rollback();
            error_log('Payment finalize failed: ' . $e->getMessage());
            throw $e;
        }
        // Receipts for the bride, the seller(s) and BridalCycle
        try {
            (new ReceiptController())->forPayment($paymentId);
        } catch (Throwable $e) {
            error_log('Receipts failed for payment ' . $paymentId . ': ' . $e->getMessage());
        }
    }

    // The bride checks a waiting payment ("I confirmed on my phone")
    public function check(string $ref, int $userId): ?array
    {
        $payment = $this->payments->getPaymentByRef($ref, $userId);
        if ($payment === null) {
            return null;
        }
        if ($payment['status'] === 'pending' && $payment['gateway_reference']) {
            $answer = $this->gateway->checkPayment($payment['gateway_reference'], $payment['msisdn'], $payment['created_at']);
            if ($answer['status'] === 'succeeded') {
                $this->finalize((int) $payment['id']);
            } elseif ($answer['status'] === 'failed') {
                $this->payments->markFailed((int) $payment['id'], $answer['reason'] ?? 'pay.reason_error');
            }
            $payment = $this->payments->getPaymentByRef($ref, $userId);
        }
        return $payment;
    }

    public function getPayment(string $ref, int $userId): ?array
    {
        return $this->payments->getPaymentByRef($ref, $userId);
    }

    // iPay Money calls our webhook
    public function webhook(string $gatewayRef): bool
    {
        $payment = $this->payments->getPaymentByGatewayRef($gatewayRef);
        if ($payment === null || $payment['status'] !== 'pending') {
            return $payment !== null;
        }
        // We never trust the webhook body alone
        $answer = $this->gateway->checkPayment((string) ($payment['gateway_reference'] ?: $payment['transaction_ref']), $payment['msisdn'], $payment['created_at']);
        if ($answer['status'] === 'succeeded') {
            $this->finalize((int) $payment['id']);
        } elseif ($answer['status'] === 'failed') {
            $this->payments->markFailed((int) $payment['id'], $answer['reason'] ?? 'pay.reason_error');
        }
        return true;
    }
}
