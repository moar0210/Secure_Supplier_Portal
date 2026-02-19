<?php

declare(strict_types=1);

$auth->requireRole('ADMIN');

$cookieParams = session_get_cookie_params();
$sessName = session_name();
$sessId = session_id();

$last = $_SESSION['security']['last_activity'] ?? null;
$lastStr = is_int($last) ? date('Y-m-d H:i:s', $last) : 'n/a';

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
?>

<h1>Security Check</h1>

<p>This page is for verifying session + security baseline behavior.</p>

<h2>Session</h2>
<table border="1" cellpadding="6" cellspacing="0">
    <tbody>
        <tr>
            <th>Session active</th>
            <td><?php echo session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no'; ?></td>
        </tr>
        <tr>
            <th>Session name</th>
            <td><?php echo h($sessName); ?></td>
        </tr>
        <tr>
            <th>Session id (first 12 chars)</th>
            <td><?php echo h(substr($sessId, 0, 12)) . '…'; ?></td>
        </tr>
        <tr>
            <th>HTTPS</th>
            <td><?php echo $isHttps ? 'yes' : 'no'; ?></td>
        </tr>
        <tr>
            <th>Cookie path</th>
            <td><?php echo h((string)($cookieParams['path'] ?? '')); ?></td>
        </tr>
        <tr>
            <th>Cookie domain</th>
            <td><?php echo h((string)($cookieParams['domain'] ?? '')); ?></td>
        </tr>
        <tr>
            <th>Cookie secure</th>
            <td><?php echo !empty($cookieParams['secure']) ? 'true' : 'false'; ?></td>
        </tr>
        <tr>
            <th>Cookie httponly</th>
            <td><?php echo !empty($cookieParams['httponly']) ? 'true' : 'false'; ?></td>
        </tr>
        <tr>
            <th>Last activity</th>
            <td><?php echo h($lastStr); ?></td>
        </tr>
    </tbody>
</table>

<h2>Headers</h2>
<p>Open DevTools → Network → this request → Response Headers and verify:</p>
<ul>
    <li>Content-Security-Policy</li>
    <li>X-Content-Type-Options</li>
    <li>Referrer-Policy</li>
    <li>X-Frame-Options</li>
    <li>Permissions-Policy (optional)</li>
</ul>

<h2>Notes</h2>
<ul>
    <li><strong>Secure cookie</strong> will be false on HTTP localhost; it becomes true on HTTPS.</li>
    <li>If inactivity timeout is enabled, staying idle past the threshold should redirect to <code>?page=login&amp;timeout=1</code>.</li>
</ul>
