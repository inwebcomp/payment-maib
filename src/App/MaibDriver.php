<?php

namespace InWeb\Payment\Maib;

use InWeb\Payment\Contracts\Payable;
use InWeb\Payment\Drivers\Driver;
use InWeb\Payment\Models\Payment;

class MaibDriver extends Driver
{
    // @todo Move to config
    const CLIENT_HANDLER = 'https://maib.ecommerce.md:21443/ecomm/ClientHandler';

    public function createPayment(Payment $payment, $successPath, $cancelPath, $buttonInfo = null)
    {
        $transactionId = optional($payment->detail)['transaction_id'];

        if ($transactionId) {
            return;
        }

        $transactionId = $this->registerTransaction($payment);

        $payment->setDetail([
            'transaction_id' => $transactionId,
            'gateway_url' => self::CLIENT_HANDLER . '?trans_id=' . urlencode($transactionId),
        ]);

        $payment->save();
    }

    public function getPaymentInfo(Payment $payment)
    {
        // @todo Get maib transaction info

        return [
            'result' => 'ok',
            'card_number' => '5***********2372',
        ];
    }

    private function registerTransaction(Payment $payment): string
    {
        // @todo Register maib transaction

        return 'rEsfhyIk8s9ypxkcS9fj/3C8FqA=';
    }
}
