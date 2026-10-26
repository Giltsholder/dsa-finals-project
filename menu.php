<?php
require_once __DIR__ . '/bootstrap.php';
require_role('customer');

[$grouped, $menu] = load_menu();      
$cart = cart_load();                  
$undo = undo_load();                  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['dish_id'] ?? 0);

    if ($action === 'undo') {
        if (!$undo->isEmpty()) {
            $last = $undo->pop();
            cart_set($cart, $last->id, $last->prev);
        }
    } elseif ($menu->has($id)) {      
        $qty = cart_qty($cart, $id);
        $new = match ($action) {
            'add'    => $qty + 1,
            'minus'  => $qty - 1,
            'remove' => 0,
            default  => $qty,
        };
        if ($new !== $qty) {
            if ($undo->size() >= 50) $undo->keepNewest(40);   
            $undo->push(new UndoEntry($id, $qty));
            cart_set($cart, $id, max(0, min($new, 20)));
        }
    }
    redirect('menu.php' . (isset($_POST['back']) ? '#' . preg_replace('/[^a-z0-9-]/', '', $_POST['back']) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu - Hapag Restaurant</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
    <h1>Hapag Restaurant</h1>
    <span>Table <?= (int)$_SESSION['table'] ?> &middot; <?= e($_SESSION['name']) ?> &middot; <a href="logout.php">Leave</a></span>
</header>

<nav class="cats">
    <?php foreach ($grouped as $g): ?>
        <a href="#cat-<?= (int)$g['category']['id'] ?>"><?= e($g['category']['name']) ?></a>
    <?php endforeach; ?>
</nav>

<main class="layout">
    <section class="menu">
        <?php foreach ($grouped as $g): $cid = 'cat-' . (int)$g['category']['id']; ?>
            <h2 id="<?= $cid ?>"><?= e($g['category']['name']) ?></h2>
            <?php foreach ($g['dishes'] as $d): $q = cart_qty($cart, (int)$d['id']); ?>
                <div class="dish">
                    <div class="dish-info">
                        <strong><?= e($d['name']) ?></strong>
                        <span class="muted"><?= e($d['description']) ?></span>
                    </div>
                    <span class="price"><?= peso($d['price']) ?></span>
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="dish_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="back" value="<?= $cid ?>">
                        <button class="btn small"><?= $q ? "Add another ($q)" : 'Add' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </section>

    <aside class="order box">
        <h2>Your order</h2>
        <?php if ($cart->size() === 0): ?>
            <p class="muted">Nothing selected yet. Add dishes from the menu.</p>
        <?php else: ?>
            <?php for ($i = 0; $i < $cart->size(); $i++):
                $line = $cart->get($i); $d = $menu->get($line->id); ?>
                <div class="line">
                    <div>
                        <?= e($d['name']) ?><br>
                        <span class="muted"><?= $line->qty ?> &times; <?= peso($d['price']) ?></span>
                    </div>
                    <form method="post" class="qty">
                        <input type="hidden" name="dish_id" value="<?= $line->id ?>">
                        <button name="action" value="minus" class="btn tiny">&minus;</button>
                        <button name="action" value="add" class="btn tiny">+</button>
                        <button name="action" value="remove" class="btn tiny dark">Remove</button>
                    </form>
                </div>
            <?php endfor; ?>
            <p class="total">Total <strong><?= peso(cart_total($cart, $menu)) ?></strong></p>
        <?php endif; ?>

        <form method="post">
            <button name="action" value="undo" class="btn light" <?= $undo->isEmpty() ? 'disabled' : '' ?>>
                Undo last change<?= $undo->isEmpty() ? '' : ' (' . $undo->size() . ')' ?>
            </button>
        </form>
        <?php if ($cart->size() > 0): ?>
            <a href="confirm.php" class="btn block">Review my order</a>
        <?php endif; ?>
    </aside>
</main>
</body>
</html>
