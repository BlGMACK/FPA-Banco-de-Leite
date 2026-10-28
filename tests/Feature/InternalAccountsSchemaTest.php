<?php

namespace Tests\Feature;

use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalAccountsSchemaTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->requireIsolatedDatabase();
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('migrate:rollback');
        });
    }

    public function test_upgrade_removes_old_accounts_sessions_and_tokens_preserving_internal_accounts(): void
    {
        $migration = require database_path('migrations/2026_10_27_202742_restrict_users_to_internal_accounts.php');
        $migration->down();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->funcionario()->create();
        $legacy = User::factory()->create(['role' => 'doadora']);
        DB::table('sessions')->insert(['id' => 'legacy-session', 'user_id' => $legacy->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $legacy->email, 'token' => 'old-token', 'created_at' => now()]);
        $migration->up();
        $this->assertDatabaseMissing('users', ['id' => $legacy->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'legacy-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $legacy->email]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'role' => 'funcionario']);
        $this->expectException(QueryException::class);
        User::factory()->create(['role' => 'doadora']);
    }

    public function test_upgrade_does_not_delete_historical_links_or_partially_remove_session_on_fk_failure(): void
    {
        $migration = require database_path('migrations/2026_10_27_202742_restrict_users_to_internal_accounts.php');
        $migration->down();
        $legacy = User::factory()->create(['role' => 'doadora']);
        $pedido = Solicitacao::factory()->create(['registrada_por_user_id' => $legacy->id]);
        DB::table('sessions')->insert(['id' => 'linked-session', 'user_id' => $legacy->id, 'payload' => '', 'last_activity' => time()]);
        try {
            $migration->up();
            $this->fail('Expected foreign key protection.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $legacy->id]);
            $this->assertDatabaseHas('sessions', ['id' => 'linked-session']);
            $this->assertDatabaseHas('solicitacoes', ['id' => $pedido->id]);
        }
        $pedido->delete();
        $migration->up();
    }
}
