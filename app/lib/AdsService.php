<?php

declare(strict_types=1);

final class AdsService
{
    public const STATUS_DRAFT    = 'DRAFT';
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    private const PRICE_MODELS = [
        'FIXED_DISCOUNT' => 'Fixed discount',
        'LAYER_DISCOUNT' => 'Layer discount',
        'FREE_GIFT' => 'Free gift',
        'PRICE_LIST' => 'Price list',
        'CUSTOM' => 'Custom offer',
    ];

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
                a.price_model_type,
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
                a.price_model_type,
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
                    (supplier_id, category_id, title, description, price_model_type, price_text, valid_from, valid_to, is_active, status, rejection_reason, created_at, updated_at)
                VALUES
                    (:sid, :cid, :t, :d, :pm, :p, :vf, :vt, :ia, :st, NULL, NOW(), NOW())
            ");
            $stmt->execute([
                ':sid' => $supplierId,
                ':cid' => $clean['category_id'],
                ':t'   => $clean['title'],
                ':d'   => $clean['description'],
                ':pm'  => $clean['price_model_type'],
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
            throw new UserFacingException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];
        if (!in_array($oldStatus, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw new UserFacingException('Only DRAFT or REJECTED ads can be submitted.');
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
            throw new UserFacingException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];

        // Lock pending ads for clarity + auditability
        if ($oldStatus === self::STATUS_PENDING) {
            throw new UserFacingException('Pending ads cannot be edited. Wait for review.');
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
                        price_model_type = :pm,
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
                        price_model_type = :pm,
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
                ':pm'  => $clean['price_model_type'],
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

            if ($forceInactive && !empty($current['is_active'])) {
                $this->insertActivationHistory($adId, true, false, $actorUserId, 'Ad forced inactive during edit workflow.');
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
    public function toggleActiveForSupplier(int $adId, int $supplierId, bool $active, ?int $actorUserId = null): void
    {
        $current = $this->getForSupplier($adId, $supplierId);
        if (!$current) {
            throw new UserFacingException('Ad not found.');
        }

        if ((string)$current['status'] !== self::STATUS_APPROVED) {
            throw new UserFacingException('Only APPROVED ads can be activated/deactivated.');
        }

        $oldActive = !empty($current['is_active']);
        if ($oldActive === $active) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
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

            $this->insertActivationHistory($adId, $oldActive, $active, $actorUserId, $active ? 'Ad activated by supplier.' : 'Ad deactivated by supplier.');

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteForSupplier(int $adId, int $supplierId): void
    {
        $current = $this->getForSupplier($adId, $supplierId);
        if (!$current) {
            throw new UserFacingException('Ad not found.');
        }

        $status = strtoupper((string)$current['status']);

        if ($status === self::STATUS_PENDING) {
            throw new UserFacingException('Pending ads cannot be deleted while they are under review.');
        }

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw new UserFacingException('Only DRAFT or REJECTED ads can be deleted.');
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM ads
            WHERE id = :id
              AND supplier_id = :sid
        ");
        $stmt->execute([
            ':id' => $adId,
            ':sid' => $supplierId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to delete the advertisement.');
        }
    }

    /* =========================================================
       Admin-facing operations
       ========================================================= */

    public function adminQueue(?string $status = self::STATUS_PENDING): array
    {
        $status = $status === null ? null : strtoupper(trim($status));

        if ($status !== null && !in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_DRAFT], true)) {
            throw new UserFacingException('Invalid status filter.');
        }

        if ($status === null) {
            $stmt = $this->pdo->prepare("
                SELECT
                    a.id,
                    a.supplier_id,
                    s.supplier_name,
                    a.category_id,
                    c.name AS category_name,
                    a.title,
                    a.price_model_type,
                    a.is_active,
                    a.status,
                    a.created_at,
                    a.updated_at
                FROM ads a
                LEFT JOIN suppliers s ON s.id_supplier = a.supplier_id
                LEFT JOIN ad_categories c ON c.id = a.category_id
                ORDER BY a.updated_at DESC, a.id DESC
            ");
            $stmt->execute();
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
                a.price_model_type,
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
     * - approve => APPROVED + is_active=0 + rejection_reason=NULL
     * - reject  => REJECTED + is_active=0 + rejection_reason=required
     */
    public function adminDecision(int $adId, bool $approve, ?string $reason, int $actorUserId): void
    {
        $current = $this->adminGet($adId);
        if (!$current) {
            throw new UserFacingException('Ad not found.');
        }

        $oldStatus = (string)$current['status'];
        if ($oldStatus !== self::STATUS_PENDING) {
            throw new UserFacingException('Only PENDING ads can be approved/rejected.');
        }

        $newStatus  = $approve ? self::STATUS_APPROVED : self::STATUS_REJECTED;
        $reasonNorm = $approve ? null : $this->normalizeReason($reason);

        if (!$approve && $reasonNorm === null) {
            throw new UserFacingException('Rejection reason is required.');
        }

        // Approval and activation are separate steps: supplier decides when an approved ad goes live.
        $isActive = 0;

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
        $stmt = $this->pdo->prepare("
            SELECT id, name
            FROM ad_categories
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function priceModelOptions(): array
    {
        return self::PRICE_MODELS;
    }

    public static function priceModelLabel(?string $value): string
    {
        $normalized = strtoupper(trim((string)$value));

        return self::PRICE_MODELS[$normalized] ?? '';
    }

    public function createCategory(string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new UserFacingException('Category name is invalid.');
        }

        if ($this->categoryNameExists($name)) {
            throw new UserFacingException('A category with that name already exists.');
        }

        $stmt = $this->pdo->prepare("INSERT INTO ad_categories (name) VALUES (:n)");
        $stmt->execute([':n' => $name]);

        return (int)$this->pdo->lastInsertId();
    }

    public function renameCategory(int $categoryId, string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new UserFacingException('Category name is invalid.');
        }

        $this->requireCategory($categoryId);

        if ($this->categoryNameExists($name, $categoryId)) {
            throw new UserFacingException('A category with that name already exists.');
        }

        $stmt = $this->pdo->prepare("UPDATE ad_categories SET name = :n WHERE id = :id");
        $stmt->execute([':n' => $name, ':id' => $categoryId]);
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->requireCategory($categoryId);

        if ($this->countAdsForCategory($categoryId) > 0) {
            throw new UserFacingException('Cannot delete a category that is still used by advertisements.');
        }

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
        $priceModelType = strtoupper(trim((string)($data['price_model_type'] ?? '')));
        $price = isset($data['price_text']) ? trim((string)$data['price_text']) : null;

        if ($priceModelType === '') {
            $priceModelType = null;
        } elseif (!isset(self::PRICE_MODELS[$priceModelType])) {
            throw new UserFacingException('Price model is invalid.');
        }

        $categoryId = $data['category_id'] ?? null;
        if ($categoryId === '' || $categoryId === 0 || $categoryId === '0') {
            $categoryId = null;
        }
        if ($categoryId !== null) {
            if (!is_int($categoryId)) {
                if (is_string($categoryId) && ctype_digit($categoryId)) {
                    $categoryId = (int)$categoryId;
                } else {
                    throw new UserFacingException('Category id is invalid.');
                }
            }
            if ($categoryId < 1) {
                throw new UserFacingException('Category id is invalid.');
            }
        }

        if ($title === '' || mb_strlen($title) > 200) {
            throw new UserFacingException('Title is required (max 200 chars).');
        }
        if ($desc === '' || mb_strlen($desc) > 5000) {
            throw new UserFacingException('Description is required (max 5000 chars).');
        }
        if ($price !== null && $price !== '' && mb_strlen($price) > 200) {
            throw new UserFacingException('Price text too long (max 200 chars).');
        }
        if ($price === '') {
            $price = null;
        }

        // Avoid FK exceptions: verify category exists when provided
        $this->assertCategoryExists($categoryId);

        $validFrom = $this->normalizeDate($data['valid_from'] ?? null);
        $validTo   = $this->normalizeDate($data['valid_to'] ?? null);

        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            throw new UserFacingException('valid_to must be on or after valid_from.');
        }

        return [
            'title' => $title,
            'description' => $desc,
            'price_model_type' => $priceModelType,
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

        $this->requireCategory($categoryId, 'Selected category does not exist.');
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
            throw new UserFacingException('Date must be a valid YYYY-MM-DD.');
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
            throw new UserFacingException('Reason too long (max 500 chars).');
        }
        return $r;
    }

    private function insertStatusHistory(int $adId, ?string $old, string $new, int $actorUserId, ?string $reason): void
    {
        if ($actorUserId < 1) {
            throw new \RuntimeException('Invalid actor user id.');
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

    private function insertActivationHistory(int $adId, bool $oldActive, bool $newActive, ?int $actorUserId, ?string $note): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_activation_history (ad_id, old_is_active, new_is_active, changed_by_user_id, note, changed_at)
            VALUES (:ad_id, :old_is_active, :new_is_active, :changed_by_user_id, :note, NOW())
        ");
        $stmt->execute([
            ':ad_id' => $adId,
            ':old_is_active' => $oldActive ? 1 : 0,
            ':new_is_active' => $newActive ? 1 : 0,
            ':changed_by_user_id' => $actorUserId,
            ':note' => $note,
        ]);
    }

    private function requireCategory(int $categoryId, string $message = 'Category not found.'): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM ad_categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);

        if (!$stmt->fetchColumn()) {
            throw new UserFacingException($message);
        }
    }

    private function categoryNameExists(string $name, ?int $excludeCategoryId = null): bool
    {
        if ($excludeCategoryId === null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM ad_categories WHERE name = :name LIMIT 1");
            $stmt->execute([':name' => $name]);

            return (bool)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ad_categories
            WHERE name = :name
              AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            ':name' => $name,
            ':id' => $excludeCategoryId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function countAdsForCategory(int $categoryId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ads WHERE category_id = :id");
        $stmt->execute([':id' => $categoryId]);

        return (int)$stmt->fetchColumn();
    }
}
