<h1>Edit Ad #<?= $adId ?></h1>

<p>
    Status: <strong><?= h($status) ?></strong>
    <?php if (!empty($ad['rejection_reason'])): ?>
        <br>Rejection reason: <span style="color:#a00;"><?= h((string)$ad['rejection_reason']) ?></span>
    <?php endif; ?>
</p>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:10px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if ($isLocked): ?>
    <div style="padding:8px;border:1px solid #aa0;background:#fffbdd;margin-bottom:10px;">
        This ad is <strong>PENDING</strong> review and cannot be edited right now.
    </div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?= Csrf::input(); ?>

    <div style="margin:10px 0;">
        <label>Title</label><br>
        <input name="title" required maxlength="200" style="width:520px;" value="<?= h((string)$ad['title']) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Description</label><br>
        <textarea name="description" required maxlength="5000" rows="6" style="width:520px;" <?= $isLocked ? 'disabled' : '' ?>><?= h((string)$ad['description']) ?></textarea>
    </div>

    <div style="margin:10px 0;">
        <label>Category</label><br>
        <select name="category_id" style="width:520px;" <?= $isLocked ? 'disabled' : '' ?>>
            <option value="">-</option>
            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <option value="<?= $categoryId ?>" <?= (int)($ad['category_id'] ?? 0) === $categoryId ? 'selected' : '' ?>>
                    <?= h((string)$category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:10px 0;">
        <label>Price text</label><br>
        <input name="price_text" maxlength="200" style="width:520px;" value="<?= h((string)($ad['price_text'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Valid from</label><br>
        <input type="date" name="valid_from" value="<?= h((string)($ad['valid_from'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Valid to</label><br>
        <input type="date" name="valid_to" value="<?= h((string)($ad['valid_to'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin-top:14px;">
        <?php if (!$isLocked): ?>
            <button type="submit" name="action" value="save_draft">Save (Draft)</button>
            <button type="submit" name="action" value="save_submit">Save + Submit</button>
        <?php endif; ?>

        <a href="?page=ads_list" style="margin-left:10px;">Back</a>
    </div>

    <?php if ($status === 'APPROVED'): ?>
        <p style="margin-top:10px;opacity:.85;">
            Note: Editing an APPROVED ad will typically re-submit it for review (APPROVED -> PENDING).
        </p>
    <?php endif; ?>
</form>
