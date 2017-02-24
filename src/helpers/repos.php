<?php

use Qintuap\Repos;

if (! function_exists('just_key')) {
    
    function repo($repoable)
    {
        return Repos::make($repoable);
    }
    
}