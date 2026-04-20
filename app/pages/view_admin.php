<?php
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$cards = [
    [
        'page' => 'admin_ads_queue',
        'title' => 'Ads queue',
        'desc' => 'Approve or reject supplier advertisements and inspect status history.',
        'cta' => 'Review advertisements',
    ],
    [
        'page' => 'suppliers',
        'title' => 'Suppliers',
        'desc' => 'Create suppliers, review profile data, and activate or deactivate accounts.',
        'cta' => 'Open supplier list',
    ],
    [
        'page' => 'admin_users',
        'title' => 'Users',
        'desc' => 'Manage portal users, assign roles, and link users to the correct company.',
        'cta' => 'Manage users',
    ],
    [
        'page' => 'admin_invoices',
        'title' => 'Invoices',
        'desc' => 'Generate monthly drafts, mark invoices as sent, and check overdue balances.',
        'cta' => 'Open invoicing',
    ],
    [
        'page' => 'admin_pricing_rules',
        'title' => 'Pricing rules',
        'desc' => 'Maintain the pricing and VAT rules used by the monthly invoice generator.',
        'cta' => 'Manage pricing',
    ],
    [
        'page' => 'admin_categories',
        'title' => 'Categories',
        'desc' => 'Maintain the advertisement categories visible to suppliers and the shop.',
        'cta' => 'Open categories',
    ],
    [
        'page' => 'admin_reports',
        'title' => 'Reports',
        'desc' => 'Review platform-wide supplier, user, listing, invoice, and visibility statistics.',
        'cta' => 'Open reports',
    ],
    [
        'page' => 'security_check',
        'title' => 'Security check',
        'desc' => 'Verify session behavior, cookie settings, and the encryption configuration.',
        'cta' => 'Open security check',
    ],
];
?>
<div class="page-header">
    <div class="page-header__title-group">
        <h1><?= h($greeting) ?></h1>
        <p class="page-header__subtitle">Here's what's happening across your supplier portal today.</p>
    </div>
    <div class="page-header__actions">
        <a href="?page=supplier_create"><button type="button" class="btn-secondary">+ Add supplier</button></a>
        <a href="?page=admin_invoices"><button type="button">+ Create invoice</button></a>
    </div>
</div>

<div class="card-grid mb-5">
    <?php foreach ($cards as $card): ?>
        <div class="stat-card">
            <div class="stat-card__head">
                <span class="stat-card__label"><?= h($card['title']) ?></span>
            </div>
            <p class="muted small mt-3" style="margin-bottom:14px;"><?= h($card['desc']) ?></p>
            <a href="?page=<?= h($card['page']) ?>"><?= h($card['cta']) ?> &rarr;</a>
        </div>
    <?php endforeach; ?>
</div>
