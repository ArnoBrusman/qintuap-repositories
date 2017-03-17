<?php

use Qintuap\Repositories\Repos;
use Qintuap\Repositories\Factory;

if (! function_exists('repo')) {
    
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