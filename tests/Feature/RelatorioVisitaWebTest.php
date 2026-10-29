<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Permissao;
use App\Models\RelatorioVisita;
use App\Models\ReservaAgenda;
use App\Models\User;
use App\Models\VersaoRelatorioVisita;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RelatorioVisitaWebTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function user(string $code = Permissao::REGISTRAR_RELATORIOS_VISITA): User
    {
        $user = User::factory()->funcionario()->create();
        $permission = Permissao::query()->where('codigo', $code)->first() ?? Permissao::factory()->create(['codigo' => $code]);
        $permission->conceder($user, User::factory()->admin()->create(), 'Teste');

        return $user;
    }

    private function data(int $revision, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'revisao' => $revision] + $extra;
    }

    public function test_all_routes_authorize_before_looking_up_records(): void
    {
        $routes = [['get', '/interno/relatorios'], ['get', '/interno/relatorios/999999'],
            ['get', '/interno/relatorios/visitas/999999'], ['post', '/interno/relatorios/visitas/999999'],
            ['post', '/interno/relatorios/999999/rascunho'], ['post', '/interno/relatorios/999999/finalizar'], ['post', '/interno/relatorios/999999/correcao']];
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->funcionario()->create());
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_RELATORIOS_VISITA));
        foreach (array_slice($routes, 2) as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_reader_sees_only_published_versions_and_escaped_content(): void
    {
        $reader = $this->user(Permissao::CONSULTAR_RELATORIOS_VISITA);
        $draft = RelatorioVisita::factory()->create(['texto_rascunho' => 'RASCUNHO_INICIAL']);
        $version = VersaoRelatorioVisita::factory()->create(['texto' => '<script>alert("x")</script>', 'motivo_correcao' => '<b>Motivo</b>']);
        $version->relatorio->forceFill(['estado' => 'em_correcao', 'texto_rascunho' => 'RASCUNHO_SECRETO', 'motivo_correcao' => 'MOTIVO_SECRETO'])->save();
        $this->actingAs($reader)->get('/interno/relatorios')->assertOk()->assertDontSee('Visitas a registrar')
            ->assertViewHas('relatorios', fn ($items): bool => $items->modelKeys() === [$version->relatorio_visita_id]);
        $this->get('/interno/relatorios/'.$draft->id)->assertNotFound();
        $response = $this->get('/interno/relatorios/'.$version->relatorio_visita_id);
        $response->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)
            ->assertSee('&lt;b&gt;Motivo&lt;/b&gt;', false)->assertDontSee('RASCUNHO_SECRETO')->assertDontSee('MOTIVO_SECRETO')
            ->assertDontSee('Salvar rascunho')->assertViewHas('rascunho', null);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/interno/relatorios/'.$version->relatorio_visita_id.'?versao=99999')->assertNotFound();
    }

    public function test_writer_can_complete_visit_and_report_without_agenda_permission(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-02 15:00:00', 'UTC'));
        $writer = $this->user();
        $agenda = Agendamento::factory()->create(['confirmado_em' => '2030-01-01 10:00:00',
            'inicio_previsto_em' => '2030-01-02 12:00:00', 'fim_previsto_em' => '2030-01-02 13:00:00']);
        ReservaAgenda::factory()->for($agenda, 'agendamento')->create(['solicitacao_id' => $agenda->solicitacao_id]);
        $this->actingAs($writer)->get('/interno/agenda')->assertForbidden();
        $this->get('/interno/relatorios')->assertOk()->assertSee('Visitas a registrar');
        $this->get('/interno/relatorios/visitas/'.$agenda->id)->assertOk()->assertSee('Registrar realização da visita');
        $this->assertDatabaseCount('atendimentos', 0);
        $data = ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'inicio_efetivo_em' => '2030-01-02T09:00',
            'fim_efetivo_em' => '2030-01-02T10:00', 'responsavel_user_id' => $agenda->responsavel_user_id,
            'modalidade' => $agenda->modalidade, 'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => '1'];
        $this->post('/interno/relatorios/visitas/'.$agenda->id, $data)->assertRedirect();
        $report = RelatorioVisita::query()->sole();
        $url = '/interno/relatorios/'.$report->id;
        $this->post('/interno/relatorios/visitas/'.$agenda->id, $data)->assertRedirect($url);
        $this->assertDatabaseCount('atendimentos', 1);
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->get($url)->assertOk()->assertSee('Nenhuma versão publicada.')->assertSee('Rascunho');
        $this->post($url.'/rascunho', $this->data(1, ['texto' => 'Primeiro relato', 'estado' => 'finalizado', 'criado_por_user_id' => 999]))->assertRedirect($url);
        $this->assertSame('rascunho', $report->fresh()->estado);
        $final = $this->data(2, ['texto' => 'Texto não salvo']);
        $this->post($url.'/finalizar', $final)->assertRedirect($url);
        $this->post($url.'/finalizar', $final)->assertRedirect($url);
        $this->assertDatabaseCount('versoes_relatorio_visita', 1);
        $this->assertDatabaseHas('versoes_relatorio_visita', ['texto' => 'Primeiro relato', 'finalizado_por_user_id' => $writer->id]);
        $other = $this->user();
        $this->actingAs($other)->post($url.'/correcao', $this->data(3, ['motivo' => 'Complemento']))->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Correção em elaboração')->assertSee('continua vigente');
        $this->post($url.'/rascunho', $this->data(4, ['texto' => 'Novo relato']))->assertRedirect($url);
        $this->post($url.'/finalizar', $this->data(5))->assertRedirect($url);
        $this->get($url.'?versao=1')->assertOk()->assertSee('Primeiro relato')->assertSee('histórica');
        $this->get($url)->assertOk()->assertSee('Novo relato')->assertSee('Complemento');
        $this->assertDatabaseCount('versoes_relatorio_visita', 2);
        $this->assertSame($writer->id, $report->fresh()->criado_por_user_id);
        $this->assertSame($other->id, $report->fresh()->ultimo_editor_user_id);
    }

    public function test_invalid_draft_is_retained_only_in_response_and_conflict_does_not_overwrite(): void
    {
        $report = RelatorioVisita::factory()->create();
        $url = '/interno/relatorios/'.$report->id;
        $text = str_repeat('a', 30001).'<script>restrito</script>';
        $this->actingAs($this->user())->post($url.'/rascunho', $this->data(1, ['texto' => $text]))
            ->assertUnprocessable()->assertSee('&lt;script&gt;restrito&lt;/script&gt;', false)->assertSessionMissing('_old_input');
        $this->assertSame(1, $report->fresh()->revisao);
        $this->post($url.'/rascunho', $this->data(1, ['texto' => 'Vigente']))->assertRedirect();
        $this->post($url.'/rascunho', $this->data(1, ['texto' => 'Texto em conflito']))->assertConflict()
            ->assertSee('Texto em conflito')->assertSee('Copie seu texto')->assertSessionMissing('_old_input');
        $this->assertSame('Vigente', $report->fresh()->texto_rascunho);
        $this->assertDatabaseCount('eventos_atendimento', 1);
    }

    public function test_revocation_prevents_post_from_exposing_submitted_narrative(): void
    {
        $writer = $this->user();
        $report = RelatorioVisita::factory()->create();
        $this->actingAs($writer)->get('/interno/relatorios/'.$report->id)->assertOk();
        Permissao::query()->where('codigo', Permissao::REGISTRAR_RELATORIOS_VISITA)->firstOrFail()
            ->revogar($writer, User::factory()->admin()->create(), 'Revogação');
        $this->post('/interno/relatorios/'.$report->id.'/rascunho', $this->data(1, ['texto' => 'NAO_EXIBIR']))
            ->assertForbidden()->assertDontSee('NAO_EXIBIR')->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('eventos_atendimento', 0);
    }

    public function test_invalid_visit_returns_form_without_creating_records_or_flashing_reason(): void
    {
        $agenda = Agendamento::factory()->create();
        ReservaAgenda::factory()->for($agenda, 'agendamento')->create(['solicitacao_id' => $agenda->solicitacao_id]);
        $this->actingAs($this->user())->post('/interno/relatorios/visitas/'.$agenda->id,
            ['motivo_divergencia' => '<b>JUSTIFICATIVA</b>'])->assertUnprocessable()
            ->assertSee('&lt;b&gt;JUSTIFICATIVA&lt;/b&gt;', false)->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('atendimentos', 0);
        $this->assertDatabaseCount('reservas_agenda', 1);
    }

    public function test_csrf_protects_every_write_route(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $report = RelatorioVisita::factory()->create();
        $this->actingAs($this->user());
        foreach (['/visitas/99999', '/'.$report->id.'/rascunho', '/'.$report->id.'/finalizar', '/'.$report->id.'/correcao'] as $path) {
            $this->post('/interno/relatorios'.$path, $this->data(1))->assertStatus(419);
        }
        $this->post('/interno/relatorios/'.$report->id.'/rascunho', $this->data(1, ['texto' => 'Salvo', '_token' => session()->token()]))->assertRedirect();
        $this->assertDatabaseCount('eventos_atendimento', 1);
    }

    public function test_filters_and_navigation_observe_access_without_exposing_draft(): void
    {
        $report = RelatorioVisita::factory()->create(['texto_rascunho' => 'NAO_LISTAR_TEXTO']);
        RelatorioVisita::factory()->create();
        $this->actingAs($this->user())->get('/interno/relatorios?estado=rascunho&responsavel='.$report->atendimento->responsavel_user_id)
            ->assertOk()->assertDontSee('NAO_LISTAR_TEXTO')->assertViewHas('relatorios', fn ($items): bool => $items->modelKeys() === [$report->id]);
        $this->from('/interno/relatorios')->get('/interno/relatorios?estado=aprovado')->assertRedirect('/interno/relatorios')->assertSessionHasErrors('estado');
        $this->get('/dashboard')->assertSee('Relatórios de visita');
        $this->actingAs(User::factory()->funcionario()->create())->get('/dashboard')->assertDontSee('Relatórios de visita');
    }

    public function test_empty_finalization_and_blank_correction_render_errors_without_publication(): void
    {
        $report = RelatorioVisita::factory()->create();
        $url = '/interno/relatorios/'.$report->id;
        $this->actingAs($this->user())->post($url.'/finalizar', $this->data(1))->assertUnprocessable()
            ->assertSee('Preencha o relatório antes de finalizar.')->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('versoes_relatorio_visita', 0);
        $this->post($url.'/rascunho', $this->data(1, ['texto' => 'Relato']))->assertRedirect();
        $this->post($url.'/finalizar', $this->data(2))->assertRedirect();
        $this->post($url.'/correcao', $this->data(3, ['motivo' => '   ']))->assertUnprocessable()->assertSee('Iniciar correção');
        $this->assertSame('finalizado', $report->fresh()->estado);
        $this->assertSame(3, $report->fresh()->revisao);
    }

    public function test_conflicting_draft_after_another_author_finalizes_remains_copyable(): void
    {
        $writer = $this->user();
        $report = RelatorioVisita::factory()->create(['texto_rascunho' => 'Já salvo']);
        $report->finalizar(User::factory()->admin()->create(), $this->data(1));
        $this->actingAs($writer)->post('/interno/relatorios/'.$report->id.'/rascunho', $this->data(1, ['texto' => 'Trabalho não salvo']))
            ->assertConflict()->assertSee('Texto enviado e não salvo')->assertSee('Trabalho não salvo')
            ->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('versoes_relatorio_visita', 1);
    }

    public function test_cancelled_visit_cannot_be_opened_or_recorded(): void
    {
        $agenda = Agendamento::factory()->create(['status' => 'cancelado']);
        $this->actingAs($this->user())->get('/interno/relatorios/visitas/'.$agenda->id)->assertConflict();
        $this->post('/interno/relatorios/visitas/'.$agenda->id, ['operacao_id' => (string) Str::uuid(), 'versao' => 1,
            'inicio_efetivo_em' => '2030-01-01T09:00', 'fim_efetivo_em' => '2030-01-01T10:00',
            'responsavel_user_id' => $agenda->responsavel_user_id, 'modalidade' => $agenda->modalidade,
            'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => '1'])->assertConflict();
        $this->assertDatabaseCount('atendimentos', 0);
    }
}
