<?php

declare(strict_types=1);

namespace VB\Bookmarks\Models;

use Illuminate\Database\Eloquent\Model;

final class Bookmark extends Model
{
    protected $table = 'vbbookmarks_bookmark';
    protected $fillable = ['userid', 'nodeid'];
    protected $casts = ['userid' => 'integer', 'nodeid' => 'integer'];
    public $timestamps = true;

    public function getTable()
    {
        return (defined('TABLE_PREFIX') ? TABLE_PREFIX : '') . parent::getTable();
    }
}
