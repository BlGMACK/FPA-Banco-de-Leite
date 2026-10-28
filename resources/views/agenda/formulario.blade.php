@php
    $entrada = old('formulario') === $formulario ? old('dados', []) : [];
    $erros = $errors->getBag($formulario);
@endphp
@if ($erros->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($erros->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
<input type="hidden" name="operacao_id" value="{{ $entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="versao" value="{{ $entrada['versao'] ?? $versao }}">
@if ($formulario === 'contato')
    @foreach (['canal' => ['whatsapp' => 'WhatsApp', 'telefone' => 'Telefone', 'email' => 'E-mail'], 'resultado' => ['tentado' => 'Tentativa', 'realizado' => 'Realizado', 'sem_resposta' => 'Sem resposta', 'numero_incorreto' => 'Número incorreto']] as $campo => $opcoes)
        <div class="mb-3"><label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ ucfirst($campo) }}</label><select class="form-select" id="{{ $formulario }}-{{ $campo }}" name="{{ $campo }}" required>@foreach ($opcoes as $valor => $texto)<option value="{{ $valor }}" @selected(($entrada[$campo] ?? '') === $valor)>{{ $texto }}</option>@endforeach</select></div>
    @endforeach
    @foreach (['destinatario' => ['Destinatário / número contatado', 255], 'finalidade' => ['Finalidade', 150]] as $campo => [$rotulo, $limite])
        <div class="mb-3"><label class="form-label" for="contato-{{ $campo }}">{{ $rotulo }}</label><input class="form-control" id="contato-{{ $campo }}" name="{{ $campo }}" maxlength="{{ $limite }}" value="{{ $entrada[$campo] ?? '' }}" required></div>
    @endforeach
    <div class="mb-3"><label class="form-label" for="contato-ocorrido">Data e hora do contato (Marília)</label><input class="form-control" id="contato-ocorrido" name="ocorrido_em" type="datetime-local" value="{{ $entrada['ocorrido_em'] ?? '' }}" required></div>
    @foreach (['resumo' => ['Resumo operacional', 4000], 'proxima_acao' => ['Próxima ação (opcional)', 2000]] as $campo => [$rotulo, $limite])
        <div class="mb-3"><label class="form-label" for="contato-{{ $campo }}">{{ $rotulo }}</label><textarea class="form-control" id="contato-{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="{{ $limite }}" @required($campo === 'resumo')>{{ $entrada[$campo] ?? '' }}</textarea></div>
    @endforeach
@else
    @if (in_array($formulario, ['confirmacao', 'reagendamento']))
        <div class="mb-3"><label class="form-label" for="{{ $formulario }}-contato">Contato que confirmou o combinado</label><select class="form-select" id="{{ $formulario }}-contato" name="contato_id" required><option value="">Selecione um contato realizado</option>@foreach ($confirmacoes as $contato)<option value="{{ $contato->id }}" @selected((string) ($entrada['contato_id'] ?? '') === (string) $contato->id)>#{{ $contato->id }} — {{ $contato->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} — {{ $contato->finalidade }}</option>@endforeach</select></div>
        @if ($formulario === 'reagendamento')<p>Registre um novo contato para confirmar a alteração antes de reagendar.</p>@endif
        <div class="mb-3"><label class="form-label" for="{{ $formulario }}-responsavel">Responsável pela visita</label><select class="form-select" id="{{ $formulario }}-responsavel" name="responsavel_user_id" required><option value="">Selecione</option>@foreach ($responsaveis as $responsavel)<option value="{{ $responsavel->id }}" @selected((string) ($entrada['responsavel_user_id'] ?? $atual?->responsavel_user_id ?? '') === (string) $responsavel->id)>{{ $responsavel->name }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label" for="{{ $formulario }}-modalidade">Modalidade</label><select class="form-select" id="{{ $formulario }}-modalidade" name="modalidade" required>@foreach (['domicilio' => 'Domicílio', 'banco' => 'Banco de Leite'] as $valor => $texto)<option value="{{ $valor }}" @selected(($entrada['modalidade'] ?? $atual?->modalidade ?? 'domicilio') === $valor)>{{ $texto }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label" for="{{ $formulario }}-local">Endereço / local combinado</label><textarea class="form-control" id="{{ $formulario }}-local" name="local_atendimento" maxlength="500" required>{{ $entrada['local_atendimento'] ?? $atual?->local_atendimento ?? '' }}</textarea></div>
        @foreach (['inicio_previsto_em' => 'Início', 'fim_previsto_em' => 'Fim'] as $campo => $rotulo)<div class="mb-3"><label class="form-label" for="{{ $formulario }}-{{ $campo }}">{{ $rotulo }} (horário de Marília)</label><input class="form-control" type="datetime-local" id="{{ $formulario }}-{{ $campo }}" name="{{ $campo }}" value="{{ $entrada[$campo] ?? '' }}" required></div>@endforeach
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="{{ $formulario }}-combinado" name="combinado_confirmado" value="1" @checked($entrada['combinado_confirmado'] ?? false) required><label class="form-check-label" for="{{ $formulario }}-combinado">Confirmo que horário e local foram combinados com a interessada.</label></div>
    @endif
    <div class="mb-3"><label class="form-label" for="{{ $formulario }}-motivo">Motivo / observação operacional</label><textarea class="form-control" id="{{ $formulario }}-motivo" name="motivo" rows="3" maxlength="2000" required>{{ $entrada['motivo'] ?? '' }}</textarea></div>
@endif
