<?php
$filters = (array)$dashboard['filters'];
$summary = (array)$dashboard['summary'];
$series = (array)$dashboard['series'];
$ads = (array)$dashboard['ads'];
$maxImpressions = 0;
foreach ($series as $row) {
    $maxImpressions = max($maxImpressions, (int)($row['impressions'] ?? 0));
}
?>
<div class="page-header">
    <h1>My statistics</h1>
</div>

<p class="muted">Visibility metrics are collected automatically from the public marketplace listing and advertisement detail views.</p>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="supplier_stats">
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
        <a href="?page=supplier_stats" class="muted small">Reset</a>
    </div>
</form>

<div class="card-grid">
    <div class="stat-card">
        <div class="stat-card__label">Impressions</div>
        <div class="stat-card__value"><?= (int)($summary['impressions'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Clicks</div>
        <div class="stat-card__value"><?= (int)($summary['clicks'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">CTR</div>
        <div class="stat-card__value"><?= number_format((float)($summary['ctr'] ?? 0), 2) ?>%</div>
    </div>
</div>

<h2>Visibility chart</h2>
<?php if (!$series): ?>
    <div class="card card--muted"><p class="mb-0 muted">No statistics collected for the selected period yet.</p></div>
<?php else: ?>
    <div class="chart-list">
        <?php foreach ($series as $row): ?>
            <?php
            $impressions = (int)($row['impressions'] ?? 0);
            $clicks = (int)($row['clicks'] ?? 0);
            $width = $maxImpressions > 0 ? max(4, (int)round(($impressions / $maxImpressions) * 100)) : 4;
            ?>
            <div class="chart-list__row">
                <div class="chart-list__header">
                    <strong><?= h((string)$row['label']) ?></strong>
                    <span><?= $impressions ?> impressions &middot; <?= $clicks ?> clicks &middot; <?= number_format((float)($row['ctr'] ?? 0), 2) ?>% CTR</span>
                </div>
                <div class="chart-list__bar">
                    <div class="chart-list__fill" style="width:<?= $width ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Per advertisement</h2>
<?php if (!$ads): ?>
    <div class="card card--muted"><p class="mb-0 muted">No advertisements found for this supplier.</p></div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Ad</th>
                <th>Status</th>
                <th>Active</th>
                <th>Impressions</th>
                <th>Clicks</th>
                <th>CTR</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ads as $row): ?>
                <tr>
                    <td><?= h((string)$row['title']) ?></td>
                    <td><?= h((string)$row['status']) ?></td>
                    <td><?= !empty($row['is_active']) ? 'Yes' : 'No' ?></td>
                    <td><?= (int)($row['impressions'] ?? 0) ?></td>
                    <td><?= (int)($row['clicks'] ?? 0) ?></td>
                    <td><?= number_format((float)($row['ctr'] ?? 0), 2) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
