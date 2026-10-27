<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\ReservaAgenda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservaAgenda>
 */
class ReservaAgendaFactory extends Factory
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
            'solicitacao_id' => fn (array $attributes): int => Agendamento::findOrFail($attributes['agendamento_id'])->solicitacao_id,
        ];
    }
}
