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
<div class="page-header">
    <h1>Reports</h1>
</div>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="admin_reports">
    <div class="filter-bar__title">Filters</div>
    <div class="field-row">
        <div class="field">
            <label>Date from</label>
            <input type="date" name="date_from" value="<?= h((string)$filters['date_from']) ?>">
        </div>
        <div class="field">
            <label>Date to</label>
            <input type="date" name="date_to" value="<?= h((string)$filters['date_to']) ?>">
        </div>
        <div class="field">
            <label>Chart grouping</label>
            <select name="granularity">
                <option value="day" <?= (string)$filters['granularity'] === 'day' ? 'selected' : '' ?>>Daily</option>
                <option value="month" <?= (string)$filters['granularity'] === 'month' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <a href="?page=admin_reports" class="muted small">Reset</a>
    </div>
</form>

<div class="card-grid mb-5">
    <div class="stat-card">
        <div class="stat-card__label">Suppliers</div>
        <div class="stat-card__value"><?= (int)($suppliers['total'] ?? 0) ?></div>
        <div class="stat-card__hint">Active <?= (int)($suppliers['active'] ?? 0) ?> &middot; Inactive <?= (int)($suppliers['inactive'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Portal users</div>
        <div class="stat-card__value"><?= (int)($users['total'] ?? 0) ?></div>
        <div class="stat-card__hint">Active <?= (int)($users['active'] ?? 0) ?> &middot; Inactive <?= (int)($users['inactive'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Advertisements</div>
        <div class="stat-card__value"><?= (int)($ads['total'] ?? 0) ?></div>
        <div class="stat-card__hint">Pending <?= (int)($ads['pending'] ?? 0) ?> &middot; Approved <?= (int)($ads['approved'] ?? 0) ?> &middot; Live <?= (int)($ads['live'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Invoices</div>
        <div class="stat-card__value"><?= (int)($invoices['total'] ?? 0) ?></div>
        <div class="stat-card__hint">Paid <?= number_format((float)($invoices['paid_total'] ?? 0), 2) ?> &middot; Outstanding <?= number_format((float)($invoices['outstanding_total'] ?? 0), 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Visibility</div>
        <div class="stat-card__value"><?= (int)($visibility['impressions'] ?? 0) ?></div>
        <div class="stat-card__hint">Clicks <?= (int)($visibility['clicks'] ?? 0) ?> &middot; CTR <?= number_format((float)($visibility['ctr'] ?? 0), 2) ?>%</div>
    </div>
</div>

<h2>Visibility chart</h2>
<?php if (!$series): ?>
    <div class="card card--muted"><p class="mb-0 muted">No visibility data collected for the selected period yet.</p></div>
<?php else: ?>
    <div class="chart-list mb-5">
        <?php foreach ($series as $row): ?>
            <?php
            $impressions = (int)($row['impressions'] ?? 0);
            $clicks = (int)($row['clicks'] ?? 0);
            $width = $maxImpressions > 0 ? max(4, (int)round(($impressions / $maxImpressions) * 100)) : 4;
            ?>
            <div class="chart-list__row">
                <div class="chart-list__header">
                    <strong><?= h((string)$row['label']) ?></strong>
                    <span class="muted small"><?= $impressions ?> impressions &middot; <?= $clicks ?> clicks &middot; <?= number_format((float)($row['ctr'] ?? 0), 2) ?>% CTR</span>
                </div>
                <div class="chart-list__bar">
                    <div class="chart-list__fill" style="width: <?= $width ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="section-grid">
    <div>
        <h2>Top ads</h2>
        <?php if (!$report['top_ads']): ?>
            <div class="card card--muted"><p class="mb-0 muted">No advertisement statistics available yet.</p></div>
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
        <h2>Top suppliers</h2>
        <?php if (!$report['top_suppliers']): ?>
            <div class="card card--muted"><p class="mb-0 muted">No supplier visibility statistics available yet.</p></div>
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

<h2>Recent activity</h2>
<?php if (!$report['recent_activity']): ?>
    <div class="card card--muted"><p class="mb-0 muted">No activity log entries available.</p></div>
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
                    <td class="muted small"><?= h((string)$row['created_at']) ?></td>
                    <td><?= h((string)$row['level']) ?></td>
                    <td><?= h((string)$row['event']) ?></td>
                    <td><?= h((string)($row['page'] ?? '')) ?></td>
                    <td>
                        <?= h((string)($row['username'] ?? '')) ?>
                        <?php if (!empty($row['user_id'])): ?>
                            <span class="muted small">(#<?= (int)$row['user_id'] ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= h((string)($row['supplier_name'] ?? '')) ?>
                        <?php if (!empty($row['supplier_id'])): ?>
                            <span class="muted small">(#<?= (int)$row['supplier_id'] ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
