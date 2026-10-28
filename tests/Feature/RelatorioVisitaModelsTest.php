<?php

namespace Tests\Feature;

use App\Models\Atendimento;
use App\Models\EventoAtendimento;
use App\Models\RelatorioVisita;
use App\Models\User;
use App\Models\VersaoRelatorioVisita;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelatorioVisitaModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_attendance_links_the_original_request_and_distinct_professionals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 15:00:00', 'UTC'));
        $responsavel = User::factory()->funcionario()->create();
        $autor = User::factory()->funcionario()->create();
        $atendimento = Atendimento::factory()->create([
            'responsavel_user_id' => $responsavel->id, 'registrado_por_user_id' => $autor->id,
            'motivo_divergencia' => 'Responsável substituído pela equipe.',
        ])->fresh();

        $this->assertTrue($atendimento->responsavel->is($responsavel));
        $this->assertTrue($atendimento->registradoPor->is($autor));
        $this->assertTrue($atendimento->agendamento->atendimento->is($atendimento));
        $this->assertSame('admitida', $atendimento->agendamento->solicitacao->status);
        $this->assertNotNull($atendimento->agendamento->solicitacao->interessada);
        $this->assertInstanceOf(CarbonImmutable::class, $atendimento->inicio_efetivo_em);
        $this->assertInstanceOf(CarbonImmutable::class, $atendimento->fim_efetivo_em);
        $this->assertInstanceOf(CarbonImmutable::class, $atendimento->registrado_em);
        $this->assertSame('2026-10-27 15:00:00', $atendimento->inicio_efetivo_em->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-27 16:00:00', $atendimento->fim_efetivo_em->format('Y-m-d H:i:s'));
    }

    public function test_report_keeps_creator_and_editor_separate_with_one_draft(): void
    {
        $criador = User::factory()->funcionario()->create();
        $editor = User::factory()->funcionario()->create();
        $relatorio = RelatorioVisita::factory()->create([
            'criado_por_user_id' => $criador->id, 'ultimo_editor_user_id' => $editor->id,
        ])->fresh();

        $this->assertTrue($relatorio->criadoPor->is($criador));
        $this->assertTrue($relatorio->ultimoEditor->is($editor));
        $this->assertTrue($relatorio->atendimento->relatorio->is($relatorio));
        $this->assertSame('rascunho', $relatorio->estado);
        $this->assertNull($relatorio->texto_rascunho);
        $this->assertNull($relatorio->base_correcao_numero);
        $this->assertSame(0, $relatorio->numero_versao_vigente);
        $this->assertSame(1, $relatorio->revisao);
        $this->assertCount(0, $relatorio->versoes);
    }

    public function test_correction_and_audit_relate_to_their_report_and_preserve_previous_authorship(): void
    {
        $base = VersaoRelatorioVisita::factory()->create(['texto' => 'Primeira versão fictícia.']);
        $outro = User::factory()->funcionario()->create();
        $correcao = VersaoRelatorioVisita::factory()->for($base->relatorio, 'relatorio')->create([
            'numero' => 2, 'versao_base_id' => $base->id, 'motivo_correcao' => 'Complemento fictício.',
            'finalizado_por_user_id' => $outro->id,
        ])->fresh();
        $evento = EventoAtendimento::factory()->for($base->relatorio->atendimento, 'atendimento')->create([
            'relatorio_visita_id' => $base->relatorio_visita_id, 'versao_relatorio_id' => $correcao->id,
            'autor_user_id' => $outro->id, 'acao' => 'finalizacao', 'revisao_analisada' => 3,
        ])->fresh();

        $this->assertTrue($correcao->versaoBase->is($base));
        $this->assertTrue($base->correcoes->sole()->is($correcao));
        $this->assertTrue($correcao->finalizadoPor->is($outro));
        $this->assertTrue($base->finalizadoPor->is($base->relatorio->ultimoEditor));
        $this->assertSame('Primeira versão fictícia.', $base->fresh()->texto);
        $this->assertSame(2, $correcao->numero);
        $this->assertInstanceOf(CarbonImmutable::class, $correcao->finalizado_em);
        $this->assertSame([$base->id, $correcao->id], $base->relatorio->versoes()->orderBy('numero')->pluck('id')->all());
        $this->assertTrue($evento->relatorio->is($base->relatorio));
        $this->assertTrue($evento->versaoRelatorio->is($correcao));
        $this->assertTrue($evento->autor->is($outro));
        $this->assertTrue($evento->atendimento->eventos->sole()->is($evento));
        $this->assertTrue($base->relatorio->eventos->sole()->is($evento));
        $this->assertTrue($correcao->eventos->sole()->is($evento));
        $this->assertSame(3, $evento->revisao_analisada);
        $this->assertInstanceOf(CarbonImmutable::class, $evento->ocorrido_em);
        $this->assertArrayNotHasKey('updated_at', $correcao->getAttributes());
        $this->assertArrayNotHasKey('updated_at', $evento->getAttributes());
    }

    public function test_serialization_omits_narratives_even_in_loaded_relationships(): void
    {
        $versao = VersaoRelatorioVisita::factory()->create(['texto' => 'NARRATIVA_RESTRITA', 'motivo_correcao' => 'MOTIVO_RESTRITO']);
        $relatorio = $versao->relatorio;
        $relatorio->forceFill(['texto_rascunho' => 'RASCUNHO_RESTRITO', 'motivo_correcao' => 'MOTIVO_RASCUNHO'])->save();
        $atendimento = $relatorio->atendimento;
        $atendimento->forceFill(['motivo_divergencia' => 'DIVERGENCIA_RESTRITA'])->save();
        $evento = EventoAtendimento::factory()->for($atendimento, 'atendimento')->create();
        $atendimento->load('relatorio.versoes', 'eventos');

        $json = $atendimento->toJson();
        foreach (['texto', 'texto_rascunho', 'motivo_correcao', 'motivo_divergencia', 'conteudo_hash',
            'NARRATIVA_RESTRITA', 'MOTIVO_RESTRITO', 'RASCUNHO_RESTRITO', 'MOTIVO_RASCUNHO', 'DIVERGENCIA_RESTRITA'] as $restrito) {
            $this->assertStringNotContainsString($restrito, $json);
        }
        $this->assertSame('NARRATIVA_RESTRITA', $versao->texto);
        $this->assertSame('RASCUNHO_RESTRITO', $atendimento->relatorio->texto_rascunho);
        $this->assertNotEmpty($evento->conteudo_hash);
    }

    /** @return array<string, array{class-string<Model>}> */
    public static function guardedModels(): array
    {
        return [
            'attendance' => [Atendimento::class], 'report' => [RelatorioVisita::class],
            'version' => [VersaoRelatorioVisita::class], 'event' => [EventoAtendimento::class],
        ];
    }

    /** @param class-string<Model> $model */
    #[DataProvider('guardedModels')]
    public function test_models_reject_untrusted_mass_assignment(string $model): void
    {
        $this->expectException(MassAssignmentException::class);

        (new $model)->fill(['id' => 123, 'estado' => 'finalizado', 'autor_user_id' => 123]);
    }
}
