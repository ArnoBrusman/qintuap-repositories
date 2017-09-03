<?php

namespace Qintuap\Repositories\Query;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Qintuap\Repositories\EloquentRepository as Repository;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Builder as EloquenceBuilder;

/**
 * Gotten from https://github.com/dwightwatson/rememberable/blob/master/src/Query/Builder.php
 * @author Arno
 */
class Builder extends EloquenceBuilder {
    
    /**
     * @var BaseBuilder $builder
     */
    protected $query;

    
    public function all($columns = ['*'])
    {
        return $this->get($columns);
    }
    
    public function random()
    {
        return $this->orderByRaw('RAND()')->first();
    }
    
    public function findBy($attribute, $value, $columns = array('*'))
    {
        return $this->findWhere($attribute, $value, $columns);
    }
    
    public function findWhere($attribute, $value, $columns = array('*'))
    {
        return $this->where($attribute, '=', $value)->first($columns);
    }
    
}
