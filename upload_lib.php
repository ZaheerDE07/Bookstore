<?php
// Upload ảnh bìa sách: kiểm tra file, lưu vào uploads/covers/<slug>.<đuôi>

const COVER_MAX_BYTES = 2 * 1024 * 1024;   // 2 MB
const COVER_DIR       = __DIR__ . '/uploads/covers/';

/**
 * Kiểm tra file ảnh bìa người dùng chọn trong $_FILES[$field].
 * Trả về [lỗi|null, đuôi file|null]. Không chọn file -> [null, null].
 */
function validate_cover_upload(string $field = 'cover'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['Ảnh bìa quá lớn (tối đa 2 MB).', null];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['Tải ảnh bìa không thành công, vui lòng thử lại.', null];
    }
    if ($f['size'] > COVER_MAX_BYTES) {
        return ['Ảnh bìa quá lớn (tối đa 2 MB).', null];
    }
    // kiểm tra theo nội dung thật của file, không tin đuôi tên file
    $info = @getimagesize($f['tmp_name']);
    $map  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($map[$info[2]])) {
        return ['Ảnh bìa phải là file JPG, PNG hoặc WEBP hợp lệ.', null];
    }
    if ($info[0] < 100 || $info[1] < 100 || $info[0] > 6000 || $info[1] > 6000) {
        return ['Kích thước ảnh bìa phải từ 100 đến 6000 px.', null];
    }
    return [null, $map[$info[2]]];
}

/** Xóa mọi file bìa của một slug (mọi đuôi) */
function delete_cover_files(string $slug): void
{
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) { return; }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $x) {
        $p = COVER_DIR . $slug . '.' . $x;
        if (is_file($p)) { @unlink($p); }
    }
}

/** Lưu file đã được validate. Trả về tên file (vd. mat-biec.jpg) hoặc null nếu thất bại. */
function save_cover_upload(string $slug, string $ext, string $field = 'cover'): ?string
{
    if (!preg_match('/^[a-z0-9-]+$/', $slug) || !isset($_FILES[$field]['tmp_name'])) { return null; }
    if (!is_dir(COVER_DIR) && !@mkdir(COVER_DIR, 0775, true)) { return null; }
    delete_cover_files($slug);   // đổi đuôi (png -> jpg) thì không để file cũ nằm lại
    $name = $slug . '.' . $ext;
    return @move_uploaded_file($_FILES[$field]['tmp_name'], COVER_DIR . $name) ? $name : null;
}
