<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Services\Auth;

final class AuthController
{
    private function after(): never
    {
        $to = $_SESSION['_intended'] ?? '/account';
        unset($_SESSION['_intended']);
        // Only allow same-site relative redirects.
        if (!is_string($to) || !str_starts_with($to, '/') || str_starts_with($to, '//')) $to = '/account';
        $b = base_path();
        if ($b !== '' && str_starts_with($to, $b)) $to = substr($to, strlen($b)) ?: '/';
        redirect($to);
    }

    public function loginForm(): void
    {
        if (current_user()) redirect('/account');
        render('auth/login', ['title' => 'Sign in - ' . cfg('app.name')]);
    }

    public function login(): void
    {
        $email = strtolower(str_in('email', 190));
        $pw = (string)input('password', '');
        if (Auth::tooManyAttempts($email)) {
            flash('danger', 'Too many failed attempts. Please wait 15 minutes or reset your password.');
            redirect('/login');
        }
        $id = Auth::attempt($email, $pw);
        if (!$id) {
            with_old(['email' => $email]);
            flash('danger', 'Incorrect email or password.');
            redirect('/login');
        }
        clear_old();
        Auth::login($id);
        flash('success', 'Welcome back!');
        $this->after();
    }

    public function registerForm(): void
    {
        if (current_user()) redirect('/account');
        render('auth/register', ['title' => 'Create account - ' . cfg('app.name')]);
    }

    public function register(): void
    {
        $name = str_in('name', 120); $email = str_in('email', 190); $phone = preg_replace('/\D/', '', str_in('phone', 15)) ?? '';
        $pw = (string)input('password', ''); $pw2 = (string)input('password2', '');
        if ($pw !== $pw2) { with_old($_POST); flash('danger', 'Passwords do not match.'); redirect('/register'); }
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) $phone = substr($phone, 2);
        [$id, $err] = Auth::register($name, $email, $phone, $pw);
        if ($err) { with_old($_POST); flash('danger', $err); redirect('/register'); }
        clear_old();
        Auth::login((int)$id);
        flash('success', 'Account created! We sent a verification link to your email.');
        $this->after();
    }

    public function logout(): void
    {
        Auth::logout();
        flash('info', 'You have been signed out.');
        redirect('/');
    }

    public function verify(array $p): void
    {
        if (Auth::verifyEmail($p['token'])) flash('success', 'Email verified. Thank you!');
        else flash('danger', 'This verification link is invalid or has expired.');
        redirect(current_user() ? '/account' : '/login');
    }

    public function resendVerification(): void
    {
        $u = require_login();
        Auth::sendVerification((int)$u['id']);
        flash('info', 'Verification email sent (if your email is not yet verified).');
        redirect('/account');
    }

    public function forgotForm(): void
    {
        render('auth/forgot', ['title' => 'Forgot password - ' . cfg('app.name')]);
    }

    public function forgot(): void
    {
        Auth::sendReset(str_in('email', 190));
        flash('success', 'If an account exists for that email, a reset link has been sent.');
        redirect('/forgot');
    }

    public function resetForm(array $p): void
    {
        if (!Auth::resetRow($p['token'])) { flash('danger', 'This reset link is invalid or has expired.'); redirect('/forgot'); }
        render('auth/reset', ['title' => 'Reset password - ' . cfg('app.name'), 'token' => $p['token']]);
    }

    public function reset(array $p): void
    {
        $pw = (string)input('password', '');
        if ($pw !== (string)input('password2', '')) { flash('danger', 'Passwords do not match.'); redirect('/reset/' . $p['token']); }
        $err = Auth::resetPassword($p['token'], $pw);
        if ($err) { flash('danger', $err); redirect('/reset/' . $p['token']); }
        flash('success', 'Password updated. Please sign in.');
        redirect('/login');
    }
}
