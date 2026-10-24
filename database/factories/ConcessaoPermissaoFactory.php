<?php

namespace Database\Factories;

use App\Models\ConcessaoPermissao;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConcessaoPermissao>
 */
class ConcessaoPermissaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->funcionario(),
            'permissao_id' => Permissao::factory()->atendimento(),
            'concedida_por_user_id' => User::factory()->admin(),
            'concedida_em' => now(),
        ];
    }
}
