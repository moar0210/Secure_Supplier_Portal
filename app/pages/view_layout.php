<?php
$currentPage = $currentPage ?? (string)($_GET['page'] ?? 'home');
$isLoggedIn = $auth instanceof Auth && $auth->isLoggedIn();
$isAdmin = $isLoggedIn && $auth->hasRole('ADMIN');
$isSupplier = $isLoggedIn && $auth->hasRole('SUPPLIER');
$supplierIdForLinks = $isSupplier ? $auth->supplierId() : null;

$sidebarLink = static function (string $page, string $label, string $iconSvg, ?string $href = null) use ($currentPage): string {
    $isActive = $currentPage === $page;
    $cls = 'sidebar__link' . ($isActive ? ' is-active' : '');
    $url = $href ?? ('?page=' . h($page));
    return '<a href="' . $url . '" class="' . $cls . '">'
        . '<span class="sidebar__icon">' . $iconSvg . '</span>'
        . '<span>' . h($label) . '</span>'
        . '</a>';
};

$disabledLink = static function (string $label, string $iconSvg): string {
    return '<span class="sidebar__link is-disabled">'
        . '<span class="sidebar__icon">' . $iconSvg . '</span>'
        . '<span>' . h($label) . ' (unlinked)</span>'
        . '</span>';
};

$icon = [
    'dashboard' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
    'queue' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1.5 1.5L7 6"/><path d="M4 12l1.5 1.5L7 12"/><path d="M4 18l1.5 1.5L7 18"/></svg>',
    'invoice' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>',
    'reports' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
    'suppliers' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V7l9-4 9 4v14"/><path d="M9 21V12h6v9"/></svg>',
    'users' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 3a4 4 0 0 1 0 8"/><path d="M22 21a6 6 0 0 0-4-5.66"/></svg>',
    'tag' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 12 22 2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>',
    'price' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/></svg>',
    'shield' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v7c0 5 3.5 9.2 8 10 4.5-.8 8-5 8-10V5l-8-3z"/></svg>',
    'db' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 11v6c0 1.66 4.03 3 9 3s9-1.34 9-3v-6"/></svg>',
    'profile' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    'ads' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-7v16L3 13z"/><path d="M11 19v-5"/></svg>',
    'stats' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 5-6"/></svg>',
    'menu' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M9 5v14"/></svg>',
    'home' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H10v7H4a1 1 0 0 1-1-1z"/></svg>',
];

$pageTitles = [
    'home' => 'Dashboard',
    'admin' => 'Dashboard',
    'admin_ads_queue' => 'Ads Queue',
    'admin_ad_review' => 'Review Ad',
    'admin_invoices' => 'Invoices',
    'admin_invoice_view' => 'Invoice',
    'admin_reports' => 'Reports',
    'suppliers' => 'Suppliers',
    'supplier_create' => 'Create Supplier',
    'supplier' => 'Supplier Profile',
    'admin_users' => 'Portal Users',
    'admin_categories' => 'Categories',
    'admin_category_edit' => 'Rename Category',
    'admin_pricing_rules' => 'Pricing Rules',
    'security_check' => 'Security Check',
    'dbtest' => 'Database Test',
    'ads_list' => 'My Ads',
    'ad_create' => 'Create Ad',
    'ad_edit' => 'Edit Ad',
    'supplier_stats' => 'My Statistics',
    'supplier_invoices' => 'My Invoices',
    'supplier_users' => 'Company Users',
    'change_password' => 'Change Password',
    'reset_request' => 'Reset Password',
    'reset_password' => 'Choose New Password',
    '403' => 'Forbidden',
    '404' => 'Not Found',
];

$headerTitle = $pageTitles[$currentPage] ?? (string)$title;

$shellClass = 'app-shell';
if (!$isLoggedIn) {
    $shellClass .= ' is-anonymous';
}

$assetVersion = '7';
?><!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="portal.css?v=<?= h($assetVersion) ?>">
</head>

<body>
    <div class="<?= $shellClass ?>" id="appShell">
        <?php if ($isLoggedIn): ?>
            <aside class="sidebar" id="sidebar">
                <a href="?page=home" class="sidebar__brand">
                    <span class="sidebar__brand-mark">
                        <?= $icon['home'] ?>
                    </span>
                    <span class="sidebar__brand-text">
                        <span class="sidebar__brand-name">Supplier Portal</span>
                        <span class="sidebar__brand-version">HEDVC.COM</span>
                    </span>
                </a>

                <nav class="sidebar__nav">
                    <?php if ($isAdmin): ?>
                        <?= $sidebarLink('admin', 'Dashboard', $icon['dashboard']) ?>

                        <div class="sidebar__group-title">Operate</div>
                        <?= $sidebarLink('admin_ads_queue', 'Ads Queue', $icon['queue']) ?>
                        <?= $sidebarLink('admin_invoices', 'Invoices', $icon['invoice']) ?>
                        <?= $sidebarLink('admin_reports', 'Reports', $icon['reports']) ?>

                        <div class="sidebar__group-title">Manage</div>
                        <?= $sidebarLink('suppliers', 'Suppliers', $icon['suppliers']) ?>
                        <?= $sidebarLink('admin_users', 'Users', $icon['users']) ?>
                        <?= $sidebarLink('admin_categories', 'Categories', $icon['tag']) ?>
                        <?= $sidebarLink('admin_pricing_rules', 'Pricing', $icon['price']) ?>

                        <div class="sidebar__group-title">Admin</div>
                        <?= $sidebarLink('security_check', 'Security', $icon['shield']) ?>
                        <?= $sidebarLink('dbtest', 'DB', $icon['db']) ?>
                    <?php elseif ($isSupplier): ?>
                        <?= $sidebarLink('home', 'Dashboard', $icon['dashboard']) ?>

                        <div class="sidebar__group-title">My Company</div>
                        <?php if ($supplierIdForLinks !== null): ?>
                            <?= $sidebarLink('supplier', 'My Profile', $icon['profile'], '?page=supplier&id=' . (int)$supplierIdForLinks) ?>
                            <?= $sidebarLink('supplier_users', 'Company Users', $icon['users']) ?>
                        <?php else: ?>
                            <?= $disabledLink('My Profile', $icon['profile']) ?>
                            <?= $disabledLink('Company Users', $icon['users']) ?>
                        <?php endif; ?>

                        <div class="sidebar__group-title">Marketing</div>
                        <?php if ($supplierIdForLinks !== null): ?>
                            <?= $sidebarLink('ads_list', 'My Ads', $icon['ads']) ?>
                            <?= $sidebarLink('supplier_stats', 'Statistics', $icon['stats']) ?>
                            <?= $sidebarLink('supplier_invoices', 'My Invoices', $icon['invoice']) ?>
                        <?php else: ?>
                            <?= $disabledLink('My Ads', $icon['ads']) ?>
                            <?= $disabledLink('Statistics', $icon['stats']) ?>
                            <?= $disabledLink('My Invoices', $icon['invoice']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
            </aside>
        <?php endif; ?>

        <div class="app-content">
            <header class="app-header">
                <?php if ($isLoggedIn): ?>
                    <button type="button" class="app-header__toggle" id="sidebarToggle" data-sidebar-toggle aria-label="Toggle navigation">
                        <?= $icon['menu'] ?>
                    </button>
                    <div class="app-header__title" aria-current="page">
                        <?= h($headerTitle) ?>
                    </div>
                <?php else: ?>
                    <a href="?page=home" class="app-header__brand">
                        <span class="sidebar__brand-mark">
                            <?= $icon['home'] ?>
                        </span>
                        <span class="sidebar__brand-text">
                            <span class="sidebar__brand-name">Supplier Portal</span>
                            <span class="sidebar__brand-version">HEDVC.COM</span>
                        </span>
                    </a>
                <?php endif; ?>

                <div class="app-header__meta">
                    <?php if ($isLoggedIn): ?>
                        <div class="app-header__user">
                            <span class="app-header__avatar"><?= h(strtoupper(substr((string)$auth->username(), 0, 2))) ?></span>
                            <span class="app-header__user-info">
                                <span class="app-header__user-name"><?= h((string)$auth->username()) ?></span>
                                <span class="app-header__user-role">
                                    <?= $isAdmin ? 'Administrator' : 'Supplier' ?>
                                </span>
                            </span>
                        </div>
                        <form method="post" action="?page=logout" class="inline-form">
                            <?= Csrf::input(); ?>
                            <button type="submit" class="logout-button">Sign out</button>
                        </form>
                    <?php else: ?>
                        <a href="?page=login" class="app-header__signin">Sign in</a>
                    <?php endif; ?>
                </div>
            </header>

            <main>
                <?= $content ?>
            </main>
        </div>
    </div>

    <script src="portal.js?v=<?= h($assetVersion) ?>" defer></script>
</body>

</html>
