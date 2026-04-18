<?php
$badge = static function (string $status): string {
    $status = strtoupper($status);
    $cls = match ($status) {
        'PAID' => 'badge--paid',
        'SENT' => 'badge--sent',
        'OVERDUE' => 'badge--overdue',
        default => 'badge--draft',
    };
    return '<span class="badge ' . $cls . '">' . h($status ?: 'DRAFT') . '</span>';
};
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<div class="page-header">
    <h1>Invoices</h1>
</div>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<div class="section-grid mb-5">
    <form method="post" class="card">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="generate">
        <h2 class="card__title mt-0">Generate monthly drafts</h2>
        <div class="field-row">
            <div class="field">
                <label>Billing month</label>
                <input type="month" name="billing_month" value="<?= h((string)($filters['billing_month'] ?? date('Y-m'))) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit">Generate invoices</button>
        </div>
    </form>

    <form method="post" class="card">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="check_overdue">
        <h2 class="card__title mt-0">Overdue check</h2>
        <p class="muted">Marks sent invoices past due date as overdue.</p>
        <div class="form-actions">
            <button type="submit">Check overdue</button>
        </div>
    </form>
</div>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="admin_invoices">
    <div class="filter-bar__title">Filters</div>
    <div class="field-row">
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach (['ALL', 'DRAFT', 'SENT', 'PAID', 'OVERDUE'] as $status): ?>
                    <option value="<?= h($status) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $status ? 'selected' : '' ?>>
                        <?= h($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Billing month</label>
            <input type="month" name="billing_month" value="<?= h((string)($filters['billing_month'] ?? '')) ?>">
        </div>
        <div class="field">
            <label>Supplier ID</label>
            <input type="number" name="supplier_id" min="1" step="1" value="<?= h((string)($filters['supplier_id'] ?? '')) ?>">
        </div>
        <div class="field">
            <label>Invoice number</label>
            <input name="invoice_number" value="<?= h((string)($filters['invoice_number'] ?? '')) ?>">
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <a href="?page=admin_invoices" class="muted small">Reset</a>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No invoices found.</p></div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Supplier</th>
                <th>Billing month</th>
                <th>Status</th>
                <th>Issue</th>
                <th>Due</th>
                <th>Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h((string)$row['invoice_number']) ?></td>
                    <td><?= h((string)$row['supplier_name_snapshot']) ?> (#<?= (int)$row['supplier_id'] ?>)</td>
                    <td><?= sprintf('%04d-%02d', (int)$row['billing_year'], (int)$row['billing_month']) ?></td>
                    <td><?= $badge((string)$row['status']) ?></td>
                    <td class="muted small"><?= h((string)$row['issue_date']) ?></td>
                    <td class="muted small"><?= h((string)$row['due_date']) ?></td>
                    <td><?= $money($row['total_amount']) ?> <?= h((string)$row['currency_code']) ?></td>
                    <td class="actions-inline">
                        <a href="?page=admin_invoice_view&amp;id=<?= (int)$row['id'] ?>">Open</a>
                        <a href="?page=invoice_pdf&amp;id=<?= (int)$row['id'] ?>">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
