<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
                $u = DB::one('SELECT id, name, email, phone, role, status, email_verified_at FROM users WHERE id = ?', [(int)$id]);
                if ($u && $u['status'] === 'active') {
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);                // prevents session fixation
        $_SESSION['uid'] = $userId;
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;
        self::$user = null;
        CartService::mergeGuestCart($userId);
        Catalog::mergeRecent($userId);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$loaded = false;
        self::$user = null;
    }

    public static function passwordError(string $pw): ?string
    {
        if (strlen($pw) < 8) return 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Password must contain at least one letter and one number.';
        if (strlen($pw) > 128) return 'Password is too long.';
        return null;
    }

    public static function validPhone(string $p): bool
    {
        return (bool)preg_match('/^[6-9]\d{9}$/', $p);   // Indian mobile numbers
    }

    /** @return array{0:?int,1:?string} [userId, error] */
    public static function register(string $name, string $email, string $phone, string $password): array
    {
        $email = strtolower(trim($email));
        if (mb_strlen($name) < 2) return [null, 'Please enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'Please enter a valid email address.'];
        if (!self::validPhone($phone)) return [null, 'Enter a valid 10-digit Indian mobile number.'];
        if ($err = self::passwordError($password)) return [null, $err];
        if (DB::val('SELECT id FROM users WHERE email = ?', [$email])) return [null, 'An account with this email already exists.'];

        $id = DB::insert('users', [
            'name' => $name, 'email' => $email, 'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'customer', 'status' => 'active', 'created_at' => now(),
        ]);
        self::sendVerification($id);
        return [$id, null];
    }

    public static function tooManyAttempts(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $byEmail = (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
        $byIp = (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
        return $byEmail >= 5 || $byIp >= 20;
    }

    public static function attempt(string $email, string $password): ?int
    {
        $email = strtolower(trim($email));
        $u = DB::one('SELECT id, password_hash, status FROM users WHERE email = ?', [$email]);
        // Always run a hash verification to keep timing similar for unknown emails.
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ1h5Wq5rWd0z6uD6uC0Z9R1M5C2e2';
        $ok = password_verify($password, $hash);
        if ($u && $ok && $u['status'] === 'active') {
            DB::exec('DELETE FROM login_attempts WHERE email = ?', [$email]);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
            }
            return (int)$u['id'];
        }
        DB::insert('login_attempts', ['email' => $email, 'ip' => client_ip(), 'created_at' => now()]);
        return null;
    }

    // ---- email verification ----------------------------------------------
    public static function sendVerification(int $userId): void
    {
        $u = DB::one('SELECT id, name, email, email_verified_at FROM users WHERE id = ?', [$userId]);
        if (!$u || $u['email_verified_at']) return;
        $token = bin2hex(random_bytes(32));
        DB::exec('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        DB::insert('email_verifications', [
            'user_id' => $userId, 'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400), 'created_at' => now(),
        ]);
        $link = abs_url('verify/' . $token);
        Mailer::send($u['email'], 'Verify your email - ' . cfg('app.name'), Mailer::wrap(
            'Welcome, ' . $u['name'] . '!',
            '<p>Please confirm your email address to secure your account.</p><p><a href="' . e($link) . '" style="background:#f59e0b;color:#111;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold">Verify email</a></p><p>This link expires in 24 hours.</p>'
        ));
    }

    public static function verifyEmail(string $token): bool
    {
        $row = DB::one('SELECT id, user_id, expires_at FROM email_verifications WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$row || $row['expires_at'] < now()) return false;
        DB::update('users', ['email_verified_at' => now()], 'id = ?', [$row['user_id']]);
        DB::exec('DELETE FROM email_verifications WHERE user_id = ?', [$row['user_id']]);
        return true;
    }

    // ---- password reset -------------------------------------------------------
    public static function sendReset(string $email): void
    {
        $u = DB::one('SELECT id, name, email FROM users WHERE email = ? AND status = ?', [strtolower(trim($email)), 'active']);
        if (!$u) return;   // never reveal whether the email exists
        $token = bin2hex(random_bytes(32));
        DB::insert('password_resets', [
            'user_id' => $u['id'], 'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => now(),
        ]);
        $link = abs_url('reset/' . $token);
        Mailer::send($u['email'], 'Reset your password - ' . cfg('app.name'), Mailer::wrap(
            'Password reset',
            '<p>Hi ' . e($u['name']) . ', we received a request to reset your password.</p><p><a href="' . e($link) . '" style="background:#f59e0b;color:#111;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold">Choose a new password</a></p><p>This link is valid for 1 hour. If you did not ask for this, you can ignore this email.</p>'
        ));
    }

    public static function resetRow(string $token): ?array
    {
        $row = DB::one('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$row || $row['used_at'] || $row['expires_at'] < now()) return null;
        return $row;
    }

    public static function resetPassword(string $token, string $newPassword): ?string
    {
        if ($err = self::passwordError($newPassword)) return $err;
        $row = self::resetRow($token);
        if (!$row) return 'This reset link is invalid or has expired.';
        DB::tx(function () use ($row, $newPassword) {
            DB::update('users', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = ?', [$row['user_id']]);
            DB::update('password_resets', ['used_at' => now()], 'id = ?', [$row['id']]);
        });
        return null;
    }
}
