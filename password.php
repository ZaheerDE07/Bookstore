<?php
require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$pdo  = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $new2    = $_POST['new2'] ?? '';

    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    $hash = (string)$st->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Mật khẩu hiện tại không đúng.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    }
    if ($new !== $new2) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }
    if (!$errors && hash_equals($current, $new)) {
        $errors[] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        audit_log((int)$user['id'], 'change_password', 'users', (int)$user['id']);
        flash_set('success', 'Đổi mật khẩu thành công.');
        redirect('password.php');
    }
}

$accActive = 'password';
$pageTitle = 'Đổi mật khẩu';
require __DIR__ . '/header.php';
?>
<div class="breadcrumb"><a href="index.php">Trang chủ</a> › <a href="account.php">Tài khoản</a> › <strong>Đổi mật khẩu</strong></div>

<div class="acc-layout">
    <?php require __DIR__ . '/account_nav.php'; ?>

    <div class="acc-main">
        <div class="co-card" style="max-width:520px">
            <h3>ĐỔI MẬT KHẨU</h3>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="password.php" novalidate>
                <?= csrf_field() ?>
                <label class="f-lbl" for="current">Mật khẩu hiện tại</label>
                <div class="pw"><input type="password" id="current" name="current" autocomplete="current-password">
                    <button type="button" class="pw-toggle" data-target="current" aria-label="Hiện/ẩn">👁</button></div>

                <label class="f-lbl" for="new">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                <div class="pw"><input type="password" id="new" name="new" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="new" aria-label="Hiện/ẩn">👁</button></div>

                <label class="f-lbl" for="new2">Nhập lại mật khẩu mới</label>
                <div class="pw"><input type="password" id="new2" name="new2" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="new2" aria-label="Hiện/ẩn">👁</button></div>

                <button type="submit" class="btn-red btn-inline" style="margin-top:16px">Cập nhật mật khẩu</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
