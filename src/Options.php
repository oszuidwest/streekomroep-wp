<?php

namespace Streekomroep;

/** Provides lazy, per-field ACF options access for Timber. */
class Options implements \ArrayAccess
{
    private array $cache = [];

    public function offsetExists(mixed $offset): bool
    {
        return $this->offsetGet($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!array_key_exists($offset, $this->cache)) {
            $this->cache[$offset] = get_field($offset, 'option');
        }

        return $this->cache[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->cache[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->cache[$offset]);
    }
}
