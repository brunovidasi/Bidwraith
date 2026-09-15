<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Public sign-up is switched off for now — the app is invite-only until this
// flag is flipped back on. register_user() below is left intact for that.
const REGISTRATION_OPEN = false;

if (!REGISTRATION_OPEN) {
    http_response_code(404);
    require __DIR__ . '/../includes/layout_top.php';
    echo '<p>Page not found.</p>';
    require __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    [$ok, $result] = register_user($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) {
        $_SESSION['user_id'] = $result;
        redirect('dashboard.php');
    }
    $error = $result;
}

$pageTitle = 'Create account';
require __DIR__ . '/../includes/layout_top.php';
?>
<div class="auth-box">
    <h1>Create account</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form class="stacked" method="post">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>
        <div class="hint">At least 8 characters.</div>

        <button type="submit">Create account</button>
    </form>
    <div class="switch">Already have an account? <a href="login.php">Log in</a></div>
</div>
<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
