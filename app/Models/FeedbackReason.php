<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeedbackReason extends Model
{
    use HasFactory;

    public const FRASES_EXPLICACAO = [
        'As explicações das recomendações estavam erradas ou confusas.',
        'As explicações não ajudaram a entender a ordem dos resultados.',
    ];

    public function data(): BelongsToMany
    {
        return $this->belongsToMany(Data::class, 'data_reasons', 'feedback_reason_id', 'data_id')
            ->withPivot('feedback')
            ->withTimestamps();
    }
}
