<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        @include('layouts.navigation')

        @isset($header)
            <div class="uk-section uk-section-muted uk-section-xsmall uk-margin-remove-bottom">
                <div class="uk-container">
                    {{ $header }}
                </div>
            </div>
        @endisset

        <main class="uk-section">
            <div class="uk-container">
                @if (session('status'))
                    <div class="uk-alert-success" uk-alert>
                        <a class="uk-alert-close" uk-close></a>
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </body>
</html>
