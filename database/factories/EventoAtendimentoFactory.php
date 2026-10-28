<?php

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\EventoAtendimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoAtendimento>
 */
class EventoAtendimentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => Atendimento::factory(),
            'relatorio_visita_id' => null, 'versao_relatorio_id' => null,
            'acao' => 'realizacao',
            'autor_user_id' => fn (array $attributes): int => Atendimento::findOrFail($attributes['atendimento_id'])->registrado_por_user_id,
            'operacao_id' => fake()->uuid(), 'conteudo_hash' => hash('sha256', 'operação fictícia'),
            'revisao_analisada' => 1, 'ocorrido_em' => now(),
        ];
    }
}
