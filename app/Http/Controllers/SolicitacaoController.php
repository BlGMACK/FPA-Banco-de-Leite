<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitacaoRequest;
use App\Models\EventoSolicitacao;
use App\Models\Solicitacao;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SolicitacaoController extends Controller
{
    public function create(Request $request): View
    {
        $interno = $request->routeIs('interno.*');
        $request->session()->put('solicitacoes.chave', $request->session()->get('solicitacoes.chave', Str::random(40)));
        $token = Crypt::encryptString(json_encode([
            'id' => (string) Str::uuid(),
            'sessao' => $request->session()->get('solicitacoes.chave'),
            'escopo' => $interno ? 'interno:'.$request->user()->id : 'publico',
            'expira' => now()->addHours(2)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return view('solicitacoes.create', ['interno' => $interno, 'token' => $token]);
    }

    public function store(StoreSolicitacaoRequest $request): RedirectResponse
    {
        $interno = $request->routeIs('interno.*');
        try {
            $operacao = json_decode(Crypt::decryptString($request->validated('operacao_token')), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(419, 'Formulário inválido ou expirado. Abra um novo formulário.');
        }
        abort_unless(is_array($operacao)
            && ($operacao['sessao'] ?? null) === $request->session()->get('solicitacoes.chave')
            && ($operacao['escopo'] ?? null) === ($interno ? 'interno:'.$request->user()->id : 'publico')
            && ($operacao['expira'] ?? 0) >= now()->timestamp
            && Str::isUuid($operacao['id'] ?? ''), 419, 'Formulário inválido ou expirado. Abra um novo formulário.');

        $dados = [];
        foreach (StoreSolicitacaoRequest::CAMPOS as $campo) {
            $dados[$campo] = $request->validated($campo);
        }
        $dados['whatsapp_disponivel'] = true;
        $dados['canal_origem'] = $interno ? $request->validated('canal_origem') : 'web';
        $dados['registrada_por_user_id'] = $interno ? $request->user()->id : null;
        $hash = hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR));
        $pedido = Solicitacao::query()->where('operacao_id', $operacao['id'])->first();

        if (! $pedido) {
            try {
                $pedido = DB::transaction(function () use ($dados, $hash, $operacao): Solicitacao {
                    $pedido = new Solicitacao;
                    $pedido->forceFill($dados + [
                        'operacao_id' => $operacao['id'], 'conteudo_hash' => $hash,
                        'protocolo' => (string) Str::uuid(), 'status' => 'enviada',
                        'nome_normalizado' => Str::lower(Str::ascii(Str::squish($dados['nome_completo']))),
                        'dados_originais' => $dados, 'whatsapp_declarado_em' => now(),
                        'enviada_em' => now(), 'versao' => 1,
                    ])->save();
                    $evento = new EventoSolicitacao;
                    $evento->forceFill([
                        'solicitacao_id' => $pedido->id, 'autor_user_id' => $dados['registrada_por_user_id'],
                        'tipo' => 'recebimento', 'estado_novo' => 'enviada',
                        'dados_novos' => ['status' => 'enviada', 'canal_origem' => $dados['canal_origem']],
                        'ocorrido_em' => now(),
                    ])->save();

                    return $pedido;
                });
            } catch (UniqueConstraintViolationException $exception) {
                $pedido = Solicitacao::query()->where('operacao_id', $operacao['id'])->first();
                if (! $pedido) {
                    throw $exception;
                }
            }
        }
        abort_unless(hash_equals($pedido->conteudo_hash, $hash), 409, 'Este formulário já foi enviado com outros dados. Abra um novo formulário.');
        if ($interno) {
            return redirect()->route('interno.solicitacoes.show', $pedido)->with('status', 'pedido-recebido');
        }

        return redirect()->route('solicitacoes.recebida')->with('pedido_recebido', true);
    }

    public function recebida(): View
    {
        return view('solicitacoes.recebida');
    }

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'situacao' => ['sometimes', Rule::in(['ativos', 'arquivados', 'todos'])],
            'ordem' => ['sometimes', Rule::in(['antigos', 'recentes'])],
            'com_correspondencias' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]) + ['situacao' => 'ativos', 'ordem' => 'antigos', 'com_correspondencias' => false];
        $consulta = Solicitacao::query()->select('solicitacoes.*');
        if ($filtros['situacao'] === 'ativos') {
            $consulta->whereIn('status', ['enviada', 'em_analise']);
        } elseif ($filtros['situacao'] === 'arquivados') {
            $consulta->where('status', 'arquivada');
        }
        $consulta->selectSub(Solicitacao::consultaCorrespondencias(false)->limit(1), 'correspondencia_pedido')
            ->selectSub(Solicitacao::consultaCorrespondencias(true)->limit(1), 'correspondencia_cadastro');
        if ($filtros['com_correspondencias']) {
            $consulta->where(function ($query): void {
                $query->whereExists(Solicitacao::consultaCorrespondencias(false))
                    ->orWhereExists(Solicitacao::consultaCorrespondencias(true));
            });
        }
        $direcao = $filtros['ordem'] === 'recentes' ? 'desc' : 'asc';
        $pedidos = $consulta->orderBy('created_at', $direcao)->orderBy('id', $direcao)->paginate(25);
        $pedidos->appends($filtros);

        return view('solicitacoes.index', compact('pedidos', 'filtros'));
    }

    public function show(Solicitacao $solicitacao): View
    {
        return view('solicitacoes.show', [
            'pedido' => $solicitacao,
            'correspondencias' => $solicitacao->correspondenciasAtuais(),
        ]);
    }
}
