<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    use HasFactory;

    public const TYPES = [
        'comment' => 'Comment',
        'correction' => 'Correction',
        'buyer_email' => 'Buyer Email',
        'reply' => 'My Reply',
    ];

    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'text',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TaskImage::class)->orderBy('id');
    }
}
