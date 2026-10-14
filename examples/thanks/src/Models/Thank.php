<?php

declare(strict_types=1);

namespace WsThanks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VbLaravel\Models\Node;
use VbLaravel\Models\User;

class Thank extends Model
{
    protected $table = 'wsthanks_thanks';
    protected $dateFormat = 'U';
    public $timestamps = false;
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid', 'userid');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'nodeid', 'nodeid');
    }
}
