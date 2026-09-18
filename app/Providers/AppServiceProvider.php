<?php

namespace App\Providers;

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
        foreach (['social-read'=>30,'room-read'=>120,'room-write'=>90,'room-create'=>10,'room-invite'=>5] as $name=>$limit) {
            \Illuminate\Support\Facades\RateLimiter::for($name, fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($name.':'.($request->attributes->get('player')?->id ?? $request->ip())));
        }
    }
}
