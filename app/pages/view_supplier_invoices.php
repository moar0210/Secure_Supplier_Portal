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
<h1>My Invoices</h1>

<form method="get" style="margin-bottom:18px;padding:12px;border:1px solid #ccc;">
    <input type="hidden" name="page" value="supplier_invoices">
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
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
    </div>
    <button type="submit" style="margin-top:10px;">Apply filters</button>
    <a href="?page=supplier_invoices" style="margin-left:10px;">Reset</a>
</form>

<?php if (!$rows): ?>
    <p>No invoices available.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Billing Month</th>
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
                    <td><?= h((string)$row['issue_date']) ?></td>
                    <td><?= h((string)$row['due_date']) ?></td>
                    <td><?= $money($row['total_amount']) ?> <?= h((string)$row['currency_code']) ?></td>
                    <td><a href="?page=invoice_pdf&id=<?= (int)$row['id'] ?>">Download</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
