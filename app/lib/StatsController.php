<?php

declare(strict_types=1);

final class StatsController extends BaseController
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

    public function marketplace(): void
    {
        $filters = [
            'search' => (string)($_GET['search'] ?? ''),
            'category_id' => (string)($_GET['category_id'] ?? ''),
        ];

        $rows = $this->statsService->listPublicAds($filters);
        $this->statsService->recordImpressions(array_column($rows, 'id'));

        $this->render('view_marketplace', [
            'filters' => $filters,
            'rows' => $rows,
            'categories' => $this->statsService->listCategories(),
        ], 200, 'Marketplace');
    }

    public function marketplaceAd(): void
    {
        $idRaw = $_GET['id'] ?? null;
        if (!$idRaw || !ctype_digit((string)$idRaw)) {
            $this->redirect('?page=404');
        }

        $adId = (int)$idRaw;
        $ad = $this->statsService->getPublicAd($adId);
        if ($ad === null) {
            $this->redirect('?page=404');
        }

        $this->statsService->recordClick($adId);

        $this->render('view_marketplace_ad', [
            'ad' => $ad,
        ], 200, 'Advertisement');
    }

    public function supplierStats(): void
    {
        $this->auth->requireRole('SUPPLIER');

        $supplierId = $this->auth->supplierId();
        if ($supplierId === null) {
            $this->redirect('?page=403');
        }

        $filters = [
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'granularity' => (string)($_GET['granularity'] ?? 'day'),
        ];

        $dashboard = $this->statsService->supplierDashboard($supplierId, $filters);

        $this->render('view_supplier_stats', [
            'dashboard' => $dashboard,
        ], 200, 'My Statistics');
    }

    public function adminReports(): void
    {
        $this->auth->requireRole('ADMIN');

        $filters = [
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'granularity' => (string)($_GET['granularity'] ?? 'day'),
        ];

        $report = $this->statsService->adminReport($filters);

        $this->render('view_admin_reports', [
            'report' => $report,
        ], 200, 'Reports');
    }
}
