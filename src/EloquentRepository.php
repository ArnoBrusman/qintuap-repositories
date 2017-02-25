<?php

namespace Qintuap\Repositories;

use Closure;
use Qintuap\Repositories\Contracts\Repository as RepositoryContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Container\Container as App;
use Illuminate\Database\Eloquent\Model;
use Qintuap\Repositories\Exceptions\RepositoryException;
use Qintuap\Scopes\Contracts\Scoped;
use Qintuap\Scopes\Scope;
use Qintuap\Scopes\Traits\HasScopes;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\Handler as Exception;

class EloquentRepository implements RepositoryContract, Scoped
{
    use \Qintuap\Scopes\Traits\HasScopes;
    
    /* @var $query Builder */
    /**
     * @var Model
     */
    protected $model;
    protected $modelName;

    public function __construct(Model $model)
    {
        $this->modelName = get_class($model);
        $this->model = $model;
    }
    
    public function all($columns = ['*'])
    {
        $query = $this->newQuery();
        return $query->get($columns);
    }
    
    public function allWhere($attribute, $value, $columns = ['*'])
    {
        return $this->newQuery()->where($attribute, '=', $value)->get($columns);
    }
    
    public function allWith($with)
    {
        $query = $this->make($with);

        return $this->_prepQuery($query)->get();
    }
    
    public function push(Model $model)
    {
        $saved = $model->save();
        if(!$saved) {
            throw new Exception('model could not be save.');
        }
        return $model;
    }
    
    public function paginate($perPage = 15, $columns = array('*'))
    {
        return $this->newQuery()->paginate($perPage, $columns);
    }

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
    
    public function attach($id, $relation, $datas, $data = [])
    {
        $model = $this->makeModel($id);
//        $relationId = is_array($data) ? $data['id'] : $data;
//        $data = is_array($data) ? $data : null;
        $model->{$relation}()->attach($datas, $data);
    }
    
    public function sync($id, $relation, $datas, $detaching = false)
    {
        $model = $this->makeModel($id);
        $relationQuery = $model->{$relation}();
        if($relationQuery instanceof BelongsTo) {
            $relationQuery->associate($datas);
            $model = $this->push($model);
        } elseif($relationQuery instanceof BelongsToMany) {
            $relationQuery->sync($datas,$detaching);
        }
        return $model;
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
        return $this->newQuery()->where($attribute, '=', $value)->first($columns);
    }
    
    public function findWith($id, $with)
    {
        $query = $this->make($with);

        return $this->_prepQuery($query)->find($id);
    }
    
    function findOfRelation($relationName,$relation) {
        return $this->scopeOfRelation($this->newQuery(), $relationName, $relation)->first();
    }
    
    public function random()
    {
        return $this->newQuery()->orderByRaw('RAND()')->first();
    }
    
    public function count()
    {
        return $this->newQuery()->get()->count();
    }
    
    public function exists($id)
    {
        return $this->newQuery()->find($id)->exists;
    }

    protected function make($with = [])
    {
        return $this->model->with($with);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws RepositoryException
     */
    public function newQuery()
    {
        return $this->_prepQuery($this->model->newQuery());
    }
    
    public function newRepo()
    {
        return new self($this->model);
    }
    
    public function getModelName()
    {
        return class_basename($this->model);
    }
    
    /**
     * Apply limit, order & scopes in query.
     * @param Builder $query
     * @return type
     */
    protected function _prepQuery($query)
    {
        if(isset($this->limit_max)) {
            $query->limit($this->limit_max);
        }
        return $this->applyOrder($query)
                ->applyScopes($query) ?: $query;   
    }

    /**
     * returns a new query without scopes or criteria.
     */
    public function newQueryWithoutScopes()
    {
        return $this->model->newQuery();
    }

    /* ----------------------------------------------------- *\
     * Default Scopes
     * ----------------------------------------------------- */
    
    public function scopeWhere($query,$attribute,$operator = null, $value = null)
    {
        return $query->where($attribute,$operator,$value);
    }
    
    /* ----------------------------------------------------- *\
     * Order method
     * ----------------------------------------------------- */
    
    protected $orders = [];

    public function orderBy($attr, $order = 'asc')
    {
        $this->orders[$attr] = $order;
        return $this;
    }
    
    public function getOrders()
    {
        return $this->orders;
    }
    
    protected function applyOrder($query)
    {
        foreach ($this->orders as $attr => $order) {
            $query->orderBy($attr, $order);
        }
        return $this;
    }
    
    /* ----------------------------------------------------- *\
     * Limit method
     * ----------------------------------------------------- */

    var $limit_max;
    function limit($max)
    {
        $this->limit_max = $max;
        return $this;
    }

    /* ----------------------------------------------------- *\
     * Overload Methods
     * ----------------------------------------------------- */
    
    public function __call($method, $parameters)
    {
        $scope = 'scope'.ucfirst($method);
        if (method_exists($this, $scope)) {
            return $this->pushCallableScope([$this, $scope], $parameters);
        } elseif (method_exists($this->model, $scope)) {
            return $this->pushCallableScope([$this->model, $scope], $parameters);
        }
        
        return call_user_func_array([$this->model, $method], $parameters);
    }
    
    static function __callStatic($name, $arguments)
    {
        return call_user_func_array([$this->model, $name], $arguments);
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

}
