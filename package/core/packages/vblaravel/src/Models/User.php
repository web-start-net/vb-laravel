<?php

declare(strict_types=1);

namespace VbLaravel\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends ReadOnlyModel
{
    protected $table = 'user';
    protected $primaryKey = 'userid';

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'userid', 'userid');
    }
}
