@php
    $conflito = isset($falhas['conflito']);
    // Reaproveita a operação só no formulário que voltou com erro de validação, para reconhecer reenvio.
    $operacao = fn (string $form, ?string $acao = null): string => ! $conflito && $formulario === $form && ($acao === null || ($entrada['acao'] ?? null) === $acao)
        ? (string) ($entrada['operacao_id'] ?? Illuminate\Support\Str::uuid()) : (string) Illuminate\Support\Str::uuid();
    $valor = fn (string $form, string $acao, string $campo): string => $formulario === $form && ($entrada['acao'] ?? null) === $acao ? (string) ($entrada[$campo] ?? '') : '';
    $hoje = App\Models\ItemAvaliacao::hoje();
@endphp
<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.exames.index') }}">Voltar aos exames</a>
        <h1 class="h3 mt-3">Exames e documentos</h1>
        <h2 class="h5">{{ $avaliacao->atendimento->agendamento->solicitacao->interessada->nome_completo }}</h2>
        <p>Visita em {{ $avaliacao->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $avaliacao->atendimento->responsavel->name }}.
            Avaliação #{{ $avaliacao->id }}: {{ App\Models\Avaliacao::ESTADOS[$avaliacao->estado] }}.
            @if ($leituraAvaliacao)<a href="{{ route('interno.avaliacoes.show', $avaliacao) }}">Abrir a avaliação</a>@endif
        </p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($falhas && ($formulario === null || ($formulario === 'outro' && ! $escrita)))<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach</div>@endif
        @if ($avaliacao->estado !== 'liberada_exames')
            <div class="alert alert-secondary">A decisão de habilitação foi registrada ({{ App\Models\Avaliacao::ESTADOS[$avaliacao->estado] }}). Os exames ficam disponíveis só para consulta.</div>
        @elseif (App\Models\Avaliacao::exigenciasCumpridas($itens))
            <div class="alert alert-success">Exigências cumpridas: todos os itens solicitados foram aceitos e nenhum está vencido.
                A decisão de habilitação é registrada na avaliação. @if ($leituraAvaliacao)<a href="{{ route('interno.avaliacoes.show', $avaliacao) }}#habilitacao">Abrir a avaliação</a>@endif</div>
        @else
            <div class="alert alert-info">Exigências pendentes: é preciso ao menos um item solicitado, todos os solicitados aceitos e nenhum vencido. Itens não solicitados não bloqueiam.</div>
        @endif

        @foreach ($itens as $item)
            @php $form = 'item-'.$item->id; @endphp
            <section class="card mb-3" id="{{ $form }}"><div class="card-body">
                <h2 class="h5">{{ $item->nome() }}
                    <span class="badge text-bg-secondary">{{ App\Models\ItemAvaliacao::ESTADOS[$item->estado] }}</span>
                    @if ($item->vencido())<span class="badge text-bg-danger">Vencido</span>@endif
                </h2>
                @if ($item->detalhes_outro)<p class="text-break" style="white-space: pre-wrap">{{ $item->detalhes_outro }}</p>@endif
                <p class="mb-2">
                    @if ($item->numero_documento)Número: {{ $item->numero_documento }}.@endif
                    @if ($item->realizado_em)Realizado em {{ $item->realizado_em->format('d/m/Y') }}.@endif
                    @if ($item->valido_ate)Válido até {{ $item->valido_ate->format('d/m/Y') }}.@endif
                    @if ($item->prorrogacoes)Prorrogações: {{ $item->prorrogacoes }} de {{ App\Models\ItemAvaliacao::MAX_PRORROGACOES }}.@endif
                </p>
                @if ($item->anexos->isNotEmpty())
                    <h3 class="h6">Arquivos</h3>
                    <ul class="list-unstyled mb-3">@foreach ($item->anexos as $anexo)
                        <li class="mb-1 text-break">{{ $anexo->nome_original }} · {{ $anexo->tamanhoLegivel() }} · {{ $anexo->enviadoPor->name }} · {{ $anexo->enviado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                            · <a href="{{ route('interno.exames.anexos.arquivo', $anexo->id) }}" target="_blank" rel="noopener">Abrir</a>
                            · <a href="{{ route('interno.exames.anexos.arquivo', ['anexo' => $anexo->id, 'baixar' => 1]) }}">Baixar</a></li>
                    @endforeach</ul>
                @endif
                @if ($formulario === $form && $falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach
                    @if ($conflito)<p class="mb-0"><a href="{{ route('interno.exames.show', $avaliacao) }}#{{ $form }}">Recarregar a situação atual</a></p>@endif</div>
                    @if ($conflito && ($entrada['motivo'] ?? '') !== '')
                        <label for="motivo-conflito-{{ $item->id }}" class="form-label">Motivo enviado e não registrado</label>
                        <textarea id="motivo-conflito-{{ $item->id }}" class="form-control mb-2" rows="2" readonly>{{ $entrada['motivo'] }}</textarea>
                    @endif
                @endif
                @if ($escrita && ! ($formulario === $form && $conflito))
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @if (in_array($item->estado, ['nao_solicitado', 'precisa_complementar'], true))
                            <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'solicitacao') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="solicitacao">
                                <button class="btn btn-primary">Solicitar</button>
                            </form>
                        @endif
                        @if ($item->estado === 'solicitado')
                            <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'recebimento') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="recebimento">
                                @if ($item->exigeNumero())
                                    <label for="numero-{{ $item->id }}" class="form-label">Número do CNS (15 dígitos)</label>
                                    <input class="form-control mb-2" id="numero-{{ $item->id }}" name="numero_documento" inputmode="numeric" maxlength="30" required value="{{ $valor($form, 'recebimento', 'numero_documento') }}">
                                @else
                                    <label for="realizado-{{ $item->id }}" class="form-label">Data de realização ou coleta</label>
                                    <input class="form-control mb-2" type="date" id="realizado-{{ $item->id }}" name="realizado_em" max="{{ $hoje }}" required value="{{ $valor($form, 'recebimento', 'realizado_em') }}">
                                @endif
                                <button class="btn btn-primary">Registrar recebimento</button>
                            </form>
                            @unless ($item->ja_recebido)
                                <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                    <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'cancelamento_solicitacao') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="cancelamento_solicitacao">
                                    <label for="cancelar-{{ $item->id }}" class="form-label">Motivo para cancelar a solicitação</label>
                                    <textarea class="form-control mb-2" id="cancelar-{{ $item->id }}" name="motivo" rows="2" maxlength="2000" required>{{ $valor($form, 'cancelamento_solicitacao', 'motivo') }}</textarea>
                                    <button class="btn btn-outline-secondary">Cancelar solicitação</button>
                                </form>
                            @endunless
                        @endif
                        @if ($item->estado === 'recebido')
                            <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'aceite') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="aceite">
                                <button class="btn btn-success">Aceitar</button>
                            </form>
                            <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'complementacao') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="complementacao">
                                <label for="complementar-{{ $item->id }}" class="form-label">O que precisa complementar</label>
                                <textarea class="form-control mb-2" id="complementar-{{ $item->id }}" name="motivo" rows="2" maxlength="2000" required>{{ $valor($form, 'complementacao', 'motivo') }}</textarea>
                                <button class="btn btn-outline-secondary">Pedir complementação</button>
                            </form>
                        @endif
                        @if ($item->vencido())
                            @if ($item->prorrogacoes < App\Models\ItemAvaliacao::MAX_PRORROGACOES)
                                <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                    <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'prorrogacao') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="prorrogacao">
                                    <label for="prorrogar-{{ $item->id }}" class="form-label">Motivo para prorrogar por {{ App\Models\ItemAvaliacao::DIAS_PRORROGACAO }} dias</label>
                                    <textarea class="form-control mb-2" id="prorrogar-{{ $item->id }}" name="motivo" rows="2" maxlength="2000" required>{{ $valor($form, 'prorrogacao', 'motivo') }}</textarea>
                                    <button class="btn btn-outline-primary">Prorrogar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('interno.exames.itens.registrar', $item->id) }}">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'novo_pedido') }}"><input type="hidden" name="versao" value="{{ $item->versao }}"><input type="hidden" name="acao" value="novo_pedido">
                                <button class="btn btn-outline-primary">Pedir novo exame</button>
                            </form>
                        @endif
                        @if ($item->aceitaAnexo())
                            <form method="POST" action="{{ route('interno.exames.itens.anexar', $item->id) }}" enctype="multipart/form-data">@csrf
                                <input type="hidden" name="operacao_id" value="{{ $operacao($form, 'anexo') }}">
                                <label for="arquivo-{{ $item->id }}" class="form-label">Anexar arquivo (PDF, JPEG ou PNG, até 10 MB)</label>
                                <input class="form-control mb-2" type="file" id="arquivo-{{ $item->id }}" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                                <button class="btn btn-outline-primary">Anexar</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div></section>
        @endforeach

        @if ($escrita)
            <form method="POST" action="{{ route('interno.exames.itens.incluir', $avaliacao) }}" class="card mb-4" id="outro"><div class="card-body">@csrf
                <h2 class="h5">Incluir outro exame</h2>
                <p>Para um exame específico desta doadora, fora da lista. O item já entra como solicitado e vale por {{ App\Models\ItemAvaliacao::VALIDADE_PADRAO_DIAS }} dias a partir da realização.</p>
                @if ($formulario === 'outro' && $falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach</div>@endif
                <input type="hidden" name="operacao_id" value="{{ $operacao('outro') }}">
                <label for="nome_outro" class="form-label">Nome do exame</label>
                <input class="form-control mb-2" id="nome_outro" name="nome_outro" maxlength="150" required value="{{ $formulario === 'outro' ? ($entrada['nome_outro'] ?? '') : '' }}">
                <label for="detalhes_outro" class="form-label">Detalhes (opcional)</label>
                <textarea class="form-control mb-3" id="detalhes_outro" name="detalhes_outro" rows="2" maxlength="2000">{{ $formulario === 'outro' ? ($entrada['detalhes_outro'] ?? '') : '' }}</textarea>
                <button class="btn btn-primary">Incluir e solicitar</button>
            </div></form>
        @endif

        <h2 class="h4">Histórico dos exames</h2>
        <ul class="list-group mb-3">@forelse ($eventos as $evento)
            <li class="list-group-item"><strong>{{ App\Models\EventoItemAvaliacao::ACOES[$evento->acao] }}</strong> · {{ $evento->item->nome() }} · {{ $evento->autor->name }} · {{ $evento->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                · {{ $evento->estado_anterior ? App\Models\ItemAvaliacao::ESTADOS[$evento->estado_anterior].' → ' : '' }}{{ App\Models\ItemAvaliacao::ESTADOS[$evento->estado_novo] }}
                @if ($evento->acao === 'recebimento' && $evento->realizado_em) · realizado em {{ $evento->realizado_em->format('d/m/Y') }}@endif
                @if ($evento->acao === 'prorrogacao' && $evento->valido_ate) · válido até {{ $evento->valido_ate->format('d/m/Y') }}@endif
                @if ($evento->motivo)<div class="text-break" style="white-space: pre-wrap">{{ $evento->motivo }}</div>@endif
            </li>
        @empty<li class="list-group-item">Nenhum registro ainda.</li>@endforelse</ul>
        {{ $eventos->links() }}
    </div>
</x-app-layout>
