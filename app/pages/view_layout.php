<?php
$currentPage = $currentPage ?? (string)($_GET['page'] ?? 'home');

$navLink = static function (string $page, string $label) use ($currentPage): string {
    $isActive = $currentPage === $page;
    $class = $isActive ? ' class="is-active"' : '';
    return '<a href="?page=' . h($page) . '"' . $class . '>' . h($label) . '</a>';
};
?><!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="portal.css">
</head>

<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a href="?page=home" class="site-header__brand">
                <span class="site-header__brand-mark">&#9679;</span> Supplier Portal
            </a>

            <nav class="site-nav">
                <?= $navLink('home', 'Home') ?>

                <?php if ($auth instanceof Auth && $auth->hasRole('ADMIN')): ?>
                    <?= $navLink('suppliers', 'Suppliers') ?>
                    <?= $navLink('admin_users', 'Users') ?>
                    <?= $navLink('admin_ads_queue', 'Ads Queue') ?>
                    <?= $navLink('admin_reports', 'Reports') ?>
                    <?= $navLink('admin_invoices', 'Invoices') ?>
                    <?= $navLink('admin_pricing_rules', 'Pricing') ?>
                    <?= $navLink('admin_categories', 'Categories') ?>
                    <?= $navLink('security_check', 'Security') ?>
                    <?= $navLink('dbtest', 'DB') ?>
                <?php elseif ($auth instanceof Auth && $auth->hasRole('SUPPLIER')): ?>
                    <?php $sid = $auth->supplierId(); ?>
                    <?php if ($sid !== null): ?>
                        <a href="?page=supplier&amp;id=<?= (int)$sid ?>"<?= $currentPage === 'supplier' ? ' class="is-active"' : '' ?>>My Profile</a>
                        <?= $navLink('supplier_users', 'Company Users') ?>
                        <?= $navLink('ads_list', 'My Ads') ?>
                        <?= $navLink('supplier_stats', 'Statistics') ?>
                        <?= $navLink('supplier_invoices', 'My Invoices') ?>
                    <?php else: ?>
                        <span class="site-nav__disabled">My Profile (unlinked)</span>
                        <span class="site-nav__disabled">Company Users (unlinked)</span>
                        <span class="site-nav__disabled">My Ads (unlinked)</span>
                        <span class="site-nav__disabled">Statistics (unlinked)</span>
                        <span class="site-nav__disabled">My Invoices (unlinked)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="site-header__meta">
                <?php if ($auth instanceof Auth && $auth->isLoggedIn()): ?>
                    <span>Signed in as <strong><?= h((string)$auth->username()) ?></strong></span>
                    <form method="post" action="?page=logout" class="inline-form">
                        <?= Csrf::input(); ?>
                        <button type="submit" class="logout-button">Sign out</button>
                    </form>
                <?php else: ?>
                    <a href="?page=login">Sign in</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <?= $content ?>
    </main>

    <script src="portal.js" defer></script>
</body>

</html>
