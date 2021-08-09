<?php

namespace Test\Generic;

class Collection<T> implements
    \ArrayAccess,
    \Iterator,
    \Countable
{
    protected \ArrayIterator $iterator;

    public function __construct(array $collection = [])
{
    $this->iterator = new \ArrayIterator($collection);
}

    public function current(): T
{
    return $this->iterator->current();
}

    public function offsetGet($offset): ?T
{
    return $this->iterator[$offset] ?? null;
}

    public function offsetSet($offset, $value)
{
    if(!$value instanceof T) {
        throw new \Exception();
    }

    if (is_null($offset)) {
        $this->iterator[] = $value;
    } else {
        $this->iterator[$offset] = $value;
    }
}

    public function offsetExists($offset): bool
{
    return $this->iterator->offsetExists($offset);
}

    public function offsetUnset($offset)
{
    unset($this->iterator[$offset]);
}

    public function next()
{
    $this->iterator->next();
}

    public function key()
{
    return $this->iterator->key();
}

    public function valid(): bool
{
    return $this->iterator->valid();
}

    public function rewind()
{
    $this->iterator->rewind();
}

    public function count(): int
{
    return count($this->iterator);
}
}
