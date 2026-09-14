<x-guest-layout>
    <h1 class="uk-card-title uk-text-center">Recuperar contraseña</h1>

    <p class="uk-text-meta uk-margin-bottom">
        ¿Olvidaste tu contraseña? No hay problema. Escribe tu correo y te enviaremos un enlace para elegir una nueva.
    </p>

    <x-auth-session-status class="uk-margin-bottom" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="uk-margin">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="uk-width-1-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="uk-flex uk-flex-right uk-margin-top">
            <x-primary-button>
                Enviar enlace de recuperación
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
