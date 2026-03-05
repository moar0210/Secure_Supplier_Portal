<?php

declare(strict_types=1);

final class AdsService
{
    public const STATUS_DRAFT    = 'DRAFT';
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================================
       Supplier-facing operations
       ========================================================= */

    public function listForSupplier(int $supplierId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.supplier_id,
                a.category_id,
                c.name AS category_name,
                a.title,
                a.description,
                a.price_text,
                a.valid_from,
                a.valid_to,
                a.is_active,
                a.status,
                a.rejection_reason,
                a.created_at,
                a.updated_at
            FROM ads a
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE a.supplier_id = :sid
            ORDER BY a.created_at DESC, a.id DESC
        ");
        $stmt->execute([':sid' => $supplierId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getForSupplier(int $adId, int $supplierId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.supplier_id,
                a.category_id,
                c.name AS category_name,
                a.title,
                a.description,
                a.price_text,
                a.valid_from,
                a.valid_to,
                a.is_active,
                a.status,
                a.rejection_reason,
                a.created_at,
                a.updated_at
            FROM ads a
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE a.id = :id AND a.supplier_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':id' => $adId, ':sid' => $supplierId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create ad (default: DRAFT). You can submit later using submitForSupplier(),
     * or submit immediately by sending submit_for_approval=1 in $data.
     *
     * Expected $data keys:
     * - title (required)
     * - description (required)
     * - category_id (optional)
     * - price_text (optional)
     * - valid_from (optional YYYY-MM-DD)
     * - valid_to (optional YYYY-MM-DD)
     * - submit_for_approval (optional truthy -> initial status PENDING)
     */
    public function createForSupplier(int $supplierId, array $data, int $actorUserId): int
    {
        $clean = $this->validateAndNormalizeAdData($data);

        $submitNow = $this->shouldSubmit($data);
        $status = $submitNow ? self::STATUS_PENDING : self::STATUS_DRAFT;

        // Only APPROVED ads are allowed to be active
        $isActive = 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ads
                    (supplier_id, category_id, title, description, price_text, valid_from, valid_to, is_active, status, rejection_reason, created_at, updated_at)
                VALUES
                    (:sid, :cid, :t, :d, :p, :vf, :vt, :ia, :st, NULL, NOW(), NOW())
            ");
            $stmt->execute([
                ':sid' => $supplierId,
                ':cid' => $clean['category_id'],
                ':t'   => $clean['title'],
                ':d'   => $clean['description'],
                ':p'   => $clean['price_text'],
                ':vf'  => $clean['valid_from'],
                ':vt'  => $clean['valid_to'],
                ':ia'  => $isActive,
                ':st'  => $status,
            ]);

            $adId = (int)$this->pdo->lastInsertId();
            $this->insertStatusHistory($adId, null, $status, $actorUserId, null);

            $this->pdo->commit();
            return $adId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Submit/resubmit:
     * - DRAFT -> PENDING
     * - REJECTED -> PENDING
     */
    public function submitForSupplier(int $adId, int $supplierId, int $actorUserId): void
    {
        $current = $this->getForSupplier($adId, $supplierId);
        if (!$current) {
            throw new \RuntimeException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];
        if (!in_array($oldStatus, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw new \RuntimeException('Only DRAFT or REJECTED ads can be submitted.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ads
                SET status = :st,
                    rejection_reason = NULL,
                    is_active = 0,
                    updated_at = NOW()
                WHERE id = :id AND supplier_id = :sid
            ");
            $stmt->execute([
                ':st'  => self::STATUS_PENDING,
                ':id'  => $adId,
                ':sid' => $supplierId,
            ]);

            $this->insertStatusHistory($adId, $oldStatus, self::STATUS_PENDING, $actorUserId, null);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update rules (Part 3):
     * - PENDING ads are locked (cannot be edited) to keep workflow clear.
     * - APPROVED edit -> PENDING + is_active=0 (re-review needed).
     * - REJECTED edit -> DRAFT (clears reason). Can submit after.
     * - DRAFT edit -> DRAFT (unless submit_for_approval=1, then goes PENDING).
     */
    public function updateForSupplier(int $adId, int $supplierId, array $data, int $actorUserId): void
    {
        $current = $this->getForSupplier($adId, $supplierId);
        if (!$current) {
            throw new \RuntimeException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];

        // Lock pending ads for clarity + auditability
        if ($oldStatus === self::STATUS_PENDING) {
            throw new \RuntimeException('Pending ads cannot be edited. Wait for review.');
        }

        $clean = $this->validateAndNormalizeAdData($data);
        $submitNow = $this->shouldSubmit($data);

        $newStatus = $oldStatus;
        $newRejectReason = $current['rejection_reason'];
        $forceInactive = false;

        if ($oldStatus === self::STATUS_APPROVED) {
            $newStatus = self::STATUS_PENDING;
            $newRejectReason = null;
            $forceInactive = true;
        } elseif ($oldStatus === self::STATUS_REJECTED) {
            $newStatus = $submitNow ? self::STATUS_PENDING : self::STATUS_DRAFT;
            $newRejectReason = null;
            $forceInactive = true; // safe default: keep inactive until re-approved
        } elseif ($oldStatus === self::STATUS_DRAFT) {
            $newStatus = $submitNow ? self::STATUS_PENDING : self::STATUS_DRAFT;
            if ($newStatus === self::STATUS_PENDING) {
                $forceInactive = true;
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($forceInactive) {
                $stmt = $this->pdo->prepare("
                    UPDATE ads
                    SET
                        category_id = :cid,
                        title = :t,
                        description = :d,
                        price_text = :p,
                        valid_from = :vf,
                        valid_to = :vt,
                        status = :st,
                        rejection_reason = :rr,
                        is_active = 0,
                        updated_at = NOW()
                    WHERE id = :id AND supplier_id = :sid
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE ads
                    SET
                        category_id = :cid,
                        title = :t,
                        description = :d,
                        price_text = :p,
                        valid_from = :vf,
                        valid_to = :vt,
                        status = :st,
                        rejection_reason = :rr,
                        updated_at = NOW()
                    WHERE id = :id AND supplier_id = :sid
                ");
            }

            $stmt->execute([
                ':cid' => $clean['category_id'],
                ':t'   => $clean['title'],
                ':d'   => $clean['description'],
                ':p'   => $clean['price_text'],
                ':vf'  => $clean['valid_from'],
                ':vt'  => $clean['valid_to'],
                ':st'  => $newStatus,
                ':rr'  => $newRejectReason,
                ':id'  => $adId,
                ':sid' => $supplierId,
            ]);

            if ($oldStatus !== $newStatus) {
                $this->insertStatusHistory($adId, $oldStatus, $newStatus, $actorUserId, null);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Supplier can only activate/deactivate APPROVED ads.
     */
    public function toggleActiveForSupplier(int $adId, int $supplierId, bool $active, int $actorUserId): void
    {
        $current = $this->getForSupplier($adId, $supplierId);
        if (!$current) {
            throw new \RuntimeException('Ad not found.');
        }

        if ((string)$current['status'] !== self::STATUS_APPROVED) {
            throw new \RuntimeException('Only APPROVED ads can be activated/deactivated.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE ads
            SET is_active = :ia, updated_at = NOW()
            WHERE id = :id AND supplier_id = :sid
        ");
        $stmt->execute([
            ':ia'  => $active ? 1 : 0,
            ':id'  => $adId,
            ':sid' => $supplierId,
        ]);
    }

    /* =========================================================
       Admin-facing operations
       ========================================================= */

    public function adminQueue(?string $status = self::STATUS_PENDING): array
    {
        $status = $status === null ? null : strtoupper(trim($status));

        if ($status !== null && !in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_DRAFT], true)) {
            throw new \InvalidArgumentException('Invalid status filter.');
        }

        if ($status === null) {
            $stmt = $this->pdo->query("
                SELECT
                    a.id,
                    a.supplier_id,
                    s.supplier_name,
                    a.category_id,
                    c.name AS category_name,
                    a.title,
                    a.is_active,
                    a.status,
                    a.created_at,
                    a.updated_at
                FROM ads a
                LEFT JOIN suppliers s ON s.id_supplier = a.supplier_id
                LEFT JOIN ad_categories c ON c.id = a.category_id
                ORDER BY a.updated_at DESC, a.id DESC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.supplier_id,
                s.supplier_name,
                a.category_id,
                c.name AS category_name,
                a.title,
                a.is_active,
                a.status,
                a.created_at,
                a.updated_at
            FROM ads a
            LEFT JOIN suppliers s ON s.id_supplier = a.supplier_id
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE a.status = :st
            ORDER BY a.updated_at DESC, a.id DESC
        ");
        $stmt->execute([':st' => $status]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function adminGet(int $adId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.*,
                s.supplier_name,
                c.name AS category_name
            FROM ads a
            LEFT JOIN suppliers s ON s.id_supplier = a.supplier_id
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $adId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Admin approves/rejects ONLY PENDING.
     * - approve => APPROVED + is_active=1 + rejection_reason=NULL
     * - reject  => REJECTED + is_active=0 + rejection_reason=required
     */
    public function adminDecision(int $adId, bool $approve, ?string $reason, int $actorUserId): void
    {
        $current = $this->adminGet($adId);
        if (!$current) {
            throw new \RuntimeException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];
        if ($oldStatus !== self::STATUS_PENDING) {
            throw new \RuntimeException('Only PENDING ads can be approved/rejected.');
        }

        $newStatus  = $approve ? self::STATUS_APPROVED : self::STATUS_REJECTED;
        $reasonNorm = $approve ? null : $this->normalizeReason($reason);

        if (!$approve && $reasonNorm === null) {
            throw new \InvalidArgumentException('Rejection reason is required.');
        }

        $isActive = $approve ? 1 : 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ads
                SET status = :st,
                    rejection_reason = :rr,
                    is_active = :ia,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':st' => $newStatus,
                ':rr' => $reasonNorm,   // NULL on approve
                ':ia' => $isActive,
                ':id' => $adId,
            ]);

            $this->insertStatusHistory($adId, $oldStatus, $newStatus, $actorUserId, $reasonNorm);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function adminHistory(int $adId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                h.old_status,
                h.new_status,
                h.reason,
                h.changed_at,
                u.username
            FROM ad_status_history h
            LEFT JOIN portal_users u ON u.id = h.changed_by_user_id
            WHERE h.ad_id = :aid
            ORDER BY h.changed_at DESC, h.id DESC
        ");
        $stmt->execute([':aid' => $adId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
       Categories
       ========================================================= */

    public function listCategories(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name
            FROM ad_categories
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function createCategory(string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Category name is invalid.');
        }

        $stmt = $this->pdo->prepare("INSERT INTO ad_categories (name) VALUES (:n)");
        $stmt->execute([':n' => $name]);

        return (int)$this->pdo->lastInsertId();
    }

    public function renameCategory(int $categoryId, string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Category name is invalid.');
        }

        $stmt = $this->pdo->prepare("UPDATE ad_categories SET name = :n WHERE id = :id");
        $stmt->execute([':n' => $name, ':id' => $categoryId]);
    }

    public function deleteCategory(int $categoryId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM ad_categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
    }

    /* =========================================================
       Internals
       ========================================================= */

    private function shouldSubmit(array $data): bool
    {
        // Supports multiple UI styles:
        // - <button name="action" value="submit">
        // - hidden input submit_for_approval=1
        $action = strtolower(trim((string)($data['action'] ?? '')));
        if ($action === 'submit') {
            return true;
        }
        return !empty($data['submit_for_approval']);
    }

    private function validateAndNormalizeAdData(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $desc  = trim((string)($data['description'] ?? ''));
        $price = isset($data['price_text']) ? trim((string)$data['price_text']) : null;

        $categoryId = $data['category_id'] ?? null;
        if ($categoryId === '' || $categoryId === 0 || $categoryId === '0') {
            $categoryId = null;
        }
        if ($categoryId !== null) {
            if (!is_int($categoryId)) {
                if (is_string($categoryId) && ctype_digit($categoryId)) {
                    $categoryId = (int)$categoryId;
                } else {
                    throw new \InvalidArgumentException('Category id is invalid.');
                }
            }
            if ($categoryId < 1) {
                throw new \InvalidArgumentException('Category id is invalid.');
            }
        }

        if ($title === '' || mb_strlen($title) > 200) {
            throw new \InvalidArgumentException('Title is required (max 200 chars).');
        }
        if ($desc === '' || mb_strlen($desc) > 5000) {
            throw new \InvalidArgumentException('Description is required (max 5000 chars).');
        }
        if ($price !== null && $price !== '' && mb_strlen($price) > 200) {
            throw new \InvalidArgumentException('Price text too long (max 200 chars).');
        }
        if ($price === '') {
            $price = null;
        }

        // Avoid FK exceptions: verify category exists when provided
        $this->assertCategoryExists($categoryId);

        $validFrom = $this->normalizeDate($data['valid_from'] ?? null);
        $validTo   = $this->normalizeDate($data['valid_to'] ?? null);

        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            throw new \InvalidArgumentException('valid_to must be on or after valid_from.');
        }

        return [
            'title' => $title,
            'description' => $desc,
            'price_text' => $price,
            'category_id' => $categoryId,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ];
    }

    private function assertCategoryExists(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM ad_categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException('Selected category does not exist.');
        }
    }

    private function normalizeDate(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string)$v);
        if ($s === '') {
            return null;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $s);
        if (!$dt || $dt->format('Y-m-d') !== $s) {
            throw new \InvalidArgumentException('Date must be a valid YYYY-MM-DD.');
        }

        return $s;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }
        $r = trim($reason);
        if ($r === '') {
            return null;
        }
        if (mb_strlen($r) > 500) {
            throw new \InvalidArgumentException('Reason too long (max 500 chars).');
        }
        return $r;
    }

    private function insertStatusHistory(int $adId, ?string $old, string $new, int $actorUserId, ?string $reason): void
    {
        if ($actorUserId < 1) {
            throw new \InvalidArgumentException('actorUserId is invalid.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ad_status_history (ad_id, old_status, new_status, reason, changed_by_user_id, changed_at)
            VALUES (:aid, :old, :new, :r, :uid, NOW())
        ");
        $stmt->execute([
            ':aid' => $adId,
            ':old' => $old,
            ':new' => $new,
            ':r'   => $reason,
            ':uid' => $actorUserId,
        ]);
    }
}