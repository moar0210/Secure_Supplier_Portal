<h1>Admin - Categories</h1>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<h2>Create category</h2>
<form method="post" style="margin-bottom:16px;">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <input name="name" required placeholder="Category name" style="width:320px;">
    <button type="submit">Create</button>
</form>

<h2>Existing categories</h2>

<?php if (!$categories): ?>
    <p>No categories yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th style="width:220px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= (int)$category['id'] ?></td>
                    <td><?= h((string)$category['name']) ?></td>
                    <td>
                        <a href="?page=admin_category_edit&id=<?= (int)$category['id'] ?>">Rename</a>

                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                            <?= Csrf::input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
