<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_lib.php';
require_guest();

// token nằm trên URL: không cho trình duyệt/trang khác lưu hoặc gửi kèm referrer
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token  = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$valid  = find_reset_token($token, 'user') !== null;

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $err = complete_password_reset($token, 'user', $_POST['password'] ?? '', $_POST['password2'] ?? '');
        if ($err === null) {
            flash_set('success', 'Đặt lại mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.');
            redirect('login.php');
        }
        $errors[] = $err;
        $valid = find_reset_token($token, 'user') !== null;   // token có thể vừa hết hạn / bị dùng
    }
}

$pageTitle = 'Đặt lại mật khẩu';
require __DIR__ . '/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Đặt lại mật khẩu</h1>

        <?php if (!$valid): ?>
            <div class="alert alert-error">Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.</div>
            <a class="btn-red" style="display:block;text-align:center;margin-top:12px" href="forgot_password.php">Yêu cầu liên kết mới</a>
        <?php else: ?>
            <p class="auth-sub">Nhập mật khẩu mới cho tài khoản của bạn.</p>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="reset_password.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <label for="password">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                <div class="pw"><input type="password" id="password" name="password" autocomplete="new-password" autofocus>
                    <button type="button" class="pw-toggle" data-target="password" aria-label="Hiện/ẩn mật khẩu">👁</button></div>

                <label for="password2">Nhập lại mật khẩu mới</label>
                <div class="pw"><input type="password" id="password2" name="password2" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="password2" aria-label="Hiện/ẩn mật khẩu">👁</button></div>

                <button type="submit" class="btn-red">Cập nhật mật khẩu</button>
            </form>
        <?php endif; ?>

        <p class="auth-foot"><a href="login.php">← Quay lại đăng nhập</a></p>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
