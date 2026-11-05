<?php

declare(strict_types=1);

namespace RPay\SDK;

/**
 * Static Facade for RPAY PHP SDK — allows calling `RPay::pay(...)`, `RPay::check(...)`, `RPay::lsgd(...)`
 * directly without manual dependency injection in Laravel, WordPress, or Pure PHP scripts.
 */
final class RPay
{
    /** @var RPayClient|null */
    private static ?RPayClient $instance = null;

    /**
     * Initialize or replace the default singleton client.
     *
     * @param array<string, mixed> $config
     * @return RPayClient
     */
    public static function init(array $config = []): RPayClient
    {
        self::$instance = new RPayClient($config);
        return self::$instance;
    }

    /**
     * Get the default singleton client (auto-initialized from `.env` if needed).
     *
     * @return RPayClient
     */
    public static function client(): RPayClient
    {
        if (self::$instance === null) {
            self::$instance = new RPayClient();
        }
        return self::$instance;
    }

    /**
     * @param string $domainOrUrl
     * @param array<string, mixed> $overrides
     * @return RPayClient
     */
    public static function forDomain(string $domainOrUrl, array $overrides = []): RPayClient
    {
        return self::client()->forDomain($domainOrUrl, $overrides);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function pay(array $params): array
    {
        return self::client()->pay($params);
    }

    /**
     * @param string $codeOrRef
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function check(string $codeOrRef, array $options = []): array
    {
        return self::client()->check($codeOrRef, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function lsgd(array $options = []): array
    {
        return self::client()->lsgd($options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function qr(array $options = []): array
    {
        return self::client()->qr($options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function route(array $options = []): array
    {
        return self::client()->route($options);
    }

    /**
     * @param string|array<string, mixed> $rawBodyOrPayload
     * @param array<string, string> $headers
     * @param string $secretOverride
     * @return array<string, mixed>
     */
    public static function verifyWebhook($rawBodyOrPayload, array $headers = [], string $secretOverride = ''): array
    {
        return self::client()->verifyWebhook($rawBodyOrPayload, $headers, $secretOverride);
    }
}
