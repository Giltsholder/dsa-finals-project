<?php
/* Sign-up, login and staff accounts. */
require_once __DIR__ . '/bootstrap.php';

function find_user(string $username): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([strtolower(trim($username))]);
    return $st->fetch() ?: null;
}

/* Remember who is signed in, and which restaurant they belong to */
function start_session_for(array $user): void {
    $st = db()->prepare('SELECT * FROM restaurants WHERE id = ?');
    $st->execute([$user['restaurant_id']]);
    $r = $st->fetch();
    session_regenerate_id(true);
    $_SESSION = [
        'role' => $user['role'], 'name' => $user['username'],
        'restaurant_id' => (int)$r['id'], 'restaurant_name' => $r['name'], 'slug' => $r['slug'],
    ];
}

function make_slug(string $name): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    if ($base === '') $base = 'restaurant';
    $slug = $base;
    $n = 2;
    $st = db()->prepare('SELECT 1 FROM restaurants WHERE slug = ?');
    while (true) {
        $st->execute([$slug]);
        if (!$st->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

/* Create a restaurant (blank menu) plus its owner account, then sign the owner in */
function register_owner(string $restaurant, string $email, string $password): string {
    $restaurant = trim($restaurant);
    $email = strtolower(trim($email));
    if ($restaurant === '') return 'Please enter your restaurant name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    if (strlen($password) < 6) return 'Password must be at least 6 characters.';
    if (find_user($email)) return 'That email is already registered. Try logging in.';

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO restaurants (name, slug) VALUES (?, ?)')->execute([$restaurant, make_slug($restaurant)]);
    $rid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (restaurant_id, username, password_hash, role) VALUES (?, ?, ?, 'owner')")
        ->execute([$rid, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $pdo->commit();

    start_session_for(find_user($email));
    return '';
}

function add_staff(int $rid, string $username, string $password): string {
    $username = strtolower(trim($username));
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) return 'Username: 3 to 30 letters, numbers, dots, dashes or underscores.';
    if (strlen($password) < 6) return 'Password must be at least 6 characters.';
    if (find_user($username)) return 'That username is taken. Try another.';
    db()->prepare("INSERT INTO users (restaurant_id, username, password_hash, role) VALUES (?, ?, ?, 'kitchen')")
        ->execute([$rid, $username, password_hash($password, PASSWORD_DEFAULT)]);
    return '';
}

function delete_staff(int $rid, int $userId): string {
    db()->prepare("DELETE FROM users WHERE id = ? AND restaurant_id = ? AND role = 'kitchen'")->execute([$userId, $rid]);
    return '';
}

function set_table_count(int $rid, int $count): string {
    if ($count < 1 || $count > 200) return 'Number of tables must be between 1 and 200.';
    db()->prepare('UPDATE restaurants SET table_count = ? WHERE id = ?')->execute([$count, $rid]);
    return '';
}
