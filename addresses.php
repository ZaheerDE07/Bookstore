<?php
require_once __DIR__ . '/functions.php';
require_login();

const MAX_ADDRESSES = 10;

$user = current_user();
$pdo  = db();
$errors = [];

$blank = ['id' => 0, 'receiver_name' => '', 'phone' => '', 'province' => '', 'district' => '', 'ward' => '', 'address' => '', 'is_default' => 0];
$form  = $blank;

/* ---------- POST: thêm / sửa / xóa / đặt mặc định ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if (!csrf_valid()) {
        flash_set('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect('addresses.php');
    }

    // địa chỉ phải thuộc về user đang đăng nhập
    $owned = null;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ?');
        $st->execute([$id, $user['id']]);
        $owned = $st->fetch();
        if (!$owned) {
            flash_set('error', 'Không tìm thấy địa chỉ.');
            redirect('addresses.php');
        }
    }

    if ($action === 'delete') {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        if ((int)$owned['is_default'] === 1) {   // xóa địa chỉ mặc định -> chuyển mặc định cho địa chỉ còn lại đầu tiên
            $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE user_id = ? ORDER BY id LIMIT 1')->execute([$user['id']]);
        }
        $pdo->commit();
        flash_set('success', 'Đã xóa địa chỉ.');
        redirect('addresses.php');
    }

    if ($action === 'default') {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$user['id']]);
        $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
        $pdo->commit();
        flash_set('success', 'Đã đặt làm địa chỉ mặc định.');
        redirect('addresses.php');
    }

    if ($action === 'save') {
        foreach (['receiver_name', 'phone', 'province', 'district', 'ward', 'address'] as $f) {
            $form[$f] = trim($_POST[$f] ?? '');
        }
        $form['id'] = $id;
        $form['is_default'] = isset($_POST['is_default']) ? 1 : 0;

        if ($form['receiver_name'] === '' || mb_strlen($form['receiver_name']) > 100) { $errors[] = 'Vui lòng nhập họ tên người nhận (tối đa 100 ký tự).'; }
        if (!preg_match('/^[0-9+]{9,15}$/', $form['phone']))                           { $errors[] = 'Số điện thoại chỉ gồm số hoặc dấu +, dài 9–15 ký tự.'; }
        if ($form['province'] === '' || mb_strlen($form['province']) > 100)           { $errors[] = 'Vui lòng nhập Tỉnh/Thành phố.'; }
        if ($form['district'] === '' || mb_strlen($form['district']) > 100)           { $errors[] = 'Vui lòng nhập Quận/Huyện.'; }
        if (mb_strlen($form['ward']) > 100)                                            { $errors[] = 'Phường/Xã tối đa 100 ký tự.'; }
        if ($form['address'] === '' || mb_strlen($form['address']) > 255)             { $errors[] = 'Vui lòng nhập địa chỉ (tối đa 255 ký tự).'; }

        if (!$errors && $id === 0) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
            $st->execute([$user['id']]);
            if ((int)$st->fetchColumn() >= MAX_ADDRESSES) {
                $errors[] = 'Bạn chỉ có thể lưu tối đa ' . MAX_ADDRESSES . ' địa chỉ.';
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
            $st->execute([$user['id']]);
            $isFirst = ((int)$st->fetchColumn() === 0);
            $makeDefault = $form['is_default'] === 1 || $isFirst;

            if ($makeDefault) {
                $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$user['id']]);
            }
            $ward = $form['ward'] === '' ? null : $form['ward'];

            if ($id > 0) {
                // giữ nguyên cờ mặc định nếu không tick (tránh làm user không còn địa chỉ mặc định)
                $def = $makeDefault ? 1 : (int)$owned['is_default'];
                $pdo->prepare(
                    'UPDATE addresses SET receiver_name=?, phone=?, province=?, district=?, ward=?, address=?, is_default=?
                     WHERE id=? AND user_id=?'
                )->execute([$form['receiver_name'], $form['phone'], $form['province'], $form['district'], $ward, $form['address'], $def, $id, $user['id']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO addresses (user_id, receiver_name, phone, province, district, ward, address, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$user['id'], $form['receiver_name'], $form['phone'], $form['province'], $form['district'], $ward, $form['address'], $makeDefault ? 1 : 0]);
            }
            $pdo->commit();
            flash_set('success', $id > 0 ? 'Đã cập nhật địa chỉ.' : 'Đã thêm địa chỉ mới.');
            redirect('addresses.php');
        }
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ?');
    $st->execute([(int)$_GET['edit'], $user['id']]);
    $found = $st->fetch();
    if ($found) {
        $form = $found;
        $form['ward'] = (string)$form['ward'];
    }
}

$st = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id');
$st->execute([$user['id']]);
$addresses = $st->fetchAll();

$showForm = $errors || isset($_GET['edit']) || isset($_GET['new']) || !$addresses;

$accActive = 'addresses';
$pageTitle = 'Sổ địa chỉ';
require __DIR__ . '/header.php';
?>
<div class="breadcrumb"><a href="index.php">Trang chủ</a> › <a href="account.php">Tài khoản</a> › <strong>Sổ địa chỉ</strong></div>

<div class="acc-layout">
    <?php require __DIR__ . '/account_nav.php'; ?>

    <div class="acc-main">
        <div class="co-card">
            <div class="card-head">
                <h3>SỔ ĐỊA CHỈ</h3>
                <?php if (!$showForm): ?><a class="btn-outline" href="addresses.php?new=1">+ Thêm địa chỉ mới</a><?php endif; ?>
            </div>

            <?php if ($addresses): ?>
                <?php foreach ($addresses as $a): ?>
                    <div class="addr-item">
                        <div class="addr-body">
                            <strong><?= e($a['receiver_name']) ?></strong> <span class="muted">| <?= e($a['phone']) ?></span>
                            <?php if ($a['is_default']): ?><span class="tag-default">Mặc định</span><?php endif; ?>
                            <div><?= e(implode(', ', array_filter([$a['address'], $a['ward'], $a['district'], $a['province']]))) ?></div>
                        </div>
                        <div class="addr-actions">
                            <a href="addresses.php?edit=<?= (int)$a['id'] ?>">Sửa</a>
                            <?php if (!$a['is_default']): ?>
                                <form method="post" action="addresses.php">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit">Đặt mặc định</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="addresses.php" onsubmit="return confirm('Xóa địa chỉ này?')">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="danger">Xóa</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Bạn chưa có địa chỉ nào. Hãy thêm địa chỉ để đặt hàng nhanh hơn.</p>
            <?php endif; ?>
        </div>

        <?php if ($showForm): ?>
        <div class="co-card">
            <h3><?= $form['id'] ? 'SỬA ĐỊA CHỈ' : 'THÊM ĐỊA CHỈ MỚI' ?></h3>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="addresses.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="co-row"><label for="receiver_name">Họ và tên người nhận</label>
                    <input type="text" id="receiver_name" name="receiver_name" maxlength="100" value="<?= e($form['receiver_name']) ?>"></div>
                <div class="co-row"><label for="phone">Số điện thoại</label>
                    <input type="text" id="phone" name="phone" maxlength="20" value="<?= e($form['phone']) ?>"></div>
                <div class="co-row"><label for="province">Tỉnh/Thành phố</label>
                    <input type="text" id="province" name="province" maxlength="100" value="<?= e($form['province']) ?>"></div>
                <div class="co-row"><label for="district">Quận/Huyện</label>
                    <input type="text" id="district" name="district" maxlength="100" value="<?= e($form['district']) ?>"></div>
                <div class="co-row"><label for="ward">Phường/Xã</label>
                    <input type="text" id="ward" name="ward" maxlength="100" placeholder="Không bắt buộc" value="<?= e($form['ward']) ?>"></div>
                <div class="co-row"><label for="address">Địa chỉ</label>
                    <input type="text" id="address" name="address" maxlength="255" placeholder="Số nhà, tên đường" value="<?= e($form['address']) ?>"></div>
                <label class="check co-check"><input type="checkbox" name="is_default" value="1" <?= $form['is_default'] ? 'checked' : '' ?>> Đặt làm địa chỉ mặc định</label>

                <div class="form-actions">
                    <button type="submit" class="btn-red btn-inline">Lưu địa chỉ</button>
                    <?php if ($addresses): ?><a href="addresses.php" class="btn-outline">Hủy</a><?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
