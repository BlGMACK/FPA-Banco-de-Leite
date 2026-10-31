<?php

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\User;
use App\Models\VersaoRelatorioVisita;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DecisaoAvaliacao>
 */
class DecisaoAvaliacaoFactory extends Factory
{
    /**
     * Define the model's default state: análise que deixou a avaliação em aguardo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avaliacao_id' => Avaliacao::factory()->state(['estado' => 'em_aguardo', 'versao' => 2]),
            'etapa' => 'analise_relatorio', 'resultado' => 'em_aguardo', 'motivo' => 'Motivo fictício.',
            'versao_relatorio_id' => fn (array $attributes): int => VersaoRelatorioVisita::query()
                ->whereHas('relatorio', fn ($query) => $query->where('atendimento_id', Avaliacao::findOrFail($attributes['avaliacao_id'])->atendimento_id))
                ->orderByDesc('numero')->value('id'),
            'autor_user_id' => User::factory()->admin(),
            'decidido_em' => now(), 'operacao_id' => fn (): string => (string) Str::uuid(),
            'conteudo_hash' => str_repeat('a', 64), 'versao_analisada' => 1,
        ];
    }
}
