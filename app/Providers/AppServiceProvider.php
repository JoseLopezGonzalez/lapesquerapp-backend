<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Pallet;
use App\Models\RawMaterialReception;
use App\Sanctum\PersonalAccessToken;
use App\Services\Production\ProductionCostResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $this->app->scoped(ProductionCostResolver::class, function () {
            return new ProductionCostResolver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'pallet' => Pallet::class,
            'order' => Order::class,
            'reception' => RawMaterialReception::class,
        ]);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Model::preventLazyLoading(! app()->isProduction());

        // Asegurar que el tema de correos markdown use nuestro default.css (resources/views/vendor/mail/html/themes/)
        // Sin esto, Laravel puede cargar el tema del framework y los estilos personalizados no se aplican.
        $mailHtmlPath = resource_path('views/vendor/mail/html');
        if (is_dir($mailHtmlPath)) {
            View::addNamespace('mail', $mailHtmlPath);
        }
    }
}
