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
        DB::transaction(function (): void {
            $contas = DB::table('users')->where('role', 'doadora')->get(['id', 'email']);
            DB::table('sessions')->whereIn('user_id', $contas->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $contas->pluck('email'))->delete();
            DB::table('users')->whereIn('id', $contas->pluck('id'))->delete();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'funcionario'])->change();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['criado_por_user_id']);
            $table->dropColumn('criado_por_user_id');
            $table->enum('role', ['admin', 'funcionario', 'doadora'])->default('doadora')->change();
        });
    }
};
