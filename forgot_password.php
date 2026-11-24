<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_lib.php';
require_guest();

$errors  = [];
$sent    = false;
$devLink = null;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }
    $email = trim(mb_strtolower($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'Vui lòng nhập một địa chỉ email hợp lệ.';
    }

    if (!$errors) {
        $token = issue_reset_token($email, 'user', 30);
        $sent  = true;   // luôn báo giống nhau, dù email có tồn tại hay không
        if ($token !== null) {
            $path = '/reset_password.php?token=' . $token;
            if (RESET_DEV_LINK && is_local_request()) {
                $devLink = 'reset_password.php?token=' . $token;
            } else {
                send_reset_mail($email, site_base_url() . $path);
            }
        }
    }
}

$pageTitle = 'Quên mật khẩu';
require __DIR__ . '/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Quên mật khẩu</h1>
        <p class="auth-sub">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết để bạn đặt mật khẩu mới.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="alert alert-success">Nếu email này đã đăng ký, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu. Liên kết có hiệu lực trong 30 phút.</div>
            <?php if ($devLink): ?>
                <div class="dev-box">
                    <strong>Chế độ localhost</strong>
                    <p>XAMPP chưa cấu hình gửi email nên liên kết được hiển thị tại đây (chỉ thấy khi truy cập từ chính máy này):</p>
                    <a href="<?= e($devLink) ?>">Đặt lại mật khẩu →</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" action="forgot_password.php" novalidate>
                <?= csrf_field() ?>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Nhập email đã đăng ký" required autofocus value="<?= e($email) ?>">
                <button type="submit" class="btn-red">Gửi liên kết đặt lại</button>
            </form>
        <?php endif; ?>

        <p class="auth-foot"><a href="login.php">← Quay lại đăng nhập</a></p>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
