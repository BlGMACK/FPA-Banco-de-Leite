<section class="blh-stack">
    <header>
        <h2 class="h5 fw-medium text-dark">
            {{ __('Excluir conta') }}
        </h2>

        <p class="mt-1 small text-body-secondary">
            {{ __('A exclusão remove sua conta de acesso e encerra sua sessão. Esta operação não pode ser desfeita.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Excluir conta') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-4">
            @csrf
            @method('delete')

            <h2 id="confirm-user-deletion-titulo" class="h5 fw-medium text-dark">
                {{ __('Tem certeza de que deseja excluir sua conta?') }}
            </h2>

            <p class="mt-1 small text-body-secondary">
                {{ __('Informe sua senha para confirmar a exclusão da conta.') }}
            </p>

            <div class="mt-4">
                <x-input-label for="password" value="{{ __('Senha') }}" class="visually-hidden" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 d-block w-100"
                    placeholder="{{ __('Senha') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <x-secondary-button data-bs-dismiss="modal">
                    {{ __('Cancelar') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Excluir conta') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
