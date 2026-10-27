-- ShopSphere India - MySQL 8+ schema (InnoDB, utf8mb4)
-- Import:  mysql -u root -p < database/schema.sql     (creates database `shopsphere`)
-- Then:    php database/seed.php                       (demo catalogue, users, coupons)

CREATE DATABASE IF NOT EXISTS shopsphere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shopsphere;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(15) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'customer',       -- customer | staff | admin
  status VARCHAR(20) NOT NULL DEFAULT 'active',       -- active | blocked
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(30) NOT NULL DEFAULT 'Home',
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  line1 VARCHAR(190) NOT NULL,
  line2 VARCHAR(190) NULL,
  landmark VARCHAR(120) NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  pincode CHAR(6) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(60) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NOT NULL UNIQUE,
  slug VARCHAR(200) NOT NULL UNIQUE,
  name VARCHAR(220) NOT NULL,
  brand VARCHAR(80) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  subcategory_id INT UNSIGNED NULL,
  short_desc VARCHAR(255) NULL,
  description TEXT NULL,
  mrp DECIMAL(10,2) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  discount_pct SMALLINT NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
  rating_count INT NOT NULL DEFAULT 0,
  sold_count INT NOT NULL DEFAULT 0,
  specs TEXT NULL,            -- JSON object {"RAM":"8 GB",...}
  options TEXT NULL,          -- JSON object {"Color":["Black","Blue"],"Size":["M","L"]}
  shipping_info VARCHAR(255) NULL,
  return_policy VARCHAR(255) NULL,
  warranty VARCHAR(190) NULL,
  gst_rate DECIMAL(4,2) NOT NULL DEFAULT 18,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_deal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  CHECK (price > 0),
  CHECK (mrp >= price),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_products_cat ON products (category_id, is_active);
CREATE INDEX idx_products_sub ON products (subcategory_id);
CREATE INDEX idx_products_brand ON products (brand);
CREATE INDEX idx_products_price ON products (price);
CREATE INDEX idx_products_created ON products (created_at);

CREATE TABLE product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  alt VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_pimg_product ON product_images (product_id, sort_order);

CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT NOT NULL,
  title VARCHAR(150) NULL,
  body TEXT NULL,
  verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE (product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer VARCHAR(1000) NULL,
  answered_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE carts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL UNIQUE,
  token CHAR(64) NULL UNIQUE,            -- guest carts are keyed by a random token kept in the session
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cart_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  options TEXT NULL,
  options_hash CHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE (cart_id, product_id, options_hash),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recently_viewed (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner VARCHAR(80) NOT NULL,            -- "u:<userId>" or "s:<guestToken>"
  product_id INT UNSIGNED NOT NULL,
  viewed_at DATETIME NOT NULL,
  UNIQUE (owner, product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(190) NULL,
  type VARCHAR(10) NOT NULL DEFAULT 'percent',     -- percent | flat
  value DECIMAL(10,2) NOT NULL,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  usage_limit INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(15) NOT NULL,
  ship_name VARCHAR(120) NOT NULL,
  ship_phone VARCHAR(15) NOT NULL,
  ship_line1 VARCHAR(190) NOT NULL,
  ship_line2 VARCHAR(190) NULL,
  ship_landmark VARCHAR(120) NULL,
  ship_city VARCHAR(80) NOT NULL,
  ship_state VARCHAR(80) NOT NULL,
  ship_pincode CHAR(6) NOT NULL,
  shipping_method VARCHAR(20) NOT NULL DEFAULT 'standard',
  mrp_total DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  product_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(30) NULL,
  coupon_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  cod_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,      -- GST included in prices (informational split)
  total DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(20) NOT NULL,              -- razorpay | cod
  payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending | cod_pending | paid | failed | refund_pending | refunded
  order_status VARCHAR(30) NOT NULL DEFAULT 'placed',     -- pending_payment | placed | processing | packed | shipped | out_for_delivery | delivered | cancelled
  razorpay_order_id VARCHAR(60) NULL,
  razorpay_payment_id VARCHAR(60) NULL,
  paid_at DATETIME NULL,
  estimated_delivery DATE NULL,
  delivered_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  stock_released TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_orders_user ON orders (user_id, created_at);
CREATE INDEX idx_orders_status ON orders (order_status, created_at);
CREATE INDEX idx_orders_rzp ON orders (razorpay_order_id);

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  sku VARCHAR(40) NOT NULL,
  name VARCHAR(220) NOT NULL,
  brand VARCHAR(80) NOT NULL,
  image_url VARCHAR(500) NULL,
  options TEXT NULL,
  qty INT NOT NULL,
  mrp DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,         -- price actually charged (snapshot)
  gst_rate DECIMAL(4,2) NOT NULL,
  tax_amount DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,         -- after coupon share
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  note VARCHAR(255) NULL,
  simulated TINYINT(1) NOT NULL DEFAULT 1,   -- 1 = admin-managed/simulated tracking, 0 = real courier feed
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_events_order ON order_events (order_id, created_at);

CREATE TABLE returns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  type VARCHAR(15) NOT NULL,                 -- return | replacement
  reason VARCHAR(120) NOT NULL,
  details VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'requested',   -- requested | approved | rejected | completed
  admin_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE refunds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  return_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'initiated',   -- initiated | processed | failed
  method VARCHAR(20) NOT NULL DEFAULT 'manual',      -- razorpay | manual
  razorpay_refund_id VARCHAR(60) NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(100) NOT NULL UNIQUE,     -- uniqueness makes webhook handling idempotent
  event_type VARCHAR(60) NOT NULL,
  payload TEXT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_attempts ON login_attempts (email, created_at);
