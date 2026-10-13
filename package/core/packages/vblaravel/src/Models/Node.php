<?php

declare(strict_types=1);

namespace VbLaravel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends ReadOnlyModel
{
    protected $table = 'node';
    protected $primaryKey = 'nodeid';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid', 'userid');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parentid', 'nodeid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parentid', 'nodeid');
    }
}
