<h1>Rename category #<?= (int)$categoryId ?></h1>

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
