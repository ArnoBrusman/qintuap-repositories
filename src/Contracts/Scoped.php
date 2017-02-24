<?php

namespace Advanza\Repositories\Contracts;

use Closure;
use Advanza\Repositories\Scopes\Scope;

/**
 *
 * @author Premiums
 */
interface Scoped {

    function pushScope($scope, Closure $implementation = null);
    function pushScopes(array $scopes);
    function pushCallableScope($callable, array $parameters = []);
    function resetScope();
    function hasScope();
    
}
