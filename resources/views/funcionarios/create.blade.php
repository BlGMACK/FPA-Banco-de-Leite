<x-app-layout>
    <div class="container py-4 blh-form-width">
        <h1 class="h3">Cadastrar funcionário</h1>
        <p>Crie uma conta individual para um integrante da equipe. A conta não recebe permissões de módulos automaticamente. Somente a administração pode concedê-las.</p>
        <p>Após o cadastro, o funcionário define sua própria senha em “Esqueceu sua senha?” na tela de acesso, usando o e-mail informado. O recebimento depende do serviço de e-mail do sistema.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}@can('gerenciar-permissoes') <a href="{{ route('interno.permissoes.index') }}">Conceder permissões</a>@endcan</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('interno.funcionarios.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="name">Nome completo</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name"></div>
            <div class="mb-3"><label class="form-label" for="email">E-mail do funcionário</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email"></div>
            <button class="btn btn-primary">Cadastrar funcionário</button>
        </form>
    </div>
</x-app-layout>
