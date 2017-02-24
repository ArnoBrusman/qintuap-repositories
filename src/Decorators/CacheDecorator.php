<?php

namespace Advanza\Repositories\Decorators;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Description of CacheDecorator
 *
 * @author Premiums
 */
interface CacheDecorator
{

    function cached($bool = true);
    
    /** generic methods **/
   

    function all($columns = ['*']);
    
    function find($id);

}
