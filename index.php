<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';

$pdo = db();

// 2 mã khuyến mãi đang còn hạn
$coupons = $pdo->query(
    "SELECT code, discount_type, discount_value, min_order FROM coupons
     WHERE is_active = 1 AND CURDATE() BETWEEN start_date AND end_date
       AND (usage_limit IS NULL OR used_count < usage_limit)
     ORDER BY id LIMIT 2"
)->fetchAll();

$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY id')->fetchAll();

$newBooks = $pdo->query(
    "SELECT * FROM books WHERE status = 'active' ORDER BY created_at DESC, id DESC LIMIT 5"
)->fetchAll();
$bestBooks = $pdo->query(
    "SELECT * FROM books WHERE status = 'active' ORDER BY sold_count DESC, id LIMIT 5"
)->fetchAll();

$catIcons  = ['van-hoc' => '📖', 'tieu-thuyet' => '📕', 'truyen-ngan' => '📝', 'ky-nang-song' => '💡', 'thieu-nhi' => '🧸'];
$catColors = ['#e8737a', '#f2a65a', '#4caf8a', '#4a90e2', '#9b6bd6', '#e66fc0', '#3bb8c9', '#8bc34a'];

function coupon_text(array $c): string
{
    return $c['discount_type'] === 'percent'
        ? 'Giảm ' . (int)$c['discount_value'] . '%'
        : 'Giảm ' . number_format((float)$c['discount_value'] / 1000, 0, ',', '.') . 'K';
}

$pageTitle = 'Trang chủ';
require __DIR__ . '/header.php';
?>
<!-- Banner + mã khuyến mãi -->
<div class="hero">
    <div class="hero-main">
        <span class="hero-tag">TRANG SÁCH HỌC</span>
        <h2>Tủ Sách Văn Học<br>Giảm đến <em>40%</em></h2>
        <a class="btn-hero" href="search.php?cat=1">XEM NGAY →</a>
        <div class="hero-deco">📚</div>
    </div>
    <div class="hero-side">
        <?php foreach ($coupons as $i => $c): ?>
            <div class="coupon-card c<?= $i + 1 ?>">
                <small>Nhập mã <?= e($c['code']) ?></small>
                <h3><?= e(coupon_text($c)) ?></h3>
                <p>Áp dụng cho đơn từ <?= money($c['min_order']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 4 thẻ khuyến mãi -->
<div class="promo-cards">
    <a class="pc a" href="search.php?cat=1"><small>ĐỘC QUYỀN · MPB.com</small><strong>Tủ sách Văn học</strong><span>MUA NGAY</span></a>
    <a class="pc b" href="search.php?cat=5"><small>HOT · THIẾU NHI</small><strong>Thế giới tuổi thơ giảm sâu</strong><span>MUA NGAY</span></a>
    <a class="pc c" href="search.php?cat=4"><small>BEST SELLER</small><strong>Sách Kỹ năng sống bán chạy</strong><span>MUA NGAY</span></a>
    <a class="pc d" href="search.php?sort=new"><small>THẮP SÁNG TRI THỨC</small><strong>Sách mới mỗi tuần</strong><span>MUA NGAY</span></a>
</div>

<!-- Danh mục -->
<div class="cat-row">
    <?php foreach ($categories as $i => $c): ?>
        <a class="cat-item" href="search.php?cat=<?= (int)$c['id'] ?>">
            <span class="cat-ico" style="background: <?= $catColors[$i % count($catColors)] ?>"><?= $catIcons[$c['slug']] ?? '📚' ?></span>
            <?= e($c['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Sách bán chạy -->
<div class="section">
    <div class="section-head"><h2>Sách bán chạy nhất</h2><a href="search.php?sort=best">Xem tất cả ›</a></div>
    <?php if ($bestBooks): ?>
        <div class="book-grid"><?php foreach ($bestBooks as $b) echo book_card($b); ?></div>
    <?php else: ?><div class="empty">Chưa có sách.</div><?php endif; ?>
</div>

<!-- Sách mới -->
<div class="section">
    <div class="section-head"><h2>Sách mới lên kệ</h2><a href="search.php?sort=new">Xem tất cả ›</a></div>
    <?php if ($newBooks): ?>
        <div class="book-grid"><?php foreach ($newBooks as $b) echo book_card($b); ?></div>
    <?php else: ?><div class="empty">Chưa có sách.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
