<x-guest-layout>
    <h1 class="h4 mb-4">Recuperar senha</h1>
    <div class="mb-4 small text-body-secondary">
        {{ __('Informe seu e-mail para solicitar um link de recuperação de senha.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input id="email" class="d-block mt-1 w-100" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end mt-4">
            <x-primary-button>
                {{ __('Enviar link de recuperação') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
