<?php

namespace Advanza\Repositories\Traits;

use Closure;
use Advanza\Repositories\Contracts\Scoped;
use Advanza\Repositories\Scopes\Scope;

/**
 * Delegate scope functions to the underlying repositories or builder calls of the object.
 * WARNING: uses the __call method to intercept methods starting with 'scope' before 
 *   calling the parent's __call method.
 * @author Premiums
 */
trait DelegatesScopes  {

    use HasScopes;
    
    protected $delegatedScopes = [];

    /**
     * Set to true if you want the __call method to push a scope for unknown methods.
     * @var bool 
     */
    public $overloadScopes = false;
    
    function pushDelegatedScope($scope, $parameters = [])
    {
        $this->delegatedScopes[] = [$scope, $parameters];
        return $this;
    }
    
    /**
     * Push the scopes on the given delegate.
     * @param Scoped $delegate The object the scopes are meant for.
     * @param bool $reset Reset the delegate before pushing scopes?
     * @return type
     */
    function delegateScopes(Scoped $delegate, $reset = true)
    {
        if($reset) {
            $delegate->resetScope();
        }
        foreach($this->scopes as $scope) {
            if(is_callable($scope)) {
                $delegate->pushCallableScope($scope);
            } else {
                call_user_func([$delegate,$scope]);
            }
        }
        foreach ($this->delegatedScopes as $scope) {
            call_user_func_array([$delegate,$scope[0]], $scope[1]);
        }

        return $this;
    }
    
    public function resetScope()
    {
        $this->scopes = [];
        $this->delegatedScopes = [];
        return $this;
    }
    
    /**
     * @return bool
     */
    public function hasScope($scope = null)
    {
        if($scope) {
            return isset($this->scopes[$scope]) || isset($this->delegatedScopes[$scope]);
        }
        return !empty($this->scopes) && !empty($this->delegatedScopes);
    }
    

    /* ----------------------------------------------------- *\
     * Overload Methods
     * ----------------------------------------------------- */
    
    public function __call($method, $parameters)
    {
        if (method_exists($this, $scope = 'scope'.ucfirst($method))) {
            return $this->pushCallableScope([$this, $scope], $parameters);
        }
        if($this->overloadScopes) {
            return $this->pushDelegatedScope('scope' . ucfirst($method), $parameters);
        }
        if (is_callable('parent::__call')) {
            return parent::__call($method, $parameters);
        }
        
    }
}
