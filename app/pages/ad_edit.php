<?php

declare(strict_types=1);

$auth->requireRole('SUPPLIER');

$supplierId = $auth->supplierId();
if ($supplierId === null) {
    header("Location: ?page=403");
    exit;
}

$idRaw = $_GET['id'] ?? null;
if (!$idRaw || !ctype_digit((string)$idRaw)) {
    header("Location: ?page=404");
    exit;
}
$adId = (int)$idRaw;

$error = null;
$info = null;

$categories = $adsService->listCategories();

$ad = $adsService->getForSupplier($adId, $supplierId);
if (!$ad) {
    header("Location: ?page=404");
    exit;
}

$status = strtoupper((string)$ad['status']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    // If the user clicked "Submit" (without editing), support both variants:
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'submit') {
        try {
            // Preferred: explicit submit method (new AdsService)
            if (method_exists($adsService, 'submitForSupplier')) {
                $adsService->submitForSupplier($adId, $supplierId, (int)$auth->userId());
            } else {
                // fallback: update with submit flags (older service might allow DRAFT->PENDING)
                $_POST['save_as_draft'] = 0;
                $_POST['submit_for_approval'] = 1;
                $adsService->updateForSupplier($adId, $supplierId, $_POST, (int)$auth->userId());
            }

            header("Location: ?page=ads_list");
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        // Normal save/update flow
        // Normalize based on which button:
        if ($action === 'save_draft') {
            $_POST['save_as_draft'] = 1;
            $_POST['submit_for_approval'] = 0;
        } elseif ($action === 'save_submit') {
            $_POST['save_as_draft'] = 0;
            $_POST['submit_for_approval'] = 1;
        }

        try {
            $adsService->updateForSupplier($adId, $supplierId, $_POST, (int)$auth->userId());
            header("Location: ?page=ads_list");
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // reload after failed post
    $ad = $adsService->getForSupplier($adId, $supplierId) ?? $ad;
    $status = strtoupper((string)$ad['status']);
}

$isLocked = ($status === 'PENDING'); // strict workflow: pending not editable
?>

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
        <input name="title" required style="width:520px;" value="<?= h((string)$ad['title']) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Description</label><br>
        <textarea name="description" required rows="6" style="width:520px;" <?= $isLocked ? 'disabled' : '' ?>><?= h((string)$ad['description']) ?></textarea>
    </div>

    <div style="margin:10px 0;">
        <label>Category</label><br>
        <select name="category_id" style="width:520px;" <?= $isLocked ? 'disabled' : '' ?>>
            <option value="">—</option>
            <?php foreach ($categories as $c): ?>
                <?php $cid = (int)$c['id']; ?>
                <option value="<?= $cid ?>" <?= ((int)($ad['category_id'] ?? 0) === $cid) ? 'selected' : '' ?>>
                    <?= h((string)$c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:10px 0;">
        <label>Price text</label><br>
        <input name="price_text" style="width:520px;" value="<?= h((string)($ad['price_text'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Valid from</label><br>
        <input name="valid_from" placeholder="YYYY-MM-DD" value="<?= h((string)($ad['valid_from'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin:10px 0;">
        <label>Valid to</label><br>
        <input name="valid_to" placeholder="YYYY-MM-DD" value="<?= h((string)($ad['valid_to'] ?? '')) ?>" <?= $isLocked ? 'disabled' : '' ?>>
    </div>

    <div style="margin-top:14px;">
        <?php if (!$isLocked): ?>
            <button type="submit" name="action" value="save_draft">Save (Draft)</button>
            <button type="submit" name="action" value="save_submit">Save + Submit</button>
        <?php endif; ?>

        <?php if (in_array($status, ['DRAFT','REJECTED'], true)): ?>
            <button type="submit" name="action" value="submit">Submit</button>
        <?php endif; ?>

        <a href="?page=ads_list" style="margin-left:10px;">Back</a>
    </div>

    <?php if ($status === 'APPROVED'): ?>
        <p style="margin-top:10px;opacity:.85;">
            Note: Editing an APPROVED ad will typically re-submit it for review (APPROVED → PENDING).
        </p>
    <?php endif; ?>
</form>