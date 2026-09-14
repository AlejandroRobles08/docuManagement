<x-guest-layout>
    <h1 class="uk-card-title uk-text-center">Verifica tu correo</h1>

    <p class="uk-text-meta uk-margin-bottom">
        Gracias por registrarte. Antes de continuar, ¿podrías verificar tu correo haciendo clic en el enlace que te acabamos de enviar? Si no te llegó, con gusto te enviamos otro.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="uk-alert-success uk-margin-bottom" uk-alert>
            <p>Se envió un nuevo enlace de verificación al correo que registraste.</p>
        </div>
    @endif

    <div class="uk-flex uk-flex-middle uk-flex-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                Reenviar correo de verificación
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="uk-button uk-button-link uk-text-small">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
