<x-guest-layout>
    <h1 class="h4 mb-4">Confirmar senha</h1>
    <div class="mb-4 small text-body-secondary">
        {{ __('Confirme sua senha para continuar.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Senha')" />

            <x-text-input id="password" class="d-block mt-1 w-100"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="d-flex justify-content-end mt-4">
            <x-primary-button>
                {{ __('Confirmar') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
