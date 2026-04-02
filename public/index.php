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
require $root . '/app/lib/View.php';
require $root . '/app/lib/BaseController.php';
require $root . '/app/lib/StaticController.php';
require $root . '/app/lib/AuthController.php';
require $root . '/app/lib/SupplierController.php';
require $root . '/app/lib/AdsController.php';
require $root . '/app/lib/AdminController.php';

$auth = new Auth($pdo);
$adsService = new AdsService($pdo);
$supplierService = new SupplierService($pdo, $crypto);
$view = new View($root . '/app/pages');

$staticController = new StaticController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$authController = new AuthController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$supplierController = new SupplierController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$adsController = new AdsController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);
$adminController = new AdminController($view, $auth, $db, $crypto, $adsService, $supplierService, $config);

$page = (string)($_GET['page'] ?? 'home');

$routes = [
    'home' => [$staticController, 'home'],
    'dbtest' => [$staticController, 'dbtest'],
    'suppliers' => [$supplierController, 'index'],
    'supplier' => [$supplierController, 'show'],
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
];

$handler = $routes[$page] ?? [$staticController, 'notFound'];
try {
    $handler();
} catch (Throwable $e) {
    error_log('[Supplier Portal][ERROR] Route dispatch failed ' . json_encode([
        'page' => $page,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo 'Something went wrong. Please try again later.';
}
