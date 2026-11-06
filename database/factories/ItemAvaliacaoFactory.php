<?php

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\ItemAvaliacao;
use App\Models\TipoExame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemAvaliacao>
 */
class ItemAvaliacaoFactory extends Factory
{
    /**
     * Define the model's default state: exame do catálogo, não solicitado, numa avaliação liberada para exames.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avaliacao_id' => Avaliacao::factory()->state(['estado' => 'liberada_exames', 'versao' => 2]),
            'tipo_exame_id' => TipoExame::factory(), 'estado' => 'nao_solicitado', 'prorrogacoes' => 0, 'versao' => 1,
        ];
    }
}
