<?php
require_once __DIR__ . '/functions.php';
require_guest();

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim(mb_strtolower($_POST['email'] ?? ''));
    $old['phone']     = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password2        = $_POST['password2'] ?? '';
    $agreed           = isset($_POST['terms']);

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Họ tên không được để trống và tối đa 100 ký tự.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = 'Email không hợp lệ.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+]{9,15}$/', $old['phone'])) {
        $errors[] = 'Số điện thoại chỉ gồm số hoặc dấu +, dài 9–15 ký tự.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    if ($password !== $password2) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }
    if (!$agreed) {
        $errors[] = 'Bạn cần đồng ý với Điều khoản dịch vụ và Chính sách bảo mật.';
    }

    if (!$errors) {
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = 'Email này đã được đăng ký.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare(
                'INSERT INTO users (email, password_hash, full_name, phone, role, status)
                 VALUES (?, ?, ?, ?, "user", "active")'
            );
            $st->execute([
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['full_name'],
                $old['phone'] === '' ? null : $old['phone'],
            ]);
            $newId = (int)$pdo->lastInsertId();

            // mỗi user có 1 giỏ hàng (carts.user_id UNIQUE)
            $pdo->prepare('INSERT INTO carts (user_id) VALUES (?)')->execute([$newId]);

            $pdo->commit();
            audit_log($newId, 'register', 'users', $newId, 'Đăng ký tài khoản mới');

            flash_set('success', 'Đăng ký thành công! Hãy đăng nhập.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = ($ex->getCode() === '23000')
                ? 'Email này đã được đăng ký.'
                : 'Có lỗi hệ thống, vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Đăng ký';
require __DIR__ . '/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Đăng Ký Tài Khoản</h1>
        <p class="auth-sub">Tạo tài khoản để mua sách và nhận ưu đãi</p>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
            <?= csrf_field() ?>

            <label for="full_name">Họ và tên</label>
            <input type="text" id="full_name" name="full_name" maxlength="100" placeholder="Nhập họ và tên" required value="<?= e($old['full_name']) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="150" placeholder="Nhập email" required value="<?= e($old['email']) ?>">

            <label for="phone">Số điện thoại</label>
            <input type="text" id="phone" name="phone" maxlength="20" placeholder="Nhập số điện thoại (không bắt buộc)" value="<?= e($old['phone']) ?>">

            <label for="password">Mật khẩu</label>
            <div class="pw">
                <input type="password" id="password" name="password" placeholder="Tối thiểu 8 ký tự" required>
                <button type="button" class="pw-toggle" data-target="password" aria-label="Hiện/ẩn mật khẩu">👁</button>
            </div>

            <label for="password2">Xác nhận mật khẩu</label>
            <div class="pw">
                <input type="password" id="password2" name="password2" placeholder="Nhập lại mật khẩu" required>
                <button type="button" class="pw-toggle" data-target="password2" aria-label="Hiện/ẩn mật khẩu">👁</button>
            </div>

            <label class="check">
                <input type="checkbox" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                <span>Tôi đồng ý với <a href="#">Điều khoản dịch vụ</a> &amp; <a href="#">Chính sách bảo mật</a></span>
            </label>

            <button type="submit" class="btn-red">Đăng ký</button>
        </form>

        <div class="divider">Hoặc đăng ký bằng</div>
        <div class="social">
            <button type="button" disabled title="Sắp ra mắt">Facebook</button>
            <button type="button" disabled title="Sắp ra mắt">Google</button>
        </div>

        <p class="auth-foot">Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
