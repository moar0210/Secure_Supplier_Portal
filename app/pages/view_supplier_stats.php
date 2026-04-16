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
<h1>My Statistics</h1>

<p>Visibility metrics are collected automatically from the public marketplace listing and advertisement detail views.</p>

<form method="get" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <input type="hidden" name="page" value="supplier_stats">
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
    <a href="?page=supplier_stats" style="margin-left:10px;">Reset</a>
</form>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px;">
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Impressions</strong>
        <div style="font-size:28px;margin-top:6px;"><?= (int)($summary['impressions'] ?? 0) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>Clicks</strong>
        <div style="font-size:28px;margin-top:6px;"><?= (int)($summary['clicks'] ?? 0) ?></div>
    </div>
    <div style="border:1px solid #ccc;padding:12px;background:#fafafa;">
        <strong>CTR</strong>
        <div style="font-size:28px;margin-top:6px;"><?= number_format((float)($summary['ctr'] ?? 0), 2) ?>%</div>
    </div>
</div>

<h2>Visibility Chart</h2>
<?php if (!$series): ?>
    <p>No statistics collected for the selected period yet.</p>
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
                    <div style="background:#3a7;height:14px;width:<?= $width ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Per Advertisement</h2>
<?php if (!$ads): ?>
    <p>No advertisements found for this supplier.</p>
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
                    <td><?= !empty($row['is_active']) ? 'yes' : 'no' ?></td>
                    <td><?= (int)($row['impressions'] ?? 0) ?></td>
                    <td><?= (int)($row['clicks'] ?? 0) ?></td>
                    <td><?= number_format((float)($row['ctr'] ?? 0), 2) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
