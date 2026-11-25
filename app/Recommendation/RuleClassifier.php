<?php

namespace App\Recommendation;

use Illuminate\Support\Str;

class RuleClassifier
{
    public const VERSAO_REGRAS = 2;

    public const PESOS = ['meta' => 4, 'nivel' => 2, 'tipo' => 1];

    private const TERMOS_PADROES = [
        '/\breconhecimento de padr(ao|oes)\b/',
        '/\bpadr(ao|oes) e sequencias?\b/',
        '/\bsequencias? (e|de) padr(ao|oes)\b/',
        '/\bpadr(ao|oes) de repeticao\b/',
        '/\bregularidades?\b/',
        '/\bsequencias? logicas?\b/',
        '/\bpadr(ao|oes)\b.{0,80}\bpensamento computacional\b/s',
        '/\bpensamento computacional\b.{0,80}\bpadr(ao|oes)\b/s',
    ];

    public const TERMOS_TEMA = [
        'algoritmos' => ['/\balgoritmos?\b/', '/\bpasso a passo\b/', '/\bsequencia de instrucoes\b/'],
        'decomposicao' => ['/\bdecomposicao(oes)?\b/', '/\bdecompor\b/', '/\bdecompoe(m)?\b/', '/\bdividir (o|um|uma) problema\b/', '/\bpartes menores\b/'],
        'reconhecimento de padroes' => self::TERMOS_PADROES,
        'padroes' => self::TERMOS_PADROES,
        'abstracao' => ['/\babstracao(oes)?\b/', '/\babstrair\b/'],
        'pensamento computacional' => ['/\bpensamento computacional\b/'],
        'programacao' => ['/\bprogramac(ao|oes)\b/', '/\bprogramar\b/', '/\bprogramando\b/'],
        'logica de programacao' => ['/\blogica (de )?programac(ao|oes)\b/'],
        'computacao desplugada' => ['/\bcomputacao desplugada\b/', '/\bdesplugad[oa]s?\b/'],
        'programacao em blocos' => ['/\bprogramac(ao|oes) (em|por) blocos\b/'],
        'scratch' => ['/\bscratch\b/'],
        'estruturas de repeticao' => ['/\bestruturas? de repetic(ao|oes)\b/', '/\blacos? de repetic(ao|oes)\b/', '/\brepeticoes?\b/'],
        'robotica educacional' => ['/\brobot(ica|ico|icas|icos)\b/', '/\brobos?\b/'],
        'inteligencia artificial' => ['/\binteligencias? artificiais?\b/', '/\bia\b/'],
    ];

    public const METAS = [
        'ma' => 'Aprendizagem',
        'mpa' => 'Performance-aproximação',
        'mpe' => 'Performance-evitação',
    ];

    private const NIVEIS = [
        'educacao infantil' => '/\b(criança|infantil)\b/',
        'ensino fundamental' => '/\b(fundamental|sexto ano|6º|sétimo ano|7º|oitavo ano|8º|nono ano|9º|ef)\b/',
        'ensino medio' => '/\b(médio)\b/',
    ];

    private const NIVEL_PADRAO = 'ensino superior';

    public const NIVEIS_VALIDOS = ['educacao infantil', 'ensino fundamental', 'ensino medio', self::NIVEL_PADRAO];

    public const FONTES_CORRIGIVEIS = ['regex', 'llm'];

    public static function chave(string $repositorio, ?string $link, ?string $titulo): string
    {
        return substr(sha1($repositorio.'|'.($link ?? '').'|'.($titulo ?? '')), 0, 12);
    }

    public static function corrigivel(?array $criterio): bool
    {
        return in_array($criterio['original']['fonte'] ?? $criterio['fonte'] ?? null, self::FONTES_CORRIGIVEIS, true);
    }

    public static function normalizar(?string $texto): string
    {
        return trim(Str::lower(Str::ascii($texto ?? '')));
    }

    private static function trechoOriginal(string $original, string $achado): string
    {
        $palavras = preg_split('/\s+/u', trim($original)) ?: [];
        $tamanho = count(explode(' ', $achado));

        for ($i = 0; $i + $tamanho <= count($palavras); $i++) {
            $janela = implode(' ', array_slice($palavras, $i, $tamanho));

            if (str_contains(self::normalizar($janela), $achado)) {
                return preg_replace('/^[^\pL\pN]+|[^\pL\pN]+$/u', '', $janela) ?: $achado;
            }
        }

        return $achado;
    }

    public static function normalizarTipos(array $tipos): array
    {
        $normalizados = [];

        array_walk_recursive($tipos, function ($tipo) use (&$normalizados) {
            $tipo = self::normalizar((string) $tipo);

            if ($tipo === '') {
                return;
            }

            $normalizados[] = $tipo;

            if ($tipo === 'e-book') {
                $normalizados[] = 'livro digital';
            }
        });

        return array_values(array_unique($normalizados));
    }

    public static function inferirNivel(?string $titulo, ?string $descricao): array
    {
        $texto = mb_strtolower(($titulo ?? '').' '.($descricao ?? ''), 'UTF-8');

        foreach (self::NIVEIS as $nivel => $regex) {
            if (preg_match($regex, $texto, $m)) {
                return ['valor' => $nivel, 'evidencia' => $m[1], 'assumido' => false];
            }
        }

        return ['valor' => self::NIVEL_PADRAO, 'evidencia' => null, 'assumido' => true];
    }

    public static function criterioTema(string $termo, string $repositorio, ?string $titulo, ?string $descricao): array
    {
        $padroes = self::TERMOS_TEMA[self::normalizar($termo)] ?? null;

        if ($padroes === null) {
            return [
                'status' => 'ok',
                'valor' => $termo,
                'evidencia' => null,
                'fonte' => 'texto',
                'repositorio' => $repositorio,
                'motivo' => 'o tema não tem termos cadastrados, então não foi conferido',
            ];
        }

        $texto = self::normalizar(($titulo ?? '').' '.($descricao ?? ''));
        $evidencia = null;

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $texto, $m)) {
                $evidencia = self::trechoOriginal(($titulo ?? '').' '.($descricao ?? ''), $m[0]);
                break;
            }
        }

        return [
            'status' => $evidencia === null ? 'falhou' : 'ok',
            'valor' => $termo,
            'evidencia' => $evidencia,
            'fonte' => 'texto',
            'repositorio' => $repositorio,
        ];
    }

    public static function criterioNivel(string $perfil, array $inferido): array
    {
        $esperado = self::normalizar($perfil);

        return [
            'status' => $inferido['valor'] === $esperado ? 'ok' : 'falhou',
            'valor' => $inferido['valor'],
            'esperado' => $esperado,
            'fonte' => 'regex',
            'evidencia' => $inferido['evidencia'],
            'assumido' => $inferido['assumido'],
        ];
    }

    public static function criterioTipo(?string $tipo, array $tiposPreferidos, string $origem = TiposPreferidos::COLABORADORES): array
    {
        $valor = self::normalizar($tipo);

        $criterio = [
            'status' => $valor !== '' && in_array($valor, $tiposPreferidos, true) ? 'ok' : 'falhou',
            'valor' => $valor,
            'esperado' => array_values($tiposPreferidos),
            'fonte' => 'colaboradores',
        ];

        if ($origem !== TiposPreferidos::COLABORADORES) {
            $criterio['fonte'] = 'usuario';
            $criterio['inclui_colaboradores'] = $origem === TiposPreferidos::CONTA_E_COLABORADORES;
        }

        return $criterio;
    }

    public static function tipoComparavel(?array $criterio): bool
    {
        return in_array($criterio['fonte'] ?? null, ['colaboradores', 'usuario'], true);
    }

    public static function criterioMeta(string $metaUsuario, ?string $classificacao, ?string $modelo = null, ?float $segundos = null, ?string $evidencia = null): array
    {
        $base = [
            'esperado' => $metaUsuario,
            'fonte' => 'llm',
            'modelo' => $modelo,
            'duracao' => $segundos === null ? null : round($segundos, 2),
        ];

        if ($evidencia !== null) {
            $base['evidencia'] = $evidencia;
        }

        if ($classificacao === null || $classificacao === '' || $classificacao === 'Não classificado') {
            return $base + ['status' => 'nao_avaliado', 'valor' => null];
        }

        return $base + [
            'status' => self::casaMeta($classificacao, $metaUsuario) ? 'ok' : 'falhou',
            'valor' => $classificacao,
        ];
    }

    public static function casaMeta(string $classificacao, ?string $meta): bool
    {
        $c = str_replace([' ', '-'], '_', self::normalizar($classificacao));

        return match ($meta) {
            'ma' => str_contains($c, 'aprendizagem'),
            'mpa' => str_contains($c, 'aproximacao'),
            'mpe' => str_contains($c, 'evitacao'),
            default => false,
        };
    }

    public static function atende(array $criterios, string $nome): bool
    {
        $criterio = $criterios[$nome] ?? null;

        return ($criterio['status'] ?? null) === 'ok' && ! ($criterio['assumido'] ?? false);
    }

    public static function grau(array $criterios, ?int $posicao = null): array
    {
        $pontos = [];

        foreach (self::PESOS as $nome => $peso) {
            if (isset($criterios[$nome])) {
                $pontos[$nome] = self::atende($criterios, $nome) ? $peso : 0;
            }
        }

        return [
            'total' => array_sum($pontos),
            'maximo' => array_sum(array_intersect_key(self::PESOS, $pontos)),
            'pontos' => $pontos,
            'posicao' => $posicao,
        ];
    }

    public static function rotular(array $criterios, bool $comMeta): string
    {
        $nivel = self::atende($criterios, 'nivel');
        $tipo = self::atende($criterios, 'tipo');

        if ($comMeta && self::atende($criterios, 'meta')) {
            if ($nivel && $tipo) {
                return 'meta_both';
            }
            if ($nivel || $tipo) {
                return 'meta_one';
            }

            return 'meta';
        }

        if ($nivel && $tipo) {
            return 'both';
        }
        if ($nivel) {
            return 'profile';
        }

        return 'interest';
    }

    public static function explicacao(array $criterios, string $faixa, ?int $posicao = null): array
    {
        return [
            'versao_regras' => self::VERSAO_REGRAS,
            'faixa' => $faixa,
            'grau' => self::grau($criterios, $posicao),
            'observacao' => null,
            'criterios' => $criterios,
        ];
    }
}
