<?php
$priceModels = AdsService::priceModelOptions();
$statusClass = match (strtoupper($status)) {
    'APPROVED' => 'badge--approved',
    'PENDING' => 'badge--pending',
    'REJECTED' => 'badge--rejected',
    default => 'badge--draft',
};
?>
<div class="page-header">
    <h1>Edit ad #<?= $adId ?></h1>
    <div class="page-header__actions">
        <a href="?page=ads_list" class="muted small">&larr; Back to my ads</a>
    </div>
</div>

<p>
    Status: <span class="badge <?= $statusClass ?>"><?= h($status) ?></span>
</p>

<?php if (!empty($ad['rejection_reason'])): ?>
    <div class="alert alert--error">
        <strong>Rejection reason:</strong> <?= h((string)$ad['rejection_reason']) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($isLocked): ?>
    <div class="alert alert--warning">
        This ad is <strong>PENDING</strong> review and cannot be edited right now.
    </div>
<?php endif; ?>

<form method="post" autocomplete="off" class="form-stack">
    <?= Csrf::input(); ?>

    <div class="field">
        <label>Title</label>
        <input name="title" required maxlength="200" value="<?= h((string)$ad['title']) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div class="field">
        <label>Description</label>
        <textarea name="description" required maxlength="5000" rows="6" <?= $isLocked ? 'disabled' : '' ?>><?= h((string)$ad['description']) ?></textarea>
    </div>

    <div class="field">
        <label>Category</label>
        <select name="category_id" <?= $isLocked ? 'disabled' : '' ?>>
            <option value="">-</option>
            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (int)$category['id']; ?>
                <option value="<?= $categoryId ?>" <?= (int)($ad['category_id'] ?? 0) === $categoryId ? 'selected' : '' ?>>
                    <?= h((string)$category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Price model</label>
        <select name="price_model_type" <?= $isLocked ? 'disabled' : '' ?>>
            <option value="">Select a model</option>
            <?php foreach ($priceModels as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= (string)($ad['price_model_type'] ?? '') === $value ? 'selected' : '' ?>>
                    <?= h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label>Offer details</label>
        <input name="price_text" maxlength="200" value="<?= h((string)($ad['price_text'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div class="field-row">
        <div class="field">
            <label>Valid from</label>
            <input type="date" name="valid_from" value="<?= h((string)($ad['valid_from'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
            <label>Valid to</label>
            <input type="date" name="valid_to" value="<?= h((string)($ad['valid_to'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
        </div>
    </div>

    <div class="form-actions">
        <?php if (!$isLocked): ?>
            <button type="submit" name="action" value="save_draft" class="btn-secondary">Save (draft)</button>
            <button type="submit" name="action" value="save_submit">Save &amp; submit</button>
        <?php endif; ?>

        <a href="?page=ads_list" class="muted small">Back</a>
    </div>

    <?php if ($status === 'APPROVED'): ?>
        <p class="muted small mt-3">
            Note: editing an APPROVED ad will typically re-submit it for review (APPROVED &rarr; PENDING).
        </p>
    <?php endif; ?>
</form>
