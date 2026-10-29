<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Permissao;
use App\Models\ReservaAgenda;
use App\Models\User;
use Database\Seeders\PermissaoSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DesativacaoFuncionarioTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function funcionarioComAgenda(User $admin): User
    {
        $employee = User::factory()->funcionario()->create(['email' => 'equipe@example.test', 'password' => 'senha-segura-1']);
        Permissao::query()->where('codigo', Permissao::GERENCIAR_AGENDA)->sole()->conceder($employee, $admin, 'Agenda');

        return $employee;
    }

    public function test_deactivation_blocks_access_suspends_permissions_and_reactivation_restores_them(): void
    {
        $this->seed(PermissaoSeeder::class);
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $employee = $this->funcionarioComAgenda($admin);
        $url = '/interno/permissoes/'.$employee->id;

        $this->actingAs($employee)->get('/interno/agenda')->assertOk();
        $this->actingAs($admin)->post($url.'/desativacao', ['motivo' => 'Saiu da equipe'])
            ->assertRedirect($url)->assertSessionHas('status', 'Conta desativada. As permissões ficam suspensas até uma eventual reativação.');
        $this->assertNotNull($employee->fresh()->desativado_em);
        $this->assertSame($admin->id, $employee->fresh()->desativado_por_user_id);
        $this->assertDatabaseHas('permissao_user', ['user_id' => $employee->id, 'revogada_em' => null]);
        $this->assertFalse($employee->fresh()->podeGerenciarAgenda());
        $this->get($url)->assertSee('Desativada')->assertSee('Suspensa')->assertSee('Saiu da equipe')->assertSee('Reativar conta');
        $this->get('/interno/permissoes')->assertSee('Desativada');

        $this->actingAs($employee)->get('/interno/agenda')->assertForbidden();
        $this->assertGuest();
        $this->post('/login', ['email' => 'equipe@example.test', 'password' => 'senha-segura-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/forgot-password', ['email' => 'equipe@example.test']);
        Notification::assertNotSentTo($employee, ResetPassword::class);

        $this->actingAs($admin)->post($url.'/desativacao', ['motivo' => 'Repetido'])
            ->assertSessionHas('status', 'Nenhuma alteração: a conta já estava desativada.');
        $this->post($url.'/reativacao', ['motivo' => 'Retornou ao setor'])
            ->assertRedirect($url)->assertSessionHas('status', 'Conta reativada com as permissões que já possuía.');
        $this->assertNull($employee->fresh()->desativado_em);
        $this->assertTrue($employee->fresh()->podeGerenciarAgenda());
        $this->assertDatabaseCount('eventos_conta', 2);
        $this->assertDatabaseHas('eventos_conta', ['user_id' => $employee->id, 'autor_user_id' => $admin->id, 'acao' => 'reativacao', 'motivo' => 'Retornou ao setor']);
        $this->post('/logout');
        $this->post('/login', ['email' => 'equipe@example.test', 'password' => 'senha-segura-1'])->assertRedirect();
        $this->assertAuthenticatedAs($employee);
    }

    public function test_future_visit_blocks_deactivation_while_past_visit_does_not(): void
    {
        $this->seed(PermissaoSeeder::class);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $future = Agendamento::factory()->create(['responsavel_user_id' => $employee->id]);
        ReservaAgenda::factory()->for($future, 'agendamento')->create(['solicitacao_id' => $future->solicitacao_id]);
        $url = '/interno/permissoes/'.$employee->id;

        $this->actingAs($admin)->get($url)->assertSee('Responsável por visitas futuras')
            ->assertSee($future->solicitacao->interessada->nome_completo);
        $this->from($url)->post($url.'/desativacao', ['motivo' => 'Saiu'])->assertRedirect($url)->assertSessionHasErrorsIn('conta', 'conta');
        $this->assertNull($employee->fresh()->desativado_em);
        $this->assertDatabaseCount('eventos_conta', 0);

        $future->forceFill(['inicio_previsto_em' => now()->subDay(), 'fim_previsto_em' => now()->subDay()->addHour()])->save();
        $this->post($url.'/desativacao', ['motivo' => 'Saiu'])->assertSessionHasNoErrors();
        $this->assertNotNull($employee->fresh()->desativado_em);
        $this->actingAs(User::factory()->funcionario()->create());
        $writer = User::query()->latest('id')->first();
        Permissao::query()->where('codigo', Permissao::REGISTRAR_RELATORIOS_VISITA)->sole()->conceder($writer, $admin, 'Relatórios');
        $this->get('/interno/relatorios/visitas/'.$future->id)->assertOk()->assertSee($employee->name);
    }

    public function test_only_admin_manages_employee_accounts_and_reason_is_required(): void
    {
        $this->seed(PermissaoSeeder::class);
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $creator = User::factory()->funcionario()->create();
        Permissao::query()->where('codigo', Permissao::CRIAR_FUNCIONARIOS)->sole()->conceder($creator, $admin, 'Cadastro');
        $url = '/interno/permissoes/'.$employee->id;

        $this->post($url.'/desativacao', ['motivo' => 'x'])->assertRedirect(route('login'));
        $this->actingAs($creator)->post($url.'/desativacao', ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($admin)->post('/interno/permissoes/'.$admin->id.'/desativacao', ['motivo' => 'x'])->assertNotFound();
        $this->from($url)->post($url.'/desativacao', ['motivo' => '   '])->assertSessionHasErrorsIn('conta', 'motivo');
        $this->from($url)->post($url.'/reativacao', ['motivo' => str_repeat('a', 2001)])->assertSessionHasErrorsIn('conta', 'motivo');
        $this->assertNull($employee->fresh()->desativado_em);
        $this->assertNull($admin->fresh()->desativado_em);
        $this->assertDatabaseCount('eventos_conta', 0);
    }
}
