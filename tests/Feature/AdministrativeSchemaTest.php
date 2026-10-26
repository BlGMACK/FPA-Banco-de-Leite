<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdministrativeSchemaTest extends TestCase
{
    use DatabaseMigrations;

    private int $sequence = 0;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $configuration = config('database.connections.'.$connection, []);
        $inMemory = $connection === 'sqlite' && ($configuration['database'] ?? null) === ':memory:';
        $isolatedMysql = $connection === 'mysql'
            && ($configuration['database'] ?? null) === 'banco_de_leite_schema_test'
            && in_array($configuration['host'] ?? null, ['127.0.0.1', 'localhost'], true);

        if (! app()->environment('testing')
            || app()->configurationIsCached()
            || ! empty($configuration['url'])
            || (! $inMemory && ! $isolatedMysql)) {
            throw new \RuntimeException('Schema tests require SQLite :memory: or the local banco_de_leite_schema_test database in testing, without cached configuration or a database URL.');
        }

        if ($isolatedMysql && DB::connection()->getDatabaseName() !== 'banco_de_leite_schema_test') {
            throw new \RuntimeException('Unexpected test database connection.');
        }
    }

    public function test_public_requests_accept_repeated_identity_data_without_creating_accounts(): void
    {
        $first = $this->request();
        $second = $this->request();

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('solicitacoes', 2);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('interessadas', 0);
        $this->assertDatabaseHas('solicitacoes', [
            'id' => $second,
            'cpf' => '00000000000',
            'interessada_id' => null,
            'registrada_por_user_id' => null,
            'status' => 'enviada',
            'versao' => 1,
        ]);
    }

    public function test_the_same_submission_operation_cannot_create_a_second_request(): void
    {
        $this->request(['operacao_id' => '00000000-0000-4000-8000-000000000001']);

        $this->expectException(QueryException::class);

        $this->request(['operacao_id' => '00000000-0000-4000-8000-000000000001']);
    }

    public function test_request_protocols_are_unique_even_for_different_operations(): void
    {
        $this->request(['protocolo' => '00000000-0000-4000-8000-000000000002']);

        $this->expectException(QueryException::class);

        $this->request(['protocolo' => '00000000-0000-4000-8000-000000000002']);
    }

    public function test_a_request_cannot_reference_a_missing_confirmed_identity(): void
    {
        $this->expectException(QueryException::class);

        $this->request(['interessada_id' => 999999]);
    }

    public function test_the_administrative_schema_does_not_accept_a_clinical_approval_status(): void
    {
        $this->expectException(QueryException::class);

        $this->request(['status' => 'aprovada']);
    }

    public function test_one_contact_can_cover_several_requests_but_not_the_same_request_twice(): void
    {
        $user = User::factory()->create();
        $contact = $this->contact($user->id);
        $first = $this->request();
        $second = $this->request();

        DB::table('contato_pedidos')->insert([
            ['contato_id' => $contact, 'solicitacao_id' => $first],
            ['contato_id' => $contact, 'solicitacao_id' => $second],
        ]);

        $this->assertDatabaseCount('contato_pedidos', 2);
        $this->expectException(QueryException::class);

        DB::table('contato_pedidos')->insert(['contato_id' => $contact, 'solicitacao_id' => $first]);
    }

    public function test_a_contact_cannot_be_associated_with_a_missing_request(): void
    {
        $contact = $this->contact(User::factory()->create()->id);

        $this->expectException(QueryException::class);

        DB::table('contato_pedidos')->insert(['contato_id' => $contact, 'solicitacao_id' => 999999]);
    }

    public function test_a_resolution_preserves_contact_principal_archived_request_and_identity_references(): void
    {
        $records = $this->resolutionHistory();

        $this->assertDatabaseHas('resolucoes_solicitacao', [
            'id' => $records['resolucoes_solicitacao'],
            'contato_id' => $records['contatos_solicitacao'],
            'principal_id' => $records['solicitacoes'],
            'responsavel_user_id' => $records['users'],
        ]);
        $this->assertDatabaseHas('resolucao_pedidos', [
            'resolucao_id' => $records['resolucoes_solicitacao'],
            'solicitacao_id' => $records['arquivada'],
            'papel' => 'arquivado',
            'versao_analisada' => 1,
        ]);
        $this->assertDatabaseHas('resolucao_cadastros', [
            'resolucao_id' => $records['resolucoes_solicitacao'],
            'interessada_id' => $records['interessadas'],
        ]);
        $this->assertDatabaseHas('eventos_solicitacao', [
            'solicitacao_id' => $records['arquivada'],
            'resolucao_id' => $records['resolucoes_solicitacao'],
            'estado_novo' => 'arquivada',
        ]);
    }

    public function test_a_resolution_operation_cannot_be_recorded_twice(): void
    {
        $records = $this->resolutionHistory();
        $resolution = (array) DB::table('resolucoes_solicitacao')->find($records['resolucoes_solicitacao']);
        unset($resolution['id']);

        $this->expectException(QueryException::class);

        DB::table('resolucoes_solicitacao')->insert($resolution);
    }

    public function test_a_resolution_cannot_reference_a_missing_contact(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('resolucoes_solicitacao')->insert($this->resolutionData($user->id, 999999));
    }

    #[DataProvider('historicalParents')]
    public function test_deleting_referenced_history_or_authorship_is_restricted(string $table): void
    {
        $records = $this->resolutionHistory();

        $this->expectException(QueryException::class);

        DB::table($table)->where('id', $records[$table])->delete();
    }

    public static function historicalParents(): array
    {
        return [
            'author' => ['users'],
            'confirmed identity' => ['interessadas'],
            'original request' => ['solicitacoes'],
            'contact evidence' => ['contatos_solicitacao'],
            'resolution evidence' => ['resolucoes_solicitacao'],
        ];
    }

    public function test_a_resolution_cannot_include_the_same_request_twice(): void
    {
        $records = $this->resolutionHistory();

        $this->expectException(QueryException::class);

        DB::table('resolucao_pedidos')->insert([
            'resolucao_id' => $records['resolucoes_solicitacao'],
            'solicitacao_id' => $records['solicitacoes'],
            'papel' => 'principal',
            'versao_analisada' => 1,
        ]);
    }

    public function test_a_permission_cannot_be_granted_twice_to_the_same_user(): void
    {
        $user = User::factory()->create();
        $permission = DB::table('permissoes')->insertGetId(['codigo' => 'atender-solicitacoes', 'nome' => 'Atendimento']);
        $grant = [
            'user_id' => $user->id,
            'permissao_id' => $permission,
            'concedida_por_user_id' => $user->id,
            'concedida_em' => '2026-10-24 10:00:00',
        ];
        DB::table('permissao_user')->insert($grant);

        $this->expectException(QueryException::class);

        DB::table('permissao_user')->insert($grant);
    }

    public function test_revocation_preserves_the_grant_and_permission_history(): void
    {
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $permission = DB::table('permissoes')->insertGetId(['codigo' => 'atender-solicitacoes', 'nome' => 'Atendimento']);
        DB::table('permissao_user')->insert([
            'user_id' => $employee->id,
            'permissao_id' => $permission,
            'concedida_por_user_id' => $admin->id,
            'concedida_em' => '2026-10-24 10:00:00',
        ]);
        DB::table('eventos_permissao')->insert([
            'user_id' => $employee->id,
            'permissao_id' => $permission,
            'autor_user_id' => $admin->id,
            'acao' => 'concessao',
            'motivo' => 'Equipe de atendimento',
            'ocorrido_em' => '2026-10-24 10:00:00',
        ]);

        DB::table('permissao_user')->where('user_id', $employee->id)->update([
            'revogada_por_user_id' => $admin->id,
            'revogada_em' => '2026-10-24 11:00:00',
        ]);
        DB::table('eventos_permissao')->insert([
            'user_id' => $employee->id,
            'permissao_id' => $permission,
            'autor_user_id' => $admin->id,
            'acao' => 'revogacao',
            'motivo' => 'Mudança de atribuição',
            'ocorrido_em' => '2026-10-24 11:00:00',
        ]);

        $this->assertDatabaseCount('eventos_permissao', 2);
        $this->assertDatabaseHas('permissao_user', [
            'user_id' => $employee->id,
            'concedida_por_user_id' => $admin->id,
            'concedida_em' => '2026-10-24 10:00:00',
            'revogada_por_user_id' => $admin->id,
        ]);
        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $admin->id)->delete();
    }

    public function test_a_permission_history_cannot_reference_a_missing_permission(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('eventos_permissao')->insert([
            'user_id' => $user->id,
            'permissao_id' => 999999,
            'autor_user_id' => $user->id,
            'acao' => 'concessao',
            'motivo' => 'Teste de integridade',
            'ocorrido_em' => '2026-10-24 10:00:00',
        ]);
    }

    public function test_rolling_back_and_reapplying_only_administrative_migrations_preserves_existing_users(): void
    {
        $user = User::factory()->create();
        $dependentes = glob(database_path('migrations/2026_10_26_*'));
        sort($dependentes);
        foreach (array_reverse($dependentes) as $arquivo) {
            (require $arquivo)->down();
        }
        $files = [
            '2026_10_24_045949_create_interessadas_table.php',
            '2026_10_24_045952_create_solicitacoes_table.php',
            '2026_10_24_045955_create_contatos_solicitacao_tables.php',
            '2026_10_24_045958_create_resolucoes_solicitacao_tables.php',
            '2026_10_24_050002_create_eventos_solicitacao_table.php',
            '2026_10_24_050005_create_permissoes_atendimento_tables.php',
        ];

        foreach (array_reverse($files) as $file) {
            (require database_path('migrations/'.$file))->down();
        }

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertFalse(Schema::hasTable('solicitacoes'));
        $this->assertFalse(Schema::hasTable('resolucoes_solicitacao'));
        $this->assertFalse(Schema::hasTable('permissoes'));

        foreach ($files as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        $request = $this->request(['registrada_por_user_id' => $user->id, 'canal_origem' => 'presencial']);
        $this->assertDatabaseHas('solicitacoes', ['id' => $request, 'registrada_por_user_id' => $user->id]);
        foreach ($dependentes as $arquivo) {
            (require $arquivo)->up();
        }
    }

    private function request(array $overrides = []): int
    {
        $this->sequence++;

        return DB::table('solicitacoes')->insertGetId(array_replace($this->identityData(), [
            'protocolo' => sprintf('10000000-0000-4000-8000-%012d', $this->sequence),
            'operacao_id' => sprintf('20000000-0000-4000-8000-%012d', $this->sequence),
            'conteudo_hash' => str_repeat('a', 64),
            'dados_originais' => json_encode(['nome_completo' => 'Pessoa Fictícia']),
            'canal_origem' => 'web',
            'enviada_em' => '2026-10-24 10:00:00',
        ], $overrides));
    }

    private function identityData(): array
    {
        return [
            'nome_completo' => 'Pessoa Fictícia',
            'nome_normalizado' => 'pessoa ficticia',
            'cpf' => '00000000000',
            'telefone_celular' => '14999990000',
            'email_contato' => 'pessoa@example.test',
            'municipio' => 'Marília',
            'bairro' => 'Bairro de Teste',
            'uf' => 'SP',
            'cep' => '00000000',
            'logradouro' => 'Rua de Teste',
            'numero' => 's/n',
            'whatsapp_telefone' => 'celular',
            'whatsapp_disponivel' => true,
            'whatsapp_declarado_em' => '2026-10-24 10:00:00',
        ];
    }

    private function contact(int $userId): int
    {
        return DB::table('contatos_solicitacao')->insertGetId([
            'registrado_por_user_id' => $userId,
            'canal' => 'whatsapp',
            'resultado' => 'realizado',
            'destinatario' => '14999990000',
            'finalidade' => 'Conferir repetição',
            'resumo' => 'Interessada esclareceu os pedidos.',
            'identidade_conferida' => true,
            'duvida_esclarecida' => true,
            'ocorrido_em' => '2026-10-24 11:00:00',
        ]);
    }

    private function resolutionData(int $userId, int $contactId): array
    {
        return [
            'operacao_id' => '30000000-0000-4000-8000-000000000001',
            'conteudo_hash' => str_repeat('b', 64),
            'tipo' => 'admitir_principal',
            'contato_id' => $contactId,
            'responsavel_user_id' => $userId,
            'motivo' => 'Repetição esclarecida no contato.',
            'ocorrido_em' => '2026-10-24 11:05:00',
        ];
    }

    /** @return array<string, int> */
    private function resolutionHistory(): array
    {
        $user = User::factory()->create();
        $identity = DB::table('interessadas')->insertGetId(array_merge($this->identityData(), [
            'confirmada_por_user_id' => $user->id,
            'confirmada_em' => '2026-10-24 11:00:00',
        ]));
        $primary = $this->request([
            'interessada_id' => $identity,
            'status' => 'admitida',
            'admitida_por_user_id' => $user->id,
            'admitida_em' => '2026-10-24 11:05:00',
        ]);
        $archived = $this->request(['status' => 'arquivada', 'arquivada_em' => '2026-10-24 11:05:00']);
        $contact = $this->contact($user->id);
        DB::table('contato_pedidos')->insert([
            ['contato_id' => $contact, 'solicitacao_id' => $primary],
            ['contato_id' => $contact, 'solicitacao_id' => $archived],
        ]);
        $resolution = DB::table('resolucoes_solicitacao')->insertGetId(array_merge(
            $this->resolutionData($user->id, $contact),
            ['principal_id' => $primary],
        ));
        DB::table('resolucao_pedidos')->insert([
            ['resolucao_id' => $resolution, 'solicitacao_id' => $primary, 'papel' => 'principal', 'versao_analisada' => 1],
            ['resolucao_id' => $resolution, 'solicitacao_id' => $archived, 'papel' => 'arquivado', 'versao_analisada' => 1],
        ]);
        DB::table('resolucao_cadastros')->insert([
            'resolucao_id' => $resolution,
            'interessada_id' => $identity,
            'versao_analisada' => 1,
        ]);
        DB::table('eventos_solicitacao')->insert([
            'solicitacao_id' => $archived,
            'autor_user_id' => $user->id,
            'resolucao_id' => $resolution,
            'tipo' => 'arquivamento',
            'estado_anterior' => 'em_analise',
            'estado_novo' => 'arquivada',
            'motivo' => 'Pedido repetido.',
            'ocorrido_em' => '2026-10-24 11:05:00',
        ]);

        return [
            'users' => $user->id,
            'interessadas' => $identity,
            'solicitacoes' => $primary,
            'arquivada' => $archived,
            'contatos_solicitacao' => $contact,
            'resolucoes_solicitacao' => $resolution,
        ];
    }
}
