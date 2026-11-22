<?php
require_once dirname(__DIR__) . '/classes/RentalClass.php';

class RentalController
{
    // What a seller can do, and from which status: op => [from, to]
    private const SELLER_STEPS = [
        'approve'   => ['requested', 'approved'],
        'reject'    => ['requested', 'rejected'],
        'picked_up' => ['paid', 'picked_up'],
        'returned'  => ['picked_up', 'returned'],
    ];
    public const SELLER_TABS = ['requested', 'approved', 'paid', 'picked_up', 'returned'];

    private RentalClass $rentals;

    public function __construct()
    {
        $this->rentals = new RentalClass();
    }

    public function getProduct(int $id): ?array { return $this->rentals->getBorrowableProduct($id); }
    public function getBookedPeriods(int $productId): array { return $this->rentals->getBookedPeriods($productId); }
    public function getUserPhone(int $userId): string { return $this->rentals->getUserPhone($userId); }
    public function getBorrowerRentals(int $userId): array { return $this->rentals->getBorrowerRentals($userId); }
    public function countSellerRequests(int $sellerId): int { return $this->rentals->countSellerRequests($sellerId); }

    public function getSellerRentals(int $sellerId, string $status): array
    {
        return $this->rentals->getSellerRentals($sellerId, in_array($status, self::SELLER_TABS, true) ? $status : '');
    }

    // First day a bride can pick up an outfit (today + notice days), as Y-m-d
    public static function firstDay(): string
    {
        return (new DateTimeImmutable('today'))->modify('+' . RENT_MIN_NOTICE_DAYS . ' day')->format('Y-m-d');
    }

    // Read a date typed as YYYY-MM-DD; returns null if it is not a real date
    private static function date(string $value): ?DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $d : null;
    }

    // Check the borrowing form and CALCULATE the amounts. Returns [$data, $errors]
    public function validateRequest(array $post, array $product): array
    {
        $clean = fn (string $key): string => trim(strip_tags((string) ($post[$key] ?? '')));
        $errors = [];

        $start = self::date($clean('start_date'));
        $end   = self::date($clean('end_date'));
        $event = $clean('event_date') === '' ? null : self::date($clean('event_date'));
        $first = self::date(self::firstDay());
        $last  = (new DateTimeImmutable('today'))->modify('+1 year');

        if (!$start || $start < $first || $start > $last) {
            $errors['start_date'] = msg('rent.err_start', ['date' => $first->format('d/m/Y')]);
        }
        $days = ($start && $end) ? (int) $start->diff($end)->format('%r%a') + 1 : 0;
        if ($start && $end && $days < 1) {
            $errors['end_date'] = msg('rent.err_before');
        } elseif (!$end || $days < RENT_MIN_DAYS || $days > RENT_MAX_DAYS) {
            $errors['end_date'] = msg('rent.err_days', ['min' => RENT_MIN_DAYS, 'max' => RENT_MAX_DAYS]);
        }
        if ($clean('event_date') !== '' && (!$event || ($start && $event < $start) || ($end && $event > $end))) {
            $errors['event_date'] = msg('rent.err_event');
        }

        $phone = preg_replace('/^(\+227|00227)/', '', preg_replace('/[\s.\-]/', '', $clean('phone')));
        if (!preg_match('/^\d{8}$/', $phone)) {
            $errors['phone'] = msg('reg.err_phone');
        }
        $size = preg_replace('/\s+/', ' ', $clean('size_needed'));
        if ($size !== '' && !preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s\/.-]{0,19}$/u", $size)) {
            $errors['size_needed'] = msg('prod.err_size');
        }
        $note = trim(strip_tags((string) ($post['note'] ?? '')));
        if (mb_strlen($note) > 500) {
            $errors['note'] = msg('rent.err_note');
        }

        $price = (int) $product['rent_price_per_day'];
        $data = [
            'start_date' => $start ? $start->format('Y-m-d') : $clean('start_date'),
            'end_date'   => $end ? $end->format('Y-m-d') : $clean('end_date'),
            'event_date' => $event ? $event->format('Y-m-d') : null,
            'days'       => max(0, $days),
            'phone'      => $phone,
            'size_needed'=> $size !== '' ? $size : null,
            'note'       => $note !== '' ? $note : null,
            'rent_price_per_day' => $price,
            'rent_total' => $price * max(0, $days),
            'deposit'    => (int) $product['deposit'],
        ];
        $data['total_due'] = $data['rent_total'] + $data['deposit'];
        // BridalCycle's commission: on the rent only (the deposit belongs to the bride)
        $data['commission']    = self::commission($data['rent_total']);
        $data['seller_payout'] = $data['rent_total'] - $data['commission'];
        return [$data, $errors];
    }

    // BridalCycle commission on an amount, in whole XOF
    public static function commission(int $amount): int
    {
        return (int) round($amount * COMMISSION_PERCENT / 100);
    }

    // Save the request. Inside a transaction with the outfit LOCKED
    public function requestRental(array $product, int $borrowerId, array $data): array
    {
        if ((int) $product['seller_id'] === $borrowerId) {
            return ['success' => false, 'error' => 'rent.err_own'];
        }
        if (!empty($product['borrowed_until'])) {
            return ['success' => false, 'error' => 'rent.err_borrowed_now',
                    'values' => ['date' => date('d/m/Y', strtotime($product['borrowed_until'] . ' +1 day'))]];
        }
        if ($this->rentals->hasOpenRequest((int) $product['id'], $borrowerId)) {
            return ['success' => false, 'error' => 'rent.err_open'];
        }
        $this->rentals->begin();
        try {
            $locked = $this->rentals->lockProduct((int) $product['id']);
            $taken = $this->rentals->countOverlaps((int) $product['id'], $data['start_date'], $data['end_date'], 0, true);
            if ($locked === null || $taken >= (int) $locked['stock']) {
                $this->rentals->rollback();
                return ['success' => false, 'error' => 'rent.err_taken'];
            }
            $ref = 'BR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $this->rentals->addRental($data + [
                'rental_ref' => $ref, 'product_id' => (int) $product['id'],
                'borrower_id' => $borrowerId, 'seller_id' => (int) $product['seller_id'],
            ]);
            $this->rentals->commit();
        } catch (Throwable $e) {
            $this->rentals->rollback();
            error_log('Borrow request failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'reg.err_server'];
        }
        return ['success' => true, 'ref' => $ref];
    }

    // A seller moves one of HER borrowings to the next step
    public function sellerStep(int $rentalId, int $sellerId, string $op, string $note, bool $keepPartOfDeposit, int $amountKept = 0): array
    {
        $rental = $this->rentals->getSellerRental($rentalId, $sellerId);
        if ($rental === null || !isset(self::SELLER_STEPS[$op])) {
            return ['success' => false, 'error' => 'rent.err_not_found'];
        }
        [$from, $to] = self::SELLER_STEPS[$op];
        if ($rental['status'] !== $from) {
            return ['success' => false, 'error' => 'rent.err_step'];
        }

        if ($op === 'reject' && (mb_strlen($note) < 5 || mb_strlen($note) > 500)) {
            return ['success' => false, 'error' => 'adm.err_reason'];
        }
        if ($op === 'returned' && $keepPartOfDeposit && (mb_strlen($note) < 5 || mb_strlen($note) > 500)) {
            return ['success' => false, 'error' => 'rent.err_deposit_note'];
        }
        // Amount kept: from 1 XOF to the whole deposit
        if ($op === 'returned' && $keepPartOfDeposit && ($amountKept < 1 || $amountKept > (int) $rental['deposit'])) {
            return ['success' => false, 'error' => 'rent.err_kept_amount', 'values' => ['max' => money((int) $rental['deposit'])]];
        }

        if ($op === 'approve') {
            // Check again: another bride may have been accepted for the same days meanwhile
            $this->rentals->begin();
            try {
                $locked = $this->rentals->lockProduct((int) $rental['product_id']);
                $taken = $this->rentals->countOverlaps((int) $rental['product_id'], $rental['start_date'], $rental['end_date'], $rentalId, true);
                if ($locked === null || $taken >= (int) $locked['stock']) {
                    $this->rentals->rollback();
                    return ['success' => false, 'error' => 'rent.err_taken_seller'];
                }
                $ok = $this->rentals->changeStatus($rentalId, $from, $to, $note !== '' ? $note : null);
                $this->rentals->commit();
            } catch (Throwable $e) {
                $this->rentals->rollback();
                error_log('Approve borrowing failed: ' . $e->getMessage());
                return ['success' => false, 'error' => 'reg.err_server'];
            }
        } elseif ($op === 'picked_up') {
            $ok = $this->rentals->changeStatus($rentalId, $from, $to, null, 'held');
        } elseif ($op === 'returned') {
            // Returned: the seller's money becomes due
            $kept = $keepPartOfDeposit ? $amountKept : 0;
            $depositStatus = ((int) $rental['deposit'] - $kept) > 0 ? 'held' : ($kept > 0 ? 'partly_kept' : 'refunded');
            $ok = $this->rentals->changeStatus($rentalId, $from, $to, $note !== '' ? $note : null, $depositStatus, $kept, 'due');
        } else {
            $ok = $this->rentals->changeStatus($rentalId, $from, $to, $note);
        }
        return $ok ? ['success' => true, 'ref' => $rental['rental_ref'], 'op' => $op]
                   : ['success' => false, 'error' => 'rent.err_step'];
    }

    // The bride cancels her request (only before paying)
    public function cancel(int $rentalId, int $borrowerId): array
    {
        $rental = $this->rentals->getBorrowerRental($rentalId, $borrowerId);
        if ($rental === null) {
            return ['success' => false, 'error' => 'rent.err_not_found'];
        }
        if (!in_array($rental['status'], ['requested', 'approved'], true)
            || !$this->rentals->changeStatus($rentalId, $rental['status'], 'cancelled')) {
            return ['success' => false, 'error' => 'rent.err_cancel'];
        }
        return ['success' => true, 'ref' => $rental['rental_ref']];
    }
}
