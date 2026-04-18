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
    <h1>My invoices</h1>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="supplier_invoices">
    <div class="filter-bar__title">Filters</div>
    <div class="field-row">
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach (['ALL', 'DRAFT', 'SENT', 'PAID', 'OVERDUE'] as $statusOption): ?>
                    <option value="<?= h($statusOption) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $statusOption ? 'selected' : '' ?>>
                        <?= h($statusOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Billing month</label>
            <input type="month" name="billing_month" value="<?= h((string)($filters['billing_month'] ?? '')) ?>">
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <a href="?page=supplier_invoices" class="muted small">Reset</a>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No invoices available.</p></div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Billing month</th>
                <th>Status</th>
                <th>Issue</th>
                <th>Due</th>
                <th>Total</th>
                <th>PDF</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h((string)$row['invoice_number']) ?></td>
                    <td><?= sprintf('%04d-%02d', (int)$row['billing_year'], (int)$row['billing_month']) ?></td>
                    <td><?= $badge((string)$row['status']) ?></td>
                    <td class="muted small"><?= h((string)$row['issue_date']) ?></td>
                    <td class="muted small"><?= h((string)$row['due_date']) ?></td>
                    <td><?= $money($row['total_amount']) ?> <?= h((string)$row['currency_code']) ?></td>
                    <td><a href="?page=invoice_pdf&amp;id=<?= (int)$row['id'] ?>">Download</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
