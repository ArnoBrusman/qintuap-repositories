<?php

namespace Qintuap\Repositories;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Contracts\Repository;

/**
 * @author Premiums
 */
class Factory {
    
    protected $namespaces = [
        'App',
    ];
    protected $entityPath = 'Models';
    protected $contractPath = 'Repositories\\Contracts';
    protected $repositoryPath = 'Repositories';
    
    static $decoratorFactories = [];
            
    function addNamespace($namespace) {
        if(!in_array($namespace, $this->namespaces)) {
            $this->namespaces[] = $namespace;
        }
    }
    
    function make($name) {
        if($name instanceof Model) {
            $name = class_basename($name);
        }
        if($name instanceof Repository) {
            $name = $name->getModelName();
        }
//        if(is_string($namespace)) {
//            $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
//        } else {
//            $repoName = $this->getContract($name);
//        }
        return $this->makeRepo($name);
    }
    
    function makeRepo($name) {
        $repo = $this->_makeRepo($name);
        if($repo) {
           return $repo; 
        }
        throw new Exception('repo ' . $name . ' doesn\'t exist');
    }
    protected function _makeRepo($repoName)
    {
        $repositoryFullName = $this->getRepository($repoName);
        $model = $this->makeModel($repoName);
        $repo = new $repositoryFullName($model);
        
        $repo = $this->decorate($repo,$repoName);
        
        return $repo;
    }
    
    protected function decorate($repo,$name) {
        foreach (self::$decoratorFactories as $decorator) {
            $repo = $decorator->make($repo,$name);
        }
        return $repo;
    }

    protected function makeModel($modelName)
    {
        $modelFullName = $this->getEntity($modelName);
        if(!$modelFullName) {
            throw new Exception('Implementation of ' . $modelName . ' not found.');
        }
        return new $modelFullName();
    }
    
    protected function getContract($repoName = '')
    {
        foreach ($this->namespaces as $namespace) {
            $interface = $namespace . '\\' . $this->contractPath . '\\' . $repoName;
            if(interface_exists($interface)) {
                return $interface;
            }
        }
    }
    protected function getEntity($repoName = '')
    {
        foreach ($this->namespaces as $namespace) {
            $class = $namespace . '\\' . $this->entityPath . '\\' . $repoName;
            if(class_exists($class)) {
                return $class;
            }
        }
    }
    protected function getRepository($repoName = '')
    {
        foreach ($this->namespaces as $namespace) {
            $class = $namespace . '\\' . $this->repositoryPath . '\\' . $repoName . 'Repository';
            if(class_exists($class)) {
                return $class;
            }
        }
    }
    
    function addDecoratorFactory($factory)
    {
        self::$decoratorFactories[] = $factory;
    }
    
}
