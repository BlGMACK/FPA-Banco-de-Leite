<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold h4 text-dark">
            {{ __('Perfil') }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container mx-auto blh-stack">
            <div class="p-4 bg-white shadow">
                <div class="blh-form-width">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 bg-white shadow">
                <div class="blh-form-width">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 bg-white shadow">
                <div class="blh-form-width">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
