<?php

namespace Tests\Feature;

use App\Models\Permissao;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FuncionarioCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_only_admin_or_explicit_grantee_can_create_employees_and_revocation_applies(): void
    {
        $this->get('/interno/funcionarios/novo')->assertRedirect('/login');
        $this->post('/interno/funcionarios')->assertRedirect('/login');
        $employee = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($employee)->get('/interno/funcionarios/novo')->assertForbidden();
        $this->post('/interno/funcionarios', [])->assertForbidden();
        $permission = Permissao::factory()->create(['codigo' => Permissao::CRIAR_FUNCIONARIOS]);
        $permission->conceder($employee, $admin, 'Cadastro da equipe');
        $this->get('/interno/funcionarios/novo')->assertOk();
        $this->post('/interno/funcionarios', ['name' => 'Nova Pessoa', 'email' => 'nova@example.test', 'role' => 'admin', 'password' => 'known-password', 'criado_por_user_id' => $admin->id, 'permissoes' => ['gerenciar-agenda']])->assertRedirect('/interno/funcionarios/novo');
        $created = User::where('email', 'nova@example.test')->sole();
        $this->assertSame('funcionario', $created->role);
        $this->assertSame($employee->id, $created->criado_por_user_id);
        $this->assertFalse(Hash::check('known-password', $created->password));
        $this->assertFalse($created->podeCriarFuncionarios());
        $this->assertFalse($created->podeAtenderSolicitacoes());
        $this->assertFalse($created->podeGerenciarAgenda());
        $permission->revogar($employee, $admin, 'Revogada');
        $this->post('/interno/funcionarios', ['name' => 'Outra', 'email' => 'outra@example.test'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'outra@example.test']);
    }

    public function test_admin_creation_and_password_setup_use_existing_email_reset_flow(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/interno/funcionarios', ['name' => 'Pessoa Interna', 'email' => 'interna@example.test'])->assertRedirect();
        $created = User::where('email', 'interna@example.test')->sole();
        $this->post('/logout');
        $this->post('/forgot-password', ['email' => $created->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($created, ResetPassword::class, function ($notification) use ($created): bool {
            $this->post('/reset-password', ['email' => $created->email, 'token' => $notification->token, 'password' => 'safe-password', 'password_confirmation' => 'safe-password'])->assertRedirect('/login');
            $this->assertTrue(Hash::check('safe-password', $created->fresh()->password));

            return true;
        });
        $this->assertDatabaseCount('permissao_user', 0);
    }

    public function test_invalid_and_duplicate_input_do_not_create_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->from('/interno/funcionarios/novo')->post('/interno/funcionarios', [])->assertSessionHasErrors(['name', 'email']);
        $this->post('/interno/funcionarios', ['name' => 'Outra', 'email' => $admin->email])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }
}
