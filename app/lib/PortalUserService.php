<?php

declare(strict_types=1);

final class PortalUserService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listUsers(array $filters = []): array
    {
        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.username LIKE :search OR u.email LIKE :search OR s.supplier_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $role = strtoupper(trim((string)($filters['role'] ?? '')));
        if ($role !== '' && $role !== 'ALL') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM user_roles ur2
                JOIN roles r2 ON r2.id = ur2.role_id
                WHERE ur2.user_id = u.id
                  AND r2.name = :role
            )';
            $params[':role'] = $role;
        }

        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status === 'ACTIVE') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'INACTIVE') {
            $where[] = 'u.is_active = 0';
        }

        $supplierId = trim((string)($filters['supplier_id'] ?? ''));
        if ($supplierId !== '' && ctype_digit($supplierId)) {
            $where[] = 'u.supplier_id = :supplier_id';
            $params[':supplier_id'] = (int)$supplierId;
        }

        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.supplier_id,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                s.supplier_name,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS role_names
            FROM portal_users u
            LEFT JOIN suppliers s ON s.id_supplier = u.supplier_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= '
            GROUP BY
                u.id,
                u.username,
                u.email,
                u.supplier_id,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                s.supplier_name
            ORDER BY u.created_at DESC, u.id DESC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'hydrateUserRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function listUsersForSupplier(int $supplierId): array
    {
        return $this->listUsers([
            'supplier_id' => (string)$supplierId,
        ]);
    }

    public function getUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                u.id,
                u.username,
                u.email,
                u.supplier_id,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                s.supplier_name,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS role_names
            FROM portal_users u
            LEFT JOIN suppliers s ON s.id_supplier = u.supplier_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE u.id = :id
            GROUP BY
                u.id,
                u.username,
                u.email,
                u.supplier_id,
                u.is_active,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                s.supplier_name
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateUserRow($row) : null;
    }

    public function createUserAsAdmin(array $input): int
    {
        $clean = $this->normalizeInput($input, true, true, null);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO portal_users (
                    username,
                    email,
                    password_hash,
                    supplier_id,
                    is_active,
                    failed_login_count,
                    locked_until
                ) VALUES (
                    :username,
                    :email,
                    :password_hash,
                    :supplier_id,
                    :is_active,
                    0,
                    NULL
                )
            ");
            $stmt->execute([
                ':username' => $clean['username'],
                ':email' => $clean['email'],
                ':password_hash' => $clean['password_hash'],
                ':supplier_id' => $clean['supplier_id'],
                ':is_active' => $clean['is_active'],
            ]);

            $userId = (int)$this->pdo->lastInsertId();
            $this->replaceRoles($userId, [$clean['role_name']]);

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateUserAsAdmin(int $userId, array $input, ?int $actorUserId = null): void
    {
        $existing = $this->getUser($userId);
        if ($existing === null) {
            throw new UserFacingException('User not found.');
        }

        $clean = $this->normalizeInput($input, true, false, null, $userId);

        if ($actorUserId !== null && $actorUserId === $userId) {
            $existingRoles = (array)($existing['roles'] ?? []);
            $wasAdmin = in_array('ADMIN', array_map('strtoupper', $existingRoles), true);
            if ($wasAdmin && $clean['role_name'] !== 'ADMIN') {
                throw new UserFacingException('You cannot remove your own ADMIN role. Ask another admin to do it.');
            }
            if ($clean['is_active'] !== 1) {
                throw new UserFacingException('You cannot deactivate your own account.');
            }
        }

        if ($clean['role_name'] !== 'ADMIN') {
            $this->assertNotLastActiveAdmin($userId);
        }
        if ($clean['is_active'] !== 1) {
            $this->assertNotLastActiveAdmin($userId);
        }

        $this->pdo->beginTransaction();

        try {
            $sql = "
                UPDATE portal_users
                SET username = :username,
                    email = :email,
                    supplier_id = :supplier_id,
                    is_active = :is_active,
                    updated_at = NOW()
            ";

            $params = [
                ':username' => $clean['username'],
                ':email' => $clean['email'],
                ':supplier_id' => $clean['supplier_id'],
                ':is_active' => $clean['is_active'],
                ':id' => $userId,
            ];

            if ($clean['password_hash'] !== null) {
                $sql .= ',
                    password_hash = :password_hash,
                    failed_login_count = 0,
                    locked_until = NULL
                ';
                $params[':password_hash'] = $clean['password_hash'];
            }

            $sql .= ' WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->replaceRoles($userId, [$clean['role_name']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function createUserForSupplier(int $supplierId, array $input): int
    {
        $clean = $this->normalizeInput($input, false, true, $supplierId);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO portal_users (
                    username,
                    email,
                    password_hash,
                    supplier_id,
                    is_active,
                    failed_login_count,
                    locked_until
                ) VALUES (
                    :username,
                    :email,
                    :password_hash,
                    :supplier_id,
                    :is_active,
                    0,
                    NULL
                )
            ");
            $stmt->execute([
                ':username' => $clean['username'],
                ':email' => $clean['email'],
                ':password_hash' => $clean['password_hash'],
                ':supplier_id' => $clean['supplier_id'],
                ':is_active' => $clean['is_active'],
            ]);

            $userId = (int)$this->pdo->lastInsertId();
            $this->replaceRoles($userId, ['SUPPLIER']);

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function updateUserForSupplier(int $userId, int $supplierId, array $input): void
    {
        $existing = $this->getUser($userId);
        if ($existing === null || (int)($existing['supplier_id'] ?? 0) !== $supplierId) {
            throw new UserFacingException('User not found.');
        }

        $clean = $this->normalizeInput($input, false, false, $supplierId, $userId);

        $this->pdo->beginTransaction();

        try {
            $sql = "
                UPDATE portal_users
                SET username = :username,
                    email = :email,
                    supplier_id = :supplier_id,
                    is_active = :is_active,
                    updated_at = NOW()
            ";

            $params = [
                ':username' => $clean['username'],
                ':email' => $clean['email'],
                ':supplier_id' => $clean['supplier_id'],
                ':is_active' => $clean['is_active'],
                ':id' => $userId,
            ];

            if ($clean['password_hash'] !== null) {
                $sql .= ',
                    password_hash = :password_hash,
                    failed_login_count = 0,
                    locked_until = NULL
                ';
                $params[':password_hash'] = $clean['password_hash'];
            }

            $sql .= ' WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->replaceRoles($userId, ['SUPPLIER']);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function normalizeInput(
        array $input,
        bool $adminMode,
        bool $isCreate,
        ?int $supplierScope,
        ?int $excludeUserId = null
    ): array {
        $username = trim((string)($input['username'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');
        $roleName = strtoupper(trim((string)($input['role_name'] ?? ($adminMode ? 'SUPPLIER' : 'SUPPLIER'))));
        $isActive = ((string)($input['is_active'] ?? '1') === '1') ? 1 : 0;

        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            throw new UserFacingException('Username must be 3-50 characters and use only letters, numbers, dot, underscore, or hyphen.');
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UserFacingException('A valid email address is required.');
        }

        if (!$adminMode) {
            $roleName = 'SUPPLIER';
        }

        if (!in_array($roleName, ['ADMIN', 'SUPPLIER'], true)) {
            throw new UserFacingException('Invalid role selection.');
        }

        $supplierId = $supplierScope;
        if ($adminMode) {
            $supplierRaw = trim((string)($input['supplier_id'] ?? ''));
            if ($roleName === 'SUPPLIER') {
                if ($supplierRaw === '' || !ctype_digit($supplierRaw)) {
                    throw new UserFacingException('Supplier users must be linked to a supplier.');
                }

                $supplierId = (int)$supplierRaw;
            } else {
                $supplierId = null;
            }
        } elseif ($supplierScope === null || $supplierScope < 1) {
            throw new RuntimeException('Supplier scope is required.');
        }

        $passwordHash = null;
        if ($isCreate || $password !== '' || $confirmPassword !== '') {
            $this->validatePassword($password, $confirmPassword);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash) || $hash === '') {
                throw new RuntimeException('Unable to securely hash the password.');
            }

            $passwordHash = $hash;
        }

        $this->assertUniqueUsername($username, $excludeUserId);
        $this->assertUniqueEmail($email, $excludeUserId);

        if ($supplierId !== null) {
            $this->assertSupplierExists($supplierId);
        }

        return [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_name' => $roleName,
            'supplier_id' => $supplierId,
            'is_active' => $isActive,
        ];
    }

    private function replaceRoles(int $userId, array $roleNames): void
    {
        $deleteStmt = $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $deleteStmt->execute([':user_id' => $userId]);

        $insertStmt = $this->pdo->prepare("
            INSERT INTO user_roles (user_id, role_id)
            SELECT :user_id, id
            FROM roles
            WHERE name = :role_name
        ");

        foreach ($roleNames as $roleName) {
            $insertStmt->execute([
                ':user_id' => $userId,
                ':role_name' => strtoupper($roleName),
            ]);

            if ($insertStmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to assign the selected role.');
            }
        }
    }

    private function assertUniqueUsername(string $username, ?int $excludeUserId = null): void
    {
        if ($excludeUserId === null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM portal_users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM portal_users WHERE username = :username AND id <> :id LIMIT 1');
            $stmt->execute([
                ':username' => $username,
                ':id' => $excludeUserId,
            ]);
        }

        if ($stmt->fetchColumn()) {
            throw new UserFacingException('That username is already in use.');
        }
    }

    private function assertUniqueEmail(string $email, ?int $excludeUserId = null): void
    {
        if ($excludeUserId === null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM portal_users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM portal_users WHERE email = :email AND id <> :id LIMIT 1');
            $stmt->execute([
                ':email' => $email,
                ':id' => $excludeUserId,
            ]);
        }

        if ($stmt->fetchColumn()) {
            throw new UserFacingException('That email address is already in use.');
        }
    }

    private function assertNotLastActiveAdmin(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM portal_users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'ADMIN'
              AND u.is_active = 1
              AND u.id <> :id
        ");
        $stmt->execute([':id' => $userId]);
        $remaining = (int)$stmt->fetchColumn();
        if ($remaining < 1) {
            throw new UserFacingException('At least one active ADMIN account must remain at all times.');
        }
    }

    private function assertSupplierExists(int $supplierId): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM suppliers WHERE id_supplier = :id LIMIT 1');
        $stmt->execute([':id' => $supplierId]);

        if (!$stmt->fetchColumn()) {
            throw new UserFacingException('Selected supplier was not found.');
        }
    }

    private function validatePassword(string $password, string $confirmPassword): void
    {
        if ($password !== $confirmPassword) {
            throw new UserFacingException('The password confirmation does not match.');
        }

        if (strlen($password) < 10) {
            throw new UserFacingException('The password must be at least 10 characters long.');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new UserFacingException('The password must contain at least one letter and one number.');
        }
    }

    private function hydrateUserRow(array $row): array
    {
        $roleNames = trim((string)($row['role_names'] ?? ''));
        $row['roles'] = $roleNames === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $roleNames))));
        $row['role_names'] = $roleNames;

        return $row;
    }
}
