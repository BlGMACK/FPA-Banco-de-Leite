<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_repeated_seeding_prepares_catalogs_and_keeps_existing_demo_accounts(): void
    {
        $existente = User::factory()->admin()->create(['email' => 'admin@teste.com', 'name' => 'Conta preservada']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('permissoes', 9);
        $this->assertDatabaseHas('tipos_exame', ['codigo' => 'exame_sangue']);
        $this->assertSame('Conta preservada', $existente->fresh()->name);
        $funcionario = User::query()->where('email', 'funcionario@teste.com')->sole();
        $this->assertSame('funcionario', $funcionario->role);
        $this->assertFalse(Hash::check('senha123', $funcionario->password));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('permissao_user', 0);
    }

    public function test_demo_accounts_are_not_created_outside_local_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('permissoes', 9);
    }
}
