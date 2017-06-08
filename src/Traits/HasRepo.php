<?php

namespace Qintuap\Repositories\Traits;

use Qintuap\Repositories\Contracts\Repository;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Repos;
use Qintuap\Repositories\Query\Builder;
use Qintuap\Repositories\Query\CacheBuilder;

/**
 * Trait that add repository functionality to a model.
 * @author Premiums
 */
trait HasRepo {
    
    protected $repository;
    protected $repoable;


    public function __get($key)
    {
        if ($key === 'repo')
            return $this->getRepository();
        else
            return parent::__get($key);
    }
    
    protected function getRepository()
    {
        if($this->repository instanceof Repository) {
            return $this->repository;
        }

        if($this->repoable) {
            $repository = Repos::make($this->repoable);
        } else {
            $repository = Repos::make($this);
        }
        $this->setRepository($repository);
        return $repository;
    }
    
    function getRelationKeyName($relationName) {
        return $this->$relationName()->getRelated()->getQualifiedKeyName();
    }
    
    function getRelationRepo($relationName) {
        return Repos::make($this->$relationName()->getRelated());
    }
    
    public function makeScopeCacheKey($method, $parameters)
    {
        return $this->repo->makeScopeCacheKey($method, $parameters);
    }
    
    public function makeScopeCacheTags($method, $parameters)
    {
        return $this->repo->makeScopeCacheTags($method, $parameters);
    }
    
    public function useScopeCache($method,$parameters)
    {
        return $this->repo->useScopeCache($method, $parameters);
    }
    
    function setRepository(Repository $repository)
    {
        $this->repository = $repository;
    }
    
}
