<?php

namespace Tests\Feature;

use App\Models\ContatoSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgendaSchemaTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
        if (config('database.default') === 'mysql' && DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test') {
            throw new \RuntimeException('Unexpected test database connection.');
        }
    }

    private function agendamento(array $alteracoes = []): int
    {
        $pedido = Solicitacao::factory()->create(['status' => 'admitida']);
        $autor = User::factory()->funcionario()->create();
        $contato = ContatoSolicitacao::factory()->create(['registrado_por_user_id' => $autor->id, 'contexto' => 'acompanhamento']);
        $contato->solicitacoes()->attach($pedido);

        return DB::table('agendamentos')->insertGetId(array_replace([
            'solicitacao_id' => $pedido->id, 'contato_confirmacao_id' => $contato->id,
            'responsavel_user_id' => $autor->id, 'confirmado_por_user_id' => $autor->id,
            'modalidade' => 'domicilio', 'local_atendimento' => 'Local fictício',
            'inicio_previsto_em' => '2026-11-01 12:00:00', 'fim_previsto_em' => '2026-11-01 13:00:00',
            'status' => 'confirmado', 'confirmado_em' => '2026-10-26 12:00:00',
            'created_at' => '2026-10-26 12:00:00', 'updated_at' => '2026-10-26 12:00:00',
        ], $alteracoes));
    }

    private function evento(int $agendamento, array $alteracoes = []): void
    {
        $registro = DB::table('agendamentos')->find($agendamento);
        DB::table('eventos_agendamento')->insert(array_replace([
            'agendamento_id' => $agendamento, 'autor_user_id' => $registro->confirmado_por_user_id,
            'acao' => 'confirmacao', 'operacao_id' => (string) Str::uuid(), 'conteudo_hash' => str_repeat('a', 64),
            'motivo' => 'Confirmação fictícia', 'dados_novos' => json_encode(['status' => 'confirmado']),
            'ocorrido_em' => '2026-10-26 12:00:00',
        ], $alteracoes));
    }

    public function test_upgrade_and_rollback_preserve_existing_contacts_users_and_requests(): void
    {
        $dependentes = glob(database_path('migrations/2026_10_28_*'));
        sort($dependentes);
        foreach (array_reverse($dependentes) as $arquivo) {
            (require $arquivo)->down();
        }
        $arquivos = glob(database_path('migrations/2026_10_26_*'));
        sort($arquivos);
        foreach (array_reverse($arquivos) as $arquivo) {
            (require $arquivo)->down();
        }
        $contato = ContatoSolicitacao::factory()->create();
        $pedido = Solicitacao::factory()->create();
        $contato->solicitacoes()->attach($pedido);
        foreach ($arquivos as $arquivo) {
            (require $arquivo)->up();
        }
        $this->assertDatabaseHas('contatos_solicitacao', ['id' => $contato->id, 'contexto' => 'analise', 'operacao_id' => null, 'conteudo_hash' => null]);
        $this->assertDatabaseHas('contato_pedidos', ['contato_id' => $contato->id, 'solicitacao_id' => $pedido->id]);
        foreach (array_reverse($arquivos) as $arquivo) {
            (require $arquivo)->down();
        }
        $this->assertDatabaseHas('users', ['id' => $contato->registrado_por_user_id]);
        $this->assertDatabaseHas('contatos_solicitacao', ['id' => $contato->id, 'resumo' => $contato->resumo]);
        $this->assertDatabaseHas('solicitacoes', ['id' => $pedido->id]);
        $this->assertFalse(Schema::hasColumn('contatos_solicitacao', 'contexto'));
        foreach ($arquivos as $arquivo) {
            (require $arquivo)->up();
        }
        foreach ($dependentes as $arquivo) {
            (require $arquivo)->up();
        }
    }

    public function test_legacy_contacts_accept_multiple_null_operations_but_new_operation_is_unique(): void
    {
        ContatoSolicitacao::factory()->count(2)->create();
        $this->assertDatabaseCount('contatos_solicitacao', 2);
        $operacao = (string) Str::uuid();
        ContatoSolicitacao::factory()->create(['operacao_id' => $operacao]);
        $this->expectException(QueryException::class);
        ContatoSolicitacao::factory()->create(['operacao_id' => $operacao]);
    }

    public function test_one_request_cannot_have_two_current_reservations(): void
    {
        $primeiro = $this->agendamento();
        $pedido = DB::table('agendamentos')->find($primeiro)->solicitacao_id;
        $segundo = $this->agendamento(['solicitacao_id' => $pedido]);
        DB::table('reservas_agenda')->insert(['solicitacao_id' => $pedido, 'agendamento_id' => $primeiro]);
        $this->expectException(QueryException::class);
        DB::table('reservas_agenda')->insert(['solicitacao_id' => $pedido, 'agendamento_id' => $segundo]);
    }

    public function test_one_appointment_cannot_be_reserved_by_two_requests(): void
    {
        $id = $this->agendamento();
        DB::table('reservas_agenda')->insert(['solicitacao_id' => DB::table('agendamentos')->find($id)->solicitacao_id, 'agendamento_id' => $id]);
        $outro = Solicitacao::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('reservas_agenda')->insert(['solicitacao_id' => $outro->id, 'agendamento_id' => $id]);
    }

    public function test_releasing_reservation_preserves_appointment_and_audit(): void
    {
        $id = $this->agendamento();
        $this->evento($id);
        DB::table('reservas_agenda')->insert(['solicitacao_id' => DB::table('agendamentos')->find($id)->solicitacao_id, 'agendamento_id' => $id]);
        DB::table('reservas_agenda')->where('agendamento_id', $id)->delete();
        $this->assertDatabaseCount('reservas_agenda', 0);
        $this->assertDatabaseHas('agendamentos', ['id' => $id]);
        $this->assertDatabaseHas('eventos_agendamento', ['agendamento_id' => $id]);
    }

    public function test_replacement_cannot_have_two_successors(): void
    {
        $id = $this->agendamento();
        $this->agendamento(['substitui_agendamento_id' => $id]);
        $this->expectException(QueryException::class);
        $this->agendamento(['substitui_agendamento_id' => $id]);
    }

    public static function referencias(): array
    {
        return array_map(fn (string $campo): array => [$campo], ['solicitacao_id', 'contato_confirmacao_id', 'responsavel_user_id', 'confirmado_por_user_id', 'substitui_agendamento_id']);
    }

    #[DataProvider('referencias')]
    public function test_appointment_rejects_missing_reference(string $campo): void
    {
        $this->expectException(QueryException::class);
        $this->agendamento([$campo => 999999]);
    }

    public static function exclusoes(): array
    {
        return [['users', 'responsavel_user_id'], ['contatos_solicitacao', 'contato_confirmacao_id'], ['solicitacoes', 'solicitacao_id']];
    }

    #[DataProvider('exclusoes')]
    public function test_referenced_records_cannot_be_deleted(string $tabela, string $campo): void
    {
        $id = $this->agendamento();
        $referencia = DB::table('agendamentos')->find($id)->{$campo};
        $this->expectException(QueryException::class);
        DB::table($tabela)->where('id', $referencia)->delete();
    }

    public function test_audit_prevents_deletion_even_after_reservation_is_released(): void
    {
        $id = $this->agendamento();
        $this->evento($id);
        $this->expectException(QueryException::class);
        DB::table('agendamentos')->where('id', $id)->delete();
    }

    public function test_duplicate_event_operation_rolls_back_a_transaction_without_partial_reservation(): void
    {
        $id = $this->agendamento();
        $operacao = (string) Str::uuid();
        $this->evento($id, ['operacao_id' => $operacao]);
        try {
            DB::transaction(function () use ($id, $operacao): void {
                $novo = $this->agendamento();
                DB::table('reservas_agenda')->insert(['solicitacao_id' => DB::table('agendamentos')->find($novo)->solicitacao_id, 'agendamento_id' => $novo]);
                $this->evento($id, ['operacao_id' => $operacao]);
            });
            $this->fail('Operação duplicada deveria falhar.');
        } catch (QueryException) {
            $this->assertDatabaseCount('agendamentos', 1);
            $this->assertDatabaseCount('reservas_agenda', 0);
            $this->assertDatabaseCount('eventos_agendamento', 1);
        }
    }
}
