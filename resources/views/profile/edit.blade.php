<x-app-layout>
    <x-slot name="header">
        <h2 class="uk-h3 uk-margin-remove">Mi perfil</h2>
    </x-slot>

    <div class="uk-child-width-1-1 uk-grid-medium" uk-grid>
        <div>
            <div class="uk-card uk-card-default uk-card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div>
            <div class="uk-card uk-card-default uk-card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div>
            <div class="uk-card uk-card-default uk-card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
