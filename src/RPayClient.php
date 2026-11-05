<?php

declare(strict_types=1);

namespace RPay\SDK;

/**
 * Official RPAY Payment & Banking Client for PHP (Laravel, WordPress, Pure PHP).
 * Supports Unified Bank (VietQR VND) + Crypto (USDT/USDC), Multi-Domain White-Label, LSGD & Webhooks.
 */
class RPayClient
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var string */
    private string $baseUrl;

    /** @var string */
    private string $siteDomain;

    /** @var callable|null */
    private $transport;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $rawDomain = self::firstNonEmpty(
            (string)($config['baseUrl'] ?? ''),
            (string)($config['domain'] ?? ''),
            self::env('RPAY_BASE_URL'),
            self::env('RPAY_DOMAIN'),
            RPayConstants::DEFAULT_BASE_URL
        );

        [$baseUrl, $detectedHost] = self::normalizeDomain($rawDomain);

        $this->baseUrl = $baseUrl;
        $this->siteDomain = self::firstNonEmpty(
            (string)($config['siteDomain'] ?? ''),
            self::env('RPAY_SITE_DOMAIN'),
            $detectedHost
        );

        $this->config = [
            'baseUrl' => $this->baseUrl,
            'siteDomain' => $this->siteDomain,
            'siteId' => self::firstNonEmpty((string)($config['siteId'] ?? ''), self::env('RPAY_SITE_ID')),
            'bankToken' => self::firstNonEmpty((string)($config['bankToken'] ?? ''), (string)($config['token'] ?? ''), self::env('RPAY_BANK_TOKEN'), self::env('RPAY_TOKEN')),
            'bankCode' => strtoupper(self::firstNonEmpty((string)($config['bankCode'] ?? ''), self::env('RPAY_BANK_CODE'), 'ACB')),
            'accountNumber' => self::firstNonEmpty((string)($config['accountNumber'] ?? ''), self::env('RPAY_ACCOUNT_NUMBER')),
            'password' => self::firstNonEmpty((string)($config['password'] ?? ''), self::env('RPAY_BANK_PASSWORD')),
            'usdtApiKey' => self::firstNonEmpty((string)($config['usdtApiKey'] ?? ''), (string)($config['apiKey'] ?? ''), self::env('RPAY_USDT_API_KEY'), self::env('RPAY_API_KEY')),
            'usdtSecretKey' => self::firstNonEmpty((string)($config['usdtSecretKey'] ?? ''), (string)($config['secretKey'] ?? ''), self::env('RPAY_USDT_SECRET_KEY'), self::env('RPAY_SECRET_KEY')),
            'usdtNetwork' => strtoupper(self::firstNonEmpty((string)($config['usdtNetwork'] ?? ''), self::env('RPAY_USDT_NETWORK'), 'TRC20')),
            'usdtMode' => strtolower(self::firstNonEmpty((string)($config['usdtMode'] ?? ''), self::env('RPAY_USDT_MODE'), 'public')),
            'webhookSecret' => self::firstNonEmpty((string)($config['webhookSecret'] ?? ''), self::env('RPAY_WEBHOOK_SECRET')),
            'timeout' => (int)($config['timeout'] ?? RPayConstants::DEFAULT_TIMEOUT_SECONDS),
            'headers' => is_array($config['headers'] ?? null) ? $config['headers'] : [],
            'transport' => is_callable($config['transport'] ?? null) ? $config['transport'] : null,
        ];

        $this->transport = $this->config['transport'];
    }

    /**
     * Create a child client bound to a specific domain or white-label tenant site while preserving credentials.
     *
     * @param string $domainOrUrl
     * @param array<string, mixed> $overrides
     * @return self
     */
    public function forDomain(string $domainOrUrl, array $overrides = []): self
    {
        $next = array_merge($this->config, $overrides, [
            'baseUrl' => $domainOrUrl,
            'domain' => $domainOrUrl,
            'siteDomain' => $overrides['siteDomain'] ?? '',
        ]);
        return new self($next);
    }

    /**
     * Unified Invoice Creation for BOTH Bank (VietQR VND) and Crypto (USDT/USDC 18 networks).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function pay(array $params): array
    {
        $channel = self::detectChannel(
            (string)($params['channel'] ?? ''),
            (string)($params['currency'] ?? ''),
            (string)($params['network'] ?? '')
        );

        if ($channel === 'usdt') {
            $mode = strtolower(self::firstNonEmpty((string)($params['mode'] ?? ''), (string)$this->config['usdtMode'], 'public'));
            $netSpec = RPayConstants::resolveUsdtNetwork(self::firstNonEmpty((string)($params['network'] ?? ''), (string)$this->config['usdtNetwork'], 'TRC20'));
            $netCode = $netSpec['network'] ?? 'TRC20';
            $tokenSymbol = strtoupper((string)($params['currency'] ?? $params['token'] ?? 'USDT')) === 'USDC' ? 'USDC' : 'USDT';

            if ($mode === 'stc') {
                $apiKey = self::firstNonEmpty((string)($params['apiKey'] ?? ''), (string)$this->config['usdtApiKey']);
                $secretKey = self::firstNonEmpty((string)($params['secretKey'] ?? ''), (string)$this->config['usdtSecretKey']);
                if ($apiKey === '' || $secretKey === '') {
                    throw new RPayException('Thiếu RPAY_USDT_API_KEY và RPAY_USDT_SECRET_KEY cho chế độ STC.', 'MISSING_STC_CREDENTIALS');
                }
                $raw = $this->request('POST', '/api/AddInvoice', [], null, [
                    'api_key' => $apiKey,
                    'secret_key' => $secretKey,
                    'amount' => (string)($params['amount'] ?? 0),
                    'request_id' => (string)($params['orderId'] ?? $params['request_id'] ?? ''),
                    'callback_url' => (string)($params['callbackUrl'] ?? $params['callback_url'] ?? ''),
                    'network' => $netCode,
                    'token' => $tokenSymbol,
                    'description' => (string)($params['description'] ?? ''),
                ]);
                return self::normalizeInvoice($raw, 'usdt');
            }

            $headers = [];
            $apiKey = self::firstNonEmpty((string)($params['apiKey'] ?? ''), (string)$this->config['usdtApiKey']);
            if ($apiKey !== '') {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            }
            if (!empty($params['idempotencyKey'])) {
                $headers['Idempotency-Key'] = (string)$params['idempotencyKey'];
            }

            $raw = $this->request('POST', '/api/public/usdt-invoices', [], [
                'amount' => (float)($params['amount'] ?? 0),
                'network' => $netCode,
                'token' => $tokenSymbol,
                'orderId' => (string)($params['orderId'] ?? ''),
                'description' => (string)($params['description'] ?? ''),
                'buyerName' => (string)($params['buyerName'] ?? ''),
                'buyerEmail' => (string)($params['buyerEmail'] ?? ''),
                'buyerPhone' => (string)($params['buyerPhone'] ?? ''),
                'callbackUrl' => (string)($params['callbackUrl'] ?? ''),
                'returnUrl' => (string)($params['returnUrl'] ?? ''),
                'cancelUrl' => (string)($params['cancelUrl'] ?? ''),
                'expiresInHours' => $params['expiresInHours'] ?? null,
            ], null, $headers);

            return self::normalizeInvoice($raw, 'usdt');
        }

        $token = self::firstNonEmpty((string)($params['token'] ?? ''), (string)$this->config['bankToken']);
        if ($token === '') {
            throw new RPayException('Thiếu Bank Token (cấu hình RPAY_BANK_TOKEN hoặc truyền token).', 'MISSING_BANK_TOKEN');
        }
        $headers = [];
        if (!empty($params['idempotencyKey'])) {
            $headers['Idempotency-Key'] = (string)$params['idempotencyKey'];
        }

        $raw = $this->request('POST', '/api/public/bank-invoices', [], [
            'token' => $token,
            'amount' => (float)($params['amount'] ?? 0),
            'orderId' => (string)($params['orderId'] ?? ''),
            'description' => (string)($params['description'] ?? ''),
            'buyerName' => (string)($params['buyerName'] ?? ''),
            'buyerEmail' => (string)($params['buyerEmail'] ?? ''),
            'buyerPhone' => (string)($params['buyerPhone'] ?? ''),
            'callbackUrl' => (string)($params['callbackUrl'] ?? ''),
            'returnUrl' => (string)($params['returnUrl'] ?? ''),
            'cancelUrl' => (string)($params['cancelUrl'] ?? ''),
            'expiresInHours' => $params['expiresInHours'] ?? null,
        ], null, $headers);

        return self::normalizeInvoice($raw, 'bank');
    }

    /**
     * Alias for pay().
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createInvoice(array $params): array
    {
        return $this->pay($params);
    }

    /**
     * Check invoice status for Bank (`RPINV...`) or Crypto (`USINV...` / `USTX...`).
     *
     * @param string $codeOrRef
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function check(string $codeOrRef, array $options = []): array
    {
        $ref = trim($codeOrRef);
        if ($ref === '') {
            throw new RPayException('Mã hóa đơn (invoiceCode) là bắt buộc.', 'MISSING_INVOICE_REF');
        }

        $channel = self::detectChannel(
            self::firstNonEmpty((string)($options['channel'] ?? ''), $ref),
            '',
            ''
        );

        if ($channel === 'usdt') {
            $mode = strtolower(self::firstNonEmpty((string)($options['mode'] ?? ''), (string)$this->config['usdtMode'], 'public'));
            if ($mode === 'stc') {
                $raw = $this->request('POST', '/api/GetInvoiceStatus', [], null, [
                    'api_key' => self::firstNonEmpty((string)($options['apiKey'] ?? ''), (string)$this->config['usdtApiKey']),
                    'secret_key' => self::firstNonEmpty((string)($options['secretKey'] ?? ''), (string)$this->config['usdtSecretKey']),
                    'trans_id' => $ref,
                ]);
                return self::normalizeInvoice($raw, 'usdt');
            }
            $raw = $this->request('GET', '/api/public/usdt-invoices/' . rawurlencode($ref));
            return self::normalizeInvoice($raw, 'usdt');
        }

        $token = self::firstNonEmpty((string)($options['token'] ?? ''), (string)$this->config['bankToken']);
        $raw = $this->request('GET', '/api/public/bank-invoices/' . rawurlencode($ref), ['token' => $token]);
        return self::normalizeInvoice($raw, 'bank');
    }

    /**
     * Alias for check().
     *
     * @param string $codeOrRef
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function status(string $codeOrRef, array $options = []): array
    {
        return $this->check($codeOrRef, $options);
    }

    /**
     * Fetch normalized transaction history (Lịch sử giao dịch - LSGD) from Bank, USDT, or both ('all').
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function lsgd(array $options = []): array
    {
        $channel = strtolower((string)($options['channel'] ?? ''));
        if ($channel === '') {
            $hasBank = self::firstNonEmpty((string)($options['token'] ?? ''), (string)$this->config['bankToken']) !== '';
            $hasUsdt = self::firstNonEmpty((string)($options['apiKey'] ?? ''), (string)$this->config['usdtApiKey']) !== ''
                && self::firstNonEmpty((string)($options['secretKey'] ?? ''), (string)$this->config['usdtSecretKey']) !== '';
            $channel = ($hasBank && $hasUsdt) ? 'all' : ($hasUsdt ? 'usdt' : 'bank');
        }

        $results = [];

        if ($channel === 'bank' || $channel === 'all') {
            $bankSpec = RPayConstants::resolveBank(self::firstNonEmpty((string)($options['bank'] ?? ''), (string)$this->config['bankCode'], 'ACB'));
            if ($bankSpec === null) {
                throw new RPayException('Ngân hàng không được hỗ trợ.', 'UNSUPPORTED_BANK');
            }
            $token = self::firstNonEmpty((string)($options['token'] ?? ''), (string)$this->config['bankToken']);
            $acc = self::firstNonEmpty((string)($options['accountNumber'] ?? ''), (string)$this->config['accountNumber']);
            $pwd = self::firstNonEmpty((string)($options['password'] ?? ''), (string)$this->config['password']);

            $raw = null;
            if ($acc !== '' && $pwd !== '' && $token !== '') {
                $raw = $this->request('POST', '/api/pos/transactions', [], [
                    'provider' => $bankSpec['code'],
                    'accountNumber' => $acc,
                    'password' => $pwd,
                    'token' => $token,
                ]);
            } elseif ($token !== '') {
                $raw = $this->request('GET', '/historyapi' . $bankSpec['apiSlug'] . 'v2/' . rawurlencode($token));
            } elseif ($channel === 'bank') {
                throw new RPayException('Thiếu Bank Token để lấy LSGD ngân hàng.', 'MISSING_BANK_TOKEN');
            }

            if (is_array($raw['transactions'] ?? null)) {
                foreach ($raw['transactions'] as $tx) {
                    if (is_array($tx)) {
                        $results[] = self::normalizeTx($tx, 'bank', (string)$bankSpec['code'], $acc);
                    }
                }
            }
        }

        if ($channel === 'usdt' || $channel === 'all') {
            $apiKey = self::firstNonEmpty((string)($options['apiKey'] ?? ''), (string)$this->config['usdtApiKey']);
            $secretKey = self::firstNonEmpty((string)($options['secretKey'] ?? ''), (string)$this->config['usdtSecretKey']);
            if ($apiKey !== '' && $secretKey !== '') {
                $raw = $this->request('POST', '/api/GetInvoiceHistory', [], null, [
                    'api_key' => $apiKey,
                    'secret_key' => $secretKey,
                    'status' => (string)($options['status'] ?? ''),
                    'network' => (string)($options['network'] ?? ''),
                    'limit' => isset($options['limit']) ? (string)$options['limit'] : '',
                ]);
                if (is_array($raw['data'] ?? null)) {
                    foreach ($raw['data'] as $tx) {
                        if (is_array($tx)) {
                            $results[] = self::normalizeTx($tx, 'usdt');
                        }
                    }
                }
            } elseif ($channel === 'usdt') {
                throw new RPayException('Thiếu USDT API Key / Secret Key để lấy LSGD USDT.', 'MISSING_STC_CREDENTIALS');
            }
        }

        return [
            'status' => 'success',
            'channel' => $channel,
            'total' => count($results),
            'transactions' => $results,
        ];
    }

    /**
     * Filter unified transactions matching orderCode and expectedAmount.
     *
     * @param array<int, array<string, mixed>> $transactions
     * @param array<string, mixed> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function matchPayment(array $transactions, array $criteria): array
    {
        $filterCh = strtolower((string)($criteria['channel'] ?? 'all'));
        $needleRaw = trim((string)($criteria['orderCode'] ?? ''));
        $needleUpper = strtoupper($needleRaw);
        $needleCompact = self::normalizeTransferText($needleRaw);
        $expectedAmount = (float)($criteria['expectedAmount'] ?? 0);
        $exactAmount = !empty($criteria['exactAmount']);

        $matched = [];
        foreach ($transactions as $item) {
            if ($filterCh !== 'all' && ($item['channel'] ?? '') !== $filterCh) {
                continue;
            }
            if (($item['type'] ?? 'IN') !== 'IN' || !in_array($item['status'] ?? '', ['completed', 'paid'], true)) {
                continue;
            }
            $amt = (float)($item['amount'] ?? 0);
            if ($expectedAmount > 0) {
                if ($exactAmount && $amt !== $expectedAmount) {
                    continue;
                }
                if (!$exactAmount && $amt < $expectedAmount) {
                    continue;
                }
            }
            if ($needleRaw !== '') {
                $desc = (string)($item['description'] ?? '');
                $orderId = (string)($item['orderId'] ?? '');
                $invCode = (string)($item['invoiceCode'] ?? '');
                $directHit = stripos($desc, $needleUpper) !== false
                    || strcasecmp($orderId, $needleRaw) === 0
                    || strcasecmp($invCode, $needleRaw) === 0;
                $compactHit = $needleCompact !== '' && (
                    strpos(self::normalizeTransferText($desc), $needleCompact) !== false
                    || self::normalizeTransferText($orderId) === $needleCompact
                );
                if (!$directHit && !$compactHit) {
                    continue;
                }
            }
            $matched[] = $item;
        }
        return $matched;
    }

    /**
     * Generate VietQR (for Bank) or Wallet QR metadata (for USDT/USDC).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function qr(array $options = []): array
    {
        $channel = self::detectChannel(
            (string)($options['channel'] ?? ''),
            (string)($options['currency'] ?? ''),
            (string)($options['network'] ?? '')
        );
        if ($channel === 'usdt' || (!empty($options['walletAddress']) && empty($options['bank']) && empty($options['bankBin']))) {
            $netSpec = RPayConstants::resolveUsdtNetwork(self::firstNonEmpty((string)($options['network'] ?? ''), (string)$this->config['usdtNetwork'], 'TRC20'));
            return [
                'channel' => 'usdt',
                'network' => $netSpec['network'] ?? 'TRC20',
                'currency' => strtoupper((string)($options['currency'] ?? 'USDT')),
                'walletAddress' => (string)($options['walletAddress'] ?? ''),
                'amount' => (float)($options['amount'] ?? 0),
                'qrContent' => (string)($options['walletAddress'] ?? ''),
            ];
        }

        $bankBin = trim((string)($options['bankBin'] ?? ''));
        if ($bankBin === '') {
            $bankSpec = RPayConstants::resolveBank(self::firstNonEmpty((string)($options['bank'] ?? ''), (string)$this->config['bankCode'], 'ACB'));
            $bankBin = (string)($bankSpec['bin'] ?? '970416');
        }
        $accountNo = self::firstNonEmpty((string)($options['accountNo'] ?? ''), (string)$this->config['accountNumber']);
        return $this->request('GET', '/api/qr/vietqr', [
            'bankBin' => $bankBin,
            'accountNo' => $accountNo,
            'accountName' => (string)($options['accountName'] ?? ''),
            'amount' => isset($options['amount']) ? (string)(int)$options['amount'] : '',
            'addInfo' => (string)($options['addInfo'] ?? ''),
            'template' => (string)($options['template'] ?? 'compact2'),
            'format' => (string)($options['format'] ?? 'json'),
        ]);
    }

    /**
     * Auto-route bank webhook / gateway metadata (Hero Card API).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function route(array $options = []): array
    {
        $provider = (string)($options['provider'] ?? '9ROUTER');
        $security = (string)($options['security'] ?? 'HMAC_256');
        $coalesced = (string)($options['coalesced'] ?? '498.310 req');
        $secret = self::firstNonEmpty((string)($options['webhookSecret'] ?? ''), (string)$this->config['webhookSecret']);

        $sig = null;
        if ($secret !== '' && stripos($security, 'HMAC') !== false) {
            $sig = hash_hmac('sha256', (string)json_encode([
                'provider' => $provider,
                'security' => $security,
                'coalesced' => $coalesced,
            ]), $secret);
        }

        return [
            'success' => true,
            'provider' => $provider,
            'security' => $security,
            'coalesced' => $coalesced,
            'socketLive' => true,
            'supportedBanks' => count(RPayConstants::BANK_CATALOG),
            'supportedUsdtNetworks' => count(RPayConstants::USDT_NETWORKS),
            'domain' => $this->siteDomain !== '' ? $this->siteDomain : $this->baseUrl,
            'signature' => $sig,
        ];
    }

    /**
     * Build V3 GET URL with safe `#` -> `%23` password encoding.
     *
     * @param string $bank
     * @param string $password
     * @param string $accountNumber
     * @param string $token
     * @return string
     */
    public function buildV3GetUrl(string $bank, string $password = '', string $accountNumber = '', string $token = ''): string
    {
        $spec = RPayConstants::resolveBank($bank !== '' ? $bank : (string)$this->config['bankCode']);
        if ($spec === null) {
            throw new RPayException('Ngân hàng không hỗ trợ: ' . $bank, 'UNSUPPORTED_BANK');
        }
        $pwd = self::firstNonEmpty($password, (string)$this->config['password']);
        $acc = self::firstNonEmpty($accountNumber, (string)$this->config['accountNumber']);
        $tok = self::firstNonEmpty($token, (string)$this->config['bankToken']);
        return sprintf(
            '%s/historyapi%sv3/%s/%s/%s',
            $this->baseUrl,
            $spec['apiSlug'],
            rawurlencode($pwd),
            rawurlencode($acc),
            rawurlencode($tok)
        );
    }

    /**
     * Verify incoming RPAY Webhook using configured webhookSecret.
     *
     * @param string|array<string, mixed> $rawBodyOrPayload
     * @param array<string, string> $headers
     * @param string $secretOverride
     * @return array<string, mixed>
     */
    public function verifyWebhook($rawBodyOrPayload, array $headers = [], string $secretOverride = ''): array
    {
        $secret = self::firstNonEmpty($secretOverride, (string)$this->config['webhookSecret']);
        return RPayWebhooks::verifyWebhook($rawBodyOrPayload, $headers, $secret);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, string> $query
     * @param array<string, mixed>|null $jsonBody
     * @param array<string, string>|null $formBody
     * @param array<string, string> $extraHeaders
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $jsonBody = null,
        ?array $formBody = null,
        array $extraHeaders = []
    ): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $filteredQuery = array_filter($query, static fn($v) => $v !== null && $v !== '');
        if (!empty($filteredQuery)) {
            $url .= '?' . http_build_query($filteredQuery);
        }

        $headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => RPayConstants::SDK_USER_AGENT,
        ], $this->config['headers'], $extraHeaders);

        if ($this->siteDomain !== '') {
            $headers['X-Site-Domain'] = $this->siteDomain;
        }
        if (!empty($this->config['siteId'])) {
            $headers['X-Site-ID'] = (string)$this->config['siteId'];
        }

        $bodyStr = null;
        if ($jsonBody !== null) {
            $cleanJson = array_filter($jsonBody, static fn($v) => $v !== null && $v !== '');
            $bodyStr = (string)json_encode($cleanJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = 'application/json';
        } elseif ($formBody !== null) {
            $cleanForm = array_filter($formBody, static fn($v) => $v !== null && $v !== '');
            $bodyStr = http_build_query($cleanForm);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if ($this->transport !== null) {
            return (array)call_user_func($this->transport, $method, $url, $headers, $bodyStr);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RPayException('Không thể khởi tạo cURL.', 'CURL_INIT_ERROR');
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => (int)$this->config['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($bodyStr !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        }

        $rawResp = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($rawResp === false) {
            throw new RPayException('Lỗi kết nối tới RPAY Gateway: ' . $curlErr, 'NETWORK_ERROR');
        }

        $decoded = json_decode((string)$rawResp, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => (string)$rawResp];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $msg = (string)($decoded['message'] ?? $decoded['msg'] ?? $decoded['error'] ?? ('HTTP ' . $statusCode));
            throw new RPayException($msg, (string)($decoded['code'] ?? 'HTTP_ERROR'), $statusCode, $decoded);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $raw
     * @param string $channel
     * @return array<string, mixed>
     */
    private static function normalizeInvoice(array $raw, string $channel): array
    {
        $data = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;
        $rawStatus = strtolower((string)($data['status'] ?? 'pending'));
        $status = in_array($rawStatus, ['paid', 'completed', 'reconciled', 'success', 'settled'], true)
            ? 'paid'
            : (in_array($rawStatus, ['expired', 'timeout'], true) ? 'expired' : (in_array($rawStatus, ['cancelled', 'canceled', 'failed'], true) ? 'cancelled' : 'pending'));

        if ($channel === 'bank') {
            $beneficiary = is_array($data['beneficiary'] ?? null) ? $data['beneficiary'] : [];
            $qr = is_array($data['qr'] ?? null) ? $data['qr'] : [];
            $amount = (float)($data['amountVnd'] ?? $data['amount'] ?? 0);
            $paid = (float)($data['paidAmountVnd'] ?? ($status === 'paid' ? $amount : 0));
            return [
                'channel' => 'bank',
                'invoiceId' => (string)($data['invoiceId'] ?? $data['id'] ?? $data['invoiceCode'] ?? ''),
                'invoiceCode' => (string)($data['invoiceCode'] ?? ''),
                'orderId' => (string)($data['orderId'] ?? ''),
                'status' => $status,
                'isPaid' => $status === 'paid',
                'amount' => $amount,
                'paidAmount' => $paid,
                'currency' => 'VND',
                'networkOrBank' => (string)($beneficiary['bankCode'] ?? $data['provider'] ?? 'BANK'),
                'bankBin' => (string)($beneficiary['bankBin'] ?? ''),
                'recipientAddress' => (string)($beneficiary['accountNumber'] ?? ''),
                'recipientName' => (string)($beneficiary['accountHolder'] ?? ''),
                'transferContent' => (string)($data['transferContent'] ?? ''),
                'paymentUrl' => (string)($data['paymentUrl'] ?? $data['checkoutUrl'] ?? ''),
                'qrContent' => (string)($qr['content'] ?? ''),
                'qrImageUrl' => (string)($qr['imageUrl'] ?? ''),
                'raw' => $data,
            ];
        }

        $amount = (float)($data['amountUsdt'] ?? $data['amount'] ?? 0);
        $paid = (float)($data['receivedUsdt'] ?? $data['received'] ?? ($status === 'paid' ? $amount : 0));
        $invCode = (string)($data['invoiceCode'] ?? $data['invoice_code'] ?? $data['trans_id'] ?? $data['txCode'] ?? '');
        return [
            'channel' => 'usdt',
            'invoiceId' => (string)($data['id'] ?? $data['invoiceId'] ?? $invCode),
            'invoiceCode' => $invCode,
            'txCode' => (string)($data['txCode'] ?? $data['trans_id'] ?? $invCode),
            'orderId' => (string)($data['orderId'] ?? $data['request_id'] ?? ''),
            'status' => $status,
            'isPaid' => $status === 'paid',
            'amount' => $amount,
            'paidAmount' => $paid,
            'currency' => strtoupper((string)($data['token'] ?? 'USDT')),
            'networkOrBank' => strtoupper((string)($data['network'] ?? 'TRC20')),
            'recipientAddress' => (string)($data['walletAddress'] ?? $data['address'] ?? ''),
            'paymentUrl' => (string)($data['paymentUrl'] ?? $data['checkoutUrl'] ?? $data['url_payment'] ?? ''),
            'qrImageUrl' => (string)($data['qrImageUrl'] ?? $data['qrcode'] ?? ''),
            'raw' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $rawTx
     * @param string $channel
     * @param string $bankCode
     * @param string $accountNo
     * @return array<string, mixed>
     */
    private static function normalizeTx(array $rawTx, string $channel, string $bankCode = '', string $accountNo = ''): array
    {
        if ($channel === 'bank') {
            $type = strtoupper((string)($rawTx['type'] ?? 'IN')) === 'OUT' ? 'OUT' : 'IN';
            return [
                'channel' => 'bank',
                'transactionID' => (string)($rawTx['transactionID'] ?? $rawTx['id'] ?? ''),
                'type' => $type,
                'status' => 'completed',
                'amount' => (float)($rawTx['amount'] ?? 0),
                'currency' => 'VND',
                'networkOrBank' => (string)($rawTx['bankCode'] ?? $bankCode ?: 'BANK'),
                'recipientAddress' => (string)($rawTx['accountNumber'] ?? $accountNo),
                'description' => (string)($rawTx['description'] ?? ''),
                'transactionDate' => (string)($rawTx['transactionDate'] ?? ''),
                'raw' => $rawTx,
            ];
        }

        return [
            'channel' => 'usdt',
            'transactionID' => (string)($rawTx['transaction_id'] ?? $rawTx['txHash'] ?? $rawTx['trans_id'] ?? ''),
            'invoiceCode' => (string)($rawTx['invoice_code'] ?? $rawTx['invoiceCode'] ?? $rawTx['trans_id'] ?? ''),
            'orderId' => (string)($rawTx['request_id'] ?? $rawTx['orderId'] ?? ''),
            'type' => 'IN',
            'status' => 'completed',
            'amount' => (float)($rawTx['received'] ?? $rawTx['receivedUsdt'] ?? $rawTx['amount'] ?? 0),
            'currency' => strtoupper((string)($rawTx['token'] ?? 'USDT')),
            'networkOrBank' => strtoupper((string)($rawTx['network'] ?? 'TRC20')),
            'recipientAddress' => (string)($rawTx['address'] ?? $rawTx['walletAddress'] ?? ''),
            'description' => (string)($rawTx['description'] ?? $rawTx['request_id'] ?? $rawTx['orderId'] ?? ''),
            'transactionDate' => (string)($rawTx['update_gettime'] ?? $rawTx['completedAt'] ?? ''),
            'txHash' => (string)($rawTx['transaction_id'] ?? $rawTx['txHash'] ?? ''),
            'raw' => $rawTx,
        ];
    }

    private static function detectChannel(string $codeOrChannel, string $currency, string $network): string
    {
        $ch = strtolower(trim($codeOrChannel));
        if (in_array($ch, ['usdt', 'usdc', 'crypto'], true)) {
            return 'usdt';
        }
        if (in_array($ch, ['bank', 'vnd', 'vietqr'], true)) {
            return 'bank';
        }
        $upper = strtoupper(trim($codeOrChannel));
        if (strpos($upper, 'USINV') === 0 || strpos($upper, 'USTX') === 0) {
            return 'usdt';
        }
        if (in_array(strtoupper(trim($currency)), ['USDT', 'USDC'], true)) {
            return 'usdt';
        }
        if ($network !== '' && RPayConstants::resolveUsdtNetwork($network) !== null) {
            return 'usdt';
        }
        return 'bank';
    }

    private static function normalizeTransferText(string $text): string
    {
        return (string)preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
    }

    /**
     * @param string $domainOrUrl
     * @return array{0: string, 1: string}
     */
    private static function normalizeDomain(string $domainOrUrl): array
    {
        $raw = trim($domainOrUrl);
        if ($raw === '') {
            $raw = RPayConstants::DEFAULT_BASE_URL;
        }
        if (strpos($raw, 'http://') !== 0 && strpos($raw, 'https://') !== 0) {
            $raw = 'https://' . $raw;
        }
        $raw = rtrim($raw, '/');
        $host = strtolower((string)parse_url($raw, PHP_URL_HOST));
        return [$raw, $host];
    }

    private static function env(string $key): string
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($val) ? trim($val) : '';
    }

    private static function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $v) {
            if (trim($v) !== '') {
                return trim($v);
            }
        }
        return '';
    }
}
