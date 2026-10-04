<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold h4 text-dark">
            {{ __('Painel') }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container mx-auto">
            <div class="bg-white overflow-hidden shadow-sm">
                <div class="p-4 text-dark">
                    {{ __('Você está conectado!') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
