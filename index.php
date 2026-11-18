<?php
require_once __DIR__ . '/account_functions.php';
db();

if (isset($_GET['r'])) redirect('welcome.php?r=' . urlencode($_GET['r']));   // customer links still work here
if (in_array($_SESSION['role'] ?? '', ['owner', 'kitchen'], true)) {
    redirect($_SESSION['role'] === 'owner' ? 'dashboard.php' : 'kitchen.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['who'] ?? '') === 'signup') {
        $error = register_owner($_POST['restaurant'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($error === '') redirect('dashboard.php');
    } else {
        $u = find_user($_POST['username'] ?? '');
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            start_session_for($u);
            redirect($u['role'] === 'owner' ? 'dashboard.php' : 'kitchen.php');
        }
        $error = 'Wrong email/username or password.';
    }
}

page_start('Restaurant Menu Maker');
?>
<main class="narrow">
    <?php if ($error): ?><p class="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="two-col">
        <form method="post" class="box">
            <h2>Log in</h2>
            <p class="muted">Restaurant owners and kitchen staff.</p>
            <input type="hidden" name="who" value="login">
            <label>Email or username <input name="username" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button class="btn">Log in</button>
        </form>

        <form method="post" class="box">
            <h2>Create your restaurant</h2>
            <p class="muted">Start with a blank menu and build it your way.</p>
            <input type="hidden" name="who" value="signup">
            <label>Restaurant name <input name="restaurant" required></label>
            <label>Email <input type="email" name="email" required></label>
            <label>Password (6+ characters) <input type="password" name="password" minlength="6" required></label>
            <button class="btn dark">Create restaurant</button>
        </form>
    </div>
    <p class="muted">Dining in? Scan the QR code or open the link on your table.</p>
</main>
<?php page_end();
