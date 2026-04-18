<?php

declare(strict_types=1);

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';
    private const PREVIOUS_KEY = 'csrf_token_previous';
    private const PREVIOUS_EXPIRES_KEY = 'csrf_token_previous_expires';
    private const GRACE_SECONDS = 300;
    private const FIELD_NAME  = 'csrf_token';

    public static function token(): string
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function input(): string
    {
        $t = self::token();
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public static function verifyOrFail(): void
    {
        self::ensureSession();

        $sent = (string)($_POST[self::FIELD_NAME] ?? '');
        $current = (string)($_SESSION[self::SESSION_KEY] ?? '');
        $previous = (string)($_SESSION[self::PREVIOUS_KEY] ?? '');
        $previousExpires = (int)($_SESSION[self::PREVIOUS_EXPIRES_KEY] ?? 0);

        if ($sent === '') {
            self::fail();
        }

        if ($current !== '' && hash_equals($current, $sent)) {
            self::rotate();
            return;
        }

        if ($previous !== '' && $previousExpires >= time() && hash_equals($previous, $sent)) {
            // Accept prior token during its grace window (multi-tab, back button).
            // Do not rotate again — the current token is already fresh.
            return;
        }

        self::fail();
    }

    private static function fail(): void
    {
        header('Location: ?page=403');
        exit;
    }

    private static function rotate(): void
    {
        $current = (string)($_SESSION[self::SESSION_KEY] ?? '');
        if ($current !== '') {
            $_SESSION[self::PREVIOUS_KEY] = $current;
            $_SESSION[self::PREVIOUS_EXPIRES_KEY] = time() + self::GRACE_SECONDS;
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
