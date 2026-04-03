<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
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

        .logout-button {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font: inherit;
            margin-left: 12px;
            padding: 0;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <header>
        <strong>Supplier Portal</strong>

        <nav style="display:inline-block; margin-left:16px;">
            <a href="?page=home">Home</a>

            <?php if ($auth instanceof Auth && $auth->hasRole('ADMIN')): ?>
                <a href="?page=dbtest">DB Test</a>
                <a href="?page=suppliers">Suppliers</a>
                <a href="?page=admin_ads_queue">Ads Queue</a>
                <a href="?page=admin_invoices">Invoices</a>
                <a href="?page=admin_pricing_rules">Pricing Rules</a>
                <a href="?page=admin_categories">Categories</a>
                <a href="?page=security_check">Security Check</a>
                <a href="?page=admin">Admin</a>
            <?php elseif ($auth instanceof Auth && $auth->hasRole('SUPPLIER')): ?>
                <?php $sid = $auth->supplierId(); ?>
                <?php if ($sid !== null): ?>
                    <a href="?page=supplier&id=<?= (int)$sid ?>">My Profile</a>
                    <a href="?page=ads_list">My Ads</a>
                    <a href="?page=supplier_invoices">My Invoices</a>
                <?php else: ?>
                    <span style="opacity:.8;">My Profile (unlinked)</span>
                    <span style="opacity:.8;">My Ads (unlinked)</span>
                    <span style="opacity:.8;">My Invoices (unlinked)</span>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <nav class="nav-right">
            <?php if ($auth instanceof Auth && $auth->isLoggedIn()): ?>
                <span>Logged in as <strong><?= h((string)$auth->username()) ?></strong></span>
                <form method="post" action="?page=logout" style="display:inline;">
                    <?= Csrf::input(); ?>
                    <button type="submit" class="logout-button">Logout</button>
                </form>
            <?php else: ?>
                <a href="?page=login">Login</a>
            <?php endif; ?>
        </nav>

        <div style="clear:both;"></div>
    </header>

    <main>
        <?= $content ?>
    </main>
</body>

</html>
