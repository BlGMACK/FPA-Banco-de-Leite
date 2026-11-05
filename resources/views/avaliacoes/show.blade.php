@php
    $resultados = ['liberada_exames' => 'Liberar para exames', 'em_aguardo' => 'Deixar em aguardo', 'nao_apta' => 'Não apta'];
    $habilitacoes = ['habilitada' => 'Habilitada para doar', 'habilitada_temporariamente' => 'Habilitada temporariamente', 'nao_habilitada' => 'Não habilitada'];
    $operacao = isset($falhas['conflito']) ? (string) Illuminate\Support\Str::uuid() : ($entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid());
@endphp
<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.avaliacoes.index') }}">Voltar às avaliações</a>
        <h1 class="h3 mt-3">Avaliação #{{ $avaliacao->id }}</h1>
        <h2 class="h5">{{ $avaliacao->atendimento->agendamento->solicitacao->interessada->nome_completo }}</h2>
        <p>Visita em {{ $avaliacao->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $avaliacao->atendimento->responsavel->name }}.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach</div>@endif
        @if (isset($falhas['conflito']))<p><a href="{{ route('interno.avaliacoes.show', $avaliacao) }}">Recarregar a situação atual</a></p>@endif
        <section class="card mb-4"><div class="card-body">
            <h2 class="h4">Situação: {{ App\Models\Avaliacao::ESTADOS[$avaliacao->estado] }}</h2>
            @foreach ($relatorios as $item)
                <p class="mb-1">{{ $loop->first ? 'Visita' : 'Visita complementar' }} em {{ $item->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $item->atendimento->responsavel->name }} —
                    @if ($item->numero_versao_vigente === 0)relatório ainda não publicado.
                    @else relatório na versão vigente {{ $item->numero_versao_vigente }}{{ $item->estado === 'em_correcao' ? ', com correção em elaboração' : '' }}.
                        @if ($leituraRelatorio)<a href="{{ route('interno.relatorios.show', $item->id) }}">Ler o relatório</a>@endif
                    @endif
                </p>
            @endforeach
            @if ($avaliacao->concluida())<p class="mb-0">A análise do relatório foi concluída. O relatório não pode mais ser corrigido.</p>@endif
            @if ($agendarComplementar)<p class="mb-0 mt-1">Se a análise depende de nova visita, <a href="{{ route('interno.agenda.acompanhamentos.show', $avaliacao->atendimento->agendamento->solicitacao_id) }}">agende uma visita complementar</a>. O relatório dela entra nesta avaliação.</p>@endif
            @if (($etapaExames || $avaliacao->itens->isNotEmpty()) && auth()->user()->can('consultar-exames'))<p class="mb-0 mt-1"><a href="{{ route('interno.exames.show', $avaliacao) }}">Exames e documentos</a></p>@endif
            @if ($avaliacao->possui_plano_saude !== null)<p class="mb-0 mt-1">Plano de saúde: {{ $avaliacao->possui_plano_saude ? 'possui' : 'não possui' }}.</p>@endif
            @if ($avaliacao->doadora)<p class="mb-0 mt-1"><a href="{{ route('interno.doadoras.show', $avaliacao->doadora) }}">Habilitação: {{ App\Models\Doadora::ESTADOS[$avaliacao->doadora->estado] }}</a></p>@endif
        </div></section>
        @if ($podeDecidir)
            <section class="card mb-4" x-data="{ resultado: @js($entrada['resultado'] ?? '') }"><div class="card-body">
                <form method="POST" action="{{ route('interno.avaliacoes.decisoes.store', $avaliacao) }}">@csrf
                    <h2 class="h4">Análise do relatório</h2>
                    @if ($avaliacao->reservadaPara(auth()->user()))
                        <p class="text-body-secondary">Análise reservada para você até {{ $avaliacao->analise_expira_em->setTimezone('America/Sao_Paulo')->format('H:i') }}. Enquanto isso, ninguém mais decide nem abre correção do relatório.</p>
                    @endif
                    <p>A decisão vale para a versão {{ $relatorio->numero_versao_vigente }} do relatório{{ $relatorios->count() > 1 ? ' da visita mais recente, considerando também as visitas anteriores' : '' }}. “Liberar para exames” e “Não apta” concluem a análise e impedem novas correções do relatório; “Em aguardo” permite decidir de novo depois.</p>
                    <input type="hidden" name="operacao_id" value="{{ $operacao }}">
                    <input type="hidden" name="versao" value="{{ $avaliacao->versao }}">
                    <fieldset class="mb-3"><legend class="form-label fs-6">Resultado</legend>
                        @foreach ($resultados as $valor => $rotulo)
                            <div class="form-check"><input class="form-check-input" type="radio" name="resultado" x-model="resultado" id="resultado-{{ $valor }}" value="{{ $valor }}" required @checked(($entrada['resultado'] ?? '') === $valor)><label class="form-check-label" for="resultado-{{ $valor }}">{{ $rotulo }}</label></div>
                        @endforeach
                    </fieldset>
                    <label for="motivo" class="form-label">Motivo (obrigatório para em aguardo e não apta)</label>
                    <textarea id="motivo" name="motivo" rows="4" maxlength="2000" class="form-control mb-3">{{ $entrada['motivo'] ?? '' }}</textarea>
                    <div class="alert alert-warning" x-show="['liberada_exames', 'nao_apta'].includes(resultado)">
                        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="confirmacao_definitiva" id="confirmacao_definitiva" value="1" x-bind:required="['liberada_exames', 'nao_apta'].includes(resultado)">
                            <label class="form-check-label" for="confirmacao_definitiva">Confirmo que esta decisão é definitiva: conclui a análise e o relatório não poderá mais ser corrigido.</label></div>
                    </div>
                    <button class="btn btn-primary" @disabled(isset($falhas['conflito']))>Registrar decisão</button>
                </form>
                <form method="POST" action="{{ route('interno.avaliacoes.analise.encerrar', $avaliacao) }}" class="mt-2">@csrf
                    <button class="btn btn-outline-secondary">Encerrar análise sem decidir</button>
                </form>
            </div></section>
        @elseif ($reservadaPorOutro)
            <p>{{ $avaliacao->mensagemReserva() }} A decisão fica disponível quando a análise for encerrada.</p>
        @elseif ($podeIniciar)
            <form method="POST" action="{{ route('interno.avaliacoes.analise.iniciar', $avaliacao) }}" class="mb-4">@csrf
                <p>Ao iniciar, a análise fica reservada para você por {{ App\Models\Avaliacao::MINUTOS_ANALISE }} minutos: ninguém mais decide nem abre correção do relatório até você decidir ou encerrar.</p>
                <button class="btn btn-primary">Iniciar análise</button>
            </form>
        @elseif (! $avaliacao->concluida() && $visitaMarcada)
            <p>Há visita complementar marcada. A decisão fica disponível depois que a visita for registrada e o relatório publicado, ou se o compromisso for cancelado.</p>
        @elseif (! $avaliacao->concluida() && $relatorios->contains('estado', 'em_correcao'))
            <p>O relatório está em correção. A decisão fica disponível depois que a correção for finalizada.</p>
        @elseif (! $avaliacao->concluida() && ! $publicados)
            <p>O relatório da visita complementar ainda não foi publicado. A decisão fica disponível depois que ele for finalizado.</p>
        @elseif ($podeHabilitar)
            <section class="card mb-4" id="habilitacao" x-data="{ resultado: @js($entrada['resultado'] ?? '') }"><div class="card-body">
                <form method="POST" action="{{ route('interno.avaliacoes.habilitacao.store', $avaliacao) }}">@csrf
                    <h2 class="h4">Decisão de habilitação</h2>
                    <p>As exigências dos exames estão cumpridas. A decisão é definitiva e encerra a etapa de exames; “Habilitada” e “Habilitada temporariamente” criam o registro da doadora.</p>
                    <input type="hidden" name="operacao_id" value="{{ $operacao }}">
                    <input type="hidden" name="versao" value="{{ $avaliacao->versao }}">
                    <fieldset class="mb-3"><legend class="form-label fs-6">Resultado</legend>
                        @foreach ($habilitacoes as $valor => $rotulo)
                            <div class="form-check"><input class="form-check-input" type="radio" name="resultado" x-model="resultado" id="habilitacao-{{ $valor }}" value="{{ $valor }}" required @checked(($entrada['resultado'] ?? '') === $valor)><label class="form-check-label" for="habilitacao-{{ $valor }}">{{ $rotulo }}</label></div>
                        @endforeach
                    </fieldset>
                    <fieldset class="mb-3"><legend class="form-label fs-6">Plano de saúde</legend>
                        @foreach (['1' => 'Possui', '0' => 'Não possui'] as $valor => $rotulo)
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="possui_plano_saude" id="plano-{{ $valor }}" value="{{ $valor }}" required @checked((string) ($entrada['possui_plano_saude'] ?? '') === (string) $valor)><label class="form-check-label" for="plano-{{ $valor }}">{{ $rotulo }}</label></div>
                        @endforeach
                    </fieldset>
                    <div x-show="resultado === 'habilitada_temporariamente'">
                        <label for="condicao" class="form-label">Condição da habilitação temporária</label>
                        <textarea id="condicao" name="condicao" rows="2" maxlength="2000" class="form-control mb-2" x-bind:required="resultado === 'habilitada_temporariamente'">{{ $entrada['condicao'] ?? '' }}</textarea>
                        <label for="valida_ate" class="form-label">Data-limite</label>
                        <input type="date" id="valida_ate" name="valida_ate" min="{{ now('America/Sao_Paulo')->addDay()->toDateString() }}" class="form-control mb-3" x-bind:required="resultado === 'habilitada_temporariamente'" value="{{ $entrada['valida_ate'] ?? '' }}">
                    </div>
                    <label for="motivo-habilitacao" class="form-label">Motivo (obrigatório para não habilitada)</label>
                    <textarea id="motivo-habilitacao" name="motivo" rows="3" maxlength="2000" class="form-control mb-3">{{ $entrada['motivo'] ?? '' }}</textarea>
                    <div class="alert alert-warning"><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="confirmacao_definitiva" id="confirmacao_habilitacao" value="1" required>
                        <label class="form-check-label" for="confirmacao_habilitacao">Confirmo que esta decisão é definitiva e encerra a etapa de exames.</label></div></div>
                    <button class="btn btn-primary" @disabled(isset($falhas['conflito']))>Registrar decisão de habilitação</button>
                </form>
            </div></section>
        @elseif ($etapaExames && auth()->user()->can('avaliar-doadoras'))
            <p id="habilitacao">A decisão de habilitação fica disponível quando houver ao menos um item solicitado, todos os solicitados estiverem aceitos e nenhum estiver vencido.</p>
        @endif
        @if (isset($falhas['conflito']) && ! $podeDecidir && ($entrada['motivo'] ?? '') !== '')
            <label for="motivo-conflito" class="form-label">Motivo enviado e não registrado</label>
            <textarea id="motivo-conflito" class="form-control mb-4" rows="4" readonly>{{ $entrada['motivo'] }}</textarea>
        @endif
        <h2 class="h4">Histórico de decisões</h2>
        <ul class="list-group mb-3">@forelse ($decisoes as $decisao)
            <li class="list-group-item"><strong>{{ App\Models\Avaliacao::ESTADOS[$decisao->resultado] }}</strong> · {{ $decisao->autor->name }} · {{ $decisao->decidido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                @if ($decisao->etapa === 'habilitacao') · decisão de habilitação · plano de saúde: {{ $decisao->possui_plano_saude ? 'possui' : 'não possui' }}@if ($decisao->valida_ate) · até {{ $decisao->valida_ate->format('d/m/Y') }}@endif
                @else · relatório{{ $relatorios->count() > 1 ? ' da visita '.($relatorios->search(fn ($item) => $item->id === $decisao->versaoRelatorio->relatorio_visita_id) + 1) : '' }} versão {{ $decisao->versaoRelatorio->numero }}@endif
                @if ($decisao->condicao)<div class="text-break" style="white-space: pre-wrap">Condição: {{ $decisao->condicao }}</div>@endif
                @if ($decisao->motivo)<div class="text-break" style="white-space: pre-wrap">{{ $decisao->motivo }}</div>@endif
            </li>
        @empty<li class="list-group-item">Nenhuma decisão registrada.</li>@endforelse</ul>
        {{ $decisoes->links() }}
    </div>
</x-app-layout>
