SET NAMES utf8mb4;
SET time_zone = '+07:00';
USE bookstore;
INSERT INTO users (email, password_hash, full_name, phone, role, email_verified_at) VALUES
('admin@nhasach.vn', '$2y$10$CkXbww4f7fJYCGKtmZuVbOJs.pd9tKLMyEcqwNsRHsWGXg3LZHuui', 'Quản trị viên',   '0900000001', 'admin', NOW()),
('an@gmail.com',     '$2y$10$kcQWnYDZCd1EtJK3wKwevu1Ic/cU4LvjQ/Y24XNjoEch4MhGz/ijC', 'Nguyễn Văn An',   '0900000002', 'user',  NOW()),
('binh@gmail.com',   '$2y$10$kcQWnYDZCd1EtJK3wKwevu1Ic/cU4LvjQ/Y24XNjoEch4MhGz/ijC', 'Trần Thị Bình',   '0900000003', 'user',  NOW()),
('chi@gmail.com',    '$2y$10$kcQWnYDZCd1EtJK3wKwevu1Ic/cU4LvjQ/Y24XNjoEch4MhGz/ijC', 'Lê Thị Chi',      '0900000004', 'user',  NOW()),
('dung@gmail.com',   '$2y$10$kcQWnYDZCd1EtJK3wKwevu1Ic/cU4LvjQ/Y24XNjoEch4MhGz/ijC', 'Phạm Quốc Dũng',  '0900000005', 'user',  NOW());
INSERT INTO addresses (user_id, receiver_name, phone, province, district, ward, address, is_default) VALUES
(2, 'Nguyễn Văn An',   '0900000002', 'TP. Hồ Chí Minh', 'Quận 1',   NULL, '12 Lê Lợi',      1),
(2, 'Nguyễn Văn An',   '0900000002', 'TP. Hồ Chí Minh', 'Quận 3',   NULL, '27 Võ Văn Tần',  0),
(3, 'Trần Thị Bình',   '0900000003', 'Đà Nẵng',         'Hải Châu', NULL, '45 Trần Phú',    1),
(4, 'Lê Thị Chi',      '0900000004', 'Hà Nội',          'Cầu Giấy', NULL, '88 Xuân Thủy',   1),
(5, 'Phạm Quốc Dũng',  '0900000005', 'Cần Thơ',         'Ninh Kiều',NULL, '15 Hòa Bình',    1);
INSERT INTO carts (user_id) VALUES (1), (2), (3), (4), (5);
INSERT INTO password_resets (user_id, token_hash, expires_at, used_at) VALUES
(2, '5dd0551e1da7409b3527b25214a78000a67868a4dba96bbdec461fe5a126f6b3', DATE_SUB(NOW(), INTERVAL 23 HOUR), DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(3, '0c0556ed7875df97061e8ce08899eb0f4caa6eac5096e3d109406698fec762bb', DATE_SUB(NOW(), INTERVAL 2 DAY),   NULL),
(4, 'df1d4de40a55f201e7ff16868cb870dc04c9c17283c9255fb4113c91e017bb5f', DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL),
(5, '9a7c2c7de6bbd6e4f7c88370e2b3b805fa59f4c70c0fcffe855bd4fb69de52a1', DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL),
(3, '96f859f080d4c02a15506547d6cdc8e56ec6c099e0c311c88f497533ac18b3cd', DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL);
INSERT INTO audit_logs (user_id, action, entity, entity_id, detail, ip_address) VALUES
(NULL, 'login_failed',        'users',  3, 'Sai mật khẩu lần 1',              '203.0.113.25'),
(2,    'login_success',       'users',  2, NULL,                              '203.0.113.10'),
(1,    'update_order_status', 'orders', 1, 'confirmed -> shipping',           '203.0.113.1'),
(1,    'update_book_price',   'books',  3, 'price 55000 -> 60000',            '203.0.113.1'),
(3,    'password_reset',      'users',  3, 'Đặt lại mật khẩu thành công',     '203.0.113.25');
INSERT INTO publishers (name) VALUES
('NXB Kim Đồng'), ('NXB Trẻ'), ('NXB Văn học'), ('NXB Lao Động'), ('NXB Giáo dục Việt Nam');
INSERT INTO authors (name, bio) VALUES
('Nguyễn Nhật Ánh', 'Nhà văn Việt Nam, nổi tiếng với các tác phẩm dành cho tuổi học trò.'),
('Nam Cao',         'Nhà văn hiện thực xuất sắc của văn học Việt Nam.'),
('Tô Hoài',         'Tác giả của Dế Mèn phiêu lưu ký.'),
('Dale Carnegie',   'Tác giả sách kỹ năng sống nổi tiếng thế giới.'),
('Nguyễn Ngọc Tư',  'Nhà văn Việt Nam, nổi tiếng với truyện ngắn và tản văn về miền Tây Nam Bộ.');
INSERT INTO categories (id, name, slug, parent_id) VALUES
(1, 'Văn học',      'van-hoc',      NULL),
(2, 'Tiểu thuyết',  'tieu-thuyet',  1),
(3, 'Truyện ngắn',  'truyen-ngan',  1),
(4, 'Kỹ năng sống', 'ky-nang-song', NULL),
(5, 'Thiếu nhi',    'thieu-nhi',    NULL);
INSERT INTO books (id, title, slug, isbn, description, price, stock, pages, publish_year, publisher_id, avg_rating, review_count, sold_count) VALUES
(1, 'Mắt biếc', 'mat-biec', '9786041000011', 'Câu chuyện tình đẹp và buồn của Ngạn dành cho Hà Lan.', 110000, 48, 300, 2019, 2, 4.50, 2, 2),
(2, 'Cho tôi xin một vé đi tuổi thơ', 'cho-toi-xin-mot-ve-di-tuoi-tho', '9786041000028', 'Những ký ức tuổi thơ trong trẻo.', 95000, 37, 250, 2018, 2, 0.00, 0, 3),
(3, 'Chí Phèo', 'chi-pheo', '9786041000035', 'Truyện ngắn kinh điển của Nam Cao.', 60000, 28, 120, 2017, 3, 5.00, 1, 2),
(4, 'Dế Mèn phiêu lưu ký', 'de-men-phieu-luu-ky', '9786041000042', 'Cuộc phiêu lưu của chú Dế Mèn.', 75000, 55, 180, 2020, 1, 4.00, 1, 5),
(5, 'Đắc nhân tâm', 'dac-nhan-tam', '9786041000059', 'Nghệ thuật đối nhân xử thế.', 88000, 78, 320, 2021, 4, 4.00, 1, 2);
INSERT INTO book_authors (book_id, author_id) VALUES (1,1),(2,1),(3,2),(4,3),(5,4);
INSERT INTO book_categories (book_id, category_id) VALUES
(1,2),(2,2),(3,3),(3,1),(4,5),(5,4);
INSERT INTO cart_items (cart_id, book_id, quantity) VALUES
(2, 4, 1),
(2, 3, 2),
(3, 2, 1),
(4, 5, 1),
(5, 1, 1);
INSERT INTO wishlists (user_id, book_id) VALUES (2,4), (3,1), (2,5), (4,3), (5,2);
INSERT INTO coupons (id, code, discount_type, discount_value, min_order, start_date, end_date, usage_limit, used_count, per_user_limit, limit_period) VALUES
(1, 'GIAM10',     'percent', 10,    100000, '2026-01-01', '2026-12-31', 100,  3, 1,    'lifetime'),
(2, 'FREESHIP',   'fixed',   30000, 200000, '2026-01-01', '2026-12-31', 50,   2, 1,    'month'),
(3, 'WELCOME20',  'percent', 20,    150000, '2026-01-01', '2026-12-31', 200,  0, 1,    'lifetime'),
(4, 'SALE50K',    'fixed',   50000, 300000, '2026-10-01', '2026-10-31', 30,   0, 1,    'lifetime'),
(5, 'THANHVIEN5', 'percent', 5,     0,      '2026-01-01', '2026-12-31', NULL, 0, NULL, 'lifetime');
INSERT INTO orders (id, order_code, user_id, receiver_name, phone, shipping_address,
                    subtotal, discount_amount, shipping_fee, total_amount, status, coupon_id) VALUES
(1, 'DH0001', 2, 'Nguyễn Văn An',  '0900000002', '12 Lê Lợi, Quận 1, TP. Hồ Chí Minh', 286000, 28600, 0,     257400, 'completed', 1),
(2, 'DH0002', 3, 'Trần Thị Bình',  '0900000003', '45 Trần Phú, Hải Châu, Đà Nẵng',      195000, 19500, 30000, 205500, 'completed', 1),
(3, 'DH0003', 4, 'Lê Thị Chi',     '0900000004', '88 Xuân Thủy, Cầu Giấy, Hà Nội',      300000, 30000, 30000, 300000, 'shipping',  2),
(4, 'DH0004', 5, 'Phạm Quốc Dũng', '0900000005', '15 Hòa Bình, Ninh Kiều, Cần Thơ',     150000, 15000, 30000, 165000, 'pending',   1),
(5, 'DH0005', 2, 'Nguyễn Văn An',  '0900000002', '27 Võ Văn Tần, Quận 3, TP. Hồ Chí Minh', 245000, 30000, 30000, 245000, 'confirmed', 2);
INSERT INTO order_items (order_id, book_id, book_title, quantity, unit_price) VALUES
(1, 1, 'Mắt biếc',                       1, 110000),
(1, 5, 'Đắc nhân tâm',                   2, 88000),
(2, 3, 'Chí Phèo',                       2, 60000),
(2, 4, 'Dế Mèn phiêu lưu ký',            1, 75000),
(3, 2, 'Cho tôi xin một vé đi tuổi thơ', 2, 95000),
(3, 1, 'Mắt biếc',                       1, 110000),
(4, 4, 'Dế Mèn phiêu lưu ký',            2, 75000),
(5, 2, 'Cho tôi xin một vé đi tuổi thơ', 1, 95000),
(5, 4, 'Dế Mèn phiêu lưu ký',            2, 75000);
INSERT INTO payments (order_id, method, amount, status, transaction_code, paid_at) VALUES
(1, 'cod',           257400, 'paid',    NULL,              NOW()),
(2, 'bank_transfer', 205500, 'paid',    'VCB20261001001',  NOW()),
(3, 'momo',          300000, 'paid',    'MOMO20261003001', NOW()),
(4, 'vnpay',         165000, 'pending', NULL,              NULL),
(5, 'cod',           245000, 'pending', NULL,              NULL);
INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note) VALUES
(1, NULL,        'pending',   2, 'Khách đặt hàng'),
(1, 'pending',   'confirmed', 1, NULL),
(1, 'confirmed', 'shipping',  1, NULL),
(1, 'shipping',  'completed', 1, NULL),
(2, NULL,        'pending',   3, 'Khách đặt hàng'),
(2, 'pending',   'confirmed', 1, NULL),
(2, 'confirmed', 'shipping',  1, NULL),
(2, 'shipping',  'completed', 1, NULL),
(3, NULL,        'pending',   4, 'Khách đặt hàng'),
(3, 'pending',   'confirmed', 1, NULL),
(3, 'confirmed', 'shipping',  1, NULL),
(4, NULL,        'pending',   5, 'Khách đặt hàng'),
(5, NULL,        'pending',   2, 'Khách đặt hàng'),
(5, 'pending',   'confirmed', 1, NULL);
INSERT INTO coupon_usages (coupon_id, user_id, order_id, period_key, usage_no) VALUES
(1, 2, 1, 'ALL',     1),
(1, 3, 2, 'ALL',     1),
(2, 4, 3, '2026-10', 1),
(1, 5, 4, 'ALL',     1),
(2, 2, 5, '2026-10', 1);
INSERT INTO stock_movements (book_id, change_qty, reason, order_id, note, created_by) VALUES
(1, 50, 'import', NULL, 'Nhập kho ban đầu', 1),
(2, 40, 'import', NULL, 'Nhập kho ban đầu', 1),
(3, 30, 'import', NULL, 'Nhập kho ban đầu', 1),
(4, 60, 'import', NULL, 'Nhập kho ban đầu', 1),
(5, 80, 'import', NULL, 'Nhập kho ban đầu', 1),
(1, -1, 'sale', 1, 'Đơn DH0001', 2),
(5, -2, 'sale', 1, 'Đơn DH0001', 2),
(3, -2, 'sale', 2, 'Đơn DH0002', 3),
(4, -1, 'sale', 2, 'Đơn DH0002', 3),
(2, -2, 'sale', 3, 'Đơn DH0003', 4),
(1, -1, 'sale', 3, 'Đơn DH0003', 4),
(4, -2, 'sale', 4, 'Đơn DH0004', 5),
(2, -1, 'sale', 5, 'Đơn DH0005', 2),
(4, -2, 'sale', 5, 'Đơn DH0005', 2);
INSERT INTO reviews (user_id, book_id, rating, comment, is_verified_purchase) VALUES
(2, 1, 5, 'Sách hay, đọc xong còn ám ảnh mãi.',            1),
(2, 5, 4, 'Nội dung bổ ích, dễ áp dụng.',                  1),
(3, 3, 5, 'Truyện ngắn kinh điển, đọc lại vẫn thấy hay.',  1),
(3, 4, 4, 'Cuốn sách tuổi thơ, giấy in đẹp.',              1),
(4, 1, 4, 'Cốt truyện cảm động, giao hàng nhanh.',         0);