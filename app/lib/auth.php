<?php

declare(strict_types=1);

class Auth
{
    private const MAX_FAILED_LOGINS = 5;
    private const LOCKOUT_MINUTES = 15;
    private const RESET_TOKEN_MINUTES = 60;

    private PDO $pdo;
    private ?string $lastLoginError = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSession();
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['auth']['user_id']);
    }

    public function userId(): ?int
    {
        return $this->isLoggedIn() ? (int)$_SESSION['auth']['user_id'] : null;
    }

    public function username(): ?string
    {
        return $this->isLoggedIn() ? (string)$_SESSION['auth']['username'] : null;
    }

    public function roles(): array
    {
        return $this->isLoggedIn() ? (array)($_SESSION['auth']['roles'] ?? []) : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtoupper($role), $this->roles(), true);
    }

    public function supplierId(): ?int
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $value = $_SESSION['auth']['supplier_id'] ?? null;

        return $value === null ? null : (int)$value;
    }

    public function attemptLogin(string $identifier, string $password): bool
    {
        $this->ensureSession();
        $this->lastLoginError = null;

        $identifier = trim($identifier);
        $user = $this->findUserByIdentifier($identifier, false, "
            id,
            username,
            email,
            password_hash,
            supplier_id,
            is_active,
            failed_login_count,
            locked_until
        ");

        if (!$user) {
            $this->lastLoginError = 'INVALID';
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            $this->lastLoginError = 'INVALID';
            return false;
        }

        if ($this->isLocked((string)($user['locked_until'] ?? ''))) {
            $this->logAuthEvent('Login blocked by lockout', [
                'user_id' => (int)$user['id'],
            ]);
            $this->lastLoginError = 'LOCKED';
            return false;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            $lockedNow = $this->registerFailedLogin((int)$user['id'], (int)$user['failed_login_count']);
            if ($lockedNow) {
                $this->logAuthEvent('Account locked after failed login attempts', [
                    'user_id' => (int)$user['id'],
                ]);
            }
            $this->lastLoginError = $lockedNow ? 'LOCKED' : 'INVALID';
            return false;
        }

        $this->resetLoginFailures((int)$user['id']);

        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_BCRYPT)) {
            $this->updatePasswordHash((int)$user['id'], password_hash($password, PASSWORD_BCRYPT));
        }

        $rolesStmt = $this->pdo->prepare("
            SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
        ");
        $rolesStmt->execute([':uid' => (int)$user['id']]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'user_id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'email' => (string)$user['email'],
            'supplier_id' => $user['supplier_id'] === null ? null : (int)$user['supplier_id'],
            'roles' => array_map('strtoupper', $roles),
            'logged_in_at' => time(),
        ];

        $this->logAuthEvent('Login succeeded', [
            'user_id' => (int)$user['id'],
            'supplier_id' => $user['supplier_id'] === null ? null : (int)$user['supplier_id'],
        ]);

        return true;
    }

    public function loginErrorMessage(): string
    {
        return $this->lastLoginError === 'LOCKED'
            ? 'Account temporarily locked due to repeated failed login attempts. Please try again later or reset your password.'
            : 'Invalid credentials.';
    }

    public function requestPasswordReset(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $user = $this->findUserByIdentifier($identifier, true, 'username');

        if (!$user) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = $this->hashResetToken($rawToken);
        $expiresAt = (new DateTimeImmutable('+' . self::RESET_TOKEN_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO verification_token (username, expiry_date, token)
            VALUES (:username, :expiry_date, :token)
            ON DUPLICATE KEY UPDATE
                expiry_date = VALUES(expiry_date),
                token = VALUES(token)
        ");
        $stmt->execute([
            ':username' => (string)$user['username'],
            ':expiry_date' => $expiresAt,
            ':token' => $tokenHash,
        ]);

        return [
            'username' => (string)$user['username'],
            'token' => $rawToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function hasValidPasswordResetToken(string $username, string $token): bool
    {
        return $this->getValidResetTokenRow($username, $token) !== null;
    }

    public function resetPasswordWithToken(string $username, string $token, string $newPassword, string $confirmPassword): void
    {
        $tokenRow = $this->getValidResetTokenRow($username, $token);
        if ($tokenRow === null) {
            throw new UserFacingException('The reset link is invalid or has expired.');
        }

        $this->validateNewPassword($newPassword, $confirmPassword);

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->updatePasswordHashForUsername($username, $passwordHash);

        $stmt = $this->pdo->prepare('DELETE FROM verification_token WHERE username = :username');
        $stmt->execute([':username' => $username]);
    }

    public function logout(): void
    {
        $this->ensureSession();

        $_SESSION = [];

        SecurityBootstrap::expireSessionCookie();

        session_destroy();
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: ?page=login');
            exit;
        }
    }

    public function requireRole(string $role): void
    {
        $this->requireLogin();

        if (!$this->hasRole($role)) {
            header('Location: ?page=403');
            exit;
        }
    }

    private function registerFailedLogin(int $userId, int $failedLoginCount): bool
    {
        $newFailedCount = $failedLoginCount + 1;

        if ($newFailedCount >= self::MAX_FAILED_LOGINS) {
            $stmt = $this->pdo->prepare("
                UPDATE portal_users
                SET failed_login_count = 0,
                    locked_until = DATE_ADD(NOW(), INTERVAL " . self::LOCKOUT_MINUTES . " MINUTE),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $userId]);

            return true;
        }

        $stmt = $this->pdo->prepare("
            UPDATE portal_users
            SET failed_login_count = :failed_login_count,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':failed_login_count' => $newFailedCount,
            ':id' => $userId,
        ]);

        return false;
    }

    private function resetLoginFailures(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE portal_users
            SET failed_login_count = 0,
                locked_until = NULL,
                last_login_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);
    }

    private function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE portal_users
            SET password_hash = :password_hash,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id' => $userId,
        ]);
    }

    private function updatePasswordHashForUsername(string $username, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE portal_users
            SET password_hash = :password_hash,
                failed_login_count = 0,
                locked_until = NULL,
                updated_at = NOW()
            WHERE username = :username
              AND is_active = 1
        ");
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':username' => $username,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to update the password for this account.');
        }
    }

    private function isLocked(string $lockedUntil): bool
    {
        if ($lockedUntil === '') {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }

    private function validateNewPassword(string $newPassword, string $confirmPassword): void
    {
        if ($newPassword !== $confirmPassword) {
            throw new UserFacingException('The password confirmation does not match.');
        }

        if (strlen($newPassword) < 10) {
            throw new UserFacingException('The password must be at least 10 characters long.');
        }

        if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            throw new UserFacingException('The password must contain at least one letter and one number.');
        }
    }

    private function findUserByIdentifier(string $identifier, bool $activeOnly, string $selectClause): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // Deterministic lookup: email-shaped identifiers resolve by email, everything else by username.
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'username';

        $sql = "
            SELECT {$selectClause}
            FROM portal_users
            WHERE {$field} = :identifier
        ";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':identifier' => $identifier]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function getValidResetTokenRow(string $username, string $rawToken): ?array
    {
        $username = trim($username);
        $rawToken = trim($rawToken);
        if ($username === '' || $rawToken === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT username, expiry_date, token
            FROM verification_token
            WHERE username = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (empty($row['expiry_date']) || strtotime((string)$row['expiry_date']) < time()) {
            return null;
        }

        return hash_equals((string)$row['token'], $this->hashResetToken($rawToken))
            ? $row
            : null;
    }

    private function hashResetToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function logAuthEvent(string $event, array $context = []): void
    {
        $payload = '';
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $payload = ' ' . $encoded;
            }
        }

        error_log('[Supplier Portal][AUTH] ' . $event . $payload);
    }
}
