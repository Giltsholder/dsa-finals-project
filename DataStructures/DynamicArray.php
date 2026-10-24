<?php

class ArraySlot {
    public $value = null;
    public ?ArraySlot $next = null;
}

class DynamicArray {
    private const MIN_CAPACITY = 4;

    private ?ArraySlot $head;
    private int $capacity;
    private int $size = 0;

    public function __construct(int $capacity = self::MIN_CAPACITY) {
        if ($capacity < self::MIN_CAPACITY) $capacity = self::MIN_CAPACITY;
        $this->capacity = $capacity;
        $this->head = self::allocate($capacity);
    }

    private static function allocate(int $count): ?ArraySlot {
        $head = null;
        for ($i = 0; $i < $count; $i++) {
            $slot = new ArraySlot();
            $slot->next = $head;
            $head = $slot;
        }
        return $head;
    }

    private function slotAt(int $index): ArraySlot {
        $slot = $this->head;
        for ($i = 0; $i < $index; $i++) $slot = $slot->next;
        return $slot;
    }

    private function resize(int $newCapacity): void {
        $newHead = self::allocate($newCapacity);
        $from = $this->head;
        $to = $newHead;
        for ($i = 0; $i < $this->size; $i++) {
            $to->value = $from->value;
            $from = $from->next;
            $to = $to->next;
        }
        $this->head = $newHead;
        $this->capacity = $newCapacity;
    }

    public function add($value): void {
        if ($this->size === $this->capacity) $this->resize($this->capacity * 2);
        $this->slotAt($this->size)->value = $value;
        $this->size++;
    }

    public function get(int $index) {
        if ($index < 0 || $index >= $this->size) return null;
        return $this->slotAt($index)->value;
    }

    public function set(int $index, $value): void {
        if ($index < 0 || $index >= $this->size) return;
        $this->slotAt($index)->value = $value;
    }

    public function removeAt(int $index): void {
        if ($index < 0 || $index >= $this->size) return;
        $slot = $this->slotAt($index);
        for ($i = $index; $i < $this->size - 1; $i++) {   // shift everything after it left
            $slot->value = $slot->next->value;
            $slot = $slot->next;
        }
        $slot->value = null;
        $this->size--;

        if ($this->capacity > self::MIN_CAPACITY && $this->size <= intdiv($this->capacity, 4)) {
            $half = intdiv($this->capacity, 2);
            $this->resize($half < self::MIN_CAPACITY ? self::MIN_CAPACITY : $half);
        }
    }

    public function size(): int     { return $this->size; }
    public function capacity(): int { return $this->capacity; }
    public function isEmpty(): bool { return $this->size === 0; }
}
