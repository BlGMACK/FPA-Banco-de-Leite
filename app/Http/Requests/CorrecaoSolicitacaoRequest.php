<?php

namespace App\Http\Requests;

use App\Models\Permissao;
use App\Models\Solicitacao;
use Illuminate\Support\Arr;

/** Correção dos dados de um pedido, com a mesma normalização e as mesmas regras do recebimento. */
class CorrecaoSolicitacaoRequest extends StoreSolicitacaoRequest
{
    protected $errorBag = 'correcao';

    private ?bool $admitida = null;

    public function authorize(): bool
    {
        return $this->user()?->can(Permissao::ATENDER_SOLICITACOES) ?? false;
    }

    /** Depois da admissão, nome, CPF e detalhes do pedido não são corrigíveis: visita, avaliação e doadora dependem da identidade conferida. */
    public function admitida(): bool
    {
        return $this->admitida ??= Solicitacao::query()->whereKey($this->route('solicitacao'))->value('status') === 'admitida';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->admitida()) {
            $this->replace($this->except(Solicitacao::CAMPOS_IDENTIDADE));
        }
    }

    public function rules(): array
    {
        $regras = Arr::except(parent::rules(), ['operacao_token', 'canal_origem'])
            + ['versao' => ['required', 'integer', 'min:1'], 'motivo' => ['required', 'string', 'max:2000']];

        return $this->admitida() ? Arr::except($regras, Solicitacao::CAMPOS_IDENTIDADE) : $regras;
    }

    public function attributes(): array
    {
        return parent::attributes() + ['motivo' => 'motivo da correção'];
    }
}
