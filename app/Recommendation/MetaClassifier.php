<?php

namespace App\Recommendation;

use App\Recommendation\Llm\ProvedorLlm;
use Illuminate\Support\Facades\Cache;

class MetaClassifier
{
    public const VERSAO_PROMPT = 1;

    public const VERSAO_PROMPT_LOTE = 1;

    public const CLASSES = [
        'ma' => 'Aprendizagem',
        'mpa' => 'Performance Aproximação',
        'mpe' => 'Performance Evitação',
    ];

    public const CHAVE_PAUSA = 'meta_llm:pausa';

    private array $metricas = [
        'ollama_time' => 0.0,
        'ollama_calls' => 0,
        'ollama_errors' => 0,
        'llm_cache_hits' => 0,
        'llm_nao_avaliados' => 0,
    ];

    private int $falhasSeguidas = 0;

    private bool $desistiu = false;

    private bool $preparado = false;

    public function __construct(private ?ProvedorLlm $provedor, private array $config = [])
    {
        $this->config += [
            'timeout' => 10,
            'concorrencia' => 4,
            'orcamento_segundos' => 240,
            'falhas_seguidas_max' => 3,
            'pausa_apos_falha_segundos' => 60,
            'cache_dias' => 30,
            'aquecer' => true,
            'timeout_aquecimento' => 60,

            'itens_por_chamada' => 1,
        ];
    }

    public function modelo(): ?string
    {
        return $this->provedor?->modelo();
    }

    public function metricas(): array
    {
        return $this->metricas;
    }

    public function criterios(string $metaUsuario, array $itens): array
    {
        $criterios = [];

        foreach ($this->classificar($itens) as $i => $c) {
            $criterios[$i] = RuleClassifier::criterioMeta($metaUsuario, $c['valor'], $this->modelo(), $c['duracao'], $c['evidencia']);
        }

        return $criterios;
    }

    public function classificar(array $itens): array
    {
        $resultado = [];
        $pendentes = [];

        foreach ($itens as $i => $item) {
            $chave = (string) $item['chave'];

            if (isset($pendentes[$chave])) {
                $pendentes[$chave][] = $i;

                continue;
            }

            $emCache = $this->lerCache($chave);
            if ($emCache !== null) {
                $this->metricas['llm_cache_hits']++;
                $resultado[$i] = $this->classificado($emCache, null, $item, 'classificação reaproveitada de uma busca anterior');

                continue;
            }

            $pendentes[$chave] = [$i];
        }

        $chamadas = array_chunk($pendentes, $this->itensPorChamada(), true);

        foreach (array_chunk($chamadas, max(1, (int) $this->config['concorrencia'])) as $lote) {
            $motivo = $this->motivoParaNaoConsultar();

            if ($motivo !== null) {
                foreach ($lote as $chamada) {
                    foreach (array_merge(...array_values($chamada)) as $i) {
                        $resultado[$i] = $this->naoAvaliado($motivo);
                    }
                }

                continue;
            }

            $this->preparar();

            $grupos = array_map(fn (array $chamada) => array_values($chamada), $lote);
            $prompts = array_map(fn (array $chamada) => $this->montarPrompt(array_map(fn (array $indices) => $itens[$indices[0]], $chamada)), $grupos);

            $inicio = microtime(true);
            try {
                $textos = $this->provedor->gerarJson($prompts, (int) $this->config['timeout'], self::schema($this->itensPorChamada()));
            } catch (\Throwable $e) {
                $textos = [];
            }
            $duracao = microtime(true) - $inicio;

            $this->metricas['ollama_time'] += $duracao;
            $this->metricas['ollama_calls'] += count($prompts);

            foreach ($grupos as $j => $chamada) {
                $texto = $textos[$j] ?? null;

                if ($texto === null) {
                    $this->metricas['ollama_errors']++;
                    $this->falhasSeguidas++;
                    $valores = [];
                } else {
                    $this->falhasSeguidas = 0;
                    $valores = $this->itensPorChamada() === 1
                        ? [1 => self::interpretar($texto)]
                        : self::interpretarLote($texto, count($chamada));
                }

                foreach ($chamada as $posicao => $indices) {
                    $item = $itens[$indices[0]];
                    $valor = $valores[$posicao + 1] ?? null;

                    if ($texto === null) {
                        $r = $this->naoAvaliado('a IA não respondeu (erro ou tempo esgotado)');
                    } elseif ($valor === null) {
                        $this->metricas['ollama_errors']++;
                        $r = $this->naoAvaliado('a IA respondeu fora do formato esperado');
                    } else {
                        $this->gravarCache((string) $item['chave'], $valor);
                        $r = $this->classificado($valor, $duracao, $item);
                    }

                    foreach ($indices as $i) {
                        $resultado[$i] = $r;
                    }
                }
            }

            if ($this->falhasSeguidas >= (int) $this->config['falhas_seguidas_max']) {
                $this->desistiu = true;
                Cache::put(self::CHAVE_PAUSA, true, max(1, (int) $this->config['pausa_apos_falha_segundos']));
            }
        }

        foreach ($resultado as $r) {
            if ($r['valor'] === null) {
                $this->metricas['llm_nao_avaliados']++;
            }
        }

        ksort($resultado);

        return $resultado;
    }

    public static function interpretar(string $texto): ?string
    {
        $json = json_decode($texto, true);

        return self::canonica(is_array($json) ? ($json['meta'] ?? null) : null);
    }

    public static function interpretarLote(string $texto, int $quantidade): array
    {
        $json = json_decode($texto, true);
        $metas = is_array($json) && is_array($json['metas'] ?? null) ? $json['metas'] : [];
        $valores = [];
        $repetidos = [];

        foreach ($metas as $m) {
            $rea = is_array($m) ? ($m['rea'] ?? null) : null;

            if (! is_int($rea) || $rea < 1 || $rea > $quantidade) {
                continue;
            }

            if (array_key_exists($rea, $valores)) {
                $repetidos[$rea] = true;
            }

            $valores[$rea] = self::canonica($m['meta'] ?? null);
        }

        $resultado = [];
        foreach (range(1, $quantidade) as $rea) {
            $resultado[$rea] = isset($repetidos[$rea]) ? null : ($valores[$rea] ?? null);
        }

        return $resultado;
    }

    private static function canonica(mixed $meta): ?string
    {
        if (! is_string($meta) || trim($meta) === '') {
            return null;
        }

        $casam = array_filter(array_keys(self::CLASSES), fn (string $codigo) => RuleClassifier::casaMeta($meta, $codigo));

        return count($casam) === 1 ? self::CLASSES[reset($casam)] : null;
    }

    public static function schema(int $itensPorChamada): array
    {
        $meta = ['type' => 'string', 'enum' => array_values(self::CLASSES)];

        if ($itensPorChamada === 1) {
            return ['type' => 'object', 'properties' => ['meta' => $meta], 'required' => ['meta'], 'additionalProperties' => false];
        }

        return [
            'type' => 'object',
            'properties' => ['metas' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['rea' => ['type' => 'integer'], 'meta' => $meta],
                'required' => ['rea', 'meta'],
                'additionalProperties' => false,
            ]]],
            'required' => ['metas'],
            'additionalProperties' => false,
        ];
    }

    private function itensPorChamada(): int
    {
        return max(1, (int) $this->config['itens_por_chamada']);
    }

    private function montarPrompt(array $itens): string
    {
        return $this->itensPorChamada() === 1 ? $this->prompt($itens[0]) : $this->promptLote($itens);
    }

    public function prompt(array $item): string
    {
        $titulo = self::campo($item['titulo'] ?? null);
        $descricao = self::campo($item['descricao'] ?? null);
        $tipo = self::campo($item['tipo'] ?? null);
        $dtype = empty($item['dtype']) ? '' : "\nDType: {$item['dtype']}";

        return <<<PROMPT
        Classifique o REA em APENAS uma das metas abaixo.

        Título: {$titulo}
        Descrição: {$descricao}
        Tipo: {$tipo}{$dtype}

        Critérios:

        - Aprendizagem: prioriza compreensão profunda, construção de conhecimento, investigação, criação de projetos, resolução de problemas, desenvolvimento de habilidades e autonomia.
        - Performance Aproximação: prioriza demonstrar desempenho, alcançar resultados, competir, testar conhecimentos, desafios, jogos, avaliações ou obtenção de reconhecimento.
        - Performance Evitação: prioriza reduzir dificuldades, facilitar a entrada no tema, apresentar conceitos introdutórios, básicos ou simplificados, minimizando erros e insegurança.

        Escolha a meta predominante considerando principalmente o objetivo pedagógico do recurso, não apenas palavras isoladas do título.

        Responda SOMENTE com um JSON no formato {"meta": "<opção>"}, em que <opção> é uma de:
        Aprendizagem
        Performance Aproximação
        Performance Evitação
        PROMPT;
    }

    public function promptLote(array $itens): string
    {
        $reas = [];
        foreach (array_values($itens) as $n => $item) {
            $titulo = self::campo($item['titulo'] ?? null);
            $descricao = self::campo($item['descricao'] ?? null);
            $tipo = self::campo($item['tipo'] ?? null);
            $dtype = empty($item['dtype']) ? '' : "\nDType: {$item['dtype']}";
            $reas[] = 'REA '.($n + 1)."\nTítulo: {$titulo}\nDescrição: {$descricao}\nTipo: {$tipo}{$dtype}";
        }
        $lista = implode("\n\n", $reas);

        return <<<PROMPT
        Classifique CADA um dos REAs abaixo em APENAS uma das metas. Avalie cada REA de forma independente dos demais.

        Critérios:

        - Aprendizagem: prioriza compreensão profunda, construção de conhecimento, investigação, criação de projetos, resolução de problemas, desenvolvimento de habilidades e autonomia.
        - Performance Aproximação: prioriza demonstrar desempenho, alcançar resultados, competir, testar conhecimentos, desafios, jogos, avaliações ou obtenção de reconhecimento.
        - Performance Evitação: prioriza reduzir dificuldades, facilitar a entrada no tema, apresentar conceitos introdutórios, básicos ou simplificados, minimizando erros e insegurança.

        Escolha a meta predominante considerando principalmente o objetivo pedagógico do recurso, não apenas palavras isoladas do título.

        {$lista}

        Responda SOMENTE com um JSON no formato {"metas": [{"rea": 1, "meta": "<opção>"}, {"rea": 2, "meta": "<opção>"}, ...]}, com um item para cada REA, em que <opção> é uma de:
        Aprendizagem
        Performance Aproximação
        Performance Evitação
        PROMPT;
    }

    private function preparar(): void
    {
        if ($this->preparado || ! $this->config['aquecer']) {
            return;
        }

        $this->preparado = true;
        $inicio = microtime(true);

        try {
            $this->provedor->preparar((int) $this->config['timeout_aquecimento']);
        } catch (\Throwable $e) {
        }

        $this->metricas['ollama_time'] += microtime(true) - $inicio;
    }

    private function motivoParaNaoConsultar(): ?string
    {
        if ($this->provedor === null) {
            return 'a classificação por IA está desativada nesta instalação';
        }

        if ($this->desistiu || Cache::has(self::CHAVE_PAUSA)) {
            return 'a IA está indisponível (falhou várias vezes seguidas) e não foi consultada para este recurso';
        }

        if ($this->metricas['ollama_time'] >= (float) $this->config['orcamento_segundos']) {
            return "o tempo reservado à classificação por IA ({$this->config['orcamento_segundos']}s por repositório) acabou antes deste recurso";
        }

        return null;
    }

    private function classificado(string $valor, ?float $duracao, array $item, ?string $evidencia = null): array
    {
        $partes = array_filter([$evidencia, $item['evidencia'] ?? null]);

        return [
            'valor' => $valor,
            'duracao' => $duracao,
            'evidencia' => $partes ? implode('; ', $partes) : null,
        ];
    }

    private function naoAvaliado(string $motivo): array
    {
        return ['valor' => null, 'duracao' => null, 'evidencia' => $motivo];
    }

    private function chaveCache(string $chave): string
    {
        $versao = $this->itensPorChamada() === 1 ? 'v'.self::VERSAO_PROMPT : 'lote-v'.self::VERSAO_PROMPT_LOTE;

        return 'meta_llm:'.$versao.':'.$this->modelo().':'.$chave;
    }

    private function lerCache(string $chave): ?string
    {
        if ($this->provedor === null || (int) $this->config['cache_dias'] <= 0) {
            return null;
        }

        $valor = Cache::get($this->chaveCache($chave));

        return in_array($valor, self::CLASSES, true) ? $valor : null;
    }

    private function gravarCache(string $chave, string $valor): void
    {
        if ((int) $this->config['cache_dias'] > 0) {
            Cache::put($this->chaveCache($chave), $valor, now()->addDays((int) $this->config['cache_dias']));
        }
    }

    private static function campo(?string $valor): string
    {
        $valor = trim(strip_tags((string) $valor));

        return $valor === '' ? 'não informado' : $valor;
    }
}
