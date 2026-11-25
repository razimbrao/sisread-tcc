<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExplanationEvent extends Model
{
    public const ACOES = ['abriu_explicacao', 'abriu_ordenacao', 'abriu_contexto', 'abriu_ocultos', 'abriu_correcao'];

    protected $fillable = [
        'searched_at',
        'user_id',
        'acao',
        'repositorio',
        'titulo',
        'faixa',
        'grupo',
    ];
}
