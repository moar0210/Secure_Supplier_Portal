<?php

declare(strict_types=1);

class Auth
{
    private PDO $pdo;

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
        if (!$this->isLoggedIn()) return null;
        $v = $_SESSION['auth']['supplier_id'] ?? null;
        return $v === null ? null : (int)$v;
    }

    /**
     * Attempt login using username OR email + password.
     * Returns true on success.
     */
    public function attemptLogin(string $identifier, string $password): bool
    {
        $this->ensureSession();

        $identifier = trim($identifier);

        $stmt = $this->pdo->prepare("
            SELECT id, username, email, password_hash, supplier_id, is_active
            FROM portal_users
            WHERE username = :u OR email = :e
            LIMIT 1
        ");
        $stmt->execute([
            ':u' => $identifier,
            ':e' => $identifier,
        ]);
        $user = $stmt->fetch();

        if (!$user) return false;
        if ((int)$user['is_active'] !== 1) return false;

        if (!password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        $rolesStmt = $this->pdo->prepare("
            SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
        ");
        $rolesStmt->execute([':uid' => (int)$user['id']]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$roles) $roles = [];

        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'user_id'      => (int)$user['id'],
            'username'     => (string)$user['username'],
            'email'        => (string)$user['email'],
            'supplier_id'  => $user['supplier_id'] === null ? null : (int)$user['supplier_id'],
            'roles'        => array_map('strtoupper', $roles),
            'logged_in_at' => time(),
        ];

        $up = $this->pdo->prepare("UPDATE portal_users SET last_login_at = NOW() WHERE id = :id");
        $up->execute([':id' => (int)$user['id']]);

        return true;
    }

    public function logout(): void
    {
        $this->ensureSession();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                (bool)$params["secure"],
                (bool)$params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Guard: require login (and optionally a role). If not allowed -> 403.
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: ?page=login");
            exit;
        }
    }

    public function requireRole(string $role): void
    {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header("Location: ?page=403");
            exit;
        }
    }
}
