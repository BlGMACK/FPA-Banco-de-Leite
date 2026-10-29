<?php

namespace Tests\Feature;

use App\Models\Permissao;
use App\Models\User;
use Database\Seeders\PermissaoSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissaoWebTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function permissao(string $codigo): Permissao
    {
        return Permissao::query()->where('codigo', $codigo)->sole();
    }

    public function test_only_admin_reaches_routes_and_authorization_precedes_lookup(): void
    {
        $this->seed(PermissaoSeeder::class);
        $routes = [['get', '/interno/permissoes'], ['get', '/interno/permissoes/999999'],
            ['post', '/interno/permissoes/999999/concessoes'], ['post', '/interno/permissoes/999999/revogacoes']];
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
        $creator = User::factory()->funcionario()->create();
        $this->permissao(Permissao::CRIAR_FUNCIONARIOS)->conceder($creator, User::factory()->admin()->create(), 'Cadastro da equipe');
        $this->actingAs($creator);
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->get('/dashboard')->assertDontSee('Permissões');
        $this->actingAs(User::factory()->admin()->create())->get('/interno/permissoes/999999')->assertNotFound();
        $this->get('/dashboard')->assertSee('Permissões');
    }

    public function test_admin_grants_and_revokes_with_reason_and_module_access_follows(): void
    {
        $this->seed(PermissaoSeeder::class);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create(['name' => 'Pessoa da Agenda']);
        $agenda = $this->permissao(Permissao::GERENCIAR_AGENDA);
        $url = '/interno/permissoes/'.$employee->id;

        $this->actingAs($admin)->get('/interno/permissoes')->assertOk()->assertSee('Pessoa da Agenda')
            ->assertDontSee($admin->email)->assertSee('Nenhuma');
        $this->get($url)->assertOk()->assertSee('Gestão da agenda')->assertSee('Não concedida')->assertSee('Nenhuma alteração registrada.');
        $this->post($url.'/concessoes', ['permissao_id' => $agenda->id, 'motivo' => 'Responsável pelas visitas'])
            ->assertRedirect($url)->assertSessionHas('status', 'Permissão “Gestão da agenda” concedida.');
        $this->assertTrue($employee->fresh()->podeGerenciarAgenda());
        $this->assertDatabaseHas('eventos_permissao', ['user_id' => $employee->id, 'permissao_id' => $agenda->id,
            'autor_user_id' => $admin->id, 'acao' => 'concessao', 'motivo' => 'Responsável pelas visitas']);
        $this->get($url)->assertSee('Ativa')->assertSee('Revogar')->assertSee('Responsável pelas visitas');
        $this->get('/interno/permissoes')->assertSee('Gestão da agenda');

        $this->actingAs($employee)->get('/interno/agenda')->assertOk();
        $this->actingAs($admin)->post($url.'/revogacoes', ['permissao_id' => $agenda->id, 'motivo' => 'Mudou de setor'])
            ->assertRedirect($url)->assertSessionHas('status', 'Permissão “Gestão da agenda” revogada.');
        $this->actingAs($employee)->get('/interno/agenda')->assertForbidden();
        $this->assertDatabaseCount('eventos_permissao', 2);
    }

    public function test_invalid_input_repeated_request_and_admin_target_do_not_create_events(): void
    {
        $this->seed(PermissaoSeeder::class);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $reports = $this->permissao(Permissao::CONSULTAR_RELATORIOS_VISITA);
        $url = '/interno/permissoes/'.$employee->id;
        $this->actingAs($admin);

        $this->from($url)->post($url.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => '   '])
            ->assertRedirect($url)->assertSessionHasErrors('motivo');
        $this->from($url)->post($url.'/concessoes', ['permissao_id' => 999999, 'motivo' => 'Teste'])
            ->assertRedirect($url)->assertSessionHasErrors('permissao_id');
        $this->from($url)->post($url.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('motivo');
        $this->assertDatabaseCount('eventos_permissao', 0);

        $this->post($url.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => 'Leitura']);
        $this->post($url.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => 'Leitura repetida'])
            ->assertSessionHas('status', 'Nenhuma alteração: a permissão já estava concedida.');
        $this->assertDatabaseCount('eventos_permissao', 1);

        $this->post('/interno/permissoes/'.$admin->id.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => 'Admin'])->assertNotFound();
        $this->post('/interno/permissoes/'.$employee->id.'/concessoes', ['permissao_id' => $reports->id, 'motivo' => 'x', 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('permissao_user', ['user_id' => $admin->id]);
    }

    public function test_history_is_escaped_and_writes_require_csrf(): void
    {
        $this->seed(PermissaoSeeder::class);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $agenda = $this->permissao(Permissao::GERENCIAR_AGENDA);
        $agenda->conceder($employee, $admin, '<script>alert("x")</script>');
        $url = '/interno/permissoes/'.$employee->id;
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);

        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->post($url.'/revogacoes', ['permissao_id' => $agenda->id, 'motivo' => 'Sem token'])->assertStatus(419);
        $this->assertTrue($employee->fresh()->podeGerenciarAgenda());
    }
}
