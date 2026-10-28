<?php

namespace Database\Factories;

use App\Models\RelatorioVisita;
use App\Models\VersaoRelatorioVisita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VersaoRelatorioVisita>
 */
class VersaoRelatorioVisitaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relatorio_visita_id' => RelatorioVisita::factory()->state(['estado' => 'finalizado', 'numero_versao_vigente' => 1]),
            'numero' => 1, 'texto' => 'Relatório fictício da visita.',
            'versao_base_id' => null, 'motivo_correcao' => null,
            'finalizado_por_user_id' => fn (array $attributes): int => RelatorioVisita::findOrFail($attributes['relatorio_visita_id'])->ultimo_editor_user_id,
            'finalizado_em' => now(),
        ];
    }
}
