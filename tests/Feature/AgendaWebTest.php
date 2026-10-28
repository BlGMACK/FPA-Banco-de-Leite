<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\ContatoSolicitacao;
use App\Models\Interessada;
use App\Models\Permissao;
use App\Models\ReservaAgenda;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgendaWebTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function operador(): User
    {
        $user = User::factory()->funcionario()->create();
        Permissao::factory()->agenda()->create()->conceder($user, User::factory()->admin()->create(), 'Organizar visitas');

        return $user;
    }

    private function pedido(): Solicitacao
    {
        return Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => Interessada::factory()]);
    }

    private function contato(Solicitacao $pedido): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao,
            'canal' => 'whatsapp', 'resultado' => 'realizado', 'destinatario' => 'Pessoa fictícia',
            'finalidade' => 'Combinar visita', 'resumo' => 'Combinado', 'ocorrido_em' => '2030-01-01T08:00'];
    }

    private function dados(Solicitacao $pedido, User $user): array
    {
        $contato = ContatoSolicitacao::factory()->create(['contexto' => 'acompanhamento', 'resultado' => 'realizado', 'ocorrido_em' => now()]);
        $contato->solicitacoes()->attach($pedido);

        return ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao,
            'contato_id' => $contato->id, 'responsavel_user_id' => $user->id, 'modalidade' => 'domicilio',
            'local_atendimento' => 'Local fictício', 'inicio_previsto_em' => '2030-01-02T09:00',
            'fim_previsto_em' => '2030-01-02T10:00', 'motivo' => 'Combinado', 'combinado_confirmado' => '1'];
    }

    public function test_all_routes_authorize_before_resolving_ids(): void
    {
        $routes = [['get', '/interno/agenda'], ['get', '/interno/agenda/acompanhamentos/99999'],
            ['post', '/interno/agenda/acompanhamentos/99999/contatos'], ['post', '/interno/agenda/acompanhamentos/99999/agendamentos'],
            ['post', '/interno/agenda/99999/reagendamentos'], ['post', '/interno/agenda/99999/cancelamento'], ['post', '/interno/agenda/99999/nao-realizacao']];
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $user = User::factory()->funcionario()->create();
        Permissao::factory()->atendimento()->create()->conceder($user, User::factory()->admin()->create(), 'Atendimento');
        $this->actingAs($user);
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertDatabaseCount('agendamentos', 0);
    }

    public function test_agenda_only_access_excludes_analysis_and_escapes_operational_text(): void
    {
        $user = $this->operador();
        $agenda = Agendamento::factory()->create(['local_atendimento' => '<script>local</script>']);
        ReservaAgenda::factory()->create(['solicitacao_id' => $agenda->solicitacao_id, 'agendamento_id' => $agenda->id]);
        $privado = ContatoSolicitacao::factory()->create(['contexto' => 'analise', 'resumo' => 'JUSTIFICATIVA_RESTRITA']);
        $privado->solicitacoes()->attach($agenda->solicitacao_id);
        $agenda->contatoConfirmacao->forceFill(['resumo' => '<script>resumo</script>'])->save();
        $this->actingAs($user)->get('/interno/agenda')->assertOk();
        $this->get('/interno/agenda/acompanhamentos/'.$agenda->solicitacao_id)->assertOk()
            ->assertSee('<script>local</script>')->assertDontSee('<script>local</script>', false)
            ->assertSee('<script>resumo</script>')->assertDontSee('<script>resumo</script>', false)
            ->assertDontSee('JUSTIFICATIVA_RESTRITA')->assertDontSee('Ver acompanhamento administrativo');
        $this->get('/interno/acompanhamentos/'.$agenda->solicitacao_id)->assertForbidden();
        $this->get('/interno/agenda/acompanhamentos/'.Solicitacao::factory()->create()->id)->assertNotFound();
        $this->get('/interno/agenda/acompanhamentos/99999')->assertNotFound();
        Permissao::where('codigo', Permissao::GERENCIAR_AGENDA)->sole()->revogar($user, User::factory()->admin()->create(), 'Revogada');
        $this->get('/interno/agenda')->assertForbidden();
    }

    public function test_contact_and_confirmation_replay_and_forced_author_fields(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
        $pedido = $this->pedido();
        $user = $this->operador();
        $url = '/interno/agenda/acompanhamentos/'.$pedido->id;
        $dados = $this->contato($pedido) + ['contexto' => 'analise', 'registrado_por_user_id' => 99999];
        $this->actingAs($user)->post($url.'/contatos', $dados)->assertRedirect($url);
        $this->post($url.'/contatos', $dados)->assertRedirect($url);
        $this->assertDatabaseCount('contatos_solicitacao', 1);
        $this->assertDatabaseHas('contatos_solicitacao', ['contexto' => 'acompanhamento', 'registrado_por_user_id' => $user->id]);
        $dados = $this->dados($pedido, $user);
        $this->post($url.'/agendamentos', $dados + ['status' => 'cancelado'])->assertRedirect($url);
        $this->post($url.'/agendamentos', $dados)->assertRedirect($url);
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('eventos_agendamento', 1);
        $this->assertDatabaseHas('agendamentos', ['status' => 'confirmado', 'inicio_previsto_em' => '2030-01-02 12:00:00']);
        $this->get($url)->assertOk()->assertSee('02/01/2030 09:00')->assertSee('Reagendar visita');
    }

    public function test_validation_preserves_only_safe_values_in_the_contact_form(): void
    {
        $pedido = $this->pedido();
        $url = '/interno/agenda/acompanhamentos/'.$pedido->id;
        $dados = array_replace($this->contato($pedido), ['resumo' => ['invalid'], 'finalidade' => 'Texto a preservar', 'role' => 'admin']);
        $this->actingAs($this->operador())->post($url.'/contatos', $dados)->assertRedirect($url)->assertSessionHasErrorsIn('contato', 'resumo');
        $this->assertNull(session()->getOldInput('dados.role'));
        $this->assertNull(session()->getOldInput('dados.resumo'));
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk()->assertSee('Texto a preservar');
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_overlap_error_and_stale_version_conflict_do_not_create_an_appointment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
        $user = $this->operador();
        Agendamento::factory()->create(['responsavel_user_id' => $user->id, 'inicio_previsto_em' => '2030-01-02 12:00:00', 'fim_previsto_em' => '2030-01-02 13:00:00']);
        $pedido = $this->pedido();
        $dados = $this->dados($pedido, $user);
        $url = '/interno/agenda/acompanhamentos/'.$pedido->id;
        $this->actingAs($user)->post($url.'/agendamentos', $dados)->assertRedirect($url)->assertSessionHasErrorsIn('confirmacao', 'inicio_previsto_em');
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk()
            ->assertSee('O funcionário já tem um compromisso marcado para esse horário.');
        $pedido->increment('versao');
        $this->post($url.'/agendamentos', $dados)->assertStatus(409)->assertSee('Reabra o acompanhamento');
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('eventos_agendamento', 0);
    }

    public function test_reschedule_and_cancel_preserve_history_and_release_reservation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
        $user = $this->operador();
        $agenda = Agendamento::factory()->create();
        ReservaAgenda::factory()->create(['solicitacao_id' => $agenda->solicitacao_id, 'agendamento_id' => $agenda->id]);
        $dados = $this->dados($agenda->solicitacao, $user);
        $dados['versao'] = $agenda->versao;
        $this->actingAs($user)->post('/interno/agenda/'.$agenda->id.'/reagendamentos', $dados)->assertRedirect();
        $novo = Agendamento::where('substitui_agendamento_id', $agenda->id)->sole();
        $this->assertSame('substituido', $agenda->fresh()->status);
        $this->post('/interno/agenda/'.$novo->id.'/cancelamento', ['operacao_id' => (string) Str::uuid(), 'versao' => $novo->versao, 'motivo' => 'Cancelado a pedido'])->assertRedirect();
        $this->assertSame('cancelado', $novo->fresh()->status);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertDatabaseCount('eventos_agendamento', 2);
        $this->get('/interno/agenda')->assertViewHas('pendentes', fn ($lista) => $lista->contains('id', $agenda->solicitacao_id));
    }

    public function test_non_realization_requires_start_time_and_releases_reservation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
        $agenda = Agendamento::factory()->create();
        ReservaAgenda::factory()->create(['solicitacao_id' => $agenda->solicitacao_id, 'agendamento_id' => $agenda->id]);
        $dados = ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'motivo' => 'Não ocorreu'];
        $this->actingAs($this->operador())->post('/interno/agenda/'.$agenda->id.'/nao-realizacao', $dados)->assertSessionHasErrorsIn('nao_realizacao', 'versao');
        $this->travel(2)->days();
        $this->post('/interno/agenda/'.$agenda->id.'/nao-realizacao', $dados)->assertRedirect();
        $this->assertSame('nao_realizado', $agenda->fresh()->status);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertDatabaseCount('eventos_agendamento', 1);
    }

    public function test_filters_respect_local_day_and_reject_unknown_status(): void
    {
        $agenda = Agendamento::factory()->create(['inicio_previsto_em' => '2030-01-03 01:00:00', 'fim_previsto_em' => '2030-01-03 02:00:00']);
        Agendamento::factory()->create(['inicio_previsto_em' => '2030-01-03 04:00:00', 'fim_previsto_em' => '2030-01-03 05:00:00']);
        $this->actingAs($this->operador())->get('/interno/agenda?inicio=2030-01-02&fim=2030-01-02&modalidade=domicilio&situacao=confirmado&responsavel='.$agenda->responsavel_user_id)
            ->assertOk()->assertViewHas('agendamentos', fn ($lista) => $lista->pluck('id')->all() === [$agenda->id]);
        $this->get('/interno/agenda?fim=2030-01-02')->assertOk();
        $this->from('/interno/agenda')->get('/interno/agenda?situacao=invalid')->assertRedirect('/interno/agenda')->assertSessionHasErrors('situacao');
    }

    public function test_csrf_is_required_for_writes(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
        $pedido = $this->pedido();
        $url = '/interno/agenda/acompanhamentos/'.$pedido->id.'/contatos';
        $dados = $this->contato($pedido);
        $this->actingAs($this->operador())->post($url, $dados)->assertStatus(419);
        $this->assertDatabaseCount('contatos_solicitacao', 0);
        $this->post($url, $dados + ['_token' => session()->token()])->assertRedirect();
        $this->assertDatabaseCount('contatos_solicitacao', 1);
    }
}
