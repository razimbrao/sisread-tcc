<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Data;
use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessMecRed;
use App\Jobs\ProcessEduplay;

class RunRecommendationSimulation extends Command
{
    protected $signature = 'simulation:run-all';

    protected $description = 'Dispara os Jobs de REAs para todas as combinações de perfil, interesse e metas pedagógicas.';

    public function handle()
    {
        $matrix = [
            'educacao infantil' => [
                'reconhecimento de padrões', 'lógica', 'robótica', 'algoritmos', 'abstração'
            ],
            'ensino fundamental' => [
                'algoritmos', 'decomposição', 'lógica de programação', 'pensamento computacional',
                'robótica', 'reconhecimento de padrões', 'abstração'
            ],
            'ensino medio' => [
                'algoritmos', 'programação', 'abstração', 'decomposição', 'lógica',
                'estruturas de dados', 'robótica'
            ],
            'ensino superior' => [
                'programação', 'estruturas de dados', 'abstração', 'algoritmos',
                'decomposição', 'lógica de programação', 'pensamento computacional'
            ]
        ];

        $metas = [null, 'ma', 'mpa', 'mpe'];

        $types = ['texto', 'video', 'software', 'audio'];

        $totalDisparados = 0;
        $counter = 1;

        $this->info('🚀 Iniciando a simulação em massa do ecossistema...');

        foreach ($matrix as $profile => $interests) {
            foreach ($interests as $interest) {
                foreach ($metas as $meta) {
                    $timestampSession = time() . '_' . $counter;

                    Data::create([
                        'searched_at' => $timestampSession,
                        'data'        => json_encode([]),
                        'finished'    => false,
                        'time'        => 0
                    ]);

                    $interestApiSearch = $interest;

                    ProcessAquarela::dispatch($interestApiSearch, $types, $profile, $timestampSession, $meta);
                    ProcessMecRed::dispatch($interestApiSearch, $types, $profile, $interest, $timestampSession, $meta);
                    ProcessEduplay::dispatch($interestApiSearch, $profile, $timestampSession, $meta, $types);

                    $metaNome = $meta ?? 'Sem Meta (Padrão)';
                    $this->line("   [✔] Enfileirado: Perfil: {$profile} | Interesse: {$interest} | Meta: {$metaNome}");

                    $counter++;
                    $totalDisparados += 3;
                }
            }
        }

        $this->info("✨ Sucesso! Varredura concluída. {$totalDisparados} Jobs foram enviados para a fila.");
        $this->info("Certifique-se de que o comando 'php artisan queue:work' esteja rodando para processá-los.");
    }
}