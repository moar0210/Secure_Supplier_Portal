<?php
$badge = static function (string $status): string {
    $status = strtoupper($status);

    return match ($status) {
        'PAID' => '<span style="padding:2px 6px;border:1px solid #080;background:#efe;">PAID</span>',
        'SENT' => '<span style="padding:2px 6px;border:1px solid #0a5;background:#e7f6ff;">SENT</span>',
        'OVERDUE' => '<span style="padding:2px 6px;border:1px solid #a00;background:#fee;">OVERDUE</span>',
        default => '<span style="padding:2px 6px;border:1px solid #888;background:#f6f6f6;">DRAFT</span>',
    };
};
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<h1>Admin - Invoices</h1>

<?php if ($notice): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        <?= h($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:18px;">
    <form method="post" style="padding:12px;border:1px solid #ccc;">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="generate">
        <strong>Generate Monthly Drafts</strong>
        <div style="margin-top:10px;">
            <label>Billing month</label><br>
            <input type="month" name="billing_month" value="<?= h((string)($filters['billing_month'] ?? date('Y-m'))) ?>">
        </div>
        <button type="submit" style="margin-top:10px;">Generate invoices</button>
    </form>

    <form method="post" style="padding:12px;border:1px solid #ccc;">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="check_overdue">
        <strong>Overdue Check</strong>
        <p style="margin:10px 0 0;">Marks sent invoices past due date as overdue.</p>
        <button type="submit" style="margin-top:10px;">Check overdue</button>
    </form>
</div>

<form method="get" style="margin-bottom:18px;padding:12px;border:1px solid #ccc;">
    <input type="hidden" name="page" value="admin_invoices">
    <strong>Filters</strong>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;">
        <div>
            <label>Status</label><br>
            <select name="status">
                <?php foreach (['ALL', 'DRAFT', 'SENT', 'PAID', 'OVERDUE'] as $status): ?>
                    <option value="<?= h($status) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $status ? 'selected' : '' ?>>
                        <?= h($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Billing month</label><br>
            <input type="month" name="billing_month" value="<?= h((string)($filters['billing_month'] ?? '')) ?>">
        </div>
        <div>
            <label>Supplier ID</label><br>
            <input type="number" name="supplier_id" min="1" step="1" value="<?= h((string)($filters['supplier_id'] ?? '')) ?>">
        </div>
        <div>
            <label>Invoice number</label><br>
            <input name="invoice_number" value="<?= h((string)($filters['invoice_number'] ?? '')) ?>">
        </div>
    </div>
    <button type="submit" style="margin-top:10px;">Apply filters</button>
    <a href="?page=admin_invoices" style="margin-left:10px;">Reset</a>
</form>

<?php if (!$rows): ?>
    <p>No invoices found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Supplier</th>
                <th>Billing Month</th>
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
                    <td><?= h((string)$row['issue_date']) ?></td>
                    <td><?= h((string)$row['due_date']) ?></td>
                    <td><?= $money($row['total_amount']) ?> <?= h((string)$row['currency_code']) ?></td>
                    <td>
                        <a href="?page=admin_invoice_view&id=<?= (int)$row['id'] ?>">Open</a>
                        |
                        <a href="?page=invoice_pdf&id=<?= (int)$row['id'] ?>">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
