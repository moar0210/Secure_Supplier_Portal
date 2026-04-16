<?php
$filters = (array)$report['filters'];
$suppliers = (array)$report['suppliers'];
$users = (array)$report['users'];
$ads = (array)$report['ads'];
$invoices = (array)$report['invoices'];
$visibility = (array)$report['visibility'];
$series = (array)$report['series'];
$maxImpressions = 0;
foreach ($series as $row) {
    $maxImpressions = max($maxImpressions, (int)($row['impressions'] ?? 0));
}
?>
<h1>Admin Reports</h1>

<form method="get" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <input type="hidden" name="page" value="admin_reports">
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div>
            <label>Date from</label><br>
            <input type="date" name="date_from" value="<?= h((string)$filters['date_from']) ?>">
        </div>
        <div>
            <label>Date to</label><br>
            <input type="date" name="date_to" value="<?= h((string)$filters['date_to']) ?>">
        </div>
        <div>
            <label>Chart grouping</label><br>
            <select name="granularity">
                <option value="day" <?= (string)$filters['granularity'] === 'day' ? 'selected' : '' ?>>Daily</option>
                <option value="month" <?= (string)$filters['granularity'] === 'month' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
    </div>
    <button type="submit" style="margin-top:10px;">Apply filters</button>
    <a href="?page=admin_reports" style="margin-left:10px;">Reset</a>
</form>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px;">
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Suppliers</strong>
        <div style="margin-top:6px;">Total: <?= (int)($suppliers['total'] ?? 0) ?></div>
        <div>Active: <?= (int)($suppliers['active'] ?? 0) ?></div>
        <div>Inactive: <?= (int)($suppliers['inactive'] ?? 0) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Portal Users</strong>
        <div style="margin-top:6px;">Total: <?= (int)($users['total'] ?? 0) ?></div>
        <div>Active: <?= (int)($users['active'] ?? 0) ?></div>
        <div>Inactive: <?= (int)($users['inactive'] ?? 0) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Advertisements</strong>
        <div style="margin-top:6px;">Total: <?= (int)($ads['total'] ?? 0) ?></div>
        <div>Pending: <?= (int)($ads['pending'] ?? 0) ?></div>
        <div>Approved: <?= (int)($ads['approved'] ?? 0) ?></div>
        <div>Live: <?= (int)($ads['live'] ?? 0) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Invoices</strong>
        <div style="margin-top:6px;">Total: <?= (int)($invoices['total'] ?? 0) ?></div>
        <div>Paid total: <?= number_format((float)($invoices['paid_total'] ?? 0), 2) ?></div>
        <div>Outstanding: <?= number_format((float)($invoices['outstanding_total'] ?? 0), 2) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Visibility</strong>
        <div style="margin-top:6px;">Impressions: <?= (int)($visibility['impressions'] ?? 0) ?></div>
        <div>Clicks: <?= (int)($visibility['clicks'] ?? 0) ?></div>
        <div>CTR: <?= number_format((float)($visibility['ctr'] ?? 0), 2) ?>%</div>
    </div>
</div>

<h2>Visibility Chart</h2>
<?php if (!$series): ?>
    <p>No visibility data collected for the selected period yet.</p>
<?php else: ?>
    <div style="display:grid;gap:10px;margin-bottom:18px;">
        <?php foreach ($series as $row): ?>
            <?php
            $impressions = (int)($row['impressions'] ?? 0);
            $clicks = (int)($row['clicks'] ?? 0);
            $width = $maxImpressions > 0 ? max(4, (int)round(($impressions / $maxImpressions) * 100)) : 4;
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <strong><?= h((string)$row['label']) ?></strong>
                    <span><?= $impressions ?> impressions | <?= $clicks ?> clicks | <?= number_format((float)($row['ctr'] ?? 0), 2) ?>% CTR</span>
                </div>
                <div style="margin-top:4px;background:#eee;height:14px;position:relative;">
                    <div style="background:#246;height:14px;width:<?= $width ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
    <div>
        <h2>Top Ads</h2>
        <?php if (!$report['top_ads']): ?>
            <p>No advertisement statistics available yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Supplier</th>
                        <th>Impr.</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['top_ads'] as $row): ?>
                        <tr>
                            <td><?= h((string)$row['title']) ?></td>
                            <td><?= h((string)$row['supplier_name']) ?></td>
                            <td><?= (int)($row['impressions'] ?? 0) ?></td>
                            <td><?= (int)($row['clicks'] ?? 0) ?></td>
                            <td><?= number_format((float)($row['ctr'] ?? 0), 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div>
        <h2>Top Suppliers</h2>
        <?php if (!$report['top_suppliers']): ?>
            <p>No supplier visibility statistics available yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Impr.</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['top_suppliers'] as $row): ?>
                        <tr>
                            <td><?= h((string)$row['supplier_name']) ?></td>
                            <td><?= (int)($row['impressions'] ?? 0) ?></td>
                            <td><?= (int)($row['clicks'] ?? 0) ?></td>
                            <td><?= number_format((float)($row['ctr'] ?? 0), 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<h2>Recent Activity</h2>
<?php if (!$report['recent_activity']): ?>
    <p>No activity log entries available.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>Level</th>
                <th>Event</th>
                <th>Page</th>
                <th>User</th>
                <th>Supplier</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report['recent_activity'] as $row): ?>
                <tr>
                    <td><?= h((string)$row['created_at']) ?></td>
                    <td><?= h((string)$row['level']) ?></td>
                    <td><?= h((string)$row['event']) ?></td>
                    <td><?= h((string)($row['page'] ?? '')) ?></td>
                    <td>
                        <?= h((string)($row['username'] ?? '')) ?>
                        <?php if (!empty($row['user_id'])): ?>
                            (#<?= (int)$row['user_id'] ?>)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= h((string)($row['supplier_name'] ?? '')) ?>
                        <?php if (!empty($row['supplier_id'])): ?>
                            (#<?= (int)$row['supplier_id'] ?>)
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
