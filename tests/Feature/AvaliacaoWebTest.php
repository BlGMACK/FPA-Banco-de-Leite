<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvaliacaoWebTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function user(string $code = Permissao::AVALIAR_DOADORAS): User
    {
        $user = User::factory()->funcionario()->create();
        $permission = Permissao::query()->where('codigo', $code)->first() ?? Permissao::factory()->create(['codigo' => $code]);
        $permission->conceder($user, User::factory()->admin()->create(), 'Teste');

        return $user;
    }

    private function data(int $version, string $result, ?string $reason = null): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $version, 'resultado' => $result, 'motivo' => $reason, 'confirmacao_definitiva' => '1'];
    }

    public function test_routes_authorize_before_looking_up_records(): void
    {
        $routes = [['get', '/interno/avaliacoes'], ['get', '/interno/avaliacoes/999999'], ['post', '/interno/avaliacoes/999999/decisoes']];
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        $this->actingAs($this->user(Permissao::REGISTRAR_RELATORIOS_VISITA));
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES));
        $this->post('/interno/avaliacoes/999999/decisoes')->assertForbidden();
        $this->get('/interno/avaliacoes/999999')->assertNotFound();
    }

    public function test_reader_sees_escaped_history_without_decision_form_or_report_link(): void
    {
        $decision = DecisaoAvaliacao::factory()->create(['motivo' => '<script>alert("x")</script>']);
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES));

        $this->get('/interno/avaliacoes')->assertOk()->assertSee('Em aguardo')
            ->assertViewHas('avaliacoes', fn ($items): bool => $items->modelKeys() === [$decision->avaliacao_id]);
        $response = $this->get('/interno/avaliacoes/'.$decision->avaliacao_id);
        $response->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)
            ->assertDontSee('Registrar decisão')->assertDontSee('Ler o relatório');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_evaluator_releases_for_exams_and_report_page_no_longer_offers_correction(): void
    {
        $evaluation = Avaliacao::factory()->create();
        $evaluator = $this->user();

        $this->actingAs($evaluator)->get('/interno/avaliacoes/'.$evaluation->id)->assertOk()
            ->assertSee('Iniciar análise')->assertDontSee('Registrar decisão')->assertSee('Ler o relatório');
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise')->assertRedirect(route('interno.avaliacoes.show', $evaluation));
        $this->get('/interno/avaliacoes/'.$evaluation->id)->assertSee('Registrar decisão')->assertSee('Análise reservada para você')
            ->assertSee('name="confirmacao_definitiva"', false);
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', $this->data(1, 'liberada_exames'))
            ->assertRedirect(route('interno.avaliacoes.show', $evaluation))->assertSessionHas('status', 'Decisão registrada. O histórico foi preservado.');

        $this->assertDatabaseHas('decisoes_avaliacao', ['avaliacao_id' => $evaluation->id, 'resultado' => 'liberada_exames', 'autor_user_id' => $evaluator->id]);
        $this->get('/interno/avaliacoes/'.$evaluation->id)->assertSee('Liberada para exames')->assertDontSee('Registrar decisão');
        $this->actingAs(User::factory()->admin()->create())->get('/interno/relatorios/'.$evaluation->atendimento->relatorio->id)
            ->assertOk()->assertSee('O relatório não pode mais ser corrigido.')->assertDontSee('Abrir correção');
    }

    public function test_missing_reason_returns_form_with_message_and_kept_input(): void
    {
        $evaluation = Avaliacao::factory()->create();

        $this->actingAs($this->user())->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', $this->data(1, 'nao_apta'))
            ->assertStatus(422)->assertSee('Informe o motivo para deixar em aguardo ou considerar não apta.')
            ->assertSee('id="resultado-nao_apta" value="nao_apta" required checked', false);
        $this->assertDatabaseCount('decisoes_avaliacao', 0);
    }

    public function test_stale_form_shows_conflict_without_recording(): void
    {
        $evaluation = Avaliacao::factory()->create();
        $evaluator = $this->user();
        $evaluation->decidir($evaluator, $this->data(1, 'em_aguardo', 'Aguardando reagendamento.'));

        $this->actingAs($evaluator)->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', $this->data(1, 'nao_apta', 'Outro motivo'))
            ->assertStatus(409)->assertSee('A avaliação mudou. Recarregue antes de continuar.')->assertSee('Recarregar a situação atual');
        $this->assertDatabaseCount('decisoes_avaliacao', 1);
    }

    public function test_reservation_hides_form_from_others_and_release_frees_it(): void
    {
        $evaluation = Avaliacao::factory()->create();
        $owner = $this->user();
        $other = $this->user();

        $this->actingAs($owner)->post('/interno/avaliacoes/'.$evaluation->id.'/analise')->assertRedirect();
        $this->actingAs($other)->get('/interno/avaliacoes/'.$evaluation->id)->assertOk()
            ->assertSee('A avaliação está em análise por '.$owner->name)->assertDontSee('Iniciar análise')->assertDontSee('Registrar decisão');
        $this->get('/interno/avaliacoes')->assertSee('Em análise por '.$owner->name);
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise')->assertStatus(409)->assertSee('A avaliação está em análise por');
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', $this->data(1, 'nao_apta', 'Motivo concorrente'))
            ->assertStatus(409)->assertSee('Motivo enviado e não registrado')->assertSee('Motivo concorrente');

        $this->actingAs($owner)->post('/interno/avaliacoes/'.$evaluation->id.'/analise/encerrar')
            ->assertSessionHas('status', 'Análise encerrada sem decisão. A avaliação ficou livre para outro profissional.');
        $this->actingAs($other)->get('/interno/avaliacoes/'.$evaluation->id)->assertSee('Iniciar análise');
        $this->assertDatabaseCount('decisoes_avaliacao', 0);
    }

    public function test_missing_confirmation_returns_form_with_message(): void
    {
        $evaluation = Avaliacao::factory()->create();
        $evaluator = $this->user();
        $evaluation->iniciarAnalise($evaluator);

        $this->actingAs($evaluator)->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', array_replace($this->data(1, 'liberada_exames'), ['confirmacao_definitiva' => null]))
            ->assertStatus(422)->assertSee('Confirme que a decisão é definitiva e impede novas correções do relatório.')
            ->assertSee('Registrar decisão');
        $this->assertDatabaseCount('decisoes_avaliacao', 0);
    }

    public function test_reader_cannot_reserve_analysis(): void
    {
        $evaluation = Avaliacao::factory()->create();

        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/avaliacoes/'.$evaluation->id)->assertDontSee('Iniciar análise');
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise')->assertForbidden();
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise/encerrar')->assertForbidden();
        $this->assertNull($evaluation->fresh()->analise_por_user_id);
    }

    public function test_csrf_protects_decision_route(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $evaluation = Avaliacao::factory()->create();
        $this->actingAs($this->user());

        $this->post('/interno/avaliacoes/'.$evaluation->id.'/decisoes', $this->data(1, 'liberada_exames'))->assertStatus(419);
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise')->assertStatus(419);
        $this->post('/interno/avaliacoes/'.$evaluation->id.'/analise/encerrar')->assertStatus(419);
        $this->assertDatabaseCount('decisoes_avaliacao', 0);
        $this->assertNull($evaluation->fresh()->analise_por_user_id);
    }

    public function test_navigation_shows_evaluations_only_with_permission(): void
    {
        $this->actingAs($this->user(Permissao::REGISTRAR_RELATORIOS_VISITA))->get('/interno/relatorios')->assertDontSee('Avaliações');
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/avaliacoes')->assertSee('Avaliações');
    }
}
