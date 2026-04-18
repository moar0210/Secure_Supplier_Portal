<div class="page-header">
    <h1><?= h((string)$ad['title']) ?></h1>
    <div class="page-header__actions">
        <a href="?page=marketplace" class="muted small">&larr; Back to marketplace</a>
    </div>
</div>

<div class="section-grid">
    <div>
        <table>
            <tbody>
                <tr>
                    <th>Supplier</th>
                    <td><?= h((string)$ad['supplier_name']) ?></td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td><?= h((string)($ad['category_name'] ?? 'Uncategorized')) ?></td>
                </tr>
                <tr>
                    <th>Validity</th>
                    <td>
                        <?= h((string)($ad['valid_from'] ?: 'now')) ?> &rarr; <?= h((string)($ad['valid_to'] ?: 'open-ended')) ?>
                    </td>
                </tr>
                <?php if (!empty($ad['price_model_type'])): ?>
                    <tr>
                        <th>Price model</th>
                        <td><?= h(AdsService::priceModelLabel((string)$ad['price_model_type'])) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($ad['price_text'])): ?>
                    <tr>
                        <th>Offer</th>
                        <td><?= h((string)$ad['price_text']) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($ad['homepage'])): ?>
                    <tr>
                        <th>Supplier website</th>
                        <td><a href="<?= h((string)$ad['homepage']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$ad['homepage']) ?></a></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h2 class="mt-0">Description</h2>
        <div class="card">
            <p class="mb-0" style="white-space:pre-wrap;"><?= h((string)$ad['description']) ?></p>
        </div>
    </div>
</div>
