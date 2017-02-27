<?php

namespace Qintuap\Repositories;

use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Contracts\Repository;

/**
 * @author Premiums
 */
class Factory {
    
    protected $repo_namespaces = [
        'App\\Repositories',
        'Advanza\\Repositories'
    ];
    
    function addNamespace($namespace) {
        if(!in_array($namespace, $this->repo_namespaces)) {
            array_unshift($this->repo_namespaces[] , $namespace);
        }
    }
    
    function make($name, $namespace = null) {
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
            foreach ($this->repo_namespaces as $namespace) {
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
