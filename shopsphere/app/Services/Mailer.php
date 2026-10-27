<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Minimal SMTP mailer (STARTTLS / SSL / plain, AUTH LOGIN).
 * When mail.enabled is false every message is appended to storage/logs/mail.log instead,
 * which is how you read verification / password-reset links during local development.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $c = cfg('mail');
        if (empty($c['enabled'])) {
            self::log($to, $subject, $html);
            return true;
        }
        try {
            return self::smtp($c, $to, $subject, $html);
        } catch (\Throwable $e) {
            log_error('Mail failed: ' . $e->getMessage());
            self::log($to, $subject . ' [SMTP FAILED]', $html);
            return false;
        }
    }

    private static function log(string $to, string $subject, string $html): void
    {
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html) ?? $html)));
        @file_put_contents(
            ROOT . '/storage/logs/mail.log',
            "=== " . date('c') . " | To: $to | Subject: $subject\n$text\n\n",
            FILE_APPEND
        );
    }

    private static function smtp(array $c, string $to, string $subject, string $html): bool
    {
        $host = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = @stream_socket_client($host . ':' . $c['port'], $errno, $errstr, 15);
        if (!$fp) throw new \RuntimeException("SMTP connect failed: $errstr");
        stream_set_timeout($fp, 15);

        $read = function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $out;
        };
        $cmd = function (string $line, string $expect) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) throw new \RuntimeException("SMTP error after [" . explode(' ', $line)[0] . "]: $r");
            return $r;
        };

        $read();
        $domain = parse_url(abs_url(), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $domain", '250');
        if ($c['secure'] === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('TLS failed');
            $cmd("EHLO $domain", '250');
        }
        if ($c['user'] !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode($c['user']), '334');
            $cmd(base64_encode($c['pass']), '235');
        }
        $cmd('MAIL FROM:<' . $c['from'] . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');

        $headers = [
            'From: =?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $c['from'] . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = chunk_split(base64_encode($html));
        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $r = $read();
        $cmd('QUIT', '221');
        fclose($fp);
        return str_starts_with($r, '250');
    }

    /** Wrap content in a simple branded HTML email shell. */
    public static function wrap(string $title, string $inner): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">'
            . '<div style="background:#1f2a44;color:#fff;padding:16px 20px;font-size:20px;font-weight:bold">' . e(cfg('app.name')) . '</div>'
            . '<div style="padding:20px;color:#111827"><h2 style="margin-top:0">' . e($title) . '</h2>' . $inner . '</div>'
            . '<div style="background:#f3f4f6;color:#6b7280;padding:12px 20px;font-size:12px">This is an automated message from ' . e(cfg('app.name')) . '. Demo store: prices shown are illustrative.</div></div>';
    }
}
