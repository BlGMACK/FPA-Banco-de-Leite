<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('anexos_item_avaliacao', function (Blueprint $table) {
            $table->dateTime('descartado_em')->nullable()->after('enviado_em');
            $table->foreignId('descartado_por_user_id')->nullable()->after('descartado_em')->constrained('users')->restrictOnDelete();
        });
        Schema::table('eventos_solicitacao', function (Blueprint $table) {
            $table->dateTime('valores_descartados_em')->nullable()->after('ocorrido_em');
            $table->foreignId('valores_descartados_por_user_id')->nullable()->after('valores_descartados_em')->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ((Schema::hasColumn('anexos_item_avaliacao', 'descartado_em') && DB::table('anexos_item_avaliacao')->whereNotNull('descartado_em')->exists())
            || (Schema::hasColumn('eventos_solicitacao', 'valores_descartados_em') && DB::table('eventos_solicitacao')->whereNotNull('valores_descartados_em')->exists())) {
            throw new RuntimeException('Há descartes por retenção registrados; reverter apagaria a marcação de que os dados foram descartados.');
        }
        // As tabelas podem ter sido recriadas por uma reversão das migrations de origem; só remove o que existe.
        foreach (['anexos_item_avaliacao' => 'descartado', 'eventos_solicitacao' => 'valores_descartados'] as $tabela => $prefixo) {
            if (Schema::hasColumn($tabela, $prefixo.'_em')) {
                Schema::table($tabela, function (Blueprint $table) use ($prefixo) {
                    $table->dropForeign([$prefixo.'_por_user_id']);
                    $table->dropColumn([$prefixo.'_em', $prefixo.'_por_user_id']);
                });
            }
        }
    }
};
