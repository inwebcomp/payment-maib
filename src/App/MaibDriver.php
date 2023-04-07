<?php

namespace InWeb\Payment\Maib;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use InWeb\Payment\Contracts\Driver\Revertable;
use InWeb\Payment\Contracts\Driver\DayShouldBeClosed;
use InWeb\Payment\Drivers\Driver;
use InWeb\Payment\Models\Payment;
use JetBrains\PhpStorm\NoReturn;
use Maib\MaibApi\MaibClient;

class MaibDriver extends Driver implements Revertable, DayShouldBeClosed
{
    public mixed $config = [
        'merchant_handler' => '',
        'client_handler'   => '',
        'certificate'      => [
            'cert_file' => '',
            'key_file'  => '',
            'password'  => '',
        ],
        'language'         => 'en',
        'method'           => 'sms',
    ];

    protected MaibClient $client;

    #[NoReturn] public function __construct()
    {
        $this->config = array_merge($this->config, config('services.payment-maib'));

        $options = [
            'base_uri' => $this->config['merchant_handler'],
            'debug'    => false,
            'verify'   => true,
            'cert'     => [$this->config['certificate']['cert_file'], $this->config['certificate']['password']],
            'ssl_key'  => $this->config['certificate']['key_file'],
            'config'   => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]
            ]
        ];

        $log = \Log::getLogger();
        $stack = HandlerStack::create();
        $stack->push(Middleware::log($log, new MessageFormatter(MessageFormatter::DEBUG)));

        $options['handler'] = $stack;

        $guzzleClient = new Client($options);

        $this->client = new MaibClient($guzzleClient);
    }

    /**
     * @throws \Exception
     */
    public function createPayment(Payment $payment, $successPath, $cancelPath, $buttonInfo = null): Payment
    {
        $transactionId = optional($payment->detail)['transaction_id'];

        if ($transactionId) {
            return Payment::where('transaction_id', $transactionId)->firstOrFail();
        }

        $transactionId = $this->registerTransaction($payment);

        if (!$transactionId) {
            throw new \Exception('Transaction registration failed');
        }

        $payment->setDetail([
            'transaction_id' => $transactionId,
            'gateway_url'    => $this->config['client_handler'] . '?trans_id=' . urlencode($transactionId),
        ]);

        $payment->save();

        return $payment;
    }

    private function getTransactionInfo(Payment $payment): array
    {
        return $this->client->getTransactionResult($payment->detail['transaction_id'], '127.0.0.1');
    }

    public function isSuccessfulPayment(Payment $payment): bool
    {
        $transaction = $this->getTransactionInfo($payment);

        return $transaction['status'] === 'OK';
    }

    private function registerTransaction(Payment $payment): string
    {
        $transactionId = null;

        if ($this->config['method'] === 'sms') {
            $transactionId = $this->registerSmsTransaction($payment);
        }

        // @todo Register maib transaction

        return $transactionId;
    }

    private function prepareTransactionInfo(Payment $payment): array
    {
        // The currency of the transaction - is the 3 digits code of currency from ISO 4217
        $currency = 498; // MDL
        // @todo Support multiple currencies in payments

        return [
            'amount'       => $payment->amount,
            'currency'     => $currency,
            'clientIpAddr' => '127.0.0.1', // The client IP address @todo
            'description'  => $payment->payable->getPaymentDetail($payment->payer),
            'lang'         => $this->config['language'] ?? 'en',
            'redirect_url' => $this->config['client_handler'] . '?trans_id=',
        ];
    }

    private function registerSmsTransaction(Payment $payment): string
    {
        $transactionInfo = $this->prepareTransactionInfo($payment);

        $transaction = $this->client->registerSmsTransaction(
            $transactionInfo['amount'],
            $transactionInfo['currency'],
            $transactionInfo['clientIpAddr'],
            $transactionInfo['description'],
            $transactionInfo['lang'],
        );

        return $transaction['TRANSACTION_ID'];
    }

    public function revertTransaction(Payment $payment, ?float $amount): bool
    {
        $response = $this->client->revertTransaction($payment->detail['transaction_id'], $amount ?? $payment->amount);

        return $response['RESULT'] === 'OK';
    }

    public function closeDay(): void
    {
        $this->client->closeDay();
    }
}
