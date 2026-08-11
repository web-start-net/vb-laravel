<?php

declare(strict_types=1);

namespace VB\LaravelTest;

use Illuminate\Database\Eloquent\Model;

final class Note extends Model
{
    protected $table = 'vblaravel_test_note';
    protected $fillable = ['userid', 'message'];
    public $timestamps = true;
}
