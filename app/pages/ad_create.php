<?php

declare(strict_types=1);

$auth->requireRole('SUPPLIER');

$supplierId = $auth->supplierId();
if ($supplierId === null) {
    header("Location: ?page=403");
    exit;
}

$error = null;

$title = '';
$description = '';
$priceText = '';
$categoryId = '';
$validFrom = '';
$validTo = '';

$categories = $adsService->listCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    // Normalize based on which button was pressed (supports both old/new AdsService variants)
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_draft') {
        $_POST['save_as_draft'] = 1;            // older service
        $_POST['submit_for_approval'] = 0;      // newer service
    } elseif ($action === 'submit') {
        $_POST['save_as_draft'] = 0;            // older service
        $_POST['submit_for_approval'] = 1;      // newer service
    }

    $title = (string)($_POST['title'] ?? '');
    $description = (string)($_POST['description'] ?? '');
    $priceText = (string)($_POST['price_text'] ?? '');
    $categoryId = (string)($_POST['category_id'] ?? '');
    $validFrom = (string)($_POST['valid_from'] ?? '');
    $validTo = (string)($_POST['valid_to'] ?? '');

    try {
        $adsService->createForSupplier(
            $supplierId,
            $_POST,
            (int)$auth->userId()
        );

        header("Location: ?page=ads_list");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>

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
        <input name="title" required style="width:520px;" value="<?= h($title) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Description</label><br>
        <textarea name="description" required rows="6" style="width:520px;"><?= h($description) ?></textarea>
    </div>

    <div style="margin:10px 0;">
        <label>Category</label><br>
        <select name="category_id" style="width:520px;">
            <option value="">—</option>
            <?php foreach ($categories as $c): ?>
                <?php $cid = (int)$c['id']; ?>
                <option value="<?= $cid ?>" <?= ((string)$cid === $categoryId) ? 'selected' : '' ?>>
                    <?= h((string)$c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:10px 0;">
        <label>Price text</label><br>
        <input name="price_text" style="width:520px;" value="<?= h($priceText) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Valid from</label><br>
        <input name="valid_from" placeholder="YYYY-MM-DD" value="<?= h($validFrom) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Valid to</label><br>
        <input name="valid_to" placeholder="YYYY-MM-DD" value="<?= h($validTo) ?>">
    </div>

    <div style="margin-top:14px;">
        <button type="submit" name="action" value="save_draft">Save Draft</button>
        <button type="submit" name="action" value="submit">Submit for approval</button>
        <a href="?page=ads_list" style="margin-left:10px;">Cancel</a>
    </div>
</form>