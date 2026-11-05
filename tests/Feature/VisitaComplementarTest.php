<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Atendimento;
use App\Models\Avaliacao;
use App\Models\ContatoSolicitacao;
use App\Models\RelatorioVisita;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VisitaComplementarTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00:00', 'UTC'));
    }

    private function decisao(Avaliacao $avaliacao, string $resultado): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $avaliacao->fresh()->versao, 'resultado' => $resultado,
            'motivo' => 'Aguardando nova visita fictícia.', 'confirmacao_definitiva' => '1'];
    }

    private function emAguardo(User $admin): Avaliacao
    {
        $avaliacao = Avaliacao::factory()->create();
        $avaliacao->decidir($admin, $this->decisao($avaliacao, 'em_aguardo'));

        return $avaliacao->fresh();
    }

    private function pedido(Avaliacao $avaliacao): Solicitacao
    {
        return $avaliacao->atendimento->agendamento->solicitacao->fresh();
    }

    private function confirmar(Solicitacao $pedido, User $admin): Agendamento
    {
        $contato = ContatoSolicitacao::factory()->create(['contexto' => 'acompanhamento', 'resultado' => 'realizado']);
        $contato->solicitacoes()->attach($pedido);
        $evento = Agendamento::confirmar($pedido->id, $admin, ['operacao_id' => (string) Str::uuid(), 'versao' => $pedido->fresh()->versao,
            'motivo' => 'Nova visita combinada', 'contato_id' => $contato->id, 'responsavel_user_id' => User::factory()->funcionario()->create()->id,
            'modalidade' => 'domicilio', 'local_atendimento' => 'Local fictício', 'inicio_previsto_em' => '2030-01-02T09:00',
            'fim_previsto_em' => '2030-01-02T10:00', 'combinado_confirmado' => true]);

        return Agendamento::query()->findOrFail($evento->agendamento_id);
    }

    private function registrarVisita(Agendamento $agenda, User $admin): RelatorioVisita
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-02 15:00:00', 'UTC'));
        $evento = Atendimento::registrar($agenda->id, $admin, ['operacao_id' => (string) Str::uuid(), 'versao' => $agenda->fresh()->versao,
            'inicio_efetivo_em' => '2030-01-02T09:00', 'fim_efetivo_em' => '2030-01-02T10:00', 'responsavel_user_id' => $agenda->responsavel_user_id,
            'modalidade' => $agenda->modalidade, 'local_atendimento' => $agenda->local_atendimento, 'realizacao_confirmada' => true]);

        return $evento->relatorio;
    }

    private function publicar(RelatorioVisita $relatorio, User $admin): void
    {
        $relatorio->salvarRascunho($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'texto' => 'Relato complementar fictício.']);
        $relatorio->finalizar($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 2]);
    }

    private function conflito(callable $callback, string $mensagem): void
    {
        try {
            $callback();
            $this->fail('Expected conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame($mensagem, $exception->getMessage());
        }
    }

    public function test_waiting_evaluation_accepts_complementary_visit_whose_report_joins_the_same_evaluation(): void
    {
        $admin = User::factory()->admin()->create();
        $avaliacao = $this->emAguardo($admin);
        $pedido = $this->pedido($avaliacao);

        $agenda = $this->confirmar($pedido, $admin);
        $this->assertSame('confirmado', $agenda->status);
        $this->conflito(fn () => $avaliacao->decidir($admin, $this->decisao($avaliacao, 'liberada_exames')),
            'Há visita complementar marcada. Registre a visita e publique o relatório, ou cancele o compromisso, antes de decidir.');

        $relatorio = $this->registrarVisita($agenda, $admin);
        $this->conflito(fn () => $avaliacao->decidir($admin, $this->decisao($avaliacao, 'liberada_exames')),
            'O relatório da visita complementar ainda não foi publicado. Decida depois que ele for finalizado.');
        $this->assertFalse($pedido->aceitaVisita());

        $this->publicar($relatorio, $admin);
        $this->assertDatabaseCount('avaliacoes', 1);
        $this->assertSame('em_aguardo', $avaliacao->fresh()->estado);

        $decisao = $avaliacao->decidir($admin, $this->decisao($avaliacao, 'liberada_exames'));
        $this->assertSame($relatorio->id, $decisao->versaoRelatorio->relatorio_visita_id);
        $this->assertSame('liberada_exames', $avaliacao->fresh()->estado);
        $this->conflito(fn () => $relatorio->fresh()->iniciarCorrecao($admin, ['operacao_id' => (string) Str::uuid(), 'revisao' => 3, 'motivo' => 'Ajuste']),
            'A análise deste relatório já foi concluída. O relatório não pode mais ser corrigido.');
    }

    public function test_complementary_visit_not_yet_analyzed_blocks_another_visit(): void
    {
        $admin = User::factory()->admin()->create();
        $avaliacao = $this->emAguardo($admin);
        $this->publicar($this->registrarVisita($this->confirmar($this->pedido($avaliacao), $admin), $admin), $admin);

        $this->assertFalse($this->pedido($avaliacao)->aceitaVisita());
        $this->conflito(fn () => $this->confirmar($this->pedido($avaliacao), $admin),
            'Este acompanhamento já possui visita realizada. Uma nova visita só pode ser marcada com a avaliação em aguardo e o último relatório analisado.');

        $avaliacao->decidir($admin, $this->decisao($avaliacao, 'em_aguardo'));
        $this->assertTrue($this->pedido($avaliacao)->aceitaVisita());
    }

    public function test_evaluation_without_waiting_decision_rejects_new_visit(): void
    {
        $admin = User::factory()->admin()->create();
        $aguardandoAnalise = Avaliacao::factory()->create();
        $liberada = Avaliacao::factory()->create();
        $liberada->decidir($admin, $this->decisao($liberada, 'liberada_exames'));

        foreach ([$aguardandoAnalise, $liberada] as $avaliacao) {
            $this->assertFalse($this->pedido($avaliacao)->aceitaVisita());
            $this->conflito(fn () => $this->confirmar($this->pedido($avaliacao), $admin),
                'Este acompanhamento já possui visita realizada. Uma nova visita só pode ser marcada com a avaliação em aguardo e o último relatório analisado.');
        }
        $this->assertDatabaseCount('agendamentos', 2);
    }

    public function test_screens_offer_complementary_visit_and_explain_blocked_decision(): void
    {
        $admin = User::factory()->admin()->create();
        $avaliacao = $this->emAguardo($admin);
        $pedido = $this->pedido($avaliacao);

        $this->actingAs($admin)->get(route('interno.avaliacoes.show', $avaliacao))->assertOk()
            ->assertSee('agende uma visita complementar')->assertSee('Iniciar análise');
        $this->get(route('interno.agenda.index'))->assertOk()
            ->assertViewHas('pendentes', fn ($pendentes): bool => $pendentes->pluck('id')->all() === [$pedido->id])
            ->assertSee('visita complementar (avaliação em aguardo)');
        $this->get(route('interno.agenda.acompanhamentos.show', $pedido))->assertOk()->assertSee('Confirmar visita complementar');

        $this->confirmar($pedido, $admin);
        $this->get(route('interno.avaliacoes.show', $avaliacao))->assertOk()
            ->assertSee('Há visita complementar marcada.')->assertDontSee('Iniciar análise')->assertDontSee('agende uma visita complementar');
    }
}
