<?php

declare(strict_types=1);

namespace Naram\PageVisits;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Naram\PageVisits\Console\FlushPageVisitsCommand;
use Naram\PageVisits\Contracts\PageVisitStore;
use Naram\PageVisits\Http\Controllers\PageVisitController;
use Naram\PageVisits\Services\RedisPageVisitStore;
use Naram\PageVisits\View\Components\VisitTracker;

final class PageVisitsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/page-visits.php'), 'page-visits');

        $this->app->singleton(PageVisitStore::class, RedisPageVisitStore::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->packagePath('database/migrations'));
        $this->loadViewsFrom($this->packagePath('resources/views'), 'page-visits');

        Blade::component('visit-tracker', VisitTracker::class);

        $this->registerRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([FlushPageVisitsCommand::class]);
            $this->registerPublishing();
        }
    }

    private function registerRoute(): void
    {
        /** @var array<int, string> $middleware */
        $middleware = Config::array('page-visits.route.middleware');

        Route::post(Config::string('page-visits.route.uri'), PageVisitController::class)
            ->middleware($middleware)
            ->name(Config::string('page-visits.route.name'));
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            $this->packagePath('config/page-visits.php') => $this->app->configPath('page-visits.php'),
        ], 'page-visits-config');

        $this->publishes([
            $this->packagePath('database/migrations') => $this->app->databasePath('migrations'),
        ], 'page-visits-migrations');

        $this->publishes([
            $this->packagePath('resources/views') => $this->app->resourcePath('views/vendor/page-visits'),
        ], 'page-visits-views');

        $this->publishes([
            $this->packagePath('resources/js') => $this->app->resourcePath('js/vendor/page-visits'),
        ], 'page-visits-assets');
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__).'/'.$path;
    }
}
