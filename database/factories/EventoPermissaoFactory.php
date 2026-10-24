<?php

namespace Database\Factories;

use App\Models\EventoPermissao;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoPermissao>
 */
class EventoPermissaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->funcionario(),
            'permissao_id' => Permissao::factory()->atendimento(),
            'autor_user_id' => User::factory()->admin(),
            'acao' => 'concessao',
            'motivo' => 'Atribuição fictícia.',
            'ocorrido_em' => now(),
        ];
    }
}
