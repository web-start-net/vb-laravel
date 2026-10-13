<?php

declare(strict_types=1);

namespace WsForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $table = 'ws_forms';
    protected $dateFormat = 'U';
    protected $guarded = [];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_id')->orderBy('display_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }
}
