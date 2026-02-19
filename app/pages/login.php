<?php

declare(strict_types=1);

$error = null;
$identifier = '';

if ($auth->isLoggedIn()) {
    echo "<p>You are already logged in as <strong>" . h((string)$auth->username()) . "</strong>.</p>";
    echo "<p><a href='?page=home'>Go home</a> or <a href='?page=logout'>Logout</a></p>";
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $identifier = (string)($_POST['identifier'] ?? '');
    $password   = (string)($_POST['password'] ?? '');

    if ($auth->attemptLogin($identifier, $password)) {
        header("Location: ?page=home");
        exit;
    }
    $error = "Invalid credentials.";
}
?>

<h1>Login</h1>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;">
        <?php echo h($error); ?>
    </div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?php echo Csrf::input(); ?>
    <div>
        <label>Username or Email</label><br>
        <input name="identifier" required autocomplete="username" value="<?php echo h($identifier); ?>">
    </div>
    <div>
        <label>Password</label><br>
        <input name="password" type="password" required autocomplete="current-password">
    </div>
    <button type="submit">Login</button>
</form>