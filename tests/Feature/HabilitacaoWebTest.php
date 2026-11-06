<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\Doadora;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HabilitacaoWebTest extends TestCase
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

    private function pronta(): Avaliacao
    {
        return ItemAvaliacao::factory()->create(['estado' => 'aceito', 'realizado_em' => now()->subDays(5)->toDateString(),
            'valido_ate' => now()->addDays(25)->toDateString()])->avaliacao;
    }

    private function decisao(Avaliacao $avaliacao, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $avaliacao->fresh()->versao, 'resultado' => 'habilitada',
            'possui_plano_saude' => '1', 'confirmacao_definitiva' => '1', ...$extra];
    }

    public function test_routes_authorize_before_looking_up_records(): void
    {
        $routes = [['get', '/interno/doadoras'], ['get', '/interno/doadoras/999999'], ['post', '/interno/doadoras/999999/eventos'],
            ['post', '/interno/avaliacoes/999999/habilitacao']];
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES));
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES));
        $this->post('/interno/doadoras/999999/eventos')->assertForbidden();
        $this->post('/interno/avaliacoes/999999/habilitacao')->assertForbidden();
        $this->get('/interno/doadoras/999999')->assertNotFound();
    }

    public function test_decision_form_appears_only_when_requirements_are_met(): void
    {
        $pendente = ItemAvaliacao::factory()->create(['estado' => 'solicitado'])->avaliacao;
        $this->actingAs($this->user())->get('/interno/avaliacoes/'.$pendente->id)
            ->assertSee('A decisão de habilitação fica disponível quando')->assertDontSee('Registrar decisão de habilitação');

        $this->get('/interno/avaliacoes/'.$this->pronta()->id)->assertOk()->assertSee('Registrar decisão de habilitação');
    }

    public function test_reader_sees_no_decision_form(): void
    {
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/avaliacoes/'.$this->pronta()->id)
            ->assertOk()->assertDontSee('Registrar decisão de habilitação');
    }

    public function test_decision_through_form_creates_donor_page(): void
    {
        $avaliacao = $this->pronta();
        $this->actingAs($this->user());

        $this->post('/interno/avaliacoes/'.$avaliacao->id.'/habilitacao', $this->decisao($avaliacao))
            ->assertRedirect(route('interno.avaliacoes.show', $avaliacao));
        $doadora = Doadora::query()->sole();

        $this->get('/interno/avaliacoes/'.$avaliacao->id)->assertSee('Plano de saúde: possui.')->assertSee(route('interno.doadoras.show', $doadora));
        $this->get('/interno/doadoras')->assertSee($doadora->interessada->nome_completo)->assertSee('Ativa');
        $this->get('/interno/doadoras/'.$doadora->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Habilitada para doar')->assertSee('Suspender');
        $this->get('/interno/exames/'.$avaliacao->id)->assertSee('Os exames ficam disponíveis só para consulta.')->assertDontSee('Pedir complementação');
    }

    public function test_validation_error_keeps_input_and_conflict_shows_message(): void
    {
        $avaliacao = $this->pronta();
        $this->actingAs($this->user());

        $this->post('/interno/avaliacoes/'.$avaliacao->id.'/habilitacao', $this->decisao($avaliacao, ['resultado' => 'nao_habilitada', 'motivo' => '']))
            ->assertStatus(422)->assertSee('Informe o motivo para não habilitar.')->assertSee('id="habilitacao-nao_habilitada" value="nao_habilitada" required checked', false);
        $this->post('/interno/avaliacoes/'.$avaliacao->id.'/habilitacao', $this->decisao($avaliacao, ['versao' => 99, 'motivo' => 'Texto enviado.']))
            ->assertStatus(409)->assertSee('A avaliação mudou. Recarregue antes de continuar.')->assertSee('Motivo enviado e não registrado');
        $this->assertDatabaseCount('doadoras', 0);
    }

    public function test_donor_actions_through_forms(): void
    {
        $doadora = Doadora::factory()->temporaria(now('America/Sao_Paulo')->subDay()->toDateString())->create();
        $this->actingAs($this->user());
        $this->get('/interno/doadoras/'.$doadora->id)->assertSee('Prazo vencido')->assertSee('Renovar prazo')->assertSee('Converter em definitiva');

        $this->post('/interno/doadoras/'.$doadora->id.'/eventos', ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'acao' => 'encerramento', 'motivo' => 'Fim.'])
            ->assertStatus(422)->assertSee('Confirme que a habilitação termina');
        $this->post('/interno/doadoras/'.$doadora->id.'/eventos', ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'acao' => 'suspensao', 'motivo' => 'Pausa.'])
            ->assertRedirect(route('interno.doadoras.show', $doadora));
        $this->get('/interno/doadoras/'.$doadora->id)->assertSee('Registrado: suspensão.')->assertSee('Retomar')->assertSee('Pausa.');
        $this->post('/interno/doadoras/'.$doadora->id.'/eventos', ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'acao' => 'retomada', 'motivo' => 'Volta.'])
            ->assertStatus(409)->assertSee('A habilitação mudou.');
    }

    public function test_reader_sees_donor_without_actions(): void
    {
        $doadora = Doadora::factory()->create();

        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/doadoras/'.$doadora->id)
            ->assertOk()->assertSee($doadora->interessada->nome_completo)->assertDontSee('Suspender');
    }

    public function test_names_are_escaped(): void
    {
        $doadora = Doadora::factory()->create();
        $doadora->interessada->forceFill(['nome_completo' => '<script>alert(1)</script>'])->save();

        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES));
        $this->get('/interno/doadoras')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/interno/doadoras/'.$doadora->id)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_navigation_shows_donors_only_with_permission(): void
    {
        $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES))->get('/interno/exames')->assertDontSee('>Doadoras<', false);
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/avaliacoes')->assertSee('>Doadoras<', false);
    }

    public function test_csrf_protects_enabling_routes(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $avaliacao = $this->pronta();
        $doadora = Doadora::factory()->create();
        $this->actingAs($this->user());

        $this->post('/interno/avaliacoes/'.$avaliacao->id.'/habilitacao', $this->decisao($avaliacao))->assertStatus(419);
        $this->post('/interno/doadoras/'.$doadora->id.'/eventos', ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'acao' => 'suspensao', 'motivo' => 'X'])
            ->assertStatus(419);
        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
        $this->assertSame('ativa', $doadora->fresh()->estado);
    }
}
