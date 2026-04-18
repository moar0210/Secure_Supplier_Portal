<div class="page-header">
    <h1>Marketplace</h1>
</div>

<p class="muted">Browse currently approved and active supplier advertisements published in the portal.</p>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="marketplace">
    <div class="filter-bar__title">Filters</div>
    <div class="field-row">
        <div class="field">
            <label>Search</label>
            <input name="search" value="<?= h((string)$filters['search']) ?>" placeholder="Title, description, supplier">
        </div>
        <div class="field">
            <label>Category</label>
            <select name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <?php $categoryId = (int)$category['id']; ?>
                    <option value="<?= $categoryId ?>" <?= (string)$categoryId === (string)$filters['category_id'] ? 'selected' : '' ?>>
                        <?= h((string)$category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <a href="?page=marketplace" class="muted small">Reset</a>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No advertisements match the current filters.</p>
    </div>
<?php else: ?>
    <div class="marketplace-grid">
        <?php foreach ($rows as $row): ?>
            <article class="ad-card">
                <div class="ad-card__category">
                    <?= h((string)($row['category_name'] ?? 'Uncategorized')) ?>
                </div>
                <h2 class="ad-card__title">
                    <a href="?page=marketplace_ad&amp;id=<?= (int)$row['id'] ?>">
                        <?= h((string)$row['title']) ?>
                    </a>
                </h2>
                <div class="ad-card__supplier">
                    <?= h((string)$row['supplier_name']) ?>
                </div>
                <p class="ad-card__description">
                    <?= h(mb_substr((string)$row['description'], 0, 180)) ?><?php if (mb_strlen((string)$row['description']) > 180): ?>&hellip;<?php endif; ?>
                </p>
                <?php if (!empty($row['price_model_type'])): ?>
                    <div class="ad-card__meta"><strong>Price model:</strong> <?= h(AdsService::priceModelLabel((string)$row['price_model_type'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($row['price_text'])): ?>
                    <div class="ad-card__meta"><strong>Offer:</strong> <?= h((string)$row['price_text']) ?></div>
                <?php endif; ?>
                <div class="ad-card__footer">
                    Valid: <?= h((string)($row['valid_from'] ?: 'now')) ?> &rarr; <?= h((string)($row['valid_to'] ?: 'open-ended')) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
