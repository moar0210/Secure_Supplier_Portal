<div class="page-header">
    <h1>Rename category #<?= (int)$categoryId ?></h1>
    <div class="page-header__actions">
        <a href="?page=admin_categories" class="muted small">&larr; Back to categories</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
    <?= Csrf::input(); ?>
    <div class="field-row">
        <div class="field">
            <label>Name</label>
            <input name="name" required maxlength="100" value="<?= h((string)$current['name']) ?>">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit">Save changes</button>
        <a href="?page=admin_categories" class="muted small">Cancel</a>
    </div>
</form>
