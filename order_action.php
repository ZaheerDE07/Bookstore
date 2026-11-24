<?php
// Hủy đơn / mua lại (POST từ orders.php và order_detail.php)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/checkout_lib.php';
require_once __DIR__ . '/order_lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders.php');
}
if (!csrf_valid()) {
    flash_set('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
    redirect('orders.php');
}

$user   = current_user();
$pdo    = db();
$action = $_POST['action'] ?? '';
$code   = trim($_POST['code'] ?? '');
$back   = (($_POST['from'] ?? '') === 'detail') ? 'order_detail.php?code=' . urlencode($code) : 'orders.php';

// đơn phải thuộc về user đang đăng nhập
$st = $pdo->prepare('SELECT id, order_code, status FROM orders WHERE order_code = ? AND user_id = ?');
$st->execute([$code, $user['id']]);
$found = $st->fetch();
if (!$found) {
    flash_set('error', 'Không tìm thấy đơn hàng.');
    redirect('orders.php');
}
$orderId = (int)$found['id'];

/* =====================  HỦY ĐƠN  ===================== */
if ($action === 'cancel') {
    try {
        $pdo->beginTransaction();

        // khóa đơn rồi kiểm tra lại trạng thái (tránh hủy 2 lần / hủy đơn admin vừa xác nhận)
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
        $st->execute([$orderId, $user['id']]);
        $o = $st->fetch();
        if (!$o || $o['status'] !== 'pending') {
            throw new RuntimeException('Chỉ có thể hủy đơn đang ở trạng thái "Chờ xác nhận".');
        }

        // 1-3. hoàn kho, hoàn lượt mã khuyến mãi, cập nhật thanh toán (dùng chung với trang quản trị)
        restore_cancelled_order($pdo, $o, (int)$user['id'], 'Khách hủy đơn ' . $o['order_code']);

        // 4. đổi trạng thái đơn + lịch sử
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        $pdo->prepare(
            "INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
             VALUES (?, 'pending', 'cancelled', ?, 'Khách hàng hủy đơn')"
        )->execute([$orderId, $user['id']]);

        $pdo->commit();
        audit_log((int)$user['id'], 'cancel_order', 'orders', $orderId, 'Hủy đơn ' . $o['order_code']);
        flash_set('success', 'Đã hủy đơn hàng ' . $o['order_code'] . '. Sách đã được hoàn lại kho.');
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash_set('error', $ex->getMessage());
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('cancel_order: ' . $ex->getMessage());
        flash_set('error', 'Có lỗi hệ thống khi hủy đơn, vui lòng thử lại sau.');
    }
    redirect($back);
}

/* =====================  MUA LẠI  ===================== */
if ($action === 'reorder') {
    $st = $pdo->prepare(
        'SELECT oi.book_id, oi.book_title, oi.quantity, b.stock, b.status
         FROM order_items oi JOIN books b ON b.id = oi.book_id
         WHERE oi.order_id = ?'
    );
    $st->execute([$orderId]);
    $lines = $st->fetchAll();

    $st = $pdo->prepare('SELECT id FROM carts WHERE user_id = ?');
    $st->execute([$user['id']]);
    $cartId = $st->fetchColumn();
    if (!$cartId) {
        $pdo->prepare('INSERT INTO carts (user_id) VALUES (?)')->execute([$user['id']]);
        $cartId = (int)$pdo->lastInsertId();
    }

    $ins = $pdo->prepare(
        'INSERT INTO cart_items (cart_id, book_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = LEAST(?, quantity + ?)'
    );
    $added = 0;
    $skipped = [];
    foreach ($lines as $ln) {
        $cap = min(100, (int)$ln['stock']);
        if ($ln['status'] !== 'active' || $cap < 1) {
            $skipped[] = $ln['book_title'];
            continue;
        }
        $qty = max(1, min((int)$ln['quantity'], $cap));
        $ins->execute([$cartId, (int)$ln['book_id'], $qty, $cap, $qty]);
        $added++;
    }

    if ($added === 0) {
        flash_set('error', 'Các sách trong đơn này hiện đã hết hàng hoặc ngừng bán.');
        redirect($back);
    }
    flash_set('success', 'Đã thêm ' . $added . ' sản phẩm vào giỏ hàng.'
        . ($skipped ? ' Bỏ qua (hết hàng): ' . implode(', ', $skipped) . '.' : ''));
    redirect('cart.php');
}

redirect('orders.php');
