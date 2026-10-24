<?php

namespace Database\Factories;

use App\Models\Solicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitacao>
 */
class SolicitacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome_completo' => 'Pessoa Fictícia',
            'nome_normalizado' => 'pessoa ficticia',
            'cpf' => '00000000000',
            'telefone_celular' => '14999990000',
            'email_contato' => fake()->unique()->safeEmail(),
            'municipio' => 'Marília',
            'bairro' => 'Bairro de Teste',
            'uf' => 'SP',
            'cep' => '00000000',
            'logradouro' => 'Rua de Teste',
            'numero' => 's/n',
            'whatsapp_telefone' => 'celular',
            'whatsapp_disponivel' => true,
            'whatsapp_declarado_em' => now(),
            'protocolo' => fake()->uuid(),
            'operacao_id' => fake()->uuid(),
            'conteudo_hash' => hash('sha256', 'entrada ficticia'),
            'dados_originais' => ['nome_completo' => 'Pessoa Fictícia'],
            'canal_origem' => 'web',
            'status' => 'enviada',
            'enviada_em' => now(),
        ];
    }
}
