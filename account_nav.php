<?php
// Menu bên trái cho các trang tài khoản. Cần biến $accActive: account | orders | addresses | password
$accUser  = current_user();
$accItems = [
    'account'   => ['account.php',   '👤', 'Thông tin tài khoản'],
    'orders'    => ['orders.php',    '📦', 'Đơn hàng của tôi'],
    'addresses' => ['addresses.php', '📍', 'Sổ địa chỉ'],
    'password'  => ['password.php',  '🔒', 'Đổi mật khẩu'],
];
?>
<aside class="acc-nav">
    <div class="acc-user">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($accUser['full_name'], 0, 1))) ?></span>
        <div><small>Tài khoản của</small><strong><?= e($accUser['full_name']) ?></strong></div>
    </div>
    <?php foreach ($accItems as $key => [$href, $ico, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= ($accActive ?? '') === $key ? 'on' : '' ?>"><span><?= $ico ?></span> <?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="logout.php"><span>🚪</span> Thoát tài khoản</a>
</aside>
