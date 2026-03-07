<?php

declare(strict_types=1);

$auth->requireRole('SUPPLIER');

$supplierId = $auth->supplierId();
if ($supplierId === null) {
    header("Location: ?page=403");
    exit;
}

$rows = $adsService->listForSupplier($supplierId);

function badge(string $s): string
{
    $s = strtoupper($s);
    return match ($s) {
        'APPROVED' => '<span style="padding:2px 6px;border:1px solid #080;background:#efe;">APPROVED</span>',
        'PENDING'  => '<span style="padding:2px 6px;border:1px solid #aa0;background:#fffbdd;">PENDING</span>',
        'REJECTED' => '<span style="padding:2px 6px;border:1px solid #a00;background:#fee;">REJECTED</span>',
        default    => '<span style="padding:2px 6px;border:1px solid #888;background:#f6f6f6;">DRAFT</span>',
    };
}
?>

<h1>My Ads</h1>

<p>
    <a href="?page=ad_create">+ Create new ad</a>
</p>

<?php if (!$rows): ?>
    <p>No ads yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Title</th>
                <th>Status</th>
                <th>Active</th>
                <th>Updated</th>
                <th style="width:260px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $id = (int)$r['id'];
                $status = (string)$r['status'];
                $isActive = !empty($r['is_active']);
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><?= h((string)($r['category_name'] ?? '—')) ?></td>
                    <td><?= h((string)$r['title']) ?></td>
                    <td><?= badge($status) ?></td>
                    <td><?= $isActive ? 'yes' : 'no' ?></td>
                    <td><?= h((string)$r['updated_at']) ?></td>
                    <td>
                        <a href="?page=ad_edit&id=<?= $id ?>">Edit / View</a>

                        <?php if (strtoupper($status) === 'APPROVED'): ?>
                            <form method="post" action="?page=ad_toggle" style="display:inline;">
                                <?= Csrf::input(); ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                                <button type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        <?php elseif (in_array(strtoupper($status), ['DRAFT','REJECTED'], true)): ?>
                            <form method="post" action="?page=ad_edit&id=<?= $id ?>" style="display:inline;">
                                <?= Csrf::input(); ?>
                                <input type="hidden" name="action" value="submit">
                                <button type="submit">Submit</button>
                            </form>
                        <?php else: ?>
                            <span style="opacity:.8;">(Waiting review)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>