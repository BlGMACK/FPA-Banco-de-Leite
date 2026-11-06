<?php

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\VersaoRelatorioVisita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avaliacao>
 */
class AvaliacaoFactory extends Factory
{
    /**
     * Define the model's default state: visita com relatório publicado, aguardando análise.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'atendimento_id' => fn (): int => VersaoRelatorioVisita::factory()->create()->relatorio->atendimento_id,
            'estado' => 'aguardando_analise', 'versao' => 1,
        ];
    }
}
