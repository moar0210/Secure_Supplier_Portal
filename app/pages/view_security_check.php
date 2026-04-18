<div class="page-header">
    <h1>Security check</h1>
</div>

<p class="muted">This page verifies session and security baseline behavior.</p>

<h2>Session</h2>
<table>
    <tbody>
        <tr>
            <th>Session active</th>
            <td><?= session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no' ?></td>
        </tr>
        <tr>
            <th>Session name</th>
            <td><?= h($sessName) ?></td>
        </tr>
        <tr>
            <th>Session id (masked)</th>
            <td><?= h($sessIdMasked) ?></td>
        </tr>
        <tr>
            <th>HTTPS</th>
            <td><?= $isHttps ? 'yes' : 'no' ?></td>
        </tr>
        <tr>
            <th>Cookie path</th>
            <td><?= h((string)($cookieParams['path'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Cookie domain</th>
            <td><?= h((string)($cookieParams['domain'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Cookie secure</th>
            <td><?= !empty($cookieParams['secure']) ? 'true' : 'false' ?></td>
        </tr>
        <tr>
            <th>Cookie httponly</th>
            <td><?= !empty($cookieParams['httponly']) ? 'true' : 'false' ?></td>
        </tr>
        <tr>
            <th>Last activity</th>
            <td class="muted small"><?= h($lastStr) ?></td>
        </tr>
    </tbody>
</table>

<h2>Headers</h2>
<div class="card card--muted">
    <p class="mb-0">Open DevTools &rarr; Network &rarr; this request &rarr; Response Headers and verify:</p>
    <ul class="mb-0 mt-3">
        <li>Content-Security-Policy</li>
        <li>X-Content-Type-Options</li>
        <li>Referrer-Policy</li>
        <li>X-Frame-Options</li>
        <li>Permissions-Policy (optional)</li>
    </ul>
</div>

<h2>Encryption</h2>
<table>
    <tbody>
        <tr>
            <th>Encryption enabled</th>
            <td><?= $cryptoEnabled ? 'yes' : 'no' ?></td>
        </tr>
        <tr>
            <th>Crypto driver</th>
            <td><?= h($cryptoDriver) ?></td>
        </tr>
        <tr>
            <th>Active key id</th>
            <td><?= h($cryptoActiveKeyId) ?></td>
        </tr>
        <tr>
            <th>Configured keys</th>
            <td><?= (int)$cryptoKeyCount ?></td>
        </tr>
    </tbody>
</table>

<h2>Notes</h2>
<div class="card card--muted">
    <ul class="mb-0">
        <li><strong>Secure cookie</strong> will be false on HTTP localhost; it becomes true on HTTPS.</li>
        <li>If inactivity timeout is enabled, staying idle past the threshold should redirect to <code>?page=login&amp;timeout=1</code>.</li>
        <li>This page exposes only safe encryption metadata. It never shows real keys, ciphertext, or decrypted supplier data.</li>
        <li>The configured key count is only a count. It does not reveal any key material.</li>
        <li>Operational key setup and rotation notes are documented in the README.</li>
        <?php if (!$cryptoEnabled): ?>
            <li>Encryption is currently disabled, so new sensitive profile writes are not protected at rest until <code>crypto.enabled</code> is turned on.</li>
        <?php endif; ?>
    </ul>
</div>
