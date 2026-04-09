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

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "media-src 'self'",
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
                'samesite' => 'Strict',
            ]);
        } else {
            session_set_cookie_params(
                0,
                ($params['path'] ?? '/') . '; samesite=Strict',
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

        self::expireSessionCookie();

        session_destroy();

        $page = $_GET['page'] ?? 'home';
        $publicPages = ['login', 'logout', 'reset_request', 'reset_password'];
        if (!in_array($page, $publicPages, true)) {
            header('Location: ?page=login&timeout=1');
            exit;
        }
    }

    public static function expireSessionCookie(): void
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => 'Strict',
            ]);

            return;
        }

        setcookie(
            session_name(),
            '',
            time() - 42000,
            ($params['path'] ?? '/') . '; samesite=Strict',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
}

SecurityBootstrap::run();
