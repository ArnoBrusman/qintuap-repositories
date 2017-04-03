<?php

namespace Qintuap\Repositories;


use Illuminate\Support\Facades\Facade;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Contracts\Repository;

/**
 * Repo factory facade
 * @method Repository make()
 */
class Repos extends Facade {
    
    static $repo_namespaces = [
        'App\\Repositories',
        'Advanza\\Repositories'
    ];
    
    /**
     * Get the binding in the IoC container
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class; // the IoC binding.
    }
    
//    static function addNamespace($namespace) {
//    }
//    
//    static function make($name, $namespace = null) {
//    }
    
}
