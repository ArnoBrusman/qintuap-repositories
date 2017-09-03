<?php

namespace Qintuap\Repositories;

use Illuminate\Support\ServiceProvider;

/**
 * @author Arno
 */
class RepositoryServiceProvider extends ServiceProvider {
    
    public function boot() 
    {
        //
    }
    
    public function register()
    {
        $this->app->singleton(Factory::class, function($app) {
            return new Factory();
        });
        $this->app->singleton(DecoratorFactory::class, function($app) {
            $decorators = config('qintuap.repo_cache_decorators');
            return new DecoratorFactory($decorators);
        });
    }
}
