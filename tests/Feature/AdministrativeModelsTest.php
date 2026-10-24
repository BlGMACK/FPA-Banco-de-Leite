<?php

namespace Tests\Feature;

use App\Models\ConcessaoPermissao;
use App\Models\ContatoSolicitacao;
use App\Models\EventoPermissao;
use App\Models\EventoSolicitacao;
use App\Models\Interessada;
use App\Models\Permissao;
use App\Models\ResolucaoSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrativeModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public function test_administrative_history_can_be_traversed_without_creating_a_donor_account(): void
    {
        $employee = User::factory()->funcionario()->create();
        $identity = Interessada::factory()->for($employee, 'confirmadaPor')->create();
        $request = Solicitacao::factory()->for($identity, 'interessada')
            ->for($employee, 'registradaPor')->for($employee, 'admitidaPor')->create([
                'status' => 'admitida', 'admitida_em' => now(),
            ]);
        $contact = ContatoSolicitacao::factory()->for($employee, 'registradoPor')->create([
            'resultado' => 'realizado', 'identidade_conferida' => true, 'duvida_esclarecida' => true,
        ]);
        $contact->solicitacoes()->attach($request);
        $resolution = ResolucaoSolicitacao::factory()->for($employee, 'responsavel')
            ->for($contact, 'contato')->for($request, 'principal')->create(['tipo' => 'admitir']);
        $resolution->solicitacoes()->attach($request, ['papel' => 'principal', 'versao_analisada' => 1]);
        $resolution->interessadas()->attach($identity, ['versao_analisada' => 1, 'evidencias' => json_encode(['cpf' => true])]);
        $event = EventoSolicitacao::factory()->for($request, 'solicitacao')
            ->for($employee, 'autor')->for($resolution, 'resolucao')->create([
                'tipo' => 'admissao', 'dados_anteriores' => ['status' => 'em_analise'],
                'dados_novos' => ['status' => 'admitida'],
            ]);

        $this->assertTrue($request->interessada->is($identity));
        $this->assertTrue($identity->confirmadaPor->is($employee));
        $this->assertTrue($identity->solicitacoes->sole()->is($request));
        $this->assertTrue($request->registradaPor->is($employee));
        $this->assertTrue($request->admitidaPor->is($employee));
        $this->assertTrue($request->contatos->sole()->is($contact));
        $this->assertTrue($contact->registradoPor->is($employee));
        $this->assertTrue($contact->solicitacoes->sole()->is($request));
        $this->assertTrue($contact->resolucoes->sole()->is($resolution));
        $this->assertTrue($request->resolucoes->sole()->is($resolution));
        $this->assertSame('principal', $resolution->solicitacoes->sole()->pivot->papel);
        $this->assertTrue($resolution->interessadas->sole()->is($identity));
        $this->assertTrue($identity->resolucoes->sole()->is($resolution));
        $this->assertTrue($resolution->principal->is($request));
        $this->assertTrue($resolution->contato->is($contact));
        $this->assertTrue($resolution->responsavel->is($employee));
        $this->assertTrue($resolution->eventos->sole()->is($event));
        $this->assertTrue($request->eventos->sole()->is($event));
        $this->assertTrue($event->solicitacao->is($request));
        $this->assertTrue($event->resolucao->is($resolution));
        $this->assertTrue($event->autor->is($employee));
        $this->assertSame(['status' => 'admitida'], $event->fresh()->dados_novos);
        $this->assertSame(['nome_completo' => 'Pessoa Fictícia'], $request->fresh()->dados_originais);
        $this->assertTrue($contact->fresh()->duvida_esclarecida);
        $this->assertTrue($identity->fresh()->whatsapp_disponivel);
        $this->assertInstanceOf(CarbonImmutable::class, $resolution->fresh()->ocorrido_em);
        $this->assertInstanceOf(CarbonImmutable::class, $request->fresh()->admitida_em);
        $this->assertSame(1, $identity->fresh()->versao);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_public_request_and_event_do_not_require_an_internal_author(): void
    {
        $request = Solicitacao::factory()->create();
        $event = EventoSolicitacao::factory()->for($request, 'solicitacao')->create();

        $this->assertNull($request->registradaPor);
        $this->assertNull($request->interessada);
        $this->assertNull($event->autor);
        $this->assertNull($event->resolucao);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_permission_models_preserve_authors_dates_and_history(): void
    {
        $grant = ConcessaoPermissao::factory()->create();
        $event = EventoPermissao::factory()->for($grant->usuario, 'usuario')
            ->for($grant->permissao, 'permissao')->for($grant->concedidaPor, 'autor')->create();

        $this->assertTrue($grant->usuario->concessoes->sole()->is($grant));
        $this->assertTrue($grant->permissao->concessoes->sole()->is($grant));
        $this->assertTrue($grant->permissao->eventos->sole()->is($event));
        $this->assertTrue($grant->usuario->eventosPermissao->sole()->is($event));
        $this->assertTrue($event->autor->is($grant->concedidaPor));
        $this->assertNull($grant->revogadaPor);
        $this->assertInstanceOf(CarbonImmutable::class, $grant->fresh()->concedida_em);
        $this->assertInstanceOf(CarbonImmutable::class, $event->fresh()->ocorrido_em);
    }

    public function test_request_technical_fields_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new Solicitacao(['status' => 'admitida', 'admitida_por_user_id' => 1]);
    }

    public function test_permissions_cannot_be_mass_assigned_from_input(): void
    {
        $this->expectException(MassAssignmentException::class);

        new Permissao(['codigo' => 'gerenciar-permissoes']);
    }
}
