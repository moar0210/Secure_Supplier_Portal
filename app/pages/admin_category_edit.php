<?php

declare(strict_types=1);

$auth->requireRole('ADMIN');

$idRaw = $_GET['id'] ?? null;
if (!$idRaw || !ctype_digit((string)$idRaw)) {
    header("Location: ?page=404");
    exit;
}
$catId = (int)$idRaw;

$error = null;

$cats = $adsService->listCategories();
$current = null;
foreach ($cats as $c) {
    if ((int)$c['id'] === $catId) {
        $current = $c;
        break;
    }
}
if (!$current) {
    header("Location: ?page=404");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();
    $name = (string)($_POST['name'] ?? '');

    try {
        $adsService->renameCategory($catId, $name);
        header("Location: ?page=admin_categories");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>

<h1>Rename category #<?= (int)$catId ?></h1>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<form method="post">
    <?= Csrf::input(); ?>
    <div>
        <label>Name</label><br>
        <input name="name" required style="width:320px;" value="<?= h((string)$current['name']) ?>">
    </div>
    <button type="submit">Save</button>
    <a href="?page=admin_categories" style="margin-left:10px;">Cancel</a>
</form>