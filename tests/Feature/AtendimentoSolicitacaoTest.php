<?php

namespace Tests\Feature;

use App\Models\ConcessaoPermissao;
use App\Models\ContatoSolicitacao;
use App\Models\EventoSolicitacao;
use App\Models\Interessada;
use App\Models\ResolucaoSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AtendimentoSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function entradas(Solicitacao ...$pedidos): array
    {
        return array_map(fn (Solicitacao $pedido): array => ['id' => $pedido->id, 'versao' => $pedido->fresh()->versao], $pedidos);
    }

    private function contato(Solicitacao ...$pedidos): ContatoSolicitacao
    {
        $contato = ContatoSolicitacao::factory()->create(['resultado' => 'realizado', 'identidade_conferida' => true, 'duvida_esclarecida' => true]);
        $contato->solicitacoes()->attach(array_map(fn (Solicitacao $pedido): int => $pedido->id, $pedidos));

        return $contato;
    }

    private function decisao(string $acao, Solicitacao ...$pedidos): array
    {
        return [
            'operacao_id' => (string) Str::uuid(), 'acao' => $acao, 'pedidos' => $this->entradas(...$pedidos),
            'contato_id' => $this->contato(...$pedidos)->id, 'motivo' => 'Esclarecido com a interessada fictícia.',
            'principal_id' => $pedidos[0]->id, 'identidade' => 'nova',
        ];
    }

    public static function escritas(): array
    {
        return array_map(fn (string $acao): array => [$acao], ['analise', 'contatos', 'conferencias', 'resolucoes', 'admissao']);
    }

    #[DataProvider('escritas')]
    public function test_writes_require_login_and_permission_before_record_lookup(string $acao): void
    {
        $this->post('/interno/solicitacoes/999/'.$acao)->assertRedirect('/login');
        $this->actingAs(User::factory()->funcionario()->create())->post('/interno/solicitacoes/999/'.$acao)->assertForbidden();
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_reading_does_not_start_analysis_but_explicit_action_records_author_and_version(): void
    {
        $pedido = Solicitacao::factory()->create();
        $autor = User::factory()->admin()->create();
        $this->actingAs($autor)->get('/interno/solicitacoes/'.$pedido->id)->assertOk()->assertSee('Registrar contato');
        $this->assertSame('enviada', $pedido->fresh()->status);
        $dados = ['pedidos' => $this->entradas($pedido)];
        $this->post('/interno/solicitacoes/'.$pedido->id.'/analise', $dados)->assertRedirect();
        $this->assertDatabaseHas('solicitacoes', ['id' => $pedido->id, 'status' => 'em_analise', 'versao' => 2]);
        $this->assertDatabaseHas('eventos_solicitacao', ['solicitacao_id' => $pedido->id, 'autor_user_id' => $autor->id, 'tipo' => 'inicio_analise']);
        $this->post('/interno/solicitacoes/'.$pedido->id.'/analise', $dados)->assertConflict();
        $this->assertDatabaseCount('eventos_solicitacao', 1);
    }

    private function dadosContato(Solicitacao ...$pedidos): array
    {
        return ['pedidos' => $this->entradas(...$pedidos), 'canal' => 'whatsapp', 'resultado' => 'realizado',
            'ocorrido_em' => now()->subMinute()->format('Y-m-d H:i:s'), 'destinatario' => 'Número fictício',
            'finalidade' => 'Conferir repetição', 'resumo' => '<script>teste</script>',
            'identidade_conferida' => true, 'duvida_esclarecida' => true];
    }

    public function test_contact_links_all_selected_requests_and_ignores_forged_author(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $autor = User::factory()->admin()->create();
        $dados = $this->dadosContato($primeiro, $segundo) + ['registrado_por_user_id' => 999, 'status' => 'admitida'];
        $this->actingAs($autor)->post('/interno/solicitacoes/'.$primeiro->id.'/contatos', $dados)->assertRedirect();
        $contato = ContatoSolicitacao::sole();
        $this->assertSame($autor->id, $contato->registrado_por_user_id);
        $this->assertSame(2, $contato->solicitacoes()->count());
        $this->assertDatabaseHas('solicitacoes', ['id' => $segundo->id, 'versao' => 2, 'status' => 'enviada']);
        $this->get('/interno/solicitacoes/'.$primeiro->id)->assertOk()->assertSee('&lt;script&gt;teste&lt;/script&gt;', false)->assertDontSee('<script>teste</script>', false);
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/contatos', $dados)->assertConflict();
        $this->assertDatabaseCount('contatos_solicitacao', 1);
    }

    public static function contatosInvalidos(): array
    {
        return [
            'sem resposta' => [['resultado' => 'sem_resposta'], 'resultado'],
            'canal inválido' => [['canal' => 'sms'], 'canal'],
            'data futura' => [['ocorrido_em' => '2099-01-01 12:00:00'], 'ocorrido_em'],
            'sem resumo' => [['resumo' => ''], 'resumo'],
        ];
    }

    #[DataProvider('contatosInvalidos')]
    public function test_invalid_contact_leaves_no_partial_records(array $alteracoes, string $campo): void
    {
        $pedido = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/contatos',
            array_replace($this->dadosContato($pedido), $alteracoes))->assertSessionHasErrorsIn('contato', $campo);
        $this->assertDatabaseCount('contatos_solicitacao', 0);
        $this->assertSame(1, $pedido->fresh()->versao);
    }

    public function test_contact_with_one_stale_request_rolls_back_the_entire_set(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->dadosContato($primeiro, $segundo);
        $segundo->increment('versao');
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/contatos', $dados)->assertConflict();
        $this->assertDatabaseCount('contatos_solicitacao', 0);
        $this->assertSame(1, $primeiro->fresh()->versao);
    }

    public function test_simple_admission_creates_identity_and_pending_followup_without_account_or_clinical_approval(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $dados = $this->decisao('admitir', $pedido);
        $autor = User::factory()->admin()->create();
        $contas = User::count();
        $this->actingAs($autor)->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $dados)
            ->assertRedirect(route('interno.acompanhamentos.show', $pedido));
        $pedido->refresh();
        $this->assertSame('admitida', $pedido->status);
        $this->assertSame($autor->id, $pedido->admitida_por_user_id);
        $this->assertNotNull($pedido->admitida_em);
        $this->assertSame($pedido->cpf, $pedido->interessada->cpf);
        $this->assertDatabaseCount('interessadas', 1);
        $this->assertDatabaseCount('users', $contas);
        $this->assertDatabaseHas('eventos_solicitacao', ['tipo' => 'admissao', 'autor_user_id' => $autor->id, 'solicitacao_id' => $pedido->id]);
        $this->get('/interno/acompanhamentos/'.$pedido->id)->assertOk()->assertSee('pendente de agendamento')->assertSee('A aprovação para doar ainda depende');
        $this->get('/interno/acompanhamentos')->assertOk()->assertSee($pedido->nome_completo);
        $this->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $dados)->assertRedirect();
        $this->assertDatabaseCount('interessadas', 1);
        $this->assertDatabaseCount('resolucoes_solicitacao', 1);
        $this->assertDatabaseCount('eventos_solicitacao', 1);
        $dados['motivo'] = 'Outro conteúdo';
        $this->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $dados)->assertConflict();
    }

    public function test_resolution_admits_selected_principal_archives_other_and_preserves_links(): void
    {
        $primeiro = Solicitacao::factory()->create(['status' => 'em_analise']);
        $segundo = Solicitacao::factory()->create(['status' => 'em_analise']);
        $dados = $this->decisao('admitir_principal', $primeiro, $segundo);
        $dados['principal_id'] = $segundo->id;
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)
            ->assertRedirect(route('interno.acompanhamentos.show', $segundo));
        $this->assertSame('arquivada', $primeiro->fresh()->status);
        $this->assertSame('admitida', $segundo->fresh()->status);
        $this->assertDatabaseHas('resolucao_pedidos', ['solicitacao_id' => $primeiro->id, 'papel' => 'arquivado', 'versao_analisada' => 1]);
        $this->assertDatabaseHas('resolucao_pedidos', ['solicitacao_id' => $segundo->id, 'papel' => 'principal']);
        $this->get('/interno/solicitacoes/'.$primeiro->id)->assertOk()->assertSee('Decisão #')->assertDontSee('name="acao"', false);
    }

    public function test_archiving_both_removes_their_flags_without_deleting_history(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $primeiro, $segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertRedirect(route('interno.solicitacoes.index'));
        $this->assertSame('arquivada', $segundo->fresh()->status);
        $this->assertNotNull($primeiro->fresh()->arquivada_em);
        $this->assertDatabaseCount('solicitacoes', 2);
        $this->assertDatabaseCount('eventos_solicitacao', 2);
        $this->assertDatabaseCount('interessadas', 0);
        $novo = Solicitacao::factory()->create();
        $this->assertCount(0, $novo->correspondenciasAtuais());
    }

    public function test_pending_matches_outside_resolution_block_every_write(): void
    {
        $primeiro = Solicitacao::factory()->create(['status' => 'em_analise']);
        $segundo = Solicitacao::factory()->create();
        Solicitacao::factory()->create();
        $dados = $this->decisao('admitir_principal', $primeiro, $segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertSessionHasErrorsIn('decisao', 'pedidos');
        $this->assertSame('enviada', $segundo->fresh()->status);
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
        $this->assertDatabaseCount('interessadas', 0);
    }

    public static function falhasDecisao(): array
    {
        return ['sem identidade' => ['identidade', null, 'identidade'], 'principal externo' => ['principal_id', 999, 'principal_id'],
            'sem motivo' => ['motivo', '', 'motivo'], 'operação inválida' => ['operacao_id', 'x', 'operacao_id']];
    }

    #[DataProvider('falhasDecisao')]
    public function test_invalid_decision_has_no_side_effects(string $campo, mixed $valor, string $erro): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise']);
        $dados = $this->decisao('admitir', $pedido);
        $dados[$campo] = $valor;
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $dados)->assertSessionHasErrorsIn('decisao', $erro);
        $this->assertDatabaseCount('interessadas', 0);
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
    }

    public function test_unanswered_contact_cannot_resolve_duplicates_but_can_record_pending_review(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $primeiro, $segundo);
        ContatoSolicitacao::findOrFail($dados['contato_id'])->forceFill(['resultado' => 'sem_resposta', 'identidade_conferida' => false, 'duvida_esclarecida' => false])->save();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertSessionHasErrorsIn('decisao', 'contato_id');
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
        $dados['acao'] = 'aguardando_esclarecimento';
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/conferencias', $dados)->assertRedirect();
        $this->assertSame('enviada', $primeiro->fresh()->status);
        $this->assertCount(1, $primeiro->fresh()->correspondenciasAtuais());
    }

    public function test_contact_must_cover_all_requests(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $primeiro, $segundo);
        ContatoSolicitacao::findOrFail($dados['contato_id'])->solicitacoes()->detach($segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertSessionHasErrorsIn('decisao', 'contato_id');
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
    }

    public function test_second_operator_with_stale_versions_cannot_overwrite_decision(): void
    {
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $primeiro, $segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertRedirect();
        $dados['operacao_id'] = (string) Str::uuid();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertConflict();
        $this->assertDatabaseCount('resolucoes_solicitacao', 1);
        $this->assertDatabaseCount('eventos_solicitacao', 2);
    }

    public function test_distinct_people_review_allows_admission_but_new_match_does_not_inherit_review(): void
    {
        $primeiro = Solicitacao::factory()->create(['status' => 'em_analise']);
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('pessoas_distintas', $primeiro, $segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/conferencias', $dados)->assertRedirect();
        $this->get('/interno/solicitacoes/'.$primeiro->id)->assertOk()->assertSee('Conferido: pessoas distintas');
        $novo = Solicitacao::factory()->create();
        $admissao = $this->decisao('admitir', $primeiro);
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/admissao', $admissao)->assertSessionHasErrorsIn('decisao', 'pedidos');
        $this->assertDatabaseCount('interessadas', 0);
        $novo->forceFill(['status' => 'arquivada'])->save();
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/admissao', $admissao)->assertRedirect(route('interno.acompanhamentos.show', $primeiro));
        $this->assertSame('enviada', $segundo->fresh()->status);
    }

    public function test_existing_identity_is_not_overwritten_and_can_support_archiving_single_request(): void
    {
        $cadastro = Interessada::factory()->create()->refresh();
        Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => $cadastro->id]);
        $pedido = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $pedido);
        $dados['cadastros'] = [['id' => $cadastro->id, 'versao' => $cadastro->versao]];
        $antes = $cadastro->getAttributes();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/resolucoes', $dados)->assertRedirect();
        $this->assertSame($antes, $cadastro->fresh()->getAttributes());
        $this->assertDatabaseHas('resolucao_cadastros', ['interessada_id' => $cadastro->id]);
        $this->assertSame('arquivada', $pedido->fresh()->status);
    }

    public function test_followup_rejects_unadmitted_request_and_unauthorized_user(): void
    {
        $pedido = Solicitacao::factory()->create();
        $this->get('/interno/acompanhamentos')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/interno/acompanhamentos/'.$pedido->id)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/interno/acompanhamentos/'.$pedido->id)->assertNotFound();
    }

    public function test_employee_with_grant_can_act_and_revocation_blocks_next_write(): void
    {
        $concessao = ConcessaoPermissao::factory()->create();
        $pedido = Solicitacao::factory()->create();
        $this->actingAs($concessao->usuario)->post('/interno/solicitacoes/'.$pedido->id.'/analise', ['pedidos' => $this->entradas($pedido)])->assertRedirect();
        $this->assertSame('em_analise', $pedido->fresh()->status);
        $concessao->forceFill(['revogada_em' => now()])->save();
        $this->post('/interno/solicitacoes/'.$pedido->id.'/contatos', $this->dadosContato($pedido))->assertForbidden();
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_explicit_existing_identity_link_preserves_canonical_data(): void
    {
        $cadastro = Interessada::factory()->create()->refresh();
        Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => $cadastro->id]);
        $pedido = Solicitacao::factory()->create(['status' => 'em_analise', 'logradouro' => 'Outra rua fictícia']);
        $dados = $this->decisao('admitir', $pedido);
        $dados['identidade'] = 'existente';
        $dados['cadastro_referencia_id'] = $cadastro->id;
        $dados['cadastros'] = [['id' => $cadastro->id, 'versao' => $cadastro->versao]];
        $antes = $cadastro->getAttributes();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $dados)
            ->assertRedirect(route('interno.acompanhamentos.show', $pedido));
        $this->assertSame($cadastro->id, $pedido->fresh()->interessada_id);
        $this->assertSame($antes, $cadastro->fresh()->getAttributes());
        $this->assertDatabaseCount('interessadas', 1);
    }

    public function test_stale_canonical_version_blocks_archiving(): void
    {
        $cadastro = Interessada::factory()->create()->refresh();
        Solicitacao::factory()->create(['status' => 'admitida', 'interessada_id' => $cadastro->id]);
        $pedido = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $pedido);
        $dados['cadastros'] = [['id' => $cadastro->id, 'versao' => $cadastro->versao]];
        $cadastro->increment('versao');
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/resolucoes', $dados)->assertConflict();
        $this->assertSame('enviada', $pedido->fresh()->status);
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
    }

    public function test_changed_identity_data_does_not_reuse_distinct_people_review(): void
    {
        $primeiro = Solicitacao::factory()->create(['status' => 'em_analise']);
        $segundo = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/conferencias',
            $this->decisao('pessoas_distintas', $primeiro, $segundo))->assertRedirect();
        $segundo->forceFill(['email_contato' => 'alterado@example.test'])->save();
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/admissao', $this->decisao('admitir', $primeiro))->assertSessionHasErrorsIn('decisao', 'pedidos');
        $this->assertDatabaseCount('interessadas', 0);
    }

    public function test_new_pending_question_supersedes_previous_distinct_people_review(): void
    {
        $primeiro = Solicitacao::factory()->create(['status' => 'em_analise']);
        $segundo = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/conferencias',
            $this->decisao('pessoas_distintas', $primeiro, $segundo))->assertRedirect();
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/conferencias',
            $this->decisao('aguardando_esclarecimento', $primeiro, $segundo))->assertRedirect();
        $this->get('/interno/solicitacoes/'.$primeiro->id)->assertOk()->assertDontSee('Conferido: pessoas distintas');
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/admissao', $this->decisao('admitir', $primeiro))->assertSessionHasErrorsIn('decisao', 'pedidos');
        $this->assertDatabaseCount('interessadas', 0);
    }

    public function test_sent_request_cannot_skip_starting_analysis(): void
    {
        $pedido = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/admissao', $this->decisao('admitir', $pedido))->assertSessionHasErrorsIn('decisao', 'principal_id');
        $this->assertDatabaseCount('interessadas', 0);
    }

    public function test_archived_request_cannot_receive_new_contact(): void
    {
        $pedido = Solicitacao::factory()->create(['status' => 'arquivada']);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/contatos', $this->dadosContato($pedido))->assertConflict();
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_missing_record_and_duplicate_selections_do_not_create_contact(): void
    {
        $pedido = Solicitacao::factory()->create();
        $dados = $this->dadosContato($pedido);
        $dados['pedidos'][] = $dados['pedidos'][0];
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$pedido->id.'/contatos', $dados)->assertSessionHasErrorsIn('contato', 'pedidos.0.id');
        $dados['pedidos'][1]['id'] = 999;
        $this->post('/interno/solicitacoes/'.$pedido->id.'/contatos', $dados)->assertNotFound();
        $this->assertDatabaseCount('contatos_solicitacao', 0);
    }

    public function test_audit_failure_rolls_back_identity_resolution_and_all_request_updates(): void
    {
        $principal = Solicitacao::factory()->create(['status' => 'em_analise']);
        $repetido = Solicitacao::factory()->create();
        $dados = $this->decisao('admitir_principal', $principal, $repetido);
        $this->actingAs(User::factory()->admin()->create());
        EventoSolicitacao::creating(function (EventoSolicitacao $evento): void {
            throw new \RuntimeException('Falha de auditoria simulada');
        });
        try {
            $this->post('/interno/solicitacoes/'.$principal->id.'/resolucoes', $dados)->assertStatus(500);
            $this->assertDatabaseCount('resolucoes_solicitacao', 0);
            $this->assertDatabaseCount('interessadas', 0);
            $this->assertDatabaseCount('eventos_solicitacao', 0);
            $this->assertDatabaseCount('resolucao_pedidos', 0);
            $this->assertSame('em_analise', $principal->fresh()->status);
            $this->assertSame('enviada', $repetido->fresh()->status);
        } finally {
            EventoSolicitacao::flushEventListeners();
        }
    }

    public function test_internal_decision_requires_csrf_and_preserves_evidence_before_archiving(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $primeiro = Solicitacao::factory()->create();
        $segundo = Solicitacao::factory()->create();
        $dados = $this->decisao('arquivar_selecionados', $primeiro, $segundo);
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados)->assertStatus(419);
        $this->assertDatabaseCount('resolucoes_solicitacao', 0);
        $this->post('/interno/solicitacoes/'.$primeiro->id.'/resolucoes', $dados + ['_token' => session()->token()])->assertRedirect();
        $resolucao = ResolucaoSolicitacao::sole();
        $evidencias = json_decode($resolucao->solicitacoes()->findOrFail($primeiro->id)->pivot->evidencias, true);
        $this->assertSame($segundo->id, $evidencias['correspondencias'][0]['id']);
        $this->assertContains('CPF', $evidencias['correspondencias'][0]['campos']);
        $this->assertSame('arquivada', $primeiro->fresh()->status);
    }
}
