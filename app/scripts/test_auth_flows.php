<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$bootstrap = require __DIR__ . '/bootstrap.php';

require_once $bootstrap['root'] . '/app/lib/auth.php';
require_once $bootstrap['root'] . '/app/lib/PortalUserService.php';

$pdo = $bootstrap['pdo'];
$supplierService = $bootstrap['supplierService'];
$sessionDir = $bootstrap['root'] . '/app/storage/cli_sessions';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
    throw new RuntimeException('Unable to create the CLI session directory.');
}

session_save_path($sessionDir);

$portalUserService = new PortalUserService($pdo);
$auth = new Auth($pdo);

$checks = [];
$fixture = [
    'actor_user_id' => null,
    'supplier_id' => null,
    'supplier_uuid' => null,
    'address_uuid' => null,
    'person_uuid' => null,
    'supplier_user_id' => null,
    'admin_user_id' => null,
    'self_admin_user_id' => null,
];

try {
    if (!mustChangePasswordColumnExists($pdo)) {
        throw new RuntimeException('portal_users.must_change_password is required for the auth flow verification.');
    }

    $fixture['actor_user_id'] = createAdminActor($pdo);

    $fixture['supplier_id'] = $supplierService->createSupplier([
        'company_name' => 'Auth Flow Supplier ' . bin2hex(random_bytes(3)),
        'short_name' => 'Auth Flow',
        'contact_person' => 'Auth Example',
        'email' => 'auth_supplier_' . bin2hex(random_bytes(3)) . '@example.local',
        'homepage' => 'https://example.local/auth-flow',
        'vat_number' => 'SE112233-4455',
        'address_line_1' => 'Security Street 1',
        'address_line_2' => '',
        'city' => 'Karlstad',
        'region' => 'Varmland',
        'postal_code' => '65224',
        'country_code' => 'SE',
        'phone_country_prefix' => '46',
        'phone_area_code' => '54',
        'phone_number' => '7654321',
    ], null, (int)$fixture['actor_user_id']);

    $supplierRow = fetchSupplierRow($pdo, (int)$fixture['supplier_id']);
    $fixture['supplier_uuid'] = (string)$supplierRow['uuid_supplier'];
    $fixture['address_uuid'] = (string)$supplierRow['uuid_address_main'];
    $fixture['person_uuid'] = (string)$supplierRow['uuid_person_contact'];

    $fixture['supplier_user_id'] = $portalUserService->createUserForSupplier((int)$fixture['supplier_id'], [
        'username' => 'auth_supplier_user_' . bin2hex(random_bytes(3)),
        'email' => 'auth_supplier_user_' . bin2hex(random_bytes(3)) . '@example.local',
        'password' => 'SupplierInit123',
        'confirm_password' => 'SupplierInit123',
        'is_active' => '1',
    ]);
    assertSameValue(1, fetchMustChangePasswordFlag($pdo, (int)$fixture['supplier_user_id']), 'supplier-created users must rotate their initial password', $checks);

    $supplierUser = $portalUserService->getUser((int)$fixture['supplier_user_id']);
    if (!is_array($supplierUser)) {
        throw new RuntimeException('Unable to load the supplier user fixture.');
    }

    $tokenData = $auth->requestPasswordReset((string)$supplierUser['email']);
    assertTrueValue(is_array($tokenData), 'password reset requests return a token for active users', $checks);
    if (!is_array($tokenData)) {
        throw new RuntimeException('Unable to create a password reset token for the supplier user.');
    }

    $auth->resetPasswordWithToken(
        (string)$tokenData['username'],
        (string)$tokenData['token'],
        'SupplierReset123',
        'SupplierReset123'
    );
    assertSameValue(0, fetchMustChangePasswordFlag($pdo, (int)$fixture['supplier_user_id']), 'successful token reset clears forced password rotation', $checks);
    assertTrueValue(userPasswordMatches($pdo, (int)$fixture['supplier_user_id'], 'SupplierReset123'), 'successful token reset stores the new password hash', $checks);
    assertSameValue(0, countResetTokensForUsername($pdo, (string)$tokenData['username']), 'password reset tokens are single-use and removed after success', $checks);

    $selfDeactivateBlocked = false;
    try {
        $portalUserService->updateUserForSupplier((int)$fixture['supplier_user_id'], (int)$fixture['supplier_id'], [
            'username' => (string)$supplierUser['username'],
            'email' => (string)$supplierUser['email'],
            'password' => '',
            'confirm_password' => '',
            'is_active' => '0',
        ], (int)$fixture['supplier_user_id']);
    } catch (UserFacingException) {
        $selfDeactivateBlocked = true;
    }
    assertTrueValue($selfDeactivateBlocked, 'supplier users cannot deactivate their own active session account', $checks);

    $fixture['admin_user_id'] = $portalUserService->createUserAsAdmin([
        'username' => 'auth_admin_user_' . bin2hex(random_bytes(3)),
        'email' => 'auth_admin_user_' . bin2hex(random_bytes(3)) . '@example.local',
        'password' => 'AdminInit123',
        'confirm_password' => 'AdminInit123',
        'role_name' => 'ADMIN',
        'supplier_id' => '',
        'is_active' => '1',
    ]);
    assertSameValue(1, fetchMustChangePasswordFlag($pdo, (int)$fixture['admin_user_id']), 'admin-created users must rotate their initial password', $checks);

    $adminManagedUser = $portalUserService->getUser((int)$fixture['admin_user_id']);
    if (!is_array($adminManagedUser)) {
        throw new RuntimeException('Unable to load the admin-managed user fixture.');
    }

    $portalUserService->updateUserAsAdmin((int)$fixture['admin_user_id'], [
        'username' => (string)$adminManagedUser['username'],
        'email' => (string)$adminManagedUser['email'],
        'password' => 'AdminReset123',
        'confirm_password' => 'AdminReset123',
        'role_name' => 'ADMIN',
        'supplier_id' => '',
        'is_active' => '1',
    ], (int)$fixture['actor_user_id']);
    assertSameValue(1, fetchMustChangePasswordFlag($pdo, (int)$fixture['admin_user_id']), 'admin-issued password changes force the target user to rotate again', $checks);

    $fixture['self_admin_user_id'] = $portalUserService->createUserAsAdmin([
        'username' => 'auth_self_admin_' . bin2hex(random_bytes(3)),
        'email' => 'auth_self_admin_' . bin2hex(random_bytes(3)) . '@example.local',
        'password' => 'SelfAdminInit123',
        'confirm_password' => 'SelfAdminInit123',
        'role_name' => 'ADMIN',
        'supplier_id' => '',
        'is_active' => '1',
    ]);

    $selfAdmin = $portalUserService->getUser((int)$fixture['self_admin_user_id']);
    if (!is_array($selfAdmin)) {
        throw new RuntimeException('Unable to load the self-admin fixture.');
    }

    $portalUserService->updateUserAsAdmin((int)$fixture['self_admin_user_id'], [
        'username' => (string)$selfAdmin['username'],
        'email' => (string)$selfAdmin['email'],
        'password' => 'SelfAdminNew123',
        'confirm_password' => 'SelfAdminNew123',
        'role_name' => 'ADMIN',
        'supplier_id' => '',
        'is_active' => '1',
    ], (int)$fixture['self_admin_user_id']);
    assertSameValue(0, fetchMustChangePasswordFlag($pdo, (int)$fixture['self_admin_user_id']), 'self-service password updates do not force another rotation cycle', $checks);

    assertTrueValue(
        $auth->attemptLogin((string)$supplierUser['username'], 'SupplierReset123'),
        'supplier fixture can sign in before session invalidation check',
        $checks
    );
    $nextRequestAuth = new Auth($pdo);
    $pdo->prepare('UPDATE portal_users SET is_active = 0, updated_at = NOW() WHERE id = :id')->execute([
        ':id' => $fixture['supplier_user_id'],
    ]);
    assertSameValue(false, $nextRequestAuth->refreshSessionUser(), 'inactive users are signed out on the next request refresh', $checks);
    assertSameValue(false, $nextRequestAuth->isLoggedIn(), 'invalidated session no longer reports an authenticated user', $checks);

    echo "Auth flow verification completed.\n";
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
    cleanupFixture($pdo, $fixture);
}

function createAdminActor(PDO $pdo): int
{
    $username = 'auth_actor_' . bin2hex(random_bytes(4));
    $email = $username . '@example.local';
    $passwordHash = password_hash('AuthActor123', PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Unable to create the auth actor password hash.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO portal_users (
            username,
            email,
            password_hash,
            supplier_id,
            is_active,
            must_change_password,
            failed_login_count,
            locked_until
        ) VALUES (
            :username,
            :email,
            :password_hash,
            NULL,
            1,
            0,
            0,
            NULL
        )
    ");
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO user_roles (user_id, role_id)
        SELECT :user_id, id
        FROM roles
        WHERE name = 'ADMIN'
    ")->execute([':user_id' => $userId]);

    return $userId;
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
        throw new RuntimeException('Unable to load the auth-flow supplier row.');
    }

    return $row;
}

function mustChangePasswordColumnExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM portal_users LIKE 'must_change_password'");

    return $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function fetchMustChangePasswordFlag(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT must_change_password FROM portal_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);

    return (int)($stmt->fetchColumn() ?: 0);
}

function userPasswordMatches(PDO $pdo, int $userId, string $password): bool
{
    $stmt = $pdo->prepare('SELECT password_hash FROM portal_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = (string)($stmt->fetchColumn() ?: '');

    return $hash !== '' && password_verify($password, $hash);
}

function countResetTokensForUsername(PDO $pdo, string $username): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM verification_token WHERE username = :username');
    $stmt->execute([':username' => $username]);

    return (int)$stmt->fetchColumn();
}

function cleanupFixture(PDO $pdo, array $fixture): void
{
    foreach (['supplier_user_id', 'admin_user_id', 'self_admin_user_id', 'actor_user_id'] as $userKey) {
        if (!empty($fixture[$userKey])) {
            $pdo->prepare('DELETE FROM verification_token WHERE username = (SELECT username FROM portal_users WHERE id = :id LIMIT 1)')->execute([
                ':id' => $fixture[$userKey],
            ]);
        }
    }

    foreach (['supplier_user_id', 'admin_user_id', 'self_admin_user_id', 'actor_user_id'] as $userKey) {
        if (!empty($fixture[$userKey])) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
            $pdo->prepare('DELETE FROM portal_users WHERE id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
        }
    }

    if (!empty($fixture['supplier_uuid'])) {
        $pdo->prepare("
            DELETE FROM phones_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
        ")->execute([
            ':uuid_entity' => $fixture['supplier_uuid'],
        ]);

        $pdo->prepare("
            DELETE FROM addresses_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
        ")->execute([
            ':uuid_entity' => $fixture['supplier_uuid'],
        ]);
    }

    if (!empty($fixture['supplier_id'])) {
        $pdo->prepare('DELETE FROM suppliers WHERE id_supplier = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
    }

    if (!empty($fixture['person_uuid'])) {
        $pdo->prepare('DELETE FROM persons WHERE uuid_entity = :uuid_entity')->execute([
            ':uuid_entity' => $fixture['person_uuid'],
        ]);
    }

    if (!empty($fixture['address_uuid'])) {
        $pdo->prepare('DELETE FROM addresses WHERE uuid_address = :uuid_address')->execute([
            ':uuid_address' => $fixture['address_uuid'],
        ]);
    }
}

function assertTrueValue(bool $condition, string $label, array &$checks): void
{
    $checks[] = ['label' => $label, 'pass' => $condition];
}

function assertSameValue(mixed $expected, mixed $actual, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $expected === $actual,
    ];
}
