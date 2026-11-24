<?php
// Đổi số lượng / xóa sản phẩm trong giỏ (POST từ cart.php)
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}
if (!csrf_valid()) {
    flash_set('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
    redirect('cart.php');
}

$user   = current_user();
$pdo    = db();
$itemId = (int)($_POST['item_id'] ?? 0);
$action = $_POST['action'] ?? '';

// chỉ thao tác trên mục thuộc giỏ của chính user
$st = $pdo->prepare(
    'SELECT ci.id, ci.quantity, b.title, b.stock
     FROM cart_items ci
     JOIN carts c ON c.id = ci.cart_id
     JOIN books b ON b.id = ci.book_id
     WHERE ci.id = ? AND c.user_id = ?'
);
$st->execute([$itemId, $user['id']]);
$it = $st->fetch();

if (!$it) {
    flash_set('error', 'Không tìm thấy sản phẩm trong giỏ hàng.');
    redirect('cart.php');
}

if ($action === 'remove') {
    $pdo->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$itemId]);
    flash_set('success', 'Đã xóa "' . $it['title'] . '" khỏi giỏ hàng.');
    redirect('cart.php');
}

if ($action === 'update') {
    $qty = isset($_POST['delta'])
        ? (int)$it['quantity'] + (int)$_POST['delta']
        : (int)($_POST['qty'] ?? $it['quantity']);

    $cap = max(1, min(100, (int)$it['stock']));
    if ($qty > $cap) {
        flash_set('error', 'Chỉ có thể mua tối đa ' . $cap . ' cuốn "' . $it['title'] . '".');
        $qty = $cap;
    }
    $qty = max(1, $qty);

    $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$qty, $itemId]);
}

redirect('cart.php');
