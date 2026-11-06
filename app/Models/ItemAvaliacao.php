<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ItemAvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemAvaliacao extends Model
{
    /** @use HasFactory<ItemAvaliacaoFactory> */
    use HasFactory;

    public const ESTADOS = ['nao_solicitado' => 'Não solicitado', 'solicitado' => 'Solicitado', 'recebido' => 'Recebido',
        'aceito' => 'Aceito', 'precisa_complementar' => 'Precisa complementar'];

    /** Validade de "Outro exame" e de tipos sem prazo próprio, contada da data de realização. */
    public const VALIDADE_PADRAO_DIAS = 30;

    public const DIAS_PRORROGACAO = 30;

    public const MAX_PRORROGACOES = 2;

    private const ACOES_COM_MOTIVO = ['cancelamento_solicitacao', 'complementacao', 'prorrogacao'];

    /** Estados em que o item aceita novos arquivos; aceito ou não solicitado não recebem anexos. */
    public const ESTADOS_COM_ANEXO = ['solicitado', 'recebido', 'precisa_complementar'];

    protected $table = 'itens_avaliacao';

    /** Cria, em "não solicitado", os itens dos tipos ativos que a avaliação ainda não tem; deve rodar na transação que libera os exames. */
    public static function criarParaAvaliacao(int $avaliacaoId): void
    {
        $existentes = static::query()->where('avaliacao_id', $avaliacaoId)->whereNotNull('tipo_exame_id')->pluck('tipo_exame_id')->all();
        TipoExame::query()->where('ativo', true)->whereNotIn('id', $existentes)->orderBy('ordem')->orderBy('id')
            ->each(function (TipoExame $tipo) use ($avaliacaoId): void {
                $item = new static;
                $item->forceFill(['avaliacao_id' => $avaliacaoId, 'tipo_exame_id' => $tipo->id, 'estado' => 'nao_solicitado', 'versao' => 1])->save();
            });
    }

    /** Dígitos do Cartão Nacional de Saúde: 15 posições, início 1, 2, 7, 8 ou 9 e soma ponderada (15 a 1) múltipla de 11. */
    public static function cnsValido(string $numero): bool
    {
        if (preg_match('/^[12789]\d{14}$/', $numero) !== 1) {
            return false;
        }
        $soma = 0;
        for ($posicao = 0; $posicao < 15; $posicao++) {
            $soma += (int) $numero[$posicao] * (15 - $posicao);
        }

        return $soma % 11 === 0;
    }

    public static function hoje(): string
    {
        return CarbonImmutable::now('America/Sao_Paulo')->toDateString();
    }

    public function nome(): string
    {
        return $this->tipo?->nome ?? (string) $this->nome_outro;
    }

    /** Recebido ou aceito com validade anterior a hoje; só sinaliza, sem mudar o estado. */
    public function vencido(): bool
    {
        return $this->valido_ate !== null && in_array($this->estado, ['recebido', 'aceito'], true)
            && $this->valido_ate->toDateString() < static::hoje();
    }

    public function exigeNumero(): bool
    {
        return (bool) $this->tipo?->exige_numero;
    }

    /** O CNS é registrado só pelo número, sem imagem (minimização acordada em 29/10/2026). */
    public function aceitaAnexo(): bool
    {
        return ! $this->exigeNumero() && in_array($this->estado, self::ESTADOS_COM_ANEXO, true);
    }

    /**
     * Guarda o arquivo no disco privado "anexos" com nome gerado e registra o anexo; nada é sobrescrito nem apagado.
     *
     * @param  array<string, mixed>  $entrada
     */
    public function anexar(User $autor, array $entrada): AnexoItemAvaliacao
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'],
            'arquivo' => ['required', 'file', 'max:'.AnexoItemAvaliacao::TAMANHO_MAXIMO_KB, 'mimetypes:'.implode(',', array_keys(AnexoItemAvaliacao::TIPOS))],
        ], ['arquivo.required' => 'Escolha um arquivo.', 'arquivo.file' => 'O envio do arquivo falhou. Tente novamente.',
            'arquivo.max' => 'O arquivo deve ter no máximo 10 MB.', 'arquivo.mimetypes' => 'Envie um arquivo PDF, JPEG ou PNG.'])->validate();
        /** @var UploadedFile $arquivo */
        $arquivo = $dados['arquivo'];
        $tipo = (string) $arquivo->getMimeType();
        $extensao = AnexoItemAvaliacao::TIPOS[$tipo] ?? throw ValidationException::withMessages(['arquivo' => 'Envie um arquivo PDF, JPEG ou PNG.']);
        $nome = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $arquivo->getClientOriginalName()))));
        $nome = mb_substr($nome === '' ? 'arquivo.'.$extensao : $nome, 0, 255);
        $sha256 = (string) hash_file('sha256', $arquivo->getRealPath());
        $hash = hash('sha256', json_encode(['anexo', $this->getKey(), $autor->id, $dados['operacao_id'], $sha256], JSON_THROW_ON_ERROR));
        $gravado = null;

        try {
            return DB::transaction(function () use ($autor, $dados, $arquivo, $tipo, $extensao, $nome, $sha256, $hash, &$gravado): AnexoItemAvaliacao {
                $referencia = static::query()->findOrFail($this->getKey());
                $avaliacao = Avaliacao::query()->lockForUpdate()->findOrFail($referencia->avaliacao_id);
                $item = static::query()->with('tipo')->lockForUpdate()->findOrFail($this->getKey());
                Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
                $anterior = AnexoItemAvaliacao::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($avaliacao->estado === 'liberada_exames', 409, 'A etapa de exames desta avaliação não está aberta.');
                abort_if($item->exigeNumero(), 409, 'O CNS é registrado só pelo número, sem imagem.');
                abort_unless($item->aceitaAnexo(), 409, 'Anexe arquivos a itens solicitados, recebidos ou que precisam complementar.');
                $gravado = Storage::disk('anexos')->putFileAs((string) $avaliacao->id, $arquivo, Str::uuid().'.'.$extensao);
                $anexo = new AnexoItemAvaliacao;
                $anexo->forceFill(['item_avaliacao_id' => $item->id, 'caminho' => $gravado, 'nome_original' => $nome, 'tipo_conteudo' => $tipo,
                    'tamanho' => $arquivo->getSize(), 'sha256' => $sha256, 'enviado_por_user_id' => $autor->id, 'enviado_em' => now()->utc(),
                    'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash])->save();

                return $anexo;
            });
        } catch (UniqueConstraintViolationException $exception) {
            static::descartarArquivoOrfao($gravado);
            if (! AnexoItemAvaliacao::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        } catch (\Throwable $exception) {
            static::descartarArquivoOrfao($gravado);
            throw $exception;
        }
    }

    /** Remove apenas o arquivo gravado por uma transação que não chegou a registrá-lo. */
    private static function descartarArquivoOrfao(?string $caminho): void
    {
        if ($caminho !== null && ! AnexoItemAvaliacao::query()->where('caminho', $caminho)->exists()) {
            Storage::disk('anexos')->delete($caminho);
        }
    }

    /** @param array<string, mixed> $entrada */
    public static function incluirOutro(Avaliacao $avaliacao, User $autor, array $entrada): EventoItemAvaliacao
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, ['operacao_id' => ['required', 'uuid'], 'nome_outro' => ['required', 'string', 'max:150'],
            'detalhes_outro' => ['nullable', 'string', 'max:2000']], ['nome_outro.required' => 'Informe o nome do exame.'])->validate();
        $dados['detalhes_outro'] = ($dados['detalhes_outro'] ?? '') === '' ? null : $dados['detalhes_outro'];
        $hash = hash('sha256', json_encode(['inclusao', $avaliacao->getKey(), $autor->id, $dados], JSON_THROW_ON_ERROR));

        return static::comReenvio($dados['operacao_id'], function () use ($avaliacao, $autor, $dados, $hash): EventoItemAvaliacao {
            $referencia = Avaliacao::query()->lockForUpdate()->findOrFail($avaliacao->getKey());
            Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
            if ($anterior = static::reenvio($dados['operacao_id'], $hash)) {
                return $anterior;
            }
            abort_unless($referencia->estado === 'liberada_exames', 409, 'A etapa de exames desta avaliação não está aberta.');
            $item = new static;
            $item->forceFill(['avaliacao_id' => $referencia->id, 'nome_outro' => $dados['nome_outro'], 'detalhes_outro' => $dados['detalhes_outro'],
                'estado' => 'solicitado', 'versao' => 1])->save();

            return $item->registrarEvento('inclusao', null, $autor, $dados['operacao_id'], $hash, null, null);
        });
    }

    /** @param array<string, mixed> $entrada */
    public function registrar(User $autor, array $entrada): EventoItemAvaliacao
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'acao' => ['required', 'in:'.implode(',', array_keys(array_diff_key(EventoItemAvaliacao::ACOES, ['inclusao' => true])))],
            'motivo' => ['nullable', 'string', 'max:2000'], 'realizado_em' => ['nullable', 'date_format:Y-m-d'],
            'numero_documento' => ['nullable', 'string', 'max:30'],
        ], ['acao.required' => 'Escolha a ação.', 'acao.in' => 'Ação inválida.', 'realizado_em.date_format' => 'Informe uma data válida.'])->validate();
        $dados['versao'] = (int) $dados['versao'];
        $dados['motivo'] = ($dados['motivo'] ?? '') === '' ? null : $dados['motivo'];
        $recebimento = $dados['acao'] === 'recebimento';
        $dados['realizado_em'] = $recebimento && ($dados['realizado_em'] ?? '') !== '' ? $dados['realizado_em'] : null;
        $dados['numero_documento'] = $recebimento ? preg_replace('/\D/', '', (string) ($dados['numero_documento'] ?? '')) : '';
        $dados['numero_documento'] = $dados['numero_documento'] === '' ? null : $dados['numero_documento'];
        if (in_array($dados['acao'], self::ACOES_COM_MOTIVO, true) && $dados['motivo'] === null) {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo.']);
        }
        $hash = hash('sha256', json_encode([$dados['acao'], $this->getKey(), $autor->id, $dados], JSON_THROW_ON_ERROR));

        return static::comReenvio($dados['operacao_id'], function () use ($autor, $dados, $hash): EventoItemAvaliacao {
            $referencia = static::query()->findOrFail($this->getKey());
            $avaliacao = Avaliacao::query()->lockForUpdate()->findOrFail($referencia->avaliacao_id);
            $item = static::query()->with('tipo')->lockForUpdate()->findOrFail($this->getKey());
            Gate::forUser($autor)->authorize(Permissao::REGISTRAR_EXAMES);
            if ($anterior = static::reenvio($dados['operacao_id'], $hash)) {
                return $anterior;
            }
            abort_unless($avaliacao->estado === 'liberada_exames', 409, 'A etapa de exames desta avaliação não está aberta.');
            abort_unless($item->versao === $dados['versao'], 409, 'O item mudou. Recarregue antes de continuar.');
            $estadoAnterior = $item->estado;
            $item->aplicar($dados);
            $item->versao++;
            $item->save();

            return $item->registrarEvento($dados['acao'], $estadoAnterior, $autor, $dados['operacao_id'], $hash, $dados['motivo'], $dados['versao']);
        });
    }

    /** @param array<string, mixed> $dados */
    private function aplicar(array $dados): void
    {
        $exigir = fn (array $estados, string $mensagem) => abort_unless(in_array($this->estado, $estados, true), 409, $mensagem);
        match ($dados['acao']) {
            'solicitacao' => $exigir(['nao_solicitado', 'precisa_complementar'], 'Só é possível solicitar um item não solicitado ou que precisa complementar.'),
            'cancelamento_solicitacao' => $exigir(['solicitado'], 'Só é possível cancelar um item solicitado.'),
            'recebimento' => $exigir(['solicitado'], 'Só é possível registrar o recebimento de um item solicitado.'),
            'aceite', 'complementacao' => $exigir(['recebido'], 'O item precisa estar recebido.'),
            'prorrogacao', 'novo_pedido' => abort_unless($this->vencido(), 409, 'O item não está vencido.'),
        };

        switch ($dados['acao']) {
            case 'solicitacao':
                $this->estado = 'solicitado';
                break;
            case 'cancelamento_solicitacao':
                abort_if($this->eventos()->where('acao', 'recebimento')->exists(), 409, 'O item já foi recebido antes; não volta a não solicitado.');
                $this->estado = 'nao_solicitado';
                break;
            case 'recebimento':
                $this->receber($dados);
                break;
            case 'aceite':
                $this->estado = 'aceito';
                break;
            case 'complementacao':
                $this->estado = 'precisa_complementar';
                break;
            case 'prorrogacao':
                abort_if($this->prorrogacoes >= self::MAX_PRORROGACOES, 409, 'Limite de prorrogações atingido. Peça um novo exame.');
                $this->valido_ate = $this->valido_ate->addDays(self::DIAS_PRORROGACAO);
                $this->prorrogacoes++;
                break;
            case 'novo_pedido':
                $this->forceFill(['estado' => 'solicitado', 'realizado_em' => null, 'valido_ate' => null, 'prorrogacoes' => 0]);
                break;
        }
    }

    /** @param array<string, mixed> $dados */
    private function receber(array $dados): void
    {
        if ($this->exigeNumero()) {
            if ($dados['numero_documento'] === null || ! static::cnsValido($dados['numero_documento'])) {
                throw ValidationException::withMessages(['numero_documento' => 'Informe um número de CNS válido, com 15 dígitos.']);
            }
            $this->forceFill(['estado' => 'recebido', 'numero_documento' => $dados['numero_documento']]);

            return;
        }
        if ($dados['realizado_em'] === null) {
            throw ValidationException::withMessages(['realizado_em' => 'Informe a data de realização ou coleta.']);
        }
        if ($dados['realizado_em'] > static::hoje()) {
            throw ValidationException::withMessages(['realizado_em' => 'A data de realização não pode estar no futuro.']);
        }
        $dias = $this->tipo === null ? self::VALIDADE_PADRAO_DIAS : $this->tipo->validade_dias;
        $realizado = CarbonImmutable::createFromFormat('!Y-m-d', $dados['realizado_em']);
        $this->forceFill(['estado' => 'recebido', 'realizado_em' => $realizado, 'valido_ate' => $dias === null ? null : $realizado->addDays($dias)]);
    }

    private function registrarEvento(string $acao, ?string $estadoAnterior, User $autor, string $operacaoId, string $hash, ?string $motivo, ?int $versao): EventoItemAvaliacao
    {
        $evento = new EventoItemAvaliacao;
        $evento->forceFill(['item_avaliacao_id' => $this->id, 'acao' => $acao, 'estado_anterior' => $estadoAnterior, 'estado_novo' => $this->estado,
            'motivo' => $motivo, 'realizado_em' => $this->realizado_em, 'valido_ate' => $this->valido_ate, 'autor_user_id' => $autor->id,
            'ocorrido_em' => now()->utc(), 'operacao_id' => $operacaoId, 'conteudo_hash' => $hash, 'versao_analisada' => $versao])->save();

        return $evento;
    }

    private static function reenvio(string $operacaoId, string $hash): ?EventoItemAvaliacao
    {
        $anterior = EventoItemAvaliacao::query()->where('operacao_id', $operacaoId)->lockForUpdate()->first();
        abort_if($anterior && ! hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

        return $anterior;
    }

    /** @param callable(): EventoItemAvaliacao $operacao */
    private static function comReenvio(string $operacaoId, callable $operacao): EventoItemAvaliacao
    {
        try {
            return DB::transaction($operacao);
        } catch (UniqueConstraintViolationException $exception) {
            if (! EventoItemAvaliacao::query()->where('operacao_id', $operacaoId)->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    protected function casts(): array
    {
        return ['realizado_em' => 'immutable_date', 'valido_ate' => 'immutable_date', 'prorrogacoes' => 'integer', 'versao' => 'integer'];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoExame::class, 'tipo_exame_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoItemAvaliacao::class, 'item_avaliacao_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AnexoItemAvaliacao::class, 'item_avaliacao_id');
    }
}
