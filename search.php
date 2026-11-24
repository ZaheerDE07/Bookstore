<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helpers.php';

$pdo = db();

/* ---------- 1. Đọc tham số (GET) ---------- */
$q     = trim($_GET['q'] ?? '');
$catId = (int)($_GET['cat'] ?? 0);
$pubs  = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['pub'] ?? [])))));

$priceRanges = [
    'lt50'    => [null,   49999, 'Dưới 50.000 đ'],
    '50-100'  => [50000,  99999, '50.000 đ - 100.000 đ'],
    '100-200' => [100000, 199999, '100.000 đ - 200.000 đ'],
    'gt200'   => [200000, null,  'Từ 200.000 đ trở lên'],
];
$priceKey = $_GET['price'] ?? '';
if (!isset($priceRanges[$priceKey])) {
    $priceKey = '';
}

$sortOptions = [
    'popular'    => 'Phổ biến',
    'best'       => 'Bán chạy',
    'new'        => 'Mới nhất',
    'price_asc'  => 'Giá thấp - cao',
    'price_desc' => 'Giá cao - thấp',
];
$sort = $_GET['sort'] ?? 'popular';
if (!isset($sortOptions[$sort])) {
    $sort = 'popular';
}

$perPage = (int)($_GET['per'] ?? 12);
if (!in_array($perPage, [12, 24, 48], true)) {
    $perPage = 12;
}
$page = max(1, (int)($_GET['page'] ?? 1));

/* ---------- 2. Dựng WHERE ---------- */
$eff    = 'COALESCE(b.sale_price, b.price)';
$where  = ["b.status = 'active'"];
$params = [];

if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(b.title LIKE ? OR b.isbn LIKE ? OR EXISTS (
                    SELECT 1 FROM book_authors ba JOIN authors a ON a.id = ba.author_id
                    WHERE ba.book_id = b.id AND a.name LIKE ?))';
    array_push($params, $like, $like, $like);
}
if ($catId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM book_categories bc
                        WHERE bc.book_id = b.id
                          AND (bc.category_id = ?
                               OR bc.category_id IN (SELECT id FROM categories WHERE parent_id = ?)))';
    array_push($params, $catId, $catId);
}
if ($pubs) {
    $where[] = 'b.publisher_id IN (' . implode(',', array_fill(0, count($pubs), '?')) . ')';
    foreach ($pubs as $p) {
        $params[] = $p;
    }
}
if ($priceKey !== '') {
    [$min, $max] = $priceRanges[$priceKey];
    if ($min !== null) { $where[] = "$eff >= ?"; $params[] = $min; }
    if ($max !== null) { $where[] = "$eff <= ?"; $params[] = $max; }
}
$whereSql = implode(' AND ', $where);

$orderMap = [
    'popular'    => 'b.review_count DESC, b.avg_rating DESC, b.sold_count DESC, b.id DESC',
    'best'       => 'b.sold_count DESC, b.id DESC',
    'new'        => 'b.created_at DESC, b.id DESC',
    'price_asc'  => "$eff ASC, b.id ASC",
    'price_desc' => "$eff DESC, b.id ASC",
];

/* ---------- 3. Query: đếm + lấy trang ---------- */
$st = $pdo->prepare("SELECT COUNT(*) FROM books b WHERE $whereSql");
$st->execute($params);
$total      = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$st = $pdo->prepare(
    "SELECT b.* FROM books b WHERE $whereSql ORDER BY {$orderMap[$sort]} LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$books = $st->fetchAll();

/* ---------- 4. Dữ liệu cho sidebar ---------- */
$allCats = $pdo->query('SELECT id, name, parent_id FROM categories ORDER BY id')->fetchAll();
$catById = [];
foreach ($allCats as $c) {
    $catById[(int)$c['id']] = $c;
}
$publishers = $pdo->query(
    "SELECT p.id, p.name, COUNT(b.id) AS cnt
     FROM publishers p JOIN books b ON b.publisher_id = p.id AND b.status = 'active'
     GROUP BY p.id, p.name ORDER BY cnt DESC, p.name"
)->fetchAll();

/* ---------- 5. Helper tạo URL giữ nguyên bộ lọc ---------- */
function url_with(array $over): string
{
    $p = $_GET;
    foreach ($over as $k => $v) {
        if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; }
    }
    return 'search.php' . ($p ? '?' . http_build_query($p) : '');
}

/* ---------- 6. Tiêu đề / breadcrumb ---------- */
$crumbs = [];
$heading = 'Tất cả sách';
if ($catId > 0 && isset($catById[$catId])) {
    $cur = $catById[$catId];
    if ($cur['parent_id'] && isset($catById[(int)$cur['parent_id']])) {
        $par = $catById[(int)$cur['parent_id']];
        $crumbs[] = [$par['name'], 'search.php?cat=' . (int)$par['id']];
    }
    $crumbs[] = [$cur['name'], null];
    $heading = $cur['name'];
}
if ($q !== '') {
    $heading = 'Kết quả cho "' . $q . '"';
}
$filtersActive = ($pubs || $priceKey !== '');

$pageTitle = $q !== '' ? 'Tìm kiếm: ' . $q : $heading;
require __DIR__ . '/header.php';
?>
<div class="breadcrumb">
    <a href="index.php">Trang chủ</a> ›
    <a href="search.php">Sách</a>
    <?php foreach ($crumbs as [$name, $link]): ?>
        › <?= $link ? '<a href="' . e($link) . '">' . e($name) . '</a>' : '<strong>' . e($name) . '</strong>' ?>
    <?php endforeach; ?>
    <?php if ($q !== ''): ?> › <strong>Tìm kiếm</strong><?php endif; ?>
</div>

<div class="listing">
    <!-- ===== Bộ lọc ===== -->
    <aside class="filters">
        <h3>BỘ LỌC TÌM KIẾM</h3>

        <div class="f-group">
            <h4>DANH MỤC</h4>
            <ul class="f-list">
                <li><a href="<?= e(url_with(['cat' => null, 'page' => null])) ?>" class="<?= $catId === 0 ? 'on' : '' ?>">Tất cả</a></li>
                <?php foreach ($allCats as $c): ?>
                    <li class="<?= $c['parent_id'] ? 'sub' : '' ?>">
                        <a href="<?= e(url_with(['cat' => (int)$c['id'], 'page' => null])) ?>"
                           class="<?= $catId === (int)$c['id'] ? 'on' : '' ?>"><?= e($c['name']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form method="get" action="search.php">
            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
            <?php if ($catId > 0): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="per" value="<?= $perPage ?>">

            <div class="f-group">
                <h4>NHÀ XUẤT BẢN</h4>
                <?php foreach ($publishers as $p): ?>
                    <label class="f-check">
                        <input type="checkbox" name="pub[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $pubs, true) ? 'checked' : '' ?>>
                        <?= e($p['name']) ?> <small>(<?= (int)$p['cnt'] ?>)</small>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="f-group">
                <h4>KHOẢNG GIÁ</h4>
                <?php foreach ($priceRanges as $key => $r): ?>
                    <label class="f-check">
                        <input type="radio" name="price" value="<?= e($key) ?>" <?= $priceKey === $key ? 'checked' : '' ?>>
                        <?= e($r[2]) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-red btn-sm">Áp dụng</button>
            <?php if ($filtersActive): ?>
                <a class="clear-link" href="<?= e(url_with(['pub' => null, 'price' => null, 'page' => null])) ?>">Xóa bộ lọc</a>
            <?php endif; ?>
        </form>
    </aside>

    <!-- ===== Kết quả ===== -->
    <section class="results">
        <div class="results-head">
            <div>
                <h1><?= e($heading) ?></h1>
                <span class="muted"><?= $total ?> sản phẩm</span>
            </div>
        </div>

        <div class="sort-bar">
            <div class="sort-left">
                <span>Sắp xếp theo:</span>
                <?php foreach ($sortOptions as $key => $label): ?>
                    <a class="sort-btn <?= $sort === $key ? 'active' : '' ?>"
                       href="<?= e(url_with(['sort' => $key, 'page' => null])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <select class="per-select" onchange="location = this.value">
                <?php foreach ([12, 24, 48] as $n): ?>
                    <option value="<?= e(url_with(['per' => $n, 'page' => null])) ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> trên trang</option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($books): ?>
            <div class="book-grid cols4">
                <?php foreach ($books as $b) echo book_card($b); ?>
            </div>
        <?php else: ?>
            <div class="empty">Không tìm thấy sách phù hợp. Hãy thử từ khóa hoặc bộ lọc khác.</div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="<?= e(url_with(['page' => $page - 1])) ?>">‹</a><?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= e(url_with(['page' => $i])) ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= e(url_with(['page' => $page + 1])) ?>">›</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/footer.php'; ?>
