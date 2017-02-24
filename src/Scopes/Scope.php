<?php

namespace Advanza\Repositories\Scopes;

use Advanza\Repositories\Contracts\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope as EloquentScope;

/**
 * Description of Scope
 *
 * @author Premiums
 */
abstract class Scope implements EloquentScope {
    
    /**
     * The unique cache key used to cache the results
     * Leave false to not cache.
     */
    var $cache_key = false;

    public abstract function apply(Builder $query, Model $model);
    
    public function useCache() {
        return !$this->cache_key === false;
    }

}
