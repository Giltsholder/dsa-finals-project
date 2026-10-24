<?php
/* ============================================================
 * Queue (first in, first out), built from scratch as a linked list.
 * `front` is the oldest item, `back` is the newest.
 * ============================================================ */

class QueueNode {
    public $value;
    public ?QueueNode $next = null;

    public function __construct($value) { $this->value = $value; }
}

class Queue {
    private ?QueueNode $front = null;
    private ?QueueNode $back = null;
    private int $size = 0;

    public function enqueue($value): void {
        $node = new QueueNode($value);
        if ($this->back === null) {
            $this->front = $node;
        } else {
            $this->back->next = $node;
        }
        $this->back = $node;
        $this->size++;
    }

    public function dequeue() {
        if ($this->front === null) return null;
        $value = $this->front->value;
        $this->front = $this->front->next;
        if ($this->front === null) $this->back = null;
        $this->size--;
        return $value;
    }

    public function peek()          { return $this->front === null ? null : $this->front->value; }
    public function isEmpty(): bool { return $this->front === null; }
    public function size(): int     { return $this->size; }
}