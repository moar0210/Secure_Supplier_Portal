<?php $priceModels = AdsService::priceModelOptions(); ?>
<h1>Create Ad</h1>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?= Csrf::input(); ?>

    <div style="margin:10px 0;">
        <label>Title</label><br>
        <input name="title" required maxlength="200" style="width:520px;" value="<?= h($form['title']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Description</label><br>
        <textarea name="description" required maxlength="5000" rows="6" style="width:520px;"><?= h($form['description']) ?></textarea>
    </div>

    <div style="margin:10px 0;">
        <label>Category</label><br>
        <select name="category_id" style="width:520px;">
            <option value="">-</option>
            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <option value="<?= $categoryId ?>" <?= (string)$categoryId === $form['category_id'] ? 'selected' : '' ?>>
                    <?= h((string)$category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:10px 0;">
        <label>Price model</label><br>
        <select name="price_model_type" style="width:520px;">
            <option value="">Select a model</option>
            <?php foreach ($priceModels as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $form['price_model_type'] === $value ? 'selected' : '' ?>>
                    <?= h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:10px 0;">
        <label>Offer details</label><br>
        <input name="price_text" maxlength="200" style="width:520px;" value="<?= h($form['price_text']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Valid from</label><br>
        <input type="date" name="valid_from" value="<?= h($form['valid_from']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Valid to</label><br>
        <input type="date" name="valid_to" value="<?= h($form['valid_to']) ?>">
    </div>

    <div style="margin-top:14px;">
        <button type="submit" name="action" value="save_draft">Save Draft</button>
        <button type="submit" name="action" value="submit">Submit for approval</button>
        <a href="?page=ads_list" style="margin-left:10px;">Cancel</a>
    </div>
</form>
