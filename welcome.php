<?php
require_once __DIR__ . '/bootstrap.php';

$st = db()->prepare('SELECT * FROM restaurants WHERE slug = ?');
$st->execute([trim($_GET['r'] ?? '')]);
$r = $st->fetch();

if (!$r) {
    page_start('Restaurant not found');
    echo '<main class="narrow"><div class="box"><p>We couldn\'t find that restaurant. Please check the link or QR code on your table.</p></div></main>';
    page_end();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $table = (int)($_POST['table_no'] ?? 0);
    if ($name === '' || $table < 1 || $table > (int)$r['table_count']) {
        $error = 'Please enter your name and a table number from 1 to ' . (int)$r['table_count'] . '.';
    } else {
        session_regenerate_id(true);
        $_SESSION = [
            'role' => 'customer', 'name' => $name, 'table' => $table,
            'restaurant_id' => (int)$r['id'], 'restaurant_name' => $r['name'], 'slug' => $r['slug'],
            'cart' => new DynamicArray(), 'undo' => new Stack(),
        ];
        redirect('menu.php');
    }
}

page_start('Welcome', '', '', $r['name']);
?>
<main class="narrow">
    <?php if ($error): ?><p class="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="box">
        <h2>Welcome to <?= e($r['name']) ?>!</h2>
        <p class="muted">Tell us who you are and where you're seated, then browse the menu.</p>
        <label>Your name <input name="name" required></label>
        <label>Table number <input type="number" name="table_no" min="1" max="<?= (int)$r['table_count'] ?>" required></label>
        <button class="btn">View the menu</button>
    </form>
</main>
<?php page_end();
