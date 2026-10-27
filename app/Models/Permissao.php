<?php

namespace App\Models;

use Database\Factories\PermissaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class Permissao extends Model
{
    /** @use HasFactory<PermissaoFactory> */
    use HasFactory;

    public const ATENDER_SOLICITACOES = 'atender-solicitacoes';

    public const GERENCIAR_AGENDA = 'gerenciar-agenda';

    protected $table = 'permissoes';

    public function conceder(User $funcionario, User $responsavel, string $motivo): bool
    {
        return $this->alterarConcessao($funcionario, $responsavel, $motivo, false);
    }

    public function revogar(User $funcionario, User $responsavel, string $motivo): bool
    {
        return $this->alterarConcessao($funcionario, $responsavel, $motivo, true);
    }

    private function alterarConcessao(User $funcionario, User $responsavel, string $motivo, bool $revogar): bool
    {
        Gate::forUser($responsavel)->authorize('gerenciar-permissoes');

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo da alteração de permissão.']);
        }

        return DB::transaction(function () use ($funcionario, $responsavel, $motivo, $revogar): bool {
            $permissao = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $destinatario = User::query()->lockForUpdate()->findOrFail($funcionario->getKey());
            Gate::forUser($responsavel)->authorize('gerenciar-permissoes');

            if (! in_array($permissao->codigo, [self::ATENDER_SOLICITACOES, self::GERENCIAR_AGENDA], true)) {
                throw ValidationException::withMessages(['permissao' => 'Permissão fora do recorte administrativo.']);
            }

            if (! $revogar && ! $destinatario->isFuncionario()) {
                throw ValidationException::withMessages(['funcionario' => 'Permissões administrativas só podem ser concedidas a funcionários.']);
            }

            $concessao = $permissao->concessoes()->where('user_id', $destinatario->id)->first();
            $ativa = $concessao !== null && $concessao->revogada_em === null;

            if (($revogar && ! $ativa) || (! $revogar && $ativa)) {
                return false;
            }

            $instante = now();
            $concessao ??= new ConcessaoPermissao;
            if ($revogar) {
                $concessao->revogada_por_user_id = $responsavel->id;
                $concessao->revogada_em = $instante;
            } else {
                $concessao->forceFill([
                    'user_id' => $destinatario->id,
                    'permissao_id' => $permissao->id,
                    'concedida_por_user_id' => $responsavel->id,
                    'concedida_em' => $instante,
                    'revogada_por_user_id' => null,
                    'revogada_em' => null,
                ]);
            }
            $concessao->save();

            $evento = new EventoPermissao;
            $evento->forceFill([
                'user_id' => $destinatario->id,
                'permissao_id' => $permissao->id,
                'autor_user_id' => $responsavel->id,
                'acao' => $revogar ? 'revogacao' : 'concessao',
                'motivo' => trim($motivo),
                'ocorrido_em' => $instante,
            ])->save();

            return true;
        });
    }

    public function concessoes(): HasMany
    {
        return $this->hasMany(ConcessaoPermissao::class, 'permissao_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPermissao::class, 'permissao_id');
    }
}
