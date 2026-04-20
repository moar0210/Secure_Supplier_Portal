<?php
$isSupplierDashboard = !empty($isLoggedIn) && !empty($isSupplier);
$supplierIdForDashboard = isset($supplierId) && $supplierId !== null ? (int)$supplierId : null;
$hasLinkedSupplier = $supplierIdForDashboard !== null;

$supplierCards = [
    [
        'href' => $hasLinkedSupplier ? '?page=supplier&id=' . $supplierIdForDashboard : null,
        'title' => 'Company profile',
        'desc' => 'Review your supplier profile, contact details, address, phone details, and logo.',
        'cta' => 'Open profile',
    ],
    [
        'href' => $hasLinkedSupplier ? '?page=supplier_users' : null,
        'title' => 'Company users',
        'desc' => 'Create and maintain the users who can access your supplier account.',
        'cta' => 'Manage users',
    ],
    [
        'href' => $hasLinkedSupplier ? '?page=ads_list' : null,
        'title' => 'My ads',
        'desc' => 'View your advertisements, update drafts, and track review status.',
        'cta' => 'Open ads',
    ],
    [
        'href' => $hasLinkedSupplier ? '?page=ad_create' : null,
        'title' => 'Create ad',
        'desc' => 'Start a new advertisement draft and submit it for admin review.',
        'cta' => 'Create ad',
    ],
    [
        'href' => $hasLinkedSupplier ? '?page=supplier_stats' : null,
        'title' => 'Statistics',
        'desc' => 'See impressions, clicks, and visibility trends for your advertisements.',
        'cta' => 'View statistics',
    ],
    [
        'href' => $hasLinkedSupplier ? '?page=supplier_invoices' : null,
        'title' => 'My invoices',
        'desc' => 'Review invoice history and download invoice PDFs when available.',
        'cta' => 'Open invoices',
    ],
];
?>

<?php if ($isSupplierDashboard): ?>
    <div class="page-header">
        <div class="page-header__title-group">
            <h1>Dashboard</h1>
            <p class="page-header__subtitle">Quick access to the supplier tools you use most often.</p>
        </div>
        <div class="page-header__actions">
            <?php if ($hasLinkedSupplier): ?>
                <a href="?page=ad_create"><button type="button">+ Create ad</button></a>
            <?php else: ?>
                <button type="button" disabled>+ Create ad</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$hasLinkedSupplier): ?>
        <div class="alert alert--warning">
            Your user account is not linked to a supplier profile yet. Some supplier shortcuts may be unavailable until an administrator links your account.
        </div>
    <?php endif; ?>

    <div class="card-grid mb-5">
        <?php foreach ($supplierCards as $card): ?>
            <div class="stat-card <?= $card['href'] === null ? 'is-disabled' : '' ?>">
                <div class="stat-card__head">
                    <span class="stat-card__label"><?= h($card['title']) ?></span>
                </div>
                <p class="muted small mt-3" style="margin-bottom:14px;"><?= h($card['desc']) ?></p>
                <?php if ($card['href'] !== null): ?>
                    <a href="<?= h($card['href']) ?>"><?= h($card['cta']) ?> &rarr;</a>
                <?php else: ?>
                    <span class="muted small">Unavailable until linked</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="hero">
        <h1>Supplier Portal</h1>
        <h2>Welcome to the Hedvc.com Supplier Portal</h2>
        <p>Manage supplier profiles, company users, advertisements, monthly invoicing, statistics, and admin reporting.</p>
    </section>

    <p class="muted">
        Use the navigation bar to access supplier profiles, company users,
        advertisement workflow, statistics, invoicing, and admin reporting features.
        Approved advertisements are consumed by the hedvc.com shop frontend via the
        read-only Shop JSON API; the portal itself does not render ads to end consumers.
    </p>
<?php endif; ?>
