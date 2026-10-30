<x-dynamic-component :component="$interno ? 'app-layout' : 'public-layout'">
    <div class="{{ $interno ? 'container py-4 blh-form-width' : 'blh-pagina' }}">
        <h1 class="{{ $interno ? 'h3' : '' }}">{{ $interno ? 'Registrar solicitação' : 'Quero doar leite humano' }}</h1>
        <p>Preencha seus dados para que a equipe do Banco de Leite entre em contato pelo WhatsApp. O envio não significa aprovação para doar nem agendamento confirmado.</p>
        <p class="small text-body-secondary">Campos obrigatórios, exceto complemento e outros detalhes. Informe pelo menos um telefone.</p>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Confira os campos indicados:</p>
                <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ route($interno ? 'interno.solicitacoes.store' : 'solicitacoes.store') }}">
            @csrf
            <input type="hidden" name="operacao_token" value="{{ old('operacao_token', $token) }}">
            <div class="row g-3">
                @php
                    $campos = [
                        'nome_completo' => ['Nome completo', 'text', 150, 'name', 'Ex.: Maria da Silva'],
                        'cpf' => ['CPF', 'text', 14, 'off', 'XXX.XXX.XXX-XX'],
                        'telefone_fixo' => ['Telefone fixo com DDD', 'tel', 20, 'tel', '(XX) XXXX-XXXX'],
                        'telefone_celular' => ['Celular com DDD', 'tel', 20, 'tel', '(XX) 9XXXX-XXXX'],
                        'email_contato' => ['E-mail', 'email', 255, 'email', 'nome@exemplo.com'],
                        'municipio' => ['Município', 'text', 150, 'address-level2', 'Ex.: Marília'],
                        'bairro' => ['Bairro', 'text', 150, 'address-level3', 'Ex.: Centro'],
                        'uf' => ['UF', 'text', 2, 'address-level1', 'Ex.: SP'],
                        'cep' => ['CEP', 'text', 9, 'postal-code', 'XXXXX-XXX'],
                        'logradouro' => ['Rua / avenida', 'text', 255, 'address-line1', 'Ex.: Rua das Flores'],
                        'numero' => ['Número (ou s/n)', 'text', 20, 'off', 'Ex.: 123'],
                        'complemento' => ['Complemento (opcional)', 'text', 150, 'address-line2', 'Ex.: apto 12, bloco B'],
                    ];
                @endphp
                @foreach ($campos as $campo => [$rotulo, $tipo, $limite, $autocomplete, $exemplo])
                    <div class="{{ in_array($campo, ['nome_completo', 'email_contato', 'logradouro']) ? 'col-12' : 'col-md-6' }}">
                        <label for="{{ $campo }}" class="form-label">{{ $rotulo }}</label>
                        <input id="{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" maxlength="{{ $limite }}"
                               autocomplete="{{ $autocomplete }}" placeholder="{{ $exemplo }}" value="{{ old($campo) }}"
                               @required(!in_array($campo, ['telefone_fixo', 'telefone_celular', 'complemento']))
                               class="form-control @error($campo) is-invalid @enderror"
                               aria-invalid="{{ $errors->has($campo) ? 'true' : 'false' }}"
                               @if($errors->has($campo)) aria-describedby="{{ $campo }}-erro" @endif>
                        @error($campo)<div class="invalid-feedback" id="{{ $campo }}-erro">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="col-12">
                    <label for="whatsapp_telefone" class="form-label">Qual número informado recebe WhatsApp?</label>
                    <select id="whatsapp_telefone" name="whatsapp_telefone" class="form-select @error('whatsapp_telefone') is-invalid @enderror" required>
                        <option value="">Selecione</option>
                        <option value="fixo" @selected(old('whatsapp_telefone') === 'fixo')>Telefone fixo informado</option>
                        <option value="celular" @selected(old('whatsapp_telefone') === 'celular')>Celular informado</option>
                    </select>
                    @error('whatsapp_telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="whatsapp_disponivel" name="whatsapp_disponivel" value="1" @checked(old('whatsapp_disponivel')) required>
                        <label class="form-check-label" for="whatsapp_disponivel">Confirmo que posso receber contato pelo WhatsApp no número selecionado.</label>
                    </div>
                    @error('whatsapp_disponivel')<p class="text-danger small">{{ $message }}</p>@enderror
                </div>
                @if ($interno)
                    <div class="col-12">
                        <label for="canal_origem" class="form-label">Canal do atendimento</label>
                        <select class="form-select" id="canal_origem" name="canal_origem" required>
                            <option value="">Selecione</option>
                            <option value="telefone" @selected(old('canal_origem') === 'telefone')>Telefone</option>
                            <option value="presencial" @selected(old('canal_origem') === 'presencial')>Presencial</option>
                        </select>
                        <p class="form-text">Confirme com a interessada os dados e a disponibilidade no WhatsApp.</p>
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label" for="outros_detalhes">Outros detalhes (opcional)</label>
                    <textarea id="outros_detalhes" name="outros_detalhes" rows="4" maxlength="2000" placeholder="Ex.: melhor horário para falarmos com você" class="form-control" aria-describedby="detalhes-ajuda">{{ old('outros_detalhes') }}</textarea>
                    <p class="form-text" id="detalhes-ajuda">Até 2.000 caracteres. Não envie exames, documentos ou dados de outras pessoas neste campo.</p>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn {{ $interno ? 'btn-success' : 'btn-blh' }}">Enviar solicitação</button>
                    @if ($interno)<a class="btn btn-outline-secondary" href="{{ route('interno.solicitacoes.index') }}">Voltar à fila</a>@endif
                </div>
            </div>
        </form>
    </div>
</x-dynamic-component>
