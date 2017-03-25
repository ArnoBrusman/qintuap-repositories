<?php

namespace Qintuap\Repositories\Contracts;

use Qintuap\Repositories\Contracts\Repository;

/**
 *
 * @author Premiums
 */
interface Factory {

    /**
     * 
     * @param type $name
     * @return Repository Repository
     */
    function make($name);
    
}
