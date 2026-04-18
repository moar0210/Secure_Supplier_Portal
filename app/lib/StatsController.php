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
