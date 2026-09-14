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
        <div class="auth-shell">
            <div class="auth-card">
                <div class="uk-text-center uk-margin-medium-bottom uk-light">
                    <x-application-logo class="uk-text-large" />
                </div>

                <div class="uk-card uk-card-default uk-card-body uk-box-shadow-large">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
