<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\Doadora;
use App\Models\EventoDoadora;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HabilitacaoOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    /** Avaliação liberada para exames com um item solicitado e aceito dentro da validade. */
    private function pronta(): Avaliacao
    {
        return ItemAvaliacao::factory()->create(['estado' => 'aceito', 'realizado_em' => now()->subDays(5)->toDateString(),
            'valido_ate' => now()->addDays(25)->toDateString()])->avaliacao;
    }

    private function decisao(Avaliacao $avaliacao, string $resultado, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $avaliacao->fresh()->versao, 'resultado' => $resultado,
            'possui_plano_saude' => '0', 'motivo' => null, 'condicao' => null, 'valida_ate' => null, 'confirmacao_definitiva' => '1', ...$extra];
    }

    private function acao(Doadora $doadora, string $acao, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $doadora->fresh()->versao, 'acao' => $acao, 'motivo' => 'Motivo fictício.',
            'confirmacao_definitiva' => '1', ...$extra];
    }

    private function conflito(callable $callback, string $mensagem): void
    {
        try {
            $callback();
            $this->fail('Expected conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame($mensagem, $exception->getMessage());
        }
    }

    private function invalido(callable $callback, string $campo): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    public function test_enabling_creates_active_definitive_donor_linked_to_applicant_and_closes_exams(): void
    {
        $admin = User::factory()->admin()->create();
        $avaliacao = $this->pronta();
        $item = $avaliacao->itens()->sole();

        $decisao = $avaliacao->habilitar($admin, $this->decisao($avaliacao, 'habilitada', ['possui_plano_saude' => '1']));

        $avaliacao->refresh();
        $doadora = Doadora::query()->sole();
        $this->assertSame(['habilitacao', 'habilitada', true], [$decisao->etapa, $decisao->resultado, $decisao->possui_plano_saude]);
        $this->assertSame(['habilitada', true], [$avaliacao->estado, $avaliacao->possui_plano_saude]);
        $this->assertSame($avaliacao->atendimento->agendamento->solicitacao->interessada_id, $doadora->interessada_id);
        $this->assertSame([$avaliacao->id, $decisao->id, 'definitiva', 'ativa', null], [$doadora->avaliacao_id, $doadora->decisao_avaliacao_id,
            $doadora->tipo, $doadora->estado, $doadora->valida_ate]);
        $this->conflito(fn () => $item->registrar($admin, ['operacao_id' => (string) Str::uuid(), 'versao' => $item->versao, 'acao' => 'aceite']),
            'A etapa de exames desta avaliação não está aberta.');
    }

    public function test_temporary_enabling_keeps_condition_and_deadline(): void
    {
        $avaliacao = $this->pronta();
        $prazo = now('America/Sao_Paulo')->addDays(60)->toDateString();

        $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'habilitada_temporariamente',
            ['condicao' => 'Reavaliar após o retorno ao trabalho.', 'valida_ate' => $prazo]));

        $doadora = Doadora::query()->sole();
        $this->assertSame(['temporaria', 'Reavaliar após o retorno ao trabalho.', $prazo], [$doadora->tipo, $doadora->condicao, $doadora->valida_ate->toDateString()]);
        $this->assertSame('habilitada_temporariamente', $avaliacao->fresh()->estado);
    }

    public static function invalidDecisions(): array
    {
        return [
            'temporary without condition' => ['habilitada_temporariamente', ['valida_ate' => '+10 days'], 'condicao'],
            'temporary without deadline' => ['habilitada_temporariamente', ['condicao' => 'Condição.'], 'valida_ate'],
            'temporary ending today' => ['habilitada_temporariamente', ['condicao' => 'Condição.', 'valida_ate' => 'today'], 'valida_ate'],
            'not enabled without reason' => ['nao_habilitada', [], 'motivo'],
            'health plan missing' => ['habilitada', ['possui_plano_saude' => null], 'possui_plano_saude'],
            'not confirmed' => ['habilitada', ['confirmacao_definitiva' => null], 'confirmacao_definitiva'],
            'analysis result' => ['liberada_exames', [], 'resultado'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_invalid_decisions_change_nothing(string $resultado, array $extra, string $campo): void
    {
        $avaliacao = $this->pronta();
        if (isset($extra['valida_ate'])) {
            $extra['valida_ate'] = now('America/Sao_Paulo')->modify($extra['valida_ate'])->toDateString();
        }

        $this->invalido(fn () => $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, $resultado, $extra)), $campo);

        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
        $this->assertDatabaseCount('doadoras', 0);
        $this->assertFalse(DecisaoAvaliacao::query()->where('etapa', 'habilitacao')->exists());
    }

    public function test_not_enabled_records_reason_without_donor(): void
    {
        $avaliacao = $this->pronta();

        $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'nao_habilitada', ['motivo' => 'Motivo fictício.']));

        $this->assertSame('nao_habilitada', $avaliacao->fresh()->estado);
        $this->assertDatabaseCount('doadoras', 0);
    }

    public static function unmetRequirements(): array
    {
        return [
            'nothing requested' => [['estado' => 'nao_solicitado']],
            'requested not accepted' => [['estado' => 'recebido', 'realizado_em' => '-5 days', 'valido_ate' => '+25 days']],
            'accepted but expired' => [['estado' => 'aceito', 'realizado_em' => '-40 days', 'valido_ate' => '-10 days']],
        ];
    }

    #[DataProvider('unmetRequirements')]
    public function test_decision_requires_exam_requirements(array $item): void
    {
        foreach (['realizado_em', 'valido_ate'] as $campo) {
            if (isset($item[$campo])) {
                $item[$campo] = now('America/Sao_Paulo')->modify($item[$campo])->toDateString();
            }
        }
        $avaliacao = ItemAvaliacao::factory()->create($item)->avaliacao;

        $this->conflito(fn () => $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'nao_habilitada', ['motivo' => 'X'])),
            'As exigências dos exames não estão cumpridas: é preciso ao menos um item solicitado, todos os solicitados aceitos e nenhum vencido.');
        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
    }

    public function test_decision_requires_open_exam_stage(): void
    {
        $avaliacao = Avaliacao::factory()->create();

        $this->conflito(fn () => $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'habilitada')),
            'A etapa de exames desta avaliação não está aberta.');
    }

    public function test_applicant_with_current_enabling_cannot_be_enabled_again(): void
    {
        $avaliacao = $this->pronta();
        $anterior = Doadora::factory()->create();
        $anterior->forceFill(['interessada_id' => $avaliacao->atendimento->agendamento->solicitacao->interessada_id])->save();

        $this->conflito(fn () => $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'habilitada')),
            'Esta interessada já tem uma habilitação ativa ou suspensa. Encerre-a antes de registrar outra.');

        $anterior->forceFill(['estado' => 'encerrada'])->save();
        $avaliacao->habilitar(User::factory()->admin()->create(), $this->decisao($avaliacao, 'habilitada'));
        $this->assertDatabaseCount('doadoras', 2);
    }

    public function test_replay_is_idempotent_and_changed_replay_or_stale_version_conflicts(): void
    {
        $admin = User::factory()->admin()->create();
        $avaliacao = $this->pronta();
        $dados = $this->decisao($avaliacao, 'habilitada');

        $primeira = $avaliacao->habilitar($admin, $dados);
        $this->assertTrue($primeira->is($avaliacao->habilitar($admin, $dados)));
        $this->conflito(fn () => $avaliacao->habilitar($admin, [...$dados, 'possui_plano_saude' => '1']), 'Operação já utilizada com outros dados.');
        $this->conflito(fn () => $avaliacao->habilitar($admin, [...$dados, 'operacao_id' => (string) Str::uuid()]), 'A avaliação mudou. Recarregue antes de continuar.');
        $this->assertDatabaseCount('doadoras', 1);
    }

    public function test_only_evaluators_decide_and_manage_enablings(): void
    {
        $leitora = User::factory()->funcionario()->create();
        Permissao::factory()->create(['codigo' => Permissao::CONSULTAR_AVALIACOES])->conceder($leitora, User::factory()->admin()->create(), 'Designação');
        $avaliacao = $this->pronta();
        $doadora = Doadora::factory()->create();

        foreach ([fn () => $avaliacao->habilitar($leitora, $this->decisao($avaliacao, 'habilitada')),
            fn () => $doadora->registrar($leitora, $this->acao($doadora, 'suspensao'))] as $operacao) {
            try {
                $operacao();
                $this->fail('Expected authorization failure.');
            } catch (AuthorizationException) {
            }
        }
        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
        $this->assertSame('ativa', $doadora->fresh()->estado);
        $this->assertDatabaseCount('doadoras', 1);
    }

    public function test_suspension_and_resumption_keep_history(): void
    {
        $admin = User::factory()->admin()->create();
        $doadora = Doadora::factory()->create();

        $doadora->registrar($admin, $this->acao($doadora, 'suspensao'));
        $this->assertSame('suspensa', $doadora->fresh()->estado);
        $this->conflito(fn () => $doadora->registrar($admin, $this->acao($doadora, 'suspensao')), 'Só é possível suspender uma habilitação ativa.');
        $doadora->registrar($admin, $this->acao($doadora, 'retomada'));

        $this->assertSame('ativa', $doadora->fresh()->estado);
        $this->assertSame([['suspensao', 'ativa', 'suspensa'], ['retomada', 'suspensa', 'ativa']],
            EventoDoadora::query()->orderBy('id')->get()->map(fn (EventoDoadora $e): array => [$e->acao, $e->estado_anterior, $e->estado_novo])->all());
    }

    public function test_temporary_enabling_is_flagged_when_expired_and_can_be_renewed_or_made_definitive(): void
    {
        $admin = User::factory()->admin()->create();
        $doadora = Doadora::factory()->temporaria(now('America/Sao_Paulo')->subDay()->toDateString())->create();
        $this->assertTrue($doadora->vencida());
        $this->assertSame('ativa', $doadora->estado);

        $this->invalido(fn () => $doadora->registrar($admin, $this->acao($doadora, 'renovacao', ['condicao' => 'Nova condição.',
            'valida_ate' => now('America/Sao_Paulo')->toDateString()])), 'valida_ate');
        $prazo = now('America/Sao_Paulo')->addDays(90)->toDateString();
        $doadora->registrar($admin, $this->acao($doadora, 'renovacao', ['condicao' => 'Nova condição.', 'valida_ate' => $prazo]));
        $doadora->refresh();
        $this->assertSame([$prazo, 'Nova condição.', false], [$doadora->valida_ate->toDateString(), $doadora->condicao, $doadora->vencida()]);

        $doadora->registrar($admin, $this->acao($doadora, 'conversao_definitiva'));
        $doadora->refresh();
        $this->assertSame(['definitiva', null, null], [$doadora->tipo, $doadora->condicao, $doadora->valida_ate]);
        $this->conflito(fn () => $doadora->registrar($admin, $this->acao($doadora, 'renovacao', ['condicao' => 'X', 'valida_ate' => $prazo])),
            'Só a habilitação temporária tem prazo para renovar.');
    }

    #[DataProvider('finalActions')]
    public function test_final_actions_require_confirmation_and_end_the_enabling(string $acao, string $estado): void
    {
        $admin = User::factory()->admin()->create();
        $doadora = Doadora::factory()->create(['estado' => 'suspensa']);

        $this->invalido(fn () => $doadora->registrar($admin, $this->acao($doadora, $acao, ['confirmacao_definitiva' => null])), 'confirmacao_definitiva');
        $doadora->registrar($admin, $this->acao($doadora, $acao));

        $this->assertSame($estado, $doadora->fresh()->estado);
        $this->conflito(fn () => $doadora->registrar($admin, $this->acao($doadora, 'retomada')), 'A habilitação já terminou e não aceita novas ações.');
    }

    public static function finalActions(): array
    {
        return ['closing' => ['encerramento', 'encerrada'], 'revoking' => ['revogacao', 'revogada']];
    }

    public function test_action_requires_reason_and_rejects_stale_version_or_changed_replay(): void
    {
        $admin = User::factory()->admin()->create();
        $doadora = Doadora::factory()->create();

        $this->invalido(fn () => $doadora->registrar($admin, $this->acao($doadora, 'suspensao', ['motivo' => ' '])), 'motivo');
        $dados = $this->acao($doadora, 'suspensao');
        $evento = $doadora->registrar($admin, $dados);
        $this->assertTrue($evento->is($doadora->registrar($admin, $dados)));
        $this->conflito(fn () => $doadora->registrar($admin, [...$dados, 'motivo' => 'Outro.']), 'Operação já utilizada com outros dados.');
        $this->conflito(fn () => $doadora->registrar($admin, [...$dados, 'operacao_id' => (string) Str::uuid(), 'acao' => 'retomada']),
            'A habilitação mudou. Recarregue antes de continuar.');
    }

    public function test_recorded_events_reject_update_and_delete(): void
    {
        $doadora = Doadora::factory()->create();
        $evento = $doadora->registrar(User::factory()->admin()->create(), $this->acao($doadora, 'suspensao'));

        foreach ([fn () => $evento->forceFill(['motivo' => 'X'])->save(), fn () => $evento->delete()] as $operacao) {
            try {
                $operacao();
                $this->fail('Expected immutability.');
            } catch (\LogicException) {
                $this->assertDatabaseHas('eventos_doadora', ['id' => $evento->id, 'motivo' => 'Motivo fictício.']);
            }
        }
    }
}
