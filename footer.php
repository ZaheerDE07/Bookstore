</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div>
            <div class="logo">MPB<span>.com</span></div>
            <p>Hệ thống nhà sách trực tuyến - Thắp sáng tri thức, kết nối mọi nhà.</p>
            <p>Bài tập website nhà sách PHP / MySQL chạy trên XAMPP.</p>
        </div>
        <div>
            <h4>Dịch vụ</h4>
            <a href="#">Điều khoản sử dụng</a>
            <a href="#">Chính sách bảo mật</a>
            <a href="#">Giới thiệu</a>
        </div>
        <div>
            <h4>Hỗ trợ</h4>
            <a href="#">Chính sách đổi - trả</a>
            <a href="#">Phương thức vận chuyển</a>
            <a href="#">Phương thức thanh toán</a>
        </div>
        <div>
            <h4>Tài khoản</h4>
            <?php if (current_user()): ?>
                <a href="logout.php">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php">Đăng nhập</a>
                <a href="register.php">Đăng ký</a>
            <?php endif; ?>
            <a href="orders.php">Lịch sử mua hàng</a>
        </div>
    </div>
    <div class="footer-copy">Copyright © <?= date('Y') ?> MPB.com. All rights reserved.</div>
</footer>

<script>
document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        if (input) { input.type = input.type === 'password' ? 'text' : 'password'; }
    });
});
</script>
</body>
</html>
