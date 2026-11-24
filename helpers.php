<?php
// Hàm hiển thị dùng chung cho trang chủ / tìm kiếm / chi tiết sách

function money($n): string
{
    return number_format((float)$n, 0, ',', '.') . ' đ';
}

/** Giá bán thực tế: sale_price nếu có và thấp hơn giá gốc */
function final_price(array $b): float
{
    $price = (float)$b['price'];
    $sale  = $b['sale_price'] !== null ? (float)$b['sale_price'] : null;
    return ($sale !== null && $sale < $price) ? $sale : $price;
}

function discount_percent(array $b): int
{
    $price = (float)$b['price'];
    $final = final_price($b);
    return ($final < $price && $price > 0) ? (int)round((1 - $final / $price) * 100) : 0;
}

function stars_html(float $avg): string
{
    $n = (int)round($avg);
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

/**
 * Đường dẫn ảnh bìa (tương đối so với thư mục gốc web), hoặc null nếu chưa có file.
 * Ưu tiên tên file lưu trong cột books.cover_image; nếu không có thì tự tìm uploads/covers/<slug>.jpg|jpeg|png|webp.
 * $prefix = '../' khi gọi từ thư mục admin.
 */
function cover_url(array $b, string $prefix = ''): ?string
{
    $dir   = __DIR__ . '/uploads/covers/';
    $cands = [];
    $f = (string)($b['cover_image'] ?? '');
    if ($f !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $f)) { $cands[] = $f; }
    $slug = (string)($b['slug'] ?? '');
    if ($slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug)) {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $x) { $cands[] = $slug . '.' . $x; }
    }
    foreach ($cands as $c) {
        if (is_file($dir . $c)) {
            return $prefix . 'uploads/covers/' . rawurlencode($c) . '?v=' . filemtime($dir . $c);
        }
    }
    return null;
}

/** Khung bìa sách: có ảnh thì hiện ảnh, chưa có thì hiện ô tên sách như cũ */
function cover_html(array $b, string $class = 'book-cover', string $prefix = '', int $titleLen = 40): string
{
    $url = cover_url($b, $prefix);
    if ($url !== null) {
        return '<div class="' . e($class) . ' has-img"><img src="' . e($url) . '" alt="' . e($b['title'] ?? $b['book_title'] ?? '') . '" loading="lazy"></div>';
    }
    $t = (string)($b['title'] ?? $b['book_title'] ?? '');
    return '<div class="' . e($class) . '">' . e(mb_strimwidth($t, 0, $titleLen, '…')) . '</div>';
}

function book_card(array $b): string
{
    $final = final_price($b);
    $off   = discount_percent($b);

    $html  = '<a class="book-card" href="book.php?id=' . (int)$b['id'] . '">';
    $html .= cover_html($b);
    $html .= '<div class="book-title">' . e($b['title']) . '</div>';
    $html .= '<div class="stars">' . stars_html((float)$b['avg_rating'])
           . '<small>(' . (int)$b['review_count'] . ')</small></div>';
    $html .= '<div class="price-row"><span class="price">' . money($final) . '</span>';
    if ($off > 0) {
        $html .= '<span class="off">-' . $off . '%</span><span class="old">' . money($b['price']) . '</span>';
    }
    $html .= '</div><div class="sold">Đã bán ' . (int)$b['sold_count'] . '</div></a>';
    return $html;
}
