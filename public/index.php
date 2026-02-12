<?php
$configPath = __DIR__ . "/../app/config/config.local.php";
if (!file_exists($configPath)) {
  http_response_code(500);
  echo "Missing config.local.php. Copy app/config/config.example.php to app/config/config.local.php and fill in credentials.";
  exit;
}
$config = require $configPath;
require __DIR__ . "/../app/lib/Database.php";

$showDetailedErrors = true;

try {
    $db = new Database($config);
} catch (Exception $e) {
    http_response_code(500);
    if ($showDetailedErrors) {
        echo "DB error: " . htmlspecialchars($e->getMessage());
    } else {
        echo "Database connection failed. Please try again later.";
    }
    exit;
}

$page = $_GET["page"] ?? "home";

$routes = [
    "home" => __DIR__ . "/../app/pages/home.php",
    "dbtest" => __DIR__ . "/../app/pages/dbtest.php",
    "suppliers" => __DIR__ . "/../app/pages/suppliers.php",
    "supplier" => __DIR__ . "/../app/pages/supplier.php",
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = "home";
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
    </style>
</head>

<body>
    <header>
        <strong>Supplier Portal</strong>
        <nav style="display:inline-block; margin-left:16px;">
            <a href="?page=home">Home</a>
            <a href="?page=dbtest">DB Test</a>
            <a href="?page=suppliers">Suppliers</a>
        </nav>
    </header>

    <main>
        <?php require $routes[$page]; ?>
    </main>
</body>

</html>