<?php

declare(strict_types=1);

namespace RPay\SDK\Tests;

use PHPUnit\Framework\TestCase;
use RPay\SDK\RPayClient;
use RPay\SDK\RPayConstants;
use RPay\SDK\RPayWebhooks;

final class RPayClientTest extends TestCase
{
    public function testCatalogsAndRoute(): void
    {
        $this->assertCount(15, RPayConstants::BANK_CATALOG);
        $this->assertCount(18, RPayConstants::USDT_NETWORKS);

        $vcb = RPayConstants::resolveBank('vietcombank');
        $this->assertNotNull($vcb);
        $this->assertSame('VCB', $vcb['code']);
        $this->assertSame('970436', $vcb['bin']);

        $bsc = RPayConstants::resolveUsdtNetwork('bsc');
        $this->assertNotNull($bsc);
        $this->assertSame('BEP20', $bsc['network']);

        $client = new RPayClient(['webhookSecret' => 'whsec_php_test']);
        $route = $client->route([
            'provider' => '9ROUTER',
            'security' => 'HMAC_256',
            'coalesced' => '498.310 req',
        ]);
        $this->assertTrue($route['success']);
        $this->assertSame(15, $route['supportedBanks']);
        $this->assertSame(18, $route['supportedUsdtNetworks']);
        $this->assertSame(64, strlen((string)$route['signature']));
    }

    public function testMultiDomainPayLsgdAndMatch(): void
    {
        $seenDomains = [];

        $transport = static function (string $method, string $url, array $headers) use (&$seenDomains): array {
            $seenDomains[] = $headers['X-Site-Domain'] ?? '';
            if (strpos($url, '/api/public/bank-invoices') !== false && $method === 'POST') {
                return [
                    'status' => 'success',
                    'data' => [
                        'invoiceId' => 'inv_php_1',
                        'invoiceCode' => 'RPINV_PHP_1',
                        'orderId' => 'DH2002',
                        'status' => 'pending',
                        'amountVnd' => 300000,
                        'currency' => 'VND',
                        'transferContent' => 'RPAY DH2002',
                        'paymentUrl' => 'https://pay.shopphp.vn/pay/RPINV_PHP_1',
                        'beneficiary' => [
                            'bankCode' => 'ACB',
                            'bankBin' => '970416',
                            'accountNumber' => '88889999',
                            'accountHolder' => 'CONG TY PHP',
                        ],
                    ],
                ];
            }
            if (strpos($url, '/api/public/usdt-invoices') !== false && $method === 'POST') {
                return [
                    'status' => 'success',
                    'data' => [
                        'id' => 'usdt_php_1',
                        'invoiceCode' => 'USINV_PHP_1',
                        'txCode' => 'USTX_PHP_1',
                        'orderId' => 'DH2002',
                        'status' => 'pending',
                        'amountUsdt' => 45,
                        'token' => 'USDT',
                        'network' => 'TRC20',
                        'walletAddress' => 'TPhpWallet123456',
                    ],
                ];
            }
            if (strpos($url, '/historyapiacbv2/') !== false) {
                return [
                    'status' => 'success',
                    'transactions' => [
                        [
                            'transactionID' => 'PHP_BANK_TX_1',
                            'amount' => 300000,
                            'description' => 'CK RPAY-DH2002',
                            'transactionDate' => '05/11/2026 21:10:00',
                            'type' => 'IN',
                        ],
                    ],
                ];
            }
            if (strpos($url, '/api/GetInvoiceHistory') !== false) {
                return [
                    'status' => 'success',
                    'data' => [
                        [
                            'trans_id' => 'USINV_PHP_1',
                            'request_id' => 'DH2002',
                            'amount' => 45,
                            'received' => 45,
                            'status' => 'completed',
                            'network' => 'TRC20',
                            'token' => 'USDT',
                            'address' => 'TPhpWallet123456',
                            'transaction_id' => '0xhash_php_999',
                        ],
                    ],
                ];
            }
            return ['status' => 'ok'];
        };

        $base = new RPayClient([
            'domain' => 'rpay.vn',
            'bankToken' => 'tok_php_1',
            'bankCode' => 'ACB',
            'usdtApiKey' => 'ukey_1',
            'usdtSecretKey' => 'usec_1',
            'transport' => $transport,
        ]);

        $tenant = $base->forDomain('pay.shopphp.vn');

        $bankInv = $tenant->pay(['amount' => 300000, 'orderId' => 'DH2002']);
        $this->assertSame('bank', $bankInv['channel']);
        $this->assertSame('RPINV_PHP_1', $bankInv['invoiceCode']);

        $usdtInv = $tenant->pay(['channel' => 'usdt', 'amount' => 45, 'network' => 'TRC20', 'orderId' => 'DH2002']);
        $this->assertSame('usdt', $usdtInv['channel']);
        $this->assertSame('TPhpWallet123456', $usdtInv['recipientAddress']);

        $lsgd = $tenant->lsgd(['channel' => 'all']);
        $this->assertSame(2, $lsgd['total']);

        $matched = $tenant->matchPayment($lsgd['transactions'], ['orderCode' => 'DH2002']);
        $this->assertCount(2, $matched);
        $this->assertSame('pay.shopphp.vn', $seenDomains[0]);
    }

    public function testWebhookVerification(): void
    {
        $secret = 'whsec_php_secret';
        $payload = [
            'eventId' => 'evt_php_1',
            'event' => 'bank.invoice.paid',
            'invoiceCode' => 'RPINV_PHP_1',
            'orderId' => 'DH2002',
            'status' => 'paid',
            'amountVnd' => 300000,
        ];
        $sig = RPayWebhooks::signCanonicalPayload($payload, $secret);
        $res = RPayWebhooks::verifyWebhook($payload, ['X-RPay-Signature' => $sig], $secret);

        $this->assertTrue($res['valid']);
        $this->assertTrue($res['normalizedEvent']['isPaid']);
        $this->assertSame('DH2002', $res['normalizedEvent']['orderId']);
    }
}
