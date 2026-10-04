<x-guest-layout>
    <h1 class="h4 mb-4">Verificar e-mail</h1>
    <div class="mb-4 small text-body-secondary">
        {{ __('Verifique seu endereço de e-mail pelo link enviado. Se necessário, solicite um novo link abaixo.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 fw-medium small text-success">
            {{ __('Um novo link de verificação foi enviado para seu e-mail.') }}
        </div>
    @endif

    <div class="mt-4 d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Reenviar verificação') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-decoration-underline small text-body-secondary rounded">
                {{ __('Sair') }}
            </button>
        </form>
    </div>
</x-guest-layout>
