<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_lib.php';
require_guest();

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }

    $email    = trim(mb_strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Vui lòng nhập email và mật khẩu.';
    }

    if (!$errors) {
        $err = try_login($email, $password);
        if ($err !== null) {
            $errors[] = $err;
        } else {
            // quay lại trang đang xem (vd: sách vừa bấm thêm giỏ); admin mặc định vào trang quản trị
            $next = $_SESSION['after_login'] ?? (current_user()['role'] === 'admin' ? 'admin/index.php' : 'index.php');
            unset($_SESSION['after_login']);
            redirect($next);
        }
    }
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-tabs">
            <a href="login.php" class="active">Đăng nhập</a>
            <a href="register.php">Đăng ký</a>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <?= csrf_field() ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Nhập email" required autofocus value="<?= e($email) ?>">

            <label for="password" class="lbl-row">Mật khẩu <a class="forgot-link" href="forgot_password.php">Quên mật khẩu?</a></label>
            <div class="pw">
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                <button type="button" class="pw-toggle" data-target="password" aria-label="Hiện/ẩn mật khẩu">👁</button>
            </div>

            <button type="submit" class="btn-red">Đăng nhập</button>
        </form>

        <div class="divider">Hoặc đăng nhập bằng</div>
        <div class="social">
            <button type="button" disabled title="Sắp ra mắt">Facebook</button>
            <button type="button" disabled title="Sắp ra mắt">Google</button>
        </div>

        <p class="auth-foot">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
