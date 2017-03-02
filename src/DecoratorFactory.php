<?php

namespace Qintuap\Repositories;

use Qintuap\CacheDecorators\Factory as DecoFac;

/**
 * @author Premiums
 */
class DecoratorFactory extends DecoFac {
    
    protected function getConcreteClass($namespace,$object) {
        if(is_array($namespace)) {
            foreach ($namespace as $v) {
                $class = $this->getConcreteClass($v,$object);
                if($class) {
                    return $class;
                }
            }
        } else {
            $name = class_basename($object);
            $name = preg_replace('/Repository$/', '', $name);
            $class = $namespace . '\\' . $name . 'Cache';
            \Debugbar::addMessage($class, 'info');
            if(class_exists($class)) {
                return $class;
            } else {
                return false;
            }
        }
    }
    
}
