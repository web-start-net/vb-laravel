<?php

declare(strict_types=1);

namespace WsForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $table = 'ws_form_fields';
    public $timestamps = false;
    protected $guarded = [];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }
}
