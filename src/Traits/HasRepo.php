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
    
    public function makeMethodCacheKey($method, $parameters)
    {
        return $this->repo->makeMethodCacheKey($method, $parameters);
    }
    
    public function makeMethodCacheTags($method, $parameters)
    {
        return $this->repo->makeMethodCacheTags($method, $parameters);
    }
}
