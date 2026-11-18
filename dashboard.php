<?php
/* Owner home screen: a short overview, the two main jobs, and the customer link. */
require_once __DIR__ . '/bootstrap.php';
require_role('owner');
$rid = (int)$_SESSION['restaurant_id'];

/* Small local helper so the dashboard never declares a function in the shared scope. */
$count = static function (string $sql) use ($rid): int {
    $st = db()->prepare($sql);
    $st->execute([$rid]);
    return (int)$st->fetchColumn();
};

$categories = $count('SELECT COUNT(*) FROM categories WHERE restaurant_id = ?');
$dishes     = $count('SELECT COUNT(*) FROM dishes WHERE restaurant_id = ?');
$staff      = $count("SELECT COUNT(*) FROM users WHERE restaurant_id = ? AND role = 'kitchen'");

$st = db()->prepare('SELECT table_count FROM restaurants WHERE id = ?');
$st->execute([$rid]);
$tables = (int)$st->fetchColumn();

$link = base_url() . '/welcome.php?r=' . rawurlencode((string)$_SESSION['slug']);

$menuIntro  = $categories === 0
    ? 'No menu yet. Add your first category to get started.'
    : 'Add categories and dishes, change prices, or mark a dish sold out.';
$staffIntro = $staff === 0
    ? 'No kitchen logins yet. Add one so the kitchen can see orders.'
    : 'Create kitchen logins and set how many tables you have.';

page_start('Dashboard', owner_nav(), '', $_SESSION['restaurant_name']);
?>
<main class="narrow stack-gap">
    <div>
        <h2>Dashboard</h2>
        <p class="muted">Welcome back, <?= e($_SESSION['name']) ?>.</p>
    </div>

    <div class="two-col">
        <section class="box">
            <h2>Menu</h2>
            <p class="muted"><?= e($menuIntro) ?></p>
            <div class="line"><span>Categories</span><strong><?= $categories ?></strong></div>
            <div class="line"><span>Dishes</span><strong><?= $dishes ?></strong></div>
            <a class="btn block" href="admin.php">Open menu editor</a>
        </section>

        <section class="box">
            <h2>Staff &amp; tables</h2>
            <p class="muted"><?= e($staffIntro) ?></p>
            <div class="line"><span>Kitchen logins</span><strong><?= $staff ?></strong></div>
            <div class="line"><span>Tables</span><strong><?= $tables ?></strong></div>
            <a class="btn block" href="staff.php">Open staff page</a>
        </section>
    </div>

    <p><a class="btn small light" href="kitchen.php">Open kitchen screen &rarr;</a></p>

    <div class="linkbox">
        <strong>Your customer link:</strong> <a href="<?= e($link) ?>"><?= e($link) ?></a><br>
        <span class="muted small">Put this link (or a QR code made from it) on your tables.</span>
    </div>
</main>
<?php page_end();
