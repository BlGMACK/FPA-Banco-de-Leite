<?php

namespace Tests\Feature;

use App\Models\Atendimento;
use App\Models\Permissao;
use App\Models\RelatorioVisita;
use App\Models\User;
use App\Models\VersaoRelatorioVisita;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelatorioVisitaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    public static function accesses(): array
    {
        return [
            'admin' => ['admin', [], true, true],
            'none' => ['funcionario', [], false, false],
            'agenda' => ['funcionario', [Permissao::GERENCIAR_AGENDA], false, false],
            'attendance' => ['funcionario', [Permissao::ATENDER_SOLICITACOES], false, false],
            'read' => ['funcionario', [Permissao::CONSULTAR_RELATORIOS_VISITA], true, false],
            'write' => ['funcionario', [Permissao::REGISTRAR_RELATORIOS_VISITA], true, true],
            'both' => ['funcionario', [Permissao::CONSULTAR_RELATORIOS_VISITA, Permissao::REGISTRAR_RELATORIOS_VISITA], true, true],
        ];
    }

    #[DataProvider('accesses')]
    public function test_permissions_are_separate_and_admin_has_explicit_access(string $role, array $codes, bool $read, bool $write): void
    {
        $user = User::factory()->create(['role' => $role]);
        $admin = User::factory()->admin()->create();
        foreach ($codes as $code) {
            Permissao::factory()->create(['codigo' => $code])->conceder($user, $admin, 'Designação fictícia');
        }

        $this->assertSame($read, Gate::forUser($user)->allows(Permissao::CONSULTAR_RELATORIOS_VISITA));
        $this->assertSame($write, Gate::forUser($user)->allows(Permissao::REGISTRAR_RELATORIOS_VISITA));
        $this->assertFalse(Gate::forUser($user)->allows('aprovar-doacao'));
        $this->assertFalse(Gate::allows(Permissao::CONSULTAR_RELATORIOS_VISITA));
    }

    public function test_read_only_access_returns_publication_but_never_draft(): void
    {
        $user = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        Permissao::factory()->create(['codigo' => Permissao::CONSULTAR_RELATORIOS_VISITA])->conceder($user, $admin, 'Leitura');
        $version = VersaoRelatorioVisita::factory()->create(['texto' => 'Texto publicado']);
        $version->relatorio->forceFill(['estado' => 'em_correcao', 'texto_rascunho' => 'Texto reservado'])->save();

        $dados = RelatorioVisita::consultarVersao($version->relatorio_visita_id, 1, $user);
        $this->assertSame('Texto publicado', $dados['texto']);
        $this->assertArrayNotHasKey('texto_rascunho', $dados);
        $this->expectException(AuthorizationException::class);
        RelatorioVisita::consultarRascunho($version->relatorio_visita_id, $user);
    }

    public function test_revocation_blocks_replay_despite_loaded_concessions(): void
    {
        $user = User::factory()->funcionario()->create();
        $admin = User::factory()->admin()->create();
        $permission = Permissao::factory()->create(['codigo' => Permissao::REGISTRAR_RELATORIOS_VISITA]);
        $permission->conceder($user, $admin, 'Escrita');
        $report = RelatorioVisita::factory()->create();
        $data = ['operacao_id' => (string) Str::uuid(), 'revisao' => 1, 'texto' => 'Relato'];
        $report->salvarRascunho($user, $data);
        $user->load('concessoes');
        $permission->revogar($user, $admin, 'Revogado');

        $this->assertFalse($user->podeConsultarRelatoriosVisita());
        $this->expectException(AuthorizationException::class);
        $report->salvarRascunho($user, $data);
    }

    public function test_denies_unknown_record_before_disclosing_its_existence(): void
    {
        $this->expectException(AuthorizationException::class);
        RelatorioVisita::consultarVersao(999999, 1, User::factory()->funcionario()->create());
    }

    public function test_stale_admin_does_not_keep_access_after_role_change(): void
    {
        $admin = User::factory()->admin()->create();
        User::query()->whereKey($admin->id)->update(['role' => 'funcionario']);

        $this->assertFalse($admin->podeRegistrarRelatoriosVisita());
        $this->assertFalse($admin->podeConsultarRelatoriosVisita());
    }

    public static function writes(): array
    {
        return [['salvarRascunho'], ['finalizar'], ['iniciarCorrecao']];
    }

    #[DataProvider('writes')]
    public function test_reader_cannot_execute_writes_even_with_invalid_payload(string $action): void
    {
        $user = User::factory()->funcionario()->create();
        Permissao::factory()->create(['codigo' => Permissao::CONSULTAR_RELATORIOS_VISITA])
            ->conceder($user, User::factory()->admin()->create(), 'Consulta');
        $report = RelatorioVisita::factory()->create();

        $this->expectException(AuthorizationException::class);
        $report->{$action}($user, []);
    }

    public function test_agenda_permission_does_not_allow_recording_attendance(): void
    {
        $user = User::factory()->funcionario()->create();
        Permissao::factory()->create(['codigo' => Permissao::GERENCIAR_AGENDA])
            ->conceder($user, User::factory()->admin()->create(), 'Agenda');

        $this->expectException(AuthorizationException::class);
        Atendimento::registrar(999999, $user, []);
    }
}
