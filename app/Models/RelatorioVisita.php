<?php

namespace App\Models;

use Database\Factories\RelatorioVisitaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RelatorioVisita extends Model
{
    /** @use HasFactory<RelatorioVisitaFactory> */
    use HasFactory;

    protected $table = 'relatorios_visita';

    protected $hidden = ['texto_rascunho', 'motivo_correcao'];

    /** @return array<string, mixed> */
    public static function consultarVersao(int $relatorioId, int $numero, User $autor): array
    {
        Gate::forUser($autor)->authorize(Permissao::CONSULTAR_RELATORIOS_VISITA);
        $versao = VersaoRelatorioVisita::query()->where('relatorio_visita_id', $relatorioId)->where('numero', $numero)->firstOrFail();

        return $versao->only(['id', 'relatorio_visita_id', 'numero', 'texto', 'versao_base_id', 'motivo_correcao', 'finalizado_por_user_id', 'finalizado_em']);
    }

    /** @return array<string, mixed> */
    public static function consultarRascunho(int $relatorioId, User $autor): array
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);

        return static::query()->findOrFail($relatorioId)->only(['id', 'estado', 'revisao', 'texto_rascunho', 'numero_versao_vigente', 'base_correcao_numero', 'motivo_correcao']);
    }

    /** @param array<string, mixed> $dados */
    public function salvarRascunho(User $autor, array $dados): EventoAtendimento
    {
        return $this->executar('salvamento_rascunho', $autor, $dados);
    }

    /** @param array<string, mixed> $dados */
    public function finalizar(User $autor, array $dados): EventoAtendimento
    {
        return $this->executar('finalizacao', $autor, $dados);
    }

    /** @param array<string, mixed> $dados */
    public function iniciarCorrecao(User $autor, array $dados): EventoAtendimento
    {
        return $this->executar('abertura_correcao', $autor, $dados);
    }

    /** @param array<string, mixed> $entrada */
    private function executar(string $acao, User $autor, array $entrada): EventoAtendimento
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $regras = ['operacao_id' => ['required', 'uuid'], 'revisao' => ['required', 'integer', 'min:1']];
        if ($acao === 'salvamento_rascunho') {
            $regras['texto'] = ['present', 'nullable', 'string', 'max:30000'];
        }
        if ($acao === 'abertura_correcao') {
            $regras['motivo'] = ['required', 'string', 'max:2000'];
        }
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, $regras)->validate();
        $dados['revisao'] = (int) $dados['revisao'];
        $hash = hash('sha256', json_encode([$acao, $this->getKey(), $autor->id, $dados], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($acao, $autor, $dados, $hash): EventoAtendimento {
                $relatorio = static::query()->lockForUpdate()->findOrFail($this->getKey());
                Gate::forUser($autor)->authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
                $anterior = EventoAtendimento::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($relatorio->revisao === $dados['revisao'], 409, 'O relatório mudou. Recarregue antes de continuar.');
                $versao = null;
                if ($acao === 'abertura_correcao') {
                    abort_unless($relatorio->estado === 'finalizado', 409, 'O relatório não está disponível para iniciar uma correção.');
                    $avaliacao = Avaliacao::query()->where('atendimento_id', $relatorio->atendimento_id)->lockForUpdate()->first();
                    abort_if($avaliacao?->concluida(), 409, 'A análise deste relatório já foi concluída. O relatório não pode mais ser corrigido.');
                    abort_if($avaliacao?->reservadaPorOutro($autor), 409, $avaliacao?->mensagemReserva().' Abra a correção depois que a análise for encerrada.');
                    $base = $relatorio->versoes()->where('numero', $relatorio->numero_versao_vigente)->firstOrFail();
                    $relatorio->forceFill(['estado' => 'em_correcao', 'base_correcao_numero' => $base->numero,
                        'texto_rascunho' => $base->texto, 'motivo_correcao' => $dados['motivo']]);
                } else {
                    abort_unless(in_array($relatorio->estado, ['rascunho', 'em_correcao'], true), 409, 'Inicie uma correção para alterar um relatório finalizado.');
                    if ($acao === 'salvamento_rascunho') {
                        $relatorio->texto_rascunho = $dados['texto'];
                    } else {
                        if (trim($relatorio->texto_rascunho ?? '') === '') {
                            throw ValidationException::withMessages(['texto' => 'Preencha o relatório antes de finalizar.']);
                        }
                        $base = null;
                        if ($relatorio->estado === 'em_correcao') {
                            abort_unless($relatorio->base_correcao_numero === $relatorio->numero_versao_vigente
                                && trim($relatorio->motivo_correcao ?? '') !== '', 409, 'Base da correção inconsistente.');
                            $base = $relatorio->versoes()->where('numero', $relatorio->base_correcao_numero)->firstOrFail();
                        } else {
                            abort_unless($relatorio->numero_versao_vigente === 0, 409, 'Estado inicial inconsistente.');
                        }
                        $versao = new VersaoRelatorioVisita;
                        $versao->forceFill(['relatorio_visita_id' => $relatorio->id,
                            'numero' => $relatorio->numero_versao_vigente + 1, 'texto' => $relatorio->texto_rascunho,
                            'versao_base_id' => $base?->id, 'motivo_correcao' => $base ? $relatorio->motivo_correcao : null,
                            'finalizado_por_user_id' => $autor->id, 'finalizado_em' => now()->utc()])->save();
                        $relatorio->forceFill(['estado' => 'finalizado', 'numero_versao_vigente' => $versao->numero,
                            'texto_rascunho' => null, 'base_correcao_numero' => null, 'motivo_correcao' => null]);
                        Avaliacao::abrirParaAtendimento($relatorio->atendimento_id);
                    }
                }
                $relatorio->ultimo_editor_user_id = $autor->id;
                $relatorio->revisao++;
                $relatorio->save();
                $evento = new EventoAtendimento;
                $evento->forceFill(['atendimento_id' => $relatorio->atendimento_id, 'relatorio_visita_id' => $relatorio->id,
                    'versao_relatorio_id' => $versao?->id, 'acao' => $acao, 'autor_user_id' => $autor->id,
                    'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash, 'revisao_analisada' => $dados['revisao'],
                    'ocorrido_em' => now()->utc()])->save();

                return $evento;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! EventoAtendimento::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    protected function casts(): array
    {
        return ['numero_versao_vigente' => 'integer', 'base_correcao_numero' => 'integer', 'revisao' => 'integer'];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function ultimoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_editor_user_id');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(VersaoRelatorioVisita::class, 'relatorio_visita_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAtendimento::class, 'relatorio_visita_id');
    }
}
