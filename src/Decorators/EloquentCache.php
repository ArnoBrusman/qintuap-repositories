<?php

namespace Qintuap\Repositories\Decorators;

use Closure;
use SplObjectStorage;
use SplFileObject;
use ReflectionFunction;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Pluralizer;
use Qintuap\Repositories\Contracts\Repository as RepositoryContract;
use Qintuap\Scopes\Contracts\Scoped;
use Qintuap\Repositories\EloquentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Qintuap\Scopes\Scope;
use Qintuap\CacheDecorators\Facades\DecoCache;
use Qintuap\Repositories\Traits\HasQueryState;
use Qintuap\CacheDecorators\Contracts\CacheDecorator;
use Qintuap\CacheDecorators\Contracts\CacheableScopes;

/**
 * Makes sure the majority of expensive queries that go through the repository are cached.
 *
 * @author Premiums
 */
class EloquentCache implements CacheDecorator,CacheableScopes, RepositoryContract, Scoped
{
//    use HasQueryState {
//        HasQueryState::__call as protected queryCall;
//    }
    
    /**
     * @var EloquentRepository
     */
    protected $repository;
    
    /**
     * @var array Any search that includes these relations will also include these tags.
     */
    protected $relationTags = [];
    
    /**
     * @var Cache
     */
    protected $cache;
    protected $cached = true;
    protected $generic_methods = [];
    var $scopes_cache = [];
    var $scope_tags = []; // array of what methods have extra tags.
    var $tags = [];
    /**
     * time in minutes that the values will be cached. Set to 0 for forever.
     */
    protected $cachetime = 0;
    
    public function __construct(Cache $cache, EloquentRepository $repository)
    {
        $this->repository = $repository;
        $this->cache = $cache;
        $this->tags = $this->makeClassTags();
    }

    public function cached($bool = true)
    {
        $this->cached = $bool;
    }
    
    public function useCache()
    {
        if(!$this->cache) return false;
        
        $cacheable = $this->scopesAreCacheable();
        
        return $cacheable;
    }
    
    /** generic methods **/
    
    protected function buildKey($method, $identifier = null)
    {
        foreach ($this->repository->getScopes() as $Scope) {
            if($Scope instanceof Scope && $Scope->useCache()) {
                $identifier .= '.'.$Scope->getCacheKey();
//            } elseif(is_array($Scope) && is_callable($Scope)) {
                
//            } elseif($Scope instanceof Closure) {
//                \Debugbar::startMeasure('hash','Hashing scope');
//                $identifier .= $this->hashClosure($Scope);
//                \Debugbar::stopMeasure('hash');
            } elseif(is_string($Scope)) {
                $identifier .= '.'.$Scope;
            }
        }
        $identifier .= 'order:';
        foreach ($this->repository->getOrders() as $attr => $order) {
            $identifier .= '.'.$attr . '-' . $order;
        }
        if(isset($this->repository->limit_max)) {
            $identifier .= 'limit:'.$this->repository->limit_max;
        }
        
        return md5($method . $identifier);
    }

    protected function getTags($withScopes = true)
    {
        $tags = $this->tags;
        if($withScopes) {
            foreach ($this->repository->getScopes() as $Scope) {
                if($Scope instanceof Scope && $Scope->useCache()) {
                    $tags = array_merge($tags, $Scope->getCacheTags());
                }
            }
        }
        return $tags;
    }
    
    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all($columns = ['*'])
    {
        if ( ! $this->useCache()) {
            return $this->repository->all($columns);
        }

        // __METHOD__ returns App\Repositories\Decorators\CacheGroupDecorator::all so not confused with other all() methods.
        $key = $this->buildKey(__METHOD__, join('.', $columns));
        $tags = $this->getTags();

        return $this->cache->tags($tags)->rememberForever($key, function () use ($columns) {
            return $this->repository->all($columns);
        });
    }

    public function allWith($relations)
    {
        if(is_string($relations)) {
            $relations = [$relations];
        }
        $tags = $this->getRelationTags($relations);
        return $this->genericMethodCache(__FUNCTION__, func_get_args(), $tags);
    }
    
    /**
     * @param Model $model
     * @return Model
     */
    public function push(Model $model)
    {
        $tags = $this->getTags(false);
        
        // also clear tags for any set relation
        foreach ($model->getRelations() as $relation) {
            if($relation instanceof Collection) $relation = $relation->first();
            $tags = array_merge($tags,$this->makeClassTags($relation));
        }
        
        $this->cache->tags($tags)->flush();
        
        
        return $this->repository->push($model);
    }
    
    /**
     * @param int $id
     * @param array $columns
     * @return \Illuminate\Database\Eloquent
     */
    public function find($id, $columns = array('*'))
    {
        return $this->genericMethodCache(__FUNCTION__, func_get_args());
    }
    
    public function findWith($id, $relations)
    {
        if(is_string($relations)) {
            $relations = [$relations];
        }
        $tags = $this->getRelationTags($relations);
        return $this->genericMethodCache(__FUNCTION__, func_get_args(), $tags);
    }
    
    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent
     */
    public function first($columns = array('*'))
    {
        if ( ! $this->useCache()) {
            return $this->repository->first($columns);
        }

        $key = $this->buildKey(__METHOD__, implode('-', $columns));
        $tags = $this->getTags();

        return $this->cache->tags($tags)->rememberForever($key, function () use ($columns) {
            return $this->repository->first($columns);
        });
    }
    
    public function create(array $data, $push = true)
    {
        if($push) {
            $this->cache->tags($this->tags)->flush();
        }
        return $this->repository->create($data,$push);
    }

    public function delete($id)
    {
        $this->cache->tags($this->tags)->flush();
        return $this->repository->delete($id);
    }

    public function findBy($field, $value, $columns = ['*'])
    {
        if ( ! $this->useCache()) {
            return $this->repository->findBy($field, $value, $columns);
        }
        $callback = function() use ($field, $value, $columns) {
            return $this->repository->findBy($field, $value, $columns);
        };
        return $this->_cacheMethod(__FUNCTION__, implode('.', func_get_args()), $callback);
    }

    public function paginate($perPage = 15, $columns = [])
    {
        $callback = function() use ($perPage, $columns) {
            return $this->repository->paginate($perPage, $columns);
        };
        
        return $this->_cacheMethod(__METHOD__, implode('.', func_get_args()), $callback);
    }

    public function update($id, array $data = [])
    {
        $this->cache->tags($this->tags)->flush();
        return $this->repository->update($id, $data);
    }
    
    public function sync($id, $relation, $datas, $detaching = false)
    {
        $tags = $this->getRelationTags($relation);
        
        $this->cache->tags($tags)->flush();
        return $this->repository->sync($id, $relation, $datas, $detaching);
    }
    
    public function exists($id)
    {
        return $this->repository->exists($id);
    }
    
    public function ofRelation($relationName,$relation) {
        $tags = $this->getRelationTags($relationName);
        $this->repository->pushCallableScope([$this->repository, 'scope' . ucfirst(__FUNCTION__)], [$relationName,$relation], $tags);
        return $this;
    }
    
    public function getRelation(Model $model,$relationName, \Closure $callback = null)
    {
        if(method_exists($model, $relationName)) {
//            return $model->getRelationValue($relationName);
            $relationQuery = $model->$relationName();
            if($callback) {
//                $snitch = new Snitch($relationQuery);
                $relationQuery = $callback($relationQuery);
//                if($snitch->isResultCacheable()) {
//                    $tags = $snitch->getTags();
//                    $key = $snitch->getKey();
//                    $result = $this->cache->tags($tags)->rememberForever($key,function() {
//                        return $relationQuery->getResults();
//                    });
//                } else {
                    $result = $relationQuery->getResults();
//                }
                return $result;
            }
        }
    }
    
    public function queryRelation(Model $model, $relationName, $callback) {
        $tags = $this->getRelationTags($relationName);
        $result = $this->genericMethodCache(__FUNCTION__, func_get_args(), $tags);
        return $result;
    }
    
    public function with($relations)
    {
        if(is_string($relations)) {
            $relations = [$relations];
        }
        $tags = $this->getRelationTags($relations);
        $this->repository->pushCallableScope([$this->repository, 'scope' . ucfirst(__FUNCTION__)], [$relations], $tags);
        return $this;
    }
    
    /**
     * 
     * @param type $method __METHOD__
     * @param type $identifier Unique identifier. Often derived from the method's given parameters.
     * @param type $callback Method that calls the function of wich result should be cached
     * @return mixed Cached result
     */
    protected function _cacheMethod($method, $identifier, $callback)
    {
        if ( ! $this->useCache()) {
            return $callback();
        }
    
        $key = $this->buildKey($method, $identifier);
        $tags = $this->getTags();
        return $this->cache->tags($tags)->rememberForever($key, $callback);
    }
    
    /**
     * methods that won't get cached
     */
    protected $dont_cache = [ ];


    public function __call($method, $parameters)
    {
//        $builder = $this->queryCall($method,$parameters);
//        if($builder) return $builder;
        // cache certain methods
        if (    !in_array($method, $this->dont_cache)
                && !preg_match('/random/', $method) 
                && !preg_match('/Criteria$/', $method)
                && !$this->repository->methodScopeExists($method)
                && ( 
                        preg_match('/^find/', $method)
                        || preg_match('/^all/', $method)
                        || preg_match('/^count/', $method)
                        || preg_match('/^fetch/', $method)
                        || preg_match('/^get/', $method)
                                )) {
            return $this->genericMethodCache($method, $parameters);
        }

        return $this->delegate($method, $parameters);
    }

    protected function genericMethodCache($method, array $parameters = [], $tags = null)
    {
        if ( ! $this->useCache()) {
            return $this->delegate($method, $parameters);
        }
        if($tags === null) {
            $tags = $this->getTags();
        }
        if(is_callable([$this->repository, $method])) {
            $key = $this->buildKey(get_class($this).'\\'.$method, json_encode($parameters));

            if($this->cachetime === 0) {
                return $this->cache->tags($tags)->rememberForever($key, function () use($method,$parameters) {
                    $result = call_user_func_array([$this->repository, $method], $parameters);
                    return $result;
                });
            } else {
                return $this->cache->tags($tags)->remember($key, $this->cachetime, function () use($method,$parameters) {
                    return call_user_func_array([$this->repository, $method], $parameters);
                });
            }
        } else {
            // it's probably still a non-cacheable repository method.
            return call_user_func_array([$this->repository, $method], $parameters);
//            throw new \Exception('method ' . $method . ' not found');
        }
    }

    protected function delegate($method,$parameters)
    {
//        if ($this->repository->methodScopeExists($method)) {
//            $scope = 'scope'.ucfirst($method);
//            $response = $this->repository->pushCallableScope([$this, $scope], $parameters);
//        } else {
            $response = call_user_func_array([$this->repository, $method], $parameters);
//        }
        // is the repo trying to chain?
        if($response instanceof EloquentRepository) {
            return $this;
        } else {
            return $response;
        }
    }


    /* ----------------------------------------------------- *\
     * Scope methods. 
     * ----------------------------------------------------- */
    
    
    protected function scopesAreCacheable()
    {
        if($this->repository->hasScope()) {
            foreach ($this->repository->getScopes() as $Scope) {
                if(!($Scope instanceof Scope && $Scope->useCache())
//                        && !is_array(is_array($Scope) && is_callable($Scope))
                        && !is_string($Scope)
                        ) {
                    if(config('app.env') !== 'production') {
                        $scopeName = $Scope instanceof Scope ? $Scope->getName() : '';
                        \Debugbar::addMessage('note: can\'t cache scope: ' .$scopeName , 'warning');
                        \Debugbar::addMessage($Scope, 'warning');
                    }
                    return false;
                }
            }
        }
        return true;
    }
    
    public function useScopeCache($method,$parameters)
    {
        return isset($this->scopes_cache) && (
                $this->scopes_cache === true 
                || $method === 'scopeOfRelation'
                || in_array($method, $this->scopes_cache));
    }

    public function makeScopeCacheKey($method, $parameters)
    {
        foreach ($parameters as &$parameter) {
            if($parameter instanceof Model) {
                $parameter = $parameter->getKey();
            }
        }
        $cache_key = md5(json_encode(array(
                $method,
                $parameters
            )));
        return $cache_key;
    }
    
    public function makeScopeCacheTags($method, $parameters)
    {
        $tags = $this->tags;
        foreach ($parameters as $parameter) {
            if($parameter instanceof Model) {
                $tags[] = DecoCache::makeModelTag($parameter);
            }
        }
        if(isset($this->scope_tags) && key_exists($method, $this->scope_tags)) {
            $scope_tags = is_array($this->scope_tags[$method]) 
                    ? $this->scope_tags[$method]
                    : [$this->scope_tags[$method]];
            $tags = array_merge($tags, $scope_tags);
        }
        return $tags;
    }

    public function hasScope()
    {
        return $this->delegate(__FUNCTION__, func_get_args());
    }

    public function pushCallableScope($callable, array $parameters = [])
    {
        return $this->delegate(__FUNCTION__, func_get_args());
    }

    public function pushScope($scope, Closure $implementation = null)
    {
        return $this->delegate(__FUNCTION__, func_get_args());
    }

    public function pushScopes(array $scopes)
    {
        return $this->delegate(__FUNCTION__, func_get_args());
    }

    public function resetScope()
    {
        return $this->delegate(__FUNCTION__, func_get_args());
    }

    /* ----------------------------------------------------- *\
     * Utility methods
     * ----------------------------------------------------- */
    
    /**
     * List of hashes
     *
     * @var SplObjectStorage
     */
    protected static $hashes = null;

    /**
     * Returns a hash for closure
     *
     * @param callable $closure
     *
     * @return string
     */
    public function hashClosure(Closure $closure)
    {
        if (!self::$hashes) {
            self::$hashes = new SplObjectStorage();
        }

        if (!isset(self::$hashes[$closure])) {
            $ref  = new ReflectionFunction($closure);
            $file = new SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine()-1);
            $content = '';
            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }
            self::$hashes[$closure] = md5(json_encode(array(
                $content,
                $ref->getStaticVariables()
            )));
        }
        return self::$hashes[$closure];
    }
    
    public function makeClassTags($class = null)
    {
        if(is_null($class)) {
            $class = $this->repository->getModelTable();
        }
        return [$class];
    }

    protected function getRelationTags($relations)
    {
        if(is_string($relations)) $relations = [$relations];
        $tags = $this->getTags();
        foreach($relations as $relation) {
            if(key_exists($relation,$this->relationTags)) {
                $_relations = $this->relationTags[$relation];

                if(is_string($_relations)) {
                    $_relations = [$_relations];
                }
                foreach ($_relations as $_relation) {
                    $tags[] = $_relation;
                }
            }elseif($relationClass = $this->repository->getRelationClass($relation)) {
                $tags[] = $relationClass;
            } else {
                throw new Exception('relation class was not found.');
            }
        }
        return $tags;
    }
    
    public function newRepo($tags = [])
    {
        return new self($this->cache, $this->repository->newRepo(),$tags);
    }
    
    /**
     * Force a clone of the underlying repository when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->repository = clone $this->repository;
    }

}
