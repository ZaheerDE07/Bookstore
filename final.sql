SET NAMES utf8mb4;
SET time_zone = '+07:00';
CREATE DATABASE bookstore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE bookstore;
CREATE TABLE users (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email              VARCHAR(150) NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  full_name          VARCHAR(100) NOT NULL,
  phone              VARCHAR(20) NULL,
  role               ENUM('admin','user') NOT NULL DEFAULT 'user',
  status             ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
  email_verified_at  DATETIME NULL,
  failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until       DATETIME NULL,
  last_login_at      DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT chk_users_phone CHECK (phone IS NULL OR phone REGEXP '^[0-9+]{9,15}$')
) ENGINE=InnoDB;
CREATE TABLE addresses (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  receiver_name VARCHAR(100) NOT NULL,
  phone         VARCHAR(20) NOT NULL,
  province      VARCHAR(100) NOT NULL,
  district      VARCHAR(100) NOT NULL,
  ward          VARCHAR(100) NULL,
  address       VARCHAR(255) NOT NULL,
  is_default    TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_addresses_phone CHECK (phone REGEXP '^[0-9+]{9,15}$')
) ENGINE=InnoDB;
CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_token (token_hash),
  KEY idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(50) NOT NULL,
  entity     VARCHAR(50) NULL,
  entity_id  INT UNSIGNED NULL,
  detail     TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id),
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE publishers (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_publishers_name (name)
) ENGINE=InnoDB;
CREATE TABLE authors (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  bio  TEXT,
  KEY idx_authors_name (name)
) ENGINE=InnoDB;
CREATE TABLE categories (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(100) NOT NULL,
  slug      VARCHAR(120) NOT NULL,
  parent_id INT UNSIGNED NULL,
  UNIQUE KEY uq_categories_slug (slug),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
    REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE books (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL,
  isbn         VARCHAR(20) NULL,
  description  TEXT,
  price        DECIMAL(12,0) NOT NULL,
  sale_price   DECIMAL(12,0) NULL,
  stock        INT UNSIGNED NOT NULL DEFAULT 0,
  cover_image  VARCHAR(255),
  pages        SMALLINT UNSIGNED,
  publish_year YEAR,
  publisher_id INT UNSIGNED NULL,
  status       ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
  avg_rating   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  sold_count   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_books_slug (slug),
  UNIQUE KEY uq_books_isbn (isbn),
  KEY idx_books_status_created (status, created_at),
  KEY idx_books_status_price (status, price),
  FULLTEXT KEY ft_books_title (title),
  CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id)
    REFERENCES publishers(id) ON DELETE SET NULL,
  CONSTRAINT chk_books_price CHECK (price >= 0),
  CONSTRAINT chk_books_sale_price CHECK (sale_price IS NULL OR (sale_price >= 0 AND sale_price <= price))
) ENGINE=InnoDB;
CREATE TABLE book_authors (
  book_id   INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (book_id, author_id),
  CONSTRAINT fk_ba_book   FOREIGN KEY (book_id)   REFERENCES books(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ba_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE book_categories (
  book_id     INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (book_id, category_id),
  CONSTRAINT fk_bc_book     FOREIGN KEY (book_id)     REFERENCES books(id)      ON DELETE CASCADE,
  CONSTRAINT fk_bc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE carts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_carts_user (user_id),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE cart_items (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id  INT UNSIGNED NOT NULL,
  book_id  INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_book (cart_id, book_id),
  CONSTRAINT fk_ci_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT chk_ci_quantity CHECK (quantity BETWEEN 1 AND 100)
) ENGINE=InnoDB;
CREATE TABLE wishlists (
  user_id    INT UNSIGNED NOT NULL,
  book_id    INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, book_id),
  CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wl_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE reviews (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id              INT UNSIGNED NOT NULL,
  book_id              INT UNSIGNED NOT NULL,
  rating               TINYINT UNSIGNED NOT NULL,
  comment              TEXT,
  is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
  status               ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (user_id, book_id),
  KEY idx_reviews_book_status (book_id, status),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;
CREATE TABLE coupons (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(50) NOT NULL,
  discount_type  ENUM('percent','fixed') NOT NULL,
  discount_value DECIMAL(12,0) NOT NULL,
  min_order      DECIMAL(12,0) NOT NULL DEFAULT 0,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  usage_limit    INT UNSIGNED NULL,
  used_count     INT UNSIGNED NOT NULL DEFAULT 0,
  per_user_limit INT UNSIGNED NULL DEFAULT 1,
  limit_period   ENUM('lifetime','month') NOT NULL DEFAULT 'lifetime',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_coupons_code (code),
  CONSTRAINT chk_coupons_per_user CHECK (per_user_limit IS NULL OR per_user_limit > 0),
  CONSTRAINT chk_coupons_dates CHECK (end_date >= start_date),
  CONSTRAINT chk_coupons_value CHECK (
    (discount_type = 'percent' AND discount_value BETWEEN 1 AND 100)
    OR (discount_type = 'fixed' AND discount_value > 0)
  ),
  CONSTRAINT chk_coupons_min CHECK (min_order >= 0),
  CONSTRAINT chk_coupons_limit CHECK (usage_limit IS NULL OR used_count <= usage_limit)
) ENGINE=InnoDB;
CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_code       VARCHAR(20) NOT NULL,
  user_id          INT UNSIGNED NOT NULL,
  receiver_name    VARCHAR(100) NOT NULL,
  phone            VARCHAR(20) NOT NULL,
  shipping_address VARCHAR(255) NOT NULL,
  subtotal         DECIMAL(12,0) NOT NULL,
  discount_amount  DECIMAL(12,0) NOT NULL DEFAULT 0,
  shipping_fee     DECIMAL(12,0) NOT NULL DEFAULT 0,
  total_amount     DECIMAL(12,0) NOT NULL,
  status           ENUM('pending','confirmed','shipping','completed','cancelled')
                   NOT NULL DEFAULT 'pending',
  coupon_id        INT UNSIGNED NULL,
  note             VARCHAR(255),
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_code (order_code),
  KEY idx_orders_user_status (user_id, status),
  KEY idx_orders_status_created (status, created_at),
  KEY idx_orders_created (created_at),
  CONSTRAINT fk_orders_user   FOREIGN KEY (user_id)   REFERENCES users(id),
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
  CONSTRAINT chk_orders_money CHECK (
    subtotal >= 0 AND discount_amount >= 0 AND shipping_fee >= 0
    AND discount_amount <= subtotal
  ),
  CONSTRAINT chk_orders_total CHECK (total_amount = subtotal - discount_amount + shipping_fee)
) ENGINE=InnoDB;
CREATE TABLE order_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  book_id    INT UNSIGNED NOT NULL,
  book_title VARCHAR(255) NOT NULL,
  quantity   INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,0) NOT NULL,
  KEY idx_oi_order (order_id),
  KEY idx_oi_book (book_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_oi_book  FOREIGN KEY (book_id)  REFERENCES books(id)  ON DELETE RESTRICT,
  CONSTRAINT chk_oi_quantity CHECK (quantity > 0),
  CONSTRAINT chk_oi_price CHECK (unit_price >= 0)
) ENGINE=InnoDB;
CREATE TABLE payments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id         INT UNSIGNED NOT NULL,
  method           ENUM('cod','bank_transfer','momo','vnpay') NOT NULL,
  amount           DECIMAL(12,0) NOT NULL,
  status           ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_code VARCHAR(100) NULL,
  paid_at          DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_txn (transaction_code),
  KEY idx_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
    REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB;
CREATE TABLE order_status_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status   VARCHAR(20) NOT NULL,
  changed_by  INT UNSIGNED NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_osh_user  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE coupon_usages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  order_id   INT UNSIGNED NOT NULL,
  period_key CHAR(7) NOT NULL DEFAULT 'ALL',
  usage_no   INT UNSIGNED NOT NULL DEFAULT 1,
  used_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cu_usage (coupon_id, user_id, period_key, usage_no),
  UNIQUE KEY uq_cu_order (order_id),
  KEY idx_cu_user (user_id),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_cu_order  FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE stock_movements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id    INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  reason     ENUM('import','sale','cancel_restore','adjust') NOT NULL,
  order_id   INT UNSIGNED NULL,
  note       VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_book (book_id, created_at),
  CONSTRAINT fk_sm_book  FOREIGN KEY (book_id)    REFERENCES books(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_sm_order FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_user  FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL,
  CONSTRAINT chk_sm_qty CHECK (change_qty <> 0)
) ENGINE=InnoDB;