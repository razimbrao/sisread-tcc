<?php

namespace App\Experimento;

use App\Features\GrupoExperimento;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

class Experimento
{
    public const GRUPOS = ['controle', 'transparencia', 'escrutabilidade'];

    public const FLAGS = [
        'explicacao-rea' => 'Por que este REA? (selo de faixa, critérios, coluna Meta do cartão)',
        'painel-ordenacao' => 'Painel "Como ordenamos estes resultados" (faixas, repositórios)',
        'reas-ocultos' => 'Lista dos REAs que não aparecem, com o motivo',
        'painel-contexto' => 'Painel "O que usamos sobre você"',
        'progresso-busca' => '"Consultando repositórios… N de 3 responderam"',
        'escrutabilidade' => 'Corrigir nível e meta, editar tipos preferidos, desfazer',
    ];

    public const FLAG_DA_ACAO = [
        'abriu_explicacao' => 'explicacao-rea',
        'abriu_ordenacao' => 'painel-ordenacao',
        'abriu_ocultos' => 'reas-ocultos',
        'abriu_contexto' => 'painel-contexto',
        'abriu_correcao' => 'escrutabilidade',
    ];

    private const SESSAO_VISITANTE = 'experimento.visitante';

    private const SESSAO_GRUPO = 'experimento.grupo';

    private array $memoria = [];

    public static function ativa(string $flag): bool
    {
        return app(self::class)->ativo($flag);
    }

    public function grupo(?User $usuario = null): string
    {
        $forcado = config('experimento.forcar_grupo');

        if (in_array($forcado, self::GRUPOS, true)) {
            return $forcado;
        }

        $escopo = $this->escopo($usuario);
        $chave = is_string($escopo) ? $escopo : 'usuario:'.$escopo->getKey();

        if (! isset($this->memoria[$chave])) {
            $grupo = Feature::for($escopo)->value(GrupoExperimento::class);

            if (! in_array($grupo, self::GRUPOS, true)) {
                $grupo = $this->grupoInicial();
                Feature::for($escopo)->activate(GrupoExperimento::class, $grupo);
            }

            $this->memoria[$chave] = $grupo;

            if ($usuario === null && $this->temSessao()) {
                session()->put(self::SESSAO_GRUPO, $grupo);
            }
        }

        return $this->memoria[$chave];
    }

    public function ativo(string $flag): bool
    {
        $forcadas = $this->flagsForcadas();

        if (array_key_exists($flag, $forcadas)) {
            return $forcadas[$flag];
        }

        return in_array($flag, config('experimento.grupos.'.$this->grupo(), []), true);
    }

    public function flagsAtivas(): array
    {
        return array_values(array_filter(array_keys(self::FLAGS), fn ($flag) => $this->ativo($flag)));
    }

    public function mostraExplicacoes(): bool
    {
        return $this->ativo('explicacao-rea') || $this->ativo('painel-ordenacao') || $this->ativo('painel-contexto');
    }

    public function salvaPreferenciaNaBusca(): bool
    {
        return (bool) config('experimento.salvar_preferencia_na_busca', true);
    }

    public function participante(): string
    {
        $escopo = $this->escopo();

        return is_string($escopo) ? 'v:'.Str::after($escopo, 'visitante:') : 'u:'.$escopo->getKey();
    }

    public function definirPorCodigo(string $codigo): ?string
    {
        $grupo = array_search($codigo, config('experimento.codigos', []), true);

        if (! is_string($grupo) || ! in_array($grupo, self::GRUPOS, true)) {
            return null;
        }

        $this->fixar($grupo);

        return $grupo;
    }

    public function fixar(string $grupo, ?User $usuario = null): void
    {
        if (! in_array($grupo, self::GRUPOS, true)) {
            throw new InvalidArgumentException('Grupo desconhecido: '.$grupo);
        }

        $escopo = $this->escopo($usuario);
        Feature::for($escopo)->activate(GrupoExperimento::class, $grupo);
        $this->memoria = [];

        if ($usuario === null && $this->temSessao()) {
            session()->put(self::SESSAO_GRUPO, $grupo);
        }
    }

    public function grupoInicial(): string
    {
        $daSessao = $this->temSessao() ? session(self::SESSAO_GRUPO) : null;

        if (in_array($daSessao, self::GRUPOS, true)) {
            return $daSessao;
        }

        $padrao = config('experimento.sem_link', 'escrutabilidade');

        return in_array($padrao, self::GRUPOS, true) ? $padrao : self::GRUPOS[random_int(0, count(self::GRUPOS) - 1)];
    }

    public function flagsForcadas(): array
    {
        $forcadas = [];

        foreach (explode(',', (string) config('experimento.forcar_flags', '')) as $par) {
            [$flag, $valor] = array_pad(array_map('trim', explode('=', $par, 2)), 2, null);

            if (isset(self::FLAGS[$flag]) && $valor !== null) {
                $forcadas[$flag] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $forcadas;
    }

    private function escopo(?User $usuario = null): User|string
    {
        $usuario ??= auth()->user();

        if ($usuario) {
            return $usuario;
        }

        if (! $this->temSessao()) {
            return 'visitante:sem-sessao';
        }

        if (! session()->has(self::SESSAO_VISITANTE)) {
            session()->put(self::SESSAO_VISITANTE, (string) Str::uuid());
        }

        return 'visitante:'.session(self::SESSAO_VISITANTE);
    }

    private function temSessao(): bool
    {
        return app()->bound('session');
    }
}
