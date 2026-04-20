<?php $priceModels = AdsService::priceModelOptions(); ?>
<div class="page-header">
    <h1>Create ad</h1>
    <div class="page-header__actions">
        <a href="?page=ads_list" class="muted small">&larr; Back to my ads</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off" class="form-stack ad-form">
    <?= Csrf::input(); ?>

    <div class="field">
        <label>Title</label>
        <input name="title" required maxlength="200" value="<?= h($form['title']) ?>">
    </div>

    <div class="field">
        <label>Description</label>
        <textarea name="description" required maxlength="5000" rows="6"><?= h($form['description']) ?></textarea>
    </div>

    <div class="field">
        <label>Category</label>
        <select name="category_id">
            <option value="">-</option>
            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <option value="<?= $categoryId ?>" <?= (string)$categoryId === $form['category_id'] ? 'selected' : '' ?>>
                    <?= h((string)$category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Price model</label>
        <select name="price_model_type">
            <option value="">Select a model</option>
            <?php foreach ($priceModels as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $form['price_model_type'] === $value ? 'selected' : '' ?>>
                    <?= h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Offer details</label>
        <input name="price_text" maxlength="200" value="<?= h($form['price_text']) ?>">
    </div>

    <div class="field-row">
        <div class="field">
            <label>Valid from</label>
            <input type="date" name="valid_from" value="<?= h($form['valid_from']) ?>">
        </div>
        <div class="field">
            <label>Valid to</label>
            <input type="date" name="valid_to" value="<?= h($form['valid_to']) ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" name="action" value="save_draft" class="btn-secondary">Save draft</button>
        <button type="submit" name="action" value="submit">Submit for approval</button>
        <a href="?page=ads_list" class="muted small">Cancel</a>
    </div>
</form>
