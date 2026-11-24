<?php
// Nghiệp vụ đơn hàng dùng chung cho khách (order_action.php) và quản trị (admin/order_view.php)

/** Các trạng thái được phép chuyển tiếp từ trạng thái hiện tại */
function allowed_next_statuses(string $status): array
{
    return [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping'  => ['completed', 'cancelled'],
    ][$status] ?? [];
}

/**
 * Hoàn kho, hoàn lượt dùng mã khuyến mãi, cập nhật thanh toán khi đơn bị hủy.
 * PHẢI gọi bên trong transaction, khi dòng đơn hàng đã được khóa (SELECT ... FOR UPDATE).
 * $o = dòng orders; $note = ghi chú ghi vào lịch sử kho.
 */
function restore_cancelled_order(PDO $pdo, array $o, int $actorId, string $note): void
{
    $orderId = (int)$o['id'];

    // 1. hoàn kho + ghi lịch sử kho
    $st = $pdo->prepare('SELECT book_id, quantity FROM order_items WHERE order_id = ? ORDER BY book_id');
    $st->execute([$orderId]);
    $updBook = $pdo->prepare('UPDATE books SET stock = stock + ?, sold_count = GREATEST(CAST(sold_count AS SIGNED) - ?, 0) WHERE id = ?');
    $insMove = $pdo->prepare(
        "INSERT INTO stock_movements (book_id, change_qty, reason, order_id, note, created_by)
         VALUES (?, ?, 'cancel_restore', ?, ?, ?)"
    );
    foreach ($st->fetchAll() as $ln) {
        $updBook->execute([(int)$ln['quantity'], (int)$ln['quantity'], (int)$ln['book_id']]);
        $insMove->execute([(int)$ln['book_id'], (int)$ln['quantity'], $orderId, $note, $actorId]);
    }

    // 2. hoàn lượt dùng mã khuyến mãi
    if ($o['coupon_id'] !== null) {
        $del = $pdo->prepare('DELETE FROM coupon_usages WHERE order_id = ?');
        $del->execute([$orderId]);
        if ($del->rowCount() > 0) {
            $pdo->prepare('UPDATE coupons SET used_count = GREATEST(CAST(used_count AS SIGNED) - 1, 0) WHERE id = ?')
                ->execute([(int)$o['coupon_id']]);
        }
    }

    // 3. thanh toán: đã trả thì "hoàn tiền", chưa trả thì "không thành công"
    $pdo->prepare(
        "UPDATE payments SET status = CASE status WHEN 'paid' THEN 'refunded' WHEN 'pending' THEN 'failed' ELSE status END
         WHERE order_id = ?"
    )->execute([$orderId]);
}

/**
 * Quản trị đổi trạng thái đơn. Ném RuntimeException (thông báo hiển thị được) nếu không hợp lệ.
 * Trả về chuỗi thông báo thành công.
 */
function admin_change_order_status(PDO $pdo, int $orderId, string $to, int $adminId, ?string $note): string
{
    $labels = $GLOBALS['ORDER_STATUS'];
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch();
        if (!$o) {
            throw new RuntimeException('Không tìm thấy đơn hàng.');
        }
        if (!in_array($to, allowed_next_statuses($o['status']), true)) {
            throw new RuntimeException('Không thể chuyển đơn từ "' . ($labels[$o['status']] ?? $o['status'])
                . '" sang "' . ($labels[$to] ?? $to) . '".');
        }

        if ($to === 'cancelled') {
            restore_cancelled_order($pdo, $o, $adminId, 'Quản trị hủy đơn ' . $o['order_code']);
        }
        if ($to === 'completed') {
            // giao thành công: thanh toán đang chờ (vd. COD thu tiền khi giao) -> đã thanh toán
            $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ? AND status = 'pending'")
                ->execute([$orderId]);
        }

        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$to, $orderId]);
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note) VALUES (?, ?, ?, ?, ?)'
        )->execute([$orderId, $o['status'], $to, $adminId, $note !== null && $note !== '' ? $note : null]);

        $pdo->commit();
        audit_log($adminId, 'admin_order_status', 'orders', $orderId, $o['order_code'] . ': ' . $o['status'] . ' -> ' . $to);
        return 'Đã chuyển đơn ' . $o['order_code'] . ' sang "' . ($labels[$to] ?? $to) . '".';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
