<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$pdo  = db();
$errors = [];

$st = $pdo->prepare('SELECT id, email, full_name, phone, created_at FROM users WHERE id = ?');
$st->execute([$user['id']]);
$me = $st->fetch();

$old = ['full_name' => $me['full_name'], 'phone' => (string)$me['phone']];

/* ---------- Cập nhật thông tin cá nhân (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Họ tên không được để trống và tối đa 100 ký tự.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+]{9,15}$/', $old['phone'])) {
        $errors[] = 'Số điện thoại chỉ gồm số hoặc dấu +, dài 9–15 ký tự.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
            ->execute([$old['full_name'], $old['phone'] === '' ? null : $old['phone'], $user['id']]);
        $_SESSION['user']['full_name'] = $old['full_name'];
        audit_log((int)$user['id'], 'update_profile', 'users', (int)$user['id']);
        flash_set('success', 'Đã cập nhật thông tin cá nhân.');
        redirect('account.php');
    }
}

/* ---------- Địa chỉ mặc định ---------- */
$st = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id LIMIT 1');
$st->execute([$user['id']]);
$addr = $st->fetch();

/* ---------- Mã khuyến mãi còn hạn + số lượt còn lại của bạn ---------- */
$st = $pdo->prepare(
    "SELECT c.*,
            (SELECT COUNT(*) FROM coupon_usages cu
              WHERE cu.coupon_id = c.id AND cu.user_id = ?
                AND cu.period_key = IF(c.limit_period = 'month', DATE_FORMAT(CURDATE(), '%Y-%m'), 'ALL')) AS my_used
     FROM coupons c
     WHERE c.is_active = 1 AND CURDATE() BETWEEN c.start_date AND c.end_date
       AND (c.usage_limit IS NULL OR c.used_count < c.usage_limit)
     ORDER BY c.end_date, c.id"
);
$st->execute([$user['id']]);
$coupons = $st->fetchAll();

$accActive = 'account';
$pageTitle = 'Thông tin tài khoản';
require __DIR__ . '/header.php';
?>
<div class="breadcrumb"><a href="index.php">Trang chủ</a> › <strong>Thông tin tài khoản</strong></div>

<div class="acc-layout">
    <?php require __DIR__ . '/account_nav.php'; ?>

    <div class="acc-main">
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="acc-two">
            <div class="co-card">
                <h3>THÔNG TIN CÁ NHÂN</h3>
                <form method="post" action="account.php" novalidate>
                    <?= csrf_field() ?>
                    <label class="f-lbl" for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($old['full_name']) ?>">

                    <label class="f-lbl" for="email">Email</label>
                    <input type="text" id="email" value="<?= e($me['email']) ?>" disabled>
                    <small class="muted">Email dùng để đăng nhập nên không thể thay đổi.</small>

                    <label class="f-lbl" for="phone">Số điện thoại</label>
                    <input type="text" id="phone" name="phone" maxlength="20" placeholder="Chưa có số điện thoại" value="<?= e($old['phone']) ?>">

                    <p class="muted" style="margin-top:12px">Thành viên từ <?= e(date('d/m/Y', strtotime($me['created_at']))) ?></p>
                    <button type="submit" class="btn-red btn-inline">Lưu thay đổi</button>
                </form>
            </div>

            <div class="co-card">
                <h3>ĐỊA CHỈ MẶC ĐỊNH</h3>
                <?php if ($addr): ?>
                    <p><strong><?= e($addr['receiver_name']) ?></strong> - <?= e($addr['phone']) ?></p>
                    <p>📍 <?= e(implode(', ', array_filter([$addr['address'], $addr['ward'], $addr['district'], $addr['province']]))) ?></p>
                <?php else: ?>
                    <p class="muted">Bạn chưa lưu địa chỉ nào.</p>
                <?php endif; ?>
                <a href="addresses.php" class="btn-outline" style="margin-top:8px">Quản lý sổ địa chỉ</a>
            </div>
        </div>

        <div class="co-card">
            <h3>MÃ KHUYẾN MÃI ĐANG CÓ</h3>
            <?php if ($coupons): ?>
                <div class="coupon-list">
                    <?php foreach ($coupons as $c):
                        $left = $c['per_user_limit'] === null ? null : max(0, (int)$c['per_user_limit'] - (int)$c['my_used']);
                    ?>
                        <div class="coupon-ticket <?= $left === 0 ? 'used' : '' ?>">
                            <strong><?= e($c['code']) ?></strong>
                            <span><?= $c['discount_type'] === 'percent' ? 'Giảm ' . (int)$c['discount_value'] . '%' : 'Giảm ' . money($c['discount_value']) ?></span>
                            <small>Đơn từ <?= money($c['min_order']) ?> · HSD <?= e(date('d/m/Y', strtotime($c['end_date']))) ?></small>
                            <small><?= $left === null ? 'Không giới hạn lượt' : ($left === 0 ? 'Bạn đã dùng hết lượt' : 'Bạn còn ' . $left . ' lượt' . ($c['limit_period'] === 'month' ? ' trong tháng' : '')) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Hiện chưa có mã khuyến mãi nào.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
