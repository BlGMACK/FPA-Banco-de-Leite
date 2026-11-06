@php
    $conflito = isset($falhas['conflito']);
    // Reaproveita a operação só no formulário que voltou com erro de validação, para reconhecer reenvio.
    $operacao = fn (string $acao): string => ! $conflito && ($entrada['acao'] ?? null) === $acao
        ? (string) ($entrada['operacao_id'] ?? Illuminate\Support\Str::uuid()) : (string) Illuminate\Support\Str::uuid();
    $valor = fn (string $acao, string $campo): string => ($entrada['acao'] ?? null) === $acao ? (string) ($entrada[$campo] ?? '') : '';
    $decisao = $doadora->decisao;
@endphp
<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.doadoras.index') }}">Voltar às doadoras</a>
        <h1 class="h3 mt-3">Habilitação #{{ $doadora->id }}</h1>
        <h2 class="h5">{{ $doadora->interessada->nome_completo }}</h2>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach
            @if ($conflito)<p class="mb-0"><a href="{{ route('interno.doadoras.show', $doadora) }}">Recarregar a situação atual</a></p>@endif</div>
            @if ($conflito && ($entrada['motivo'] ?? '') !== '')
                <label for="motivo-conflito" class="form-label">Motivo enviado e não registrado</label>
                <textarea id="motivo-conflito" class="form-control mb-3" rows="2" readonly>{{ $entrada['motivo'] }}</textarea>
            @endif
        @endif
        <section class="card mb-4"><div class="card-body">
            <h2 class="h4">Situação: {{ App\Models\Doadora::ESTADOS[$doadora->estado] }}
                @if ($doadora->vencida())<span class="badge text-bg-danger">Prazo vencido</span>@endif</h2>
            <p class="mb-1">Tipo: {{ App\Models\Doadora::TIPOS[$doadora->tipo] }}@if ($doadora->valida_ate), até {{ $doadora->valida_ate->format('d/m/Y') }}@endif.</p>
            @if ($doadora->condicao)<p class="mb-1 text-break" style="white-space: pre-wrap">Condição: {{ $doadora->condicao }}</p>@endif
            @if ($doadora->vencida())<p class="mb-1">O prazo venceu. A habilitação não termina sozinha: renove o prazo, converta em definitiva ou encerre.</p>@endif
            <p class="mb-0">Decidida por {{ $decisao->autor->name }} em {{ $decisao->decidido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                · plano de saúde: {{ $decisao->possui_plano_saude ? 'possui' : 'não possui' }}
                · <a href="{{ route('interno.avaliacoes.show', $doadora->avaliacao_id) }}">Abrir a avaliação #{{ $doadora->avaliacao_id }}</a></p>
        </div></section>

        @if ($escrita && ! $conflito)
            <section class="card mb-4"><div class="card-body">
                <h2 class="h4">Ações</h2>
                <p>Toda ação exige motivo e fica no histórico. Encerrar ou revogar termina a habilitação; uma nova habilitação depende de nova avaliação.</p>
                <div class="d-flex flex-wrap gap-4 align-items-start">
                    @foreach (array_filter(['suspensao' => $doadora->estado === 'ativa', 'retomada' => $doadora->estado === 'suspensa']) as $acao => $_)
                        <form method="POST" action="{{ route('interno.doadoras.eventos.store', $doadora) }}">@csrf
                            <input type="hidden" name="operacao_id" value="{{ $operacao($acao) }}"><input type="hidden" name="versao" value="{{ $doadora->versao }}"><input type="hidden" name="acao" value="{{ $acao }}">
                            <label for="motivo-{{ $acao }}" class="form-label">Motivo da {{ mb_strtolower(App\Models\EventoDoadora::ACOES[$acao]) }}</label>
                            <textarea class="form-control mb-2" id="motivo-{{ $acao }}" name="motivo" rows="2" maxlength="2000" required>{{ $valor($acao, 'motivo') }}</textarea>
                            <button class="btn btn-outline-primary">{{ $acao === 'suspensao' ? 'Suspender' : 'Retomar' }}</button>
                        </form>
                    @endforeach
                    @if ($doadora->tipo === 'temporaria')
                        <form method="POST" action="{{ route('interno.doadoras.eventos.store', $doadora) }}">@csrf
                            <input type="hidden" name="operacao_id" value="{{ $operacao('renovacao') }}"><input type="hidden" name="versao" value="{{ $doadora->versao }}"><input type="hidden" name="acao" value="renovacao">
                            <label for="valida-renovacao" class="form-label">Nova data-limite</label>
                            <input type="date" class="form-control mb-2" id="valida-renovacao" name="valida_ate" min="{{ now('America/Sao_Paulo')->addDay()->toDateString() }}" required value="{{ $valor('renovacao', 'valida_ate') }}">
                            <label for="condicao-renovacao" class="form-label">Condição</label>
                            <textarea class="form-control mb-2" id="condicao-renovacao" name="condicao" rows="2" maxlength="2000" required>{{ ($entrada['acao'] ?? null) === 'renovacao' ? ($entrada['condicao'] ?? '') : $doadora->condicao }}</textarea>
                            <label for="motivo-renovacao" class="form-label">Motivo da renovação</label>
                            <textarea class="form-control mb-2" id="motivo-renovacao" name="motivo" rows="2" maxlength="2000" required>{{ $valor('renovacao', 'motivo') }}</textarea>
                            <button class="btn btn-outline-primary">Renovar prazo</button>
                        </form>
                        <form method="POST" action="{{ route('interno.doadoras.eventos.store', $doadora) }}">@csrf
                            <input type="hidden" name="operacao_id" value="{{ $operacao('conversao_definitiva') }}"><input type="hidden" name="versao" value="{{ $doadora->versao }}"><input type="hidden" name="acao" value="conversao_definitiva">
                            <label for="motivo-conversao" class="form-label">Motivo para tornar definitiva</label>
                            <textarea class="form-control mb-2" id="motivo-conversao" name="motivo" rows="2" maxlength="2000" required>{{ $valor('conversao_definitiva', 'motivo') }}</textarea>
                            <button class="btn btn-outline-primary">Converter em definitiva</button>
                        </form>
                    @endif
                    @foreach (['encerramento' => 'Encerrar', 'revogacao' => 'Revogar'] as $acao => $botao)
                        <form method="POST" action="{{ route('interno.doadoras.eventos.store', $doadora) }}">@csrf
                            <input type="hidden" name="operacao_id" value="{{ $operacao($acao) }}"><input type="hidden" name="versao" value="{{ $doadora->versao }}"><input type="hidden" name="acao" value="{{ $acao }}">
                            <label for="motivo-{{ $acao }}" class="form-label">Motivo {{ $acao === 'revogacao' ? 'da revogação (revisão dos dados ou outra razão)' : 'do encerramento' }}</label>
                            <textarea class="form-control mb-2" id="motivo-{{ $acao }}" name="motivo" rows="2" maxlength="2000" required>{{ $valor($acao, 'motivo') }}</textarea>
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmacao_definitiva" id="confirmacao-{{ $acao }}" value="1" required>
                                <label class="form-check-label" for="confirmacao-{{ $acao }}">Confirmo que a habilitação termina e não pode ser retomada.</label></div>
                            <button class="btn btn-outline-danger">{{ $botao }}</button>
                        </form>
                    @endforeach
                </div>
            </div></section>
        @endif

        <h2 class="h4">Histórico da habilitação</h2>
        <ul class="list-group mb-3">
            @foreach ($eventos as $evento)
                <li class="list-group-item"><strong>{{ App\Models\EventoDoadora::ACOES[$evento->acao] }}</strong> · {{ $evento->autor->name }} · {{ $evento->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                    · {{ App\Models\Doadora::ESTADOS[$evento->estado_anterior] }} → {{ App\Models\Doadora::ESTADOS[$evento->estado_novo] }}
                    @if ($evento->valida_ate) · até {{ $evento->valida_ate->format('d/m/Y') }}@endif
                    @if ($evento->condicao)<div class="text-break" style="white-space: pre-wrap">Condição: {{ $evento->condicao }}</div>@endif
                    <div class="text-break" style="white-space: pre-wrap">{{ $evento->motivo }}</div>
                </li>
            @endforeach
            @if ($eventos->onLastPage())
                <li class="list-group-item"><strong>{{ App\Models\Avaliacao::ESTADOS[$decisao->resultado] }}</strong> · {{ $decisao->autor->name }} · {{ $decisao->decidido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · decisão de habilitação
                    @if ($decisao->valida_ate) · até {{ $decisao->valida_ate->format('d/m/Y') }}@endif
                    @if ($decisao->condicao)<div class="text-break" style="white-space: pre-wrap">Condição: {{ $decisao->condicao }}</div>@endif
                    @if ($decisao->motivo)<div class="text-break" style="white-space: pre-wrap">{{ $decisao->motivo }}</div>@endif
                </li>
            @endif
        </ul>
        {{ $eventos->links() }}
    </div>
</x-app-layout>
