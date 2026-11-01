<?php

namespace Database\Factories;

use App\Models\TipoExame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoExame>
 */
class TipoExameFactory extends Factory
{
    /**
     * Define the model's default state: exame com validade de 30 dias.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('exame_????????'), 'nome' => 'Exame fictício '.fake()->unique()->numberBetween(1, 99999),
            'categoria' => 'exame', 'validade_dias' => 30, 'exige_numero' => false, 'ativo' => true, 'ordem' => 0,
        ];
    }

    public function cns(): static
    {
        return $this->state(['codigo' => TipoExame::CNS, 'nome' => 'Cartão Nacional de Saúde (CNS)', 'categoria' => 'documento',
            'validade_dias' => null, 'exige_numero' => true]);
    }
}
