<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\TipoExame;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExameWebTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function user(string $code = Permissao::REGISTRAR_EXAMES): User
    {
        $user = User::factory()->funcionario()->create();
        $permission = Permissao::query()->where('codigo', $code)->first() ?? Permissao::factory()->create(['codigo' => $code]);
        $permission->conceder($user, User::factory()->admin()->create(), 'Teste');

        return $user;
    }

    private function data(ItemAvaliacao $item, string $action, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $item->fresh()->versao, 'acao' => $action, ...$extra];
    }

    public function test_routes_authorize_before_looking_up_records(): void
    {
        $routes = [['get', '/interno/exames'], ['get', '/interno/exames/999999'], ['post', '/interno/exames/999999/itens'], ['post', '/interno/exames/itens/999999/eventos']];
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES));
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES));
        $this->post('/interno/exames/999999/itens')->assertForbidden();
        $this->post('/interno/exames/itens/999999/eventos')->assertForbidden();
        $this->get('/interno/exames/999999')->assertNotFound();
    }

    public function test_evaluation_without_exam_stage_is_not_found(): void
    {
        $evaluation = Avaliacao::factory()->create();

        $this->actingAs($this->user())->get('/interno/exames/'.$evaluation->id)->assertNotFound();
        $this->post('/interno/exames/'.$evaluation->id.'/itens', ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'X'])->assertNotFound();
    }

    public function test_registrar_runs_cycle_through_forms(): void
    {
        $item = ItemAvaliacao::factory()->create(['tipo_exame_id' => TipoExame::factory()->state(['nome' => 'Exame de sangue'])]);
        $this->actingAs($this->user());
        $url = '/interno/exames/'.$item->avaliacao_id;

        $this->get('/interno/exames')->assertOk()->assertSee('Abrir exames da avaliação #'.$item->avaliacao_id)->assertSee('Pendentes');
        $response = $this->get($url)->assertOk()->assertSee('Exame de sangue')->assertSee('Não solicitado')->assertSee('Solicitar')
            ->assertSee('Exigências pendentes')->assertSee('Incluir outro exame');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->post('/interno/exames/itens/'.$item->id.'/eventos', $this->data($item, 'solicitacao'))
            ->assertRedirect($url)->assertSessionHas('status', 'Registrado: solicitação de “Exame de sangue”. O histórico foi preservado.');
        $this->get($url)->assertSee('Registrar recebimento')->assertSee('Cancelar solicitação');
        $this->post('/interno/exames/itens/'.$item->id.'/eventos', $this->data($item, 'recebimento', ['realizado_em' => now('America/Sao_Paulo')->toDateString()]))->assertRedirect($url);
        $this->get($url)->assertSee('Aceitar')->assertSee('Pedir complementação')->assertSee('Válido até');
        $this->post('/interno/exames/itens/'.$item->id.'/eventos', $this->data($item, 'aceite'))->assertRedirect($url);
        $this->get($url)->assertSee('Exigências cumpridas')->assertSee('Aceito');
    }

    public function test_validation_error_keeps_input_in_item_form(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'recebido']);
        $data = $this->data($item, 'complementacao', ['motivo' => '']);

        $this->actingAs($this->user())->post('/interno/exames/itens/'.$item->id.'/eventos', $data)
            ->assertStatus(422)->assertSee('Informe o motivo.')->assertSee('value="'.$data['operacao_id'].'"', false);
        $this->assertDatabaseCount('eventos_item_avaliacao', 0);
    }

    public function test_stale_form_shows_conflict_and_unsaved_reason(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'recebido']);
        $user = $this->user();
        $stale = $this->data($item, 'complementacao', ['motivo' => 'Texto que não entrou']);
        $item->registrar($user, $this->data($item, 'aceite'));

        $this->actingAs($user)->post('/interno/exames/itens/'.$item->id.'/eventos', $stale)
            ->assertStatus(409)->assertSee('O item mudou. Recarregue antes de continuar.')->assertSee('Recarregar a situação atual')
            ->assertSee('Motivo enviado e não registrado')->assertSee('Texto que não entrou');
        $this->assertSame('aceito', $item->fresh()->estado);
    }

    public function test_other_exam_form(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $this->actingAs($this->user());

        $this->post('/interno/exames/'.$item->avaliacao_id.'/itens', ['operacao_id' => (string) Str::uuid(), 'nome_outro' => ''])
            ->assertStatus(422)->assertSee('Informe o nome do exame.');
        $this->post('/interno/exames/'.$item->avaliacao_id.'/itens', ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'Ultrassom', 'detalhes_outro' => '<b>x</b>'])
            ->assertRedirect('/interno/exames/'.$item->avaliacao_id);
        $this->get('/interno/exames/'.$item->avaliacao_id)->assertSee('Ultrassom')->assertSee('&lt;b&gt;x&lt;/b&gt;', false)->assertDontSee('<b>x</b>', false);
    }

    public function test_reader_sees_items_and_history_without_forms(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'recebido']);
        $item->registrar(User::factory()->admin()->create(), $this->data($item, 'complementacao', ['motivo' => '<script>alert(1)</script>']));

        $response = $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES))->get('/interno/exames/'.$item->avaliacao_id)->assertOk()
            ->assertSee('Precisa complementar')->assertSee('Pedido de complementação')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false)->assertDontSee('Incluir outro exame')->assertDontSee('Abrir a avaliação');
        $this->assertStringNotContainsString('/eventos"', $response->getContent());
    }

    public function test_expired_item_shows_badge_and_actions(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'aceito', 'realizado_em' => '2026-08-01', 'valido_ate' => '2026-08-31', 'prorrogacoes' => 2]);

        $this->actingAs($this->user())->get('/interno/exames/'.$item->avaliacao_id)
            ->assertSee('Vencido')->assertSee('Pedir novo exame')->assertDontSee('Motivo para prorrogar')->assertSee('Prorrogações: 2 de 2')
            ->assertSee('Exigências pendentes');
    }

    public function test_evaluation_page_links_to_exams_only_with_permission(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $url = '/interno/avaliacoes/'.$item->avaliacao_id;

        $this->actingAs($this->user(Permissao::AVALIAR_DOADORAS))->get($url)->assertSee('Exames e documentos');
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get($url)->assertDontSee('Exames e documentos');
    }

    public function test_navigation_shows_exams_only_with_permission(): void
    {
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get('/interno/avaliacoes')->assertDontSee('>Exames<', false);
        $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES))->get('/interno/exames')->assertSee('>Exames<', false);
    }

    public function test_csrf_protects_exam_routes(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $item = ItemAvaliacao::factory()->create();
        $this->actingAs($this->user());

        $this->post('/interno/exames/itens/'.$item->id.'/eventos', $this->data($item, 'solicitacao'))->assertStatus(419);
        $this->post('/interno/exames/'.$item->avaliacao_id.'/itens', ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'X'])->assertStatus(419);
        $this->assertDatabaseCount('eventos_item_avaliacao', 0);
    }
}
