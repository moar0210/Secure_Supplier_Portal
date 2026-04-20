<div class="page-header">
    <div class="page-header__title-group">
        <h1>Categories</h1>
        <p class="page-header__subtitle">Maintain the advertisement categories visible to suppliers and the shop.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" data-collapsible-target="createCategoryPanel" aria-expanded="false">
            + Create category
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<div class="collapsible" id="createCategoryPanel">
    <div class="modal__header">
        <h2 class="modal__title">Create category</h2>
        <button type="button" class="modal__close" data-collapsible-close="createCategoryPanel" aria-label="Close">&times;</button>
    </div>
    <div class="collapsible__body">
        <form method="post">
            <?= Csrf::input(); ?>
            <input type="hidden" name="action" value="create">
            <div class="field-row">
                <div class="field" style="flex:1 1 320px;">
                    <label>Name</label>
                    <input name="name" required maxlength="100" placeholder="Category name">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Create category</button>
                <button type="button" class="btn-secondary" data-collapsible-close="createCategoryPanel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$categories): ?>
    <div class="card card--muted"><p class="mb-0 muted">No categories yet.</p></div>
<?php else: ?>
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th style="width:80px;">ID</th>
                    <th>Name</th>
                    <th style="width:240px;" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td class="muted"><?= (int)$category['id'] ?></td>
                        <td><strong><?= h((string)$category['name']) ?></strong></td>
                        <td class="text-right">
                            <div class="actions-inline" style="justify-content:flex-end;">
                                <a href="?page=admin_category_edit&amp;id=<?= (int)$category['id'] ?>" class="btn-secondary" style="padding:6px 12px;border-radius:8px;border:1px solid var(--color-border-strong);text-decoration:none;color:var(--color-text);">Rename</a>
                                <form method="post" class="inline-form" data-confirm="Delete this category?">
                                    <?= Csrf::input(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
