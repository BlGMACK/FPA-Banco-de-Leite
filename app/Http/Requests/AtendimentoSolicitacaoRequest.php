<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AtendimentoSolicitacaoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('atender-solicitacoes') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $base = [
            'pedidos' => ['required', 'array', 'min:1', 'max:50'],
            'pedidos.*' => ['array:id,versao'],
            'pedidos.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'pedidos.*.versao' => ['required', 'integer', 'min:1'],
        ];
        if ($this->routeIs('*.analise.store')) {
            return $base;
        }
        if ($this->routeIs('*.contatos.store')) {
            return $base + [
                'canal' => ['required', 'in:whatsapp,telefone,email'],
                'resultado' => ['required', 'in:tentado,realizado,sem_resposta,numero_incorreto'],
                'destinatario' => ['required', 'string', 'max:255'],
                'finalidade' => ['required', 'string', 'max:150'],
                'resumo' => ['required', 'string', 'max:4000'],
                'proxima_acao' => ['nullable', 'string', 'max:2000'],
                'ocorrido_em' => ['required', 'date', 'before_or_equal:now'],
                'identidade_conferida' => ['sometimes', 'boolean'],
                'duvida_esclarecida' => ['sometimes', 'boolean'],
            ];
        }

        return $base + [
            'operacao_id' => ['required', 'uuid'],
            'acao' => ['required', 'in:aguardando_esclarecimento,pessoas_distintas,arquivar_selecionados,admitir_principal,admitir'],
            'contato_id' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'max:4000'],
            'principal_id' => ['nullable', 'integer', 'min:1'],
            'identidade' => ['nullable', 'in:nova,existente'],
            'cadastro_referencia_id' => ['nullable', 'integer', 'min:1'],
            'cadastros' => ['sometimes', 'array', 'max:50'],
            'cadastros.*' => ['array:id,versao'],
            'cadastros.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'cadastros.*.versao' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->routeIs('*.contatos.store') ? 'contato' : 'decisao';
    }

    public function messages(): array
    {
        return [
            'required' => 'Preencha o campo :attribute.',
            'in' => 'Selecione uma opção válida em :attribute.',
            'before_or_equal' => 'O contato não pode estar no futuro.',
            'distinct' => 'Não selecione o mesmo registro mais de uma vez.',
            'max' => 'O campo :attribute ultrapassa o limite permitido (:max).',
            'uuid' => 'Identificação da operação inválida. Reabra a página.',
        ];
    }
}
