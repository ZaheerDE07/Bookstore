<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/checkout_lib.php';
require_login();

$user = current_user();
$pdo  = db();
$code = trim($_GET['code'] ?? '');

// chỉ xem được đơn của chính mình
$st = $pdo->prepare(
    'SELECT o.*, c.code AS coupon_code FROM orders o LEFT JOIN coupons c ON c.id = o.coupon_id
     WHERE o.order_code = ? AND o.user_id = ?'
);
$st->execute([$code, $user['id']]);
$order = $st->fetch();

$accActive = 'orders';
if (!$order) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy đơn hàng';
    require __DIR__ . '/header.php';
    echo '<div class="section"><div class="empty">Không tìm thấy đơn hàng. <a href="orders.php" style="color:var(--red)">Về danh sách đơn hàng</a></div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$order['id']]);
$items = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$order['id']]);
$payment = $st->fetch();

$st = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id');
$st->execute([$order['id']]);
$history = $st->fetchAll();

$statusLabels = $GLOBALS['ORDER_STATUS'];
$methods      = $GLOBALS['PAYMENT_METHODS'];
$payStatus    = ['pending' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán', 'failed' => 'Không thành công', 'refunded' => 'Đã hoàn tiền'];

$pageTitle = 'Đơn hàng ' . $order['order_code'];
require __DIR__ . '/header.php';
?>
<div class="breadcrumb"><a href="index.php">Trang chủ</a> › <a href="account.php">Tài khoản</a> › <a href="orders.php">Đơn hàng của tôi</a> › <strong>#<?= e($order['order_code']) ?></strong></div>

<div class="acc-layout">
    <?php require __DIR__ . '/account_nav.php'; ?>

    <div class="acc-main">
        <div class="co-card">
            <div class="card-head">
                <h3>CHI TIẾT ĐƠN HÀNG #<?= e($order['order_code']) ?></h3>
                <span class="status st-<?= e($order['status']) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span>
            </div>
            <p class="muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime($order['created_at']))) ?></p>

            <table class="order-table">
                <?php foreach ($items as $oi): ?>
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
                <div><span>Giảm giá<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span>
                    <span><?= (float)$order['discount_amount'] > 0 ? '- ' . money($order['discount_amount']) : '0 đ' ?></span></div>
                <div><span>Phí vận chuyển</span><span><?= (float)$order['shipping_fee'] == 0 ? 'Miễn phí' : money($order['shipping_fee']) ?></span></div>
                <div class="grand"><span>Tổng thanh toán</span><span><?= money($order['total_amount']) ?></span></div>
            </div>
        </div>

        <div class="acc-two">
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
                    <p>Trạng thái: <strong><?= e($payStatus[$payment['status']] ?? $payment['status']) ?></strong></p>
                    <p>Số tiền: <?= money($payment['amount']) ?></p>
                <?php else: ?><p class="muted">Chưa có thông tin thanh toán.</p><?php endif; ?>
            </div>
        </div>

        <div class="co-card">
            <h3>LỊCH SỬ TRẠNG THÁI</h3>
            <ul class="timeline">
                <?php foreach (array_reverse($history) as $h): ?>
                    <li>
                        <strong><?= e($statusLabels[$h['to_status']] ?? $h['to_status']) ?></strong>
                        <span class="muted"><?= e(date('H:i d/m/Y', strtotime($h['created_at']))) ?></span>
                        <?php if ($h['note']): ?><div class="muted"><?= e($h['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$history): ?><li class="muted">Chưa có lịch sử.</li><?php endif; ?>
            </ul>
        </div>

        <div class="detail-actions">
            <a href="orders.php" class="btn-outline">← Quay lại danh sách</a>
            <?php if (in_array($order['status'], ['completed', 'cancelled'], true)): ?>
                <form method="post" action="order_action.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reorder"><input type="hidden" name="code" value="<?= e($order['order_code']) ?>">
                    <button type="submit" class="btn-red btn-inline">Mua lại</button>
                </form>
            <?php endif; ?>
            <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="order_action.php" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn này?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="from" value="detail">
                    <input type="hidden" name="code" value="<?= e($order['order_code']) ?>">
                    <button type="submit" class="btn-gray btn-inline">Hủy đơn hàng</button>
                </form>
            <?php elseif ($order['status'] === 'confirmed'): ?>
                <span class="muted">Đơn đã được xác nhận nên không thể tự hủy. Vui lòng liên hệ cửa hàng nếu cần hỗ trợ.</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
