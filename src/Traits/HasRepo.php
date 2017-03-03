<?php

namespace Qintuap\Repositories\Traits;

use Qintuap\Repositories\Contracts\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait that add repository functionality to a model.
 * @author Premiums
 */
trait HasRepo {
    
    
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

        return $this->repository = repo($this);
    }
    
    function getRelationKeyName($relationName) {
        return $this->$relationName()->getRelated()->getQualifiedKeyName();
    }
    
    function getRelationRepo($relationName) {
        return repo($this->$relationName()->getRelated());
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
}
