<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\ConcessaoPermissao;
use App\Models\EventoAgendamento;
use App\Models\Permissao;
use App\Models\ReservaAgenda;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgendaModelsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public static function permissoes(): array
    {
        return [
            'nenhuma' => ['funcionario', false, false, false, false],
            'atendimento' => ['funcionario', true, false, true, false],
            'agenda' => ['funcionario', false, true, false, true],
            'ambas' => ['funcionario', true, true, true, true],
            'admin' => ['admin', false, false, true, true],
            'doadora com concessão indevida' => ['doadora', false, true, false, false],
        ];
    }

    #[DataProvider('permissoes')]
    public function test_gates_separate_and_combine_permissions_without_clinical_access(string $papel, bool $atendimento, bool $agenda, bool $podeAtender, bool $podeAgendar): void
    {
        $usuario = User::factory()->create(['role' => $papel]);
        foreach ([Permissao::ATENDER_SOLICITACOES => $atendimento, Permissao::GERENCIAR_AGENDA => $agenda] as $codigo => $conceder) {
            if ($conceder) {
                ConcessaoPermissao::factory()->for($usuario, 'usuario')->for(Permissao::factory()->create(['codigo' => $codigo]), 'permissao')->create();
            }
        }
        $this->assertSame($podeAtender, Gate::forUser($usuario)->allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertSame($podeAgendar, Gate::forUser($usuario)->allows(Permissao::GERENCIAR_AGENDA));
        $this->assertFalse(Gate::forUser($usuario)->allows('consultar-relatorio'));
        $this->assertFalse(Gate::forUser($usuario)->allows('aprovar-doacao'));
    }

    public function test_agenda_grant_revoke_and_regrant_preserve_other_permission_and_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $usuario = User::factory()->funcionario()->create();
        $agenda = Permissao::factory()->agenda()->create();
        Permissao::factory()->atendimento()->create()->conceder($usuario, $admin, 'Atendimento');
        $usuario->load('concessoes');
        $this->assertTrue($agenda->conceder($usuario, $admin, 'Agenda'));
        $this->assertFalse($agenda->conceder($usuario, $admin, 'Reenvio'));
        $this->assertTrue($usuario->podeGerenciarAgenda());
        $this->assertTrue($agenda->revogar($usuario, $admin, 'Mudança de função'));
        $this->assertFalse($agenda->revogar($usuario, $admin, 'Reenvio'));
        $this->assertFalse($usuario->podeGerenciarAgenda());
        $this->assertTrue($usuario->podeAtenderSolicitacoes());
        $this->assertTrue($agenda->conceder($usuario, $admin, 'Retorno'));
        $this->assertTrue($usuario->podeGerenciarAgenda());
        $this->assertSame(['concessao', 'revogacao', 'concessao'], $agenda->eventos()->orderBy('id')->pluck('acao')->all());
        $this->assertDatabaseHas('eventos_permissao', ['permissao_id' => $agenda->id, 'autor_user_id' => $admin->id, 'motivo' => 'Mudança de função']);
        $this->assertDatabaseCount('permissao_user', 2);
    }

    public function test_agenda_middleware_observes_revocation_and_does_not_require_attendance(): void
    {
        Route::middleware(['web', 'auth', 'can:gerenciar-agenda'])->get('/_test/agenda', fn () => response('Agenda'));
        $this->get('/_test/agenda')->assertRedirect('/login');
        $usuario = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        $agenda = Permissao::factory()->agenda()->create();
        $agenda->conceder($usuario, $admin, 'Agenda');
        $this->actingAs($usuario)->get('/_test/agenda')->assertOk();
        $this->get('/interno/solicitacoes')->assertForbidden();
        $agenda->revogar($usuario, $admin, 'Revogação');
        $this->get('/_test/agenda')->assertForbidden();
    }

    public function test_current_role_and_existence_override_stale_user_for_agenda(): void
    {
        $usuario = User::factory()->admin()->create();
        User::whereKey($usuario->id)->update(['role' => 'doadora']);
        $this->assertFalse($usuario->podeGerenciarAgenda());
        User::whereKey($usuario->id)->delete();
        $this->assertFalse($usuario->podeGerenciarAgenda());
        $this->assertFalse((new User)->podeGerenciarAgenda());
        $this->assertFalse(Gate::allows(Permissao::GERENCIAR_AGENDA));
    }

    public function test_employee_cannot_self_grant_agenda(): void
    {
        $usuario = User::factory()->funcionario()->create();
        $agenda = Permissao::factory()->agenda()->create();
        try {
            $agenda->conceder($usuario, $usuario, 'Tentativa');
            $this->fail('Concessão deveria ser negada.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('permissao_user', 0);
            $this->assertDatabaseCount('eventos_permissao', 0);
        }
    }

    public function test_agenda_cannot_be_granted_to_donor(): void
    {
        $agenda = Permissao::factory()->agenda()->create();
        try {
            $agenda->conceder(User::factory()->create(), User::factory()->admin()->create(), 'Tentativa');
            $this->fail('Destinatário inválido deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('funcionario', $exception->errors());
            $this->assertDatabaseCount('permissao_user', 0);
        }
    }

    public function test_models_navigate_schedule_chain_and_preserve_history_after_releasing_reservation(): void
    {
        $this->freezeTime();
        $anterior = Agendamento::factory()->create(['status' => 'substituido']);
        $atual = Agendamento::factory()->create(['solicitacao_id' => $anterior->solicitacao_id, 'substitui_agendamento_id' => $anterior->id]);
        $reserva = ReservaAgenda::factory()->for($atual, 'agendamento')->create();
        $evento = EventoAgendamento::factory()->for($anterior, 'agendamento')->create([
            'acao' => 'reagendamento', 'agendamento_resultante_id' => $atual->id,
            'dados_anteriores' => ['status' => 'confirmado'], 'dados_novos' => ['status' => 'substituido'], 'versao_analisada' => 1,
        ])->refresh();
        $this->assertTrue($atual->substitui->is($anterior));
        $this->assertTrue($anterior->sucessor->is($atual));
        $this->assertTrue($atual->reserva->is($reserva));
        $this->assertTrue($reserva->solicitacao->reservaAgenda->is($reserva));
        $this->assertTrue($reserva->agendamento->is($atual));
        $this->assertCount(2, $atual->solicitacao->agendamentos);
        $this->assertTrue($atual->contatoConfirmacao->solicitacoes->contains('id', $atual->solicitacao_id));
        $this->assertTrue($atual->contatoConfirmacao->agendamentos->first()->is($atual));
        $this->assertSame('acompanhamento', $atual->contatoConfirmacao->contexto);
        $this->assertSame($atual->responsavel_user_id, $atual->responsavel->id);
        $this->assertSame($atual->confirmado_por_user_id, $atual->confirmadoPor->id);
        $this->assertTrue($evento->agendamento->is($anterior));
        $this->assertTrue($evento->agendamentoResultante->is($atual));
        $this->assertTrue($anterior->eventos->first()->is($evento));
        $this->assertSame($anterior->confirmado_por_user_id, $evento->autor->id);
        $this->assertSame(['status' => 'substituido'], $evento->dados_novos);
        $this->assertSame(['status' => 'confirmado'], $evento->dados_anteriores);
        $this->assertSame(1, $evento->versao_analisada);
        $this->assertInstanceOf(CarbonImmutable::class, $evento->ocorrido_em);
        $this->assertInstanceOf(CarbonImmutable::class, $atual->inicio_previsto_em);
        $this->assertInstanceOf(CarbonImmutable::class, $atual->fim_previsto_em);
        $this->assertInstanceOf(CarbonImmutable::class, $atual->confirmado_em);
        $this->assertSame(1, $atual->fresh()->versao);
        $this->assertSame($atual->solicitacao_id, $reserva->getKey());
        $reserva->delete();
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertDatabaseCount('agendamentos', 2);
        $this->assertDatabaseCount('eventos_agendamento', 1);
        $this->assertDatabaseCount('permissao_user', 0);
    }

    public static function models(): array
    {
        return [[Agendamento::class], [ReservaAgenda::class], [EventoAgendamento::class]];
    }

    #[DataProvider('models')]
    public function test_new_models_guard_request_derived_mass_assignment(string $model): void
    {
        $this->expectException(MassAssignmentException::class);
        (new $model)->fill(['solicitacao_id' => 99, 'autor_user_id' => 99]);
    }
}
