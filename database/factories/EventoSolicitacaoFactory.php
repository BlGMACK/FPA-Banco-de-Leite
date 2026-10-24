<?php

namespace Database\Factories;

use App\Models\EventoSolicitacao;
use App\Models\Solicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoSolicitacao>
 */
class EventoSolicitacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitacao_id' => Solicitacao::factory(),
            'tipo' => 'recebimento',
            'estado_novo' => 'enviada',
            'dados_novos' => ['status' => 'enviada'],
            'ocorrido_em' => now(),
        ];
    }
}
