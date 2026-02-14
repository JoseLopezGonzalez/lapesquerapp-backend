<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Order::class => \App\Policies\OrderPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Salesperson::class => \App\Policies\SalespersonPolicy::class,
        \App\Models\Label::class => \App\Policies\LabelPolicy::class,
        \App\Models\RawMaterialReception::class => \App\Policies\RawMaterialReceptionPolicy::class,
        \App\Models\Pallet::class => \App\Policies\PalletPolicy::class,
        \App\Models\Box::class => \App\Policies\BoxPolicy::class,
        \App\Models\CeboDispatch::class => \App\Policies\CeboDispatchPolicy::class,
        \App\Models\Store::class => \App\Policies\StorePolicy::class,
        \App\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Models\ProductCategory::class => \App\Policies\ProductCategoryPolicy::class,
        \App\Models\ProductFamily::class => \App\Policies\ProductFamilyPolicy::class,
        \App\Models\ActivityLog::class => \App\Policies\ActivityLogPolicy::class,
        \App\Sanctum\PersonalAccessToken::class => \App\Policies\SessionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
