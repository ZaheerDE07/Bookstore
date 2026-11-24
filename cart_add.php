<?php
// Thêm sách vào giỏ hàng (POST từ trang chi tiết sách)
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$bookId = (int)($_POST['book_id'] ?? 0);
$qty    = (int)($_POST['qty'] ?? 1);
$back   = 'book.php?id=' . $bookId;

if (!csrf_valid()) {
    flash_set('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
    redirect($back);
}

$user = current_user();
if (!$user) {
    $_SESSION['after_login'] = $back;   // đăng nhập xong quay lại đúng sách
    flash_set('error', 'Vui lòng đăng nhập để thêm sách vào giỏ hàng.');
    redirect('login.php');
}

$pdo = db();
$st = $pdo->prepare("SELECT id, title, stock FROM books WHERE id = ? AND status = 'active'");
$st->execute([$bookId]);
$book = $st->fetch();

if (!$book) {
    flash_set('error', 'Sách không tồn tại hoặc đã ngừng bán.');
    redirect('index.php');
}
if ((int)$book['stock'] < 1) {
    flash_set('error', 'Sách này hiện đã hết hàng.');
    redirect($back);
}

$cap = min(100, (int)$book['stock']);        // tối đa 100 (CHECK) và không vượt tồn kho
$qty = max(1, min($qty, $cap));

// lấy (hoặc tạo) giỏ hàng của user
$st = $pdo->prepare('SELECT id FROM carts WHERE user_id = ?');
$st->execute([$user['id']]);
$cartId = $st->fetchColumn();
if (!$cartId) {
    $pdo->prepare('INSERT INTO carts (user_id) VALUES (?)')->execute([$user['id']]);
    $cartId = (int)$pdo->lastInsertId();
}

// đã có trong giỏ thì cộng dồn (không vượt $cap)
$pdo->prepare(
    'INSERT INTO cart_items (cart_id, book_id, quantity) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE quantity = LEAST(?, quantity + ?)'
)->execute([$cartId, $bookId, $qty, $cap, $qty]);

flash_set('success', 'Đã thêm "' . $book['title'] . '" vào giỏ hàng.');

if (!empty($_POST['buy_now']) && file_exists(__DIR__ . '/cart.php')) {
    redirect('cart.php');
}
redirect($back);
