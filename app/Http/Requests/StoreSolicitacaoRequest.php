<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSolicitacaoRequest extends FormRequest
{
    public const CAMPOS = [
        'nome_completo', 'cpf', 'telefone_fixo', 'telefone_celular', 'email_contato',
        'municipio', 'bairro', 'uf', 'cep', 'logradouro', 'numero', 'complemento',
        'whatsapp_telefone', 'whatsapp_disponivel', 'outros_detalhes',
    ];

    public function authorize(): bool
    {
        return ! $this->routeIs('interno.*') || ($this->user()?->can('atender-solicitacoes') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $dados = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $this->input($campo);
            if (! is_string($valor)) {
                continue;
            }
            $valor = trim($valor);
            if (in_array($campo, ['cpf', 'cep'], true)) {
                $valor = preg_replace('/[.\-\s]/u', '', $valor);
            }
            if (in_array($campo, ['telefone_fixo', 'telefone_celular'], true)) {
                $valor = preg_replace('/[\s()+\-]/u', '', $valor);
                if (in_array(strlen($valor), [12, 13], true) && str_starts_with($valor, '55')) {
                    $valor = substr($valor, 2);
                }
            }
            if ($campo === 'email_contato') {
                $valor = Str::lower($valor);
            }
            if ($campo === 'uf') {
                $valor = Str::upper($valor);
            }
            $dados[$campo] = $valor === '' ? null : $valor;
        }
        $this->merge($dados);
    }

    public function rules(): array
    {
        return [
            'operacao_token' => ['required', 'string', 'max:2048'],
            'nome_completo' => ['required', 'string', 'max:150'],
            'cpf' => ['required', 'string', 'regex:/^[0-9]{11}$/'],
            'telefone_fixo' => ['nullable', 'required_without:telefone_celular', 'string', 'regex:/^[1-9][0-9]{9,10}$/'],
            'telefone_celular' => ['nullable', 'required_without:telefone_fixo', 'string', 'regex:/^[1-9][0-9]{9,10}$/'],
            'email_contato' => ['required', 'string', 'email', 'max:255'],
            'municipio' => ['required', 'string', 'max:150'],
            'bairro' => ['required', 'string', 'max:150'],
            'uf' => ['required', Rule::in(explode(' ', 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'))],
            'cep' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:150'],
            'whatsapp_telefone' => ['required', Rule::in(['fixo', 'celular'])],
            'whatsapp_disponivel' => ['required', 'accepted'],
            'outros_detalhes' => ['nullable', 'string', 'max:2000'],
            'canal_origem' => $this->routeIs('interno.*') ? ['required', Rule::in(['telefone', 'presencial'])] : ['exclude'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('cpf') && $this->filled('cpf') && ! $this->cpfValido($this->input('cpf'))) {
                $validator->errors()->add('cpf', 'Informe um CPF válido.');
            }
            $selecao = $this->input('whatsapp_telefone');
            if (in_array($selecao, ['fixo', 'celular'], true) && ! $this->filled('telefone_'.$selecao)) {
                $validator->errors()->add('whatsapp_telefone', 'Selecione um telefone preenchido para receber contato pelo WhatsApp.');
            }
        }];
    }

    private function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($digito = 9; $digito <= 10; $digito++) {
            $soma = 0;
            for ($indice = 0; $indice < $digito; $indice++) {
                $soma += (int) $cpf[$indice] * ($digito + 1 - $indice);
            }
            $verificador = ($soma * 10) % 11;
            if ((int) $cpf[$digito] !== ($verificador === 10 ? 0 : $verificador)) {
                return false;
            }
        }

        return true;
    }

    public function messages(): array
    {
        return [
            'required' => 'Preencha o campo :attribute.',
            'required_without' => 'Informe pelo menos um telefone com DDD.',
            'string' => 'Informe um texto válido em :attribute.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'regex' => 'Informe um valor válido em :attribute.',
            'email' => 'Informe um e-mail válido.',
            'in' => 'Selecione uma opção válida em :attribute.',
            'accepted' => 'Confirme que pode receber contato pelo WhatsApp no número selecionado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome_completo' => 'nome completo', 'cpf' => 'CPF', 'email_contato' => 'e-mail',
            'telefone_fixo' => 'telefone fixo', 'telefone_celular' => 'celular',
            'municipio' => 'município', 'uf' => 'UF', 'cep' => 'CEP', 'numero' => 'número',
            'whatsapp_telefone' => 'número para WhatsApp', 'canal_origem' => 'canal de atendimento',
            'operacao_token' => 'identificação do formulário', 'outros_detalhes' => 'outros detalhes',
        ];
    }
}
