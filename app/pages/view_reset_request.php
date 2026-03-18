<h1>Reset Password</h1>

<p>Enter your username or email address to create a password reset request.</p>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if ($submitted && !$error): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        If the account exists, a reset request has been created.
        <div style="margin-top:8px; opacity:.85;">
            For security, reset links are not shown in the browser and are not written to logs.
            This local thesis prototype does not yet include a secure delivery channel for password reset links.
        </div>
    </div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?= Csrf::input(); ?>
    <div>
        <label>Username or Email</label><br>
        <input name="identifier" required style="width:420px;" value="<?= h($identifier) ?>">
    </div>
    <button type="submit" style="margin-top:12px;">Create reset request</button>
</form>

<p style="margin-top:12px;">
    <a href="?page=login">Back to login</a>
</p>
