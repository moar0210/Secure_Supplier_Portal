<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/app/lib/helpers.php';

$configPath = $root . '/app/config/config.local.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Missing config.local.php. Copy app/config/config.example.php to app/config/config.local.php and fill in credentials.';
    exit;
}
$config = require $configPath;

require $root . '/app/lib/SecurityBootstrap.php';
require $root . '/app/lib/Database.php';
require $root . '/app/lib/UserFacingException.php';
require $root . '/app/lib/PortalLogger.php';
require $root . '/app/lib/Crypto.php';
require $root . '/app/lib/SupplierProfileEncryptionMap.php';

$showDetailedErrors = false;

try {
    $db = new Database($config);
    $pdo = $db->pdo();
    $crypto = new Crypto((array)($config['crypto'] ?? []));
} catch (Exception $e) {
    http_response_code(500);

    if ($showDetailedErrors) {
        echo 'Bootstrap error: ' . h($e->getMessage());
    } else {
        echo 'Application bootstrap failed. Please check the database and encryption configuration.';
    }

    exit;
}

require $root . '/app/lib/auth.php';
require $root . '/app/lib/Csrf.php';
require $root . '/app/lib/AdsService.php';
require $root . '/app/lib/SupplierService.php';
require $root . '/app/lib/InvoiceService.php';
require $root . '/app/lib/PortalUserService.php';
require $root . '/app/lib/StatsService.php';
require $root . '/app/lib/View.php';
require $root . '/app/lib/BaseController.php';
require $root . '/app/lib/StaticController.php';
require $root . '/app/lib/AuthController.php';
require $root . '/app/lib/SupplierController.php';
require $root . '/app/lib/AdsController.php';
require $root . '/app/lib/AdminController.php';
require $root . '/app/lib/InvoiceController.php';
require $root . '/app/lib/UserController.php';
require $root . '/app/lib/StatsController.php';
require $root . '/app/lib/ApiController.php';

$auth = new Auth($pdo);
$adsService = new AdsService($pdo);
$supplierService = new SupplierService($pdo, $crypto);
$portalUserService = new PortalUserService($pdo);
$statsService = new StatsService($pdo);
$view = new View($root . '/app/pages');

$staticController = new StaticController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$authController = new AuthController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$supplierController = new SupplierController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$adsController = new AdsController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$adminController = new AdminController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$invoiceController = new InvoiceController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$userController = new UserController($view, $auth, $db, $crypto, $adsService, $supplierService, $portalUserService, $config);
$statsController = new StatsController($view, $auth, $db, $crypto, $adsService, $supplierService, $statsService, $config);
$apiController = new ApiController($view, $auth, $db, $crypto, $adsService, $supplierService, $statsService, $config);

$page = (string)($_GET['page'] ?? 'home');

$routes = [
    'home' => [$staticController, 'home'],
    'marketplace' => [$statsController, 'marketplace'],
    'marketplace_ad' => [$statsController, 'marketplaceAd'],
    'dbtest' => [$staticController, 'dbtest'],
    'suppliers' => [$supplierController, 'index'],
    'supplier_create' => [$supplierController, 'create'],
    'supplier' => [$supplierController, 'show'],
    'supplier_status' => [$supplierController, 'status'],
    'supplier_logo' => [$supplierController, 'logo'],
    'login' => [$authController, 'login'],
    'logout' => [$authController, 'logout'],
    'reset_request' => [$authController, 'resetRequest'],
    'reset_password' => [$authController, 'resetPassword'],
    '403' => [$staticController, 'forbidden'],
    '404' => [$staticController, 'notFound'],
    'admin' => [$adminController, 'dashboard'],
    'security_check' => [$adminController, 'securityCheck'],
    'ads_list' => [$adsController, 'index'],
    'ad_create' => [$adsController, 'create'],
    'ad_edit' => [$adsController, 'edit'],
    'ad_toggle' => [$adsController, 'toggle'],
    'ad_delete' => [$adsController, 'delete'],
    'admin_ads_queue' => [$adminController, 'adsQueue'],
    'admin_ad_review' => [$adminController, 'adReview'],
    'admin_categories' => [$adminController, 'categories'],
    'admin_category_edit' => [$adminController, 'categoryEdit'],
    'admin_users' => [$userController, 'adminUsers'],
    'supplier_users' => [$userController, 'supplierUsers'],
    'admin_reports' => [$statsController, 'adminReports'],
    'admin_invoices' => [$invoiceController, 'adminInvoices'],
    'admin_invoice_view' => [$invoiceController, 'adminInvoiceView'],
    'admin_pricing_rules' => [$invoiceController, 'adminPricingRules'],
    'supplier_invoices' => [$invoiceController, 'supplierInvoices'],
    'supplier_stats' => [$statsController, 'supplierStats'],
    'invoice_pdf' => [$invoiceController, 'pdf'],
    'api_shop_ads' => [$apiController, 'shopAds'],
    'api_shop_ad' => [$apiController, 'shopAd'],
    'api_shop_categories' => [$apiController, 'shopCategories'],
    'api_shop_supplier_logo' => [$apiController, 'shopSupplierLogo'],
];

$handler = $routes[$page] ?? [$staticController, 'notFound'];
try {
    $handler();
} catch (Throwable $e) {
    PortalLogger::write($pdo ?? null, 'ERROR', 'Route dispatch failed', [
        'page' => $page,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo 'Something went wrong. Please try again later.';
}
