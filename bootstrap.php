<?php
require_once __DIR__ . '/DataStructures/DynamicArray.php';
require_once __DIR__ . '/DataStructures/Stack.php';
require_once __DIR__ . '/DataStructures/Queue.php';
require_once __DIR__ . '/DataStructures/HashMap.php';

class CartLine  { public function __construct(public int $id, public int $qty) {} }
class UndoEntry { public function __construct(public int $id, public int $prev) {} }

session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . $dir . '/restaurant.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'kitchen'
        );
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS dishes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES categories(id),
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_no INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            note TEXT,
            status TEXT NOT NULL DEFAULT 'queued',   -- queued | cooking | served
            total REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id),
            dish_id INTEGER NOT NULL,
            dish_name TEXT NOT NULL,
            price REAL NOT NULL,
            qty INTEGER NOT NULL
        );
    ");

    if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) seed($pdo);
    return $pdo;
}

function seed(PDO $pdo): void {
    $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?,?,?)')
        ->execute(['kitchen', password_hash('kitchen123', PASSWORD_DEFAULT), 'kitchen']);

    $menu = [
        'Appetizers' => [
            ['Calamares', 'Crispy squid rings with garlic-vinegar dip', 280],
            ['Lumpiang Shanghai', 'Pork spring rolls with sweet chili sauce', 240],
            ['Tokwa\'t Baboy', 'Fried tofu and pork belly in soy-vinegar', 220],
        ],
        'Soups' => [
            ['Sinigang na Hipon', 'Shrimp in tamarind broth with vegetables', 380],
            ['Bulalo', 'Beef shank and marrow soup with corn', 560],
            ['Tinolang Manok', 'Chicken, green papaya and chili leaves in ginger broth', 320],
        ],
        'Seafood' => [
            ['Garlic Butter Shrimp', 'Tiger prawns sautéed in garlic butter', 450],
            ['Grilled Salmon', 'Salmon fillet with lemon-dill sauce', 520],
            ['Inihaw na Pusit', 'Grilled squid stuffed with tomato and onion', 380],
            ['Kinilaw na Tanigue', 'Fresh tuna cured in vinegar and calamansi', 350],
        ],
        'Pork' => [
            ['Lechon Kawali', 'Deep-fried crispy pork belly with liver sauce', 340],
            ['Sizzling Sisig', 'Chopped pork face and ears on a hot plate', 320],
            ['Crispy Pata', 'Deep-fried pork knuckle, serves 3 to 4', 780],
            ['Pork Adobo', 'Slow-braised pork in soy sauce and vinegar', 290],
        ],
        'Chicken & Beef' => [
            ['Chicken Inasal', 'Grilled chicken marinated in lemongrass and annatto', 290],
            ['Beef Kaldereta', 'Beef stew with liver spread, potatoes and peppers', 420],
            ['Bistek Tagalog', 'Sliced beef sirloin with onion rings in soy-calamansi', 390],
            ['Beef Salpicao', 'Garlic-seared beef cubes with mushrooms', 450],
        ],
        'Vegetables' => [
            ['Pinakbet', 'Mixed vegetables with shrimp paste', 220],
            ['Laing', 'Taro leaves simmered in coconut milk', 230],
            ['Chopsuey', 'Stir-fried vegetables in light sauce', 240],
        ],
        'Desserts' => [
            ['Leche Flan', 'Silky caramel custard', 140],
            ['Halo-Halo', 'Shaved ice with sweet beans, fruit and ube ice cream', 170],
            ['Turon with Ice Cream', 'Banana spring rolls with caramel', 150],
        ],
        'Drinks' => [
            ['Iced Tea', 'House-brewed, bottomless refill', 90],
            ['Calamansi Juice', 'Fresh-squeezed', 100],
            ['Fresh Buko Juice', 'Young coconut water with strips of meat', 120],
        ],
    ];

    $catStmt  = $pdo->prepare('INSERT INTO categories (name, sort_order) VALUES (?,?)');
    $dishStmt = $pdo->prepare('INSERT INTO dishes (category_id, name, description, price) VALUES (?,?,?,?)');
    $order = 1;
    foreach ($menu as $cat => $dishes) {
        $catStmt->execute([$cat, $order++]);
        $catId = (int)$pdo->lastInsertId();
        foreach ($dishes as $d) $dishStmt->execute([$catId, $d[0], $d[1], $d[2]]);
    }
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }
function redirect(string $to): never { header('Location: ' . $to); exit; }

function require_role(string $role): void {
    if (($_SESSION['role'] ?? '') !== $role) redirect('index.php');
}

function load_menu(): array {
    $cats = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
    $map  = new HashMap();
    $grouped = [];
    foreach ($cats as $c) {
        $st = db()->prepare('SELECT * FROM dishes WHERE category_id = ? ORDER BY id');
        $st->execute([$c['id']]);
        $dishes = $st->fetchAll();
        foreach ($dishes as $d) $map->put((int)$d['id'], $d);
        $grouped[] = ['category' => $c, 'dishes' => $dishes];
    }
    return [$grouped, $map];
}

function cart_load(): DynamicArray {
    if (!(($_SESSION['cart'] ?? null) instanceof DynamicArray)) $_SESSION['cart'] = new DynamicArray();
    return $_SESSION['cart'];
}
function cart_find(DynamicArray $c, int $id): int {
    for ($i = 0; $i < $c->size(); $i++) if ($c->get($i)->id === $id) return $i;
    return -1;
}
function cart_qty(DynamicArray $c, int $id): int {
    $i = cart_find($c, $id);
    return $i < 0 ? 0 : $c->get($i)->qty;
}
function cart_set(DynamicArray $c, int $id, int $qty): void {
    $i = cart_find($c, $id);
    if ($qty <= 0) { if ($i >= 0) $c->removeAt($i); return; }
    if ($i >= 0) $c->get($i)->qty = $qty;
    else $c->add(new CartLine($id, $qty));
}
function cart_total(DynamicArray $c, HashMap $menu): float {
    $t = 0;
    for ($i = 0; $i < $c->size(); $i++) {
        $line = $c->get($i);
        $t += $menu->get($line->id)['price'] * $line->qty;
    }
    return $t;
}

function undo_load(): Stack {
    if (!(($_SESSION['undo'] ?? null) instanceof Stack)) $_SESSION['undo'] = new Stack();
    return $_SESSION['undo'];
}

function kitchen_queue(): Queue {
    $q = new Queue();
    foreach (db()->query("SELECT * FROM orders WHERE status = 'queued' ORDER BY id")->fetchAll() as $o) {
        $q->enqueue($o);
    }
    return $q;
}
function order_items(int $orderId): array {
    $st = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}
