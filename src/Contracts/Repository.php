<?php

namespace Qintuap\Repositories\Contracts;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
//use Qintuap\Models\Model;

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
     * @param array $push Push the created model to the database.
     * @return Model
     */
    public function create(array $data, $push = true);
 
    public function update($id, array $data = []);
 
    public function delete($id);
    
    public function exists();
 
    public function find($id, $columns = array('*'));
 
    public function findBy($field, $value, $columns = array('*'));
    
    public function first($columns);
    
}
