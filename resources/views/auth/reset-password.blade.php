<x-guest-layout>
    <h1 class="uk-card-title uk-text-center">Restablecer contraseña</h1>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="uk-margin">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="uk-width-1-1" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="uk-margin">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="uk-width-1-1" type="password" name="password" required autocomplete="new-password" />
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
                Restablecer contraseña
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
