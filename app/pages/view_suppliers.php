<?php
$filters = (array)($filters ?? []);
$pagination = (array)($pagination ?? []);
$searchTerm = trim((string)($filters['search'] ?? ''));
$pageNo = max(1, (int)($pagination['page_no'] ?? 1));
$totalPages = max(1, (int)($pagination['total_pages'] ?? 1));
$total = max(0, (int)($pagination['total'] ?? count((array)$rows)));
$perPage = max(1, (int)($pagination['per_page'] ?? 50));
$firstItem = $total === 0 ? 0 : (($pageNo - 1) * $perPage) + 1;
$lastItem = $total === 0 ? 0 : min($total, $firstItem + count((array)$rows) - 1);
$baseQuery = 'page=suppliers' . ($searchTerm !== '' ? '&search=' . rawurlencode($searchTerm) : '');
?>
<div class="page-header">
    <div class="page-header__title-group">
        <h1>Suppliers</h1>
        <p class="page-header__subtitle">
            Showing <?= $firstItem ?>-<?= $lastItem ?> of <?= $total ?> suppliers.
        </p>
    </div>
    <div class="page-header__actions">
        <a href="?page=supplier_create"><button type="button">+ Create supplier</button></a>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="suppliers">
    <div class="field-row">
        <div class="field" style="flex: 2 1 320px;">
            <label>Search</label>
            <input name="search" value="<?= h($searchTerm) ?>" placeholder="ID, name, homepage">
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <?php if ($searchTerm !== ''): ?>
            <a href="?page=suppliers" class="muted small">Reset</a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No suppliers found.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier name</th>
                <th>Short name</th>
                <th>Email</th>
                <th>Homepage</th>
                <th>Status</th>
                <th>Users</th>
                <th>Ads</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $sid = (int)$row['id_supplier'];
                $isInactive = !empty($row['is_inactive']);
                ?>
                <tr>
                    <td><?= $sid ?></td>
                    <td>
                        <a href="?page=supplier&amp;id=<?= $sid ?>">
                            <?= h((string)$row['supplier_name']) ?>
                        </a>
                    </td>
                    <td><?= h((string)$row['short_name']) ?></td>
                    <td><?= h((string)$row['email']) ?></td>
                    <td><?= h((string)$row['homepage']) ?></td>
                    <td>
                        <span class="badge <?= $isInactive ? 'badge--pending' : 'badge--approved' ?>">
                            <?= $isInactive ? 'Inactive' : 'Active' ?>
                        </span>
                    </td>
                    <td><?= (int)($row['portal_user_count'] ?? 0) ?></td>
                    <td><?= (int)($row['ad_count'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($pageNo > 1): ?>
                <a href="?<?= h($baseQuery . '&page_no=' . ($pageNo - 1)) ?>">Previous</a>
            <?php else: ?>
                <span class="pagination__disabled">Previous</span>
            <?php endif; ?>

            <span class="pagination__status">Page <?= $pageNo ?> of <?= $totalPages ?></span>

            <?php if ($pageNo < $totalPages): ?>
                <a href="?<?= h($baseQuery . '&page_no=' . ($pageNo + 1)) ?>">Next</a>
            <?php else: ?>
                <span class="pagination__disabled">Next</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
