<?php
require_once __DIR__ . '/bootstrap.php';
require_role('kitchen', 'owner');
$rid = (int)$_SESSION['restaurant_id'];

$queue = kitchen_queue($rid);         

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['order_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'start') {

        $front = $queue->peek();
        if ($front && (int)$front['id'] === $id) {
            $next = $queue->dequeue();
            db()->prepare("UPDATE orders SET status = 'cooking' WHERE id = ? AND restaurant_id = ?")->execute([$next['id'], $rid]);
        }
    } elseif (($_POST['action'] ?? '') === 'served') {
        db()->prepare("UPDATE orders SET status = 'served' WHERE id = ? AND restaurant_id = ? AND status = 'cooking'")->execute([$id, $rid]);
    }
    redirect('kitchen.php');
}

$st = db()->prepare("SELECT * FROM orders WHERE status = 'cooking' AND restaurant_id = ? ORDER BY id");
$st->execute([$rid]);
$cooking = $st->fetchAll();
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

$right = e($_SESSION['name']) . ' &middot; refreshes every 10 seconds &middot; ';
$right .= $_SESSION['role'] === 'owner' ? owner_nav() : '<a href="logout.php">Sign out</a>';
page_start('Kitchen', $right, '<meta http-equiv="refresh" content="10">', 'Kitchen - ' . $_SESSION['restaurant_name']);
?>
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
<?php page_end();
