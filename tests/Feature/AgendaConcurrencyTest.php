<?php

namespace Tests\Feature;

use App\Models\ContatoSolicitacao;
use App\Models\Interessada;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AgendaConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Concorrência exige MariaDB isolado com conexões independentes.');
        }
        if (DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test') {
            throw new \RuntimeException('Unexpected database.');
        }
    }

    public static function disputas(): array
    {
        return ['mesmo responsável' => [false], 'mesmo pedido' => [true]];
    }

    #[DataProvider('disputas')]
    public function test_concurrent_confirmations_allow_only_one_reservation(bool $mesmoPedido): void
    {
        $autor = User::factory()->admin()->create();
        $outro = User::factory()->funcionario()->create();
        $primeiro = Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => Interessada::factory()])->refresh();
        $segundo = $mesmoPedido ? $primeiro : Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => Interessada::factory()])->refresh();
        $processos = [];
        $sinais = [];
        foreach ([$primeiro, $segundo] as $indice => $pedido) {
            $contato = ContatoSolicitacao::factory()->create(['contexto' => 'acompanhamento', 'resultado' => 'realizado']);
            $contato->solicitacoes()->attach($pedido);
            $dados = ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->versao,
                'motivo' => 'Teste concorrente', 'contato_id' => $contato->id,
                'responsavel_user_id' => $mesmoPedido && $indice === 1 ? $outro->id : $autor->id,
                'modalidade' => 'banco', 'local_atendimento' => 'Local fictício',
                'inicio_previsto_em' => now()->setTimezone('America/Sao_Paulo')->addDay()->format('Y-m-d\TH:i'),
                'fim_previsto_em' => now()->setTimezone('America/Sao_Paulo')->addDay()->addHour()->format('Y-m-d\TH:i'),
                'combinado_confirmado' => true];
            $sinal = tempnam(sys_get_temp_dir(), 'blh-agenda-');
            $sinais[] = $sinal;
            $processos[] = $this->worker($pedido->id, $autor->id, $dados, $sinal);
        }
        DB::beginTransaction();
        try {
            if ($mesmoPedido) {
                Solicitacao::query()->lockForUpdate()->findOrFail($primeiro->id);
            } else {
                User::query()->lockForUpdate()->findOrFail($autor->id);
            }
            foreach ($processos as $processo) {
                $processo->start();
            }
            $limite = microtime(true) + 20;
            foreach ($sinais as $indice => $sinal) {
                while (file_get_contents($sinal) !== 'ready' && microtime(true) < $limite && $processos[$indice]->isRunning()) {
                    usleep(10000);
                }
                $this->assertSame('ready', file_get_contents($sinal), 'Worker não inicializou: '.$processos[$indice]->getErrorOutput());
            }
            DB::commit();
            $saidas = [];
            foreach ($processos as $processo) {
                $this->assertSame(0, $processo->wait(), 'Falha inesperada no worker: '.$processo->getErrorOutput());
                $saidas[] = trim($processo->getOutput());
            }
            sort($saidas);
            $this->assertSame(['BLOCKED', 'OK'], $saidas);
            $this->assertDatabaseCount('agendamentos', 1);
            $this->assertDatabaseCount('reservas_agenda', 1);
            $this->assertDatabaseCount('eventos_agendamento', 1);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processos as $processo) {
                if ($processo->isRunning()) {
                    $processo->stop(0);
                }
            }
            foreach ($sinais as $sinal) {
                unlink($sinal);
            }
        }
    }

    /** @param array<string, mixed> $dados */
    private function worker(int $pedido, int $autor, array $dados, string $sinal): Process
    {
        $codigo = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || app()->configurationIsCached() || config('database.default') !== 'mysql'
    || DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test'
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)
    || config('database.connections.mysql.url')) { exit(2); }
file_put_contents($argv[4], 'ready');
try {
    App\Models\Agendamento::confirmar((int) $argv[1], App\Models\User::findOrFail((int) $argv[2]), json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR));
    echo 'OK';
} catch (Illuminate\Validation\ValidationException $e) {
    if (! isset($e->errors()['inicio_previsto_em'])) { exit(3); }
    echo 'BLOCKED';
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() !== 409) { exit(4); }
    echo 'BLOCKED';
}
PHP;
        $config = config('database.connections.mysql');

        return new Process([PHP_BINARY, '-r', $codigo, (string) $pedido, (string) $autor, json_encode($dados, JSON_THROW_ON_ERROR), $sinal], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'banco_de_leite_schema_test', 'DB_URL' => '',
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ], timeout: 30);
    }
}
