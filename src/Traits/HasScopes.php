<?php

namespace Advanza\Repositories\Traits;

use Closure;
use Advanza\Repositories\Scopes\Scope;
use Advanza\Repositories\Scopes\ScopeCall;

/**
 * Scopes are methods defined in the eloquent models or repositories, to be used in the 
 * builder to specify searches
 * WARNING: uses the __call method to intercept methods starting with 'scope' before 
 *   calling the parent's __call method.
 * @author Premiums
 */
trait HasScopes  {

    /**
     * @var array
     */
    protected $scopes = [];
    /**
     * @var bool
     */
    public $skipScopes = false;

    /**
     * @param bool $status
     * @return $this
     */
    public function skipScopes($status = true)
    {
        $this->skipScopes = $status;
        return $this;
    }
    
    public function pushScope($scope, Closure $implementation = null)
    {
        if (is_string($scope) && ! is_null($implementation)) {
            $this->scopes[$scope] = $implementation;
        } elseif ($scope instanceof Closure) {
            $this->scopes[spl_object_hash($scope)] = $scope;
        } elseif ($scope instanceof Scope) {
            $this->scopes[get_class($scope)] = $scope;
        } elseif (is_callable($scope) && is_array($scope)) {
            $this->scopes[$scope[1]] = $scope;
        } else {
            $this->scopes[$scope] = $scope;
        }
        
        return $this;
    }
    
    public function removeScope($scope)
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }
        unset($this->scopes[$scope]);
    }
    
    public function pushScopes(array $scopes)
    {
        foreach ($scopes as $scope) {
            $this->pushScope($scope);
        }
        return $this;
    }
    
    function pushCallableScope($callable, array $arguments = [], $tags = [])
    {
        $scope = new ScopeCall($callable, $arguments, $tags);
        $this->pushScope($scope);
        return $this;
    }

    function applyScopes($query = null)
    {
        if(is_null($query)) 
            $query = $this->newQuery();
        if($this->skipScopes === true)
            return $query;
        
        foreach($this->getScopes() as $scope) {
            if($scope instanceof Closure) {
                $query = $scope($query) ?: $query;
            } elseif ($scope instanceof Scope) {
                $query = $scope->apply($query, $this->model) ?: $query;
            } elseif (is_callable($scope)) {
                $query = call_user_func($scope, $query);
            } else {
                error_log('Scope \'' . $scope .'\' couldn\'t be found. ', E_NOTICE);
            }
        }

        return $query;
    }
    
    public function resetScope()
    {
        $this->scopes = [];
        return $this;
    }
    
    /**
     * @return bool
     */
    public function hasScope($scope = null)
    {
        if($scope) {
            return isset($this->scopes[$scope]);
        }
        return !empty($this->scopes);
    }
    
    public function getScopes()
    {
        return $this->scopes;
    }
    

    /* ----------------------------------------------------- *\
     * Overload Methods
     * ----------------------------------------------------- */
    
    public function __call($method, $parameters)
    {
        if (method_exists($this, $scope = 'scope'.ucfirst($method))) {
            return $this->pushCallableScope([$this, $scope], $parameters);
        }
        
        if (is_callable('parent::__call')) {
            return parent::__call($method, $parameters);
        }
        
    }
}
