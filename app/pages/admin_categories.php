<?php

declare(strict_types=1);

$auth->requireRole('ADMIN');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = (string)($_POST['name'] ?? '');
            $adsService->createCategory($name);
            header("Location: ?page=admin_categories");
            exit;
        }

        if ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            if (!ctype_digit($id)) {
                throw new RuntimeException('Invalid category id.');
            }
            $adsService->deleteCategory((int)$id);
            header("Location: ?page=admin_categories");
            exit;
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$cats = $adsService->listCategories();
?>

<h1>Admin – Categories</h1>

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

<?php if (!$cats): ?>
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
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= h((string)$c['name']) ?></td>
                    <td>
                        <a href="?page=admin_category_edit&id=<?= (int)$c['id'] ?>">Rename</a>

                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                            <?= Csrf::input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>