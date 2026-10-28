<x-guest-layout>
    <h1 class="h4 mb-3">Acesso da equipe</h1>
    <p class="text-body-secondary small">Acesso exclusivo para funcionários e administradores do Banco de Leite. Solicite sua conta à equipe responsável.</p>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input id="email" class="d-block mt-1 w-100" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />

            <x-text-input id="password" class="d-block mt-1 w-100"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="d-block mt-4">
            <label for="remember_me" class="d-inline-flex align-items-center">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <span class="ms-2 small text-body-secondary">{{ __('Lembrar de mim') }}</span>
            </label>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end mt-4">
            @if (Route::has('password.request'))
                <a class="text-decoration-underline small text-body-secondary rounded" href="{{ route('password.request') }}">
                    {{ __('Esqueceu sua senha?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Entrar') }}
            </x-primary-button>
        </div>
    </form>
    <p class="mt-4 mb-0 small">Quer doar leite humano? <a href="{{ route('solicitacoes.create') }}">Envie sua solicitação sem criar uma conta.</a></p>
</x-guest-layout>
