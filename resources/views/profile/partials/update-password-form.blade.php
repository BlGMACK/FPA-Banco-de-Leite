<section>
    <header>
        <h2 class="h5 fw-medium text-dark">
            {{ __('Alterar senha') }}
        </h2>

        <p class="mt-1 small text-body-secondary">
            {{ __('Escolha uma senha longa e exclusiva para sua conta.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-4 blh-stack">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Senha atual')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 d-block w-100" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Nova senha')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 d-block w-100" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmar senha')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 d-block w-100" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="d-flex align-items-center gap-3">
            <x-primary-button>{{ __('Salvar') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="small text-body-secondary"
                >{{ __('Salvo.') }}</p>
            @endif
        </div>
    </form>
</section>
