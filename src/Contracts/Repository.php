<?php

namespace Advanza\Repositories\Contracts;
use Advanza\Models\Contracts\Entity;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Description of Repository
 *
 * @author Premiums
 */
interface Repository {

    /**
     * @return Collection
     */
    public function all($columns = array('*'));
 
    /**
     * @return Paginator
     */
    public function paginate($perPage = 15, $columns = array('*'));
 
    /**
     * @param Model $model
     * @return Model
     */
    public function push(Model $model);
    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data, $push = true);
 
    public function update($id, array $data = []);
 
    public function delete($id);
    
    public function exists($id);
 
    /**
     * @return Entity
     */
    public function find($id, $columns = array('*'));
 
    /**
     * 
     * @param string $field
     * @param mixed $value
     * @param array $columns
     * @return Entity
     */
    public function findBy($field, $value, $columns = array('*'));
    
    public function first($columns);
    
}
