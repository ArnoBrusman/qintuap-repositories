<?php

namespace Advanza;

use Exception;
use Advanza\Models\Model;
use Advanza\Repositories\Contracts\Repository;

/**
 * Repo factory
 * @author Premiums
 */
class Repos {
    
    static $repo_namespaces = [
        'App\\Repositories',
        'Advanza\\Repositories'
    ];
    
    static function addNamespace($namespace) {
        if(!in_array($namespace, self::$repo_namespaces)) {
            array_unshift(self::$repo_namespaces[] , $namespace);
        }
    }
    
    static function make($name, $namespace = null) {
        
        if($name instanceof Model) {
            $name = class_basename($name);
        }
        if($name instanceof Repository) {
            $name = $name->getModelName();
        }
        
        if(interface_exists($name)) {
            return resolve($name);
        }
        
        if($namespace === null) {
            foreach (self::$repo_namespaces as $namespace) {
                $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
                if(interface_exists($repoName)) {
                    return resolve($repoName);
                }
            }
        } else {
            $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
            if(interface_exists($repoName)) {
                return resolve($repoName);
            }
        }
        throw new Exception('repo ' . $name . ' doesn\'t exist');
    }
    
}
