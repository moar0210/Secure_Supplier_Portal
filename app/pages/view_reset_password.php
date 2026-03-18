<h1>Choose New Password</h1>

<?php if ($success): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        Your password has been updated successfully. You can now <a href="?page=login">log in</a>.
    </div>
<?php elseif (!$isValidToken): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        The reset link is invalid or has expired.
    </div>
    <p><a href="?page=reset_request">Create a new reset request</a></p>
<?php else: ?>
    <?php if ($error): ?>
        <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?page=reset_password&amp;username=<?= rawurlencode($username) ?>&amp;token=<?= rawurlencode($token) ?>" autocomplete="off">
        <?= Csrf::input(); ?>

        <div style="margin:10px 0;">
            <label>New password</label><br>
            <input name="new_password" type="password" required style="width:320px;" autocomplete="new-password">
        </div>

        <div style="margin:10px 0;">
            <label>Confirm new password</label><br>
            <input name="confirm_password" type="password" required style="width:320px;" autocomplete="new-password">
        </div>

        <p style="opacity:.85;">
            Passwords must be at least 10 characters and include at least one letter and one number.
        </p>

        <button type="submit">Update password</button>
    </form>
<?php endif; ?>
