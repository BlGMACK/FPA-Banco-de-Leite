<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\EventoAgendamento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelatorioVisitaSchemaTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
        if (config('database.default') === 'mysql' && DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test') {
            throw new \RuntimeException('Unexpected test database connection.');
        }
    }

    /** @return list<string> */
    private function migrations(): array
    {
        $files = glob(database_path('migrations/2026_10_28_*'));
        sort($files);

        return $files;
    }

    private function atendimento(Agendamento $agenda): int
    {
        return DB::table('atendimentos')->insertGetId([
            'agendamento_id' => $agenda->id,
            'inicio_efetivo_em' => '2026-10-28 12:00:00', 'fim_efetivo_em' => '2026-10-28 13:00:00',
            'responsavel_user_id' => $agenda->responsavel_user_id,
            'modalidade' => 'domicilio', 'local_atendimento' => 'Local fictício',
            'registrado_por_user_id' => $agenda->confirmado_por_user_id, 'registrado_em' => '2026-10-28 14:00:00',
        ]);
    }

    private function relatorio(int $atendimento, int $autor): int
    {
        return DB::table('relatorios_visita')->insertGetId([
            'atendimento_id' => $atendimento, 'criado_por_user_id' => $autor, 'ultimo_editor_user_id' => $autor,
        ]);
    }

    /** @param array<string, mixed> $changes */
    private function versao(int $relatorio, int $autor, array $changes = []): int
    {
        return DB::table('versoes_relatorio_visita')->insertGetId(array_replace([
            'relatorio_visita_id' => $relatorio, 'numero' => 1, 'texto' => 'Relato fictício',
            'finalizado_por_user_id' => $autor, 'finalizado_em' => '2026-10-28 14:00:00',
        ], $changes));
    }

    /** @param array<string, mixed> $changes */
    private function evento(int $atendimento, int $autor, array $changes = []): int
    {
        return DB::table('eventos_atendimento')->insertGetId(array_replace([
            'atendimento_id' => $atendimento, 'autor_user_id' => $autor, 'acao' => 'realizacao',
            'operacao_id' => (string) Str::uuid(), 'conteudo_hash' => str_repeat('a', 64),
            'revisao_analisada' => 1, 'ocorrido_em' => '2026-10-28 14:00:00',
        ], $changes));
    }

    public function test_upgrade_and_rollback_preserve_existing_agenda_and_reservation(): void
    {
        $files = $this->migrations();
        foreach (array_reverse($files) as $file) {
            (require $file)->down();
        }
        $agenda = Agendamento::factory()->create();
        $event = EventoAgendamento::factory()->create(['agendamento_id' => $agenda->id]);
        DB::table('reservas_agenda')->insert(['solicitacao_id' => $agenda->solicitacao_id, 'agendamento_id' => $agenda->id]);
        foreach ($files as $file) {
            (require $file)->up();
        }
        $attendance = $this->atendimento($agenda);
        $report = $this->relatorio($attendance, $agenda->confirmado_por_user_id);
        $base = $this->versao($report, $agenda->confirmado_por_user_id);
        $this->versao($report, $agenda->confirmado_por_user_id, ['numero' => 2, 'versao_base_id' => $base, 'motivo_correcao' => 'Complemento fictício']);
        $this->evento($attendance, $agenda->confirmado_por_user_id, ['relatorio_visita_id' => $report, 'versao_relatorio_id' => $base]);

        foreach (array_reverse($files) as $file) {
            (require $file)->down();
        }
        $this->assertFalse(Schema::hasTable('atendimentos'));
        $this->assertDatabaseHas('agendamentos', ['id' => $agenda->id, 'status' => 'confirmado']);
        $this->assertDatabaseHas('eventos_agendamento', ['id' => $event->id, 'acao' => 'confirmacao']);
        $this->assertDatabaseHas('reservas_agenda', ['solicitacao_id' => $agenda->solicitacao_id, 'agendamento_id' => $agenda->id]);
        $this->assertDatabaseHas('solicitacoes', ['id' => $agenda->solicitacao_id, 'status' => 'admitida']);
        $this->assertDatabaseHas('users', ['id' => $agenda->confirmado_por_user_id]);
        foreach ($files as $file) {
            (require $file)->up();
        }
        $this->assertDatabaseCount('atendimentos', 0);
    }

    public function test_an_appointment_cannot_have_two_attendances(): void
    {
        $agenda = Agendamento::factory()->create();
        $this->atendimento($agenda);

        $this->expectException(QueryException::class);
        $this->atendimento($agenda);
    }

    public function test_an_attendance_cannot_have_two_report_drafts(): void
    {
        $agenda = Agendamento::factory()->create();
        $attendance = $this->atendimento($agenda);
        $this->relatorio($attendance, $agenda->confirmado_por_user_id);

        $this->expectException(QueryException::class);
        $this->relatorio($attendance, $agenda->confirmado_por_user_id);
    }

    public function test_version_numbers_are_unique_within_each_report(): void
    {
        $agenda = Agendamento::factory()->create();
        $report = $this->relatorio($this->atendimento($agenda), $agenda->confirmado_por_user_id);
        $this->versao($report, $agenda->confirmado_por_user_id);
        $other = Agendamento::factory()->create();
        $otherReport = $this->relatorio($this->atendimento($other), $other->confirmado_por_user_id);
        $this->versao($otherReport, $other->confirmado_por_user_id);
        $this->assertDatabaseCount('versoes_relatorio_visita', 2);

        $this->expectException(QueryException::class);
        $this->versao($report, $agenda->confirmado_por_user_id);
    }

    public function test_drafts_accept_empty_content_and_versions_preserve_large_unicode_text(): void
    {
        $agenda = Agendamento::factory()->create();
        $report = $this->relatorio($this->atendimento($agenda), $agenda->confirmado_por_user_id);
        $this->assertDatabaseHas('relatorios_visita', ['id' => $report, 'estado' => 'rascunho', 'texto_rascunho' => null, 'numero_versao_vigente' => 0, 'revisao' => 1]);
        $text = str_repeat('🍼', 30000);

        DB::table('relatorios_visita')->where('id', $report)->update(['texto_rascunho' => $text]);
        $version = $this->versao($report, $agenda->confirmado_por_user_id, ['texto' => $text]);

        $this->assertSame($text, DB::table('relatorios_visita')->where('id', $report)->value('texto_rascunho'));
        $this->assertSame($text, DB::table('versoes_relatorio_visita')->where('id', $version)->value('texto'));
    }

    public function test_duplicate_event_rolls_back_the_whole_attendance_transaction(): void
    {
        $agenda = Agendamento::factory()->create();
        $existing = $this->atendimento($agenda);
        $operation = (string) Str::uuid();
        $this->evento($existing, $agenda->confirmado_por_user_id, ['operacao_id' => $operation]);
        $other = Agendamento::factory()->create();

        try {
            DB::transaction(function () use ($other, $operation): void {
                $attendance = $this->atendimento($other);
                $this->relatorio($attendance, $other->confirmado_por_user_id);
                $this->evento($attendance, $other->confirmado_por_user_id, ['operacao_id' => $operation]);
            });
            $this->fail('Duplicate operation must fail.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('atendimentos', ['agendamento_id' => $other->id]);
            $this->assertDatabaseCount('relatorios_visita', 0);
            $this->assertDatabaseCount('eventos_atendimento', 1);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function restrictedReferences(): array
    {
        return [
            'appointment history' => ['atendimentos', 'agendamento_id'],
            'actual professional' => ['atendimentos', 'responsavel_user_id'],
            'recording professional' => ['atendimentos', 'registrado_por_user_id'],
            'report attendance' => ['relatorios_visita', 'atendimento_id'],
            'original author' => ['relatorios_visita', 'criado_por_user_id'],
            'last editor' => ['relatorios_visita', 'ultimo_editor_user_id'],
            'published report' => ['versoes_relatorio_visita', 'relatorio_visita_id'],
            'base version' => ['versoes_relatorio_visita', 'versao_base_id'],
            'finalizing author' => ['versoes_relatorio_visita', 'finalizado_por_user_id'],
            'audited attendance' => ['eventos_atendimento', 'atendimento_id'],
            'audited report' => ['eventos_atendimento', 'relatorio_visita_id'],
            'audited version' => ['eventos_atendimento', 'versao_relatorio_id'],
            'event author' => ['eventos_atendimento', 'autor_user_id'],
        ];
    }

    #[DataProvider('restrictedReferences')]
    public function test_rejects_missing_references(string $table, string $column): void
    {
        $agenda = Agendamento::factory()->create();
        $attendance = $this->atendimento($agenda);
        $report = $this->relatorio($attendance, $agenda->confirmado_por_user_id);
        $base = $this->versao($report, $agenda->confirmado_por_user_id);
        $version = $this->versao($report, $agenda->confirmado_por_user_id, ['numero' => 2, 'versao_base_id' => $base]);
        $event = $this->evento($attendance, $agenda->confirmado_por_user_id, ['relatorio_visita_id' => $report, 'versao_relatorio_id' => $version]);
        $ids = ['atendimentos' => $attendance, 'relatorios_visita' => $report, 'versoes_relatorio_visita' => $version, 'eventos_atendimento' => $event];

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $ids[$table])->update([$column => 99999999]);
    }

    public function test_correction_prevents_deletion_of_its_published_base(): void
    {
        $agenda = Agendamento::factory()->create();
        $report = $this->relatorio($this->atendimento($agenda), $agenda->confirmado_por_user_id);
        $base = $this->versao($report, $agenda->confirmado_por_user_id);
        $this->versao($report, $agenda->confirmado_por_user_id, ['numero' => 2, 'versao_base_id' => $base]);

        $this->expectException(QueryException::class);
        DB::table('versoes_relatorio_visita')->where('id', $base)->delete();
    }

    public function test_attendance_prevents_deleting_the_appointment(): void
    {
        $agenda = Agendamento::factory()->create();
        $this->atendimento($agenda);

        $this->expectException(QueryException::class);
        $agenda->delete();
    }

    /** @return array<string, array{string, string}> */
    public static function invalidStates(): array
    {
        return [
            'attendance modality' => ['atendimentos', 'modalidade'],
            'report state' => ['relatorios_visita', 'estado'],
            'attendance event' => ['eventos_atendimento', 'acao'],
            'appointment state' => ['agendamentos', 'status'],
            'appointment event' => ['eventos_agendamento', 'acao'],
        ];
    }

    #[DataProvider('invalidStates')]
    public function test_schema_does_not_accept_unplanned_states(string $table, string $column): void
    {
        $agenda = Agendamento::factory()->create();
        $attendance = $this->atendimento($agenda);
        $report = $this->relatorio($attendance, $agenda->confirmado_por_user_id);
        $event = $this->evento($attendance, $agenda->confirmado_por_user_id);
        $agendaEvent = EventoAgendamento::factory()->create(['agendamento_id' => $agenda->id]);
        $ids = ['atendimentos' => $attendance, 'relatorios_visita' => $report, 'eventos_atendimento' => $event,
            'agendamentos' => $agenda->id, 'eventos_agendamento' => $agendaEvent->id];

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $ids[$table])->update([$column => 'aprovada']);
    }

    /** @return array<string, array{string, string}> */
    public static function realizationStates(): array
    {
        return [
            'appointment only' => ['realizado', 'confirmacao'],
            'event only' => ['confirmado', 'realizacao'],
            'both records' => ['realizado', 'realizacao'],
        ];
    }

    #[DataProvider('realizationStates')]
    public function test_realization_states_are_supported_and_prevent_lossy_rollback(string $status, string $action): void
    {
        $agenda = Agendamento::factory()->create();
        $event = EventoAgendamento::factory()->create(['agendamento_id' => $agenda->id, 'acao' => $action]);
        DB::table('agendamentos')->where('id', $agenda->id)->update(['status' => $status]);
        $files = $this->migrations();

        try {
            (require end($files))->down();
            $this->fail('Rollback must preserve realization history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback da agenda recusado', $exception->getMessage());
            $this->assertDatabaseHas('agendamentos', ['id' => $agenda->id, 'status' => $status]);
            $this->assertDatabaseHas('eventos_agendamento', ['id' => $event->id, 'acao' => $action]);
        } finally {
            DB::table('eventos_agendamento')->where('id', $event->id)->delete();
            DB::table('agendamentos')->where('id', $agenda->id)->update(['status' => 'confirmado']);
        }
    }
}
