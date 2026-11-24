<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/checkout_lib.php';
require_login();

$user = current_user();
$pdo  = db();
$statusLabels = $GLOBALS['ORDER_STATUS'];

/* Các tab theo layout: Tất cả / Chờ xử lý / Đang vận chuyển / Đã giao / Đã hủy */
$tabs = [
    'all'       => ['Tất cả',          null],
    'pending'   => ['Chờ xử lý',       ['pending', 'confirmed']],
    'shipping'  => ['Đang vận chuyển', ['shipping']],
    'completed' => ['Đã giao',         ['completed']],
    'cancelled' => ['Đã hủy',          ['cancelled']],
];
$tab = $_GET['tab'] ?? 'all';
if (!isset($tabs[$tab])) {
    $tab = 'all';
}

// số đơn theo từng trạng thái (để hiện trên tab)
$st = $pdo->prepare('SELECT status, COUNT(*) FROM orders WHERE user_id = ? GROUP BY status');
$st->execute([$user['id']]);
$byStatus = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$tabCount = [];
foreach ($tabs as $k => [$label, $sts]) {
    $tabCount[$k] = $sts === null ? array_sum($byStatus) : array_sum(array_intersect_key($byStatus, array_flip($sts)));
}

/* ---------- Truy vấn đơn hàng của tab hiện tại ---------- */
$where  = 'o.user_id = ?';
$params = [$user['id']];
if ($tabs[$tab][1] !== null) {
    $where .= ' AND o.status IN (' . implode(',', array_fill(0, count($tabs[$tab][1]), '?')) . ')';
    $params = array_merge($params, $tabs[$tab][1]);
}

$perPage    = 8;
$total      = $tabCount[$tab];
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$st = $pdo->prepare(
    "SELECT o.* FROM orders o WHERE $where ORDER BY o.created_at DESC, o.id DESC LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$orders = $st->fetchAll();

// sản phẩm của các đơn trong trang
$itemsByOrder = [];
if ($orders) {
    $ids = array_map('intval', array_column($orders, 'id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT oi.*, b.slug, b.cover_image FROM order_items oi LEFT JOIN books b ON b.id = oi.book_id WHERE oi.order_id IN ($in) ORDER BY oi.id");
    $st->execute($ids);
    foreach ($st->fetchAll() as $oi) {
        $itemsByOrder[(int)$oi['order_id']][] = $oi;
    }
}

$accActive = 'orders';
$pageTitle = 'Đơn hàng của tôi';
require __DIR__ . '/header.php';
?>
<div class="breadcrumb"><a href="index.php">Trang chủ</a> › <a href="account.php">Tài khoản</a> › <strong>Đơn hàng của tôi</strong></div>

<div class="acc-layout">
    <?php require __DIR__ . '/account_nav.php'; ?>

    <div class="acc-main">
        <div class="co-card">
            <h3>ĐƠN HÀNG CỦA TÔI</h3>

            <div class="order-tabs">
                <?php foreach ($tabs as $k => [$label, $sts]): ?>
                    <a href="orders.php?tab=<?= e($k) ?>" class="<?= $tab === $k ? 'on' : '' ?>">
                        <?= e($label) ?><?php if ($tabCount[$k] > 0): ?> <small>(<?= $tabCount[$k] ?>)</small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (!$orders): ?>
                <div class="empty">Chưa có đơn hàng nào.<br><br>
                    <a class="btn-red btn-inline" style="display:inline-block" href="search.php">Mua sắm ngay</a></div>
            <?php endif; ?>

            <?php foreach ($orders as $o):
                $its   = $itemsByOrder[(int)$o['id']] ?? [];
                $shown = array_slice($its, 0, 2);
                $more  = count($its) - count($shown);
            ?>
                <div class="order-card">
                    <div class="oc-head">
                        <div>
                            <a class="oc-code" href="order_detail.php?code=<?= e($o['order_code']) ?>">Mã đơn hàng: #<?= e($o['order_code']) ?></a>
                            <span class="muted"> · Ngày đặt <?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></span>
                        </div>
                        <span class="status st-<?= e($o['status']) ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span>
                    </div>

                    <?php foreach ($shown as $oi): ?>
                        <div class="oc-item">
                            <?= cover_html($oi, 'oc-cover', '', 18) ?>
                            <div class="oc-name">
                                <a href="book.php?id=<?= (int)$oi['book_id'] ?>"><?= e($oi['book_title']) ?></a>
                                <small class="muted">Số lượng: <?= (int)$oi['quantity'] ?></small>
                            </div>
                            <div class="oc-price"><?= money($oi['unit_price']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($more > 0): ?><div class="muted" style="padding:4px 0 8px">+ <?= $more ?> sản phẩm khác</div><?php endif; ?>

                    <div class="oc-foot">
                        <div class="oc-ship muted">Giao đến: <?= e($o['receiver_name']) ?> - <?= e($o['phone']) ?></div>
                        <div class="oc-total">Thành tiền: <strong class="price"><?= money($o['total_amount']) ?></strong></div>
                    </div>
                    <div class="oc-actions">
                        <a class="btn-outline btn-xs" href="order_detail.php?code=<?= e($o['order_code']) ?>">Xem chi tiết</a>
                        <?php if (in_array($o['status'], ['completed', 'cancelled'], true)): ?>
                            <form method="post" action="order_action.php">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reorder"><input type="hidden" name="code" value="<?= e($o['order_code']) ?>">
                                <button type="submit" class="btn-red btn-xs">Mua lại</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($o['status'] === 'pending'): ?>
                            <form method="post" action="order_action.php" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn <?= e($o['order_code']) ?>?')">
                                <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="code" value="<?= e($o['order_code']) ?>">
                                <button type="submit" class="btn-gray btn-xs">Hủy đơn</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="orders.php?tab=<?= e($tab) ?>&page=<?= $page - 1 ?>">‹</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="orders.php?tab=<?= e($tab) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="orders.php?tab=<?= e($tab) ?>&page=<?= $page + 1 ?>">›</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
