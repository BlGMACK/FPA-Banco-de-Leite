<?php

namespace Tests\Feature;

use App\Models\ConcessaoPermissao;
use App\Models\EventoPermissao;
use App\Models\Permissao;
use App\Models\User;
use Database\Seeders\PermissaoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    #[DataProvider('accessMatrix')]
    public function test_administrative_gates_respect_current_role_and_grant(string $role, bool $granted, bool $revoked, bool $canAttend, bool $canManage): void
    {
        $user = User::factory()->create(['role' => $role]);
        if ($granted) {
            ConcessaoPermissao::factory()->for($user, 'usuario')->create([
                'revogada_em' => $revoked ? now() : null,
            ]);
        }

        $this->assertSame($canAttend, Gate::forUser($user)->allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertSame($canManage, Gate::forUser($user)->allows('gerenciar-permissoes'));
        $this->assertFalse(Gate::forUser($user)->allows('aprovar-doacao'));
    }

    public static function accessMatrix(): array
    {
        return [
            'admin' => ['admin', false, false, true, true],
            'employee without grant' => ['funcionario', false, false, false, false],
            'employee with active grant' => ['funcionario', true, false, true, false],
            'employee revoked' => ['funcionario', true, true, false, false],
        ];
    }

    public function test_guest_cannot_use_administrative_gates(): void
    {
        $this->assertFalse(Gate::allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertFalse(Gate::allows('gerenciar-permissoes'));
    }

    public function test_grant_revoke_and_regrant_take_effect_without_reloading_the_user(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $permission = Permissao::factory()->atendimento()->create();
        $employee->load('concessoes');

        $this->assertTrue($permission->conceder($employee, $admin, 'Atendimento'));
        $this->assertFalse($permission->conceder($employee, $admin, 'Repetição'));
        $this->assertTrue(Gate::forUser($employee)->allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertTrue($permission->revogar($employee, $admin, 'Mudança de função'));
        $this->assertFalse($permission->revogar($employee, $admin, 'Repetição'));
        $this->assertFalse(Gate::forUser($employee)->allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertDatabaseHas('permissao_user', [
            'user_id' => $employee->id,
            'concedida_por_user_id' => $admin->id,
            'revogada_por_user_id' => $admin->id,
            'revogada_em' => now()->format('Y-m-d H:i:s'),
        ]);
        $this->assertTrue($permission->conceder($employee, $admin, 'Retorno ao atendimento'));
        $this->assertTrue(Gate::forUser($employee)->allows(Permissao::ATENDER_SOLICITACOES));
        $this->assertDatabaseCount('permissao_user', 1);
        $this->assertSame(['concessao', 'revogacao', 'concessao'], $permission->eventos()->orderBy('id')->pluck('acao')->all());
        $this->assertDatabaseHas('eventos_permissao', [
            'user_id' => $employee->id, 'autor_user_id' => $admin->id,
            'acao' => 'revogacao', 'motivo' => 'Mudança de função',
        ]);
    }

    public function test_current_database_role_overrides_a_stale_admin_instance(): void
    {
        $admin = User::factory()->admin()->create();
        User::query()->whereKey($admin->id)->update(['role' => 'funcionario']);

        $this->assertFalse(Gate::forUser($admin)->allows('gerenciar-permissoes'));
        $this->assertFalse(Gate::forUser($admin)->allows(Permissao::ATENDER_SOLICITACOES));
    }

    public function test_employee_cannot_grant_themselves_a_permission(): void
    {
        $employee = User::factory()->funcionario()->create();
        $permission = Permissao::factory()->atendimento()->create();

        try {
            $permission->conceder($employee, $employee, 'Tentativa indevida');
            $this->fail('Expected authorization failure.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('permissao_user', 0);
            $this->assertDatabaseCount('eventos_permissao', 0);
        }
    }

    public function test_employee_cannot_revoke_an_existing_grant(): void
    {
        $grant = ConcessaoPermissao::factory()->create();
        try {
            $grant->permissao->revogar($grant->usuario, $grant->usuario, 'Tentativa indevida');
            $this->fail('Expected authorization failure.');
        } catch (AuthorizationException) {
            $this->assertNull($grant->fresh()->revogada_em);
            $this->assertDatabaseCount('eventos_permissao', 0);
        }
    }

    #[DataProvider('invalidGrants')]
    public function test_invalid_grants_do_not_write_history(string $role, string $code, string $reason, string $field): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create(['role' => $role]);
        $permission = Permissao::factory()->create(['codigo' => $code]);

        try {
            $permission->conceder($recipient, $admin, $reason);
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertDatabaseCount('permissao_user', 0);
            $this->assertDatabaseCount('eventos_permissao', 0);
        }
    }

    public static function invalidGrants(): array
    {
        return [
            'admin' => ['admin', 'atender-solicitacoes', 'Motivo', 'funcionario'],
            'clinical permission' => ['funcionario', 'aprovar-doacao', 'Motivo', 'permissao'],
            'blank reason' => ['funcionario', 'atender-solicitacoes', '  ', 'motivo'],
        ];
    }

    public function test_audit_failure_rolls_back_the_grant(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $permission = Permissao::factory()->atendimento()->create();
        EventoPermissao::creating(function (EventoPermissao $event): void {
            throw new \RuntimeException('Audit unavailable');
        });

        try {
            $permission->conceder($employee, $admin, 'Atendimento');
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertDatabaseCount('permissao_user', 0);
            $this->assertDatabaseCount('eventos_permissao', 0);
        } finally {
            EventoPermissao::flushEventListeners();
        }
    }

    public function test_can_middleware_observes_revocation_with_the_same_authenticated_user(): void
    {
        Route::middleware(['web', 'auth', 'can:atender-solicitacoes'])
            ->get('/_test/atendimento', fn () => response('Permitido'));
        $grant = ConcessaoPermissao::factory()->create();
        $this->actingAs($grant->usuario)->get('/_test/atendimento')->assertOk();

        $grant->permissao->revogar($grant->usuario, $grant->concedidaPor, 'Revogação');
        $this->get('/_test/atendimento')->assertForbidden();
    }

    public function test_permission_catalog_seeding_is_idempotent_and_does_not_grant_access(): void
    {
        $this->seed(PermissaoSeeder::class);
        $this->seed(PermissaoSeeder::class);

        $this->assertDatabaseCount('permissoes', 3);
        $this->assertDatabaseHas('permissoes', ['codigo' => Permissao::ATENDER_SOLICITACOES]);
        $this->assertDatabaseHas('permissoes', ['codigo' => Permissao::GERENCIAR_AGENDA]);
        $this->assertDatabaseCount('permissao_user', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_public_registration_cannot_set_an_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Pessoa Fictícia', 'email' => 'nova@example.test',
            'password' => 'password', 'password_confirmation' => 'password', 'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
        $this->assertFalse(Gate::allows('gerenciar-permissoes'));
    }

    public function test_profile_and_mass_assignment_cannot_promote_a_user(): void
    {
        $user = User::factory()->create();
        $user->fill(['name' => 'Nome Atualizado', 'role' => 'admin'])->save();
        $this->assertSame('funcionario', $user->fresh()->role);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Nome Atualizado', 'email' => $user->email, 'role' => 'admin',
        ])->assertRedirect('/profile');
        $this->assertSame('funcionario', $user->fresh()->role);
    }
}
