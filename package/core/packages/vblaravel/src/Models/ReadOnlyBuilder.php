<?php

declare(strict_types=1);

namespace VbLaravel\Models;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;

class ReadOnlyBuilder extends Builder
{
    private function throwReadOnlyException(string $method): never
    {
        $class = $this->model ? get_class($this->model) : 'Model';
        throw new BadMethodCallException(
            "Cannot call '{$method}' on read-only core vBulletin model {$class}. Writes to core tables must be executed via native vBulletin APIs."
        );
    }

    public function insert(array $values): bool
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function insertGetId(array $values, $sequence = null): int
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function insertOrIgnore(array $values): int
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function update(array $values)
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function updateFrom(array $values)
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function updateOrInsert(array $attributes, array $values = [])
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function delete()
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function forceDelete()
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }

    public function truncate()
    {
        $this->throwReadOnlyException(__FUNCTION__);
    }
}
