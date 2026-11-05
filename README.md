# RPAY PHP SDK (`rpayvn/rpay-sdk`)

**Thư viện PHP chính thức cho hệ sinh thái RPAY Payment & Banking Gateway** — tích hợp đồng thời **15 ngân hàng Việt Nam (VietQR / Auto-Bank LSGD)** và **Crypto USDT/USDC (18 mạng lưới Blockchain)** trên **mọi tên miền (Multi-Domain / White-Label Child Site)**.

- **Tương thích mọi nền tảng PHP**: **Laravel 8/9/10/11**, **WordPress / WooCommerce**, **CodeIgniter / Symfony**, và **PHP thuần (Pure PHP >= 7.4 / 8.x)** (chỉ cần `ext-curl` và `ext-json`).
- **Zero-Config `.env` Ready**: Tự động đọc biến môi trường `RPAY_*` từ `$_ENV`, `$_SERVER`, hoặc `getenv()`.
- **Static Facade `RPay::pay(...)` & Instance `RPayClient`**: Gọi siêu ngắn gọn 1 dòng hoặc khởi tạo theo từng đại lý/tên miền qua `RPay::forDomain('pay.shopcon.vn')`.
- **Unified API (`pay`, `check`, `lsgd`, `qr`, `matchPayment`, `verifyWebhook`)**: Chuẩn hóa kết quả trả về đồng nhất cho cả Ngân hàng (VND) và Crypto (USDT/USDC).

---

## 1. Cài đặt qua Composer (Packagist)

```bash
composer require rpayvn/rpay-sdk
```

---

## 2. Cấu hình nhanh trong `.env`

```env
RPAY_DOMAIN=https://rpay.vn
RPAY_BANK_TOKEN=tok_live_xxxxxxxxxxxx
RPAY_BANK_CODE=ACB
RPAY_ACCOUNT_NUMBER=12345678
RPAY_USDT_API_KEY=usdt_key_xxxxxxxx
RPAY_USDT_SECRET_KEY=usdt_sec_xxxxxxxx
RPAY_USDT_NETWORK=TRC20
RPAY_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx
```

---

## 3. Sử dụng siêu gọn (Unified Bank & USDT)

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use RPay\SDK\RPay;

// 1. Tạo hóa đơn Ngân hàng (VietQR VND)
$bankInvoice = RPay::pay([
    'amount'      => 150000,
    'orderId'     => 'DH1001',
    'description' => 'Thanh toan don hang DH1001',
]);

echo "QR Image: " . $bankInvoice['qrImageUrl'] . PHP_EOL;
echo "Checkout URL: " . $bankInvoice['paymentUrl'] . PHP_EOL;

// 2. Tạo hóa đơn Crypto (USDT - TRC20 / BEP20 / POLYGON / SOL / TON...)
$usdtInvoice = RPay::pay([
    'channel' => 'usdt',
    'amount'  => 25.5,
    'network' => 'TRC20',
    'orderId' => 'DH1001',
]);

echo "Ví nhận USDT: " . $usdtInvoice['recipientAddress'] . PHP_EOL;

// 3. Kiểm tra trạng thái hóa đơn (tự nhận diện RPINV... hoặc USINV...)
$status = RPay::check($bankInvoice['invoiceCode']);
if ($status['isPaid']) {
    echo "Đơn hàng đã thanh toán thành công!";
}
```

---

## 4. Dùng trên nhiều Domain (Multi-Domain / White-Label Child Site)

Khi bạn vận hành nhiều website hoặc đại lý White-Label (`pay.shopA.vn`, `pay.shopB.vn`), dùng `RPay::forDomain(...)` để SDK tự động định tuyến URL và gắn header `X-Site-Domain`:

```php
use RPay\SDK\RPay;

$shopA = RPay::forDomain('pay.shop-a.vn');
$shopB = RPay::forDomain('pay.shop-b.vn', [
    'bankToken' => 'tok_rieng_shop_b',
    'bankCode'  => 'VCB',
]);

$invA = $shopA->pay(['amount' => 99000, 'orderId' => 'A_101']);
$invB = $shopB->pay(['channel' => 'usdt', 'amount' => 15, 'network' => 'BEP20', 'orderId' => 'B_202']);
```

---

## 5. Cronjob lấy lịch sử giao dịch (LSGD) & Tự động đối soát (`matchPayment`)

```php
use RPay\SDK\RPay;

// Lấy gộp cả LSGD Ngân hàng + LSGD USDT trong 1 lệnh duy nhất
$lsgd = RPay::lsgd([
    'channel' => 'all', // 'bank' | 'usdt' | 'all'
]);

// Tự động tìm giao dịch khớp mã đơn 'DH1001' và số tiền >= 150.000đ
$matched = RPay::client()->matchPayment($lsgd['transactions'], [
    'orderCode'      => 'DH1001',
    'expectedAmount' => 150000,
]);

if (!empty($matched)) {
    $tx = $matched[0];
    echo "Đã nhận {$tx['amount']} {$tx['currency']} qua {$tx['networkOrBank']} (Mã GD: {$tx['transactionID']})";
}
```

---

## 6. Xác thực Webhook `HMAC-SHA256` (Laravel & PHP Thuần)

### 6.1. Trong Laravel Controller (`routes/api.php`)

```php
use Illuminate\Http\Request;
use RPay\SDK\RPay;

Route::post('/rpay/webhook', function (Request $request) {
    $result = RPay::verifyWebhook(
        $request->getContent(),
        $request->headers->all()
    );

    if (!$result['valid']) {
        return response()->json(['error' => $result['reason']], 401);
    }

    $event = $result['normalizedEvent'];
    if ($event['isPaid']) {
        // Cộng tiền / kích hoạt đơn hàng theo $event['orderId'] và $event['transactionId']
    }

    return response()->json(['received' => true]);
});
```

### 6.2. Trong PHP Thuần (`webhook.php`)

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use RPay\SDK\RPay;

$rawBody = file_get_contents('php://input') ?: '';
$result  = RPay::verifyWebhook($rawBody, $_SERVER);

header('Content-Type: application/json');
if (!$result['valid']) {
    http_response_code(401);
    echo json_encode(['received' => false, 'reason' => $result['reason']]);
    exit;
}

$event = $result['normalizedEvent'];
if ($event['isPaid']) {
    // Duyệt đơn hàng $event['orderId']
}

echo json_encode(['received' => true]);
```

---

## 7. Danh sách Ngân hàng & Mạng USDT hỗ trợ

- **15 Ngân hàng Việt Nam (`RPayConstants::BANK_CATALOG`)**: `VCB`, `ACB`, `MB`, `TCB`, `BIDV`, `ICB` (VietinBank), `VPB`, `TPB`, `VBA` (Agribank), `MSB`, `OCB`, `SEAB`, `EIB` (Eximbank), `BVB` (BVBank), `VTP` (Viettel Money).
- **18 Mạng lưới Crypto (`RPayConstants::USDT_NETWORKS`)**: `TRC20`, `BEP20`, `ERC20`, `POLYGON`, `ARBITRUM`, `OPTIMISM`, `SOLANA`, `TON`, `BASE`, `AVALANCHE`, `APTOS`, `SUI`, `OPBNB`, `CELO`, `SCROLL`, `KAVA`, `POLKADOT`, `OSMOSIS`.

---

## 8. Xuất bản lên Packagist (Composer)

1. Đẩy thư mục `plugin/php` lên GitHub repository (ví dụ `https://github.com/kienlovc/rpay-sdk-php`).
2. Tạo tag phiên bản:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
3. Truy cập `https://packagist.org/packages/submit` và nhập URL GitHub repository.
