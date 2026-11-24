<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/checkout_lib.php';
require_login();

$user = current_user();
$pdo  = db();
$code = trim($_GET['code'] ?? '');

// chỉ xem được đơn của chính mình
$st = $pdo->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
$st->execute([$code, $user['id']]);
$order = $st->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy đơn hàng';
    require __DIR__ . '/header.php';
    echo '<div class="section"><div class="empty">Không tìm thấy đơn hàng. <a href="index.php" style="color:var(--red)">Về trang chủ</a></div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$order['id']]);
$orderItems = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$order['id']]);
$payment = $st->fetch();

$methods  = $GLOBALS['PAYMENT_METHODS'];
$statuses = $GLOBALS['ORDER_STATUS'];

$pageTitle = 'Đặt hàng thành công';
require __DIR__ . '/header.php';
?>
<div class="section success-box">
    <div class="ok-icon">✔</div>
    <h1>Đặt hàng thành công!</h1>
    <p>Mã đơn hàng của bạn: <strong class="price"><?= e($order['order_code']) ?></strong></p>
    <p class="muted">Trạng thái: <strong><?= e($statuses[$order['status']] ?? $order['status']) ?></strong>
        · Đặt lúc <?= e(date('H:i d/m/Y', strtotime($order['created_at']))) ?></p>
</div>

<div class="co-card">
    <h3>THÔNG TIN GIAO HÀNG</h3>
    <p><strong><?= e($order['receiver_name']) ?></strong> - <?= e($order['phone']) ?></p>
    <p><?= e($order['shipping_address']) ?></p>
    <?php if ($order['note']): ?><p class="muted">Ghi chú: <?= e($order['note']) ?></p><?php endif; ?>
</div>

<div class="co-card">
    <h3>THANH TOÁN</h3>
    <?php if ($payment): ?>
        <p>Phương thức: <strong><?= e($methods[$payment['method']] ?? $payment['method']) ?></strong></p>
        <?php if ($payment['method'] === 'cod'): ?>
            <p class="muted">Bạn thanh toán <?= money($payment['amount']) ?> cho nhân viên giao hàng khi nhận sách.</p>
        <?php else: ?>
            <p class="muted">Đơn đang chờ thanh toán <?= money($payment['amount']) ?>. Cửa hàng sẽ liên hệ hướng dẫn và xác nhận thanh toán
                (bài tập này chưa tích hợp cổng thanh toán thật).</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="co-card">
    <h3>CHI TIẾT ĐƠN HÀNG</h3>
    <table class="order-table">
        <?php foreach ($orderItems as $oi): ?>
            <tr>
                <td class="ot-name"><a href="book.php?id=<?= (int)$oi['book_id'] ?>"><?= e($oi['book_title']) ?></a></td>
                <td><?= money($oi['unit_price']) ?></td>
                <td>x <?= (int)$oi['quantity'] ?></td>
                <td class="ot-line"><?= money((float)$oi['unit_price'] * (int)$oi['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <div class="co-sum">
        <div><span>Tạm tính</span><span><?= money($order['subtotal']) ?></span></div>
        <div><span>Giảm giá</span><span><?= (float)$order['discount_amount'] > 0 ? '- ' . money($order['discount_amount']) : '0 đ' ?></span></div>
        <div><span>Phí vận chuyển</span><span><?= (float)$order['shipping_fee'] == 0 ? 'Miễn phí' : money($order['shipping_fee']) ?></span></div>
        <div class="grand"><span>Tổng thanh toán</span><span><?= money($order['total_amount']) ?></span></div>
    </div>
</div>

<p style="text-align:center">
    <a class="btn-outline" href="order_detail.php?code=<?= e($order['order_code']) ?>">Xem đơn hàng</a>
    <a class="btn-red btn-inline" style="display:inline-block" href="search.php">Tiếp tục mua sắm</a>
</p>
<?php require __DIR__ . '/footer.php'; ?>
