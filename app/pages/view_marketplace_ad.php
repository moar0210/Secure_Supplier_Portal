<h1><?= h((string)$ad['title']) ?></h1>

<p>
    <a href="?page=marketplace">Back to marketplace</a>
</p>

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
                <?= h((string)($ad['valid_from'] ?: 'now')) ?>
                to
                <?= h((string)($ad['valid_to'] ?: 'open-ended')) ?>
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

<h2>Description</h2>
<p style="white-space:pre-wrap;"><?= h((string)$ad['description']) ?></p>
