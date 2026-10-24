<?php

namespace Database\Factories;

use App\Models\ResolucaoSolicitacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResolucaoSolicitacao>
 */
class ResolucaoSolicitacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operacao_id' => fake()->uuid(),
            'conteudo_hash' => hash('sha256', 'resolucao ficticia'),
            'tipo' => 'aguardando_esclarecimento',
            'responsavel_user_id' => User::factory()->funcionario(),
            'motivo' => 'Aguardando retorno.',
            'ocorrido_em' => now(),
        ];
    }
}
