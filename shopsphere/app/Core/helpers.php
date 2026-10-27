<?php
declare(strict_types=1);

use App\Core\DB;

function cfg(string $key, mixed $default = null): mixed
{
    $v = $GLOBALS['__cfg'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function log_error(string $msg): void
{
    $dir = ROOT . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/app.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_starts_with(request_path(), '/api/')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

// ---- URLs -----------------------------------------------------------------
function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        if ($b === '.') $b = '';
    }
    return $b;
}

function request_path(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $b = base_path();
    if ($b !== '' && str_starts_with($p, $b)) $p = substr($p, strlen($b));
    $p = '/' . trim($p, '/');
    return $p === '' ? '/' : $p;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function abs_url(string $path = ''): string
{
    $root = cfg('app.url');
    if (!$root) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $root = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    return rtrim($root, '/') . '/' . ltrim($path, '/');
}

function redirect(string $to, int $code = 302): never
{
    if (!preg_match('#^https?://#', $to)) $to = url($to);
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Image URL helper: absolute http(s) stays as is, "/x" is made base-relative, empty -> placeholder. */
function img_url(?string $u): string
{
    if (!$u) return asset('img/placeholder.svg');
    if (preg_match('#^https?://#i', $u)) return $u;
    return url(ltrim($u, '/'));
}

// ---- money (Indian numbering) --------------------------------------------
function inr(float|int|string|null $n): string
{
    $n = round((float)$n, 2);
    $neg = $n < 0;
    $n = abs($n);
    $whole = (int)floor($n);
    $frac = (int)round(($n - $whole) * 100);
    if ($frac === 100) { $whole++; $frac = 0; }
    $s = (string)$whole;
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $s = $rest . ',' . $last3;
    }
    $out = '₹' . $s . ($frac > 0 ? '.' . str_pad((string)$frac, 2, '0', STR_PAD_LEFT) : '');
    return ($neg ? '-' : '') . $out;
}

function discount_pct(float $mrp, float $price): int
{
    if ($mrp <= 0 || $price >= $mrp) return 0;
    return (int)round((($mrp - $price) / $mrp) * 100);
}

// ---- input ----------------------------------------------------------------
function input(string $key, mixed $default = null): mixed
{
    static $json = null;
    if ($json === null) {
        $json = [];
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $d = json_decode(file_get_contents('php://input') ?: '', true);
            if (is_array($d)) $json = $d;
        }
    }
    return $json[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
}

function str_in(string $key, int $max = 255): string
{
    $v = input($key, '');
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '', '-'));
    return $s !== '' ? $s : 'item';
}

// ---- CSRF -----------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['_csrf'] ?? '', $sent);
}

function csrf_require(): void
{
    if (!csrf_check()) {
        if (wants_json()) json_out(['ok' => false, 'error' => 'Session expired. Please refresh the page.'], 419);
        http_response_code(419);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

// ---- flash & old input ----------------------------------------------------
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function with_old(array $data): void
{
    unset($data['password'], $data['password2'], $data['_csrf']);
    $_SESSION['_old'] = $data;
}

function old(string $k, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$k] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---- auth shortcuts -------------------------------------------------------
function current_user(): ?array
{
    return \App\Services\Auth::user();
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_staff(): bool
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'staff'], true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        if (wants_json()) json_out(['ok' => false, 'error' => 'Please sign in to continue.', 'login' => true], 401);
        flash('info', 'Please sign in to continue.');
        redirect('/login');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('403 - You do not have permission to view this page.');
    }
    return $u;
}

// ---- rendering ------------------------------------------------------------
function render(string $view, array $data = [], string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/app/Views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === '') { echo $content; return; }
    require ROOT . '/app/Views/partials/' . $layout . '.php';
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require ROOT . '/app/Views/partials/' . $name . '.php';
}

function not_found(): never
{
    http_response_code(404);
    if (wants_json()) json_out(['ok' => false, 'error' => 'Not found'], 404);
    render('404', ['title' => 'Page not found']);
    exit;
}

// ---- view helpers ---------------------------------------------------------
function stars(float $rating): string
{
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) $out .= '<i class="bi bi-star-fill"></i>';
        elseif ($rating >= $i - 0.5) $out .= '<i class="bi bi-star-half"></i>';
        else $out .= '<i class="bi bi-star"></i>';
    }
    return '<span class="stars" aria-label="' . e(number_format($rating, 1)) . ' out of 5 stars">' . $out . '</span>';
}

function availability(array $p): array
{
    if (!(int)$p['is_active'] || (int)$p['stock'] <= 0) return ['Out of stock', 'danger'];
    if ((int)$p['stock'] <= 5) return ['Only ' . (int)$p['stock'] . ' left in stock', 'warning'];
    return ['In stock', 'success'];
}

function json_dec(?string $s): array
{
    if (!$s) return [];
    $d = json_decode($s, true);
    return is_array($d) ? $d : [];
}

function status_label(string $s): string
{
    return ucwords(str_replace('_', ' ', $s));
}

function status_badge(string $s): string
{
    $map = [
        'delivered' => 'success', 'paid' => 'success', 'completed' => 'success', 'processed' => 'success', 'approved' => 'success',
        'cancelled' => 'danger', 'failed' => 'danger', 'rejected' => 'danger',
        'pending_payment' => 'warning', 'pending' => 'warning', 'requested' => 'warning', 'initiated' => 'warning', 'refund_pending' => 'warning', 'cod_pending' => 'secondary',
        'refunded' => 'info',
    ];
    return '<span class="badge text-bg-' . ($map[$s] ?? 'primary') . '">' . e(status_label($s)) . '</span>';
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function indian_states(): array
{
    return ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
        'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan',
        'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'];
}

/** Validate an address array from request input. @return array{0:?array,1:?string} */
function parse_address(string $prefix = ''): array
{
    $g = fn(string $k, int $max = 190) => str_in($prefix . $k, $max);
    $a = [
        'label' => $g('label', 30) ?: 'Home', 'full_name' => $g('full_name', 120), 'phone' => preg_replace('/\D/', '', $g('phone', 15)) ?? '',
        'line1' => $g('line1'), 'line2' => $g('line2'), 'landmark' => $g('landmark', 120), 'city' => $g('city', 80),
        'state' => $g('state', 80), 'pincode' => preg_replace('/\D/', '', $g('pincode', 10)) ?? '',
    ];
    if (strlen($a['phone']) === 12 && str_starts_with($a['phone'], '91')) $a['phone'] = substr($a['phone'], 2);
    if (mb_strlen($a['full_name']) < 2) return [null, 'Please enter the recipient\'s name.'];
    if (!\App\Services\Auth::validPhone($a['phone'])) return [null, 'Enter a valid 10-digit mobile number for delivery.'];
    if (mb_strlen($a['line1']) < 5) return [null, 'Please enter your house / street address.'];
    if (mb_strlen($a['city']) < 2) return [null, 'Please enter your city.'];
    if (!in_array($a['state'], indian_states(), true)) return [null, 'Please select a valid state.'];
    if (!\App\Services\Delivery::validPin($a['pincode'])) return [null, 'Enter a valid 6-digit PIN code.'];
    return [$a, null];
}
