@php
    $resultados = ['liberada_exames' => 'Liberar para exames', 'em_aguardo' => 'Deixar em aguardo', 'nao_apta' => 'Não apta'];
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
            <p class="mb-1">Relatório de visita: versão vigente {{ $relatorio->numero_versao_vigente }}{{ $relatorio->estado === 'em_correcao' ? ', com correção em elaboração' : '' }}.
                @if ($leituraRelatorio)<a href="{{ route('interno.relatorios.show', $relatorio->id) }}">Ler o relatório</a>@endif
            </p>
            @if ($avaliacao->concluida())<p class="mb-0">A análise do relatório foi concluída. O relatório não pode mais ser corrigido.</p>@endif
        </div></section>
        @if ($podeDecidir)
            <form method="POST" action="{{ route('interno.avaliacoes.decisoes.store', $avaliacao) }}" class="card mb-4"><div class="card-body">@csrf
                <h2 class="h4">Análise do relatório</h2>
                <p>A decisão vale para a versão {{ $relatorio->numero_versao_vigente }} do relatório. “Liberar para exames” e “Não apta” concluem a análise e impedem novas correções do relatório; “Em aguardo” permite decidir de novo depois.</p>
                <input type="hidden" name="operacao_id" value="{{ isset($falhas['conflito']) ? (string) Illuminate\Support\Str::uuid() : ($entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid()) }}">
                <input type="hidden" name="versao" value="{{ $avaliacao->versao }}">
                <fieldset class="mb-3"><legend class="form-label fs-6">Resultado</legend>
                    @foreach ($resultados as $valor => $rotulo)
                        <div class="form-check"><input class="form-check-input" type="radio" name="resultado" id="resultado-{{ $valor }}" value="{{ $valor }}" required @checked(($entrada['resultado'] ?? '') === $valor)><label class="form-check-label" for="resultado-{{ $valor }}">{{ $rotulo }}</label></div>
                    @endforeach
                </fieldset>
                <label for="motivo" class="form-label">Motivo (obrigatório para em aguardo e não apta)</label>
                <textarea id="motivo" name="motivo" rows="4" maxlength="2000" class="form-control mb-3">{{ $entrada['motivo'] ?? '' }}</textarea>
                <button class="btn btn-primary" @disabled(isset($falhas['conflito']))>Registrar decisão</button>
            </div></form>
        @elseif (! $avaliacao->concluida() && $relatorio->estado !== 'finalizado')
            <p>O relatório está em correção. A decisão fica disponível depois que a correção for finalizada.</p>
        @endif
        <h2 class="h4">Histórico de decisões</h2>
        <ul class="list-group mb-3">@forelse ($decisoes as $decisao)
            <li class="list-group-item"><strong>{{ App\Models\Avaliacao::ESTADOS[$decisao->resultado] }}</strong> · {{ $decisao->autor->name }} · {{ $decisao->decidido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · relatório versão {{ $decisao->versaoRelatorio->numero }}
                @if ($decisao->motivo)<div class="text-break" style="white-space: pre-wrap">{{ $decisao->motivo }}</div>@endif
            </li>
        @empty<li class="list-group-item">Nenhuma decisão registrada.</li>@endforelse</ul>
        {{ $decisoes->links() }}
    </div>
</x-app-layout>
