<?php
// Quy tắc nghiệp vụ dùng cho giỏ hàng / thanh toán

const SHIP_FEE      = 30000;    // phí vận chuyển cố định
const FREE_SHIP_MIN = 250000;   // đơn từ mức này (trước giảm giá) thì miễn phí vận chuyển

function shipping_fee(int $subtotal): int
{
    return $subtotal >= FREE_SHIP_MIN ? 0 : SHIP_FEE;
}

$GLOBALS['PAYMENT_METHODS'] = [
    'cod'           => 'Thanh toán khi nhận hàng (COD)',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'momo'          => 'Ví MoMo',
    'vnpay'         => 'VNPay',
];

$GLOBALS['ORDER_STATUS'] = [
    'pending'   => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping'  => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy',
];

/**
 * Kiểm tra mã khuyến mãi cho 1 đơn.
 * Trả về ['error' => '...'] hoặc ['coupon' => row, 'discount' => int, 'period_key' => string, 'next_no' => int].
 * $forUpdate = true khi đang trong transaction đặt hàng (khóa dòng coupon để tránh vượt lượt dùng).
 */
function coupon_check(PDO $pdo, string $code, int $userId, int $subtotal, bool $forUpdate = false): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['error' => 'Vui lòng nhập mã khuyến mãi.'];
    }

    $st = $pdo->prepare('SELECT * FROM coupons WHERE code = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
    $st->execute([$code]);
    $c = $st->fetch();

    if (!$c || !(int)$c['is_active']) {
        return ['error' => 'Mã khuyến mãi không tồn tại hoặc đã ngừng áp dụng.'];
    }

    $today = date('Y-m-d');
    if ($today < $c['start_date']) {
        return ['error' => 'Mã khuyến mãi chưa đến thời gian áp dụng (từ ' . date('d/m/Y', strtotime($c['start_date'])) . ').'];
    }
    if ($today > $c['end_date']) {
        return ['error' => 'Mã khuyến mãi đã hết hạn.'];
    }
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) {
        return ['error' => 'Mã khuyến mãi đã hết lượt sử dụng.'];
    }
    if ($subtotal < (int)$c['min_order']) {
        return ['error' => 'Đơn hàng cần tối thiểu ' . money($c['min_order']) . ' để dùng mã này.'];
    }

    // giới hạn theo từng người dùng (trọn đời hoặc theo tháng)
    $periodKey = $c['limit_period'] === 'month' ? date('Y-m') : 'ALL';
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS used, COALESCE(MAX(usage_no), 0) AS max_no
         FROM coupon_usages WHERE coupon_id = ? AND user_id = ? AND period_key = ?'
    );
    $st->execute([$c['id'], $userId, $periodKey]);
    $u = $st->fetch();

    if ($c['per_user_limit'] !== null && (int)$u['used'] >= (int)$c['per_user_limit']) {
        return ['error' => $c['limit_period'] === 'month'
            ? 'Bạn đã dùng hết lượt của mã này trong tháng ' . date('m/Y') . '.'
            : 'Bạn đã dùng hết lượt của mã này.'];
    }

    $value    = (int)$c['discount_value'];
    $discount = $c['discount_type'] === 'percent'
        ? intdiv($subtotal * $value, 100)
        : min($value, $subtotal);

    return [
        'coupon'     => $c,
        'discount'   => $discount,
        'period_key' => $periodKey,
        'next_no'    => (int)$u['max_no'] + 1,
    ];
}
