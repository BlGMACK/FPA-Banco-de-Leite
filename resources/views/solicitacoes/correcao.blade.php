@php
    $admitida = $pedido->status === 'admitida';
    $registro = $admitida ? $pedido->interessada : $pedido;
    $campos = ($admitida ? [] : ['nome_completo' => 'Nome completo', 'cpf' => 'CPF']) + ['telefone_fixo' => 'Telefone fixo com DDD', 'telefone_celular' => 'Celular com DDD',
        'email_contato' => 'E-mail', 'cep' => 'CEP', 'logradouro' => 'Logradouro', 'numero' => 'Número', 'complemento' => 'Complemento',
        'bairro' => 'Bairro', 'municipio' => 'Município', 'uf' => 'UF'];
    $valor = fn (string $campo): ?string => old($campo, $registro->{$campo});
@endphp
<details class="card mb-4" @if($errors->getBag('correcao')->any()) open @endif><summary class="card-header">Corrigir dados</summary><div class="card-body">
    <p>{{ $admitida ? 'Corrige os contatos e o endereço do cadastro. Nome e CPF não mudam depois da admissão.' : 'Corrige os dados do pedido. O texto recebido originalmente fica preservado.' }} O motivo e os valores anteriores ficam no histórico.</p>
    <form method="POST" action="{{ route('interno.solicitacoes.correcao.store', $pedido) }}" class="row g-3">
        @csrf
        <input type="hidden" name="versao" value="{{ $pedido->versao }}">
        @foreach ($campos as $campo => $rotulo)
            <div class="col-md-6"><label class="form-label" for="correcao-{{ $campo }}">{{ $rotulo }}</label>
                <input class="form-control" id="correcao-{{ $campo }}" name="{{ $campo }}" value="{{ $valor($campo) }}" @required(! in_array($campo, ['telefone_fixo', 'telefone_celular', 'complemento'], true))></div>
        @endforeach
        <fieldset class="col-12"><legend class="form-label fs-6">Número para WhatsApp</legend>
            @foreach (['fixo' => 'Telefone fixo', 'celular' => 'Celular'] as $opcao => $rotulo)
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="whatsapp_telefone" id="correcao-whatsapp-{{ $opcao }}" value="{{ $opcao }}" required @checked($valor('whatsapp_telefone') === $opcao)><label class="form-check-label" for="correcao-whatsapp-{{ $opcao }}">{{ $rotulo }}</label></div>
            @endforeach
            <div class="form-check"><input class="form-check-input" type="checkbox" name="whatsapp_disponivel" id="correcao-whatsapp-disponivel" value="1" required><label class="form-check-label" for="correcao-whatsapp-disponivel">A interessada confirmou que recebe contato pelo WhatsApp no número selecionado.</label></div>
        </fieldset>
        @unless ($admitida)
            <div class="col-12"><label class="form-label" for="correcao-outros_detalhes">Outros detalhes</label><textarea class="form-control" id="correcao-outros_detalhes" name="outros_detalhes" rows="3" maxlength="2000">{{ $valor('outros_detalhes') }}</textarea></div>
        @endunless
        <div class="col-12"><label class="form-label" for="correcao-motivo">Motivo da correção</label><textarea class="form-control" id="correcao-motivo" name="motivo" rows="2" maxlength="2000" required>{{ old('motivo') }}</textarea></div>
        <div class="col-12"><button class="btn btn-primary">Salvar correção</button></div>
    </form>
</div></details>
