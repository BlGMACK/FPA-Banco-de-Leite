<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\EventoAgendamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoAgendamento>
 */
class EventoAgendamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agendamento_id' => Agendamento::factory(),
            'autor_user_id' => fn (array $attributes): int => Agendamento::findOrFail($attributes['agendamento_id'])->confirmado_por_user_id,
            'acao' => 'confirmacao', 'operacao_id' => fake()->uuid(),
            'conteudo_hash' => hash('sha256', 'operação fictícia'), 'motivo' => 'Confirmação fictícia',
            'dados_novos' => ['status' => 'confirmado'], 'ocorrido_em' => now(),
        ];
    }
}
