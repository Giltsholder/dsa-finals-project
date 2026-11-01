<?php
require_once __DIR__ . '/DataStructures/DynamicArray.php';
require_once __DIR__ . '/DataStructures/Stack.php';
require_once __DIR__ . '/DataStructures/Queue.php';
require_once __DIR__ . '/DataStructures/HashMap.php';

/* Small record classes held inside the structures (defined before session_start
   so the cart and undo objects can be restored from the session). */
class CartLine  { public function __construct(public int $id, public int $qty) {} }
class UndoEntry { public function __construct(public int $id, public int $prev) {} }

session_start();

/* ---------- Database: created automatically on first run, starts with NO menu ---------- */
function open_db(string $file): PDO {
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

/* True only for a database built by the old single-restaurant version: it has a
   users table but no restaurant_id column. */
function schema_is_outdated(PDO $pdo): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM sqlite_master WHERE type = ? AND name = ?');
    $st->execute(['table', 'users']);
    if ((int)$st->fetchColumn() === 0) return false;            // brand new file, nothing to do
    foreach ($pdo->query('PRAGMA table_info(users)') as $column) {
        if ($column['name'] === 'restaurant_id') return false;  // already the current layout
    }
    return true;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir  = __DIR__ . '/data';
    $file = $dir . '/restaurant.sqlite';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\n");

    $pdo = open_db($file);

    /* CREATE TABLE IF NOT EXISTS never touches a table that already exists, so an
       outdated file would keep its old columns and every INSERT that mentions
       restaurant_id would fail. Move it aside and start a current database. */
    if (schema_is_outdated($pdo)) {
        $pdo = null;                                            // close first, or Windows won't rename
        $backup = $dir . '/restaurant.old-' . date('Ymd-His') . '.sqlite';
        if (!rename($file, $backup)) {
            throw new RuntimeException('Could not move the outdated database aside. Delete ' . $file . ' and reload.');
        }
        $_SESSION = [];                       // old logins point at restaurants that no longer exist
        session_regenerate_id(true);
        $pdo = open_db($file);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS restaurants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,                 -- used in the customer link
            table_count INTEGER NOT NULL DEFAULT 20
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            restaurant_id INTEGER NOT NULL REFERENCES restaurants(id),
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL                          -- owner | kitchen
        );
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            restaurant_id INTEGER NOT NULL REFERENCES restaurants(id),
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS dishes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            restaurant_id INTEGER NOT NULL REFERENCES restaurants(id),
            category_id INTEGER NOT NULL REFERENCES categories(id),
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL,
            available INTEGER NOT NULL DEFAULT 1        -- 0 = sold out
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            restaurant_id INTEGER NOT NULL REFERENCES restaurants(id),
            table_no INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            note TEXT,
            status TEXT NOT NULL DEFAULT 'queued',      -- queued | cooking | served
            total REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id),
            dish_id INTEGER NOT NULL,
            dish_name TEXT NOT NULL,                    -- copied at order time, so later menu edits
            price REAL NOT NULL,                        -- never change what an old order shows
            qty INTEGER NOT NULL
        );
    ");
    return $pdo;
}


function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }
function redirect(string $to): never { header('Location: ' . $to); exit; }

function require_role(string ...$roles): void {
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) redirect('index.php');
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}

function flash(string $message): void { $_SESSION['flash'] = $message; }
function take_flash(): string {
    $m = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $m;
}

function page_start(string $title, string $right = '', string $extraHead = '', string $heading = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= $extraHead ?>
<title><?= e($title) ?></title>
<link rel="stylesheet" href="style.css">
<script src="password-toggle.js" defer></script>
<script src="header-menu.js" defer></script>
</head>
<body>
<header class="top">
    <h1><?= e($heading !== '' ? $heading : $title) ?></h1>
    <div class="top-right"><?= $right ?></div>
</header>
<?php }
function page_end(): void { echo "</body>\n</html>\n"; }

/* Hamburger menu for the header. Links are label => href. */
function header_menu(array $links, string $user = '', string $note = ''): string {
    $html = '<div class="header-account">';
    if ($note !== '') $html .= '<span class="menu-note">' . e($note) . '</span>';
    $html .= '<details class="menu">'
           . '<summary class="menu-button" aria-label="Account menu" title="Account menu">'
           . '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" '
           . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">'
           . '<path d="M4 7h16M4 12h16M4 17h16"/></svg>'
           . '</summary>'
           . '<nav class="menu-panel" aria-label="Account menu">';
    if ($user !== '') $html .= '<span class="menu-user">' . e($user) . '</span>';
    foreach ($links as $label => $href) {
        $html .= '<a href="' . e($href) . '">' . e($label) . '</a>';
    }
    return $html . '</nav></details></div>';
}

function owner_nav(): string {
    return header_menu([
        'Menu'             => 'admin.php',
        'Staff & Settings' => 'staff.php',
        'Kitchen Screen'   => 'kitchen.php',
        'Sign Out'         => 'logout.php',
    ]);
}

function load_menu(int $restaurantId, bool $customerView = true): array {
    $cats = db()->prepare('SELECT * FROM categories WHERE restaurant_id = ? ORDER BY sort_order, id');
    $cats->execute([$restaurantId]);
    $dishQuery = db()->prepare(
        'SELECT * FROM dishes WHERE category_id = ?' . ($customerView ? ' AND available = 1' : '') . ' ORDER BY id'
    );

    $map = new HashMap();
    $grouped = [];
    foreach ($cats->fetchAll() as $c) {
        $dishQuery->execute([$c['id']]);
        $dishes = $dishQuery->fetchAll();
        if ($customerView && !$dishes) continue;        
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

function cart_prune(DynamicArray $c, HashMap $menu): void {
    for ($i = $c->size() - 1; $i >= 0; $i--) {
        if (!$menu->has($c->get($i)->id)) $c->removeAt($i);
    }
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

function kitchen_queue(int $restaurantId): Queue {
    $q = new Queue();
    $st = db()->prepare("SELECT * FROM orders WHERE status = 'queued' AND restaurant_id = ? ORDER BY id");
    $st->execute([$restaurantId]);
    foreach ($st->fetchAll() as $o) $q->enqueue($o);
    return $q;
}
function order_items(int $orderId): array {
    $st = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}
