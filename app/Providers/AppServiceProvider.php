<?php

namespace App\Providers;

use App\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Asegurar que el tema de correos markdown use nuestro default.css (resources/views/vendor/mail/html/themes/)
        // Sin esto, Laravel puede cargar el tema del framework y los estilos personalizados no se aplican.
        $mailHtmlPath = resource_path('views/vendor/mail/html');
        if (is_dir($mailHtmlPath)) {
            View::addNamespace('mail', $mailHtmlPath);
        }
    }
}
