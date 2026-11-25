<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Data extends Model
{
    use HasFactory;

    protected $fillable = [
        'data',
        'searched_at',
        'finished',
        'stars',
        'time',
        'grupo',
        'flags',
        'participante',
        'contexto',
    ];

    protected $casts = [
        'flags' => 'array',
        'contexto' => 'array',
    ];

    public function feedbackReasons(): BelongsToMany
    {
        return $this->belongsToMany(FeedbackReason::class, 'data_reasons', 'data_id', 'feedback_reason_id')
            ->withPivot('feedback')
            ->withTimestamps();
    }
}
