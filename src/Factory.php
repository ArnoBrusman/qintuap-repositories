<?php

namespace Qintuap\Repositories;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Contracts\Repository;

/**
 * @author Premiums
 */
class Factory implements Contracts\Factory {
    
    protected $namespaces = [
    ];
    protected $entityPath = 'Models';
    protected $contractPath = 'Repositories\\Contracts';
    protected $repositoryPath = 'Repositories';
    
    static $decoratorFactories = [];
    
    public function __construct()
    {
        $namespaces = config('qintuap.namespaces');
        foreach ($namespaces as $namespace) {
            $this->addNamespace($namespace);
        }
    }
    
    function addNamespace($namespace) {
        if(!in_array($namespace, $this->namespaces)) {
            $this->namespaces[] = $namespace;
        }
    }
    
    /**
     * 
     * @param type $repoAble
     * @return Repository
     */
    function make($repoAble) {
        
        if(!$this->isRepoAble($repoAble))
            throw new Exception('No repository could be created from argument');
            
        $name = $this->makeRepoNameFromRepoable($repoAble);
//        if(is_string($namespace)) {
//            $repoName = $namespace.'\\Contracts\\'. ucfirst($name);
//        } else {
//            $repoName = $this->getContract($name);
//        }
        return $this->makeRepo($name);
    }
    
    function isRepoAble($repoAble)
    {
        $name = $this->makeRepoNameFromRepoable($repoAble);
        
        $entityName = $this->getEntity($name);
//        $repositoryFullName = $this->getRepository($repoName);
        
        return $entityName !== false;
    }
    
    protected function makeRepoNameFromRepoable($repoAble)
    {
        if($repoAble instanceof Model) {
            $repoAble = class_basename($repoAble);
        }
        if($repoAble instanceof Repository) {
            $repoAble = $repoAble->getModelName();
        }
        
        return $repoAble;
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
        $repo = new $repositoryFullName($model, $this);
        
        $decorated = $this->decorate($repo,$repoName);
        if(!$decorated) {
            \Debugbar::addMessage('Repo '.$repoName.'could not be decorated', 'error');
        } else {
            $repo = $decorated;
        }
        
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
        $model = $this->getEntity($modelName);
        if(!$model) {
            throw new Exception('Implementation of ' . $modelName . ' not found.');
        }
        return new $model();
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
        return false;
    }
    
    protected function getRepository($repoName = '')
    {
        foreach ($this->namespaces as $namespace) {
            $class = $namespace . '\\' . $this->repositoryPath . '\\' . $repoName . 'Repository';
            if(class_exists($class)) {
                return $class;
            }
        }
        return EloquentRepository::class;
    }
    
    function addDecoratorFactory($factory)
    {
        self::$decoratorFactories[] = $factory;
    }
    
}
