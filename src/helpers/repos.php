<?php

use Advanza\Repos;

if (! function_exists('just_key')) {
    
    function repo($repo_name)
    {
        return Repos::make($repo_name);
    }
    
}