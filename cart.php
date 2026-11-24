<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_login();

$user = current_user();
$pdo  = db();

$st = $pdo->prepare(
    'SELECT ci.id AS item_id, ci.quantity, b.id AS book_id, b.title, b.slug, b.cover_image, b.price, b.sale_price, b.stock, b.status
     FROM carts c
     JOIN cart_items ci ON ci.cart_id = c.id
     JOIN books b ON b.id = ci.book_id
     WHERE c.user_id = ?
     ORDER BY ci.id DESC'
);
$st->execute([$user['id']]);
$items = $st->fetchAll();

// gợi ý vài mã khuyến mãi đang còn hạn
$hints = $pdo->query(
    "SELECT code, discount_type, discount_value, min_order FROM coupons
     WHERE is_active = 1 AND CURDATE() BETWEEN start_date AND end_date
       AND (usage_limit IS NULL OR used_count < usage_limit)
     ORDER BY id LIMIT 3"
)->fetchAll();

$pageTitle = 'Giỏ hàng';
require __DIR__ . '/header.php';
?>
<h1 class="page-title">Giỏ hàng</h1>

<?php if (!$items): ?>
    <div class="section">
        <div class="empty">
            Giỏ hàng của bạn đang trống.<br><br>
            <a class="btn-red btn-inline" href="search.php" style="display:inline-block">Tiếp tục mua sắm</a>
        </div>
    </div>
<?php else: ?>
<div class="cart-layout">
    <!-- ===== Danh sách sản phẩm ===== -->
    <div class="cart-list">
        <div class="cart-head">
            <label class="check-all"><input type="checkbox" id="check-all" checked> Chọn tất cả (<?= count($items) ?> sản phẩm)</label>
            <span>Số lượng</span>
            <span>Thành tiền</span>
            <span></span>
        </div>

        <?php foreach ($items as $it):
            $qty    = (int)$it['quantity'];
            $stock  = (int)$it['stock'];
            $unit   = (int)final_price($it);
            $line   = $unit * $qty;
            $note   = '';
            if ($it['status'] !== 'active')      { $note = 'Sản phẩm ngừng bán'; }
            elseif ($stock < 1)                  { $note = 'Hết hàng'; }
            elseif ($qty > $stock)               { $note = 'Chỉ còn ' . $stock . ' cuốn - hãy giảm số lượng'; }
            $ok     = ($note === '');
            $maxQty = max(1, min(100, $stock));
        ?>
        <div class="cart-row <?= $ok ? '' : 'bad' ?>">
            <div class="c-select">
                <input type="checkbox" class="item-check" name="items[]" value="<?= (int)$it['item_id'] ?>"
                       form="checkout-form" data-line="<?= $line ?>" <?= $ok ? 'checked' : 'disabled' ?>>
            </div>
            <a class="c-link" href="book.php?id=<?= (int)$it['book_id'] ?>"><?= cover_html($it, 'c-cover', '', 24) ?></a>
            <div class="c-info">
                <a class="c-title" href="book.php?id=<?= (int)$it['book_id'] ?>"><?= e($it['title']) ?></a>
                <div class="price-row">
                    <span class="price"><?= money($unit) ?></span>
                    <?php if (discount_percent($it) > 0): ?><span class="old"><?= money($it['price']) ?></span><?php endif; ?>
                </div>
                <?php if ($note): ?><div class="c-note"><?= e($note) ?></div><?php endif; ?>
            </div>

            <form method="post" action="cart_update.php" class="c-qty">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                <div class="qty">
                    <button type="submit" name="delta" value="-1" <?= $qty <= 1 ? 'disabled' : '' ?>>−</button>
                    <input type="number" name="qty" value="<?= $qty ?>" min="1" max="<?= $maxQty ?>" onchange="this.form.submit()">
                    <button type="submit" name="delta" value="1" <?= $qty >= $maxQty ? 'disabled' : '' ?>>+</button>
                </div>
            </form>

            <div class="c-total"><?= money($line) ?></div>

            <form method="post" action="cart_update.php" class="c-del">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                <button type="submit" title="Xóa khỏi giỏ" onclick="return confirm('Xóa sách này khỏi giỏ hàng?')">🗑</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== Tóm tắt ===== -->
    <aside class="cart-summary">
        <div class="sum-block">
            <h4>🎁 Khuyến mãi</h4>
            <?php if ($hints): ?>
                <?php foreach ($hints as $h): ?>
                    <div class="hint">
                        <strong><?= e($h['code']) ?></strong>
                        <span><?= $h['discount_type'] === 'percent' ? 'Giảm ' . (int)$h['discount_value'] . '%' : 'Giảm ' . money($h['discount_value']) ?>
                            · đơn từ <?= money($h['min_order']) ?></span>
                    </div>
                <?php endforeach; ?>
                <p class="muted">Nhập mã ở bước thanh toán.</p>
            <?php else: ?>
                <p class="muted">Hiện chưa có mã khuyến mãi.</p>
            <?php endif; ?>
        </div>

        <div class="sum-block">
            <div class="sum-line"><span>Đã chọn</span><span><span id="sum-count">0</span> sản phẩm</span></div>
            <div class="sum-line"><span>Thành tiền</span><span id="sum-sub">0 đ</span></div>
            <div class="sum-line total"><span>Tổng số tiền</span><span id="sum-total">0 đ</span></div>
            <p class="muted">Phí vận chuyển và mã giảm giá được tính ở bước thanh toán.</p>

            <form method="post" action="checkout.php" id="checkout-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <button type="submit" class="btn-red" id="btn-checkout">THANH TOÁN</button>
            </form>
        </div>
    </aside>
</div>

<script>
(function () {
    var checks = [].slice.call(document.querySelectorAll('.item-check:not(:disabled)'));
    var all = document.getElementById('check-all');
    var btn = document.getElementById('btn-checkout');
    function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' đ'; }
    function update() {
        var sum = 0, cnt = 0;
        checks.forEach(function (c) { if (c.checked) { sum += parseInt(c.dataset.line, 10); cnt++; } });
        document.getElementById('sum-count').textContent = cnt;
        document.getElementById('sum-sub').textContent = fmt(sum);
        document.getElementById('sum-total').textContent = fmt(sum);
        btn.disabled = (cnt === 0);
        all.checked = (cnt === checks.length && cnt > 0);
    }
    checks.forEach(function (c) { c.addEventListener('change', update); });
    all.addEventListener('change', function () { checks.forEach(function (c) { c.checked = all.checked; }); update(); });
    update();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
