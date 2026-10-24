<?php

namespace Tests\Feature;

use App\Models\ConcessaoPermissao;
use App\Models\EventoSolicitacao;
use App\Models\Interessada;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SolicitacaoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function token(string $path = '/doar'): string
    {
        return $this->get($path)->assertOk()->viewData('token');
    }

    private function dados(string $token): array
    {
        return [
            'operacao_token' => $token, 'nome_completo' => 'Pessoa Fictícia',
            'cpf' => '529.982.247-25', 'telefone_fixo' => null, 'telefone_celular' => '+55 (14) 99999-0000',
            'email_contato' => 'PESSOA@example.test', 'municipio' => 'Marília',
            'bairro' => 'Centro', 'uf' => 'sp', 'cep' => '17500-000',
            'logradouro' => 'Rua Fictícia', 'numero' => 's/n', 'complemento' => null,
            'whatsapp_telefone' => 'celular', 'whatsapp_disponivel' => '1', 'outros_detalhes' => null,
        ];
    }

    public function test_public_submission_normalizes_data_creates_history_and_ignores_privileged_fields(): void
    {
        $dados = $this->dados($this->token()) + [
            'role' => 'admin', 'status' => 'admitida', 'interessada_id' => 123,
            'registrada_por_user_id' => 123, 'canal_origem' => 'presencial',
        ];
        $this->post('/pedidos-de-cadastro', $dados)->assertRedirect(route('solicitacoes.recebida'));
        $pedido = Solicitacao::sole();
        $this->assertSame('enviada', $pedido->status);
        $this->assertSame('web', $pedido->canal_origem);
        $this->assertSame('52998224725', $pedido->cpf);
        $this->assertSame('14999990000', $pedido->telefone_celular);
        $this->assertSame('pessoa@example.test', $pedido->email_contato);
        $this->assertSame('pessoa ficticia', $pedido->nome_normalizado);
        $this->assertNull($pedido->registrada_por_user_id);
        $this->assertNull($pedido->interessada_id);
        $this->assertSame('52998224725', $pedido->dados_originais['cpf']);
        $this->assertDatabaseHas('eventos_solicitacao', ['solicitacao_id' => $pedido->id, 'tipo' => 'recebimento', 'autor_user_id' => null]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('interessadas', 0);
        $this->get('/pedido-recebido')->assertOk()->assertSee('Solicitação recebida')
            ->assertDontSee($pedido->cpf)->assertDontSee($pedido->email_contato);
    }

    public function test_direct_confirmation_does_not_reveal_status_or_accept_a_lookup(): void
    {
        Solicitacao::factory()->create(['nome_completo' => 'Nome Restrito']);
        $this->get('/pedido-recebido?cpf=00000000000')->assertOk()->assertSee('preencha o formulário')
            ->assertDontSee('Nome Restrito')->assertDontSee('Solicitação recebida');
    }

    public function test_retransmission_is_idempotent_but_new_form_accepts_duplicate_identity(): void
    {
        $dados = $this->dados($this->token());
        $this->post('/pedidos-de-cadastro', $dados)->assertRedirect();
        $this->post('/pedidos-de-cadastro', $dados)->assertRedirect();
        $this->assertDatabaseCount('solicitacoes', 1);
        $this->assertDatabaseCount('eventos_solicitacao', 1);

        $this->post('/pedidos-de-cadastro', $this->dados($this->token()))->assertRedirect();
        $this->assertDatabaseCount('solicitacoes', 2);
        $this->assertDatabaseCount('eventos_solicitacao', 2);
    }

    public function test_reusing_operation_with_changed_data_is_a_conflict(): void
    {
        $dados = $this->dados($this->token());
        $this->post('/pedidos-de-cadastro', $dados)->assertRedirect();
        $this->post('/pedidos-de-cadastro', array_replace($dados, ['nome_completo' => 'Outro Nome']))->assertStatus(409);
        $this->assertSame('Pessoa Fictícia', Solicitacao::sole()->nome_completo);
        $this->assertDatabaseCount('eventos_solicitacao', 1);
    }

    public function test_forged_operation_cannot_write(): void
    {
        $this->post('/pedidos-de-cadastro', $this->dados('token-inventado'))->assertStatus(419);
        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_operation_from_another_session_cannot_write(): void
    {
        $dados = $this->dados($this->token());
        $this->withSession(['solicitacoes.chave' => 'outra-sessao'])
            ->post('/pedidos-de-cadastro', $dados)->assertStatus(419);
        $this->assertDatabaseCount('solicitacoes', 0);
    }

    public function test_expired_operation_requires_a_new_form(): void
    {
        $dados = $this->dados($this->token());
        $this->travel(121)->minutes();
        $this->post('/pedidos-de-cadastro', $dados)->assertStatus(419);
        $this->assertDatabaseCount('solicitacoes', 0);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_does_not_create_partial_records(array $changes, string $field): void
    {
        $dados = array_replace($this->dados($this->token()), $changes);
        $this->from('/doar')->post('/pedidos-de-cadastro', $dados)
            ->assertRedirect('/doar')->assertSessionHasErrors($field);
        $this->assertDatabaseCount('solicitacoes', 0);
        $this->assertDatabaseCount('eventos_solicitacao', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'cpf check digits' => [['cpf' => '52998224726'], 'cpf'],
            'cpf repeated digits' => [['cpf' => '11111111111'], 'cpf'],
            'cpf type' => [['cpf' => ['52998224725']], 'cpf'],
            'cpf letters' => [['cpf' => 'abc52998224725'], 'cpf'],
            'no phones' => [['telefone_celular' => null], 'telefone_fixo'],
            'whatsapp on absent phone' => [['whatsapp_telefone' => 'fixo'], 'whatsapp_telefone'],
            'no declaration' => [['whatsapp_disponivel' => '0'], 'whatsapp_disponivel'],
            'email' => [['email_contato' => 'sem-email'], 'email_contato'],
            'address' => [['logradouro' => ''], 'logradouro'],
            'state' => [['uf' => 'ZZ'], 'uf'],
            'name' => [['nome_completo' => str_repeat('a', 151)], 'nome_completo'],
            'details' => [['outros_detalhes' => str_repeat('a', 2001)], 'outros_detalhes'],
        ];
    }

    public function test_only_landline_with_whatsapp_is_accepted(): void
    {
        $dados = array_replace($this->dados($this->token()), [
            'telefone_celular' => null, 'telefone_fixo' => '(14) 3333-0000', 'whatsapp_telefone' => 'fixo',
        ]);
        $this->post('/pedidos-de-cadastro', $dados)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('1433330000', Solicitacao::sole()->telefone_fixo);
    }

    public function test_public_rate_limit_blocks_further_attempts_without_new_records(): void
    {
        $dados = $this->dados($this->token());
        for ($i = 0; $i < 5; $i++) {
            $this->post('/pedidos-de-cadastro', $dados)->assertRedirect();
        }
        $this->post('/pedidos-de-cadastro', $dados)->assertStatus(429);
        $this->assertDatabaseCount('solicitacoes', 1);
        $this->assertDatabaseCount('eventos_solicitacao', 1);
    }

    public function test_internal_submission_records_the_authenticated_employee_and_requires_permission(): void
    {
        $grant = ConcessaoPermissao::factory()->create();
        $this->actingAs($grant->usuario);
        $dados = $this->dados($this->token('/interno/solicitacoes/nova')) + ['canal_origem' => 'telefone'];
        $response = $this->post('/interno/solicitacoes', $dados);
        $response->assertRedirect(route('interno.solicitacoes.show', Solicitacao::sole()));
        $this->assertDatabaseHas('solicitacoes', ['canal_origem' => 'telefone', 'registrada_por_user_id' => $grant->user_id]);
        $this->assertDatabaseHas('eventos_solicitacao', ['autor_user_id' => $grant->user_id]);
        $grant->revogada_em = now();
        $grant->save();
        $this->get('/interno/solicitacoes')->assertForbidden();
        $this->post('/interno/solicitacoes', $dados)->assertForbidden();
        $this->assertDatabaseCount('solicitacoes', 1);
    }

    public function test_public_operation_cannot_be_reused_for_internal_submission(): void
    {
        $dados = $this->dados($this->token()) + ['canal_origem' => 'telefone'];
        $this->actingAs(User::factory()->admin()->create())->post('/interno/solicitacoes', $dados)->assertStatus(419);
        $this->assertDatabaseCount('solicitacoes', 0);
    }

    #[DataProvider('deniedRoles')]
    public function test_unauthorized_roles_cannot_read_or_write_requests(string $role): void
    {
        $pedido = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/interno/solicitacoes')->assertForbidden();
        $this->get('/interno/solicitacoes/nova')->assertForbidden();
        $this->get('/interno/solicitacoes/'.$pedido->id)->assertForbidden()->assertDontSee($pedido->cpf);
        $this->get('/interno/solicitacoes/999999')->assertForbidden();
        $this->post('/interno/solicitacoes', [])->assertForbidden();
        $this->assertDatabaseCount('solicitacoes', 1);
    }

    public static function deniedRoles(): array
    {
        return ['donor' => ['doadora'], 'employee without grant' => ['funcionario']];
    }

    public function test_guest_is_redirected_from_private_routes(): void
    {
        $this->get('/interno/solicitacoes')->assertRedirect(route('login'));
        $this->get('/interno/solicitacoes/999')->assertRedirect(route('login'));
        $this->post('/interno/solicitacoes', [])->assertRedirect(route('login'));
    }

    public function test_active_matches_are_aggregated_and_archived_records_are_excluded(): void
    {
        $first = Solicitacao::factory()->create();
        $second = Solicitacao::factory()->create(['email_contato' => $first->email_contato]);
        $archived = Solicitacao::factory()->create(['status' => 'arquivada']);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->get('/interno/solicitacoes?com_correspondencias=1')->assertOk();
        $this->assertSame(2, $response->viewData('pedidos')->total());
        $this->assertSame([$first->id, $second->id], $response->viewData('pedidos')->pluck('id')->all());
        $matches = $this->get('/interno/solicitacoes/'.$first->id)->assertOk()->viewData('correspondencias');
        $this->assertCount(1, $matches);
        $this->assertSame(['CPF', 'Nome', 'E-mail', 'Telefone'], $matches->sole()['campos']);
        $this->assertSame($second->id, $matches->sole()['registro']->id);
        $this->assertCount(0, $archived->correspondenciasAtuais());

        $second->status = 'arquivada';
        $second->save();
        $this->assertSame(0, $this->get('/interno/solicitacoes?com_correspondencias=1')->viewData('pedidos')->total());
    }

    public function test_confirmed_identity_is_one_target_and_origin_is_not_duplicated(): void
    {
        $identity = Interessada::factory()->create();
        $origin = Solicitacao::factory()->for($identity, 'interessada')->create(['status' => 'admitida']);
        $active = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $matches = $this->get('/interno/solicitacoes/'.$active->id)->assertOk()->viewData('correspondencias');
        $this->assertCount(1, $matches);
        $this->assertSame('cadastro', $matches->sole()['tipo']);
        $this->assertSame($identity->id, $matches->sole()['registro']->id);
        $this->assertSame(1, $this->get('/interno/solicitacoes?com_correspondencias=1')->viewData('pedidos')->total());

        $active->interessada_id = $identity->id;
        $active->save();
        $this->assertCount(0, $active->correspondenciasAtuais());
        $this->assertSame(0, $this->get('/interno/solicitacoes?com_correspondencias=1')->viewData('pedidos')->total());
        $this->assertSame('admitida', $origin->fresh()->status);
    }

    public function test_cross_phone_match_and_empty_values_do_not_create_false_matches(): void
    {
        $first = Solicitacao::factory()->create(['cpf' => '11111111111', 'nome_normalizado' => 'primeira']);
        $other = Solicitacao::factory()->create([
            'cpf' => '22222222222', 'nome_normalizado' => 'segunda', 'telefone_celular' => null,
            'telefone_fixo' => $first->telefone_celular,
        ]);
        $this->assertSame(['Telefone'], $first->correspondenciasAtuais()->sole()['campos']);
        $other->telefone_fixo = '1433330000';
        $other->save();
        $this->assertCount(0, $first->correspondenciasAtuais());
    }

    public function test_queue_paginates_in_creation_order_and_rejects_arbitrary_sorting(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $registros = Solicitacao::factory()->count(26)->create();
        $response = $this->get('/interno/solicitacoes')->assertOk();
        $this->assertSame(26, $response->viewData('pedidos')->total());
        $this->assertCount(25, $response->viewData('pedidos')->items());
        $this->assertSame($registros->first()->id, $response->viewData('pedidos')->first()->id);
        $this->assertSame($registros->last()->id, $this->get('/interno/solicitacoes?ordem=recentes')->viewData('pedidos')->first()->id);
        $this->from('/interno/solicitacoes')->get('/interno/solicitacoes?ordem=cpf%20desc')->assertSessionHasErrors('ordem');
        $this->get('/interno/solicitacoes/9999')->assertNotFound();
    }

    public function test_email_match_is_case_insensitive_in_queue_and_detail(): void
    {
        $first = Solicitacao::factory()->create([
            'nome_normalizado' => 'primeira', 'cpf' => '11111111111',
            'telefone_celular' => '14999991111', 'email_contato' => 'MESMO@example.test',
        ]);
        Solicitacao::factory()->create([
            'nome_normalizado' => 'segunda', 'cpf' => '22222222222',
            'telefone_celular' => '14999992222', 'email_contato' => 'mesmo@example.test',
        ]);
        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame(2, $this->get('/interno/solicitacoes?com_correspondencias=1')->assertOk()->viewData('pedidos')->total());
        $this->assertSame(['E-mail'], $this->get('/interno/solicitacoes/'.$first->id)->assertOk()->viewData('correspondencias')->sole()['campos']);
        $this->assertSame('enviada', $first->fresh()->status);
    }

    public function test_identity_without_admitted_origin_does_not_generate_a_flag(): void
    {
        Interessada::factory()->create();
        $pedido = Solicitacao::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame(0, $this->get('/interno/solicitacoes?com_correspondencias=1')->assertOk()->viewData('pedidos')->total());
        $this->assertCount(0, $pedido->correspondenciasAtuais());
    }

    public function test_internal_form_requires_an_internal_channel_and_accepts_both_phones(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $dados = $this->dados($this->token('/interno/solicitacoes/nova'));
        $this->post('/interno/solicitacoes', $dados + ['canal_origem' => 'web'])->assertSessionHasErrors('canal_origem');
        $this->assertDatabaseCount('solicitacoes', 0);
        $this->post('/interno/solicitacoes', array_replace($dados, [
            'canal_origem' => 'presencial', 'telefone_fixo' => '1433330000',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('1433330000', Solicitacao::sole()->telefone_fixo);
        $this->assertSame('14999990000', Solicitacao::sole()->telefone_celular);
    }

    public function test_untrusted_text_is_stored_as_data_and_escaped_in_the_detail(): void
    {
        $payload = "<script>alert('teste')</script>";
        $dados = array_replace($this->dados($this->token()), ['nome_completo' => "Pessoa ' OR 1=1 --", 'outros_detalhes' => $payload]);
        $this->post('/pedidos-de-cadastro', $dados)->assertRedirect();
        $this->assertDatabaseCount('solicitacoes', 1);
        $this->assertSame($payload, Solicitacao::sole()->outros_detalhes);
        $this->actingAs(User::factory()->admin()->create())->get('/interno/solicitacoes/'.Solicitacao::sole()->id)
            ->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_audit_failure_rolls_back_submission(): void
    {
        $dados = $this->dados($this->token());
        EventoSolicitacao::creating(function (EventoSolicitacao $evento): void {
            throw new \RuntimeException('Falha de auditoria simulada');
        });
        try {
            $this->post('/pedidos-de-cadastro', $dados)->assertStatus(500);
            $this->assertDatabaseCount('solicitacoes', 0);
            $this->assertDatabaseCount('eventos_solicitacao', 0);
        } finally {
            EventoSolicitacao::flushEventListeners();
        }
    }

    public function test_csrf_rejects_missing_token_and_accepts_the_session_token(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $dados = $this->dados($this->token());
        $this->post('/pedidos-de-cadastro', $dados)->assertStatus(419);
        $this->assertDatabaseCount('solicitacoes', 0);
        $this->post('/pedidos-de-cadastro', $dados + ['_token' => session()->token()])
            ->assertRedirect(route('solicitacoes.recebida'));
        $this->assertDatabaseCount('solicitacoes', 1);
    }
}
