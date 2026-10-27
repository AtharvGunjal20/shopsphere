# ShopSphere India

A full-stack demo e-commerce site (Amazon/Flipkart-style) in **PHP 8.2+, MySQL 8+ (PDO), vanilla JS, Bootstrap 5, Chart.js**, with Razorpay test-mode payments. No framework, MVC-style layout.

> **Demo data notice.** Brands are fictional and all prices, stock, discounts and reviews are *sample data* (the UI says so). Product images ship as generated SVG placeholders — see "Real product images" below.

## Quick start (XAMPP / any PHP + MySQL)

1. Copy the folder to `htdocs/shopsphere` (or any web root). PHP needs `pdo_mysql`, `mbstring`, `curl`.
2. Create the database and tables: `mysql -u root -p < database/schema.sql`
3. `cp config/config.local.example.php config/config.local.php` and set DB credentials (and Razorpay keys, optional).
4. Load demo data: `php database/seed.php`  (`--fresh` wipes and reseeds)
5. Open `http://localhost/shopsphere/` (root `.htaccess` forwards to `public/`). Needs `mod_rewrite` with `AllowOverride All`.
   - Or: `cd public && php -S localhost:8000 index.php` → http://localhost:8000
   - Production: point the vhost `DocumentRoot` at `public/`, serve over HTTPS, set `app.env` to `production`.

### Demo logins (change/delete before any real deployment)
| Role | Email | Password |
|---|---|---|
| Admin | admin@shopsphere.test | Admin@12345 |
| Staff (orders/returns/refunds only) | staff@shopsphere.test | Staff@12345 |
| Customer | demo@shopsphere.test | Demo@12345 |

Coupons: `WELCOME10`, `FLAT200`, `FESTIVE15`, `FREESHIP`.

## Features
Storefront: hero carousel, deals, bestsellers, recommendations (from browsing/purchase history), new arrivals, per-category shelves, recently viewed, reviews, newsletter, live search suggestions with recent searches, PIN-based delivery estimates, filters (price, brand, rating, discount, stock, colour, size), 7 sorts, pagination, wishlist, compare (up to 4), Q&A, frequently-bought-together.
Cart/checkout: guest + user carts (merged on login), server-side pricing/GST/coupons/shipping, stock re-validation inside a DB transaction with conditional stock decrement, COD and Razorpay, invoice (printable), confirmation email.
Orders: tracking timeline, cancellation rules, return/replacement requests, refund tracking.
Admin (`/admin`): KPIs + Chart.js analytics, products CRUD (server-validated prices/stock, auto discount %), orders & tracking updates, returns, refunds, coupons, users/roles.
Security: PDO prepared statements, `password_hash`, session-id regeneration on login, session fingerprint + idle timeout, CSRF token on every POST, login throttling, role checks, output escaping, CSP/security headers, HMAC-verified Razorpay callbacks and webhooks.

## Razorpay (test mode)
1. Put test keys in `config/config.local.php` (`razorpay.key_id`, `key_secret`).
2. In the Razorpay dashboard add webhook `https://YOUR-HOST/webhook/razorpay` with events `payment.captured`, `payment.failed`, `refund.processed`, and put its secret in `webhook_secret`.
3. Flow: server creates the order → Checkout opens → browser callback is **signature-verified and re-fetched from Razorpay** → order marked paid only server-side. The webhook is idempotent (unique event id). A redirect to the success page never marks an order paid. Late payments on expired/cancelled orders create a refund task automatically.

Without keys, online payment is disabled and COD works.

## Email
`mail.enabled = false` (default) writes emails to `storage/logs/mail.log` — open it to get verification and reset links locally. Set SMTP details and `enabled = true` to send real mail.

## Cron
`php bin/expire_pending.php` every ~10 min releases stock held by unpaid online orders (also runs lazily on page loads).

## Real product images
The catalogue uses generated SVG placeholders because this project can't license real photos for you. To use genuine images: use official manufacturer/press feeds, an authorised image API, or your own photos, then either edit images in **Admin → Products**, or run `php bin/import_images.php images.csv` (`SKU,https://url1,https://url2`). A broken URL falls back to a placeholder automatically.

## Honest limitations
- Tracking is **admin-entered/simulated**, not a courier integration (labelled as such in the UI).
- Delivery estimates come from configurable rules, not live courier data.
- Invoice is a sample, not a legal GST invoice. Replace seller details/policies in `config/config.php` and `/page/*` text.
- Only English is wired up; strings are in views and can be moved to `app/lang/<code>.php`.
- Tested end-to-end here against SQLite with a converted schema plus PHP's built-in server; MySQL 8 is the target and the SQL is standard, but run the flow once on your MySQL install.

## Layout
`public/` web root · `app/Core` router, DB, helpers · `app/Services` business logic (cart, orders, payments, auth) · `app/Controllers` · `app/Views` · `database/` schema + seeder · `bin/` CLI tools · `config/`
