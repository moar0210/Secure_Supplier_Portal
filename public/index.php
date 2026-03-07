<?php

declare(strict_types=1);

$root = dirname(__DIR__);

// ---- Load config ----
$configPath = $root . "/app/config/config.local.php";
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Missing config.local.php. Copy app/config/config.example.php to app/config/config.local.php and fill in credentials.";
    exit;
}
$config = require $configPath;

// ---- Security bootstrap (starts session + headers + idle timeout) ----
require $root . "/app/lib/SecurityBootstrap.php";

// ---- DB ----
require $root . "/app/lib/Database.php";
$showDetailedErrors = true;

try {
    $db = new Database($config);
    $pdo = $db->pdo();
} catch (Exception $e) {
    http_response_code(500);
    if ($showDetailedErrors) {
        echo "DB error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } else {
        echo "Database connection failed. Please try again later.";
    }
    exit;
}

// ---- Auth + CSRF ----
require $root . "/app/lib/Auth.php";
require $root . "/app/lib/Csrf.php";

$auth = new Auth($pdo);

// ---- Services ----
require $root . "/app/lib/AdsService.php";
$adsService = new AdsService($pdo);

// ---- Router ----
$page = $_GET["page"] ?? "home";

$routes = [
    "home"            => $root . "/app/pages/home.php",
    "dbtest"          => $root . "/app/pages/dbtest.php",
    "suppliers"       => $root . "/app/pages/suppliers.php",
    "supplier"        => $root . "/app/pages/supplier.php",

    // Auth
    "login"           => $root . "/app/pages/login.php",
    "logout"          => $root . "/app/pages/logout.php",
    "403"             => $root . "/app/pages/403.php",
    "404"             => $root . "/app/pages/404.php",

    // Admin-only
    "admin"           => $root . "/app/pages/admin.php",
    "security_check"  => $root . "/app/pages/security_check.php",

    // Supplier Ads 
    "ads_list"        => $root . "/app/pages/ads_list.php",
    "ad_create"       => $root . "/app/pages/ad_create.php",
    "ad_edit"         => $root . "/app/pages/ad_edit.php",
    "ad_toggle"       => $root . "/app/pages/ad_toggle.php",

    // Admin Ads 
    "admin_ads_queue" => $root . "/app/pages/admin_ads_queue.php",
    "admin_ad_review" => $root . "/app/pages/admin_ad_review.php",

    // Admin Categories 
    "admin_categories"    => $root . "/app/pages/admin_categories.php",
    "admin_category_edit" => $root . "/app/pages/admin_category_edit.php",
];

// fallback + avoid fatal if file missing
if (!isset($routes[$page]) || !file_exists($routes[$page])) {
    $page = "404";
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Supplier Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
        }

        header {
            background: #222;
            color: #fff;
            padding: 12px 16px;
        }

        nav a {
            color: #fff;
            margin-right: 12px;
            text-decoration: none;
        }

        main {
            padding: 16px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f5f5f5;
        }

        .nav-right {
            float: right;
        }

        .nav-right a {
            margin-right: 0;
            margin-left: 12px;
        }
    </style>
</head>

<body>
    <header>
        <strong>Supplier Portal</strong>

        <nav style="display:inline-block; margin-left:16px;">
            <a href="?page=home">Home</a>
            <a href="?page=dbtest">DB Test</a>

            <?php if ($auth->hasRole('ADMIN')): ?>
                <a href="?page=suppliers">Suppliers</a>
                <a href="?page=admin_ads_queue">Ads Queue</a>
                <a href="?page=admin_categories">Categories</a>
                <a href="?page=security_check">Security Check</a>
                <a href="?page=admin">Admin</a>
            <?php elseif ($auth->hasRole('SUPPLIER')): ?>
                <?php $sid = $auth->supplierId(); ?>
                <?php if ($sid !== null): ?>
                    <a href="?page=supplier&id=<?= (int)$sid ?>">My Profile</a>
                    <a href="?page=ads_list">My Ads</a>
                <?php else: ?>
                    <span style="opacity:.8;">My Profile (unlinked)</span>
                    <span style="opacity:.8;">My Ads (unlinked)</span>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <nav class="nav-right">
            <?php if ($auth->isLoggedIn()): ?>
                <span>Logged in as <strong><?php echo h((string)$auth->username()); ?></strong></span>
                <a href="?page=logout">Logout</a>
            <?php else: ?>
                <a href="?page=login">Login</a>
            <?php endif; ?>
        </nav>

        <div style="clear:both;"></div>
    </header>

    <main>
        <?php require $routes[$page]; ?>
    </main>
</body>

</html>