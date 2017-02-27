<?php

namespace Qintuap\Repositories;


use Illuminate\Support\Facades\Facade;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Contracts\Repository;

/**
 * Repo factory facade
 * @author Premiums
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
//        if(!in_array($namespace, self::$repo_namespaces)) {
//            array_unshift(self::$repo_namespaces[] , $namespace);
//        }
//    }
//    
//    static function make($name, $namespace = null) {
//        
//        if($name instanceof Model) {
//            $name = class_basename($name);
//        }
//        if($name instanceof Repository) {
//            $name = $name->getModelName();
//        }
//        
//        if(interface_exists($name)) {
//            return resolve($name);
//        }
//        
//        if($namespace === null) {
//            foreach (self::$repo_namespaces as $namespace) {
//                $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
//                if(interface_exists($repoName)) {
//                    return resolve($repoName);
//                }
//            }
//        } else {
//            $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
//            if(interface_exists($repoName)) {
//                return resolve($repoName);
//            }
//        }
//        throw new Exception('repo ' . $name . ' doesn\'t exist');
//    }
    
}
