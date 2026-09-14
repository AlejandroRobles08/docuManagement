<section>
    <header>
        <h3 class="uk-h4">Información del perfil</h3>
        <p class="uk-text-meta">Actualiza tu nombre y correo electrónico.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="uk-margin-top">
        @csrf
        @method('patch')

        <div class="uk-margin">
            <x-input-label for="name" value="Nombre" />
            <x-text-input id="name" name="name" type="text" class="uk-width-1-1" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="uk-margin">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" class="uk-width-1-1" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="uk-margin-small-top">
                    <p class="uk-text-small">
                        Tu correo no está verificado.
                        <button form="send-verification" class="uk-button uk-button-link uk-text-small">
                            Reenviar correo de verificación.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="uk-text-success uk-text-small">
                            Se envió un nuevo enlace de verificación a tu correo.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="uk-flex uk-flex-middle" style="gap: 1rem;">
            <x-primary-button>Guardar</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p class="uk-text-meta uk-margin-remove">Guardado.</p>
            @endif
        </div>
    </form>
</section>
