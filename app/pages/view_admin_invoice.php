<?php
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<h1>Invoice <?= h((string)$invoice['invoice_number']) ?></h1>

<p>
    <a href="?page=admin_invoices">Back to invoices</a>
    |
    <a href="?page=invoice_pdf&id=<?= (int)$invoice['id'] ?>">Download PDF</a>
</p>

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

<table>
    <tbody>
        <tr>
            <th>Supplier</th>
            <td><?= h((string)$invoice['supplier_name_snapshot']) ?> (#<?= (int)$invoice['supplier_id'] ?>)</td>
        </tr>
        <tr>
            <th>Contact</th>
            <td><?= h((string)$invoice['contact_person_snapshot']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= h((string)$invoice['supplier_email_snapshot']) ?></td>
        </tr>
        <tr>
            <th>VAT number</th>
            <td><?= h((string)$invoice['supplier_vat_number_snapshot']) ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td>
                <?= h(trim((string)$invoice['address_line_1_snapshot'] . ' ' . (string)$invoice['address_line_2_snapshot'])) ?><br>
                <?= h(trim((string)$invoice['postal_code_snapshot'] . ' ' . (string)$invoice['city_snapshot'])) ?><br>
                <?= h(trim((string)$invoice['region_snapshot'] . ' ' . (string)$invoice['country_code_snapshot'])) ?>
            </td>
        </tr>
        <tr>
            <th>Billing month</th>
            <td><?= sprintf('%04d-%02d', (int)$invoice['billing_year'], (int)$invoice['billing_month']) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><strong><?= h((string)$invoice['status']) ?></strong></td>
        </tr>
        <tr>
            <th>Issue date</th>
            <td><?= h((string)$invoice['issue_date']) ?></td>
        </tr>
        <tr>
            <th>Due date</th>
            <td><?= h((string)$invoice['due_date']) ?></td>
        </tr>
        <tr>
            <th>Pricing rule</th>
            <td><?= h((string)($invoice['pricing_rule_name'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Totals</th>
            <td>
                Subtotal: <?= $money($invoice['subtotal_amount']) ?> <?= h((string)$invoice['currency_code']) ?><br>
                VAT: <?= $money($invoice['vat_amount']) ?> <?= h((string)$invoice['currency_code']) ?> (<?= $money($invoice['vat_rate']) ?>%)<br>
                Total: <?= $money($invoice['total_amount']) ?> <?= h((string)$invoice['currency_code']) ?>
            </td>
        </tr>
    </tbody>
</table>

<h2>Line Items</h2>
<?php if (!$invoice['lines']): ?>
    <p>No line items found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Coverage</th>
                <th>Net</th>
                <th>VAT</th>
                <th>Gross</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['lines'] as $line): ?>
                <tr>
                    <td>
                        <?= h((string)$line['ad_title']) ?>
                        <?php if (!empty($line['line_type']) && (string)$line['line_type'] !== 'ADVERTISEMENT'): ?>
                            <br><span style="opacity:.75;"><?= h((string)$line['line_type']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= h((string)$line['description']) ?></td>
                    <td><?= h((string)$line['line_period_start']) ?> to <?= h((string)$line['line_period_end']) ?></td>
                    <td><?= $money($line['net_amount']) ?> <?= h((string)$invoice['currency_code']) ?></td>
                    <td><?= $money($line['vat_amount']) ?> <?= h((string)$invoice['currency_code']) ?></td>
                    <td><?= $money($line['gross_amount']) ?> <?= h((string)$invoice['currency_code']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ((string)$invoice['status'] === 'DRAFT'): ?>
    <h2>Send Invoice</h2>
    <form method="post">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="mark_sent">
        <button type="submit">Mark as sent</button>
    </form>

    <form method="post" style="margin-top:10px;" data-confirm="Delete this draft invoice?">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="delete_draft">
        <button type="submit">Delete draft invoice</button>
    </form>
<?php endif; ?>

<?php if (in_array((string)$invoice['status'], ['SENT', 'OVERDUE'], true)): ?>
    <h2>Record Payment</h2>
    <form method="post">
        <?= Csrf::input(); ?>
        <input type="hidden" name="action" value="record_payment">
        <div style="margin:10px 0;">
            <label>Amount</label><br>
            <input type="number" name="amount" min="0" step="0.01" required value="<?= h($money($invoice['total_amount'])) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Payment date</label><br>
            <input type="date" name="payment_date" required value="<?= h(date('Y-m-d')) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Method</label><br>
            <input name="payment_method" required maxlength="100" placeholder="Bank transfer">
        </div>
        <button type="submit">Record payment</button>
    </form>
<?php endif; ?>

<h2>Payment</h2>
<?php if (!$invoice['payment']): ?>
    <p>No payment recorded.</p>
<?php else: ?>
    <table>
        <tbody>
            <tr>
                <th>Date</th>
                <td><?= h((string)$invoice['payment']['payment_date']) ?></td>
            </tr>
            <tr>
                <th>Method</th>
                <td><?= h((string)$invoice['payment']['payment_method']) ?></td>
            </tr>
            <tr>
                <th>Amount</th>
                <td><?= $money($invoice['payment']['amount']) ?> <?= h((string)$invoice['currency_code']) ?></td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>

<h2>Status History</h2>
<?php if (!$invoice['status_history']): ?>
    <p>No history rows found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>From</th>
                <th>To</th>
                <th>By</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['status_history'] as $entry): ?>
                <tr>
                    <td><?= h((string)$entry['changed_at']) ?></td>
                    <td><?= h((string)($entry['old_status'] ?? '')) ?></td>
                    <td><?= h((string)$entry['new_status']) ?></td>
                    <td><?= h((string)($entry['username'] ?? '')) ?></td>
                    <td><?= h((string)($entry['note'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
