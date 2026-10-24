<?php

namespace Database\Factories;

use App\Models\ContatoSolicitacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContatoSolicitacao>
 */
class ContatoSolicitacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registrado_por_user_id' => User::factory()->funcionario(),
            'canal' => 'whatsapp',
            'resultado' => 'tentado',
            'destinatario' => '14999990000',
            'finalidade' => 'Conferir dados',
            'resumo' => 'Tentativa de contato fictícia.',
            'ocorrido_em' => now(),
        ];
    }
}
