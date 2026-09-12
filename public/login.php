<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('dashboard.php');
    }
    $error = 'Invalid email or password.';
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="auth-box">
    <h1>Log in</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="stacked" method="post">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Log in</button>
    </form>
    <div class="switch">No account yet? <a href="register.php">Create one</a></div>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
