<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\RelatorioVisita;
use App\Models\VersaoRelatorioVisita;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AvaliacaoSchemaTest extends TestCase
{
    use DatabaseMigrations;

    /** Recria o banco sem rollback final: migrations anteriores recusam reverter com visitas registradas. */
    public function runDatabaseMigrations(): void
    {
        $this->requireIsolatedDatabase();
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_upgrade_backfills_evaluations_only_for_published_reports(): void
    {
        $anexos = require database_path('migrations/2026_11_01_200000_create_anexos_item_avaliacao_table.php');
        $eventos = require database_path('migrations/2026_11_01_100002_create_eventos_item_avaliacao_table.php');
        $itens = require database_path('migrations/2026_11_01_100001_create_itens_avaliacao_table.php');
        $decisoes = require database_path('migrations/2026_10_31_062120_create_decisoes_avaliacao_table.php');
        $avaliacoes = require database_path('migrations/2026_10_31_062119_create_avaliacoes_table.php');
        $anexos->down();
        $eventos->down();
        $itens->down();
        $decisoes->down();
        $avaliacoes->down();
        $publicado = VersaoRelatorioVisita::factory()->create()->relatorio;
        RelatorioVisita::factory()->create();

        $avaliacoes->up();
        $decisoes->up();
        $itens->up();
        $eventos->up();
        $anexos->up();

        $this->assertSame([$publicado->atendimento_id], Avaliacao::query()->pluck('atendimento_id')->all());
        $this->assertSame('aguardando_analise', Avaliacao::query()->sole()->estado);
    }

    public function test_one_evaluation_per_visit(): void
    {
        $avaliacao = Avaliacao::factory()->create();

        $this->expectException(QueryException::class);
        Avaliacao::factory()->create(['atendimento_id' => $avaliacao->atendimento_id]);
    }

    public function test_rollback_is_refused_while_decisions_exist(): void
    {
        $migration = require database_path('migrations/2026_10_31_062120_create_decisoes_avaliacao_table.php');
        DecisaoAvaliacao::factory()->create();

        try {
            $migration->down();
            $this->fail('Expected rollback refusal.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('decisões de avaliação', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('decisoes_avaliacao'));
    }
}
