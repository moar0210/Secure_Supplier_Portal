<div class="page-header">
    <h1>Categories</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<h2>Create category</h2>
<form method="post" class="card mb-5">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <div class="field-row">
        <div class="field">
            <label>Name</label>
            <input name="name" required maxlength="100" placeholder="Category name">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit">Create category</button>
    </div>
</form>

<h2>Existing categories</h2>
<?php if (!$categories): ?>
    <div class="card card--muted"><p class="mb-0 muted">No categories yet.</p></div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= (int)$category['id'] ?></td>
                    <td><?= h((string)$category['name']) ?></td>
                    <td class="actions-inline">
                        <a href="?page=admin_category_edit&amp;id=<?= (int)$category['id'] ?>">Rename</a>
                        <form method="post" class="inline-form" data-confirm="Delete this category?">
                            <?= Csrf::input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
