<?php

return [
    'grupos' => [
        'controle' => [],
        'transparencia' => [
            'explicacao-rea',
            'painel-ordenacao',
            'reas-ocultos',
            'painel-contexto',
            'progresso-busca',
        ],
        'escrutabilidade' => [
            'explicacao-rea',
            'painel-ordenacao',
            'reas-ocultos',
            'painel-contexto',
            'progresso-busca',
            'escrutabilidade',
        ],
    ],

    'forcar_grupo' => env('EXPERIMENTO_FORCAR_GRUPO'),

    'forcar_flags' => env('EXPERIMENTO_FORCAR_FLAGS', ''),

    'parametro_url' => env('EXPERIMENTO_PARAMETRO', 'grupo'),

    'codigos' => [
        'controle' => env('EXPERIMENTO_CODIGO_CONTROLE', 'controle'),
        'transparencia' => env('EXPERIMENTO_CODIGO_TRANSPARENCIA', 'transparencia'),
        'escrutabilidade' => env('EXPERIMENTO_CODIGO_ESCRUTABILIDADE', 'escrutabilidade'),
    ],

    'sem_link' => env('EXPERIMENTO_SEM_LINK', 'escrutabilidade'),

    'salvar_preferencia_na_busca' => (bool) env('EXPERIMENTO_SALVAR_PREFERENCIA', true),
];
