<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\Atendimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Atendimento>
 */
class AtendimentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agendamento_id' => Agendamento::factory()->state([
                'status' => 'realizado', 'confirmado_em' => now()->subDays(2),
                'inicio_previsto_em' => now()->subDay()->startOfHour(),
                'fim_previsto_em' => now()->subDay()->startOfHour()->addHour(),
            ]),
            'inicio_efetivo_em' => now()->subDay()->startOfHour(),
            'fim_efetivo_em' => now()->subDay()->startOfHour()->addHour(),
            'responsavel_user_id' => fn (array $attributes): int => Agendamento::findOrFail($attributes['agendamento_id'])->responsavel_user_id,
            'modalidade' => fn (array $attributes): string => Agendamento::findOrFail($attributes['agendamento_id'])->modalidade,
            'local_atendimento' => fn (array $attributes): string => Agendamento::findOrFail($attributes['agendamento_id'])->local_atendimento,
            'registrado_por_user_id' => fn (array $attributes): int => Agendamento::findOrFail($attributes['agendamento_id'])->confirmado_por_user_id,
            'registrado_em' => now(),
        ];
    }
}
