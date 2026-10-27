<?php

namespace Database\Factories;

use App\Models\Agendamento;
use App\Models\ContatoSolicitacao;
use App\Models\Interessada;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agendamento>
 */
class AgendamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitacao_id' => Solicitacao::factory()->state([
                'status' => 'admitida', 'interessada_id' => Interessada::factory(),
                'admitida_por_user_id' => User::factory()->funcionario(), 'admitida_em' => now(),
            ]),
            'contato_confirmacao_id' => function (array $attributes): int {
                $contato = ContatoSolicitacao::factory()->create(['contexto' => 'acompanhamento', 'resultado' => 'realizado']);
                $contato->solicitacoes()->attach($attributes['solicitacao_id']);

                return $contato->id;
            },
            'responsavel_user_id' => User::factory()->funcionario(),
            'confirmado_por_user_id' => User::factory()->funcionario(),
            'modalidade' => 'domicilio', 'local_atendimento' => 'Endereço fictício da visita',
            'inicio_previsto_em' => now()->addDay(), 'fim_previsto_em' => now()->addDay()->addHour(),
            'status' => 'confirmado', 'confirmado_em' => now(), 'versao' => 1,
        ];
    }
}
