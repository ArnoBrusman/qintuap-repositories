<?php

namespace Qintuap\Repositories;

use Closure;
use Qintuap\Repositories\Contracts\Repository as RepositoryContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Container\Container as App;
use Illuminate\Database\Eloquent\Model;
//use Qintuap\Models\Model;
use Qintuap\Repositories\Exceptions\RepositoryException;
use Qintuap\Scopes\Contracts\Scoped;
use Qintuap\Scopes\Scope;
use Qintuap\Scopes\Traits\HasScopes;
use Illuminate\Support\Collection;
use Qintuap\Repositories\Query\Builder as Query;
use Illuminate\Database\Eloquent\Builder as EloquentQuery;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Exception;

class EloquentRepository implements RepositoryContract
{
    
    /* @var $query Builder */
    /**
     * @var Model
     */
    protected $model;
    /**
     * @var Factory
     */
    protected $factory;
    protected $modelName;
    protected $keyByKey = true;

    public function __construct(Model $model, Factory $factory)
    {
        $this->modelName = get_class($model);
        $this->model = $model;
        $this->factory = $factory;
    }
    
    protected function prepCollecion($collection)
    {
        if($this->keyByKey) {
            $collection->keyBy($this->model->getKeyName());
        }
        return $collection;
    }
    
    protected function make($with = [])
    {
        $query = $this->model->with($with);
        return $this->_prepQuery($query);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws RepositoryException
     */
    public function newQuery()
    {
        $query = $this->model->newQuery();
        return $this->_prepQuery($query);
    }
    
    public function newRepo()
    {
        return new self($this->model);
    }
    
    public function getModelName()
    {
        return class_basename($this->model);
    }

    public function getModelClass()
    {
        return get_class($this->model);
    }
    
    public function getModelTable()
    {
        return $this->model->getTable();
    }
    
    public function cloneRepo()
    {
        return clone $this;
    }
    
    /**
     * Apply limit, order & scopes in query.
     * @param Builder $query
     * @return type
     */
    protected function _prepQuery($query)
    {
        return $query;   
    }
    
    /* ----------------------------------------------------- *\
     * Query Result functions
     * ----------------------------------------------------- */
    
    public function all($columns = ['*'])
    {
        $query = $this->newQuery();
        return $this->prepCollecion($query->get($columns));
    }
    
    public function allWhere($attribute, $value, $columns = ['*'])
    {
        return $this->prepCollecion($this->newQuery()->where($attribute, '=', $value)->get($columns));
    }
    
    public function getRelation(Model $model,$relationName, \Closure $callback = null)
    {
        if(method_exists($model, $relationName)) {
            $relationQuery = $model->$relationName();
            if($callback) {
                $relationQuery = $callback($relationQuery) ?: $relationQuery;
            }
            return $relationQuery->getResults();
        }
        throw new Exception('Relation ' . $relationName . ' doesn\'t exist');
    }
    
    public function hasRelation(Model $model,$relationName, \Closure $callback = null)
    {
        if(method_exists($model, $relationName)) {
            return $model->whereHas($relationName,$callback)->exists();
        }
        throw new Exception('Relation ' . $relationName . ' doesn\'t exist');
    }
    
    public function allWith($with)
    {
        $query = $this->make($with);

        return $this->prepCollecion($this->_prepQuery($query)->get());
    }
    
    public function push(Model $model)
    {
        $saved = $model->push();
        if(!$saved) {
            throw new Exception('model could not be saved.');
        }
        return $model;
    }
    
    public function paginate($perPage = 15, $columns = array('*'))
    {
        return $this->newQuery()->paginate($perPage, $columns);
    }
    
    public function first($columns = array('*'))
    {
        return $this->newQuery()->first($columns = array('*'));
    }
    
    public function find($id, $columns = array('*'))
    {
        return $this->newQuery()->find($id, $columns);
    }
    
    public function findBy($attribute, $value, $columns = array('*'))
    {
        return $this->findWhere($attribute, $value, $columns);
    }
    
    public function findWhere($attribute, $value, $columns = array('*'))
    {
        return $this->newQuery()->where($attribute, '=', $value)->first($columns);
    }
    
    public function findWith($id, $with)
    {
        $query = $this->make($with);

        return $this->_prepQuery($query)->find($id);
    }
    
    public function toSql()
    {
        return $this->newQuery()->toSql();
    }
    
    function findOfRelation($relationName,$relation) {
        return $this->scopeOfRelation($this->newQuery(), $relationName, $relation)->first();
    }
    
    // return an array with a distinct selection of the attribute.
    public function pick($attribute) {
        $query = $this->newQuery();
        return $query->select($attribute)->distinct()->get()->pick($attribute);
    }
    
    public function count()
    {
        return $this->newQuery()->count();
    }
    
    public function sum($column)
    {
        return $this->newQuery()->sum($column);
    }
    
    public function min($column)
    {
        return $this->newQuery()->min($column);
    }
    
    public function exists()
    {
        return $this->newQuery()->exists();
    }

    /* ----------------------------------------------------- *\
     * Database Edit Functions
     * ----------------------------------------------------- */

    public function create(array $data, $push = true)
    {
        $model = $this->model->newInstance();
        foreach ($data as $key => $value) {
            $model->$key = $value;
        }
        if($push) {
            return $this->push($model);
        } else {
            return $model;
        }
    }

    public function update($id, array $data = [])
    {
        $model = $this->makeModel($id);
        return $model->update($data);
    }
 
    public function delete($id)
    {
        $model = $this->makeModel($id);
        return $model->delete();
    }
    
    public function attach($id, $relation, $datas, $pivotData = [])
    {
        $model = $this->makeModel($id);
        $model->{$relation}()->attach($datas, $pivotData);
        unset($model->$relation);
    }
    
    public function sync($id, $relation, $datas, $detaching = false)
    {
        $model = $this->makeModel($id);
        $relationQuery = $model->{$relation}();
        if($relationQuery instanceof BelongsTo) {
            $relationQuery->associate($datas);
            $model = $this->push($model);
        } elseif($relationQuery instanceof BelongsToMany) {
            if($datas instanceof Model) {
                $datas = [$datas->getKey()];
            }
            $relationQuery->sync($datas,$detaching);
        }
        unset($model->$relation);
        return $model;
    }
    
    /* ----------------------------------------------------- *\
     * Overload Methods
     * ----------------------------------------------------- */
    public function __call($method, $parameters)
    {
        return $this->newQuery()->$method(...$parameters);
    }
    
    public function methodBuilderExists($method)
    {
        return method_exists(Builder::class, $method) || method_exists(QueryBuilder::class, $method);
    }
    
    /* ----------------------------------------------------- *\
     * Internal methods
     * ----------------------------------------------------- */
    
    /**
     * Syntax cleaning function.
     * @param Model $id
     * @return Model
     */
    function makeModel($id)
    {
        if($id instanceof Model) {
            $model = $id;
        } else {
            $model = $this->find($id);
        }
        return $model;
    }
    
    /**
     * Syntax cleaning function.
     * @param Model|int $model
     * @return int
     */
    function makeKey($model)
    {
        if($model instanceof Model) {
            $key = $model->getKey();
        } else {
            $key = $model;
        }
        return $key;
    }
    
    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->model = clone $this->model;
    }

    function __toString()
    {
        return class_basename($this);
    }
}
