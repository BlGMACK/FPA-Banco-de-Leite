<x-public-layout titulo="Acesso da equipe | Banco de Leite Humano">
    <section class="blh-pagina" aria-labelledby="login-titulo">
        <h1 id="login-titulo">Acesso da equipe</h1>
        <p class="text-body-secondary mb-4">Acesso exclusivo para funcionários e administradores do Banco de Leite. Solicite sua conta à equipe responsável.</p>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="blh-stack">
            @csrf

            <div>
                <x-input-label for="email" :value="__('E-mail')" />
                <x-text-input id="email" class="d-block mt-1 w-100" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Senha')" />
                <x-text-input id="password" class="d-block mt-1 w-100" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <label for="remember_me" class="d-inline-flex align-items-center">
                    <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                    <span class="ms-2 small text-body-secondary">{{ __('Lembrar de mim') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="small" href="{{ route('password.request') }}">
                        {{ __('Esqueceu sua senha?') }}
                    </a>
                @endif
            </div>

            <div class="d-flex justify-content-end">
                <x-primary-button class="btn-blh">
                    {{ __('Entrar') }}
                </x-primary-button>
            </div>
        </form>

        <p class="mt-4 mb-0 small">Quer doar leite humano? <a href="{{ route('solicitacoes.create') }}">Envie sua solicitação sem criar uma conta.</a></p>
    </section>
</x-public-layout>
