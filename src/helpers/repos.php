<?php

use Qintuap\Repositories\Repos;
use Qintuap\Repositories\Factory;

if (! function_exists('just_key')) {
    
    function repo($repoable)
    {
        $factory = app(Factory::class);
        return $factory->make($repoable);
    }
    
}