<?php

namespace App\Features;

use App\Experimento\Experimento;

class GrupoExperimento
{
    public string $name = 'grupo-experimento';

    public function resolve(mixed $scope): string
    {
        return app(Experimento::class)->grupoInicial();
    }
}
