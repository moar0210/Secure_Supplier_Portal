<?php

declare(strict_types=1);

$auth->requireRole('ADMIN');

$status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
$allowed = ['PENDING', 'APPROVED', 'REJECTED', 'DRAFT', 'ALL'];
if (!in_array($status, $allowed, true)) {
    $status = 'PENDING';
}

try {
    $rows = ($status === 'ALL')
        ? $adsService->adminQueue(null)
        : $adsService->adminQueue($status);
} catch (Throwable $e) {
    $rows = [];
    $err = $e->getMessage();
}
?>

<h1>Admin – Ads Queue</h1>

<p>
    Filter:
    <?php foreach ($allowed as $s): ?>
        <a href="?page=admin_ads_queue&status=<?= h($s) ?>"
           style="margin-right:10px;<?= $s === $status ? 'font-weight:bold;text-decoration:underline;' : '' ?>">
            <?= h($s) ?>
        </a>
    <?php endforeach; ?>
</p>

<?php if (!empty($err)): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;">
        <?= h($err) ?>
    </div>
<?php endif; ?>

<?php if (!$rows): ?>
    <p>No ads found for this filter.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier</th>
                <th>Category</th>
                <th>Title</th>
                <th>Status</th>
                <th>Active</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= h((string)($r['supplier_name'] ?? '')) ?> (#<?= (int)$r['supplier_id'] ?>)</td>
                    <td><?= h((string)($r['category_name'] ?? '—')) ?></td>
                    <td><?= h((string)$r['title']) ?></td>
                    <td><?= h((string)$r['status']) ?></td>
                    <td><?= !empty($r['is_active']) ? 'yes' : 'no' ?></td>
                    <td><?= h((string)$r['updated_at']) ?></td>
                    <td>
                        <a href="?page=admin_ad_review&id=<?= (int)$r['id'] ?>">Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>