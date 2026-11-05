<?php

declare(strict_types=1);

namespace RPay\SDK;

/**
 * Webhook signature verification (HMAC-SHA256 Canonical, Raw Body, and STC) + Unified Event Normalizer.
 */
final class RPayWebhooks
{
    /**
     * Build sorted canonical string `key=value&...` excluding `signature`.
     *
     * @param array<string, mixed> $payload
     * @return string
     */
    public static function buildCanonicalString(array $payload): string
    {
        unset($payload['signature']);
        if (empty($payload)) {
            return '';
        }
        ksort($payload, SORT_STRING);
        $parts = [];
        foreach ($payload as $key => $val) {
            if ($val === null) {
                $strVal = '';
            } elseif (is_bool($val)) {
                $strVal = $val ? 'true' : 'false';
            } elseif (is_array($val) || is_object($val)) {
                $strVal = (string)json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $strVal = trim((string)$val);
            }
            $parts[] = $key . '=' . $strVal;
        }
        return implode('&', $parts);
    }

    /**
     * Sign payload array using canonical sorted key-value HMAC-SHA256.
     *
     * @param array<string, mixed> $payload
     * @param string $secret
     * @return string
     */
    public static function signCanonicalPayload(array $payload, string $secret): string
    {
        return hash_hmac('sha256', self::buildCanonicalString($payload), $secret);
    }

    /**
     * Sign raw HTTP request body bytes in `sha256=<hex>` format.
     *
     * @param string $rawBody
     * @param string $secret
     * @return string
     */
    public static function signRawBody(string $rawBody, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    }

    /**
     * Constant-time signature comparison.
     *
     * @param string $expected
     * @param string $received
     * @return bool
     */
    public static function safeCompare(string $expected, string $received): bool
    {
        $a = strtolower(trim($expected));
        $b = strtolower(trim($received));
        if ($a === '' || $b === '' || strlen($a) !== strlen($b)) {
            return false;
        }
        return hash_equals($a, $b);
    }

    /**
     * Normalize incoming Bank or USDT webhook payload into a single unified event structure.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public static function normalizeWebhookEvent(array $payload, array $headers = []): array
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $eventName = (string)($payload['event'] ?? $headers['x-rpay-event'] ?? $headers['x-stc-event'] ?? '');

        $isCrypto = strpos($eventName, 'crypto.') === 0
            || isset($data['trans_id'])
            || isset($data['invoice_code'])
            || isset($data['network'])
            || isset($data['amountUsdt']);

        if ($isCrypto) {
            $status = strtolower((string)($data['status'] ?? 'paid'));
            $isPaid = in_array($status, ['paid', 'completed', 'reconciled', 'success', 'settled'], true);
            $amount = (float)($data['amount'] ?? $data['amountUsdt'] ?? 0);
            $paidAmount = (float)($data['received'] ?? $data['receivedUsdt'] ?? $amount);
            return [
                'channel' => 'usdt',
                'event' => $eventName !== '' ? $eventName : 'crypto.invoice.paid',
                'invoiceCode' => (string)($data['invoice_code'] ?? $data['invoiceCode'] ?? $data['trans_id'] ?? ''),
                'orderId' => (string)($data['request_id'] ?? $data['orderId'] ?? $data['order_id'] ?? ''),
                'status' => $isPaid ? 'paid' : $status,
                'isPaid' => $isPaid,
                'amount' => $amount,
                'paidAmount' => $paidAmount,
                'currency' => strtoupper((string)($data['token'] ?? 'USDT')),
                'networkOrBank' => strtoupper((string)($data['network'] ?? 'TRC20')),
                'transactionId' => (string)($data['transaction_id'] ?? $data['txHash'] ?? $data['trans_id'] ?? ''),
                'recipientAddress' => (string)($data['to_address'] ?? $data['address'] ?? ''),
                'senderAddress' => (string)($data['from_address'] ?? ''),
                'raw' => $payload,
            ];
        }

        $status = strtolower((string)($data['status'] ?? 'paid'));
        $isPaid = in_array($status, ['paid', 'completed', 'reconciled', 'success', 'settled'], true);
        $amount = (float)($data['amountVnd'] ?? $data['amount'] ?? 0);
        $paidAmount = (float)($data['paidAmountVnd'] ?? $amount);

        return [
            'channel' => 'bank',
            'event' => $eventName !== '' ? $eventName : 'bank.invoice.paid',
            'invoiceCode' => (string)($data['invoiceCode'] ?? $data['invoice_code'] ?? ''),
            'orderId' => (string)($data['orderId'] ?? $data['order_id'] ?? ''),
            'status' => $isPaid ? 'paid' : $status,
            'isPaid' => $isPaid,
            'amount' => $amount,
            'paidAmount' => $paidAmount,
            'currency' => 'VND',
            'networkOrBank' => (string)($data['bankCode'] ?? $headers['x-stc-bank'] ?? 'BANK'),
            'transactionId' => (string)($data['transactionId'] ?? $data['matchedTransactionId'] ?? ''),
            'transferContent' => (string)($data['transferContent'] ?? ''),
            'recipientAddress' => (string)($data['accountNumber'] ?? $headers['x-stc-account'] ?? ''),
            'raw' => $payload,
        ];
    }

    /**
     * Auto-detect and verify RPAY Webhook (`X-RPay-Signature`, `X-Webhook-Signature`, `X-RPay-Body-Signature`, `X-STC-HMAC-SHA256`).
     *
     * @param string|array<string, mixed> $rawBodyOrPayload
     * @param array<string, string> $headers
     * @param string $secret
     * @return array<string, mixed>
     */
    public static function verifyWebhook($rawBodyOrPayload, array $headers = [], string $secret = ''): array
    {
        $normHeaders = [];
        foreach ($headers as $k => $v) {
            $lh = strtolower(trim((string)$k));
            if (strpos($lh, 'http_') === 0) {
                $lh = str_replace('_', '-', substr($lh, 5));
            }
            $normHeaders[$lh] = trim((string)$v);
        }

        $rawBody = is_string($rawBodyOrPayload)
            ? $rawBodyOrPayload
            : (string)json_encode($rawBodyOrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = is_array($rawBodyOrPayload)
            ? $rawBodyOrPayload
            : (json_decode($rawBody, true) ?: []);

        $secret = trim($secret);
        if ($secret === '') {
            return [
                'valid' => false,
                'reason' => 'MISSING_WEBHOOK_SECRET',
                'scheme' => 'none',
                'normalizedEvent' => self::normalizeWebhookEvent($parsed, $normHeaders),
            ];
        }

        // 1. STC HMAC Header
        if (!empty($normHeaders['x-stc-hmac-sha256'])) {
            $expected = hash_hmac('sha256', $rawBody, $secret);
            $valid = self::safeCompare($expected, $normHeaders['x-stc-hmac-sha256']);
            return [
                'valid' => $valid,
                'reason' => $valid ? 'OK' : 'STC_HMAC_MISMATCH',
                'scheme' => 'x-stc-hmac-sha256',
                'normalizedEvent' => self::normalizeWebhookEvent($parsed, $normHeaders),
            ];
        }

        // 2. Raw Body Signature (`sha256=<hex>`)
        if (!empty($normHeaders['x-rpay-body-signature'])) {
            $expected = self::signRawBody($rawBody, $secret);
            $received = $normHeaders['x-rpay-body-signature'];
            if (strpos($received, 'sha256=') !== 0) {
                $received = 'sha256=' . $received;
            }
            $valid = self::safeCompare($expected, $received);
            return [
                'valid' => $valid,
                'reason' => $valid ? 'OK' : 'BODY_SIGNATURE_MISMATCH',
                'scheme' => 'x-rpay-body-signature',
                'normalizedEvent' => self::normalizeWebhookEvent($parsed, $normHeaders),
            ];
        }

        // 3. Canonical Sorted Key-Value Signature
        $sigCandidate = $normHeaders['x-rpay-signature']
            ?? $normHeaders['x-webhook-signature']
            ?? (string)($parsed['signature'] ?? '');
        $sigCandidate = preg_replace('/^sha256=/i', '', trim($sigCandidate)) ?? '';

        if ($sigCandidate !== '' && !empty($parsed)) {
            $expected = self::signCanonicalPayload($parsed, $secret);
            $valid = self::safeCompare($expected, $sigCandidate);
            if (!$valid) {
                // Fallback to raw body HMAC if caller signed raw body into X-Webhook-Signature
                $rawExpected = hash_hmac('sha256', $rawBody, $secret);
                $valid = self::safeCompare($rawExpected, $sigCandidate);
            }
            return [
                'valid' => $valid,
                'reason' => $valid ? 'OK' : 'CANONICAL_SIGNATURE_MISMATCH',
                'scheme' => 'canonical-hmac-sha256',
                'normalizedEvent' => self::normalizeWebhookEvent($parsed, $normHeaders),
            ];
        }

        return [
            'valid' => false,
            'reason' => 'MISSING_SIGNATURE',
            'scheme' => 'none',
            'normalizedEvent' => self::normalizeWebhookEvent($parsed, $normHeaders),
        ];
    }
}
