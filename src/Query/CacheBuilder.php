<?php

namespace Qintuap\Repositories\Query;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Query\Builder as BaseBuilder;

/**
 * @author Arno
 */
class CacheBuilder extends Builder {
    
    public function __construct(BaseBuilder $query)
    {
        $this->setQuery($query);
    }
    
    public function setQuery($query)
    {
        $this->query = new BaseCacheBuilder($query);
    }
    
}
