<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandStarting::class,
            function ($event) {
                $skip = [
                    'migrate', 'migrate:fresh', 'migrate:rollback', 'migrate:reset', 'migrate:refresh',
                    'db:seed', 'queue:work', 'queue:listen', 'queue:restart', 'package:discover',
                    'config:cache', 'config:clear', 'route:cache', 'route:clear', 'view:cache', 'view:clear',
                    'clear-compiled', 'key:generate',
                ];
                if (in_array($event->command, $skip, true)) {
                    return;
                }
                $tenant = app(\App\Services\Cloud\CloudTenantResolver::class)->forConsole();
                if ($tenant) {
                    app(\App\Services\Cloud\CloudTenantContext::class)->set($tenant);
                }
            }
        );
    }
}
