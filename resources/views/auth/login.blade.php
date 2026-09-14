<x-guest-layout>
    <h1 class="uk-card-title uk-text-center">Iniciar sesión</h1>

    <x-auth-session-status class="uk-margin-bottom" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="uk-margin">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="uk-width-1-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="uk-margin">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="uk-width-1-1"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="uk-margin">
            <label>
                <input type="checkbox" class="uk-checkbox" name="remember">
                Recordarme
            </label>
        </div>

        <div class="uk-flex uk-flex-middle uk-flex-between uk-margin-top">
            @if (Route::has('password.request'))
                <a class="uk-text-small" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @else
                <span></span>
            @endif

            <x-primary-button>
                Entrar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
