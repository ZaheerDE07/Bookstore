<?php
require_once __DIR__ . '/functions.php';

$user      = current_user();
$flash     = flash_get();
$pageTitle = $pageTitle ?? 'Trang chủ';
$searchQ   = trim($_GET['q'] ?? '');

// Danh mục cho menu (cha trước, con sau)
$menuCats = db()->query(
    'SELECT id, name, parent_id FROM categories ORDER BY parent_id IS NOT NULL, parent_id, id'
)->fetchAll();

// Số lượng sản phẩm trong giỏ của user đang đăng nhập
$cartCount = 0;
if ($user) {
    $st = db()->prepare(
        'SELECT COALESCE(SUM(ci.quantity), 0)
         FROM carts c JOIN cart_items ci ON ci.cart_id = c.id
         WHERE c.user_id = ?'
    );
    $st->execute([$user['id']]);
    $cartCount = (int)$st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - MPB.com</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="pages.css">
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>

<div class="promo-bar">
    <div class="promo-inner">
        <div class="promo-left">
            <span class="promo-badge">SAIGON BOOKS</span>
            <span class="slogan">THẮP SÁNG TRI THỨC - KẾT NỐI MỌI NHÀ</span>
        </div>
        <div class="promo-right">
            <a class="pill pill-yellow" href="search.php?sort=best">Ưu đãi HOT</a>
            <a class="pill pill-white" href="search.php?sort=new">MUA NGAY</a>
        </div>
    </div>
</div>

<header class="site-header">
    <div class="header-inner">
        <a class="logo" href="index.php">MPB<span>.com</span></a>

        <div class="dropdown">
            <button type="button" class="dropdown-toggle">☰ ▾</button>
            <div class="dropdown-menu">
                <?php foreach ($menuCats as $c): ?>
                    <a href="search.php?cat=<?= (int)$c['id'] ?>"><?= $c['parent_id'] ? '&nbsp;&nbsp;↳ ' : '' ?><?= e($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form class="search-box" method="get" action="search.php">
            <input type="text" name="q" placeholder="Tìm kiếm sách, tác giả..." value="<?= e($searchQ) ?>">
            <button type="submit" aria-label="Tìm kiếm">🔍</button>
        </form>

        <div class="header-actions">
            <a class="h-item" href="#" title="Chưa có thông báo">
                <span class="ico">🔔</span>Thông báo
            </a>
            <a class="h-item" href="cart.php">
                <span class="ico">🛒</span>Giỏ hàng
                <?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
            <div class="dropdown">
                <a class="h-item" href="<?= $user ? 'account.php' : 'login.php' ?>">
                    <span class="ico">👤</span><?= $user ? e(mb_strimwidth($user['full_name'], 0, 14, '…')) : 'Tài khoản' ?>
                </a>
                <div class="dropdown-menu right">
                    <?php if ($user): ?>
                        <span class="dd-text"><?= e($user['email']) ?></span>
                        <a href="account.php">Thông tin tài khoản</a>
                        <a href="orders.php">Đơn hàng của tôi</a>
                        <a href="addresses.php">Sổ địa chỉ</a>
                        <?php if (($user['role'] ?? '') === 'admin'): ?><a href="admin/index.php">⚙ Trang quản trị</a><?php endif; ?>
                        <a href="logout.php">Đăng xuất</a>
                    <?php else: ?>
                        <a href="login.php">Đăng nhập</a>
                        <a href="register.php">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="container">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>
