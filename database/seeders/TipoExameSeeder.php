<?php

namespace Database\Seeders;

use App\Models\Avaliacao;
use App\Models\ItemAvaliacao;
use App\Models\TipoExame;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoExameSeeder extends Seeder
{
    /**
     * Catálogo provisório acordado em 01/11/2026; a lista real entra por aqui, sem tela de edição.
     * "Outro exame" não está no catálogo: é um item de nome livre incluído na avaliação.
     */
    public function run(): void
    {
        $catalogo = [
            TipoExame::EXAME_SANGUE => ['nome' => 'Exame de sangue', 'categoria' => 'exame', 'validade_dias' => 30, 'exige_numero' => false, 'ordem' => 10],
            TipoExame::CNS => ['nome' => 'Cartão Nacional de Saúde (CNS)', 'categoria' => 'documento', 'validade_dias' => null, 'exige_numero' => true, 'ordem' => 20],
        ];
        foreach ($catalogo as $codigo => $dados) {
            $tipo = TipoExame::query()->where('codigo', $codigo)->first() ?? new TipoExame;
            $tipo->forceFill(['codigo' => $codigo, ...$dados])->save();
        }

        // Avaliações já liberadas recebem os tipos novos em "não solicitado", que não bloqueia o avanço.
        Avaliacao::query()->where('estado', 'liberada_exames')->pluck('id')
            ->each(fn (int $id) => DB::transaction(function () use ($id): void {
                Avaliacao::query()->lockForUpdate()->findOrFail($id);
                ItemAvaliacao::criarParaAvaliacao($id);
            }));
    }
}
