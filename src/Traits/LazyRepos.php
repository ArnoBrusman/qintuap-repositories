<?php

namespace Qintuap\Repositories\Traits;

use Qintuap\Repositories\Repos;

/**
 * WARNING: sets the __get function. Currently doesn't preserve scope of lazy loaded properties.
 * Only build the dependant repository when they are called.
 *
 * @author Premiums
 */
trait LazyRepos {

    protected $lazyRepoAccessors = [];
    protected $lazyRepoOptions = [];
    protected $lazyPropertyAffix = 'Repo';

    protected function initLazyRepos(array $lazyRepos, $namespace = null)
    {
        if($namespace !== null) {
            Repos::addNamespace($namespace.'\\Repositories');
        }
        foreach ($lazyRepos as $lazyRepo) {

            $lazyProperty = lcfirst($lazyRepo) . $this->lazyPropertyAffix;
            // record the properties that were defined as "lazy"
            $this->lazyRepoAccessors[$lazyProperty] = false;
            $this->lazyRepoOptions[$lazyProperty] = [
                'namespace' => $namespace
            ];

            // if the property is defined, then ignore it (we don't want to sensibly alter object state)
            if (! isset($this->$lazyProperty)) {
                // unset the property, this allows us to use magic getters
                unset($this->$lazyProperty);
            } else {
                $this->lazyRepoAccessors[$lazyProperty] = true;
            }
        }
    }
    
    public function __get($lazyProperty)
    {
        // disallow access to non-existing properties
        if (!$this->isLazyRepo($lazyProperty) && is_callable('parent::__set')) {
            return parent::__get($lazyProperty);
        } elseif(! $this->isLazyRepo($lazyProperty)) {
            return null;
        }

        // set the property to `null` (disables notices)
        $this->$lazyProperty = null;

        // initialize the property
//         string
        if(method_exists($this, 'get' . ucfirst($lazyProperty))) {
            $this->$lazyProperty = $this->{'get' . ucfirst($lazyProperty)}();
        } else {
            $this->$lazyProperty = $this->getGenericRepository($lazyProperty);
            if(is_null($this->$lazyProperty)) {
                throw new \Exception('property '. $lazyProperty . ' does not properly convert to a repository.');
            }
        }

        $this->lazyRepoAccessors[$lazyProperty] = true;
        return $this->$lazyProperty;
    }
    
    public function isLazyRepo($key)
    {
        return isset($this->lazyRepoAccessors[$key]);
    }
    
    public function __set($lazyProperty, $repo)
    {
        // disallow access to non-existing properties
        if (! $this->isLazyRepo($lazyProperty) && is_callable('parent::__set')) {
            return parent::__set($lazyProperty, $repo);
        } else {
            // you'd think __set would call itself, but it doesn't
            $this->$lazyProperty = $repo;
        }
    }
    
    
    protected function getGenericRepository($lazyProperty)
    {
        $namespace = $this->lazyRepoOptions[$lazyProperty]['namespace'];
        $repoName = preg_replace('/'.ucfirst($this->lazyPropertyAffix).'$/', '', $lazyProperty);
        return Repos::make($repoName);
    }
    
    /**
     * reset the scopes of the repos that have been build
     */
    function resetRepos()
    {
        foreach ($this->lazyRepoAccessors as $lazyRepo => $active) {
            if($active) {
                $this->$lazyRepo->resetScope();
            }
        }
    }
    
    /**
     * re-initialize the repositories on wakeup.
     */
    public function __wakeup()
    {
        $lazyRepos = [];
        foreach ($this->lazyRepoAccessors as $lazyRepo => $active) {
            $repoName = preg_replace('/'.ucfirst($this->lazyPropertyAffix).'$/', '', $lazyRepo);
            $lazyRepos[] = $repoName;
        }
        $this->initLazyRepos($lazyRepos);
        if(is_callable('parent::__wakeup')) {
            parent::__wakeup();
        };
    }
}
