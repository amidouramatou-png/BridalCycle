-- BridalCycle database (MySQL 8 / MariaDB)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews, receipts, admin_logs, login_attempts, password_resets, messages, payments,
                     rentals, order_items, bc_orders, cart_items, product_images,
                     bc_products, designers, bc_categories, users;

SET FOREIGN_KEY_CHECKS = 1;

-- USERS
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    google_id       VARCHAR(64)  NULL,
    role            ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
    shop_name       VARCHAR(120) NULL,
    hide_name       TINYINT(1) NOT NULL DEFAULT 0,
    region          ENUM('Agadez','Diffa','Dosso','Maradi','Niamey','Tahoua','Tillabéri','Zinder') NOT NULL DEFAULT 'Niamey',
    city            VARCHAR(80)  NULL,
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    failed_logins   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_login_at   DATETIME NULL,
    last_login_ip   VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- SECURITY: only ONE admin can ever exist
    admin_lock      TINYINT AS (IF(role = 'admin', 1, NULL)) STORED,

    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_one_admin (admin_lock),
    UNIQUE KEY uq_users_google (google_id),
    CONSTRAINT chk_users_google_admin CHECK (role <> 'admin' OR google_id IS NULL),
    CONSTRAINT chk_users_email CHECK (email LIKE '%_@_%._%'),
    CONSTRAINT chk_users_phone CHECK (CHAR_LENGTH(phone) BETWEEN 8 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CATEGORIES
CREATE TABLE bc_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80)  NOT NULL,
    slug          VARCHAR(80)  NOT NULL,
    category_type ENUM('tradition','inspiration','style') NOT NULL,
    description   VARCHAR(255) NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_categories_slug (slug),
    CONSTRAINT chk_categories_slug CHECK (slug REGEXP '^[a-z0-9-]+$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2b. DESIGNERS
CREATE TABLE designers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    country     VARCHAR(60)  NULL,
    description VARCHAR(255) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_designers_slug (slug),
    CONSTRAINT chk_designers_slug CHECK (slug REGEXP '^[a-z0-9-]+$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PRODUCTS
CREATE TABLE bc_products (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id          INT UNSIGNED NOT NULL,
    category_id        INT UNSIGNED NOT NULL,
    designer_id        INT UNSIGNED NULL,
    title              VARCHAR(150) NOT NULL,
    description        TEXT NOT NULL,
    item_condition     ENUM('new','pre-owned') NOT NULL,
    size               VARCHAR(20)  NOT NULL,
    colour             VARCHAR(40)  NULL,
    is_for_sale        TINYINT(1)   NOT NULL DEFAULT 0,
    sale_price         INT UNSIGNED NULL,
    is_for_rent        TINYINT(1)   NOT NULL DEFAULT 0,
    rent_price_per_day INT UNSIGNED NULL,
    deposit            INT UNSIGNED NULL,
    stock              INT UNSIGNED NOT NULL DEFAULT 1,
    status             ENUM('pending','approved','rejected','sold','archived') NOT NULL DEFAULT 'pending',
    rejection_reason   VARCHAR(255) NULL,
    featured_until     DATETIME NULL,
    view_count         INT UNSIGNED NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_seller   FOREIGN KEY (seller_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES bc_categories(id),
    CONSTRAINT fk_products_designer FOREIGN KEY (designer_id) REFERENCES designers(id),
    -- The database itself refuses impossible listings
    CONSTRAINT chk_products_mode    CHECK (is_for_sale = 1 OR is_for_rent = 1),
    -- (IS NOT NULL is needed
    CONSTRAINT chk_products_sale    CHECK (is_for_sale = 0 OR (sale_price IS NOT NULL AND sale_price > 0)),
    CONSTRAINT chk_products_rent    CHECK (is_for_rent = 0 OR (rent_price_per_day IS NOT NULL AND rent_price_per_day > 0 AND deposit IS NOT NULL)),
    INDEX idx_products_status (status),
    INDEX idx_products_featured (featured_until),
    INDEX idx_products_category (category_id),
    FULLTEXT INDEX ft_products_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Several photos per outfit (premium gallery). Files are stored in public/uploads/
CREATE TABLE product_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    file_name  VARCHAR(80)  NOT NULL,             -- random name created by the server, never the user's name
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_images_file (file_name),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES bc_products(id) ON DELETE CASCADE,
    CONSTRAINT chk_images_name CHECK (file_name REGEXP '^[a-f0-9]{32}\\.(jpg|png|webp)$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CART AND ORDERS (buying)
CREATE TABLE cart_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL DEFAULT 1,
    added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES bc_products(id) ON DELETE CASCADE,
    CONSTRAINT chk_cart_qty    CHECK (quantity BETWEEN 1 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bc_orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ref        VARCHAR(30)  NOT NULL,
    buyer_id         INT UNSIGNED NOT NULL,
    total            INT UNSIGNED NOT NULL,
    delivery_method  ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
    delivery_address VARCHAR(255) NULL,
    delivery_fee     INT UNSIGNED NOT NULL DEFAULT 0,  -- REVENUE: BridalCycle's delivery fee (0 for pick-up); included in total
    phone            VARCHAR(20)  NOT NULL,
    status           ENUM('pending_payment','paid','ready','completed','cancelled') NOT NULL DEFAULT 'pending_payment',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_ref (order_ref),
    CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT chk_orders_total CHECK (total > 0),
    CONSTRAINT chk_orders_address CHECK (delivery_method = 'pickup' OR delivery_address IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    seller_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    unit_price INT UNSIGNED NOT NULL,
    quantity   INT UNSIGNED NOT NULL,
    commission    INT UNSIGNED NOT NULL DEFAULT 0,      -- BridalCycle's share (COMMISSION_PERCENT)
    seller_payout INT UNSIGNED NOT NULL DEFAULT 0,
    payout_status ENUM('not_due','due','paid') NOT NULL DEFAULT 'not_due',
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES bc_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES bc_products(id),
    CONSTRAINT fk_items_seller  FOREIGN KEY (seller_id)  REFERENCES users(id),
    CONSTRAINT chk_items_values CHECK (unit_price > 0 AND quantity > 0),
    CONSTRAINT chk_items_payout CHECK (commission + seller_payout = unit_price * quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RENTALS: brides who BORROW an outfit for their wedding
CREATE TABLE rentals (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_ref         VARCHAR(30)  NOT NULL,
    product_id         INT UNSIGNED NOT NULL,
    borrower_id        INT UNSIGNED NOT NULL,
    seller_id          INT UNSIGNED NOT NULL,
    start_date         DATE NOT NULL,
    end_date           DATE NOT NULL,
    event_date         DATE NULL,
    days               INT UNSIGNED NOT NULL,
    rent_price_per_day INT UNSIGNED NOT NULL,
    rent_total         INT UNSIGNED NOT NULL,
    deposit            INT UNSIGNED NOT NULL,
    total_due          INT UNSIGNED NOT NULL,
    commission         INT UNSIGNED NOT NULL DEFAULT 0,   -- BridalCycle's share (COMMISSION_PERCENT of the rent, never of the deposit)
    seller_payout      INT UNSIGNED NOT NULL DEFAULT 0,
    payout_status      ENUM('not_due','due','paid') NOT NULL DEFAULT 'not_due',
    phone              VARCHAR(20)  NOT NULL,
    size_needed        VARCHAR(20)  NULL,
    note               VARCHAR(500) NULL,
    status             ENUM('requested','approved','rejected','paid','picked_up','returned','cancelled') NOT NULL DEFAULT 'requested',
    deposit_status     ENUM('not_paid','held','refunded','partly_kept') NOT NULL DEFAULT 'not_paid',
    deposit_kept       INT UNSIGNED NOT NULL DEFAULT 0,
    seller_note        VARCHAR(500) NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rentals_ref (rental_ref),
    CONSTRAINT fk_rentals_product  FOREIGN KEY (product_id)  REFERENCES bc_products(id),
    CONSTRAINT fk_rentals_borrower FOREIGN KEY (borrower_id) REFERENCES users(id),
    CONSTRAINT fk_rentals_seller   FOREIGN KEY (seller_id)   REFERENCES users(id),
    -- Rules the database checks by itself
    CONSTRAINT chk_rentals_dates   CHECK (end_date >= start_date),
    CONSTRAINT chk_rentals_days    CHECK (days BETWEEN 1 AND 30),
    CONSTRAINT chk_rentals_event   CHECK (event_date IS NULL OR event_date BETWEEN start_date AND end_date),
    CONSTRAINT chk_rentals_total   CHECK (rent_total = rent_price_per_day * days AND total_due = rent_total + deposit),
    CONSTRAINT chk_rentals_self    CHECK (borrower_id <> seller_id),
    CONSTRAINT chk_rentals_payout  CHECK (commission + seller_payout = rent_total),
    CONSTRAINT chk_rentals_kept    CHECK (deposit_kept <= deposit),
    INDEX idx_rentals_dates (product_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PAYMENTS: every attempt, for an order or a rental
CREATE TABLE payments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_ref   VARCHAR(40)  NOT NULL,
    payable_type      ENUM('order','rental','feature') NOT NULL,
    payable_id        INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    provider          VARCHAR(30)  NOT NULL,
    -- Payment methods in Niger. Wave is ready here but shown as "coming soon" on the site
    method            ENUM('airtel_money','zamani_cash','moov_money','mynita','amanata','wave') NOT NULL,
    amount            INT UNSIGNED NOT NULL,
    msisdn            VARCHAR(20)  NOT NULL,
    gateway_reference VARCHAR(80)  NULL,
    purchase_code     VARCHAR(40)  NULL,
    status            ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    failure_reason    VARCHAR(255) NULL,
    raw_response      TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at           DATETIME NULL,
    UNIQUE KEY uq_payments_ref (transaction_ref),
    UNIQUE KEY uq_payments_gateway (gateway_reference),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT chk_payments_amount CHECK (amount > 0),
    INDEX idx_payments_payable (payable_type, payable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6b. RECEIPTS
CREATE TABLE receipts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_ref   VARCHAR(30)  NOT NULL,
    kind          ENUM('payment','payout','refund') NOT NULL,
    payment_id    INT UNSIGNED NULL,
    payable_type  ENUM('order','rental','feature') NOT NULL,
    payable_id    INT UNSIGNED NOT NULL,
    recipient     ENUM('buyer','seller','business') NOT NULL,
    user_id       INT UNSIGNED NULL,
    email         VARCHAR(190) NOT NULL,
    subject       VARCHAR(200) NOT NULL,
    body_html     MEDIUMTEXT NOT NULL,
    amount        INT UNSIGNED NOT NULL,
    email_status  ENUM('sent','saved','failed') NOT NULL DEFAULT 'saved',
    email_error   VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_receipts_ref (receipt_ref),
    CONSTRAINT fk_receipts_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
    INDEX idx_receipts_payable (payable_type, payable_id),
    INDEX idx_receipts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6c. REVIEWS
CREATE TABLE reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT UNSIGNED NOT NULL,
    seller_id   INT UNSIGNED NOT NULL,
    order_id    INT UNSIGNED NULL,
    rental_id   INT UNSIGNED NULL,
    rating      TINYINT UNSIGNED NOT NULL,
    comment     VARCHAR(500) NULL,
    is_hidden   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reviews_order (reviewer_id, seller_id, order_id),
    UNIQUE KEY uq_reviews_rental (reviewer_id, rental_id),
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_seller   FOREIGN KEY (seller_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_order    FOREIGN KEY (order_id)    REFERENCES bc_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_rental   FOREIGN KEY (rental_id)   REFERENCES rentals(id) ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating  CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT chk_reviews_one     CHECK ((order_id IS NULL) <> (rental_id IS NULL)),
    CONSTRAINT chk_reviews_self    CHECK (reviewer_id <> seller_id),
    INDEX idx_reviews_seller (seller_id, is_hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MESSAGES between brides and sellers
CREATE TABLE messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NULL,
    body        TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_product  FOREIGN KEY (product_id)  REFERENCES bc_products(id) ON DELETE SET NULL,
    CONSTRAINT chk_msg_body    CHECK (CHAR_LENGTH(body) BETWEEN 1 AND 2000),
    CONSTRAINT chk_msg_self    CHECK (sender_id <> receiver_id),
    INDEX idx_msg_receiver (receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SECURITY TABLES

-- Every login attempt (by email AND by IP address), to block password-guessing attacks
CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(190) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_email (email, attempted_at),
    INDEX idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Forgot password" links. We store only a HASH of the secret token, and it expires
CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_resets_token (token_hash),
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everything the admin does is recorded (who, what, when, from which IP)
CREATE TABLE admin_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(60)  NOT NULL,
    target_type VARCHAR(30)  NULL,
    target_id   INT UNSIGNED NULL,
    details     VARCHAR(500) NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_admin FOREIGN KEY (admin_id) REFERENCES users(id),
    INDEX idx_logs_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STARTING DATA
INSERT INTO bc_categories (name, slug, category_type, description, sort_order) VALUES
-- Traditions of Niger
('Hausa',                     'hausa',        'tradition',   'Hausa bridal dresses, wrappers and head ties',                      1),
('Zarma (Djerma)',            'zarma',        'tradition',   'Zarma bridal ensembles and woven wrappers',                          2),
('Songhai',                   'songhai',      'tradition',   'Songhai wedding attire from the Niger river valley',                 3),
('Tuareg (Kel Tamasheq)',     'tuareg',       'tradition',   'Tuareg indigo garments and wedding finery',                          4),
('Fulani / Peul & Wodaabe',   'fulani',       'tradition',   'Fulani, Peul and Wodaabe bridal dresses and beadwork',               5),
('Kanuri (Beriberi)',         'kanuri',       'tradition',   'Kanuri bridal outfits from the Diffa and Zinder regions',            6),
('Toubou (Teda)',             'toubou',       'tradition',   'Toubou wedding garments from the eastern regions',                   7),
('Arab (Diffa Arabs)',        'arab',         'tradition',   'Bridal attire of the Arab communities of Niger',                     8),
('Gourmantché',               'gourmantche',  'tradition',   'Gourmantché wedding attire from the Tillabéri region',               9),
('Boudouma (Buduma)',         'boudouma',     'tradition',   'Boudouma bridal outfits from the Lake Chad area',                   10),
('Tasawaq (Ingall)',          'tasawaq',      'tradition',   'Wedding attire of the Tasawaq-speaking community of Ingall',        11),
-- Inspiration from Nigeria
('Nigeria Outfit Inspiration','nigeria-inspiration','inspiration','Nigerian-inspired bridal looks: aso-oke, gele, george and more', 20),
-- Styles worn across all traditions
('Western Wedding Dresses',   'western',      'style',       'White and ivory gowns for the church or civil ceremony',            30),
('Bazin Riche',               'bazin',        'style',       'Bazin riche gowns for wedding ceremonies',                          31),
('Grand Boubou',              'grand-boubou', 'style',       'Embroidered grand boubou outfits',                                  32),
('Ankara & Batik',            'ankara',       'style',       'Ankara wax print and batik bridal outfits',                         33);

-- Admin account (create it once)
-- 1. Make a hash: php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"
-- 2. INSERT INTO users (full_name, email, phone, password_hash, role)
--    VALUES ('Your Name', 'your-email@example.com', '90000000', 'PASTE-THE-HASH-HERE', 'admin');
