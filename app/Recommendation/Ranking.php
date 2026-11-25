<?php

namespace App\Recommendation;

class Ranking
{
    public const ORDEM_COM_META = ['meta_both', 'meta_one', 'meta', 'both', 'profile', 'interest'];

    public const ORDEM_SEM_META = ['both', 'profile', 'interest'];

    private const GRAU_LEGADO = ['meta_both' => 7, 'meta_one' => 5, 'meta' => 4, 'both' => 3, 'profile' => 2, 'interest' => 0];

    public static function ordem(bool $comMeta): array
    {
        return $comMeta ? self::ORDEM_COM_META : self::ORDEM_SEM_META;
    }

    public const GRUPO_COMPATIVEL = 0;

    public const GRUPO_META_NAO_CONFERIDA = 1;

    public const GRUPO_META_INCOMPATIVEL = 2;

    public static function ordenar(iterable $reas, bool $comMeta): array
    {
        $itens = [];

        foreach (self::comPosicao($reas) as [$rea, $dados, $posicao]) {
            if (! self::visivel($dados, $comMeta)) {
                continue;
            }

            $itens[] = [
                'rea' => $rea,
                'chave' => [self::grupoMeta($dados, $comMeta), -self::grauDe($dados), $posicao, (string) ($dados['repositorio'] ?? ''), (string) ($dados['chave'] ?? '')],
            ];
        }

        usort($itens, fn ($a, $b) => $a['chave'] <=> $b['chave']);

        return array_column($itens, 'rea');
    }

    public static function visivel($rea, bool $comMeta): bool
    {
        $dados = self::paraArray($rea);

        if (self::temaFalhou($dados)) {
            return false;
        }

        return in_array($dados['recommended'] ?? null, self::ordem($comMeta), true);
    }

    public static function grupoMeta($rea, bool $comMeta): int
    {
        $dados = self::paraArray($rea);

        if (! $comMeta || in_array($dados['recommended'] ?? null, ['meta_both', 'meta_one', 'meta'], true)) {
            return self::GRUPO_COMPATIVEL;
        }

        return self::statusMeta($dados) === 'falhou' ? self::GRUPO_META_INCOMPATIVEL : self::GRUPO_META_NAO_CONFERIDA;
    }

    public static function grupoTopo(iterable $reas, bool $comMeta): ?int
    {
        $topo = null;

        foreach ($reas as $rea) {
            if (self::visivel($rea, $comMeta)) {
                $grupo = self::grupoMeta($rea, $comMeta);
                $topo = $topo === null ? $grupo : min($topo, $grupo);
            }
        }

        return $topo;
    }

    public static function grauDe($rea): int
    {
        $dados = self::paraArray($rea);
        $criterios = $dados['explicacao']['criterios'] ?? [];

        if (! empty($criterios)) {
            return RuleClassifier::grau($criterios)['total'];
        }

        return self::GRAU_LEGADO[$dados['recommended'] ?? ''] ?? 0;
    }

    public static function contar(iterable $reas, bool $comMeta): array
    {
        $faixas = array_fill_keys(self::ordem($comMeta), 0);
        $ocultos = 0;
        $motivos = ['tema' => 0, 'sem_meta_usuario' => 0, 'outros' => 0];
        $abaixo = ['meta_nao_conferida' => 0, 'meta_incompativel' => 0, 'meta_corrigida_incompativel' => 0];
        $corrigidos = 0;
        $mudaramFaixa = 0;

        foreach ($reas as $rea) {
            $dados = self::paraArray($rea);
            $rotulo = $dados['recommended'] ?? null;
            $faixaOriginal = $dados['explicacao']['faixa_original'] ?? null;

            if ($faixaOriginal !== null) {
                $corrigidos++;
                $mudaramFaixa += $faixaOriginal !== $rotulo ? 1 : 0;
            }

            if (self::visivel($dados, $comMeta)) {
                $faixas[$rotulo]++;

                $grupo = self::grupoMeta($dados, $comMeta);
                if ($grupo === self::GRUPO_META_NAO_CONFERIDA) {
                    $abaixo['meta_nao_conferida']++;
                } elseif ($grupo === self::GRUPO_META_INCOMPATIVEL) {
                    $abaixo[($dados['explicacao']['criterios']['meta']['fonte'] ?? null) === 'usuario' ? 'meta_corrigida_incompativel' : 'meta_incompativel']++;
                }

                continue;
            }

            $ocultos++;
            $motivos[self::motivoOculto($dados, $comMeta)]++;
        }

        return [
            'faixas' => $faixas,
            'ocultos' => $ocultos,
            'motivos_ocultos' => $motivos,
        ] + $abaixo + [
            'corrigidos' => $corrigidos,
            'mudaram_faixa' => $mudaramFaixa,
        ];
    }

    public static function motivo($rea, bool $comMeta): string
    {
        return self::motivoOculto(self::paraArray($rea), $comMeta);
    }

    private static function motivoOculto(array $dados, bool $comMeta): string
    {
        $rotulo = $dados['recommended'] ?? null;

        if (self::temaFalhou($dados)) {
            return 'tema';
        }

        if (! $comMeta && in_array($rotulo, ['meta_both', 'meta_one', 'meta'], true)) {
            return 'sem_meta_usuario';
        }

        return 'outros';
    }

    private static function temaFalhou(array $dados): bool
    {
        return ($dados['explicacao']['criterios']['tema']['status'] ?? null) === 'falhou';
    }

    private static function comPosicao(iterable $reas): \Generator
    {
        $contagem = [];

        foreach ($reas as $rea) {
            $dados = self::paraArray($rea);
            $repositorio = (string) ($dados['repositorio'] ?? '');
            $contagem[$repositorio] = ($contagem[$repositorio] ?? 0) + 1;

            yield [$rea, $dados, (int) ($dados['explicacao']['grau']['posicao'] ?? $contagem[$repositorio])];
        }
    }

    private static function statusMeta(array $dados): ?string
    {
        return $dados['explicacao']['criterios']['meta']['status'] ?? null;
    }

    private static function paraArray($rea): array
    {
        return is_array($rea) ? $rea : (json_decode(json_encode($rea), true) ?? []);
    }
}
