<?php

namespace Database\Factories;

use App\Models\Permissao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permissao>
 */
class PermissaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->slug(3),
            'nome' => 'Permissão fictícia',
        ];
    }

    public function atendimento(): static
    {
        return $this->state(fn (array $attributes): array => [
            'codigo' => Permissao::ATENDER_SOLICITACOES,
            'nome' => 'Atendimento de solicitações',
        ]);
    }
}
