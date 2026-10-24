<?php
require_once __DIR__ . '/bootstrap.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['who'] ?? '') === 'customer') {
        $name  = trim($_POST['name'] ?? '');
        $table = (int)($_POST['table_no'] ?? 0);
        if ($name === '' || $table < 1 || $table > 50) {
            $error = 'Please enter your name and a table number from 1 to 50.';
        } else {
            $_SESSION = ['role' => 'customer', 'name' => $name, 'table' => $table, 'cart' => new DynamicArray(), 'undo' => new Stack()];
            redirect('menu.php');
        }
    } elseif (($_POST['who'] ?? '') === 'kitchen') {
        $st = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'kitchen'");
        $st->execute([trim($_POST['username'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            $_SESSION = ['role' => 'kitchen', 'name' => $u['username']];
            redirect('kitchen.php');
        }
        $error = 'Wrong username or password.';
    }
}
db(); // make sure the database exists even before anyone signs in
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hapag Restaurant</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top"><h1>Hapag Restaurant</h1></header>
<main class="narrow">
    <?php if ($error): ?><p class="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="two-col">
        <form method="post" class="box">
            <h2>Dining in</h2>
            <p class="muted">Tell us who and where you're seated, then browse the menu.</p>
            <input type="hidden" name="who" value="customer">
            <label>Your name <input name="name" required></label>
            <label>Table number <input type="number" name="table_no" min="1" max="50" required></label>
            <button class="btn">View the menu</button>
        </form>

        <form method="post" class="box">
            <h2>Kitchen staff</h2>
            <p class="muted">Sign in to see incoming orders.</p>
            <input type="hidden" name="who" value="kitchen">
            <label>Username <input name="username" value="kitchen" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button class="btn dark">Open kitchen screen</button>
            <p class="muted small">Default login: kitchen / kitchen123</p>
        </form>
    </div>
</main>
</body>
</html>
