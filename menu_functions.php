<?php
require_once __DIR__ . '/bootstrap.php';

function owns_category(int $rid, int $categoryId): bool {
    $st = db()->prepare('SELECT 1 FROM categories WHERE id = ? AND restaurant_id = ?');
    $st->execute([$categoryId, $rid]);
    return (bool)$st->fetch();
}

function valid_price(float $price): bool { return $price > 0 && $price < 1000000; }

function add_category(int $rid, string $name): string {
    $name = trim($name);
    if ($name === '') return 'Please enter a category name.';
    $st = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories WHERE restaurant_id = ?');
    $st->execute([$rid]);
    db()->prepare('INSERT INTO categories (restaurant_id, name, sort_order) VALUES (?, ?, ?)')
        ->execute([$rid, $name, (int)$st->fetchColumn()]);
    return '';
}

function delete_category(int $rid, int $categoryId): string {
    if (!owns_category($rid, $categoryId)) return 'That category was not found.';
    $st = db()->prepare('SELECT COUNT(*) FROM dishes WHERE category_id = ?');
    $st->execute([$categoryId]);
    if ((int)$st->fetchColumn() > 0) return 'Remove or move the dishes in that category first.';
    db()->prepare('DELETE FROM categories WHERE id = ? AND restaurant_id = ?')->execute([$categoryId, $rid]);
    return '';
}

function add_dish(int $rid, int $categoryId, string $name, string $description, float $price): string {
    $name = trim($name);
    if ($name === '') return 'Please enter a dish name.';
    if (!valid_price($price)) return 'Please enter a price above zero.';
    if (!owns_category($rid, $categoryId)) return 'Please choose one of your categories.';
    db()->prepare('INSERT INTO dishes (restaurant_id, category_id, name, description, price) VALUES (?, ?, ?, ?, ?)')
        ->execute([$rid, $categoryId, $name, trim($description), $price]);
    return '';
}

function update_dish(int $rid, int $dishId, int $categoryId, string $name, string $description, float $price): string {
    $name = trim($name);
    if ($name === '') return 'Please enter a dish name.';
    if (!valid_price($price)) return 'Please enter a price above zero.';
    if (!owns_category($rid, $categoryId)) return 'Please choose one of your categories.';
    db()->prepare('UPDATE dishes SET category_id = ?, name = ?, description = ?, price = ? WHERE id = ? AND restaurant_id = ?')
        ->execute([$categoryId, $name, trim($description), $price, $dishId, $rid]);
    return '';
}

function set_available(int $rid, int $dishId, bool $available): string {
    db()->prepare('UPDATE dishes SET available = ? WHERE id = ? AND restaurant_id = ?')
        ->execute([$available ? 1 : 0, $dishId, $rid]);
    return '';
}

function delete_dish(int $rid, int $dishId): string {
    db()->prepare('DELETE FROM dishes WHERE id = ? AND restaurant_id = ?')->execute([$dishId, $rid]);
    return '';
}
