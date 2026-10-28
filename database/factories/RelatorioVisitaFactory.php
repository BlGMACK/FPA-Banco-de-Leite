<?php

namespace Database\Factories;

use App\Models\Atendimento;
use App\Models\RelatorioVisita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelatorioVisita>
 */
class RelatorioVisitaFactory extends Factory
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
            'estado' => 'rascunho', 'texto_rascunho' => null,
            'numero_versao_vigente' => 0, 'base_correcao_numero' => null, 'motivo_correcao' => null,
            'criado_por_user_id' => fn (array $attributes): int => Atendimento::findOrFail($attributes['atendimento_id'])->registrado_por_user_id,
            'ultimo_editor_user_id' => fn (array $attributes): int => $attributes['criado_por_user_id'],
            'revisao' => 1,
        ];
    }
}
