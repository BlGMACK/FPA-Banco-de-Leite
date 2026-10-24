<?php

namespace Database\Factories;

use App\Models\Interessada;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interessada>
 */
class InteressadaFactory extends Factory
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
            'confirmada_por_user_id' => User::factory()->funcionario(),
            'confirmada_em' => now(),
        ];
    }
}
