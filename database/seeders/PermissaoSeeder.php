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
        foreach ([Permissao::ATENDER_SOLICITACOES => 'Atendimento de solicitações', Permissao::GERENCIAR_AGENDA => 'Gestão da agenda', Permissao::CRIAR_FUNCIONARIOS => 'Criação de funcionários',
            Permissao::CONSULTAR_RELATORIOS_VISITA => 'Consulta de relatórios de visita',
            Permissao::REGISTRAR_RELATORIOS_VISITA => 'Registro de relatórios de visita'] as $codigo => $nome) {
            $permissao = Permissao::query()->where('codigo', $codigo)->first() ?? new Permissao;
            $permissao->codigo = $codigo;
            $permissao->nome = $nome;
            $permissao->save();
        }
    }
}
