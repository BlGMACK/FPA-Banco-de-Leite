<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Atendimento;
use App\Models\EventoAtendimento;
use App\Models\RelatorioVisita;
use App\Models\ReservaAgenda;
use App\Models\User;
use App\Models\VersaoRelatorioVisita;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RelatorioVisitaOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function agenda(): Agendamento
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-02 15:00:00', 'UTC'));
        $agenda = Agendamento::factory()->create(['confirmado_em' => '2030-01-01 10:00:00',
            'inicio_previsto_em' => '2030-01-02 12:00:00', 'fim_previsto_em' => '2030-01-02 13:00:00']);
        ReservaAgenda::factory()->for($agenda, 'agendamento')->create(['solicitacao_id' => $agenda->solicitacao_id]);

        return $agenda;
    }

    private function dados(Agendamento $agenda): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'inicio_efetivo_em' => '2030-01-02T09:00',
            'fim_efetivo_em' => '2030-01-02T10:00', 'responsavel_user_id' => $agenda->responsavel_user_id,
            'modalidade' => $agenda->modalidade, 'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => true];
    }

    private function operacao(int $revisao, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'revisao' => $revisao] + $extra;
    }

    private function conflito(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_realization_is_atomic_idempotent_and_preserves_admission_without_exposing_report(): void
    {
        $agenda = $this->agenda();
        $admin = User::factory()->admin()->create();
        $dados = $this->dados($agenda) + ['registrado_por_user_id' => 999, 'status' => 'aprovada'];
        $evento = Atendimento::registrar($agenda->id, $admin, $dados);

        $this->assertSame($evento->id, Atendimento::registrar($agenda->id, $admin, $dados)->id);
        $this->assertDatabaseCount('atendimentos', 1);
        $this->assertDatabaseCount('relatorios_visita', 1);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertSame('realizado', $agenda->fresh()->status);
        $this->assertSame(2, $agenda->fresh()->versao);
        $this->assertSame('admitida', $agenda->solicitacao->fresh()->status);
        $this->assertSame(2, $agenda->solicitacao->fresh()->versao);
        $this->assertSame($admin->id, $evento->atendimento->registrado_por_user_id);
        $this->assertSame('2030-01-02 12:00:00', $evento->atendimento->inicio_efetivo_em->format('Y-m-d H:i:s'));
        $this->assertNull($evento->relatorio->texto_rascunho);
        $this->conflito(fn () => Atendimento::registrar($agenda->id, $admin, array_replace($dados, ['local_atendimento' => 'Outro'])));

        $evento->relatorio->salvarRascunho($admin, $this->operacao(1, ['texto' => 'CONTEUDO_RESTRITO']));
        $this->actingAs($admin)->get(route('interno.agenda.index', ['situacao' => 'realizado']))->assertOk()
            ->assertViewHas('pendentes', fn ($pendentes): bool => $pendentes->total() === 0)->assertDontSee('CONTEUDO_RESTRITO');
        $this->get(route('interno.agenda.acompanhamentos.show', $agenda->solicitacao_id))->assertOk()
            ->assertSee('Visita realizada')->assertDontSee('Confirmar visita')->assertDontSee('CONTEUDO_RESTRITO');
        $this->conflito(fn () => Agendamento::confirmar($agenda->solicitacao_id, $admin, [
            'operacao_id' => (string) Str::uuid(), 'versao' => 2, 'motivo' => 'Novo retorno', 'contato_id' => $agenda->contato_confirmacao_id,
            'responsavel_user_id' => $agenda->responsavel_user_id, 'modalidade' => 'banco', 'local_atendimento' => 'Banco',
            'inicio_previsto_em' => '2030-01-03T09:00', 'fim_previsto_em' => '2030-01-03T10:00', 'combinado_confirmado' => true,
        ]));
    }

    public function test_correction_by_another_author_preserves_published_version_until_finalized(): void
    {
        $report = RelatorioVisita::factory()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $saved = $report->salvarRascunho($admin, $this->operacao(1, ['texto' => 'Original']));
        $final = $this->operacao(2);
        $published = $report->finalizar($admin, $final);
        $this->assertSame($published->id, $report->finalizar($admin, $final)->id);
        $report->iniciarCorrecao($other, $this->operacao(3, ['motivo' => 'Complemento']));
        $report->salvarRascunho($other, $this->operacao(4, ['texto' => 'Corrigido']));
        $this->assertSame(1, $report->fresh()->numero_versao_vigente);
        $this->assertSame('Original', RelatorioVisita::consultarVersao($report->id, 1, $other)['texto']);
        $correction = $report->finalizar($other, $this->operacao(5));

        $this->assertSame('Original', $published->versaoRelatorio->fresh()->texto);
        $this->assertSame($published->versao_relatorio_id, $correction->versaoRelatorio->versao_base_id);
        $this->assertSame($other->id, $correction->versaoRelatorio->finalizado_por_user_id);
        $this->assertSame('Complemento', $correction->versaoRelatorio->motivo_correcao);
        $this->assertSame(2, $report->fresh()->numero_versao_vigente);
        $this->assertNull($report->fresh()->texto_rascunho);
        $this->assertDatabaseCount('versoes_relatorio_visita', 2);
        $this->assertDatabaseCount('eventos_atendimento', 5);
        $this->conflito(fn () => $report->salvarRascunho($other, $this->operacao(6, ['texto' => 'Sobrescrever'])));
        $this->conflito(fn () => $report->finalizar($other, $this->operacao(2)));
        $this->assertArrayNotHasKey('texto', $saved->getAttributes());
    }

    public static function invalidAttendance(): array
    {
        return [
            'future' => [['fim_efetivo_em' => '2030-01-03T10:00'], 'inicio_efetivo_em'],
            'before confirmation' => [['inicio_efetivo_em' => '2029-12-31T09:00'], 'inicio_efetivo_em'],
            'reversed' => [['fim_efetivo_em' => '2030-01-02T08:00'], 'fim_efetivo_em'],
            'unconfirmed' => [['realizacao_confirmada' => false], 'realizacao_confirmada'],
            'changed place' => [['local_atendimento' => 'Outro'], 'motivo_divergencia'],
            'unknown responsible' => [['responsavel_user_id' => 999999], 'responsavel_user_id'],
        ];
    }

    #[DataProvider('invalidAttendance')]
    public function test_invalid_attendance_keeps_reservation_and_has_no_partial_records(array $changes, string $field): void
    {
        $agenda = $this->agenda();
        try {
            Atendimento::registrar($agenda->id, User::factory()->admin()->create(), array_replace($this->dados($agenda), $changes));
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('atendimentos', 0);
        $this->assertDatabaseCount('relatorios_visita', 0);
        $this->assertDatabaseCount('eventos_atendimento', 0);
        $this->assertDatabaseCount('reservas_agenda', 1);
        $this->assertSame('confirmado', $agenda->fresh()->status);
    }

    public function test_empty_draft_can_be_saved_but_not_finalized(): void
    {
        $report = RelatorioVisita::factory()->create();
        $admin = User::factory()->admin()->create();
        $report->salvarRascunho($admin, $this->operacao(1, ['texto' => '   ']));
        $this->expectException(ValidationException::class);
        $report->finalizar($admin, $this->operacao(2));
    }

    public function test_audit_failure_rolls_back_finalization(): void
    {
        $report = RelatorioVisita::factory()->create(['texto_rascunho' => 'Relato']);
        EventoAtendimento::creating(function (): void {
            throw new \RuntimeException('Falha simulada');
        });
        try {
            $report->finalizar(User::factory()->admin()->create(), $this->operacao(1));
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada', $exception->getMessage());
            $this->assertDatabaseCount('versoes_relatorio_visita', 0);
            $this->assertSame(1, $report->fresh()->revisao);
            $this->assertSame('rascunho', $report->fresh()->estado);
        } finally {
            EventoAtendimento::flushEventListeners();
        }
    }

    public function test_finalized_versions_reject_model_update_and_delete(): void
    {
        $version = VersaoRelatorioVisita::factory()->create();
        foreach (['save', 'delete'] as $action) {
            try {
                $version->texto = 'Alteração';
                $version->{$action}();
                $this->fail('Expected immutable version.');
            } catch (\LogicException) {
                $this->assertDatabaseHas('versoes_relatorio_visita', ['id' => $version->id, 'texto' => 'Relatório fictício da visita.']);
            }
        }
    }

    public function test_cancelled_appointment_cannot_be_realized(): void
    {
        $agenda = $this->agenda();
        $admin = User::factory()->admin()->create();
        $agenda->cancelar($admin, ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'motivo' => 'Cancelado']);

        $this->conflito(fn () => Atendimento::registrar($agenda->id, $admin, $this->dados($agenda)));
        $this->assertDatabaseCount('atendimentos', 0);
    }

    public function test_stale_draft_and_changed_replay_do_not_overwrite_newer_text(): void
    {
        $report = RelatorioVisita::factory()->create();
        $admin = User::factory()->admin()->create();
        $data = $this->operacao(1, ['texto' => 'Vigente']);
        $event = $report->salvarRascunho($admin, $data);
        $this->assertSame($event->id, $report->salvarRascunho($admin, $data)->id);
        $this->conflito(fn () => $report->salvarRascunho($admin, array_replace($data, ['texto' => 'Alterado'])));
        $this->conflito(fn () => $report->salvarRascunho($admin, $this->operacao(1, ['texto' => 'Antigo'])));
        $this->assertSame('Vigente', $report->fresh()->texto_rascunho);
        $this->assertDatabaseCount('eventos_atendimento', 1);
    }

    public function test_text_limit_and_correction_reason_are_validated_without_changes(): void
    {
        $report = RelatorioVisita::factory()->create();
        $admin = User::factory()->admin()->create();
        try {
            $report->salvarRascunho($admin, $this->operacao(1, ['texto' => str_repeat('a', 30001)]));
            $this->fail('Expected text limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('texto', $exception->errors());
        }
        $this->assertSame(1, $report->fresh()->revisao);
        $this->assertDatabaseCount('eventos_atendimento', 0);
        $this->expectException(ValidationException::class);
        $report->iniciarCorrecao($admin, $this->operacao(1, ['motivo' => '   ']));
    }

    public function test_realization_audit_failure_restores_agenda_reservation_and_request(): void
    {
        $agenda = $this->agenda();
        EventoAtendimento::creating(function (): void {
            throw new \RuntimeException('Falha simulada');
        });
        try {
            Atendimento::registrar($agenda->id, User::factory()->admin()->create(), $this->dados($agenda));
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada', $exception->getMessage());
            $this->assertDatabaseCount('atendimentos', 0);
            $this->assertDatabaseCount('relatorios_visita', 0);
            $this->assertDatabaseCount('eventos_agendamento', 0);
            $this->assertDatabaseCount('reservas_agenda', 1);
            $this->assertSame('confirmado', $agenda->fresh()->status);
            $this->assertSame(1, $agenda->solicitacao->fresh()->versao);
        } finally {
            EventoAtendimento::flushEventListeners();
        }
    }

    public function test_justified_changes_are_preserved_without_leaking_reason_to_agenda(): void
    {
        $agenda = $this->agenda();
        $admin = User::factory()->admin()->create();
        $event = Atendimento::registrar($agenda->id, $admin, array_replace($this->dados($agenda), [
            'responsavel_user_id' => $admin->id, 'modalidade' => 'banco', 'local_atendimento' => 'Banco',
            'motivo_divergencia' => 'JUSTIFICATIVA_RESTRITA',
        ]));

        $this->assertSame($admin->id, $event->atendimento->responsavel_user_id);
        $this->assertSame('JUSTIFICATIVA_RESTRITA', $event->atendimento->motivo_divergencia);
        $this->actingAs($admin)->get(route('interno.agenda.acompanhamentos.show', $agenda->solicitacao_id))
            ->assertOk()->assertDontSee('JUSTIFICATIVA_RESTRITA');
    }
}
