<?php
require_once __DIR__ . '/bootstrap.php';
require_role('customer');

[, $menu] = load_menu();
$cart = cart_load();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cart->size() > 0) {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO orders (table_no, customer_name, note, total) VALUES (?,?,?,?)')
        ->execute([$_SESSION['table'], $_SESSION['name'], trim($_POST['note'] ?? ''), cart_total($cart, $menu)]);
    $orderId = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare('INSERT INTO order_items (order_id, dish_id, dish_name, price, qty) VALUES (?,?,?,?,?)');
    for ($i = 0; $i < $cart->size(); $i++) {
        $line = $cart->get($i);
        $d = $menu->get($line->id);       
        $ins->execute([$orderId, $d['id'], $d['name'], $d['price'], $line->qty]);
    }
    $pdo->commit();

    $_SESSION['cart'] = new DynamicArray();
    $_SESSION['undo'] = new Stack();
    redirect('confirm.php?sent=' . $orderId);
}

$sent = (int)($_GET['sent'] ?? 0);
if ($sent) {
    $st = db()->prepare('SELECT * FROM orders WHERE id = ? AND table_no = ?');
    $st->execute([$sent, $_SESSION['table']]);
    $order = $st->fetch();
    $ahead = $order ? (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'queued' AND id < " . (int)$order['id'])->fetchColumn() : 0;
}

if (!$sent && $cart->size() === 0) redirect('menu.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirm order - Hapag Restaurant</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
    <h1>Hapag Restaurant</h1>
    <span>Table <?= (int)$_SESSION['table'] ?> &middot; <?= e($_SESSION['name']) ?></span>
</header>
<main class="narrow">
<?php if ($sent && $order): ?>
    <div class="box">
        <h2>Your order is with the kitchen</h2>
        <p>Order #<?= (int)$order['id'] ?> for table <?= (int)$order['table_no'] ?>, total <?= peso($order['total']) ?>.</p>
        <p class="muted">
            <?= $ahead === 0 ? 'No orders ahead of yours.' : "$ahead order" . ($ahead > 1 ? 's' : '') . ' ahead of yours.' ?>
            Please relax, we'll bring your dishes to the table.
        </p>
        <a href="menu.php" class="btn">Order something else</a>
    </div>
<?php else: ?>
    <div class="box">
        <h2>Is this everything?</h2>
        <p class="muted">Check your dishes below. Once you confirm, the order goes to the kitchen and can't be changed here.</p>

        <?php for ($i = 0; $i < $cart->size(); $i++):
            $line = $cart->get($i); $d = $menu->get($line->id); ?>
            <div class="line">
                <span><?= $line->qty ?> &times; <?= e($d['name']) ?></span>
                <span><?= peso($d['price'] * $line->qty) ?></span>
            </div>
        <?php endfor; ?>
        <p class="total">Total <strong><?= peso(cart_total($cart, $menu)) ?></strong></p>

        <form method="post">
            <label>Notes for the kitchen (allergies, how you like it cooked)
                <textarea name="note" rows="3" maxlength="300"></textarea>
            </label>
            <div class="actions">
                <a href="menu.php" class="btn light">No, add more dishes</a>
                <button class="btn">Yes, send to kitchen</button>
            </div>
        </form>
    </div>
<?php endif; ?>
</main>
</body>
</html>
