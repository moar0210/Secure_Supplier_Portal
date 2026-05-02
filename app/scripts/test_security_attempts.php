<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "[ERROR] The curl extension is required for HTTP security checks.\n");
    exit(1);
}

$bootstrap = require __DIR__ . '/bootstrap.php';

require_once $bootstrap['root'] . '/app/lib/auth.php';
require_once $bootstrap['root'] . '/app/lib/InvoiceService.php';

$root = (string)$bootstrap['root'];
$config = (array)$bootstrap['config'];
$pdo = $bootstrap['pdo'];
$crypto = $bootstrap['crypto'];
$supplierService = $bootstrap['supplierService'];

$baseUrl = resolveBaseUrl($root, $config, $argv[1] ?? null);
$cookieDir = $root . '/tmp';
if (!is_dir($cookieDir) && !mkdir($cookieDir, 0775, true) && !is_dir($cookieDir)) {
    fwrite(STDERR, "[ERROR] Unable to create tmp directory for cookie jars.\n");
    exit(1);
}

$checks = [];
$cookieFiles = [];
$fixture = [
    'admin_user_id' => null,
    'supplier_a_id' => null,
    'supplier_a_uuid' => null,
    'supplier_a_address_uuid' => null,
    'supplier_a_person_uuid' => null,
    'supplier_a_user_id' => null,
    'supplier_b_id' => null,
    'supplier_b_uuid' => null,
    'supplier_b_address_uuid' => null,
    'supplier_b_person_uuid' => null,
    'supplier_b_user_id' => null,
    'bruteforce_user_id' => null,
    'supplier_b_invoice_id' => null,
];

try {
    assertTrueValue(tableExists($pdo, 'portal_users'), 'security prerequisite: portal_users table exists', $checks);
    assertTrueValue(tableExists($pdo, 'invoices'), 'security prerequisite: invoices table exists', $checks);

    $fixture['admin_user_id'] = createPortalUser($pdo, 'security_admin', null, 'ADMIN', 'SecurityAdmin123');

    $fixture['supplier_a_id'] = $supplierService->createSupplier(supplierInput('A'), null, (int)$fixture['admin_user_id']);
    $supplierService->setSupplierActiveState((int)$fixture['supplier_a_id'], true);
    $supplierA = fetchSupplierRow($pdo, (int)$fixture['supplier_a_id']);
    $fixture['supplier_a_uuid'] = (string)$supplierA['uuid_supplier'];
    $fixture['supplier_a_address_uuid'] = (string)$supplierA['uuid_address_main'];
    $fixture['supplier_a_person_uuid'] = (string)$supplierA['uuid_person_contact'];
    $fixture['supplier_a_user_id'] = createPortalUser($pdo, 'security_supplier_a', (int)$fixture['supplier_a_id'], 'SUPPLIER', 'SecuritySupplierA123');

    $fixture['supplier_b_id'] = $supplierService->createSupplier(supplierInput('B'), null, (int)$fixture['admin_user_id']);
    $supplierService->setSupplierActiveState((int)$fixture['supplier_b_id'], true);
    $supplierB = fetchSupplierRow($pdo, (int)$fixture['supplier_b_id']);
    $fixture['supplier_b_uuid'] = (string)$supplierB['uuid_supplier'];
    $fixture['supplier_b_address_uuid'] = (string)$supplierB['uuid_address_main'];
    $fixture['supplier_b_person_uuid'] = (string)$supplierB['uuid_person_contact'];
    $fixture['supplier_b_user_id'] = createPortalUser($pdo, 'security_supplier_b', (int)$fixture['supplier_b_id'], 'SUPPLIER', 'SecuritySupplierB123');

    $fixture['bruteforce_user_id'] = createPortalUser($pdo, 'security_lockout', null, 'ADMIN', 'CorrectLockout123');
    $fixture['supplier_b_invoice_id'] = createDraftInvoiceFixture(
        $pdo,
        $crypto,
        $supplierService,
        (int)$fixture['supplier_b_id'],
        (int)$fixture['admin_user_id']
    );

    $adminCookie = createCookieFile($cookieDir, $cookieFiles);
    $supplierCookie = createCookieFile($cookieDir, $cookieFiles);
    $anonymousCookie = createCookieFile($cookieDir, $cookieFiles);
    $lockoutCookie = createCookieFile($cookieDir, $cookieFiles);

    assertHttpReachable($baseUrl, $anonymousCookie);

    $supplierLogin = loginHttp($baseUrl, $supplierCookie, fetchUsername($pdo, (int)$fixture['supplier_a_user_id']), 'SecuritySupplierA123');
    assertTrueValue(isRedirectTo($supplierLogin, 'page=home'), 'fixture supplier can sign in through the real login form', $checks);

    $adminAttempt = httpRequest($baseUrl, 'GET', '?page=admin_users', null, $supplierCookie);
    assertTrueValue(isForbiddenOrForbiddenRedirect($adminAttempt), 'A01: supplier cannot access admin route ?page=admin_users', $checks);

    $foreignInvoiceAttempt = httpRequest($baseUrl, 'GET', '?page=invoice_pdf&id=' . (int)$fixture['supplier_b_invoice_id'], null, $supplierCookie);
    assertTrueValue(isForbiddenOrForbiddenRedirect($foreignInvoiceAttempt), 'A01: supplier A cannot fetch supplier B invoice by direct id', $checks);

    $injectionLogin = loginHttp($baseUrl, $anonymousCookie, "' OR 1=1 --", 'not-the-password');
    assertTrueValue(
        $injectionLogin['status'] === 200
            && str_contains($injectionLogin['body'], 'Invalid credentials.')
            && !isRedirectTo($injectionLogin, 'page=home'),
        "A03: SQL-style login payload does not authenticate",
        $checks
    );

    $adminLogin = loginHttp($baseUrl, $adminCookie, fetchUsername($pdo, (int)$fixture['admin_user_id']), 'SecurityAdmin123');
    assertTrueValue(isRedirectTo($adminLogin, 'page=home'), 'fixture admin can sign in through the real login form', $checks);

    $searchPayload = "'; DROP TABLE portal_users; --";
    $searchAttempt = httpRequest($baseUrl, 'GET', '?page=admin_users&search=' . rawurlencode($searchPayload), null, $adminCookie);
    assertTrueValue(
        $searchAttempt['status'] === 200 && tableExists($pdo, 'portal_users'),
        'A03: hostile search text does not break query or drop portal_users',
        $checks
    );

    $missingCsrf = httpRequest($baseUrl, 'POST', '?page=admin_users', [
        'action' => 'create',
        'username' => 'csrf_missing_' . bin2hex(random_bytes(3)),
    ], $adminCookie);
    assertTrueValue(isForbiddenOrForbiddenRedirect($missingCsrf), 'A07: POST without CSRF token is rejected', $checks);

    $wrongCsrf = httpRequest($baseUrl, 'POST', '?page=admin_users', [
        'csrf_token' => str_repeat('0', 64),
        'action' => 'create',
        'username' => 'csrf_wrong_' . bin2hex(random_bytes(3)),
    ], $adminCookie);
    assertTrueValue(isForbiddenOrForbiddenRedirect($wrongCsrf), 'A07: POST with wrong CSRF token is rejected', $checks);

    $lockoutUsername = fetchUsername($pdo, (int)$fixture['bruteforce_user_id']);
    for ($i = 1; $i <= 6; $i++) {
        loginHttp($baseUrl, $lockoutCookie, $lockoutUsername, 'WrongPassword' . $i);
    }
    assertTrueValue(isUserLocked($pdo, (int)$fixture['bruteforce_user_id']), 'A07: six failed login attempts leave account locked for 15 minutes', $checks);

    $errorAttempt = httpRequest($baseUrl, 'GET', '?page=admin_invoices&billing_month=' . rawurlencode('not-a-month'), null, $adminCookie);
    assertTrueValue(
        $errorAttempt['status'] < 500 && !containsSensitiveErrorDetails($errorAttempt['body']),
        'A05: handled error response does not expose stack trace or filesystem path',
        $checks
    );

    echo "Security attempts verification completed.\n";
    echo "Target URL: {$baseUrl}\n";
    foreach ($checks as $check) {
        echo ($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['label'] . "\n";
    }

    foreach ($checks as $check) {
        if (!$check['pass']) {
            exit(1);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    cleanupSecurityFixture($pdo, $fixture);
    foreach ($cookieFiles as $cookieFile) {
        if (is_string($cookieFile) && $cookieFile !== '' && is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

function resolveBaseUrl(string $root, array $config, ?string $override): string
{
    $candidate = trim((string)($override ?? ''));
    if ($candidate === '') {
        $candidate = trim((string)($config['portal']['base_url'] ?? ''));
    }
    if ($candidate === '') {
        $candidate = 'http://localhost/' . basename($root) . '/public';
    }

    return rtrim($candidate, '/');
}

function createCookieFile(string $cookieDir, array &$cookieFiles): string
{
    $cookieFile = tempnam($cookieDir, 'security_cookie_');
    if (!is_string($cookieFile) || $cookieFile === '') {
        throw new RuntimeException('Unable to create a temporary cookie jar.');
    }

    $cookieFiles[] = $cookieFile;

    return $cookieFile;
}

function assertHttpReachable(string $baseUrl, string $cookieFile): void
{
    $response = httpRequest($baseUrl, 'GET', '?page=login', null, $cookieFile);
    if ($response['status'] < 200 || $response['status'] >= 500 || !str_contains($response['body'], 'Sign in')) {
        throw new RuntimeException(
            'The portal did not respond like the login page. Start Apache and verify the base URL: ' . $baseUrl
        );
    }
}

function httpRequest(string $baseUrl, string $method, string $path, ?array $postData, string $cookieFile): array
{
    if (str_starts_with($path, '?')) {
        $basePath = (string)(parse_url($baseUrl, PHP_URL_PATH) ?: '');
        $url = $baseUrl . (str_ends_with($basePath, '.php') ? '' : '/') . $path;
    } else {
        $url = $baseUrl . '/' . ltrim($path, '/');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize curl.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_USERAGENT => 'SupplierPortalSecurityTest/1.0',
        CURLOPT_TIMEOUT => 20,
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData ?? [], '', '&'));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed for ' . $url . '. Start Apache or pass the correct base URL. curl: ' . $error);
    }

    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headersRaw = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);

    return [
        'status' => $status,
        'headers' => parseHeaders($headersRaw),
        'body' => $body,
    ];
}

function parseHeaders(string $headersRaw): array
{
    $headers = [];
    foreach (preg_split('/\r\n|\n|\r/', trim($headersRaw)) ?: [] as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    return $headers;
}

function loginHttp(string $baseUrl, string $cookieFile, string $identifier, string $password): array
{
    $loginPage = httpRequest($baseUrl, 'GET', '?page=login', null, $cookieFile);
    $token = extractCsrfToken($loginPage['body']);

    return httpRequest($baseUrl, 'POST', '?page=login', [
        'csrf_token' => $token,
        'identifier' => $identifier,
        'password' => $password,
    ], $cookieFile);
}

function extractCsrfToken(string $html): string
{
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('Unable to find CSRF token in login form.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isForbiddenOrForbiddenRedirect(array $response): bool
{
    if ((int)$response['status'] === 403) {
        return true;
    }

    if (!in_array((int)$response['status'], [301, 302, 303, 307, 308], true)) {
        return false;
    }

    return str_contains((string)($response['headers']['location'] ?? ''), 'page=403');
}

function isRedirectTo(array $response, string $expectedLocationPart): bool
{
    return in_array((int)$response['status'], [301, 302, 303, 307, 308], true)
        && str_contains((string)($response['headers']['location'] ?? ''), $expectedLocationPart);
}

function containsSensitiveErrorDetails(string $body): bool
{
    return preg_match('/Stack trace|Traceback|Fatal error|Warning:|Notice:|C:\\\\xampp|\/xampp\/htdocs|#\d+\s+|supplier-portal-thesis-2026[\\\\\/]app[\\\\\/](lib|pages|config)/i', $body) === 1;
}

function supplierInput(string $label): array
{
    $suffix = strtolower($label) . '_' . bin2hex(random_bytes(4));

    return [
        'company_name' => 'Security Supplier ' . $label . ' ' . strtoupper($suffix),
        'short_name' => 'Security ' . $label,
        'contact_person' => 'Security Contact ' . $label,
        'email' => 'security_' . $suffix . '@example.local',
        'homepage' => 'https://security-' . strtolower($label) . '.example.local',
        'vat_number' => 'SE' . random_int(100000, 999999) . '-' . random_int(1000, 9999),
        'address_line_1' => 'Security Street ' . random_int(1, 99),
        'address_line_2' => '',
        'city' => 'Karlstad',
        'region' => 'Varmland',
        'postal_code' => '65224',
        'country_code' => 'SE',
        'phone_country_prefix' => '46',
        'phone_area_code' => '54',
        'phone_number' => (string)random_int(1000000, 9999999),
    ];
}

function createPortalUser(PDO $pdo, string $prefix, ?int $supplierId, string $role, string $password): int
{
    $username = $prefix . '_' . bin2hex(random_bytes(4));
    $email = $username . '@example.local';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Unable to create password hash.');
    }

    $columns = [
        'username',
        'email',
        'password_hash',
        'supplier_id',
        'is_active',
        'failed_login_count',
        'locked_until',
    ];
    $values = [
        ':username',
        ':email',
        ':password_hash',
        ':supplier_id',
        '1',
        '0',
        'NULL',
    ];
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':supplier_id' => $supplierId,
    ];

    if (mustChangePasswordColumnExists($pdo)) {
        $columns[] = 'must_change_password';
        $values[] = '0';
    }

    $stmt = $pdo->prepare("
        INSERT INTO portal_users (
            " . implode(",\n            ", $columns) . "
        ) VALUES (
            " . implode(",\n            ", $values) . "
        )
    ");
    $stmt->execute($params);
    $userId = (int)$pdo->lastInsertId();

    $roleStmt = $pdo->prepare("
        INSERT INTO user_roles (user_id, role_id)
        SELECT :user_id, id
        FROM roles
        WHERE name = :role
    ");
    $roleStmt->execute([
        ':user_id' => $userId,
        ':role' => strtoupper($role),
    ]);

    return $userId;
}

function createDraftInvoiceFixture(PDO $pdo, Crypto $crypto, SupplierService $supplierService, int $supplierId, int $actorUserId): int
{
    $profile = $supplierService->getProfile($supplierId);
    if ($profile === null) {
        throw new RuntimeException('Unable to load supplier profile for invoice fixture.');
    }

    $invoiceNumber = 'SEC-' . strtoupper(bin2hex(random_bytes(5)));
    $snapshots = [
        'supplier_name_snapshot' => encryptSnapshotValue($crypto, (string)$profile['company_name'], 'invoices.supplier_name_snapshot'),
        'supplier_short_name_snapshot' => encryptSnapshotValue($crypto, (string)$profile['short_name'], 'invoices.supplier_short_name_snapshot'),
        'contact_person_snapshot' => encryptSnapshotValue($crypto, (string)$profile['contact_person'], 'invoices.contact_person_snapshot'),
        'supplier_email_snapshot' => encryptSnapshotValue($crypto, (string)$profile['email'], 'invoices.supplier_email_snapshot'),
        'supplier_vat_number_snapshot' => encryptSnapshotValue($crypto, (string)$profile['vat_number'], 'invoices.supplier_vat_number_snapshot'),
        'homepage_snapshot' => encryptSnapshotValue($crypto, (string)$profile['homepage'], 'invoices.homepage_snapshot'),
        'address_line_1_snapshot' => encryptSnapshotValue($crypto, (string)$profile['address_line_1'], 'invoices.address_line_1_snapshot'),
        'address_line_2_snapshot' => encryptSnapshotValue($crypto, (string)$profile['address_line_2'], 'invoices.address_line_2_snapshot'),
        'city_snapshot' => encryptSnapshotValue($crypto, (string)$profile['city'], 'invoices.city_snapshot'),
        'region_snapshot' => encryptSnapshotValue($crypto, (string)$profile['region'], 'invoices.region_snapshot'),
        'postal_code_snapshot' => encryptSnapshotValue($crypto, (string)$profile['postal_code'], 'invoices.postal_code_snapshot'),
    ];

    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            supplier_id,
            pricing_rule_id,
            billing_year,
            billing_month,
            billing_period_start,
            billing_period_end,
            sequence_no,
            invoice_number,
            status,
            currency_code,
            issue_date,
            due_date,
            vat_rate,
            subtotal_amount,
            vat_amount,
            total_amount,
            supplier_name_snapshot,
            supplier_short_name_snapshot,
            contact_person_snapshot,
            supplier_email_snapshot,
            supplier_vat_number_snapshot,
            homepage_snapshot,
            address_line_1_snapshot,
            address_line_2_snapshot,
            city_snapshot,
            region_snapshot,
            postal_code_snapshot,
            country_code_snapshot,
            generated_by_user_id
        ) VALUES (
            :supplier_id,
            NULL,
            2099,
            11,
            '2099-11-01',
            '2099-11-30',
            :sequence_no,
            :invoice_number,
            'DRAFT',
            'SEK',
            '2099-11-01',
            '2099-11-30',
            25.00,
            0.00,
            0.00,
            0.00,
            :supplier_name_snapshot,
            :supplier_short_name_snapshot,
            :contact_person_snapshot,
            :supplier_email_snapshot,
            :supplier_vat_number_snapshot,
            :homepage_snapshot,
            :address_line_1_snapshot,
            :address_line_2_snapshot,
            :city_snapshot,
            :region_snapshot,
            :postal_code_snapshot,
            :country_code_snapshot,
            :generated_by_user_id
        )
    ");
    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':sequence_no' => random_int(10000, 99999),
        ':invoice_number' => $invoiceNumber,
        ':supplier_name_snapshot' => $snapshots['supplier_name_snapshot'],
        ':supplier_short_name_snapshot' => $snapshots['supplier_short_name_snapshot'],
        ':contact_person_snapshot' => $snapshots['contact_person_snapshot'],
        ':supplier_email_snapshot' => $snapshots['supplier_email_snapshot'],
        ':supplier_vat_number_snapshot' => $snapshots['supplier_vat_number_snapshot'],
        ':homepage_snapshot' => $snapshots['homepage_snapshot'],
        ':address_line_1_snapshot' => $snapshots['address_line_1_snapshot'],
        ':address_line_2_snapshot' => $snapshots['address_line_2_snapshot'],
        ':city_snapshot' => $snapshots['city_snapshot'],
        ':region_snapshot' => $snapshots['region_snapshot'],
        ':postal_code_snapshot' => $snapshots['postal_code_snapshot'],
        ':country_code_snapshot' => strtoupper((string)$profile['country_code']),
        ':generated_by_user_id' => $actorUserId,
    ]);

    return (int)$pdo->lastInsertId();
}

function encryptSnapshotValue(Crypto $crypto, string $value, string $aad): string
{
    return (string)$crypto->encryptNullable($value, $aad);
}

function fetchSupplierRow(PDO $pdo, int $supplierId): array
{
    $stmt = $pdo->prepare("
        SELECT uuid_supplier, uuid_address_main, uuid_person_contact
        FROM suppliers
        WHERE id_supplier = :supplier_id
        LIMIT 1
    ");
    $stmt->execute([':supplier_id' => $supplierId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Unable to load supplier fixture row.');
    }

    return $row;
}

function fetchUsername(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT username FROM portal_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $username = (string)($stmt->fetchColumn() ?: '');
    if ($username === '') {
        throw new RuntimeException('Unable to load fixture username.');
    }

    return $username;
}

function isUserLocked(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT locked_until FROM portal_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $lockedUntil = (string)($stmt->fetchColumn() ?: '');

    return $lockedUntil !== '' && strtotime($lockedUntil) > time();
}

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute([':table_name' => $tableName]);

    return (int)$stmt->fetchColumn() > 0;
}

function mustChangePasswordColumnExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM portal_users LIKE 'must_change_password'");

    return $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function cleanupSecurityFixture(PDO $pdo, array $fixture): void
{
    foreach (['admin_user_id', 'supplier_a_user_id', 'supplier_b_user_id', 'bruteforce_user_id'] as $userKey) {
        if (!empty($fixture[$userKey])) {
            $pdo->prepare('DELETE FROM verification_token WHERE username = (SELECT username FROM portal_users WHERE id = :id LIMIT 1)')->execute([
                ':id' => $fixture[$userKey],
            ]);
        }
    }

    if (!empty($fixture['supplier_b_invoice_id'])) {
        $pdo->prepare('DELETE FROM invoices WHERE id = :id')->execute([
            ':id' => $fixture['supplier_b_invoice_id'],
        ]);
    }

    foreach (['supplier_a_id', 'supplier_b_id'] as $supplierKey) {
        if (!empty($fixture[$supplierKey])) {
            $pdo->prepare('DELETE FROM invoices WHERE supplier_id = :supplier_id')->execute([
                ':supplier_id' => $fixture[$supplierKey],
            ]);
            $pdo->prepare('DELETE FROM ads WHERE supplier_id = :supplier_id')->execute([
                ':supplier_id' => $fixture[$supplierKey],
            ]);
            $pdo->prepare('DELETE FROM portal_activity_logs WHERE supplier_id = :supplier_id')->execute([
                ':supplier_id' => $fixture[$supplierKey],
            ]);
        }
    }

    foreach (['supplier_a_uuid', 'supplier_b_uuid'] as $uuidKey) {
        if (!empty($fixture[$uuidKey])) {
            $pdo->prepare("
                DELETE FROM phones_entities
                WHERE entity_name = 'SUPPLIER'
                  AND uuid_entity = :uuid_entity
            ")->execute([
                ':uuid_entity' => $fixture[$uuidKey],
            ]);

            $pdo->prepare("
                DELETE FROM addresses_entities
                WHERE entity_name = 'SUPPLIER'
                  AND uuid_entity = :uuid_entity
            ")->execute([
                ':uuid_entity' => $fixture[$uuidKey],
            ]);
        }
    }

    foreach (['admin_user_id', 'supplier_a_user_id', 'supplier_b_user_id', 'bruteforce_user_id'] as $userKey) {
        if (!empty($fixture[$userKey])) {
            $pdo->prepare('DELETE FROM portal_activity_logs WHERE user_id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
            $pdo->prepare('DELETE FROM portal_users WHERE id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
        }
    }

    foreach (['supplier_a_id', 'supplier_b_id'] as $supplierKey) {
        if (!empty($fixture[$supplierKey])) {
            $pdo->prepare('DELETE FROM suppliers WHERE id_supplier = :supplier_id')->execute([
                ':supplier_id' => $fixture[$supplierKey],
            ]);
        }
    }

    foreach (['supplier_a_person_uuid', 'supplier_b_person_uuid'] as $personKey) {
        if (!empty($fixture[$personKey])) {
            $pdo->prepare('DELETE FROM persons WHERE uuid_entity = :uuid_entity')->execute([
                ':uuid_entity' => $fixture[$personKey],
            ]);
        }
    }

    foreach (['supplier_a_address_uuid', 'supplier_b_address_uuid'] as $addressKey) {
        if (!empty($fixture[$addressKey])) {
            $pdo->prepare('DELETE FROM addresses WHERE uuid_address = :uuid_address')->execute([
                ':uuid_address' => $fixture[$addressKey],
            ]);
        }
    }
}

function assertTrueValue(bool $condition, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $condition,
    ];
}
