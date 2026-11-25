<?php

return [
    'provedor' => env('LLM_PROVEDOR', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'modelo' => env('OLLAMA_MODELO', 'gemma3:4b'),

        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '10m'),

        'itens_por_chamada' => (int) env('OLLAMA_ITENS_POR_CHAMADA', 1),
    ],

    'groq' => [
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        'chave' => env('GROQ_API_KEY'),
        'modelo' => env('GROQ_MODELO', 'openai/gpt-oss-20b'),

        'espera_max_segundos' => (int) env('GROQ_ESPERA_MAX_SEGUNDOS', 60),

        'itens_por_chamada' => (int) env('GROQ_ITENS_POR_CHAMADA', 10),

        'extras' => [
            'reasoning_effort' => env('GROQ_ESFORCO', 'low'),
            'include_reasoning' => false,
        ],
    ],

    'timeout' => (int) env('LLM_TIMEOUT', 10),

    'aquecer' => (bool) env('LLM_AQUECER', true),
    'timeout_aquecimento' => (int) env('LLM_TIMEOUT_AQUECIMENTO', 60),

    'concorrencia' => (int) env('LLM_CONCORRENCIA', 4),

    'orcamento_segundos' => (int) env('LLM_ORCAMENTO_SEGUNDOS', 240),

    'falhas_seguidas_max' => (int) env('LLM_FALHAS_SEGUIDAS_MAX', 3),

    'pausa_apos_falha_segundos' => (int) env('LLM_PAUSA_APOS_FALHA_SEGUNDOS', 60),

    'cache_dias' => (int) env('LLM_CACHE_DIAS', 30),
];
