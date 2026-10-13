<?php

declare(strict_types=1);

namespace VbLaravel\Models;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;

abstract class ReadOnlyModel extends Model
{
    /**
     * vBulletin timestamps are stored as Unix epoch integers (dateline).
     */
    protected $dateFormat = 'U';

    public $timestamps = false;

    public function newEloquentBuilder($query): ReadOnlyBuilder
    {
        return new ReadOnlyBuilder($query);
    }

    public function save(array $options = []): bool
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Writes to core tables must be executed via native vBulletin APIs.'
        );
    }

    public function delete(): ?bool
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Deletes must go through native vBulletin APIs.'
        );
    }

    public function forceDelete(): ?bool
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Deletes must go through native vBulletin APIs.'
        );
    }

    public static function create(array $attributes = [])
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Inserts must go through native vBulletin APIs.'
        );
    }

    public static function insert(array $values): bool
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Inserts must go through native vBulletin APIs.'
        );
    }

    public static function truncate(): void
    {
        throw new BadMethodCallException(
            static::class . ' is a read-only core vBulletin model. Writes must go through native vBulletin APIs.'
        );
    }
}
