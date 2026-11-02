<?php

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\Doadora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doadora>
 */
class DoadoraFactory extends Factory
{
    /**
     * Define the model's default state: habilitação definitiva ativa, com a decisão que a originou.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avaliacao_id' => Avaliacao::factory()->state(['estado' => 'habilitada', 'possui_plano_saude' => false, 'versao' => 3]),
            'interessada_id' => fn (array $attributes): int => Avaliacao::with('atendimento.agendamento.solicitacao')
                ->findOrFail($attributes['avaliacao_id'])->atendimento->agendamento->solicitacao->interessada_id,
            'decisao_avaliacao_id' => fn (array $attributes): int => DecisaoAvaliacao::factory()->create([
                'avaliacao_id' => $attributes['avaliacao_id'], 'etapa' => 'habilitacao', 'resultado' => 'habilitada',
                'motivo' => null, 'possui_plano_saude' => false, 'versao_analisada' => 2,
            ])->id,
            'tipo' => 'definitiva', 'condicao' => null, 'valida_ate' => null, 'estado' => 'ativa', 'versao' => 1,
        ];
    }

    /** Habilitação temporária com a data-limite informada (padrão: daqui a 30 dias). */
    public function temporaria(?string $validaAte = null): static
    {
        return $this->state(['tipo' => 'temporaria', 'condicao' => 'Condição fictícia.', 'valida_ate' => $validaAte ?? now()->addDays(30)->toDateString()]);
    }
}
