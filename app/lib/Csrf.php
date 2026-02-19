<?php

declare(strict_types=1);

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';
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
        $good = (string)($_SESSION[self::SESSION_KEY] ?? '');

        if ($sent === '' || $good === '' || !hash_equals($good, $sent)) {
            header('Location: ?page=403');
            exit;
        }

        self::rotate();
    }

    private static function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
