<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.relatorios.index') }}">Voltar aos relatórios</a>
        <h1 class="h3 mt-3">Registrar realização da visita</h1>
        <h2 class="h5">{{ $agenda->solicitacao->interessada->nome_completo }}</h2>
        <p>Agendamento #{{ $agenda->id }} · {{ $agenda->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} até {{ $agenda->fim_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.<br>Previsto: {{ $agenda->responsavel->name }} · {{ $agenda->modalidade === 'domicilio' ? 'Domicílio' : 'Banco de Leite' }} · {{ $agenda->local_atendimento }}</p>
        <p>Horários de Marília. Início e fim vêm preenchidos com o horário previsto; ajuste se a visita ocorreu em outro horário. Informar a realização libera a reserva e abre um relatório em rascunho; não aprova a doação.</p>
        @if ($falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach</div>@endif
        <form method="POST" action="{{ route('interno.relatorios.visitas.store', $agenda) }}">@csrf
            <input type="hidden" name="operacao_id" value="{{ $entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="versao" value="{{ $entrada['versao'] ?? $agenda->versao }}">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="inicio">Início efetivo</label><input class="form-control" type="datetime-local" id="inicio" name="inicio_efetivo_em" value="{{ $entrada['inicio_efetivo_em'] ?? $agenda->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="fim">Fim efetivo</label><input class="form-control" type="datetime-local" id="fim" name="fim_efetivo_em" value="{{ $entrada['fim_efetivo_em'] ?? $agenda->fim_previsto_em->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="responsavel">Responsável efetivo</label><select class="form-select" id="responsavel" name="responsavel_user_id" required>@foreach ($responsaveis as $pessoa)<option value="{{ $pessoa->id }}" @selected((string) ($entrada['responsavel_user_id'] ?? $agenda->responsavel_user_id) === (string) $pessoa->id)>{{ $pessoa->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="modalidade">Modalidade</label><select class="form-select" id="modalidade" name="modalidade" required>@foreach (['domicilio' => 'Domicílio', 'banco' => 'Banco de Leite'] as $valor => $rotulo)<option value="{{ $valor }}" @selected(($entrada['modalidade'] ?? $agenda->modalidade) === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="local">Local efetivo</label><input class="form-control" id="local" name="local_atendimento" maxlength="500" value="{{ $entrada['local_atendimento'] ?? $agenda->local_atendimento }}" required></div>
                <div class="col-12"><label class="form-label" for="motivo">Justificativa de mudança de responsável ou local</label><textarea class="form-control" id="motivo" name="motivo_divergencia" maxlength="2000" rows="3">{{ $entrada['motivo_divergencia'] ?? '' }}</textarea></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="confirmacao" name="realizacao_confirmada" value="1" @checked(($entrada['realizacao_confirmada'] ?? '') === '1') required><label class="form-check-label" for="confirmacao">Confirmo que a visita ocorreu conforme os dados informados.</label></div></div>
                <div class="col-12"><button class="btn btn-primary">Registrar visita e abrir relatório</button></div>
            </div>
        </form>
    </div>
</x-app-layout>
