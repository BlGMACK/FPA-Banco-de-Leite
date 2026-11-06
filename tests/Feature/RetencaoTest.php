<?php

namespace Tests\Feature;

use App\Models\AnexoItemAvaliacao;
use App\Models\DecisaoAvaliacao;
use App\Models\EventoSolicitacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RetencaoTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('anexos');
        config(['retencao.anos' => '5']);
    }

    /** Anexo de uma avaliação cuja decisão de habilitação ocorreu há $anos anos; sem $anos, a avaliação segue na etapa de exames. */
    private function anexo(?int $anos): AnexoItemAvaliacao
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $caminho = tempnam(sys_get_temp_dir(), 'anexo');
        file_put_contents($caminho, "%PDF-1.4\n%%EOF\n");
        $anexo = $item->anexar(User::factory()->admin()->create(), ['operacao_id' => (string) Str::uuid(),
            'arquivo' => new UploadedFile($caminho, 'exame.pdf', null, null, true)]);
        if ($anos !== null) {
            $item->avaliacao->forceFill(['estado' => 'habilitada'])->save();
            DecisaoAvaliacao::factory()->create(['avaliacao_id' => $item->avaliacao_id, 'etapa' => 'habilitacao',
                'resultado' => 'habilitada', 'possui_plano_saude' => false, 'decidido_em' => now()->subYears($anos)]);
        }

        return $anexo;
    }

    private function correcao(Solicitacao $pedido, int $anos): EventoSolicitacao
    {
        return EventoSolicitacao::factory()->create(['solicitacao_id' => $pedido->id, 'tipo' => 'correcao', 'estado_anterior' => 'em_analise',
            'estado_novo' => 'em_analise', 'autor_user_id' => User::factory()->admin(), 'motivo' => 'Correção fictícia.', 'ocorrido_em' => now()->subYears($anos),
            'dados_anteriores' => ['registro' => 'pedido', 'campos' => ['telefone_celular' => '14999990000', 'bairro' => 'Bairro Antigo']],
            'dados_novos' => ['registro' => 'pedido', 'campos' => ['telefone_celular' => '14988887777', 'bairro' => 'Bairro Novo'], 'versao' => 2]]);
    }

    private function descartar(User $admin, array $dados = []): TestResponse
    {
        $limite = $this->actingAs($admin)->get(route('interno.retencao.index'))->viewData('limite');

        return $this->post(route('interno.retencao.descarte.store'), $dados + ['limite' => $limite->toIso8601String(), 'confirmacao' => '1']);
    }

    public function test_only_administrators_reach_the_retention_screen(): void
    {
        $this->get('/interno/retencao')->assertRedirect('/login');
        $this->post('/interno/retencao/descarte')->assertRedirect('/login');
        $funcionario = User::factory()->funcionario()->create();
        foreach (Permissao::query()->get() as $permissao) {
            $permissao->conceder($funcionario, User::factory()->admin()->create(), 'Todas as permissões.');
        }

        $this->actingAs($funcionario)->get('/interno/retencao')->assertForbidden();
        $this->post('/interno/retencao/descarte', ['limite' => now()->toIso8601String(), 'confirmacao' => '1'])->assertForbidden();
        $this->get('/dashboard')->assertDontSee('Retenção');
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee('Retenção');
    }

    public function test_without_a_configured_period_nothing_is_listed_or_discarded(): void
    {
        $anexo = $this->anexo(30);
        $admin = User::factory()->admin()->create();

        foreach ([null, '', '0', 'abc'] as $valor) {
            config(['retencao.anos' => $valor]);
            $this->actingAs($admin)->get(route('interno.retencao.index'))->assertOk()
                ->assertSee('O prazo de retenção não está definido')->assertDontSee('Descartar o que venceu');
            $this->post(route('interno.retencao.descarte.store'), ['limite' => now()->toIso8601String(), 'confirmacao' => '1'])->assertStatus(409);
        }

        Storage::disk('anexos')->assertExists($anexo->caminho);
        $this->assertNull($anexo->fresh()->descartado_em);
    }

    public function test_expired_attachments_are_deleted_and_marked_while_recent_and_undecided_ones_stay(): void
    {
        $vencido = $this->anexo(6);
        $recente = $this->anexo(1);
        $semDecisao = $this->anexo(null);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('interno.retencao.index'))->assertOk()->assertSee('5 anos')
            ->assertSee('#'.$vencido->item->avaliacao_id)->assertDontSee('#'.$recente->item->avaliacao_id)->assertDontSee('#'.$semDecisao->item->avaliacao_id);
        $this->descartar($admin)->assertRedirect(route('interno.retencao.index'))
            ->assertSessionHas('status', 'Descarte concluído: 1 arquivo(s) de exames e valores de 0 correção(ões) de pedidos.');

        Storage::disk('anexos')->assertMissing($vencido->caminho);
        Storage::disk('anexos')->assertExists($recente->caminho);
        Storage::disk('anexos')->assertExists($semDecisao->caminho);
        $vencido->refresh();
        $this->assertSame($admin->id, $vencido->descartado_por_user_id);
        $this->assertNotNull($vencido->descartado_em);
        $this->assertSame('exame.pdf', $vencido->nome_original);
        $this->assertNull($recente->fresh()->descartado_em);

        $this->get(route('interno.exames.anexos.arquivo', $vencido->id))->assertStatus(410);
        $this->get(route('interno.exames.show', $vencido->item->avaliacao_id))->assertOk()
            ->assertSee('Arquivo descartado pelo prazo de retenção')->assertDontSee(route('interno.exames.anexos.arquivo', $vencido->id));
    }

    public function test_expired_correction_values_are_erased_keeping_fields_author_and_reason(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $vencida = $this->correcao($pedido, 6);
        $recente = $this->correcao($pedido, 2);
        $admin = User::factory()->admin()->create();

        $this->descartar($admin)->assertSessionHas('status', 'Descarte concluído: 0 arquivo(s) de exames e valores de 1 correção(ões) de pedidos.');

        $vencida->refresh();
        $this->assertSame(['registro' => 'pedido', 'campos' => ['telefone_celular' => null, 'bairro' => null]], $vencida->dados_anteriores);
        $this->assertSame(['registro' => 'pedido', 'campos' => ['telefone_celular' => null, 'bairro' => null], 'versao' => 2], $vencida->dados_novos);
        $this->assertSame([$admin->id, 'Correção fictícia.'], [$vencida->valores_descartados_por_user_id, $vencida->motivo]);
        $this->assertSame('Bairro Antigo', $recente->fresh()->dados_anteriores['campos']['bairro']);
        $this->get(route('interno.solicitacoes.show', $pedido))->assertOk()
            ->assertSee('Campos corrigidos no pedido: telefone_celular, bairro. Valores descartados pelo prazo de retenção em', false);
    }

    public function test_discard_requires_confirmation_rejects_a_later_limit_and_repeating_changes_nothing(): void
    {
        $anexo = $this->anexo(6);
        $admin = User::factory()->admin()->create();

        $this->descartar($admin, ['confirmacao' => ''])->assertSessionHasErrors('confirmacao');
        $this->descartar($admin, ['limite' => now()->subYears(4)->toIso8601String()])->assertStatus(409);
        Storage::disk('anexos')->assertExists($anexo->caminho);

        $this->descartar($admin)->assertSessionHas('status', 'Descarte concluído: 1 arquivo(s) de exames e valores de 0 correção(ões) de pedidos.');
        $marcado = $anexo->fresh()->descartado_em;
        $this->descartar($admin)->assertSessionHas('status', 'Descarte concluído: 0 arquivo(s) de exames e valores de 0 correção(ões) de pedidos.');
        $this->assertEquals($marcado, $anexo->fresh()->descartado_em);
    }

    public function test_discard_mark_is_final_and_rollback_is_refused_after_a_discard(): void
    {
        $anexo = $this->anexo(6);
        $this->descartar(User::factory()->admin()->create());
        $anexo->refresh();

        foreach ([fn () => $anexo->forceFill(['descartado_em' => now()->addDay()])->save(), fn () => $anexo->forceFill(['nome_original' => 'x.pdf'])->save()] as $acao) {
            try {
                $acao();
                $this->fail('Expected immutable attachment.');
            } catch (\LogicException) {
            }
        }
        try {
            (require database_path('migrations/2026_11_05_100000_add_descarte_retencao.php'))->down();
            $this->fail('Expected rollback refusal.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('descartes por retenção', $exception->getMessage());
        }
    }
}
