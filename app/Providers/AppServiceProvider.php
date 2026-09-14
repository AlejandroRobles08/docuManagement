<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los paginadores de Laravel traen vistas con clases de Tailwind por
        // defecto; usamos una propia con clases de UIkit (ver
        // resources/views/vendor/pagination/uikit.blade.php).
        Paginator::defaultView('vendor.pagination.uikit');
        Paginator::defaultSimpleView('vendor.pagination.uikit');
    }
}
