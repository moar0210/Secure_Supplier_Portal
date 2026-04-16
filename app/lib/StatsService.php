<?php

declare(strict_types=1);

final class StatsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listCategories(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM ad_categories ORDER BY name ASC');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPublicAds(array $filters = []): array
    {
        $where = [
            "a.status = 'APPROVED'",
            'a.is_active = 1',
            's.is_inactive = 0',
            '(a.valid_from IS NULL OR a.valid_from <= CURDATE())',
            '(a.valid_to IS NULL OR a.valid_to >= CURDATE())',
        ];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.title LIKE :search OR a.description LIKE :search OR s.supplier_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $categoryId = trim((string)($filters['category_id'] ?? ''));
        if ($categoryId !== '' && ctype_digit($categoryId)) {
            $where[] = 'a.category_id = :category_id';
            $params[':category_id'] = (int)$categoryId;
        }

        $sql = "
            SELECT
                a.id,
                a.supplier_id,
                a.category_id,
                a.title,
                a.description,
                a.price_model_type,
                a.price_text,
                a.valid_from,
                a.valid_to,
                a.updated_at,
                s.supplier_name,
                s.homepage,
                c.name AS category_name
            FROM ads a
            JOIN suppliers s ON s.id_supplier = a.supplier_id
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.updated_at DESC, a.id DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPublicAd(int $adId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.supplier_id,
                a.category_id,
                a.title,
                a.description,
                a.price_model_type,
                a.price_text,
                a.valid_from,
                a.valid_to,
                a.updated_at,
                s.supplier_name,
                s.homepage,
                c.name AS category_name
            FROM ads a
            JOIN suppliers s ON s.id_supplier = a.supplier_id
            LEFT JOIN ad_categories c ON c.id = a.category_id
            WHERE a.id = :id
              AND a.status = 'APPROVED'
              AND a.is_active = 1
              AND s.is_inactive = 0
              AND (a.valid_from IS NULL OR a.valid_from <= CURDATE())
              AND (a.valid_to IS NULL OR a.valid_to >= CURDATE())
            LIMIT 1
        ");
        $stmt->execute([':id' => $adId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function recordImpressions(array $adIds): void
    {
        $adIds = array_values(array_unique(array_filter(array_map('intval', $adIds), static fn(int $value): bool => $value > 0)));
        if ($adIds === []) {
            return;
        }

        $ads = $this->loadAdsForStats($adIds);
        if ($ads === []) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ad_daily_stats (
                ad_id,
                supplier_id,
                stat_date,
                impressions,
                clicks
            ) VALUES (
                :ad_id,
                :supplier_id,
                CURDATE(),
                1,
                0
            )
            ON DUPLICATE KEY UPDATE
                impressions = impressions + 1,
                supplier_id = VALUES(supplier_id),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($ads as $ad) {
            $stmt->execute([
                ':ad_id' => (int)$ad['id'],
                ':supplier_id' => (int)$ad['supplier_id'],
            ]);
        }
    }

    public function recordClick(int $adId): void
    {
        $ads = $this->loadAdsForStats([$adId]);
        $ad = $ads[0] ?? null;
        if ($ad === null) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ad_daily_stats (
                ad_id,
                supplier_id,
                stat_date,
                impressions,
                clicks
            ) VALUES (
                :ad_id,
                :supplier_id,
                CURDATE(),
                0,
                1
            )
            ON DUPLICATE KEY UPDATE
                clicks = clicks + 1,
                supplier_id = VALUES(supplier_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':ad_id' => (int)$ad['id'],
            ':supplier_id' => (int)$ad['supplier_id'],
        ]);
    }

    public function supplierDashboard(int $supplierId, array $filters = []): array
    {
        [$dateFrom, $dateTo, $granularity] = $this->normalizeDateRange($filters);

        $summaryStmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(clicks), 0) AS clicks
            FROM ad_daily_stats
            WHERE supplier_id = :supplier_id
              AND stat_date BETWEEN :date_from AND :date_to
        ");
        $summaryStmt->execute([
            ':supplier_id' => $supplierId,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'impressions' => 0,
            'clicks' => 0,
        ];

        $perAdStmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.title,
                a.status,
                a.is_active,
                COALESCE(SUM(s.impressions), 0) AS impressions,
                COALESCE(SUM(s.clicks), 0) AS clicks
            FROM ads a
            LEFT JOIN ad_daily_stats s
              ON s.ad_id = a.id
             AND s.stat_date BETWEEN :date_from AND :date_to
            WHERE a.supplier_id = :supplier_id
            GROUP BY a.id, a.title, a.status, a.is_active
            ORDER BY impressions DESC, clicks DESC, a.updated_at DESC, a.id DESC
        ");
        $perAdStmt->execute([
            ':supplier_id' => $supplierId,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        $ads = $perAdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $series = $this->loadVisibilitySeries(
            'WHERE supplier_id = :supplier_id AND stat_date BETWEEN :date_from AND :date_to',
            [
                ':supplier_id' => $supplierId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
            ],
            $granularity
        );

        return [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'granularity' => $granularity,
            ],
            'summary' => $this->decorateSummary($summary),
            'ads' => array_map([$this, 'decorateSummary'], $ads),
            'series' => $series,
        ];
    }

    public function adminReport(array $filters = []): array
    {
        [$dateFrom, $dateTo, $granularity] = $this->normalizeDateRange($filters);

        $suppliers = $this->fetchSingleRow("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_inactive = 0 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_inactive = 1 THEN 1 ELSE 0 END) AS inactive
            FROM suppliers
        ");

        $users = $this->fetchSingleRow("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive
            FROM portal_users
        ");

        $ads = $this->fetchSingleRow("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'APPROVED' AND is_active = 1 THEN 1 ELSE 0 END) AS live
            FROM ads
        ");

        $invoices = $this->fetchSingleRow("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'OVERDUE' THEN 1 ELSE 0 END) AS overdue,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN total_amount ELSE 0 END), 0) AS paid_total,
                COALESCE(SUM(CASE WHEN status IN ('SENT', 'OVERDUE') THEN total_amount ELSE 0 END), 0) AS outstanding_total
            FROM invoices
        ");

        $visibility = $this->fetchSingleRow("
            SELECT
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(clicks), 0) AS clicks
            FROM ad_daily_stats
            WHERE stat_date BETWEEN :date_from AND :date_to
        ", [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);

        $topAdsStmt = $this->pdo->prepare("
            SELECT
                a.id,
                a.title,
                s.supplier_name,
                COALESCE(SUM(ds.impressions), 0) AS impressions,
                COALESCE(SUM(ds.clicks), 0) AS clicks
            FROM ads a
            JOIN suppliers s ON s.id_supplier = a.supplier_id
            LEFT JOIN ad_daily_stats ds
              ON ds.ad_id = a.id
             AND ds.stat_date BETWEEN :date_from AND :date_to
            GROUP BY a.id, a.title, s.supplier_name
            ORDER BY impressions DESC, clicks DESC, a.id DESC
            LIMIT 10
        ");
        $topAdsStmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        $topAds = array_map([$this, 'decorateSummary'], $topAdsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $topSuppliersStmt = $this->pdo->prepare("
            SELECT
                s.id_supplier,
                s.supplier_name,
                COALESCE(SUM(ds.impressions), 0) AS impressions,
                COALESCE(SUM(ds.clicks), 0) AS clicks
            FROM suppliers s
            LEFT JOIN ad_daily_stats ds
              ON ds.supplier_id = s.id_supplier
             AND ds.stat_date BETWEEN :date_from AND :date_to
            GROUP BY s.id_supplier, s.supplier_name
            ORDER BY impressions DESC, clicks DESC, s.id_supplier DESC
            LIMIT 10
        ");
        $topSuppliersStmt->execute([
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ]);
        $topSuppliers = array_map([$this, 'decorateSummary'], $topSuppliersStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $activityStmt = $this->pdo->prepare("
            SELECT
                l.id,
                l.level,
                l.event,
                l.context_json,
                l.page,
                l.user_id,
                l.supplier_id,
                l.created_at,
                u.username,
                s.supplier_name
            FROM portal_activity_logs l
            LEFT JOIN portal_users u ON u.id = l.user_id
            LEFT JOIN suppliers s ON s.id_supplier = l.supplier_id
            ORDER BY created_at DESC, id DESC
            LIMIT 20
        ");
        $activityStmt->execute();
        $recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $series = $this->loadVisibilitySeries(
            'WHERE stat_date BETWEEN :date_from AND :date_to',
            [
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
            ],
            $granularity
        );

        return [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'granularity' => $granularity,
            ],
            'suppliers' => $suppliers,
            'users' => $users,
            'ads' => $ads,
            'invoices' => $this->decorateSummary($invoices),
            'visibility' => $this->decorateSummary($visibility),
            'top_ads' => $topAds,
            'top_suppliers' => $topSuppliers,
            'recent_activity' => $recentActivity,
            'series' => $series,
        ];
    }

    private function loadAdsForStats(array $adIds): array
    {
        $placeholders = implode(',', array_fill(0, count($adIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, supplier_id
            FROM ads
            WHERE id IN ($placeholders)
              AND status = 'APPROVED'
              AND is_active = 1
        ");
        $stmt->execute($adIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadVisibilitySeries(string $whereClause, array $params, string $granularity): array
    {
        if ($granularity === 'month') {
            $labelExpr = "DATE_FORMAT(stat_date, '%Y-%m')";
        } else {
            $labelExpr = "DATE_FORMAT(stat_date, '%Y-%m-%d')";
        }

        $stmt = $this->pdo->prepare("
            SELECT
                $labelExpr AS label,
                SUM(impressions) AS impressions,
                SUM(clicks) AS clicks
            FROM ad_daily_stats
            $whereClause
            GROUP BY label
            ORDER BY label ASC
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'decorateSummary'], $rows);
    }

    private function normalizeDateRange(array $filters): array
    {
        $defaultTo = (new DateTimeImmutable('today'))->format('Y-m-d');
        $defaultFrom = (new DateTimeImmutable('today'))->sub(new DateInterval('P29D'))->format('Y-m-d');

        $dateFrom = trim((string)($filters['date_from'] ?? $defaultFrom));
        $dateTo = trim((string)($filters['date_to'] ?? $defaultTo));
        $granularity = strtolower(trim((string)($filters['granularity'] ?? 'day')));
        if (!in_array($granularity, ['day', 'month'], true)) {
            $granularity = 'day';
        }

        $from = DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
        $to = DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);

        if (!$from || $from->format('Y-m-d') !== $dateFrom) {
            $dateFrom = $defaultFrom;
            $from = new DateTimeImmutable($dateFrom);
        }

        if (!$to || $to->format('Y-m-d') !== $dateTo) {
            $dateTo = $defaultTo;
            $to = new DateTimeImmutable($dateTo);
        }

        if ($from > $to) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo, $granularity];
    }

    private function fetchSingleRow(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    private function decorateSummary(array $row): array
    {
        if (array_key_exists('impressions', $row) && array_key_exists('clicks', $row)) {
            $impressions = (int)$row['impressions'];
            $clicks = (int)$row['clicks'];
            $row['ctr'] = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        }

        return $row;
    }
}
