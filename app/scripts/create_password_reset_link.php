<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$identifier = trim((string)($argv[1] ?? ''));
if ($identifier === '') {
    fwrite(STDERR, "Usage: C:\\xampp\\php\\php.exe app\\scripts\\create_password_reset_link.php <username-or-email>\n");
    exit(1);
}

$bootstrap = require __DIR__ . '/bootstrap.php';

require_once $bootstrap['root'] . '/app/lib/auth.php';

$pdo = $bootstrap['pdo'];
$config = (array)$bootstrap['config'];
$sessionDir = $bootstrap['root'] . '/app/storage/cli_sessions';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
    fwrite(STDERR, "[ERROR] Unable to create the CLI session directory.\n");
    exit(1);
}

session_save_path($sessionDir);

try {
    $auth = new Auth($pdo);
    $tokenData = $auth->requestPasswordReset($identifier);

    if ($tokenData === null) {
        fwrite(STDOUT, "No active account matched the supplied identifier.\n");
        exit(0);
    }

    $path = '?page=reset_password&username='
        . rawurlencode((string)$tokenData['username'])
        . '&token='
        . rawurlencode((string)$tokenData['token']);

    $baseUrl = rtrim((string)($config['portal']['base_url'] ?? ''), '/');
    $link = $baseUrl !== '' ? $baseUrl . '/' . $path : $path;

    fwrite(STDOUT, "Reset link for " . (string)$tokenData['username'] . " (expires " . (string)$tokenData['expires_at'] . "):\n");
    fwrite(STDOUT, $link . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
