<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\ContatoSolicitacao;
use App\Models\EventoAgendamento;
use App\Models\Interessada;
use App\Models\Permissao;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AgendaOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
    }

    private function pedido(): Solicitacao
    {
        return Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => Interessada::factory()])->refresh();
    }

    private function contato(Solicitacao $pedido): ContatoSolicitacao
    {
        $contato = ContatoSolicitacao::factory()->create(['contexto' => 'acompanhamento', 'resultado' => 'realizado']);
        $contato->solicitacoes()->attach($pedido);

        return $contato;
    }

    private function dados(Solicitacao $pedido, User $responsavel): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao, 'motivo' => 'Combinado com interessada fictícia',
            'contato_id' => $this->contato($pedido)->id, 'responsavel_user_id' => $responsavel->id,
            'modalidade' => 'domicilio', 'local_atendimento' => 'Local fictício',
            'inicio_previsto_em' => '2030-01-02T09:00', 'fim_previsto_em' => '2030-01-02T10:00', 'combinado_confirmado' => true];
    }

    private function dadosContato(Solicitacao $pedido): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao,
            'canal' => 'whatsapp', 'resultado' => 'realizado', 'destinatario' => 'Telefone fictício',
            'finalidade' => 'Combinar visita', 'resumo' => 'Contato operacional fictício', 'ocorrido_em' => '2030-01-01T08:00'];
    }

    private function invalid(callable $operacao, string $campo): void
    {
        try {
            $operacao();
            $this->fail('Esperada validação de '.$campo);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
        }
    }

    private function conflito(callable $operacao): void
    {
        try {
            $operacao();
            $this->fail('Esperado conflito de operação.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_contact_is_scoped_to_admitted_request_with_server_author_and_idempotent_replay(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dadosContato($pedido) + ['contexto' => 'analise', 'registrado_por_user_id' => 999, 'identidade_conferida' => true];
        $contato = ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, $dados);
        $this->assertSame('acompanhamento', $contato->contexto);
        $this->assertSame($autor->id, $contato->registrado_por_user_id);
        $this->assertFalse($contato->identidade_conferida);
        $this->assertSame('2030-01-01 11:00:00', $contato->ocorrido_em->format('Y-m-d H:i:s'));
        $this->assertSame([$pedido->id], $contato->solicitacoes->modelKeys());
        $this->assertSame(2, $pedido->fresh()->versao);
        $this->assertSame('admitida', $pedido->fresh()->status);
        $this->assertTrue($contato->is(ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, $dados)));
        $this->assertDatabaseCount('contatos_solicitacao', 1);
        $dados['resumo'] = 'Outro conteúdo';
        $this->conflito(fn () => ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, $dados));
    }

    public function test_contact_rejects_future_date_stale_version_and_nonadmitted_request(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dadosContato($pedido);
        $this->invalid(fn () => ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, array_replace($dados, ['ocorrido_em' => '2030-01-02T10:00'])), 'ocorrido_em');
        $pedido->increment('versao');
        $this->conflito(fn () => ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, $dados));
        $pedido->forceFill(['status' => 'enviada'])->save();
        $this->conflito(fn () => ContatoSolicitacao::registrarAcompanhamento($pedido->id, $autor, $this->dadosContato($pedido)));
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_confirmation_rejects_deactivated_responsible(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $responsavel = User::factory()->funcionario()->create(['desativado_em' => now()]);
        $this->invalid(fn () => Agendamento::confirmar($pedido->id, $autor, $this->dados($pedido, $responsavel)), 'responsavel_user_id');
        $this->assertDatabaseCount('agendamentos', 0);
    }

    public function test_confirmation_converts_local_time_and_persists_reservation_audit_and_safe_replay(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dados($pedido, $autor) + ['status' => 'realizado', 'confirmado_por_user_id' => 999];
        $evento = Agendamento::confirmar($pedido->id, $autor, $dados);
        $agenda = $evento->agendamento;
        $this->assertSame('2030-01-02 12:00:00', $agenda->inicio_previsto_em->format('Y-m-d H:i:s'));
        $this->assertSame('2030-01-02 13:00:00', $agenda->fim_previsto_em->format('Y-m-d H:i:s'));
        $this->assertSame('confirmado', $agenda->status);
        $this->assertSame($autor->id, $agenda->confirmado_por_user_id);
        $this->assertTrue($agenda->reserva->solicitacao->is($pedido));
        $this->assertTrue($evento->dados_novos['combinado_confirmado']);
        $this->assertSame('admitida', $pedido->fresh()->status);
        $this->assertSame(2, $pedido->fresh()->versao);
        $this->travelTo(now()->addDays(3));
        $this->assertTrue($evento->is(Agendamento::confirmar($pedido->id, $autor, $dados)));
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('eventos_agendamento', 1);
        $dados['motivo'] = 'Outro motivo';
        $this->conflito(fn () => Agendamento::confirmar($pedido->id, $autor, $dados));
    }

    public static function entradasInvalidas(): array
    {
        return [
            'modalidade' => ['modalidade', 'online'], 'fim anterior' => ['fim_previsto_em', '2030-01-02T08:00'],
            'formato inválido' => ['inicio_previsto_em', 'amanhã'], 'sem acordo' => ['combinado_confirmado', false],
            'sem motivo' => ['motivo', '  '], 'operação inválida' => ['operacao_id', 'abc'],
            'sem local' => ['local_atendimento', ''], 'início passado' => ['inicio_previsto_em', '2030-01-01T08:00'],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_invalid_confirmation_does_not_write_agenda(string $campo, mixed $valor): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = array_replace($this->dados($pedido, $autor), [$campo => $valor]);
        $this->invalid(fn () => Agendamento::confirmar($pedido->id, $autor, $dados), $campo);
        $this->assertDatabaseCount('agendamentos', 0);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertSame(1, $pedido->fresh()->versao);
    }

    public static function contatosInvalidos(): array
    {
        return [['analise', 'realizado'], ['acompanhamento', 'sem_resposta'], ['acompanhamento', 'tentado']];
    }

    #[DataProvider('contatosInvalidos')]
    public function test_confirmation_requires_realized_followup_contact(string $contexto, string $resultado): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dados($pedido, $autor);
        ContatoSolicitacao::findOrFail($dados['contato_id'])->forceFill(compact('contexto', 'resultado'))->save();
        $this->invalid(fn () => Agendamento::confirmar($pedido->id, $autor, $dados), 'contato_id');
        $this->assertDatabaseCount('agendamentos', 0);
    }

    public function test_contact_from_other_request_and_missing_responsible_are_rejected(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dados($pedido, $autor);
        $outroContato = $this->contato($this->pedido());
        $this->invalid(fn () => Agendamento::confirmar($pedido->id, $autor, array_replace($dados, ['contato_id' => $outroContato->id])), 'contato_id');
        $this->invalid(fn () => Agendamento::confirmar($pedido->id, $autor, array_replace($dados, ['responsavel_user_id' => 999999])), 'responsavel_user_id');
        $this->assertDatabaseCount('agendamentos', 0);
    }

    public function test_overlap_is_blocked_but_adjacent_same_day_slot_is_allowed(): void
    {
        $autor = User::factory()->admin()->create();
        $primeiro = $this->pedido();
        Agendamento::confirmar($primeiro->id, $autor, $this->dados($primeiro, $autor));
        $segundo = $this->pedido();
        $dados = $this->dados($segundo, $autor);
        try {
            Agendamento::confirmar($segundo->id, $autor, $dados);
            $this->fail('Esperado bloqueio de horário.');
        } catch (ValidationException $exception) {
            $this->assertSame('O funcionário já tem um compromisso marcado para esse horário. Escolha outro horário ou responsável.', $exception->errors()['inicio_previsto_em'][0]);
        }
        $this->assertDatabaseCount('agendamentos', 1);
        $dados['inicio_previsto_em'] = '2030-01-02T10:00';
        $dados['fim_previsto_em'] = '2030-01-02T11:00';
        Agendamento::confirmar($segundo->id, $autor, $dados);
        $this->assertDatabaseCount('reservas_agenda', 2);
    }

    public function test_rescheduling_preserves_old_schedule_and_replaces_reservation_with_new_contact(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $antigo = Agendamento::confirmar($pedido->id, $autor, $this->dados($pedido, $autor))->agendamento;
        $dados = array_replace($this->dados($pedido, $autor), ['versao' => $antigo->versao, 'inicio_previsto_em' => '2030-01-02T10:00', 'fim_previsto_em' => '2030-01-02T11:00']);
        $evento = $antigo->reagendar($autor, $dados);
        $novo = $evento->agendamentoResultante;
        $this->assertSame('substituido', $antigo->fresh()->status);
        $this->assertSame('2030-01-02 12:00:00', $antigo->fresh()->inicio_previsto_em->format('Y-m-d H:i:s'));
        $this->assertSame($antigo->id, $novo->substitui_agendamento_id);
        $this->assertSame($novo->id, $pedido->fresh()->reservaAgenda->agendamento_id);
        $this->assertSame('confirmado', $evento->dados_anteriores['status']);
        $this->assertSame('substituido', $evento->dados_novos['agendamento']['status']);
        $this->assertTrue($evento->is($antigo->reagendar($autor, $dados)));
        $this->assertDatabaseCount('agendamentos', 2);
        $this->assertDatabaseCount('reservas_agenda', 1);
    }

    public function test_rescheduling_rejects_old_contact_and_preserves_reservation_on_overlap(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dados($pedido, $autor);
        $agenda = Agendamento::confirmar($pedido->id, $autor, $dados)->agendamento;
        $dados['operacao_id'] = (string) Str::uuid();
        $dados['versao'] = $agenda->versao;
        $this->invalid(fn () => $agenda->reagendar($autor, $dados), 'contato_id');
        $outro = $this->pedido();
        Agendamento::confirmar($outro->id, $autor, array_replace($this->dados($outro, $autor), ['inicio_previsto_em' => '2030-01-02T10:00', 'fim_previsto_em' => '2030-01-02T11:00']));
        $dados = array_replace($this->dados($pedido, $autor), ['versao' => $agenda->versao, 'inicio_previsto_em' => '2030-01-02T10:00', 'fim_previsto_em' => '2030-01-02T11:00']);
        $this->invalid(fn () => $agenda->reagendar($autor, $dados), 'inicio_previsto_em');
        $this->assertSame('confirmado', $agenda->fresh()->status);
        $this->assertSame($agenda->id, $pedido->fresh()->reservaAgenda->agendamento_id);
        $this->assertDatabaseCount('agendamentos', 2);
    }

    public function test_cancel_preserves_admission_releases_reservation_and_rejects_stale_decision(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $agenda = Agendamento::confirmar($pedido->id, $autor, $this->dados($pedido, $autor))->agendamento;
        $dados = ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'motivo' => 'Cancelamento combinado'];
        $evento = $agenda->cancelar($autor, $dados);
        $this->assertSame('cancelado', $agenda->fresh()->status);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertSame('admitida', $pedido->fresh()->status);
        $this->assertTrue($evento->is($agenda->cancelar($autor, $dados)));
        $dados['operacao_id'] = (string) Str::uuid();
        $this->conflito(fn () => $agenda->cancelar($autor, $dados));
        $this->assertDatabaseCount('eventos_agendamento', 2);
    }

    public function test_nonrealization_requires_time_to_have_arrived_without_creating_clinical_attendance(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $agenda = Agendamento::confirmar($pedido->id, $autor, $this->dados($pedido, $autor))->agendamento;
        $dados = ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'motivo' => 'Visita não realizada'];
        $this->invalid(fn () => $agenda->registrarNaoRealizacao($autor, $dados), 'versao');
        $this->travelTo(CarbonImmutable::parse('2030-01-02 12:00:00', 'UTC'));
        $agenda->registrarNaoRealizacao($autor, $dados);
        $this->assertSame('nao_realizado', $agenda->fresh()->status);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertSame('admitida', $pedido->fresh()->status);
    }

    public function test_audit_failure_rolls_back_schedule_reservation_and_version(): void
    {
        $pedido = $this->pedido();
        $autor = User::factory()->admin()->create();
        $dados = $this->dados($pedido, $autor);
        EventoAgendamento::creating(function (EventoAgendamento $evento): void {
            throw new \RuntimeException('Falha simulada');
        });
        try {
            Agendamento::confirmar($pedido->id, $autor, $dados);
            $this->fail('Esperada falha de auditoria.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada', $exception->getMessage());
            $this->assertDatabaseCount('agendamentos', 0);
            $this->assertDatabaseCount('reservas_agenda', 0);
            $this->assertDatabaseCount('eventos_agendamento', 0);
            $this->assertSame(1, $pedido->fresh()->versao);
        } finally {
            EventoAgendamento::flushEventListeners();
        }
    }

    public function test_attendance_only_cannot_write_and_revoked_agenda_cannot_replay(): void
    {
        $usuario = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        Permissao::factory()->atendimento()->create()->conceder($usuario, $admin, 'Atendimento');
        $pedido = $this->pedido();
        $dados = $this->dados($pedido, $usuario);
        try {
            Agendamento::confirmar($pedido->id, $usuario, $dados);
            $this->fail('Esperado acesso negado.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('agendamentos', 0);
        }
        $permissao = Permissao::factory()->agenda()->create();
        $permissao->conceder($usuario, $admin, 'Agenda');
        Agendamento::confirmar($pedido->id, $usuario, $dados);
        $permissao->revogar($usuario, $admin, 'Revogação');
        $this->expectException(AuthorizationException::class);
        Agendamento::confirmar($pedido->id, $usuario, $dados);
    }
}
