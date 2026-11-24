<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

/* ---------- Lấy sách ---------- */
$st = $pdo->prepare(
    "SELECT b.*, p.name AS publisher_name
     FROM books b LEFT JOIN publishers p ON p.id = b.publisher_id
     WHERE b.id = ? AND b.status = 'active'"
);
$st->execute([$id]);
$book = $st->fetch();

if (!$book) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sách';
    require __DIR__ . '/header.php';
    echo '<div class="section"><div class="empty">Sách không tồn tại hoặc đã ngừng bán. <a href="index.php" style="color:var(--red)">Về trang chủ</a></div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

/* ---------- Gửi / sửa đánh giá (POST) ---------- */
$reviewErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Vui lòng đăng nhập để viết đánh giá.');
        redirect('login.php');
    }
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!csrf_valid()) {
        $reviewErrors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    }
    if ($rating < 1 || $rating > 5) {
        $reviewErrors[] = 'Vui lòng chọn số sao từ 1 đến 5.';
    }
    if (mb_strlen($comment) > 2000) {
        $reviewErrors[] = 'Nhận xét tối đa 2000 ký tự.';
    }

    if (!$reviewErrors) {
        // đã mua (đơn hoàn thành) thì gắn nhãn "Đã mua hàng"
        $st = $pdo->prepare(
            "SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND oi.book_id = ? AND o.status = 'completed' LIMIT 1"
        );
        $st->execute([$user['id'], $id]);
        $verified = $st->fetchColumn() ? 1 : 0;

        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO reviews (user_id, book_id, rating, comment, is_verified_purchase)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment),
                                         is_verified_purchase = VALUES(is_verified_purchase)'
            )->execute([$user['id'], $id, $rating, $comment === '' ? null : $comment, $verified]);

            // cập nhật cột tổng hợp trong bảng books
            $pdo->prepare(
                "UPDATE books SET
                    avg_rating   = (SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM reviews WHERE book_id = ? AND status = 'visible'),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE book_id = ? AND status = 'visible')
                 WHERE id = ?"
            )->execute([$id, $id, $id]);
            $pdo->commit();

            audit_log($user['id'], 'review_book', 'books', $id, 'Đánh giá ' . $rating . ' sao');
            flash_set('success', 'Cảm ơn bạn đã đánh giá sản phẩm!');
            redirect('book.php?id=' . $id . '#reviews');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $reviewErrors[] = 'Có lỗi hệ thống, vui lòng thử lại sau.';
        }
    }
}

/* ---------- Dữ liệu hiển thị ---------- */
// Tác giả
$st = $pdo->prepare('SELECT a.name FROM book_authors ba JOIN authors a ON a.id = ba.author_id WHERE ba.book_id = ? ORDER BY a.name');
$st->execute([$id]);
$authors = $st->fetchAll(PDO::FETCH_COLUMN);

// Danh mục (ưu tiên danh mục con để làm breadcrumb)
$st = $pdo->prepare(
    'SELECT c.id, c.name, c.parent_id, p.name AS parent_name
     FROM book_categories bc
     JOIN categories c ON c.id = bc.category_id
     LEFT JOIN categories p ON p.id = c.parent_id
     WHERE bc.book_id = ?
     ORDER BY (c.parent_id IS NULL) ASC, c.id ASC'
);
$st->execute([$id]);
$cats = $st->fetchAll();
$mainCat = $cats[0] ?? null;

// Thống kê đánh giá (đếm trực tiếp từ bảng reviews)
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$st = $pdo->prepare("SELECT rating, COUNT(*) AS c FROM reviews WHERE book_id = ? AND status = 'visible' GROUP BY rating");
$st->execute([$id]);
foreach ($st->fetchAll() as $r) {
    $dist[(int)$r['rating']] = (int)$r['c'];
}
$reviewTotal = array_sum($dist);
$sum = 0;
foreach ($dist as $star => $c) { $sum += $star * $c; }
$avg = $reviewTotal > 0 ? $sum / $reviewTotal : 0;

// Danh sách đánh giá
$st = $pdo->prepare(
    "SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.book_id = ? AND r.status = 'visible' ORDER BY r.created_at DESC LIMIT 20"
);
$st->execute([$id]);
$reviews = $st->fetchAll();

// Đánh giá của chính người dùng (để điền sẵn form)
$user = current_user();
$myReview = null;
if ($user) {
    $st = $pdo->prepare('SELECT rating, comment FROM reviews WHERE user_id = ? AND book_id = ?');
    $st->execute([$user['id'], $id]);
    $myReview = $st->fetch() ?: null;
}

// Sách liên quan (cùng danh mục)
$st = $pdo->prepare(
    "SELECT DISTINCT b.* FROM books b
     JOIN book_categories bc ON bc.book_id = b.id
     WHERE bc.category_id IN (SELECT category_id FROM book_categories WHERE book_id = ?)
       AND b.id <> ? AND b.status = 'active'
     ORDER BY b.sold_count DESC, b.id DESC LIMIT 5"
);
$st->execute([$id, $id]);
$related = $st->fetchAll();

$final  = final_price($book);
$off    = discount_percent($book);
$stock  = (int)$book['stock'];
$maxQty = max(1, min(100, $stock));

$pageTitle = $book['title'];
require __DIR__ . '/header.php';
?>
<div class="breadcrumb">
    <a href="index.php">Trang chủ</a> ›
    <a href="search.php">Sách</a>
    <?php if ($mainCat): ?>
        <?php if ($mainCat['parent_id']): ?>› <a href="search.php?cat=<?= (int)$mainCat['parent_id'] ?>"><?= e($mainCat['parent_name']) ?></a><?php endif; ?>
        › <a href="search.php?cat=<?= (int)$mainCat['id'] ?>"><?= e($mainCat['name']) ?></a>
    <?php endif; ?>
</div>

<!-- ===== Khối thông tin chính ===== -->
<div class="detail-main">
    <div class="detail-cover">
        <?= cover_html($book, 'book-cover big', '', 200) ?>
    </div>

    <div class="detail-info">
        <h1><?= e($book['title']) ?></h1>

        <div class="meta-grid">
            <div>Nhà xuất bản: <strong><?= e($book['publisher_name'] ?? 'Đang cập nhật') ?></strong></div>
            <div>Tác giả: <strong><?= $authors ? e(implode(', ', $authors)) : 'Đang cập nhật' ?></strong></div>
        </div>

        <div class="rating-line">
            <span class="stars"><?= stars_html($avg) ?></span>
            <span class="muted">(<?= $reviewTotal ?> đánh giá)</span>
            <span class="muted">| Đã bán <?= (int)$book['sold_count'] ?></span>
        </div>

        <div class="detail-price">
            <span class="price big"><?= money($final) ?></span>
            <?php if ($off > 0): ?>
                <span class="old"><?= money($book['price']) ?></span>
                <span class="off">-<?= $off ?>%</span>
            <?php endif; ?>
        </div>

        <?php if ($stock > 0): ?>
            <p class="muted">Còn <?= $stock ?> cuốn trong kho</p>
            <form method="post" action="cart_add.php" class="buy-form">
                <?= csrf_field() ?>
                <input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>">
                <div class="qty-row">
                    <span>Số lượng:</span>
                    <div class="qty">
                        <button type="button" id="qty-minus">−</button>
                        <input type="text" id="qty" name="qty" value="1" readonly data-max="<?= $maxQty ?>">
                        <button type="button" id="qty-plus">+</button>
                    </div>
                </div>
                <div class="buy-actions">
                    <button type="submit" class="btn-outline">Thêm vào giỏ hàng</button>
                    <button type="submit" name="buy_now" value="1" class="btn-red btn-inline">Mua ngay</button>
                </div>
            </form>
        <?php else: ?>
            <p class="out-stock">Hết hàng</p>
        <?php endif; ?>

        <div class="policy">
            <h4>Chính sách của MPB</h4>
            <div class="policy-row">
                <div><strong>🚚 Thời gian giao hàng</strong><br><span class="muted">Giao nhanh và đúng hẹn</span></div>
                <div><strong>🔁 Chính sách đổi trả</strong><br><span class="muted">Đổi trả trong 30 ngày</span></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Thông tin chi tiết ===== -->
<div class="section">
    <div class="section-head"><h2>Thông tin chi tiết</h2></div>
    <table class="info-table">
        <?php if ($book['isbn']): ?><tr><th>Mã hàng (ISBN)</th><td><?= e($book['isbn']) ?></td></tr><?php endif; ?>
        <tr><th>Nhà xuất bản</th><td><?= e($book['publisher_name'] ?? 'Đang cập nhật') ?></td></tr>
        <tr><th>Tác giả</th><td><?= $authors ? e(implode(', ', $authors)) : 'Đang cập nhật' ?></td></tr>
        <?php if ($book['publish_year']): ?><tr><th>Năm xuất bản</th><td><?= (int)$book['publish_year'] ?></td></tr><?php endif; ?>
        <?php if ($book['pages']): ?><tr><th>Số trang</th><td><?= (int)$book['pages'] ?></td></tr><?php endif; ?>
        <?php if ($cats): ?>
            <tr><th>Danh mục</th><td><?= e(implode(', ', array_column($cats, 'name'))) ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- ===== Mô tả ===== -->
<div class="section">
    <div class="section-head"><h2>Mô tả sản phẩm</h2></div>
    <div class="desc">
        <strong><?= e($book['title']) ?></strong>
        <p><?= $book['description'] ? nl2br(e($book['description'])) : 'Đang cập nhật mô tả.' ?></p>
    </div>
</div>

<!-- ===== Đánh giá ===== -->
<div class="section" id="reviews">
    <div class="section-head"><h2>Đánh giá sản phẩm</h2></div>

    <div class="review-summary">
        <div class="rv-score">
            <div class="rv-num"><?= number_format($avg, 1, ',', '.') ?><small> / 5</small></div>
            <div class="stars big"><?= stars_html($avg) ?></div>
            <div class="muted"><?= $reviewTotal ?> lượt đánh giá</div>
        </div>
        <div class="rv-bars">
            <?php foreach ($dist as $star => $c): $pct = $reviewTotal ? round($c * 100 / $reviewTotal) : 0; ?>
                <div class="rv-bar">
                    <span><?= $star ?> sao</span>
                    <div class="bar"><div style="width: <?= $pct ?>%"></div></div>
                    <span><?= $pct ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="rv-write">
            <p class="muted">Chia sẻ cảm nhận của bạn về sản phẩm này</p>
            <?php if ($user): ?>
                <a href="#review-form" class="btn-outline">Viết đánh giá</a>
            <?php else: ?>
                <a href="login.php" class="btn-outline">Đăng nhập để đánh giá</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($user): ?>
        <form method="post" action="book.php?id=<?= $id ?>#review-form" id="review-form" class="review-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <h4><?= $myReview ? 'Cập nhật đánh giá của bạn' : 'Đánh giá của bạn' ?></h4>
            <?php if ($reviewErrors): ?>
                <div class="alert alert-error"><ul><?php foreach ($reviewErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php $selRating = (int)($_POST['rating'] ?? ($myReview['rating'] ?? 5)); ?>
            <select name="rating">
                <?php foreach ([5 => 'Tuyệt vời', 4 => 'Hài lòng', 3 => 'Bình thường', 2 => 'Không hài lòng', 1 => 'Rất tệ'] as $n => $lbl): ?>
                    <option value="<?= $n ?>" <?= $selRating === $n ? 'selected' : '' ?>><?= str_repeat('★', $n) ?> - <?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
            <textarea name="comment" rows="3" maxlength="2000" placeholder="Nhận xét của bạn về cuốn sách..."><?= e($_POST['comment'] ?? ($myReview['comment'] ?? '')) ?></textarea>
            <button type="submit" class="btn-red btn-inline">Gửi đánh giá</button>
        </form>
    <?php endif; ?>

    <div class="review-list">
        <?php foreach ($reviews as $r): ?>
            <div class="review-item">
                <div class="rv-top">
                    <strong><?= e($r['full_name']) ?></strong>
                    <?php if ($r['is_verified_purchase']): ?><span class="verified">✔ Đã mua hàng</span><?php endif; ?>
                    <span class="muted"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></span>
                </div>
                <div class="stars"><?= stars_html((float)$r['rating']) ?></div>
                <?php if ($r['comment']): ?><p><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><div class="empty">Chưa có đánh giá nào cho sản phẩm này.</div><?php endif; ?>
    </div>
</div>

<!-- ===== Sách liên quan ===== -->
<?php if ($related): ?>
<div class="section">
    <div class="section-head"><h2>Sản phẩm liên quan</h2></div>
    <div class="book-grid"><?php foreach ($related as $b) echo book_card($b); ?></div>
</div>
<?php endif; ?>

<script>
(function () {
    var input = document.getElementById('qty');
    if (!input) { return; }
    var max = parseInt(input.dataset.max, 10) || 1;
    document.getElementById('qty-minus').addEventListener('click', function () {
        input.value = Math.max(1, parseInt(input.value, 10) - 1);
    });
    document.getElementById('qty-plus').addEventListener('click', function () {
        input.value = Math.min(max, parseInt(input.value, 10) + 1);
    });
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
