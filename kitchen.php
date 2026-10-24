<?php
require_once __DIR__ . '/bootstrap.php';
require_role('kitchen');

$queue = kitchen_queue();                 // Queue: oldest waiting order at the front

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['order_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'start') {
        // Only the order at the FRONT of the queue may be started (first in, first out)
        $front = $queue->peek();
        if ($front && (int)$front['id'] === $id) {
            $next = $queue->dequeue();
            db()->prepare("UPDATE orders SET status = 'cooking' WHERE id = ?")->execute([$next['id']]);
        }
    } elseif (($_POST['action'] ?? '') === 'served') {
        db()->prepare("UPDATE orders SET status = 'served' WHERE id = ? AND status = 'cooking'")->execute([$id]);
    }
    redirect('kitchen.php');
}

$cooking = db()->query("SELECT * FROM orders WHERE status = 'cooking' ORDER BY id")->fetchAll();
$waitingCount = $queue->size();

function ticket(array $o, bool $withButton, string $action, string $label, string $class = ''): void { ?>
    <div class="ticket <?= $class ?>">
        <div class="ticket-head">
            <strong>Table <?= (int)$o['table_no'] ?></strong>
            <span class="muted">#<?= (int)$o['id'] ?> &middot; <?= e($o['customer_name']) ?> &middot; <?= e(substr($o['created_at'], 11, 5)) ?> UTC</span>
        </div>
        <?php foreach (order_items((int)$o['id']) as $it): ?>
            <div class="line"><span><?= (int)$it['qty'] ?> &times; <?= e($it['dish_name']) ?></span></div>
        <?php endforeach; ?>
        <?php if ($o['note']): ?><p class="note">Note: <?= e($o['note']) ?></p><?php endif; ?>
        <?php if ($withButton): ?>
            <form method="post">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button name="action" value="<?= $action ?>" class="btn"><?= $label ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="10">
<title>Kitchen - Hapag Restaurant</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
    <h1>Kitchen</h1>
    <span><?= e($_SESSION['name']) ?> &middot; refreshes every 10 seconds &middot; <a href="logout.php">Sign out</a></span>
</header>

<main class="kitchen">
    <section>
        <h2>Waiting (<?= $waitingCount ?>)</h2>
        <?php if ($queue->isEmpty()): ?><p class="muted">No orders waiting.</p><?php endif; ?>
        <?php $i = 0; while (!$queue->isEmpty()): $o = $queue->dequeue(); ?>
            <?php if ($i === 0): ?><p class="muted small">Next in line</p><?php endif; ?>
            <?php ticket($o, $i === 0, 'start', 'Start cooking', $i === 0 ? 'next' : ''); $i++; ?>
        <?php endwhile; ?>
    </section>

    <section>
        <h2>Cooking (<?= count($cooking) ?>)</h2>
        <?php if (!$cooking): ?><p class="muted">Nothing on the stove.</p><?php endif; ?>
        <?php foreach ($cooking as $o) ticket($o, true, 'served', 'Mark as served'); ?>
    </section>
</main>
</body>
</html>
