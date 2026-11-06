<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\Doadora;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemonstracaoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_seeds_every_stage_of_the_first_flow_through_the_domain_operations(): void
    {
        $this->seed(DemonstracaoSeeder::class);

        $this->assertFalse(Carbon::hasTestNow(), 'O relógio deve voltar ao normal ao final.');
        $this->assertSame(['admitida' => 10, 'em_analise' => 1, 'enviada' => 3],
            Solicitacao::query()->selectRaw('status, count(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->all());
        $this->assertSame(['aguardando_analise' => 1, 'habilitada' => 2, 'habilitada_temporariamente' => 1, 'liberada_exames' => 2],
            Avaliacao::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->orderBy('estado')->pluck('total', 'estado')->all());
        $this->assertSame(3, Doadora::query()->where('estado', 'ativa')->count());
        $this->assertDatabaseCount('relatorios_visita', 7);
        $this->assertSame(2, Solicitacao::query()->whereHas('agendamentos', fn ($q) => $q->where('status', 'confirmado')
            ->where('inicio_previsto_em', '>', now()))->count());
        $this->assertSame(Solicitacao::query()->count(), Solicitacao::query()->whereHas('eventos', fn ($q) => $q->where('tipo', 'recebimento'))->count());
        $this->assertSame(1, Solicitacao::query()->where('nome_completo', 'Fernanda Lima Rocha')->first()->correspondenciasAtuais()->count());
        $this->assertTrue(Doadora::query()->where('tipo', 'temporaria')->sole()->valida_ate->isFuture());
    }

    public function test_internal_screens_show_the_demo_records(): void
    {
        $this->seed(DemonstracaoSeeder::class);
        $admin = User::query()->where('role', 'admin')->sole();

        $this->actingAs($admin)->get('/interno/solicitacoes')->assertOk()->assertSee('Juliana Prado Martins')->assertSee('Débora Rezende Moura');
        $this->get('/interno/doadoras')->assertOk()->assertSee('Camila Ferreira Santos')->assertSee('Renata Oliveira Costa');
        $this->get('/interno/avaliacoes')->assertOk()->assertSee('Aline Barbosa Teixeira');
        $this->get('/interno/agenda')->assertOk()->assertSee('Tatiane Moreira Lopes');
    }

    public function test_refuses_to_run_twice(): void
    {
        $this->seed(DemonstracaoSeeder::class);
        $this->seed(DemonstracaoSeeder::class);

        $this->assertDatabaseCount('solicitacoes', 14);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_refuses_to_run_outside_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->app->make(DemonstracaoSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_dates_are_relative_to_the_current_day(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2027-03-10 15:00:00', 'America/Sao_Paulo'));
        try {
            $this->seed(DemonstracaoSeeder::class);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('2027-03-10', Solicitacao::query()->latest('id')->first()->enviada_em->setTimezone('America/Sao_Paulo')->toDateString());
    }
}
