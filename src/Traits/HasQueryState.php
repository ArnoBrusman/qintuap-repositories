<?php

namespace Qintuap\Repositories\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;
use Closure;

/**
 * When in the query state, all calls go directly to the query itself. 
 * The parameters are stored to create a caching key.
 * When the results are then asked for, the results are cached for future
 * requests with the same scope & parameters.
 * Note: for now, scopes with closures as parameters aren't cached.
 *
 * @author Premiums
 */
trait HasQueryState {

    
    
    /**
     * @var Builder
     */
    protected $current_query;
    protected $query_cachable = true;
    protected $querying = false;
    protected $querying_relation = false;

    protected $query_scopes = [];
    protected $query_scope_parameters = [];
    
    protected $last_query_key;
    
    function startQuerying()
    {
        $this->querying = true;
        $this->query = $this->newQuery();
    }
    
    function startRelationQuerying($relation)
    {
        $this->querying = true;
        $this->querying_relation = true;
        $this->query = $this->newRelationQuery($relation);
    }
    
    function queryIsCachable()
    {
        if(!$this->query_cachable) return false;
        $queryKey = $this->makeQueryKey();
        return !$queryKey;
    }
    
    function stopQuerying()
    {
        $this->query_cachable = true;
        $this->querying = false;
        $this->query = null;
        $this->query_scope_parameters = [];
        $this->query_scopes = [];
    }
            
    function __call($method, $parameters = [])
    {
        if($this->querying) {
            
            $this->addQueryScope($method,$parameters);
            
            return call_user_func([$this->query,$method], $parameters);
        }
    }
    
    function addQueryScope($scope,$parameters = [])
    {
        if(in_array($scope, $this->query_scopes)) {
            $i = 1;
            do {
                $i++;
                $scope = $scope . '-' . $i;
            } while (in_array($scope, $this->query_scopes));
        }
        $this->query_scopes[] = $scope;
        $this->query_scope_parameters[$scope] = $parameters;
    }
    
    function makeQueryKey($query = null)
    {
        if(is_null($query)) $query = $this->query;
        return hash('sha256', $query->toSql().serialize($query->getBindings()));
    }
    
    function makeQueryTags($query)
    {
        if(is_null($query)) $query = $this->query;
        $tables = [$query->table] + $query->joins;
        return $tables;
    }
    
    function getQueryGetResults($query)
    {
        if(is_null($query)) $query = $this->query;
        $key = $this->makeQueryKey($query);
        $tags = $this->makeQueryTags($query);
        return $query->get();
    }
    
}
