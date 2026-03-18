<?php

declare(strict_types=1);

final class SecurityBootstrap
{
    public static function run(): void
    {
        self::applyHeaders();
        self::startSecureSession();
        self::enforceIdleTimeout();
    }

    private static function applyHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "connect-src 'self'",
        ]);

        header("Content-Security-Policy: {$csp}");
    }

    private static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $params = session_get_cookie_params();

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(
                0,
                ($params['path'] ?? '/') . '; samesite=Lax',
                $params['domain'] ?? '',
                $isHttps,
                true
            );
        }

        session_start();
    }

    private static function enforceIdleTimeout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $timeoutSeconds = 30 * 60; // 30 minutes

        $now = time();
        $last = isset($_SESSION['security']['last_activity']) ? (int)$_SESSION['security']['last_activity'] : $now;
        $_SESSION['security']['last_activity'] = $now;

        if (($now - $last) <= $timeoutSeconds) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }

        session_destroy();

        $page = $_GET['page'] ?? 'home';
        $publicPages = ['login', 'logout', 'reset_request', 'reset_password'];
        if (!in_array($page, $publicPages, true)) {
            header('Location: ?page=login&timeout=1');
            exit;
        }
    }
}

SecurityBootstrap::run();
