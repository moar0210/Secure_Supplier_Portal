<?php

declare(strict_types=1);

final class ApiController extends BaseController
{
    private StatsService $statsService;

    public function __construct(
        View $view,
        Auth $auth,
        Database $db,
        Crypto $crypto,
        AdsService $adsService,
        SupplierService $supplierService,
        StatsService $statsService,
        array $config
    ) {
        parent::__construct($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
        $this->statsService = $statsService;
    }

    public function shopAds(): void
    {
        $this->applyApiHeaders();
        if ($this->handlePreflight()) {
            return;
        }
        if (!$this->requireMethod('GET')) {
            return;
        }

        $filters = [
            'search' => (string)($_GET['search'] ?? ''),
            'category_id' => (string)($_GET['category_id'] ?? ''),
        ];

        $rows = $this->statsService->listPublicAds($filters);
        $trackParam = strtolower((string)($_GET['track'] ?? '1'));
        $shouldTrack = !in_array($trackParam, ['0', 'false', 'no'], true);
        if ($shouldTrack && $this->trackingAllowed('list')) {
            $this->statsService->recordImpressions(array_column($rows, 'id'));
        }

        $this->respond([
            'filters' => [
                'search' => $filters['search'],
                'category_id' => $filters['category_id'] !== '' ? (int)$filters['category_id'] : null,
            ],
            'count' => count($rows),
            'ads' => array_map([$this, 'shapeAd'], $rows),
        ]);
    }

    public function shopAd(): void
    {
        $this->applyApiHeaders();
        if ($this->handlePreflight()) {
            return;
        }
        if (!$this->requireMethod('GET')) {
            return;
        }

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->respondError(400, 'Missing or invalid id parameter.');
            return;
        }

        $adId = (int)$idRaw;
        $ad = $this->statsService->getPublicAd($adId);
        if ($ad === null) {
            $this->respondError(404, 'Advertisement not found.');
            return;
        }

        $trackParam = strtolower((string)($_GET['track'] ?? '1'));
        $shouldTrack = !in_array($trackParam, ['0', 'false', 'no'], true);
        if ($shouldTrack && $this->trackingAllowed('ad:' . $adId)) {
            $this->statsService->recordClick($adId);
        }

        $this->respond([
            'ad' => $this->shapeAd($ad),
        ]);
    }

    public function shopCategories(): void
    {
        $this->applyApiHeaders();
        if ($this->handlePreflight()) {
            return;
        }
        if (!$this->requireMethod('GET')) {
            return;
        }

        $rows = $this->statsService->listCategories();
        $this->respond([
            'count' => count($rows),
            'categories' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
            ], $rows),
        ]);
    }

    public function shopSupplierLogo(): void
    {
        $this->applyApiHeaders(false);
        if ($this->handlePreflight()) {
            return;
        }
        if (!$this->requireMethod('GET')) {
            return;
        }

        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->respondError(400, 'Missing or invalid id parameter.');
            return;
        }

        $supplierId = (int)$idRaw;
        $logo = $this->supplierService->getLogoAsset($supplierId);
        if ($logo === null) {
            $this->respondError(404, 'Logo not found.');
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header_remove('Content-Type');
        header('Content-Type: ' . (string)$logo['mime_type']);
        header('Content-Length: ' . (string)(filesize((string)$logo['path']) ?: 0));
        header('Cache-Control: public, max-age=60');
        header('Content-Disposition: inline; filename="' . (string)$logo['download_name'] . '"');
        readfile((string)$logo['path']);
    }

    private function shapeAd(array $row): array
    {
        $priceModel = (string)($row['price_model_type'] ?? '');
        $supplierId = (int)($row['supplier_id'] ?? 0);
        $base = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'description' => (string)$row['description'],
            'category' => [
                'id' => isset($row['category_id']) ? (int)$row['category_id'] : null,
                'name' => (string)($row['category_name'] ?? ''),
            ],
            'supplier' => [
                'id' => $supplierId,
                'name' => (string)($row['supplier_name'] ?? ''),
                'homepage' => (string)($row['homepage'] ?? ''),
                'logo_url' => $supplierId > 0 ? $this->supplierLogoUrl($supplierId) : null,
            ],
            'price' => [
                'model' => $priceModel !== '' ? $priceModel : null,
                'model_label' => $priceModel !== '' ? AdsService::priceModelLabel($priceModel) : null,
                'text' => (string)($row['price_text'] ?? ''),
            ],
            'validity' => [
                'from' => $row['valid_from'] !== null ? (string)$row['valid_from'] : null,
                'to' => $row['valid_to'] !== null ? (string)$row['valid_to'] : null,
            ],
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];

        return $base;
    }

    private function supplierLogoUrl(int $supplierId): ?string
    {
        $logo = $this->supplierService->getLogoMeta($supplierId);
        if ($logo === null) {
            return null;
        }

        $base = rtrim((string)($this->config['portal']['base_url'] ?? ''), '/');
        $path = '?page=api_shop_supplier_logo&id=' . $supplierId
            . '&v=' . rawurlencode((string)($logo['updated_at'] ?? ''));

        return $base !== '' ? $base . '/' . $path : $path;
    }

    private function applyApiHeaders(bool $asJson = true): void
    {
        header_remove('Cross-Origin-Resource-Policy');
        header_remove('Cross-Origin-Opener-Policy');
        header('Cross-Origin-Resource-Policy: cross-origin');

        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $allowedOrigins = (array)($this->config['api']['cors_allowed_origins'] ?? ['*']);
        $allowOrigin = $this->resolveCorsOrigin($origin, $allowedOrigins);

        if ($allowOrigin !== null) {
            header('Access-Control-Allow-Origin: ' . $allowOrigin);
            if ($allowOrigin !== '*') {
                header('Vary: Origin');
            }
        }
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        header('Access-Control-Max-Age: 600');

        if ($asJson) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }

    private function resolveCorsOrigin(string $requestOrigin, array $allowed): ?string
    {
        // Empty allowlist denies cross-origin requests. Only an explicit ["*"]
        // entry opens the endpoint to any origin.
        if ($allowed === []) {
            return null;
        }

        if (in_array('*', $allowed, true)) {
            return '*';
        }

        if ($requestOrigin !== '' && in_array($requestOrigin, $allowed, true)) {
            return $requestOrigin;
        }

        return null;
    }

    private function trackingAllowed(string $scope): bool
    {
        $interval = (int)($this->config['api']['track_min_interval_seconds'] ?? 30);
        if ($interval <= 0) {
            return true;
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ip === '') {
            return true;
        }

        $key = hash('sha256', $ip . '|' . $ua . '|' . $scope);
        $cacheDir = dirname(__DIR__) . '/storage/shop_track';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cutoff = time() - $interval;
        $this->pruneTrackingCache($cacheDir, $cutoff);

        $path = $cacheDir . '/' . $key;
        if (is_file($path) && (int)@filemtime($path) >= $cutoff) {
            return false;
        }

        @touch($path);
        return true;
    }

    private function pruneTrackingCache(string $dir, int $cutoff): void
    {
        if (mt_rand(0, 49) !== 0) {
            return;
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path) && (int)@filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }

    private function handlePreflight(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'OPTIONS') {
            return false;
        }

        http_response_code(204);
        return true;
    }

    private function requireMethod(string $method): bool
    {
        $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($requestMethod === strtoupper($method)) {
            return true;
        }

        header('Allow: ' . $method . ', OPTIONS');
        $this->respondError(405, 'Method not allowed.');
        return false;
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function respondError(int $status, string $message): void
    {
        http_response_code($status);
        echo json_encode([
            'error' => [
                'status' => $status,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
