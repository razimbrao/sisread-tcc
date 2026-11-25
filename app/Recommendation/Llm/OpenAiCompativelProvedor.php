<?php

namespace App\Recommendation\Llm;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class OpenAiCompativelProvedor implements ProvedorLlm
{
    public function __construct(
        private string $url,
        private ?string $chave,
        private string $modelo,
        private array $extras = [],
        private int $esperaMaxima = 60,
    ) {
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    public function preparar(int $timeout): void
    {
    }

    private function endpoint(): string
    {
        return rtrim($this->url, '/').'/chat/completions';
    }

    public function gerarJson(array $prompts, int $timeout, ?array $schema = null): array
    {
        $prompts = array_values($prompts);
        $textos = array_fill_keys(array_keys($prompts), null);
        $pendentes = array_keys($prompts);
        $esperado = 0.0;

        while (true) {
            $respostas = $this->enviar($prompts, $pendentes, $timeout, $schema);
            $recusados = [];
            $espera = 0.0;

            foreach ($pendentes as $i) {
                $resposta = $respostas["p{$i}"] ?? null;

                if ($resposta instanceof Response && $resposta->status() === 429) {
                    $recusados[] = $i;
                    $espera = max($espera, self::retryAfter($resposta));

                    continue;
                }

                $texto = $resposta instanceof Response && $resposta->successful()
                    ? $resposta->json('choices.0.message.content')
                    : null;
                $textos[$i] = is_string($texto) ? $texto : null;
            }

            if ($recusados === [] || $esperado + $espera > $this->esperaMaxima) {
                return $textos;
            }

            Sleep::for($espera)->seconds();
            $esperado += $espera;
            $pendentes = $recusados;
        }
    }

    private function enviar(array $prompts, array $indices, int $timeout, ?array $schema): array
    {
        $endpoint = $this->endpoint();

        return Http::pool(fn (Pool $pool) => array_map(
            fn (int $i) => $pool->as("p{$i}")->withToken((string) $this->chave)->timeout($timeout)->post($endpoint, $this->corpo($prompts[$i], $schema)),
            $indices
        ));
    }

    private static function retryAfter(Response $resposta): float
    {
        $valor = $resposta->header('retry-after');

        return is_numeric($valor) ? max(0.1, (float) $valor) : 1.0;
    }

    private function corpo(string $prompt, ?array $schema): array
    {
        $formato = $schema === null
            ? ['type' => 'json_object']
            : ['type' => 'json_schema', 'json_schema' => ['name' => 'resposta', 'strict' => true, 'schema' => $schema]];

        return array_merge([
            'model' => $this->modelo,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => $formato,
        ], $this->extras);
    }
}
