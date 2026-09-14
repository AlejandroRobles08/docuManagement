<section>
    <header>
        <h3 class="uk-h4">Eliminar cuenta</h3>
        <p class="uk-text-meta">
            Una vez eliminada tu cuenta, todos sus datos se borrarán permanentemente. Descarga cualquier información que quieras conservar antes de continuar.
        </p>
    </header>

    <button type="button" class="uk-button uk-button-danger uk-margin-top" uk-toggle="target: #confirm-user-deletion">
        Eliminar cuenta
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <h3 class="uk-h4">¿Seguro que quieres eliminar tu cuenta?</h3>

            <p class="uk-text-meta">
                Una vez eliminada, todos sus datos se borrarán permanentemente. Escribe tu contraseña para confirmar.
            </p>

            <div class="uk-margin">
                <x-input-label for="password" value="Contraseña" class="uk-hidden" />
                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="uk-width-1-1"
                    placeholder="Contraseña"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="uk-flex uk-flex-right uk-margin-top" style="gap: .5rem;">
                <button type="button" class="uk-button uk-button-default uk-modal-close">
                    Cancelar
                </button>
                <x-danger-button>
                    Eliminar cuenta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
