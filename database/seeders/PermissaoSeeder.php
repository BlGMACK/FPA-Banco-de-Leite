<?php

namespace Database\Seeders;

use App\Models\Permissao;
use Illuminate\Database\Seeder;

class PermissaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissao = Permissao::query()->where('codigo', Permissao::ATENDER_SOLICITACOES)->first() ?? new Permissao;
        $permissao->codigo = Permissao::ATENDER_SOLICITACOES;
        $permissao->nome = 'Atendimento de solicitações';
        $permissao->save();
    }
}
