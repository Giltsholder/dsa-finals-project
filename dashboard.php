<?php
/* Owner home screen: two big choices so the owner is never dropped into a long page. */
require_once __DIR__ . '/bootstrap.php';
require_role('owner');
$rid = (int)$_SESSION['restaurant_id'];

function count_rows(string $sql, int $rid): int {
    $st = db()->prepare($sql);
    $st->execute([$rid]);
    return (int)$st->fetchColumn();
}
$categories = count_rows('SELECT COUNT(*) FROM categories WHERE restaurant_id = ?', $rid);
$dishes     = count_rows('SELECT COUNT(*) FROM dishes WHERE restaurant_id = ?', $rid);
$staff      = count_rows("SELECT COUNT(*) FROM users WHERE restaurant_id = ? AND role = 'kitchen'", $rid);
$link       = base_url() . '/welcome.php?r=' . $_SESSION['slug'];

$menuStatus  = $categories === 0
    ? 'Not started yet'
    : $categories . ($categories === 1 ? ' category' : ' categories') . ' &middot; ' . $dishes . ($dishes === 1 ? ' dish' : ' dishes');
$staffStatus = $staff === 0
    ? 'No staff yet'
    : $staff . ' kitchen ' . ($staff === 1 ? 'login' : 'logins');

page_start('Dashboard', owner_nav(), '', $_SESSION['restaurant_name']);
?>
<main class="narrow stack-gap">
    <div>
        <h2>What would you like to do?</h2>
        <p class="muted">Pick one to get started. You can come back here any time from the menu at the top right.</p>
    </div>

    <div class="dash-grid">
        <a class="dash-card" href="admin.php">
            <span class="dash-title">Create menu</span>
            <span class="muted">Add categories and dishes, change prices, or mark a dish sold out.</span>
            <span class="dash-status"><?= $menuStatus ?></span>
            <span class="dash-go">Open menu editor &rarr;</span>
        </a>

        <a class="dash-card" href="staff.php">
            <span class="dash-title">Add staff</span>
            <span class="muted">Create kitchen logins and set how many tables you have.</span>
            <span class="dash-status"><?= $staffStatus ?></span>
            <span class="dash-go">Open staff page &rarr;</span>
        </a>
    </div>

    <div class="linkbox">
        <strong>Your customer link:</strong> <a href="<?= e($link) ?>"><?= e($link) ?></a><br>
        <span class="muted small">Put this link (or a QR code made from it) on your tables.</span>
    </div>
</main>
<?php page_end();
