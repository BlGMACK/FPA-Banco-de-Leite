<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->foreignId('analise_por_user_id')->nullable()->after('versao')->constrained('users')->nullOnDelete();
            $table->timestamp('analise_iniciada_em')->nullable()->after('analise_por_user_id');
            $table->timestamp('analise_expira_em')->nullable()->after('analise_iniciada_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analise_por_user_id');
            $table->dropColumn(['analise_iniciada_em', 'analise_expira_em']);
        });
    }
};
