<?php
/* The owner's menu editor: add/edit/delete categories and dishes, mark dishes sold out. */
require_once __DIR__ . '/menu_functions.php';
require_role('owner');
$rid = (int)$_SESSION['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = '';
    switch ($_POST['action'] ?? '') {
        case 'add_category':
            $err = add_category($rid, $_POST['name'] ?? ''); break;
        case 'delete_category':
            $err = delete_category($rid, (int)($_POST['category_id'] ?? 0)); break;
        case 'add_dish':
            $err = add_dish($rid, (int)($_POST['category_id'] ?? 0), $_POST['name'] ?? '',
                            $_POST['description'] ?? '', (float)($_POST['price'] ?? 0)); break;
        case 'update_dish':
            $err = update_dish($rid, (int)($_POST['dish_id'] ?? 0), (int)($_POST['category_id'] ?? 0),
                               $_POST['name'] ?? '', $_POST['description'] ?? '', (float)($_POST['price'] ?? 0)); break;
        case 'set_available':
            $err = set_available($rid, (int)($_POST['dish_id'] ?? 0), (int)($_POST['available'] ?? 0) === 1); break;
        case 'delete_dish':
            $err = delete_dish($rid, (int)($_POST['dish_id'] ?? 0)); break;
    }
    if ($err !== '') flash($err);
    redirect('admin.php');
}

[$grouped] = load_menu($rid, false);        // owner sees everything, including sold-out dishes

$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM dishes WHERE id = ? AND restaurant_id = ?');
    $st->execute([(int)$_GET['edit'], $rid]);
    $edit = $st->fetch() ?: null;
}
$flash = take_flash();
$link  = base_url() . '/welcome.php?r=' . $_SESSION['slug'];

page_start('My menu', owner_nav(), '', $_SESSION['restaurant_name']);
?>
<main class="narrow stack-gap">
    <?php if ($flash): ?><p class="alert"><?= e($flash) ?></p><?php endif; ?>

    <div class="linkbox">
        <strong>Your customer link:</strong> <a href="<?= e($link) ?>"><?= e($link) ?></a><br>
        <span class="muted small">Put this link (or a QR code made from it) on your tables.</span>
    </div>

    <form method="post" class="box">
        <h2>Add a category</h2>
        <input type="hidden" name="action" value="add_category">
        <label>Category name <input name="name" placeholder="e.g. Seafood, Pasta, Drinks" required></label>
        <button class="btn">Add category</button>
    </form>

    <?php if ($grouped): ?>
    <form method="post" class="box" id="dish-form">
        <h2><?= $edit ? 'Edit dish' : 'Add a dish' ?></h2>
        <input type="hidden" name="action" value="<?= $edit ? 'update_dish' : 'add_dish' ?>">
        <?php if ($edit): ?><input type="hidden" name="dish_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <label>Category
            <select name="category_id">
                <?php foreach ($grouped as $g): $cid = (int)$g['category']['id']; ?>
                    <option value="<?= $cid ?>" <?= ($edit && (int)$edit['category_id'] === $cid) ? 'selected' : '' ?>>
                        <?= e($g['category']['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Dish name <input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
        <label>Description (optional) <input name="description" value="<?= e($edit['description'] ?? '') ?>"></label>
        <label>Price (₱) <input type="number" name="price" step="0.01" min="0.01" value="<?= e($edit['price'] ?? '') ?>" required></label>
        <div class="actions">
            <button class="btn"><?= $edit ? 'Save changes' : 'Add dish' ?></button>
            <?php if ($edit): ?><a href="admin.php" class="btn light">Cancel</a><?php endif; ?>
        </div>
    </form>
    <?php endif; ?>

    <div>
    <?php if (!$grouped): ?>
        <div class="box"><h2>Your menu is empty</h2><p class="muted">Add your first category above to get started.</p></div>
    <?php endif; ?>

    <?php foreach ($grouped as $g): $cid = (int)$g['category']['id']; ?>
        <div class="cat-head">
            <h2><?= e($g['category']['name']) ?></h2>
            <?php if (!$g['dishes']): ?>
                <form method="post" class="inline" onsubmit="return confirm('Delete this empty category?')">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?= $cid ?>">
                    <button class="btn tiny dark">Delete category</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$g['dishes']): ?><p class="muted">No dishes yet.</p><?php endif; ?>

        <?php foreach ($g['dishes'] as $d): $isOn = (int)$d['available'] === 1; ?>
            <div class="dish <?= $isOn ? '' : 'sold' ?>">
                <div class="dish-info">
                    <strong><?= e($d['name']) ?><?= $isOn ? '' : ' (sold out)' ?></strong>
                    <span class="muted"><?= e($d['description']) ?></span>
                </div>
                <span class="price"><?= peso($d['price']) ?></span>
                <div class="row-actions">
                    <a class="btn tiny light" href="admin.php?edit=<?= (int)$d['id'] ?>#dish-form">Edit</a>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="set_available">
                        <input type="hidden" name="dish_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="available" value="<?= $isOn ? 0 : 1 ?>">
                        <button class="btn tiny light"><?= $isOn ? 'Mark sold out' : 'Mark available' ?></button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this dish?')">
                        <input type="hidden" name="action" value="delete_dish">
                        <input type="hidden" name="dish_id" value="<?= (int)$d['id'] ?>">
                        <button class="btn tiny dark">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </div>
</main>
<?php page_end();
