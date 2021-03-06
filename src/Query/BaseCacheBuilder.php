<?php

namespace Qintuap\Repositories\Query;

use Illuminate\Database\Query\Builder as IlluBaseBuilder;
use Illuminate\Support\Facades\Cache;
use Sofa\Eloquence\Query\Builder as BaseBuilder;

/**
 * @author Arno
 */
class BaseCacheBuilder extends BaseBuilder {
    
    /**
     * The key that should be used when caching the query.
     *
     * @var string
     */
    protected $cacheKey;
    /**
     * The number of minutes to cache the query.
     *
     * @var int
     */
    protected $cacheMinutes;
    /**
     * The tags for the query cache.
     *
     * @var array
     */
    protected $cacheTags = [];
    /**
     * The cache driver to be used.
     *
     * @var string
     */
    protected $cacheDriver;
    /**
     * A cache prefix.
     *
     * @var string
     */
    protected $cachePrefix = 'query';
    
    protected $query;

    /**
     * Execute the query as a cached "select" statement.
     *
     * @param  array  $columns
     * @return array
     */
    public function getCached($columns = ['*'])
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }
        // If the query is requested to be cached, we will cache it using a unique key
        // for this database connection and query statement, including the bindings
        // that are used on this query, providing great convenience when caching.
        list($key, $minutes) = $this->getCacheInfo();
        $cache = $this->getCache();
        $callback = $this->getCacheCallback($columns);
        // If the "minutes" value is less than zero, we will use that as the indicator
        // that the value should be remembered indefinitely and if we have minutes
        // we will use the typical remember function here.
        if (!$minutes || $minutes < 0) {
            return $cache->rememberForever($key, $callback);
        }
        return $cache->remember($key, $minutes, $callback);
    }
    
    /**
     * Indicate that the query results should be cached.
     *
     * @param  \DateTime|int  $minutes
     * @param  string  $key
     * @return $this
     */
    public function remember($minutes, $key = null)
    {
        list($this->cacheMinutes, $this->cacheKey) = [$minutes, $key];
        return $this;
    }
    /**
     * Indicate that the query results should be cached forever.
     *
     * @param  string  $key
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function rememberForever($key = null)
    {
        return $this->remember(-1, $key);
    }
    /**
     * Indicate that the query should not be cached.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function dontRemember()
    {
        $this->cacheMinutes = $this->cacheKey = $this->cacheTags = null;
        return $this;
    }
    /**
     * Indicate that the query should not be cached. Alias for dontRemember().
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function doNotRemember()
    {
        return $this->dontRemember();
    }
    /**
     * Indicate that the results, if cached, should use the given cache tags.
     *
     * @param  array|mixed  $cacheTags
     * @return $this
     */
    public function cacheTags($cacheTags)
    {
        $this->cacheTags = $cacheTags;
        return $this;
    }
    /**
     * Indicate that the results, if cached, should use the given cache driver.
     *
     * @param  string  $cacheDriver
     * @return $this
     */
    public function cacheDriver($cacheDriver)
    {
        $this->cacheDriver = $cacheDriver;
        return $this;
    }
    /**
     * Get the cache object with tags assigned, if applicable.
     *
     * @return \Illuminate\Cache\CacheManager
     */
    protected function getCache()
    {
        $cache = Cache::driver($this->cacheDriver);
        
        $tags = $this->getTags();
        
        return $tags ? $cache->tags($tags) : $cache;
    }
    /**
     * Get the cache key and cache minutes as an array.
     *
     * @return array
     */
    protected function getCacheInfo()
    {
        return [$this->getCacheKey(), $this->cacheMinutes];
    }
    /**
     * Get a unique cache key for the complete query.
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cachePrefix.':'.($this->cacheKey ?: $this->generateCacheKey());
    }
    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
//        $name = $this->connection->getName();
        $name = $this->getConnection()->getName();
        return hash('sha256', $name.$this->toSql().serialize($this->getBindings()));
    }
    /**
     * Flush the cache for the current model or a given tag name
     *
     * @param  mixed  $cacheTags
     * @return boolean
     */
    public function flushCache($cacheTags = null)
    {
        if ( ! method_exists(Cache::getStore(), 'tags')) {
            return false;
        }
        $cacheTags = $cacheTags ?: $this->getTags();
        Cache::tags($cacheTags)->flush();
        return true;
    }
    /**
     * Get the Closure callback used when caching queries.
     *
     * @param  array  $columns
     * @return \Closure
     */
    protected function getCacheCallback($columns)
    {
        return function () use ($columns) {
            return parent::get($columns);
        };
    }
    
    /**
     * Set the cache prefix.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function prefix($prefix)
    {
        $this->cachePrefix = $prefix;
        return $this;
    }
    
    /* ----------------------------------------------------- *\
     * Table update hijacks
     * ----------------------------------------------------- */
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function get($columns = ['*'])
    {
        return $this->getCached($columns);
    }
    
    public function delete($id = null)
    {
        $statement = parent::delete($id);
        $this->flushCache();
        return $statement;
    }
    
    public function update(array $values)
    {
        $this->flushCache();
        return parent::update($values);
    }
    
    public function insert(array $values)
    {
        $this->flushCache();
        return parent::insert($values);
    }
    public function insertGetId(array $values, $sequence = null)
    {
        $this->flushCache();
        return parent::insertGetId($values, $sequence);
    }
    
    public function truncate()
    {
        $this->flushCache();
        return parent::truncate();
    }
    
    public function getTags()
    {
        $tags = $this->cacheTags;
        $tags[] = $this->removePrefixFromTable($this->from);
        if($this->joins) {
            foreach ($this->joins as $join) {
                $tags[] = $join->table;
            }
        }
        return $tags;
    }
    
    protected function removePrefixFromTable($table)
    {
        return preg_replace('~^[^\.]*\.~', '', $table);
    }
}
