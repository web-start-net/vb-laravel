<?php

declare(strict_types=1);

namespace WsRatings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VbLaravel\Models\Node;
use VbLaravel\Models\User;

class Rating extends Model
{
    protected $table = 'wsratings_ratings';
    protected $dateFormat = 'U';
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid', 'userid');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'nodeid', 'nodeid');
    }

    protected static function booted(): void
    {
        static::saving(static function (Rating $rating): void {
            if (empty($rating->dateline)) {
                $rating->dateline = time();
            }
        });
    }
}
