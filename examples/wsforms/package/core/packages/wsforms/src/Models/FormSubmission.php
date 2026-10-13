<?php

declare(strict_types=1);

namespace WsForms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VbLaravel\Models\User;

class FormSubmission extends Model
{
    protected $table = 'ws_form_submissions';
    protected $dateFormat = 'U';
    public $timestamps = false;
    protected $guarded = [];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid', 'userid');
    }

    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'submission_id');
    }
}
