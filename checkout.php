<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/checkout_lib.php';
require_login();

$user = current_user();
$pdo  = db();
$PAYMENT_METHODS = $GLOBALS['PAYMENT_METHODS'];

/* ---------- B1: nhận danh sách sản phẩm đã chọn từ giỏ hàng ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    if (!csrf_valid()) {
        flash_set('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect('cart.php');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['items'] ?? [])))));
    if (!$ids) {
        flash_set('error', 'Vui lòng chọn ít nhất 1 sản phẩm để thanh toán.');
        redirect('cart.php');
    }
    $_SESSION['checkout_items'] = $ids;
    unset($_SESSION['checkout_coupon']);
    redirect('checkout.php');
}

$ids = $_SESSION['checkout_items'] ?? [];
if (!$ids) {
    redirect('cart.php');
}

/* ---------- Tải sản phẩm đang thanh toán (luôn lấy từ DB) ---------- */
$in = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare(
    "SELECT ci.id AS item_id, ci.quantity, b.*
     FROM cart_items ci
     JOIN carts c ON c.id = ci.cart_id
     JOIN books b ON b.id = ci.book_id
     WHERE c.user_id = ? AND ci.id IN ($in)
     ORDER BY ci.id"
);
$st->execute(array_merge([$user['id']], $ids));
$items = $st->fetchAll();

if (!$items) {
    unset($_SESSION['checkout_items'], $_SESSION['checkout_coupon']);
    flash_set('error', 'Các sản phẩm đã chọn không còn trong giỏ hàng.');
    redirect('cart.php');
}
foreach ($items as $it) {
    if ($it['status'] !== 'active' || (int)$it['stock'] < (int)$it['quantity']) {
        flash_set('error', 'Sách "' . $it['title'] . '" đã hết hàng hoặc không đủ số lượng, vui lòng cập nhật giỏ hàng.');
        redirect('cart.php');
    }
}

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (int)final_price($it) * (int)$it['quantity'];
}

/* ---------- Địa chỉ đã lưu + giá trị mặc định cho form ---------- */
$st = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id');
$st->execute([$user['id']]);
$addresses = $st->fetchAll();

$st = $pdo->prepare('SELECT full_name, phone FROM users WHERE id = ?');
$st->execute([$user['id']]);
$me = $st->fetch();

$def = $addresses[0] ?? null;
$old = [
    'receiver_name' => $def['receiver_name'] ?? ($me['full_name'] ?? ''),
    'phone'         => $def['phone'] ?? ($me['phone'] ?? ''),
    'province'      => $def['province'] ?? '',
    'district'      => $def['district'] ?? '',
    'ward'          => $def['ward'] ?? '',
    'address'       => $def['address'] ?? '',
    'payment'       => 'cod',
    'note'          => '',
    'save_address'  => false,
];

$errors       = [];
$couponError  = '';
$couponNotice = '';

/* ---------- POST: áp dụng / bỏ mã, đặt hàng ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    foreach (['receiver_name', 'phone', 'province', 'district', 'ward', 'address', 'note'] as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }
    $old['payment']      = $_POST['payment'] ?? 'cod';
    $old['save_address'] = isset($_POST['save_address']);

    if (!csrf_valid()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
        $action = '';
    }

    if ($action === 'apply_coupon') {
        $res = coupon_check($pdo, $_POST['coupon_code'] ?? '', (int)$user['id'], $subtotal);
        if (isset($res['error'])) {
            $couponError = $res['error'];
            unset($_SESSION['checkout_coupon']);
        } else {
            $_SESSION['checkout_coupon'] = strtoupper(trim($_POST['coupon_code']));
            $couponNotice = 'Áp dụng mã thành công!';
        }
    } elseif ($action === 'remove_coupon') {
        unset($_SESSION['checkout_coupon']);
    }
}

/* ---------- Tính tiền (mã khuyến mãi lấy từ session, kiểm tra lại mỗi lần) ---------- */
$couponCode = $_SESSION['checkout_coupon'] ?? '';
$discount   = 0;
if ($couponCode !== '') {
    $res = coupon_check($pdo, $couponCode, (int)$user['id'], $subtotal);
    if (isset($res['error'])) {
        $couponError = $couponError ?: $res['error'];
        unset($_SESSION['checkout_coupon']);
        $couponCode = '';
    } else {
        $discount = $res['discount'];
    }
}
$shipping = shipping_fee($subtotal);
$total    = $subtotal - $discount + $shipping;

/* ---------- Đặt hàng ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place' && !$errors) {
    // 1. kiểm tra dữ liệu nhập
    if ($old['receiver_name'] === '' || mb_strlen($old['receiver_name']) > 100) {
        $errors[] = 'Vui lòng nhập họ tên người nhận (tối đa 100 ký tự).';
    }
    if (!preg_match('/^[0-9+]{9,15}$/', $old['phone'])) {
        $errors[] = 'Số điện thoại chỉ gồm số hoặc dấu +, dài 9–15 ký tự.';
    }
    if ($old['province'] === '' || mb_strlen($old['province']) > 100) {
        $errors[] = 'Vui lòng nhập Tỉnh/Thành phố.';
    }
    if ($old['district'] === '' || mb_strlen($old['district']) > 100) {
        $errors[] = 'Vui lòng nhập Quận/Huyện.';
    }
    if (mb_strlen($old['ward']) > 100) {
        $errors[] = 'Phường/Xã tối đa 100 ký tự.';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors[] = 'Vui lòng nhập địa chỉ nhận hàng.';
    }
    if (!isset($PAYMENT_METHODS[$old['payment']])) {
        $errors[] = 'Vui lòng chọn phương thức thanh toán.';
    }
    if (mb_strlen($old['note']) > 255) {
        $errors[] = 'Ghi chú tối đa 255 ký tự.';
    }
    $shippingAddress = implode(', ', array_filter([$old['address'], $old['ward'], $old['district'], $old['province']]));
    if (mb_strlen($shippingAddress) > 255) {
        $errors[] = 'Địa chỉ giao hàng quá dài (tối đa 255 ký tự).';
    }

    // 2. tạo đơn trong 1 transaction
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // khóa các dòng sách, kiểm tra tồn kho + lấy giá mới nhất
            $bookIds = array_map('intval', array_column($items, 'id'));
            sort($bookIds);
            $in2 = implode(',', array_fill(0, count($bookIds), '?'));
            $st = $pdo->prepare("SELECT id, title, price, sale_price, stock, status FROM books WHERE id IN ($in2) ORDER BY id FOR UPDATE");
            $st->execute($bookIds);
            $fresh = [];
            foreach ($st->fetchAll() as $r) {
                $fresh[(int)$r['id']] = $r;
            }

            $lines = [];
            $sub   = 0;
            foreach ($items as $it) {
                $bk  = $fresh[(int)$it['id']] ?? null;
                $qty = (int)$it['quantity'];
                if (!$bk || $bk['status'] !== 'active' || (int)$bk['stock'] < $qty) {
                    throw new RuntimeException('Sách "' . $it['title'] . '" vừa hết hàng hoặc không đủ số lượng.');
                }
                $unit    = (int)final_price($bk);
                $lines[] = ['book_id' => (int)$bk['id'], 'title' => $bk['title'], 'qty' => $qty, 'unit' => $unit];
                $sub    += $unit * $qty;
            }

            // mã khuyến mãi (khóa dòng coupon)
            $couponRow = null; $disc = 0; $periodKey = 'ALL'; $usageNo = 1;
            if ($couponCode !== '') {
                $r = coupon_check($pdo, $couponCode, (int)$user['id'], $sub, true);
                if (isset($r['error'])) {
                    throw new RuntimeException($r['error']);
                }
                $couponRow = $r['coupon'];
                $disc      = $r['discount'];
                $periodKey = $r['period_key'];
                $usageNo   = $r['next_no'];
            }
            $ship  = shipping_fee($sub);
            $grand = $sub - $disc + $ship;

            // đơn hàng (mã tạm, đổi thành DHxxxx sau khi có id)
            $pdo->prepare(
                'INSERT INTO orders (order_code, user_id, receiver_name, phone, shipping_address,
                                     subtotal, discount_amount, shipping_fee, total_amount, coupon_id, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                'T' . substr(bin2hex(random_bytes(10)), 0, 19),
                $user['id'], $old['receiver_name'], $old['phone'], $shippingAddress,
                $sub, $disc, $ship, $grand,
                $couponRow ? (int)$couponRow['id'] : null,
                $old['note'] === '' ? null : $old['note'],
            ]);
            $orderId   = (int)$pdo->lastInsertId();
            $orderCode = 'DH' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE orders SET order_code = ? WHERE id = ?')->execute([$orderCode, $orderId]);

            // chi tiết đơn + trừ kho + ghi lịch sử kho
            $insItem = $pdo->prepare('INSERT INTO order_items (order_id, book_id, book_title, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
            $updBook = $pdo->prepare('UPDATE books SET stock = stock - ?, sold_count = sold_count + ? WHERE id = ? AND stock >= ?');
            $insMove = $pdo->prepare(
                "INSERT INTO stock_movements (book_id, change_qty, reason, order_id, note, created_by)
                 VALUES (?, ?, 'sale', ?, ?, ?)"
            );
            foreach ($lines as $ln) {
                $insItem->execute([$orderId, $ln['book_id'], $ln['title'], $ln['qty'], $ln['unit']]);
                $updBook->execute([$ln['qty'], $ln['qty'], $ln['book_id'], $ln['qty']]);
                if ($updBook->rowCount() !== 1) {
                    throw new RuntimeException('Sách "' . $ln['title'] . '" không đủ số lượng trong kho.');
                }
                $insMove->execute([$ln['book_id'], -$ln['qty'], $orderId, 'Đơn ' . $orderCode, $user['id']]);
            }

            // thanh toán + lịch sử trạng thái
            $pdo->prepare("INSERT INTO payments (order_id, method, amount, status) VALUES (?, ?, ?, 'pending')")
                ->execute([$orderId, $old['payment'], $grand]);
            $pdo->prepare(
                "INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
                 VALUES (?, NULL, 'pending', ?, 'Khách đặt hàng')"
            )->execute([$orderId, $user['id']]);

            // ghi nhận lượt dùng mã
            if ($couponRow) {
                $pdo->prepare(
                    'INSERT INTO coupon_usages (coupon_id, user_id, order_id, period_key, usage_no) VALUES (?, ?, ?, ?, ?)'
                )->execute([$couponRow['id'], $user['id'], $orderId, $periodKey, $usageNo]);
                $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$couponRow['id']]);
            }

            // xóa các sản phẩm đã mua khỏi giỏ
            $cartIds = array_map('intval', array_column($items, 'item_id'));
            $in3 = implode(',', array_fill(0, count($cartIds), '?'));
            $pdo->prepare("DELETE FROM cart_items WHERE id IN ($in3)")->execute($cartIds);

            // lưu địa chỉ mới nếu khách chọn
            if ($old['save_address']) {
                $st = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
                $st->execute([$user['id']]);
                $isFirst = ((int)$st->fetchColumn() === 0) ? 1 : 0;
                $pdo->prepare(
                    'INSERT INTO addresses (user_id, receiver_name, phone, province, district, ward, address, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $user['id'], $old['receiver_name'], $old['phone'], $old['province'], $old['district'],
                    $old['ward'] === '' ? null : $old['ward'], $old['address'], $isFirst,
                ]);
            }

            $pdo->commit();

            audit_log((int)$user['id'], 'place_order', 'orders', $orderId, 'Đặt đơn ' . $orderCode . ' - ' . money($grand));
            unset($_SESSION['checkout_items'], $_SESSION['checkout_coupon']);
            flash_set('success', 'Đặt hàng thành công!');
            redirect('order_success.php?code=' . urlencode($orderCode));
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('checkout: ' . $ex->getMessage());
            $errors[] = ($ex->getCode() === '23000' && stripos($ex->getMessage(), 'coupon') !== false)
                ? 'Mã khuyến mãi vừa được sử dụng, vui lòng thử lại.'
                : 'Có lỗi hệ thống khi tạo đơn hàng, vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Thanh toán';
require __DIR__ . '/header.php';
?>
<h1 class="page-title">Thanh toán</h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="checkout.php" id="checkout-form" novalidate>
    <?= csrf_field() ?>

    <!-- ===== Địa chỉ giao hàng ===== -->
    <div class="co-card">
        <h3>ĐỊA CHỈ GIAO HÀNG</h3>

        <?php if ($addresses): ?>
            <div class="co-row">
                <label for="saved">Địa chỉ đã lưu</label>
                <select id="saved">
                    <option value="">-- Nhập địa chỉ mới --</option>
                    <?php foreach ($addresses as $i => $a): ?>
                        <option value="<?= $i ?>"
                                data-name="<?= e($a['receiver_name']) ?>" data-phone="<?= e($a['phone']) ?>"
                                data-province="<?= e($a['province']) ?>" data-district="<?= e($a['district']) ?>"
                                data-ward="<?= e($a['ward'] ?? '') ?>" data-address="<?= e($a['address']) ?>">
                            <?= e($a['receiver_name'] . ' - ' . $a['address'] . ', ' . $a['district'] . ', ' . $a['province']) ?><?= $a['is_default'] ? ' (Mặc định)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="co-row"><label for="receiver_name">Họ và tên người nhận</label>
            <input type="text" id="receiver_name" name="receiver_name" maxlength="100" placeholder="Nhập họ và tên người nhận" value="<?= e($old['receiver_name']) ?>"></div>
        <div class="co-row"><label>Email</label>
            <input type="text" value="<?= e($user['email']) ?>" disabled></div>
        <div class="co-row"><label for="phone">Số điện thoại</label>
            <input type="text" id="phone" name="phone" maxlength="20" placeholder="Nhập số điện thoại" value="<?= e($old['phone']) ?>"></div>
        <div class="co-row"><label>Quốc gia</label>
            <input type="text" value="Việt Nam" disabled></div>
        <div class="co-row"><label for="province">Tỉnh/Thành phố</label>
            <input type="text" id="province" name="province" maxlength="100" placeholder="VD: TP. Hồ Chí Minh" value="<?= e($old['province']) ?>"></div>
        <div class="co-row"><label for="district">Quận/Huyện</label>
            <input type="text" id="district" name="district" maxlength="100" placeholder="VD: Quận 1" value="<?= e($old['district']) ?>"></div>
        <div class="co-row"><label for="ward">Phường/Xã</label>
            <input type="text" id="ward" name="ward" maxlength="100" placeholder="Không bắt buộc" value="<?= e($old['ward']) ?>"></div>
        <div class="co-row"><label for="address">Địa chỉ nhận hàng</label>
            <input type="text" id="address" name="address" maxlength="255" placeholder="Số nhà, tên đường" value="<?= e($old['address']) ?>"></div>
        <label class="check co-check"><input type="checkbox" name="save_address" value="1" <?= $old['save_address'] ? 'checked' : '' ?>> Lưu địa chỉ này cho lần mua sau</label>
    </div>

    <!-- ===== Vận chuyển ===== -->
    <div class="co-card">
        <h3>PHƯƠNG THỨC VẬN CHUYỂN</h3>
        <p>Giao hàng tiêu chuẩn: <strong><?= $shipping === 0 ? 'Miễn phí' : money($shipping) ?></strong></p>
        <p class="muted">Phí vận chuyển <?= money(SHIP_FEE) ?>, miễn phí cho đơn từ <?= money(FREE_SHIP_MIN) ?>.</p>
    </div>

    <!-- ===== Thanh toán ===== -->
    <div class="co-card">
        <h3>PHƯƠNG THỨC THANH TOÁN</h3>
        <?php foreach ($PAYMENT_METHODS as $key => $label): ?>
            <label class="pay-opt">
                <input type="radio" name="payment" value="<?= e($key) ?>" <?= $old['payment'] === $key ? 'checked' : '' ?>>
                <?= e($label) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <!-- ===== Mã khuyến mãi ===== -->
    <div class="co-card">
        <h3>MÃ KHUYẾN MÃI</h3>
        <?php if ($couponCode !== ''): ?>
            <div class="coupon-ok">
                ✔ Đã áp dụng mã <strong><?= e($couponCode) ?></strong> - giảm <strong><?= money($discount) ?></strong>
                <button type="submit" name="action" value="remove_coupon" class="link-btn">Bỏ mã</button>
            </div>
        <?php else: ?>
            <div class="coupon-form">
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Nhập mã khuyến mãi" maxlength="50">
                <button type="submit" name="action" value="apply_coupon" id="btn-apply" class="btn-blue">Áp dụng</button>
            </div>
        <?php endif; ?>
        <?php if ($couponError): ?><p class="co-error"><?= e($couponError) ?></p><?php endif; ?>
        <?php if ($couponNotice): ?><p class="co-ok"><?= e($couponNotice) ?></p><?php endif; ?>
    </div>

    <!-- ===== Thông tin khác ===== -->
    <div class="co-card">
        <h3>THÔNG TIN KHÁC</h3>
        <textarea name="note" rows="2" maxlength="255" placeholder="Ghi chú cho đơn hàng (không bắt buộc)"><?= e($old['note']) ?></textarea>
    </div>

    <!-- ===== Kiểm tra lại đơn hàng ===== -->
    <div class="co-card">
        <h3>KIỂM TRA LẠI ĐƠN HÀNG</h3>
        <table class="order-table">
            <?php foreach ($items as $it): $unit = (int)final_price($it); $q = (int)$it['quantity']; ?>
                <tr>
                    <td class="ot-name"><a href="book.php?id=<?= (int)$it['id'] ?>"><?= e($it['title']) ?></a></td>
                    <td><?= money($unit) ?></td>
                    <td>x <?= $q ?></td>
                    <td class="ot-line"><?= money($unit * $q) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="co-sum">
            <div><span>Tạm tính</span><span><?= money($subtotal) ?></span></div>
            <div><span>Giảm giá</span><span><?= $discount > 0 ? '- ' . money($discount) : '0 đ' ?></span></div>
            <div><span>Phí vận chuyển</span><span><?= $shipping === 0 ? 'Miễn phí' : money($shipping) ?></span></div>
            <div class="grand"><span>Tổng thanh toán</span><span><?= money($total) ?></span></div>
        </div>
    </div>

    <div class="co-bar">
        <a href="cart.php">← Quay lại giỏ hàng</a>
        <div class="co-bar-right">
            <span>Tổng: <strong class="price big"><?= money($total) ?></strong></span>
            <button type="submit" name="action" value="place" id="btn-place" class="btn-red btn-inline">Xác nhận thanh toán</button>
        </div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('checkout-form');

    // Chọn địa chỉ đã lưu -> điền vào form
    var saved = document.getElementById('saved');
    if (saved) {
        saved.addEventListener('change', function () {
            var o = saved.options[saved.selectedIndex];
            if (!o.value) { return; }
            document.getElementById('receiver_name').value = o.dataset.name;
            document.getElementById('phone').value    = o.dataset.phone;
            document.getElementById('province').value = o.dataset.province;
            document.getElementById('district').value = o.dataset.district;
            document.getElementById('ward').value     = o.dataset.ward;
            document.getElementById('address').value  = o.dataset.address;
        });
    }

    // Enter trong ô nhập: chỉ ô mã khuyến mãi mới được "Áp dụng", các ô khác không tự gửi form
    form.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' || ev.target.tagName === 'TEXTAREA' || ev.target.tagName === 'BUTTON') { return; }
        ev.preventDefault();
        if (ev.target.id === 'coupon_code') {
            document.getElementById('btn-apply').click();
        }
    });

    // Tránh bấm "Xác nhận" 2 lần
    form.addEventListener('submit', function (ev) {
        var s = ev.submitter;
        if (s && s.id === 'btn-place') {
            // gửi giá trị action rồi mới khóa nút
            setTimeout(function () { s.disabled = true; s.textContent = 'Đang xử lý...'; }, 0);
        }
    });
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
