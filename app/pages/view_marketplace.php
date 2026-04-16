<h1>Marketplace</h1>

<p>Browse currently approved and active supplier advertisements published in the portal.</p>

<form method="get" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <input type="hidden" name="page" value="marketplace">
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div>
            <label>Search</label><br>
            <input name="search" value="<?= h((string)$filters['search']) ?>" placeholder="Title, description, supplier">
        </div>
        <div>
            <label>Category</label><br>
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
    <button type="submit" style="margin-top:10px;">Apply filters</button>
    <a href="?page=marketplace" style="margin-left:10px;">Reset</a>
</form>

<?php if (!$rows): ?>
    <p>No advertisements match the current filters.</p>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
        <?php foreach ($rows as $row): ?>
            <article style="border:1px solid #ccc;padding:16px;background:#fafafa;">
                <div style="opacity:.8;font-size:14px;">
                    <?= h((string)($row['category_name'] ?? 'Uncategorized')) ?>
                </div>
                <h2 style="margin:8px 0 6px;">
                    <a href="?page=marketplace_ad&id=<?= (int)$row['id'] ?>">
                        <?= h((string)$row['title']) ?>
                    </a>
                </h2>
                <div style="margin-bottom:8px;opacity:.85;">
                    Supplier: <?= h((string)$row['supplier_name']) ?>
                </div>
                <p style="margin:0 0 10px;">
                    <?= h(mb_substr((string)$row['description'], 0, 180)) ?>
                    <?php if (mb_strlen((string)$row['description']) > 180): ?>...<?php endif; ?>
                </p>
                <?php if (!empty($row['price_model_type'])): ?>
                    <div style="margin-bottom:6px;"><strong>Price model:</strong> <?= h(AdsService::priceModelLabel((string)$row['price_model_type'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($row['price_text'])): ?>
                    <div style="margin-bottom:8px;"><strong>Offer:</strong> <?= h((string)$row['price_text']) ?></div>
                <?php endif; ?>
                <div style="opacity:.8;font-size:14px;">
                    Valid:
                    <?= h((string)($row['valid_from'] ?: 'now')) ?>
                    to
                    <?= h((string)($row['valid_to'] ?: 'open-ended')) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
