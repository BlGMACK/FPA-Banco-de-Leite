<?php

namespace Tests\Feature;

use App\Models\AnexoItemAvaliacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use App\Models\TipoExame;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AnexoExameTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function beforeRefreshingDatabase(): void
    {
        $this->requireIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('anexos');
    }

    /** Arquivo com conteúdo real: o tipo é detectado pelo conteúdo, não pelo nome. */
    private function arquivo(string $nome, string $conteudo = self::PDF): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'anexo');
        file_put_contents($caminho, $conteudo);

        return new UploadedFile($caminho, $nome, null, null, true);
    }

    private function dados(UploadedFile $arquivo, ?string $operacao = null): array
    {
        return ['operacao_id' => $operacao ?? (string) Str::uuid(), 'arquivo' => $arquivo];
    }

    private function user(string $code = Permissao::REGISTRAR_EXAMES): User
    {
        $user = User::factory()->funcionario()->create();
        $permission = Permissao::query()->where('codigo', $code)->first() ?? Permissao::factory()->create(['codigo' => $code]);
        $permission->conceder($user, User::factory()->admin()->create(), 'Teste');

        return $user;
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

    public function test_pdf_and_png_are_stored_privately_with_generated_names(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $admin = User::factory()->admin()->create();

        $pdf = $item->anexar($admin, $this->dados($this->arquivo('Exame Maria.pdf')));
        $png = $item->anexar($admin, $this->dados($this->arquivo('foto.png', base64_decode(self::PNG))));

        $this->assertMatchesRegularExpression('#^'.$item->avaliacao_id.'/[0-9a-f-]{36}\.pdf$#', $pdf->caminho);
        $this->assertMatchesRegularExpression('#\.png$#', $png->caminho);
        Storage::disk('anexos')->assertExists([$pdf->caminho, $png->caminho]);
        $this->assertSame(['Exame Maria.pdf', 'application/pdf', strlen(self::PDF), hash('sha256', self::PDF)],
            [$pdf->nome_original, $pdf->tipo_conteudo, $pdf->tamanho, $pdf->sha256]);
        $this->assertSame('image/png', $png->tipo_conteudo);
        $this->assertSame(1, $item->fresh()->versao);
    }

    public function test_replay_is_idempotent_and_reused_operation_with_other_file_conflicts(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'recebido']);
        $admin = User::factory()->admin()->create();
        $operacao = (string) Str::uuid();

        $anexo = $item->anexar($admin, $this->dados($this->arquivo('a.pdf'), $operacao));
        $this->assertSame($anexo->id, $item->anexar($admin, $this->dados($this->arquivo('a.pdf'), $operacao))->id);
        $this->conflito(fn () => $item->anexar($admin, $this->dados($this->arquivo('b.pdf', self::PDF.'outro'), $operacao)),
            'Operação já utilizada com outros dados.');

        $this->assertCount(1, Storage::disk('anexos')->allFiles());
        $this->assertSame(1, AnexoItemAvaliacao::query()->count());
    }

    public function test_content_type_size_and_presence_are_validated(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $admin = User::factory()->admin()->create();
        $casos = [
            'executável com nome de PDF' => [$this->arquivo('exame.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff".str_repeat("\x00", 64).'PE'), 'Envie um arquivo PDF, JPEG ou PNG.'],
            'HTML com nome de imagem' => [$this->arquivo('foto.png', '<html><script>alert(1)</script></html>'), 'Envie um arquivo PDF, JPEG ou PNG.'],
            'maior que 10 MB' => [UploadedFile::fake()->create('grande.pdf', AnexoItemAvaliacao::TAMANHO_MAXIMO_KB + 1, 'application/pdf'), 'O arquivo deve ter no máximo 10 MB.'],
        ];
        foreach ($casos as $caso => [$arquivo, $mensagem]) {
            try {
                $item->anexar($admin, $this->dados($arquivo));
                $this->fail('Expected validation error: '.$caso);
            } catch (ValidationException $exception) {
                $this->assertSame(['arquivo' => [$mensagem]], $exception->errors(), $caso);
            }
        }
        $this->expectException(ValidationException::class);
        $item->anexar($admin, ['operacao_id' => (string) Str::uuid()]);
    }

    public function test_original_name_cannot_manipulate_the_storage_path(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);

        $anexo = $item->anexar(User::factory()->admin()->create(), $this->dados($this->arquivo("..\\..\\etc/pass\x01wd.pdf")));

        $this->assertSame('passwd.pdf', $anexo->nome_original);
        $this->assertStringNotContainsString('..', $anexo->caminho);
        $this->assertStringNotContainsString('passwd', $anexo->caminho);
    }

    public function test_state_rules_reject_without_leaving_files(): void
    {
        $admin = User::factory()->admin()->create();
        $mensagem = 'Anexe arquivos a itens solicitados, recebidos ou que precisam complementar.';
        foreach (['nao_solicitado', 'aceito'] as $estado) {
            $item = ItemAvaliacao::factory()->create(['estado' => $estado]);
            $this->conflito(fn () => $item->anexar($admin, $this->dados($this->arquivo('a.pdf'))), $mensagem);
        }
        $cns = ItemAvaliacao::factory()->create(['estado' => 'solicitado', 'tipo_exame_id' => TipoExame::factory()->cns()]);
        $this->conflito(fn () => $cns->anexar($admin, $this->dados($this->arquivo('cns.pdf'))), 'O CNS é registrado só pelo número, sem imagem.');
        $fechado = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $fechado->avaliacao->forceFill(['estado' => 'em_aguardo'])->save();
        $this->conflito(fn () => $fechado->anexar($admin, $this->dados($this->arquivo('a.pdf'))), 'A etapa de exames desta avaliação não está aberta.');

        $this->assertSame([], Storage::disk('anexos')->allFiles());
        $this->assertSame(0, AnexoItemAvaliacao::query()->count());
    }

    public function test_reader_and_revoked_registrar_cannot_attach(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $user = $this->user();
        Permissao::query()->where('codigo', Permissao::REGISTRAR_EXAMES)->first()->revogar($user, User::factory()->admin()->create(), 'Fim');

        foreach ([$user, $this->user(Permissao::CONSULTAR_EXAMES)] as $semEscrita) {
            try {
                $item->anexar($semEscrita, $this->dados($this->arquivo('a.pdf')));
                $this->fail('Expected authorization error.');
            } catch (AuthorizationException) {
            }
        }
        $this->assertSame([], Storage::disk('anexos')->allFiles());
    }

    public function test_attachments_are_immutable_and_rollback_is_refused(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $anexo = $item->anexar(User::factory()->admin()->create(), $this->dados($this->arquivo('a.pdf')));

        foreach ([fn () => $anexo->forceFill(['nome_original' => 'x.pdf'])->save(), fn () => $anexo->delete()] as $acao) {
            try {
                $acao();
                $this->fail('Expected immutable attachment.');
            } catch (\LogicException) {
            }
        }
        try {
            (require database_path('migrations/2026_11_01_200000_create_anexos_item_avaliacao_table.php'))->down();
            $this->fail('Expected rollback refusal.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('anexos de exames', $exception->getMessage());
        }
        Storage::disk('anexos')->assertExists($anexo->caminho);
    }

    public function test_upload_form_and_list_on_exam_page(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado', 'tipo_exame_id' => TipoExame::factory()->state(['nome' => 'Exame de sangue'])]);
        $user = $this->user();
        $url = '/interno/exames/'.$item->avaliacao_id;

        $this->actingAs($user)->get($url)->assertSee('Anexar arquivo (PDF, JPEG ou PNG, até 10 MB)')->assertSee('enctype="multipart/form-data"', false);
        $this->post('/interno/exames/itens/'.$item->id.'/anexos', $this->dados($this->arquivo('<b>laudo.pdf')))
            ->assertRedirect($url)->assertSessionHas('status', 'Arquivo “<b>laudo.pdf” anexado a “Exame de sangue”.');
        $this->get($url)->assertSee('&lt;b&gt;laudo.pdf', false)->assertDontSee('<b>laudo', false)
            ->assertSee('Abrir')->assertSee('baixar=1', false);

        $this->post('/interno/exames/itens/'.$item->id.'/anexos', $this->dados($this->arquivo('foto.png', '<html></html>')))
            ->assertStatus(422)->assertSee('Envie um arquivo PDF, JPEG ou PNG.');
        $this->post('/interno/exames/itens/'.$item->id.'/anexos', ['operacao_id' => (string) Str::uuid()])
            ->assertStatus(422)->assertSee('Escolha um arquivo.');
    }

    public function test_form_is_hidden_where_attachments_are_not_accepted(): void
    {
        $aceito = ItemAvaliacao::factory()->create(['estado' => 'aceito']);
        $cns = ItemAvaliacao::factory()->create(['avaliacao_id' => $aceito->avaliacao_id, 'estado' => 'solicitado', 'tipo_exame_id' => TipoExame::factory()->cns()]);

        $this->actingAs($this->user())->get('/interno/exames/'.$aceito->avaliacao_id)->assertOk()->assertDontSee('Anexar arquivo');
        $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES))->get('/interno/exames/'.ItemAvaliacao::factory()->create(['estado' => 'solicitado'])->avaliacao_id)
            ->assertDontSee('Anexar arquivo');
        $this->assertSame('solicitado', $cns->estado);
    }

    public function test_download_requires_permission_and_sends_safe_headers(): void
    {
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);
        $anexo = $item->anexar(User::factory()->admin()->create(), $this->dados($this->arquivo('Exame Maria.pdf')));
        $url = '/interno/exames/anexos/'.$anexo->id;

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user(Permissao::CONSULTAR_AVALIACOES))->get($url)->assertForbidden();

        $response = $this->actingAs($this->user(Permissao::CONSULTAR_EXAMES))->get($url)->assertOk();
        $this->assertSame(self::PDF, $response->streamedContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline; filename="Exame Maria.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', $this->get($url.'?baixar=1')->headers->get('Content-Disposition'));

        Storage::disk('anexos')->delete($anexo->caminho);
        $this->get($url)->assertNotFound();
        $this->get('/interno/exames/anexos/999999')->assertNotFound();
    }

    public function test_csrf_protects_upload(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $item = ItemAvaliacao::factory()->create(['estado' => 'solicitado']);

        $this->actingAs($this->user())->post('/interno/exames/itens/'.$item->id.'/anexos', $this->dados($this->arquivo('a.pdf')))->assertStatus(419);
        $this->assertSame([], Storage::disk('anexos')->allFiles());
    }
}
