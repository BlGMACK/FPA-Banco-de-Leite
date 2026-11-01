<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\EventoItemAvaliacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\TipoExame;
use App\Models\User;
use Database\Seeders\TipoExameSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExameOperationsTest extends TestCase
{
    use RefreshDatabase;

    /** CNS fictício com dígitos válidos. */
    private const CNS = '700000000000005';

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    private function acao(ItemAvaliacao $item, string $acao, array $extra = []): array
    {
        return ['operacao_id' => (string) Str::uuid(), 'versao' => $item->fresh()->versao, 'acao' => $acao, ...$extra];
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

    private function invalido(callable $callback, array $erros): void
    {
        try {
            $callback();
            $this->fail('Expected validation error.');
        } catch (ValidationException $exception) {
            $this->assertSame($erros, $exception->errors());
        }
    }

    public function test_seeded_catalog_is_idempotent_and_releasing_creates_one_item_per_active_type(): void
    {
        $this->seed(TipoExameSeeder::class);
        $this->seed(TipoExameSeeder::class);
        TipoExame::factory()->create(['ativo' => false]);
        $this->assertSame([TipoExame::EXAME_SANGUE, TipoExame::CNS], TipoExame::query()->where('ativo', true)->orderBy('ordem')->pluck('codigo')->all());

        $avaliacao = Avaliacao::factory()->create();
        $avaliacao->decidir(User::factory()->admin()->create(), ['operacao_id' => (string) Str::uuid(), 'versao' => 1,
            'resultado' => 'liberada_exames', 'confirmacao_definitiva' => '1']);

        $itens = $avaliacao->itens()->with('tipo')->get();
        $this->assertSame([TipoExame::EXAME_SANGUE, TipoExame::CNS], $itens->pluck('tipo.codigo')->all());
        $this->assertSame(['nao_solicitado'], $itens->pluck('estado')->unique()->values()->all());
        $this->assertDatabaseCount('eventos_item_avaliacao', 0);
    }

    public function test_waiting_and_not_eligible_do_not_create_items(): void
    {
        $this->seed(TipoExameSeeder::class);
        $avaliacao = Avaliacao::factory()->create();
        $avaliacao->decidir(User::factory()->admin()->create(), ['operacao_id' => (string) Str::uuid(), 'versao' => 1, 'resultado' => 'em_aguardo', 'motivo' => 'Espera']);

        $this->assertDatabaseCount('itens_avaliacao', 0);
    }

    public function test_seeder_adds_new_types_to_already_released_evaluations(): void
    {
        $item = ItemAvaliacao::factory()->create();

        $this->seed(TipoExameSeeder::class);

        $this->assertSame(3, $item->avaliacao->itens()->count());
    }

    public function test_full_cycle_with_complement_and_audit(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $user = User::factory()->admin()->create();

        $item->registrar($user, $this->acao($item, 'solicitacao'));
        $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => now('America/Sao_Paulo')->subDays(2)->toDateString()]));
        $item->registrar($user, $this->acao($item, 'complementacao', ['motivo' => 'Faltou a segunda página.']));
        $item->registrar($user, $this->acao($item, 'solicitacao'));
        $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => now('America/Sao_Paulo')->toDateString()]));
        $item->registrar($user, $this->acao($item, 'aceite'));

        $item->refresh();
        $this->assertSame('aceito', $item->estado);
        $this->assertSame(now('America/Sao_Paulo')->addDays(30)->toDateString(), $item->valido_ate->toDateString());
        $this->assertSame(7, $item->versao);
        $this->assertSame(['solicitacao', 'recebimento', 'complementacao', 'solicitacao', 'recebimento', 'aceite'],
            $item->eventos()->orderBy('id')->pluck('acao')->all());
        $this->assertSame('Faltou a segunda página.', $item->eventos()->where('acao', 'complementacao')->value('motivo'));
        $this->assertTrue(Avaliacao::exigenciasCumpridas($item->avaliacao->itens()->get()));
    }

    public function test_invalid_transitions_conflict(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $user = User::factory()->admin()->create();

        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'aceite')), 'O item precisa estar recebido.');
        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => '2026-10-01'])),
            'Só é possível registrar o recebimento de um item solicitado.');
        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'prorrogacao', ['motivo' => 'x'])), 'O item não está vencido.');
        $this->assertSame('nao_solicitado', $item->fresh()->estado);
        $this->assertDatabaseCount('eventos_item_avaliacao', 0);
    }

    public function test_cancel_only_before_any_receipt_and_with_reason(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $user = User::factory()->admin()->create();
        $item->registrar($user, $this->acao($item, 'solicitacao'));

        $this->invalido(fn () => $item->registrar($user, $this->acao($item, 'cancelamento_solicitacao')), ['motivo' => ['Informe o motivo.']]);
        $item->registrar($user, $this->acao($item, 'cancelamento_solicitacao', ['motivo' => 'Pedido por engano.']));
        $this->assertSame('nao_solicitado', $item->fresh()->estado);

        $item->registrar($user, $this->acao($item, 'solicitacao'));
        $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => now('America/Sao_Paulo')->toDateString()]));
        $item->registrar($user, $this->acao($item, 'complementacao', ['motivo' => 'Ilegível']));
        $item->registrar($user, $this->acao($item, 'solicitacao'));
        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'cancelamento_solicitacao', ['motivo' => 'Desistência'])),
            'O item já foi recebido antes; não volta a não solicitado.');
    }

    public function test_receipt_requires_past_date_for_exams(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $user = User::factory()->admin()->create();

        $this->invalido(fn () => $item->registrar($user, $this->acao($item, 'recebimento')), ['realizado_em' => ['Informe a data de realização ou coleta.']]);
        $this->invalido(fn () => $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => now('America/Sao_Paulo')->addDay()->toDateString()])),
            ['realizado_em' => ['A data de realização não pode estar no futuro.']]);
        $this->assertSame('solicitado', $item->fresh()->estado);
    }

    public function test_cns_requires_valid_number_and_never_expires(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado', 'tipo_exame_id' => TipoExame::factory()->cns()]);
        $user = User::factory()->admin()->create();

        $this->invalido(fn () => $item->registrar($user, $this->acao($item, 'recebimento', ['numero_documento' => '700000000000006'])),
            ['numero_documento' => ['Informe um número de CNS válido, com 15 dígitos.']]);
        $item->registrar($user, $this->acao($item, 'recebimento', ['numero_documento' => '700 0000 0000 0005']));
        $item->refresh();
        $this->assertSame(self::CNS, $item->numero_documento);
        $this->assertNull($item->valido_ate);
        $this->assertFalse($item->vencido());
    }

    public function test_cns_check_digits(): void
    {
        $this->assertTrue(ItemAvaliacao::cnsValido(self::CNS));
        $this->assertTrue(ItemAvaliacao::cnsValido('100000000000007'));
        $this->assertFalse(ItemAvaliacao::cnsValido('300000000000003'));
        $this->assertFalse(ItemAvaliacao::cnsValido('70000000000000'));
    }

    public function test_expired_item_can_be_extended_twice_then_only_reordered(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'aceito', 'realizado_em' => '2026-08-01', 'valido_ate' => '2026-08-31']);
        $user = User::factory()->admin()->create();
        $this->assertTrue($item->vencido());
        $this->assertFalse(Avaliacao::exigenciasCumpridas([$item]));

        $this->invalido(fn () => $item->registrar($user, $this->acao($item, 'prorrogacao')), ['motivo' => ['Informe o motivo.']]);
        $item->registrar($user, $this->acao($item, 'prorrogacao', ['motivo' => 'Aguardando laboratório.']));
        $item->registrar($user, $this->acao($item, 'prorrogacao', ['motivo' => 'Aguardando laboratório.']));
        $item->refresh();
        $this->assertSame('2026-10-30', $item->valido_ate->toDateString());
        $this->assertSame(2, $item->prorrogacoes);
        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'prorrogacao', ['motivo' => 'Mais uma'])),
            'Limite de prorrogações atingido. Peça um novo exame.');

        $item->registrar($user, $this->acao($item, 'novo_pedido'));
        $item->refresh();
        $this->assertSame(['solicitado', null, null, 0], [$item->estado, $item->realizado_em, $item->valido_ate, $item->prorrogacoes]);
        $this->assertSame(['prorrogacao', 'prorrogacao', 'novo_pedido'], $item->eventos()->orderBy('id')->pluck('acao')->all());
    }

    public function test_item_valid_until_today_is_not_expired(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'aceito', 'realizado_em' => now()->subDays(30), 'valido_ate' => now('America/Sao_Paulo')->toDateString()]);

        $this->assertFalse($item->vencido());
    }

    public function test_other_exam_is_included_as_requested_and_uses_default_validity(): void
    {
        $avaliacao = ItemAvaliacao::factory()->create()->avaliacao;
        $user = User::factory()->admin()->create();

        $this->invalido(fn () => ItemAvaliacao::incluirOutro($avaliacao, $user, ['operacao_id' => (string) Str::uuid(), 'nome_outro' => ' ']),
            ['nome_outro' => ['Informe o nome do exame.']]);
        $dados = ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'Ultrassom', 'detalhes_outro' => 'Pedido médico.'];
        $evento = ItemAvaliacao::incluirOutro($avaliacao, $user, $dados);
        $this->assertSame($evento->id, ItemAvaliacao::incluirOutro($avaliacao, $user, $dados)->id);
        $item = $evento->item;
        $this->assertSame(['Ultrassom', 'solicitado', null], [$item->nome(), $item->estado, $item->tipo_exame_id]);

        $item->registrar($user, $this->acao($item, 'recebimento', ['realizado_em' => '2026-10-20']));
        $this->assertSame('2026-11-19', $item->fresh()->valido_ate->toDateString());
        ItemAvaliacao::incluirOutro($avaliacao, $user, ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'Ultrassom']);
        $this->assertSame(2, $avaliacao->itens()->whereNull('tipo_exame_id')->count());
    }

    public function test_replay_is_idempotent_and_changed_replay_or_stale_version_conflicts(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $user = User::factory()->admin()->create();
        $dados = $this->acao($item, 'solicitacao');

        $evento = $item->registrar($user, $dados);
        $this->assertSame($evento->id, $item->registrar($user, $dados)->id);
        $this->conflito(fn () => $item->registrar($user, array_replace($dados, ['motivo' => 'Outro'])), 'Operação já utilizada com outros dados.');
        $this->conflito(fn () => $item->registrar($user, array_replace($this->acao($item, 'cancelamento_solicitacao', ['motivo' => 'x']), ['versao' => 1])),
            'O item mudou. Recarregue antes de continuar.');
        $this->assertDatabaseCount('eventos_item_avaliacao', 1);
    }

    #[DataProvider('closedStates')]
    public function test_exam_stage_must_be_open(string $estado): void
    {
        $item = ItemAvaliacao::factory()->create();
        $item->avaliacao->forceFill(['estado' => $estado])->save();
        $user = User::factory()->admin()->create();

        $this->conflito(fn () => $item->registrar($user, $this->acao($item, 'solicitacao')), 'A etapa de exames desta avaliação não está aberta.');
        $this->conflito(fn () => ItemAvaliacao::incluirOutro($item->avaliacao, $user, ['operacao_id' => (string) Str::uuid(), 'nome_outro' => 'X']),
            'A etapa de exames desta avaliação não está aberta.');
    }

    public static function closedStates(): array
    {
        return ['aguardando' => ['aguardando_analise'], 'em aguardo' => ['em_aguardo'], 'nao apta' => ['nao_apta']];
    }

    public static function accesses(): array
    {
        return [
            'none' => [[], false, false],
            'read exams' => [[Permissao::CONSULTAR_EXAMES], true, false],
            'register exams' => [[Permissao::REGISTRAR_EXAMES], true, true],
            'evaluate' => [[Permissao::AVALIAR_DOADORAS], true, false],
            'read evaluations' => [[Permissao::CONSULTAR_AVALIACOES], false, false],
        ];
    }

    #[DataProvider('accesses')]
    public function test_exam_permissions(array $codes, bool $read, bool $write): void
    {
        $user = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        foreach ($codes as $code) {
            Permissao::factory()->create(['codigo' => $code])->conceder($user, $admin, 'Teste');
        }

        $this->assertSame([$read, $write], [$user->can(Permissao::CONSULTAR_EXAMES), $user->can(Permissao::REGISTRAR_EXAMES)]);
        $this->assertSame([true, true], [$admin->can(Permissao::CONSULTAR_EXAMES), $admin->can(Permissao::REGISTRAR_EXAMES)]);
    }

    public function test_revoked_registrar_cannot_change_items(): void
    {
        $user = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        $permissao = Permissao::factory()->create(['codigo' => Permissao::REGISTRAR_EXAMES]);
        $permissao->conceder($user, $admin, 'Designação');
        $permissao->revogar($user, $admin, 'Fim da designação');
        $item = ItemAvaliacao::factory()->create();

        $this->expectException(AuthorizationException::class);
        $item->registrar($user, $this->acao($item, 'solicitacao'));
    }

    public function test_catalog_type_appears_once_per_evaluation(): void
    {
        $item = ItemAvaliacao::factory()->create();

        $this->expectException(QueryException::class);
        ItemAvaliacao::factory()->create(['avaliacao_id' => $item->avaliacao_id, 'tipo_exame_id' => $item->tipo_exame_id]);
    }

    public function test_rollback_is_refused_while_exam_history_exists(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $item->registrar(User::factory()->admin()->create(), $this->acao($item, 'solicitacao'));

        foreach (['2026_11_01_100002_create_eventos_item_avaliacao_table.php' => 'eventos de exames', '2026_11_01_100001_create_itens_avaliacao_table.php' => 'itens de exame'] as $arquivo => $texto) {
            try {
                (require database_path('migrations/'.$arquivo))->down();
                $this->fail('Expected rollback refusal.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($texto, $exception->getMessage());
            }
        }
        $this->assertSame(1, EventoItemAvaliacao::query()->count());
    }

    public function test_events_reject_update_and_delete(): void
    {
        $item = ItemAvaliacao::factory()->create();
        $evento = $item->registrar(User::factory()->admin()->create(), $this->acao($item, 'solicitacao'));

        foreach ([fn () => $evento->forceFill(['motivo' => 'x'])->save(), fn () => $evento->delete()] as $acao) {
            try {
                $acao();
                $this->fail('Expected immutable event.');
            } catch (\LogicException) {
            }
        }
        $this->assertSame(1, EventoItemAvaliacao::query()->count());
    }
}
