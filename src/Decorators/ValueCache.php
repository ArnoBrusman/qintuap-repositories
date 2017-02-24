<?php

namespace Advanza\Repositories\Decorators;


/**
 * @author Premiums
 */
abstract class ValueCache extends EloquentCache {
    
    public function getValue($valueName, $id = 0)
    {
        return $this->genericMethodCache(__FUNCTION__, func_get_args());
    }

    function storeValue($valueName, $value, $id = 0)
    {
        $this->cache->tags($this->tag)->flush();
        return $this->repository->storeValue($valueName, $value, $id);
    }
    function storeValues($valueName, $value, $id = 0)
    {
        $this->cache->tags($this->tag)->flush();
        return $this->repository->storeValues($valueName, $value, $id);
    }
}