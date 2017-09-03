<?php

namespace Qintuap\Repositories;

use Qintuap\CacheDecorators\Factory as DecoFac;

/**
 * @author Arno
 */
class DecoratorFactory extends DecoFac {
    
    var $use_simple = false;
    
    protected function _getConcreteClass($namespace, $object, $name = null) {
        if(is_null($name)) {
            $name = class_basename($object);
            $name = preg_replace('/Repository$/', '', $name);
        }
        $class = $namespace . '\\' . $name . 'Cache';
        if(class_exists($class)) {
            return $class;
        } else {
            return false;
        }
    }
    
}
