<?php
require_once __DIR__ . '/account_functions.php';
require_role('owner');
$rid = (int)$_SESSION['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = '';
    switch ($_POST['action'] ?? '') {
        case 'add_staff':    $err = add_staff($rid, $_POST['username'] ?? '', $_POST['password'] ?? ''); break;
        case 'delete_staff': $err = delete_staff($rid, (int)($_POST['user_id'] ?? 0)); break;
        case 'set_tables':   $err = set_table_count($rid, (int)($_POST['table_count'] ?? 0)); break;
    }
    if ($err !== '') flash($err);
    redirect('staff.php');
}

$st = db()->prepare("SELECT id, username FROM users WHERE restaurant_id = ? AND role = 'kitchen' ORDER BY username");
$st->execute([$rid]);
$staff = $st->fetchAll();

$st = db()->prepare('SELECT table_count FROM restaurants WHERE id = ?');
$st->execute([$rid]);
$tables = (int)$st->fetchColumn();
$flash = take_flash();

page_start('Staff & settings', owner_nav(), '', $_SESSION['restaurant_name']);
?>
<main class="narrow stack-gap">
    <p class="small"><a href="dashboard.php">&larr; Back to dashboard</a></p>
    <?php if ($flash): ?><p class="alert"><?= e($flash) ?></p><?php endif; ?>

    <form method="post" class="box">
        <h2>Tables</h2>
        <input type="hidden" name="action" value="set_tables">
        <label>Number of tables in your restaurant <input type="number" name="table_count" min="1" max="200" value="<?= $tables ?>" required></label>
        <button class="btn">Save</button>
    </form>

    <form method="post" class="box">
        <h2>Add kitchen staff</h2>
        <p class="muted">They log in on the home page and only see your restaurant's orders.</p>
        <input type="hidden" name="action" value="add_staff">
        <label>Username <input name="username" required></label>
        <label>Password (6+ characters) <input type="password" name="password" minlength="6" required></label>
        <button class="btn">Add staff</button>
    </form>

    <div class="box">
        <h2>Your kitchen staff</h2>
        <?php if (!$staff): ?><p class="muted">No staff accounts yet.</p><?php endif; ?>
        <?php foreach ($staff as $s): ?>
            <div class="line">
                <span><?= e($s['username']) ?></span>
                <form method="post" class="inline" onsubmit="return confirm('Remove this staff login?')">
                    <input type="hidden" name="action" value="delete_staff">
                    <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                    <button class="btn tiny dark">Remove</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<?php page_end();