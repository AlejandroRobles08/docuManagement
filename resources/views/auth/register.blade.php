<x-app-layout>
    <x-slot name="header">
        <h2 class="uk-h3 uk-margin-remove">Agregar usuario al panel</h2>
        <p class="uk-text-meta uk-margin-remove-top">
            Crea una cuenta para un nuevo compañero de staff. No hay registro público: solo el personal ya autenticado puede dar de alta a otros usuarios.
        </p>
    </x-slot>

    <div class="uk-card uk-card-default uk-card-body uk-width-1-2@m">
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="uk-margin">
                <x-input-label for="name" value="Nombre" />
                <x-text-input id="name" class="uk-width-1-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div class="uk-margin">
                <x-input-label for="email" value="Correo electrónico" />
                <x-text-input id="email" class="uk-width-1-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div class="uk-margin">
                <x-input-label for="password" value="Contraseña" />
                <x-text-input id="password" class="uk-width-1-1"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div class="uk-margin">
                <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                <x-text-input id="password_confirmation" class="uk-width-1-1"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

            <div class="uk-flex uk-flex-right uk-margin-top">
                <x-primary-button>
                    Crear usuario
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
