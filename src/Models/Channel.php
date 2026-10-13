<?php

declare(strict_types=1);

namespace VbLaravel\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Channel extends ReadOnlyModel
{
    protected $table = 'channel';
    protected $primaryKey = 'nodeid';

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'nodeid', 'nodeid');
    }
}
