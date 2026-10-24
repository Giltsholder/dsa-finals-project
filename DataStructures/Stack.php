<?php
/* ============================================================
 * Stack (last in, first out), built from scratch as a linked list.
 * Each node points to the node underneath it; `top` is the newest.
 * ============================================================ */

class StackNode {
    public $value;
    public ?StackNode $below;

    public function __construct($value, ?StackNode $below) {
        $this->value = $value;
        $this->below = $below;
    }
}

class Stack {
    private ?StackNode $top = null;
    private int $size = 0;

    public function push($value): void {
        $this->top = new StackNode($value, $this->top);
        $this->size++;
    }

    public function pop() {
        if ($this->top === null) return null;
        $value = $this->top->value;
        $this->top = $this->top->below;
        $this->size--;
        return $value;
    }

    public function peek()          { return $this->top === null ? null : $this->top->value; }
    public function isEmpty(): bool { return $this->top === null; }
    public function size(): int     { return $this->size; }

    /* Keep only the newest $n entries (uses a second stack to flip the order twice). */
    public function keepNewest(int $n): void {
        $temp = new Stack();
        for ($i = 0; $i < $n && !$this->isEmpty(); $i++) $temp->push($this->pop());
        $this->top = null;
        $this->size = 0;
        while (!$temp->isEmpty()) $this->push($temp->pop());
    }
}
