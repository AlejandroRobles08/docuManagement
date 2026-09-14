<section>
    <header>
        <h3 class="uk-h4">Actualizar contraseña</h3>
        <p class="uk-text-meta">Usa una contraseña larga y aleatoria para mantener tu cuenta segura.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="uk-margin-top">
        @csrf
        @method('put')

        <div class="uk-margin">
            <x-input-label for="update_password_current_password" value="Contraseña actual" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="uk-width-1-1" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="uk-margin">
            <x-input-label for="update_password_password" value="Nueva contraseña" />
            <x-text-input id="update_password_password" name="password" type="password" class="uk-width-1-1" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
        </div>

        <div class="uk-margin">
            <x-input-label for="update_password_password_confirmation" value="Confirmar contraseña" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="uk-width-1-1" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="uk-flex uk-flex-middle" style="gap: 1rem;">
            <x-primary-button>Guardar</x-primary-button>

            @if (session('status') === 'password-updated')
                <p class="uk-text-meta uk-margin-remove">Guardado.</p>
            @endif
        </div>
    </form>
</section>
