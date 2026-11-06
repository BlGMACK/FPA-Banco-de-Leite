<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Contas fictícias de demonstração, criadas somente em ambiente local. */
    public const CONTAS_DEMONSTRACAO = [
        ['name' => 'Admin Teste', 'email' => 'admin@teste.com', 'role' => 'admin'],
        ['name' => 'Funcionário Teste', 'email' => 'funcionario@teste.com', 'role' => 'funcionario'],
    ];

    /**
     * Prepara os catálogos e, em ambiente local, as contas de demonstração. Pode ser repetido: catálogos são idempotentes
     * e contas já existentes são mantidas como estão. A senha de cada conta nova é aleatória e exibida uma única vez.
     */
    public function run(): void
    {
        $this->call([PermissaoSeeder::class, TipoExameSeeder::class]);
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Contas de demonstração não foram criadas: o ambiente não é local.');

            return;
        }
        foreach (self::CONTAS_DEMONSTRACAO as $conta) {
            if (User::query()->where('email', $conta['email'])->exists()) {
                $this->command?->info("Conta {$conta['email']} já existe; mantida sem alteração.");

                continue;
            }
            $senha = Str::password(16);
            User::factory()->create($conta + ['password' => Hash::make($senha)]);
            $this->command?->info("Conta {$conta['email']} criada com a senha {$senha}. Guarde-a: ela não será exibida de novo.");
        }
    }
}
