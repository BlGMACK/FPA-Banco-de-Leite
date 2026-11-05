<?php

namespace Tests\Feature;

use App\Models\EventoSolicitacao;
use App\Models\Interessada;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrecaoSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function dados(Solicitacao $pedido, array $alteracoes = []): array
    {
        return array_replace([
            'versao' => $pedido->fresh()->versao, 'motivo' => 'Informado pela interessada fictícia.',
            'nome_completo' => 'Pessoa Fictícia', 'cpf' => '529.982.247-25', 'telefone_celular' => '(14) 99999-0000',
            'email_contato' => 'ficticia@example.test', 'municipio' => 'Marília', 'bairro' => 'Bairro de Teste', 'uf' => 'sp',
            'cep' => '17500-000', 'logradouro' => 'Rua de Teste', 'numero' => '10', 'whatsapp_telefone' => 'celular', 'whatsapp_disponivel' => '1',
        ], $alteracoes);
    }

    private function admitido(): Solicitacao
    {
        $interessada = Interessada::factory()->create(['nome_completo' => 'Cadastro Fictício', 'cpf' => '52998224725', 'telefone_celular' => '14999990000']);

        return Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => $interessada->id,
            'admitida_por_user_id' => User::factory()->funcionario(), 'admitida_em' => now()]);
    }

    public function test_correction_requires_login_and_permission_before_record_lookup(): void
    {
        $this->post('/interno/solicitacoes/999/correcao')->assertRedirect('/login');
        $this->actingAs(User::factory()->funcionario()->create())->post('/interno/solicitacoes/999/correcao')->assertForbidden();
        $this->assertDatabaseCount('eventos_solicitacao', 0);
    }

    public function test_request_in_analysis_has_identity_and_contacts_corrected_with_history_and_original_snapshot(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise', 'cpf' => '00000000000', 'telefone_celular' => '14999990000']);
        $autor = User::factory()->admin()->create();

        $this->actingAs($autor)->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido, ['nome_completo' => 'Pessoa Corrigida']))
            ->assertRedirect(route('interno.solicitacoes.show', $pedido))->assertSessionHas('status', 'dados-corrigidos');

        $pedido->refresh();
        $this->assertSame(['Pessoa Corrigida', 'pessoa corrigida', '52998224725', '17500000', '2'], [$pedido->nome_completo, $pedido->nome_normalizado, $pedido->cpf, $pedido->cep, (string) $pedido->versao]);
        $this->assertSame(['nome_completo' => 'Pessoa Fictícia'], $pedido->dados_originais);
        $evento = EventoSolicitacao::query()->where('tipo', 'correcao')->sole();
        $this->assertSame($autor->id, $evento->autor_user_id);
        $this->assertSame('Informado pela interessada fictícia.', $evento->motivo);
        $this->assertSame(['registro' => 'pedido', 'campos' => ['nome_completo' => 'Pessoa Fictícia', 'cpf' => '00000000000',
            'email_contato' => $evento->dados_anteriores['campos']['email_contato'], 'cep' => '00000000', 'numero' => 's/n']], $evento->dados_anteriores);
        $this->assertSame('Pessoa Corrigida', $evento->dados_novos['campos']['nome_completo']);
        $this->get(route('interno.solicitacoes.show', $pedido))->assertOk()->assertSee('Dados corrigidos.')
            ->assertSee('Campos corrigidos no pedido: nome_completo, cpf, email_contato, cep, numero.');
    }

    public function test_admitted_request_corrects_only_registry_contacts_and_ignores_identity_fields(): void
    {
        $pedido = $this->admitido();
        $interessada = $pedido->interessada;

        $this->actingAs(User::factory()->admin()->create())->post(route('interno.solicitacoes.correcao.store', $pedido),
            $this->dados($pedido, ['nome_completo' => 'Outro Nome', 'cpf' => '111.444.777-35', 'telefone_celular' => '14988887777', 'bairro' => 'Novo Bairro']))
            ->assertRedirect(route('interno.acompanhamentos.show', $pedido))->assertSessionHasNoErrors('correcao');

        $interessada->refresh();
        $this->assertSame(['Cadastro Fictício', '52998224725', '14988887777', 'Novo Bairro', 2],
            [$interessada->nome_completo, $interessada->cpf, $interessada->telefone_celular, $interessada->bairro, $interessada->versao]);
        $this->assertSame(['14999990000', 'Bairro de Teste', 2], [$pedido->fresh()->telefone_celular, $pedido->fresh()->bairro, $pedido->fresh()->versao]);
        $evento = EventoSolicitacao::query()->where('tipo', 'correcao')->sole();
        $this->assertSame('cadastro', $evento->dados_novos['registro']);
        $this->assertArrayNotHasKey('nome_completo', $evento->dados_novos['campos']);
        $this->get(route('interno.acompanhamentos.show', $pedido))->assertOk()->assertSee('Dados do cadastro corrigidos.')->assertDontSee('correcao-cpf', false);
    }

    public function test_invalid_cpf_or_missing_reason_is_rejected_without_changes(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido, ['cpf' => '111.111.111-11']))
            ->assertSessionHasErrors(['cpf' => 'Informe um CPF válido.'], errorBag: 'correcao');
        $this->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido, ['motivo' => '']))
            ->assertSessionHasErrors(['motivo' => 'Preencha o campo motivo da correção.'], errorBag: 'correcao');
        $this->assertSame(1, $pedido->fresh()->versao);
        $this->assertDatabaseCount('eventos_solicitacao', 0);
    }

    public function test_unchanged_data_is_rejected(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $this->actingAs(User::factory()->admin()->create())->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido));
        $this->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido))
            ->assertSessionHasErrors(['dados' => 'Nenhum dado foi alterado.'], errorBag: 'correcao');

        $this->assertSame(1, EventoSolicitacao::query()->where('tipo', 'correcao')->count());
    }

    public function test_stale_version_and_archived_request_return_409(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise', 'versao' => 3]);
        $arquivado = Solicitacao::factory()->create(['status' => 'arquivada']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido, ['versao' => 2]))->assertConflict();
        $this->post(route('interno.solicitacoes.correcao.store', $arquivado), $this->dados($arquivado))->assertConflict();
        $this->assertDatabaseCount('eventos_solicitacao', 0);
    }

    public function test_reason_is_escaped_in_history(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('interno.solicitacoes.correcao.store', $pedido), $this->dados($pedido, ['motivo' => '<script>alert(1)</script>']));

        $this->get(route('interno.solicitacoes.show', $pedido))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
