<?php

declare(strict_types=1);

namespace RPay\SDK;

/**
 * Canonical catalog of 15 Vietnamese Banks (VietQR / Auto-Bank) and 18 USDT/USDC Blockchain Networks.
 */
final class RPayConstants
{
    public const DEFAULT_BASE_URL = 'https://api.rpay.vn';
    public const DEFAULT_TIMEOUT_SECONDS = 60;
    public const SDK_USER_AGENT = 'rpay-sdk-php/1.0.0';

    /**
     * 15 Canonical Vietnamese Bank Gateways supported by RPAY.
     *
     * @var array<string, array<string, mixed>>
     */
    public const BANK_CATALOG = [
        'VCB' => [
            'key' => 'vietcombank',
            'code' => 'VCB',
            'shortName' => 'Vietcombank',
            'fullName' => 'Ngân hàng TMCP Ngoại thương Việt Nam',
            'bin' => '970436',
            'apiSlug' => 'vcb',
            'aliases' => ['vcb', 'vietcombank'],
        ],
        'ACB' => [
            'key' => 'acb',
            'code' => 'ACB',
            'shortName' => 'ACB',
            'fullName' => 'Ngân hàng TMCP Á Châu',
            'bin' => '970416',
            'apiSlug' => 'acb',
            'aliases' => ['acb'],
        ],
        'MB' => [
            'key' => 'mbbank',
            'code' => 'MB',
            'shortName' => 'MBBank',
            'fullName' => 'Ngân hàng TMCP Quân đội',
            'bin' => '970422',
            'apiSlug' => 'mb',
            'aliases' => ['mb', 'mbbank'],
        ],
        'TCB' => [
            'key' => 'techcombank',
            'code' => 'TCB',
            'shortName' => 'Techcombank',
            'fullName' => 'Ngân hàng TMCP Kỹ thương Việt Nam',
            'bin' => '970407',
            'apiSlug' => 'tcb',
            'aliases' => ['tcb', 'techcombank'],
        ],
        'BIDV' => [
            'key' => 'bidv',
            'code' => 'BIDV',
            'shortName' => 'BIDV',
            'fullName' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam',
            'bin' => '970418',
            'apiSlug' => 'bidv',
            'aliases' => ['bidv'],
        ],
        'ICB' => [
            'key' => 'vietinbank',
            'code' => 'ICB',
            'shortName' => 'VietinBank',
            'fullName' => 'Ngân hàng TMCP Công thương Việt Nam',
            'bin' => '970415',
            'apiSlug' => 'icb',
            'aliases' => ['icb', 'vietinbank', 'ctg', 'vtb', 'viettinbank'],
        ],
        'VPB' => [
            'key' => 'vpbank',
            'code' => 'VPB',
            'shortName' => 'VPBank',
            'fullName' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng',
            'bin' => '970432',
            'apiSlug' => 'vpb',
            'aliases' => ['vpb', 'vpbank'],
        ],
        'TPB' => [
            'key' => 'tpbank',
            'code' => 'TPB',
            'shortName' => 'TPBank',
            'fullName' => 'Ngân hàng TMCP Tiên Phong',
            'bin' => '970423',
            'apiSlug' => 'tpb',
            'aliases' => ['tpb', 'tpbank'],
        ],
        'VBA' => [
            'key' => 'agribank',
            'code' => 'VBA',
            'shortName' => 'Agribank',
            'fullName' => 'Ngân hàng Nông nghiệp và PTNT Việt Nam',
            'bin' => '970405',
            'apiSlug' => 'vba',
            'aliases' => ['vba', 'agribank'],
        ],
        'MSB' => [
            'key' => 'msb',
            'code' => 'MSB',
            'shortName' => 'MSB',
            'fullName' => 'Ngân hàng TMCP Hàng Hải',
            'bin' => '970426',
            'apiSlug' => 'msb',
            'aliases' => ['msb', 'maritimebank'],
        ],
        'OCB' => [
            'key' => 'ocb',
            'code' => 'OCB',
            'shortName' => 'OCB',
            'fullName' => 'Ngân hàng TMCP Phương Đông',
            'bin' => '970448',
            'apiSlug' => 'ocb',
            'aliases' => ['ocb', 'oceanbank'],
        ],
        'SEAB' => [
            'key' => 'seabank',
            'code' => 'SEAB',
            'shortName' => 'SeABank',
            'fullName' => 'Ngân hàng TMCP Đông Nam Á',
            'bin' => '970440',
            'apiSlug' => 'seab',
            'aliases' => ['seabank', 'ssb', 'seab', 'sebank'],
        ],
        'EIB' => [
            'key' => 'eximbank',
            'code' => 'EIB',
            'shortName' => 'Eximbank',
            'fullName' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam',
            'bin' => '970431',
            'apiSlug' => 'eib',
            'aliases' => ['eib', 'eximbank', 'exim'],
        ],
        'BVB' => [
            'key' => 'bvbank',
            'code' => 'BVB',
            'shortName' => 'BVBank',
            'fullName' => 'Ngân hàng TMCP Bản Việt',
            'bin' => '970454',
            'apiSlug' => 'bvb',
            'aliases' => ['bvb', 'bvbank', 'banviet', 'vietcapitalbank'],
        ],
        'VTP' => [
            'key' => 'viettelpay',
            'code' => 'VTP',
            'shortName' => 'Viettel Money',
            'fullName' => 'Tổng công ty Dịch vụ số Viettel',
            'bin' => '971005',
            'apiSlug' => 'vtp',
            'aliases' => ['vtm', 'vtp', 'viettelmoney', 'viettelpay', 'viettel_money', 'viettel-money'],
        ],
    ];

    /**
     * 18 Canonical USDT/USDC Blockchain Networks supported by RPAY.
     *
     * @var array<string, array<string, mixed>>
     */
    public const USDT_NETWORKS = [
        'TRC20' => ['network' => 'TRC20', 'name' => 'TRON (TRC-20)', 'chainKey' => 'tron', 'decimals' => 6, 'explorerUrl' => 'https://tronscan.org'],
        'BEP20' => ['network' => 'BEP20', 'name' => 'BNB Smart Chain (BEP-20)', 'chainKey' => 'bsc', 'decimals' => 18, 'explorerUrl' => 'https://bscscan.com'],
        'ERC20' => ['network' => 'ERC20', 'name' => 'Ethereum (ERC-20)', 'chainKey' => 'ethereum', 'decimals' => 6, 'explorerUrl' => 'https://etherscan.io'],
        'POLYGON' => ['network' => 'POLYGON', 'name' => 'Polygon POS', 'chainKey' => 'polygon', 'decimals' => 6, 'explorerUrl' => 'https://polygonscan.com'],
        'ARBITRUM' => ['network' => 'ARBITRUM', 'name' => 'Arbitrum One', 'chainKey' => 'arbitrum', 'decimals' => 6, 'explorerUrl' => 'https://arbiscan.io'],
        'OPTIMISM' => ['network' => 'OPTIMISM', 'name' => 'OP Mainnet (Optimism)', 'chainKey' => 'optimism', 'decimals' => 6, 'explorerUrl' => 'https://optimistic.etherscan.io'],
        'SOLANA' => ['network' => 'SOLANA', 'name' => 'Solana (SPL)', 'chainKey' => 'solana', 'decimals' => 6, 'explorerUrl' => 'https://solscan.io'],
        'TON' => ['network' => 'TON', 'name' => 'The Open Network (TON Jetton)', 'chainKey' => 'ton', 'decimals' => 6, 'explorerUrl' => 'https://tonviewer.com'],
        'BASE' => ['network' => 'BASE', 'name' => 'Base Mainnet', 'chainKey' => 'base', 'decimals' => 6, 'explorerUrl' => 'https://basescan.org'],
        'AVALANCHE' => ['network' => 'AVALANCHE', 'name' => 'Avalanche C-Chain', 'chainKey' => 'avalanche', 'decimals' => 6, 'explorerUrl' => 'https://snowtrace.io'],
        'APTOS' => ['network' => 'APTOS', 'name' => 'Aptos Mainnet', 'chainKey' => 'aptos', 'decimals' => 6, 'explorerUrl' => 'https://aptoscan.com'],
        'SUI' => ['network' => 'SUI', 'name' => 'Sui Network', 'chainKey' => 'sui', 'decimals' => 6, 'explorerUrl' => 'https://suiscan.xyz/mainnet'],
        'OPBNB' => ['network' => 'OPBNB', 'name' => 'opBNB Mainnet', 'chainKey' => 'opbnb', 'decimals' => 18, 'explorerUrl' => 'https://opbnbscan.com'],
        'CELO' => ['network' => 'CELO', 'name' => 'Celo Mainnet', 'chainKey' => 'celo', 'decimals' => 6, 'explorerUrl' => 'https://celoscan.io'],
        'SCROLL' => ['network' => 'SCROLL', 'name' => 'Scroll Mainnet', 'chainKey' => 'scroll', 'decimals' => 6, 'explorerUrl' => 'https://scrollscan.com'],
        'KAVA' => ['network' => 'KAVA', 'name' => 'Kava EVM', 'chainKey' => 'kava', 'decimals' => 6, 'explorerUrl' => 'https://kavascan.com'],
        'POLKADOT' => ['network' => 'POLKADOT', 'name' => 'Polkadot Asset Hub (Statemint)', 'chainKey' => 'polkadot', 'decimals' => 6, 'explorerUrl' => 'https://assethub-polkadot.subscan.io'],
        'OSMOSIS' => ['network' => 'OSMOSIS', 'name' => 'Osmosis (Cosmos IBC)', 'chainKey' => 'osmosis', 'decimals' => 6, 'explorerUrl' => 'https://www.mintscan.io/osmosis'],
    ];

    /**
     * Resolve a bank specification by code, BIN, slug, or alias.
     *
     * @param string $identifier
     * @return array<string, mixed>|null
     */
    public static function resolveBank(string $identifier): ?array
    {
        $norm = strtolower(trim($identifier));
        if ($norm === '') {
            return null;
        }
        foreach (self::BANK_CATALOG as $bank) {
            if (
                strtolower((string)$bank['code']) === $norm ||
                strtolower((string)$bank['key']) === $norm ||
                (string)$bank['bin'] === $norm ||
                strtolower((string)$bank['shortName']) === $norm ||
                strtolower((string)$bank['apiSlug']) === $norm ||
                in_array($norm, $bank['aliases'], true)
            ) {
                return $bank;
            }
        }
        return null;
    }

    /**
     * Resolve a USDT/USDC blockchain network specification by code or alias.
     *
     * @param string $rawNetwork
     * @return array<string, mixed>|null
     */
    public static function resolveUsdtNetwork(string $rawNetwork): ?array
    {
        $norm = strtoupper(trim($rawNetwork));
        if ($norm === '') {
            return null;
        }
        $aliases = [
            'TRX' => 'TRC20',
            'TRON' => 'TRC20',
            'TRC-20' => 'TRC20',
            'BSC' => 'BEP20',
            'BNB' => 'BEP20',
            'BEP-20' => 'BEP20',
            'ETH' => 'ERC20',
            'ETHEREUM' => 'ERC20',
            'ERC-20' => 'ERC20',
            'MATIC' => 'POLYGON',
            'ARB' => 'ARBITRUM',
            'OP' => 'OPTIMISM',
            'SOL' => 'SOLANA',
            'AVAX' => 'AVALANCHE',
            'DOT' => 'POLKADOT',
            'STATEMINT' => 'POLKADOT',
            'OSMO' => 'OSMOSIS',
        ];
        if (isset($aliases[$norm])) {
            $norm = $aliases[$norm];
        }
        if (isset(self::USDT_NETWORKS[$norm])) {
            return self::USDT_NETWORKS[$norm];
        }
        foreach (self::USDT_NETWORKS as $item) {
            if (strtoupper((string)$item['chainKey']) === $norm) {
                return $item;
            }
        }
        return null;
    }
}
