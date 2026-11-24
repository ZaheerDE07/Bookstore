<?php
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

/* ---------- Flash message ---------- */
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ---------- Auth ---------- */
function current_user(): ?array
{
    // mỗi request kiểm tra lại 1 lần: tài khoản bị khóa/xóa thì mất phiên ngay, role/tên luôn mới nhất
    static $checked = false;
    if (!$checked) {
        $checked = true;
        if (!empty($_SESSION['user'])) {
            $st = db()->prepare('SELECT status, role, full_name FROM users WHERE id = ?');
            $st->execute([(int)$_SESSION['user']['id']]);
            $row = $st->fetch();
            if (!$row || $row['status'] !== 'active') {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user']['role']      = $row['role'];
                $_SESSION['user']['full_name'] = $row['full_name'];
            }
        }
    }
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function require_guest(): void
{
    if (current_user()) {
        redirect('index.php');
    }
}

/* ---------- Audit log ---------- */
function audit_log(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, ?string $detail = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, detail, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$userId, $action, $entity, $entityId, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        // log lỗi không được làm hỏng luồng chính
    }
}
