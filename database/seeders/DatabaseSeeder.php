<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Contas internas com senha previsível — só para
        // desenvolvimento local. Usados para testar manualmente se o
        // middleware de permissão realmente bloqueia quem não deveria entrar.
        User::factory()->create([
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'role' => 'admin',
            'password' => bcrypt('senha123'),
        ]);

        User::factory()->create([
            'name' => 'Funcionário Teste',
            'email' => 'funcionario@teste.com',
            'role' => 'funcionario',
            'password' => bcrypt('senha123'),
        ]);

    }
}
