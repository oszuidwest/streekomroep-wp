<?php

namespace Streekomroep;

/**
 * Lazy ACF options access for the Timber context.
 *
 * Loading all options with get_fields('option') formats every field on every
 * request, including the complete television schedule and the front-page block
 * configuration. Fetching per field keeps pages that use two or three options
 * from paying for all of them.
 */
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
