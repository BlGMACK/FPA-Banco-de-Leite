<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\RelatorioVisita;
use App\Models\ReservaAgenda;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RelatorioVisitaConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Concorrência exige MariaDB isolado.');
        }
        if (DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test') {
            throw new \RuntimeException('Unexpected database.');
        }
    }

    public static function disputes(): array
    {
        return ['finalizations' => [true], 'cancellation versus realization' => [false]];
    }

    #[DataProvider('disputes')]
    public function test_concurrent_operations_preserve_a_single_valid_result(bool $finalize): void
    {
        $admin = User::factory()->admin()->create();
        $report = $finalize ? RelatorioVisita::factory()->create(['texto_rascunho' => 'Relato concorrente']) : null;
        $agenda = $report?->atendimento->agendamento ?? Agendamento::factory()->create([
            'confirmado_em' => now()->subDays(2), 'inicio_previsto_em' => now()->subDay(), 'fim_previsto_em' => now()->subDay()->addHour(),
        ]);
        if (! $finalize) {
            ReservaAgenda::factory()->for($agenda, 'agendamento')->create(['solicitacao_id' => $agenda->solicitacao_id]);
        }
        $processes = [];
        $signals = [];
        foreach ([0, 1] as $index) {
            $action = $finalize ? 'finalizar' : ($index === 0 ? 'realizar' : 'cancelar');
            $data = ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'versao' => 1, 'motivo' => 'Cancelamento concorrente',
                'inicio_efetivo_em' => now()->subDay()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i'),
                'fim_efetivo_em' => now()->subDay()->addHour()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i'),
                'responsavel_user_id' => $agenda->responsavel_user_id, 'modalidade' => $agenda->modalidade,
                'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => true];
            $signal = tempnam(sys_get_temp_dir(), 'blh-relatorio-');
            $signals[] = $signal;
            $processes[] = $this->worker($action, $report?->id ?? $agenda->id, $admin->id, $data, $signal);
        }
        DB::beginTransaction();
        try {
            if ($report) {
                RelatorioVisita::query()->lockForUpdate()->findOrFail($report->id);
            } else {
                User::query()->lockForUpdate()->findOrFail($agenda->responsavel_user_id);
            }
            foreach ($processes as $process) {
                $process->start();
            }
            $deadline = microtime(true) + 20;
            foreach ($signals as $index => $signal) {
                while (file_get_contents($signal) !== 'ready' && microtime(true) < $deadline && $processes[$index]->isRunning()) {
                    usleep(10000);
                }
                $this->assertSame('ready', file_get_contents($signal), $processes[$index]->getErrorOutput());
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = trim($process->getOutput());
            }
            sort($results);
            $this->assertSame(['BLOCKED', 'OK'], $results);
            if ($report) {
                $this->assertDatabaseCount('versoes_relatorio_visita', 1);
                $this->assertDatabaseCount('eventos_atendimento', 1);
                $this->assertSame(2, $report->fresh()->revisao);
            } else {
                $this->assertDatabaseCount('reservas_agenda', 0);
                $this->assertDatabaseCount('eventos_agendamento', 1);
                $realized = $agenda->fresh()->status === 'realizado';
                $this->assertContains($agenda->fresh()->status, ['realizado', 'cancelado']);
                $this->assertDatabaseCount('atendimentos', $realized ? 1 : 0);
                $this->assertDatabaseCount('relatorios_visita', $realized ? 1 : 0);
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            foreach ($signals as $signal) {
                unlink($signal);
            }
            DB::table('eventos_agendamento')->where('agendamento_id', $agenda->id)->where('acao', 'realizacao')->delete();
            DB::table('agendamentos')->where('id', $agenda->id)->update(['status' => 'confirmado']);
        }
    }

    /** @param array<string, mixed> $data */
    private function worker(string $action, int $id, int $author, array $data, string $signal): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || app()->configurationIsCached() || config('database.default') !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test'
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)
    || config('database.connections.mysql.url')) { exit(2); }
file_put_contents($argv[5], 'ready');
try {
    $user = App\Models\User::findOrFail((int) $argv[3]);
    $data = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
    match ($argv[1]) {
        'finalizar' => App\Models\RelatorioVisita::findOrFail((int) $argv[2])->finalizar($user, $data),
        'realizar' => App\Models\Atendimento::registrar((int) $argv[2], $user, $data),
        'cancelar' => App\Models\Agendamento::findOrFail((int) $argv[2])->cancelar($user, $data),
    };
    echo 'OK';
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() !== 409) { exit(4); }
    echo 'BLOCKED';
}
PHP;
        $config = config('database.connections.mysql');

        return new Process([PHP_BINARY, '-r', $code, $action, (string) $id, (string) $author, json_encode($data, JSON_THROW_ON_ERROR), $signal], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'banco_de_leite_schema_test', 'DB_URL' => '',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ], timeout: 30);
    }
}
