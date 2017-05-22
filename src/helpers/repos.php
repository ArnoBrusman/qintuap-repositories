<?php

use Qintuap\Repositories\Repos;
use Qintuap\Repositories\Factory;
use Qintuap\Repositories\EloquentRepository;

if (! function_exists('repo')) {
    
    /**
     * 
     * @param mixed $repoable
     * @return EloquentRepository
     */
    function repo($repoable)
    {
        $factory = app(Factory::class);
        return $factory->make($repoable);
    }
}
    
if (! function_exists('fetchRelation')) {
    function fetchRelation($model,$relationName)
    {
//        class_basename($class)
        return repo($model)->getRelation($model,$relationName);
    }
    
}

if (! function_exists('repo_push')) {
    
    /**
     * 
     * @param mixed $repoable
     * @return EloquentRepository
     */
    function repo_push($repoable)
    {
        $factory = app(Factory::class);
        return $factory->make($repoable)->push($repoable);
    }
}