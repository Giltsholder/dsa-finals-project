<?php
/* ============================================================
 * Hash map, built from scratch.
 *  - Own hash function (djb2) instead of a built-in one.
 *  - Collisions are handled by chaining: each bucket is a linked
 *    list of HashEntry nodes.
 *  - The bucket table is our own DynamicArray.
 *  - Doubles its bucket count when more than 75% full.
 * ============================================================ */

require_once __DIR__ . '/DynamicArray.php';

class HashEntry {
    public $key;
    public $value;
    public ?HashEntry $next;

    public function __construct($key, $value, ?HashEntry $next) {
        $this->key = $key;
        $this->value = $value;
        $this->next = $next;
    }
}

class HashMap {
    private DynamicArray $buckets;
    private int $capacity;
    private int $count = 0;

    public function __construct(int $capacity = 16) {
        $this->capacity = $capacity;
        $this->buckets = self::emptyTable($capacity);
    }

    private static function emptyTable(int $capacity): DynamicArray {
        $table = new DynamicArray($capacity);
        for ($i = 0; $i < $capacity; $i++) $table->add(null);
        return $table;
    }

    private static function hash($key, int $capacity): int {
        $text = (string)$key;
        $length = strlen($text);
        $h = 5381;
        for ($i = 0; $i < $length; $i++) {
            $h = (($h * 33) + ord($text[$i])) % 2147483647;
        }
        return $h % $capacity;
    }

    private function findEntry($key): ?HashEntry {
        $entry = $this->buckets->get(self::hash($key, $this->capacity));
        while ($entry !== null) {
            if ($entry->key === $key) return $entry;
            $entry = $entry->next;
        }
        return null;
    }

    public function put($key, $value): void {
        $existing = $this->findEntry($key);
        if ($existing !== null) { $existing->value = $value; return; }

        $index = self::hash($key, $this->capacity);
        $this->buckets->set($index, new HashEntry($key, $value, $this->buckets->get($index)));
        $this->count++;

        if ($this->count * 4 > $this->capacity * 3) $this->grow();
    }

    public function get($key) {
        $entry = $this->findEntry($key);
        return $entry === null ? null : $entry->value;
    }

    public function has($key): bool { return $this->findEntry($key) !== null; }
    public function size(): int     { return $this->count; }

    public function remove($key): void {
        $index = self::hash($key, $this->capacity);
        $entry = $this->buckets->get($index);
        $previous = null;
        while ($entry !== null) {
            if ($entry->key === $key) {
                if ($previous === null) $this->buckets->set($index, $entry->next);
                else $previous->next = $entry->next;
                $this->count--;
                return;
            }
            $previous = $entry;
            $entry = $entry->next;
        }
    }

    private function grow(): void {
        $newCapacity = $this->capacity * 2;
        $newTable = self::emptyTable($newCapacity);
        for ($i = 0; $i < $this->capacity; $i++) {
            $entry = $this->buckets->get($i);
            while ($entry !== null) {
                $next = $entry->next;                         // re-link each entry into the new table
                $index = self::hash($entry->key, $newCapacity);
                $entry->next = $newTable->get($index);
                $newTable->set($index, $entry);
                $entry = $next;
            }
        }
        $this->buckets = $newTable;
        $this->capacity = $newCapacity;
    }
}