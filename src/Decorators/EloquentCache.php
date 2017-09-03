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
 * @author Arno
 */
class EloquentCache implements CacheDecorator, RepositoryContract
{
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
        
        return true;
    }
    
    /** generic methods **/
    
    protected function buildKey($method, $identifier = null)
    {
        return md5($method . $identifier);
    }

    protected function getTags()
    {
        $tags = $this->tags;
        return $tags;
    }
    
    public function delete($id)
    {
        $this->cache->tags($this->tags)->flush();
        return $this->repository->delete($id);
    }

    public function update($id, array $data = [])
    {
        $this->cache->tags($this->tags)->flush();
        return $this->repository->update($id, $data);
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
        return call_user_func_array([$this->repository, $method], $parameters);
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
