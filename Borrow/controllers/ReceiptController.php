<?php
require_once dirname(__DIR__) . '/classes/ReceiptClass.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

class ReceiptController
{
    private const METHODS = ['airtel_money' => 'Airtel Money', 'zamani_cash' => 'Zamani Cash', 'moov_money' => 'Moov Money',
                             'mynita' => 'MyNita', 'amanata' => 'Amanata', 'wave' => 'Wave'];

    private ReceiptClass $receipts;
    private Mailer $mailer;

    public function __construct()
    {
        $this->receipts = new ReceiptClass();
        $this->mailer = new Mailer();
    }

    public function getReceipt(string $ref): ?array { return $this->receipts->getByRef($ref); }
    public function getRefsFor(string $type, int $id, int $userId): array { return $this->receipts->getRefsFor($type, $id, $userId); }
    public function getAll(int $limit = 100): array { return $this->receipts->getAll($limit); }

    // After a successful payment
    public function forPayment(int $paymentId): void
    {
        $payment = $this->receipts->getPayment($paymentId);
        if ($payment === null || $payment['status'] !== 'succeeded' || $this->receipts->existsForPayment($paymentId)) {
            return;
        }
        $buyer = $this->receipts->getUser((int) $payment['user_id']);
        $refund = $payment['failure_reason'] !== null && str_starts_with($payment['failure_reason'], 'REFUND DUE');
        $method = self::METHODS[$payment['method']] ?? $payment['method'];
        $date = date('d/m/Y H:i', strtotime($payment['paid_at'] ?? 'now'));
        $base = ['kind' => $refund ? 'refund' : 'payment', 'payment_id' => $paymentId,
                 'payable_type' => $payment['payable_type'], 'payable_id' => (int) $payment['payable_id']];

        if ($refund) {
            $rows = [['Référence du paiement', 'Payment reference', e($payment['transaction_ref'])],
                     ['Montant reçu', 'Amount received', money((int) $payment['amount'])],
                     ['Moyen de paiement', 'Payment method', e($method)], ['Date', 'Date', $date]];
            $this->save($base + ['recipient' => 'buyer', 'user' => $buyer, 'amount' => (int) $payment['amount'],
                'subject' => 'BridalCycle – Remboursement / Refund ' . $payment['transaction_ref'],
                'html' => $this->card('Remboursement à venir', 'Refund on its way', $rows,
                    'La tenue a été prise juste avant votre paiement. BridalCycle va vous rembourser.',
                    'The outfit was taken just before your payment arrived. BridalCycle will refund you.')]);
            $this->save($base + ['recipient' => 'business', 'user' => null, 'amount' => (int) $payment['amount'],
                'subject' => 'REMBOURSEMENT À FAIRE / REFUND DUE – ' . $payment['transaction_ref'],
                'html' => $this->card('Remboursement à faire', 'Refund to make', array_merge($rows,
                    [['Cliente', 'Customer', e($buyer['full_name']) . ' · ' . e($payment['msisdn'])]]),
                    'Remboursez la cliente sur le numéro ci-dessus.', 'Refund the customer on the number above.')]);
            return;
        }

        if ($payment['payable_type'] === 'feature') {
            $this->featureReceipts($payment, $buyer, $method, $date, $base);
        } elseif ($payment['payable_type'] === 'order') {
            $this->orderReceipts($payment, $buyer, $method, $date, $base);
        } else {
            $this->rentalReceipts($payment, $buyer, $method, $date, $base);
        }
    }

    private function orderReceipts(array $payment, array $buyer, string $method, string $date, array $base): void
    {
        $order = $this->receipts->getOrder((int) $payment['payable_id']);
        $items = $this->receipts->getOrderItems((int) $order['id']);
        $head = [['Commande', 'Order', e($order['order_ref'])], ['Paiement', 'Payment', e($payment['transaction_ref'])],
                 ['Date', 'Date', $date], ['Moyen de paiement', 'Payment method', e($method) . ' · ' . e($payment['msisdn'])]];

        // 1. The bride
        $rows = $head;
        foreach ($items as $i) {
            $rows[] = [e($i['title']) . ' × ' . (int) $i['quantity'], '', money((int) $i['unit_price'] * (int) $i['quantity'])];
        }
        if ((int) $order['delivery_fee'] > 0) {
            $rows[] = ['Livraison BridalCycle', 'BridalCycle delivery', money((int) $order['delivery_fee'])];
        }
        $rows[] = ['<strong>Total payé</strong>', '<strong>Total paid</strong>', '<strong>' . money((int) $payment['amount']) . '</strong>'];
        $this->save($base + ['recipient' => 'buyer', 'user' => $buyer, 'amount' => (int) $payment['amount'],
            'subject' => 'BridalCycle – Reçu de paiement / Payment receipt ' . $order['order_ref'],
            'html' => $this->card('Paiement reçu', 'Payment received', $rows,
                'Merci ! La vendeuse va vous contacter pour le retrait ou la livraison.',
                'Thank you! The seller will contact you for the pick-up or the delivery.')]);

        // 2. Each seller: only HER items and HER share
        $bySeller = [];
        foreach ($items as $i) {
            $bySeller[(int) $i['seller_id']][] = $i;
        }
        $commissionTotal = 0;
        $payoutTotal = 0;
        foreach ($bySeller as $sellerId => $list) {
            $seller = $this->receipts->getUser($sellerId);
            $rows = [['Commande', 'Order', e($order['order_ref'])], ['Date', 'Date', $date],
                     ['Cliente', 'Customer', e(public_name($buyer['full_name'], null, $buyer['hide_name'] ?? 0, 'buyer')) . ' · ' . e($order['phone'])],
                     ['Livraison', 'Delivery', $order['delivery_method'] === 'delivery' ? 'Livraison / Delivery: ' . e($order['delivery_address']) : 'Retrait / Pick-up']];
            $payout = 0;
            foreach ($list as $i) {
                $rows[] = [e($i['title']) . ' × ' . (int) $i['quantity'], '', money((int) $i['unit_price'] * (int) $i['quantity'])];
                $rows[] = ['Commission BridalCycle', 'BridalCycle commission', '− ' . money((int) $i['commission'])];
                $payout += (int) $i['seller_payout'];
                $payoutTotal += (int) $i['seller_payout'];
                $commissionTotal += (int) $i['commission'];
            }
            $rows[] = ['<strong>Vous recevrez</strong>', '<strong>You will receive</strong>', '<strong>' . money($payout) . '</strong>'];
            $this->save($base + ['recipient' => 'seller', 'user' => $seller, 'amount' => $payout,
                'subject' => 'BridalCycle – Tenue payée / Outfit paid ' . $order['order_ref'],
                'html' => $this->card('Votre tenue est payée', 'Your outfit is paid', $rows,
                    'La cliente a payé BridalCycle. Contactez-la pour lui remettre la tenue ; BridalCycle vous versera votre part ensuite.',
                    'The customer paid BridalCycle. Contact her to hand over the outfit; BridalCycle will then pay you your share.')]);
        }

        // 3. BridalCycle's copy
        $rows = array_merge($head, [['Cliente', 'Customer', e($buyer['full_name']) . ' · ' . e($buyer['email'])],
            ['Montant reçu', 'Amount received', money((int) $payment['amount'])],
            ['Commission BridalCycle', 'BridalCycle commission', money($commissionTotal)],
            ['Frais de livraison', 'Delivery fee', money((int) $order['delivery_fee'])],
            ['À verser aux vendeuses', 'To pay to sellers', money($payoutTotal)]]);
        $this->save($base + ['recipient' => 'business', 'user' => null, 'amount' => (int) $payment['amount'],
            'subject' => 'Paiement reçu / Payment received – ' . $order['order_ref'] . ' – ' . money((int) $payment['amount']),
            'html' => $this->card('Paiement reçu', 'Payment received', $rows, '', '')]);
    }

    // A seller paid to feature her outfit
    private function featureReceipts(array $payment, array $seller, string $method, string $date, array $base): void
    {
        $p = $this->receipts->getProduct((int) $payment['payable_id']);
        $until = $p['featured_until'] ? date('d/m/Y H:i', strtotime($p['featured_until'])) : '—';
        $rows = [['Tenue', 'Outfit', e($p['title'])], ['Mise en avant jusqu\'au', 'Featured until', $until],
                 ['Paiement', 'Payment', e($payment['transaction_ref'])], ['Date', 'Date', $date],
                 ['Moyen de paiement', 'Payment method', e($method) . ' · ' . e($payment['msisdn'])],
                 ['<strong>Total payé</strong>', '<strong>Total paid</strong>', '<strong>' . money((int) $payment['amount']) . '</strong>']];
        $this->save($base + ['recipient' => 'seller', 'user' => $seller, 'amount' => (int) $payment['amount'],
            'subject' => 'BridalCycle – Mise en avant / Featured outfit ' . $p['title'],
            'html' => $this->card('Votre tenue est mise en avant', 'Your outfit is featured', $rows,
                'Votre tenue apparaît en premier dans la boutique avec le badge « En vedette ».',
                'Your outfit now appears first in the shop with the "Featured" badge.')]);
        $this->save($base + ['recipient' => 'business', 'user' => null, 'amount' => (int) $payment['amount'],
            'subject' => 'Mise en avant payée / Featured outfit paid – ' . money((int) $payment['amount']),
            'html' => $this->card('Mise en avant payée', 'Featured outfit paid', array_merge($rows,
                [['Vendeuse', 'Seller', e($seller['shop_name'] ?: $seller['full_name']) . ' · ' . e($seller['email'])]]), '', '')]);
    }

    private function rentalReceipts(array $payment, array $buyer, string $method, string $date, array $base): void
    {
        $r = $this->receipts->getRental((int) $payment['payable_id']);
        $seller = $this->receipts->getUser((int) $r['seller_id']);
        $dates = date('d/m/Y', strtotime($r['start_date'])) . ' → ' . date('d/m/Y', strtotime($r['end_date'])) . ' (' . (int) $r['days'] . ' j / days)';
        $head = [['Location', 'Borrowing', e($r['rental_ref'])], ['Tenue', 'Outfit', e($r['title'])], ['Dates', 'Dates', $dates],
                 ['Paiement', 'Payment', e($payment['transaction_ref'])], ['Date', 'Date', $date],
                 ['Moyen de paiement', 'Payment method', e($method) . ' · ' . e($payment['msisdn'])]];

        $this->save($base + ['recipient' => 'buyer', 'user' => $buyer, 'amount' => (int) $payment['amount'],
            'subject' => 'BridalCycle – Reçu de location / Borrowing receipt ' . $r['rental_ref'],
            'html' => $this->card('Paiement reçu', 'Payment received', array_merge($head, [
                ['Location', 'Rent', money((int) $r['rent_total'])],
                ['Caution (rendue au retour)', 'Deposit (given back after return)', money((int) $r['deposit'])],
                ['<strong>Total payé</strong>', '<strong>Total paid</strong>', '<strong>' . money((int) $payment['amount']) . '</strong>']]),
                'Vos dates sont confirmées. Contactez la vendeuse pour récupérer la tenue.',
                'Your dates are confirmed. Contact the seller to pick up the outfit.')]);

        $this->save($base + ['recipient' => 'seller', 'user' => $seller, 'amount' => (int) $r['seller_payout'],
            'subject' => 'BridalCycle – Location payée / Borrowing paid ' . $r['rental_ref'],
            'html' => $this->card('Location payée', 'Borrowing paid', array_merge($head, [
                ['Cliente', 'Customer', e(public_name($buyer['full_name'], null, $buyer['hide_name'] ?? 0, 'buyer')) . ' · ' . e($r['phone'])],
                ['Location', 'Rent', money((int) $r['rent_total'])],
                ['Commission BridalCycle', 'BridalCycle commission', '− ' . money((int) $r['commission'])],
                ['<strong>Vous recevrez</strong>', '<strong>You will receive</strong>', '<strong>' . money((int) $r['seller_payout']) . '</strong>'],
                ['Caution', 'Deposit', money((int) $r['deposit']) . ' – gardée par BridalCycle / held by BridalCycle']]),
                'Remettez la tenue à la cliente. Votre part est versée après le retour de la tenue.',
                'Hand over the outfit to the customer. Your share is paid after the outfit comes back.')]);

        $this->save($base + ['recipient' => 'business', 'user' => null, 'amount' => (int) $payment['amount'],
            'subject' => 'Paiement reçu / Payment received – ' . $r['rental_ref'] . ' – ' . money((int) $payment['amount']),
            'html' => $this->card('Paiement reçu (location)', 'Payment received (borrowing)', array_merge($head, [
                ['Cliente', 'Customer', e($buyer['full_name']) . ' · ' . e($buyer['email'])],
                ['Commission BridalCycle', 'BridalCycle commission', money((int) $r['commission'])],
                ['À verser à la vendeuse', 'To pay to the seller', money((int) $r['seller_payout'])],
                ['Caution détenue', 'Deposit held', money((int) $r['deposit'])]]), '', '')]);
    }

    // BridalCycle paid a seller: receipt for the seller and for BridalCycle
    public function forPayout(string $type, int $id, int $sellerId, int $amount, string $how): void
    {
        $seller = $this->receipts->getUser($sellerId);
        $ref = $type === 'order' ? $this->receipts->getOrder($id)['order_ref'] : $this->receipts->getRental($id)['rental_ref'];
        $rows = [[$type === 'order' ? 'Commande' : 'Location', $type === 'order' ? 'Order' : 'Borrowing', e($ref)],
                 ['Vendeuse', 'Seller', e($seller['shop_name'] ?: $seller['full_name'])],
                 ['Date', 'Date', date('d/m/Y H:i')], ['Versé par', 'Paid with', e($how)],
                 ['<strong>Montant versé</strong>', '<strong>Amount paid</strong>', '<strong>' . money($amount) . '</strong>']];
        $base = ['kind' => 'payout', 'payment_id' => null, 'payable_type' => $type, 'payable_id' => $id, 'amount' => $amount];
        $this->save($base + ['recipient' => 'seller', 'user' => $seller,
            'subject' => 'BridalCycle – Versement / Payout ' . $ref . ' – ' . money($amount),
            'html' => $this->card('BridalCycle vous a payée', 'BridalCycle paid you', $rows,
                'Merci de vendre avec BridalCycle.', 'Thank you for selling with BridalCycle.')]);
        $this->save($base + ['recipient' => 'business', 'user' => null,
            'subject' => 'Versement effectué / Payout made – ' . $ref . ' – ' . money($amount),
            'html' => $this->card('Versement effectué', 'Payout made', $rows, '', '')]);
    }

    // BridalCycle gave the deposit back to the bride after the return
    public function forDepositBack(int $rentalId, string $how): void
    {
        $r = $this->receipts->getRental($rentalId);
        $bride = $this->receipts->getUser((int) $r['borrower_id']);
        $back = (int) $r['deposit'] - (int) $r['deposit_kept'];
        $rows = [['Location', 'Borrowing', e($r['rental_ref'])], ['Tenue', 'Outfit', e($r['title'])],
                 ['Caution payée', 'Deposit paid', money((int) $r['deposit'])]];
        if ((int) $r['deposit_kept'] > 0) {
            $rows[] = ['Gardé pour dommage', 'Kept for damage', '− ' . money((int) $r['deposit_kept'])];
        }
        $rows = array_merge($rows, [['Date', 'Date', date('d/m/Y H:i')], ['Versé par', 'Paid with', e($how)],
                 ['<strong>Caution rendue</strong>', '<strong>Deposit given back</strong>', '<strong>' . money($back) . '</strong>']]);
        $base = ['kind' => 'refund', 'payment_id' => null, 'payable_type' => 'rental', 'payable_id' => $rentalId, 'amount' => $back];
        $this->save($base + ['recipient' => 'buyer', 'user' => $bride,
            'subject' => 'BridalCycle – Caution rendue / Deposit given back ' . $r['rental_ref'],
            'html' => $this->card('Votre caution est rendue', 'Your deposit is given back', $rows,
                'Merci d\'avoir rendu la tenue. Tous nos vœux de bonheur !', 'Thank you for returning the outfit. We wish you every happiness!')]);
        $this->save($base + ['recipient' => 'business', 'user' => null,
            'subject' => 'Caution rendue / Deposit given back – ' . $r['rental_ref'] . ' – ' . money($back),
            'html' => $this->card('Caution rendue', 'Deposit given back', array_merge($rows,
                [['Cliente', 'Customer', e($bride['full_name']) . ' · ' . e($r['phone'])]]), '', '')]);
    }

    // BridalCycle refunded a payment (outfit taken just before)
    public function forRefundMade(array $payment, string $how): void
    {
        $bride = $this->receipts->getUser((int) $payment['user_id']);
        $rows = [['Paiement remboursé', 'Refunded payment', e($payment['transaction_ref'])],
                 ['Date', 'Date', date('d/m/Y H:i')], ['Versé par', 'Paid with', e($how)],
                 ['<strong>Montant remboursé</strong>', '<strong>Amount refunded</strong>', '<strong>' . money((int) $payment['amount']) . '</strong>']];
        $base = ['kind' => 'refund', 'payment_id' => (int) $payment['id'], 'payable_type' => $payment['payable_type'],
                 'payable_id' => (int) $payment['payable_id'], 'amount' => (int) $payment['amount']];
        $this->save($base + ['recipient' => 'buyer', 'user' => $bride,
            'subject' => 'BridalCycle – Remboursement effectué / Refund made ' . $payment['transaction_ref'],
            'html' => $this->card('Vous êtes remboursée', 'You have been refunded', $rows,
                'Nous sommes désolés que la tenue n\'ait plus été disponible.', 'We are sorry the outfit was no longer available.')]);
        $this->save($base + ['recipient' => 'business', 'user' => null,
            'subject' => 'Remboursement effectué / Refund made – ' . $payment['transaction_ref'] . ' – ' . money((int) $payment['amount']),
            'html' => $this->card('Remboursement effectué', 'Refund made', array_merge($rows,
                [['Cliente', 'Customer', e($bride['full_name']) . ' · ' . e($payment['msisdn'])]]), '', '')]);
    }

    // Save the receipt, then try to email it
    private function save(array $r): void
    {
        $email = $r['user']['email'] ?? BUSINESS_EMAIL;
        $name  = $r['user']['full_name'] ?? 'BridalCycle';
        $ref   = 'RC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $html  = str_replace('{{REF}}', $ref, $r['html']);
        $sent  = $this->mailer->send($email, $name, $r['subject'], $html);
        $this->receipts->addReceipt([
            'receipt_ref' => $ref, 'kind' => $r['kind'], 'payment_id' => $r['payment_id'],
            'payable_type' => $r['payable_type'], 'payable_id' => $r['payable_id'], 'recipient' => $r['recipient'],
            'user_id' => isset($r['user']['id']) ? (int) $r['user']['id'] : null, 'email' => $email,
            'subject' => $r['subject'], 'body_html' => $html, 'amount' => $r['amount'],
            'email_status' => $sent['status'], 'email_error' => $sent['error'],
        ]);
    }

    // The receipt itself
    private function card(string $titleFr, string $titleEn, array $rows, string $noteFr, string $noteEn): string
    {
        $lines = '';
        foreach ($rows as [$fr, $en, $value]) {
            $label = $en !== '' ? $fr . ' <span style="color:#8a7a72">/ ' . $en . '</span>' : $fr;
            $lines .= '<tr><td style="padding:9px 0;border-bottom:1px solid #f6efe4;color:#5b4a48">' . $label . '</td>'
                    . '<td style="padding:9px 0;border-bottom:1px solid #f6efe4;text-align:right;color:#2a1d1f">' . $value . '</td></tr>';
        }
        $note = $noteFr !== '' ? '<p style="margin:22px 0 0;color:#5b4a48;font-size:14px">' . $noteFr . '<br><span style="color:#8a7a72">' . $noteEn . '</span></p>' : '';
        return '<div style="max-width:560px;margin:0 auto;background:#fdfaf5;border:1px solid #e9dcc8;font-family:Helvetica,Arial,sans-serif">'
             . '<div style="background:#3d0d19;color:#d8c193;text-align:center;padding:22px;font-family:Georgia,serif;font-size:28px;font-style:italic">BridalCycle</div>'
             . '<div style="padding:28px 30px">'
             . '<p style="margin:0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#b08d4a">Reçu / Receipt {{REF}}</p>'
             . '<h1 style="margin:6px 0 18px;font-family:Georgia,serif;font-weight:normal;color:#5a1526;font-size:26px">' . $titleFr
             . ' <span style="color:#8a7a72;font-size:18px">/ ' . $titleEn . '</span></h1>'
             . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $lines . '</table>' . $note
             . '</div><div style="padding:14px 30px;border-top:1px solid #e9dcc8;color:#8a7a72;font-size:12px;text-align:center">'
             . 'BridalCycle · Niamey, Niger · ' . e(CURRENCY) . '</div></div>';
    }
}
