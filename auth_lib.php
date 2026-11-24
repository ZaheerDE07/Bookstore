<?php
// Đăng nhập dùng chung cho login.php (khách) và admin/login.php (quản trị)

const MAX_FAILED   = 5;   // sai quá 5 lần thì khóa tạm
const LOCK_MINUTES = 15;

/**
 * Thử đăng nhập. Trả về null nếu thành công (đã tạo session), ngược lại trả về thông báo lỗi.
 * $adminOnly = true: chỉ tài khoản role 'admin' mới được vào (người khác nhận thông báo chung chung).
 */
function try_login(string $email, string $password, bool $adminOnly = false): ?string
{
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked
         FROM users WHERE email = ? LIMIT 1'
    );
    $st->execute([$email]);
    $u = $st->fetch();

    $generic = 'Email hoặc mật khẩu không đúng.';

    if (!$u || $u['status'] === 'deleted') {
        audit_log(null, 'login_failed', 'users', null, 'Email không tồn tại: ' . $email);
        return $generic;
    }
    if ($u['status'] === 'locked') {
        return 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
    }
    if ((int)$u['is_locked'] === 1) {
        return 'Đăng nhập sai quá nhiều lần. Tài khoản tạm khóa đến '
            . date('H:i d/m/Y', strtotime($u['locked_until'])) . '.';
    }

    if (!password_verify($password, $u['password_hash'])) {
        $count = (int)$u['failed_login_count'] + 1;
        if ($count >= MAX_FAILED) {
            $pdo->prepare(
                'UPDATE users SET failed_login_count = 0,
                        locked_until = DATE_ADD(NOW(), INTERVAL ' . LOCK_MINUTES . ' MINUTE)
                 WHERE id = ?'
            )->execute([$u['id']]);
            audit_log((int)$u['id'], 'account_locked', 'users', (int)$u['id'], 'Khóa tạm do sai mật khẩu nhiều lần');
            return 'Bạn đã nhập sai ' . MAX_FAILED . ' lần. Tài khoản bị khóa ' . LOCK_MINUTES . ' phút.';
        }
        $pdo->prepare('UPDATE users SET failed_login_count = ? WHERE id = ?')->execute([$count, $u['id']]);
        audit_log((int)$u['id'], 'login_failed', 'users', (int)$u['id'], 'Sai mật khẩu lần ' . $count);
        return $generic;
    }

    // đúng mật khẩu nhưng không phải admin mà vào cổng admin
    if ($adminOnly && $u['role'] !== 'admin') {
        audit_log((int)$u['id'], 'admin_login_denied', 'users', (int)$u['id'], 'Tài khoản thường thử vào trang quản trị');
        return $generic;
    }

    $pdo->prepare(
        'UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?'
    )->execute([$u['id']]);

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$u['id'],
        'email'     => $u['email'],
        'full_name' => $u['full_name'],
        'role'      => $u['role'],
    ];
    audit_log((int)$u['id'], 'login_success', 'users', (int)$u['id'], $adminOnly ? 'Đăng nhập trang quản trị' : null);
    return null;
}

/* =====================  QUÊN / ĐẶT LẠI MẬT KHẨU  ===================== */

// XAMPP mặc định không gửi được email. Khi bật, link đặt lại được hiện ngay trên trang,
// nhưng CHỈ cho người truy cập từ chính máy chủ (localhost). Đặt false khi đưa lên host thật.
const RESET_DEV_LINK     = true;
const RESET_MAX_REQUESTS = 3;    // tối đa 3 yêu cầu / tài khoản / 15 phút

function is_local_request(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

/** Địa chỉ thư mục hiện tại, vd. http://localhost/bookstore hoặc http://localhost/bookstore/admin */
function site_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

/**
 * Tạo token đặt lại mật khẩu cho tài khoản (đúng vai trò) đang hoạt động.
 * Trả về token gốc (chỉ dùng 1 lần để gửi cho chủ tài khoản) hoặc null nếu không tạo được
 * (không có tài khoản, hoặc yêu cầu quá nhiều). Trang gọi luôn hiển thị cùng một thông báo.
 * Trong DB chỉ lưu SHA-256 của token.
 */
function issue_reset_token(string $email, string $role, int $ttlMinutes = 30): ?string
{
    $pdo = db();
    $st = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = ? AND status = 'active' LIMIT 1");
    $st->execute([$email, $role]);
    $uid = $st->fetchColumn();
    if (!$uid) {
        audit_log(null, 'password_reset_unknown', 'users', null, 'Yêu cầu cho email không hợp lệ: ' . $email);
        return null;
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $st->execute([$uid]);
    if ((int)$st->fetchColumn() >= RESET_MAX_REQUESTS) {
        audit_log((int)$uid, 'password_reset_throttled', 'users', (int)$uid, 'Yêu cầu quá nhiều lần');
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . (int)$ttlMinutes . ' MINUTE))'
    )->execute([$uid, hash('sha256', $token)]);
    audit_log((int)$uid, 'password_reset_request', 'users', (int)$uid);
    return $token;
}

/** Thử gửi email chứa link (thất bại thì bỏ qua, không báo gì cho người dùng) */
function send_reset_mail(string $email, string $link): void
{
    $subject = '=?UTF-8?B?' . base64_encode('Đặt lại mật khẩu MPB.com') . '?=';
    $body    = "Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu.\r\n\r\nBấm vào liên kết sau để đặt mật khẩu mới:\r\n"
             . $link . "\r\n\r\nLiên kết có hiệu lực trong thời gian ngắn và chỉ dùng được 1 lần. "
             . "Nếu không phải bạn yêu cầu, hãy bỏ qua email này.";
    @mail($email, $subject, $body, "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8");
}

/** Kiểm tra token còn hiệu lực (chưa dùng, chưa hết hạn, đúng vai trò). Trả về dòng {id, user_id, email} hoặc null. */
function find_reset_token(string $token, string $role): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $st = db()->prepare(
        "SELECT pr.id, pr.user_id, u.email
         FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
           AND u.status = 'active' AND u.role = ?"
    );
    $st->execute([hash('sha256', $token), $role]);
    return $st->fetch() ?: null;
}

/** Đặt mật khẩu mới bằng token. Trả về null nếu thành công, ngược lại là thông báo lỗi. */
function complete_password_reset(string $token, string $role, string $new, string $new2): ?string
{
    if (strlen($new) < 8) {
        return 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    }
    if ($new !== $new2) {
        return 'Mật khẩu nhập lại không khớp.';
    }
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // khóa dòng token để 2 lần bấm cùng lúc không dùng được 2 lần
        $st = $pdo->prepare(
            "SELECT pr.id, pr.user_id
             FROM password_resets pr JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
               AND u.status = 'active' AND u.role = ? FOR UPDATE"
        );
        $st->execute([hash('sha256', $token), $role]);
        $row = $st->fetch();
        if (!$row) {
            $pdo->rollBack();
            return 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
        }
        $uid = (int)$row['user_id'];

        // đổi mật khẩu + gỡ khóa tạm do đăng nhập sai
        $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        // vô hiệu mọi token còn lại của tài khoản này
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$uid]);

        $pdo->commit();
        audit_log($uid, 'password_reset', 'users', $uid, 'Đặt lại mật khẩu thành công');
        return null;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('password_reset: ' . $ex->getMessage());
        return 'Có lỗi hệ thống, vui lòng thử lại sau.';
    }
}
