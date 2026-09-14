<x-guest-layout>
    <h1 class="uk-card-title uk-text-center">Confirma tu contraseña</h1>

    <p class="uk-text-meta uk-margin-bottom">
        Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="uk-margin">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="uk-width-1-1"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="uk-flex uk-flex-right uk-margin-top">
            <x-primary-button>
                Confirmar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
