<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\Permissao;
use App\Models\RelatorioVisita;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AvaliacaoOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function decisao(int $versao, string $resultado, ?string $motivo = 'Motivo fictício.'): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $versao, 'resultado' => $resultado, 'motivo' => $motivo];
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

    public static function accesses(): array
    {
        return [
            'admin' => ['admin', [], true, true, true],
            'none' => ['funcionario', [], false, false, false],
            'report writer' => ['funcionario', [Permissao::REGISTRAR_RELATORIOS_VISITA], false, false, true],
            'read evaluations' => ['funcionario', [Permissao::CONSULTAR_AVALIACOES], true, false, false],
            'evaluate' => ['funcionario', [Permissao::AVALIAR_DOADORAS], true, true, true],
        ];
    }

    #[DataProvider('accesses')]
    public function test_evaluation_permissions_are_separate_and_evaluator_reads_reports(string $role, array $codes, bool $read, bool $decide, bool $readReports): void
    {
        $user = User::factory()->create(['role' => $role]);
        $admin = User::factory()->admin()->create();
        foreach ($codes as $code) {
            Permissao::factory()->create(['codigo' => $code])->conceder($user, $admin, 'Designação fictícia');
        }

        $this->assertSame($read, Gate::forUser($user)->allows(Permissao::CONSULTAR_AVALIACOES));
        $this->assertSame($decide, Gate::forUser($user)->allows(Permissao::AVALIAR_DOADORAS));
        $this->assertSame($readReports, Gate::forUser($user)->allows(Permissao::CONSULTAR_RELATORIOS_VISITA));
    }

    public function test_evaluator_cannot_write_reports(): void
    {
        $user = User::factory()->funcionario()->create();
        Permissao::factory()->create(['codigo' => Permissao::AVALIAR_DOADORAS])->conceder($user, User::factory()->admin()->create(), 'Designação');

        $this->assertFalse(Gate::forUser($user)->allows(Permissao::REGISTRAR_RELATORIOS_VISITA));
    }

    public function test_first_publication_opens_one_evaluation_and_corrections_do_not_duplicate_it(): void
    {
        $admin = User::factory()->admin()->create();
        $relatorio = RelatorioVisita::factory()->create(['texto_rascunho' => 'Relato fictício.']);

        $relatorio->finalizar($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 1]);
        $avaliacao = Avaliacao::query()->sole();
        $this->assertSame($relatorio->atendimento_id, $avaliacao->atendimento_id);
        $this->assertSame('aguardando_analise', $avaliacao->estado);

        $relatorio->iniciarCorrecao($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 2, 'motivo' => 'Ajuste']);
        $relatorio->finalizar($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 3]);
        $this->assertDatabaseCount('avaliacoes', 1);
    }

    public function test_draft_without_publication_has_no_evaluation(): void
    {
        $relatorio = RelatorioVisita::factory()->create();
        $relatorio->salvarRascunho(User::factory()->admin()->create(), ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'texto' => 'Rascunho']);

        $this->assertDatabaseCount('avaliacoes', 0);
    }

    public function test_waiting_decision_can_be_followed_by_release_with_history_of_both(): void
    {
        $avaliacao = Avaliacao::factory()->create();
        $admin = User::factory()->admin()->create();

        $primeira = $avaliacao->decidir($admin, $this->decisao(1, 'em_aguardo', '  Aguardando reagendamento.  '));
        $this->assertSame('em_aguardo', $avaliacao->fresh()->estado);
        $this->assertSame('Aguardando reagendamento.', $primeira->motivo);
        $this->assertSame(1, $primeira->versaoRelatorio->numero);

        $segunda = $avaliacao->decidir($admin, $this->decisao(2, 'liberada_exames', ''));
        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
        $this->assertSame(3, $avaliacao->fresh()->versao);
        $this->assertNull($segunda->motivo);
        $this->assertSame($admin->id, $segunda->autor_user_id);
        $this->assertSame(['em_aguardo', 'liberada_exames'], DecisaoAvaliacao::query()->orderBy('id')->pluck('resultado')->all());
    }

    #[DataProvider('definitiveResults')]
    public function test_definitive_decision_closes_analysis_and_blocks_report_correction(string $resultado): void
    {
        $avaliacao = Avaliacao::factory()->create();
        $admin = User::factory()->admin()->create();
        $relatorio = $avaliacao->atendimento->relatorio;

        $avaliacao->decidir($admin, $this->decisao(1, $resultado));

        $this->conflito(fn () => $avaliacao->decidir($admin, $this->decisao(2, 'em_aguardo')), 'A análise do relatório já foi concluída.');
        $this->conflito(fn () => $relatorio->iniciarCorrecao($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'motivo' => 'Ajuste']),
            'A análise deste relatório já foi concluída. O relatório não pode mais ser corrigido.');
        $this->assertSame('finalizado', $relatorio->fresh()->estado);
        $this->assertDatabaseCount('decisoes_avaliacao', 1);
    }

    public static function definitiveResults(): array
    {
        return ['liberada' => ['liberada_exames'], 'nao apta' => ['nao_apta']];
    }

    public function test_waiting_decision_still_allows_report_correction(): void
    {
        $avaliacao = Avaliacao::factory()->create();
        $admin = User::factory()->admin()->create();
        $avaliacao->decidir($admin, $this->decisao(1, 'em_aguardo'));

        $avaliacao->atendimento->relatorio->iniciarCorrecao($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'motivo' => 'Ajuste']);

        $this->assertSame('em_correcao', $avaliacao->atendimento->relatorio->fresh()->estado);
    }

    public function test_decision_waits_while_report_correction_is_open_and_then_uses_new_version(): void
    {
        $avaliacao = Avaliacao::factory()->create();
        $admin = User::factory()->admin()->create();
        $relatorio = $avaliacao->atendimento->relatorio;
        $relatorio->iniciarCorrecao($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'motivo' => 'Ajuste']);

        $this->conflito(fn () => $avaliacao->decidir($admin, $this->decisao(1, 'liberada_exames')),
            'O relatório está em correção. Decida depois que a correção for finalizada.');
        $this->assertSame('aguardando_analise', $avaliacao->fresh()->estado);

        $relatorio->fresh()->finalizar($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 2]);
        $decisao = $avaliacao->decidir($admin, $this->decisao(1, 'liberada_exames'));
        $this->assertSame(2, $decisao->versaoRelatorio->numero);
    }

    #[DataProvider('reasonRequired')]
    public function test_waiting_and_not_eligible_require_reason_without_changes(string $resultado): void
    {
        $avaliacao = Avaliacao::factory()->create();

        try {
            $avaliacao->decidir(User::factory()->admin()->create(), $this->decisao(1, $resultado, '   '));
            $this->fail('Expected validation error.');
        } catch (ValidationException $exception) {
            $this->assertSame(['motivo' => ['Informe o motivo para deixar em aguardo ou considerar não apta.']], $exception->errors());
        }
        $this->assertSame('aguardando_analise', $avaliacao->fresh()->estado);
        $this->assertDatabaseCount('decisoes_avaliacao', 0);
    }

    public static function reasonRequired(): array
    {
        return ['em aguardo' => ['em_aguardo'], 'nao apta' => ['nao_apta']];
    }

    public function test_unknown_result_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Avaliacao::factory()->create()->decidir(User::factory()->admin()->create(), $this->decisao(1, 'habilitada'));
    }

    public function test_replay_is_idempotent_and_changed_replay_or_stale_version_conflicts(): void
    {
        $avaliacao = Avaliacao::factory()->create();
        $admin = User::factory()->admin()->create();
        $dados = $this->decisao(1, 'em_aguardo');

        $decisao = $avaliacao->decidir($admin, $dados);
        $this->assertSame($decisao->id, $avaliacao->decidir($admin, $dados)->id);
        $this->conflito(fn () => $avaliacao->decidir($admin, array_replace($dados, ['motivo' => 'Outro'])), 'Operação já utilizada com outros dados.');
        $this->conflito(fn () => $avaliacao->decidir($admin, $this->decisao(1, 'nao_apta')), 'A avaliação mudou. Recarregue antes de continuar.');
        $this->assertDatabaseCount('decisoes_avaliacao', 1);
    }

    public function test_revoked_evaluator_cannot_decide(): void
    {
        $user = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        $permissao = Permissao::factory()->create(['codigo' => Permissao::AVALIAR_DOADORAS]);
        $permissao->conceder($user, $admin, 'Designação');
        $permissao->revogar($user, $admin, 'Fim da designação');

        $this->expectException(AuthorizationException::class);
        Avaliacao::factory()->create()->decidir($user, $this->decisao(1, 'liberada_exames'));
    }

    public function test_recorded_decisions_reject_update_and_delete(): void
    {
        $decisao = DecisaoAvaliacao::factory()->create();

        foreach ([fn () => $decisao->forceFill(['resultado' => 'nao_apta'])->save(), fn () => $decisao->delete()] as $acao) {
            try {
                $acao();
                $this->fail('Expected immutable decision.');
            } catch (\LogicException) {
            }
        }
        $this->assertSame('em_aguardo', $decisao->fresh()->resultado);
    }
}
