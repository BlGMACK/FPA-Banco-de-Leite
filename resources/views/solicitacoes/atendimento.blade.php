@foreach (['analise-iniciada' => 'Análise iniciada.', 'contato-registrado' => 'Contato registrado. Nenhuma mensagem foi enviada pelo sistema.', 'conferencia-registrada' => 'Conferência registrada.', 'dados-corrigidos' => 'Dados corrigidos. O histórico foi preservado.'] as $codigo => $mensagem)
    @if(session('status') === $codigo)<div class="alert alert-success" role="status">{{ $mensagem }}</div>@endif
@endforeach
@foreach (['contato', 'decisao', 'correcao'] as $bag)
    @if($errors->getBag($bag)->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->getBag($bag)->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
    @endif
@endforeach
@if($pedido->status === 'enviada')
    <form method="POST" action="{{ route('interno.solicitacoes.analise.store', $pedido) }}" class="mb-4">
        @csrf
        <input type="hidden" name="pedidos[0][id]" value="{{ $pedido->id }}">
        <input type="hidden" name="pedidos[0][versao]" value="{{ $pedido->versao }}">
        <button class="btn btn-primary">Iniciar análise</button>
    </form>
@endif
@if(in_array($pedido->status, ['enviada', 'em_analise']))
    @include('solicitacoes.correcao')
    <details class="card mb-4" @if($errors->getBag('contato')->any()) open @endif><summary class="card-header">Registrar contato</summary><div class="card-body">
        <p>Registre o contato feito pela equipe. O sistema não envia mensagens. Marque somente os pedidos realmente abordados.</p>
        <form method="POST" action="{{ route('interno.solicitacoes.contatos.store', $pedido) }}" class="row g-3">
            @csrf
            <input type="hidden" name="pedidos[0][id]" value="{{ $pedido->id }}">
            <input type="hidden" name="pedidos[0][versao]" value="{{ $pedido->versao }}">
            @foreach($correspondencias->where('tipo', 'pedido') as $par)
                <div class="col-12" x-data="{ incluir: false }">
                    <label><input type="checkbox" x-model="incluir"> Incluir pedido #{{ $par['registro']->id }} — {{ $par['registro']->nome_completo }}</label>
                    <input type="hidden" name="pedidos[{{ $loop->iteration }}][id]" value="{{ $par['registro']->id }}" disabled :disabled="!incluir">
                    <input type="hidden" name="pedidos[{{ $loop->iteration }}][versao]" value="{{ $par['registro']->versao }}" disabled :disabled="!incluir">
                </div>
            @endforeach
            <div class="col-md-6"><label for="contato-canal" class="form-label">Canal</label><select id="contato-canal" name="canal" class="form-select">@foreach(['whatsapp' => 'WhatsApp', 'telefone' => 'Telefone', 'email' => 'E-mail'] as $valor => $rotulo)<option value="{{ $valor }}" @selected(old('canal') === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
            <div class="col-md-6"><label for="contato-resultado" class="form-label">Resultado</label><select id="contato-resultado" name="resultado" class="form-select">@foreach(['tentado' => 'Tentado', 'realizado' => 'Realizado', 'sem_resposta' => 'Sem resposta', 'numero_incorreto' => 'Número incorreto'] as $valor => $rotulo)<option value="{{ $valor }}" @selected(old('resultado') === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
            <div class="col-md-6"><label for="contato-data" class="form-label">Quando ocorreu</label><input id="contato-data" name="ocorrido_em" type="datetime-local" class="form-control" value="{{ old('ocorrido_em') }}" required></div>
            @foreach(['destinatario' => ['Destinatário / número ou e-mail utilizado', 255], 'finalidade' => ['Finalidade', 150]] as $campo => [$rotulo, $limite])
                <div class="col-md-6"><label for="contato-{{ $campo }}" class="form-label">{{ $rotulo }}</label><input id="contato-{{ $campo }}" name="{{ $campo }}" class="form-control" maxlength="{{ $limite }}" value="{{ old($campo) }}" required></div>
            @endforeach
            <div class="col-12"><label for="contato-resumo" class="form-label">Resumo operacional</label><textarea id="contato-resumo" name="resumo" class="form-control" rows="3" maxlength="4000" required>{{ old('resumo') }}</textarea></div>
            <div class="col-12"><label for="contato-proxima" class="form-label">Próxima ação (opcional)</label><textarea id="contato-proxima" name="proxima_acao" class="form-control" maxlength="2000">{{ old('proxima_acao') }}</textarea></div>
            <div class="col-12"><label><input type="checkbox" name="identidade_conferida" value="1" @checked(old('identidade_conferida'))> Conferi a identidade durante o contato realizado</label></div>
            <div class="col-12"><label><input type="checkbox" name="duvida_esclarecida" value="1" @checked(old('duvida_esclarecida'))> Esclareci a dúvida sobre os pedidos e a intenção de prosseguir</label></div>
            <div class="col-12"><button class="btn btn-primary">Registrar contato</button></div>
        </form>
    </div></details>
    <details class="card mb-4" @if($errors->getBag('decisao')->any()) open @endif><summary class="card-header">Conferência e decisão administrativa</summary><div class="card-body">
        <p>Selecione somente os registros esclarecidos no contato. A admissão exige que o principal esteja em análise. Arquivados saem das comparações e permanecem no histórico.</p>
        <form method="POST" action="{{ route('interno.solicitacoes.resolucoes.store', $pedido) }}" class="row g-3">
            @csrf
            <input type="hidden" name="operacao_id" value="{{ $operacaoId }}">
            <input type="hidden" name="pedidos[0][id]" value="{{ $pedido->id }}">
            <input type="hidden" name="pedidos[0][versao]" value="{{ $pedido->versao }}">
            <div class="col-12">Pedido de origem #{{ $pedido->id }} incluído.</div>
            @foreach($correspondencias as $par)
                @php($grupo = $par['tipo'] === 'pedido' ? 'pedidos' : 'cadastros')
                <div class="col-12" x-data="{ incluir: false }">
                    <label><input type="checkbox" x-model="incluir"> {{ $par['tipo'] === 'pedido' ? 'Pedido' : 'Cadastro' }} #{{ $par['registro']->id }} — {{ $par['registro']->nome_completo }}</label>
                    <input type="hidden" name="{{ $grupo }}[{{ $loop->iteration }}][id]" value="{{ $par['registro']->id }}" disabled :disabled="!incluir">
                    <input type="hidden" name="{{ $grupo }}[{{ $loop->iteration }}][versao]" value="{{ $par['registro']->versao }}" disabled :disabled="!incluir">
                </div>
            @endforeach
            <div class="col-12"><label for="decisao-contato" class="form-label">Contato que fundamenta a decisão</label><select name="contato_id" id="decisao-contato" class="form-select" required><option value="">Selecione</option>@foreach($pedido->contatos as $contato)<option value="{{ $contato->id }}">#{{ $contato->id }} — {{ $contato->ocorrido_em->format('d/m/Y H:i') }} — {{ $contato->resultado }}</option>@endforeach</select></div>
            <div class="col-12"><label for="decisao-motivo" class="form-label">Motivo / esclarecimento</label><textarea id="decisao-motivo" name="motivo" rows="3" class="form-control" maxlength="4000" required>{{ old('motivo') }}</textarea></div>
            <div class="col-md-4"><label for="decisao-principal" class="form-label">Principal, se houver admissão</label><select id="decisao-principal" name="principal_id" class="form-select"><option value="{{ $pedido->id }}">Pedido #{{ $pedido->id }}</option>@foreach($correspondencias->where('tipo', 'pedido') as $par)<option value="{{ $par['registro']->id }}">Pedido #{{ $par['registro']->id }}</option>@endforeach</select></div>
            <div class="col-md-4"><label for="decisao-identidade" class="form-label">Identificação para admissão</label><select id="decisao-identidade" name="identidade" class="form-select"><option value="">Selecione para admitir</option><option value="nova">Criar identidade confirmada</option><option value="existente">Vincular cadastro existente</option></select></div>
            <div class="col-md-4"><label for="decisao-cadastro" class="form-label">Cadastro existente de referência</label><select id="decisao-cadastro" name="cadastro_referencia_id" class="form-select"><option value="">Nenhum</option>@foreach($correspondencias->where('tipo', 'cadastro') as $par)<option value="{{ $par['registro']->id }}">Cadastro #{{ $par['registro']->id }}</option>@endforeach</select></div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary" name="acao" value="aguardando_esclarecimento" formaction="{{ route('interno.solicitacoes.conferencias.store', $pedido) }}">Registrar dúvida pendente</button>
                <button class="btn btn-outline-primary" name="acao" value="pessoas_distintas" formaction="{{ route('interno.solicitacoes.conferencias.store', $pedido) }}">Confirmar pessoas distintas</button>
                <button class="btn btn-outline-danger" name="acao" value="arquivar_selecionados">Arquivar selecionados</button>
                <button class="btn btn-success" name="acao" value="admitir_principal">Admitir principal e arquivar repetidos</button>
                <button class="btn btn-primary" name="acao" value="admitir" formaction="{{ route('interno.solicitacoes.admissao.store', $pedido) }}">Admitir somente este pedido</button>
            </div>
        </form>
    </div></details>
@elseif($pedido->status === 'admitida')
    <p><a href="{{ route('interno.acompanhamentos.show', $pedido) }}">Ver acompanhamento administrativo</a></p>
@endif
<h2 class="h5">Contatos registrados</h2>
@forelse($pedido->contatos as $contato)
    <div class="card mb-3"><div class="card-body text-break">
        <h3 class="h6">Contato #{{ $contato->id }} — {{ $contato->ocorrido_em->format('d/m/Y H:i') }}</h3>
        <p>{{ $contato->canal }} — {{ $contato->resultado }}. Registrado por {{ $contato->registradoPor->name }}.</p>
        <p>Destinatário: {{ $contato->destinatario }}. Finalidade: {{ $contato->finalidade }}.</p>
        <p style="white-space: pre-wrap">{{ $contato->resumo }}</p>
        <p>Próxima ação: {{ $contato->proxima_acao ?: 'Não informada' }}.</p>
        <p>Identidade conferida: {{ $contato->identidade_conferida ? 'Sim' : 'Não' }}. Dúvida esclarecida: {{ $contato->duvida_esclarecida ? 'Sim' : 'Não' }}.</p>
        <p>Pedidos abordados: @foreach($contato->solicitacoes as $abordado)<a href="{{ route('interno.solicitacoes.show', $abordado) }}">#{{ $abordado->id }}</a> @endforeach</p>
    </div></div>
@empty<p>Nenhum contato registrado.</p>@endforelse
<h2 class="h5">Histórico administrativo</h2>
@foreach($pedido->eventos as $evento)
    <p class="text-break">{{ $evento->ocorrido_em->format('d/m/Y H:i') }} — {{ $evento->tipo }} — {{ $evento->autor?->name ?? 'Recebimento público' }}. {{ $evento->motivo }}
        @if($evento->tipo === 'correcao')<br>Campos corrigidos no {{ $evento->dados_novos['registro'] }}: {{ implode(', ', $evento->camposCorrigidos()) }}.@if($evento->valores_descartados_em) Valores descartados pelo prazo de retenção em {{ $evento->valores_descartados_em->format('d/m/Y') }}.@endif @endif</p>
@endforeach
@foreach($pedido->resolucoes as $resolucao)
    <div class="border rounded p-3 mb-3 text-break">
        <strong>Decisão #{{ $resolucao->id }} — {{ $resolucao->tipo }}</strong>
        <p>{{ $resolucao->motivo }}</p>
        <p>Pedidos relacionados: @foreach($resolucao->solicitacoes as $relacionado)<a href="{{ route('interno.solicitacoes.show', $relacionado) }}">#{{ $relacionado->id }} ({{ $relacionado->pivot->papel }})</a> @endforeach</p>
        @foreach($resolucao->interessadas as $cadastro)<p>Cadastro de referência #{{ $cadastro->id }} — {{ $cadastro->nome_completo }}</p>@endforeach
    </div>
@endforeach
